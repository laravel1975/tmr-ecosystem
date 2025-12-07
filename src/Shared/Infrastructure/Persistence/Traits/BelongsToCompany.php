<?php

namespace TmrEcosystem\Shared\Infrastructure\Persistence\Traits;

use TmrEcosystem\Shared\Infrastructure\Persistence\Scopes\CompanyScope;
use TmrEcosystem\Shared\Domain\Models\Company;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Auth;

trait BelongsToCompany
{
    public static function bootBelongsToCompany(): void
    {
        static::addGlobalScope(new CompanyScope);

        // Auto-fill company_id when creating
        static::creating(function ($model) {
            if (Auth::hasUser() && !$model->company_id) {
                $model->company_id = Auth::user()->company_id;
            }
        });
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }
}
