import React from 'react';
import { Head, Link } from '@inertiajs/react';
import { PageProps } from '@/types';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';

// (Import ShadCN & Icons)
import { Card, CardHeader, CardTitle, CardContent } from '@/Components/ui/card';
import SalesNavigationMenu from './Partials/SalesNavigationMenu';

/* --- Types --- */
interface Props {
    stats: {
        totalItems: 3500000;
        totalWarehouses: 350;
        totalStockValue: 54200;
        itemsNoStock: 15320;
    };
}

// (Helper Component สำหรับ Stat Card)
const StatCard = ({ title, value, icon: Icon, colorClass, link }: any) => (
    <Card>
        <Link href={link}>
            <CardHeader className="flex flex-row items-center justify-between space-y-0 pb-2">
                <CardTitle className="text-sm font-medium text-muted-foreground">
                    {title}
                </CardTitle>
                <Icon className={`h-4 w-4 ${colorClass ?? 'text-muted-foreground'}`} />
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold">{value}</div>
            </CardContent>
        </Link>
    </Card>
);

// (Helper Format เงิน)
const formatCurrency = (value: number) =>
    value.toLocaleString('th-TH', { style: 'currency', currency: 'THB', minimumFractionDigits: 2 });


export default function InventoryDashboard({ auth, stats }: PageProps & Props) {

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                    Sales Overview
                </h2>
            }
            navigationMenu={<SalesNavigationMenu />}
        >
            <Head title="Sales Dashboard" />

            <div className="py-12">


            </div>
        </AuthenticatedLayout>
    );
}
