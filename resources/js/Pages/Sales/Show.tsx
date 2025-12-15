import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SalesNavigationMenu from './Partials/SalesNavigationMenu';
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from "@/Components/ui/card";
import { Separator } from "@/Components/ui/separator";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import {
    Printer, Edit, ArrowLeft, Calendar, CreditCard, FileText,
    User as UserIcon, Building, Phone, Mail, MapPin,
    Truck, Package, CheckCircle2, Clock, AlertCircle, XCircle
} from "lucide-react";
import Chatter from '@/Components/Sales/Chatter';
import OrderTimeline from '@/Components/Sales/OrderTimeline';
import SmartButton from '@/Components/SmartButton';
import { cn } from "@/lib/utils";

// --- Types ---
interface OrderItem {
    id: number;
    product_id: string;
    description: string;
    quantity: number;
    qty_shipped: number;
    unit_price: number;
    total: number;
    image_url?: string;
}

interface SalesOrder {
    id: string;
    order_number: string;
    status: string;
    total_amount: number;
    currency: string;
    note: string;
    payment_terms: string;
    created_at: string; // ISO String or Formatted
    customer?: {
        id: string;
        name: string;
        code: string;
        email?: string;
        phone?: string;
        address?: string;
    };
    salesperson?: {
        id: number;
        name: string;
        email: string;
    };
    items: OrderItem[];
    picking_count: number;
    is_fully_shipped: boolean;
    has_shipped_items: boolean;
    shipping_progress: number;
    timeline: any[];
}

interface Props {
    auth: any;
    order: SalesOrder;
}

export default function Show({ auth, order }: Props) {

    // Helper: Status Color
    const getStatusColor = (status: string) => {
        switch (status) {
            case 'draft': return 'bg-gray-100 text-gray-700 border-gray-200';
            case 'confirmed': return 'bg-blue-100 text-blue-700 border-blue-200';
            case 'processing': return 'bg-indigo-100 text-indigo-700 border-indigo-200';
            case 'completed': return 'bg-green-100 text-green-700 border-green-200';
            case 'cancelled': return 'bg-red-100 text-red-700 border-red-200';
            default: return 'bg-gray-100 text-gray-700';
        }
    };

    // Helper: Currency
    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('th-TH', {
            style: 'currency',
            currency: order.currency || 'THB'
        }).format(amount);
    };

    // Actions
    const handleEdit = () => {
        router.get(route('sales.orders.edit', order.id)); // ใช้ route create แต่ส่ง id ไปเพื่อ edit ตาม Logic Controller เดิม
        // หรือถ้าคุณแยก route edit แล้วก็ใช้ route('sales.orders.edit', order.id)
    };

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<SalesNavigationMenu />}>
            <Head title={`Order ${order.order_number}`} />

            <div className="py-8 bg-gray-50/50 min-h-screen">
                <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                    {/* --- Header Section --- */}
                    <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div>
                            <div className="flex items-center gap-2 mb-1">
                                <Link href={route('sales.index')} className="text-sm text-gray-500 hover:text-gray-700 flex items-center gap-1">
                                    <ArrowLeft className="w-4 h-4" /> Back to Orders
                                </Link>
                            </div>
                            <div className="flex items-center gap-3">
                                <h1 className="text-3xl font-bold text-gray-900">{order.order_number}</h1>
                                <Badge variant="outline" className={cn("text-sm font-semibold capitalize px-3 py-1", getStatusColor(order.status))}>
                                    {order.status}
                                </Badge>
                            </div>
                            <p className="text-sm text-gray-500 mt-1 flex items-center gap-2">
                                <Calendar className="w-4 h-4" /> Created on {new Date(order.created_at).toLocaleDateString('th-TH', { year: 'numeric', month: 'long', day: 'numeric' })}
                            </p>
                        </div>

                        <div className="flex items-center gap-2">
                            {/* PDF Button */}
                            <a href={route('sales.orders.pdf', order.id)} target="_blank">
                                <Button variant="outline" className="gap-2">
                                    <Printer className="w-4 h-4" /> Print PDF
                                </Button>
                            </a>

                            {/* Edit Button (Only if not cancelled/completed) */}
                            {!['cancelled', 'completed'].includes(order.status) && (
                                <Button onClick={handleEdit} className="gap-2 bg-purple-700 hover:bg-purple-800">
                                    <Edit className="w-4 h-4" /> Edit Order
                                </Button>
                            )}
                        </div>
                    </div>

                    <Separator />

                    <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                        {/* --- LEFT COLUMN (Main Info) --- */}
                        <div className="lg:col-span-2 space-y-6">

                            {/* Customer & Salesperson Info */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                {/* Customer Card */}
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base font-medium text-gray-500 uppercase flex items-center gap-2">
                                            <Building className="w-4 h-4" /> Customer
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        <div className="space-y-3">
                                            <div>
                                                <Link href={route('customers.show', order.customer_id)} className="text-lg font-bold text-gray-900 hover:underline hover:text-purple-700">
                                                    {order.customer?.name || 'Unknown Customer'}
                                                </Link>
                                                <div className="text-sm text-gray-500">{order.customer?.code}</div>
                                            </div>
                                            <div className="text-sm text-gray-600 space-y-1">
                                                {order.customer?.phone && <div className="flex items-center gap-2"><Phone className="w-3 h-3 text-gray-400"/> {order.customer.phone}</div>}
                                                {order.customer?.email && <div className="flex items-center gap-2"><Mail className="w-3 h-3 text-gray-400"/> {order.customer.email}</div>}
                                                {order.customer?.address && <div className="flex items-start gap-2"><MapPin className="w-3 h-3 text-gray-400 mt-1"/> <span className="line-clamp-2">{order.customer.address}</span></div>}
                                            </div>
                                        </div>
                                    </CardContent>
                                </Card>

                                {/* ✅ Salesperson Card */}
                                <Card>
                                    <CardHeader className="pb-3">
                                        <CardTitle className="text-base font-medium text-gray-500 uppercase flex items-center gap-2">
                                            <UserIcon className="w-4 h-4" /> Sales Representative
                                        </CardTitle>
                                    </CardHeader>
                                    <CardContent>
                                        {order.salesperson ? (
                                            <div className="flex items-center gap-3 h-full">
                                                <div className="h-12 w-12 rounded-full bg-indigo-50 flex items-center justify-center text-indigo-600 font-bold text-xl border border-indigo-100">
                                                    {order.salesperson.name.charAt(0)}
                                                </div>
                                                <div>
                                                    <div className="font-bold text-gray-900">{order.salesperson.name}</div>
                                                    <div className="text-sm text-gray-500 flex items-center gap-1">
                                                        <Mail className="w-3 h-3" /> {order.salesperson.email}
                                                    </div>
                                                </div>
                                            </div>
                                        ) : (
                                            <div className="h-full flex flex-col justify-center items-center text-gray-400 py-4 bg-gray-50 rounded-md border border-dashed">
                                                <UserIcon className="w-8 h-8 mb-1 opacity-20" />
                                                <span className="text-sm">No Salesperson Assigned</span>
                                            </div>
                                        )}
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Order Items Table */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Order Items</CardTitle>
                                    <CardDescription>List of products included in this order.</CardDescription>
                                </CardHeader>
                                <CardContent className="p-0">
                                    <div className="overflow-x-auto">
                                        <table className="w-full text-sm text-left">
                                            <thead className="text-xs text-gray-500 uppercase bg-gray-50 border-b">
                                                <tr>
                                                    <th className="px-6 py-3 font-medium">Product / Description</th>
                                                    <th className="px-6 py-3 font-medium text-right">Unit Price</th>
                                                    <th className="px-6 py-3 font-medium text-right">Qty</th>
                                                    {/* Show Shipped Column if confirmed */}
                                                    {order.status !== 'draft' && <th className="px-6 py-3 font-medium text-right text-blue-600">Shipped</th>}
                                                    <th className="px-6 py-3 font-medium text-right">Total</th>
                                                </tr>
                                            </thead>
                                            <tbody className="divide-y divide-gray-100">
                                                {order.items.map((item) => (
                                                    <tr key={item.id} className="hover:bg-gray-50/50">
                                                        <td className="px-6 py-4">
                                                            <div className="font-medium text-gray-900">{item.description}</div>
                                                            <div className="text-xs text-gray-500">{item.product_id}</div>
                                                        </td>
                                                        <td className="px-6 py-4 text-right">{formatCurrency(item.unit_price)}</td>
                                                        <td className="px-6 py-4 text-right font-mono">{item.quantity}</td>
                                                        {order.status !== 'draft' && (
                                                            <td className="px-6 py-4 text-right font-mono text-blue-600 font-medium">
                                                                {item.qty_shipped}
                                                            </td>
                                                        )}
                                                        <td className="px-6 py-4 text-right font-medium">{formatCurrency(item.total)}</td>
                                                    </tr>
                                                ))}
                                            </tbody>
                                            <tfoot className="bg-gray-50 font-medium text-gray-900 border-t">
                                                <tr>
                                                    <td colSpan={order.status !== 'draft' ? 4 : 3} className="px-6 py-4 text-right">Grand Total</td>
                                                    <td className="px-6 py-4 text-right text-lg font-bold text-purple-700">{formatCurrency(order.total_amount)}</td>
                                                </tr>
                                            </tfoot>
                                        </table>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Additional Info */}
                            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <Card>
                                    <CardHeader className="pb-2"><CardTitle className="text-sm text-gray-500 uppercase">Payment Terms</CardTitle></CardHeader>
                                    <CardContent>
                                        <div className="flex items-center gap-2 text-gray-900 font-medium">
                                            <CreditCard className="w-5 h-5 text-gray-400" />
                                            {order.payment_terms === 'immediate' ? 'Immediate Payment' : order.payment_terms.replace('_', ' ').toUpperCase()}
                                        </div>
                                    </CardContent>
                                </Card>
                                <Card>
                                    <CardHeader className="pb-2"><CardTitle className="text-sm text-gray-500 uppercase">Internal Note</CardTitle></CardHeader>
                                    <CardContent>
                                        <p className="text-sm text-gray-700 whitespace-pre-wrap">{order.note || '-'}</p>
                                    </CardContent>
                                </Card>
                            </div>

                            {/* Activity Log / Chatter */}
                            <div className="pt-6">
                                <h3 className="text-lg font-medium mb-4">Activity & Communication</h3>
                                <Chatter modelType="sales_order" modelId={order.id} />
                            </div>
                        </div>

                        {/* --- RIGHT COLUMN (Tracking & Stats) --- */}
                        <div className="space-y-6">

                            {/* Fulfillment Status */}
                            <Card className="border-t-4 border-t-purple-600">
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <Truck className="w-5 h-5 text-purple-600" /> Fulfillment
                                    </CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-6">
                                    {/* Progress Bar */}
                                    <div>
                                        <div className="flex justify-between text-sm mb-2">
                                            <span className="text-gray-500">Shipping Progress</span>
                                            <span className="font-bold">{order.shipping_progress}%</span>
                                        </div>
                                        <div className="w-full bg-gray-200 rounded-full h-2.5">
                                            <div className="bg-purple-600 h-2.5 rounded-full transition-all duration-500" style={{ width: `${order.shipping_progress}%` }}></div>
                                        </div>
                                    </div>

                                    {/* Status Badges */}
                                    <div className="space-y-2">
                                        {order.is_fully_shipped ? (
                                            <div className="flex items-center gap-2 p-3 bg-green-50 text-green-700 rounded-md border border-green-100">
                                                <CheckCircle2 className="w-5 h-5" />
                                                <span className="font-medium">Order Fully Shipped</span>
                                            </div>
                                        ) : order.has_shipped_items ? (
                                            <div className="flex items-center gap-2 p-3 bg-orange-50 text-orange-700 rounded-md border border-orange-100">
                                                <Clock className="w-5 h-5" />
                                                <span className="font-medium">Partially Shipped</span>
                                            </div>
                                        ) : (
                                            <div className="flex items-center gap-2 p-3 bg-gray-50 text-gray-500 rounded-md border border-gray-200">
                                                <AlertCircle className="w-5 h-5" />
                                                <span className="font-medium">Pending Shipment</span>
                                            </div>
                                        )}
                                    </div>

                                    <div className="grid grid-cols-2 gap-4 pt-2">
                                        <div className="text-center p-3 bg-gray-50 rounded border">
                                            <div className="text-xs text-gray-500 uppercase">Ordered</div>
                                            <div className="text-xl font-bold">{order.items.reduce((s, i) => s + i.quantity, 0)}</div>
                                        </div>
                                        <div className="text-center p-3 bg-blue-50 rounded border border-blue-100">
                                            <div className="text-xs text-blue-600 uppercase">Shipped</div>
                                            <div className="text-xl font-bold text-blue-700">{order.items.reduce((s, i) => s + i.qty_shipped, 0)}</div>
                                        </div>
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Related Logistics Documents */}
                            <Card>
                                <CardHeader>
                                    <CardTitle className="flex items-center gap-2">
                                        <FileText className="w-5 h-5 text-gray-500" /> Documents
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    {order.picking_count > 0 ? (
                                        <div className="space-y-3">
                                            <div className="flex items-center justify-between p-3 bg-white border rounded-lg hover:shadow-sm transition-shadow">
                                                <div className="flex items-center gap-3">
                                                    <div className="p-2 bg-indigo-50 rounded-full text-indigo-600">
                                                        <Package className="w-4 h-4" />
                                                    </div>
                                                    <div>
                                                        <p className="font-medium text-sm">Picking Slips</p>
                                                        <p className="text-xs text-gray-500">{order.picking_count} document(s)</p>
                                                    </div>
                                                </div>
                                                <Link href={route('logistics.picking.index', { search: order.order_number })}>
                                                    <Button variant="ghost" size="sm" className="h-8">View</Button>
                                                </Link>
                                            </div>
                                            {/* You can add Delivery Note / Invoice logic here later */}
                                        </div>
                                    ) : (
                                        <div className="text-center py-6 text-gray-400 text-sm">
                                            No logistics documents generated yet.
                                        </div>
                                    )}

                                    {/* Action to create picking (if needed) */}
                                    {order.status === 'confirmed' && !order.is_fully_shipped && (
                                        <div className="mt-4 pt-4 border-t">
                                            <Link href={route('logistics.picking.index')} className="w-full">
                                                {/* In real app, might link to a "create picking from order" page */}
                                                <Button variant="outline" className="w-full">Manage Logistics</Button>
                                            </Link>
                                        </div>
                                    )}
                                </CardContent>
                            </Card>

                            {/* Timeline */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Order History</CardTitle>
                                </CardHeader>
                                <CardContent className="max-h-[400px] overflow-y-auto pr-2">
                                    <OrderTimeline events={order.timeline || []} />
                                </CardContent>
                            </Card>

                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
