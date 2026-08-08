<?php
// MeroMaidan - SuperAdmin System Alert Center
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();

$db = getDB();

if (isset($_GET['mark_all'])) {
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE role = 'superadmin'")->execute();
    header("Location: notifications.php");
    exit;
}

$stmt = $db->prepare("SELECT * FROM notifications WHERE role = 'superadmin' ORDER BY created_at DESC");
$stmt->execute();
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>System Alerts - SuperAdmin MeroMaidan</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', system-ui, sans-serif; }
    body { background: #f8fafc; color: #1e293b; display: flex; min-height: 100vh; }
    .sidebar { width: 240px; background: #0f172a; color: white; padding: 24px; display: flex; flex-direction: column; gap: 8px; }
    .sidebar h2 { font-size: 20px; font-weight: 800; color: #f97316; margin-bottom: 24px; }
    .nav-item { padding: 12px 16px; border-radius: 8px; color: #94a3b8; text-decoration: none; font-size: 14px; font-weight: 500; }
    .nav-item:hover, .nav-item.active { background: #1e293b; color: white; }
    .main-content { flex: 1; padding: 32px; max-width: 900px; }
    .page-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
    .page-title { font-size: 24px; font-weight: 800; }
    .btn-read-all { background: #e2e8f0; color: #334155; padding: 8px 16px; border-radius: 6px; text-decoration: none; font-size: 13px; font-weight: 600; }
    .notif-list { display: flex; flex-direction: column; gap: 12px; }
    .notif-card { background: white; border-radius: 10px; padding: 16px 20px; border-left: 4px solid #ef4444; box-shadow: 0 2px 5px rgba(0,0,0,0.03); display: flex; gap: 16px; align-items: flex-start; }
    .notif-card.unread { background: #fef2f2; border-left-color: #ef4444; }
    .notif-card.read { border-left-color: #cbd5e1; opacity: 0.8; }
    .notif-icon { font-size: 20px; background: #fee2e2; padding: 10px; border-radius: 50%; }
    .notif-body h4 { font-size: 15px; color: #0f172a; margin-bottom: 4px; }
    .notif-body p { font-size: 13px; color: #475569; line-height: 1.4; }
    .notif-time { font-size: 11px; color: #94a3b8; margin-top: 6px; }
  </style>
</head>
<body>

<div class="sidebar">
  <h2>MeroAdmin</h2>
  <a href="index.php" class="nav-item">📊 Governance</a>
  <a href="applications.php" class="nav-item">📋 Applications</a>
  <a href="owners.php" class="nav-item">🏢 Tenants</a>
  <a href="plans.php" class="nav-item">💳 SaaS Plans</a>
  <a href="cms.php" class="nav-item">📝 CMS & Content</a>
  <a href="audit.php" class="nav-item">🛡️ Audit Logs</a>
  <a href="notifications.php" class="nav-item active">🔔 System Alerts</a>
  <a href="../auth/logout.php" class="nav-item" style="margin-top:auto; color:#ef4444;">🚪 Logout</a>
</div>

<div class="main-content">
  <div class="page-header">
    <h1 class="page-title">Platform System Alerts</h1>
    <a href="notifications.php?mark_all=1" class="btn-read-all">✓ Mark All as Read</a>
  </div>

  <div class="notif-list">
    <?php if (empty($notifications)): ?>
      <div style="background:white; padding:40px; text-align:center; border-radius:12px; color:#64748b;">
        🔔 No system alerts recorded.
      </div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div class="notif-card <?= $n['is_read'] ? 'read' : 'unread' ?>">
          <div class="notif-icon">🛡️</div>
          <div class="notif-body">
            <h4><?= htmlspecialchars($n['title']) ?></h4>
            <p><?= htmlspecialchars($n['message']) ?></p>
            <div class="notif-time"><?= date('d M Y, h:i A', strtotime($n['created_at'])) ?></div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</div>

</body>
</html>
