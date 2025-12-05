import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import AppPanel from '@/Layouts/AppPanel';

// (1. 👈 [แก้ไข] Import ไอคอนใหม่ที่สื่อความหมายกว่า)
import {
    MessageSquarePlus, // (แจ้งซ่อม)
    Users,             // (HRM)
    Wrench,            // (Maintenance)
    Factory,           // (Manufacturing)
    Package,           // (Inventory) - เปลี่ยนจาก Boxes ให้ดูคลีนขึ้น
    ShoppingCart,      // (Purchase)
    TrendingUp,        // (Sales)
    Settings,
    Truck,
    VerifiedIcon,          // (Settings)
} from 'lucide-react';

import { Card } from "@/Components/ui/card";
import { AspectRatio } from "@/Components/ui/aspect-ratio";

interface AppItemProps {
    href: string;
    label: string;
    Icon: React.ElementType;
    colorClasses: string;
}

const allApps: AppItemProps[] = [
    // --- Group 1: General & HR (ทุกคนใช้) ---
    {
        href: route('maintenance.service-request.create'),
        label: 'แจ้งซ่อม',
        Icon: MessageSquarePlus,
        colorClasses: 'bg-rose-500 text-white',
    },
    {
        href: route('hrm.dashboard'),
        label: 'HRM',
        Icon: Users,
        colorClasses: 'bg-orange-500 text-white',
    },
    {
        href: route('approval.index'),
        label: 'Approval',
        Icon: VerifiedIcon,
        colorClasses: 'bg-yellow-500 text-white',
    },

    // --- Group 2: Core Operation (งานหลัก) ---
    {
        href: route('maintenance.dashboard.index'),
        label: 'Maintenance',
        Icon: Wrench,
        colorClasses: 'bg-violet-600 text-white',
    },

    // --- Group 3: Inventory & Location (การจัดการของ) ---
    {
        href: route('inventory.dashboard.index'),
        label: 'Inventory',
        Icon: Package,
        colorClasses: 'bg-emerald-500 text-white',
    },
    {
        href: route('logistics.shipments.index'),
        label: 'TPMS',
        Icon: Truck,
        colorClasses: 'bg-blue-600 text-white',
    },

    // --- Group 4: Supply Chain (ซื้อ -> ผลิต -> ขาย) ---
    {
        href: route('purchase.orders.index'), // (Purchase)
        label: 'Purchase',
        Icon: ShoppingCart,
        colorClasses: 'bg-cyan-600 text-white',
    },
    {
        href: "#", // (Manufacturing)
        label: 'Manufacturing',
        Icon: Factory,
        colorClasses: 'bg-amber-500 text-white',
    },
    {
        href: route('sales.dashboard'), // (Sales/Stock)
        label: 'Sales',
        Icon: TrendingUp,
        colorClasses: 'bg-green-600 text-white',
    },

    // --- Group 5: System (ท้ายสุด) ---
    {
        href: route('iam.dashboard'),
        label: 'Settings',
        Icon: Settings,
        colorClasses: 'bg-slate-700 text-white',
    },
];

export default function AppLauncher({ auth }: PageProps) {
    return (
        <AppPanel user={auth.user}>
            <Head title="Applications" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">

                    <div className="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 xl:grid-cols-6 gap-4 sm:gap-6">
                        {allApps.map((app) => (
                            <Link href={app.href} key={app.label}>
                                <Card className="rounded-xl shadow-sm m-2
                                               hover:shadow-lg hover:scale-105
                                               transition-all duration-200 ease-in-out
                                               cursor-pointer group border-0 bg-white/50 backdrop-blur-sm"
                                >
                                    <AspectRatio
                                        ratio={1 / 1}
                                        className="p-4 flex flex-col items-center justify-center"
                                    >
                                        <div
                                            className={`h-16 w-16 rounded-2xl flex items-center justify-center shadow-md
                                                        ${app.colorClasses}
                                                        transition-transform duration-300 group-hover:rotate-3`}
                                        >
                                            <app.Icon className="h-8 w-8" />
                                        </div>
                                        <span className="mt-4 text-sm font-semibold text-center text-gray-700 group-hover:text-gray-900">
                                            {app.label}
                                        </span>
                                    </AspectRatio>
                                </Card>
                            </Link>
                        ))}
                    </div>

                </div>
            </div>
        </AppPanel>
    );
}
