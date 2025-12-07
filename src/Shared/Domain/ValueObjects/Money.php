<?php

namespace TmrEcosystem\Shared\Domain\ValueObjects;

use InvalidArgumentException;

class Money
{
    public function __construct(
        public readonly int $amount, // เก็บเป็น Cents เสมอ (เช่น 100.00 บาท = 10000)
        public readonly string $currency = 'THB'
    ) {
        if ($amount < 0) {
            throw new InvalidArgumentException("Money/Cost cannot be negative.");
        }
    }

    public function amount(): float
    {
        return $this->amount; // <-- (แก้ไข) จาก $this.amount
    }

    public static function fromFloat(float $amount, string $currency = 'THB'): self
    {
        return new self((int) round($amount * 100), $currency);
    }

    public function toFloat(): float
    {
        return $this->amount / 100;
    }

    public function add(Money $other): self
    {
        if ($this->currency !== $other->currency) {
            throw new InvalidArgumentException('Currency mismatch');
        }
        return new self($this->amount + $other->amount, $this->currency);
    }

    public function multiply(float $multiplier): self
    {
        return new self((int) round($this->amount * $multiplier), $this->currency);
    }

    // Helper สำหรับการแสดงผลใน Frontend
    public function format(): string
    {
        return number_format($this->toFloat(), 2) . ' ' . $this->currency;
    }
}
