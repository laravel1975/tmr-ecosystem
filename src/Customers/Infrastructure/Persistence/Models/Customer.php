<?php

namespace TmrEcosystem\Customers\Infrastructure\Persistence\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // ✅ 1. เพิ่ม Import นี้
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;

class Customer extends Model
{
    use HasFactory, SoftDeletes, HasUuids; // ✅ 2. เรียกใช้ Trait นี้

    protected $fillable = [
        'company_id',
        'code',
        'name',
        'email',
        'phone',
        'address',
        'tax_id',
        // Financial Fields
        'credit_limit',
        'outstanding_balance',
        'is_credit_hold',
        'credit_term_days'
    ];

    protected $casts = [
        'credit_limit' => 'decimal:2',
        'outstanding_balance' => 'decimal:2',
        'is_credit_hold' => 'boolean',
        'credit_term_days' => 'integer',
    ];

    // ✅ เพิ่มความสัมพันธ์กับ Sales Order
    public function orders()
    {
        return $this->hasMany(SalesOrderModel::class, 'customer_id');
    }
}
