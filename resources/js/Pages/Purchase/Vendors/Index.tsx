import React, { useState, useEffect } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import {
    Table,
    TableBody,
    TableCell,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { Button } from '@/Components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/Components/ui/card';
import { Plus, Edit, Trash2, Building2 } from 'lucide-react';
import Pagination from '@/Components/Pagination';
import SearchFilter from '@/Components/SearchFilter';
import SortableColumn from '@/Components/SortableColumn';
import PurchaseNavigationMenu from '@/Pages/Purchase/Partials/PurchaseNavigationMenu'; // Import Menu

interface Vendor {
    id: number;
    uuid: string;
    code: string;
    name: string;
    contact_person: string;
    phone: string;
    email: string;
}

interface Props {
    auth: any;
    vendors: {
        data: Vendor[];
        links: any[];
        meta: any;
    };
    filters: {
        search?: string;
        sort?: string;
        direction?: 'asc' | 'desc';
    };
}

export default function Index({ auth, vendors, filters }: Props) {
    const [search, setSearch] = useState(filters.search || '');

    useEffect(() => {
        const timer = setTimeout(() => {
            if (search !== (filters.search || '')) {
                router.get(
                    route('purchase.vendors.index'),
                    { ...filters, search: search, page: 1 },
                    { preserveState: true, replace: true }
                );
            }
        }, 300);
        return () => clearTimeout(timer);
    }, [search]);

    const handleSort = (key: string) => {
        const newDirection = filters.sort === key && filters.direction === 'asc' ? 'desc' : 'asc';
        router.get(
            route('purchase.vendors.index'),
            { ...filters, sort: key, direction: newDirection },
            { preserveState: true }
        );
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this vendor?')) {
            router.delete(route('purchase.vendors.destroy', id));
        }
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={
                <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                    <div className="flex items-center gap-4">
                        <h2 className="font-semibold text-xl text-gray-800 leading-tight">
                            Purchase Management
                        </h2>
                    </div>

                    <Link href={route('purchase.vendors.create')}>
                        <Button>
                            <Plus className="w-4 h-4 mr-2" /> Create Vendor
                        </Button>
                    </Link>
                </div>
            }
            navigationMenu={<PurchaseNavigationMenu />}
        >
            <Head title="Vendors" />

            <div className="py-8 max-w-7xl mx-auto sm:px-6 lg:px-8 space-y-6">
                <Card>
                    <CardHeader className="border-b px-6 py-4">
                        <div className="flex items-center justify-between">
                            <div>
                                <CardTitle className="flex items-center gap-2">
                                    <Building2 className="w-5 h-5" /> Vendors List
                                </CardTitle>
                                <CardDescription>Manage your suppliers and partners.</CardDescription>
                            </div>
                            <div className="w-full md:w-auto">
                                <SearchFilter
                                    value={search}
                                    onChange={setSearch}
                                    placeholder="Search vendor name, code..."
                                    className="w-full md:w-[300px]"
                                />
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent className="p-0">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <SortableColumn
                                        label="Code"
                                        sortKey="code"
                                        currentSort={filters.sort || ''}
                                        currentDirection={filters.direction || 'asc'}
                                        onSort={handleSort}
                                        className="pl-6 w-[150px]"
                                    />
                                    <SortableColumn
                                        label="Name"
                                        sortKey="name"
                                        currentSort={filters.sort || ''}
                                        currentDirection={filters.direction || 'asc'}
                                        onSort={handleSort}
                                    />
                                    <SortableColumn
                                        label="Contact Person"
                                        sortKey="contact_person"
                                        currentSort={filters.sort || ''}
                                        currentDirection={filters.direction || 'asc'}
                                        onSort={handleSort}
                                    />
                                    <SortableColumn
                                        label="Phone"
                                        sortKey="phone"
                                        currentSort={filters.sort || ''}
                                        currentDirection={filters.direction || 'asc'}
                                        onSort={handleSort}
                                    />
                                    <SortableColumn
                                        label="Email"
                                        sortKey="email"
                                        currentSort={filters.sort || ''}
                                        currentDirection={filters.direction || 'asc'}
                                        onSort={handleSort}
                                    />
                                    <TableCell className="text-right pr-6">Actions</TableCell>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {vendors.data.length > 0 ? (
                                    vendors.data.map((vendor) => (
                                        <TableRow key={vendor.id}>
                                            <TableCell className="font-medium pl-6">{vendor.code}</TableCell>
                                            <TableCell>{vendor.name}</TableCell>
                                            <TableCell>{vendor.contact_person || '-'}</TableCell>
                                            <TableCell>{vendor.phone || '-'}</TableCell>
                                            <TableCell>{vendor.email || '-'}</TableCell>
                                            <TableCell className="text-right pr-6">
                                                <div className="flex justify-end gap-2">
                                                    <Link href={route('purchase.vendors.edit', vendor.id)}>
                                                        <Button variant="ghost" size="icon" className="h-8 w-8 text-blue-600">
                                                            <Edit className="w-4 h-4" />
                                                        </Button>
                                                    </Link>
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        className="h-8 w-8 text-red-600"
                                                        onClick={() => handleDelete(vendor.id)}
                                                    >
                                                        <Trash2 className="w-4 h-4" />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    ))
                                ) : (
                                    <TableRow>
                                        <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                            No vendors found.
                                        </TableCell>
                                    </TableRow>
                                )}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
                <div className="mt-4">
                    <Pagination links={vendors.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
