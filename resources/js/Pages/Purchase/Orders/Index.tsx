import React from 'react';
import { Head, Link, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Plus, Eye, FileText } from 'lucide-react';
import Pagination from '@/Components/Pagination';
import SearchFilter from '@/Components/SearchFilter';
import SortableColumn from '@/Components/SortableColumn';
import { format } from 'date-fns';

// Define Interface สำหรับข้อมูลที่ส่งมาจาก Backend
interface PurchaseOrder {
    id: number;
    uuid: string;
    document_number: string;
    order_date: string;
    expected_delivery_date: string | null;
    status: 'draft' | 'ordered' | 'received' | 'cancelled';
    grand_total: number;
    vendor: {
        name: string;
        code: string;
    };
    items_count: number;
}

interface Props {
    auth: any;
    orders: {
        data: PurchaseOrder[];
        links: any[];
        meta: any;
    };
    filters: any;
}

export default function Index({ auth, orders, filters }: Props) {

    // ฟังก์ชันสำหรับเลือกสี Badge ตาม Status
    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'draft':
                return <Badge variant="secondary">Draft</Badge>;
            case 'ordered':
                return <Badge className="bg-blue-500 hover:bg-blue-600">Ordered</Badge>;
            case 'received':
                return <Badge className="bg-green-500 hover:bg-green-600">Received</Badge>;
            case 'cancelled':
                return <Badge variant="destructive">Cancelled</Badge>;
            default:
                return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Purchase Orders</h2>}
        >
            <Head title="Purchase Orders" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-4">
                            <CardTitle>All Orders</CardTitle>
                            <Link href={route('purchase.orders.create')}>
                                <Button>
                                    <Plus className="w-4 h-4 mr-2" />
                                    Create Order
                                </Button>
                            </Link>
                        </CardHeader>
                        <CardContent>
                            <div className="mb-4">
                                <SearchFilter
                                    routeParams={route('purchase.orders.index')}
                                    placeholder="Search by PO Number or Vendor..."
                                />
                            </div>

                            <div className="rounded-md border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead>
                                                <SortableColumn label="Document No." column="document_number" />
                                            </TableHead>
                                            <TableHead>
                                                <SortableColumn label="Vendor" column="vendor_name" />
                                            </TableHead>
                                            <TableHead>
                                                <SortableColumn label="Order Date" column="order_date" />
                                            </TableHead>
                                            <TableHead>Items</TableHead>
                                            <TableHead>
                                                <SortableColumn label="Total" column="grand_total" />
                                            </TableHead>
                                            <TableHead>Status</TableHead>
                                            <TableHead className="text-right">Actions</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {orders.data.length > 0 ? (
                                            orders.data.map((order) => (
                                                <TableRow key={order.uuid}>
                                                    <TableCell className="font-medium">
                                                        <Link
                                                            href={route('purchase.orders.show', order.id)}
                                                            className="text-blue-600 hover:underline"
                                                        >
                                                            {order.document_number}
                                                        </Link>
                                                    </TableCell>
                                                    <TableCell>
                                                        <div className="flex flex-col">
                                                            <span>{order.vendor.name}</span>
                                                            <span className="text-xs text-gray-500">{order.vendor.code}</span>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell>
                                                        {format(new Date(order.order_date), 'dd MMM yyyy')}
                                                    </TableCell>
                                                    <TableCell>{order.items_count}</TableCell>
                                                    <TableCell>
                                                        {Number(order.grand_total).toLocaleString()} ฿
                                                    </TableCell>
                                                    <TableCell>{getStatusBadge(order.status)}</TableCell>
                                                    <TableCell className="text-right">
                                                        <div className="flex justify-end gap-2">
                                                            <Link href={route('purchase.orders.show', order.id)}>
                                                                <Button variant="ghost" size="icon">
                                                                    <Eye className="w-4 h-4" />
                                                                </Button>
                                                            </Link>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        ) : (
                                            <TableRow>
                                                <TableCell colSpan={7} className="h-24 text-center">
                                                    No orders found.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>

                            <div className="mt-4">
                                <Pagination links={orders.links} />
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
