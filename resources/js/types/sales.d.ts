export interface Product {
    id: string;
    name: string;
    price: number;
    stock: number;
}

export interface Customer {
    id: string;
    name: string;
}

export interface OrderItemRow {
    product_id: string;
    description: string;
    quantity: number;
    unit_price: number;
    total: number;
}

export interface SalesOrderItem {
    id: number;
    product_id: string;
    product_name: string;
    quantity: number;
    qty_shipped?: number;
    unit_price: number;
    subtotal: number;
}

export interface SalesOrder {
    id: string;
    order_number: string;
    status: 'draft' | 'confirmed' | 'processing' | 'completed' | 'cancelled';
    total_amount: number;
    currency: string;
    note?: string;
    payment_terms?: string;
    created_at: string;

    // Relations
    customer_id: string;
    customer?: {
        id: string;
        name: string;
        code: string;
        address?: string;
        phone?: string;
        email?: string;
    };

    // ✅ เพิ่ม Salesperson
    salesperson_id?: number | string;
    salesperson?: {
        id: number;
        name: string;
        email?: string;
    };

    items: SalesOrderItem[];

    // Extra fields for UI
    picking_count?: number;
    timeline?: any[];
}
