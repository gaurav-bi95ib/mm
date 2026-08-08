<?php
require_once __DIR__ . '/../api/db.php';
requireOwner();
$db        = getDB();
$ownerId   = $_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';

// Fetch owner's venues
$venuesStmt = $db->prepare("SELECT id, name FROM venues WHERE owner_id = :oid");
$venuesStmt->execute([':oid' => $ownerId]);
$myVenues = $venuesStmt->fetchAll();
$venueIds = array_column($myVenues, 'id');

$totalRevenue = 0;
$totalBookingsCount = 0;
$confirmedCount = 0;
$cancelledCount = 0;
$monthlyData = [];

if (!empty($venueIds)) {
    $inList = implode(',', $venueIds);

    $totalRevenue = $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE venue_id IN ($inList) AND status = 'confirmed'")->fetchColumn();
    $totalBookingsCount = $db->query("SELECT COUNT(*) FROM bookings WHERE venue_id IN ($inList)")->fetchColumn();
    $confirmedCount = $db->query("SELECT COUNT(*) FROM bookings WHERE venue_id IN ($inList) AND status = 'confirmed'")->fetchColumn();
    $cancelledCount = $db->query("SELECT COUNT(*) FROM bookings WHERE venue_id IN ($inList) AND status = 'cancelled'")->fetchColumn();

    // Monthly breakdown for last 6 months
    $monthlyStmt = $db->query("
        SELECT DATE_FORMAT(booking_date, '%b %Y') as month_label,
               SUM(total_price) as revenue,
               COUNT(*) as count
        FROM bookings
        WHERE venue_id IN ($inList) AND status = 'confirmed'
        GROUP BY DATE_FORMAT(booking_date, '%Y-%m')
        ORDER BY booking_date DESC
        LIMIT 6
    ");
    $monthlyData = array_reverse($monthlyStmt->fetchAll());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Analytics & Reports – MeroMaidan Owner</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;600;700;800;900&display=swap" rel="stylesheet">
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
      <a href="reports.php" class="nav-link active"><span class="icon">📈</span> Reports & Analytics</a>
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
      <div class="topbar-title">Analytics & <span>Reports</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Revenue & Booking Performance</h1>
        <p>Tenant-scoped financial metrics and booking analytics.</p>
      </div>

      <!-- Stats Grid -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon-wrap amber">💰</div>
          <div>
            <div class="stat-num">NPR <?= number_format($totalRevenue) ?></div>
            <div class="stat-label">Total Revenue</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrap green">📅</div>
          <div>
            <div class="stat-num"><?= $confirmedCount ?></div>
            <div class="stat-label">Confirmed Bookings</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrap red">✕</div>
          <div>
            <div class="stat-num"><?= $cancelledCount ?></div>
            <div class="stat-label">Cancelled Bookings</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon-wrap navy">🏟️</div>
          <div>
            <div class="stat-num"><?= count($myVenues) ?></div>
            <div class="stat-label">Active Venues</div>
          </div>
        </div>
      </div>

      <!-- Revenue Chart / Breakdown Card -->
      <div class="data-card" style="margin-bottom:24px;">
        <div class="data-card-header">
          <h3>📊 Monthly Revenue Breakdown</h3>
        </div>
        <div style="padding:20px;">
          <?php if (!empty($monthlyData)): ?>
            <div style="display:flex;align-items:flex-end;gap:24px;height:200px;padding-top:20px;border-bottom:2px solid #e2e8f0;">
              <?php
                $maxRev = max(array_column($monthlyData, 'revenue')) ?: 1;
                foreach ($monthlyData as $m):
                  $heightPct = min(100, max(15, round(($m['revenue'] / $maxRev) * 100)));
              ?>
                <div style="flex:1;display:flex;flex-direction:column;align-items:center;height:100%;justify-content:flex-end;">
                  <div style="font-size:11px;font-weight:700;color:#1BB955;margin-bottom:6px;">NPR <?= number_format($m['revenue']) ?></div>
                  <div style="width:100%;max-width:48px;height:<?= $heightPct ?>%;background:linear-gradient(180deg,#1BB955,#14355d);border-radius:8px 8px 0 0;"></div>
                  <div style="font-size:12px;font-weight:700;color:#64748b;margin-top:8px;"><?= $m['month_label'] ?></div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php else: ?>
            <p style="color:#64748b;font-size:13px;text-align:center;padding:32px;">No historical booking data available yet.</p>
          <?php endif; ?>
        </div>
      </div>

    </div>
  </main>
</div>
</body>
</html>
