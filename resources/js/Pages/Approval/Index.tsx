import React, { useState, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { debounce } from 'lodash';
import {
    Search, Box, ArrowRight, Eye, UserCheck,
    RefreshCw, CheckCircle2, Clock, User, XCircle, FileText, AlertCircle
} from "lucide-react";

// Components (ShadCN)
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/Components/ui/tabs";
import { Textarea } from "@/Components/ui/textarea"; // เพิ่ม Textarea
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
import { cn } from '@/lib/utils';
import ApprovalsNavigationMenu from './Partials/ApprovalsNavigationMenu';

// --- Interfaces ---
interface ApprovalStep {
    order: number;
    approver_role: string;
}

interface ApprovalRequest {
    id: string;
    document_number?: string;
    subject_id: string;
    status: string;
    created_at: string;
    workflow: { name: string };
    requester: { name: string };
    current_step?: ApprovalStep;
    current_step_order: number;
}

interface Props {
    auth: any;
    approvals: {
        data: ApprovalRequest[];
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
        completed: number;
    };
}

export default function ApprovalIndex({ auth, approvals, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [currentStatus, setCurrentStatus] = useState(filters.status || 'pending');

    // State สำหรับ Modal Action
    const [selectedRequest, setSelectedRequest] = useState<ApprovalRequest | null>(null);
    const [actionType, setActionType] = useState<'approve' | 'reject' | null>(null);
    const [comment, setComment] = useState('');
    const [isLoading, setIsLoading] = useState(false);

    // --- Handlers ---

    const debouncedSearch = useCallback(
        debounce((query: string, status: string) => {
            router.get(
                route('approval.index'),
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
            route('approval.index'),
            {
                search,
                status: val === 'all' ? undefined : val
            },
            { preserveState: true, replace: true, onStart: () => setIsLoading(true), onFinish: () => setIsLoading(false) }
        );
    };

    // เปิด Modal
    const openActionDialog = (item: ApprovalRequest, type: 'approve' | 'reject') => {
        setSelectedRequest(item);
        setActionType(type);
        setComment('');
    };

    // ยืนยันการทำรายการ (Approve/Reject)
    const confirmAction = () => {
        if (!selectedRequest || !actionType) return;

        router.post(route('approval.action'), {
            request_id: selectedRequest.id,
            action: actionType,
            comment: comment
        }, {
            onSuccess: () => {
                setSelectedRequest(null);
                setActionType(null);
                setComment('');
            },
            preserveScroll: true
        });
    };

    // --- Helpers ---

    const getStatusBadge = (status: string, currentStep?: ApprovalStep) => {
        switch (status) {
            case 'pending':
                return (
                    <Badge variant="outline" className="bg-orange-50 text-orange-700 border-orange-200 gap-1 font-normal">
                        <Clock className="w-3 h-3" />
                        {currentStep ? `Wait: ${currentStep.approver_role}` : 'Pending'}
                    </Badge>
                );
            case 'approved':
                return <Badge variant="outline" className="bg-green-50 text-green-700 border-green-200 gap-1"><CheckCircle2 className="w-3 h-3" /> Approved</Badge>;
            case 'rejected':
                return <Badge variant="outline" className="bg-red-50 text-red-700 border-red-200 gap-1"><XCircle className="w-3 h-3" /> Rejected</Badge>;
            default:
                return <Badge variant="secondary">{status}</Badge>;
        }
    };

    const getInitials = (name: string) => name ? name.substring(0, 2).toUpperCase() : 'NA';

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<ApprovalsNavigationMenu />}>
            <Head title="Approval Workflow" />

            <div className="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                {/* --- Header Stats --- */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    {/* Card 1: Pending Total */}
                    <Card className="bg-gradient-to-br from-indigo-500 to-purple-600 text-white border-none shadow-md">
                        <CardContent className="p-6 flex justify-between items-center">
                            <div>
                                <p className="text-indigo-100 text-sm font-medium mb-1">Total Pending</p>
                                <h3 className="text-3xl font-bold">{stats.total_pending}</h3>
                            </div>
                            <div className="p-3 bg-white/20 rounded-full"><Clock className="w-6 h-6 text-white" /></div>
                        </CardContent>
                    </Card>

                    {/* Card 2: My Tasks */}
                    <Card className="border-none shadow-sm bg-white border-l-4 border-l-orange-400">
                        <CardContent className="p-6 flex justify-between items-center">
                            <div>
                                <p className="text-muted-foreground text-sm font-medium mb-1">My Tasks</p>
                                <h3 className="text-3xl font-bold text-gray-800">{stats.my_tasks}</h3>
                            </div>
                            <div className="p-3 bg-orange-50 rounded-full"><AlertCircle className="w-6 h-6 text-orange-500" /></div>
                        </CardContent>
                    </Card>

                    {/* Card 3: Completed */}
                    <Card className="border-none shadow-sm bg-white">
                        <CardContent className="p-6 flex justify-between items-center">
                            <div>
                                <p className="text-muted-foreground text-sm font-medium mb-1">Completed</p>
                                <h3 className="text-3xl font-bold text-gray-800">{stats.completed}</h3>
                            </div>
                            <div className="p-3 bg-green-50 rounded-full"><CheckCircle2 className="w-6 h-6 text-green-600" /></div>
                        </CardContent>
                    </Card>
                </div>

                {/* --- Main Content --- */}
                <Card className="shadow-sm border-gray-200">
                    <CardHeader className="px-6 py-4 border-b flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div>
                            <CardTitle className="text-xl font-bold text-gray-900 flex items-center gap-2">
                                <FileText className="w-6 h-6 text-blue-600" />
                                Approval Requests
                                {isLoading && <RefreshCw className="w-4 h-4 animate-spin text-gray-400" />}
                            </CardTitle>
                            <p className="text-sm text-muted-foreground mt-1">Manage documents requiring your approval.</p>
                        </div>

                        <div className="flex items-center gap-2">
                            <div className="relative w-full sm:w-64">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
                                <Input
                                    placeholder="Search Doc #, Requester..."
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
                                    {['pending', 'approved', 'rejected', 'all'].map((tab) => (
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
                                                <TableHead className="pl-6">Doc Number</TableHead>
                                                <TableHead>Workflow / Type</TableHead>
                                                <TableHead>Requester</TableHead>
                                                <TableHead className="text-center">Step</TableHead>
                                                <TableHead>Current Assignee</TableHead>
                                                <TableHead>Date</TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead className="text-right pr-6">Action</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {approvals.data.length === 0 ? (
                                                <TableRow>
                                                    <TableCell colSpan={8} className="h-48 text-center text-gray-500">
                                                        <div className="flex flex-col items-center justify-center gap-2">
                                                            <CheckCircle2 className="w-10 h-10 text-green-100" />
                                                            <p>No approval requests found.</p>
                                                            <p className="text-sm">You are all caught up!</p>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                approvals.data.map((item) => (
                                                    <TableRow key={item.id} className="hover:bg-gray-50/60 transition-colors group">
                                                        <TableCell className="pl-6 font-medium font-mono text-blue-600">
                                                            {item.document_number || item.subject_id}
                                                        </TableCell>
                                                        <TableCell>
                                                            <Badge variant="outline" className="bg-white">
                                                                {item.workflow?.name}
                                                            </Badge>
                                                        </TableCell>
                                                        <TableCell>
                                                            <div className="flex items-center gap-2">
                                                                <div className="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-[10px] font-bold text-gray-500">
                                                                    {getInitials(item.requester?.name)}
                                                                </div>
                                                                <span className="text-sm font-medium text-gray-700">
                                                                    {item.requester?.name}
                                                                </span>
                                                            </div>
                                                        </TableCell>
                                                        <TableCell className="text-center font-mono text-xs">
                                                            {item.current_step_order}
                                                        </TableCell>
                                                        <TableCell>
                                                            {item.status === 'pending' && item.current_step ? (
                                                                <span className="text-sm text-orange-600 font-medium">
                                                                    {item.current_step.approver_role}
                                                                </span>
                                                            ) : (
                                                                <span className="text-xs text-gray-400 italic">-</span>
                                                            )}
                                                        </TableCell>
                                                        <TableCell className="text-gray-500 text-xs">
                                                            {new Date(item.created_at).toLocaleDateString('th-TH', { day: '2-digit', month: 'short', hour: '2-digit', minute: '2-digit' })}
                                                        </TableCell>
                                                        <TableCell>{getStatusBadge(item.status, item.current_step)}</TableCell>
                                                        <TableCell className="text-right pr-6">
                                                            {item.status === 'pending' && (
                                                                <div className="flex justify-end gap-2">
                                                                    <Button
                                                                        size="sm"
                                                                        variant="outline"
                                                                        className="text-red-600 border-red-200 hover:bg-red-50 h-8"
                                                                        onClick={() => openActionDialog(item, 'reject')}
                                                                    >
                                                                        Reject
                                                                    </Button>
                                                                    <Button
                                                                        size="sm"
                                                                        className="bg-green-600 hover:bg-green-700 text-white h-8 shadow-sm"
                                                                        onClick={() => openActionDialog(item, 'approve')}
                                                                    >
                                                                        Approve
                                                                    </Button>
                                                                </div>
                                                            )}
                                                            {item.status !== 'pending' && (
                                                                <Button size="sm" variant="ghost" className="text-gray-500 hover:text-gray-900 h-8">
                                                                    <Eye className="w-4 h-4" />
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
                                Showing {approvals.from || 0}-{approvals.to || 0} of {approvals.total}
                            </div>
                            <div className="flex gap-1">
                                {approvals.links.map((link: any, index: number) => (
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

                {/* --- Action Dialog (Approve/Reject) --- */}
                <AlertDialog open={!!selectedRequest} onOpenChange={(open) => !open && setSelectedRequest(null)}>
                    <AlertDialogContent>
                        <AlertDialogHeader>
                            <AlertDialogTitle className={`flex items-center gap-2 ${actionType === 'approve' ? 'text-green-700' : 'text-red-700'}`}>
                                {actionType === 'approve' ? <CheckCircle2 className="w-6 h-6" /> : <XCircle className="w-6 h-6" />}
                                {actionType === 'approve' ? 'Confirm Approval' : 'Reject Request'}
                            </AlertDialogTitle>
                            <AlertDialogDescription>
                                คุณกำลังจะทำการ <strong>{actionType === 'approve' ? 'อนุมัติ' : 'ปฏิเสธ'}</strong> เอกสารหมายเลข <span className="font-bold text-black">{selectedRequest?.document_number || selectedRequest?.subject_id}</span>
                            </AlertDialogDescription>
                        </AlertDialogHeader>

                        <div className="py-2">
                            <label className="text-sm font-medium text-gray-700 mb-1 block">Comment / Remark (Optional)</label>
                            <Textarea
                                value={comment}
                                onChange={e => setComment(e.target.value)}
                                placeholder={actionType === 'approve' ? "ระบุเหตุผลการอนุมัติ..." : "ระบุสาเหตุที่ปฏิเสธ..."}
                                className={actionType === 'reject' ? 'border-red-200 focus:ring-red-500' : ''}
                            />
                        </div>

                        <AlertDialogFooter>
                            <AlertDialogCancel>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={confirmAction}
                                className={actionType === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'}
                            >
                                Confirm {actionType === 'approve' ? 'Approval' : 'Rejection'}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

            </div>
        </AuthenticatedLayout>
    );
}
