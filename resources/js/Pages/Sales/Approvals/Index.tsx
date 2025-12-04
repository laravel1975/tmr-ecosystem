import React, { useState, useCallback, useMemo } from 'react';
import { Head, Link, router, usePage } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { debounce } from 'lodash';
import {
    Search, FileText, CheckCircle2, XCircle,
    Clock, AlertCircle, Filter, DollarSign,
    UserPlus, Truck, Factory, AlertTriangle,
    Briefcase, CalendarClock, CreditCard
} from 'lucide-react';

// UI Components (ShadCN)
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Tabs, TabsList, TabsTrigger, TabsContent } from "@/Components/ui/tabs";
import { Textarea } from "@/Components/ui/textarea";
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/Components/ui/select"
import { cn } from '@/lib/utils';
import SalesNavigationMenu from '@/Pages/Sales/Partials/SalesNavigationMenu'; // เมนูของ Sales

// --- Interfaces ---
interface ApprovalStep {
    order: number;
    approver_role: string;
}

interface ApprovalRequest {
    id: string;
    document_number: string;
    subject_id: string; // ID ของ Order/Customer จริง
    subject_type: string;
    status: string;
    created_at: string;
    workflow: {
        name: string;
        code: string;
    };
    requester: { name: string; avatar?: string };
    current_step?: ApprovalStep;
    current_step_order: number;
    payload_snapshot?: any; // ข้อมูลสำคัญ เช่น %ส่วนลด, วงเงิน
}

interface Props {
    auth: any;
    approvals: {
        data: ApprovalRequest[];
        links: any[];
        total: number;
        from: number;
        to: number;
    };
    filters: {
        search: string;
        status: string;
        type: string;
    };
    stats: {
        total_pending: number;
        urgent_tasks: number;
        completed_today: number;
    };
}

// --- 🛠️ Helper: Config สำหรับ 15 Scenarios ---
// ใช้ Mapping เพื่อให้ UI แสดงผล Icon และสีที่สื่อความหมายถูกต้อง
const getWorkflowConfig = (code: string) => {
    switch (code) {
        // 💰 Price & Sales
        case 'SALES_PRICE_APPROVE':
        case 'SALES_DISCOUNT_APPROVE':
        case 'SALES_QUOTATION_APPROVE':
            return { icon: DollarSign, color: 'text-green-600', bg: 'bg-green-50', label: 'Price & Discount' };

        // 👥 Customer & Credit
        case 'CRM_NEW_CUSTOMER':
        case 'FINANCE_CREDIT_LIMIT':
            return { icon: UserPlus, color: 'text-blue-600', bg: 'bg-blue-50', label: 'Customer & Credit' };

        // 🏭 Production & Urgent
        case 'PROD_URGENT_ORDER':
            return { icon: AlertTriangle, color: 'text-red-600', bg: 'bg-red-50', label: 'Urgent / Rush' };
        case 'QC_SPEC_CHANGE':
        case 'MKT_ARTWORK_APPROVE':
        case 'ENG_NEW_MOLD':
        case 'PROD_START_JOB':
            return { icon: Factory, color: 'text-purple-600', bg: 'bg-purple-50', label: 'Production / Spec' };

        // 🔄 After Sales (RMA/Claim)
        case 'LOG_RMA_APPROVE':
        case 'QC_CLAIM_REQUEST':
        case 'SALES_REPLACEMENT':
            return { icon: Truck, color: 'text-orange-600', bg: 'bg-orange-50', label: 'RMA & Claim' };

        // 📂 General
        case 'GEN_NEW_PROJECT':
        case 'ACC_EXTRA_EXPENSE':
            return { icon: Briefcase, color: 'text-gray-600', bg: 'bg-gray-100', label: 'Project / Expense' };

        default:
            return { icon: FileText, color: 'text-gray-600', bg: 'bg-gray-50', label: 'General Request' };
    }
};

export default function SalesApprovalIndex({ auth, approvals, filters, stats }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [currentStatus, setCurrentStatus] = useState(filters.status || 'pending');

    // State สำหรับ Modal Action
    const [selectedRequest, setSelectedRequest] = useState<ApprovalRequest | null>(null);
    const [actionType, setActionType] = useState<'approve' | 'reject' | null>(null);
    const [comment, setComment] = useState('');
    const [isProcessing, setIsProcessing] = useState(false);

    // --- Search & Filter Logic ---
    const debouncedSearch = useCallback(
        debounce((query: string, status: string) => {
            router.get(
                route('sales.approvals.index'), // ตรวจสอบ Route Name ของคุณ
                { search: query, status: status === 'all' ? undefined : status },
                { preserveState: true, replace: true }
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
            route('sales.approvals.index'),
            { search, status: val === 'all' ? undefined : val },
            { preserveState: true, replace: true }
        );
    };

    // --- Action Handlers ---
    const openActionDialog = (item: ApprovalRequest, type: 'approve' | 'reject') => {
        setSelectedRequest(item);
        setActionType(type);
        setComment('');
    };

    const confirmAction = () => {
        if (!selectedRequest || !actionType) return;
        setIsProcessing(true);

        router.post(route('approval.action'), {
            request_id: selectedRequest.id,
            action: actionType,
            comment: comment
        }, {
            onSuccess: () => {
                setSelectedRequest(null);
                setActionType(null);
                setComment('');
                setIsProcessing(false);
            },
            onError: () => setIsProcessing(false),
            preserveScroll: true
        });
    };

    // --- Render Helpers ---
    const renderPayloadDetails = (item: ApprovalRequest) => {
        const payload = item.payload_snapshot || {};
        const config = getWorkflowConfig(item.workflow.code);

        // แสดงรายละเอียดตามประเภท (Smart Display)
        if (item.workflow.code === 'SALES_DISCOUNT_APPROVE') {
            return <div className="text-xs text-red-600 font-semibold">Request Discount: {payload.discount_percent}%</div>;
        }
        if (item.workflow.code === 'PROD_URGENT_ORDER') {
            return <div className="text-xs text-red-600 font-bold flex items-center gap-1"><AlertTriangle className="w-3 h-3"/> URGENT: {payload.reason}</div>;
        }
        if (item.workflow.code === 'FINANCE_CREDIT_LIMIT') {
            return <div className="text-xs text-blue-600">New Limit: {Number(payload.new_credit_limit).toLocaleString()} THB</div>;
        }
        // Default
        return <div className="text-xs text-gray-500">{config.label}</div>;
    };

    const getInitials = (name: string) => name ? name.substring(0, 2).toUpperCase() : 'NA';

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<SalesNavigationMenu />}>
            <Head title="Sales Approvals" />

            <div className="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 space-y-6">

                {/* --- Header Stats (KPIs) --- */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <Card className="bg-gradient-to-br from-blue-600 to-indigo-700 text-white border-none shadow-md">
                        <CardContent className="p-6 flex justify-between items-center">
                            <div>
                                <p className="text-blue-100 text-sm font-medium mb-1">Waiting for You</p>
                                <h3 className="text-3xl font-bold">{stats.total_pending}</h3>
                                <p className="text-xs text-blue-200 mt-1">Pending Approval Tasks</p>
                            </div>
                            <div className="p-3 bg-white/20 rounded-full"><Clock className="w-6 h-6 text-white" /></div>
                        </CardContent>
                    </Card>

                    <Card className="bg-white border-l-4 border-l-red-500 shadow-sm">
                        <CardContent className="p-6 flex justify-between items-center">
                            <div>
                                <p className="text-gray-500 text-sm font-medium mb-1">Urgent Requests</p>
                                <h3 className="text-3xl font-bold text-red-600">{stats.urgent_tasks}</h3>
                                <p className="text-xs text-gray-400 mt-1">Require immediate attention</p>
                            </div>
                            <div className="p-3 bg-red-50 rounded-full"><AlertTriangle className="w-6 h-6 text-red-500" /></div>
                        </CardContent>
                    </Card>

                    <Card className="bg-white shadow-sm">
                        <CardContent className="p-6 flex justify-between items-center">
                            <div>
                                <p className="text-gray-500 text-sm font-medium mb-1">Completed Today</p>
                                <h3 className="text-3xl font-bold text-green-600">{stats.completed_today}</h3>
                                <p className="text-xs text-gray-400 mt-1">Approvals processed</p>
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
                                Approval Workflow
                            </CardTitle>
                            <p className="text-sm text-muted-foreground mt-1">Manage all sales related approval requests.</p>
                        </div>

                        <div className="flex items-center gap-2 w-full sm:w-auto">
                            <div className="relative w-full sm:w-64">
                                <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-gray-400" />
                                <Input
                                    placeholder="Search Doc #, Subject..."
                                    className="pl-9 bg-white"
                                    value={search}
                                    onChange={e => handleSearchChange(e.target.value)}
                                />
                            </div>
                            <Button variant="outline" size="icon">
                                <Filter className="h-4 w-4 text-gray-500" />
                            </Button>
                        </div>
                    </CardHeader>

                    <div className="p-0">
                        <Tabs value={currentStatus} onValueChange={handleTabChange} className="w-full">
                            <div className="px-6 pt-4 pb-0 border-b bg-gray-50/30">
                                <TabsList className="bg-transparent p-0 h-auto space-x-6">
                                    {['pending', 'approved', 'rejected', 'all'].map((tab) => (
                                        <TabsTrigger
                                            key={tab}
                                            value={tab}
                                            className="data-[state=active]:border-b-2 data-[state=active]:border-blue-600 data-[state=active]:text-blue-700 data-[state=active]:shadow-none rounded-none px-2 py-3 text-gray-500 hover:text-gray-700 capitalize bg-transparent font-medium"
                                        >
                                            {tab === 'pending' ? 'Pending Actions' : tab}
                                        </TabsTrigger>
                                    ))}
                                </TabsList>
                            </div>

                            <TabsContent value={currentStatus} className="m-0">
                                <div className="overflow-x-auto">
                                    <Table>
                                        <TableHeader className="bg-gray-50/50">
                                            <TableRow>
                                                <TableHead className="pl-6 w-[250px]">Document / Subject</TableHead>
                                                <TableHead>Type</TableHead>
                                                <TableHead>Details (Key Info)</TableHead>
                                                <TableHead>Requester</TableHead>
                                                <TableHead>Status</TableHead>
                                                <TableHead className="text-right pr-6">Action</TableHead>
                                            </TableRow>
                                        </TableHeader>
                                        <TableBody>
                                            {approvals.data.length === 0 ? (
                                                <TableRow>
                                                    <TableCell colSpan={6} className="h-48 text-center text-gray-500">
                                                        <div className="flex flex-col items-center justify-center gap-2">
                                                            <CheckCircle2 className="w-12 h-12 text-green-100" />
                                                            <p className="font-medium text-gray-900">All caught up!</p>
                                                            <p className="text-sm">No requests found for this status.</p>
                                                        </div>
                                                    </TableCell>
                                                </TableRow>
                                            ) : (
                                                approvals.data.map((item) => {
                                                    const config = getWorkflowConfig(item.workflow.code);
                                                    const Icon = config.icon;

                                                    return (
                                                        <TableRow key={item.id} className="hover:bg-blue-50/30 transition-colors group">
                                                            {/* Document & Subject */}
                                                            <TableCell className="pl-6">
                                                                <div className="flex flex-col">
                                                                    <span className="font-bold text-gray-800 text-base">{item.document_number}</span>
                                                                    <span className="text-xs text-gray-500 font-mono">ID: {item.subject_id}</span>
                                                                    <span className="text-xs text-gray-400 mt-1">{new Date(item.created_at).toLocaleString()}</span>
                                                                </div>
                                                            </TableCell>

                                                            {/* Workflow Type */}
                                                            <TableCell>
                                                                <div className={`flex items-center gap-2 px-2.5 py-1 rounded-md w-fit border ${config.bg} border-opacity-50`}>
                                                                    <Icon className={`w-4 h-4 ${config.color}`} />
                                                                    <span className={`text-xs font-semibold ${config.color}`}>{item.workflow.name}</span>
                                                                </div>
                                                            </TableCell>

                                                            {/* Smart Details Payload */}
                                                            <TableCell>
                                                                {renderPayloadDetails(item)}
                                                            </TableCell>

                                                            {/* Requester */}
                                                            <TableCell>
                                                                <div className="flex items-center gap-2">
                                                                    <div className="w-8 h-8 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600 border border-gray-200 shadow-sm">
                                                                        {getInitials(item.requester?.name)}
                                                                    </div>
                                                                    <div>
                                                                        <p className="text-sm font-medium text-gray-700">{item.requester?.name}</p>
                                                                        <p className="text-[10px] text-gray-400">Sales Dept.</p>
                                                                    </div>
                                                                </div>
                                                            </TableCell>

                                                            {/* Status */}
                                                            <TableCell>
                                                                {item.status === 'pending' && item.current_step ? (
                                                                    <Badge variant="outline" className="bg-orange-50 text-orange-700 border-orange-200 gap-1.5 py-1">
                                                                        <Clock className="w-3 h-3 animate-pulse"/>
                                                                        Waiting: {item.current_step.approver_role}
                                                                    </Badge>
                                                                ) : (
                                                                    <Badge variant={item.status === 'approved' ? 'default' : 'destructive'} className={item.status === 'approved' ? 'bg-green-100 text-green-700 hover:bg-green-100 border-green-200' : ''}>
                                                                        {item.status.toUpperCase()}
                                                                    </Badge>
                                                                )}
                                                            </TableCell>

                                                            {/* Actions */}
                                                            <TableCell className="text-right pr-6">
                                                                {item.status === 'pending' && (
                                                                    <div className="flex justify-end gap-2">
                                                                        <Button
                                                                            size="sm"
                                                                            variant="ghost"
                                                                            className="text-red-600 hover:text-red-700 hover:bg-red-50"
                                                                            onClick={() => openActionDialog(item, 'reject')}
                                                                        >
                                                                            Reject
                                                                        </Button>
                                                                        <Button
                                                                            size="sm"
                                                                            className="bg-blue-600 hover:bg-blue-700 text-white shadow-sm"
                                                                            onClick={() => openActionDialog(item, 'approve')}
                                                                        >
                                                                            Review & Approve
                                                                        </Button>
                                                                    </div>
                                                                )}
                                                                {item.status !== 'pending' && (
                                                                    <Button size="sm" variant="ghost" className="text-gray-400" disabled>
                                                                        Archived
                                                                    </Button>
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                    );
                                                })
                                            )}
                                        </TableBody>
                                    </Table>
                                </div>
                            </TabsContent>
                        </Tabs>

                        {/* --- Pagination --- */}
                        {approvals.total > 0 && (
                            <div className="p-4 border-t bg-gray-50 flex items-center justify-between">
                                <div className="text-xs text-gray-500">
                                    Showing {approvals.from}-{approvals.to} of {approvals.total} requests
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
                        )}
                    </div>
                </Card>

                {/* --- Action Dialog (Smart Modal) --- */}
                <AlertDialog open={!!selectedRequest} onOpenChange={(open) => !open && setSelectedRequest(null)}>
                    <AlertDialogContent className="sm:max-w-[500px]">
                        <AlertDialogHeader>
                            <AlertDialogTitle className={`flex items-center gap-2 text-xl ${actionType === 'approve' ? 'text-green-700' : 'text-red-700'}`}>
                                {actionType === 'approve' ? <CheckCircle2 className="w-6 h-6"/> : <XCircle className="w-6 h-6"/>}
                                {actionType === 'approve' ? 'Approve Request' : 'Reject Request'}
                            </AlertDialogTitle>
                            <AlertDialogDescription className="text-gray-600 mt-2">
                                คุณกำลังจะทำการ <strong>{actionType === 'approve' ? 'อนุมัติ' : 'ปฏิเสธ'}</strong> รายการดังต่อไปนี้:

                                <div className="mt-4 p-4 bg-gray-50 rounded-lg border border-gray-100 space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Document:</span>
                                        <span className="font-bold text-gray-900">{selectedRequest?.document_number}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Type:</span>
                                        <span className="font-semibold text-blue-600">{selectedRequest?.workflow.name}</span>
                                    </div>

                                    {/* Smart Payload Display in Modal */}
                                    {selectedRequest && selectedRequest.payload_snapshot && (
                                        <div className="pt-2 mt-2 border-t border-gray-200">
                                            <p className="text-xs text-gray-400 mb-1">Request Details:</p>
                                            {Object.entries(selectedRequest.payload_snapshot).map(([key, value]) => (
                                                <div key={key} className="flex justify-between text-xs">
                                                    <span className="capitalize text-gray-600">{key.replace(/_/g, ' ')}:</span>
                                                    <span className="font-medium text-gray-800">{String(value)}</span>
                                                </div>
                                            ))}
                                        </div>
                                    )}
                                </div>
                            </AlertDialogDescription>
                        </AlertDialogHeader>

                        <div className="py-2">
                            <label className="text-sm font-medium text-gray-700 mb-1 block">
                                {actionType === 'approve' ? 'Note / Remark (Optional)' : 'Reason for Rejection (Required)'}
                            </label>
                            <Textarea
                                value={comment}
                                onChange={e => setComment(e.target.value)}
                                placeholder={actionType === 'approve' ? "บันทึกช่วยจำ..." : "ระบุสาเหตุที่ไม่อนุมัติ..."}
                                className={cn("min-h-[100px]", actionType === 'reject' ? 'border-red-200 focus:ring-red-500' : '')}
                            />
                        </div>

                        <AlertDialogFooter>
                            <AlertDialogCancel disabled={isProcessing}>Cancel</AlertDialogCancel>
                            <AlertDialogAction
                                onClick={confirmAction}
                                disabled={isProcessing || (actionType === 'reject' && !comment.trim())}
                                className={actionType === 'approve' ? 'bg-green-600 hover:bg-green-700' : 'bg-red-600 hover:bg-red-700'}
                            >
                                {isProcessing ? 'Processing...' : `Confirm ${actionType === 'approve' ? 'Approval' : 'Rejection'}`}
                            </AlertDialogAction>
                        </AlertDialogFooter>
                    </AlertDialogContent>
                </AlertDialog>

            </div>
        </AuthenticatedLayout>
    );
}
