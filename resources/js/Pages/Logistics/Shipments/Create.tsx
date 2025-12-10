import React, { useState } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Card, CardContent, CardHeader, CardTitle } from "@/Components/ui/card";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Textarea } from "@/Components/ui/textarea";
import { Checkbox } from "@/Components/ui/checkbox";
import { Truck, Calendar, User, Phone, ArrowLeft, Save } from "lucide-react";
import LogisticsNavigationMenu from '../Partials/LogisticsNavigationMenu';

// --- Types ---
interface DeliveryNote {
    id: string;
    delivery_number: string;
    order_number: string;
    customer_name: string;
    shipping_address: string;
    created_at: string;
}
interface VehicleOption {
    id: string;
    name: string;
    driver_name: string;
    driver_phone: string;
}

export default function CreateShipment({ auth, readyDeliveries, vehicles, newShipmentNumber }: any) {

    const { data, setData, post, processing, errors } = useForm({
        shipment_number: newShipmentNumber,
        vehicle_id: '',
        delivery_note_ids: [] as string[],
        planned_date: new Date().toISOString().split('T')[0],
        driver_name: '',
        driver_phone: '',
        note: ''
    });

    // --- Handlers ---
    const handleVehicleChange = (vehicleId: string) => {
        const vehicle = vehicles.find((v: VehicleOption) => v.id == vehicleId); // Loose equality for string/number id match
        setData(data => ({
            ...data,
            vehicle_id: vehicleId,
            driver_name: vehicle?.driver_name || '',
            driver_phone: vehicle?.driver_phone || ''
        }));
    };

    const toggleDelivery = (id: string) => {
        const current = data.delivery_note_ids;
        if (current.includes(id)) {
            setData('delivery_note_ids', current.filter(i => i !== id));
        } else {
            setData('delivery_note_ids', [...current, id]);
        }
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('logistics.shipments.store'));
    };

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<LogisticsNavigationMenu />}>
            <Head title="Plan Shipment" />

            <form onSubmit={handleSubmit} className="max-w-7xl mx-auto p-6 space-y-6">

                {/* Header */}
                <div className="flex justify-between items-center">
                    <div className="flex items-center gap-2 text-gray-500">
                        <Button variant="ghost" size="sm" onClick={() => window.history.back()}>
                            <ArrowLeft className="w-4 h-4 mr-1" /> Back
                        </Button>
                        <h2 className="text-2xl font-bold text-gray-800">Plan New Shipment</h2>
                    </div>
                    <Button type="submit" className="bg-indigo-600 hover:bg-indigo-700" disabled={processing || data.delivery_note_ids.length === 0}>
                        <Save className="w-4 h-4 mr-2" /> Create Shipment
                    </Button>
                </div>

                <div className="grid grid-cols-1 lg:grid-cols-3 gap-6">

                    {/* Left: Shipment Info (Trip Details) */}
                    <Card className="lg:col-span-1 h-fit">
                        <CardHeader>
                            <CardTitle className="flex items-center gap-2">
                                <Truck className="w-5 h-5" /> Trip Info
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <div className="space-y-2">
                                <Label>Shipment No.</Label>
                                <Input value={data.shipment_number} onChange={e => setData('shipment_number', e.target.value)} />
                                {errors.shipment_number && <p className="text-red-500 text-xs">{errors.shipment_number}</p>}
                            </div>

                            <div className="space-y-2">
                                <Label>Plan Date</Label>
                                <div className="relative">
                                    <Calendar className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                    <Input type="date" className="pl-9" value={data.planned_date} onChange={e => setData('planned_date', e.target.value)} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Vehicle *</Label>
                                <Select onValueChange={handleVehicleChange}>
                                    <SelectTrigger><SelectValue placeholder="Select Vehicle" /></SelectTrigger>
                                    <SelectContent>
                                        {vehicles.map((v: VehicleOption) => (
                                            <SelectItem key={v.id} value={v.id.toString()}>{v.name}</SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {errors.vehicle_id && <p className="text-red-500 text-xs">{errors.vehicle_id}</p>}
                            </div>

                            <div className="grid grid-cols-2 gap-3">
                                <div className="space-y-2">
                                    <Label>Driver</Label>
                                    <div className="relative">
                                        <User className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                        <Input className="pl-9" value={data.driver_name} onChange={e => setData('driver_name', e.target.value)} />
                                    </div>
                                </div>
                                <div className="space-y-2">
                                    <Label>Phone</Label>
                                    <div className="relative">
                                        <Phone className="absolute left-3 top-2.5 h-4 w-4 text-gray-400" />
                                        <Input className="pl-9" value={data.driver_phone} onChange={e => setData('driver_phone', e.target.value)} />
                                    </div>
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label>Note</Label>
                                <Textarea placeholder="e.g. สายเหนือ, เก็บเงินปลายทาง" value={data.note} onChange={e => setData('note', e.target.value)} />
                            </div>
                        </CardContent>
                    </Card>

                    {/* Right: Select Delivery Notes */}
                    <Card className="lg:col-span-2">
                        <CardHeader className="flex flex-row justify-between items-center">
                            <CardTitle>Select Orders to Ship ({data.delivery_note_ids.length})</CardTitle>
                            <span className="text-sm text-gray-500">Available: {readyDeliveries.length}</span>
                        </CardHeader>
                        <CardContent className="p-0">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead className="w-[50px]"></TableHead>
                                        <TableHead>DO Number</TableHead>
                                        <TableHead>Customer</TableHead>
                                        <TableHead>Location</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {readyDeliveries.length === 0 ? (
                                        <TableRow><TableCell colSpan={4} className="text-center h-24 text-gray-500">No ready orders found.</TableCell></TableRow>
                                    ) : (
                                        readyDeliveries.map((dn: DeliveryNote) => (
                                            <TableRow key={dn.id} className={data.delivery_note_ids.includes(dn.id) ? "bg-indigo-50 hover:bg-indigo-50" : "hover:bg-gray-50"}>
                                                <TableCell>
                                                    <Checkbox
                                                        checked={data.delivery_note_ids.includes(dn.id)}
                                                        onCheckedChange={() => toggleDelivery(dn.id)}
                                                    />
                                                </TableCell>
                                                <TableCell className="font-medium">
                                                    {dn.delivery_number}
                                                    <div className="text-xs text-gray-500">{dn.order_number}</div>
                                                </TableCell>
                                                <TableCell>{dn.customer_name}</TableCell>
                                                <TableCell className="text-xs text-gray-600 max-w-[200px] truncate" title={dn.shipping_address}>
                                                    {dn.shipping_address}
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                            {errors.delivery_note_ids && <p className="text-red-500 text-xs p-4">{errors.delivery_note_ids}</p>}
                        </CardContent>
                    </Card>

                </div>
            </form>
        </AuthenticatedLayout>
    );
}
