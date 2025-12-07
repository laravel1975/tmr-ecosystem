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

        $boms = BillOfMaterial::with(['item', 'item.uom']) // Eager Load
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
     * แสดงหน้าจอสร้าง BOM
     */
    public function create(Request $request): Response
    {
        // ดึงสินค้าเฉพาะบริษัทที่ Login อยู่
        $companyId = $request->user()->company_id;

        $products = DB::table('inventory_items')
            ->where('company_id', $companyId)
            ->whereNull('deleted_at')
            ->select('uuid as id', 'name', 'part_number', 'sale_price as price', 'cost_price')
            // ปรับ select ให้ตรงกับ Interface Product ใน React
            ->orderBy('name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => "{$item->part_number} - {$item->name}", // แสดงรหัสคู่ชื่อ
                    'price' => $item->price ?? 0,
                    // 'stock' => ... ถ้าต้องการโชว์สต็อก
                ];
            });

        return Inertia::render('Manufacturing/BOM/Create', [
            'products' => $products
        ]);
    }

    /**
     * Store a newly created BOM in storage.
     */
    public function store(
        StoreBomRequest $request,
        CreateBomUseCase $useCase
    ): RedirectResponse {
        try {
            $user = Auth::user();

            // เรียก UseCase
            $bom = $useCase->execute(
                $request->validated(),
                (string) $user->company_id,
                (string) $user->id
            );

            return redirect()->route('manufacturing.dashboard')
                ->with('success', "BOM '{$bom->code}' created successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to create BOM: ' . $e->getMessage());
            return back()->withInput()->with('error', 'Failed to create BOM. Please try again.');
        }
    }
}
