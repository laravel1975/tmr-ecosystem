<?php

namespace TmrEcosystem\Customers\Presentation\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Support\Facades\DB;
use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;
use TmrEcosystem\Customers\Presentation\Http\Requests\StoreCustomerRequest;
use TmrEcosystem\Customers\Presentation\Http\Requests\UpdateCustomerRequest;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderItemModel;

class CustomerController extends Controller
{
    public function index(Request $request): Response
    {
        $query = Customer::query()->where('company_id', $request->user()->company_id);

        if ($request->search) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', "%{$request->search}%")
                    ->orWhere('code', 'like', "%{$request->search}%");
            });
        }

        $customers = $query->orderBy('name')->paginate(10)->withQueryString();

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => $request->only(['search']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    public function store(StoreCustomerRequest $request)
    {
        $data = $request->validated();
        $data['company_id'] = $request->user()->company_id;

        Customer::create($data);

        return to_route('customers.index')->with('success', 'Customer created successfully.');
    }

    public function show(Customer $customer): Response
    {
        // 1. Recent Orders (ตัด payment_status ออกตามที่เจอปัญหาก่อนหน้า)
        $recentOrders = $customer->orders()
            ->latest()
            ->take(5)
            ->get(['id', 'order_number', 'total_amount', 'status', 'created_at']);

        // 2. Stats (แก้ grand_total -> total_amount)
        $confirmedOrders = $customer->orders()->whereIn('status', ['confirmed', 'completed']);
        $stats = [
            'total_sales' => $confirmedOrders->sum('total_amount'),
            'total_orders' => $customer->orders()->count(),
            'avg_order_value' => $confirmedOrders->avg('total_amount') ?? 0,
            'last_order_date' => $customer->orders()->latest()->value('created_at'),
        ];

        // 3. Top Purchased Products (Permanent Fix Logic)
        // ใช้ Eloquent Model เพื่ออ้างอิงตารางได้ถูกต้องแม่นยำ
        $topProducts = SalesOrderItemModel::query()
            ->join('sales_orders', 'sales_order_items.sales_order_id', '=', 'sales_orders.id')
            ->where('sales_orders.customer_id', $customer->id)
            ->whereIn('sales_orders.status', ['confirmed', 'completed'])
            ->select(
                'sales_order_items.product_id',
                'sales_order_items.product_name',
                DB::raw('SUM(sales_order_items.quantity) as total_qty'),
                DB::raw('SUM(sales_order_items.subtotal) as total_spent'),
                DB::raw('MAX(sales_orders.created_at) as last_purchased_at')
            )
            ->groupBy('sales_order_items.product_id', 'sales_order_items.product_name')
            ->orderByDesc('total_spent')
            ->limit(10)
            ->get();

        return Inertia::render('Customers/Show', [
            'customer' => $customer,
            'recentOrders' => $recentOrders,
            'stats' => $stats,
            'topProducts' => $topProducts
        ]);
    }

    public function edit(Customer $customer): Response
    {
        return Inertia::render('Customers/Edit', [
            'customer' => $customer
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer)
    {
        $customer->update($request->validated());
        return to_route('customers.index')->with('success', 'Customer updated successfully.');
    }

    public function destroy(Customer $customer)
    {
        $customer->delete();
        return back()->with('success', 'Customer deleted.');
    }
}
