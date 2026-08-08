<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db        = getDB();
$adminName = $_SESSION['superadmin_name'] ?? 'Admin';

// Platform Stats
$totalVenues   = $db->query("SELECT COUNT(*) FROM venues")->fetchColumn();
$totalOwners   = $db->query("SELECT COUNT(*) FROM venue_owners")->fetchColumn();
$totalPlayers  = $db->query("SELECT COUNT(*) FROM players")->fetchColumn();
$totalBookings = $db->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$totalGrossRev = $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status = 'confirmed'")->fetchColumn();
$monthlyRev    = $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status = 'confirmed' AND MONTH(booking_date) = MONTH(CURDATE())")->fetchColumn();

// Top Venues by Revenue
$topVenues = $db->query("
    SELECT v.name, v.city, v.sport_type,
           COUNT(b.id) as booking_count,
           SUM(CASE WHEN b.status='confirmed' THEN b.total_price ELSE 0 END) as revenue
    FROM venues v
    LEFT JOIN bookings b ON v.id = b.venue_id
    GROUP BY v.id
    ORDER BY revenue DESC
    LIMIT 6
")->fetchAll();

$pendingCount = $db->query("SELECT COUNT(*) FROM venues WHERE status='pending'")->fetchColumn();
$appsCount    = $db->query("SELECT COUNT(*) FROM owner_applications WHERE status='new'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Platform Reports – MeroMaidan Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">

  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <div>
        <div class="sidebar-logo-text">Mero<span>Maidan</span> <span class="sidebar-badge">ADMIN</span></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px;">Super Admin Panel</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Dashboard</a>
      <a href="reports.php" class="nav-link active"><span class="icon">📈</span> Reports</a>

      <div class="nav-section-label">Management</div>
      <a href="venues.php" class="nav-link"><span class="icon">🏟️</span> Venues <?php if($pendingCount>0): ?><span class="badge orange"><?=$pendingCount?></span><?php endif; ?></a>
      <a href="owners.php" class="nav-link"><span class="icon">👤</span> Owners</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="applications.php" class="nav-link"><span class="icon">📋</span> Applications <?php if($appsCount>0): ?><span class="badge orange"><?=$appsCount?></span><?php endif; ?></a>
      <a href="plans.php" class="nav-link"><span class="icon">⭐</span> Plans</a>

      <div class="nav-section-label">System Governance</div>
      <a href="audit.php" class="nav-link"><span class="icon">🛡️</span> Audit Logs</a>
      <a href="settings.php" class="nav-link"><span class="icon">⚙️</span> Settings</a>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?= strtoupper(substr($adminName,0,2)) ?></div>
        <div class="admin-user-info">
          <div class="admin-user-name"><?= htmlspecialchars($adminName) ?></div>
          <div class="admin-user-role">Super Admin</div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">Platform <span>Reports & Analytics</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Platform Financial & Operational Performance</h1>
        <p>Ecosystem metrics across all tenants and registered players.</p>
      </div>

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon-wrap amber">💰</div>
          <div><div class="stat-num">NPR <?= number_format($totalGrossRev) ?></div><div class="stat-label">Gross Platform Revenue</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap green">📅</div>
          <div><div class="stat-num">NPR <?= number_format($monthlyRev) ?></div><div class="stat-label">This Month's Revenue</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap navy">⚽</div>
          <div><div class="stat-num"><?= $totalPlayers ?></div><div class="stat-label">Registered Players</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap orange">🏟️</div>
          <div><div class="stat-num"><?= $totalVenues ?></div><div class="stat-label">Total Venues</div></div>
        </div>
      </div>

      <!-- Top Venues Data Card -->
      <div class="data-card">
        <div class="data-card-header">
          <h3>🏆 Top Performing Venues</h3>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Venue Name</th>
              <th>City</th>
              <th>Sport</th>
              <th>Total Bookings</th>
              <th>Gross Revenue</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($topVenues as $v): ?>
              <tr>
                <td><strong><?= htmlspecialchars($v['name']) ?></strong></td>
                <td><?= htmlspecialchars($v['city']) ?></td>
                <td><span class="badge pending"><?= htmlspecialchars($v['sport_type']) ?></span></td>
                <td><?= number_format($v['booking_count']) ?> bookings</td>
                <td><strong>NPR <?= number_format($v['revenue']) ?></strong></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($topVenues)): ?>
              <tr><td colspan="5" style="text-align:center;padding:32px;color:#64748b;">No revenue data recorded yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>
</body>
</html>
