import React from 'react';
import { Head, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    ArrowLeft, Printer, MapPin, Phone,
    FileText, User, Calendar, Package, Truck
} from "lucide-react";
import { format } from 'date-fns';
import InventoryNavigationMenu from '@/Pages/Inventory/Partials/InventoryNavigationMenu';
import ImageViewer from '@/Components/ImageViewer';
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table"; // ✅ ใช้ Table Component
import { cn } from '@/lib/utils';

// --- Types ---
interface DeliveryItem {
    id: number;
    product_id: string;
    product_name: string;
    description?: string;
    barcode?: string;
    quantity_ordered: number;
    qty_shipped: number;
    qty_backorder?: number;
    image_url?: string;
    uom?: string;
}

interface DeliveryNote {
    id: string;
    delivery_number: string;
    status: string;
    created_at: string;
    shipping_address: string;
    contact_person?: string;
    contact_phone?: string;
    carrier_name?: string;
    tracking_number?: string;
    picking_number?: string;
    order?: {
        order_number: string;
        customer?: {
            name: string;
            address?: string;
            phone?: string;
        }
    };
    // รองรับกรณี Items ติดมากับ Delivery Object
    items?: DeliveryItem[];
}

interface Props {
    auth: any;
    delivery: DeliveryNote;
    items?: DeliveryItem[]; // ทำให้เป็น Optional เพื่อกัน Error
}

export default function DeliveryShow({ auth, delivery, items = [] }: Props) { // ✅ 1. ใส่ Default Value = []

    // ✅ 2. Safe Fallback Logic:
    // ถ้า items (prop แยก) ไม่มีค่า ให้ลองไปหาใน delivery.items แทน
    // ถ้าไม่มีทั้งคู่ ให้เป็น array ว่าง [] เพื่อกัน .map() พัง
    const safeItems = (items && items.length > 0) ? items : (delivery.items || []);

    const getStatusBadge = (status: string) => {
        const styles: Record<string, string> = {
            draft: "bg-gray-100 text-gray-700 border-gray-200",
            wait_operation: "bg-yellow-100 text-yellow-700 border-yellow-200",
            ready_to_ship: "bg-blue-100 text-blue-700 border-blue-200",
            shipped: "bg-orange-100 text-orange-700 border-orange-200",
            delivered: "bg-emerald-100 text-emerald-700 border-emerald-200",
            cancelled: "bg-red-100 text-red-700 border-red-200",
        };
        return (
            <Badge variant="outline" className={`px-3 py-1 rounded-full font-semibold print:hidden ${styles[status] || "bg-gray-100 text-gray-700"}`}>
                {status ? status.toUpperCase().replace('_', ' ') : 'UNKNOWN'}
            </Badge>
        );
    };

    const encodeCode128 = (text: string) => text;

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<div className="print:hidden"><InventoryNavigationMenu /></div>}>
            <Head>
                <title>{`Delivery ${delivery.delivery_number}`}</title>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
                <style>{`
                    @media print {
                        @page { margin: 0; size: A4; }
                        body { background-color: white !important; -webkit-print-color-adjust: exact; }
                        nav, header, .no-print { display: none !important; }
                        .print-container { width: 100%; margin: 0; padding: 1.5cm; box-shadow: none !important; border: none !important; }
                        .page-break { page-break-inside: avoid; }
                        .print-hidden { display: none; }
                    }
                `}</style>
            </Head>

            <div className="min-h-screen bg-gray-100/80 pb-12 pt-6 print:bg-white print:pt-0 print:pb-0">
                <div className="max-w-5xl mx-auto px-4 sm:px-6">

                    {/* --- Action Toolbar --- */}
                    <div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 no-print">
                        <div className="flex items-center gap-3">
                            <Button variant="outline" size="icon" className="h-9 w-9 rounded-full bg-white shadow-sm hover:bg-gray-50 border-gray-300" asChild>
                                <Link href={route('logistics.delivery.index')}>
                                    <ArrowLeft className="h-4 w-4 text-gray-600" />
                                </Link>
                            </Button>
                            <div className="flex flex-col">
                                <div className="flex items-center gap-2">
                                    <h1 className="text-xl font-bold text-gray-900">{delivery.delivery_number}</h1>
                                    {getStatusBadge(delivery.status)}
                                </div>
                                <span className="text-xs text-gray-500 flex items-center gap-1">
                                    <FileText className="w-3 h-3" /> Order: {delivery.order?.order_number}
                                </span>
                            </div>
                        </div>

                        <a href={route('logistics.delivery.pdf', delivery.id)} target="_blank">
                            <Button variant="outline" className="flex-1 sm:flex-none bg-white shadow-sm gap-2 border-gray-300">
                                <Printer className="w-4 h-4" /> Print PDF
                            </Button>
                        </a>
                    </div>

                    {/* --- Document Sheet --- */}
                    <div className="print-container bg-white rounded-xl shadow-xl border border-gray-200 p-8 md:p-12 relative overflow-hidden">

                        <div className="absolute top-0 left-0 right-0 h-2 bg-orange-500 no-print" />

                        {/* 1. Header */}
                        <div className="flex justify-between items-start border-b-2 border-gray-100 pb-8 mb-8">
                            <div className="space-y-4">
                                <div className="flex items-center gap-3">
                                    <div className="w-12 h-12 bg-gray-900 text-white flex items-center justify-center font-bold text-2xl rounded-lg shadow-sm">T</div>
                                    <div>
                                        <h2 className="font-bold text-xl leading-none tracking-tight text-gray-900">TMR EcoSystem</h2>
                                        <p className="text-xs text-gray-500 font-medium uppercase tracking-widest mt-1">Logistics Division</p>
                                    </div>
                                </div>
                                <div className="grid grid-cols-2 gap-x-8 gap-y-1 text-sm text-gray-600">
                                    <div className="flex items-center gap-2"><Calendar className="w-4 h-4 text-gray-400" /> <span className="font-semibold text-gray-900">Date:</span></div>
                                    <div>{delivery.created_at ? format(new Date(delivery.created_at), 'dd MMM yyyy') : '-'}</div>

                                    <div className="flex items-center gap-2"><Truck className="w-4 h-4 text-gray-400" /> <span className="font-semibold text-gray-900">Carrier:</span></div>
                                    <div>{delivery.carrier_name || '-'}</div>

                                    <div className="flex items-center gap-2"><FileText className="w-4 h-4 text-gray-400" /> <span className="font-semibold text-gray-900">Tracking:</span></div>
                                    <div className="font-mono">{delivery.tracking_number || '-'}</div>

                                    <div className="flex items-center gap-2"><Package className="w-4 h-4 text-gray-400" /> <span className="font-semibold text-gray-900">Ref Picking:</span></div>
                                    <div className="font-mono">{delivery.picking_number || '-'}</div>
                                </div>
                            </div>

                            <div className="text-right flex flex-col items-end">
                                <div className="bg-gray-50 px-4 py-2 rounded-lg border border-gray-100 mb-2">
                                    <span className="text-xs font-bold text-gray-400 uppercase tracking-wider">Document Type</span>
                                    <div className="text-lg font-black text-gray-900">DELIVERY NOTE</div>
                                </div>
                                <div className="relative mt-2">
                                    <div className="text-6xl text-gray-900 select-none transform scale-y-110 scale-x-90" style={{ fontFamily: '"Libre Barcode 128", cursive' }}>
                                        {encodeCode128(delivery.delivery_number)}
                                    </div>
                                    <div className="text-center font-mono font-bold text-sm tracking-widest text-gray-500 -mt-2">
                                        {delivery.delivery_number}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* 2. Details Grid */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                            {/* Customer Info */}
                            <div className="bg-gray-50/50 rounded-xl p-5 border border-gray-100">
                                <h3 className="text-xs font-bold uppercase text-gray-400 mb-4 flex items-center gap-2">
                                    <User className="w-4 h-4" /> Customer Information
                                </h3>
                                <div className="space-y-3 text-sm">
                                    <div className="flex justify-between border-b border-gray-200 pb-2">
                                        <span className="text-gray-500">Customer Name</span>
                                        <span className="font-bold text-gray-900">{delivery.order?.customer?.name || 'N/A'}</span>
                                    </div>
                                    <div className="flex justify-between border-b border-gray-200 pb-2">
                                        <span className="text-gray-500">Order Ref.</span>
                                        <span className="font-mono font-medium text-gray-900">{delivery.order?.order_number}</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span className="text-gray-500">Contact Person</span>
                                        <span className="font-medium text-gray-900">{delivery.contact_person || '-'}</span>
                                    </div>
                                </div>
                            </div>

                            {/* Shipping Address */}
                            <div className="bg-gray-50/50 rounded-xl p-5 border border-gray-100">
                                <h3 className="text-xs font-bold uppercase text-gray-400 mb-4 flex items-center gap-2">
                                    <MapPin className="w-4 h-4" /> Ship To
                                </h3>
                                <div className="text-sm">
                                    <p className="text-gray-600 leading-relaxed mb-3 min-h-[40px]">
                                        {delivery.shipping_address || delivery.order?.customer?.address || <span className="italic text-gray-400">No address provided</span>}
                                    </p>
                                    {(delivery.contact_phone || delivery.order?.customer?.phone) && (
                                        <div className="inline-flex items-center gap-2 bg-white px-3 py-1 rounded-full border border-gray-200 text-xs font-medium text-gray-600">
                                            <Phone className="w-3 h-3" /> {delivery.contact_phone || delivery.order?.customer?.phone}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* 3. Items Table (✅ Fixed map error here) */}
                        <div className="mb-10">
                            <div className="rounded-lg border border-gray-200 overflow-hidden">
                                <Table>
                                    <TableHeader className="bg-gray-50 text-gray-500 font-semibold uppercase text-xs">
                                        <TableRow>
                                            <TableHead className="py-3 pl-4 text-left w-12">#</TableHead>
                                            <TableHead className="py-3 text-left">Product Details</TableHead>
                                            <TableHead className="py-3 text-center w-32">Ordered</TableHead>
                                            <TableHead className="py-3 text-right w-32 pr-6 bg-orange-50/50 text-orange-700 border-l">Shipped</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody className="divide-y divide-gray-100">
                                        {safeItems.length > 0 ? (
                                            safeItems.map((item, index) => (
                                                <TableRow key={index} className="group page-break hover:bg-gray-50/50 transition-colors">
                                                    <TableCell className="py-4 pl-4 align-top text-gray-400 font-medium">{index + 1}</TableCell>
                                                    <TableCell className="py-4 align-top">
                                                        <div className="flex gap-4">
                                                            {item.image_url ? (
                                                                <div className="w-12 h-12 rounded bg-gray-50 border border-gray-100 flex-shrink-0 overflow-hidden print-hidden">
                                                                    <ImageViewer images={[item.image_url]} alt={item.product_name} className="w-full h-full object-cover" />
                                                                </div>
                                                            ) : (
                                                                <div className="w-12 h-12 rounded bg-gray-50 border border-gray-100 flex-shrink-0 flex items-center justify-center text-gray-300 print-hidden">
                                                                    <Package className="w-5 h-5" />
                                                                </div>
                                                            )}
                                                            <div>
                                                                <div className="font-bold text-gray-900 text-base">{item.product_name || item.product_id}</div>
                                                                <div className="text-gray-500 text-xs mt-1">
                                                                    {item.description || '-'}
                                                                </div>
                                                                <div className="text-[10px] font-mono text-gray-400 mt-1">SKU: {item.barcode || item.product_id}</div>
                                                            </div>
                                                        </div>
                                                    </TableCell>
                                                    <TableCell className="py-4 align-top text-center text-gray-400 font-medium">
                                                        {item.quantity_ordered}
                                                    </TableCell>
                                                    <TableCell className="py-4 align-top text-right pr-6 font-bold text-lg text-orange-700 bg-orange-50/10 border-l border-gray-100">
                                                        {item.qty_shipped} <span className="text-xs font-normal text-gray-500">{item.uom || 'Units'}</span>
                                                    </TableCell>
                                                </TableRow>
                                            ))
                                        ) : (
                                            <TableRow>
                                                <TableCell colSpan={4} className="h-24 text-center text-gray-400">
                                                    No items found in this shipment.
                                                </TableCell>
                                            </TableRow>
                                        )}
                                    </TableBody>
                                </Table>
                            </div>
                        </div>

                        {/* 4. Signature Section */}
                        <div className="mt-auto page-break">
                            <div className="grid grid-cols-2 gap-12 pt-8 border-t-2 border-gray-100">
                                <div className="text-center group">
                                    <p className="font-bold text-xs uppercase text-gray-400 mb-12 tracking-widest group-hover:text-gray-600 transition-colors">Sent By (Driver/Carrier)</p>
                                    <div className="border-b border-gray-300 mx-8 mb-2"></div>
                                    <p className="text-[10px] text-gray-400">Date: ____ / ____ / ________</p>
                                </div>
                                <div className="text-center group">
                                    <p className="font-bold text-xs uppercase text-gray-400 mb-12 tracking-widest group-hover:text-gray-600 transition-colors">Received By (Customer)</p>
                                    <div className="border-b border-gray-300 mx-8 mb-2"></div>
                                    <p className="text-[10px] text-gray-400">Date: ____ / ____ / ________</p>
                                </div>
                            </div>
                        </div>

                    </div>
                    <div className="text-center mt-6 text-xs text-gray-400 no-print">Logistics Management System &bull; TMR EcoSystem</div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
