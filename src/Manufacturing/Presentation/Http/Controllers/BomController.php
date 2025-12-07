<?php

namespace TmrEcosystem\Manufacturing\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use TmrEcosystem\Manufacturing\Domain\Models\BillOfMaterial;
use TmrEcosystem\Manufacturing\Application\UseCases\CreateBomUseCase; // (สร้าง UseCase นี้ใน Step ถัดไป หรือจะเขียน Logic ในนี้ชั่วคราวก็ได้)
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Manufacturing\Presentation\Http\Requests\StoreBomRequest;

class BomController extends Controller
{
    public function __construct(
        protected ItemLookupServiceInterface $itemLookupService
    ) {}

    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;
        $search = $request->input('search');

        $boms = BillOfMaterial::with(['item', 'item.uom'])
            ->where('company_id', $companyId)
            ->when($search, function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                        ->orWhere('name', 'like', "%{$search}%")
                        ->orWhereHas('item', fn($q) => $q->where('name', 'like', "%{$search}%"));
                });
            })
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString();

        return Inertia::render('Manufacturing/BOM/Index', [
            'boms' => $boms,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * แสดงหน้าจอสร้าง BOM พร้อมข้อมูล Master Data ที่จำเป็น
     */
    public function create(Request $request): Response
    {
        $companyId = $request->user()->company_id;

        // ✅ Fix: Base Query พร้อม Join UOM
        $baseItemQuery = DB::table('inventory_items')
            ->leftJoin('inventory_uoms', 'inventory_items.uom_id', '=', 'inventory_uoms.id')
            ->where('inventory_items.company_id', $companyId)
            ->whereNull('inventory_items.deleted_at')
            ->orderBy('inventory_items.name');

        // Helper Map Function
        $mapItem = fn($item) => [
            'id' => $item->uuid,
            'name' => "{$item->part_number} - {$item->name}",
            'price' => (float)($item->target_price ?? 0), // ใช้ตัวแปรชั่วคราวรับค่า
            'uom' => $item->uom_symbol ?? '', // ✅ ใช้ Alias จากการ Join
        ];

        // 1. Finished Goods: สินค้าที่ผลิตได้
        $finishedGoods = (clone $baseItemQuery)
            // เช็คว่ามี column is_manufactured หรือไม่ (ถ้ายังไม่ได้ Migrate ให้ลบบรรทัดนี้ก่อน)
            ->where('inventory_items.is_manufactured', true)
            ->select(
                'inventory_items.uuid',
                'inventory_items.part_number',
                'inventory_items.name',
                'inventory_items.sale_price as target_price', // Finished Good ใช้ราคาขายแสดง
                'inventory_uoms.symbol as uom_symbol' // ✅ ดึง Symbol มาเป็น alias
            )
            ->get()
            ->map($mapItem);

        // 2. Raw Materials: วัตถุดิบ
        $rawMaterials = (clone $baseItemQuery)
            ->where('inventory_items.is_component', true)
            ->select(
                'inventory_items.uuid',
                'inventory_items.part_number',
                'inventory_items.name',
                'inventory_items.cost_price as target_price', // Raw Mat ใช้ราคาทุนแสดง
                'inventory_uoms.symbol as uom_symbol'
            )
            ->get()
            ->map($mapItem);

        // 3. By Products: ผลพลอยได้ (ใช้ Logic เดียวกับ Raw Materials หรือ Finished Goods ก็ได้)
        $byProducts = $rawMaterials;

        return Inertia::render('Manufacturing/BOM/Create', [
            'finishedGoods' => $finishedGoods,
            'rawMaterials' => $rawMaterials,
            'byProducts' => $byProducts,
        ]);
    }

    /**
     * บันทึก BOM ลงฐานข้อมูล
     */
    public function store(
        StoreBomRequest $request,
        CreateBomUseCase $useCase
    ): RedirectResponse {
        try {
            $user = Auth::user();

            // ✅ ส่ง Data ที่ Validate แล้วไปยัง UseCase
            // UseCase จะต้องรองรับ 'type' และ 'byproducts' (ต้องไปแก้ UseCase เพิ่มเติมตามแผน)
            $bom = $useCase->execute(
                $request->validated(),
                (string) $user->company_id,
                (string) $user->id
            );

            return redirect()->route('manufacturing.boms.index')
                ->with('success', "BOM '{$bom->code}' created successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to create BOM: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to create BOM: ' . $e->getMessage());
        }
    }
}
