<?php

namespace TmrEcosystem\Shared\Infrastructure\Persistence\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use TmrEcosystem\Shared\Domain\ValueObjects\Money;

class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        if (is_null($value)) {
            return null;
        }

        // Database เก็บ int (cents), แปลงกลับเป็น Money Object
        return new Money((int) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?int
    {
        if (is_null($value)) {
            return null;
        }

        if ($value instanceof Money) {
            return $value->amount;
        }

        throw new \InvalidArgumentException('Value must be an instance of Money');
    }
}
