<?php

namespace TmrEcosystem\Inventory\Application\UseCases\ManageItems;

use Exception;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use TmrEcosystem\Inventory\Application\DTOs\ItemData;
use TmrEcosystem\Inventory\Domain\Aggregates\Item;
use TmrEcosystem\Inventory\Domain\Exceptions\PartNumberAlreadyExistsException;
use TmrEcosystem\Inventory\Domain\Repositories\ItemRepositoryInterface;
use TmrEcosystem\Inventory\Domain\ValueObjects\ItemCode;
// Models
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\ItemImage;
use TmrEcosystem\Shared\Domain\ValueObjects\Money;

class UpdateItemUseCase
{
    public function __construct(
        protected ItemRepositoryInterface $itemRepository
    ) {
    }

    /**
     * @throws PartNumberAlreadyExistsException | Exception
     */
    // ✅ 1. เพิ่ม Argument $setPrimaryImageId ตรงนี้
    public function __invoke(string $uuid, ItemData $data, array $removedImageIds = [], ?int $setPrimaryImageId = null): Item
    {
        $item = $this->itemRepository->findByUuid($uuid);

        if (!$item) {
            throw new Exception("Item not found with UUID: {$uuid}");
        }

        // Check Duplicate Part Number
        $existingItem = $this->itemRepository->findByPartNumber($data->partNumber, $data->companyId);
        if ($existingItem && $existingItem->uuid() !== $uuid) {
            throw new PartNumberAlreadyExistsException("Part number '{$data->partNumber}' already exists.");
        }

        $partNumberVO = new ItemCode($data->partNumber);
        $averageCostVO = new Money($data->averageCost);

        // ✅ 2. เพิ่มตัวแปรเข้าไปใน use (...)
        return DB::transaction(function () use ($item, $data, $partNumberVO, $averageCostVO, $removedImageIds, $uuid, $setPrimaryImageId) {

            // 1. Update Core Data
            $item->updateDetails(
                partNumber: $partNumberVO,
                name: $data->name,
                uomId: $data->uomId,
                categoryId: $data->categoryId,
                averageCost: $averageCostVO,
                description: $data->description
            );
            $this->itemRepository->save($item);

            // 2. Delete Removed Images
            if (!empty($removedImageIds)) {
                $imagesToDelete = ItemImage::whereIn('id', $removedImageIds)
                    ->where('item_uuid', $uuid)
                    ->get();

                foreach ($imagesToDelete as $img) {
                    if (Storage::disk('public')->exists($img->path)) {
                        Storage::disk('public')->delete($img->path);
                    }
                    $img->delete();
                }
            }

            // 3. Upload New Images
            if (!empty($data->images)) {
                $lastSortOrder = ItemImage::where('item_uuid', $uuid)->max('sort_order') ?? -1;

                foreach ($data->images as $index => $imageFile) {
                    $path = $imageFile->store('items', 'public');

                    ItemImage::create([
                        'item_uuid' => $uuid,
                        'path' => $path,
                        'original_name' => $imageFile->getClientOriginalName(),
                        'is_primary' => false,
                        'sort_order' => $lastSortOrder + 1 + $index,
                    ]);
                }
            }

            // 4. ✅ Set Primary Image Logic
            if ($setPrimaryImageId) {
                // A. รีเซ็ตทุกรูปของสินค้านี้ให้เป็น false ก่อน
                ItemImage::where('item_uuid', $uuid)->update(['is_primary' => false]);

                // B. ตั้งค่ารูปที่เลือกให้เป็น true
                ItemImage::where('id', $setPrimaryImageId)
                    ->where('item_uuid', $uuid) // Security check
                    ->update(['is_primary' => true]);
            }

            return $item;
        });
    }
}
