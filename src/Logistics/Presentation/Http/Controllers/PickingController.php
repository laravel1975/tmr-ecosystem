<?php

namespace TmrEcosystem\Logistics\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderItemModel;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Logistics\Application\UseCases\GeneratePickingSuggestionsUseCase;
use TmrEcosystem\Stock\Application\Services\StockPickingService;
use TmrEcosystem\Stock\Domain\Exceptions\InsufficientStockException;

class PickingController extends Controller
{
    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService
    ) {}

    public function index(Request $request)
    {
        $query = PickingSlip::query()
            ->with(['order.items'])
            // ✅ แก้ไข: เปลี่ยน sales_picking_slips เป็น logistics_picking_slips
            ->join('sales_orders', 'logistics_picking_slips.order_id', '=', 'sales_orders.id')
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'logistics_picking_slips.picker_user_id', '=', 'users.id')
            ->select(
                'logistics_picking_slips.*',
                'sales_orders.order_number',
                'customers.name as customer_name',
                'users.name as picker_name'
            );

        if ($request->filled('status') && $request->status !== 'all') {
            // ✅ แก้ไข
            $query->where('logistics_picking_slips.status', $request->status);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                // ✅ แก้ไข
                $q->where('logistics_picking_slips.picking_number', 'like', "%{$request->search}%")
                    ->orWhere('sales_orders.order_number', 'like', "%{$request->search}%")
                    ->orWhere('customers.name', 'like', "%{$request->search}%")
                    ->orWhere('users.name', 'like', "%{$request->search}%");
            });
        }

        // ✅ แก้ไข Order By
        $pickingSlips = $query->orderByRaw("CASE WHEN logistics_picking_slips.status = 'pending' THEN 1 WHEN logistics_picking_slips.status = 'assigned' THEN 2 ELSE 3 END")
            ->orderBy('logistics_picking_slips.created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($slip) => [
                'id' => $slip->id,
                'picking_number' => $slip->picking_number,
                'order_number' => $slip->order_number,
                'customer_name' => $slip->customer_name ?? 'Unknown',
                'items_count' => $slip->order ? $slip->order->items->sum('quantity') : 0,
                'status' => $slip->status,
                'created_at' => $slip->created_at->format('d/m/Y H:i'),
                'picker_name' => $slip->picker_name,
                'picker_user_id' => $slip->picker_user_id,
            ]);

        $stats = [
            'total_pending' => PickingSlip::where('status', 'pending')->count(),
            'my_tasks' => PickingSlip::where('picker_user_id', auth()->id())
                ->whereIn('status', ['assigned', 'in_progress'])
                ->count(),
        ];

        return Inertia::render('Logistics/Picking/Index', [
            'pickingSlips' => $pickingSlips,
            'filters' => $request->only(['search', 'status']),
            'stats' => $stats,
        ]);
    }

    public function show(string $id)
    {
        $pickingSlip = PickingSlip::with(['items', 'order.customer'])->findOrFail($id);

        $warehouseUuid = $pickingSlip->order->warehouse_id ?? $pickingSlip->warehouse_id;
        if (!$warehouseUuid || $warehouseUuid === 'Main-WH') {
             $warehouseUuid = \TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel::where('company_id', $pickingSlip->company_id)->value('uuid');
        }

        $items = $pickingSlip->items->map(function ($pickItem) use ($pickingSlip, $warehouseUuid) {
            $itemDto = $this->itemLookupService->findByPartNumber($pickItem->product_id);
            $suggestions = [];

            if ($itemDto && $pickingSlip->status !== 'done' && $warehouseUuid) {
                // ดึง Stock Level ทั้งหมดที่มีการจอง (ยอดรวม Global ของทุกออร์เดอร์)
                $reservedStocks = $this->stockRepo->findWithSoftReserve($itemDto->uuid, $warehouseUuid);

                // ✅ [แก้ไข 1] ตั้งต้นยอดที่ต้องหา สำหรับ "Picking Slip ใบนี้เท่านั้น"
                $remainingToFind = $pickItem->quantity_requested - $pickItem->quantity_picked;

                foreach ($reservedStocks as $stock) {
                    if ($remainingToFind <= 0) break; // ถ้าครบยอดของใบนี้แล้ว หยุดทันที (ไม่สนว่า Global จะเหลือเท่าไหร่)

                    $reservedGlobal = $stock->getQuantitySoftReserved(); // ยอดจองรวมใน DB (เช่น 35)

                    if ($reservedGlobal > 0) {
                        // ✅ [แก้ไข 2] ตัดยอดแสดงผล: เอาค่าน้อยสุด เพื่อไม่ให้ตัวเลขเกินความต้องการของใบนี้
                        // เช่น มี 35 แต่ใบนี้ขอ 20 -> โชว์ 20
                        $showQty = min($reservedGlobal, $remainingToFind);

                        // จัด Format ตัวเลข
                        $formattedQty = number_format($showQty, 2);
                        if (floor($showQty) == $showQty) {
                             $formattedQty = number_format($showQty, 0);
                        }

                        $locationCode = DB::table('warehouse_storage_locations')
                            ->where('uuid', $stock->getLocationUuid())
                            ->value('code');

                        $suggestions[] = [
                            'location_uuid' => $stock->getLocationUuid(),
                            'location_code' => $locationCode ?? 'UNKNOWN',
                            'quantity' => $formattedQty
                        ];

                        // ✅ [แก้ไข 3] หักยอดที่หาได้ออกจากโควตาของใบนี้
                        $remainingToFind -= $showQty;
                    }
                }
            }

            $locationString = collect($suggestions)
                ->map(fn($s) => "{$s['location_code']} ({$s['quantity']})")
                ->join(', ');

            return [
                'id' => $pickItem->id,
                'product_id' => $pickItem->product_id,
                'product_name' => $itemDto ? $itemDto->name : $pickItem->product_id,
                'barcode' => $itemDto ? $itemDto->partNumber : '',
                'qty_ordered' => $pickItem->quantity_requested,
                'qty_picked' => $pickItem->quantity_picked,
                'is_completed' => $pickingSlip->status === 'done' || ($pickItem->quantity_picked >= $pickItem->quantity_requested),
                'image_url' => $itemDto ? $itemDto->imageUrl : null,
                'picking_suggestions' => $suggestions,
                'location_display' => $locationString ?: 'WAITING STOCK'
            ];
        });

        return Inertia::render('Logistics/Picking/Process', [
            'pickingSlip' => [
                'id' => $pickingSlip->id,
                'picking_number' => $pickingSlip->picking_number,
                'order_number' => $pickingSlip->order->order_number,
                'customer_name' => $pickingSlip->order->customer->name ?? 'N/A',
                'status' => $pickingSlip->status,
            ],
            'items' => $items
        ]);
    }

    /**
     * Action: สร้างรายการแนะนำการหยิบสินค้า (Allocated Picking List)
     * URL: POST /logistics/picking/{id}/generate-plan
     */
    public function generatePlan(Request $request, string $id, GeneratePickingSuggestionsUseCase $useCase)
    {
        try {
            // ✅ ต้องเรียก method 'handle' ซึ่งเป็น Entry Point มาตรฐานของ Use Case
            $useCase->handle($id);

            return back()->with('success', 'Picking plan generated successfully. Items allocated to locations.');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to generate plan: ' . $e->getMessage());
        }
    }

    public function confirm(Request $request, string $id)
    {
        $request->validate([
            'items' => 'required|array',
            'create_backorder' => 'boolean'
        ]);

        DB::transaction(function () use ($id, $request) {
            $picking = PickingSlip::with('items')->findOrFail($id);
            $submittedItems = collect($request->items);
            $backorderItems = [];

            $warehouseUuid = $picking->order->warehouse_id ?? $picking->warehouse_id;
            if (!$warehouseUuid || $warehouseUuid === 'Main-WH') {
                 $warehouseUuid = \TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel::where('company_id', $picking->company_id)->value('uuid');
            }

            foreach ($picking->items as $item) {
                $submitted = $submittedItems->firstWhere('id', $item->id);
                $qtyPickedActual = $submitted ? (float)$submitted['qty_picked'] : 0;

                // 1. Commit Stock (Hard Reserve) สำหรับยอดที่หยิบได้
                if ($qtyPickedActual > 0) {
                    $inventoryItemDto = $this->itemLookupService->findByPartNumber($item->product_id);

                    if ($inventoryItemDto && $warehouseUuid) {
                        $reservedStocks = $this->stockRepo->findWithSoftReserve($inventoryItemDto->uuid, $warehouseUuid);
                        $qtyToCommit = $qtyPickedActual;

                        foreach ($reservedStocks as $stockLevel) {
                            if ($qtyToCommit <= 0) break;
                            $amount = min($qtyToCommit, $stockLevel->getQuantitySoftReserved());

                            if ($amount > 0) {
                                $stockLevel->commitReservation($amount);
                                $this->stockRepo->save($stockLevel, []);
                                $qtyToCommit -= $amount;
                            }
                        }
                    }
                }

                // 2. Release Stock (คืนสต๊อกจอง) สำหรับยอดที่หยิบไม่ได้ (เพื่อเตรียมสร้าง Backorder ใหม่ หรือปล่อยให้คนอื่น)
                $qtyUnpicked = $item->quantity_requested - $qtyPickedActual;
                if ($qtyUnpicked > 0) {
                      $inventoryItemDto = $this->itemLookupService->findByPartNumber($item->product_id);
                      if ($inventoryItemDto && $warehouseUuid) {
                        $reservedStocks = $this->stockRepo->findWithSoftReserve($inventoryItemDto->uuid, $warehouseUuid);
                        $releaseRemaining = $qtyUnpicked;
                        foreach ($reservedStocks as $stockLevel) {
                             if ($releaseRemaining <= 0) break;
                             $releaseAmt = min($releaseRemaining, $stockLevel->getQuantitySoftReserved());
                             $stockLevel->releaseSoftReservation($releaseAmt);
                             $this->stockRepo->save($stockLevel, []);
                             $releaseRemaining -= $releaseAmt;
                        }
                      }
                }

                $item->update(['quantity_picked' => $qtyPickedActual]);

                $salesItem = SalesOrderItemModel::find($item->sales_order_item_id);
                if ($salesItem) {
                    $salesItem->increment('qty_shipped', $qtyPickedActual);
                }

                $remaining = $item->quantity_requested - $qtyPickedActual;
                if ($remaining > 0) {
                    $backorderItems[] = [
                        'sales_order_item_id' => $item->sales_order_item_id,
                        'product_id' => $item->product_id,
                        'quantity_requested' => $remaining,
                        'quantity_picked' => 0
                    ];
                }
            }

            $picking->update(['status' => 'done', 'picked_at' => now()]);
            DeliveryNote::where('picking_slip_id', $id)->update(['status' => 'ready_to_ship']);

            // 3. จัดการ Backorder (สร้างใบใหม่ + จองสต๊อกกลับเข้าไปทันที)
            if (!empty($backorderItems) && $request->create_backorder) {
                $newPicking = PickingSlip::create([
                    'picking_number' => 'PK-' . time() . '-BO',
                    'company_id' => $picking->company_id,
                    'order_id' => $picking->order_id,
                    'status' => 'pending',
                    'note' => 'Backorder from ' . $picking->picking_number
                ]);
                $newPicking->items()->createMany($backorderItems);

                DeliveryNote::create([
                    'delivery_number' => 'DO-' . time() . '-BO',
                    'company_id' => $picking->company_id,
                    'order_id' => $picking->order_id,
                    'picking_slip_id' => $newPicking->id,
                    'shipping_address' => $picking->order->shipping_address ?? 'Same as original',
                    'status' => 'wait_operation',
                ]);

                // ✅ [ADDED] Auto-Reserve Stock for Backorder Items
                // ทำการจองสต๊อก (Soft Reserve) ให้กับใบ Backorder ทันที เพื่อให้ Suggestion Logic มองเห็นยอด
                foreach ($backorderItems as $boItem) {
                    $qtyNeeded = $boItem['quantity_requested'];
                    $inventoryItemDto = $this->itemLookupService->findByPartNumber($boItem['product_id']);

                    if ($inventoryItemDto && $warehouseUuid) {
                        try {
                            // คำนวณแผนการหยิบใหม่ (หา Location ที่มีของว่าง หรือจะใช้ Location เดิมก็ได้)
                            $plan = $this->pickingService->calculatePickingPlan(
                                $inventoryItemDto->uuid,
                                $warehouseUuid,
                                (float) $qtyNeeded
                            );

                            foreach ($plan as $step) {
                                if ($step['quantity'] <= 0) continue;

                                $stockLevel = $this->stockRepo->findByLocation(
                                    $inventoryItemDto->uuid,
                                    $step['location_uuid'],
                                    $picking->company_id
                                );

                                if ($stockLevel) {
                                    // จองกลับเข้าไปใหม่
                                    $stockLevel->reserveSoft($step['quantity']);
                                    $this->stockRepo->save($stockLevel, []);
                                }
                            }
                        } catch (\Exception $e) {
                            // ถ้าจองไม่ได้ (เช่น ของหมดเกลี้ยงจริงๆ) ก็ปล่อยผ่านไป
                            Log::warning("Backorder Auto-Reserve Failed for {$boItem['product_id']}: " . $e->getMessage());
                        }
                    }
                }
            }
        });

        return to_route('logistics.picking.index')->with('success', 'Picking Validated!');
    }

    public function assign(Request $request, string $id)
    {
        $picking = PickingSlip::findOrFail($id);
        if ($picking->status !== 'pending' && $picking->status !== 'assigned') {
            return back()->with('error', 'ไม่สามารถรับงานนี้ได้ (สถานะไม่ถูกต้อง)');
        }
        if ($picking->picker_user_id && $picking->picker_user_id !== auth()->id()) {
            return back()->with('error', 'งานนี้มีผู้รับผิดชอบแล้ว');
        }
        $picking->update([
            'picker_user_id' => auth()->id(),
            'status' => 'assigned'
        ]);
        return back()->with('success', 'รับงานเรียบร้อยแล้ว เริ่มจัดของได้เลย!');
    }

    public function reViewItem(string $id)
    {
        $pickingSlip = PickingSlip::with(['items', 'order.customer', 'picker'])->findOrFail($id);

        $warehouseUuid = $pickingSlip->order->warehouse_id ?? 'Main-WH';
        if ($warehouseUuid === 'Main-WH' || !$warehouseUuid) {
             $warehouseUuid = \TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel::where('company_id', $pickingSlip->order->company_id)->value('uuid');
        }

        $items = $pickingSlip->items->map(function ($pickItem) use ($pickingSlip, $warehouseUuid) {
            $itemDto = $this->itemLookupService->findByPartNumber($pickItem->product_id);

            $suggestions = [];
            if ($itemDto && $pickingSlip->status !== 'done' && $warehouseUuid) {
                 $reservedStocks = $this->stockRepo->findWithSoftReserve($itemDto->uuid, $warehouseUuid);

                 // ✅ แก้ไข: เพิ่ม Logic การคำนวณเหมือน method show
                 $remainingToFind = $pickItem->quantity_requested;

                 foreach ($reservedStocks as $stock) {
                    if ($remainingToFind <= 0) break;

                    $reservedInLoc = $stock->getQuantitySoftReserved();

                    if ($reservedInLoc > 0) {
                        $showQty = min($reservedInLoc, $remainingToFind);

                        $locationCode = DB::table('warehouse_storage_locations')
                            ->where('uuid', $stock->getLocationUuid())
                            ->value('code');

                        $suggestions[] = [
                            'location_uuid' => $stock->getLocationUuid(),
                            'location_code' => $locationCode ?? 'UNKNOWN',
                            'quantity' => $showQty
                        ];

                        $remainingToFind -= $showQty;
                    }
                }
            }

            $locationStr = collect($suggestions)
                ->map(fn($s) => "{$s['location_code']} ({$s['quantity']})") // เพิ่มการแสดงจำนวนใน String ด้วยเพื่อความชัดเจน
                ->unique()
                ->join(', ');

            return [
                'id' => $pickItem->id,
                'product_id' => $pickItem->product_id,
                'product_name' => $itemDto ? $itemDto->name : $pickItem->product_id,
                'description' => $itemDto->description ?? '',
                'barcode' => $itemDto ? $itemDto->partNumber : '',
                'location' => $locationStr ?: 'WAITING',
                'qty_ordered' => $pickItem->quantity_requested,
                'qty_picked' => $pickItem->quantity_picked,
                'is_completed' => false,
                'image_url' => $itemDto ? $itemDto->imageUrl : null,
            ];
        });

        return Inertia::render('Logistics/Picking/Show', [
            'pickingSlip' => [
                'id' => $pickingSlip->id,
                'picking_number' => $pickingSlip->picking_number,
                'status' => $pickingSlip->status,
                'created_at' => $pickingSlip->created_at->toIso8601String(),
                'warehouse_id' => $warehouseUuid,
                'picker_name' => $pickingSlip->picker ? $pickingSlip->picker->name : null,
                'order' => [
                    'order_number' => $pickingSlip->order->order_number,
                    'customer' => [
                        'name' => $pickingSlip->order->customer->name ?? 'N/A',
                        'address' => $pickingSlip->order->customer->address ?? '-',
                        'phone' => $pickingSlip->order->customer->phone ?? '-',
                    ]
                ],
                'items' => $items
            ]
        ]);
    }
}
