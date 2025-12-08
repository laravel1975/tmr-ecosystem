<?php

namespace TmrEcosystem\Inventory\Domain\Aggregates;

use TmrEcosystem\Inventory\Domain\ValueObjects\ItemCode;
use TmrEcosystem\Shared\Domain\ValueObjects\Money;

class Item
{
    private ?int $dbId;
    private string $uuid;
    private string $companyId;
    private ItemCode $partNumber;
    private string $name;

    // ✅ เปลี่ยนเป็น ID
    private string $uomId;
    private ?string $categoryId;

    private Money $averageCost;
    private ?string $description;

    public function __construct(
        ?int $dbId,
        string $uuid,
        string $companyId,
        ItemCode $partNumber,
        string $name,
        string $uomId, // ID
        ?string $categoryId, // ID
        Money $averageCost,
        ?string $description
    ) {
        $this->dbId = $dbId;
        $this->uuid = $uuid;
        $this->companyId = $companyId;
        $this->partNumber = $partNumber;
        $this->name = $name;
        $this->uomId = $uomId;
        $this->categoryId = $categoryId;
        $this->averageCost = $averageCost;
        $this->description = $description;
    }

    public static function create(
        string $uuid,
        string $companyId,
        ItemCode $partNumber,
        string $name,
        string $uomId,
        ?string $categoryId,
        Money $averageCost,
        ?string $description
    ): self {
        return new self(
            null,
            $uuid,
            $companyId,
            $partNumber,
            $name,
            $uomId,
            $categoryId,
            $averageCost,
            $description
        );
    }

    public function updateDetails(
        ItemCode $partNumber,
        string $name,
        string $uomId,
        ?string $categoryId,
        Money $averageCost,
        ?string $description
    ): void {
        $this->partNumber = $partNumber;
        $this->name = $name;
        $this->uomId = $uomId;
        $this->categoryId = $categoryId;
        $this->averageCost = $averageCost;
        $this->description = $description;
    }

    public function dbId(): ?int { return $this->dbId; }
    public function uuid(): string { return $this->uuid; }
    public function companyId(): string { return $this->companyId; }
    public function partNumber(): string { return $this->partNumber->value(); }
    public function name(): string { return $this->name; }

    // ✅ Getter คืนค่า ID
    public function uomId(): string { return $this->uomId; }
    public function categoryId(): ?string { return $this->categoryId; }

    public function averageCost(): float { return $this->averageCost->amount(); }
    public function description(): ?string { return $this->description; }
}
