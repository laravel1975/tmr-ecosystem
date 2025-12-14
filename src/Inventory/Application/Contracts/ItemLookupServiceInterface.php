<?php

namespace TmrEcosystem\Inventory\Application\Contracts;

use TmrEcosystem\Inventory\Application\DTOs\PublicItemDto;

interface ItemLookupServiceInterface
{
    /**
     * ค้นหาสินค้าชิ้นเดียวด้วย Part Number
     */
    public function findByPartNumber(string $partNumber): ?PublicItemDto;

    public function findByUuid(string $uuid): ?PublicItemDto;

    /**
     * ค้นหาสินค้าหลายชิ้นพร้อมกัน (Batch Query เพื่อ Performance)
     * @param array $partNumbers รายการ Part Number ที่ต้องการหา
     * @return array<string, PublicItemDto> Key คือ Part Number
     */
    public function getByPartNumbers(array $partNumbers): array;
    /**
     * ✅ ค้นหาสินค้าสำหรับ Dropdown (คืนค่ารายการสินค้าพร้อมรูป)
     * @param string $search คำค้นหา (Part No หรือ Name)
     * @param array $includeIds ID ที่ต้องรวมมาด้วยเสมอ (เช่น สินค้าที่เลือกไว้แล้ว)
     * @return PublicItemDto[]
     */

    public function searchItems(string $search = '', array $includeIds = []): array;
}
