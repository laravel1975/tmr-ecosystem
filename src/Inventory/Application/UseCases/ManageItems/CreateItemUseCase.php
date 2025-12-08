<?php

namespace TmrEcosystem\Inventory\Application\UseCases\ManageItems;

use Illuminate\Support\Facades\DB; // ✅ ใช้ Transaction
use Illuminate\Support\Facades\Storage; // ✅ ใช้ Storage
use TmrEcosystem\Inventory\Application\DTOs\ItemData;
use TmrEcosystem\Inventory\Domain\Aggregates\Item;
use TmrEcosystem\Inventory\Domain\Exceptions\PartNumberAlreadyExistsException;
use TmrEcosystem\Inventory\Domain\Repositories\ItemRepositoryInterface;
use TmrEcosystem\Inventory\Domain\ValueObjects\ItemCode;
// ✅ Import Image Model
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemImage;
use TmrEcosystem\Shared\Domain\ValueObjects\Money;

class CreateItemUseCase
{
    public function __construct(
        protected ItemRepositoryInterface $itemRepository
    ) {
    }

    public function __invoke(ItemData $data): Item
    {
        // 1. Validate Business Logic
        if ($this->itemRepository->partNumberExists($data->partNumber, $data->companyId)) {
            throw new PartNumberAlreadyExistsException(
                "Part number '{$data->partNumber}' already exists for this company."
            );
        }

        $partNumberVO = new ItemCode($data->partNumber);
        $averageCostVO = new Money($data->averageCost);
        $uuid = $this->itemRepository->nextUuid();

        // ✅ ใช้ Database Transaction เพื่อความชัวร์ (สร้าง Item + รูปต้องสำเร็จพร้อมกัน)
        return DB::transaction(function () use ($uuid, $data, $partNumberVO, $averageCostVO) {

            // 2. Create Aggregate
            $item = Item::create(
                uuid: $uuid,
                companyId: $data->companyId,
                partNumber: $partNumberVO,
                name: $data->name,
                uomId: $data->uomId,
                categoryId: $data->categoryId,
                averageCost: $averageCostVO,
                description: $data->description
            );

            // 3. Save Item
            $this->itemRepository->save($item);

            // 4. ✅ Process Images (Loop บันทึกรูป)
            if (!empty($data->images)) {
                foreach ($data->images as $index => $imageFile) {
                    // Upload to Storage (storage/app/public/items)
                    $path = $imageFile->store('items', 'public');

                    // Create Database Record
                    ItemImage::create([
                        'item_uuid' => $uuid,
                        'path' => $path,
                        'original_name' => $imageFile->getClientOriginalName(),
                        'is_primary' => $index === 0, // รูปแรกให้เป็นรูปหลัก
                        'sort_order' => $index,
                    ]);
                }
            }

            return $item;
        });
    }
}
