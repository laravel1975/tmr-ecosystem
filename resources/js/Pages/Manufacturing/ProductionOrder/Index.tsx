import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import SearchFilter from '@/Components/SearchFilter';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Plus, Calendar, Boxes } from 'lucide-react';
import { format } from 'date-fns'; // แนะนำให้ลง date-fns: npm install date-fns
import ManufacturingNavigationMenu from '../Partials/ManufacturingNavigationMenu';

interface ProductionOrder {
    uuid: string;
    order_number: string;
    status: 'draft' | 'planned' | 'in_progress' | 'completed' | 'cancelled';
    planned_quantity: number;
    produced_quantity: number;
    planned_start_date: string;
    planned_end_date: string | null;
    item: {
        name: string;
        part_number: string;
        uom: { symbol: string } | null;
    };
}

interface Props {
    auth: any;
    orders: {
        data: ProductionOrder[];
        links: any[];
        meta: any;
    };
    filters: any;
}

// Helper: แปลงสถานะเป็นสี Badge
const getStatusBadge = (status: string) => {
    switch (status) {
        case 'draft': return <Badge variant="secondary">Draft</Badge>;
        case 'planned': return <Badge className="bg-blue-100 text-blue-800 hover:bg-blue-200 border-0">Planned</Badge>;
        case 'in_progress': return <Badge className="bg-yellow-100 text-yellow-800 hover:bg-yellow-200 border-0 animate-pulse">In Progress</Badge>;
        case 'completed': return <Badge className="bg-green-100 text-green-800 hover:bg-green-200 border-0">Completed</Badge>;
        case 'cancelled': return <Badge variant="destructive">Cancelled</Badge>;
        default: return <Badge variant="outline">{status}</Badge>;
    }
};

export default function ProductionOrderIndex({ auth, orders, filters }: Props) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        ใบสั่งผลิต (Production Orders)
                    </h2>
                    <Link href={route('manufacturing.production-orders.create')}>
                        <Button>
                            <Plus className="w-4 h-4 mr-2" /> เปิดใบสั่งผลิต
                        </Button>
                    </Link>
                </div>
            }
            navigationMenu={<ManufacturingNavigationMenu />}
        >
            <Head title="Production Orders" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {/* Search & Filter */}
                    <div className="flex justify-between items-center">
                        <div className="w-full max-w-md">
                            <SearchFilter
                                placeholder="ค้นหาเลขที่ MO, สินค้า..."
                                routeName="manufacturing.production-orders.index"
                            />
                        </div>
                        {/* อาจเพิ่ม Filter ตาม Status ได้ตรงนี้ */}
                    </div>

                    {/* Table */}
                    <Card className="bg-white shadow-sm border-0">
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-gray-50/50">
                                        <TableHead className="w-[160px]">เลขที่สั่งผลิต</TableHead>
                                        <TableHead>สินค้า (Item)</TableHead>
                                        <TableHead className="text-right">จำนวนที่สั่ง</TableHead>
                                        <TableHead className="text-right">ผลิตเสร็จ</TableHead>
                                        <TableHead>กำหนดการ</TableHead>
                                        <TableHead className="text-center">สถานะ</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {orders.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="h-24 text-center text-gray-500">
                                                ไม่มีรายการสั่งผลิต
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        orders.data.map((order) => (
                                            <TableRow key={order.uuid} className="hover:bg-gray-50/50 transition-colors cursor-pointer" onClick={() => window.location.href = route('manufacturing.production-orders.show', order.uuid)}>
                                                <TableCell className="font-medium text-indigo-600">
                                                    {order.order_number}
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <span className="font-medium text-gray-900">{order.item.name}</span>
                                                        <span className="text-xs text-gray-500">{order.item.part_number}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right font-medium">
                                                    {Number(order.planned_quantity).toLocaleString()} <span className="text-gray-400 text-xs">{order.item.uom?.symbol}</span>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    {/* Progress Bar แบบง่ายๆ */}
                                                    <div className="flex flex-col items-end">
                                                        <span>{Number(order.produced_quantity).toLocaleString()}</span>
                                                        <div className="w-16 h-1.5 bg-gray-100 rounded-full mt-1 overflow-hidden">
                                                            <div
                                                                className="h-full bg-green-500 rounded-full"
                                                                style={{ width: `${Math.min((order.produced_quantity / order.planned_quantity) * 100, 100)}%` }}
                                                            ></div>
                                                        </div>
                                                    </div>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex items-center text-sm text-gray-500">
                                                        <Calendar className="w-3.5 h-3.5 mr-1.5 text-gray-400" />
                                                        {order.planned_start_date ? format(new Date(order.planned_start_date), 'dd/MM/yyyy') : '-'}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    {getStatusBadge(order.status)}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>

                    {/* Pagination */}
                    <div className="flex justify-end">
                        <Pagination links={orders.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
