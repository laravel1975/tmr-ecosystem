<?php

namespace TmrEcosystem\Logistics\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\Shipment;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\Vehicle;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlip;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\PickingSlipItem;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\ReturnNote;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\ReturnNoteItem;

use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Stock\Application\Services\StockPickingService;

class ShipmentController extends Controller
{
    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService
    ) {}

    public function index(Request $request)
    {
        $query = Shipment::query()->with(['vehicle', 'deliveryNotes.order.customer']);

        if ($request->search) {
            $query->where('shipment_number', 'like', "%{$request->search}%");
        }

        $shipments = $query->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Logistics/Shipments/Index', [
            'shipments' => $shipments,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create()
    {
        $readyDeliveries = DeliveryNote::with(['order.customer'])
            ->where('status', 'ready_to_ship')
            ->whereNull('shipment_id')
            ->orderBy('created_at', 'asc')
            ->get()
            ->map(fn($dn) => [
                'id' => $dn->id,
                'delivery_number' => $dn->delivery_number,
                'order_number' => $dn->order->order_number,
                'customer_name' => $dn->order->customer->name ?? 'N/A',
                'shipping_address' => $dn->shipping_address,
                'created_at' => $dn->created_at->format('d/m/Y'),
            ]);

        $vehicles = Vehicle::query()
            ->get()
            ->map(fn($v) => [
                'id' => $v->id,
                'name' => "{$v->license_plate} - {$v->brand} {$v->model}",
                'driver_name' => $v->driver_name,
                'driver_phone' => $v->driver_phone
            ]);

        $todayPrefix = 'SH-' . date('Ymd') . '-';
        $lastShipment = Shipment::where('shipment_number', 'like', "$todayPrefix%")->latest()->first();
        $nextNumber = $lastShipment
            ? $todayPrefix . str_pad((int)substr($lastShipment->shipment_number, -4) + 1, 4, '0', STR_PAD_LEFT)
            : $todayPrefix . '0001';

        return Inertia::render('Logistics/Shipments/Create', [
            'readyDeliveries' => $readyDeliveries,
            'vehicles' => $vehicles,
            'newShipmentNumber' => $nextNumber
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'shipment_number' => 'required|unique:logistics_shipments,shipment_number',
            'vehicle_id' => 'required|exists:logistics_vehicles,id',
            'planned_date' => 'required|date',
            'delivery_note_ids' => 'required|array|min:1',
            'driver_name' => 'nullable|string',
            'driver_phone' => 'nullable|string',
            'note' => 'nullable|string',
        ]);

        DB::transaction(function () use ($request) {
            $shipment = Shipment::create([
                'shipment_number' => $request->shipment_number,
                'vehicle_id' => $request->vehicle_id,
                'driver_name' => $request->driver_name,
                'driver_phone' => $request->driver_phone,
                'planned_date' => $request->planned_date,
                'status' => 'planned',
                'note' => $request->note
            ]);

            DeliveryNote::whereIn('id', $request->delivery_note_ids)
                ->update(['shipment_id' => $shipment->id]);
        });

        return to_route('logistics.shipments.index')->with('success', 'Shipment Plan created!');
    }

    public function show(string $id)
    {
        $shipment = Shipment::with(['vehicle', 'deliveryNotes.order.customer', 'deliveryNotes.pickingSlip.items'])
            ->findOrFail($id);

        return Inertia::render('Logistics/Shipments/Show', [
            'shipment' => $shipment,
            'deliveries' => $shipment->deliveryNotes
        ]);
    }

    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|in:shipped,completed',
        ]);

        $shipment = Shipment::with(['deliveryNotes.pickingSlip.items', 'deliveryNotes.order'])->findOrFail($id);

        DB::transaction(function () use ($shipment, $request) {
            $newStatus = $request->status;

            $shipment->update([
                'status' => $newStatus,
                'departed_at' => $newStatus === 'shipped' ? now() : $shipment->departed_at,
                'completed_at' => $newStatus === 'completed' ? now() : $shipment->completed_at,
            ]);

            foreach ($shipment->deliveryNotes as $delivery) {
                if ($newStatus === 'shipped' && $delivery->status !== 'shipped') {
                    $delivery->update([
                        'status' => 'shipped',
                        'shipped_at' => now()
                    ]);

                    $warehouseUuid = $delivery->order->warehouse_id;
                    $pickingItems = $delivery->pickingSlip->items ?? [];

                    $generalLocationUuid = DB::table('warehouse_storage_locations')
                        ->where('warehouse_uuid', $warehouseUuid)
                        ->where('code', 'GENERAL')
                        ->value('uuid');

                    foreach ($pickingItems as $item) {
                        if ($item->quantity_picked > 0) {
                            $itemDto = $this->itemLookupService->findByPartNumber($item->product_id);

                            if ($itemDto) {
                                $plan = $this->pickingService->calculateShipmentDeductionPlan(
                                    $itemDto->uuid,
                                    $warehouseUuid,
                                    (float)$item->quantity_picked
                                );

                                foreach ($plan as $step) {
                                    $locationUuid = $step['location_uuid'];
                                    $qtyToShip = $step['quantity'];

                                    if (!$locationUuid) {
                                        $locationUuid = $generalLocationUuid;
                                    }

                                    if ($locationUuid) {
                                        $stockLevel = $this->stockRepo->findByLocation(
                                            $itemDto->uuid,
                                            $locationUuid,
                                            $delivery->order->company_id
                                        );

                                        if ($stockLevel) {
                                            $stockLevel->shipReserved(
                                                $qtyToShip,
                                                auth()->id(),
                                                "Shipment: {$shipment->shipment_number} / DO: {$delivery->delivery_number}"
                                            );
                                            $this->stockRepo->save($stockLevel, []);
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                if ($newStatus === 'completed' && $delivery->status !== 'delivered') {
                    $delivery->update([
                        'status' => 'delivered',
                        'delivered_at' => now()
                    ]);
                }
            }
        });

        return back()->with('success', "Shipment status updated to {$request->status}.");
    }

    public function startTrip(string $id)
    {
        $request = new Request(['status' => 'shipped']);
        return $this->updateStatus($request, $id);
    }

    public function completeTrip(string $id)
    {
        $request = new Request(['status' => 'completed']);
        return $this->updateStatus($request, $id);
    }

    public function removeDelivery(Request $request, string $id)
    {
         $request->validate(['delivery_note_id' => 'required|exists:sales_delivery_notes,id']);

         $shipment = Shipment::findOrFail($id);
         if ($shipment->status !== 'planned') {
             return back()->with('error', 'Cannot remove items from an active shipment.');
         }

         $delivery = DeliveryNote::findOrFail($request->delivery_note_id);
         $delivery->update(['shipment_id' => null]);

         return back()->with('success', 'Delivery removed from shipment.');
    }

    // ✅ [MODIFIED] Unload Logic with Target Action (Stock vs Return)
    public function unload(Request $request, string $id)
    {
        $request->validate([
            'delivery_note_id' => 'required|exists:sales_delivery_notes,id',
            'type' => 'required|in:whole,partial',
            'items' => 'required_if:type,partial|array',
            'target_action' => 'nullable|in:stock,return'
        ]);

        $targetAction = $request->target_action ?? 'stock';
        $shipment = Shipment::findOrFail($id);

        if ($shipment->status !== 'planned') {
            return back()->with('error', 'รถออกไปแล้ว ไม่สามารถเอาของลงได้');
        }

        // ✅ [FIXED] เพิ่ม $shipment เข้าไปใน use (...)
        DB::transaction(function () use ($request, $id, $targetAction, $shipment) {
            $delivery = DeliveryNote::with('pickingSlip')->findOrFail($request->delivery_note_id);

            // 1. กรณี Whole Unload (เอาลงทั้งใบ)
            if ($request->type === 'whole') {
                $delivery->update(['shipment_id' => null]); // ปลดออกจากรถ

                // ถ้าเลือกเป็น Return -> ต้องยกเลิก DO นี้และสร้าง Return Note
                if ($targetAction === 'return') {
                     $delivery->update([
                         'status' => 'cancelled',
                         'note' => 'Unloaded as Damaged/Returned from ' . $shipment->shipment_number
                     ]);

                     // สร้าง Return Note
                     $this->createReturnNoteFromDelivery($delivery, "Unload from Shipment {$shipment->shipment_number}");
                }
            }
            // 2. กรณี Partial Unload (แบ่งของลง)
            else {
                $itemsToUnload = collect($request->items)->filter(fn($i) => $i['qty_unload'] > 0);

                if ($itemsToUnload->isEmpty()) return;

                $originalPicking = $delivery->pickingSlip;

                // สร้าง Picking Slip ใบใหม่ (สำหรับของที่เอาลง)
                $newPicking = PickingSlip::create([
                    'picking_number' => $originalPicking->picking_number . '-SP' . rand(10, 99),
                    'company_id' => $delivery->pickingSlip->company_id,
                    'order_id' => $delivery->order_id,
                    'status' => 'done', // ของถูกหยิบแล้ว
                    'picker_user_id' => $originalPicking->picker_user_id,
                    'picked_at' => now(),
                    'note' => "Split/Unloaded from DO {$delivery->delivery_number}"
                ]);

                foreach ($itemsToUnload as $unloadItem) {
                    $originalItem = PickingSlipItem::find($unloadItem['id']);

                    if ($originalItem) {
                        // ลดจำนวนในใบเดิม
                        $originalItem->decrement('quantity_picked', $unloadItem['qty_unload']);
                        $originalItem->decrement('quantity_requested', $unloadItem['qty_unload']);

                        // เพิ่มในใบใหม่
                        $newPicking->items()->create([
                            'sales_order_item_id' => $originalItem->sales_order_item_id,
                            'product_id' => $originalItem->product_id,
                            'quantity_requested' => $unloadItem['qty_unload'],
                            'quantity_picked' => $unloadItem['qty_unload'],
                        ]);
                    }
                }

                // สร้าง Delivery Note ใบใหม่
                $newDeliveryStatus = ($targetAction === 'return') ? 'cancelled' : 'ready_to_ship';
                $newDeliveryNote = ($targetAction === 'return') ? "Unloaded as Return (Damaged)" : null;

                $newDelivery = DeliveryNote::create([
                    'delivery_number' => $delivery->delivery_number . '-SP' . rand(10, 99),
                    'company_id' => $delivery->company_id,
                    'order_id' => $delivery->order_id,
                    'picking_slip_id' => $newPicking->id,
                    'shipping_address' => $delivery->shipping_address,
                    'status' => $newDeliveryStatus,
                    'shipment_id' => null, // ไม่ได้อยู่บนรถคันนี้แล้ว
                    'note' => $newDeliveryNote
                ]);

                // ถ้าเป็น Return -> สร้าง Return Note และคืนยอด SalesOrder
                if ($targetAction === 'return') {
                     $this->createReturnNoteFromDelivery($newDelivery, "Partial Unload (Damaged)");
                }
            }
        });

        return back()->with('success', 'Unloaded successfully.');
    }

    // Helper: สร้าง Return Note และคืนยอด Sales Order
    private function createReturnNoteFromDelivery(DeliveryNote $delivery, string $reason)
    {
        $returnNote = ReturnNote::create([
            'return_number' => 'RN-' . date('Ymd') . '-' . rand(1000, 9999),
            'order_id' => $delivery->order_id,
            'picking_slip_id' => $delivery->picking_slip_id,
            'delivery_note_id' => $delivery->id,
            'status' => 'pending',
            'reason' => $reason
        ]);

        foreach ($delivery->pickingSlip->items as $item) {
            if ($item->quantity_picked > 0) {
                ReturnNoteItem::create([
                    'return_note_id' => $returnNote->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity_picked
                ]);

                // 🔴 คืนยอด Shipped ใน SalesOrder
                DB::table('sales_order_items')
                    ->where('id', $item->sales_order_item_id)
                    ->decrement('qty_shipped', $item->quantity_picked);
            }
        }
    }

    public function getDeliveryItems(string $deliveryId)
    {
        $delivery = DeliveryNote::with('pickingSlip.items')->findOrFail($deliveryId);

        $items = $delivery->pickingSlip->items->map(function ($item) {
            $itemDto = $this->itemLookupService->findByPartNumber($item->product_id);
            $productName = $itemDto ? $itemDto->name : $item->product_id;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $productName,
                'qty_picked' => $item->quantity_picked,
            ];
        });

        return response()->json($items);
    }
}
