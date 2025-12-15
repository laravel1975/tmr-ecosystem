<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // เพิ่ม default_salesperson_id เพื่อให้ระบบดึงอัตโนมัติเมื่อเปิดบิล
            $table->foreignId('default_salesperson_id')->nullable()->after('company_id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn('default_salesperson_id');
        });
    }
};
