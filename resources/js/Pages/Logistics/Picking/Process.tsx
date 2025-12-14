import React, { useState, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

// UI Components
import { Card, CardContent } from '@/Components/ui/card';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Badge } from '@/Components/ui/badge';
import { Separator } from "@/Components/ui/separator";
import { CheckCircle2, MapPin, Package, ArrowLeft, AlertCircle, Save, Printer, ScanBarcode, Zap } from 'lucide-react';
import ImageViewer from '@/Components/ImageViewer';
import { cn } from '@/lib/utils';

import { useBarcodeScanner } from '@/Hooks/useBarcodeScanner';

// Types
interface PickingSuggestion {
    location_uuid: string;
    location_code: string;
    quantity: number | string; // Backend อาจส่ง string format มา
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
    picking_suggestions: PickingSuggestion[];
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

    // Initialize state with previously picked quantities
    const [pickedData, setPickedData] = useState<Record<number, number>>(() => {
        const initialData: Record<number, number> = {};
        items.forEach(item => {
            initialData[item.id] = item.qty_picked;
        });
        return initialData;
    });

    const [lastScanned, setLastScanned] = useState<{ name: string, status: 'success' | 'error' } | null>(null);
    const [isSubmitting, setIsSubmitting] = useState(false);

    // --- Helper: คำนวณยอดที่ต้องหยิบ (Picking Target) ---
    // ยึดตาม Picking Suggestion ที่ Backend ส่งมา (ซึ่งผ่านการ Cap ยอด min() มาแล้ว)
    const getTargetQty = (item: PickingItem) => {
        if (!item.picking_suggestions || item.picking_suggestions.length === 0) return 0;

        // แปลงเป็น number ก่อนบวก (กันเหนียวเพราะบางที backend ส่ง string '20.00')
        return item.picking_suggestions.reduce((sum, s) => sum + Number(s.quantity), 0);
    };

    // --- 📡 BARCODE SCANNER LOGIC ---
    const handleScan = useCallback((code: string) => {
        const targetItem = items.find(item =>
            item.barcode?.trim().toLowerCase() === code.trim().toLowerCase() ||
            item.product_id?.trim().toLowerCase() === code.trim().toLowerCase()
        );

        if (targetItem) {
            const currentQty = pickedData[targetItem.id] || 0;
            const targetQty = getTargetQty(targetItem);

            if (currentQty >= targetQty) {
                setLastScanned({ name: `Already Complete: ${targetItem.product_name}`, status: 'error' });
            } else {
                setPickedData(prev => ({
                    ...prev,
                    [targetItem.id]: (prev[targetItem.id] || 0) + 1
                }));
                setLastScanned({ name: `Scanned: ${targetItem.product_name}`, status: 'success' });
            }
        } else {
            setLastScanned({ name: `Product not found: ${code}`, status: 'error' });
        }

        setTimeout(() => setLastScanned(null), 2500);

    }, [items, pickedData]);

    useBarcodeScanner(handleScan);

    // Manual Input Handler
    const handleQtyChange = (itemId: number, val: string, maxLimit: number) => {
        const num = parseFloat(val);
        // Allow user to clear input (NaN -> 0), but don't exceed maxLimit
        const safeNum = isNaN(num) ? 0 : Math.min(num, maxLimit);

        setPickedData(prev => ({
            ...prev,
            [itemId]: safeNum
        }));
    };

    // Auto Fill Button
    const handleAutoFill = (item: PickingItem, maxLimit: number) => {
        setPickedData(prev => ({
            ...prev,
            [item.id]: maxLimit
        }));
    };

    const handleSubmit = () => {
        // Validation: Check if everything matches the reservation target
        const incompleteItems = items.filter(item => {
             const target = getTargetQty(item);
             const picked = pickedData[item.id] || 0;
             return picked < target;
        });

        let confirmMsg = 'All items picked according to reservation. Confirm?';

        if (incompleteItems.length > 0) {
            confirmMsg = `⚠️ You have ${incompleteItems.length} incomplete items.\n\nUnpicked items will be released back to stock or backordered.\n\nProceed?`;
        }

        if (!confirm(confirmMsg)) return;

        setIsSubmitting(true);

        const payload = {
            items: Object.entries(pickedData).map(([id, qty]) => ({
                id: parseInt(id),
                qty_picked: qty
            })),
            create_backorder: true // Default to true (Business Logic decision)
        };

        router.post(route('logistics.picking.confirm', pickingSlip.id), payload, {
            onFinish: () => setIsSubmitting(false)
        });
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                    <div className="flex items-center gap-4">
                        <Link href={route('logistics.picking.index')}>
                            <Button variant="ghost" size="icon"><ArrowLeft className="h-5 w-5" /></Button>
                        </Link>
                        <div>
                            <h2 className="font-bold text-2xl text-gray-800 leading-tight flex items-center gap-2">
                                {pickingSlip.picking_number}
                                <Badge className="bg-blue-100 text-blue-700 hover:bg-blue-200 border-0">
                                    <ScanBarcode className="w-3 h-3 mr-1" /> Scan Ready
                                </Badge>
                            </h2>
                            <p className="text-sm text-gray-500 mt-1">
                                Order: <span className="font-mono font-medium text-gray-700">{pickingSlip.order_number}</span> •
                                {pickingSlip.customer_name}
                            </p>
                        </div>
                    </div>

                    <div className="flex gap-2 w-full sm:w-auto">
                        <Button variant="outline" className="flex-1 sm:flex-none" asChild>
                            <a href={route('logistics.picking.pdf', pickingSlip.id)} target="_blank">
                                <Printer className="mr-2 h-4 w-4" /> Print
                            </a>
                        </Button>
                        {pickingSlip.status !== 'done' && (
                            <Button
                                className="flex-1 sm:flex-none bg-green-600 hover:bg-green-700 text-white shadow-md hover:shadow-lg transition-all"
                                onClick={handleSubmit}
                                disabled={isSubmitting}
                            >
                                <Save className="mr-2 h-4 w-4" /> Confirm
                            </Button>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`Pick ${pickingSlip.picking_number}`} />

            {/* Notification Toast */}
            {lastScanned && (
                <div className={cn(
                    "fixed top-24 right-4 z-50 px-6 py-4 rounded-xl shadow-2xl border flex items-center gap-4 transition-all duration-500 animate-in slide-in-from-right-10",
                    lastScanned.status === 'success' ? "bg-green-600 text-white border-green-700" : "bg-red-600 text-white border-red-700"
                )}>
                    <div className="p-2 bg-white/20 rounded-full">
                        {lastScanned.status === 'success' ? <CheckCircle2 className="w-6 h-6" /> : <AlertCircle className="w-6 h-6" />}
                    </div>
                    <div>
                        <p className="font-bold text-lg">{lastScanned.status === 'success' ? 'Success' : 'Error'}</p>
                        <p className="text-sm text-white/90 font-medium">{lastScanned.name}</p>
                    </div>
                </div>
            )}

            <div className="py-8 max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
                <div className="grid gap-6">
                    {items.map((item) => {
                        const currentPicked = pickedData[item.id] || 0;

                        // Calculated Target from Backend Suggestions
                        const targetQty = getTargetQty(item);

                        const isStockAvailable = targetQty > 0;
                        const isCompleted = currentPicked >= targetQty && isStockAvailable;
                        const isJustScanned = lastScanned?.name.includes(item.product_name) && lastScanned?.status === 'success';

                        // Calculate Progress Percentage
                        const progress = isStockAvailable ? Math.min((currentPicked / targetQty) * 100, 100) : 0;

                        return (
                            <Card
                                key={item.id}
                                className={cn(
                                    "overflow-hidden transition-all duration-300 border-l-4",
                                    isCompleted
                                        ? "border-l-green-500 bg-white opacity-80"
                                        : isStockAvailable
                                            ? "border-l-blue-500 shadow-md transform hover:-translate-y-1"
                                            : "border-l-gray-300 bg-gray-50 opacity-60"
                                )}
                            >
                                <CardContent className="p-0">
                                    <div className="flex flex-col md:flex-row">
                                        {/* Left: Image & Info */}
                                        <div className="p-5 flex-1 flex gap-5">
                                            <div className="relative group">
                                                {item.image_url ? (
                                                    <ImageViewer
                                                        images={[item.image_url]}
                                                        alt={item.product_name}
                                                        className="w-28 h-28 rounded-xl border border-gray-200 object-contain bg-white shadow-sm group-hover:scale-105 transition-transform"
                                                    />
                                                ) : (
                                                    <div className="w-28 h-28 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center text-gray-300">
                                                        <Package className="w-10 h-10" />
                                                    </div>
                                                )}
                                                {isCompleted && (
                                                    <div className="absolute inset-0 bg-green-500/20 rounded-xl flex items-center justify-center backdrop-blur-[1px]">
                                                        <CheckCircle2 className="w-10 h-10 text-green-600 drop-shadow-md" />
                                                    </div>
                                                )}
                                            </div>

                                            <div className="flex-1 min-w-0 py-1">
                                                <div className="flex justify-between items-start mb-1">
                                                    <h3 className="text-lg font-bold text-gray-900 truncate pr-2">{item.product_name}</h3>
                                                    {isJustScanned && <Badge className="bg-yellow-400 text-yellow-900 hover:bg-yellow-500">Just Scanned</Badge>}
                                                </div>

                                                <p className="text-sm font-mono text-blue-600 font-medium mb-3">{item.product_id}</p>

                                                <div className="flex flex-wrap gap-2 mb-4">
                                                    <Badge variant="outline" className="bg-gray-50 text-gray-600 border-gray-200 font-mono">
                                                        <ScanBarcode className="w-3 h-3 mr-1" />
                                                        {item.barcode || 'N/A'}
                                                    </Badge>
                                                    <Badge variant="outline" className="bg-blue-50 text-blue-700 border-blue-200">
                                                        Ordered: {item.qty_ordered}
                                                    </Badge>
                                                </div>

                                                {/* Suggestions (Locations) */}
                                                <div className="bg-slate-50 rounded-lg p-3 border border-slate-100">
                                                    <div className="flex items-center gap-2 mb-2">
                                                        <MapPin className="w-3.5 h-3.5 text-slate-500" />
                                                        <span className="text-xs font-bold text-slate-500 uppercase tracking-wide">Pick From</span>
                                                    </div>

                                                    {item.picking_suggestions && item.picking_suggestions.length > 0 ? (
                                                        <div className="flex flex-wrap gap-2">
                                                            {item.picking_suggestions.map((sug, idx) => (
                                                                <div key={idx} className="flex items-center pl-2 pr-1 py-1 rounded-md border bg-white border-blue-100 shadow-sm">
                                                                    <span className="text-sm font-bold text-slate-700 mr-2 font-mono">{sug.location_code}</span>
                                                                    <Badge className="bg-blue-100 text-blue-800 hover:bg-blue-200 border-0 h-5 px-1.5 min-w-[2rem] justify-center">
                                                                        {Number(sug.quantity)}
                                                                    </Badge>
                                                                </div>
                                                            ))}
                                                        </div>
                                                    ) : (
                                                        <p className="text-sm text-red-500 flex items-center font-medium italic">
                                                            <AlertCircle className="w-4 h-4 mr-1" /> Not Allocated / Out of Stock
                                                        </p>
                                                    )}
                                                </div>
                                            </div>
                                        </div>

                                        {/* Right: Actions & Input */}
                                        <div className="bg-gray-50 border-t md:border-t-0 md:border-l border-gray-100 p-5 flex flex-col justify-center w-full md:w-48 gap-3">

                                            <div className="text-center">
                                                <span className="text-xs font-bold text-gray-400 uppercase tracking-wider block mb-1">Target</span>
                                                <span className={cn("text-3xl font-black tracking-tight", isStockAvailable ? "text-gray-800" : "text-gray-300")}>
                                                    {targetQty}
                                                </span>
                                            </div>

                                            <div className="relative">
                                                <Input
                                                    type="number"
                                                    min="0"
                                                    max={targetQty}
                                                    value={currentPicked}
                                                    onChange={(e) => handleQtyChange(item.id, e.target.value, targetQty)}
                                                    className={cn(
                                                        "text-center font-bold text-lg h-12 transition-all",
                                                        isCompleted ? "border-green-500 text-green-700 bg-green-50" : "focus:border-blue-500"
                                                    )}
                                                    disabled={!isStockAvailable || pickingSlip.status === 'done'}
                                                />
                                                {/* Progress Bar */}
                                                <div className="absolute bottom-0 left-0 right-0 h-1 bg-gray-200 rounded-b-md overflow-hidden">
                                                    <div
                                                        className={cn("h-full transition-all duration-500", isCompleted ? "bg-green-500" : "bg-blue-500")}
                                                        style={{ width: `${progress}%` }}
                                                    />
                                                </div>
                                            </div>

                                            {!isCompleted && isStockAvailable && pickingSlip.status !== 'done' && (
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    className="w-full text-xs font-bold"
                                                    onClick={() => handleAutoFill(item, targetQty)}
                                                >
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

                {items.length === 0 && (
                    <div className="text-center py-12 bg-white rounded-lg border border-dashed">
                        <Package className="w-12 h-12 text-gray-300 mx-auto mb-3" />
                        <p className="text-gray-500">No items found in this picking slip.</p>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
