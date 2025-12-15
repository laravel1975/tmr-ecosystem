import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Separator } from "@/Components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import {
    ArrowLeft, Edit, Phone, Mail, MapPin, Building,
    CreditCard, DollarSign, ShoppingBag, Calendar,
    TrendingUp, Package, Clock
} from "lucide-react";
import { format } from 'date-fns';
import SalesNavigationMenu from '../Sales/Partials/SalesNavigationMenu';

// Helper function to format currency
const formatCurrency = (amount: number) => {
    return new Intl.NumberFormat('th-TH', { style: 'currency', currency: 'THB' }).format(amount);
};

export default function CustomerShow({ auth, customer, recentOrders, stats, topProducts }: any) {

    // Status color helper
    const getStatusColor = (status: string) => {
        switch (status) {
            case 'completed': return 'bg-green-100 text-green-800 border-green-200';
            case 'confirmed': return 'bg-blue-100 text-blue-800 border-blue-200';
            case 'pending': return 'bg-yellow-100 text-yellow-800 border-yellow-200';
            case 'cancelled': return 'bg-gray-100 text-gray-800 border-gray-200';
            default: return 'bg-gray-50';
        }
    };

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<SalesNavigationMenu />}>
            <Head title={`Customer: ${customer.name}`} />

            <div className="max-w-7xl mx-auto p-6 space-y-6">
                {/* Header Section */}
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-gray-500 mb-1">
                            <Link href={route('customers.index')}>
                                <Button variant="ghost" size="sm" className="h-6 px-0 hover:bg-transparent hover:underline">
                                    <ArrowLeft className="w-4 h-4 mr-1" /> Back to Customers
                                </Button>
                            </Link>
                        </div>
                        <h1 className="text-3xl font-bold text-gray-900 flex items-center gap-3">
                            {customer.name}
                            {customer.is_credit_hold && (
                                <Badge variant="destructive" className="text-sm">Credit Hold</Badge>
                            )}
                        </h1>
                        <div className="flex items-center gap-4 text-sm text-gray-500 mt-2">
                            <span className="flex items-center gap-1"><Building className="w-3 h-3" /> {customer.code}</span>
                            <span className="flex items-center gap-1"><CreditCard className="w-3 h-3" /> Tax ID: {customer.tax_id || '-'}</span>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        <Link href={route('customers.edit', customer.id)}>
                            <Button variant="outline" className="gap-2">
                                <Edit className="w-4 h-4" /> Edit Profile
                            </Button>
                        </Link>
                        {/* ปุ่มสร้าง Sales Order ทันที (ถ้ามี Route) */}
                        {/* <Link href={route('sales.orders.create', { customer_id: customer.id })}>
                            <Button className="gap-2 bg-indigo-600 hover:bg-indigo-700">
                                <ShoppingBag className="w-4 h-4" /> New Order
                            </Button>
                        </Link> */}
                    </div>
                </div>

                {/* Key Metrics Cards */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-gray-500">Total Sales</CardTitle>
                            <DollarSign className="h-4 w-4 text-green-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-gray-900">{formatCurrency(stats.total_sales)}</div>
                            <p className="text-xs text-gray-500">Lifetime value</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-gray-500">Outstanding Balance</CardTitle>
                            <CreditCard className="h-4 w-4 text-orange-600" />
                        </CardHeader>
                        <CardContent>
                            <div className={`text-2xl font-bold ${customer.outstanding_balance > 0 ? 'text-orange-600' : 'text-gray-900'}`}>
                                {formatCurrency(customer.outstanding_balance)}
                            </div>
                            <div className="flex justify-between items-center text-xs mt-1">
                                <span className="text-gray-500">Limit: {formatCurrency(customer.credit_limit)}</span>
                                <span className={`${(customer.outstanding_balance / (customer.credit_limit || 1)) * 100 > 80 ? 'text-red-500 font-bold' : 'text-green-600'}`}>
                                    {customer.credit_limit > 0 ? Math.round((customer.outstanding_balance / customer.credit_limit) * 100) + '%' : 'N/A'} Used
                                </span>
                            </div>
                            {/* Simple Progress Bar */}
                            <div className="w-full bg-gray-200 rounded-full h-1.5 mt-2">
                                <div
                                    className={`h-1.5 rounded-full ${customer.outstanding_balance > customer.credit_limit ? 'bg-red-500' : 'bg-blue-500'}`}
                                    style={{ width: `${Math.min((customer.outstanding_balance / (customer.credit_limit || 1)) * 100, 100)}%` }}
                                ></div>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-gray-500">Avg. Order Value</CardTitle>
                            <TrendingUp className="h-4 w-4 text-blue-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-gray-900">{formatCurrency(stats.avg_order_value)}</div>
                            <p className="text-xs text-gray-500">Per transaction</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium text-gray-500">Last Order</CardTitle>
                            <Clock className="h-4 w-4 text-purple-600" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-xl font-bold text-gray-900">
                                {stats.last_order_date ? format(new Date(stats.last_order_date), 'dd MMM yyyy') : '-'}
                            </div>
                            <p className="text-xs text-gray-500">{stats.total_orders} Total Orders</p>
                        </CardContent>
                    </Card>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    {/* Left Column: Contact Info & Details */}
                    <div className="lg:col-span-1 space-y-6">
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Contact Details</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex items-start gap-3">
                                    <MapPin className="w-5 h-5 text-gray-400 mt-0.5 shrink-0" />
                                    <div>
                                        <label className="text-xs font-bold text-gray-500 uppercase">Address</label>
                                        <p className="text-sm text-gray-700 whitespace-pre-line">{customer.address || '-'}</p>
                                    </div>
                                </div>
                                <Separator />
                                <div className="flex items-center gap-3">
                                    <Phone className="w-5 h-5 text-gray-400 shrink-0" />
                                    <div>
                                        <label className="text-xs font-bold text-gray-500 uppercase">Phone</label>
                                        <p className="text-sm text-gray-700">{customer.phone || '-'}</p>
                                    </div>
                                </div>
                                <div className="flex items-center gap-3">
                                    <Mail className="w-5 h-5 text-gray-400 shrink-0" />
                                    <div>
                                        <label className="text-xs font-bold text-gray-500 uppercase">Email</label>
                                        <p className="text-sm text-gray-700">{customer.email || '-'}</p>
                                    </div>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader>
                                <CardTitle className="text-lg">Financial Settings</CardTitle>
                            </CardHeader>
                            <CardContent className="space-y-3">
                                <div className="flex justify-between items-center py-2 border-b">
                                    <span className="text-sm text-gray-500">Credit Term</span>
                                    <span className="font-medium">{customer.credit_term_days} Days</span>
                                </div>
                                <div className="flex justify-between items-center py-2 border-b">
                                    <span className="text-sm text-gray-500">Credit Status</span>
                                    {customer.is_credit_hold ? (
                                        <Badge variant="destructive">Hold / Locked</Badge>
                                    ) : (
                                        <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200">Active</Badge>
                                    )}
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Right Column: Transactions & Items */}
                    <div className="lg:col-span-2">
                        <Tabs defaultValue="orders" className="w-full">
                            <TabsList className="grid w-full grid-cols-2 mb-4">
                                <TabsTrigger value="orders">Recent Orders</TabsTrigger>
                                <TabsTrigger value="products">Purchased Products</TabsTrigger>
                            </TabsList>

                            <TabsContent value="orders">
                                <Card>
                                    <CardHeader>
                                        <CardTitle className="flex justify-between items-center">
                                            <span>Sales Orders History</span>
                                            {/* <Link href={route('sales.orders.index', { customer: customer.id })} className="text-sm text-indigo-600 hover:underline font-normal">View All</Link> */}
                                        </CardTitle>
                                        <CardDescription>Latest 5 transactions from this customer</CardDescription>
                                    </CardHeader>
                                    <CardContent className="p-0">
                                        <table className="w-full text-sm text-left">
                                            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                                                <tr>
                                                    <th className="px-4 py-3 font-medium">Order No.</th>
                                                    <th className="px-4 py-3 font-medium">Date</th>
                                                    <th className="px-4 py-3 font-medium">Status</th>
                                                    <th className="px-4 py-3 font-medium text-right">Total</th>
                                                    <th className="px-4 py-3"></th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {recentOrders.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={5} className="px-4 py-8 text-center text-gray-500">No orders found.</td>
                                                    </tr>
                                                ) : (
                                                    recentOrders.map((order: any) => (
                                                        <tr key={order.id} className="hover:bg-gray-50 transition-colors">
                                                            <td className="px-4 py-3 font-medium text-indigo-600">{order.order_number}</td>
                                                            <td className="px-4 py-3 text-gray-500">
                                                                {format(new Date(order.created_at), 'dd/MM/yyyy')}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <Badge variant="outline" className={getStatusColor(order.status)}>
                                                                    {order.status}
                                                                </Badge>
                                                                {/* ถ้าต้องการแสดง Payment Status แต่ไม่มีใน DB ให้ Hardcode หรือ Comment ไว้ก่อน */}
                                                                {/* <span className="ml-2 text-xs text-gray-500">{order.payment_status}</span> */}
                                                            </td>
                                                            <td className="px-4 py-3 text-right font-medium">
                                                                {formatCurrency(Number(order.total_amount))}
                                                            </td>
                                                            <td className="px-4 py-3 text-right">
                                                                {/* <Link href={route('sales.orders.show', order.id)}>
                                                                    <Button variant="ghost" size="sm" className="h-8 w-8 p-0">
                                                                        <ChevronRight className="w-4 h-4" />
                                                                    </Button>
                                                                </Link> */}
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </CardContent>
                                </Card>
                            </TabsContent>

                            <TabsContent value="products">
                                <Card>
                                    <CardHeader>
                                        <CardTitle>Top Purchased Items</CardTitle>
                                        <CardDescription>Items most frequently bought by this customer</CardDescription>
                                    </CardHeader>
                                    <CardContent className="p-0">
                                        <table className="w-full text-sm text-left">
                                            <thead className="bg-gray-50 text-gray-500 uppercase text-xs">
                                                <tr>
                                                    <th className="px-4 py-3 font-medium">Product Name</th>
                                                    <th className="px-4 py-3 font-medium text-right">Total Qty</th>
                                                    <th className="px-4 py-3 font-medium text-right">Total Spent</th>
                                                    <th className="px-4 py-3 font-medium text-right">Last Bought</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {topProducts.length === 0 ? (
                                                    <tr>
                                                        <td colSpan={4} className="px-4 py-8 text-center text-gray-500">No purchase history yet.</td>
                                                    </tr>
                                                ) : (
                                                    topProducts.map((item: any, idx: number) => (
                                                        <tr key={idx} className="hover:bg-gray-50">
                                                            <td className="px-4 py-3">
                                                                <div className="font-medium text-gray-900">{item.product_name || 'Unknown Product'}</div>
                                                                <div className="text-xs text-gray-500">{item.product_id}</div>
                                                            </td>
                                                            <td className="px-4 py-3 text-right font-mono text-gray-600">
                                                                {Number(item.total_qty || 0).toLocaleString()}
                                                            </td>
                                                            <td className="px-4 py-3 text-right font-medium">
                                                                {formatCurrency(Number(item.total_spent || 0))}
                                                            </td>
                                                            <td className="px-4 py-3 text-right text-gray-500 text-xs">
                                                                {/* ✅ เพิ่มการเช็คค่า null ก่อนเรียกใช้ format date */}
                                                                {item.last_purchased_at
                                                                    ? format(new Date(item.last_purchased_at), 'dd MMM yyyy')
                                                                    : '-'}
                                                            </td>
                                                        </tr>
                                                    ))
                                                )}
                                            </tbody>
                                        </table>
                                    </CardContent>
                                </Card>
                            </TabsContent>
                        </Tabs>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
