<?php
// MeroMaidan - Owner Notifications Inbox
require_once __DIR__ . '/../api/db.php';
requireOwner();

$db = getDB();
$ownerId = $_SESSION['owner_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'mark_all') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) { http_response_code(403); die('Your session expired.'); }
    $db->prepare("UPDATE notifications SET is_read = 1 WHERE tenant_id = ? AND role = 'owner'")->execute([$ownerId]);
    header("Location: notifications.php");
    exit;
}

$stmt = $db->prepare("SELECT * FROM notifications WHERE tenant_id = ? AND role = 'owner' ORDER BY created_at DESC");
$stmt->execute([$ownerId]);
$notifications = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Notifications - MeroMaidan Owner</title>
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
    .notif-card { background: white; border-radius: 10px; padding: 16px 20px; border-left: 4px solid #10b981; box-shadow: 0 2px 5px rgba(0,0,0,0.03); display: flex; gap: 16px; align-items: flex-start; }
    .notif-card.unread { background: #ecfdf5; border-left-color: #10b981; }
    .notif-card.read { border-left-color: #cbd5e1; opacity: 0.8; }
    .notif-icon { font-size: 20px; background: #d1fae5; padding: 10px; border-radius: 50%; }
    .notif-body h4 { font-size: 15px; color: #0f172a; margin-bottom: 4px; }
    .notif-body p { font-size: 13px; color: #475569; line-height: 1.4; }
    .notif-time { font-size: 11px; color: #94a3b8; margin-top: 6px; }
  </style>
</head>
<body>

<div class="sidebar">
  <h2>MeroMaidan</h2>
  <a href="index.php" class="nav-item">📊 Dashboard</a>
  <a href="bookings.php" class="nav-item">📅 Bookings</a>
  <a href="venue.php" class="nav-item">🏟️ Venue CRUD</a>
  <a href="slots.php" class="nav-item">⏱️ Slot Pricing</a>
  <a href="recommended-promotion.php" class="nav-item">R &nbsp;Recommended Venue</a>
  <a href="event-promotion.php" class="nav-item">E &nbsp;Event Promotion</a>
  <a href="field_ops.php" class="nav-item">📋 Field Operations</a>
  <a href="staff.php" class="nav-item">👥 Staff & Roles</a>
  <a href="subscription.php" class="nav-item">⭐ Subscription</a>
  <a href="notifications.php" class="nav-item active">🔔 Notifications</a>
  <a href="../auth/logout.php" class="nav-item" style="margin-top:auto; color:#ef4444;">🚪 Logout</a>
</div>

<div class="main-content">
  <div class="page-header">
    <h1 class="page-title">Tenant Notifications</h1>
    <form method="post"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="action" value="mark_all"><button class="btn-read-all" style="border:0;cursor:pointer">✓ Mark All as Read</button></form>
  </div>

  <div class="notif-list">
    <?php if (empty($notifications)): ?>
      <div style="background:white; padding:40px; text-align:center; border-radius:12px; color:#64748b;">
        🔔 No tenant notifications.
      </div>
    <?php else: ?>
      <?php foreach ($notifications as $n): ?>
        <div class="notif-card <?= $n['is_read'] ? 'read' : 'unread' ?>">
          <div class="notif-icon">
            <?= $n['type'] === 'payment' ? '💰' : ($n['type'] === 'booking' ? '⚽' : '📢') ?>
          </div>
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
