import React, { useState } from 'react';
import { Head, Link, useForm, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    ArrowLeft, Truck, User, Phone, MapPin,
    CheckCircle2, Package, Printer, ExternalLink, RotateCcw, Image as ImageIcon
} from "lucide-react";

// Components
import { Button } from "@/Components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter, DialogDescription } from "@/components/ui/dialog";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/Components/ui/tabs";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import InventoryNavigationMenu from '@/Pages/Inventory/Partials/InventoryNavigationMenu';
import { Alert, AlertDescription, AlertTitle } from "@/Components/ui/alert";
import ImageViewer from '@/Components/ImageViewer'; // ✅ Import

// Types
interface DeliveryItem {
    id: number;
    product_id: string;
    product_name: string;
    quantity_ordered: number;
    qty_shipped: number;   // ยอดส่งจริงในรอบนี้
    qty_backorder?: number; // ทำให้เป็น Optional เพื่อความยืดหยุ่น
    unit_price: number;
    image_url?: string | null;
    description?: string;
    barcode?: string;
}

interface DeliveryNote {
    id: string;
    delivery_number: string;
    order_number: string;
    customer_name: string;
    shipping_address: string;
    contact_person: string | null;
    contact_phone: string | null;
    status: 'wait_operation' | 'ready_to_ship' | 'shipped' | 'delivered' | 'cancelled';
    carrier_name: string | null;
    tracking_number: string | null;
    shipped_at: string | null;
    picking_number: string;
    shipment_id?: string;
    shipment_number?: string;
}

interface Vehicle {
    id: string;
    license_plate: string;
    brand: string;
    model: string;
    driver_name?: string;
}

interface Props {
    auth: any;
    delivery: DeliveryNote;
    items: DeliveryItem[];
    vehicles: Vehicle[];
}

export default function DeliveryProcess({ auth, delivery, items, vehicles }: Props) {
    const [isShipModalOpen, setIsShipModalOpen] = useState(false);
    const [isDeliverModalOpen, setIsDeliverModalOpen] = useState(false);

    const shipForm = useForm({
        status: 'shipped',
        shipment_type: 'carrier',
        vehicle_id: '',
        carrier_name: delivery.carrier_name || '',
        tracking_number: delivery.tracking_number || '',
    });

    const deliverForm = useForm({ status: 'delivered' });

    const handleShipSubmit = (e: React.FormEvent) => { e.preventDefault(); shipForm.put(route('logistics.delivery.update', delivery.id), { onSuccess: () => setIsShipModalOpen(false) }); };
    const handleDeliverSubmit = (e: React.FormEvent) => { e.preventDefault(); deliverForm.put(route('logistics.delivery.update', delivery.id), { onSuccess: () => setIsDeliverModalOpen(false) }); };
    const handleCancelReturn = () => { if (confirm("ยืนยันการยกเลิกการจัดส่งและส่งคืนสินค้าเข้าคลัง? (ระบบจะสร้างใบ Return Note)")) { router.post(route('logistics.delivery.cancel-return', delivery.id)); } };

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'wait_operation': return <Badge variant="outline" className="bg-gray-100 text-gray-500">Waiting Picking</Badge>;
            case 'ready_to_ship': return <Badge className="bg-blue-600">Ready to Ship</Badge>;
            case 'shipped': return <Badge className="bg-orange-500">Shipped</Badge>;
            case 'delivered': return <Badge className="bg-green-600">Delivered</Badge>;
            case 'cancelled': return <Badge variant="destructive">Cancelled</Badge>;
            default: return <Badge variant="secondary">{status}</Badge>;
        }
    };

    const isInShipmentPlan = !!delivery.shipment_id;

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<InventoryNavigationMenu />}>
            <Head title={`Delivery ${delivery.delivery_number}`} />

            <div className="max-w-5xl mx-auto px-4 py-8 space-y-6">

                {/* Header (เหมือนเดิม) */}
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <div className="flex items-center gap-2 text-gray-500 mb-1">
                            <Link href={route('logistics.delivery.index')}><Button variant="ghost" size="sm" className="h-6 px-0"><ArrowLeft className="w-4 h-4 mr-1" /> Back to List</Button></Link>
                        </div>
                        <div className="flex items-center gap-3"><h1 className="text-3xl font-bold text-gray-900">{delivery.delivery_number}</h1>{getStatusBadge(delivery.status)}</div>
                        <p className="text-sm text-gray-500 mt-1">Order Ref: <span className="font-medium text-gray-700">{delivery.order_number}</span> | Picking Ref: <span className="font-medium text-gray-700">{delivery.picking_number}</span></p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild className="gap-2">
                            <a href={route('logistics.delivery.pdf', delivery.id)} target="_blank">
                                <Printer className="w-4 h-4" /> Print DO
                            </a>
                        </Button>
                        {delivery.status === 'ready_to_ship' && (<><Button variant="destructive" onClick={handleCancelReturn} className="gap-2"><RotateCcw className="w-4 h-4" /> Cancel & Return</Button>{!isInShipmentPlan && (<Button className="bg-blue-600 hover:bg-blue-700 gap-2" onClick={() => setIsShipModalOpen(true)}><Truck className="w-4 h-4" /> Confirm Shipment</Button>)}</>)}
                        {delivery.status === 'shipped' && (<Button className="bg-green-600 hover:bg-green-700 gap-2" onClick={() => setIsDeliverModalOpen(true)}><CheckCircle2 className="w-4 h-4" /> Mark as Delivered</Button>)}
                    </div>
                </div>

                {isInShipmentPlan && delivery.status !== 'delivered' && (
                    <Alert className="bg-indigo-50 border-indigo-200">
                        <Truck className="h-4 w-4 text-indigo-600" />
                        <AlertTitle className="text-indigo-800 font-bold">Planned Shipment</AlertTitle>
                        <AlertDescription className="text-indigo-700 flex items-center gap-2">
                            ใบส่งของนี้ถูกจัดอยู่ในรอบรถเลขที่
                            <Link href={route('logistics.shipments.show', delivery.shipment_id!)} className="font-bold underline flex items-center gap-1 hover:text-indigo-900">{delivery.shipment_number} <ExternalLink className="w-3 h-3"/></Link>
                            กรุณาดำเนินการปล่อยรถผ่านเมนู Shipment Planning
                        </AlertDescription>
                    </Alert>
                )}

                {/* Info Cards (เหมือนเดิม) */}
                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <Card><CardHeader className="pb-2"><CardTitle className="text-sm font-medium text-gray-500 uppercase">Customer</CardTitle></CardHeader><CardContent><div className="flex items-start gap-3"><div className="bg-blue-50 p-2 rounded-full"><User className="w-5 h-5 text-blue-600" /></div><div><p className="font-bold text-gray-900">{delivery.customer_name}</p>{delivery.contact_person && <p className="text-sm text-gray-600">{delivery.contact_person}</p>}{delivery.contact_phone && (<div className="flex items-center gap-1 text-sm text-gray-500 mt-1"><Phone className="w-3 h-3" /> {delivery.contact_phone}</div>)}</div></div></CardContent></Card>
                    <Card><CardHeader className="pb-2"><CardTitle className="text-sm font-medium text-gray-500 uppercase">Shipping Address</CardTitle></CardHeader><CardContent><div className="flex items-start gap-3"><div className="bg-orange-50 p-2 rounded-full"><MapPin className="w-5 h-5 text-orange-600" /></div><p className="text-sm text-gray-700 leading-relaxed">{delivery.shipping_address}</p></div></CardContent></Card>
                    <Card><CardHeader className="pb-2"><CardTitle className="text-sm font-medium text-gray-500 uppercase">Logistics Info</CardTitle></CardHeader><CardContent className="space-y-3"><div className="flex justify-between text-sm"><span className="text-gray-500">Carrier:</span><span className="font-medium">{delivery.carrier_name || '-'}</span></div><div className="flex justify-between text-sm"><span className="text-gray-500">Tracking No:</span><span className="font-mono bg-gray-100 px-2 rounded">{delivery.tracking_number || '-'}</span></div>{delivery.shipped_at && <div className="flex justify-between text-sm"><span className="text-gray-500">Shipped Date:</span><span className="text-gray-700">{delivery.shipped_at}</span></div>}</CardContent></Card>
                </div>

                {/* Items Table */}
                <Card>
                    <CardHeader><CardTitle className="flex items-center gap-2"><Package className="w-5 h-5 text-gray-500" /> Items to Deliver</CardTitle></CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-[50px]">#</TableHead>
                                    <TableHead className="w-[80px] text-center">Image</TableHead>
                                    <TableHead>Product Details</TableHead>
                                    <TableHead className="text-center text-gray-400">Total Ordered</TableHead>
                                    <TableHead className="text-right pr-6 bg-blue-50/50 text-blue-700 font-bold border-l">Shipped (This Delivery)</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {items.length > 0 ? items.map((item, idx) => (
                                    <TableRow key={item.id}>
                                        <TableCell className="text-gray-500">{idx + 1}</TableCell>

                                        {/* Image */}
                                        <TableCell className="text-center">
                                            {item.image_url ? (
                                                <ImageViewer images={[item.image_url]} alt={item.product_name} className="w-10 h-10 rounded border bg-white object-contain" />
                                            ) : (
                                                <div className="w-10 h-10 bg-gray-100 rounded flex items-center justify-center border text-gray-300 mx-auto"><ImageIcon className="w-5 h-5" /></div>
                                            )}
                                        </TableCell>

                                        <TableCell>
                                            <div className="font-medium text-gray-900">{item.product_name}</div>
                                            <div className="text-xs text-gray-500 font-mono mt-0.5">{item.product_id}</div>
                                            {item.barcode && <div className="text-[10px] text-gray-400 font-mono">SKU: {item.barcode}</div>}
                                        </TableCell>

                                        <TableCell className="text-center text-gray-500 font-medium">
                                            {item.quantity_ordered}
                                        </TableCell>

                                        <TableCell className="text-right pr-6 font-bold text-blue-700 text-lg bg-blue-50/20 border-l border-blue-100">
                                            {item.qty_shipped}
                                        </TableCell>
                                    </TableRow>
                                )) : (
                                    <TableRow><TableCell colSpan={5} className="text-center h-24 text-gray-400">No items in this shipment.</TableCell></TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>

                {/* Modals (เหมือนเดิม) */}
                <Dialog open={isShipModalOpen} onOpenChange={setIsShipModalOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader><DialogTitle>Confirm Shipment</DialogTitle><DialogDescription>เลือกรูปแบบการขนส่งเพื่อยืนยันการส่งสินค้าออกจากคลัง</DialogDescription></DialogHeader>
                        <form onSubmit={handleShipSubmit} className="space-y-4 py-2">
                            <Tabs defaultValue="carrier" value={shipForm.data.shipment_type} onValueChange={(val) => shipForm.setData('shipment_type', val)} className="w-full">
                                <TabsList className="grid w-full grid-cols-2 mb-4"><TabsTrigger value="carrier">External Carrier</TabsTrigger><TabsTrigger value="fleet">Internal Fleet</TabsTrigger></TabsList>
                                <TabsContent value="carrier" className="space-y-4">
                                    <div className="space-y-2"><Label>Carrier Name</Label><Input placeholder="e.g. Kerry, Flash Express" value={shipForm.data.carrier_name} onChange={e => shipForm.setData('carrier_name', e.target.value)} required={shipForm.data.shipment_type === 'carrier'} /></div>
                                    <div className="space-y-2"><Label>Tracking Number</Label><Input placeholder="e.g. TH12345678" value={shipForm.data.tracking_number} onChange={e => shipForm.setData('tracking_number', e.target.value)} /></div>
                                </TabsContent>
                                <TabsContent value="fleet" className="space-y-4">
                                    <div className="space-y-2"><Label>Select Vehicle</Label><Select value={shipForm.data.vehicle_id} onValueChange={(val) => shipForm.setData('vehicle_id', val)} required={shipForm.data.shipment_type === 'fleet'}><SelectTrigger><SelectValue placeholder="-- เลือกรถที่จะใช้ส่ง --" /></SelectTrigger><SelectContent>{vehicles && vehicles.length > 0 ? (vehicles.map((v) => <SelectItem key={v.id} value={v.id}>{v.license_plate} - {v.brand} {v.model} ({v.driver_name || 'No Driver'})</SelectItem>)) : (<div className="p-2 text-sm text-gray-500 text-center">No active vehicles found</div>)}</SelectContent></Select><p className="text-xs text-gray-500 mt-1">*ระบบจะสร้าง Shipment Plan (Trip) ให้อัตโนมัติเมื่อกด Confirm</p></div>
                                </TabsContent>
                            </Tabs>
                            <DialogFooter className="mt-6"><Button type="button" variant="outline" onClick={() => setIsShipModalOpen(false)}>Cancel</Button><Button type="submit" disabled={shipForm.processing}>Confirm Ship</Button></DialogFooter>
                        </form>
                    </DialogContent>
                </Dialog>

                <Dialog open={isDeliverModalOpen} onOpenChange={setIsDeliverModalOpen}>
                    <DialogContent><DialogHeader><DialogTitle>Mark as Delivered</DialogTitle><DialogDescription>ยืนยันว่าลูกค้าได้รับสินค้าเรียบร้อยแล้ว?</DialogDescription></DialogHeader><DialogFooter><Button type="button" variant="outline" onClick={() => setIsDeliverModalOpen(false)}>Cancel</Button><Button onClick={handleDeliverSubmit} disabled={deliverForm.processing} className="bg-green-600 hover:bg-green-700">Confirm Delivery</Button></DialogFooter></DialogContent>
                </Dialog>

            </div>
        </AuthenticatedLayout>
    );
}
