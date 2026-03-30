<?php
// ============================================================
//  WVMS - Reports Controller
// ============================================================

require_once __DIR__ . '/../config/db.php';

/**
 * Sales report: daily, weekly, or monthly
 */
function getSalesReport(string $period = 'monthly', int $vendorId = 0): array {
    $db = getDB();

    $groupBy = match($period) {
        'daily'   => "DATE(o.created_at)",
        'weekly'  => "YEARWEEK(o.created_at, 1)",
        default   => "DATE_FORMAT(o.created_at, '%Y-%m')",
    };

    $label = match($period) {
        'daily'   => "DATE(o.created_at)",
        'weekly'  => "CONCAT('Week ', WEEK(o.created_at))",
        default   => "DATE_FORMAT(o.created_at, '%b %Y')",
    };

    $sql = "SELECT
                {$label}          AS period_label,
                COUNT(o.order_id) AS total_orders,
                SUM(o.total_amount) AS total_revenue,
                SUM(o.quantity_litres) AS total_litres,
                SUM(CASE WHEN o.status='delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN o.status='cancelled' THEN 1 ELSE 0 END) AS cancelled
            FROM water_orders o
            WHERE o.status != 'pending'";

    $params = [];
    if ($vendorId > 0) {
        $sql .= " AND o.vendor_id = ?";
        $params[] = $vendorId;
    }

    $sql .= " GROUP BY {$groupBy} ORDER BY {$groupBy} DESC LIMIT 12";

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Top customers by order volume
 */
function getTopCustomers(int $vendorId = 0, int $limit = 10): array {
    $db = getDB();
    $sql = "SELECT u.full_name, u.phone, u.location,
                   COUNT(o.order_id)     AS total_orders,
                   SUM(o.total_amount)   AS total_spent,
                   SUM(o.quantity_litres) AS total_litres
            FROM water_orders o
            JOIN users u ON o.customer_id = u.user_id
            WHERE o.status = 'delivered'";
    $params = [];
    if ($vendorId > 0) {
        $sql .= " AND o.vendor_id = ?";
        $params[] = $vendorId;
    }
    $sql .= " GROUP BY u.user_id ORDER BY total_spent DESC LIMIT ?";
    $params[] = $limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Delivery performance stats
 */
function getDeliveryStats(int $vendorId = 0): array {
    $db = getDB();
    $sql = "SELECT
                COUNT(o.order_id) AS total_orders,
                SUM(CASE WHEN o.status='delivered' THEN 1 ELSE 0 END) AS delivered,
                SUM(CASE WHEN o.status='cancelled' THEN 1 ELSE 0 END) AS cancelled,
                SUM(CASE WHEN o.status='in_transit' THEN 1 ELSE 0 END) AS in_transit,
                ROUND(
                    SUM(CASE WHEN o.status='delivered' THEN 1 ELSE 0 END)
                    / COUNT(o.order_id) * 100, 1
                ) AS delivery_rate
            FROM water_orders o WHERE 1=1";
    $params = [];
    if ($vendorId > 0) {
        $sql .= " AND o.vendor_id = ?";
        $params[] = $vendorId;
    }
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetch();
}

/**
 * Admin: all vendors summary
 */
function getAllVendorsSummary(): array {
    $db = getDB();
    $stmt = $db->query(
        "SELECT v.vendor_id, v.business_name, v.service_area,
                COUNT(o.order_id)    AS total_orders,
                SUM(o.total_amount)  AS total_revenue,
                ROUND(AVG(f.rating), 1) AS avg_rating
         FROM vendors v
         LEFT JOIN water_orders o ON v.vendor_id = o.vendor_id AND o.status = 'delivered'
         LEFT JOIN feedback f     ON o.order_id  = f.order_id
         GROUP BY v.vendor_id
         ORDER BY total_revenue DESC"
    );
    return $stmt->fetchAll();
}
