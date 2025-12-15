<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // เพิ่มฟิลด์ต่อจาก email
            $table->decimal('credit_limit', 15, 2)->default(0)->after('email')->comment('วงเงินเครดิต (0=ไม่จำกัด/จ่ายสด)');
            $table->decimal('outstanding_balance', 15, 2)->default(0)->after('credit_limit')->comment('ยอดหนี้คงค้าง');
            $table->boolean('is_credit_hold')->default(false)->after('outstanding_balance')->comment('ระงับสินเชื่อหรือไม่');
            $table->integer('credit_term_days')->default(30)->after('is_credit_hold')->comment('เครดิตเทอม (วัน)');
            // เพิ่ม company_id เพื่อระบุสังกัดบริษัท (Multi-tenancy)
            // ใส่ nullable() ไว้ก่อนเผื่อมีข้อมูลเก่า แต่ index() เพื่อความเร็วในการค้นหา
            $table->foreignId('company_id')->nullable()->after('id')->index();
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->dropColumn(['credit_limit', 'outstanding_balance', 'is_credit_hold', 'credit_term_days', 'company_id']);
        });
    }
};
