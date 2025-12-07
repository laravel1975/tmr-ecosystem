import React, { FormEventHandler } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import ProductCombobox from '@/Components/ProductCombobox'; // ✅ นำ Component ที่คุณให้มาใช้
import { Button } from '@/Components/ui/button';
import { Trash2, Plus } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { Separator } from '@/Components/ui/separator';
import ManufacturingNavigationMenu from '../Partials/ManufacturingNavigationMenu';

// Interface สำหรับข้อมูลสินค้าที่รับมาจาก Backend (Controller)
interface Product {
    id: string;
    name: string;
    price: number;
    stock?: number;
}

// Interface สำหรับแถววัตถุดิบในฟอร์ม
interface BomComponent {
    item_uuid: string;
    quantity: number;
    waste_percent: number;
}

interface Props {
    auth: any;
    products: Product[]; // รายการสินค้าทั้งหมดที่จะส่งมาให้ Combobox
}

export default function CreateBom({ auth, products }: Props) {
    // กำหนด Form State
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        item_uuid: '', // Finished Good
        output_quantity: 1,
        components: [] as BomComponent[], // Raw Materials array
    });

    // Handle Submit
    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        // ส่งข้อมูลไปยัง Route store (ต้องตรวจสอบ route name ใน routes/manufacturing.php ของคุณ)
        post(route('manufacturing.boms.store'));
    };

    // Helper: เพิ่มแถววัตถุดิบ
    const addComponent = () => {
        setData('components', [
            ...data.components,
            { item_uuid: '', quantity: 1, waste_percent: 0 }
        ]);
    };

    // Helper: ลบแถววัตถุดิบ
    const removeComponent = (index: number) => {
        const newComponents = [...data.components];
        newComponents.splice(index, 1);
        setData('components', newComponents);
    };

    // Helper: อัปเดตข้อมูลในแถววัตถุดิบ
    const updateComponent = (index: number, field: keyof BomComponent, value: any) => {
        const newComponents = [...data.components];
        // @ts-ignore
        newComponents[index][field] = value;
        setData('components', newComponents);
    };

    return (
        <AuthenticatedLayout
            user={auth.user}
            header={<h2 className="font-semibold text-xl text-gray-800 leading-tight">สร้างสูตรการผลิต (New BOM)</h2>}
            navigationMenu={<ManufacturingNavigationMenu />}
        >
            <Head title="Create BOM" />

            <div className="py-8">
                <div className="max-w-7xl mx-auto sm:px-6 lg:px-8">
                    <form onSubmit={submit} className="space-y-6">

                        {/* ------------------------------------------- */}
                        {/* Section 1: ข้อมูลทั่วไป (Header Info)       */}
                        {/* ------------------------------------------- */}
                        <Card className="bg-white shadow-sm border-0">
                            <CardHeader className="pb-3 border-b">
                                <CardTitle className="text-lg">ข้อมูลสูตรการผลิต (Header)</CardTitle>
                            </CardHeader>
                            <CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                                {/* รหัสสูตร */}
                                <div>
                                    <InputLabel htmlFor="code" value="รหัสสูตร (BOM Code)" />
                                    <TextInput
                                        id="code"
                                        className="mt-1 block w-full"
                                        value={data.code}
                                        onChange={(e) => setData('code', e.target.value)}
                                        placeholder="เช่น BOM-FG-001"
                                        required
                                    />
                                    <InputError message={errors.code} className="mt-2" />
                                </div>

                                {/* ชื่อสูตร */}
                                <div>
                                    <InputLabel htmlFor="name" value="ชื่อสูตร (Description)" />
                                    <TextInput
                                        id="name"
                                        className="mt-1 block w-full"
                                        value={data.name}
                                        onChange={(e) => setData('name', e.target.value)}
                                        placeholder="เช่น สูตรมาตรฐาน v1.0"
                                        required
                                    />
                                    <InputError message={errors.name} className="mt-2" />
                                </div>

                                {/* เลือกสินค้าที่จะผลิต (Finished Good) */}
                                <div className="md:col-span-2">
                                    <InputLabel value="สินค้าสำเร็จรูป (Finished Good)" />
                                    <div className="mt-1">
                                        <ProductCombobox
                                            products={products}
                                            value={data.item_uuid}
                                            onChange={(value) => {
                                                setData('item_uuid', value);
                                                // Option: Auto generate BOM Name based on product
                                                const selected = products.find(p => p.id === value);
                                                if (selected && !data.name) {
                                                    setData('name', `สูตรผลิต ${selected.name}`);
                                                }
                                            }}
                                            placeholder="เลือกสินค้าที่ต้องการผลิต..."
                                            error={errors.item_uuid}
                                        />
                                    </div>
                                    <p className="text-sm text-gray-500 mt-1">เลือกสินค้าที่คุณต้องการตั้งสูตรการผลิตนี้</p>
                                </div>

                                {/* จำนวนผลผลิตที่ได้ */}
                                <div>
                                    <InputLabel htmlFor="output_quantity" value="จำนวนที่ผลิตได้ (Output Qty)" />
                                    <div className="flex items-center gap-2 mt-1">
                                        <TextInput
                                            id="output_quantity"
                                            type="number"
                                            step="0.0001"
                                            className="block w-full"
                                            value={data.output_quantity}
                                            onChange={(e) => setData('output_quantity', parseFloat(e.target.value))}
                                            required
                                        />
                                        <span className="text-gray-500 text-sm whitespace-nowrap">หน่วย (Units)</span>
                                    </div>
                                    <InputError message={errors.output_quantity} className="mt-2" />
                                </div>

                            </CardContent>
                        </Card>

                        {/* ------------------------------------------- */}
                        {/* Section 2: รายการวัตถุดิบ (Components)      */}
                        {/* ------------------------------------------- */}
                        <Card className="bg-white shadow-sm border-0">
                            <CardHeader className="flex flex-row items-center justify-between pb-3 border-b">
                                <CardTitle className="text-lg">ส่วนประกอบ / วัตถุดิบ (Components)</CardTitle>
                                <Button type="button" variant="outline" size="sm" onClick={addComponent}>
                                    <Plus className="w-4 h-4 mr-2" /> เพิ่มรายการวัตถุดิบ
                                </Button>
                            </CardHeader>
                            <CardContent className="pt-6">
                                <div className="space-y-4">
                                    {data.components.length === 0 && (
                                        <div className="text-center py-10 bg-slate-50 rounded-lg border-2 border-dashed border-slate-200">
                                            <p className="text-gray-500">ยังไม่มีรายการวัตถุดิบ</p>
                                            <p className="text-sm text-gray-400">กดปุ่ม "เพิ่มรายการวัตถุดิบ" เพื่อเริ่มกำหนดสูตร</p>
                                        </div>
                                    )}

                                    {data.components.map((comp, index) => (
                                        <div key={index} className="flex flex-col md:flex-row gap-4 items-start md:items-end p-4 bg-gray-50 rounded-lg border border-gray-100 relative group">

                                            {/* ลำดับ */}
                                            <div className="hidden md:block pb-3 text-gray-400 font-medium w-8">
                                                #{index + 1}
                                            </div>

                                            {/* เลือกวัตถุดิบ */}
                                            <div className="w-full md:flex-grow">
                                                <InputLabel value="วัตถุดิบ (Raw Material)" className="mb-1" />
                                                <ProductCombobox
                                                    products={products}
                                                    value={comp.item_uuid}
                                                    onChange={(value) => updateComponent(index, 'item_uuid', value)}
                                                    placeholder="เลือกวัตถุดิบ..."
                                                    // @ts-ignore
                                                    error={errors[`components.${index}.item_uuid`]}
                                                />
                                            </div>

                                            {/* ปริมาณ */}
                                            <div className="w-full md:w-32">
                                                <InputLabel value="ปริมาณ (Qty)" className="mb-1" />
                                                <TextInput
                                                    type="number"
                                                    step="0.0001"
                                                    className="w-full"
                                                    value={comp.quantity}
                                                    onChange={(e) => updateComponent(index, 'quantity', parseFloat(e.target.value))}
                                                />
                                            </div>

                                            {/* % สูญเสีย */}
                                            <div className="w-full md:w-28">
                                                <InputLabel value="% สูญเสีย (Waste)" className="mb-1" />
                                                <TextInput
                                                    type="number"
                                                    step="0.01"
                                                    min="0"
                                                    max="100"
                                                    className="w-full"
                                                    value={comp.waste_percent}
                                                    onChange={(e) => updateComponent(index, 'waste_percent', parseFloat(e.target.value))}
                                                />
                                            </div>

                                            {/* ปุ่มลบ */}
                                            <div className="w-full md:w-auto flex justify-end md:pb-1">
                                                <Button
                                                    type="button"
                                                    variant="destructive"
                                                    size="icon"
                                                    onClick={() => removeComponent(index)}
                                                    className="h-10 w-10"
                                                >
                                                    <Trash2 className="w-4 h-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}

                                    <InputError message={errors.components} className="mt-2" />
                                </div>
                            </CardContent>
                        </Card>

                        {/* ------------------------------------------- */}
                        {/* Form Actions                                */}
                        {/* ------------------------------------------- */}
                        <div className="flex items-center justify-end gap-4">
                            <Link href={route('manufacturing.dashboard')}>
                                <SecondaryButton disabled={processing}>
                                    ยกเลิก
                                </SecondaryButton>
                            </Link>

                            <PrimaryButton disabled={processing} className="min-w-[120px] justify-center">
                                {processing ? 'กำลังบันทึก...' : 'บันทึกสูตรการผลิต'}
                            </PrimaryButton>
                        </div>

                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
