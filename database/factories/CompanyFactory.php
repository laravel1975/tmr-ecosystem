<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str; // <-- (สำคัญ) Import Str เหมือนใน Seeder
use TmrEcosystem\Shared\Domain\Models\Company;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Company>
 */
class CompanyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Company::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // --- (นี่คือตรรกะที่ "ล้อตาม" Seeder ของคุณ) ---

        // 1. สร้างชื่อบริษัทแบบสุ่ม
        $name = $this->faker->company();

        // 2. สร้าง slug จาก name (เหมือนใน Seeder)
        $slug = Str::slug($name);

        // 3. ตั้ง is_active เป็น true (เหมือนใน Seeder)
        $isActive = true;
        // --- (จบส่วนตรรกะ) ---

        return [
            'name' => $name,
            'slug' => $slug, // (ใช้ค่าที่สร้าง)
            'is_active' => $isActive, // (ใช้ค่าที่สร้าง)

            // (Field อื่นๆ ที่จำเป็นสำหรับ Factory แต่ Seeder ไม่มี)
            'email' => $this->faker->unique()->safeEmail(),
            'phone' => $this->faker->phoneNumber(),
            'address' => $this->faker->address(),
        ];
    }
}
