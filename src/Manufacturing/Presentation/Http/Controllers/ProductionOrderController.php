<?php

namespace TmrEcosystem\Manufacturing\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use TmrEcosystem\Manufacturing\Application\UseCases\CreateProductionOrderUseCase;
use TmrEcosystem\Manufacturing\Domain\Models\ProductionOrder;
use TmrEcosystem\Manufacturing\Presentation\Http\Requests\StoreProductionOrderRequest;

class ProductionOrderController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->user()->company_id;

        $orders = ProductionOrder::with(['item', 'item.uom'])
            ->where('company_id', $companyId)
            // ... filter logic ...
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return Inertia::render('Manufacturing/ProductionOrder/Index', [
            'orders' => $orders,
            'filters' => $request->only(['search'])
        ]);
    }

    /**
     * แสดงหน้าเปิดใบสั่งผลิต
     */
    public function create(Request $request): Response
    {
        $companyId = $request->user()->company_id;

        // ดึงเฉพาะสินค้าที่มี BOM แล้วเท่านั้น (เพื่อป้องกันการเลือกผิด)
        $productsWithBom = DB::table('inventory_items')
            ->join('manufacturing_boms', 'inventory_items.uuid', '=', 'manufacturing_boms.item_uuid')
            ->where('inventory_items.company_id', $companyId)
            ->where('manufacturing_boms.is_default', true)
            ->where('manufacturing_boms.is_active', true)
            ->select('inventory_items.uuid as id', 'inventory_items.name', 'inventory_items.part_number')
            ->distinct()
            ->orderBy('inventory_items.name')
            ->get()
            ->map(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => "{$item->part_number} - {$item->name}",
                    'price' => 0 // Dummy
                ];
            });

        return Inertia::render('Manufacturing/ProductionOrder/Create', [
            'products' => $productsWithBom
        ]);
    }

    /**
     * บันทึกใบสั่งผลิต
     */
    public function store(
        StoreProductionOrderRequest $request,
        CreateProductionOrderUseCase $useCase
    ): RedirectResponse {
        try {
            $user = Auth::user();

            $order = $useCase->execute(
                $request->validated(),
                (string) $user->company_id,
                (string) $user->id
            );

            return redirect()->route('manufacturing.dashboard')
                ->with('success', "Production Order '{$order->order_number}' created successfully.");
        } catch (\Exception $e) {
            Log::error('Failed to create PO: ' . $e->getMessage());
            return back()->withInput()->with('error', $e->getMessage());
        }
    }
}
