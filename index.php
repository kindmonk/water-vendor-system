<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>WVMS – Water Vendor Management System</title>
<style>
  * { margin:0; padding:0; box-sizing:border-box; }
  :root {
    --blue:  #065A82;
    --teal:  #1C7293;
    --mint:  #02C39A;
    --light: #F0F9FF;
    --dark:  #0D2333;
    --gray:  #64748B;
    --white: #FFFFFF;
    --red:   #E53E3E;
  }
  body { font-family:'Segoe UI',sans-serif; background:var(--light);
         display:flex; min-height:100vh; }

  /* ── Left panel ── */
  .panel-left {
    width:45%; background:linear-gradient(160deg,var(--dark),var(--teal));
    display:flex; flex-direction:column; justify-content:center;
    padding:3rem; color:var(--white);
  }
  .panel-left .logo { font-size:2.4rem; font-weight:800; letter-spacing:1px; }
  .panel-left .logo span { color:var(--mint); }
  .panel-left p { margin-top:1rem; opacity:.8; font-size:1.05rem; line-height:1.7; }
  .feature-list { margin-top:2rem; list-style:none; }
  .feature-list li { display:flex; align-items:center; gap:.6rem;
                     margin-bottom:.8rem; font-size:.95rem; opacity:.9; }
  .feature-list li::before { content:'✓'; color:var(--mint);
                              font-weight:800; font-size:1.1rem; }

  /* ── Right panel (forms) ── */
  .panel-right {
    flex:1; display:flex; align-items:center; justify-content:center; padding:2rem;
  }
  .form-card {
    background:var(--white); border-radius:16px;
    box-shadow:0 4px 24px rgba(0,0,0,.1);
    padding:2.5rem; width:100%; max-width:420px;
  }
  .tabs { display:flex; gap:0; margin-bottom:2rem;
          border-radius:8px; overflow:hidden; border:1px solid #E2E8F0; }
  .tab-btn { flex:1; padding:.75rem; border:none; background:#F8FAFC;
             cursor:pointer; font-size:.95rem; font-weight:600;
             color:var(--gray); transition:.2s; }
  .tab-btn.active { background:var(--blue); color:var(--white); }

  h2 { font-size:1.4rem; color:var(--dark); margin-bottom:1.5rem; }

  .form-group { margin-bottom:1.1rem; }
  label { display:block; font-size:.85rem; font-weight:600;
          color:var(--gray); margin-bottom:.4rem; }
  input, select {
    width:100%; padding:.7rem 1rem; border:1.5px solid #E2E8F0;
    border-radius:8px; font-size:.95rem; color:var(--dark);
    transition:border-color .2s;
  }
  input:focus, select:focus { outline:none; border-color:var(--teal); }

  .btn-primary {
    width:100%; padding:.85rem; background:var(--blue); color:var(--white);
    border:none; border-radius:8px; font-size:1rem; font-weight:700;
    cursor:pointer; transition:.2s; margin-top:.5rem;
  }
  .btn-primary:hover { background:var(--teal); }

  .msg { padding:.7rem 1rem; border-radius:8px; font-size:.9rem;
         margin-bottom:1rem; display:none; }
  .msg.error   { background:#FEF2F2; color:var(--red); border:1px solid #FECACA; }
  .msg.success { background:#F0FFF4; color:#276749; border:1px solid #9AE6B4; }

  .form-section { display:none; }
  .form-section.active { display:block; }

  @media(max-width:768px) {
    .panel-left { display:none; }
    .panel-right { padding:1rem; }
  }
</style>
</head>
<body>

<?php
require_once 'helpers/auth.php';

$loginError = $registerError = $registerSuccess = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {

        if ($_POST['action'] === 'login') {
            $result = login(trim($_POST['phone']), $_POST['password']);
            if ($result['success']) {
                $redirect = match($result['role']) {
                    'vendor' => 'pages/vendor/dashboard.php',
                    'admin'  => 'pages/admin/dashboard.php',
                    default  => 'pages/customer/dashboard.php',
                };
                header("Location: $redirect");
                exit;
            } else {
                $loginError = $result['message'];
            }

        } elseif ($_POST['action'] === 'register') {
            $result = register([
                'full_name' => trim($_POST['full_name']),
                'phone'     => trim($_POST['phone']),
                'email'     => trim($_POST['email'] ?? ''),
                'password'  => $_POST['password'],
                'location'  => trim($_POST['location'] ?? ''),
            ]);
            if ($result['success']) {
                $registerSuccess = $result['message'];
            } else {
                $registerError = $result['message'];
            }
        }
    }
}
?>

<!-- Left Panel -->
<div class="panel-left">
  <div class="logo">WV<span>MS</span></div>
  <p>A smart digital platform for water vendors to manage orders, payments, and deliveries with ease.</p>
  <ul class="feature-list">
    <li>Real-time order management</li>
    <li>M-Pesa &amp; cash payment tracking</li>
    <li>Customer delivery notifications</li>
    <li>Sales &amp; delivery reports</li>
    <li>Multi-role access control</li>
  </ul>
</div>

<!-- Right Panel -->
<div class="panel-right">
  <div class="form-card">

    <div class="tabs">
      <button class="tab-btn active" onclick="showTab('login')">Login</button>
      <button class="tab-btn" onclick="showTab('register')">Register</button>
    </div>

    <!-- LOGIN -->
    <div class="form-section active" id="tab-login">
      <h2>Welcome Back</h2>

      <?php if ($loginError): ?>
        <div class="msg error" style="display:block"><?= htmlspecialchars($loginError) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="action" value="login">
        <div class="form-group">
          <label>Phone Number</label>
          <input type="text" name="phone" placeholder="07XXXXXXXX" required>
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Enter your password" required>
        </div>
        <button type="submit" class="btn-primary">Login</button>
      </form>
    </div>

    <!-- REGISTER -->
    <div class="form-section" id="tab-register">
      <h2>Create Account</h2>

      <?php if ($registerError): ?>
        <div class="msg error" style="display:block"><?= htmlspecialchars($registerError) ?></div>
      <?php endif; ?>
      <?php if ($registerSuccess): ?>
        <div class="msg success" style="display:block"><?= htmlspecialchars($registerSuccess) ?></div>
      <?php endif; ?>

      <form method="POST">
        <input type="hidden" name="action" value="register">
        <div class="form-group">
          <label>Full Name</label>
          <input type="text" name="full_name" placeholder="e.g. Mary Wanjiku" required>
        </div>
        <div class="form-group">
          <label>Phone Number</label>
          <input type="text" name="phone" placeholder="07XXXXXXXX" required>
        </div>
        <div class="form-group">
          <label>Email (optional)</label>
          <input type="email" name="email" placeholder="you@email.com">
        </div>
        <div class="form-group">
          <label>Location / Area</label>
          <input type="text" name="location" placeholder="e.g. Westlands, Nairobi">
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Choose a password" required minlength="6">
        </div>
        <button type="submit" class="btn-primary">Create Account</button>
      </form>
    </div>

  </div>
</div>

<script>
function showTab(tab) {
  document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
  document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
  document.getElementById('tab-' + tab).classList.add('active');
  event.target.classList.add('active');
}
</script>
</body>
</html>
