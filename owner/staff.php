<?php
// MeroMaidan - Tenant Staff & Roles Management
require_once __DIR__ . '/../api/db.php';
requireOwner();

$db = getDB();
$ownerId   = $_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';

$msg = '';
$err = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); die('Your session expired.'); }
    $action = $_POST['action'] ?? 'add_staff';
    if ($action === 'add_staff') {
        if (!checkSubscriptionLimits($ownerId, 'staff')) {
            $err = 'The standard operational staff limit has been reached. Contact support if your venue needs help.';
        } else {
            $name  = trim($_POST['name'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $phone = trim($_POST['phone'] ?? '');
            $role  = in_array($_POST['role'] ?? '', ['Manager','Receptionist','Field Admin'], true) ? $_POST['role'] : 'Field Admin';
            $venue_id = (int)($_POST['assigned_venue_id'] ?? 0);
            $plainPassword = $_POST['password'] ?? '';
            $pass  = password_hash($plainPassword, PASSWORD_BCRYPT);

            $venueCheck=$db->prepare("SELECT COUNT(*) FROM venues WHERE id=? AND owner_id=?");$venueCheck->execute([$venue_id,$ownerId]);
            if ($name && filter_var($email,FILTER_VALIDATE_EMAIL) && strlen($plainPassword)>=8 && (!$venue_id||(int)$venueCheck->fetchColumn()>0)) {
                try {
                    $stmt = $db->prepare("INSERT INTO tenant_staff (owner_id, name, email, phone, password_hash, role, assigned_venue_id, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'active')");
                    $stmt->execute([$ownerId, $name, $email, $phone, $pass, $role, $venue_id ?: null]);
                    logAudit('add_staff', 'staff', 'tenant_staff', $db->lastInsertId(), "Added staff $name with role $role");
                    $msg = '✅ Staff account created successfully!';
                } catch (\PDOException $e) {
                    $err = '❌ Failed to create staff. Email may already be registered.';
                }
            } else $err = 'Enter a valid name, email, venue and password of at least 8 characters.';
        }
    } elseif ($action === 'delete_staff') {
        $staff_id = (int)($_POST['staff_id'] ?? 0);
        if ($staff_id) {
            $stmt = $db->prepare("DELETE FROM tenant_staff WHERE id = ? AND owner_id = ?");
            $stmt->execute([$staff_id, $ownerId]);
            $msg = 'Staff member removed.';
        }
    }
}

// Fetch staff list
$sStmt = $db->prepare("SELECT ts.*, v.name as venue_name FROM tenant_staff ts LEFT JOIN venues v ON ts.assigned_venue_id = v.id WHERE ts.owner_id = ? ORDER BY ts.created_at DESC");
$sStmt->execute([$ownerId]);
$staffMembers = $sStmt->fetchAll();

// Fetch venues for dropdown
$vStmt = $db->prepare("SELECT id, name FROM venues WHERE owner_id = ?");
$vStmt->execute([$ownerId]);
$myVenues = $vStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Staff & Roles - MeroMaidan Owner</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
  <style>
    .grid-layout { display: grid; grid-template-columns: 1fr 340px; gap: 24px; }
  </style>
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
      <a href="field_ops.php" class="nav-link"><span class="icon">📋</span> Field Operations</a>
      <a href="staff.php" class="nav-link active"><span class="icon">👥</span> Staff & Roles</a>
      <a href="subscription.php" class="nav-link"><span class="icon">⭐</span> Subscription</a>
      <a href="notifications.php" class="nav-link"><span class="icon">🔔</span> Notifications</a>
      <?php include __DIR__ . '/_promotion_nav.php'; ?>
      <div class="nav-section-label">Account</div>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?=strtoupper(substr($ownerName,0,2))?></div>
        <div class="admin-user-info">
          <div class="admin-user-name"><?=htmlspecialchars($ownerName)?></div>
          <div class="admin-user-role">Venue Owner</div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">👥 Staff & <span>Role Assignments</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Tenant Staff Management</h1>
        <p>Assign staff roles (Manager, Receptionist, Field Admin) to delegate venue operations.</p>
      </div>

      <?php if ($msg): ?><div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:12px;border-radius:8px;color:#166534;margin-bottom:20px;font-size:13px;font-weight:700;"><?=$msg?></div><?php endif; ?>
      <?php if ($err): ?><div style="background:#fef2f2;border:1px solid #fecaca;padding:12px;border-radius:8px;color:#991b1b;margin-bottom:20px;font-size:13px;font-weight:700;"><?=$err?></div><?php endif; ?>

      <div class="grid-layout">
        <!-- Staff List -->
        <div class="data-card">
          <div class="data-card-header">
            <h3>👥 Active Staff Members (<?= count($staffMembers) ?>)</h3>
          </div>
          <table class="custom-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Role</th>
                <th>Assigned Venue</th>
                <th>Contact</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php if (empty($staffMembers)): ?>
                <tr><td colspan="5" style="text-align:center;padding:30px;color:#64748b;">No staff added yet. Use the form to invite your first team member.</td></tr>
              <?php else: ?>
                <?php foreach ($staffMembers as $s): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($s['name']) ?></strong></td>
                    <td><span class="badge active"><?= htmlspecialchars($s['role']) ?></span></td>
                    <td><?= htmlspecialchars($s['venue_name'] ?? 'All Venues') ?></td>
                    <td><?= htmlspecialchars($s['email']) ?><br><small><?= htmlspecialchars($s['phone']) ?></small></td>
                    <td>
                      <form method="POST" style="display:inline;" onsubmit="return confirm('Remove staff member?');">
                        <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
                        <input type="hidden" name="action" value="delete_staff">
                        <input type="hidden" name="staff_id" value="<?= $s['id'] ?>">
                        <button type="submit" class="btn btn-ghost btn-sm" style="color:#ef4444;">Remove</button>
                      </form>
                    </td>
                  </tr>
                <?php endforeach; ?>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Add Staff Form -->
        <div class="data-card" style="padding:20px;">
          <h3 style="font-size:16px;margin-bottom:16px;">➕ Add Staff Member</h3>
          <form method="POST">
            <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
            <input type="hidden" name="action" value="add_staff">
            
            <div class="form-group" style="margin-bottom:12px;">
              <label class="form-label">Full Name</label>
              <input type="text" name="name" class="form-input" required placeholder="e.g. Hari Karki">
            </div>

            <div class="form-group" style="margin-bottom:12px;">
              <label class="form-label">Email Address</label>
              <input type="email" name="email" class="form-input" required placeholder="hari@example.com">
            </div>

            <div class="form-group" style="margin-bottom:12px;">
              <label class="form-label">Phone Number</label>
              <input type="tel" name="phone" class="form-input" placeholder="98XXXXXXXX">
            </div>

            <div class="form-group" style="margin-bottom:12px;">
              <label class="form-label">Operational Role</label>
              <select name="role" class="form-input">
                <option value="Field Admin">Field Admin (Check-in & Grounds)</option>
                <option value="Manager">Venue Manager (Full Access)</option>
                <option value="Receptionist">Receptionist (Bookings only)</option>
                <option value="Ground Supervisor">Ground Supervisor (Maintenance)</option>
              </select>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
              <label class="form-label">Assign Venue</label>
              <select name="assigned_venue_id" class="form-input">
                <option value="0">All Venues</option>
                <?php foreach ($myVenues as $v): ?>
                  <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group" style="margin-bottom:16px;">
              <label class="form-label">Initial Password</label>
              <input type="password" name="password" class="form-input" value="Staff@1234" required>
            </div>

            <button type="submit" class="btn btn-green" style="width:100%;">➕ Create Staff Account</button>
          </form>
        </div>
      </div>

    </div>
  </main>
</div>
</body>
</html>
