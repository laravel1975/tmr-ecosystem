import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CustomerForm from './Partials/CustomerForm';
import SalesNavigationMenu from '../Sales/Partials/SalesNavigationMenu';

export default function CustomerEdit({ auth, customer }: any) {
    const { data, setData, put, processing, errors } = useForm({
        code: customer.code,
        name: customer.name,
        email: customer.email || '',
        phone: customer.phone || '',
        address: customer.address || '',
        tax_id: customer.tax_id || '',
        credit_limit: customer.credit_limit,
        credit_term_days: customer.credit_term_days,
        is_credit_hold: Boolean(customer.is_credit_hold)
    });

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<SalesNavigationMenu />}>
            <Head title="Edit Customer" />
            <div className="max-w-4xl mx-auto p-6">
                <h1 className="text-2xl font-bold mb-6">Edit Customer: {customer.code}</h1>
                <CustomerForm
                    isEditing
                    data={data} setData={setData} errors={errors}
                    processing={processing} onSubmit={() => put(route('customers.update', customer.id))}
                />
            </div>
        </AuthenticatedLayout>
    );
}
