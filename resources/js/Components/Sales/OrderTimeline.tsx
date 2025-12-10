import React from 'react';
import {
    FileText, CheckCircle, PackageOpen, CheckSquare,
    Box, Truck, MapPin, RotateCcw, CheckCircle2,
    AlertCircle, Clock
} from "lucide-react";
import { cn } from '@/lib/utils';
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { ScrollArea } from "@/Components/ui/scroll-area";

// Interface ให้ตรงกับข้อมูลที่ Service ส่งมา
interface TimelineEvent {
    stage: string;
    title: string;
    description: string;
    timestamp: string;
    formatted_date: string;
    icon: string;
    status: 'completed' | 'pending' | 'warning' | 'error';
}

interface Props {
    events: TimelineEvent[];
}

export default function OrderTimeline({ events }: Props) {

    // Helper: แปลงชื่อ Icon จาก String เป็น Component จริง
    const getIcon = (iconName: string) => {
        const icons: Record<string, any> = {
            FileText, CheckCircle, PackageOpen, CheckSquare,
            Box, Truck, MapPin, RotateCcw, CheckCircle2,
            AlertCircle, Clock
        };
        const IconComponent = icons[iconName] || Clock;
        return <IconComponent className="w-5 h-5 text-white" />;
    };

    // Helper: เลือกสีตาม Stage ของงาน
    const getStatusColor = (status: string, stage: string) => {
        if (status === 'warning') return 'bg-yellow-500';
        if (status === 'error') return 'bg-red-500';

        switch (stage) {
            case 'order': return 'bg-blue-500';     // สีฟ้า (Sales)
            case 'picking': return 'bg-indigo-500'; // สีม่วง (Warehouse)
            case 'delivery': return 'bg-orange-500';// สีส้ม (Logistics)
            case 'return': return 'bg-red-500';     // สีแดง (Return)
            default: return 'bg-gray-400';
        }
    };

    return (
        <Card className="h-full shadow-sm border-gray-200">
            <CardHeader className="pb-3 border-b bg-gray-50/50">
                <CardTitle className="text-lg font-bold flex items-center gap-2">
                    <Clock className="w-5 h-5 text-gray-500" /> Order History & Tracking
                </CardTitle>
            </CardHeader>
            <CardContent className="p-0">
                <ScrollArea className="h-[400px] p-6">
                    {/* Vertical Timeline Line */}
                    <div className="relative border-l-2 border-gray-200 ml-3 space-y-8 pb-2">
                        {events.length === 0 ? (
                            <div className="text-center text-gray-400 py-8 italic ml-4">No history available.</div>
                        ) : (
                            events.map((event, index) => (
                                <div key={index} className="mb-8 ml-8 relative group">
                                    {/* Icon Dot (วงกลมสีๆ ทางซ้าย) */}
                                    <span className={cn(
                                        "absolute -left-[49px] flex h-10 w-10 items-center justify-center rounded-full ring-4 ring-white shadow-sm transition-all group-hover:scale-110",
                                        getStatusColor(event.status, event.stage)
                                    )}>
                                        {getIcon(event.icon)}
                                    </span>

                                    {/* Content (ข้อความ) */}
                                    <div className="flex flex-col sm:flex-row sm:justify-between sm:items-start gap-1 bg-white p-3 rounded-lg border border-transparent hover:border-gray-100 hover:shadow-sm transition-all -mt-2">
                                        <div>
                                            <h3 className="flex items-center text-base font-bold text-gray-900">
                                                {event.title}
                                            </h3>
                                            <p className="text-sm text-gray-600 mt-1 max-w-md leading-relaxed">
                                                {event.description}
                                            </p>
                                        </div>
                                        <time className="block mb-1 text-xs font-medium text-gray-400 sm:order-last sm:mb-0 min-w-[100px] text-right font-mono bg-gray-50 px-2 py-1 rounded">
                                            {event.formatted_date}
                                        </time>
                                    </div>
                                </div>
                            ))
                        )}
                    </div>
                </ScrollArea>
            </CardContent>
        </Card>
    );
}
