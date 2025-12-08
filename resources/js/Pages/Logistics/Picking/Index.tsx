import React, { useState, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { debounce } from 'lodash';
import {
    Search, Box, ArrowRight, Eye, UserPlus, UserCheck,
    RefreshCw, CheckCircle2, Clock, User, FileText // เพิ่ม FileText
} from "lucide-react";

// Components
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/Components/ui/tabs";
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
} from "@/Components/ui/alert-dialog";
import InventoryNavigationMenu from '@/Pages/Inventory/Partials/InventoryNavigationMenu';
import { cn } from '@/lib/utils';

// --- Interfaces ---
interface PickingSlip {
    id: string;
    picking_number: string;
    order_number: string;
    customer_name: string;
    items_count: number;
    status: string; // pending, assigned, done
    created_at: string;
    picker_name?: string | null;
    picker_user_id?: number | null;
    // --- เพิ่มฟิลด์สำหรับเช็ค Logic ใบเสร็จ (ต้องตรงกับ Backend Resource) ---
    payment_status?: string; // เช่น 'paid', 'pending', 'unpaid'
    receipt_path?: string | null; // หรือ receipt_url
}

interface Props {
    auth: any;
    pickingSlips: {
        data: PickingSlip[];
        links: any[];
        current_page: number;
        last_page: number;
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search: string;
        status: string;
    };
    stats: {
        total_pending: number;
        my_tasks: number;
    };
}

export default function PickingIndex({ auth, pickingSlips, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [currentStatus, setCurrentStatus] = useState(filters.status || 'all');
    const [claimingSlip, setClaimingSlip] = useState<PickingSlip | null>(null);
    const [isLoading, setIsLoading] = useState(false);

    // --- Handlers ---

    const debouncedSearch = useCallback(
        debounce((query: string, status: string) => {
            router.get(
                route('logistics.picking.index'),
                {
                    search: query,
                    status: status === 'all' ? undefined : status
                },
                { preserveState: true, replace: true, onStart: () => setIsLoading(true), onFinish: () => setIsLoading(false) }
            );
        }, 400), []
    );

    const handleSearchChange = (val: string) => {
        setSearch(val);
        debouncedSearch(val, currentStatus);
    };

    const handleTabChange = (val: string) => {
        setCurrentStatus(val);
        router.get(
            route('logistics.picking.index'),
            {
                search,
                status: val === 'all' ? undefined : val
            },
            { preserveState: true, replace: true, onStart: () => setIsLoading(true), onFinish: () => setIsLoading(false) }
        );
    };

    const handleReset = () => {
        setSearch('');
        setCurrentStatus('all');
        router.get(route('logistics.picking.index'), {}, { replace: true });
    };

    const handleClaimClick = (slip: PickingSlip) => setClaimingSlip(slip);

    const confirmClaim = () => {
        if (!claimingSlip) return;
        router.post(route('logistics.picking.assign', claimingSlip.id), {}, {
            onSuccess: () => setClaimingSlip(null),
            preserveScroll: true
        });
    };

    // --- Helpers (Logic Badge อยู่ที่นี่) ---

    const getStatusBadge = (slip: PickingSlip) => {
        // 1. Logic พิเศษ: จ่ายเงินแล้ว แต่ยังไม่มีใบเสร็จ (Waiting Receipt)
        // ตรวจสอบว่า payment_status เป็น 'paid' และ receipt_path เป็น null/ว่าง
        if (slip.payment_status === 'paid' && !slip.receipt_path) {
            return (
                <Badge variant="outline" className="bg-orange-50 text-orange-700 border-orange-200 gap-1">
                    <FileText className="w-3 h-3" /> Waiting Receipt
                </Badge>
            );
        }

        // 2. Logic สถานะปกติ (Pending, Assigned, Done)
        switch (slip.status) {
            case 'pending':
                return <Badge variant="outline" className="bg-yellow-50 text-yellow-700 border-yellow-200 gap-1"><Clock className="w-3 h-3"/> Pending</Badge>;
            case 'assigned':
                return <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200 gap-1"><User className="w-3 h-3"/> Assigned</Badge>;
            case 'done':
                return <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200 gap-1"><CheckCircle2 className="w-3 h-3"/> Done</Badge>;
            default:
                return <Badge variant="secondary">{slip.status}</Badge>;
        }
    };

    const getInitials = (name: string) => name ? name.substring(0, 2).toUpperCase() : 'NA';

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<InventoryNavigationMenu />}>
            <Head title="Picking Operations" />

            <div className="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                {/* --- Header Stats --- */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card className="bg-gradient-to-br from-indigo-500 to-purple-600 text-white border-none shadow-md">
                        <CardContent className="p-6 flex justify-between items-center">
                            <div>
                                <p className="text-indigo-100 text-sm font-medium mb-1">Total Pending</p>
                                <h3 className="text-3xl font-bold">{stats.total_pending}</h3>
                            </div>
                            <div className="p-3 bg-white/20 rounded-full"><Box className="w-6 h-6 text-white" /></div>
                        </CardContent>
                    </Card>
                    <Card className="border-none shadow-sm bg-white">
                        <CardContent className="p-6 flex justify-between items-center">
                            <div>
                                <p className="text-muted-foreground text-sm font-medium mb-1">My Tasks</p>
                                <h3 className="text-3xl font-bold text-gray-800">{stats.my_tasks}</h3>
                            </div>
                            <div className="p-3 bg-blue-50 rounded-full"><UserCheck className="w-6 h-6 text-blue-600" /></div>
                        </CardContent>
                    </Card>
                </div>

                {/* --- Main Content --- */}
                <Card className="shadow-sm border-gray-200">
                    <CardHeader className="px-6 py-4 border-b flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <CardTitle className="text-xl font-bold text-gray-900 flex items-center gap-2">
                                Picking Operations
                                {isLoading && <RefreshCw className="w-4 h-4 animate-spin text-gray-400" />}
                            </CardTitle>
                            <p className="text-sm text-muted-foreground mt-1">Manage picking slips and assignments.</p>
                        </div>

                        <div className="flex items-center gap-2">
                            <div className="relative w-full sm:w-64">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
                                <Input
                                    placeholder="Search Picking #, SO #..."
                                    className="pl-9 bg-white"
                                    value={search}
                                    onChange={e => handleSearchChange(e.target.value)}
                                />
                            </div>
                        </div>
                    </CardHeader>

                    <div className="p-0">
                        <Tabs value={currentStatus} onValueChange={handleTabChange} className="w-full">
                            <div className="px-6 pt-4 pb-0 border-b">
                                <TabsList className="bg-transparent p-0 h-auto space-x-6">
                                    {['all', 'pending', 'assigned', 'done'].map((tab) => (
                                        <TabsTrigger
                                            key={tab}
                                            value={tab}
                                            className="data-[state=active]:border-b-2 data-[state=active]:border-indigo-600 data-[state=active]:text-indigo-600 data-[state=active]:shadow-none rounded-none px-2 py-3 text-gray-500 hover:text-gray-700 capitalize bg-transparent"
                                        >
                                            {tab}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                            </div>

                            <TabsContent value={currentStatus} className="m-0">
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader className="bg-gray-50/50">
                                            <TableRow>
                                                <TableHead className="pl-6">Reference</TableHead>
                                                <TableHead>Source Doc</TableHead>
                                                <TableHead>Customer</TableHead>
                                                <TableHead className="text-center">Items</TableHead>
                                                <TableHead>Assigned To</TableHead>
                                                <TableHead>Date</TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead className="text-right pr-6">Action</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {pickingSlips.data.length === 0 ? (
                                                <TableRow>
                                                    <TableCell colSpan={8} className="h-32 text-center">
                                                        <div className="flex flex-col items-center justify-center text-gray-400">
                                                            <Box className="w-8 h-8 mb-2 opacity-20" />
                                                            <p>No picking tasks found.</p>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                pickingSlips.data.map((slip) => (
                                                    <TableRow key={slip.id} className="hover:bg-gray-50/60 transition-colors group">
                                                        <TableCell className="pl-6 font-medium">
                                                            <Link
                                                                href={route('logistics.picking.show', slip.id)}
                                                                className="text-indigo-600 hover:underline"
                                                            >
                                                                {slip.picking_number}
                                                            </Link>
                                                        </TableCell>
                                                        <TableCell className="text-gray-600">{slip.order_number}</TableCell>
                                                        <TableCell>
                                                            <div className="flex items-center gap-2">
                                                                <div className="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-[10px] font-bold text-gray-500">
                                                                    {getInitials(slip.customer_name)}
                                                                </div>
                                                                <span className="text-sm font-medium text-gray-700 line-clamp-1 max-w-[150px]" title={slip.customer_name}>
                                                                    {slip.customer_name}
                                                                </span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-center">
                                                            <Badge variant="secondary" className="font-mono">{slip.items_count}</Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            {slip.picker_name ? (
                                                                <div className="flex items-center gap-2">
                                                                    <div className="w-6 h-6 rounded-full bg-indigo-50 flex items-center justify-center text-[10px] font-bold text-indigo-600 border border-indigo-100">
                                                                        {getInitials(slip.picker_name)}
                                                                    </div>
                                                                    <span className="text-sm text-gray-700">{slip.picker_name}</span>
                                                                </div>
                                                            ) : (
                                                                <span className="text-xs text-gray-400 italic">Unassigned</span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-gray-500 text-xs">
                                                            {new Date(slip.created_at).toLocaleDateString('th-TH', { day: '2-digit', month: 'short', hour: '2-digit', minute:'2-digit'})}
                                                        </TableCell>
                                                        {/* เรียกใช้ Function Badge โดยส่งทั้ง Object */}
                                                        <TableCell>{getStatusBadge(slip)}</TableCell>
                                                        <TableCell className="text-right pr-6">
                                                            {(slip.status === 'pending'||slip.status === 'ready') && (
                                                                <Button
                                                                    size="sm"
                                                                    className="bg-amber-500 hover:bg-amber-600 text-white shadow-sm h-8"
                                                                    onClick={() => handleClaimClick(slip)}
                                                                >
                                                                    <UserPlus className="w-3.5 h-3.5 mr-1.5" /> Claim
                                                                </Button>
                                                            )}
                                                            {slip.status === 'assigned' && (
                                                                <Button
                                                                    size="sm"
                                                                    variant="outline"
                                                                    className="border-indigo-200 text-indigo-700 hover:bg-indigo-50 h-8"
                                                                    asChild
                                                                >
                                                                    <Link href={route('logistics.picking.process', slip.id)}>
                                                                        Process <ArrowRight className="w-3.5 h-3.5 ml-1" />
                                                                    </Link>
                                                                </Button>
                                                            )}
                                                            {slip.status === 'done' && (
                                                                <Button size="sm" variant="ghost" className="text-gray-500 hover:text-gray-900 h-8" asChild>
                                                                    <Link href={route('logistics.picking.show', slip.id)}>
                                                                        <Eye className="w-4 h-4" />
                                                                    </Link>
                                                                </Button>
                                                            )}
                                                        </TableCell>
                                                    </TableRow>
                                                ))
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            </TabsContent>
                        </Tabs>

                        {/* --- Pagination --- */}
                        <div className="p-4 border-t bg-gray-50 flex items-center justify-between">
                            <div className="text-xs text-gray-500">
                                Showing {pickingSlips.from}-{pickingSlips.to} of {pickingSlips.total}
                            </div>
                            <div className="flex gap-1">
                                {pickingSlips.links.map((link: any, index: number) => (
                                    <Button
                                        key={index}
                                        variant={link.active ? "default" : "outline"}
                                        size="sm"
                                        className={cn("h-8 px-3", !link.url && "opacity-50 cursor-not-allowed")}
                                        asChild={!!link.url}
                                        disabled={!link.url}
                                    >
                                        {link.url ? (
                                            <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                                        ) : (
                                            <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                        )}
                                    </Button>
                                ))}
                            </div>
                        </div>
                    </div>
                </Card>

                {/* --- Confirmation Modal --- */}
                <AlertDialog open={!!claimingSlip} onOpenChange={(open) => !open && setClaimingSlip(null)}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle className="flex items-center gap-2">
                                <UserPlus className="w-5 h-5 text-indigo-600"/> Claim Task
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                คุณต้องการรับงาน Picking Slip หมายเลข <span className="font-bold text-black">{claimingSlip?.picking_number}</span> ใช่หรือไม่?
                                <br /><br />
                                <span className="bg-yellow-50 text-yellow-800 text-xs px-2 py-1 rounded border border-yellow-200">
                                    Note: สถานะจะเปลี่ยนเป็น "Assigned" และคุณต้องรับผิดชอบงานนี้
                                </span>
                            </AlertDialogDescription>
                        </AlertDialogHeader>
                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction onClick={confirmClaim} className="bg-indigo-600 hover:bg-indigo-700">
                                Confirm & Start
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

            </div>
        </AuthenticatedLayout>
    );
}
