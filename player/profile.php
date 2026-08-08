<?php
require_once __DIR__ . '/../api/db.php';
requirePlayer();
$db       = getDB();
$playerId = $_SESSION['player_id'];

$msg   = '';
$error = '';

// Fetch player details
$stmt = $db->prepare("SELECT * FROM players WHERE id = :id");
$stmt->execute([':id' => $playerId]);
$player = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name    = trim($_POST['name'] ?? '');
    $phone   = trim($_POST['phone'] ?? '');
    $newPass = $_POST['new_password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (!$name) {
        $error = 'Full name is required.';
    } else {
        $db->prepare("UPDATE players SET name = :n, phone = :p WHERE id = :id")
           ->execute([':n' => $name, ':p' => $phone, ':id' => $playerId]);

        $_SESSION['player_name'] = $name;

        if ($newPass) {
            if (strlen($newPass) < 6) {
                $error = 'New password must be at least 6 characters.';
            } elseif ($newPass !== $confirm) {
                $error = 'Passwords do not match.';
            } else {
                $hash = password_hash($newPass, PASSWORD_BCRYPT);
                $db->prepare("UPDATE players SET password_hash = :h WHERE id = :id")->execute([':h' => $hash, ':id' => $playerId]);
                $msg = '✅ Profile & Password updated successfully!';
            }
        } else {
            $msg = '✅ Profile updated successfully!';
        }

        logAudit('update_profile', 'IAM', 'player', $playerId, "Player updated profile details");

        // Refresh player details
        $stmt->execute([':id' => $playerId]);
        $player = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Account Profile – MeroMaidan</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/player.css">
</head>
<body>
<div class="player-layout">

  <!-- Sidebar -->
  <aside class="player-sidebar">
    <div class="sidebar-logo">
      <div class="logo-badge">
        <svg viewBox="0 0 30 30" fill="none">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M8 8H15C18 8 20 9.5 20 12C20 14 18 15.5 15 15.5H8V8ZM8 18H16C20 18 22 20 22 23C22 26 19 27.5 15.5 27.5H8V18Z" stroke="white" stroke-width="2.2"/>
          <circle cx="15" cy="15" r="3.5" fill="white"/>
          <circle cx="15" cy="15" r="1.5" fill="#1BB955"/>
        </svg>
      </div>
      <div class="logo-title">Mero<span>Maidan</span></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Player Dashboard</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Overview</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> My Bookings</a>
      <a href="favorites.php" class="nav-link"><span class="icon">❤️</span> Saved Venues</a>
      <a href="profile.php" class="nav-link active"><span class="icon">⚙️</span> Account Profile</a>
      
      <div class="nav-section-label">Explore</div>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🔍</span> Browse Venues</a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-profile-summary">
        <div class="user-avatar"><?= strtoupper(substr($player['name'], 0, 2)) ?></div>
        <div class="user-info">
          <div class="name"><?= htmlspecialchars($player['name']) ?></div>
          <div class="role">Verified Player</div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-signout">🚪 Sign Out</a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="player-main">
    <header class="player-topbar">
      <div class="topbar-title">Account <span>Profile</span></div>
      <div class="topbar-actions">
        <a href="../index.php" class="btn-book-new">⚽ Browse Grounds</a>
      </div>
    </header>

    <div class="player-content">
      <div class="page-header">
        <h1>Profile & Preferences</h1>
        <p>Manage your account identity, phone number, and security credentials (USR-10).</p>
      </div>

      <?php if ($msg): ?><div style="background:#f0fdf4;color:#16a34a;padding:12px 16px;border-radius:10px;margin-bottom:20px;font-weight:700;"><?= $msg ?></div><?php endif; ?>
      <?php if ($error): ?><div style="background:#fef2f2;color:#dc2626;padding:12px 16px;border-radius:10px;margin-bottom:20px;font-weight:700;"><?= $error ?></div><?php endif; ?>

      <div class="content-card" style="max-width:540px;">
        <div class="card-header">
          <h3>👤 Personal Information</h3>
        </div>

        <form method="POST">
          <div style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px;">Full Name</label>
            <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($player['name']) ?>" required style="width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:10px;font-family:inherit;">
          </div>

          <div style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px;">Email Address</label>
            <input type="email" class="form-input" value="<?= htmlspecialchars($player['email']) ?>" disabled style="width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:10px;background:#f1f5f9;color:#64748b;">
            <span style="font-size:11px;color:#94a3b8;">Email address is verified and cannot be changed.</span>
          </div>

          <div style="margin-bottom:24px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px;">Phone Number</label>
            <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($player['phone'] ?? '') ?>" placeholder="98XXXXXXXX" style="width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:10px;font-family:inherit;">
          </div>

          <hr style="border:none;border-top:1px solid #e2e8f0;margin:24px 0;">

          <h4 style="font-size:15px;font-weight:800;color:#0f2740;margin-bottom:16px;">🔒 Change Password (Optional)</h4>

          <div style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px;">New Password</label>
            <input type="password" name="new_password" placeholder="Leave blank to keep current password" style="width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:10px;font-family:inherit;">
          </div>

          <div style="margin-bottom:24px;">
            <label style="display:block;font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px;">Confirm New Password</label>
            <input type="password" name="confirm_password" placeholder="Repeat new password" style="width:100%;padding:11px;border:1px solid #cbd5e1;border-radius:10px;font-family:inherit;">
          </div>

          <button type="submit" class="btn-action primary" style="padding:12px 24px;font-weight:800;width:100%;justify-content:center;">💾 Save Profile Changes</button>
        </form>
      </div>

    </div>
  </main>
</div>
</body>
</html>
