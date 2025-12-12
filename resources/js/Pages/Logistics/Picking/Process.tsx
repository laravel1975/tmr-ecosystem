import React, { useState, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

// UI Components
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Separator } from "@/Components/ui/separator";
import { CheckCircle2, MapPin, Package, ArrowLeft, AlertCircle, Save, Printer, ScanBarcode, Zap } from 'lucide-react';
import ImageViewer from '@/Components/ImageViewer';
import { cn } from '@/lib/utils';

import { useBarcodeScanner } from '@/Hooks/useBarcodeScanner';

// Types (Updated for Smart Reservation)
interface PickingSuggestion {
    location_uuid: string;
    location_code: string;
    quantity: number;
}

interface PickingItem {
    id: number;
    product_id: string;
    product_name: string;
    barcode: string;
    qty_ordered: number;
    qty_picked: number;
    is_completed: boolean;
    image_url?: string;
    picking_suggestions: PickingSuggestion[]; // แผนการหยิบจาก Smart Reserve
}

interface PickingSlip {
    id: string;
    picking_number: string;
    order_number: string;
    customer_name: string;
    status: string;
}

interface Props extends PageProps {
    pickingSlip: PickingSlip;
    items: PickingItem[];
}

export default function Process({ auth, pickingSlip, items }: Props) {

    const [pickedData, setPickedData] = useState<Record<number, number>>(() => {
        const initialData: Record<number, number> = {};
        items.forEach(item => {
            initialData[item.id] = item.qty_picked;
        });
        return initialData;
    });

    const [lastScanned, setLastScanned] = useState<{ name: string, status: 'success' | 'error' } | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // --- Helper: คำนวณยอดที่หยิบได้จริง (Available to Pick) จาก Smart Reservation ---
    const getMaxPickable = (item: PickingItem) => {
        if (!item.picking_suggestions || item.picking_suggestions.length === 0) return 0;

        // รวมยอดจากทุก Location ที่ถูกจองไว้ให้
        return item.picking_suggestions.reduce((sum, s) => sum + s.quantity, 0);
    };

    // --- 📡 BARCODE SCANNER LOGIC ---
    const handleScan = useCallback((code: string) => {
        const targetItem = items.find(item =>
            item.barcode?.trim().toLowerCase() === code.trim().toLowerCase() ||
            item.product_id?.trim().toLowerCase() === code.trim().toLowerCase()
        );

        if (targetItem) {
            const currentQty = pickedData[targetItem.id] || 0;
            const maxPickable = getMaxPickable(targetItem);

            if (currentQty >= maxPickable) {
                setLastScanned({ name: `Max Reached: ${targetItem.product_name}`, status: 'error' });
            } else {
                setPickedData(prev => ({
                    ...prev,
                    [targetItem.id]: (prev[targetItem.id] || 0) + 1
                }));
                setLastScanned({ name: `Picked: ${targetItem.product_name}`, status: 'success' });
            }
        } else {
            setLastScanned({ name: `Not found: ${code}`, status: 'error' });
        }

        setTimeout(() => setLastScanned(null), 3000);

    }, [items, pickedData]);

    useBarcodeScanner(handleScan);

    // Update Local State (Manual Input)
    const handleQtyChange = (itemId: number, val: string, maxLimit: number) => {
        const num = parseFloat(val);
        // ✅ Limit value to maxPickable (Smart Reserve Limit)
        const safeNum = isNaN(num) ? 0 : Math.min(num, maxLimit);

        setPickedData(prev => ({
            ...prev,
            [itemId]: safeNum
        }));
    };

    // Auto Fill
    const handleAutoFill = (item: PickingItem, maxLimit: number) => {
        setPickedData(prev => ({
            ...prev,
            [item.id]: maxLimit
        }));
    };

    const handleSubmit = () => {
        const isAllComplete = items.every(item => {
             const max = getMaxPickable(item);
             return (pickedData[item.id] || 0) >= max;
        });

        const confirmMsg = isAllComplete
            ? 'All reserved items picked. Confirm to finish?'
            : 'Some items are missing/incomplete. Confirm partial picking?';

        if (!confirm(confirmMsg)) return;

        setIsSubmitting(true);
        const payload = {
            items: Object.entries(pickedData).map(([id, qty]) => ({
                id: parseInt(id),
                qty_picked: qty
            })),
            create_backorder: true
        };

        router.post(route('logistics.picking.confirm', pickingSlip.id), payload, {
            onFinish: () => setIsSubmitting(false)
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href={route('logistics.picking.index')}>
                            <Button variant="ghost" size="icon"><ArrowLeft className="h-4 w-4" /></Button>
                        </Link>
                        <div>
                            <h2 className="font-semibold text-xl text-gray-800 leading-tight flex items-center gap-2">
                                Picking : {pickingSlip.picking_number}
                                <Badge variant="outline" className="ml-2 animate-pulse bg-green-50 text-green-700 border-green-200">
                                    <ScanBarcode className="w-3 h-3 mr-1" /> Scanner Ready
                                </Badge>
                            </h2>
                            <p className="text-sm text-gray-500">Order: {pickingSlip.order_number} | Customer: {pickingSlip.customer_name}</p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="outline" asChild>
                            <a href={route('logistics.picking.pdf', pickingSlip.id)} target="_blank">
                                <Printer className="mr-2 h-4 w-4" /> Print Slip
                            </a>
                        </Button>
                        {pickingSlip.status !== 'done' && (
                            <Button
                                className="bg-green-600 hover:bg-green-700"
                                onClick={handleSubmit}
                                disabled={isSubmitting}
                            >
                                <Save className="mr-2 h-4 w-4" /> Confirm Picking
                            </Button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`Pick ${pickingSlip.picking_number}`} />

            {/* Toast Feedback */}
            {lastScanned && (
                <div className={cn(
                    "fixed top-20 right-4 z-50 px-6 py-4 rounded-lg shadow-lg border flex items-center gap-3 transition-all duration-300 transform translate-y-0 opacity-100",
                    lastScanned.status === 'success' ? "bg-green-600 text-white border-green-700" : "bg-red-600 text-white border-red-700"
                )}>
                    {lastScanned.status === 'success' ? <CheckCircle2 className="w-6 h-6" /> : <AlertCircle className="w-6 h-6" />}
                    <div>
                        <p className="font-bold text-lg">{lastScanned.status === 'success' ? 'Scanned!' : 'Error!'}</p>
                        <p className="text-sm opacity-90">{lastScanned.name}</p>
                    </div>
                </div>
            )}

            <div className="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">

                <div className="space-y-6">
                    {items.map((item) => {
                        const currentPicked = pickedData[item.id] || 0;

                        // ✅ 1. คำนวณยอดที่หยิบได้จริง (ตาม Stock ที่จองไว้)
                        const maxPickable = getMaxPickable(item);
                        const isStockEmpty = maxPickable === 0;

                        const isFullyPicked = currentPicked >= maxPickable && maxPickable > 0;
                        const isJustScanned = lastScanned?.name.includes(item.product_name) && lastScanned?.status === 'success';

                        return (
                            <Card
                                key={item.id}
                                className={cn(
                                    "border-l-4 shadow-sm transition-all duration-300",
                                    isFullyPicked ? "border-l-green-500 bg-green-50/10" : "border-l-orange-400",
                                    isJustScanned ? "ring-2 ring-green-500 scale-[1.02]" : "",
                                    isStockEmpty && "opacity-75 bg-gray-50 border-l-gray-300"
                                )}
                            >
                                <CardContent className="p-6">
                                    <div className="flex flex-col md:flex-row gap-6">

                                        <div className="flex-shrink-0">
                                            {item.image_url ? (
                                                <ImageViewer
                                                    images={[item.image_url]}
                                                    alt={item.product_name}
                                                    className="w-24 h-24 rounded-lg border object-contain bg-white"
                                                />
                                            ) : (
                                                <div className="w-24 h-24 rounded-lg border bg-gray-100 flex items-center justify-center text-gray-300">
                                                    <Package className="w-10 h-10" />
                                                </div>
                                            )}
                                        </div>

                                        <div className="flex-1 space-y-2">
                                            <div className="flex justify-between items-start">
                                                <div>
                                                    <h3 className="text-lg font-bold text-gray-800 flex items-center gap-2">
                                                        {item.product_name}
                                                        {isFullyPicked && <CheckCircle2 className="w-5 h-5 text-green-600" />}
                                                    </h3>
                                                    <p className="text-sm font-mono text-blue-600">{item.product_id}</p>
                                                    <div className="flex items-center gap-2 mt-1">
                                                        <ScanBarcode className="w-4 h-4 text-gray-400" />
                                                        <span className="text-xs font-mono bg-gray-100 px-2 py-0.5 rounded text-gray-600 tracking-wider">
                                                            {item.barcode || 'NO BARCODE'}
                                                        </span>
                                                    </div>
                                                </div>
                                                <div className="text-right">
                                                    <span className="block text-xs text-gray-500 uppercase font-bold">Reserved</span>
                                                    <span className={cn("text-2xl font-bold", isStockEmpty ? "text-red-500" : "text-gray-900")}>
                                                        {Number(maxPickable).toLocaleString()}
                                                    </span>
                                                </div>
                                            </div>

                                            <Separator className="my-3" />

                                            <div className="bg-slate-50 rounded-md p-3 border border-slate-200">
                                                <div className="flex items-center gap-2 mb-2">
                                                    <MapPin className="w-4 h-4 text-blue-500" />
                                                    <span className="text-sm font-bold text-gray-700">Pick From (Reserved)</span>
                                                </div>

                                                {item.picking_suggestions && item.picking_suggestions.length > 0 ? (
                                                    <div className="flex flex-wrap gap-2">
                                                        {item.picking_suggestions.map((sug, idx) => (
                                                            <div key={idx} className="flex items-center gap-2 px-3 py-1.5 rounded-full border text-sm font-mono bg-white border-blue-200 text-blue-800 shadow-sm">
                                                                <span className="font-bold">{sug.location_code}</span>
                                                                <Badge variant="secondary" className="h-5 px-1.5 bg-blue-100 text-blue-800 hover:bg-blue-200">
                                                                    x {sug.quantity}
                                                                </Badge>
                                                            </div>
                                                        ))}
                                                    </div>
                                                ) : (
                                                    <p className="text-sm text-red-500 flex items-center italic">
                                                        <AlertCircle className="w-4 h-4 mr-1" /> No stock reserved
                                                    </p>
                                                )}
                                            </div>
                                        </div>

                                        {/* Picking Input Area */}
                                        <div className="flex flex-col justify-center items-end gap-3 min-w-[150px] border-l pl-6 md:pl-0 md:border-l-0">
                                            <div className="text-right w-full">
                                                <label className="text-xs font-bold text-gray-500 mb-1 block">ACTUAL PICKED</label>
                                                <div className="flex items-center gap-2 relative">
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        max={maxPickable} // ✅ 2. จำกัด Max ที่ยอดที่จองไว้
                                                        value={currentPicked}
                                                        onChange={(e) => handleQtyChange(item.id, e.target.value, maxPickable)}
                                                        className={cn(
                                                            "text-right font-bold text-2xl h-14 w-full",
                                                            isFullyPicked ? "text-green-600 border-green-500 bg-green-50 ring-2 ring-green-200" : "text-gray-800",
                                                            isStockEmpty ? "bg-gray-100 text-gray-400 cursor-not-allowed" : ""
                                                        )}
                                                        disabled={pickingSlip.status === 'done' || isStockEmpty}
                                                    />
                                                    {isJustScanned && (
                                                        <Zap className="absolute -right-8 w-6 h-6 text-yellow-500 animate-ping" />
                                                    )}
                                                </div>
                                            </div>

                                            {pickingSlip.status !== 'done' && !isFullyPicked && !isStockEmpty && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className="w-full text-blue-600 hover:text-blue-700 hover:bg-blue-50"
                                                    onClick={() => handleAutoFill(item, maxPickable)}
                                                >
                                                    <CheckCircle2 className="w-4 h-4 mr-1" />
                                                    Pick All
                                                </Button>
                                            )}
                                        </div>

                                    </div>
                                </CardContent>
                            </Card>
                        );
                    })}
                </div>

            </div>
        </AuthenticatedLayout>
    );
}
