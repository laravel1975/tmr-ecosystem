import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import {
    Factory,
    FileText,
    Settings,
    ClipboardList,
    CheckCircle2,
    Clock,
    AlertCircle
} from 'lucide-react';
import ManufacturingNavigationMenu from './Partials/ManufacturingNavigationMenu';

interface DashboardStats {
    boms: {
        total: number;
        active: number;
    };
    orders: {
        total: number;
        draft: number;
        planned: number;
        in_progress: number;
        completed: number;
    };
}

interface Props {
    auth: any;
    stats: DashboardStats;
}

export default function ManufacturingDashboard({ auth, stats }: Props) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">ภาพรวมการผลิต (Manufacturing Overview)</h2>}
            navigationMenu={<ManufacturingNavigationMenu />}
        >
            <Head title="Manufacturing Dashboard" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-8">

                    {/* --- Quick Actions --- */}
                    <div className="flex gap-4">
                        <Link href={route('manufacturing.production-orders.create')}>
                            <Button className="shadow-sm">
                                <Factory className="mr-2 h-4 w-4" /> เปิดใบสั่งผลิตใหม่
                            </Button>
                        </Link>
                        <Link href={route('manufacturing.boms.create')}>
                            <Button variant="outline" className="shadow-sm">
                                <FileText className="mr-2 h-4 w-4" /> สร้างสูตรการผลิต (BOM)
                            </Button>
                        </Link>
                    </div>

                    {/* --- Stats Grid --- */}
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">

                        {/* 1. BOM Stats */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-gray-500">
                                    สูตรการผลิตทั้งหมด
                                </CardTitle>
                                <Settings className="h-4 w-4 text-gray-400" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.boms.total}</div>
                                <p className="text-xs text-gray-500 mt-1">
                                    ใช้งานอยู่ (Active): {stats.boms.active}
                                </p>
                            </CardContent>
                        </Card>

                        {/* 2. Total Orders */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-gray-500">
                                    ใบสั่งผลิตทั้งหมด
                                </CardTitle>
                                <ClipboardList className="h-4 w-4 text-gray-400" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold">{stats.orders.total}</div>
                                <p className="text-xs text-gray-500 mt-1">
                                    รวมทุกสถานะ
                                </p>
                            </CardContent>
                        </Card>

                        {/* 3. In Progress */}
                        <Card className="border-yellow-200 bg-yellow-50/30">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-yellow-700">
                                    กำลังดำเนินการผลิต
                                </CardTitle>
                                <Clock className="h-4 w-4 text-yellow-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-yellow-700">{stats.orders.in_progress}</div>
                                <p className="text-xs text-yellow-600 mt-1">
                                    Planned: {stats.orders.planned}
                                </p>
                            </CardContent>
                        </Card>

                        {/* 4. Completed */}
                        <Card className="border-green-200 bg-green-50/30">
                            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                                <CardTitle className="text-sm font-medium text-green-700">
                                    ผลิตเสร็จสิ้น
                                </CardTitle>
                                <CheckCircle2 className="h-4 w-4 text-green-600" />
                            </CardHeader>
                            <CardContent>
                                <div className="text-2xl font-bold text-green-700">{stats.orders.completed}</div>
                                <p className="text-xs text-green-600 mt-1">
                                    Ready for stock
                                </p>
                            </CardContent>
                        </Card>
                    </div>

                    {/* --- Navigation Cards --- */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        {/* Link to BOM Index */}
                        <Link href={route('manufacturing.boms.index')} className="block group">
                            <Card className="h-full hover:border-indigo-300 hover:shadow-md transition-all cursor-pointer">
                                <CardHeader>
                                    <CardTitle className="flex items-center text-indigo-700 group-hover:text-indigo-900">
                                        <Settings className="mr-2 h-5 w-5" />
                                        จัดการสูตรการผลิต (BOM Management)
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-gray-600">
                                        ดูรายการสูตรการผลิตทั้งหมด แก้ไขส่วนประกอบ หรือปรับราคาต้นทุนมาตรฐาน
                                    </p>
                                </CardContent>
                            </Card>
                        </Link>

                        {/* Link to Production Order Index */}
                        <Link href={route('manufacturing.production-orders.index')} className="block group">
                            <Card className="h-full hover:border-blue-300 hover:shadow-md transition-all cursor-pointer">
                                <CardHeader>
                                    <CardTitle className="flex items-center text-blue-700 group-hover:text-blue-900">
                                        <Factory className="mr-2 h-5 w-5" />
                                        จัดการใบสั่งผลิต (Order Management)
                                    </CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <p className="text-sm text-gray-600">
                                        ติดตามสถานะการผลิต อัปเดตความคืบหน้า และบันทึกยอดรับเข้าคลังสินค้า
                                    </p>
                                </CardContent>
                            </Card>
                        </Link>
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
