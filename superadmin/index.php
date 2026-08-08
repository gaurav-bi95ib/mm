<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db = getDB();

// Stats
$stats = [];
$stats['venues']   = $db->query("SELECT COUNT(*) FROM venues WHERE status='active'")->fetchColumn();
$stats['pending']  = $db->query("SELECT COUNT(*) FROM venues WHERE status='pending'")->fetchColumn();
$stats['owners']   = $db->query("SELECT COUNT(*) FROM venue_owners WHERE status='active'")->fetchColumn();
$stats['bookings'] = $db->query("SELECT COUNT(*) FROM bookings WHERE booking_date = CURDATE()")->fetchColumn();
$stats['revenue']  = $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE MONTH(booking_date)=MONTH(CURDATE()) AND status='confirmed'")->fetchColumn();
$stats['apps']     = $db->query("SELECT COUNT(*) FROM owner_applications WHERE status='new'")->fetchColumn();

// Recent bookings
$recentBookings = $db->query("
    SELECT b.*, v.name as venue_name
    FROM bookings b JOIN venues v ON b.venue_id=v.id
    ORDER BY b.created_at DESC LIMIT 8
")->fetchAll();

// Pending venues
$pendingVenues = $db->query("
    SELECT v.*, vo.name as owner_name, vo.email as owner_email
    FROM venues v LEFT JOIN venue_owners vo ON v.owner_id=vo.id
    WHERE v.status='pending' ORDER BY v.created_at DESC LIMIT 5
")->fetchAll();

$adminName = $_SESSION['superadmin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Super Admin Dashboard – MeroMaidan</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">

  <!-- Sidebar -->
  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-logo">
      <div>
        <div class="sidebar-logo-text">Mero<span>Maidan</span> <span class="sidebar-badge">ADMIN</span></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px;">Super Admin Panel</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="index.php" class="nav-link active"><span class="icon">📊</span> Dashboard</a>

      <div class="nav-section-label">Management</div>
      <a href="venues.php" class="nav-link">
        <span class="icon">🏟️</span> Venues
        <?php if($stats['pending']>0): ?><span class="badge orange"><?= $stats['pending'] ?></span><?php endif; ?>
      </a>
      <a href="owners.php" class="nav-link"><span class="icon">👤</span> Owners</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="applications.php" class="nav-link">
        <span class="icon">📋</span> Applications
        <?php if($stats['apps']>0): ?><span class="badge orange"><?= $stats['apps'] ?></span><?php endif; ?>
      </a>
      <a href="plans.php" class="nav-link"><span class="icon">⭐</span> Plans</a>

      <div class="nav-section-label">System</div>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?= strtoupper(substr($adminName,0,2)) ?></div>
        <div class="admin-user-info">
          <div class="admin-user-name"><?= htmlspecialchars($adminName) ?></div>
          <div class="admin-user-role">Super Administrator</div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>

  <!-- Main -->
  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">Dashboard <span>Overview</span></div>
      <div class="topbar-actions">
        <div class="topbar-notif"><span>🔔</span><div class="notif-dot"></div></div>
        <div class="admin-avatar" style="width:36px;height:36px;font-size:13px;"><?= strtoupper(substr($adminName,0,2)) ?></div>
      </div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Welcome back, <?= htmlspecialchars(explode(' ', $adminName)[0]) ?>! 👋</h1>
        <p>Here's what's happening on MeroMaidan today — <?= date('l, F j, Y') ?></p>
      </div>

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon-wrap green">🏟️</div>
          <div><div class="stat-num"><?= $stats['venues'] ?></div><div class="stat-label">Active Venues</div><div class="stat-change up">+<?= $stats['pending'] ?> pending</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap navy">👤</div>
          <div><div class="stat-num"><?= $stats['owners'] ?></div><div class="stat-label">Active Owners</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap orange">📅</div>
          <div><div class="stat-num"><?= $stats['bookings'] ?></div><div class="stat-label">Today's Bookings</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap amber">💰</div>
          <div><div class="stat-num">NPR <?= number_format($stats['revenue']) ?></div><div class="stat-label">Monthly Revenue</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap red">📋</div>
          <div><div class="stat-num"><?= $stats['apps'] ?></div><div class="stat-label">New Applications</div></div>
        </div>
      </div>

      <!-- Grid -->
      <div style="display:grid;grid-template-columns:1fr 380px;gap:20px;" class="dashboard-grid">

        <!-- Recent Bookings -->
        <div class="data-card">
          <div class="data-card-header">
            <h3>📅 Recent Bookings</h3>
            <a href="bookings.php" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <table class="data-table">
            <thead><tr>
              <th>Ref</th><th>Customer</th><th>Venue</th><th>Date/Time</th><th>Amount</th><th>Status</th>
            </tr></thead>
            <tbody>
            <?php foreach($recentBookings as $b): ?>
            <tr>
              <td><code style="font-size:11px;background:#f1f5f9;padding:3px 8px;border-radius:6px;"><?= htmlspecialchars($b['booking_ref']) ?></code></td>
              <td><div><?= htmlspecialchars($b['customer_name']) ?></div><div style="font-size:11px;color:#64748b;"><?= htmlspecialchars($b['customer_phone']) ?></div></td>
              <td><?= htmlspecialchars($b['venue_name']) ?></td>
              <td><div><?= date('M j', strtotime($b['booking_date'])) ?></div><div style="font-size:11px;color:#64748b;"><?= substr($b['start_time'],0,5) ?> – <?= substr($b['end_time'],0,5) ?></div></td>
              <td><strong>NPR <?= number_format($b['total_price']) ?></strong></td>
              <td><span class="badge <?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($recentBookings)): ?>
            <tr><td colspan="6" style="text-align:center;color:#64748b;padding:32px;">No bookings yet</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>

        <!-- Pending Venues -->
        <div class="data-card">
          <div class="data-card-header">
            <h3>⏳ Pending Approval</h3>
            <a href="venues.php?status=pending" class="btn btn-ghost btn-sm">View All</a>
          </div>
          <div style="padding:8px 0;">
          <?php if(empty($pendingVenues)): ?>
          <div style="text-align:center;padding:40px;color:#64748b;font-size:13px;">✅ All venues approved!</div>
          <?php endif; ?>
          <?php foreach($pendingVenues as $v): ?>
          <div style="padding:14px 20px;border-bottom:1px solid #f1f5f9;">
            <div style="font-size:13px;font-weight:800;color:#0f2740;"><?= htmlspecialchars($v['name']) ?></div>
            <div style="font-size:12px;color:#64748b;margin:2px 0;"><?= htmlspecialchars($v['city']) ?> · <?= htmlspecialchars($v['sport_type']) ?></div>
            <div style="font-size:11px;color:#64748b;">Owner: <?= htmlspecialchars($v['owner_name']) ?></div>
            <div style="display:flex;gap:8px;margin-top:8px;">
              <a href="venues.php?action=approve&id=<?= $v['id'] ?>" class="btn btn-green btn-sm">✓ Approve</a>
              <a href="venues.php?action=reject&id=<?= $v['id'] ?>" class="btn btn-red btn-sm">✕ Reject</a>
            </div>
          </div>
          <?php endforeach; ?>
          </div>
        </div>

      </div><!-- /grid -->
    </div><!-- /content -->
  </main>
</div>
</body>
</html>
