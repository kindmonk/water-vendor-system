<?php
// ============================================================
//  WVMS - Orders Controller
// ============================================================

require_once __DIR__ . '/../config/db.php';

/**
 * Place a new water order (Customer)
 */
function placeOrder(array $data): array {
    $db = getDB();

    // Get vendor pricing
    $stmt = $db->prepare("SELECT * FROM vendors WHERE vendor_id = ? AND is_available = 1");
    $stmt->execute([$data['vendor_id']]);
    $vendor = $stmt->fetch();

    if (!$vendor) {
        return ['success' => false, 'message' => 'Vendor not available.'];
    }

    if ($data['quantity_litres'] < $vendor['min_order_litres']) {
        return [
            'success' => false,
            'message' => "Minimum order is {$vendor['min_order_litres']} litres."
        ];
    }

    $total = $data['quantity_litres'] * $vendor['price_per_litre'];

    $stmt = $db->prepare(
        "INSERT INTO water_orders
            (customer_id, vendor_id, quantity_litres, unit_price, total_amount,
             delivery_address, delivery_time, notes)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)"
    );
    $stmt->execute([
        $data['customer_id'],
        $data['vendor_id'],
        $data['quantity_litres'],
        $vendor['price_per_litre'],
        $total,
        $data['delivery_address'],
        $data['delivery_time'] ?? null,
        $data['notes']         ?? null,
    ]);

    $orderId = $db->lastInsertId();

    // Notify vendor
    notifyUser($vendor['user_id'],
        "New order #{$orderId}: {$data['quantity_litres']}L requested.", 'order');

    return ['success' => true, 'order_id' => $orderId, 'total' => $total];
}

/**
 * Get orders for a customer
 */
function getCustomerOrders(int $customerId): array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT o.*, v.business_name, p.method, p.payment_status, p.mpesa_code
         FROM water_orders o
         JOIN vendors v  ON o.vendor_id  = v.vendor_id
         LEFT JOIN payments p ON o.order_id = p.order_id
         WHERE o.customer_id = ?
         ORDER BY o.created_at DESC"
    );
    $stmt->execute([$customerId]);
    return $stmt->fetchAll();
}

/**
 * Get orders for a vendor
 */
function getVendorOrders(int $vendorId, string $status = ''): array {
    $db = getDB();
    $sql = "SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone,
                   p.method, p.payment_status
            FROM water_orders o
            JOIN users u ON o.customer_id = u.user_id
            LEFT JOIN payments p ON o.order_id = p.order_id
            WHERE o.vendor_id = ?";
    $params = [$vendorId];

    if ($status) {
        $sql .= " AND o.status = ?";
        $params[] = $status;
    }

    $sql .= " ORDER BY o.created_at DESC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Update order status (Vendor/Admin)
 */
function updateOrderStatus(int $orderId, string $newStatus, int $actorId): array {
    $allowed = ['pending', 'accepted', 'in_transit', 'delivered', 'cancelled'];
    if (!in_array($newStatus, $allowed)) {
        return ['success' => false, 'message' => 'Invalid status.'];
    }

    $db = getDB();

    // Fetch order + customer info
    $stmt = $db->prepare(
        "SELECT o.*, u.full_name FROM water_orders o
         JOIN users u ON o.customer_id = u.user_id
         WHERE o.order_id = ?"
    );
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();

    if (!$order) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    $stmt = $db->prepare("UPDATE water_orders SET status = ? WHERE order_id = ?");
    $stmt->execute([$newStatus, $orderId]);

    // If delivered, stamp delivery time
    if ($newStatus === 'delivered') {
        $db->prepare("UPDATE deliveries SET delivered_at = NOW() WHERE order_id = ?")
           ->execute([$orderId]);
        $db->prepare("UPDATE payments SET payment_status = 'confirmed', paid_at = NOW(), recorded_by = ?
                      WHERE order_id = ? AND method = 'cash'")
           ->execute([$actorId, $orderId]);
    }

    // Notify customer
    $messages = [
        'accepted'   => "Your order #{$orderId} has been accepted! It will be delivered soon.",
        'in_transit' => "Your order #{$orderId} is on the way!",
        'delivered'  => "Your order #{$orderId} has been delivered. Thank you!",
        'cancelled'  => "Your order #{$orderId} has been cancelled. Please contact your vendor.",
    ];

    if (isset($messages[$newStatus])) {
        notifyUser($order['customer_id'], $messages[$newStatus], 'order');
    }

    return ['success' => true, 'message' => "Order status updated to {$newStatus}."];
}

/**
 * Get a single order by ID
 */
function getOrderById(int $orderId): ?array {
    $db = getDB();
    $stmt = $db->prepare(
        "SELECT o.*, u.full_name AS customer_name, u.phone AS customer_phone,
                v.business_name, p.method, p.payment_status, p.mpesa_code
         FROM water_orders o
         JOIN users u    ON o.customer_id = u.user_id
         JOIN vendors v  ON o.vendor_id   = v.vendor_id
         LEFT JOIN payments p ON o.order_id = p.order_id
         WHERE o.order_id = ?"
    );
    $stmt->execute([$orderId]);
    return $stmt->fetch() ?: null;
}

/**
 * Create or send in-system notification
 */
function notifyUser(int $userId, string $message, string $type = 'system'): void {
    $db = getDB();
    $db->prepare("INSERT INTO notifications (user_id, message, type) VALUES (?, ?, ?)")
       ->execute([$userId, $message, $type]);
}
