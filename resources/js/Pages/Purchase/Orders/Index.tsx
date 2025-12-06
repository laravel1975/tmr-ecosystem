import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react'; // เพิ่ม router
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Table,
    TableBody,
    TableCell,
    TableHead, // ยังต้องใช้สำหรับคอลัมน์ที่ไม่ใช่ Sortable
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';
import { Badge } from '@/Components/ui/badge';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Plus, Eye, FileText, ShoppingCart, FileQuestion, CheckCircle } from 'lucide-react';
import Pagination from '@/Components/Pagination';
import SearchFilter from '@/Components/SearchFilter';
import SortableColumn from '@/Components/SortableColumn';
import { format } from 'date-fns';
import PurchaseNavigationMenu from '../Partials/PurchaseNavigationMenu';

// Interfaces... (คงเดิม)
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

interface OrderStats {
    total_requests: number;
    rfq_pending: number;
    completed: number;
}

interface Props {
    auth: any;
    orders: {
        data: PurchaseOrder[];
        links: any[];
        meta: any;
    };
    // อัปเดต Type ของ filters ให้รองรับ search, sort, direction
    filters: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
    };
    stats: OrderStats;
}

export default function Index({ auth, orders, filters, stats }: Props) {
    // 1. สร้าง State สำหรับ Search
    const [search, setSearch] = useState(filters.search || '');

    // 2. ใช้ useEffect เพื่อทำ Debounce Search (ค้นหาเมื่อหยุดพิมพ์ 300ms)
    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters.search || '')) {
                router.get(
                    route('purchase.orders.index'),
                    { ...filters, search: search, page: 1 }, // Reset page เมื่อ search
                    { preserveState: true, replace: true }
                );
            }
        }, 300);

        return () => clearTimeout(timer);
    }, [search]);

    // 3. ฟังก์ชันสำหรับ Sorting
    const handleSort = (key: string) => {
        const newDirection = filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get(
            route('purchase.orders.index'),
            { ...filters, sort: key, direction: newDirection },
            { preserveState: true }
        );
    };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'draft': return <Badge variant="secondary">Draft</Badge>;
            case 'ordered': return <Badge className="bg-blue-500 hover:bg-blue-600">Ordered</Badge>;
            case 'received': return <Badge className="bg-green-500 hover:bg-green-600">Received</Badge>;
            case 'cancelled': return <Badge variant="destructive">Cancelled</Badge>;
            default: return <Badge variant="outline">{status}</Badge>;
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        Purchase Orders
                    </h2>
                    <div className="flex gap-2">
                        <Link href={route('purchase.orders.create')}>
                            <Button>
                                <Plus className="w-4 h-4 mr-2" />
                                Create Order
                            </Button>
                        </Link>
                    </div>
                </div>
            }
            navigationMenu={<PurchaseNavigationMenu />}
        >
            <Head title="Purchase Orders" />

            <div className="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                {/* Stats Cards (คงเดิม) */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Total Requests</CardTitle>
                            <ShoppingCart className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{stats.total_requests}</div>
                            <p className="text-xs text-muted-foreground">All purchase orders created</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Requests for Quotation</CardTitle>
                            <FileQuestion className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-orange-600">{stats.rfq_pending}</div>
                            <p className="text-xs text-muted-foreground">Pending confirmation (Drafts)</p>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle className="text-sm font-medium">Complete Orders</CardTitle>
                            <CheckCircle className="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold text-green-600">{stats.completed}</div>
                            <p className="text-xs text-muted-foreground">Fully received and closed</p>
                        </CardContent>
                    </Card>
                </div>

                {/* Main Content Card */}
                <Card>
                    <CardHeader className="border-b px-6 py-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle>Order List</CardTitle>
                                <CardDescription>Manage and track your purchase orders.</CardDescription>
                            </div>
                            <div className="w-full md:w-auto">
                                {/* แก้ไข SearchFilter: ส่ง value และ onChange */}
                                <SearchFilter
                                    value={search}
                                    onChange={setSearch}
                                    placeholder="Search PO Number or Vendor..."
                                    className="w-full md:w-[300px]"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <div className="rounded-none border-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {/* แก้ไข SortableColumn: ลบ TableHead ที่ครอบออก และใช้ Props ให้ถูก */}
                                        <SortableColumn
                                            label="Document No."
                                            sortKey="document_number"
                                            currentSort={filters.sort || ''}
                                            currentDirection={filters.direction || 'asc'}
                                            onSort={handleSort}
                                            className="pl-6"
                                        />
                                        <SortableColumn
                                            label="Vendor"
                                            sortKey="vendor_name" // ต้องเช็คกับ Controller ว่าใช้ key นี้ได้ไหม หรือต้องแก้เป็น vendor.name
                                            currentSort={filters.sort || ''}
                                            currentDirection={filters.direction || 'asc'}
                                            onSort={handleSort}
                                        />
                                        <SortableColumn
                                            label="Order Date"
                                            sortKey="order_date"
                                            currentSort={filters.sort || ''}
                                            currentDirection={filters.direction || 'asc'}
                                            onSort={handleSort}
                                        />
                                        {/* คอลัมน์ที่ Sort ไม่ได้ให้ใช้ TableHead ธรรมดา */}
                                        <TableHead>Items</TableHead>
                                        <SortableColumn
                                            label="Total"
                                            sortKey="grand_total"
                                            currentSort={filters.sort || ''}
                                            currentDirection={filters.direction || 'asc'}
                                            onSort={handleSort}
                                        />
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right pr-6">Actions</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {orders.data.length > 0 ? (
                                        orders.data.map((order) => (
                                            <TableRow key={order.uuid}>
                                                <TableCell className="font-medium pl-6">
                                                    <Link
                                                        href={route('purchase.orders.show', order.id)}
                                                        className="text-blue-600 hover:underline flex items-center gap-2"
                                                    >
                                                        <FileText className="w-4 h-4" />
                                                        {order.document_number}
                                                    </Link>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <span className="font-medium">{order.vendor.name}</span>
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
                                                <TableCell className="text-right pr-6">
                                                    <div className="flex justify-end gap-2">
                                                        <Link href={route('purchase.orders.show', order.id)}>
                                                            <Button variant="ghost" size="icon" className="h-8 w-8">
                                                                <Eye className="w-4 h-4" />
                                                            </Button>
                                                        </Link>
                                                    </div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    ) : (
                                        <TableRow>
                                            <TableCell colSpan={7} className="h-24 text-center text-muted-foreground">
                                                No orders found.
                                            </TableCell>
                                        </TableRow>
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    </CardContent>
                </Card>

                <div className="mt-4">
                    <Pagination links={orders.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
