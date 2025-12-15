import React, { useState } from 'react';
import { Head, useForm, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Textarea } from "@/Components/ui/textarea";
import { Trash2, Plus, ChevronRight, ChevronLeft, Check, ChevronsUpDown, Save, FileCheck, RefreshCw, Lock, ArrowRight, RotateCcw, XCircle, Truck, Image as ImageIcon, Printer, Package, PackageX, FileText, User as UserIcon } from "lucide-react";
import { cn } from "@/lib/utils";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/Components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip';

// Import Tabs & Timeline
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import OrderTimeline from '@/Components/Sales/OrderTimeline';

// Components
import SalesNavigationMenu from './Partials/SalesNavigationMenu';
import Chatter from '@/Components/Sales/Chatter';
import Breadcrumbs from '@/Components/Breadcrumbs';
import SmartButton from '@/Components/SmartButton';
import ProductCombobox from '@/Components/ProductCombobox';
import ImageViewer from '@/Components/ImageViewer';

// --- Types ---
interface Product { id: string; name: string; price: number; stock: number; image_url?: string; }
interface Customer { id: string; name: string; code: string; payment_terms?: string; default_salesperson_id?: number; }

interface OrderItemRow {
    id?: number;
    product_id: string;
    description: string;
    quantity: number;
    qty_shipped?: number;
    unit_price: number;
    total: number;
    original_id?: number | null;
    image_url?: string;
}

interface PaginationInfo { current_index: number; total: number; prev_id: string | null; next_id: string | null; }

interface Props {
    auth: any;
    customers: Customer[];
    availableProducts: Product[];
    paginationInfo?: PaginationInfo | null;
    salespersons?: any[];
    canAssignSalesperson?: boolean;
    currentUser?: any;
    // ✅ บังคับให้มี order เสมอสำหรับหน้า Edit
    order: {
        id: string;
        order_number: string;
        customer_id: string;
        salesperson_id?: string | number;
        picking_count?: number;
        items: any[];
        status: string;
        note: string;
        payment_terms: string;
        total_amount: number;
        is_fully_shipped?: boolean;
        has_shipped_items?: boolean;
        shipping_progress?: number;
        timeline?: any[];
    };
}

const PagerLink = ({ href, disabled, children }: { href: string, disabled: boolean, children: React.ReactNode }) => (
    <Link href={href} preserveScroll className={`flex items-center justify-center w-8 h-8 rounded-md border transition-colors ${disabled ? 'text-gray-300 border-gray-200 cursor-not-allowed pointer-events-none' : 'text-gray-600 border-gray-300 hover:bg-gray-50 hover:text-gray-900'}`}>{children}</Link>
);

export default function EditOrder({ auth, customers, availableProducts, order, paginationInfo, salespersons = [], canAssignSalesperson = false, currentUser }: Props) {

    // ✅ Initialize Items from existing order
    const initialItems: OrderItemRow[] = order.items.map((item: any) => {
        const productInfo = availableProducts.find(p => p.id === item.product_id);
        return {
            id: item.id,
            product_id: item.product_id,
            description: item.description || item.product_name, // Fallback if description is missing
            quantity: item.quantity,
            qty_shipped: item.qty_shipped ?? 0,
            unit_price: item.unit_price,
            total: item.total || (item.quantity * item.unit_price),
            image_url: productInfo?.image_url || item.image_url
        };
    });

    const { data, setData, put, post, processing, errors } = useForm({
        customer_id: order.customer_id,
        salesperson_id: order.salesperson_id?.toString() || '',
        // Note: ในหน้า Edit วันที่อาจจะมาจาก order.created_at หรือ field วันที่ใบเสนอราคาถ้ามี
        order_date: new Date().toISOString().split('T')[0],
        payment_terms: order.payment_terms || '',
        items: initialItems,
        note: order.note || '',
        action: 'save'
    });

    const [openCustomer, setOpenCustomer] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // Flags
    const isConfirmed = order.status === 'confirmed';
    const isCancelled = order.status === 'cancelled';
    const isReadOnly = ['cancelled', 'completed'].includes(order.status);
    const isFullyShipped = order.is_fully_shipped ?? false;
    const hasShippedItems = order.has_shipped_items ?? false;
    // Header read-only condition (Can customize based on business rules)
    const isHeaderReadOnly = isConfirmed || isReadOnly;

    // Calculations
    const calculateSubtotal = () => data.items.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
    const taxAmount = calculateSubtotal() * 0.07;
    const grandTotal = calculateSubtotal() + taxAmount;
    const originalTotal = order.total_amount || 0;
    const priceDifference = grandTotal - originalTotal;

    const handleCustomerSelect = (customer: Customer) => {
        let newData: any = { customer_id: customer.id };
        if (customer.payment_terms) {
            newData.payment_terms = customer.payment_terms;
        }
        if (canAssignSalesperson && customer.default_salesperson_id) {
            newData.salesperson_id = customer.default_salesperson_id.toString();
        }
        setData((prev) => ({ ...prev, ...newData }));
        setOpenCustomer(false);
    };

    const addItem = () => {
        setData('items', [...data.items, { product_id: '', description: '', quantity: 1, unit_price: 0, total: 0, image_url: undefined }]);
    };

    const removeItem = (index: number) => {
        const item = data.items[index];
        const newItems = [...data.items];
        if (item.qty_shipped && item.qty_shipped > 0) {
            alert('ไม่สามารถลบรายการที่จัดส่งไปแล้วได้');
            return;
        }
        if (isConfirmed && item.id) {
            if (confirm("ต้องการยกเลิกรายการสินค้านี้ใช่หรือไม่? (จำนวนจะถูกปรับเป็น 0)")) {
                newItems[index].quantity = 0;
                newItems[index].total = 0;
                setData('items', newItems);
            }
            return;
        }
        newItems.splice(index, 1);
        setData('items', newItems);
    };

    const restoreItem = (index: number) => {
        const newItems = [...data.items];
        newItems[index].quantity = 1;
        newItems[index].total = newItems[index].unit_price; // Recalculate total if needed
        setData('items', newItems);
    };

    const updateItem = (index: number, field: keyof OrderItemRow, value: any) => {
        const newItems = [...data.items];
        const row = newItems[index];

        if (isConfirmed && row.quantity === 0 && field !== 'quantity') return; // Cancelled item locked
        if (isConfirmed && row.id && field === 'product_id') return; // Cannot change product of existing line

        if (field === 'quantity' && value < (row.qty_shipped || 0)) {
            alert(`จำนวนสินค้าต้องไม่น้อยกว่าที่จัดส่งไปแล้ว (${row.qty_shipped})`);
            return;
        }

        if (field === 'product_id') {
            const product = availableProducts.find(p => p.id === value);
            if (product) {
                row.product_id = product.id;
                row.description = product.name;
                row.unit_price = product.price;
                row.image_url = product.image_url;
            }
        } else {
            (row as any)[field] = value;
        }
        row.total = row.quantity * row.unit_price;
        setData('items', newItems);
    };

    // ✅ Handle Update
    const handleSubmit = (actionType: 'save' | 'confirm') => {
        setIsSubmitting(true);
        const payload = { ...data, action: actionType };
        const options = {
            onSuccess: () => setIsSubmitting(false),
            onError: () => setIsSubmitting(false),
            preserveScroll: true
        };
        // Always PUT for Edit page
        put(route('sales.orders.update', order.id), payload, options);
    };

    const handleCancelOrder = () => {
        if (hasShippedItems) {
            alert("ไม่สามารถยกเลิกออเดอร์ที่มีการจัดส่งแล้วได้ กรุณาทำใบคืนสินค้า (Return Note) แทน");
            return;
        }
        if (confirm("ยืนยันการยกเลิกออเดอร์นี้?")) {
            post(route('sales.orders.cancel', order.id)); // Use post from inertia helper
        }
    };

    // Dashboard Helpers
    const getTotalOrdered = () => data.items.reduce((s, i) => s + i.quantity, 0);
    const getTotalShipped = () => data.items.reduce((s, i) => s + (i.qty_shipped || 0), 0);
    const getBackorder = () => data.items.reduce((s, i) => s + Math.max(0, i.quantity - (i.qty_shipped || 0)), 0);

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<SalesNavigationMenu />}>
            <Head title={`Edit Order ${order.order_number}`} />

            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-2">
                <Breadcrumbs links={[{ label: 'Sales Orders', href: route('sales.index') }]} activeLabel={order.order_number} />
            </div>

            <div className="max-w-7xl mx-auto bg-white border-b px-6 py-3 flex justify-between items-center sticky top-0 z-10 shadow-sm">
                <div className="flex items-center gap-4">
                    <div className="flex items-center">
                        <a href={route('sales.orders.pdf', order.id)} target="_blank" className="mr-1">
                            <Button variant="outline" className="gap-2">
                                <Printer className="w-4 h-4" /> PDF
                            </Button>
                        </a>
                        <Button variant="outline" className='mr-1' disabled>Send by Email</Button>

                        {!isCancelled && (
                            <>
                                {!isReadOnly && (
                                    <>
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    <div>
                                                        <Button
                                                            variant="outline"
                                                            size="icon"
                                                            onClick={() => handleSubmit('save')}
                                                            disabled={isSubmitting || hasShippedItems}
                                                            className="h-9 w-9"
                                                        >
                                                            {isConfirmed ? <RefreshCw className="h-4 w-4 text-blue-600 ml-1" /> : <Save className="h-4 w-4 text-gray-600" />}
                                                        </Button>
                                                    </div>
                                                </TooltipTrigger>
                                                <TooltipContent>
                                                    <p>{hasShippedItems ? "Cannot update: Items already shipped" : (isConfirmed ? "Update Changes" : "Save Changes")}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>

                                        {!isConfirmed &&
                                            <TooltipProvider>
                                                <Tooltip>
                                                    <TooltipTrigger asChild>
                                                        <Button
                                                            size={"icon"}
                                                            className="bg-purple-700 hover:bg-purple-800 text-white ml-1"
                                                            onClick={() => handleSubmit('confirm')}
                                                            disabled={isSubmitting}
                                                        >
                                                            <FileCheck className="h-4 w-4" />
                                                        </Button>
                                                    </TooltipTrigger>
                                                    <TooltipContent>
                                                        <p>Confirm Order</p>
                                                    </TooltipContent>
                                                </Tooltip>
                                            </TooltipProvider>
                                        }
                                    </>
                                )}
                            </>
                        )}
                        {!isCancelled && !hasShippedItems && !isReadOnly && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        <div>
                                            <Button
                                                variant="outline"
                                                size={"icon"}
                                                className="ml-1 text-red-600 border-red-200 hover:bg-red-50 hover:text-red-700"
                                                onClick={handleCancelOrder}
                                            >
                                                <XCircle className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>Cancel Order</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-4">
                    {order.picking_count !== undefined && order.picking_count > 0 && <SmartButton label="Delivery" value={order.picking_count} icon={Truck} href={route('logistics.picking.index', { search: order.order_number })} className="h-12 px-3 text-sm border-gray-200 shadow-none hover:bg-gray-50" />}
                </div>
                <div className="flex">
                    <div className="flex items-center text-sm font-medium text-gray-500">
                        {isCancelled ? (
                            <span className="px-3 py-1 rounded-md bg-red-100 text-red-700 border border-red-200 font-bold flex items-center gap-2"><XCircle className="h-4 w-4" /> CANCELLED</span>
                        ) : (
                            <>
                                <span className={cn("px-2 py-1 rounded-md border transition-colors", (order.status === 'draft') ? "text-purple-700 font-bold bg-purple-50 border-purple-200" : "text-gray-500 border-transparent")}>Quotation</span>
                                <ChevronRight className="h-4 w-4 mx-1" />
                                <span className={cn("px-2 py-1 rounded-md border transition-colors", (order.status === 'confirmed') ? "text-green-700 font-bold bg-green-50 border-green-200" : "text-gray-500 border-transparent")}>Sales Order</span>
                            </>
                        )}
                    </div>
                    {paginationInfo && <div className="flex items-center gap-2 ml-2 border-l pl-4 h-8"><span className="text-sm font-medium text-gray-500 mr-2">{paginationInfo.current_index} / {paginationInfo.total}</span><PagerLink href={paginationInfo.prev_id ? route('sales.orders.edit', paginationInfo.prev_id) : '#'} disabled={!paginationInfo.prev_id}><ChevronLeft className="w-4 h-4" /></PagerLink><PagerLink href={paginationInfo.next_id ? route('sales.orders.edit', paginationInfo.next_id) : '#'} disabled={!paginationInfo.next_id}><ChevronRight className="w-4 h-4" /></PagerLink></div>}
                </div>
            </div>

            <div className="max-w-7xl mx-auto py-4 px-4 sm:px-6 lg:px-8">
                <Card className="border-0 shadow-none bg-transparent">
                    <CardContent className="p-0 space-y-6">

                        <Tabs defaultValue="details" className="w-full">
                            <TabsList className="grid w-full grid-cols-2 lg:w-[400px] mb-4">
                                <TabsTrigger value="details">Order Details</TabsTrigger>
                                <TabsTrigger value="tracking">History & Tracking</TabsTrigger>
                            </TabsList>

                            {/* --- TAB 1: DETAILS --- */}
                            <TabsContent value="details">
                                <div className="bg-white p-6 rounded-lg border shadow-sm grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6 relative">
                                    {isHeaderReadOnly && <div className="absolute inset-0 bg-gray-50/30 pointer-events-none z-10" />}

                                    {/* Left Column */}
                                    <div className="space-y-4">
                                        {/* Customer Select */}
                                        <div className="grid grid-cols-3 items-center gap-4">
                                            <Label className="text-right font-medium">Customer</Label>
                                            <div className="col-span-2 relative">
                                                <Popover open={!isHeaderReadOnly && openCustomer} onOpenChange={setOpenCustomer}>
                                                    <PopoverTrigger asChild>
                                                        <Button variant="outline" role="combobox" disabled={isHeaderReadOnly} className={cn("w-full justify-between pl-3 text-left font-normal", !data.customer_id && "text-muted-foreground")}>
                                                            {data.customer_id ? customers.find((c) => c.id === data.customer_id)?.name : "Search for a customer..."}
                                                            {!isHeaderReadOnly && <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />}
                                                        </Button>
                                                    </PopoverTrigger>
                                                    <PopoverContent className="w-[400px] p-0" align="start">
                                                        <Command>
                                                            <CommandInput placeholder="Search customer..." />
                                                            <CommandList>
                                                                <CommandEmpty>No customer found.</CommandEmpty>
                                                                <CommandGroup>
                                                                    {customers.map((customer) => (
                                                                        <CommandItem key={customer.id} value={customer.name} onSelect={() => handleCustomerSelect(customer)}>
                                                                            <Check className={cn("mr-2 h-4 w-4", data.customer_id === customer.id ? "opacity-100" : "opacity-0")} />
                                                                            {customer.name}
                                                                        </CommandItem>
                                                                    ))}
                                                                </CommandGroup>
                                                            </CommandList>
                                                        </Command>
                                                    </PopoverContent>
                                                </Popover>
                                                {isHeaderReadOnly && <Lock className="w-4 h-4 text-gray-400 absolute right-3 top-3" />}
                                            </div>
                                        </div>

                                        {/* Salesperson Select */}
                                        <div className="grid grid-cols-3 items-center gap-4">
                                            <Label className="text-right font-medium flex items-center justify-end gap-2">
                                                <UserIcon className="w-4 h-4 text-gray-500" /> Salesperson
                                            </Label>
                                            <div className="col-span-2 relative">
                                                <Select
                                                    disabled={isHeaderReadOnly || !canAssignSalesperson}
                                                    value={data.salesperson_id}
                                                    onValueChange={(val) => setData('salesperson_id', val)}
                                                >
                                                    <SelectTrigger className={!canAssignSalesperson ? "bg-gray-50" : ""}>
                                                        <SelectValue placeholder="Select Salesperson" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {currentUser && (
                                                            <SelectItem value={currentUser.id.toString()}>
                                                                {currentUser.name} {currentUser.id.toString() === data.salesperson_id ? '(Me)' : ''}
                                                            </SelectItem>
                                                        )}
                                                        {salespersons.map((sp: any) => (
                                                            sp.id !== currentUser?.id && (
                                                                <SelectItem key={sp.id} value={sp.id.toString()}>
                                                                    {sp.name}
                                                                </SelectItem>
                                                            )
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                                    </div>

                                    {/* Right Column */}
                                    <div className="space-y-4">
                                        <div className="grid grid-cols-3 items-center gap-4"><Label className="text-right">Quotation Date</Label><div className="col-span-2 relative"><Input type="date" disabled={isHeaderReadOnly} value={data.order_date} onChange={e => setData('order_date', e.target.value)} />{isHeaderReadOnly && <Lock className="w-4 h-4 text-gray-400 absolute right-3 top-3" />}</div></div>
                                        <div className="grid grid-cols-3 items-center gap-4"><Label className="text-right">Payment Terms</Label><div className="col-span-2 relative"><Select disabled={isHeaderReadOnly} value={data.payment_terms} onValueChange={val => setData('payment_terms', val)}><SelectTrigger><SelectValue placeholder="Select..." /></SelectTrigger><SelectContent><SelectItem value="immediate">Immediate</SelectItem><SelectItem value="15_days">15 Days</SelectItem><SelectItem value="30_days">30 Days</SelectItem></SelectContent></Select>{isHeaderReadOnly && <Lock className="w-4 h-4 text-gray-400 absolute right-8 top-3" />}</div></div>
                                    </div>
                                </div>

                                <div className="bg-white rounded-lg border shadow-sm overflow-hidden min-h-[400px] flex flex-col mt-6">
                                    <div className="flex border-b bg-gray-50"><button className="px-6 py-3 text-sm font-medium border-b-2 border-purple-700 text-purple-700 bg-white">Order Lines</button></div>
                                    <div className="p-0 flex-1">
                                        <Table>
                                            <TableHeader>
                                                <TableRow className="bg-gray-50/50">
                                                    <TableHead className="w-[60px] text-center">Image</TableHead>
                                                    <TableHead className="w-[35%]">Product</TableHead>
                                                    <TableHead className="w-[25%]">Description</TableHead>
                                                    <TableHead className="w-[10%] text-right">Quantity</TableHead>
                                                    <TableHead className="w-[10%] text-right text-blue-600 bg-blue-50/30">Delivered</TableHead>
                                                    <TableHead className="w-[10%] text-right">Unit Price</TableHead>
                                                    <TableHead className="w-[10%] text-right">Subtotal</TableHead>
                                                    <TableHead className="w-[5%]"></TableHead>
                                                </TableRow>
                                            </TableHeader>
                                            <TableBody>
                                                {data.items.map((item, index) => {
                                                    const isCancelledItem = isConfirmed && item.id && item.quantity === 0;
                                                    const isItemShipped = (item.qty_shipped || 0) > 0;
                                                    const productInfo = availableProducts.find(p => p.id === item.product_id);
                                                    const isOutOfStock = productInfo ? (item.quantity > productInfo.stock) : false;
                                                    const stockShortage = isOutOfStock ? item.quantity - (productInfo?.stock || 0) : 0;

                                                    return (
                                                        <TableRow key={index} className={cn("group align-top", isCancelledItem && "bg-gray-50 opacity-60")}>
                                                            <TableCell className="p-2 text-center align-top">
                                                                {item.image_url ? (
                                                                    <ImageViewer images={[item.image_url]} alt="Product" className="w-10 h-10 rounded border bg-white object-contain" />
                                                                ) : (
                                                                    <div className="w-10 h-10 bg-gray-100 rounded flex items-center justify-center border text-gray-300"><ImageIcon className="w-5 h-5" /></div>
                                                                )}
                                                            </TableCell>
                                                            <TableCell className="p-2 relative w-[350px]">
                                                                <ProductCombobox
                                                                    products={availableProducts}
                                                                    value={item.product_id}
                                                                    onChange={(val) => updateItem(index, 'product_id', val)}
                                                                    disabled={(isConfirmed && !!item.id) || isItemShipped || isReadOnly}
                                                                    placeholder="Select Product"
                                                                />
                                                                {((isConfirmed && item.id) || isItemShipped || isReadOnly) && <Lock className="w-3 h-3 text-gray-400 absolute right-3 top-6" />}

                                                                {productInfo && (
                                                                    <div className="mt-2 space-y-1">
                                                                        <div className="flex justify-between text-xs">
                                                                            <span className="text-gray-500">Stock Available:</span>
                                                                            <span className={cn("font-medium", productInfo.stock < item.quantity ? "text-red-600" : "text-green-600")}>
                                                                                {productInfo.stock} units
                                                                            </span>
                                                                        </div>
                                                                        {isOutOfStock && !isItemShipped && !isCancelledItem && (
                                                                            <div className="flex items-start gap-2 p-2 bg-amber-50 text-amber-800 rounded-md border border-amber-200 text-xs">
                                                                                <PackageX className="w-4 h-4 mt-0.5 shrink-0 text-amber-600" />
                                                                                <div>
                                                                                    <p className="font-semibold">Insufficient Stock!</p>
                                                                                    <p>Missing {stockShortage} units. Backorder.</p>
                                                                                </div>
                                                                            </div>
                                                                        )}
                                                                    </div>
                                                                )}
                                                            </TableCell>
                                                            <TableCell className="p-2"><Input disabled={isCancelledItem || isItemShipped || isReadOnly} value={item.description} onChange={(e) => updateItem(index, 'description', e.target.value)} className={cn("border-0 shadow-none h-9", isCancelledItem && "line-through")} /></TableCell>
                                                            <TableCell className="p-2"><Input type="number" min="0" value={item.quantity} onChange={(e) => updateItem(index, 'quantity', parseFloat(e.target.value))} className={cn("border-0 shadow-none h-9 text-right", !isCancelledItem && "bg-yellow-50/50")} disabled={isItemShipped || isReadOnly} /></TableCell>

                                                            {/* Shipped Qty Column */}
                                                            <TableCell className="p-2 text-right align-middle bg-blue-50/10">
                                                                <span className={cn("font-bold text-sm", (item.qty_shipped || 0) >= item.quantity ? "text-green-600" : (item.qty_shipped || 0) > 0 ? "text-orange-500" : "text-gray-300")}>
                                                                    {item.qty_shipped || 0}
                                                                </span>
                                                            </TableCell>

                                                            <TableCell className="p-2"><Input disabled={isCancelledItem || isItemShipped || isReadOnly} type="number" value={item.unit_price} onChange={(e) => updateItem(index, 'unit_price', parseFloat(e.target.value))} className={cn("border-0 shadow-none h-9 text-right", !isCancelledItem && "bg-yellow-50/50")} /></TableCell>
                                                            <TableCell className="text-right font-medium p-2 align-middle"><span className={isCancelledItem ? "line-through text-gray-400" : "text-gray-700"}>{item.total.toLocaleString()} ฿</span>{isCancelledItem && <span className="ml-2 text-xs text-red-500 font-bold">(CANCELLED)</span>}</TableCell>
                                                            <TableCell className="p-2 text-center">
                                                                {isCancelledItem ? (
                                                                    <TooltipProvider><Tooltip><TooltipTrigger asChild><Button variant="ghost" size="icon" className="h-8 w-8 text-green-600 hover:bg-green-50" onClick={() => restoreItem(index)} disabled={isReadOnly}><RotateCcw className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent><p>Restore</p></TooltipContent></Tooltip></TooltipProvider>
                                                                ) : (
                                                                    !isItemShipped && (
                                                                        (!isConfirmed || !item.id) ? (
                                                                            <Button variant="ghost" size="icon" className="h-8 w-8 text-gray-400 hover:text-red-500" onClick={() => removeItem(index)} disabled={isReadOnly}><Trash2 className="h-4 w-4" /></Button>
                                                                        ) : (
                                                                            <Button variant="ghost" size="icon" className="h-8 w-8 text-gray-400 hover:text-red-500" onClick={() => removeItem(index)} disabled={isReadOnly}><Trash2 className="h-4 w-4" /></Button>
                                                                        )
                                                                    )
                                                                )}
                                                            </TableCell>
                                                        </TableRow>
                                                    );
                                                })}
                                                <TableRow>
                                                    <TableCell colSpan={9} className="p-2">
                                                        {!hasShippedItems && !isFullyShipped && !isReadOnly && (
                                                            <Button variant="ghost" className="text-purple-700 hover:bg-purple-50 w-full justify-start" onClick={addItem}>
                                                                <Plus className="h-4 w-4 mr-2" /> Add a product
                                                            </Button>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            </TableBody>
                                        </Table>
                                    </div>
                                    <div className="p-6 grid grid-cols-1 md:grid-cols-2 gap-8 border-t">
                                        <Textarea disabled={isHeaderReadOnly} placeholder="Terms..." className="resize-none h-32 bg-gray-50" value={data.note} onChange={e => setData('note', e.target.value)} />
                                        <div className="flex flex-col items-end space-y-3 text-sm">
                                            <div className="flex justify-between w-2/3"><span>Total:</span><span className="font-bold text-lg">{grandTotal.toLocaleString()} ฿</span></div>
                                            {isConfirmed && Math.abs(priceDifference) > 0 && <div className="flex items-center gap-2 text-xs bg-gray-100 px-3 py-1 rounded-full border"><span className="text-gray-500">Original: {originalTotal.toLocaleString()}</span><ArrowRight className="w-3 h-3 text-gray-400" /><span className={priceDifference > 0 ? "text-red-600 font-bold" : "text-green-600 font-bold"}>{priceDifference > 0 ? "+" : ""}{priceDifference.toLocaleString()}</span></div>}
                                        </div>
                                    </div>
                                </div>
                            </TabsContent>

                            {/* --- TAB 2: TRACKING & HISTORY --- */}
                            <TabsContent value="tracking">
                                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6 h-full">
                                    {/* Timeline Component */}
                                    <div className="lg:col-span-1">
                                        <OrderTimeline events={order.timeline || []} />
                                    </div>

                                    {/* Summary Stats */}
                                    <div className="lg:col-span-2 space-y-6">
                                        <Card>
                                            <CardHeader><CardTitle className="flex items-center gap-2"><Truck className="w-5 h-5 text-indigo-600" /> Fulfillment Status</CardTitle></CardHeader>
                                            <CardContent>
                                                <div className="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                                                    <div className="p-4 bg-gray-50 rounded-lg border">
                                                        <div className="text-xs text-gray-500 uppercase font-bold mb-1">Total Ordered</div>
                                                        <div className="text-3xl font-bold text-gray-800">{getTotalOrdered()}</div>
                                                    </div>
                                                    <div className="p-4 bg-blue-50 rounded-lg border border-blue-100">
                                                        <div className="text-xs text-blue-600 uppercase font-bold mb-1">Shipped</div>
                                                        <div className="text-3xl font-bold text-blue-700">
                                                            {getTotalShipped()}
                                                        </div>
                                                    </div>
                                                    <div className="p-4 bg-red-50 rounded-lg border border-red-100">
                                                        <div className="text-xs text-red-600 uppercase font-bold mb-1">Backorder</div>
                                                        <div className="text-3xl font-bold text-red-700">
                                                            {getBackorder()}
                                                        </div>
                                                    </div>
                                                    <div className="p-4 bg-green-50 rounded-lg border border-green-100">
                                                        <div className="text-xs text-green-600 uppercase font-bold mb-1">Progress</div>
                                                        <div className="text-3xl font-bold text-green-700">
                                                            {order.shipping_progress}%
                                                        </div>
                                                    </div>
                                                </div>
                                            </CardContent>
                                        </Card>

                                        {/* Related Documents */}
                                        <Card>
                                            <CardHeader><CardTitle className="flex items-center gap-2"><FileText className="w-5 h-5 text-gray-500" /> Related Documents</CardTitle></CardHeader>
                                            <CardContent>
                                                <div className="space-y-4">
                                                    {order.picking_count ? (
                                                        <div className="flex items-center justify-between p-3 bg-white border rounded-lg hover:shadow-sm transition-shadow">
                                                            <div className="flex items-center gap-3">
                                                                <div className="p-2 bg-indigo-50 rounded-full text-indigo-600"><Package className="w-5 h-5" /></div>
                                                                <div>
                                                                    <p className="font-bold text-sm">Picking Slips</p>
                                                                    <p className="text-xs text-gray-500">{order.picking_count} document(s) generated</p>
                                                                </div>
                                                            </div>
                                                            <Link href={route('logistics.picking.index', { search: order.order_number })}>
                                                                <Button variant="outline" size="sm">View</Button>
                                                            </Link>
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-gray-400 italic text-center py-4">No logistics documents generated yet.</p>
                                                    )}
                                                </div>
                                            </CardContent>
                                        </Card>
                                    </div>
                                </div>
                            </TabsContent>
                        </Tabs>

                    </CardContent>
                </Card>
            </div>
            <div className="max-w-7xl mx-auto"><div className="bg-white rounded-lg border shadow-sm p-6"><h3 className="text-lg font-medium mb-4 border-b pb-2">Communication History</h3><Chatter modelType="sales_order" modelId={order.id} /></div></div>
        </AuthenticatedLayout>
    );
}
