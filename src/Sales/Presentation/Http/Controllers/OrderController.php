<?php

namespace TmrEcosystem\Sales\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;
use Inertia\Response;
// DTOs & UseCases
use TmrEcosystem\Sales\Application\DTOs\CreateOrderDto;
use TmrEcosystem\Sales\Application\DTOs\UpdateOrderDto;
use TmrEcosystem\Sales\Application\UseCases\PlaceOrderUseCase;
use TmrEcosystem\Sales\Application\UseCases\UpdateOrderUseCase;
use TmrEcosystem\Sales\Application\UseCases\CancelOrderUseCase;

// Domain & Repositories
use TmrEcosystem\Sales\Domain\Repositories\OrderRepositoryInterface;
use TmrEcosystem\Sales\Domain\Events\OrderConfirmed;
use TmrEcosystem\Sales\Domain\ValueObjects\OrderStatus;

// Persistence Models
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;
use TmrEcosystem\IAM\Domain\Models\User;
use TmrEcosystem\Warehouse\Infrastructure\Persistence\Eloquent\Models\WarehouseModel;

// ✅ Import Service Interface
use TmrEcosystem\Inventory\Application\Contracts\ItemLookupServiceInterface;
use TmrEcosystem\Sales\Application\DTOs\OrderItemSnapshotDto;
use TmrEcosystem\Sales\Application\DTOs\OrderSnapshotDto;
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

    public function index(Request $request): Response
    {
        // เริ่มต้น Query โดยใช้ Eloquent Relationships
        // ใช้ withSum เพื่อคำนวณยอดที่ส่งแล้ว (Shipped Amount) ตาม Logic เดิม
        $query = SalesOrderModel::query()
            ->with(['customer', 'salesperson']) // ✅ Eager load salesperson
            ->withSum([
                'items as shipped_total_amount' => function ($q) {
                    $q->select(DB::raw('SUM(qty_shipped * unit_price)'));
                }
            ], 'subtotal');

        // 1. Search Filter (คงเดิม)
        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('order_number', 'like', "%{$request->search}%")
                    ->orWhereHas('customer', function ($q) use ($request) {
                        $q->where('name', 'like', "%{$request->search}%")
                            ->orWhere('code', 'like', "%{$request->search}%");
                    });
            });
        }

        // 2. ✅ Filter: My Orders (เฉพาะของตัวเอง)
        if ($request->boolean('my_orders')) {
            $query->where('salesperson_id', $request->user()->id);
        }

        // 3. ✅ Filter: Salesperson (สำหรับ Manager เลือกดูของคนอื่น)
        if ($request->salesperson_id) {
            $query->where('salesperson_id', $request->salesperson_id);
        }

        // 4. ✅ Permission Check & Prepare Salespersons List (Manager Only)
        // ตรวจสอบว่า user มีสิทธิ์ดูทั้งหมดหรือไม่ (เช่น Super Admin หรือ Sales Manager)
        // หมายเหตุ: ปรับ Role Name ตามที่ใช้จริงในระบบของคุณ
        $canViewAll = $request->user()->hasRole(['Super Admin', 'Sales Manager']);

        $salespersons = [];
        if ($canViewAll) {
            $salespersons = User::role(['Sales Executive', 'Sales Manager'])
                ->orderBy('name')
                ->get(['id', 'name']);
        } else {
            // ถ้าไม่ใช่ Manager ให้บังคับดูแค่ของตัวเอง (Optional Security)
            // $query->where('salesperson_id', $request->user()->id);
        }

        return Inertia::render('Sales/Index', [
            'orders' => $query->latest('created_at')
                ->paginate(10)
                ->withQueryString()
                ->through(fn($order) => [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer ? $order->customer->name : 'N/A',
                    'customer_code' => $order->customer ? $order->customer->code : 'N/A',
                    'salesperson' => $order->salesperson ? [ // ✅ ส่งข้อมูล Salesperson กลับไป
                        'id' => $order->salesperson->id,
                        'name' => $order->salesperson->name,
                    ] : null,
                    'status' => $order->status,
                    'created_at' => $order->created_at->format('d/m/Y'),
                    'total_amount' => $order->total_amount,
                    'shipped_amount' => $order->shipped_total_amount ?? 0,
                    'display_amount' => $order->status === 'cancelled' ? 0 : $order->total_amount,
                ]),
            'filters' => $request->only(['search', 'my_orders', 'salesperson_id']),
            'salespersons' => $salespersons, // ✅ ส่ง List ให้ Dropdown Filter
            'canViewAll' => $canViewAll,     // ✅ ส่ง Flag บอกหน้าบ้านว่ามีสิทธิ์เลือกดูไหม
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

    public function create(Request $request): Response
    {
        // 1. ✅ Check Permissions
        // คนที่มีสิทธิ์เลือก Salesperson ให้คนอื่นได้ (Manager/Admin)
        $canAssignSalesperson = $request->user()->hasRole(['Super Admin', 'Sales Manager']);

        // 2. ✅ Load Salespersons List
        // ถ้ามีสิทธิ์ ให้ดึงรายชื่อเซลล์ไปแสดงใน Dropdown
        $salespersons = $canAssignSalesperson
            ? User::role(['Sales Executive', 'Sales Manager'])->orderBy('name')->get(['id', 'name'])
            : [];

        // 3. ✅ Load Customers with Default Salesperson
        // ดึงข้อมูลลูกค้าพร้อม default_salesperson_id เพื่อทำ Auto-select
        $customers = Customer::query()
            ->where('company_id', $request->user()->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'default_salesperson_id']);

        return Inertia::render('Sales/CreateOrder', [
            'customers' => $customers, // ส่ง Object ลูกค้าที่ครบถ้วนกว่าเดิม
            'salespersons' => $salespersons,
            'canAssignSalesperson' => $canAssignSalesperson,
            'currentUser' => $request->user(), // ส่ง user ปัจจุบันไปเพื่อ set default

            // ข้อมูลเดิม
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
            'salesperson_id' => 'nullable|exists:users,id',
        ]);

        $user = $request->user();
        $companyId = $user->company_id ?? 'DEFAULT_COMPANY';

        // 1. หา Warehouse
        $warehouse = WarehouseModel::where('company_id', $companyId)
            ->where('is_active', true)
            ->first();

        if (!$warehouse) {
            return back()->with('error', 'ไม่พบคลังสินค้า (Warehouse) ในระบบ');
        }

        $warehouseId = $warehouse->uuid;

        // 2. Salesperson
        $salespersonId = $request->filled('salesperson_id')
            ? $request->salesperson_id
            : $user->id;

        $inputData = $validated;

        try {
            // 3. สร้าง DTO
            $dto = CreateOrderDto::fromRequest(
                data: $inputData,
                companyId: $companyId,
                warehouseId: $warehouseId,
                salespersonId: $salespersonId
            );

            // 4. เรียก UseCase สร้าง Order (ได้สถานะ Draft)
            $order = $useCase->handle($dto);

            // ✅ 5. Logic Manual Confirm
            if ($request->input('action') === 'confirm') {

                // อัปเดตสถานะใน Database
                SalesOrderModel::where('id', $order->getId())->update(['status' => 'confirmed']);

                // -------------------------------------------------------------
                // 🔴 [FIXED] Re-fetch Order เพื่อให้ได้ Item IDs ที่ถูกต้องจาก DB
                // -------------------------------------------------------------
                // เพราะ $order เดิมที่ได้จาก UseCase อาจจะยังไม่มี ID ของ Item
                // เนื่องจากเป็น Object ใน Memory ก่อน Save
                $freshOrder = $this->orderRepository->findById($order->getId());

                // ✅ ใช้ $freshOrder แทน $order ในการทำ Snapshot
                $itemsSnapshot = $freshOrder->getItems()->map(fn($item) => new OrderItemSnapshotDto(
                    id: $item->id, // ตอนนี้จะมีค่าแล้ว เพราะดึงมาจาก DB
                    productId: $item->productId,
                    productName: $item->productName,
                    quantity: $item->quantity,
                    unitPrice: $item->unitPrice
                ))->toArray();

                $snapshot = new OrderSnapshotDto(
                    orderId: $freshOrder->getId(),
                    orderNumber: $freshOrder->getOrderNumber(),
                    customerId: $freshOrder->getCustomerId(),
                    companyId: $freshOrder->getCompanyId(),
                    warehouseId: $freshOrder->getWarehouseId(),
                    items: $itemsSnapshot,
                    note: $freshOrder->getNote()
                );

                // ส่ง Event
                OrderConfirmed::dispatch($freshOrder->getId(), $snapshot);
            }

            return to_route('sales.orders.show', $order->getId())
                ->with('success', 'Order created successfully!');
        } catch (Exception $e) {
            Log::error("Create Order Failed: " . $e->getMessage());

            if (isset($order) && $order->getId()) {
                return to_route('sales.orders.show', $order->getId())
                    ->with('error', 'บันทึกออเดอร์แล้ว แต่มีข้อผิดพลาด: ' . $e->getMessage());
            }

            return back()->with('error', 'Failed to create order: ' . $e->getMessage());
        }
    }

    // ✅ [Update] ปรับปรุงเมธอด show เพื่อคำนวณสถานะการจัดส่ง + Timeline + Salesperson
    public function show(string $id, OrderRepositoryInterface $repo)
    {
        // 1. ดึงข้อมูลผ่าน Repository (ซึ่งจะเรียก reconstitute ให้)
        $order = $this->orderRepository->findById($id);

        if (!$order) {
            abort(404, 'Order not found');
        }

        // 2. Fetch Order Model via Eloquent (for relationships & quick reads)
        $orderModel = SalesOrderModel::withCount('pickingSlips')
            ->with(['salesperson', 'customer']) // ✅ Eager load salesperson
            ->find($id);

        if (!$order) abort(404);

        // 3. Pagination Logic (Previous/Next Order)
        $allIds = SalesOrderModel::orderBy('created_at', 'desc')->pluck('id')->toArray();
        $currentIndex = array_search($id, $allIds);

        $paginationInfo = [
            'current_index' => $currentIndex + 1,
            'total' => count($allIds),
            'prev_id' => $allIds[$currentIndex - 1] ?? null,
            'next_id' => $allIds[$currentIndex + 1] ?? null,
        ];

        // 4. Product Details Lookup
        $orderProductIds = $order->getItems()->map(fn($item) => $item->productId)->toArray();
        $productDetails = $this->itemLookupService->getByPartNumbers($orderProductIds);

        // 5. ✅ Calculate Shipping Status
        $totalQty = 0;
        $totalShipped = 0;
        $isFullyShipped = true;
        $hasShippedItems = false;

        $itemsData = $order->getItems()->map(function ($item) use ($productDetails, &$totalQty, &$totalShipped, &$isFullyShipped, &$hasShippedItems) {
            $qty = $item->quantity;
            $shipped = $item->qtyShipped ?? 0; // Ensure property exists

            $totalQty += $qty;
            $totalShipped += $shipped;

            if ($shipped > 0) {
                $hasShippedItems = true;
            }
            if ($shipped < $qty) {
                $isFullyShipped = false;
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

        // Edge case: Empty order
        if ($order->getItems()->isEmpty()) {
            $isFullyShipped = false;
        }

        // 6. ✅ Fetch Timeline
        $timelineData = $this->traceabilityService->getOrderTimeline($id);

        // 7. ✅ Prepare Salesperson Data for Dropdown (if editable)
        // Similar logic to create method, needed if we allow changing salesperson in edit mode
        $user = request()->user();
        $canAssignSalesperson = $user->hasRole(['Super Admin', 'Sales Manager']);
        $salespersons = $canAssignSalesperson
            ? User::role(['Sales Executive', 'Sales Manager'])->orderBy('name')->get(['id', 'name'])
            : [];

        // 8. Return Response
        return Inertia::render('Sales/Show', [
            'customers' => $this->getCustomerOptions(),
            'salespersons' => $salespersons, // ✅ Send list for editing
            'canAssignSalesperson' => $canAssignSalesperson,
            'currentUser' => $user,
            'availableProducts' => $this->getProductOptions($orderProductIds),
            'paginationInfo' => $paginationInfo,
            'order' => [
                'id' => $order->getId(),
                'order_number' => $order->getOrderNumber(),
                'customer_id' => $order->getCustomerId(),
                // ✅ Salesperson Data from Domain/Model
                'salesperson_id' => $order->getSalespersonId(),
                'salesperson' => $orderModel->salesperson ? [
                    'id' => $orderModel->salesperson->id,
                    'name' => $orderModel->salesperson->name,
                    'email' => $orderModel->salesperson->email,
                ] : null,
                'status' => $order->getStatus()->value,
                'note' => $order->getNote(),
                'payment_terms' => $order->getPaymentTerms(),
                'total_amount' => $order->getTotalAmount(),
                'picking_count' => $orderModel->picking_slips_count ?? 0,
                'items' => $itemsData,

                // ✅ Shipping Flags
                'is_fully_shipped' => $isFullyShipped,
                'has_shipped_items' => $hasShippedItems,
                'shipping_progress' => $totalQty > 0 ? round(($totalShipped / $totalQty) * 100) : 0,

                // ✅ Timeline
                'timeline' => $timelineData
            ]
        ]);
    }

    public function edit(string $id, Request $request): Response
    {
        // 1. ดึงข้อมูล Order พร้อมความสัมพันธ์
        $orderModel = SalesOrderModel::with(['customer', 'items', 'salesperson'])
            ->withCount('pickingSlips')
            ->findOrFail($id);

        // 2. ป้องกันการแก้ไขถ้าสถานะจบไปแล้ว (Optional: แล้วแต่ Policy)
        // if (in_array($orderModel->status, ['cancelled', 'completed'])) {
        //     return redirect()->route('sales.orders.show', $id);
        // }

        // 3. เตรียมข้อมูลสำหรับ Dropdown (Customers & Salespersons)
        $user = $request->user();
        $canAssignSalesperson = $user->hasRole(['Super Admin', 'Sales Manager']);

        $salespersons = $canAssignSalesperson
            ? User::role(['Sales Executive', 'Sales Manager'])->orderBy('name')->get(['id', 'name'])
            : [];

        $customers = Customer::query()
            ->where('company_id', $user->company_id)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'default_salesperson_id', 'payment_terms']);

        // 4. เตรียมข้อมูลสินค้า (Available Products)
        // ใช้ Helper เดิมที่มีใน Controller
        $products = $this->getProductOptions();

        // 5. คำนวณสถานะการจัดส่ง (Shipping Status Logic) - เพื่อแสดงใน Tracking Tab
        $totalQty = 0;
        $totalShipped = 0;
        $isFullyShipped = true;
        $hasShippedItems = false;

        $itemsData = $orderModel->items->map(function ($item) use (&$totalQty, &$totalShipped, &$isFullyShipped, &$hasShippedItems) {
            $qty = $item->quantity;
            $shipped = $item->qty_shipped ?? 0;

            $totalQty += $qty;
            $totalShipped += $shipped;

            if ($shipped > 0) $hasShippedItems = true;
            if ($shipped < $qty) $isFullyShipped = false;

            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'description' => $item->product_name, // Mapping ให้ตรงกับ Frontend
                'quantity' => $qty,
                'qty_shipped' => $shipped,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                // image_url จะถูกจัดการโดย ProductCombobox ใน Frontend ผ่าน availableProducts
            ];
        });

        if ($orderModel->items->isEmpty()) $isFullyShipped = false;

        // 6. ดึงข้อมูล Timeline
        $timelineData = $this->traceabilityService->getOrderTimeline($id);

        // 7. Pagination Logic (สำหรับปุ่ม Previous/Next)
        $allIds = SalesOrderModel::orderBy('created_at', 'desc')->pluck('id')->toArray();
        $currentIndex = array_search($id, $allIds);
        $paginationInfo = [
            'current_index' => $currentIndex + 1,
            'total' => count($allIds),
            'prev_id' => $allIds[$currentIndex - 1] ?? null,
            'next_id' => $allIds[$currentIndex + 1] ?? null,
        ];

        return Inertia::render('Sales/Edit', [
            'order' => [
                'id' => $orderModel->id,
                'order_number' => $orderModel->order_number,
                'customer_id' => $orderModel->customer_id,
                'salesperson_id' => $orderModel->salesperson_id,
                'picking_count' => $orderModel->picking_slips_count ?? 0,
                'status' => $orderModel->status,
                'note' => $orderModel->note,
                'payment_terms' => $orderModel->payment_terms,
                'total_amount' => (float) $orderModel->total_amount,

                // Shipping Flags
                'is_fully_shipped' => $isFullyShipped,
                'has_shipped_items' => $hasShippedItems,
                'shipping_progress' => $totalQty > 0 ? round(($totalShipped / $totalQty) * 100) : 0,

                'timeline' => $timelineData,
                'items' => $itemsData,
            ],
            'customers' => $customers,
            'salespersons' => $salespersons,
            'availableProducts' => $products,
            'canAssignSalesperson' => $canAssignSalesperson,
            'currentUser' => $user,
            'paginationInfo' => $paginationInfo,
        ]);
    }

    public function update(
        Request $request,
        string $id,
        UpdateOrderUseCase $useCase
    ) {
        $request->validate([
            'customer_id' => 'required',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required',
            'items.*.quantity' => 'required|integer|min:0',
        ]);

        try {
            // Transform Request to DTO
            $dto = UpdateOrderDto::fromRequest($request);

            // Execute Use Case
            $order = $useCase->handle($id, $dto);

            // dd($order);

            // Determine Response Message
            // ✅ ตอนนี้เรียกใช้ OrderStatus::Confirmed ได้แล้วเพราะ import มาแล้ว
            if ($order->getStatus() === OrderStatus::Confirmed) {
                $message = 'ยืนยันออเดอร์และจองสินค้าเรียบร้อยแล้ว (Order Confirmed & Stock Reserved)';
            } else {
                $message = 'บันทึกข้อมูลเรียบร้อยแล้ว (Order Updated)';
            }

            return to_route('sales.orders.edit', $id)
                ->with('success', $message);
        } catch (Exception $e) {
            Log::error("Order Update Failed: " . $e->getMessage());
            return back()->with('error', 'เกิดข้อผิดพลาด: ' . $e->getMessage());
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
