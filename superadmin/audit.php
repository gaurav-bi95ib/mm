<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db        = getDB();
$adminName = $_SESSION['superadmin_name'] ?? 'Admin';

$filterModule = $_GET['module'] ?? 'all';
$sql = "SELECT * FROM audit_logs WHERE 1=1";
$params = [];

if ($filterModule !== 'all') {
    $sql .= " AND module = :mod";
    $params[':mod'] = $filterModule;
}

$sql .= " ORDER BY created_at DESC LIMIT 100";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$logs = $stmt->fetchAll();

$pendingCount = $db->query("SELECT COUNT(*) FROM venues WHERE status='pending'")->fetchColumn();
$appsCount    = $db->query("SELECT COUNT(*) FROM owner_applications WHERE status='new'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Audit Logs – MeroMaidan Admin</title>
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
      <a href="reports.php" class="nav-link"><span class="icon">📈</span> Reports</a>

      <div class="nav-section-label">Management</div>
      <a href="venues.php" class="nav-link"><span class="icon">🏟️</span> Venues <?php if($pendingCount>0): ?><span class="badge orange"><?=$pendingCount?></span><?php endif; ?></a>
      <a href="owners.php" class="nav-link"><span class="icon">👤</span> Owners</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="applications.php" class="nav-link"><span class="icon">📋</span> Applications <?php if($appsCount>0): ?><span class="badge orange"><?=$appsCount?></span><?php endif; ?></a>
      <a href="plans.php" class="nav-link"><span class="icon">💳</span> Commercial Services</a>
      <a href="recommended-promotions.php" class="nav-link"><span class="icon">📍</span> Recommended Venue</a>
      <a href="event-promotions.php" class="nav-link"><span class="icon">📣</span> Event Campaigns</a>
      <a href="cms.php" class="nav-link"><span class="icon">📝</span> CMS & Content</a>

      <div class="nav-section-label">System Governance</div>
      <a href="audit.php" class="nav-link active"><span class="icon">🛡️</span> Audit Logs</a>
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
      <div class="topbar-title">System <span>Audit Trail</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Immutable Audit Logs</h1>
        <p>Traceability record for security events, authentication, status transitions, and data edits.</p>
      </div>

      <!-- Filter Bar -->
      <div style="display:flex;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
        <a href="?module=all" class="btn btn-ghost btn-sm <?= $filterModule==='all'?'active':'' ?>">All Modules</a>
        <a href="?module=IAM" class="btn btn-ghost btn-sm <?= $filterModule==='IAM'?'active':'' ?>">IAM / Auth</a>
        <a href="?module=Booking" class="btn btn-ghost btn-sm <?= $filterModule==='Booking'?'active':'' ?>">Bookings</a>
        <a href="?module=Tenant" class="btn btn-ghost btn-sm <?= $filterModule==='Tenant'?'active':'' ?>">Tenant</a>
      </div>

      <div class="data-card">
        <table class="data-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Timestamp</th>
              <th>Actor</th>
              <th>Module</th>
              <th>Action</th>
              <th>Description</th>
              <th>IP Address</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($logs as $l): ?>
              <tr>
                <td><code>#<?= $l['id'] ?></code></td>
                <td style="font-size:12px;color:#64748b;"><?= date('M j, Y H:i:s', strtotime($l['created_at'])) ?></td>
                <td>
                  <strong><?= htmlspecialchars($l['actor_name'] ?: 'System') ?></strong><br>
                  <span style="font-size:11px;color:#94a3b8;text-transform:uppercase;"><?= htmlspecialchars($l['actor_type']) ?></span>
                </td>
                <td><span class="badge pending"><?= htmlspecialchars($l['module'] ?: 'System') ?></span></td>
                <td><code><?= htmlspecialchars($l['action']) ?></code></td>
                <td><?= htmlspecialchars($l['description'] ?: '-') ?></td>
                <td style="font-size:11px;color:#64748b;"><?= htmlspecialchars($l['ip_address'] ?: '-') ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($logs)): ?>
              <tr><td colspan="7" style="text-align:center;color:#64748b;padding:32px;">No audit events recorded yet. Perform actions to see audit logs here.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>
</body>
</html>
