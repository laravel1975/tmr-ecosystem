import React from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Textarea } from '@/Components/ui/textarea';
import InputError from '@/Components/InputError';
import { ArrowLeft } from 'lucide-react';
import PurchaseNavigationMenu from '../Partials/PurchaseNavigationMenu';

export default function Create({ auth }: { auth: any }) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        tax_id: '',
        email: '',
        phone: '',
        contact_person: '',
        address: '',
        credit_term_days: 30,
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('purchase.vendors.store'));
    };

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<PurchaseNavigationMenu />}>
            <Head title="Create Vendor" />
            <div className="py-8 max-w-2xl mx-auto sm:px-6 lg:px-8">
                <Link href={route('purchase.vendors.index')} className="flex items-center text-gray-500 mb-4 hover:text-gray-700">
                    <ArrowLeft className="w-4 h-4 mr-2" /> Back to Vendors
                </Link>
                <Card>
                    <CardHeader>
                        <CardTitle>Create New Vendor</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <form onSubmit={submit} className="space-y-4">
                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="code">Vendor Code</Label>
                                    <Input
                                        id="code"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                        placeholder="e.g. VEN-001"
                                        required
                                    />
                                    <InputError message={errors.code} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="tax_id">Tax ID</Label>
                                    <Input
                                        id="tax_id"
                                        value={data.tax_id}
                                        onChange={(e) => setData('tax_id', e.target.value)}
                                    />
                                    <InputError message={errors.tax_id} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="name">Company Name</Label>
                                <Input
                                    id="name"
                                    value={data.name}
                                    onChange={(e) => setData('name', e.target.value)}
                                    required
                                />
                                <InputError message={errors.name} />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="contact_person">Contact Person</Label>
                                    <Input
                                        id="contact_person"
                                        value={data.contact_person}
                                        onChange={(e) => setData('contact_person', e.target.value)}
                                    />
                                    <InputError message={errors.contact_person} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="phone">Phone</Label>
                                    <Input
                                        id="phone"
                                        value={data.phone}
                                        onChange={(e) => setData('phone', e.target.value)}
                                    />
                                    <InputError message={errors.phone} />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <Label htmlFor="email">Email</Label>
                                    <Input
                                        id="email"
                                        type="email"
                                        value={data.email}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                    <InputError message={errors.email} />
                                </div>
                                <div className="space-y-2">
                                    <Label htmlFor="credit_term_days">Credit Term (Days)</Label>
                                    <Input
                                        id="credit_term_days"
                                        type="number"
                                        value={data.credit_term_days}
                                        onChange={(e) => setData('credit_term_days', Number(e.target.value))}
                                    />
                                    <InputError message={errors.credit_term_days} />
                                </div>
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="address">Address</Label>
                                <Textarea
                                    id="address"
                                    value={data.address}
                                    onChange={(e) => setData('address', e.target.value)}
                                    rows={3}
                                />
                                <InputError message={errors.address} />
                            </div>

                            <div className="flex justify-end pt-4">
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Saving...' : 'Create Vendor'}
                                </Button>
                            </div>
                        </form>
                    </CardContent>
                </Card>
            </div>
        </AuthenticatedLayout>
    );
}
