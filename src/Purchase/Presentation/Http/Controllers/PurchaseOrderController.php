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
    public function create()
    {
        return Inertia::render('Purchase/Orders/Create', [
            'vendors' => Vendor::select('id', 'name', 'code')->orderBy('name')->get(),

            // --- แก้ไขตรงนี้ ---
            // เปลี่ยนจาก 'price' เป็น 'average_cost as price'
            'products' => Item::select('uuid as id', 'name', 'part_number', 'average_cost as price')
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

    public function index(Request $request)
{
    $query = PurchaseOrder::with(['vendor'])
        ->withCount('items');

    // Filter Logic
    if ($request->has('search')) {
        $search = $request->input('search');
        $query->where(function($q) use ($search) {
            $q->where('document_number', 'like', "%{$search}%")
              ->orWhereHas('vendor', function($q) use ($search) {
                  $q->where('name', 'like', "%{$search}%");
              });
        });
    }

    // Sort Logic (Basic)
    if ($request->has('sort')) {
        $sort = $request->input('sort');
        $direction = $request->input('direction', 'asc');
        $query->orderBy($sort, $direction);
    } else {
        $query->latest();
    }

    return Inertia::render('Purchase/Orders/Index', [
        'orders' => $query->paginate(10)->withQueryString(),
        'filters' => $request->all(['search', 'sort', 'direction']),
    ]);
}
}
