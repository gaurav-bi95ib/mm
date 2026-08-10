<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db = getDB();

// State changes are POST-only and CSRF-protected.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Your session expired. Please return and try again.');
    }
    $id     = (int)$_POST['id'];
    $action = $_POST['action'];
    if ($action === 'approve') {
        $db->prepare("UPDATE venues SET status='active' WHERE id=:id")->execute([':id'=>$id]);
    } elseif ($action === 'reject' || $action === 'suspend') {
        $db->prepare("UPDATE venues SET status='suspended' WHERE id=:id")->execute([':id'=>$id]);
    } else {
        http_response_code(400);
        die('Unsupported venue action.');
    }
    logAudit('moderate_venue','Venues','venue',$id,$action.' venue');
    header('Location: venues.php');
    exit;
}

$statusFilter = $_GET['status'] ?? 'all';
$sportFilter  = $_GET['sport']  ?? 'all';

$sql = "SELECT v.*, vo.name as owner_name, vo.email as owner_email, vo.phone as owner_phone,
               sp.name as plan_name
        FROM venues v
        LEFT JOIN venue_owners vo ON v.owner_id=vo.id
        LEFT JOIN subscription_plans sp ON vo.plan_id=sp.id
        WHERE 1=1";
$params = [];
if ($statusFilter !== 'all') { $sql .= " AND v.status=:status"; $params[':status'] = $statusFilter; }
if ($sportFilter !== 'all')  { $sql .= " AND v.sport_type=:sport"; $params[':sport'] = $sportFilter; }
$sql .= " ORDER BY v.created_at DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$venues = $stmt->fetchAll();
$adminName = $_SESSION['superadmin_name'] ?? 'Admin';

$pendingCount = $db->query("SELECT COUNT(*) FROM venues WHERE status='pending'")->fetchColumn();
$appsCount    = $db->query("SELECT COUNT(*) FROM owner_applications WHERE status='new'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Venues – MeroMaidan Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <div><div class="sidebar-logo-text">Mero<span>Maidan</span> <span class="sidebar-badge">ADMIN</span></div></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Dashboard</a>
      <div class="nav-section-label">Management</div>
      <a href="venues.php" class="nav-link active"><span class="icon">🏟️</span> Venues <?php if($pendingCount>0): ?><span class="badge orange"><?=$pendingCount?></span><?php endif;?></a>
      <a href="owners.php" class="nav-link"><span class="icon">👤</span> Owners</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="applications.php" class="nav-link"><span class="icon">📋</span> Applications <?php if($appsCount>0): ?><span class="badge orange"><?=$appsCount?></span><?php endif;?></a>
      <a href="plans.php" class="nav-link"><span class="icon">💳</span> Commercial Services</a>
      <a href="recommended-promotions.php" class="nav-link"><span class="icon">📍</span> Recommended Venue</a>
      <a href="event-promotions.php" class="nav-link"><span class="icon">📣</span> Event Campaigns</a>
      <a href="cms.php" class="nav-link"><span class="icon">📝</span> CMS & Content</a>
      <div class="nav-section-label">System</div>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?=strtoupper(substr($adminName,0,2))?></div>
        <div class="admin-user-info"><div class="admin-user-name"><?=htmlspecialchars($adminName)?></div><div class="admin-user-role">Super Admin</div></div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>
  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">🏟️ Venue <span>Management</span></div>
    </div>
    <div class="admin-content">
      <div class="page-header">
        <h1>All Venues</h1>
        <p>Manage venue listings, approve pending venues, and control visibility.</p>
      </div>
      <div class="filter-bar">
        <form method="GET" style="display:flex;gap:10px;flex-wrap:wrap;">
          <select name="status" class="filter-select" onchange="this.form.submit()">
            <option value="all" <?=$statusFilter==='all'?'selected':''?>>All Status</option>
            <option value="active" <?=$statusFilter==='active'?'selected':''?>>Active</option>
            <option value="pending" <?=$statusFilter==='pending'?'selected':''?>>Pending</option>
            <option value="suspended" <?=$statusFilter==='suspended'?'selected':''?>>Suspended</option>
          </select>
          <select name="sport" class="filter-select" onchange="this.form.submit()">
            <option value="all">All Sports</option>
            <option value="Futsal" <?=$sportFilter==='Futsal'?'selected':''?>>Futsal</option>
            <option value="Football" <?=$sportFilter==='Football'?'selected':''?>>Football</option>
            <option value="Cricket" <?=$sportFilter==='Cricket'?'selected':''?>>Cricket</option>
            <option value="Cricsal" <?=$sportFilter==='Cricsal'?'selected':''?>>Cricsal</option>
          </select>
          <span style="margin-left:auto;font-size:13px;color:#64748b;font-weight:600;align-self:center;"><?=count($venues)?> venues</span>
        </form>
      </div>
      <div class="data-card">
        <table class="data-table">
          <thead><tr>
            <th>Venue</th><th>Owner</th><th>Sport</th><th>Location</th><th>Rate/hr</th><th>Plan</th><th>Status</th><th>Actions</th>
          </tr></thead>
          <tbody>
          <?php foreach($venues as $v): ?>
          <tr>
            <td>
              <div class="venue-name-cell"><?=htmlspecialchars($v['name'])?></div>
              <div class="venue-location"><?=htmlspecialchars($v['capacity'])?> · <?=htmlspecialchars($v['slug'])?></div>
            </td>
            <td>
              <div class="owner-name"><?=htmlspecialchars($v['owner_name'] ?? 'N/A')?></div>
              <div class="owner-email"><?=htmlspecialchars($v['owner_email'] ?? '')?></div>
            </td>
            <td><span class="badge pending"><?=htmlspecialchars($v['sport_type'])?></span></td>
            <td><?=htmlspecialchars($v['city'])?>, <?=htmlspecialchars($v['district'])?></td>
            <td><strong>NPR <?=number_format($v['price_per_hour'])?></strong></td>
            <td><span class="badge active"><?=htmlspecialchars($v['plan_name']??'Annual Venue Subscription')?></span></td>
            <td><span class="badge <?=$v['status']?>"><?=ucfirst($v['status'])?></span></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if($v['status']==='pending'): ?>
                <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="id" value="<?=$v['id']?>"><button name="action" value="approve" class="btn btn-green btn-sm">✓ Approve</button></form>
                <form method="post" style="display:inline" onsubmit="return confirm('Reject this venue?')"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="id" value="<?=$v['id']?>"><button name="action" value="reject" class="btn btn-red btn-sm">✕</button></form>
                <?php elseif($v['status']==='active'): ?>
                <form method="post" style="display:inline" onsubmit="return confirm('Suspend this venue?')"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="id" value="<?=$v['id']?>"><button name="action" value="suspend" class="btn btn-ghost btn-sm">⏸ Suspend</button></form>
                <?php else: ?>
                <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="id" value="<?=$v['id']?>"><button name="action" value="approve" class="btn btn-green btn-sm">▶ Activate</button></form>
                <?php endif; ?>
                <a href="../venue.php?slug=<?=urlencode($v['slug'])?>" target="_blank" class="btn btn-ghost btn-sm">👁 View</a>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($venues)): ?>
          <tr><td colspan="9" style="text-align:center;padding:40px;color:#64748b;">No venues found</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
</body>
</html>
