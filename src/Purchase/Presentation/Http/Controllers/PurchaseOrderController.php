<?php

namespace TmrEcosystem\Purchase\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Inertia\Inertia;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use TmrEcosystem\Purchase\Application\UseCases\CreatePurchaseOrderUseCase;
use TmrEcosystem\Purchase\Application\DTOs\PurchaseOrderData;
use TmrEcosystem\Purchase\Presentation\Http\Requests\StorePurchaseOrderRequest;
use TmrEcosystem\Purchase\Domain\Models\Vendor;
use TmrEcosystem\Inventory\Infrastructure\Persistence\Eloquent\Models\Item; // Using existing Inventory module
use TmrEcosystem\Purchase\Domain\Models\PurchaseOrder;

class PurchaseOrderController extends Controller
{

    public function index(Request $request)
    {
        $query = PurchaseOrder::with(['vendor'])
            ->withCount('items');

        // Filter Logic
        if ($request->filled('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('document_number', 'like', "%{$search}%")
                    ->orWhereHas('vendor', function ($q) use ($search) {
                        $q->where('name', 'like', "%{$search}%");
                    });
            });
        }

        // Sort Logic (แก้ไขส่วนนี้)
        if ($request->filled('sort')) { // ใช้ filled() แทน has() เพื่อป้องกันค่าว่าง
            $sort = $request->input('sort');
            $direction = strtolower($request->input('direction', 'asc'));

            // Validate direction
            if (!in_array($direction, ['asc', 'desc'])) {
                $direction = 'asc';
            }

            // Handle Special Sort Columns (เช่น การเรียงตามชื่อ Vendor)
            if ($sort === 'vendor_name') {
                $query->join('vendors', 'purchase_orders.vendor_id', '=', 'vendors.id')
                    ->orderBy('vendors.name', $direction)
                    ->select('purchase_orders.*'); // ต้อง Select กลับมาที่ PO เพื่อป้องกัน ID ชนกัน
            } else {
                // Default Sort
                $query->orderBy($sort, $direction);
            }
        } else {
            $query->latest();
        }

        // Stats Logic (คงเดิม)
        $stats = [
            'total_requests' => PurchaseOrder::count(),
            'rfq_pending'    => PurchaseOrder::where('status', 'draft')->count(),
            'completed'      => PurchaseOrder::where('status', 'received')->count(),
        ];

        return Inertia::render('Purchase/Orders/Index', [
            'orders' => $query->paginate(10)->withQueryString(),
            'filters' => $request->only(['search', 'sort', 'direction']),
            'stats' => $stats,
        ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(\TmrEcosystem\Purchase\Domain\Models\PurchaseOrder $order)
    {
        // โหลดข้อมูลความสัมพันธ์ที่ต้องใช้ในหน้า Show
        // items.item = โหลดรายการสินค้า และข้อมูลสินค้า (ชื่อ, part_number)
        // vendor = ข้อมูลผู้ขาย
        // creator = ผู้สร้างเอกสาร
        $order->load(['vendor', 'items.item', 'creator']);

        // ส่งข้อมูลไปยังหน้า Show.tsx
        return Inertia::render('Purchase/Orders/Show', [
            'order' => $order,
        ]);
    }

    public function create()
    {
        return Inertia::render('Purchase/Orders/Create', [
            'vendors' => Vendor::select('id', 'name', 'code')->orderBy('name')->get(),

            // --- แก้ไขตรงนี้ ---
            // เปลี่ยนจาก 'price' เป็น 'average_cost as price'
            'products' => Item::select('uuid as id', 'name', 'part_number', 'average_cost as price')
                ->where('can_purchase', true) // <--- เพิ่มเงื่อนไขนี้
                ->orderBy('name')
                ->get(),
            // ------------------
        ]);
    }

    public function store(StorePurchaseOrderRequest $request, CreatePurchaseOrderUseCase $useCase): RedirectResponse
    {
        $dto = PurchaseOrderData::fromRequest($request);
        $po = $useCase->execute($dto);

        return redirect()->route('purchase.orders.index')
            ->with('success', "Purchase Order {$po->document_number} created successfully.");
    }
}
