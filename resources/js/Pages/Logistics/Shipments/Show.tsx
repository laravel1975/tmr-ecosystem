import React, { useState } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Separator } from "@/Components/ui/separator";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import {
    ArrowLeft, Truck, User, Calendar, MapPin,
    Play, CheckCircle2, Printer, Package, XCircle, Phone,
    AlertTriangle, Loader2, Map as MapIcon
} from "lucide-react";
import { cn } from '@/lib/utils';
import { Tooltip, TooltipContent, TooltipProvider, TooltipTrigger } from '@/Components/ui/tooltip';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from "@/Components/ui/dialog";
import { RadioGroup, RadioGroupItem } from "@/Components/ui/radio-group";
import axios from 'axios';
import LogisticsNavigationMenu from '../Partials/LogisticsNavigationMenu';
// ✅ Import Timeline Component
import ShipmentTimeline from '@/Components/Logistics/ShipmentTimeline';

// --- Types ---
interface Delivery {
    id: string;
    delivery_number: string;
    order_number: string;
    customer_name: string;
    shipping_address: string;
    status: string;
}

interface Shipment {
    id: string;
    shipment_number: string;
    status: 'planned' | 'shipped' | 'completed';
    planned_date: string;
    departed_at?: string;
    completed_at?: string;
    driver_name: string;
    driver_phone: string;
    note: string;
    vehicle: {
        license_plate: string;
        brand: string;
        type: string;
    } | null;
}

interface Props {
    auth: any;
    shipment: Shipment;
    deliveries: Delivery[];
}

export default function ShipmentShow({ auth, shipment, deliveries }: Props) {

    // --- States ---
    const [unloadModalOpen, setUnloadModalOpen] = useState(false);
    const [statusModalOpen, setStatusModalOpen] = useState(false);
    const [targetStatus, setTargetStatus] = useState<'shipped' | 'completed' | null>(null);

    const [selectedDelivery, setSelectedDelivery] = useState<Delivery | null>(null);
    const [unloadType, setUnloadType] = useState<'whole' | 'partial'>('whole');

    // ✅ [เพิ่ม] State สำหรับเลือก Action ปลายทาง (Stock หรือ Return)
    const [unloadAction, setUnloadAction] = useState<'stock' | 'return'>('stock');

    const [deliveryItems, setDeliveryItems] = useState<any[]>([]);
    const [isLoadingItems, setIsLoadingItems] = useState(false);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // --- Status Handlers ---
    const openStatusConfirm = (status: 'shipped' | 'completed') => {
        setTargetStatus(status);
        setStatusModalOpen(true);
    };

    const confirmUpdateStatus = () => {
        if (!targetStatus) return;

        setIsSubmitting(true);
        router.post(route('logistics.shipments.status', shipment.id), {
            status: targetStatus
        }, {
            onSuccess: () => {
                setStatusModalOpen(false);
                setIsSubmitting(false);
            },
            onError: () => setIsSubmitting(false)
        });
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'planned': return 'bg-blue-600 hover:bg-blue-700';
            case 'shipped': return 'bg-orange-500 hover:bg-orange-600';
            case 'completed': return 'bg-green-600 hover:bg-green-700';
            default: return 'bg-gray-500';
        }
    };

    const getStatusLabel = (status: string) => {
        switch (status) {
            case 'planned': return 'Planned (รอจัดส่ง)';
            case 'shipped': return 'Shipped (ระหว่างทาง)';
            case 'completed': return 'Completed (จบงาน)';
            default: return status.toUpperCase();
        }
    };

    // --- Unload Handlers ---
    const handleUnloadClick = (delivery: Delivery) => {
        setSelectedDelivery(delivery);
        setUnloadType('whole');
        setUnloadAction('stock'); // Default Action
        setDeliveryItems([]);
        setUnloadModalOpen(true);
    };

    const handleTypeChange = (type: 'whole' | 'partial') => {
        setUnloadType(type);
        if (type === 'partial' && selectedDelivery && deliveryItems.length === 0) {
            setIsLoadingItems(true);
            axios.get(route('logistics.delivery.items', selectedDelivery.id))
                .then(res => setDeliveryItems(res.data.map((i: any) => ({ ...i, qty_unload: 0 }))))
                .catch(err => alert("Error loading items"))
                .finally(() => setIsLoadingItems(false));
        }
    };

    const updateUnloadQty = (index: number, val: number) => {
        const newItems = [...deliveryItems];
        newItems[index].qty_unload = Math.min(Math.max(0, val), newItems[index].qty_picked);
        setDeliveryItems(newItems);
    };

    const confirmUnload = () => {
        if (!selectedDelivery) return;
        if (unloadType === 'partial') {
            const totalUnload = deliveryItems.reduce((acc, i) => acc + i.qty_unload, 0);
            if (totalUnload === 0) { alert("กรุณาระบุจำนวนสินค้า"); return; }
        }

        const confirmMessage = unloadAction === 'return'
            ? "⚠️ คำเตือน: สินค้าจะถูกส่งคืน (Return) และต้องผ่านขั้นตอน QC\nยืนยันการเอาของลง?"
            : "ยืนยันการเอาของลงเพื่อรอจัดส่งรอบหน้า?";

        if(!confirm(confirmMessage)) return;

        setIsSubmitting(true);
        router.post(route('logistics.shipments.unload', shipment.id), {
            delivery_note_id: selectedDelivery.id,
            type: unloadType,
            target_action: unloadAction, // ✅ ส่ง target_action ไปด้วย
            items: deliveryItems
        }, {
            onSuccess: () => { setUnloadModalOpen(false); setIsSubmitting(false); },
            onError: () => setIsSubmitting(false)
        });
    };

    const openGoogleMapsRoute = () => {
        if (deliveries.length === 0) return;
        const origin = encodeURIComponent("บริษัท ทีเอ็มอาร์ อีโคซิสเต็ม จำกัด");
        const lastDelivery = deliveries[deliveries.length - 1];
        const destination = encodeURIComponent(lastDelivery.shipping_address);
        const waypoints = deliveries.slice(0, -1)
            .map(d => encodeURIComponent(d.shipping_address))
            .join('|');
        const url = `https://www.google.com/maps/dir/?api=1&origin=${origin}&destination=${destination}&waypoints=${waypoints}&travelmode=driving`;
        window.open(url, '_blank');
    };

    const isEditable = shipment.status === 'planned';

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<LogisticsNavigationMenu/>}>
            <Head title={`Shipment ${shipment.shipment_number}`} />

            <div className="max-w-7xl mx-auto p-6 space-y-6">

                {/* Header & Actions */}
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-gray-500 mb-1">
                            <Link href={route('logistics.shipments.index')}>
                                <Button variant="ghost" size="sm" className="h-6 px-0">
                                    <ArrowLeft className="w-4 h-4 mr-1" /> Back to List
                                </Button>
                            </Link>
                        </div>
                        <div className="flex items-center gap-3">
                            <h1 className="text-3xl font-bold text-gray-900">{shipment.shipment_number}</h1>
                            <Badge className={cn("text-base px-3 py-1", getStatusColor(shipment.status))}>
                                {getStatusLabel(shipment.status)}
                            </Badge>
                        </div>
                    </div>

                    <div className="flex gap-2">
                        {shipment.status !== 'completed' && deliveries.length > 0 && (
                            <Button variant="outline" className="gap-2 text-blue-600 border-blue-200 hover:bg-blue-50" onClick={openGoogleMapsRoute}>
                                <MapIcon className="w-4 h-4" /> Route Map
                            </Button>
                        )}
                        <Button variant="outline" className="gap-2" asChild>
                            <a href={route('logistics.shipments.manifest', shipment.id)} target="_blank">
                                <Printer className="w-4 h-4" /> Print Manifest (PDF)
                            </a>
                        </Button>
                        {shipment.status === 'planned' && (
                            <Button className="bg-orange-600 hover:bg-orange-700 text-white gap-2 shadow-sm" onClick={() => openStatusConfirm('shipped')}>
                                <Play className="w-4 h-4" /> Confirm Departure (ปล่อยรถ)
                            </Button>
                        )}
                        {shipment.status === 'shipped' && (
                            <Button className="bg-green-600 hover:bg-green-700 text-white gap-2 shadow-sm" onClick={() => openStatusConfirm('completed')}>
                                <CheckCircle2 className="w-4 h-4" /> Close Trip (จบงาน)
                            </Button>
                        )}
                    </div>
                </div>

                <div className="mt-2">
                    <ShipmentTimeline status={shipment.status} />
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <Card className="lg:col-span-1 h-fit border-indigo-100 shadow-sm">
                        <CardHeader className="bg-indigo-50/30 pb-4">
                            <CardTitle className="flex items-center gap-2 text-indigo-900">
                                <Truck className="w-5 h-5" /> Trip Info
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4 pt-4">
                            <div>
                                <label className="text-xs text-gray-500 uppercase font-bold">Vehicle</label>
                                <div className="text-lg font-medium">{shipment.vehicle?.license_plate || 'N/A'}</div>
                                <div className="text-sm text-gray-500">{shipment.vehicle?.brand} ({shipment.vehicle?.type})</div>
                            </div>
                            <Separator />
                            <div>
                                <label className="text-xs text-gray-500 uppercase font-bold">Driver</label>
                                <div className="flex items-center gap-2 mt-1">
                                    <User className="w-4 h-4 text-gray-400" />
                                    <span>{shipment.driver_name || '-'}</span>
                                </div>
                                <div className="flex items-center gap-2 mt-1 text-sm text-gray-500 pl-6">
                                    <Phone className="w-3 h-3" /> {shipment.driver_phone || '-'}
                                </div>
                            </div>
                            <Separator />
                            <div>
                                <label className="text-xs text-gray-500 uppercase font-bold">Schedule</label>
                                <div className="flex items-center gap-2 mt-1">
                                    <Calendar className="w-4 h-4 text-gray-400" />
                                    <span>Plan: {shipment.planned_date}</span>
                                </div>
                                {shipment.departed_at && <div className="text-xs text-orange-600 mt-2 font-medium ml-6">Out: {shipment.departed_at}</div>}
                                {shipment.completed_at && <div className="text-xs text-green-600 mt-1 font-medium ml-6">In: {shipment.completed_at}</div>}
                            </div>
                            {shipment.note && (
                                <div className="bg-yellow-50 p-3 rounded border border-yellow-100 text-sm mt-4 text-gray-700">
                                    <span className="font-bold block mb-1">Note:</span> {shipment.note}
                                </div>
                            )}
                        </CardContent>
                    </Card>

                    <Card className="lg:col-span-2 shadow-sm">
                        <CardHeader className="flex flex-row justify-between items-center bg-gray-50/50 border-b py-3">
                            <CardTitle className="flex items-center gap-2 text-gray-800">
                                <Package className="w-5 h-5" /> Cargo Manifest
                            </CardTitle>
                            <Badge variant="secondary" className="text-sm">{deliveries.length} Orders</Badge>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>DO Number</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Destination</TableHead>
                                        <TableHead className="text-right">Status</TableHead>
                                        {isEditable && <TableHead className="w-[50px]"></TableHead>}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {deliveries.length === 0 ? (
                                        <TableRow><TableCell colSpan={5} className="text-center h-32 text-gray-400">No orders in this shipment.</TableCell></TableRow>
                                    ) : (
                                        deliveries.map((dn) => (
                                            <TableRow key={dn.id} className="hover:bg-slate-50">
                                                <TableCell className="font-medium">
                                                    <span className="text-indigo-600">{dn.delivery_number}</span>
                                                    <div className="text-xs text-gray-500">{dn.order_number}</div>
                                                </TableCell>
                                                <TableCell>{dn.customer_name}</TableCell>
                                                <TableCell>
                                                    <div className="flex items-start gap-2 text-sm text-gray-600 max-w-[300px]">
                                                        <MapPin className="w-4 h-4 mt-0.5 text-gray-400 shrink-0" />
                                                        <span className="truncate">{dn.shipping_address}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell className="text-right">
                                                    <Badge variant="outline" className="bg-white">{dn.status}</Badge>
                                                </TableCell>
                                                {isEditable && (
                                                    <TableCell className="text-center">
                                                        <TooltipProvider>
                                                            <Tooltip>
                                                                <TooltipTrigger asChild>
                                                                    <Button variant="ghost" size="icon" className="text-gray-400 hover:text-red-600 hover:bg-red-50" onClick={() => handleUnloadClick(dn)}>
                                                                        <XCircle className="w-5 h-5" />
                                                                    </Button>
                                                                </TooltipTrigger>
                                                                <TooltipContent><p>Unload (เอาของลง / คืนสถานะ)</p></TooltipContent>
                                                            </Tooltip>
                                                        </TooltipProvider>
                                                    </TableCell>
                                                )}
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                </div>

                {/* Status Confirm Modal (คงเดิม) */}
                <Dialog open={statusModalOpen} onOpenChange={setStatusModalOpen}>
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>
                                {targetStatus === 'shipped' ? 'Confirm Departure (ยืนยันรถออก)' : 'Confirm Completion (ยืนยันจบงาน)'}
                            </DialogTitle>
                            <DialogDescription>
                                {targetStatus === 'shipped'
                                    ? 'เมื่อยืนยันแล้ว ระบบจะตัดสต็อกสินค้าออกจากระบบ (Hard Reserve -> Deduct) และเปลี่ยนสถานะใบส่งของทั้งหมดเป็น "Shipped"'
                                    : 'เมื่อยืนยันแล้ว สถานะ Shipment จะปิดจบ และเปลี่ยนสถานะใบส่งของทั้งหมดเป็น "Delivered"'}
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button variant="outline" onClick={() => setStatusModalOpen(false)} disabled={isSubmitting}>Cancel</Button>
                            <Button
                                onClick={confirmUpdateStatus}
                                disabled={isSubmitting}
                                className={targetStatus === 'shipped' ? "bg-orange-600 hover:bg-orange-700" : "bg-green-600 hover:bg-green-700"}
                            >
                                {isSubmitting && <Loader2 className="w-4 h-4 animate-spin mr-2" />}
                                Confirm
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Unload Modal (Updated with Action Selection) */}
                <Dialog open={unloadModalOpen} onOpenChange={setUnloadModalOpen}>
                    <DialogContent className="sm:max-w-[600px]">
                        <DialogHeader>
                            <DialogTitle className="flex items-center gap-2 text-red-600">
                                <AlertTriangle className="w-5 h-5" /> Unload Delivery (เอาของลง)
                            </DialogTitle>
                            <div className="text-sm text-gray-500 mt-1 border-l-4 border-red-100 pl-3 py-1">
                                DO No: <span className="font-bold text-gray-900">{selectedDelivery?.delivery_number}</span> <br/>
                                Customer: {selectedDelivery?.customer_name}
                            </div>
                        </DialogHeader>

                        <div className="py-4 space-y-6">
                            {/* 1. Select Unload Type (Whole/Partial) */}
                            <div className="space-y-3">
                                <Label className="text-sm font-semibold text-gray-700">1. Select Unload Type</Label>
                                <RadioGroup value={unloadType} onValueChange={(v) => handleTypeChange(v as any)} className="flex gap-4">
                                    <div className={cn("flex items-center space-x-2 border p-3 rounded-md flex-1 cursor-pointer hover:bg-gray-50 transition-colors", unloadType === 'whole' && "border-indigo-500 bg-indigo-50")}>
                                        <RadioGroupItem value="whole" id="whole" />
                                        <Label htmlFor="whole" className="cursor-pointer font-medium">เอาลงทั้งใบ (Whole)</Label>
                                    </div>
                                    <div className={cn("flex items-center space-x-2 border p-3 rounded-md flex-1 cursor-pointer hover:bg-gray-50 transition-colors", unloadType === 'partial' && "border-indigo-500 bg-indigo-50")}>
                                        <RadioGroupItem value="partial" id="partial" />
                                        <Label htmlFor="partial" className="cursor-pointer font-medium">เอาลงบางส่วน (Partial)</Label>
                                    </div>
                                </RadioGroup>
                            </div>

                            {/* 2. Select Target Action (Stock/Return) */}
                            <div className="space-y-3">
                                <Label className="text-sm font-semibold text-gray-700">2. Select Target Action</Label>
                                <RadioGroup value={unloadAction} onValueChange={(v) => setUnloadAction(v as any)} className="flex gap-4">
                                    <div className={cn("flex items-center space-x-2 border p-3 rounded-md flex-1 cursor-pointer hover:bg-gray-50 transition-colors", unloadAction === 'stock' && "border-blue-500 bg-blue-50")}>
                                        <RadioGroupItem value="stock" id="act_stock" />
                                        <div className="grid gap-1">
                                            <Label htmlFor="act_stock" className="cursor-pointer font-medium">รอส่งรอบหน้า (Reschedule)</Label>
                                            <p className="text-xs text-gray-500">เก็บไว้ในคลัง สถานะ Ready to Ship</p>
                                        </div>
                                    </div>
                                    <div className={cn("flex items-center space-x-2 border p-3 rounded-md flex-1 cursor-pointer hover:bg-gray-50 transition-colors", unloadAction === 'return' && "border-red-500 bg-red-50")}>
                                        <RadioGroupItem value="return" id="act_return" />
                                        <div className="grid gap-1">
                                            <Label htmlFor="act_return" className="cursor-pointer font-medium text-red-700">สินค้าเสียหาย/ยกเลิก (Return)</Label>
                                            <p className="text-xs text-red-500">สร้าง Return Note และคืนยอด Sales Order</p>
                                        </div>
                                    </div>
                                </RadioGroup>
                            </div>

                            {/* Partial Items Table */}
                            {unloadType === 'partial' && (
                                <div className="border rounded-md overflow-hidden bg-white mt-4">
                                    <div className="bg-gray-100 px-4 py-2 text-xs font-bold text-gray-500 uppercase">Select items to unload</div>

                                    {isLoadingItems ? (
                                        <div className="p-8 text-center text-gray-500 flex flex-col items-center gap-2">
                                            <Loader2 className="w-6 h-6 animate-spin text-indigo-600" />
                                            Loading items...
                                        </div>
                                    ) : (
                                        <div className="max-h-[250px] overflow-y-auto">
                                            <Table>
                                                <TableHeader>
                                                    <TableRow>
                                                        <TableHead>Product</TableHead>
                                                        <TableHead className="text-center w-[80px]">On Truck</TableHead>
                                                        <TableHead className="text-center w-[100px] bg-red-50 text-red-700">Unload</TableHead>
                                                    </TableRow>
                                                </TableHeader>
                                                <TableBody>
                                                    {deliveryItems.map((item, idx) => (
                                                        <TableRow key={item.id}>
                                                            <TableCell className="py-2">
                                                                <div className="font-medium text-sm">{item.product_name}</div>
                                                                <div className="text-xs text-gray-400">{item.product_id}</div>
                                                            </TableCell>
                                                            <TableCell className="text-center font-bold text-gray-600">
                                                                {item.qty_picked}
                                                            </TableCell>
                                                            <TableCell className="py-2">
                                                                <Input
                                                                    type="number" min="0" max={item.qty_picked}
                                                                    value={item.qty_unload}
                                                                    onChange={e => updateUnloadQty(idx, parseInt(e.target.value) || 0)}
                                                                    className={cn(
                                                                        "h-8 text-center font-bold",
                                                                        item.qty_unload > 0 ? "border-red-500 bg-red-50 text-red-700" : "bg-white"
                                                                    )}
                                                                />
                                                            </TableCell>
                                                        </TableRow>
                                                    ))}
                                                </TableBody>
                                            </Table>
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>

                        <DialogFooter>
                            <Button variant="outline" onClick={() => setUnloadModalOpen(false)} disabled={isSubmitting}>Cancel</Button>
                            <Button variant="destructive" onClick={confirmUnload} disabled={isSubmitting}>
                                {isSubmitting ? <Loader2 className="w-4 h-4 animate-spin mr-2" /> : null}
                                Confirm Unload
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

            </div>
        </AuthenticatedLayout>
    );
}
