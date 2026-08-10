<?php
require_once __DIR__ . '/../api/db.php';
requireOwner();
$db        = getDB();
$ownerId   = $_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';

$msg = '';
$error = '';

// Fetch owner details
$stmt = $db->prepare("SELECT * FROM venue_owners WHERE id = :id");
$stmt->execute([':id' => $ownerId]);
$owner = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); die('Your session expired.'); }
    $name     = trim($_POST['name'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $business = trim($_POST['business_name'] ?? '');
    $newPass  = $_POST['new_password'] ?? '';

    if (!$name || !preg_match('/^(?:\+977[- ]?)?9[678]\d{8}$/', $phone)) {
        $error = 'Enter a valid owner name and Nepal mobile number.';
    } elseif ($newPass !== '' && strlen($newPass) < 8) {
        $error = 'New password must be at least 8 characters.';
    } else {
        $db->prepare("UPDATE venue_owners SET name = :n, phone = :p, business_name = :b WHERE id = :id")
           ->execute([':n' => $name, ':p' => $phone, ':b' => $business, ':id' => $ownerId]);

        $_SESSION['owner_name'] = $name;

        if ($newPass !== '') {
            $hash = password_hash($newPass, PASSWORD_BCRYPT);
            $db->prepare("UPDATE venue_owners SET password_hash = :h WHERE id = :id")->execute([':h' => $hash, ':id' => $ownerId]);
        }

        logAudit('update_settings', 'Tenant', 'venue_owner', $ownerId, "Owner updated business profile");
        $msg = '✅ Profile & Settings updated successfully!';

        // Refresh details
        $stmt->execute([':id' => $ownerId]);
        $owner = $stmt->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Business Settings – MeroMaidan Owner</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">

  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <div>
        <div class="sidebar-logo-text">Mero<span>Maidan</span></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px;">Venue Owner Panel</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">My Dashboard</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Overview</a>
      <a href="venue.php" class="nav-link"><span class="icon">🏟️</span> My Venue</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="slots.php" class="nav-link"><span class="icon">⏰</span> Manage Slots</a>
      <a href="customers.php" class="nav-link"><span class="icon">👥</span> Customers (CRM)</a>
      <a href="reports.php" class="nav-link"><span class="icon">📈</span> Reports & Analytics</a>
      <a href="settings.php" class="nav-link active"><span class="icon">⚙️</span> Business Settings</a>
      <?php include __DIR__ . '/_promotion_nav.php'; ?>
      <div class="nav-section-label">Account</div>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?= strtoupper(substr($ownerName, 0, 2)) ?></div>
        <div class="admin-user-info">
          <div class="admin-user-name"><?= htmlspecialchars($ownerName) ?></div>
          <div class="admin-user-role">Venue Owner</div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">Business <span>Settings</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Business & Account Profile</h1>
        <p>Manage tenant contact identity and account security.</p>
      </div>

      <?php if ($msg): ?><div class="alert success" style="background:#f0fdf4;color:#16a34a;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;"><?= $msg ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert error" style="background:#fef2f2;color:#dc2626;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;"><?= $error ?></div><?php endif; ?>

      <div class="data-card" style="max-width:600px;">
        <div class="data-card-header">
          <h3>⚙️ Tenant Profile Details</h3>
        </div>
        <form method="POST" style="padding:24px;">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <div class="form-group" style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:6px;">Owner Full Name</label>
            <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($owner['name']) ?>" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
          </div>

          <div class="form-group" style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:6px;">Company / Business Name</label>
            <input type="text" name="business_name" class="form-input" value="<?= htmlspecialchars($owner['business_name'] ?? '') ?>" placeholder="e.g. Royal Futsal Pvt Ltd" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
          </div>

          <div class="form-group" style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:6px;">Contact Phone Number</label>
            <input type="tel" name="phone" class="form-input" value="<?= htmlspecialchars($owner['phone']) ?>" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
          </div>

          <div class="form-group" style="margin-bottom:16px;">
            <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:6px;">Email Address (Primary Login)</label>
            <input type="email" class="form-input" value="<?= htmlspecialchars($owner['email']) ?>" disabled style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;background:#f1f5f9;">
            <span style="font-size:11px;color:#94a3b8;">Email address cannot be changed directly. Contact SuperAdmin for assistance.</span>
          </div>

          <div class="form-group" style="margin-bottom:24px;">
            <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:6px;">New Password (Optional)</label>
            <input type="password" name="new_password" class="form-input" placeholder="Leave blank to keep current password" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
          </div>

          <button type="submit" class="btn btn-green" style="padding:12px 24px;font-weight:800;">💾 Save Changes</button>
        </form>
      </div>

    </div>
  </main>
</div>
</body>
</html>
