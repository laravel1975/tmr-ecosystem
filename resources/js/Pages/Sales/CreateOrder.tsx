import React, { useState } from 'react';
import { Head, useForm, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Card, CardContent } from "@/Components/ui/card";
import { Textarea } from "@/Components/ui/textarea";
import { Trash2, Plus, ChevronRight, ChevronLeft, Check, ChevronsUpDown, Save, FileCheck, RefreshCw, Lock, ArrowRight, RotateCcw, XCircle, Truck, Image as ImageIcon, AlertCircle, Printer } from "lucide-react";
import { cn } from "@/lib/utils";
import { Command, CommandEmpty, CommandGroup, CommandInput, CommandItem, CommandList } from "@/Components/ui/command";
import { Popover, PopoverContent, PopoverTrigger } from "@/Components/ui/popover";
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip';

// Components
import SalesNavigationMenu from './Partials/SalesNavigationMenu';
import Chatter from '@/Components/Sales/Chatter';
import Breadcrumbs from '@/Components/Breadcrumbs';
import SmartButton from '@/Components/SmartButton';
import ProductCombobox from '@/Components/ProductCombobox';
import ImageViewer from '@/Components/ImageViewer';

// --- Types ---
interface Product { id: string; name: string; price: number; stock: number; image_url?: string; }
interface Customer { id: string; name: string; payment_terms?: string; }

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
    order?: {
        id: string;
        order_number: string;
        customer_id: string;
        picking_count?: number;
        items: any[];
        status: string;
        note: string;
        payment_terms: string;
        total_amount: number;
        // ✅ New Props from Backend
        is_fully_shipped?: boolean;
        has_shipped_items?: boolean;
        shipping_progress?: number;
    } | null;
}

const PagerLink = ({ href, disabled, children }: { href: string, disabled: boolean, children: React.ReactNode }) => (
    <Link href={href} preserveScroll className={`flex items-center justify-center w-8 h-8 rounded-md border transition-colors ${disabled ? 'text-gray-300 border-gray-200 cursor-not-allowed pointer-events-none' : 'text-gray-600 border-gray-300 hover:bg-gray-50 hover:text-gray-900'}`}>{children}</Link>
);

export default function CreateOrder({ auth, customers, availableProducts, order, paginationInfo }: Props) {

    const initialItems: OrderItemRow[] = order?.items.map((item: any) => {
        const productInfo = availableProducts.find(p => p.id === item.product_id);

        return {
            id: item.id,
            product_id: item.product_id,
            description: item.description,
            quantity: item.quantity,
            qty_shipped: item.qty_shipped ?? 0,
            unit_price: item.unit_price,
            total: item.total || (item.quantity * item.unit_price),
            image_url: productInfo?.image_url || item.image_url
        };
    }) || [];

    const { data, setData, post, put, processing, errors } = useForm({
        customer_id: order?.customer_id || '',
        order_date: new Date().toISOString().split('T')[0],
        expiration_date: '',
        payment_terms: order?.payment_terms || '',
        items: initialItems,
        note: order?.note || '',
        action: 'save'
    });

    const [openCustomer, setOpenCustomer] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // ✅ Logic Flags
    const isConfirmed = order?.status === 'confirmed';
    const isCancelled = order?.status === 'cancelled';
    const isReadOnly = order ? ['cancelled', 'completed'].includes(order.status) : false;

    // ✅ New Shipping Flags
    const isFullyShipped = order?.is_fully_shipped ?? false;
    const hasShippedItems = order?.has_shipped_items ?? false;

    const isHeaderReadOnly = isConfirmed || isCancelled;

    const calculateSubtotal = () => data.items.reduce((sum, item) => sum + (item.quantity * item.unit_price), 0);
    const taxAmount = calculateSubtotal() * 0.07;
    const grandTotal = calculateSubtotal() + taxAmount;
    const originalTotal = order?.total_amount || 0;
    const priceDifference = grandTotal - originalTotal;

    const addItem = () => {
        setData('items', [...data.items, { product_id: '', description: '', quantity: 1, unit_price: 0, total: 0, image_url: undefined }]);
    };

    const removeItem = (index: number) => {
        const item = data.items[index];
        const newItems = [...data.items];

        // ถ้ามีการส่งของไปแล้ว ห้ามลบรายการที่มีการส่งแล้ว
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
        newItems[index].total = newItems[index].unit_price;
        setData('items', newItems);
    };

    const updateItem = (index: number, field: keyof OrderItemRow, value: any) => {
        const newItems = [...data.items];
        const row = newItems[index];

        if (isConfirmed && row.quantity === 0 && field !== 'quantity') return;
        if (isConfirmed && row.id && field === 'product_id') return;

        // ❌ ห้ามแก้ยอดให้น้อยกว่าที่ส่งไปแล้ว
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

    const handleSubmit = (actionType: 'save' | 'confirm') => {
        setIsSubmitting(true);
        const payload = { ...data, action: actionType };
        const options = {
            onSuccess: () => setIsSubmitting(false),
            onError: () => setIsSubmitting(false),
            preserveScroll: true
        };
        if (!order) {
            router.post(route('sales.orders.store'), payload, options);
        } else {
            router.put(route('sales.orders.update', order.id), payload, options);
        }
    };

    const handleDelete = () => { if (confirm('Are you sure?')) router.delete(route('sales.orders.destroy', order!.id)); };

    const handleCancelOrder = () => {
        if (hasShippedItems) {
            alert("ไม่สามารถยกเลิกออเดอร์ที่มีการจัดส่งแล้วได้ กรุณาทำใบคืนสินค้า (Return Note) แทน");
            return;
        }
        if (confirm("ยืนยันการยกเลิกออเดอร์นี้?")) router.post(route('sales.orders.cancel', order!.id));
    };

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<SalesNavigationMenu />}>
            <Head title={order ? `${order.order_number}` : "New Quotation"} />
            <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-4 pb-2">
                <Breadcrumbs links={[{ label: 'Sales Orders', href: route('sales.index') }]} activeLabel={order ? order.order_number : 'New Quotation'} />
            </div>

            {/* --- Top Action Bar --- */}
            <div className="max-w-7xl mx-auto bg-white border-b px-6 py-3 flex justify-between items-center sticky top-0 z-10 shadow-sm">
                <div className="flex items-center gap-4">
                    <div className="flex items-center">
                        {/* ✅ ปุ่ม Download PDF */}
                        {/* ✅ FIX: เพิ่มเงื่อนไข {order && (...)} ครอบปุ่ม PDF ไว้ */}
                        {/* แสดงเฉพาะเมื่อมี Order แล้วเท่านั้น */}
                        {order && (
                            <a href={route('sales.orders.pdf', order.id)} target="_blank" className="mr-1">
                                <Button variant="outline" className="gap-2">
                                    <Printer className="w-4 h-4" /> PDF
                                </Button>
                            </a>
                        )}
                        <Button variant="outline" className='mr-1' disabled>Send by Email</Button>
                        {!isCancelled && (
                            <>
                                {!isReadOnly && (
                                    <>
                                        <TooltipProvider>
                                            <Tooltip>
                                                <TooltipTrigger asChild>
                                                    {/* ✅ Disable ปุ่ม Update ถ้ามีของส่งออกไปแล้ว */}
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
                                                    <p>{hasShippedItems ? "Cannot update: Items already shipped" : (isConfirmed ? "Update Changes" : "Save Draft")}</p>
                                                </TooltipContent>
                                            </Tooltip>
                                        </TooltipProvider>

                                        {!isConfirmed && <TooltipProvider><Tooltip><TooltipTrigger asChild><Button size={"icon"} className="bg-purple-700 hover:bg-purple-800 text-white ml-1" onClick={() => handleSubmit('confirm')} disabled={isSubmitting}><FileCheck className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent><p>Confirm Order</p></TooltipContent></Tooltip></TooltipProvider>}
                                    </>
                                )}
                            </>
                        )}
                        {order && !isCancelled && (
                            <TooltipProvider>
                                <Tooltip>
                                    <TooltipTrigger asChild>
                                        {/* ✅ Disable ปุ่ม Cancel ถ้ามีของส่งออกไปแล้ว */}
                                        <div>
                                            <Button
                                                variant="outline"
                                                size={"icon"}
                                                className={cn("ml-1", hasShippedItems ? "text-gray-300 border-gray-200" : "text-red-600 border-red-200 hover:bg-red-50 hover:text-red-700")}
                                                onClick={handleCancelOrder}
                                                disabled={hasShippedItems}
                                            >
                                                <XCircle className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </TooltipTrigger>
                                    <TooltipContent>
                                        <p>{hasShippedItems ? "Cannot cancel: Items already shipped" : "Cancel Order"}</p>
                                    </TooltipContent>
                                </Tooltip>
                            </TooltipProvider>
                        )}
                    </div>
                </div>
                <div className="flex items-center gap-4">
                    {order && order.picking_count !== undefined && order.picking_count > 0 && <SmartButton label="Delivery" value={order.picking_count} icon={Truck} href={route('logistics.picking.index', { search: order.order_number })} className="h-12 px-3 text-sm border-gray-200 shadow-none hover:bg-gray-50" />}
                </div>
                <div className="flex">
                    <div className="flex items-center text-sm font-medium text-gray-500">
                        {isCancelled ? (
                            <span className="px-3 py-1 rounded-md bg-red-100 text-red-700 border border-red-200 font-bold flex items-center gap-2"><XCircle className="h-4 w-4" /> CANCELLED</span>
                        ) : (
                            <>
                                <span className={cn("px-2 py-1 rounded-md border transition-colors", (!order || order.status === 'draft') ? "text-purple-700 font-bold bg-purple-50 border-purple-200" : "text-gray-500 border-transparent")}>Quotation</span>
                                <ChevronRight className="h-4 w-4 mx-1" />
                                <span className={cn("px-2 py-1 rounded-md border transition-colors", (order?.status === 'confirmed') ? "text-green-700 font-bold bg-green-50 border-green-200" : "text-gray-500 border-transparent")}>Sales Order</span>
                            </>
                        )}
                    </div>
                    {paginationInfo && <div className="flex items-center gap-2 ml-2 border-l pl-4 h-8"><span className="text-sm font-medium text-gray-500 mr-2">{paginationInfo.current_index} / {paginationInfo.total}</span><PagerLink href={paginationInfo.prev_id ? route('sales.orders.show', paginationInfo.prev_id) : '#'} disabled={!paginationInfo.prev_id}><ChevronLeft className="w-4 h-4" /></PagerLink><PagerLink href={paginationInfo.next_id ? route('sales.orders.show', paginationInfo.next_id) : '#'} disabled={!paginationInfo.next_id}><ChevronRight className="w-4 h-4" /></PagerLink></div>}
                </div>
            </div>

            <div className="max-w-7xl mx-auto py-4">
                <Card className="border-0 shadow-none bg-transparent">
                    <CardContent className="p-0 space-y-6">
                        <div className="flex justify-between items-start">
                            <div className="flex flex-col-reverse items-start">
                                <h1 className="text-3xl font-medium text-gray-800">{order ? order.order_number : "New"}</h1>
                                <div className="flex gap-2">
                                    {isConfirmed && <span className="px-2 py-0 bg-blue-700 text-white text-xs font-bold rounded">LOCKED MODE</span>}
                                    {isFullyShipped && <span className="px-2 py-0 bg-green-600 text-white text-xs font-bold rounded">FULLY SHIPPED</span>}
                                    {hasShippedItems && !isFullyShipped && <span className="px-2 py-0 bg-orange-500 text-white text-xs font-bold rounded">PARTIALLY SHIPPED</span>}
                                </div>
                            </div>
                        </div>

                        {/* Customer & Dates */}
                        <div className="bg-white p-6 rounded-lg border shadow-sm grid grid-cols-1 md:grid-cols-2 gap-x-12 gap-y-6 relative">
                            {isHeaderReadOnly && <div className="absolute inset-0 bg-gray-50/30 pointer-events-none z-10" />}
                            <div className="space-y-4">
                                <div className="grid grid-cols-3 items-center gap-4">
                                    <Label className="text-right font-medium">Customer</Label>
                                    <div className="col-span-2 relative">
                                        <Popover open={!isHeaderReadOnly && openCustomer} onOpenChange={setOpenCustomer}>
                                            <PopoverTrigger asChild><Button variant="outline" role="combobox" disabled={isHeaderReadOnly} className={cn("w-full justify-between pl-3 text-left font-normal", !data.customer_id && "text-muted-foreground")}>{data.customer_id ? customers.find((c) => c.id === data.customer_id)?.name : "Search for a customer..."}{!isHeaderReadOnly && <ChevronsUpDown className="ml-2 h-4 w-4 shrink-0 opacity-50" />}</Button></PopoverTrigger>
                                            <PopoverContent className="w-[400px] p-0" align="start"><Command><CommandInput placeholder="Search customer..." /><CommandList><CommandEmpty>No customer found.</CommandEmpty><CommandGroup>{customers.map((customer) => (<CommandItem key={customer.id} value={customer.name} onSelect={() => { setData('customer_id', customer.id); setOpenCustomer(false); }}><Check className={cn("mr-2 h-4 w-4", data.customer_id === customer.id ? "opacity-100" : "opacity-0")} />{customer.name}</CommandItem>))}</CommandGroup></CommandList></Command></PopoverContent>
                                        </Popover>
                                        {isHeaderReadOnly && <Lock className="w-4 h-4 text-gray-400 absolute right-3 top-3" />}
                                    </div>
                                </div>
                            </div>
                            <div className="space-y-4">
                                <div className="grid grid-cols-3 items-center gap-4"><Label className="text-right">Quotation Date</Label><div className="col-span-2 relative"><Input type="date" disabled={isHeaderReadOnly} value={data.order_date} onChange={e => setData('order_date', e.target.value)} />{isHeaderReadOnly && <Lock className="w-4 h-4 text-gray-400 absolute right-3 top-3" />}</div></div>
                                <div className="grid grid-cols-3 items-center gap-4"><Label className="text-right">Payment Terms</Label><div className="col-span-2 relative"><Select disabled={isHeaderReadOnly} value={data.payment_terms} onValueChange={val => setData('payment_terms', val)}><SelectTrigger><SelectValue placeholder="Select..." /></SelectTrigger><SelectContent><SelectItem value="immediate">Immediate</SelectItem><SelectItem value="15_days">15 Days</SelectItem><SelectItem value="30_days">30 Days</SelectItem></SelectContent></Select>{isHeaderReadOnly && <Lock className="w-4 h-4 text-gray-400 absolute right-8 top-3" />}</div></div>
                            </div>
                        </div>

                        {/* Order Lines */}
                        <div className="bg-white rounded-lg border shadow-sm overflow-hidden min-h-[400px] flex flex-col">
                            <div className="flex border-b bg-gray-50"><button className="px-6 py-3 text-sm font-medium border-b-2 border-purple-700 text-purple-700 bg-white">Order Lines</button></div>
                            <div className="p-0 flex-1">
                                <Table>
                                    <TableHeader>
                                        <TableRow className="bg-gray-50/50">
                                            <TableHead className="w-[60px] text-center">Image</TableHead>
                                            <TableHead className="w-[35%]">Product</TableHead>
                                            <TableHead className="w-[25%]">Description</TableHead>
                                            <TableHead className="w-[10%] text-right">Quantity</TableHead>
                                            <TableHead className="w-[10%] text-right text-blue-600">Delivered</TableHead>
                                            <TableHead className="w-[10%] text-right">Unit Price</TableHead>
                                            <TableHead className="w-[10%] text-right">Subtotal</TableHead>
                                            <TableHead className="w-[5%]"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.items.map((item, index) => {
                                            const isCancelled = isConfirmed && item.id && item.quantity === 0;
                                            const isItemShipped = (item.qty_shipped || 0) > 0;

                                            return (
                                                <TableRow key={index} className={cn("group align-top", isCancelled && "bg-gray-50 opacity-60")}>
                                                    <TableCell className="p-2 text-center align-top">
                                                        {item.image_url ? (
                                                            <ImageViewer images={[item.image_url]} alt="Product" className="w-10 h-10 rounded border bg-white object-contain" />
                                                        ) : (
                                                            <div className="w-10 h-10 bg-gray-100 rounded flex items-center justify-center border text-gray-300"><ImageIcon className="w-5 h-5" /></div>
                                                        )}
                                                    </TableCell>
                                                    <TableCell className="p-2 relative w-[350px]">
                                                        {/* Disable เปลี่ยนสินค้าถ้าส่งไปแล้ว */}
                                                        <ProductCombobox
                                                            products={availableProducts}
                                                            value={item.product_id}
                                                            onChange={(val) => updateItem(index, 'product_id', val)}
                                                            disabled={(isConfirmed && !!item.id) || isItemShipped}
                                                            placeholder="Select Product"
                                                        />

                                        {((isConfirmed && item.id) || isItemShipped) && <Lock className="w-3 h-3 text-gray-400 absolute right-3 top-6" />}

                                                    </TableCell>
                                                    <TableCell className="p-2"><Input disabled={isCancelled || isItemShipped} value={item.description} onChange={(e) => updateItem(index, 'description', e.target.value)} className={cn("border-0 shadow-none h-9", isCancelled && "line-through")} /></TableCell>
                                                    <TableCell className="p-2"><Input type="number" min="0" value={item.quantity} onChange={(e) => updateItem(index, 'quantity', parseFloat(e.target.value))} className={cn("border-0 shadow-none h-9 text-right", !isCancelled && "bg-yellow-50/50")} disabled={isItemShipped} /></TableCell>
                                                    <TableCell className="p-2 text-right align-middle"><span className={cn("font-medium", (item.qty_shipped || 0) >= item.quantity ? "text-green-600" : "text-orange-500")}>{item.qty_shipped || 0}</span></TableCell>
                                                    <TableCell className="p-2"><Input disabled={isCancelled || isItemShipped} type="number" value={item.unit_price} onChange={(e) => updateItem(index, 'unit_price', parseFloat(e.target.value))} className={cn("border-0 shadow-none h-9 text-right", !isCancelled && "bg-yellow-50/50")} /></TableCell>
                                                    <TableCell className="text-right font-medium p-2 align-middle"><span className={isCancelled ? "line-through text-gray-400" : "text-gray-700"}>{item.total.toLocaleString()} ฿</span>{isCancelled && <span className="ml-2 text-xs text-red-500 font-bold">(CANCELLED)</span>}</TableCell>
                                                    <TableCell className="p-2 text-center">
                                                        {isCancelled ? (
                                                            <TooltipProvider><Tooltip><TooltipTrigger asChild><Button variant="ghost" size="icon" className="h-8 w-8 text-green-600 hover:bg-green-50" onClick={() => restoreItem(index)}><RotateCcw className="h-4 w-4" /></Button></TooltipTrigger><TooltipContent><p>Restore</p></TooltipContent></Tooltip></TooltipProvider>
                                                        ) : (
                                                            // ✅ ซ่อนปุ่มลบ ถ้าส่งของไปแล้ว
                                                            !isItemShipped && (
                                                                (!isConfirmed || !item.id) ? (
                                                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-gray-400 hover:text-red-500" onClick={() => removeItem(index)}><Trash2 className="h-4 w-4" /></Button>
                                                                ) : (
                                                                    <Button variant="ghost" size="icon" className="h-8 w-8 text-gray-400 hover:text-red-500" onClick={() => removeItem(index)}><Trash2 className="h-4 w-4" /></Button>
                                                                )
                                                            )
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            );
                                        })}
                                        <TableRow>
                                            <TableCell colSpan={8} className="p-2">
                                                {/* ✅ ซ่อนปุ่ม Add ถ้าส่งของครบแล้ว */}
                                                {!isFullyShipped && (
                                                    <Button variant="ghost" className="text-purple-700 hover:bg-purple-50 w-full justify-start" onClick={addItem}><Plus className="h-4 w-4 mr-2" /> Add a product</Button>
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
                    </CardContent>
                </Card>
            </div>
            <div className="max-w-7xl mx-auto"><div className="bg-white rounded-lg border shadow-sm p-6"><h3 className="text-lg font-medium mb-4 border-b pb-2">Communication History</h3><Chatter modelType="sales_order" modelId={order?.id || ""} /></div></div>
        </AuthenticatedLayout>
    );
}
