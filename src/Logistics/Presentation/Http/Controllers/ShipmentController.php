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

use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
// ✅ Use Service
use TmrEcosystem\Stock\Application\Services\StockPickingService;

class ShipmentController extends Controller
{
    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService // ✅ Inject Service
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

    /**
     * อัปเดตสถานะ Shipment (ปล่อยรถ / จบงาน)
     */
    public function updateStatus(Request $request, string $id)
    {
        $request->validate([
            'status' => 'required|in:shipped,completed',
        ]);

        $shipment = Shipment::with(['deliveryNotes.pickingSlip.items', 'deliveryNotes.order'])->findOrFail($id);

        DB::transaction(function () use ($shipment, $request) {
            $newStatus = $request->status;

            // 1. อัปเดตสถานะของ Shipment หลัก
            $shipment->update([
                'status' => $newStatus,
                'departed_at' => $newStatus === 'shipped' ? now() : $shipment->departed_at,
                'completed_at' => $newStatus === 'completed' ? now() : $shipment->completed_at,
            ]);

            // 2. วนลูปจัดการ Delivery Notes ในรถคันนั้น
            foreach ($shipment->deliveryNotes as $delivery) {

                // --- กรณี: รถออกจากคลัง (Shipped) -> ตัดสต็อก ---
                if ($newStatus === 'shipped' && $delivery->status !== 'shipped') {
                    $delivery->update([
                        'status' => 'shipped',
                        'shipped_at' => now()
                    ]);

                    $warehouseUuid = $delivery->order->warehouse_id;
                    $pickingItems = $delivery->pickingSlip->items ?? [];

                    // เตรียม UUID ของ General Location ไว้กันเหนียว
                    $generalLocationUuid = DB::table('warehouse_storage_locations')
                        ->where('warehouse_uuid', $warehouseUuid)
                        ->where('code', 'GENERAL')
                        ->value('uuid');

                    foreach ($pickingItems as $item) {
                        if ($item->quantity_picked > 0) {

                            // A. แปลง Product ID -> Item UUID
                            $itemDto = $this->itemLookupService->findByPartNumber($item->product_id);

                            if ($itemDto) {
                                // B. ✅ ใช้ Service คำนวณหาจุดตัดสต็อก (Deduction Plan)
                                // เน้นหาจาก Reserved / On Hand
                                $plan = $this->pickingService->calculateShipmentDeductionPlan(
                                    $itemDto->uuid,
                                    $warehouseUuid,
                                    (float)$item->quantity_picked
                                );

                                // C. วนลูปตัดของตามแผนที่ได้
                                foreach ($plan as $step) {
                                    $locationUuid = $step['location_uuid'];
                                    $qtyToShip = $step['quantity'];

                                    // Fallback: ถ้า Service หาไม่เจอ ให้ไปตัดที่ GENERAL
                                    if (!$locationUuid) {
                                        $locationUuid = $generalLocationUuid;
                                    }

                                    if ($locationUuid) {
                                        // D. ค้นหา StockLevel ที่พิกัดนั้น
                                        $stockLevel = $this->stockRepo->findByLocation(
                                            $itemDto->uuid,
                                            $locationUuid,
                                            $delivery->order->company_id
                                        );

                                        if ($stockLevel) {
                                            // E. สั่งตัดสต็อก (Hard Deduct)
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

                // --- กรณี: จบงาน (Completed) -> เปลี่ยนสถานะเป็น Delivered ---
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

    public function unload(Request $request, string $id)
    {
        $request->validate([
            'delivery_note_id' => 'required|exists:sales_delivery_notes,id',
            'type' => 'required|in:whole,partial',
            'items' => 'required_if:type,partial|array'
        ]);

        $shipment = Shipment::findOrFail($id);

        if ($shipment->status !== 'planned') {
            return back()->with('error', 'รถออกไปแล้ว ไม่สามารถเอาของลงได้');
        }

        DB::transaction(function () use ($request, $id) {
            $delivery = DeliveryNote::with('pickingSlip')->findOrFail($request->delivery_note_id);

            if ($request->type === 'whole') {
                $delivery->update(['shipment_id' => null]);
            } else {
                $itemsToUnload = collect($request->items)->filter(fn($i) => $i['qty_unload'] > 0);

                if ($itemsToUnload->isEmpty()) return;

                $originalPicking = $delivery->pickingSlip;
                $newPicking = PickingSlip::create([
                    'picking_number' => $originalPicking->picking_number . '-SP' . rand(10, 99),
                    'company_id' => $delivery->pickingSlip->company_id,
                    'order_id' => $delivery->order_id,
                    'status' => 'done',
                    'picker_user_id' => $originalPicking->picker_user_id,
                    'picked_at' => now(),
                    'note' => "Split/Unloaded from DO {$delivery->delivery_number}"
                ]);

                foreach ($itemsToUnload as $unloadItem) {
                    $originalItem = PickingSlipItem::find($unloadItem['id']);

                    if ($originalItem) {
                        $originalItem->decrement('quantity_picked', $unloadItem['qty_unload']);
                        $originalItem->decrement('quantity_requested', $unloadItem['qty_unload']);

                        $newPicking->items()->create([
                            'sales_order_item_id' => $originalItem->sales_order_item_id,
                            'product_id' => $originalItem->product_id,
                            'quantity_requested' => $unloadItem['qty_unload'],
                            'quantity_picked' => $unloadItem['qty_unload'],
                        ]);
                    }
                }

                DeliveryNote::create([
                    'delivery_number' => $delivery->delivery_number . '-SP' . rand(10, 99),
                    'company_id' => $delivery->company_id,
                    'order_id' => $delivery->order_id,
                    'picking_slip_id' => $newPicking->id,
                    'shipping_address' => $delivery->shipping_address,
                    'status' => 'ready_to_ship',
                    'shipment_id' => null
                ]);
            }
        });

        return back()->with('success', 'Unloaded successfully.');
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
