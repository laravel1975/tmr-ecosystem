<?php

namespace Tests\Unit\Inventory;

use PHPUnit\Framework\TestCase;
use TmrEcosystem\Inventory\Domain\Aggregates\Item;
use TmrEcosystem\Inventory\Domain\ValueObjects\ItemCode;
use InvalidArgumentException;
use TmrEcosystem\Shared\Domain\ValueObjects\Money;

class ItemTest extends TestCase
{
    public function test_can_create_item_with_valid_data()
    {
        $item = Item::create(
            uuid: 'uuid-123',
            companyId: 'company-1',
            partNumber: new ItemCode('PART-001'),
            name: 'Test Item',
            uom: 'EA',
            category: 'General',
            averageCost: new Money(100.50),
            description: 'Test Description'
        );

        $this->assertInstanceOf(Item::class, $item);
        $this->assertEquals('PART-001', $item->partNumber());
        $this->assertEquals(100.50, $item->averageCost());
    }

    public function test_cannot_create_item_with_empty_part_number()
    {
        $this->expectException(InvalidArgumentException::class);

        new ItemCode(''); // ควร Error ตรงนี้
    }

    public function test_cannot_create_item_with_negative_cost()
    {
        $this->expectException(InvalidArgumentException::class);

        new Money(-50); // ควร Error ตรงนี้
    }
}
