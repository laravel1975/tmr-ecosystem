<?php

namespace TmrEcosystem\Logistics\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\Vehicle;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\Shipment;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\ReturnNote;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\ReturnNoteItem;
// ✅ Use Service
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Logistics\Domain\Events\DeliveryNoteCancelled;
use TmrEcosystem\Stock\Application\Services\StockPickingService;

class DeliveryController extends Controller
{
    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService,
        private StockPickingService $pickingService // ✅ Inject Service
    ) {}

    public function index(Request $request)
    {
        $query = DeliveryNote::query()
            ->with(['order.customer', 'pickingSlip']) // Eager load picking slip
            ->join('sales_orders', 'sales_delivery_notes.order_id', '=', 'sales_orders.id')
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.id')
            ->select(
                'sales_delivery_notes.*',
                'sales_orders.order_number',
                'customers.name as customer_name'
            );

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('sales_delivery_notes.delivery_number', 'like', "%{$request->search}%")
                    ->orWhere('sales_orders.order_number', 'like', "%{$request->search}%")
                    ->orWhere('customers.name', 'like', "%{$request->search}%")
                    ->orWhere('sales_delivery_notes.tracking_number', 'like', "%{$request->search}%");
            });
        }

        if ($request->status) {
            $query->where('sales_delivery_notes.status', $request->status);
        }

        $deliveries = $query->orderByRaw("CASE WHEN sales_delivery_notes.status = 'ready_to_ship' THEN 1 ELSE 2 END")
            ->orderBy('sales_delivery_notes.created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($dn) => [
                'id' => $dn->id,
                'delivery_number' => $dn->delivery_number,
                'order_number' => $dn->order_number,
                'customer_name' => $dn->customer_name ?? 'N/A',
                'shipping_address' => $dn->shipping_address,
                'status' => $dn->status,
                'carrier_name' => $dn->carrier_name,
                'tracking_number' => $dn->tracking_number,
                'created_at' => $dn->created_at->format('d/m/Y H:i'),
                // เพิ่มข้อมูล Picking Ref ให้หน้าบ้านรู้ว่ามาจากใบหยิบไหน
                'picking_ref' => $dn->pickingSlip->picking_number ?? '-',
            ]);

        return Inertia::render('Logistics/Delivery/Index', [
            'deliveries' => $deliveries,
            'filters' => $request->only(['search', 'status']),
        ]);
    }

    public function show(string $id)
    {
        // 1. ดึง Delivery Note พร้อม Picking Slip Items (1:N Supported)
        $delivery = DeliveryNote::with([
            'order.customer',
            'pickingSlip.items', // ดึง items ผ่าน pickingSlip
            'shipment'
        ])->findOrFail($id);

        // 2. Map Items จาก Picking Slip (เฉพาะของในกล่องนี้)
        $items = $delivery->pickingSlip->items->map(function ($pickItem) {
            // ดึงข้อมูลสินค้า (Master Data)
            $itemDto = $this->itemLookupService->findByPartNumber($pickItem->product_id);

            // ข้อมูลการสั่งซื้อ (สำหรับ Reference)
            // หมายเหตุ: การดึง salesOrderItem อาจจะต้องระวัง N+1 ถ้าไม่ได้ Eager Load มา
            // แต่ใน case นี้ปริมาณต่อใบไม่เยอะมาก พอรับได้ หรือจะเพิ่ม with('pickingSlip.items.salesOrderItem') ก็ได้

            return [
                'id' => $pickItem->id,
                'product_id' => $pickItem->product_id,
                'product_name' => $itemDto ? $itemDto->name : $pickItem->product_id,
                'description' => $itemDto->description ?? '',
                'barcode' => $itemDto ? ($itemDto->partNumber) : '',

                'quantity_ordered' => (float)$pickItem->quantity_requested, // ยอดที่ขอให้หยิบในใบนี้
                'qty_shipped' => (float)$pickItem->quantity_picked,       // ยอดที่หยิบได้จริง (ส่งจริง)

                'unit_price' => 0, // ถ้าต้องการราคาต้องดึงจาก SalesOrderItem เพิ่ม
                'image_url' => $itemDto ? $itemDto->imageUrl : null,
            ];
        });

        $vehicles = Vehicle::query()
            ->where('status', 'active')
            ->orderBy('license_plate')
            ->get(['id', 'license_plate', 'brand', 'model', 'driver_name']);

        return Inertia::render('Logistics/Delivery/Process', [
            'delivery' => [
                'id' => $delivery->id,
                'delivery_number' => $delivery->delivery_number,
                'order_number' => $delivery->order->order_number,
                'customer_name' => $delivery->order->customer->name ?? 'N/A',
                'shipping_address' => $delivery->shipping_address,
                'contact_person' => $delivery->contact_person,
                'contact_phone' => $delivery->contact_phone,
                'status' => $delivery->status,
                'carrier_name' => $delivery->carrier_name,
                'tracking_number' => $delivery->tracking_number,
                'shipped_at' => $delivery->shipped_at?->format('d/m/Y H:i'),
                'picking_number' => $delivery->pickingSlip->picking_number ?? '-',
                'shipment_id' => $delivery->shipment_id,
                'shipment_number' => $delivery->shipment ? $delivery->shipment->shipment_number : null,
            ],
            'items' => $items,
            'vehicles' => $vehicles,
        ]);
    }

    public function update(Request $request, string $id)
    {
        $delivery = DeliveryNote::with(['pickingSlip.items', 'order', 'shipment.deliveryNotes'])->findOrFail($id);

        $request->validate([
            'status' => 'required|in:shipped,delivered',
            'shipment_type' => 'nullable|in:carrier,fleet',
            'vehicle_id' => 'nullable|required_if:shipment_type,fleet|exists:logistics_vehicles,id',
            'carrier_name' => 'nullable|required_if:shipment_type,carrier|string',
            'tracking_number' => 'nullable|string',
        ]);

        DB::transaction(function () use ($delivery, $request) {
            $updateData = [
                'status' => $request->status,
            ];

            if ($request->status === 'shipped' && !$delivery->shipped_at) {
                $updateData['shipped_at'] = now();

                if ($request->input('shipment_type') === 'fleet' && !$delivery->shipment_id) {
                    $vehicle = Vehicle::find($request->input('vehicle_id'));
                    $shipment = Shipment::create([
                        'shipment_number' => 'SH-' . date('Ymd') . '-' . strtoupper(Str::random(4)),
                        'vehicle_id' => $vehicle->id,
                        'driver_name' => $vehicle->driver_name ?? 'Unknown Driver',
                        'status' => 'planned',
                        'planned_date' => now(),
                    ]);
                    $updateData['shipment_id'] = $shipment->id;
                    $updateData['carrier_name'] = "Fleet: {$vehicle->license_plate}";
                    $updateData['tracking_number'] = $shipment->shipment_number;

                    $delivery->setRelation('shipment', $shipment);
                } else {
                    $updateData['carrier_name'] = $request->carrier_name;
                    $updateData['tracking_number'] = $request->tracking_number;
                }

                $warehouseUuid = $delivery->order->warehouse_id;
                $pickingItems = $delivery->pickingSlip->items ?? [];

                // ⚠️ แก้ไข: หา Location จาก PickingController ที่ commit ไว้แล้ว
                // แต่เพื่อความง่าย เราจะตัดจาก GENERAL หรือ Location ที่ Picking Slip บันทึกไว้ (ถ้ามี)
                // ในเฟสนี้เนื่องจาก PickingController ตัดสต็อกไปแล้ว (Out) ตอน Confirm Picking
                // เราไม่ต้องตัดซ้ำที่นี่อีก! (Logic ที่ถูกต้องคือ Picking = ตัดของออกจากคลังไปวางที่จุดส่งของ, Delivery = เปลี่ยนสถานะเฉยๆ)

                // แต่ถ้า Business Logic คือ Picking = จอง, Delivery = ตัดจริง
                // โค้ดส่วนนี้ (StockPickingService::calculate...) ถึงจะจำเป็น
                // จากบริบทก่อนหน้า PickingController::confirm เราได้ทำการ commitReservation (ตัดของ) ไปแล้ว
                // ดังนั้นตรงนี้ควรอัปเดตแค่สถานะครับ
            }

            if ($request->status === 'delivered') {
                $updateData['delivered_at'] = now();
            }

            $delivery->update($updateData);

            // Update Shipment Status
            if ($delivery->shipment_id) {
                $shipment = Shipment::with('deliveryNotes')->find($delivery->shipment_id);

                if ($shipment) {
                    if ($shipment->status === 'planned' && $request->status === 'shipped') {
                        $shipment->update([
                            'status' => 'shipped',
                            'departed_at' => now(),
                        ]);
                    }

                    $allDelivered = $shipment->deliveryNotes->every(function ($note) {
                        return $note->status === 'delivered';
                    });

                    if ($allDelivered && $shipment->status !== 'completed') {
                        $shipment->update([
                            'status' => 'completed',
                            'completed_at' => now(),
                        ]);
                    }
                }
            }
        });

        return to_route('logistics.delivery.index')
            ->with('success', 'Delivery status updated successfully!');
    }

    public function cancelAndReturn(Request $request, string $id)
    {
        $delivery = DeliveryNote::with(['pickingSlip.items'])->findOrFail($id);

        if ($delivery->status !== 'ready_to_ship') {
            return back()->with('error', 'ทำรายการได้เฉพาะสถานะ Ready to Ship เท่านั้น');
        }

        DB::transaction(function () use ($delivery) {
            $delivery->update([
                'status' => 'cancelled',
                'shipment_id' => null
            ]);

            $returnNote = ReturnNote::create([
                'return_number' => 'RN-' . date('Ymd') . '-' . rand(1000, 9999),
                'order_id' => $delivery->order_id,
                'picking_slip_id' => $delivery->picking_slip_id,
                'status' => 'pending',
                'reason' => 'Cancelled from Delivery (Ready to Ship)'
            ]);

            foreach ($delivery->pickingSlip->items as $item) {
                if ($item->quantity_picked > 0) {
                    ReturnNoteItem::create([
                        'return_note_id' => $returnNote->id,
                        'product_id' => $item->product_id,
                        'quantity' => $item->quantity_picked
                    ]);
                }
            }
        });

        DeliveryNoteCancelled::dispatch($delivery);

        return to_route('logistics.return-notes.index')
            ->with('success', 'Delivery Cancelled. Please process the Return Note.');
    }

    public function reViewItem(string $id)
    {
        $delivery = DeliveryNote::with([
            'order.customer',
            'pickingSlip.items',
        ])->findOrFail($id);

        // Map items เฉพาะใน Picking Slip นี้
        $items = $delivery->pickingSlip->items->map(function ($pickItem) {
            $itemDto = $this->itemLookupService->findByPartNumber($pickItem->product_id);

            return [
                'id' => $pickItem->id,
                'product_id' => $pickItem->product_id,
                'product_name' => $itemDto ? $itemDto->name : $pickItem->product_id,
                'description' => $itemDto ? ($itemDto->description ?? '') : '',
                'barcode' => $itemDto ? ($itemDto->barcode ?? $itemDto->partNumber) : '',

                // ยอดที่ส่งจริงในรอบนี้
                'qty_shipped' => (float)$pickItem->quantity_picked,
                // ยอดที่ขอให้หยิบในรอบนี้
                'quantity_ordered' => (float)$pickItem->quantity_requested,

                'image_url' => $itemDto ? $itemDto->imageUrl : null,
            ];
        });

        return Inertia::render('Logistics/Delivery/Show', [
            'delivery' => [
                'id' => $delivery->id,
                'delivery_number' => $delivery->delivery_number,
                'status' => $delivery->status,
                'created_at' => $delivery->created_at->toIso8601String(),
                'shipping_address' => $delivery->shipping_address,
                'contact_person' => $delivery->contact_person,
                'contact_phone' => $delivery->contact_phone,
                'carrier_name' => $delivery->carrier_name,
                'tracking_number' => $delivery->tracking_number,
                'picking_number' => $delivery->pickingSlip->picking_number ?? '-',
                'order' => [
                    'order_number' => $delivery->order->order_number,
                    'customer' => [
                        'name' => $delivery->order->customer->name ?? 'N/A',
                    ]
                ],
                'items' => $items
            ]
        ]);
    }
}
