<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // วงเงินเครดิต (0 = ไม่จำกัด หรือต้องจ่ายสด)
            $table->decimal('credit_limit', 15, 2)->default(0)->after('email');

            // ยอดหนี้คงค้างปัจจุบัน (รวมบิลที่ยังไม่จ่าย)
            $table->decimal('outstanding_balance', 15, 2)->default(0)->after('credit_limit');

            // Flag ว่าติด Hold หรือไม่
            $table->boolean('is_credit_hold')->default(false)->after('outstanding_balance');
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['credit_limit', 'outstanding_balance', 'is_credit_hold']);
        });
    }
};
