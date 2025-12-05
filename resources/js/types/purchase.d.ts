export interface Vendor {
    id: number;
    code: string;
    name: string;
}

export interface Product {
    id: string; // แก้ไข: เปลี่ยนจาก number เป็น string (UUID)
    part_number: string;
    name: string;
    price: number;
}

export interface PurchaseOrderItemForm {
    item_id: string; // แก้ไข: เปลี่ยนจาก number เป็น string (UUID)
    quantity: number;
    unit_price: number;
}

export interface PurchaseOrderForm {
    vendor_id: number | null;
    order_date: string;
    expected_delivery_date: string;
    notes: string;
    items: PurchaseOrderItemForm[];
}
