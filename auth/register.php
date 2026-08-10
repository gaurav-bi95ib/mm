<?php
require_once __DIR__ . '/../api/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

// Redirect if already logged in
if (!empty($_SESSION['player_id'])) { header('Location: ' . APP_URL . '/player/index.php'); exit; }
if (!empty($_SESSION['owner_id'])) { header('Location: ' . APP_URL . '/owner/index.php'); exit; }
if (!empty($_SESSION['superadmin_id'])) { header('Location: ' . APP_URL . '/superadmin/index.php'); exit; }

$error = '';
$success = '';
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $email   = trim($_POST['email'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $pass    = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $old = compact('name','email','phone');

    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Refresh the page and try again.';
    } elseif (!$name || !$email || !$pass || !$confirm) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif ($phone !== '' && !preg_match('/^(?:\+977[- ]?)?9[678]\d{8}$/', $phone)) {
        $error = 'Please enter a valid Nepal mobile number.';
    } elseif (strlen($pass) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($pass !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $db = getDB();
        $stmt = $db->prepare("SELECT id FROM players WHERE email=:e LIMIT 1");
        $stmt->execute([':e' => $email]);
        if ($stmt->fetch()) {
            $error = 'An account with this email already exists. <a href="login.php">Log in?</a>';
        } else {
            $hash = password_hash($pass, PASSWORD_BCRYPT);
            $stmt = $db->prepare("INSERT INTO players (name,email,phone,password_hash,status) VALUES (:n,:e,:p,:h,'active')");
            $stmt->execute([':n'=>$name,':e'=>$email,':p'=>$phone,':h'=>$hash]);
            $playerId = $db->lastInsertId();
            $_SESSION['player_id']   = $playerId;
            $_SESSION['player_name'] = $name;
            $_SESSION['player_email']= $email;
            session_regenerate_id(true);
            logAudit('register','IAM','player',$playerId,"New player registered: $email");
            header('Location: ' . APP_URL . '/player/index.php?welcome=1');
            exit;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Create Account – MeroMaidan</title>
  <meta name="description" content="Join MeroMaidan and start booking sports venues in Nepal instantly.">
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    body {
      font-family: 'Plus Jakarta Sans', sans-serif;
      min-height: 100vh;
      background: #0f2740;
      display: flex;
      align-items: stretch;
    }

    /* Left Panel */
    .reg-left {
      flex: 1;
      background: linear-gradient(160deg, #0f2740 0%, #0d3d2a 60%, #1BB955 100%);
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 60px 56px;
      position: relative;
      overflow: hidden;
    }
    .reg-left::before {
      content: '';
      position: absolute;
      top: -80px; right: -80px;
      width: 320px; height: 320px;
      background: rgba(27,185,85,.12);
      border-radius: 50%;
    }
    .reg-left::after {
      content: '';
      position: absolute;
      bottom: -60px; left: -60px;
      width: 240px; height: 240px;
      background: rgba(27,185,85,.08);
      border-radius: 50%;
    }
    .logo-area { display: flex; align-items: center; gap: 14px; margin-bottom: 56px; position: relative; z-index: 1; }
    .logo-icon { width: 50px; height: 50px; background: #1BB955; border-radius: 14px; display: grid; place-items: center; }
    .logo-icon svg { width: 30px; height: 30px; }
    .logo-text { font-size: 22px; font-weight: 900; color: #fff; }
    .logo-text span { color: #1BB955; }
    .reg-headline { font-size: clamp(28px,3.5vw,40px); font-weight: 900; color: #fff; line-height: 1.2; margin-bottom: 16px; position: relative; z-index: 1; }
    .reg-headline em { color: #1BB955; font-style: normal; }
    .reg-sub { font-size: 15px; color: rgba(255,255,255,.65); line-height: 1.7; margin-bottom: 48px; position: relative; z-index: 1; max-width: 380px; }
    .feature-list { list-style: none; display: flex; flex-direction: column; gap: 16px; position: relative; z-index: 1; }
    .feature-list li { display: flex; align-items: center; gap: 14px; color: rgba(255,255,255,.8); font-size: 14px; font-weight: 500; }
    .feat-icon { width: 36px; height: 36px; background: rgba(27,185,85,.18); border-radius: 10px; display: grid; place-items: center; font-size: 16px; flex-shrink: 0; }
    .stats-row { display: flex; gap: 24px; margin-top: 48px; position: relative; z-index: 1; }
    .stat-pill { background: rgba(255,255,255,.08); border: 1px solid rgba(255,255,255,.12); border-radius: 16px; padding: 16px 24px; text-align: center; }
    .stat-pill .num { font-size: 24px; font-weight: 900; color: #1BB955; }
    .stat-pill .lbl { font-size: 11px; color: rgba(255,255,255,.5); margin-top: 2px; }

    /* Right Panel */
    .reg-right {
      width: 480px;
      background: #fff;
      display: flex;
      flex-direction: column;
      justify-content: center;
      padding: 56px 48px;
      overflow-y: auto;
    }
    .form-title { font-size: 26px; font-weight: 900; color: #0f2740; margin-bottom: 6px; }
    .form-sub { font-size: 14px; color: #64748b; margin-bottom: 32px; }
    .form-sub a { color: #1BB955; font-weight: 600; text-decoration: none; }

    .alert { padding: 12px 16px; border-radius: 10px; font-size: 13px; margin-bottom: 20px; }
    .alert.error { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }
    .alert.success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #16a34a; }
    .alert a { color: inherit; font-weight: 600; }

    .form-group { margin-bottom: 18px; }
    .form-label { font-size: 13px; font-weight: 700; color: #374151; display: block; margin-bottom: 6px; }
    .form-input {
      width: 100%; padding: 13px 16px;
      border: 1.5px solid #e5e7eb;
      border-radius: 10px;
      font-family: inherit; font-size: 14px; color: #0f2740;
      transition: border-color .2s, box-shadow .2s;
      outline: none;
      background: #f9fafb;
    }
    .form-input:focus { border-color: #1BB955; box-shadow: 0 0 0 3px rgba(27,185,85,.12); background: #fff; }
    .form-input::placeholder { color: #9ca3af; }
    .input-row { display: grid; grid-template-columns: 1fr 1fr; gap: 14px; }

    .password-wrap { position: relative; }
    .password-wrap .form-input { padding-right: 46px; }
    .pass-toggle { position: absolute; right: 14px; top: 50%; transform: translateY(-50%); background: none; border: none; cursor: pointer; color: #64748b; font-size: 16px; line-height: 1; }
    .pass-toggle:hover { color: #1BB955; }

    .consent { display: flex; align-items: flex-start; gap: 10px; margin: 6px 0 24px; }
    .consent input[type=checkbox] { margin-top: 3px; accent-color: #1BB955; width: 16px; height: 16px; flex-shrink: 0; }
    .consent label { font-size: 12px; color: #64748b; line-height: 1.5; }
    .consent a { color: #1BB955; font-weight: 600; text-decoration: none; }

    .btn-register {
      width: 100%; padding: 15px;
      background: linear-gradient(135deg, #1BB955, #15a248);
      color: #fff; border: none; border-radius: 12px;
      font-family: inherit; font-size: 15px; font-weight: 800;
      cursor: pointer; transition: all .2s;
      box-shadow: 0 4px 20px rgba(27,185,85,.35);
    }
    .btn-register:hover { transform: translateY(-1px); box-shadow: 0 8px 28px rgba(27,185,85,.45); }
    .btn-register:active { transform: translateY(0); }

    .divider { display: flex; align-items: center; gap: 12px; margin: 20px 0; }
    .divider hr { flex: 1; border: none; border-top: 1px solid #e5e7eb; }
    .divider span { font-size: 12px; color: #9ca3af; }

    .alt-links { display: flex; gap: 10px; flex-wrap: wrap; }
    .alt-link { flex: 1; text-align: center; padding: 12px; border: 1.5px solid #e5e7eb; border-radius: 10px; font-size: 12px; color: #64748b; text-decoration: none; font-weight: 600; transition: all .2s; }
    .alt-link:hover { border-color: #1BB955; color: #1BB955; }
    .sign-in-link { text-align: center; margin-top: 24px; font-size: 13px; color: #64748b; }
    .sign-in-link a { color: #1BB955; font-weight: 700; text-decoration: none; }

    @media (max-width: 900px) {
      .reg-left { display: none; }
      .reg-right { width: 100%; padding: 40px 28px; }
    }
  </style>
</head>
<body>
  <!-- Left Brand Panel -->
  <div class="reg-left">
    <div class="logo-area">
      <div class="logo-icon">
        <svg viewBox="0 0 30 30" fill="none">
          <path d="M5 5h10c4 0 6.5 2.5 6.5 5.5C21.5 13 19.5 15 17 15.5c3 1 5 3.5 5 7C22 26 18.5 28 13.5 28H5V5Z" fill="none"/>
          <path fill-rule="evenodd" clip-rule="evenodd" d="M8 8H15C18 8 20 9.5 20 12C20 14 18 15.5 15 15.5H8V8ZM8 18H16C20 18 22 20 22 23C22 26 19 27.5 15.5 27.5H8V18Z" stroke="white" stroke-width="2.2"/>
          <circle cx="15" cy="15" r="3.5" fill="white"/>
          <circle cx="15" cy="15" r="1.5" fill="#1BB955"/>
        </svg>
      </div>
      <div class="logo-text">Mero<span>Maidan</span></div>
    </div>

    <div class="reg-headline">Book Your <em>Game.</em><br>Start Playing Today.</div>
    <p class="reg-sub">Join thousands of players discovering and booking Nepal's best sports venues — all in one place.</p>

    <ul class="feature-list">
      <li><div class="feat-icon">🔍</div> Search venues by sport, location & price</li>
      <li><div class="feat-icon">⚡</div> Real-time availability & instant confirmation</li>
      <li><div class="feat-icon">📅</div> Manage all bookings from your dashboard</li>
      <li><div class="feat-icon">❤️</div> Save favorite venues for later</li>
    </ul>

    <div class="stats-row">
      <div class="stat-pill"><div class="num">100+</div><div class="lbl">Venues</div></div>
      <div class="stat-pill"><div class="num">5K+</div><div class="lbl">Players</div></div>
      <div class="stat-pill"><div class="num">24/7</div><div class="lbl">Online access</div></div>
    </div>
  </div>

  <!-- Right Form Panel -->
  <div class="reg-right">
    <div class="form-title">Create Your Account</div>
    <div class="form-sub">Already have one? <a href="login.php">Sign in here</a></div>

    <?php if ($error): ?>
      <div class="alert error">⚠ <?= $error ?></div>
    <?php endif; ?>

    <form method="POST" action="">
      <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
      <div class="input-row">
        <div class="form-group">
          <label class="form-label" for="reg-name">Full Name</label>
          <input type="text" id="reg-name" name="name" class="form-input"
                 placeholder="e.g. Anil Maharjan"
                 value="<?= htmlspecialchars($old['name'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label" for="reg-phone">Phone Number</label>
          <input type="tel" id="reg-phone" name="phone" class="form-input"
                 placeholder="98XXXXXXXX"
                 value="<?= htmlspecialchars($old['phone'] ?? '') ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="reg-email">Email Address</label>
        <input type="email" id="reg-email" name="email" class="form-input"
               placeholder="you@example.com"
               value="<?= htmlspecialchars($old['email'] ?? '') ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="reg-pass">Password</label>
        <div class="password-wrap">
          <input type="password" id="reg-pass" name="password" class="form-input"
                 placeholder="Min. 8 characters" minlength="8" required>
          <button type="button" class="pass-toggle" onclick="togglePass('reg-pass', this)">👁</button>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label" for="reg-confirm">Confirm Password</label>
        <div class="password-wrap">
          <input type="password" id="reg-confirm" name="confirm_password" class="form-input"
                 placeholder="Repeat your password" required>
          <button type="button" class="pass-toggle" onclick="togglePass('reg-confirm', this)">👁</button>
        </div>
      </div>

      <div class="consent">
        <input type="checkbox" id="consent" required>
        <label for="consent">
          I agree to MeroMaidan's <a href="#" target="_blank">Terms of Service</a> and
          <a href="#" target="_blank">Privacy Policy</a>. I confirm I am 13+ years old.
        </label>
      </div>

      <button type="submit" class="btn-register" id="btn-register">
        🚀 Create My Account
      </button>
    </form>

    <div class="sign-in-link">
      <a href="../index.php">← Back to MeroMaidan</a>
    </div>
  </div>

  <script>
    function togglePass(id, btn) {
      const input = document.getElementById(id);
      if (input.type === 'password') {
        input.type = 'text';
        btn.textContent = '🙈';
      } else {
        input.type = 'password';
        btn.textContent = '👁';
      }
    }
    // Live password match check
    const pass = document.getElementById('reg-pass');
    const confirm = document.getElementById('reg-confirm');
    confirm.addEventListener('input', () => {
      if (confirm.value && confirm.value !== pass.value) {
        confirm.style.borderColor = '#ef4444';
      } else {
        confirm.style.borderColor = '';
      }
    });
  </script>
</body>
</html>
