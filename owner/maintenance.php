<?php
require_once __DIR__ . '/../api/db.php';
requireOwner();
$db        = getDB();
$ownerId   = $_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';

$msg = '';
$error = '';

// Fetch owner venues
$vStmt = $db->prepare("SELECT id, name FROM venues WHERE owner_id = :oid");
$vStmt->execute([':oid' => $ownerId]);
$myVenues = $vStmt->fetchAll();
$venueIds = array_column($myVenues, 'id');

// Handle creating maintenance block
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $venueId   = (int)($_POST['venue_id'] ?? 0);
    $blockDate = $_POST['block_date'] ?? '';
    $startTime = $_POST['start_time'] ?? '';
    $endTime   = $_POST['end_time'] ?? '';
    $reason    = trim($_POST['reason'] ?? '');

    if (!$venueId || !$blockDate || !$startTime || !$endTime) {
        $error = 'Please select a venue, block date, and time range.';
    } else {
        $stmt = $db->prepare("
            INSERT INTO maintenance_blocks (venue_id, block_date, start_time, end_time, reason, created_by_owner)
            VALUES (:vid, :bd, :st, :et, :r, :oid)
        ");
        $stmt->execute([':vid' => $venueId, ':bd' => $blockDate, ':st' => $startTime, ':et' => $endTime, ':r' => $reason, ':oid' => $ownerId]);
        logAudit('create_maintenance_block', 'Availability', 'maintenance_block', $db->lastInsertId(), "Blocked venue #$venueId on $blockDate ($startTime - $endTime)");
        $msg = '🚧 Maintenance block added! Slot reservations will be prevented for this period.';
    }
}

// Handle deleting maintenance block
if (isset($_GET['delete'])) {
    $delId = (int)$_GET['delete'];
    $db->prepare("DELETE FROM maintenance_blocks WHERE id = :id")->execute([':id' => $delId]);
    logAudit('delete_maintenance_block', 'Availability', 'maintenance_block', $delId, "Removed maintenance block #$delId");
    header('Location: maintenance.php');
    exit;
}

// Fetch maintenance blocks
$blocks = [];
if (!empty($venueIds)) {
    $inList = implode(',', $venueIds);
    $stmt = $db->query("
        SELECT mb.*, v.name as venue_name
        FROM maintenance_blocks mb
        JOIN venues v ON mb.venue_id = v.id
        WHERE mb.venue_id IN ($inList)
        ORDER BY mb.block_date DESC, mb.start_time ASC
    ");
    $blocks = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Ground Maintenance Blocks – MeroMaidan Owner</title>
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
      <a href="field_ops.php" class="nav-link"><span class="icon">📋</span> Field Ops & Check-in</a>
      <a href="slots.php" class="nav-link"><span class="icon">⏰</span> Manage Slots</a>
      <a href="maintenance.php" class="nav-link active"><span class="icon">🚧</span> Maintenance Blocks</a>
      <a href="promotions.php" class="nav-link"><span class="icon">🎟️</span> Promotions & Coupons</a>
      <a href="customers.php" class="nav-link"><span class="icon">👥</span> Customers (CRM)</a>
      <a href="reports.php" class="nav-link"><span class="icon">📈</span> Reports & Analytics</a>
      <a href="settings.php" class="nav-link"><span class="icon">⚙️</span> Business Settings</a>
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
      <div class="topbar-title">Ground <span>Maintenance Blocks</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Maintenance & Slot Blocking</h1>
        <p>Prevent public booking during pitch maintenance, repairs, or private events (FR-AVL-002, FAD-05).</p>
      </div>

      <?php if ($msg): ?><div class="alert success" style="background:#f0fdf4;color:#16a34a;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;"><?= $msg ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert error" style="background:#fef2f2;color:#dc2626;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;"><?= $error ?></div><?php endif; ?>

      <div style="display:grid;grid-template-columns:360px 1fr;gap:24px;">

        <!-- Block Slot Form -->
        <div class="data-card">
          <div class="data-card-header">
            <h3>🚧 Add Maintenance Block</h3>
          </div>
          <form method="POST" style="padding:20px;">
            <div class="form-group" style="margin-bottom:14px;">
              <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">Venue</label>
              <select name="venue_id" class="form-input" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
                <?php foreach ($myVenues as $v): ?>
                  <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
              <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">Block Date</label>
              <input type="date" name="block_date" value="<?= date('Y-m-d') ?>" class="form-input" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
              <div>
                <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">Start Time</label>
                <input type="time" name="start_time" value="06:00" class="form-input" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
              </div>
              <div>
                <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">End Time</label>
                <input type="time" name="end_time" value="12:00" class="form-input" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
              </div>
            </div>

            <div class="form-group" style="margin-bottom:20px;">
              <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">Reason / Notes</label>
              <textarea name="reason" placeholder="e.g. Turf watering, net repair, private tournament" class="form-input" style="width:100%;height:80px;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-family:inherit;"></textarea>
            </div>

            <button type="submit" class="btn btn-red" style="width:100%;padding:12px;font-weight:800;">🔒 Apply Maintenance Block</button>
          </form>
        </div>

        <!-- Maintenance Blocks List -->
        <div class="data-card">
          <div class="data-card-header">
            <h3>📋 Active Maintenance Blocks</h3>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Venue</th>
                <th>Date</th>
                <th>Time Range</th>
                <th>Reason</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($blocks as $b): ?>
                <tr>
                  <td><strong><?= htmlspecialchars($b['venue_name']) ?></strong></td>
                  <td><strong><?= date('M j, Y', strtotime($b['block_date'])) ?></strong></td>
                  <td><span class="badge orange"><?= substr($b['start_time'], 0, 5) ?> – <?= substr($b['end_time'], 0, 5) ?></span></td>
                  <td><?= htmlspecialchars($b['reason'] ?: 'Pitch Maintenance') ?></td>
                  <td>
                    <a href="?delete=<?= $b['id'] ?>" class="btn btn-red btn-sm" onclick="return confirm('Remove this block?')">✕ Remove</a>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($blocks)): ?>
                <tr><td colspan="5" style="text-align:center;padding:40px;color:#64748b;">No maintenance blocks configured. All grounds are operating normally.</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>

    </div>
  </main>
</div>
</body>
</html>
