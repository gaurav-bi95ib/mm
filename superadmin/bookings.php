<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db = getDB();

$statusFilter = $_GET['status'] ?? 'all';
$dateFilter   = $_GET['date']   ?? '';

$sql = "SELECT b.*, v.name as venue_name, v.sport_type, v.city
        FROM bookings b JOIN venues v ON b.venue_id = v.id WHERE 1=1";
$params = [];
if ($statusFilter !== 'all') { $sql .= " AND b.status = :status"; $params[':status'] = $statusFilter; }
if ($dateFilter) { $sql .= " AND b.booking_date = :date"; $params[':date'] = $dateFilter; }
$sql .= " ORDER BY b.created_at DESC LIMIT 200";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

$totalRevenue = $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE status='confirmed'")->fetchColumn();
$todayCount   = $db->query("SELECT COUNT(*) FROM bookings WHERE booking_date=CURDATE()")->fetchColumn();
$pendingCount = $db->query("SELECT COUNT(*) FROM venues WHERE status='pending'")->fetchColumn();
$appsCount    = $db->query("SELECT COUNT(*) FROM owner_applications WHERE status='new'")->fetchColumn();
$adminName    = $_SESSION['superadmin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>All Bookings – MeroMaidan Admin</title>
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
      <a href="owners.php" class="nav-link"><span class="icon">👤</span> Owners</a>
      <a href="bookings.php" class="nav-link active"><span class="icon">📅</span> Bookings</a>
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
      <div class="topbar-title">📅 All <span>Bookings</span></div>
    </div>
    <div class="admin-content">
      <div class="page-header">
        <h1>Bookings Management</h1>
        <p>View and manage all bookings across all venues on the platform.</p>
      </div>

      <!-- Quick Stats -->
      <div class="stats-row" style="grid-template-columns: repeat(3,1fr);margin-bottom:20px;">
        <div class="stat-card">
          <div class="stat-icon-wrap orange">📅</div>
          <div><div class="stat-num"><?=$todayCount?></div><div class="stat-label">Today's Bookings</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap amber">💰</div>
          <div><div class="stat-num">NPR <?=number_format($totalRevenue)?></div><div class="stat-label">Total Confirmed Revenue</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap navy">📋</div>
          <div><div class="stat-num"><?=count($bookings)?></div><div class="stat-label">Showing Results</div></div>
        </div>
      </div>

      <!-- Filters -->
      <form method="GET" class="filter-bar">
        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="all" <?=$statusFilter==='all'?'selected':''?>>All Status</option>
          <option value="confirmed" <?=$statusFilter==='confirmed'?'selected':''?>>Confirmed</option>
          <option value="pending" <?=$statusFilter==='pending'?'selected':''?>>Pending</option>
          <option value="cancelled" <?=$statusFilter==='cancelled'?'selected':''?>>Cancelled</option>
          <option value="completed" <?=$statusFilter==='completed'?'selected':''?>>Completed</option>
        </select>
        <input type="date" name="date" class="filter-select" value="<?=htmlspecialchars($dateFilter)?>" onchange="this.form.submit()">
        <?php if($statusFilter!=='all'||$dateFilter): ?>
        <a href="bookings.php" class="btn btn-ghost btn-sm">✕ Clear Filters</a>
        <?php endif; ?>
      </form>

      <div class="data-card">
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead><tr>
              <th>Ref</th><th>Customer</th><th>Venue</th><th>Date</th><th>Time</th><th>Amount</th><th>Payment</th><th>Status</th><th>Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach($bookings as $b): ?>
            <tr>
              <td><code style="font-size:11px;background:#f1f5f9;padding:3px 8px;border-radius:6px;font-weight:700;"><?=htmlspecialchars($b['booking_ref'])?></code></td>
              <td>
                <div style="font-weight:800;color:#0f2740;"><?=htmlspecialchars($b['customer_name'])?></div>
                <div style="font-size:11px;color:#64748b;"><?=htmlspecialchars($b['customer_phone'])?></div>
                <?php if($b['customer_email']): ?><div style="font-size:11px;color:#64748b;"><?=htmlspecialchars($b['customer_email'])?></div><?php endif;?>
              </td>
              <td>
                <div style="font-weight:700;"><?=htmlspecialchars($b['venue_name'])?></div>
                <div style="font-size:11px;color:#64748b;"><?=htmlspecialchars($b['sport_type'])?> · <?=htmlspecialchars($b['city'])?></div>
              </td>
              <td><?=date('M j, Y', strtotime($b['booking_date']))?></td>
              <td><?=substr($b['start_time'],0,5)?> – <?=substr($b['end_time'],0,5)?></td>
              <td><strong>NPR <?=number_format($b['total_price'])?></strong></td>
              <td><?=ucfirst($b['payment_method'])?></td>
              <td><span class="badge <?=$b['status']?>"><?=ucfirst($b['status'])?></span></td>
              <td>
                <?php if($b['status']==='pending'): ?>
                <a href="?action=confirm&id=<?=$b['id']?>&<?=http_build_query(['status'=>$statusFilter,'date'=>$dateFilter])?>" class="btn btn-green btn-sm">✓ Confirm</a>
                <?php elseif($b['status']==='confirmed'): ?>
                <a href="?action=cancel&id=<?=$b['id']?>&<?=http_build_query(['status'=>$statusFilter,'date'=>$dateFilter])?>" class="btn btn-red btn-sm" onclick="return confirm('Cancel this booking?')">✕ Cancel</a>
                <?php else: ?>
                <span style="font-size:11px;color:#94a3b8;">—</span>
                <?php endif;?>
              </td>
            </tr>
            <?php endforeach; ?>
            <?php if(empty($bookings)): ?>
            <tr><td colspan="9" style="text-align:center;padding:40px;color:#64748b;">No bookings found</td></tr>
            <?php endif; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </main>
</div>
<?php
// Handle actions
if(isset($_GET['action'],$_GET['id'])){
    $id=$_GET['id'];
    if($_GET['action']==='confirm') $db->prepare("UPDATE bookings SET status='confirmed' WHERE id=:id")->execute([':id'=>$id]);
    if($_GET['action']==='cancel')  $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=:id")->execute([':id'=>$id]);
}
?>
</body>
</html>
