<?php

namespace TmrEcosystem\Sales\Application\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;

class OrderTraceabilityService
{
    /**
     * ดึง Timeline ของ Order ทั้งหมด (เรียงตามเวลาเกิดจริง)
     */
    public function getOrderTimeline(string $orderId): array
    {
        // 1. ดึงข้อมูล Order พร้อมความสัมพันธ์ทั้งหมดที่จำเป็น
        $order = SalesOrderModel::with([
            'pickingSlips',
            'deliveryNotes.shipment.vehicle', // ดึงไปถึงรถขนส่ง
            'returnNotes'
        ])->findOrFail($orderId);

        $timeline = new Collection();

        // --- 1. Sales Order Events ---
        $timeline->push([
            'stage' => 'order',
            'title' => 'Order Placed',
            'description' => "Order #{$order->order_number} created.",
            'timestamp' => $order->created_at,
            'icon' => 'FileText',
            'status' => 'completed'
        ]);

        if ($order->status === 'confirmed') {
            $timeline->push([
                'stage' => 'order',
                'title' => 'Order Confirmed',
                'description' => 'Order approved and inventory reserved.',
                'timestamp' => $order->updated_at, // อาจจะไม่แม่นยำถ้าไม่มี timestamp แยก แต่พอใช้แทนได้
                'icon' => 'CheckCircle',
                'status' => 'completed'
            ]);
        }

        // --- 2. Picking Events ---
        foreach ($order->pickingSlips as $slip) {
            // Picking Created
            $timeline->push([
                'stage' => 'picking',
                'title' => 'Picking Started',
                'description' => "Picking Slip #{$slip->picking_number} generated.",
                'timestamp' => $slip->created_at,
                'icon' => 'PackageOpen',
                'status' => 'completed'
            ]);

            // Picking Done
            if ($slip->status === 'done' && $slip->picked_at) {
                $timeline->push([
                    'stage' => 'picking',
                    'title' => 'Picking Completed',
                    'description' => "Items picked and packed for Slip #{$slip->picking_number}.",
                    'timestamp' => Carbon::parse($slip->picked_at),
                    'icon' => 'CheckSquare',
                    'status' => 'completed'
                ]);
            }
        }

        // --- 3. Delivery & Shipment Events ---
        foreach ($order->deliveryNotes as $dn) {
            // Delivery Note Created (Ready to Ship)
            $timeline->push([
                'stage' => 'delivery',
                'title' => 'Ready to Ship',
                'description' => "Delivery Note #{$dn->delivery_number} created. Waiting for carrier.",
                'timestamp' => $dn->created_at,
                'icon' => 'Box',
                'status' => 'completed'
            ]);

            // Shipped (In Transit)
            if (($dn->status === 'shipped' || $dn->status === 'delivered') && $dn->shipped_at) {
                $vehicleInfo = $dn->shipment && $dn->shipment->vehicle
                    ? "Vehicle: {$dn->shipment->vehicle->license_plate}"
                    : ($dn->carrier_name ?? 'External Carrier');

                $timeline->push([
                    'stage' => 'delivery',
                    'title' => 'In Transit',
                    'description' => "Shipment departed. {$vehicleInfo} (Track: {$dn->tracking_number})",
                    'timestamp' => Carbon::parse($dn->shipped_at),
                    'icon' => 'Truck',
                    'status' => 'completed' // or active
                ]);
            }

            // Delivered
            if ($dn->status === 'delivered' && $dn->delivered_at) {
                $timeline->push([
                    'stage' => 'delivery',
                    'title' => 'Delivered',
                    'description' => "Items delivered to customer (DO: {$dn->delivery_number}).",
                    'timestamp' => Carbon::parse($dn->delivered_at),
                    'icon' => 'MapPin',
                    'status' => 'completed'
                ]);
            }
        }

        // --- 4. Return Events ---
        foreach ($order->returnNotes as $rn) {
            $timeline->push([
                'stage' => 'return',
                'title' => 'Return Requested',
                'description' => "Return Note #{$rn->return_number} created. Reason: {$rn->reason}",
                'timestamp' => $rn->created_at,
                'icon' => 'RotateCcw',
                'status' => 'warning'
            ]);

            if ($rn->status === 'completed') {
                $timeline->push([
                    'stage' => 'return',
                    'title' => 'Return Completed',
                    'description' => "Items received back into inventory.",
                    'timestamp' => $rn->updated_at,
                    'icon' => 'CheckCircle2',
                    'status' => 'completed'
                ]);
            }
        }

        // --- 5. Sort & Format ---
        return $timeline
            ->sortBy('timestamp')
            ->values()
            ->map(function ($item) {
                $item['formatted_date'] = Carbon::parse($item['timestamp'])->format('d M Y, H:i');
                return $item;
            })
            ->toArray();
    }
}
