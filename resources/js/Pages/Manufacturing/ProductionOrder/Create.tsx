import React, { FormEventHandler } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import ProductCombobox from '@/Components/ProductCombobox';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import ManufacturingNavigationMenu from '../Partials/ManufacturingNavigationMenu';

interface Product {
    id: string;
    name: string;
    price: number;
}

interface Props {
    auth: any;
    products: Product[];
}

export default function CreateProductionOrder({ auth, products }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        item_uuid: '',
        planned_quantity: 1,
        planned_start_date: new Date().toISOString().split('T')[0], // Today
        planned_end_date: '',
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('manufacturing.production-orders.store'));
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">เปิดใบสั่งผลิต (New Production Order)</h2>}
            navigationMenu={<ManufacturingNavigationMenu />}
        >
            <Head title="New Production Order" />

            <div className="py-8 max-w-3xl mx-auto sm:px-6 lg:px-8">
                <form onSubmit={submit}>
                    <Card className="bg-white shadow-sm border-0">
                        <CardHeader className="pb-3 border-b">
                            <CardTitle>รายละเอียดการสั่งผลิต</CardTitle>
                        </CardHeader>
                        <CardContent className="pt-6 space-y-6">

                            {/* เลือกสินค้า */}
                            <div>
                                <InputLabel value="สินค้าที่ต้องการผลิต (Finished Good)" />
                                <div className="mt-1">
                                    <ProductCombobox
                                        products={products}
                                        value={data.item_uuid}
                                        onChange={(value) => setData('item_uuid', value)}
                                        placeholder="ค้นหาสินค้าที่มีสูตรการผลิต..."
                                        error={errors.item_uuid}
                                    />
                                </div>
                                <p className="text-sm text-gray-500 mt-1">
                                    ระบบจะเลือกใช้สูตรการผลิตมาตรฐาน (Default BOM) โดยอัตโนมัติ
                                </p>
                            </div>

                            {/* จำนวน */}
                            <div>
                                <InputLabel htmlFor="planned_quantity" value="จำนวนที่สั่งผลิต (Qty)" />
                                <TextInput
                                    id="planned_quantity"
                                    type="number"
                                    min="1"
                                    step="1"
                                    className="mt-1 block w-full"
                                    value={data.planned_quantity}
                                    onChange={(e) => setData('planned_quantity', parseFloat(e.target.value))}
                                    required
                                />
                                <InputError message={errors.planned_quantity} className="mt-2" />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                {/* วันที่เริ่ม */}
                                <div>
                                    <InputLabel htmlFor="planned_start_date" value="วันที่เริ่มผลิต (Start Date)" />
                                    <TextInput
                                        id="planned_start_date"
                                        type="date"
                                        className="mt-1 block w-full"
                                        value={data.planned_start_date}
                                        onChange={(e) => setData('planned_start_date', e.target.value)}
                                        required
                                    />
                                    <InputError message={errors.planned_start_date} className="mt-2" />
                                </div>

                                {/* วันที่กำหนดส่ง */}
                                <div>
                                    <InputLabel htmlFor="planned_end_date" value="กำหนดเสร็จ (Due Date)" />
                                    <TextInput
                                        id="planned_end_date"
                                        type="date"
                                        className="mt-1 block w-full"
                                        value={data.planned_end_date}
                                        onChange={(e) => setData('planned_end_date', e.target.value)}
                                    />
                                    <InputError message={errors.planned_end_date} className="mt-2" />
                                </div>
                            </div>

                        </CardContent>
                    </Card>

                    {/* Actions */}
                    <div className="flex items-center justify-end gap-4 mt-6">
                        <Link href={route('manufacturing.dashboard')}>
                            <SecondaryButton disabled={processing}>ยกเลิก</SecondaryButton>
                        </Link>
                        <PrimaryButton disabled={processing} className="min-w-[140px] justify-center">
                            {processing ? 'กำลังบันทึก...' : 'ยืนยันการผลิต'}
                        </PrimaryButton>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
