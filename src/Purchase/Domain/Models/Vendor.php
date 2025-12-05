<?php

namespace TmrEcosystem\Purchase\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Vendor extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'uuid', 'code', 'name', 'tax_id', 'address',
        'contact_person', 'email', 'phone', 'credit_term_days'
    ];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->uuid = (string) Str::uuid();
        });
    }
}
