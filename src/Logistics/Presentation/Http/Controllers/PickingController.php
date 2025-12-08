<?php

namespace TmrEcosystem\Logistics\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderItemModel;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Stock\Application\Services\StockPickingService;
use TmrEcosystem\Stock\Domain\Exceptions\InsufficientStockException;

class PickingController extends Controller
{
    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService
    ) {}

    // ... (index method เหมือนเดิม) ...
    public function index(Request $request)
    {
        // (Code เดิม...)
        $query = PickingSlip::query()
            ->with(['order.items'])
            ->join('sales_orders', 'sales_picking_slips.order_id', '=', 'sales_orders.id')
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.id')
            ->leftJoin('users', 'sales_picking_slips.picker_user_id', '=', 'users.id')
            ->select(
                'sales_picking_slips.*',
                'sales_orders.order_number',
                'customers.name as customer_name',
                'users.name as picker_name'
            );

        if ($request->filled('status') && $request->status !== 'all') {
            $query->where('sales_picking_slips.status', $request->status);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('sales_picking_slips.picking_number', 'like', "%{$request->search}%")
                    ->orWhere('sales_orders.order_number', 'like', "%{$request->search}%")
                    ->orWhere('customers.name', 'like', "%{$request->search}%")
                    ->orWhere('users.name', 'like', "%{$request->search}%");
            });
        }

        $pickingSlips = $query->orderByRaw("CASE WHEN sales_picking_slips.status = 'pending' THEN 1 WHEN sales_picking_slips.status = 'assigned' THEN 2 ELSE 3 END")
            ->orderBy('sales_picking_slips.created_at', 'desc')
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

        // 1. ตรวจสอบ Warehouse
        $warehouseUuid = $pickingSlip->order->warehouse_id ?? $pickingSlip->warehouse_id;
        if (!$warehouseUuid || $warehouseUuid === 'Main-WH') {
             // Fallback: หา Warehouse แรกของบริษัท
             $warehouseUuid = \TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel::where('company_id', $pickingSlip->company_id)->value('uuid');
        }

        $items = $pickingSlip->items->map(function ($pickItem) use ($pickingSlip, $warehouseUuid) {
            $itemDto = $this->itemLookupService->findByPartNumber($pickItem->product_id);
            $suggestions = [];

            // 2. ✅ [Smart Logic] ดึงข้อมูล "สินค้าถูกจองไว้ที่ไหน?" (Where is it reserved?)
            if ($itemDto && $pickingSlip->status !== 'done' && $warehouseUuid) {
                // เรียกใช้ Repo เพื่อหา Reservation จริงที่มีอยู่
                $reservedStocks = $this->stockRepo->findWithSoftReserve($itemDto->uuid, $warehouseUuid);

                foreach ($reservedStocks as $stock) {
                    $locationCode = DB::table('warehouse_storage_locations')
                        ->where('uuid', $stock->getLocationUuid())
                        ->value('code');

                    if ($stock->getQuantitySoftReserved() > 0) {
                        $suggestions[] = [
                            'location_uuid' => $stock->getLocationUuid(),
                            'location_code' => $locationCode ?? 'UNKNOWN',
                            'quantity' => $stock->getQuantitySoftReserved()
                        ];
                    }
                }
            }

            // Flatten location string for display (Support Legacy UI)
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

                // ✅ ส่งข้อมูล Suggestion ไป Frontend
                'picking_suggestions' => $suggestions,
                // ✅ ส่ง String ไปเผื่อใช้แสดงผลง่ายๆ
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

            // หา Warehouse Context
            $warehouseUuid = $picking->order->warehouse_id ?? $picking->warehouse_id;
            if (!$warehouseUuid || $warehouseUuid === 'Main-WH') {
                 $warehouseUuid = \TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel::where('company_id', $picking->company_id)->value('uuid');
            }

            foreach ($picking->items as $item) {
                $submitted = $submittedItems->firstWhere('id', $item->id);
                $qtyPickedActual = $submitted ? (float)$submitted['qty_picked'] : 0;

                // --- ✅ FIX: เปลี่ยน Logic การตัดสต็อกให้รองรับ Location (Smart Commit) ---
                if ($qtyPickedActual > 0) {
                    $inventoryItemDto = $this->itemLookupService->findByPartNumber($item->product_id);

                    if ($inventoryItemDto && $warehouseUuid) {
                        // 1. ค้นหา Stock Level ที่มี Soft Reserve (ที่เราจองไว้ตอน CreateLogisticsDocuments)
                        $reservedStocks = $this->stockRepo->findWithSoftReserve($inventoryItemDto->uuid, $warehouseUuid);

                        $qtyToCommit = $qtyPickedActual;

                        foreach ($reservedStocks as $stockLevel) {
                            if ($qtyToCommit <= 0) break;

                            // ตัดสต็อกเท่าที่มีจองไว้ หรือเท่าที่หยิบจริง
                            $amount = min($qtyToCommit, $stockLevel->getQuantitySoftReserved());

                            if ($amount > 0) {
                                // ฟังก์ชันนี้จะเปลี่ยน Soft Reserve -> Out (ลด On Hand)
                                $stockLevel->commitReservation($amount);
                                $this->stockRepo->save($stockLevel, []);
                                $qtyToCommit -= $amount;
                            }
                        }

                        // Note: ถ้า $qtyToCommit ยังเหลือ แสดงว่ามีการหยิบเกินที่จอง (Over-pick)
                        // ในอนาคตอาจต้องเพิ่ม Logic ตัดจาก Available Stock ที่อื่นเพิ่ม
                    }
                }

                // 2. Release Unused Reservation (คืนยอดจองส่วนเกิน ถ้าหยิบไม่ครบ)
                $qtyUnpicked = $item->quantity_requested - $qtyPickedActual;
                if ($qtyUnpicked > 0) {
                     $inventoryItemDto = $this->itemLookupService->findByPartNumber($item->product_id);
                     if ($inventoryItemDto && $warehouseUuid) {
                        $reservedStocks = $this->stockRepo->findWithSoftReserve($inventoryItemDto->uuid, $warehouseUuid);
                        $releaseRemaining = $qtyUnpicked;
                        foreach ($reservedStocks as $stockLevel) {
                             if ($releaseRemaining <= 0) break;
                             $releaseAmt = min($releaseRemaining, $stockLevel->getQuantitySoftReserved());
                             $stockLevel->releaseSoftReservation($releaseAmt); // คืนยอดจองกลับเป็น OnHand ปกติ
                             $this->stockRepo->save($stockLevel, []);
                             $releaseRemaining -= $releaseAmt;
                        }
                     }
                }
                // -----------------------------------------------------------

                // Update Picking Item Data
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

            if (!empty($backorderItems) && $request->create_backorder) {
                // (Logic Backorder เหมือนเดิม)
                $newPicking = PickingSlip::create([
                    'picking_number' => 'PK-' . time() . '-BO',
                    'order_id' => $picking->order_id,
                    'status' => 'pending',
                    'note' => 'Backorder from ' . $picking->picking_number
                ]);
                $newPicking->items()->createMany($backorderItems);
                DeliveryNote::create([
                    'delivery_number' => 'DO-' . time() . '-BO',
                    'order_id' => $picking->order_id,
                    'picking_slip_id' => $newPicking->id,
                    'shipping_address' => 'Same as original',
                    'status' => 'wait_operation',
                ]);
            }
        });

        return to_route('logistics.picking.index')->with('success', 'Picking Validated!');
    }

    public function assign(Request $request, string $id)
    {
        // (Code เดิม...)
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

    /**
     * เมธอดสำหรับแสดงหน้า Picking Slip (Show.tsx)
     * หรือหน้าที่ Picker เข้ามาดูก่อนกด Continue Picking
     */
    public function reViewItem(string $id)
    {
        $pickingSlip = PickingSlip::with(['items', 'order.customer', 'picker'])->findOrFail($id);

        // 1. หา Warehouse ID ให้ชัวร์ (เหมือนเดิม)
        $warehouseUuid = $pickingSlip->order->warehouse_id ?? 'Main-WH';
        if ($warehouseUuid === 'Main-WH' || !$warehouseUuid) {
             $warehouseUuid = \TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel::where('company_id', $pickingSlip->order->company_id)->value('uuid');
        }

        $items = $pickingSlip->items->map(function ($pickItem) use ($pickingSlip, $warehouseUuid) {
            $itemDto = $this->itemLookupService->findByPartNumber($pickItem->product_id);

            // 2. ✅ [Smart Picking Logic] ใช้ Logic เดียวกับ show() เพื่อความ Consistent
            $suggestions = [];
            if ($itemDto && $pickingSlip->status !== 'done' && $warehouseUuid) {
                 // ดึงจาก Reservation จริง
                 $reservedStocks = $this->stockRepo->findWithSoftReserve($itemDto->uuid, $warehouseUuid);
                 foreach ($reservedStocks as $stock) {
                    $locationCode = DB::table('warehouse_storage_locations')
                        ->where('uuid', $stock->getLocationUuid())
                        ->value('code');

                    if ($stock->getQuantitySoftReserved() > 0) {
                        $suggestions[] = [
                            'location_uuid' => $stock->getLocationUuid(),
                            'location_code' => $locationCode ?? 'UNKNOWN',
                            'quantity' => $stock->getQuantitySoftReserved()
                        ];
                    }
                }
            }

            // 3. ✅ แปลง Suggestion เป็น String สำหรับแสดงผลในตาราง
            $locationStr = collect($suggestions)
                ->map(fn($s) => "{$s['location_code']}") // เอาแค่ Code ก็พอ
                ->unique()
                ->join(', ');

            return [
                'id' => $pickItem->id,
                'product_id' => $pickItem->product_id,
                'product_name' => $itemDto ? $itemDto->name : $pickItem->product_id,
                'description' => $itemDto->description ?? '', // เพิ่ม Description
                'barcode' => $itemDto ? $itemDto->partNumber : '',

                // ✅ ใช้ค่าจริงที่คำนวณได้ (ถ้าไม่มีให้ขึ้น Waiting หรือ N/A)
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
                'warehouse_id' => $warehouseUuid, // ส่ง Warehouse ID จริงไป
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
