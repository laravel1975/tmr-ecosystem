<?php

namespace TmrEcosystem\Logistics\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\DeliveryNote;
use Carbon\Carbon;

class PublicTrackingController extends Controller
{
    public function show($token)
    {
        $delivery = DeliveryNote::with(['shipment.vehicle', 'items'])
            ->where('tracking_token', $token)
            ->firstOrFail();

        // สร้าง Timeline แบบง่ายๆ
        $timeline = [];

        // 1. Created
        $timeline[] = [
            'status' => 'Pending',
            'description' => 'Order processed & packed',
            'date' => $delivery->created_at->format('d M Y, H:i'),
            'active' => true
        ];

        // 2. Shipped
        if ($delivery->status === 'shipped' || $delivery->status === 'delivered') {
            $timeline[] = [
                'status' => 'In Transit',
                'description' => $delivery->shipment
                    ? "Departed with {$delivery->shipment->vehicle->license_plate}"
                    : "Handed over to carrier ({$delivery->carrier_name})",
                'date' => $delivery->shipped_at ? $delivery->shipped_at->format('d M Y, H:i') : '-',
                'active' => true
            ];
        } else {
             $timeline[] = ['status' => 'In Transit', 'description' => 'Waiting for shipment', 'date' => '', 'active' => false];
        }

        // 3. Delivered
        if ($delivery->status === 'delivered') {
            $timeline[] = [
                'status' => 'Delivered',
                'description' => 'Package delivered to recipient',
                'date' => $delivery->delivered_at ? $delivery->delivered_at->format('d M Y, H:i') : '-',
                'active' => true
            ];
        } else {
             $timeline[] = ['status' => 'Delivered', 'description' => 'Estimated arrival soon', 'date' => '', 'active' => false];
        }

        return Inertia::render('Logistics/Tracking/PublicShow', [
            'delivery' => [
                'number' => $delivery->delivery_number,
                'status' => $delivery->status,
                'customer_name' => $this->maskString($delivery->order->customer->name ?? 'Guest'), // Mask ชื่อลูกค้าเพื่อ Privacy
                'shipping_address' => $delivery->shipping_address,
                'items_count' => $delivery->items->count(),
                'carrier' => $delivery->carrier_name,
                'tracking_no' => $delivery->tracking_number,
                'items' => $delivery->items->map(fn($i) => [
                    'product_id' => $i->product_id,
                    'qty' => $i->quantity_picked
                ])
            ],
            'timeline' => $timeline
        ]);
    }

    private function maskString($str) {
        $len = strlen($str);
        if ($len <= 4) return $str;
        return mb_substr($str, 0, 3) . '***' . mb_substr($str, -2);
    }
}
