<?php

namespace TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;

class StorageLocationModel extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'warehouse_storage_locations';

    // Primary Key ของเราคือ 'uuid' ใน Domain แต่ใน DB คือ 'id'
    // Eloquent ปกติใช้ id, แต่เรา map ผ่าน Mapper

    protected $fillable = [
        'uuid',
        'warehouse_uuid',
        'code',
        'barcode',
        'type',
        'description',
        'is_active',
        'max_capacity',
        'is_full',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_full' => 'boolean',     
        'max_capacity' => 'float',  
    ];

    public function uniqueIds(): array
    {
        return ['uuid'];
    }

    // Relation กลับหา Warehouse
    public function warehouse()
    {
        return $this->belongsTo(WarehouseModel::class, 'warehouse_uuid', 'uuid');
    }
}
