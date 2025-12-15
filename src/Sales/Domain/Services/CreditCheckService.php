<?php

namespace TmrEcosystem\Sales\Domain\Services;

use TmrEcosystem\Customers\Infrastructure\Persistence\Models\Customer;
use TmrEcosystem\Sales\Infrastructure\Persistence\Models\SalesOrderModel;
use Exception;

class CreditCheckService
{
    /**
     * ตรวจสอบว่าลูกค้าสามารถสั่งซื้อยอดนี้ได้หรือไม่
     * * @param int $customerId
     * @param float $newOrderAmount ยอดเงินของออร์เดอร์ใหม่ (หรือยอดส่วนต่างที่เพิ่มขึ้น)
     * @return bool
     * @throws Exception
     */
    public function canPlaceOrder(int $customerId, float $newOrderAmount): bool
    {
        $customer = Customer::find($customerId);

        if (!$customer) {
            return true; // หรือ throw exception ตาม business rule
        }

        // 1. ถ้าติด Blacklist/Hold ห้ามขาย
        if ($customer->is_credit_hold) {
            throw new Exception("Credit Hold: ลูกค้ารายนี้ถูกระงับการสั่งซื้อชั่วคราว");
        }

        // 2. ถ้า Credit Limit เป็น 0 (Unlimited หรือ Cash Only - แล้วแต่ตกลง)
        // สมมติ: 0 คือ Unlimited ในเคสนี้
        if ($customer->credit_limit <= 0) {
            return true;
        }

        // 3. คำนวณยอดหนี้รวม = หนี้เก่า + ออร์เดอร์ที่รอส่ง (Open Orders) + ยอดใหม่
        // หมายเหตุ: outstanding_balance ควร update จากระบบบัญชี/การเงิน
        // แต่ที่นี่เราจะรวมยอด Sales Order ที่ยังไม่จบ (pending, confirmed) เข้าไปคิดด้วยเพื่อความชัวร์

        $pendingOrdersTotal = SalesOrderModel::where('customer_id', $customerId)
            ->whereIn('status', ['pending', 'confirmed']) // สถานะที่ยังไม่เกิด Invoice สมบูรณ์
            ->sum('grand_total');

        $totalExposure = $customer->outstanding_balance + $pendingOrdersTotal + $newOrderAmount;

        if ($totalExposure > $customer->credit_limit) {
            $diff = number_format($totalExposure - $customer->credit_limit, 2);
            throw new Exception("Credit Limit Exceeded: วงเงินไม่พอ (เกินวงเงิน {$diff} บาท)");
        }

        return true;
    }
}
