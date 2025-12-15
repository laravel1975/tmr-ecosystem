import React, { useState, useCallback } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import SalesNavigationMenu from './Partials/SalesNavigationMenu';
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/ui/table";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "@/Components/ui/dropdown-menu";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/Components/ui/select";
import { Badge } from "@/Components/ui/badge";
import { MoreHorizontal, Eye, Pencil, Trash2, Plus, Search, User as UserIcon } from "lucide-react";
import { debounce } from 'lodash';

// --- Types ---
interface Order {
    id: string;
    order_number: string;
    customer_id: string;
    customer_code: string;
    customer_name: string;
    status: string;
    total_amount: number;
    currency: string;
    created_at: string;
    // ✅ เพิ่ม salesperson info
    salesperson?: {
        id: number;
        name: string;
    };
}

interface Props {
    auth: any;
    orders: {
        data: Order[];
        links: any[];
        current_page: number;
        last_page: number;
    };
    filters: {
        search: string;
        my_orders?: boolean;     // ✅ New Filter
        salesperson_id?: string; // ✅ New Filter
    };
    salespersons: any[]; // ✅ List for Manager
    canViewAll: boolean; // ✅ Permission Flag
}

export default function Index({ auth, orders, filters, salespersons, canViewAll }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    // Search Handler
    const handleSearch = useCallback(
        debounce((newFilters: any) => {
            router.get(
                route('sales.orders.index'),
                { ...filters, ...newFilters }, // Merge old & new filters
                { preserveState: true, replace: true }
            );
        }, 300),
        [filters] // Dependency on filters so we don't lose other filter states
    );

    const onSearchChange = (val: string) => {
        setSearch(val);
        handleSearch({ search: val });
    };

    // Filter Handlers
    const handleMyOrdersToggle = () => {
        const newValue = !filters.my_orders;
        router.get(route('sales.orders.index'), { ...filters, my_orders: newValue ? '1' : '' }, { preserveState: true, replace: true });
    };

    const handleSalespersonChange = (val: string) => {
        router.get(route('sales.orders.index'), { ...filters, salesperson_id: val === 'all' ? '' : val }, { preserveState: true, replace: true });
    };

    const handleDelete = (id: string) => {
        if (confirm('Are you sure you want to delete this order?')) {
            router.delete(route('sales.orders.destroy', id));
        }
    };

    const getStatusColor = (status: string) => {
        switch (status) {
            case 'draft': return 'bg-gray-500';
            case 'confirmed': return 'bg-blue-600';
            case 'done': return 'bg-green-600';
            case 'cancelled': return 'bg-red-600';
            default: return 'bg-gray-500';
        }
    };

    const formatCurrency = (amount: number, currency: string) => {
        return new Intl.NumberFormat('th-TH', {
            style: 'currency',
            currency: currency || 'THB'
        }).format(amount);
    };

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<SalesNavigationMenu />}>
            <Head title="Sales Orders" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">

                    {/* Header */}
                    <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <div>
                            <h2 className="text-2xl font-bold tracking-tight text-gray-900">Sales Orders</h2>
                            <p className="text-sm text-muted-foreground">Manage your quotations and orders.</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <Button asChild className="bg-purple-700 hover:bg-purple-800">
                                <Link href={route('sales.orders.create')}>
                                    <Plus className="mr-2 h-4 w-4" /> Create Order
                                </Link>
                            </Button>
                        </div>
                    </div>

                    {/* ✅ Filters Toolbar */}
                    <div className="bg-white p-4 rounded-lg border shadow-sm flex flex-col md:flex-row items-center gap-4">
                        <div className="flex-1 w-full relative">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-gray-500" />
                            <Input
                                placeholder="Search order number, customer..."
                                value={search}
                                onChange={(e) => onSearchChange(e.target.value)}
                                className="pl-9 w-full"
                            />
                        </div>

                        <div className="flex items-center gap-2 w-full md:w-auto">
                            {/* My Orders Toggle */}
                            <Button
                                variant={filters.my_orders ? "default" : "outline"}
                                size="sm"
                                onClick={handleMyOrdersToggle}
                                className="gap-2 whitespace-nowrap"
                            >
                                <UserIcon className="w-4 h-4" /> My Orders
                            </Button>

                            {/* Manager Filter */}
                            {canViewAll && (
                                <Select
                                    value={filters.salesperson_id || "all"}
                                    onValueChange={handleSalespersonChange}
                                >
                                    <SelectTrigger className="w-[200px]">
                                        <SelectValue placeholder="Filter by Salesperson" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="all">All Salespersons</SelectItem>
                                        {salespersons.map((sp: any) => (
                                            <SelectItem key={sp.id} value={sp.id.toString()}>
                                                {sp.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            )}
                        </div>
                    </div>

                    {/* Table */}
                    <div className="bg-white rounded-lg border shadow-sm overflow-hidden">
                        <Table>
                            <TableHeader>
                                <TableRow className="bg-gray-50">
                                    <TableHead className="w-[150px]">Order Number</TableHead>
                                    <TableHead>Customer</TableHead>
                                    {/* ✅ Salesperson Column */}
                                    <TableHead>Salesperson</TableHead>
                                    <TableHead>Date</TableHead>
                                    <TableHead className="text-right">Total</TableHead>
                                    <TableHead className="text-center">Status</TableHead>
                                    <TableHead className="text-right">Actions</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {orders.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={7} className="h-24 text-center text-gray-500">
                                            No orders found.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    orders.data.map((order) => (
                                        <TableRow key={order.id} className="hover:bg-slate-50/50">
                                            <TableCell className="font-medium">
                                                <Link href={route('sales.orders.show', order.id)} className="text-purple-700 hover:underline">
                                                    {order.order_number}
                                                </Link>
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex flex-col">
                                                    <span className="font-medium text-gray-900">{order.customer_name || 'Unknown'}</span>
                                                    <span className="text-xs text-gray-400">{order.customer_code}</span>
                                                </div>
                                            </TableCell>

                                            {/* ✅ Salesperson Display */}
                                            <TableCell>
                                                <div className="flex items-center gap-2">
                                                    <div className="w-6 h-6 rounded-full bg-gray-100 flex items-center justify-center text-xs font-bold text-gray-600 border">
                                                        {order.salesperson?.name?.charAt(0) || '?'}
                                                    </div>
                                                    <span className="text-sm text-gray-600">
                                                        {order.salesperson?.name || <span className="text-gray-400 italic">Unassigned</span>}
                                                    </span>
                                                </div>
                                            </TableCell>

                                            <TableCell className="text-gray-500">
                                                {new Date(order.created_at).toLocaleDateString('th-TH')}
                                            </TableCell>
                                            <TableCell className="text-right font-mono font-medium">
                                                {formatCurrency(order.total_amount, order.currency)}
                                            </TableCell>
                                            <TableCell className="text-center">
                                                <Badge className={`${getStatusColor(order.status)} hover:${getStatusColor(order.status)}`}>
                                                    {order.status.toUpperCase()}
                                                </Badge>
                                            </TableCell>
                                            <TableCell className="text-right">
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger asChild>
                                                        <Button variant="ghost" className="h-8 w-8 p-0">
                                                            <span className="sr-only">Open menu</span>
                                                            <MoreHorizontal className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuLabel>Actions</DropdownMenuLabel>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('sales.orders.show', order.id)} className="cursor-pointer flex w-full items-center">
                                                                <Eye className="mr-2 h-4 w-4 text-gray-500" /> View Detail
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem asChild>
                                                            <Link href={route('sales.orders.edit', order.id)} className="cursor-pointer flex w-full items-center">
                                                                <Pencil className="mr-2 h-4 w-4 text-blue-500" /> Edit
                                                            </Link>
                                                        </DropdownMenuItem>
                                                        <DropdownMenuSeparator />
                                                        <DropdownMenuItem onClick={() => handleDelete(order.id)} className="text-red-600 cursor-pointer focus:text-red-600 focus:bg-red-50">
                                                            <Trash2 className="mr-2 h-4 w-4" /> Delete
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    {/* Pagination */}
                    <div className="flex items-center justify-between">
                        <div className="text-sm text-gray-500">
                            Page {orders.current_page} of {orders.last_page}
                        </div>
                        <div className="flex gap-1">
                            {orders.links.map((link, index) => (
                                <Button
                                    key={index}
                                    variant={link.active ? "default" : "outline"}
                                    size="sm"
                                    className={!link.url ? "opacity-50 cursor-not-allowed" : ""}
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
