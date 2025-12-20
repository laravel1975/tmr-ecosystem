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

class PickingController extends Controller
{
    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService
    ) {}

    public function index(Request $request)
    {
        // ✅ REFACTOR: ใช้ Join เพื่อดึงข้อมูล Read Model และลบ with(['order']) ออก
        $query = PickingSlip::query()
            ->withSum('items as total_items_to_pick', 'quantity_requested') // นับจำนวนจาก Picking Slip Items โดยตรง
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
            $query->where('logistics_picking_slips.status', $request->status);
        }

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('logistics_picking_slips.picking_number', 'like', "%{$request->search}%")
                    ->orWhere('sales_orders.order_number', 'like', "%{$request->search}%")
                    ->orWhere('customers.name', 'like', "%{$request->search}%")
                    ->orWhere('users.name', 'like', "%{$request->search}%");
            });
        }

        $pickingSlips = $query->orderByRaw("CASE WHEN logistics_picking_slips.status = 'pending' THEN 1 WHEN logistics_picking_slips.status = 'assigned' THEN 2 ELSE 3 END")
            ->orderBy('logistics_picking_slips.created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($slip) => [
                'id' => $slip->id,
                'picking_number' => $slip->picking_number,
                'order_number' => $slip->order_number, // ใช้ค่าจาก select
                'customer_name' => $slip->customer_name ?? 'Unknown',
                'items_count' => $slip->total_items_to_pick ?? 0, // ใช้ค่าจาก withSum
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
        // ✅ REFACTOR: ลบ 'order.customer' ออกจาก with
        $pickingSlip = PickingSlip::with(['items'])->findOrFail($id);

        // ดึงข้อมูล Context Sales แบบ Read-Only ผ่าน DB Facade (ไม่ใช้ Model เพื่อลด Coupling)
        $orderContext = DB::table('sales_orders')
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.id')
            ->where('sales_orders.id', $pickingSlip->order_id)
            ->select(
                'sales_orders.order_number',
                'sales_orders.warehouse_id',
                'sales_orders.company_id',
                'customers.name as customer_name',
                'customers.address as customer_address',
                'customers.phone as customer_phone'
            )
            ->first();

        // Fallback หา Warehouse UUID
        $warehouseUuid = $orderContext->warehouse_id ?? $pickingSlip->warehouse_id;
        if (!$warehouseUuid || $warehouseUuid === 'Main-WH') {
            $warehouseUuid = \TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel::where('company_id', $pickingSlip->company_id)->value('uuid');
        }

        $items = $pickingSlip->items->map(function ($pickItem) use ($pickingSlip, $warehouseUuid) {
            $itemDto = $this->itemLookupService->findByPartNumber($pickItem->product_id);
            $suggestions = [];

            if ($itemDto && $pickingSlip->status !== 'done' && $warehouseUuid) {
                $reservedStocks = $this->stockRepo->findWithSoftReserve($itemDto->uuid, $warehouseUuid);
                $remainingToFind = $pickItem->quantity_requested - $pickItem->quantity_picked;

                foreach ($reservedStocks as $stock) {
                    if ($remainingToFind <= 0) break;
                    $reservedGlobal = $stock->getQuantitySoftReserved();

                    if ($reservedGlobal > 0) {
                        $showQty = min($reservedGlobal, $remainingToFind);
                        $formattedQty = number_format($showQty, (floor($showQty) == $showQty ? 0 : 2));

                        $locationCode = DB::table('warehouse_storage_locations')
                            ->where('uuid', $stock->getLocationUuid())
                            ->value('code');

                        $suggestions[] = [
                            'location_uuid' => $stock->getLocationUuid(),
                            'location_code' => $locationCode ?? 'UNKNOWN',
                            'quantity' => $formattedQty
                        ];
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
                'quantity' => $pickItem->quantity_requested,
                'quantity_requested' => $pickItem->quantity_requested,
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
                'order_number' => $orderContext->order_number ?? 'N/A',
                'customer_name' => $orderContext->customer_name ?? 'N/A',
                'status' => $pickingSlip->status,
            ],
            'items' => $items
        ]);
    }

    public function generatePlan(Request $request, string $id, GeneratePickingSuggestionsUseCase $useCase)
    {
        try {
            $useCase->handle($id);
            return back()->with('success', 'Picking plan generated successfully.');
        } catch (Exception $e) {
            return back()->with('error', 'Failed to generate plan: ' . $e->getMessage());
        }
    }

    public function confirm(Request $request, string $id)
    {
        $request->validate(['items' => 'required|array', 'create_backorder' => 'boolean']);

        DB::transaction(function () use ($id, $request) {
            $picking = PickingSlip::with('items')->findOrFail($id);
            $submittedItems = collect($request->items);
            $backorderItems = [];

            // Helper to get Order Context quickly
            $orderContext = DB::table('sales_orders')->select('warehouse_id', 'shipping_address')->where('id', $picking->order_id)->first();

            $warehouseUuid = $orderContext->warehouse_id ?? $picking->warehouse_id;
            if (!$warehouseUuid || $warehouseUuid === 'Main-WH') {
                $warehouseUuid = \TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel::where('company_id', $picking->company_id)->value('uuid');
            }

            foreach ($picking->items as $item) {
                $submitted = $submittedItems->firstWhere('id', $item->id);
                $qtyPickedActual = $submitted ? (float)$submitted['qty_picked'] : 0;

                // 1. Commit Stock logic
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

                // 2. Release Unpicked Stock logic
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

                // Update Sales Item
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
                    'shipping_address' => $orderContext->shipping_address ?? 'Same as original',
                    'status' => 'wait_operation',
                ]);

                // Auto-Reserve for Backorder
                foreach ($backorderItems as $boItem) {
                    $inventoryItemDto = $this->itemLookupService->findByPartNumber($boItem['product_id']);
                    if ($inventoryItemDto && $warehouseUuid) {
                        try {
                            $plan = $this->pickingService->calculatePickingPlan($inventoryItemDto->uuid, $warehouseUuid, (float)$boItem['quantity_requested']);
                            foreach ($plan as $step) {
                                if ($step['quantity'] <= 0) continue;
                                $stockLevel = $this->stockRepo->findByLocation($inventoryItemDto->uuid, $step['location_uuid'], $picking->company_id);
                                if ($stockLevel) {
                                    $stockLevel->reserveSoft($step['quantity']);
                                    $this->stockRepo->save($stockLevel, []);
                                }
                            }
                        } catch (\Exception $e) {
                            Log::warning("Backorder Auto-Reserve Failed: " . $e->getMessage());
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
        if (!in_array($picking->status, ['pending', 'assigned'])) {
            return back()->with('error', 'Status invalid.');
        }
        if ($picking->picker_user_id && $picking->picker_user_id !== auth()->id()) {
            return back()->with('error', 'Already assigned.');
        }
        $picking->update(['picker_user_id' => auth()->id(), 'status' => 'assigned']);
        return back()->with('success', 'Task assigned.');
    }

    public function reViewItem(string $id)
    {
        // View-only method logic updated
        $pickingSlip = PickingSlip::with(['items', 'picker'])->findOrFail($id);

        $orderContext = DB::table('sales_orders')
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.id')
            ->where('sales_orders.id', $pickingSlip->order_id)
            ->select('sales_orders.order_number', 'sales_orders.warehouse_id', 'sales_orders.company_id', 'customers.name', 'customers.address', 'customers.phone')
            ->first();

        $warehouseUuid = $orderContext->warehouse_id ?? 'Main-WH';
        if ($warehouseUuid === 'Main-WH' || !$warehouseUuid) {
            $warehouseUuid = \TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel::where('company_id', $orderContext->company_id)->value('uuid');
        }

        $items = $pickingSlip->items->map(function ($pickItem) use ($pickingSlip, $warehouseUuid) {
            $itemDto = $this->itemLookupService->findByPartNumber($pickItem->product_id);
            return [
                'id' => $pickItem->id,
                'product_id' => $pickItem->product_id,
                'product_name' => $itemDto ? $itemDto->name : $pickItem->product_id,
                'description' => $itemDto->description ?? '',
                'barcode' => $itemDto ? $itemDto->partNumber : '',
                'location' => 'CHECK APP',
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
                    'order_number' => $orderContext->order_number ?? '-',
                    'customer' => [
                        'name' => $orderContext->name ?? 'N/A',
                        'address' => $orderContext->address ?? '-',
                        'phone' => $orderContext->phone ?? '-',
                    ]
                ],
                'items' => $items
            ]
        ]);
    }
}
