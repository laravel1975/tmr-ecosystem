import React, { useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Textarea } from '@/Components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/Components/ui/select';
import { Trash2, Plus } from 'lucide-react';
import { Vendor, Product, PurchaseOrderForm } from '@/types/purchase';
import { format } from 'date-fns';
import ProductCombobox from '@/Components/ProductCombobox';

interface Props {
    auth: any;
    vendors: Vendor[];
    products: Product[];
}

export default function Create({ auth, vendors, products }: Props) {
    const { data, setData, post, processing, errors } = useForm<PurchaseOrderForm>({
        vendor_id: null,
        order_date: format(new Date(), 'yyyy-MM-dd'),
        expected_delivery_date: '',
        notes: '',
        items: [
            { item_id: "", quantity: 1, unit_price: 0 }
        ],
    });

    const addItem = () => {
        setData('items', [
            ...data.items,
            { item_id: "", quantity: 1, unit_price: 0 }
        ]);
    };

    const removeItem = (index: number) => {
        const newItems = [...data.items];
        newItems.splice(index, 1);
        setData('items', newItems);
    };

    const updateItem = (index: number, field: keyof typeof data.items[0], value: any) => {
        const newItems = [...data.items];
        if (field === 'item_id') {
            const product = products.find(p => p.id === value);
            newItems[index].item_id = value;
            newItems[index].unit_price = product ? Number(product.price) : 0;
        } else {
            newItems[index] = { ...newItems[index], [field]: value };
        }
        setData('items', newItems);
    };

    const calculateSubtotal = () => {
        return data.items.reduce((acc, item) => acc + (item.quantity * item.unit_price), 0);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('purchase.orders.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">Create Purchase Order</h2>}
        >
            <Head title="Create Purchase Order" />

            <div className="py-12">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <form onSubmit={handleSubmit}>
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            {/* Vendor Details */}
                            <Card className="md:col-span-2">
                                <CardHeader>
                                    <CardTitle>Order Details</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="grid grid-cols-2 gap-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="vendor_id">Vendor</Label>
                                            <Select
                                                onValueChange={(val) => setData('vendor_id', Number(val))}
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select Vendor" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {vendors.map((vendor) => (
                                                        <SelectItem key={vendor.id} value={String(vendor.id)}>
                                                            {vendor.code} - {vendor.name}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {errors.vendor_id && <p className="text-red-500 text-sm">{errors.vendor_id}</p>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="order_date">Order Date</Label>
                                            <Input
                                                type="date"
                                                id="order_date"
                                                value={data.order_date}
                                                onChange={(e) => setData('order_date', e.target.value)}
                                            />
                                            {errors.order_date && <p className="text-red-500 text-sm">{errors.order_date}</p>}
                                        </div>

                                        <div className="space-y-2">
                                            <Label htmlFor="delivery_date">Expected Delivery</Label>
                                            <Input
                                                type="date"
                                                id="delivery_date"
                                                value={data.expected_delivery_date}
                                                onChange={(e) => setData('expected_delivery_date', e.target.value)}
                                            />
                                            {errors.expected_delivery_date && <p className="text-red-500 text-sm">{errors.expected_delivery_date}</p>}
                                        </div>
                                    </div>

                                    <div className="space-y-2">
                                        <Label htmlFor="notes">Notes</Label>
                                        <Textarea
                                            id="notes"
                                            value={data.notes}
                                            onChange={(e) => setData('notes', e.target.value)}
                                            placeholder="Additional information..."
                                        />
                                    </div>
                                </CardContent>
                            </Card>

                            {/* Summary Card */}
                            <Card>
                                <CardHeader>
                                    <CardTitle>Summary</CardTitle>
                                </CardHeader>
                                <CardContent className="space-y-4">
                                    <div className="flex justify-between">
                                        <span>Subtotal:</span>
                                        <span className="font-bold">{calculateSubtotal().toLocaleString()} THB</span>
                                    </div>
                                    <div className="flex justify-between">
                                        <span>VAT (7%):</span>
                                        <span className="font-bold">{(calculateSubtotal() * 0.07).toLocaleString()} THB</span>
                                    </div>
                                    <div className="border-t pt-2 flex justify-between text-lg">
                                        <span>Total:</span>
                                        <span className="font-bold text-primary">{(calculateSubtotal() * 1.07).toLocaleString()} THB</span>
                                    </div>
                                    <Button type="submit" className="w-full mt-4" disabled={processing}>
                                        {processing ? 'Creating...' : 'Create Order'}
                                    </Button>
                                </CardContent>
                            </Card>
                        </div>

                        {/* Items Table */}
                        <Card>
                            <CardHeader className="flex flex-row items-center justify-between">
                                <CardTitle>Order Items</CardTitle>
                                <Button type="button" variant="outline" size="sm" onClick={addItem}>
                                    <Plus className="w-4 h-4 mr-2" /> Add Item
                                </Button>
                            </CardHeader>
                            <CardContent>
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-[40%]">Product</TableHead>
                                            <TableHead>Quantity</TableHead>
                                            <TableHead>Unit Price</TableHead>
                                            <TableHead>Total</TableHead>
                                            <TableHead className="w-[50px]"></TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {data.items.map((item, index) => (
                                            <TableRow key={index}>
                                                <TableCell>
                                                    <ProductCombobox
                                                        products={products}
                                                        value={item.item_id}
                                                        onChange={(val) => updateItem(index, 'item_id', val)}
                                                        placeholder="Select product..."
                                                    />

                                                    {errors[`items.${index}.item_id` as keyof typeof errors] &&
                                                        <p className="text-red-500 text-xs mt-1">Required</p>
                                                    }
                                                </TableCell>
                                                <TableCell>
                                                    <Input
                                                        type="number"
                                                        min="1"
                                                        value={item.quantity}
                                                        onChange={(e) => updateItem(index, 'quantity', Number(e.target.value))}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    <Input
                                                        type="number"
                                                        min="0"
                                                        step="0.01"
                                                        value={item.unit_price}
                                                        onChange={(e) => updateItem(index, 'unit_price', Number(e.target.value))}
                                                    />
                                                </TableCell>
                                                <TableCell>
                                                    {(item.quantity * item.unit_price).toLocaleString()}
                                                </TableCell>
                                                <TableCell>
                                                    {data.items.length > 1 && (
                                                        <Button
                                                            type="button"
                                                            variant="ghost"
                                                            size="icon"
                                                            className="text-red-500"
                                                            onClick={() => removeItem(index)}
                                                        >
                                                            <Trash2 className="w-4 h-4" />
                                                        </Button>
                                                    )}
                                                </TableCell>
                                            </TableRow>
                                        ))}
                                    </TableBody>
                                </Table>
                                {errors.items && <p className="text-red-500 text-sm mt-2">{errors.items}</p>}
                            </CardContent>
                        </Card>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
