<?php
require_once __DIR__ . '/../api/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$role  = $_GET['role'] ?? 'player';
$error = '';

// Already logged in?
if (!empty($_SESSION['superadmin_id'])) {
    header('Location: ' . APP_URL . '/superadmin/index.php'); exit;
}
if (!empty($_SESSION['owner_id'])) {
    header('Location: ' . APP_URL . '/owner/index.php'); exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $role  = $_POST['role'] ?? 'player';
    $db    = getDB();

    if ($role === 'admin') {
        $stmt = $db->prepare("SELECT * FROM superadmins WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['superadmin_id']   = $user['id'];
            $_SESSION['superadmin_name'] = $user['name'];
            logAudit('login', 'IAM', 'superadmin', $user['id'], "SuperAdmin logged in");
            header('Location: ' . APP_URL . '/superadmin/index.php');
            exit;
        }
    } elseif ($role === 'owner') {
        $stmt = $db->prepare("SELECT * FROM venue_owners WHERE email = :email AND status = 'active' LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['owner_id']   = $user['id'];
            $_SESSION['owner_name'] = $user['name'];
            logAudit('login', 'IAM', 'owner', $user['id'], "Owner logged in");
            header('Location: ' . APP_URL . '/owner/index.php');
            exit;
        }
    } else {
        // Player role
        $stmt = $db->prepare("SELECT * FROM players WHERE email = :email AND status = 'active' LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch();
        if ($user && password_verify($pass, $user['password_hash'])) {
            $_SESSION['player_id']   = $user['id'];
            $_SESSION['player_name'] = $user['name'];
            $_SESSION['player_email']= $user['email'];
            logAudit('login', 'IAM', 'player', $user['id'], "Player logged in");
            header('Location: ' . APP_URL . '/player/index.php');
            exit;
        }
    }
    $error = 'Invalid credentials or inactive account. Please try again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Login – MeroMaidan</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      background: linear-gradient(135deg, #0f2740 0%, #1BB955 100%);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 24px;
    }
    .auth-card {
      background: #fff;
      border-radius: 24px;
      padding: 44px 40px;
      max-width: 420px;
      width: 100%;
      box-shadow: 0 24px 64px rgba(0,0,0,.25);
      animation: slideUp .3s ease;
    }
    @keyframes slideUp { from { opacity:0; transform:translateY(20px); } to { opacity:1; transform:translateY(0); } }
    .logo-wrap { text-align: center; margin-bottom: 28px; }
    .logo-text { font-size: 26px; font-weight: 900; color: #0f2740; }
    .logo-text span { color: #1BB955; }
    .logo-sub { font-size: 12px; color: #64748b; margin-top: 4px; }
    .auth-tabs {
      display: flex;
      background: #f1f5f9;
      border-radius: 12px;
      padding: 4px;
      margin-bottom: 24px;
    }
    .auth-tab {
      flex: 1;
      text-align: center;
      padding: 10px;
      border-radius: 10px;
      font-size: 13px;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s;
      color: #64748b;
      text-decoration: none;
    }
    .auth-tab.active { background: #fff; color: #0f2740; box-shadow: 0 2px 8px rgba(0,0,0,.1); }
    .auth-title { font-size: 20px; font-weight: 900; color: #0f2740; margin-bottom: 4px; }
    .auth-sub { font-size: 13px; color: #64748b; margin-bottom: 22px; }
    .form-group { margin-bottom: 16px; }
    .form-label { display: block; font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .3px; }
    .form-input {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-family: inherit;
      font-size: 14px;
      font-weight: 600;
      color: #2b3648;
      background: #f8fafc;
      outline: none;
      transition: border-color .2s;
    }
    .form-input:focus { border-color: #1BB955; background: #fff; }
    .btn-auth {
      width: 100%;
      background: #1BB955;
      color: #fff;
      border: none;
      padding: 14px;
      border-radius: 50px;
      font-family: inherit;
      font-size: 15px;
      font-weight: 800;
      cursor: pointer;
      transition: all .2s;
      margin-top: 8px;
    }
    .btn-auth:hover { background: #159943; transform: translateY(-1px); }
    .btn-auth.admin-btn { background: #0f2740; }
    .btn-auth.admin-btn:hover { background: #0a1d2f; }
    .error-box {
      background: #fef2f2;
      border: 1.5px solid #fecaca;
      border-radius: 10px;
      padding: 12px;
      font-size: 13px;
      font-weight: 600;
      color: #dc2626;
      margin-bottom: 16px;
    }
    .demo-box {
      background: #f0fdf4;
      border: 1.5px solid #bbf7d0;
      border-radius: 10px;
      padding: 12px;
      font-size: 12px;
      color: #166534;
      margin-top: 16px;
      line-height: 1.7;
    }
    .demo-box strong { display: block; font-weight: 800; margin-bottom: 4px; }
    .back-link { text-align: center; margin-top: 16px; font-size: 13px; color: #64748b; }
    .back-link a { color: #1BB955; font-weight: 700; text-decoration: none; }
    .back-link a:hover { text-decoration: underline; }
    @media (max-width: 480px) {
      .auth-card { padding: 32px 24px; }
    }
  </style>
</head>
<body>
<div class="auth-card">
  <div class="logo-wrap">
    <div class="logo-text">Mero<span>Maidan</span></div>
    <div class="logo-sub">Nepal's Smart Sports Venue Platform</div>
  </div>

  <div class="auth-tabs">
    <a href="?role=player" class="auth-tab <?= $role==='player' ? 'active' : '' ?>">⚽ Player</a>
    <a href="?role=owner" class="auth-tab <?= $role==='owner' ? 'active' : '' ?>">🏟️ Owner</a>
    <a href="?role=admin" class="auth-tab <?= $role==='admin' ? 'active' : '' ?>">🛡️ Admin</a>
  </div>

  <?php if ($role === 'admin'): ?>
    <div class="auth-title">Admin Login</div>
    <p class="auth-sub">Sign in to the MeroMaidan control panel</p>
  <?php elseif ($role === 'owner'): ?>
    <div class="auth-title">Owner Login</div>
    <p class="auth-sub">Access your venue management dashboard</p>
  <?php else: ?>
    <div class="auth-title">Player Login</div>
    <p class="auth-sub">Sign in to manage your sports bookings</p>
  <?php endif; ?>

  <?php if ($error): ?>
  <div class="error-box">❌ <?= htmlspecialchars($error) ?></div>
  <?php endif; ?>

  <form method="POST">
    <input type="hidden" name="role" value="<?= htmlspecialchars($role) ?>">
    <div class="form-group">
      <label class="form-label">Email Address</label>
      <input type="email" name="email" class="form-input" placeholder="you@example.com" required autofocus>
    </div>
    <div class="form-group">
      <label class="form-label">Password</label>
      <input type="password" name="password" class="form-input" placeholder="••••••••" required>
    </div>
    <button type="submit" class="btn-auth <?= $role==='admin' ? 'admin-btn' : '' ?>">
      <?= $role==='admin' ? '🛡️ Sign In as Admin' : ($role==='owner' ? '🏟️ Sign In as Owner' : '⚽ Sign In as Player') ?>
    </button>
  </form>

  <div class="demo-box">
    <strong>🔑 Demo Credentials:</strong>
    <?php if ($role === 'admin'): ?>
      📧 admin@meromaidan.com<br>
      🔒 Admin@1234
    <?php elseif ($role === 'owner'): ?>
      📧 ramesh@royalfutsal.com<br>
      🔒 Owner@1234
    <?php else: ?>
      📧 anil@example.com<br>
      🔒 Admin@1234
    <?php endif; ?>
  </div>

  <div class="back-link">
    <a href="../index.php">← Back to MeroMaidan</a>
    <?php if ($role === 'player'): ?>
    &nbsp;·&nbsp;
    <a href="register.php">Create Account</a>
    <?php elseif ($role === 'owner'): ?>
    &nbsp;·&nbsp;
    <a href="../list-ground.php">Register your ground</a>
    <?php endif; ?>
  </div>
</div>
</body>
</html>
