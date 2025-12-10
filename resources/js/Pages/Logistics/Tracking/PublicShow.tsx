import React from 'react';
import { Head } from '@inertiajs/react';
import { Package, Truck, CheckCircle, MapPin, Box, ArrowRight } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Badge } from "@/Components/ui/badge";
import { cn } from '@/lib/utils';

export default function PublicShow({ delivery, timeline }: any) {
    return (
        <div className="min-h-screen bg-gray-50 py-12 px-4 sm:px-6 lg:px-8 font-sans">
            <Head title={`Tracking ${delivery.number}`} />

            <div className="max-w-xl mx-auto space-y-8">

                {/* Brand Logo / Header */}
                <div className="text-center">
                    <div className="mx-auto h-12 w-12 bg-indigo-600 rounded-lg flex items-center justify-center text-white font-bold text-xl mb-4">
                        T
                    </div>
                    <h2 className="text-3xl font-extrabold text-gray-900">Track Your Package</h2>
                    <p className="mt-2 text-sm text-gray-600">
                        Delivery No: <span className="font-mono font-bold text-indigo-600">{delivery.number}</span>
                    </p>
                </div>

                {/* Status Card */}
                <Card className="shadow-lg border-0 ring-1 ring-black/5">
                    <CardHeader className="bg-white pb-2">
                        <div className="flex justify-between items-start">
                            <div>
                                <p className="text-xs text-gray-500 uppercase tracking-wide font-semibold">Current Status</p>
                                <h3 className="text-2xl font-bold text-gray-900 mt-1 capitalize">{delivery.status.replace('_', ' ')}</h3>
                            </div>
                            <div className="p-3 bg-indigo-50 rounded-full">
                                <Truck className="w-6 h-6 text-indigo-600" />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="pt-6">
                        {/* Timeline */}
                        <div className="relative pl-4 border-l-2 border-gray-200 space-y-8">
                            {timeline.map((step: any, idx: number) => (
                                <div key={idx} className="relative">
                                    <span className={cn(
                                        "absolute -left-[21px] top-1 h-4 w-4 rounded-full border-2 bg-white",
                                        step.active ? "border-indigo-600 bg-indigo-600" : "border-gray-300"
                                    )} />
                                    <div className={cn("ml-4", !step.active && "opacity-50")}>
                                        <p className="text-sm font-bold text-gray-900">{step.status}</p>
                                        <p className="text-xs text-gray-500">{step.description}</p>
                                        {step.date && <p className="text-xs text-indigo-600 font-medium mt-1">{step.date}</p>}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </CardContent>
                </Card>

                {/* Details Card */}
                <Card className="shadow-sm">
                    <CardContent className="p-6 space-y-4">
                        <div className="flex items-start gap-4">
                            <MapPin className="w-5 h-5 text-gray-400 mt-1" />
                            <div>
                                <p className="text-sm font-medium text-gray-900">Destination</p>
                                <p className="text-sm text-gray-500">{delivery.shipping_address}</p>
                                <p className="text-xs text-gray-400 mt-1">Receiver: {delivery.customer_name}</p>
                            </div>
                        </div>

                        <div className="border-t pt-4 flex items-start gap-4">
                            <Box className="w-5 h-5 text-gray-400 mt-1" />
                            <div className="w-full">
                                <p className="text-sm font-medium text-gray-900 mb-2">Items ({delivery.items_count})</p>
                                <div className="space-y-2">
                                    {delivery.items.map((item: any, i: number) => (
                                        <div key={i} className="flex justify-between text-sm text-gray-600 bg-gray-50 p-2 rounded">
                                            <span>{item.product_id}</span>
                                            <span className="font-bold">x {item.qty}</span>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        </div>

                        {delivery.carrier && (
                             <div className="border-t pt-4 flex items-start gap-4">
                                <Truck className="w-5 h-5 text-gray-400 mt-1" />
                                <div>
                                    <p className="text-sm font-medium text-gray-900">Carrier Info</p>
                                    <p className="text-sm text-gray-500">{delivery.carrier} {delivery.tracking_no && `(Tracking: ${delivery.tracking_no})`}</p>
                                </div>
                            </div>
                        )}
                    </CardContent>
                </Card>

                <div className="text-center text-xs text-gray-400">
                    &copy; {new Date().getFullYear()} TMR EcoSystem Logistics
                </div>
            </div>
        </div>
    );
}
