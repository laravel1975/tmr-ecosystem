import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Pagination from '@/Components/Pagination';
import SearchFilter from '@/Components/SearchFilter';
import { Badge } from '@/Components/ui/badge';
import { Button } from '@/Components/ui/button';
import { Card, CardContent } from '@/Components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/Components/ui/table';
import { Plus, FileText, Settings } from 'lucide-react';
import ManufacturingNavigationMenu from '../Partials/ManufacturingNavigationMenu';

// Interface สำหรับข้อมูล BOM ที่ส่งมาจาก Controller
interface BOM {
    uuid: string;
    code: string;
    name: string;
    version: string;
    output_quantity: number;
    is_active: boolean;
    is_default: boolean;
    item: {
        name: string;
        part_number: string;
        uom: { symbol: string } | null;
    };
}

interface Props {
    auth: any;
    boms: {
        data: BOM[];
        links: any[];
        meta: any;
    };
    filters: any;
}

export default function BomIndex({ auth, boms, filters }: Props) {
    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex justify-between items-center">
                    <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                        สูตรการผลิต (Bill of Materials)
                    </h2>
                    <Link href={route('manufacturing.boms.create')}>
                        <Button>
                            <Plus className="w-4 h-4 mr-2" /> สร้างสูตรใหม่
                        </Button>
                    </Link>
                </div>
            }
            navigationMenu={<ManufacturingNavigationMenu />}
        >
            <Head title="Bill of Materials" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {/* Search & Filter Section */}
                    <div className="flex justify-between items-center">
                        <div className="w-full max-w-md">
                            <SearchFilter
                                placeholder="ค้นหา รหัสสูตร, ชื่อสูตร หรือสินค้า..."
                                routeName="manufacturing.boms.index"
                            />
                        </div>
                    </div>

                    {/* Table Section */}
                    <Card className="bg-white shadow-sm border-0">
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow className="bg-gray-50/50">
                                        <TableHead className="w-[150px]">รหัสสูตร</TableHead>
                                        <TableHead>ชื่อสูตร / รายละเอียด</TableHead>
                                        <TableHead>สินค้าที่ผลิต (Finished Good)</TableHead>
                                        <TableHead className="text-center">เวอร์ชัน</TableHead>
                                        <TableHead className="text-center">สถานะ</TableHead>
                                        <TableHead className="text-right">Action</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {boms.data.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="h-24 text-center text-gray-500">
                                                ไม่พบข้อมูลสูตรการผลิต
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        boms.data.map((bom) => (
                                            <TableRow key={bom.uuid} className="hover:bg-gray-50/50 transition-colors">
                                                <TableCell className="font-medium">
                                                    <Link href={route('manufacturing.boms.index', bom.uuid)} className="text-indigo-600 hover:underline">
                                                        {bom.code}
                                                    </Link>
                                                </TableCell>
                                                <TableCell>{bom.name}</TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <span className="font-medium text-gray-900">{bom.item.name}</span>
                                                        <span className="text-xs text-gray-500">{bom.item.part_number}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <Badge variant="outline">v{bom.version}</Badge>
                                                </TableCell>
                                                <TableCell className="text-center">
                                                    <div className="flex flex-col gap-1 items-center">
                                                        {bom.is_active ? (
                                                            <Badge className="bg-green-100 text-green-800 hover:bg-green-200 border-0">Active</Badge>
                                                        ) : (
                                                            <Badge variant="secondary">Inactive</Badge>
                                                        )}
                                                        {bom.is_default && (
                                                            <span className="text-[10px] text-blue-600 font-medium bg-blue-50 px-1.5 py-0.5 rounded">Default</span>
                                                        )}
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Button variant="ghost" size="icon" asChild>
                                                        <Link href={route('manufacturing.boms.index', bom.uuid)}>
                                                            <Settings className="w-4 h-4 text-gray-500" />
                                                        </Link>
                                                    </Button>
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
                        <Pagination links={boms.links} />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
