<?php

use Tmr\IAM\Domain\Models\User;
use Tmr\Inventory\Infrastructure\Persistence\Eloquent\Models\Item;
use Tmr\Purchase\Domain\Models\Vendor;
use Tmr\Purchase\Domain\Models\PurchaseOrder;

uses(\Tests\TestCase::class);

test('authenticated user can create purchase order', function () {
    // Arrange
    $user = User::factory()->create();
    $vendor = Vendor::create(['code' => 'V1', 'name' => 'Test Vendor', 'uuid' => Str::uuid()]);
    $item = Item::factory()->create(['price' => 100]); // Assuming Item factory exists

    $payload = [
        'vendor_id' => $vendor->id,
        'order_date' => now()->format('Y-m-d'),
        'items' => [
            [
                'item_id' => $item->id,
                'quantity' => 10,
                'unit_price' => 100
            ]
        ]
    ];

    // Act
    $response = $this->actingAs($user)
        ->post(route('purchase.orders.store'), $payload);

    // Assert
    $response->assertRedirect(route('purchase.orders.index'));

    $this->assertDatabaseHas('purchase_orders', [
        'vendor_id' => $vendor->id,
        'grand_total' => 1070.00 // 1000 + 7% VAT
    ]);

    $this->assertDatabaseHas('purchase_order_items', [
        'item_id' => $item->id,
        'quantity' => 10
    ]);
});
