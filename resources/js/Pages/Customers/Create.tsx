import React from 'react';
import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import CustomerForm from './Partials/CustomerForm';
import SalesNavigationMenu from '../Sales/Partials/SalesNavigationMenu';

export default function CustomerCreate({ auth }: any) {
    const { data, setData, post, processing, errors } = useForm({
        code: '', name: '', email: '', phone: '', address: '', tax_id: '',
        credit_limit: 0, credit_term_days: 30, is_credit_hold: false
    });

    return (
        <AuthenticatedLayout user={auth.user} navigationMenu={<SalesNavigationMenu />}>
            <Head title="Create Customer" />
            <div className="max-w-4xl mx-auto p-6">
                <h1 className="text-2xl font-bold mb-6">Create New Customer</h1>
                <CustomerForm
                    data={data} setData={setData} errors={errors}
                    processing={processing} onSubmit={() => post(route('customers.store'))}
                />
            </div>
        </AuthenticatedLayout>
    );
}
