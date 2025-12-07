<?php

namespace TmrEcosystem\Inventory\Infrastructure\Persistence\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\Item as ItemModel; // <-- (สำคัญ) ระบุ Model ของเรา
use TmrEcosystem\Shared\Domain\Models\Company;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\Src\Inventory\Infrastructure\Persistence\Eloquent\Models\Item>
 */
class ItemFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ItemModel::class; // <-- (สำคัญ) บอก Factory ว่า Model ของเราคือตัวไหน

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // (สร้าง Part Number แบบสุ่ม เช่น "SP-123456")
        $partNumber = 'PN-' . $this->faker->unique()->numberBetween(100000, 999999);

        return [
            // --- 1. Keys (อิงจาก Migration และ Model) ---
            'uuid' => $this->faker->uuid(),

            // (สำคัญ) ดึง Company มา 1 รายการ
            // ถ้าไม่มี ให้สร้างใหม่ (อิงจาก Pattern ของคุณ)
            'company_id' => Company::first() ?? Company::factory(),

            // --- 2. Core Fields (อิงจาก $fillable) ---
            'part_number' => $partNumber,
            'name' => 'Spare Part ' . $partNumber,
            'description' => $this->faker->sentence(10),
            'category' => $this->faker->randomElement(['Mechanical', 'Electrical', 'Consumable', 'Lubricant']),

            // (Req G) (อิงจาก $casts['average_cost' => 'decimal:4'])
            'average_cost' => $this->faker->randomFloat(4, 10, 1000),

            'uom' => $this->faker->randomElement(['EA', 'PC', 'SET', 'MTR', 'KG']),
        ];
    }
}
