<?php
require_once __DIR__ . '/../api/db.php';
requireOwner();
$db        = getDB();
$ownerId   = $_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';

$msg = '';
$error = '';

// Handle booking status updates (Check-in, Complete, No-show)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); die('Your session expired.'); }
    $action    = $_POST['action'] ?? '';
    $bookingId = (int)($_POST['booking_id'] ?? 0);
    $newStatus = $_POST['new_status'] ?? '';

    if ($bookingId && in_array($newStatus, ['checked_in', 'in_progress', 'completed', 'no_show', 'cancelled'])) {
        $update=$db->prepare("UPDATE bookings b JOIN venues v ON v.id=b.venue_id SET b.status=:st WHERE b.id=:id AND v.owner_id=:owner");
        $update->execute([':st'=>$newStatus,':id'=>$bookingId,':owner'=>$ownerId]);
        if($update->rowCount()){
            logAudit('update_booking_status', 'FieldOps', 'booking', $bookingId, "Field Admin updated booking #$bookingId status to $newStatus");
            $msg = "✅ Booking status updated to " . ucfirst(str_replace('_', ' ', $newStatus));
        } else $error='Booking was not found for your venue.';
    }
}

// Fetch owner venues
$vStmt = $db->prepare("SELECT id, name FROM venues WHERE owner_id = :oid");
$vStmt->execute([':oid' => $ownerId]);
$myVenues = $vStmt->fetchAll();
$venueIds = array_column($myVenues, 'id');

$selectedDate = $_GET['date'] ?? date('Y-m-d');
$searchQuery  = trim($_GET['search'] ?? '');

$todayBookings = [];
if (!empty($venueIds)) {
    $inList = implode(',', $venueIds);
    $sql = "
        SELECT b.*, v.name as venue_name
        FROM bookings b
        JOIN venues v ON b.venue_id = v.id
        WHERE b.venue_id IN ($inList)
    ";

    $params = [];
    if ($searchQuery) {
        $sql .= " AND (b.booking_ref LIKE :q OR b.customer_name LIKE :q OR b.customer_phone LIKE :q)";
        $params[':q'] = "%$searchQuery%";
    } else {
        $sql .= " AND b.booking_date = :date";
        $params[':date'] = $selectedDate;
    }

    $sql .= " ORDER BY b.start_time ASC";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $todayBookings = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Field Operations & Check-in – MeroMaidan</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">

  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <div>
        <div class="sidebar-logo-text">Mero<span>Maidan</span></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px;">Field Operations Portal</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Field Operations</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Overview</a>
      <a href="field_ops.php" class="nav-link active"><span class="icon">📋</span> Daily Check-in</a>
      <a href="maintenance.php" class="nav-link"><span class="icon">🚧</span> Ground Readiness</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> All Bookings</a>
      <a href="slots.php" class="nav-link"><span class="icon">⏰</span> Manage Slots</a>
      
      <?php include __DIR__ . '/_promotion_nav.php'; ?>
      <div class="nav-section-label">Management</div>
      <a href="reports.php" class="nav-link"><span class="icon">📈</span> Reports & Analytics</a>
      <a href="settings.php" class="nav-link"><span class="icon">⚙️</span> Settings</a>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?= strtoupper(substr($ownerName, 0, 2)) ?></div>
        <div class="admin-user-info">
          <div class="admin-user-name"><?= htmlspecialchars($ownerName) ?></div>
          <div class="admin-user-role">Field Admin / Owner</div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">Field Operations <span>& Check-in</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Ground Check-in & Daily Schedule</h1>
        <p>Verify customer identity, perform instant check-in, and record match completion (FR-FIELD-001..005).</p>
      </div>

      <?php if ($msg): ?><div class="alert success" style="background:#f0fdf4;color:#16a34a;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;"><?= $msg ?></div><?php endif; ?>

      <!-- Filter & Search Controls -->
      <div class="data-card" style="margin-bottom:24px;padding:20px;">
        <form method="GET" style="display:flex;gap:16px;align-items:center;flex-wrap:wrap;">
          <div style="flex:1;min-width:260px;">
            <label style="display:block;font-size:11px;font-weight:800;color:#64748b;margin-bottom:4px;">🔍 Search Booking Ref / Phone / Customer</label>
            <input type="text" name="search" value="<?= htmlspecialchars($searchQuery) ?>" placeholder="e.g. MM1A2B3C or 9841..." class="form-input" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
          </div>
          <div>
            <label style="display:block;font-size:11px;font-weight:800;color:#64748b;margin-bottom:4px;">📅 Schedule Date</label>
            <input type="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" class="form-input" style="padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
          </div>
          <div style="margin-top:18px;">
            <button type="submit" class="btn btn-green">Filter Schedule</button>
            <?php if($searchQuery): ?>
              <a href="field_ops.php" class="btn btn-ghost">Clear Search</a>
            <?php endif; ?>
          </div>
        </form>
      </div>

      <!-- Bookings Check-in Table -->
      <div class="data-card">
        <div class="data-card-header">
          <h3>📋 Schedule for <?= date('l, M j, Y', strtotime($selectedDate)) ?> (<?= count($todayBookings) ?> bookings)</h3>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Time</th>
              <th>Booking Ref</th>
              <th>Customer</th>
              <th>Venue</th>
              <th>Payment</th>
              <th>Status</th>
              <th>Check-in & Execution Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($todayBookings as $b): ?>
              <tr>
                <td><strong><?= substr($b['start_time'], 0, 5) ?> – <?= substr($b['end_time'], 0, 5) ?></strong></td>
                <td><code style="background:#f1f5f9;padding:4px 8px;border-radius:6px;font-weight:800;"><?= htmlspecialchars($b['booking_ref']) ?></code></td>
                <td>
                  <strong><?= htmlspecialchars($b['customer_name']) ?></strong><br>
                  <a href="tel:<?= htmlspecialchars($b['customer_phone']) ?>" style="font-size:11px;color:#1BB955;font-weight:700;text-decoration:none;"><?= htmlspecialchars($b['customer_phone']) ?></a>
                </td>
                <td><?= htmlspecialchars($b['venue_name']) ?></td>
                <td><span style="font-size:11px;font-weight:700;text-transform:uppercase;"><?= htmlspecialchars($b['payment_method']) ?> (NPR <?= number_format($b['total_price']) ?>)</span></td>
                <td><span class="badge <?= $b['status'] ?>"><?= ucfirst(str_replace('_', ' ', $b['status'])) ?></span></td>
                <td>
                  <form method="POST" style="display:inline-flex;gap:6px;flex-wrap:wrap;">
                    <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
                    <input type="hidden" name="booking_id" value="<?= $b['id'] ?>">

                    <?php if (in_array($b['status'], ['confirmed', 'pending'])): ?>
                      <button type="submit" name="new_status" value="checked_in" class="btn btn-green btn-sm">✅ Check In</button>
                      <button type="submit" name="new_status" value="no_show" class="btn btn-red btn-sm" onclick="return confirm('Mark as No-show?')">⚠️ No-show</button>
                    <?php elseif ($b['status'] === 'checked_in'): ?>
                      <button type="submit" name="new_status" value="in_progress" class="btn btn-navy btn-sm">▶ Match In Progress</button>
                    <?php elseif ($b['status'] === 'in_progress'): ?>
                      <button type="submit" name="new_status" value="completed" class="btn btn-green btn-sm">🏁 Mark Completed</button>
                    <?php else: ?>
                      <span style="font-size:12px;color:#94a3b8;">Finalized</span>
                    <?php endif; ?>
                  </form>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($todayBookings)): ?>
              <tr><td colspan="7" style="text-align:center;padding:40px;color:#64748b;">No bookings scheduled for this date.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>
</body>
</html>
