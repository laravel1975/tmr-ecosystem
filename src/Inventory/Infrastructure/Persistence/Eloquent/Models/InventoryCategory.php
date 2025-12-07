<?php

namespace TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use src\Shared\Domain\Models\Company;
use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;

class InventoryCategory extends Model
{
    use SoftDeletes, HasUuids;

    protected $table = 'inventory_categories';
    protected $fillable = ['id', 'company_id', 'name', 'code', 'parent_id'];

    protected static function booted(): void
    {
        static::addGlobalScope(new CompanyScope);
    }

    public function company()
    {
        return $this->belongsTo(Company::class);
    }
}
