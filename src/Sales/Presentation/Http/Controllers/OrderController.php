<?php

namespace TmrEcosystem\Sales\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

// DTOs & UseCases
use TmrEcosystem\Sales\Application\DTOs\CreateOrderDto;
use TmrEcosystem\Sales\Application\DTOs\UpdateOrderDto;
use TmrEcosystem\Sales\Application\UseCases\PlaceOrderUseCase;
use TmrEcosystem\Sales\Application\UseCases\UpdateOrderUseCase;
use TmrEcosystem\Sales\Application\UseCases\CancelOrderUseCase;

// Domain & Repositories
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;

// Persistence Models
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;
use TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel;

// ✅ Import Service Interface
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Stock\Application\Contracts\StockCheckServiceInterface;
// ✅ [เพิ่ม] Traceability Service
use TmrEcosystem\Sales\Application\Services\OrderTraceabilityService;

class OrderController extends Controller
{
    // ✅ Inject Service
    public function __construct(
        private PlaceOrderUseCase $placeOrderUseCase,
        private CancelOrderUseCase $cancelOrderUseCase,
        private OrderRepositoryInterface $orderRepository,
        private ItemLookupServiceInterface $itemLookupService,
        private StockCheckServiceInterface $stockCheckService,
        private OrderTraceabilityService $traceabilityService // ✅ [เพิ่ม] Inject Service ใหม่
    ) {}

    public function dashboard()
    {
        return Inertia::render('Sales/Dashboard');
    }

    public function index(Request $request)
    {
        $query = SalesOrderModel::query()
            ->leftJoin('customers', 'sales_orders.customer_id', '=', 'customers.id')
            ->withSum([
                'items as shipped_total_amount' => function ($q) {
                    $q->select(DB::raw('SUM(qty_shipped * unit_price)'));
                }
            ], 'subtotal')
            ->select('sales_orders.*', 'customers.name as customer_name', 'customers.code as customer_code');

        if ($request->search) {
            $query->where('sales_orders.order_number', 'like', "%{$request->search}%")
                ->orWhere('customers.name', 'like', "%{$request->search}%");
        }

        return Inertia::render('Sales/Index', [
            'orders' => $query->latest('sales_orders.created_at')->paginate(10)->withQueryString()->through(fn($order) => [
                'id' => $order->id,
                'order_number' => $order->order_number,
                'customer_name' => $order->customer_name,
                'customer_code' => $order->customer_code,
                'status' => $order->status,
                'created_at' => $order->created_at->format('d/m/Y'),
                'total_amount' => $order->total_amount,
                'shipped_amount' => $order->shipped_total_amount ?? 0,
                'display_amount' => $order->status === 'cancelled' ? 0 : $order->total_amount,
            ]),
            'filters' => $request->only(['search']),
        ]);
    }

    // --- Helper methods ---
    private function getCustomerOptions()
    {
        return Customer::query()->orderBy('name')->get()->map(fn($c) => [
            'id' => $c->id,
            'name' => "{$c->name} ({$c->code})",
            'payment_terms' => $c->credit_term ?? 'immediate',
            'address' => $c->address
        ]);
    }

    private function getProductOptions(array $orderProductIds = [])
    {
        // 1. เรียก Service ค้นหาสินค้า
        $items = $this->itemLookupService->searchItems('', $orderProductIds);

        // 2. ✅ [FIX] ดึง Warehouse ID จริงจาก DB (แทนการใช้ 'DEFAULT_WAREHOUSE')
        // ถ้ามี User login ควรดึงจาก auth()->user()->warehouse_id
        $defaultWarehouse = WarehouseModel::first();
        $warehouseId = $defaultWarehouse ? $defaultWarehouse->uuid : 'DEFAULT_WAREHOUSE';

        // 3. เตรียมข้อมูล Part Numbers สำหรับ Batch Query
        $partNumbers = collect($items)->pluck('partNumber')->toArray();

        // 4. ดึงข้อมูล Stock (Batch)
        $stockMap = $this->stockCheckService->checkAvailabilityBatch(
            $partNumbers,
            $warehouseId // ✅ ส่ง ID จริงไป
        );

        return collect($items)
            ->filter(fn($dto) => $dto->canSell ?? true)
            ->map(fn($dto) => [
                'id' => $dto->partNumber,
                'uuid' => $dto->uuid,
                'name' => "{$dto->name} ({$dto->partNumber})",
                'price' => $dto->price,

                // ✅ ดึงค่า Stock จาก Map
                'stock' => $stockMap[$dto->partNumber] ?? 0,

                'image_url' => $dto->imageUrl
            ])
            ->values()
            ->toArray();
    }

    public function create()
    {
        return Inertia::render('Sales/CreateOrder', [
            'customers' => $this->getCustomerOptions(),
            // ✅ เรียกฟังก์ชันใหม่ที่ผ่าน Service
            'availableProducts' => $this->getProductOptions(),
            'newOrderNumber' => 'New',
            'order' => null,
            'paginationInfo' => null,
        ]);
    }

    public function store(Request $request, PlaceOrderUseCase $useCase)
    {
        $validated = $request->validate([
            'customer_id' => 'required',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|integer|min:1',
            'note' => 'nullable|string',
            'payment_terms' => 'nullable|string',
        ]);

        $user = $request->user();
        $companyId = $user->company_id ?? 'DEFAULT_COMPANY';

        $warehouse = WarehouseModel::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (!$warehouse) {
            return back()->with('error', 'ไม่พบคลังสินค้า (Warehouse) ในระบบ กรุณาสร้างคลังสินค้าก่อนทำรายการ');
        }

        $warehouseId = $warehouse->uuid;

        try {
            $dto = CreateOrderDto::fromRequest($validated, $companyId, $warehouseId);
            $order = $useCase->handle($dto);

            if ($request->input('action') === 'confirm') {
                SalesOrderModel::where('id', $order->getId())->update(['status' => 'confirmed']);
                OrderConfirmed::dispatch($order);
            }

            return to_route('sales.orders.show', $order->getId())
                ->with('success', 'Order created successfully!');
        } catch (Exception $e) {
            Log::error("Create Order Failed: " . $e->getMessage());

            if (isset($order)) {
                SalesOrderModel::where('id', $order->getId())->update(['status' => 'draft']);
                return to_route('sales.orders.show', $order->getId())
                    ->with('error', 'บันทึกออเดอร์แล้ว แต่ยืนยันไม่ได้: ' . $e->getMessage());
            }

            return back()->with('error', 'Failed to create order: ' . $e->getMessage());
        }
    }

    // ✅ [Update] ปรับปรุงเมธอด show เพื่อคำนวณสถานะการจัดส่ง + Timeline
    public function show(string $id, OrderRepositoryInterface $repo)
    {
        $order = $repo->findById($id);
        $orderModel = SalesOrderModel::withCount('pickingSlips')->find($id);

        if (!$order) abort(404);

        $allIds = SalesOrderModel::orderBy('created_at', 'desc')->pluck('id')->toArray();
        $currentIndex = array_search($id, $allIds);

        $paginationInfo = [
            'current_index' => $currentIndex + 1,
            'total' => count($allIds),
            'prev_id' => $allIds[$currentIndex - 1] ?? null,
            'next_id' => $allIds[$currentIndex + 1] ?? null,
        ];

        $orderProductIds = $order->getItems()->map(fn($item) => $item->productId)->toArray();
        $productDetails = $this->itemLookupService->getByPartNumbers($orderProductIds);

        // ✅ คำนวณสถานะการจัดส่ง (Shipping Status Logic)
        $totalQty = 0;
        $totalShipped = 0;
        $isFullyShipped = true;
        $hasShippedItems = false;

        $itemsData = $order->getItems()->map(function ($item) use ($productDetails, &$totalQty, &$totalShipped, &$isFullyShipped, &$hasShippedItems) {
            $qty = $item->quantity;
            $shipped = $item->qtyShipped;

            $totalQty += $qty;
            $totalShipped += $shipped;

            if ($shipped > 0) {
                $hasShippedItems = true;
            }
            if ($shipped < $qty) {
                $isFullyShipped = false; // ถ้ามีรายการไหนยังส่งไม่ครบ ก็ถือว่ายังไม่ Fully Shipped
            }

            return [
                'id' => $item->id,
                'product_id' => $item->productId,
                'description' => $item->productName,
                'quantity' => $qty,
                'qty_shipped' => $shipped,
                'unit_price' => $item->unitPrice,
                'total' => $item->total(),
                'image_url' => $productDetails[$item->productId]->imageUrl ?? null,
            ];
        });

        // Handle edge case: empty order is not fully shipped in business sense (or handled by validation)
        if ($order->getItems()->isEmpty()) {
            $isFullyShipped = false;
        }

        // ✅ [เพิ่ม] เรียกข้อมูล Timeline จาก Service
        $timelineData = $this->traceabilityService->getOrderTimeline($id);

        return Inertia::render('Sales/CreateOrder', [
            'customers' => $this->getCustomerOptions(),
            'availableProducts' => $this->getProductOptions($orderProductIds),
            'paginationInfo' => $paginationInfo,
            'order' => [
                'id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'customer_id' => $order->getCustomerId(),
                'status' => $order->getStatus()->value,
                'note' => $order->getNote(),
                'payment_terms' => $order->getPaymentTerms(),
                'total_amount' => $order->getTotalAmount(),
                'picking_count' => $orderModel->picking_slips_count ?? 0,
                'items' => $itemsData,

                // ✅ ส่ง Flags ไป Frontend
                'is_fully_shipped' => $isFullyShipped,
                'has_shipped_items' => $hasShippedItems,
                'shipping_progress' => $totalQty > 0 ? round(($totalShipped / $totalQty) * 100) : 0,

                // ✅ ส่ง Timeline ไป Frontend
                'timeline' => $timelineData
            ]
        ]);
    }

    public function update(
        Request $request,
        string $id,
        UpdateOrderUseCase $useCase,
        OrderRepositoryInterface $repo
    ) {
        $request->validate([
            'customer_id' => 'required',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|integer|min:0',
        ]);

        try {
            $dto = UpdateOrderDto::fromRequest($request);
            $useCase->handle($id, $dto);

            if ($request->input('action') === 'confirm') {
                $currentStatus = SalesOrderModel::where('id', $id)->value('status');

                if ($currentStatus !== 'confirmed') {
                    SalesOrderModel::where('id', $id)->update(['status' => 'confirmed']);
                    $order = $repo->findById($id);
                    OrderConfirmed::dispatch($order);

                    return to_route('sales.orders.show', $id)
                        ->with('success', 'Order confirmed & Stock reserved successfully!');
                } else {
                    return to_route('sales.orders.show', $id)
                        ->with('success', 'Order updated successfully!');
                }
            }

            return to_route('sales.orders.show', $id)
                ->with('success', 'Order updated successfully!');
        } catch (Exception $e) {
            Log::warning("Order Update/Confirm Failed: " . $e->getMessage());
            return back()->with('error', 'Operation Failed: ' . $e->getMessage());
        }
    }

    public function cancel(string $id, CancelOrderUseCase $useCase)
    {
        try {
            $useCase->handle($id);
            return back()->with('success', 'Order cancelled successfully.');
        } catch (Exception $e) {
            return back()->with('error', 'Cancel Failed: ' . $e->getMessage());
        }
    }

    public function destroy(string $id, OrderRepositoryInterface $repo)
    {
        try {
            $order = $repo->findById($id);
            if (!$order || $order->getStatus()->value !== 'draft') {
                abort(403, 'Only draft orders can be deleted.');
            }

            SalesOrderModel::destroy($id);
            return to_route('sales.index')->with('success', 'Order deleted.');
        } catch (Exception $e) {
            return back()->with('error', 'Delete Failed: ' . $e->getMessage());
        }
    }
}
