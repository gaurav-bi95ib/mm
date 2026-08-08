<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db = getDB();

// Handle actions
if(isset($_GET['action'],$_GET['id'])){
    $id=(int)$_GET['id'];
    if($_GET['action']==='activate')  $db->prepare("UPDATE venue_owners SET status='active' WHERE id=:id")->execute([':id'=>$id]);
    if($_GET['action']==='suspend')   $db->prepare("UPDATE venue_owners SET status='suspended' WHERE id=:id")->execute([':id'=>$id]);
    if($_GET['action']==='set_plan' && isset($_GET['plan_id'])){
        $db->prepare("UPDATE venue_owners SET plan_id=:plan WHERE id=:id")->execute([':plan'=>$_GET['plan_id'],':id'=>$id]);
    }
    header('Location: owners.php'); exit;
}

$owners = $db->query("
    SELECT vo.*, sp.name as plan_name,
           COUNT(v.id) as venue_count,
           (SELECT COUNT(*) FROM bookings b JOIN venues v2 ON b.venue_id=v2.id WHERE v2.owner_id=vo.id AND b.status='confirmed') as total_bookings
    FROM venue_owners vo
    LEFT JOIN subscription_plans sp ON vo.plan_id=sp.id
    LEFT JOIN venues v ON v.owner_id=vo.id
    GROUP BY vo.id
    ORDER BY vo.created_at DESC
")->fetchAll();

$plans = $db->query("SELECT * FROM subscription_plans WHERE is_active=1")->fetchAll();
$pendingCount = $db->query("SELECT COUNT(*) FROM venues WHERE status='pending'")->fetchColumn();
$appsCount    = $db->query("SELECT COUNT(*) FROM owner_applications WHERE status='new'")->fetchColumn();
$adminName    = $_SESSION['superadmin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Owners – MeroMaidan Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-logo"><div><div class="sidebar-logo-text">Mero<span>Maidan</span> <span class="sidebar-badge">ADMIN</span></div></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Dashboard</a>
      <div class="nav-section-label">Management</div>
      <a href="venues.php" class="nav-link"><span class="icon">🏟️</span> Venues <?php if($pendingCount>0): ?><span class="badge orange"><?=$pendingCount?></span><?php endif;?></a>
      <a href="owners.php" class="nav-link active"><span class="icon">👤</span> Owners</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="applications.php" class="nav-link"><span class="icon">📋</span> Applications <?php if($appsCount>0): ?><span class="badge orange"><?=$appsCount?></span><?php endif;?></a>
      <a href="plans.php" class="nav-link"><span class="icon">⭐</span> Plans</a>
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
      <div class="topbar-title">👤 Venue <span>Owners</span></div>
    </div>
    <div class="admin-content">
      <div class="page-header">
        <h1>Owner Accounts</h1>
        <p>Manage venue owners, their subscription plans and account status.</p>
      </div>
      <div class="data-card">
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead><tr><th>Owner</th><th>Business</th><th>Phone</th><th>Venues</th><th>Bookings</th><th>Plan</th><th>Status</th><th>Joined</th><th>Actions</th></tr></thead>
            <tbody>
            <?php foreach($owners as $o): ?>
            <tr>
              <td>
                <div class="owner-cell">
                  <div class="owner-av"><?=strtoupper(substr($o['name'],0,2))?></div>
                  <div>
                    <div class="owner-name"><?=htmlspecialchars($o['name'])?></div>
                    <div class="owner-email"><?=htmlspecialchars($o['email'])?></div>
                  </div>
                </div>
              </td>
              <td><?=htmlspecialchars($o['business_name']??'—')?></td>
              <td><?=htmlspecialchars($o['phone'])?></td>
              <td><strong><?=$o['venue_count']?></strong></td>
              <td><strong><?=$o['total_bookings']?></strong></td>
              <td>
                <form method="GET" style="display:inline;">
                  <input type="hidden" name="action" value="set_plan">
                  <input type="hidden" name="id" value="<?=$o['id']?>">
                  <select name="plan_id" class="filter-select" style="padding:4px 10px;font-size:12px;" onchange="this.form.submit()">
                    <?php foreach($plans as $p): ?>
                    <option value="<?=$p['id']?>" <?=$p['id']==$o['plan_id']?'selected':''?>><?=$p['name']?></option>
                    <?php endforeach;?>
                  </select>
                </form>
              </td>
              <td><span class="badge <?=$o['status']?>"><?=ucfirst($o['status'])?></span></td>
              <td style="font-size:12px;color:#64748b;"><?=date('M j, Y',strtotime($o['created_at']))?></td>
              <td>
                <div style="display:flex;gap:6px;flex-wrap:wrap;">
                  <?php if($o['status']==='active'): ?>
                  <a href="?action=suspend&id=<?=$o['id']?>" class="btn btn-red btn-sm" onclick="return confirm('Suspend this owner?')">⏸ Suspend</a>
                  <?php else: ?>
                  <a href="?action=activate&id=<?=$o['id']?>" class="btn btn-green btn-sm">▶ Activate</a>
                  <?php endif;?>
                  <a href="bookings.php" class="btn btn-ghost btn-sm">📅 Bookings</a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($owners)): ?>
            <tr><td colspan="9" style="text-align:center;padding:40px;color:#64748b;">No owners yet</td></tr>
            <?php endif;?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>
