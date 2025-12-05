import React from 'react';
import { Head, Link, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Printer, CheckCircle, ArrowLeft, PackageCheck } from 'lucide-react';
import { format } from 'date-fns';

// Define Types (ควรอยู่ใน resources/js/types/purchase.d.ts แต่เขียนที่นี่เพื่อความครบถ้วน)
interface PurchaseOrder {
    id: number;
    uuid: string;
    document_number: string;
    status: string;
    order_date: string;
    expected_delivery_date: string | null;
    notes: string | null;
    subtotal: number;
    tax_amount: number;
    grand_total: number;
    vendor: {
        name: string;
        code: string;
        address: string;
        contact_person: string;
        phone: string;
    };
    items: Array<{
        id: number;
        quantity: number;
        unit_price: number;
        total_price: number;
        item: {
            name: string;
            part_number: string;
            uom: string;
        };
    }>;
    created_by_user: {
        name: string;
    };
}

interface Props {
    auth: any;
    order: PurchaseOrder;
}

export default function Show({ auth, order }: Props) {
    const { post, processing } = useForm();

    const handleConfirm = () => {
        if (confirm('Are you sure you want to confirm this order? Items will be expected in stock.')) {
            // ต้องสร้าง Route นี้ใน Backend: Route::post('/orders/{order}/confirm', ...)
            post(route('purchase.orders.confirm', order.id));
        }
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'draft': return <Badge variant="secondary">Draft</Badge>;
            case 'ordered': return <Badge className="bg-blue-600">Ordered</Badge>;
            case 'received': return <Badge className="bg-green-600">Received</Badge>;
            default: return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        Purchase Order: {order.document_number}
                    </h2>
                    <div className="flex gap-2">
                        {order.status === 'draft' && (
                            <Button onClick={handleConfirm} disabled={processing} className="bg-green-600 hover:bg-green-700">
                                <CheckCircle className="w-4 h-4 mr-2" /> Confirm Order
                            </Button>
                        )}
                        {order.status === 'ordered' && (
                            <Link href={route('stock.receive.create', { po_id: order.id })}>
                                <Button variant="secondary">
                                    <PackageCheck className="w-4 h-4 mr-2" /> Receive Items
                                </Button>
                            </Link>
                        )}
                        <Button variant="outline">
                            <Printer className="w-4 h-4 mr-2" /> Print PDF
                        </Button>
                    </div>
                </div>
            }
        >
            <Head title={`PO ${order.document_number}`} />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                    <Link href={route('purchase.orders.index')} className="flex items-center text-gray-500 hover:text-gray-700 mb-4">
                        <ArrowLeft className="w-4 h-4 mr-2" /> Back to Orders
                    </Link>

                    {/* Header Info */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <Card className="md:col-span-2">
                            <CardHeader><CardTitle>Vendor Information</CardTitle></CardHeader>
                            <CardContent className="grid grid-cols-2 gap-4">
                                <div>
                                    <span className="text-sm text-gray-500">Name</span>
                                    <p className="font-medium">{order.vendor.name}</p>
                                    <p className="text-sm text-gray-600">{order.vendor.code}</p>
                                </div>
                                <div>
                                    <span className="text-sm text-gray-500">Contact</span>
                                    <p>{order.vendor.contact_person}</p>
                                    <p>{order.vendor.phone}</p>
                                </div>
                                <div className="col-span-2">
                                    <span className="text-sm text-gray-500">Address</span>
                                    <p>{order.vendor.address || '-'}</p>
                                </div>
                            </CardContent>
                        </Card>

                        <Card>
                            <CardHeader><CardTitle>Document Details</CardTitle></CardHeader>
                            <CardContent className="space-y-4">
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Status</span>
                                    {getStatusBadge(order.status)}
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Order Date</span>
                                    <span>{format(new Date(order.order_date), 'dd MMM yyyy')}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Expected Delivery</span>
                                    <span>{order.expected_delivery_date ? format(new Date(order.expected_delivery_date), 'dd MMM yyyy') : '-'}</span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-sm text-gray-500">Created By</span>
                                    <span>{order.created_by_user?.name}</span>
                                </div>
                            </CardContent>
                        </Card>
                    </div>

                    {/* Items Table */}
                    <Card>
                        <CardHeader><CardTitle>Order Items</CardTitle></CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>#</TableHead>
                                        <TableHead>Product / Description</TableHead>
                                        <TableHead className="text-right">Quantity</TableHead>
                                        <TableHead className="text-right">Unit Price</TableHead>
                                        <TableHead className="text-right">Total</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {order.items.map((item, index) => (
                                        <TableRow key={item.id}>
                                            <TableCell>{index + 1}</TableCell>
                                            <TableCell>
                                                <div>
                                                    <p className="font-medium">{item.item.name}</p>
                                                    <p className="text-xs text-gray-500">{item.item.part_number}</p>
                                                </div>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                {item.quantity} <span className="text-xs text-gray-400">{item.item.uom}</span>
                                            </TableCell>
                                            <TableCell className="text-right">{Number(item.unit_price).toLocaleString()}</TableCell>
                                            <TableCell className="text-right">{Number(item.total_price).toLocaleString()}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>

                            {/* Summary Footer */}
                            <div className="flex justify-end mt-6">
                                <div className="w-full md:w-1/3 space-y-2">
                                    <div className="flex justify-between py-1">
                                        <span className="text-gray-600">Subtotal</span>
                                        <span className="font-medium">{Number(order.subtotal).toLocaleString()} THB</span>
                                    </div>
                                    <div className="flex justify-between py-1">
                                        <span className="text-gray-600">VAT (7%)</span>
                                        <span className="font-medium">{Number(order.tax_amount).toLocaleString()} THB</span>
                                    </div>
                                    <div className="flex justify-between py-2 border-t border-gray-200">
                                        <span className="text-lg font-bold">Grand Total</span>
                                        <span className="text-lg font-bold text-primary">{Number(order.grand_total).toLocaleString()} THB</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Notes */}
                    {order.notes && (
                        <Card>
                            <CardHeader><CardTitle>Notes</CardTitle></CardHeader>
                            <CardContent>
                                <p className="text-gray-700 whitespace-pre-line">{order.notes}</p>
                            </CardContent>
                        </Card>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
