import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    ArrowLeft, Printer, Play, Phone, MapPin,
    FileText, User, Calendar, Package, Box, Image as ImageIcon, CheckSquare, Check
} from "lucide-react";
import { format } from 'date-fns';
import InventoryNavigationMenu from '@/Pages/Inventory/Partials/InventoryNavigationMenu';
import ImageViewer from '@/Components/ImageViewer';
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";

// --- Types (Updated for Smart Reservation) ---
interface PickingSuggestion {
    location_uuid: string;
    location_code: string;
    quantity: number;
}

interface PickingItem {
    id: number;
    product_id: string;
    product_name: string;
    description?: string;
    barcode?: string;
    // location?: string; // Legacy field (optional)
    qty_ordered: number;
    qty_picked: number;
    is_completed: boolean;
    image_url?: string;
    picking_suggestions?: PickingSuggestion[]; // ✅ เพิ่มส่วนนี้
    location_display?: string; // ✅ เพิ่มส่วนนี้
}

interface PickingSlip {
    id: string;
    picking_number: string;
    status: 'pending' | 'in_progress' | 'completed' | 'cancelled' | 'assigned' | 'done' | 'partial';
    created_at: string;
    warehouse_id: string;
    picker_name?: string;
    order?: {
        order_number: string;
        customer?: {
            name: string;
            address?: string;
            phone?: string;
        }
    };
    items: PickingItem[];
}

interface Props {
    auth: any;
    pickingSlip: PickingSlip;
}

export default function PickingShow({ auth, pickingSlip }: Props) {

    // Helper: สถานะและสี
    const getStatusBadge = (status: string) => {
        const styles: Record<string, string> = {
            pending: "bg-yellow-100 text-yellow-700 border-yellow-200 hover:bg-yellow-100",
            assigned: "bg-blue-100 text-blue-700 border-blue-200 hover:bg-blue-100",
            in_progress: "bg-indigo-100 text-indigo-700 border-indigo-200 hover:bg-indigo-100",
            done: "bg-emerald-100 text-emerald-700 border-emerald-200 hover:bg-emerald-100",
            completed: "bg-emerald-100 text-emerald-700 border-emerald-200 hover:bg-emerald-100",
            cancelled: "bg-red-100 text-red-700 border-red-200 hover:bg-red-100",
            partial: "bg-orange-100 text-orange-700 border-orange-200 hover:bg-orange-100",
        };
        return (
            <Badge variant="outline" className={`px-3 py-1 rounded-full font-semibold print:hidden ${styles[status] || "bg-gray-100 text-gray-700"}`}>
                {status.toUpperCase().replace('_', ' ')}
            </Badge>
        );
    };

    // Logic: เริ่มงาน (ถ้าสถานะยังไม่เริ่ม)
    const handleStartPicking = () => {
        if (pickingSlip.status === 'pending' || pickingSlip.status === 'partial') {
            router.post(route('logistics.picking.assign', pickingSlip.id), {}, {
                onSuccess: () => router.visit(route('logistics.picking.process', pickingSlip.id))
            });
        } else {
            router.visit(route('logistics.picking.process', pickingSlip.id));
        }
    };

    const canStartPicking = ['pending', 'assigned', 'in_progress', 'partial'].includes(pickingSlip.status);

    // Helper: Barcode Font (ใช้ Text แทนถ้าไม่ได้ลง Font ไว้)
    const encodeCode128 = (text: string) => text;

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<div className="print:hidden"><InventoryNavigationMenu /></div>}>
            <Head>
                <title>{`Picking ${pickingSlip.picking_number}`}</title>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link href="https://fonts.googleapis.com/css2?family=Libre+Barcode+128&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
                <style>{`
                    @media print {
                        @page { margin: 0; size: A4; }
                        body { background-color: white !important; -webkit-print-color-adjust: exact; }
                        nav, header, .no-print { display: none !important; }
                        .print-container {
                            width: 100%;
                            margin: 0;
                            padding: 1.5cm;
                            box-shadow: none !important;
                            border: none !important;
                        }
                        .page-break { page-break-inside: avoid; }
                        .print-hidden { display: none; }
                    }
                `}</style>
            </Head>

            {/* --- Background Wrapper --- */}
            <div className="min-h-screen bg-gray-100/80 pb-12 pt-6 print:bg-white print:pt-0 print:pb-0">
                <div className="max-w-5xl mx-auto px-4 sm:px-6">

                    {/* --- Action Toolbar (Floating) --- */}
                    <div className="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 no-print">
                        <div className="flex items-center gap-3">
                            <Button variant="outline" size="icon" className="h-9 w-9 rounded-full bg-white shadow-sm hover:bg-gray-50 border-gray-300" asChild>
                                <Link href={route('logistics.picking.index')}>
                                    <ArrowLeft className="h-4 w-4 text-gray-600" />
                                </Link>
                            </Button>
                            <div className="flex flex-col">
                                <div className="flex items-center gap-2">
                                    <h1 className="text-xl font-bold text-gray-900">{pickingSlip.picking_number}</h1>
                                    {getStatusBadge(pickingSlip.status)}
                                </div>
                                <span className="text-xs text-gray-500 flex items-center gap-1">
                                    <FileText className="w-3 h-3" /> Ref: {pickingSlip.order?.order_number}
                                </span>
                            </div>
                        </div>

                        <div className="flex items-center gap-2 w-full sm:w-auto">
                            <a href={route('logistics.picking.pdf', pickingSlip.id)} target="_blank">
                                <Button variant="outline" className="gap-2">
                                    <Printer className="h-4 w-4" /> Print PDF
                                </Button>
                            </a>

                            {canStartPicking && (
                                <Button onClick={handleStartPicking} className="flex-1 sm:flex-none gap-2 bg-indigo-600 hover:bg-indigo-700 shadow-md shadow-indigo-200 text-white">
                                    <Play className="w-4 h-4 fill-current" />
                                    {pickingSlip.status === 'pending' || pickingSlip.status === 'partial' ? 'Accept & Start' : 'Continue Picking'}
                                </Button>
                            )}
                        </div>
                    </div>

                    {/* --- Digital Document Sheet --- */}
                    <div className="print-container bg-white rounded-xl shadow-xl border border-gray-200 p-8 md:p-12 relative overflow-hidden">

                        {/* Decorative Top Bar (Screen Only) */}
                        <div className="absolute top-0 left-0 right-0 h-2 bg-indigo-600 no-print" />

                        {/* 1. Header Section */}
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
                                    <div className="flex items-center gap-2"><Box className="w-4 h-4 text-gray-400" /> <span className="font-semibold text-gray-900">Warehouse:</span></div>
                                    <div>{pickingSlip.warehouse_id || 'Main-WH'}</div>

                                    <div className="flex items-center gap-2"><Calendar className="w-4 h-4 text-gray-400" /> <span className="font-semibold text-gray-900">Date:</span></div>
                                    <div>{format(new Date(), 'dd MMM yyyy')}</div>
                                </div>
                            </div>

                            <div className="text-right flex flex-col items-end">
                                <div className="bg-gray-50 px-4 py-2 rounded-lg border border-gray-100 mb-2">
                                    <span className="text-xs font-bold text-gray-400 uppercase tracking-wider">Document Type</span>
                                    <div className="text-lg font-black text-gray-900">PICKING SLIP</div>
                                </div>
                                <div className="relative mt-2">
                                    <div className="text-6xl text-gray-900 select-none transform scale-y-110 scale-x-90" style={{ fontFamily: '"Libre Barcode 128", cursive' }}>
                                        {encodeCode128(pickingSlip.picking_number)}
                                    </div>
                                    <div className="text-center font-mono font-bold text-sm tracking-widest text-gray-500 -mt-2">
                                        {pickingSlip.picking_number}
                                    </div>
                                </div>
                            </div>
                        </div>

                        {/* 2. Details Grid */}
                        <div className="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                            {/* Order Info */}
                            <div className="bg-gray-50/50 rounded-xl p-5 border border-gray-100">
                                <h3 className="text-xs font-bold uppercase text-gray-400 mb-4 flex items-center gap-2">
                                    <Package className="w-4 h-4" /> Order Reference
                                </h3>
                                <div className="space-y-3 text-sm">
                                    <div className="flex justify-between border-b border-gray-200 pb-2">
                                        <span className="text-gray-500">Order Number</span>
                                        <span className="font-mono font-bold text-base text-gray-900">{pickingSlip.order?.order_number}</span>
                                    </div>
                                    <div className="flex justify-between border-b border-gray-200 pb-2">
                                        <span className="text-gray-500">Created Date</span>
                                        <span className="font-medium">{format(new Date(pickingSlip.created_at), 'dd MMM yyyy HH:mm')}</span>
                                    </div>
                                    <div className="flex justify-between items-center">
                                        <span className="text-gray-500">Assigned Picker</span>
                                        <span className="font-medium text-gray-900 flex items-center gap-2">
                                            {pickingSlip.picker_name ? (
                                                <><User className="w-3 h-3 text-indigo-600" /> {pickingSlip.picker_name}</>
                                            ) : (
                                                <span className="text-gray-400 italic">Unassigned</span>
                                            )}
                                        </span>
                                    </div>
                                </div>
                            </div>

                            {/* Customer Info */}
                            <div className="bg-gray-50/50 rounded-xl p-5 border border-gray-100">
                                <h3 className="text-xs font-bold uppercase text-gray-400 mb-4 flex items-center gap-2">
                                    <MapPin className="w-4 h-4" /> Delivery Destination
                                </h3>
                                <div className="text-sm">
                                    <p className="font-bold text-gray-900 text-base mb-1">{pickingSlip.order?.customer?.name || 'N/A'}</p>
                                    <p className="text-gray-600 leading-relaxed mb-3 min-h-[40px]">
                                        {pickingSlip.order?.customer?.address || <span className="italic text-gray-400">No address provided</span>}
                                    </p>
                                    {pickingSlip.order?.customer?.phone && (
                                        <div className="inline-flex items-center gap-2 bg-white px-3 py-1 rounded-full border border-gray-200 text-xs font-medium text-gray-600">
                                            <Phone className="w-3 h-3" /> {pickingSlip.order.customer.phone}
                                        </div>
                                    )}
                                </div>
                            </div>
                        </div>

                        {/* 3. Items Table */}
                        <div className="mb-10">
                            <div className="rounded-lg border border-gray-200 overflow-hidden">
                                <table className="w-full text-sm">
                                    <thead className="bg-gray-50 text-gray-500 font-semibold uppercase text-xs">
                                        <tr>
                                            <th className="py-3 pl-4 text-left w-12">#</th>
                                            <th className="py-3 text-left">Product Details</th>
                                            <th className="py-3 text-left w-32">SKU / Barcode</th>
                                            <th className="py-3 text-center w-32">Loc. (Reserved)</th>
                                            <th className="py-3 text-right w-24 pr-4">Qty</th>
                                            <th className="py-3 text-center w-16 bg-gray-100 border-l">Check</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-gray-100">
                                        {pickingSlip.items.map((item, index) => {
                                            const isPicked = item.qty_picked >= item.qty_ordered;

                                            return (
                                                <tr key={index} className="group page-break hover:bg-gray-50/50 transition-colors">
                                                    <td className="py-4 pl-4 align-top text-gray-400 font-medium">{index + 1}</td>

                                                    {/* Product */}
                                                    <td className="py-4 align-top">
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
                                                                <div className="text-gray-500 text-xs mt-1 line-clamp-2 print:line-clamp-none">
                                                                    {item.description || 'No description available'}
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </td>

                                                    {/* SKU */}
                                                    <td className="py-4 align-top">
                                                        <div className="font-mono text-xs text-gray-600 bg-gray-100 px-2 py-1 rounded w-fit print:bg-transparent print:p-0 print:text-black">
                                                            {item.barcode || item.product_id}
                                                        </div>
                                                    </td>

                                                    {/* ✅ Location (Smart Reservation Support) */}
                                                    <td className="py-4 align-top text-center font-mono text-gray-600">
                                                        {item.picking_suggestions && item.picking_suggestions.length > 0 ? (
                                                            <div className="flex flex-col gap-1 items-center">
                                                                {item.picking_suggestions.map((sug, i) => (
                                                                    <div key={i} className="flex items-center gap-1 text-xs">
                                                                         <span className="font-bold">{sug.location_code}</span>
                                                                         <span className="text-gray-400 text-[10px]">x{sug.quantity}</span>
                                                                    </div>
                                                                ))}
                                                            </div>
                                                        ) : (
                                                            <span className="text-gray-400 text-xs italic">{item.location_display || 'Wait'}</span>
                                                        )}
                                                    </td>

                                                    {/* Qty */}
                                                    <td className="py-4 align-top text-right pr-4">
                                                        <span className="text-xl font-bold text-gray-900">{item.qty_ordered}</span>
                                                        <span className="text-xs text-gray-400 block">Units</span>
                                                    </td>

                                                    {/* Checkbox Status */}
                                                    <td className="py-4 align-top text-center border-l border-gray-100">
                                                        {isPicked ? (
                                                            <div className="w-6 h-6 border-2 border-green-600 bg-green-600 text-white rounded-sm mx-auto mt-1 flex items-center justify-center print:border-black print:bg-transparent print:text-black">
                                                                <Check className="w-4 h-4 font-bold" strokeWidth={4} />
                                                            </div>
                                                        ) : item.qty_picked > 0 ? (
                                                             <div className="w-6 h-6 border-2 border-orange-400 bg-orange-100 text-orange-600 rounded-sm mx-auto mt-1 flex items-center justify-center text-xs font-bold">
                                                                P
                                                             </div>
                                                        ) : (
                                                            <div className="w-6 h-6 border-2 border-gray-300 rounded-sm mx-auto mt-1 print:border-black"></div>
                                                        )}
                                                    </td>
                                                </tr>
                                            );
                                        })}
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        {/* 4. Signature Section */}
                        <div className="mt-auto page-break">
                            <div className="flex justify-end mb-12">
                                <div className="w-48 bg-gray-50 rounded-lg p-4 border border-gray-100 flex justify-between items-center">
                                    <span className="text-sm font-semibold text-gray-500 uppercase">Total Items</span>
                                    <span className="text-2xl font-bold text-gray-900">{pickingSlip.items.length}</span>
                                </div>
                            </div>

                            <div className="grid grid-cols-3 gap-12 pt-8 border-t-2 border-gray-100">
                                {['Picked By', 'Checked By', 'Received By'].map((role) => (
                                    <div key={role} className="text-center group">
                                        <p className="text-xs font-bold uppercase text-gray-400 mb-12 tracking-widest group-hover:text-gray-600 transition-colors">{role}</p>
                                        <div className="border-b border-gray-300 mx-4 mb-2"></div>
                                        <p className="text-[10px] text-gray-400">Date: ____ / ____ / ________</p>
                                    </div>
                                ))}
                            </div>
                        </div>

                    </div>

                    {/* Footer Credit */}
                    <div className="text-center mt-6 text-xs text-gray-400 no-print">
                        Logistics Management System &bull; TMR EcoSystem
                    </div>

                </div>
            </div>
        </AuthenticatedLayout>
    );
}
