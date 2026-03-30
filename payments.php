<?php


require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/orders.php';


function confirmPayment(int $orderId, string $method, ?string $mpesaCode, int $recordedBy): array {
    $db = getDB();

    $stmt = $db->prepare("SELECT * FROM payments WHERE order_id = ?");
    $stmt->execute([$orderId]);
    $payment = $stmt->fetch();

    if (!$payment) {
        return ['success' => false, 'message' => 'Payment record not found.'];
    }

    if ($payment['payment_status'] === 'confirmed') {
        return ['success' => false, 'message' => 'Payment already confirmed.'];
    }

    if ($method === 'mpesa' && empty($mpesaCode)) {
        return ['success' => false, 'message' => 'M-Pesa transaction code is required.'];
    }

    $stmt = $db->prepare(
        "UPDATE payments
         SET method = ?, mpesa_code = ?, payment_status = 'confirmed',
             paid_at = NOW(), recorded_by = ?
         WHERE order_id = ?"
    );
    $stmt->execute([$method, $mpesaCode, $recordedBy, $orderId]);

    
    $order = getOrderById($orderId);
    if ($order) {
        notifyUser($order['customer_id'],
            "Payment for order #{$orderId} confirmed. Amount: KES {$order['total_amount']}.",
            'payment');
    }

    return ['success' => true, 'message' => 'Payment confirmed successfully.'];
}


function getVendorPaymentSummary(int $vendorId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT
             COUNT(p.payment_id)                                     AS total_transactions,
             SUM(CASE WHEN p.payment_status='confirmed' THEN p.amount ELSE 0 END) AS total_collected,
             SUM(CASE WHEN p.method='mpesa' AND p.payment_status='confirmed' THEN p.amount ELSE 0 END) AS mpesa_total,
             SUM(CASE WHEN p.method='cash'  AND p.payment_status='confirmed' THEN p.amount ELSE 0 END) AS cash_total,
             COUNT(CASE WHEN p.payment_status='pending' THEN 1 END)  AS pending_count
         FROM water_orders o
         JOIN payments p ON o.order_id = p.order_id
         WHERE o.vendor_id = ?"
    );
    $stmt->execute([$vendorId]);
    return $stmt->fetch();
}
