import React, { useState, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { debounce } from 'lodash';
import { Search, Truck, Calendar, User, MapPin, ArrowRight, Plus, Package, CheckCircle2, CircleDashed } from "lucide-react";

// Components
import { Input } from "@/Components/ui/input";
import { Button } from "@/Components/ui/button";
import { Badge } from "@/Components/ui/badge";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import {
  Accordion,
  AccordionContent,
  AccordionItem,
  AccordionTrigger,
} from "@/Components/ui/accordion";
import LogisticsNavigationMenu from '../Partials/LogisticsNavigationMenu';

// Interfaces
interface DeliveryNote {
    id: string;
    delivery_number: string;
    status: string;
    customer_name?: string; // Optional: ถ้ามีชื่อลูกค้าจะดีมาก
}

interface Shipment {
    id: string;
    shipment_number: string;
    vehicle: {
        license_plate: string;
        vehicle_type: string;
    } | null;
    driver_name: string;
    driver_phone: string;
    planned_date: string;
    status: string;
    delivery_notes: DeliveryNote[];
    created_at: string;
}

interface Props {
    auth: any;
    shipments: { data: Shipment[]; links: any[]; total: number };
    filters: { search: string; };
}

export default function ShipmentIndex({ auth, shipments, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    const debouncedSearch = useCallback(
        debounce((query: string) => {
            router.get(route('logistics.shipments.index'), { search: query }, { preserveState: true, replace: true });
        }, 300), []
    );

    const getStatusBadge = (status: string) => {
        switch (status) {
            case 'ready': return <Badge className="bg-blue-600 hover:bg-blue-700">Ready</Badge>;
            case 'in_transit': return <Badge className="bg-orange-500 hover:bg-orange-600">In Transit</Badge>;
            case 'completed': return <Badge className="bg-green-600 hover:bg-green-700">Completed</Badge>;
            case 'cancelled': return <Badge variant="destructive">Cancelled</Badge>;
            default: return <Badge variant="secondary">{status}</Badge>;
        }
    };

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<LogisticsNavigationMenu />}>
            <Head title="Shipment Planning" />

            <div className="py-8 max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-end mb-6 gap-4">
                    <div>
                        <h2 className="text-2xl font-bold text-gray-800 flex items-center gap-2">
                            <Truck className="h-6 w-6" /> Shipment Planning
                        </h2>
                        <p className="text-gray-500 mt-1">จัดการรอบการเดินรถและติดตามสถานะการจัดส่ง</p>
                    </div>
                    <Button asChild className="bg-indigo-600 hover:bg-indigo-700 shadow-md transition-all hover:scale-105">
                        <Link href={route('logistics.shipments.create')}>
                            <Plus className="w-4 h-4 mr-2" /> Plan New Trip
                        </Link>
                    </Button>
                </div>

                <div className="bg-white rounded-xl border shadow-sm overflow-hidden">
                    {/* Search Bar */}
                    <div className="p-4 border-b bg-gray-50/50 flex items-center">
                        <div className="relative w-full sm:w-72">
                            <Search className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                            <Input
                                placeholder="Search Shipment #, Driver..."
                                className="pl-9 bg-white focus-visible:ring-indigo-500"
                                value={search}
                                onChange={e => { setSearch(e.target.value); debouncedSearch(e.target.value); }}
                            />
                        </div>
                    </div>

                    {/* Table */}
                    <Table>
                        <TableHeader>
                            <TableRow className="bg-gray-50/80 hover:bg-gray-50/80">
                                <TableHead className="w-[140px]">Shipment No.</TableHead>
                                <TableHead className="w-[180px]">Vehicle / Driver</TableHead>
                                <TableHead className="w-[120px]">Plan Date</TableHead>
                                {/* ✅ Expanded Column Width for Accordion */}
                                <TableHead className="w-[320px]">Assigned Orders</TableHead>
                                <TableHead className="w-[100px]">Status</TableHead>
                                <TableHead className="text-right">Action</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {shipments.data.length === 0 ? (
                                <TableRow><TableCell colSpan={6} className="text-center h-32 text-gray-500">No shipments found.</TableCell></TableRow>
                            ) : (
                                shipments.data.map((shipment) => (
                                    <TableRow key={shipment.id} className="group hover:bg-slate-50/50 transition-colors">
                                        <TableCell className="font-medium text-indigo-600 align-top py-4">
                                            {shipment.shipment_number}
                                        </TableCell>
                                        <TableCell className="align-top py-4">
                                            <div className="flex flex-col gap-1">
                                                <div className="font-medium text-gray-700 flex items-center gap-1.5">
                                                    <Truck className="w-3.5 h-3.5 text-gray-400" />
                                                    {shipment.vehicle?.license_plate || <span className="text-gray-400 italic">No Vehicle</span>}
                                                </div>
                                                <div className="text-xs text-gray-500 flex items-center gap-1.5">
                                                    <User className="w-3.5 h-3.5" />
                                                    {shipment.driver_name || <span className="italic">No Driver</span>}
                                                </div>
                                            </div>
                                        </TableCell>
                                        <TableCell className="align-top py-4">
                                            <div className="flex items-center gap-2 text-gray-600 bg-gray-100 px-2 py-1 rounded-md w-fit text-xs font-medium">
                                                <Calendar className="w-3.5 h-3.5" />
                                                {new Date(shipment.planned_date).toLocaleDateString('th-TH', { day: '2-digit', month: 'short', year: '2-digit' })}
                                            </div>
                                        </TableCell>

                                        {/* ✅ Accordion Implementation */}
                                        <TableCell className="align-top py-3">
                                            {shipment.delivery_notes.length > 0 ? (
                                                <Accordion type="single" collapsible className="w-full bg-white rounded-lg border shadow-sm">
                                                    <AccordionItem value="orders" className="border-0">
                                                        <AccordionTrigger className="px-3 py-2 text-sm font-medium hover:no-underline hover:bg-gray-50 rounded-t-lg data-[state=open]:border-b transition-all">
                                                            <div className="flex items-center gap-2">
                                                                <Package className="w-4 h-4 text-indigo-500" />
                                                                <span>{shipment.delivery_notes.length} Orders</span>
                                                                <span className="text-xs text-gray-400 font-normal ml-1">
                                                                    ({shipment.delivery_notes.filter(n => n.status === 'delivered').length} Done)
                                                                </span>
                                                            </div>
                                                        </AccordionTrigger>
                                                        <AccordionContent className="px-0 pb-0">
                                                            <div className="max-h-[160px] overflow-y-auto scrollbar-thin scrollbar-thumb-gray-200 scrollbar-track-transparent">
                                                                {shipment.delivery_notes.map((note, idx) => (
                                                                    <Link
                                                                        key={note.id}
                                                                        href={route('logistics.delivery.process', note.id)}
                                                                        className={`flex items-center justify-between px-3 py-2 text-xs hover:bg-indigo-50 transition-colors border-b last:border-0 ${idx % 2 === 0 ? 'bg-gray-50/30' : ''}`}
                                                                    >
                                                                        <div className="flex items-center gap-2">
                                                                            {note.status === 'delivered' ? (
                                                                                <CheckCircle2 className="w-3.5 h-3.5 text-green-500 flex-shrink-0" />
                                                                            ) : (
                                                                                <CircleDashed className="w-3.5 h-3.5 text-gray-300 flex-shrink-0" />
                                                                            )}
                                                                            <span className={`font-mono ${note.status === 'delivered' ? 'text-gray-500 line-through' : 'text-gray-700'}`}>
                                                                                {note.delivery_number}
                                                                            </span>
                                                                        </div>
                                                                        <div className="flex items-center">
                                                                            {note.status === 'delivered' ? (
                                                                                <span className="text-[10px] font-bold text-green-600 bg-green-100 px-1.5 py-0.5 rounded">Done</span>
                                                                            ) : (
                                                                                 <ArrowRight className="w-3 h-3 text-indigo-300 group-hover:text-indigo-600" />
                                                                            )}
                                                                        </div>
                                                                    </Link>
                                                                ))}
                                                            </div>
                                                        </AccordionContent>
                                                    </AccordionItem>
                                                </Accordion>
                                            ) : (
                                                <div className="text-sm text-gray-400 flex items-center gap-2 py-2 px-1">
                                                    <CircleDashed className="w-4 h-4" /> No orders
                                                </div>
                                            )}
                                        </TableCell>

                                        <TableCell className="align-top py-4">
                                            {getStatusBadge(shipment.status)}
                                        </TableCell>
                                        <TableCell className="text-right align-top py-4">
                                            <Button size="icon" variant="ghost" className="h-8 w-8 text-gray-400 hover:text-indigo-600 hover:bg-indigo-50" asChild>
                                                <Link href={route('logistics.shipments.show', shipment.id)}>
                                                    <ArrowRight className="w-4 h-4" />
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))
                            )}
                        </TableBody>
                    </Table>

                    {/* Pagination */}
                    <div className="p-4 border-t bg-gray-50 flex flex-col sm:flex-row justify-between items-center gap-4 text-sm text-gray-500">
                        <span>Showing {shipments.data.length} of {shipments.total} trips</span>
                        <div className="flex gap-1 flex-wrap justify-center">
                            {shipments.links.map((link: any, index: number) => (
                                <Button
                                    key={index}
                                    variant={link.active ? "default" : "outline"}
                                    size="sm"
                                    className={`h-8 min-w-[2rem] ${!link.url ? "opacity-50 cursor-not-allowed" : ""}`}
                                    asChild={!!link.url}
                                    disabled={!link.url}
                                >
                                    {link.url ? (
                                        <Link href={link.url} dangerouslySetInnerHTML={{ __html: link.label }} />
                                    ) : (
                                        <span dangerouslySetInnerHTML={{ __html: link.label }} />
                                    )}
                                </Button>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
