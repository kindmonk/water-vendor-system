<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Customer Dashboard – WVMS</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  :root {
    --blue:#065A82; --teal:#1C7293; --mint:#02C39A;
    --light:#F0F9FF; --dark:#0D2333; --gray:#64748B; --white:#fff;
  }
  body { font-family:'Segoe UI',sans-serif; background:#F1F5F9; }

  
  nav { background:var(--dark); color:var(--white);
        display:flex; align-items:center; justify-content:space-between;
        padding:1rem 2rem; }
  nav .brand { font-size:1.3rem; font-weight:800; }
  nav .brand span { color:var(--mint); }
  nav .nav-right { display:flex; align-items:center; gap:1.5rem; }
  nav .nav-right a { color:var(--white); text-decoration:none;
                     opacity:.8; font-size:.9rem; }
  nav .nav-right a:hover { opacity:1; }
  .badge { background:var(--mint); color:var(--dark); border-radius:50%;
           padding:.1rem .45rem; font-size:.75rem; font-weight:800; }

 
  .container { max-width:1100px; margin:0 auto; padding:2rem 1.5rem; }
  h1 { font-size:1.6rem; color:var(--dark); margin-bottom:.3rem; }
  .subtitle { color:var(--gray); margin-bottom:2rem; font-size:.95rem; }

  .stats { display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr));
           gap:1rem; margin-bottom:2rem; }
  .stat-card { background:var(--white); border-radius:12px; padding:1.2rem 1.5rem;
               box-shadow:0 2px 8px rgba(0,0,0,.06); }
  .stat-card .num { font-size:2rem; font-weight:800; color:var(--blue); }
  .stat-card .lbl { font-size:.85rem; color:var(--gray); margin-top:.2rem; }

  .card { background:var(--white); border-radius:14px; padding:1.8rem;
          box-shadow:0 2px 10px rgba(0,0,0,.07); margin-bottom:2rem; }
  .card h2 { font-size:1.1rem; color:var(--dark); margin-bottom:1.2rem;
             border-bottom:2px solid var(--light); padding-bottom:.6rem; }

 
  .form-row { display:grid; grid-template-columns:1fr 1fr; gap:1rem; }
  .form-group { margin-bottom:1rem; }
  label { display:block; font-size:.83rem; font-weight:600;
          color:var(--gray); margin-bottom:.35rem; }
  input, select, textarea {
    width:100%; padding:.65rem .9rem; border:1.5px solid #E2E8F0;
    border-radius:8px; font-size:.93rem; color:var(--dark); }
  input:focus, select:focus, textarea:focus { outline:none; border-color:var(--teal); }
  .btn { padding:.65rem 1.4rem; border:none; border-radius:8px;
         font-weight:700; cursor:pointer; font-size:.9rem; transition:.2s; }
  .btn-primary { background:var(--blue); color:var(--white); }
  .btn-primary:hover { background:var(--teal); }

  
  table { width:100%; border-collapse:collapse; font-size:.9rem; }
  th { background:var(--light); color:var(--gray); font-weight:700;
       padding:.7rem 1rem; text-align:left; }
  td { padding:.7rem 1rem; border-bottom:1px solid #F1F5F9; color:var(--dark); }
  tr:last-child td { border:none; }


  .pill { display:inline-block; padding:.25rem .7rem; border-radius:20px;
          font-size:.8rem; font-weight:700; }
  .pill-pending    { background:#FEF9C3; color:#713F12; }
  .pill-accepted   { background:#DBEAFE; color:#1E40AF; }
  .pill-in_transit { background:#FEF3C7; color:#92400E; }
  .pill-delivered  { background:#D1FAE5; color:#065F46; }
  .pill-cancelled  { background:#FEE2E2; color:#991B1B; }

  .msg-success { background:#F0FFF4; color:#276749; border:1px solid #9AE6B4;
                 padding:.7rem 1rem; border-radius:8px; margin-bottom:1rem; }
</style>
</head>
<body>

<?php
require_once '../../helpers/auth.php';
require_once '../../helpers/orders.php';
require_once '../../config/db.php';

requireRole('customer', '../../index.php');
$user = getCurrentUser();

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'place_order') {
        $result = placeOrder([
            'customer_id'      => $user['user_id'],
            'vendor_id'        => (int)$_POST['vendor_id'],
            'quantity_litres'  => (int)$_POST['quantity_litres'],
            'delivery_address' => trim($_POST['delivery_address']),
            'delivery_time'    => $_POST['delivery_time'] ?? null,
            'notes'            => trim($_POST['notes'] ?? ''),
        ]);
        $msg = $result['message'] ?? ($result['success'] ? "Order placed! Total: KES {$result['total']}" : 'Error placing order.');
    }
}

$orders = getCustomerOrders($user['user_id']);


$totalOrders   = count($orders);
$delivered     = count(array_filter($orders, fn($o) => $o['status'] === 'delivered'));
$pending       = count(array_filter($orders, fn($o) => $o['status'] === 'pending'));
$totalSpent    = array_sum(array_column(
    array_filter($orders, fn($o) => $o['status'] === 'delivered'), 'total_amount'));


$db = getDB();
$vendors = $db->query("SELECT v.vendor_id, v.business_name, v.price_per_litre,
                               v.min_order_litres, v.service_area
                        FROM vendors v WHERE v.is_available=1")->fetchAll();

$notifs = $db->prepare("SELECT * FROM notifications WHERE user_id=? AND is_read=0 ORDER BY created_at DESC LIMIT 5");
$notifs->execute([$user['user_id']]);
$notifications = $notifs->fetchAll();
$db->prepare("UPDATE notifications SET is_read=1 WHERE user_id=?")->execute([$user['user_id']]);
?>

<nav>
  <div class="brand">WV<span>MS</span> <small style="font-weight:400;font-size:.75rem;opacity:.7">Customer</small></div>
  <div class="nav-right">
    <span>👋 <?= htmlspecialchars($user['full_name']) ?></span>
    <?php if ($notifications): ?>
      <span title="Notifications">🔔 <span class="badge"><?= count($notifications) ?></span></span>
    <?php endif; ?>
    <a href="../../helpers/logout.php">Logout</a>
  </div>
</nav>

<div class="container">
  <h1>My Dashboard</h1>
  <p class="subtitle">Place and track your water orders below.</p>

  <?php if ($notifications): ?>
  <div class="card" style="border-left:4px solid var(--mint);padding:1rem 1.5rem;">
    <strong>🔔 Notifications</strong>
    <?php foreach($notifications as $n): ?>
      <p style="margin-top:.4rem;font-size:.9rem;color:var(--gray);"><?= htmlspecialchars($n['message']) ?></p>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  
  <div class="stats">
    <div class="stat-card"><div class="num"><?= $totalOrders ?></div><div class="lbl">Total Orders</div></div>
    <div class="stat-card"><div class="num"><?= $delivered ?></div><div class="lbl">Delivered</div></div>
    <div class="stat-card"><div class="num"><?= $pending ?></div><div class="lbl">Pending</div></div>
    <div class="stat-card"><div class="num">KES <?= number_format($totalSpent,2) ?></div><div class="lbl">Total Spent</div></div>
  </div>

 
  <div class="card">
    <h2>📦 Place a New Order</h2>
    <?php if($msg): ?><div class="msg-success"><?= htmlspecialchars($msg) ?></div><?php endif; ?>
    <form method="POST">
      <input type="hidden" name="action" value="place_order">
      <div class="form-row">
        <div class="form-group">
          <label>Select Vendor</label>
          <select name="vendor_id" required>
            <option value="">-- Choose a vendor --</option>
            <?php foreach($vendors as $v): ?>
              <option value="<?= $v['vendor_id'] ?>">
                <?= htmlspecialchars($v['business_name']) ?>
                (KES <?= $v['price_per_litre'] ?>/L | Min: <?= $v['min_order_litres'] ?>L)
              </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label>Quantity (Litres)</label>
          <input type="number" name="quantity_litres" min="20" placeholder="e.g. 100" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label>Delivery Address</label>
          <input type="text" name="delivery_address" placeholder="e.g. House 5, Mpaka Road" required>
        </div>
        <div class="form-group">
          <label>Preferred Delivery Time</label>
          <input type="datetime-local" name="delivery_time">
        </div>
      </div>
      <div class="form-group">
        <label>Notes (optional)</label>
        <textarea name="notes" rows="2" placeholder="Any special instructions..."></textarea>
      </div>
      <button type="submit" class="btn btn-primary">Place Order</button>
    </form>
  </div>

 
  <div class="card">
    <h2>📋 My Orders</h2>
    <?php if(empty($orders)): ?>
      <p style="color:var(--gray);font-size:.93rem;">No orders yet. Place your first order above!</p>
    <?php else: ?>
    <div style="overflow-x:auto">
    <table>
      <tr>
        <th>#</th><th>Vendor</th><th>Qty (L)</th>
        <th>Amount</th><th>Payment</th><th>Status</th><th>Date</th>
      </tr>
      <?php foreach($orders as $o): ?>
      <tr>
        <td><?= $o['order_id'] ?></td>
        <td><?= htmlspecialchars($o['business_name']) ?></td>
        <td><?= $o['quantity_litres'] ?></td>
        <td>KES <?= number_format($o['total_amount'],2) ?></td>
        <td><?= strtoupper($o['method'] ?? '—') ?>
            <?php if($o['payment_status']==='confirmed'): ?> ✅<?php endif; ?></td>
        <td><span class="pill pill-<?= $o['status'] ?>"><?= ucfirst(str_replace('_',' ',$o['status'])) ?></span></td>
        <td><?= date('d M Y', strtotime($o['created_at'])) ?></td>
      </tr>
      <?php endforeach; ?>
    </table>
    </div>
    <?php endif; ?>
  </div>

</div>
</body>
</html>
