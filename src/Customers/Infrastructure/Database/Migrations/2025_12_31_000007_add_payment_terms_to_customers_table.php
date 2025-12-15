<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            // Add payment_terms column, nullable, after credit_term_days
            // Defaulting to 'immediate' or null based on your business logic
            if (!Schema::hasColumn('customers', 'payment_terms')) {
                $table->string('payment_terms')->nullable()->default('immediate')->after('credit_term_days');
            }
        });
    }

    public function down(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            if (Schema::hasColumn('customers', 'payment_terms')) {
                $table->dropColumn('payment_terms');
            }
        });
    }
};
