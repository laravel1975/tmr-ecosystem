<?php

namespace TmrEcosystem\Logistics\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\ReturnNote;
use TmrEcosystem\Logistics\Infrastructure\Persistence\Models\ReturnEvidence;
use TmrEcosystem\Stock\Domain\Repositories\StockLevelRepositoryInterface;
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;

class ReturnNoteController extends Controller
{
    public function __construct(
        private StockLevelRepositoryInterface $stockRepo,
        private ItemLookupServiceInterface $itemLookupService
    ) {}

    public function index(Request $request)
    {
        $query = ReturnNote::query()
            ->with(['order.customer'])
            ->select('logistics_return_notes.*');

        if ($request->search) {
            $query->where('return_number', 'like', "%{$request->search}%")
                ->orWhereHas('order', fn($q) => $q->where('order_number', 'like', "%{$request->search}%"));
        }

        $notes = $query->orderBy('created_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn($note) => [
                'id' => $note->id,
                'return_number' => $note->return_number,
                'order_number' => $note->order->order_number ?? '-',
                'customer_name' => $note->order->customer->name ?? 'N/A',
                'status' => $note->status,
                'reason' => $note->reason,
                'created_at' => $note->created_at->format('d/m/Y H:i'),
            ]);

        return Inertia::render('Logistics/ReturnNotes/Index', [
            'returnNotes' => $notes,
            'filters' => $request->only(['search']),
        ]);
    }

    public function show(string $id)
    {
        // ✅ Eager Load 'evidenceImages' เพื่อนำไปเช็คและแสดงผล
        $returnNote = ReturnNote::with(['items', 'order.customer', 'evidenceImages'])->findOrFail($id);

        $items = $returnNote->items->map(function ($item) {
            $itemDto = $this->itemLookupService->findByPartNumber($item->product_id);
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $itemDto ? $itemDto->name : $item->product_id,
                'quantity' => $item->quantity,
                'image_url' => $itemDto ? $itemDto->imageUrl : null,
            ];
        });

        // Map ข้อมูล Evidence ส่งไป Frontend
        $evidences = $returnNote->evidenceImages->map(fn($e) => [
            'id' => $e->id,
            'url' => asset('storage/' . $e->path),
            'description' => $e->description
        ]);

        return Inertia::render('Logistics/ReturnNotes/Process', [
            'returnNote' => [
                'id' => $returnNote->id,
                'return_number' => $returnNote->return_number,
                'order_number' => $returnNote->order->order_number ?? '-',
                'customer_name' => $returnNote->order->customer->name ?? 'N/A',
                'status' => $returnNote->status,
                'reason' => $returnNote->reason,
                'created_at' => $returnNote->created_at->format('d/m/Y H:i'),
            ],
            'items' => $items,
            'evidences' => $evidences
        ]);
    }

    public function confirm(Request $request, string $id)
    {
        $returnNote = ReturnNote::with(['items', 'order', 'evidenceImages'])->findOrFail($id);

        if ($returnNote->status !== 'pending') {
            return back()->with('error', 'เอกสารนี้ถูกดำเนินการไปแล้ว');
        }

        // ✅ STRICT VALIDATION
        if ($returnNote->evidenceImages->count() === 0) {
            return back()->with('error', 'กรุณาอัปโหลดรูปภาพหลักฐานสภาพสินค้า (Evidence) อย่างน้อย 1 รูป ก่อนยืนยันการรับคืน');
        }

        DB::transaction(function () use ($returnNote) {
            $order = $returnNote->order;
            $warehouseId = $order->warehouse_id;
            $companyId = $order->company_id;

            // ตรวจสอบว่าเป็น Internal Return (เช่น ของเสียจากการ Unload)
            $isInternalReturn = str_contains($returnNote->reason, 'Cancel') ||
                str_contains($returnNote->reason, 'Unload') ||
                ($order && $order->status === 'cancelled');

            // 1. กำหนด Location ปลายทาง (GENERAL หรือ SCRAP)
            $targetLocationCode = 'GENERAL';
            if (stripos($returnNote->reason, 'Damaged') !== false ||
                stripos($returnNote->reason, 'เสีย') !== false ||
                stripos($returnNote->reason, 'ชำรุด') !== false) {
                $targetLocationCode = 'SCRAP';
            }

            // หา UUID ของ Location
            $targetLocUuid = DB::table('warehouse_storage_locations')->where('warehouse_uuid', $warehouseId)->where('code', $targetLocationCode)->value('uuid');
            $generalLocUuid = DB::table('warehouse_storage_locations')->where('warehouse_uuid', $warehouseId)->where('code', 'GENERAL')->value('uuid');

            // Fallback: ถ้าหา SCRAP ไม่เจอ ให้ลง GENERAL
            if (!$targetLocUuid) $targetLocUuid = $generalLocUuid;

            foreach ($returnNote->items as $returnItem) {
                $itemDto = $this->itemLookupService->findByPartNumber($returnItem->product_id);
                if (!$itemDto) continue;

                // --- A. จัดการ STOCK (INVENTORY) ---

                // 1. เตรียม Stock Level ปลายทาง (Target) - รับของเข้า
                // ✅ แก้ไข: ลบเงื่อนไข !$isInternalReturn ออก เพื่อให้สร้าง Stock ที่ SCRAP ได้เสมอ
                $targetStock = $this->stockRepo->findByLocation($itemDto->uuid, $targetLocUuid, $companyId);
                if (!$targetStock) {
                    $targetStock = \TmrEcosystem\Stock\Domain\Aggregates\StockLevel::create(
                        uuid: $this->stockRepo->nextUuid(),
                        companyId: $companyId,
                        itemUuid: $itemDto->uuid,
                        warehouseUuid: $warehouseId,
                        locationUuid: $targetLocUuid
                    );
                    $this->stockRepo->save($targetStock, []);
                }

                // รับของเข้าปลายทาง (เพิ่ม On Hand)
                $targetStock->receive(
                    (float)$returnItem->quantity,
                    auth()->id(),
                    "Return Confirmed: {$returnNote->return_number} to {$targetLocationCode}"
                );
                $this->stockRepo->save($targetStock, []);


                // 2. จัดการ Stock ต้นทาง (Source) - กรณี Internal Return (Unload)
                // ต้องไปตัด Hard Reserve ที่ค้างอยู่ออก (เสมือนว่าเบิกออกไปแล้ว แต่แทนที่จะไปหาลูกค้า ดันไปลงถัง Scrap แทน)
                if ($isInternalReturn) {
                    $sourceStock = $this->stockRepo->findByLocation($itemDto->uuid, $generalLocUuid, $companyId);

                    if ($sourceStock) {
                        // ✅ แก้ไข: ใช้ shipReserved เพื่อตัดทั้ง Reserved และ OnHand ออกจาก GENERAL
                        // (เพราะเราย้าย OnHand ไปอยู่ที่ SCRAP/Target แล้วในขั้นตอนที่ 1)
                        try {
                            $sourceStock->shipReserved(
                                (float)$returnItem->quantity,
                                auth()->id(),
                                "Internal Return Transfer to {$targetLocationCode}"
                            );
                            $this->stockRepo->save($sourceStock, []);
                        } catch (\Exception $e) {
                            // กรณีตัด Reserve ไม่ผ่าน (เช่น Reserve หายไปแล้ว) ให้ข้ามไป ไม่ต้อง Crash
                            \Illuminate\Support\Facades\Log::warning("Could not shipReserved for return: " . $e->getMessage());
                        }
                    }
                }

                // --- B. จัดการ SALES ORDER (คืนยอด Shipped) ---
                // ✅ เพิ่ม: ตรวจสอบและคืนยอด Sales Order หากยังไม่ได้คืน
                if ($returnItem->sales_order_item_id) { // ต้องมั่นใจว่าใน Table ReturnNoteItem มี column นี้ (หรือ Join เอา)
                     // หมายเหตุ: ปกติ ReturnNoteItem อาจไม่มี sales_order_item_id เก็บไว้โดยตรง
                     // ถ้าไม่มี ต้องไปหาจาก picking_slip_items หรือ order_items

                     // Fallback: หาจาก Product ID ใน Order นี้
                     $soItem = DB::table('sales_order_items')
                        ->where('order_id', $order->id)
                        ->where('product_id', $returnItem->product_id)
                        ->first();

                     if ($soItem) {
                         // คืนยอด Shipped (เพื่อให้ Sale เปิดบิลส่งใหม่ได้)
                         // ใช้ decrement เพื่อความปลอดภัย (ไม่ให้ต่ำกว่า 0)
                         if ($soItem->qty_shipped > 0) {
                             DB::table('sales_order_items')
                                ->where('id', $soItem->id)
                                ->decrement('qty_shipped', $returnItem->quantity);
                         }
                     }
                }
            }

            // อัปเดตสถานะ Return Note
            $returnNote->update(['status' => 'completed']);

            // อัปเดตสถานะ Sales Order (ถ้าจำเป็น)
            if ($order->status === 'completed') {
                $order->update(['status' => 'partially_shipped']); // ถอยสถานะกลับมา
            }
        });

        return to_route('logistics.return-notes.index')
            ->with('success', 'บันทึกการรับคืนสินค้าเรียบร้อยแล้ว (Stock & Order Updated)');
    }

    public function uploadEvidence(Request $request, string $id)
    {
        $request->validate([
            'images' => 'required|array',
            'images.*' => 'image|max:10240' // Max 10MB
        ]);

        $returnNote = ReturnNote::findOrFail($id);

        foreach ($request->file('images') as $file) {
            $path = $file->store('returns/evidence', 'public');

            ReturnEvidence::create([
                'return_note_id' => $returnNote->id,
                'path' => $path,
                'user_id' => auth()->id()
            ]);
        }

        return back()->with('success', 'Uploaded evidence successfully.');
    }

    public function removeEvidence(string $evidenceId)
    {
        $evidence = ReturnEvidence::findOrFail($evidenceId);

        if (Storage::disk('public')->exists($evidence->path)) {
            Storage::disk('public')->delete($evidence->path);
        }

        $evidence->delete();

        return back()->with('success', 'Evidence removed.');
    }

    public function reViewItem(string $id)
    {
        // โหลด Evidence Images มาด้วย
        $returnNote = ReturnNote::with(['items', 'order.customer', 'evidenceImages'])->findOrFail($id);

        $items = $returnNote->items->map(function ($item) {
            // 1. ดึงข้อมูล Master Data
            $itemDto = $this->itemLookupService->findByPartNumber($item->product_id);

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $itemDto ? $itemDto->name : $item->product_id,
                'quantity' => $item->quantity,

                // ✅ เพิ่มข้อมูลรูปภาพ
                'image_url' => $itemDto ? $itemDto->imageUrl : null,
            ];
        });

        // เตรียมข้อมูลรูปหลักฐาน (ถ้าต้องการแสดงในใบ Print)
        $evidences = $returnNote->evidenceImages->map(fn($e) => [
            'id' => $e->id,
            'url' => $e->url // ใช้ Accessor getUrlAttribute หรือ asset()
        ]);

        return Inertia::render('Logistics/ReturnNotes/Show', [
            'returnNote' => [
                'id' => $returnNote->id,
                'return_number' => $returnNote->return_number,
                'status' => $returnNote->status,
                'reason' => $returnNote->reason,
                'created_at' => $returnNote->created_at->toIso8601String(),

                // Customer & Order Info
                'customer_name' => $returnNote->order->customer->name ?? 'N/A',
                'order_number' => $returnNote->order->order_number ?? '-',

                'items' => $items,
                'evidences' => $evidences // ส่งไปเผื่อใช้
            ]
        ]);
    }
}
