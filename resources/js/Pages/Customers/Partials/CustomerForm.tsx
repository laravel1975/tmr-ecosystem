import React from 'react';
import { useForm } from '@inertiajs/react';
import { Button } from "@/Components/ui/button";
import { Input } from "@/Components/ui/input";
import { Label } from "@/Components/ui/label";
import { Checkbox } from "@/Components/ui/checkbox";
import { Card, CardContent } from "@/Components/ui/card";
import { Textarea } from "@/Components/ui/textarea";

interface Props {
    customer?: any;
    isEditing?: boolean;
    onSubmit: (data: any) => void;
    processing: boolean;
    errors: any;
    data: any;
    setData: (key: string, value: any) => void;
}

export default function CustomerForm({ isEditing, onSubmit, processing, errors, data, setData }: Props) {

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        onSubmit(data);
    };

    return (
        <form onSubmit={handleSubmit} className="space-y-6">
            <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                {/* ข้อมูลพื้นฐาน */}
                <Card>
                    <CardContent className="pt-6 space-y-4">
                        <h3 className="font-semibold text-lg mb-4">General Information</h3>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="code">Customer Code</Label>
                                <Input
                                    id="code"
                                    value={data.code}
                                    onChange={e => setData('code', e.target.value)}
                                    placeholder="CUST-001"
                                />
                                {errors.code && <div className="text-red-500 text-sm mt-1">{errors.code}</div>}
                            </div>
                            <div>
                                <Label htmlFor="tax_id">Tax ID</Label>
                                <Input
                                    id="tax_id"
                                    value={data.tax_id}
                                    onChange={e => setData('tax_id', e.target.value)}
                                />
                                {errors.tax_id && <div className="text-red-500 text-sm mt-1">{errors.tax_id}</div>}
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="name">Company/Customer Name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                            />
                            {errors.name && <div className="text-red-500 text-sm mt-1">{errors.name}</div>}
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email" type="email"
                                    value={data.email}
                                    onChange={e => setData('email', e.target.value)}
                                />
                            </div>
                            <div>
                                <Label htmlFor="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    value={data.phone}
                                    onChange={e => setData('phone', e.target.value)}
                                />
                            </div>
                        </div>

                        <div>
                            <Label htmlFor="address">Address</Label>
                            <Textarea
                                id="address"
                                value={data.address}
                                onChange={e => setData('address', e.target.value)}
                            />
                        </div>
                    </CardContent>
                </Card>

                {/* ข้อมูลทางการเงิน (Financial Control) */}
                <Card className="border-orange-200 bg-orange-50/20">
                    <CardContent className="pt-6 space-y-4">
                        <h3 className="font-semibold text-lg mb-4 text-orange-800">Financial Control</h3>

                        <div>
                            <Label htmlFor="credit_limit">Credit Limit (THB)</Label>
                            <Input
                                id="credit_limit" type="number" step="0.01"
                                value={data.credit_limit}
                                onChange={e => setData('credit_limit', e.target.value)}
                                className="font-mono text-lg"
                            />
                            <p className="text-xs text-gray-500 mt-1">Set to 0 for unlimited or cash-only.</p>
                            {errors.credit_limit && <div className="text-red-500 text-sm mt-1">{errors.credit_limit}</div>}
                        </div>

                        <div>
                            <Label htmlFor="credit_term_days">Credit Term (Days)</Label>
                            <Input
                                id="credit_term_days" type="number"
                                value={data.credit_term_days}
                                onChange={e => setData('credit_term_days', e.target.value)}
                            />
                            {errors.credit_term_days && <div className="text-red-500 text-sm mt-1">{errors.credit_term_days}</div>}
                        </div>

                        <div className="flex items-center space-x-2 pt-4">
                            <Checkbox
                                id="is_credit_hold"
                                checked={data.is_credit_hold}
                                onCheckedChange={(checked) => setData('is_credit_hold', checked)}
                            />
                            <Label htmlFor="is_credit_hold" className="text-red-600 font-bold">
                                Credit Hold (ระงับการขายชั่วคราว)
                            </Label>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <div className="flex justify-end gap-2">
                <Button type="button" variant="outline" onClick={() => window.history.back()}>Cancel</Button>
                <Button type="submit" disabled={processing}>
                    {isEditing ? 'Update Customer' : 'Create Customer'}
                </Button>
            </div>
        </form>
    );
}
