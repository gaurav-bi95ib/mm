<?php
require_once __DIR__ . '/../api/db.php';
requireOwner();
$db = getDB();
$ownerId = $_SESSION['owner_id'];

// Get owner's venues
$stmt = $db->prepare("SELECT * FROM venues WHERE owner_id=:oid ORDER BY created_at DESC");
$stmt->execute([':oid'=>$ownerId]);
$myVenues = $stmt->fetchAll();

$venueIds = array_column($myVenues,'id');
$bookings = [];
if(!empty($venueIds)){
    $inList = implode(',', array_map('intval',$venueIds));
    $statusFilter = $_GET['status'] ?? 'all';
    $dateFilter   = $_GET['date']   ?? '';
    $sql = "SELECT b.*, v.name as venue_name FROM bookings b JOIN venues v ON b.venue_id=v.id WHERE b.venue_id IN ($inList)";
    $params = [];
    if($statusFilter!=='all'){ $sql .= " AND b.status=:status"; $params[':status']=$statusFilter;}
    if($dateFilter){ $sql .= " AND b.booking_date=:date"; $params[':date']=$dateFilter;}
    $sql .= " ORDER BY b.booking_date DESC, b.start_time ASC LIMIT 100";
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $bookings = $stmt->fetchAll();
}

$ownerName = $_SESSION['owner_name'] ?? 'Owner';
$statusFilter = $_GET['status'] ?? 'all';
$dateFilter   = $_GET['date']   ?? '';

// Stats
$todayCount  = 0;
$monthRev    = 0;
if(!empty($venueIds)){
    $inList = implode(',', array_map('intval',$venueIds));
    $todayCount = $db->query("SELECT COUNT(*) FROM bookings WHERE venue_id IN ($inList) AND booking_date=CURDATE()")->fetchColumn();
    $monthRev   = $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE venue_id IN ($inList) AND MONTH(booking_date)=MONTH(CURDATE()) AND status='confirmed'")->fetchColumn();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>My Bookings – MeroMaidan Owner</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-logo"><div><div class="sidebar-logo-text">Mero<span>Maidan</span></div><div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px;">Venue Owner Panel</div></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">My Dashboard</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Overview</a>
      <a href="venue.php" class="nav-link"><span class="icon">🏟️</span> My Venue</a>
      <a href="bookings.php" class="nav-link active"><span class="icon">📅</span> Bookings</a>
      <a href="slots.php" class="nav-link"><span class="icon">⏰</span> Manage Slots</a>
      <div class="nav-section-label">Account</div>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
      <a href="../list-ground.php" class="nav-link"><span class="icon">➕</span> Add Venue</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?=strtoupper(substr($ownerName,0,2))?></div>
        <div class="admin-user-info"><div class="admin-user-name"><?=htmlspecialchars($ownerName)?></div><div class="admin-user-role">Venue Owner</div></div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>
  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">📅 My <span>Bookings</span></div>
    </div>
    <div class="admin-content">
      <div class="page-header">
        <h1>Bookings for My Venues</h1>
        <p>Track all reservations at your sports venues in one place.</p>
      </div>
      <div class="stats-row" style="grid-template-columns:repeat(3,1fr);margin-bottom:20px;">
        <div class="stat-card"><div class="stat-icon-wrap orange">📅</div><div><div class="stat-num"><?=$todayCount?></div><div class="stat-label">Today's Bookings</div></div></div>
        <div class="stat-card"><div class="stat-icon-wrap amber">💰</div><div><div class="stat-num">NPR <?=number_format($monthRev)?></div><div class="stat-label">Monthly Revenue</div></div></div>
        <div class="stat-card"><div class="stat-icon-wrap navy">📋</div><div><div class="stat-num"><?=count($bookings)?></div><div class="stat-label">Showing Results</div></div></div>
      </div>
      <form method="GET" class="filter-bar">
        <select name="status" class="filter-select" onchange="this.form.submit()">
          <option value="all" <?=$statusFilter==='all'?'selected':''?>>All Status</option>
          <option value="confirmed" <?=$statusFilter==='confirmed'?'selected':''?>>Confirmed</option>
          <option value="pending" <?=$statusFilter==='pending'?'selected':''?>>Pending</option>
          <option value="cancelled" <?=$statusFilter==='cancelled'?'selected':''?>>Cancelled</option>
        </select>
        <input type="date" name="date" class="filter-select" value="<?=htmlspecialchars($dateFilter)?>" onchange="this.form.submit()">
        <?php if($statusFilter!=='all'||$dateFilter): ?><a href="bookings.php" class="btn btn-ghost btn-sm">✕ Clear</a><?php endif;?>
      </form>
      <div class="data-card">
        <div style="overflow-x:auto;">
          <table class="data-table">
            <thead><tr><th>Ref</th><th>Customer</th><th>Venue</th><th>Date</th><th>Time</th><th>Amount</th><th>Payment</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach($bookings as $b): ?>
            <tr>
              <td><code style="font-size:11px;background:#f1f5f9;padding:3px 8px;border-radius:6px;font-weight:700;"><?=htmlspecialchars($b['booking_ref'])?></code></td>
              <td>
                <div style="font-weight:800;color:#0f2740;"><?=htmlspecialchars($b['customer_name'])?></div>
                <div style="font-size:11px;color:#64748b;">📞 <?=htmlspecialchars($b['customer_phone'])?></div>
              </td>
              <td><?=htmlspecialchars($b['venue_name'])?></td>
              <td><?=date('M j, Y',strtotime($b['booking_date']))?></td>
              <td><?=substr($b['start_time'],0,5)?> – <?=substr($b['end_time'],0,5)?></td>
              <td><strong>NPR <?=number_format($b['total_price'])?></strong></td>
              <td><?=ucfirst($b['payment_method'])?></td>
              <td><span class="badge <?=$b['status']?>"><?=ucfirst($b['status'])?></span></td>
            </tr>
            <?php endforeach;?>
            <?php if(empty($bookings)): ?>
            <tr><td colspan="8" style="text-align:center;padding:40px;color:#64748b;">No bookings found for your venues yet.</td></tr>
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
