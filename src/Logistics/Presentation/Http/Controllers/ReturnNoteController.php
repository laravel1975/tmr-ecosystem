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

        // ✅ STRICT VALIDATION: ต้องมีรูปภาพหลักฐานอย่างน้อย 1 รูป
        if ($returnNote->evidenceImages->count() === 0) {
            return back()->with('error', 'กรุณาอัปโหลดรูปภาพหลักฐานสภาพสินค้า (Evidence) อย่างน้อย 1 รูป ก่อนยืนยันการรับคืน');
        }

        DB::transaction(function () use ($returnNote) {
            $order = $returnNote->order;

            // เช็คว่าเป็น Internal Return (คืนยอดจองภายใน) หรือ Customer Return (รับของเข้า)
            $isInternalReturn = str_contains($returnNote->reason, 'Cancel') ||
                str_contains($returnNote->reason, 'Unload') ||
                ($order && $order->status === 'cancelled');

            $warehouseId = $order->warehouse_id;
            $companyId = $order->company_id;

            // --- ✅ FIX: Logic รับคืนสต็อกเข้า GENERAL ---

            // 1. หา Location 'GENERAL'
            $locationUuid = DB::table('warehouse_storage_locations')
                ->where('warehouse_uuid', $warehouseId)
                ->where('code', 'GENERAL')
                ->value('uuid');

            if ($locationUuid) {
                foreach ($returnNote->items as $returnItem) {
                    $inventoryItemDto = $this->itemLookupService->findByPartNumber($returnItem->product_id);

                    if ($inventoryItemDto) {
                        // 2. ค้นหา StockLevel ด้วย Location
                        $stockLevel = $this->stockRepo->findByLocation(
                            $inventoryItemDto->uuid,
                            $locationUuid, // ✅ GENERAL
                            $companyId
                        );

                        // 3. ถ้าไม่มี Stock Level (กรณีของหมดเกลี้ยง หรือเป็นสินค้าใหม่) ต้องสร้างใหม่
                        if (!$stockLevel && !$isInternalReturn) {
                            $stockLevel = \TmrEcosystem\Stock\Domain\Aggregates\StockLevel::create(
                                uuid: $this->stockRepo->nextUuid(),
                                companyId: $companyId,
                                itemUuid: $inventoryItemDto->uuid,
                                warehouseUuid: $warehouseId,
                                locationUuid: $locationUuid
                            );
                            $this->stockRepo->save($stockLevel, []);
                        }

                        if ($stockLevel) {
                            if ($isInternalReturn) {
                                // กรณี Internal Return (เช่น ยกเลิก Picking): ปลด Hard Reserve คืนเป็น Available
                                $stockLevel->releaseHardReservation((float)$returnItem->quantity);
                                \Illuminate\Support\Facades\Log::info("Stock: Released Hard Reserve for {$returnItem->product_id}");
                            } else {
                                // กรณี Customer Return (ลูกค้าคืนของ): รับของเข้า (เพิ่ม On Hand)
                                $stockLevel->receive(
                                    (float)$returnItem->quantity,
                                    auth()->id(),
                                    "Restock from Return Note: {$returnNote->return_number}"
                                );
                                \Illuminate\Support\Facades\Log::info("Stock: Received Stock for {$returnItem->product_id}");
                            }
                            $this->stockRepo->save($stockLevel, []);
                        }
                    }
                }
            }
            // -----------------------------------------------------

            $returnNote->update(['status' => 'completed']);
        });

        return to_route('logistics.return-notes.index')
            ->with('success', 'บันทึกการรับคืนสินค้าเรียบร้อยแล้ว (Stock Updated)');
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
