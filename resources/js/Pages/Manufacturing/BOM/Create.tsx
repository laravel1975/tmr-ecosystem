import React, { FormEventHandler } from 'react';
import { Head, useForm, Link } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import ProductCombobox from '@/Components/ProductCombobox';
import ManufacturingNavigationMenu from '../Partials/ManufacturingNavigationMenu';
import { Button } from '@/Components/ui/button';
import { Trash2, Plus, AlertCircle } from 'lucide-react';
import { Card, CardContent, CardHeader, CardTitle } from '@/Components/ui/card';
import { RadioGroup, RadioGroupItem } from "@/Components/ui/radio-group";
import { Label } from "@/Components/ui/label";
import { Separator } from '@/Components/ui/separator';

// Interface
interface Product {
    id: string;
    name: string;
    price: number;
    uom?: string;
}

interface BomComponent {
    item_uuid: string;
    quantity: number;
    waste_percent: number;
}

interface BomByProduct {
    item_uuid: string;
    quantity: number;
    uom?: string;
}

interface Props {
    auth: any;
    finishedGoods: Product[];
    rawMaterials: Product[];
    byProducts: Product[]; // เพิ่ม List สำหรับ By-product
}

export default function CreateBom({ auth, finishedGoods, rawMaterials, byProducts }: Props) {
    const { data, setData, post, processing, errors } = useForm({
        code: '',
        name: '',
        item_uuid: '',
        type: 'manufacture', // ✅ Req 2: Default type
        output_quantity: 1,
        components: [] as BomComponent[],
        byproducts: [] as BomByProduct[], // ✅ Req 3: By-products array
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('manufacturing.boms.store'));
    };

    // --- Helpers for Components ---
    const addComponent = () => setData('components', [...data.components, { item_uuid: '', quantity: 1, waste_percent: 0 }]);
    const removeComponent = (index: number) => {
        const list = [...data.components]; list.splice(index, 1); setData('components', list);
    };
    const updateComponent = (index: number, field: keyof BomComponent, value: any) => {
        const list = [...data.components];
        // @ts-ignore
        list[index][field] = value;
        setData('components', list);
    };

    // --- Helpers for By-products ---
    const addByproduct = () => setData('byproducts', [...data.byproducts, { item_uuid: '', quantity: 1 }]);
    const removeByproduct = (index: number) => {
        const list = [...data.byproducts]; list.splice(index, 1); setData('byproducts', list);
    };
    const updateByproduct = (index: number, field: keyof BomByProduct, value: any) => {
        const list = [...data.byproducts];
        // @ts-ignore
        list[index][field] = value;
        setData('byproducts', list);
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

                        {/* --- Card 1: Header Information --- */}
                        <Card className="bg-white shadow-sm border-0">
                            <CardHeader className="pb-3 border-b">
                                <CardTitle className="text-lg">ข้อมูลทั่วไป (General Information)</CardTitle>
                            </CardHeader>
                            <CardContent className="pt-6 grid grid-cols-1 md:grid-cols-2 gap-6">

                                {/* Code */}
                                <div>
                                    <InputLabel htmlFor="code" value="รหัสสูตร (BOM Code)" />
                                    <TextInput id="code" className="mt-1 block w-full"
                                        value={data.code} onChange={(e) => setData('code', e.target.value)}
                                        placeholder="e.g. BOM-FG001-V1" required />
                                    <InputError message={errors.code} className="mt-2" />
                                </div>

                                {/* Name */}
                                <div>
                                    <InputLabel htmlFor="name" value="ชื่อสูตร (Description)" />
                                    <TextInput id="name" className="mt-1 block w-full"
                                        value={data.name} onChange={(e) => setData('name', e.target.value)}
                                        placeholder="e.g. Standard Production" required />
                                    <InputError message={errors.name} className="mt-2" />
                                </div>

                                {/* Type Selection (Req 2) */}
                                <div className="md:col-span-2">
                                    <InputLabel className="mb-3">ประเภทสูตร (BOM Type)</InputLabel>
                                    <RadioGroup value={data.type} onValueChange={(val) => setData('type', val)} className="flex flex-col space-y-1">
                                        <div className="flex items-center space-x-3 bg-gray-50 p-3 rounded-lg border border-gray-100 cursor-pointer hover:border-indigo-200">
                                            <RadioGroupItem value="manufacture" id="r-man" />
                                            <div className="flex-1">
                                                <Label htmlFor="r-man" className="font-medium cursor-pointer">Manufacture this product</Label>
                                                <p className="text-xs text-gray-500">ใช้สำหรับการผลิตจริง มีการเบิกวัตถุดิบและรับสินค้าสำเร็จรูปเข้าคลัง</p>
                                            </div>
                                        </div>
                                        <div className="flex items-center space-x-3 bg-gray-50 p-3 rounded-lg border border-gray-100 cursor-pointer hover:border-indigo-200">
                                            <RadioGroupItem value="kit" id="r-kit" />
                                            <div className="flex-1">
                                                <Label htmlFor="r-kit" className="font-medium cursor-pointer">Kit / Phantom</Label>
                                                <p className="text-xs text-gray-500">ชุดสินค้าสำหรับขาย (ตัดสต็อกวัตถุดิบอัตโนมัติเมื่อขาย) หรือสูตรย่อย</p>
                                            </div>
                                        </div>
                                    </RadioGroup>
                                </div>

                                {/* Product Selection (Req 1: Finished Goods Only) */}
                                <div className="md:col-span-2">
                                    <InputLabel value="สินค้าที่ผลิต (Finished Good)" />
                                    <div className="mt-1">
                                        <ProductCombobox
                                            products={finishedGoods} // ✅ เฉพาะ is_manufactured=true
                                            value={data.item_uuid}
                                            onChange={(val) => {
                                                setData('item_uuid', val);
                                                const selected = finishedGoods.find(p => p.id === val);
                                                if (selected && !data.name) setData('name', `BOM: ${selected.name}`);
                                            }}
                                            placeholder="เลือกสินค้าสำเร็จรูป..."
                                            error={errors.item_uuid}
                                        />
                                    </div>
                                </div>

                                {/* Output Qty */}
                                <div>
                                    <InputLabel htmlFor="output_quantity" value="จำนวนที่ได้ (Output Quantity)" />
                                    <div className="flex items-center gap-2 mt-1">
                                        <TextInput type="number" id="output_quantity" step="0.0001" className="block w-full"
                                            value={data.output_quantity} onChange={(e) => setData('output_quantity', parseFloat(e.target.value))} required />
                                        <span className="text-sm text-gray-500">Units</span>
                                    </div>
                                    <InputError message={errors.output_quantity} className="mt-2" />
                                </div>

                            </CardContent>
                        </Card>

                        {/* --- Card 2: Components (Raw Materials) --- */}
                        <Card className="bg-white shadow-sm border-0">
                            <CardHeader className="flex flex-row items-center justify-between pb-3 border-b">
                                <CardTitle className="text-lg">ส่วนประกอบ / วัตถุดิบ (Components)</CardTitle>
                                <Button type="button" variant="outline" size="sm" onClick={addComponent}>
                                    <Plus className="w-4 h-4 mr-2" /> เพิ่มวัตถุดิบ
                                </Button>
                            </CardHeader>
                            <CardContent className="pt-6 space-y-4">
                                {data.components.length === 0 && (
                                    <div className="text-center py-8 bg-gray-50 rounded-lg border border-dashed text-gray-400 text-sm">
                                        ยังไม่มีรายการวัตถุดิบ กด "เพิ่มวัตถุดิบ" เพื่อเริ่มรายการ
                                    </div>
                                )}
                                {data.components.map((comp, index) => (
                                    <div key={index} className="flex flex-col md:flex-row gap-4 items-end p-4 bg-gray-50 rounded-lg border border-gray-100">
                                        <div className="flex-grow w-full">
                                            <InputLabel value={`วัตถุดิบ #${index + 1}`} className="mb-1" />
                                            <ProductCombobox
                                                products={rawMaterials} // ✅ Req 1: เฉพาะ is_component=true
                                                value={comp.item_uuid}
                                                onChange={(val) => updateComponent(index, 'item_uuid', val)}
                                                placeholder="เลือกวัตถุดิบ..."
                                                // @ts-ignore
                                                error={errors[`components.${index}.item_uuid`]}
                                            />
                                        </div>
                                        <div className="w-full md:w-32">
                                            <InputLabel value="ปริมาณ" className="mb-1" />
                                            <TextInput type="number" step="0.0001" className="w-full"
                                                value={comp.quantity} onChange={(e) => updateComponent(index, 'quantity', parseFloat(e.target.value))} />
                                        </div>
                                        <div className="w-full md:w-28">
                                            <InputLabel value="% สูญเสีย" className="mb-1" />
                                            <TextInput type="number" step="0.01" className="w-full"
                                                value={comp.waste_percent} onChange={(e) => updateComponent(index, 'waste_percent', parseFloat(e.target.value))} />
                                        </div>
                                        <div className="pb-1">
                                            <Button type="button" variant="destructive" size="icon" onClick={() => removeComponent(index)}>
                                                <Trash2 className="w-4 h-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                                <InputError message={errors.components} className="mt-2" />
                            </CardContent>
                        </Card>

                        {/* --- Card 3: By-products (Req 3) --- */}
                        {data.type === 'manufacture' && (
                            <Card className="bg-white shadow-sm border-0">
                                <CardHeader className="flex flex-row items-center justify-between pb-3 border-b">
                                    <CardTitle className="text-lg">ผลพลอยได้ (By-products)</CardTitle>
                                    <Button type="button" variant="outline" size="sm" onClick={addByproduct}>
                                        <Plus className="w-4 h-4 mr-2" /> เพิ่มผลพลอยได้
                                    </Button>
                                </CardHeader>
                                <CardContent className="pt-6 space-y-4">
                                    {data.byproducts.length === 0 && (
                                        <div className="text-center py-4 text-gray-400 text-sm">
                                            ไม่มีรายการผลพลอยได้
                                        </div>
                                    )}
                                    {data.byproducts.map((bp, index) => (
                                        <div key={index} className="flex flex-col md:flex-row gap-4 items-end p-4 bg-gray-50 rounded-lg border border-gray-100">
                                            <div className="flex-grow w-full">
                                                <InputLabel value={`ผลพลอยได้ #${index + 1}`} className="mb-1" />
                                                <ProductCombobox
                                                    products={byProducts} // ✅ Req 3
                                                    value={bp.item_uuid}
                                                    onChange={(val) => updateByproduct(index, 'item_uuid', val)}
                                                    placeholder="เลือกสินค้า..."
                                                />
                                            </div>
                                            <div className="w-full md:w-32">
                                                <InputLabel value="จำนวนที่ได้" className="mb-1" />
                                                <TextInput type="number" step="0.0001" className="w-full"
                                                    value={bp.quantity} onChange={(e) => updateByproduct(index, 'quantity', parseFloat(e.target.value))} />
                                            </div>
                                            <div className="pb-1">
                                                <Button type="button" variant="destructive" size="icon" onClick={() => removeByproduct(index)}>
                                                    <Trash2 className="w-4 h-4" />
                                                </Button>
                                            </div>
                                        </div>
                                    ))}
                                </CardContent>
                            </Card>
                        )}

                        {/* Actions */}
                        <div className="flex justify-end gap-4">
                            <Link href={route('manufacturing.boms.index')}>
                                <SecondaryButton disabled={processing}>Cancel</SecondaryButton>
                            </Link>
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving...' : 'Create BOM'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
