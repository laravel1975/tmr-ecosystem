<?php

namespace TmrEcosystem\Sales\Domain\Services;

use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use Exception;

class CreditCheckService
{
    /**
     * ตรวจสอบว่าลูกค้าสามารถสั่งซื้อยอดนี้ได้หรือไม่
     * * @param string $customerId  // ✅ แก้ Docblock เป็น string
     * @param float $newOrderAmount
     * @return bool
     * @throws Exception
     */
    // ✅ แก้ Type Hint จาก int เป็น string ให้รองรับ UUID
    public function canPlaceOrder(string $customerId, float $newOrderAmount): bool
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            // ถ้าไม่เจอลูกค้า อาจจะปล่อยผ่านหรือ Error แล้วแต่ Business Logic
            // ในที่นี้ปล่อยผ่านเพื่อให้ทำงานต่อได้ (หรืออาจจะเป็นลูกค้า Walk-in)
            return true;
        }

        // 1. ถ้าติด Blacklist/Hold ห้ามขาย
        if ($customer->is_credit_hold) {
            throw new Exception("Credit Hold: ลูกค้ารายนี้ถูกระงับการสั่งซื้อชั่วคราว");
        }

        // 2. ถ้า Credit Limit เป็น 0 (Unlimited หรือ Cash Only)
        if ($customer->credit_limit <= 0) {
            return true;
        }

        // 3. คำนวณยอดหนี้รวม
        // ดึงยอดจากออร์เดอร์ที่ยังไม่เสร็จสิ้น (Pending/Confirmed)
        $pendingOrdersTotal = SalesOrderModel::where('customer_id', $customerId)
            ->whereIn('status', ['draft', 'pending', 'confirmed']) // ✅ เพิ่ม draft เผื่อด้วยถ้าต้องการนับ
            ->sum('total_amount'); // ✅ แก้จาก 'grand_total' เป็น 'total_amount' ให้ตรงกับ DB

        // ยอดหนี้ปัจจุบัน (จากบัญชี) + ยอดออร์เดอร์ค้างส่ง + ยอดที่จะซื้อใหม่
        $totalExposure = $customer->outstanding_balance + $pendingOrdersTotal + $newOrderAmount;

        if ($totalExposure > $customer->credit_limit) {
            $diff = number_format($totalExposure - $customer->credit_limit, 2);
            $limit = number_format($customer->credit_limit, 2);
            throw new Exception("Credit Limit Exceeded: วงเงินไม่พอ (วงเงิน: {$limit}, ยอดรวมหลังสั่งซื้อ: " . number_format($totalExposure, 2) . ", เกิน: {$diff} บาท)");
        }

        return true;
    }
}
