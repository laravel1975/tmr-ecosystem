import React from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from "@/Components/ui/button";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { Badge } from "@/Components/ui/badge";
import { Plus, Search, Edit, Trash2 } from "lucide-react";
import { Input } from "@/Components/ui/input";
import SalesNavigationMenu from '../Sales/Partials/SalesNavigationMenu';

export default function CustomerIndex({ auth, customers, filters }: any) {
    const handleSearch = (e: any) => {
        router.get(route('customers.index'), { search: e.target.value }, { preserveState: true, replace: true });
    };

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<SalesNavigationMenu />}>
            <Head title="Customers" />
            <div className="max-w-7xl mx-auto p-6 space-y-6">
                <div className="flex justify-between items-center">
                    <h1 className="text-2xl font-bold">Customer Management</h1>
                    <Link href={route('customers.create')}>
                        <Button className="gap-2"><Plus className="w-4 h-4" /> New Customer</Button>
                    </Link>
                </div>

                <div className="flex items-center gap-2 bg-white p-2 rounded shadow-sm border">
                    <Search className="w-4 h-4 text-gray-400" />
                    <Input
                        placeholder="Search customer code or name..."
                        defaultValue={filters.search}
                        onChange={handleSearch}
                        className="border-none shadow-none focus-visible:ring-0"
                    />
                </div>

                <div className="bg-white rounded-md border shadow-sm">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead>Code</TableHead>
                                <TableHead>Name</TableHead>
                                <TableHead>Contact</TableHead>
                                <TableHead className="text-right">Credit Limit</TableHead>
                                <TableHead className="text-right">Balance</TableHead>
                                <TableHead className="text-center">Status</TableHead>
                                <TableHead></TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            {customers.data.map((customer: any) => (
                                <TableRow key={customer.id}>
                                    <TableCell className="font-mono">{customer.code}</TableCell>
                                    <TableCell className="font-medium">
                                        <Link href={route('customers.show', customer.id)} className="text-indigo-600 hover:underline">
                                            {customer.name}
                                        </Link>
                                    </TableCell>
                                    <TableCell>
                                        <div className="text-sm">{customer.phone}</div>
                                        <div className="text-xs text-gray-500">{customer.email}</div>
                                    </TableCell>
                                    <TableCell className="text-right font-mono">
                                        {new Intl.NumberFormat().format(customer.credit_limit)}
                                    </TableCell>
                                    <TableCell className={`text-right font-mono font-bold ${customer.outstanding_balance > customer.credit_limit ? 'text-red-600' : 'text-green-600'}`}>
                                        {new Intl.NumberFormat().format(customer.outstanding_balance)}
                                    </TableCell>
                                    <TableCell className="text-center">
                                        {customer.is_credit_hold ? (
                                            <Badge variant="destructive">HOLD</Badge>
                                        ) : (
                                            <Badge variant="outline" className="text-green-600 border-green-200 bg-green-50">Active</Badge>
                                        )}
                                    </TableCell>
                                    <TableCell className="text-right">
                                        <Link href={route('customers.edit', customer.id)}>
                                            <Button variant="ghost" size="icon"><Edit className="w-4 h-4" /></Button>
                                        </Link>
                                    </TableCell>
                                </TableRow>
                            ))}
                        </TableBody>
                    </Table>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
