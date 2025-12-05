<?php

namespace TmrEcosystem\Purchase\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;
use TmrEcosystem\Purchase\Domain\Enums\PurchaseOrderStatus;
use TmrEcosystem\IAM\Domain\Models\User;

class PurchaseOrder extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'document_number', 'vendor_id', 'created_by',
        'order_date', 'expected_delivery_date', 'status',
        'notes', 'subtotal', 'tax_amount', 'grand_total'
    ];

    protected $casts = [
        'order_date' => 'date',
        'expected_delivery_date' => 'date',
        'status' => PurchaseOrderStatus::class,
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }

    public function vendor()
    {
        return $this->belongsTo(Vendor::class);
    }

    public function items()
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
