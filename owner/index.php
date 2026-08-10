<?php
require_once __DIR__ . '/../api/db.php';
requireOwner();
$db      = getDB();
$ownerId = $_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';

// Fetch owner's venues
$venues = $db->prepare("SELECT * FROM venues WHERE owner_id=:oid ORDER BY created_at DESC");
$venues->execute([':oid' => $ownerId]);
$myVenues = $venues->fetchAll();
$venueIds = array_column($myVenues, 'id');

// Stats
$todayBookings = 0;
$monthRevenue  = 0;
$totalBookings = 0;
if (!empty($venueIds)) {
    $inList = implode(',', $venueIds);
    $todayBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE venue_id IN ($inList) AND booking_date=CURDATE()")->fetchColumn();
    $monthRevenue  = $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE venue_id IN ($inList) AND MONTH(booking_date)=MONTH(CURDATE()) AND status='confirmed'")->fetchColumn();
    $totalBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE venue_id IN ($inList)")->fetchColumn();

    // Recent bookings for owner
    $stmt = $db->prepare("SELECT b.*, v.name as venue_name FROM bookings b JOIN venues v ON b.venue_id=v.id WHERE b.venue_id IN ($inList) ORDER BY b.created_at DESC LIMIT 10");
    $stmt->execute();
    $recentBookings = $stmt->fetchAll();
} else {
    $recentBookings = [];
}

// Owner plan
$owner = $db->prepare("SELECT vo.*, sp.name as plan_name, sp.max_venues FROM venue_owners vo LEFT JOIN subscription_plans sp ON vo.plan_id=sp.id WHERE vo.id=:id");
$owner->execute([':id' => $ownerId]);
$ownerData = $owner->fetch();
$recommendedCountStmt = $db->prepare("SELECT COUNT(*) FROM recommended_venue_promotions WHERE owner_id=? AND status IN ('pending_payment','pending_review','scheduled','active')");
$recommendedCountStmt->execute([$ownerId]);
$recommendedCount = (int)$recommendedCountStmt->fetchColumn();
$eventCountStmt = $db->prepare("SELECT COUNT(*) FROM event_promotions WHERE owner_id=? AND status IN ('draft','pending_payment','pending_review','scheduled','active')");
$eventCountStmt->execute([$ownerId]);
$eventCount = (int)$eventCountStmt->fetchColumn();
$eventPrice = EVENT_PROMOTION_PRICE_NPR;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>My Dashboard – MeroMaidan</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
  <style>
    .promotion-shortcuts{display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:20px}.promotion-shortcut{position:relative;overflow:hidden;display:grid;grid-template-columns:1fr auto;gap:18px;align-items:center;padding:22px;border:1px solid #dfe8ee;border-radius:18px;text-decoration:none;background:#fff;box-shadow:0 8px 24px rgba(15,39,64,.045);transition:.2s}.promotion-shortcut:hover{transform:translateY(-2px);box-shadow:0 14px 32px rgba(15,39,64,.1)}.promotion-shortcut.recommended{background:linear-gradient(135deg,#effcf4,#fff)}.promotion-shortcut.event{background:linear-gradient(135deg,#fff3ea,#fff)}.promotion-shortcut small{display:block;font-size:9px;font-weight:900;letter-spacing:.11em;color:#5f7689}.promotion-shortcut h3{margin:5px 0;color:#0f2740;font-size:18px}.promotion-shortcut p{margin:0;color:#64748b;font-size:11px;line-height:1.55}.promotion-shortcut .price{font-size:17px;font-weight:900;color:#0f2740;text-align:right}.promotion-shortcut .price span{display:block;margin-top:5px;color:#16a34a;font-size:10px}@media(max-width:800px){.promotion-shortcuts{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="admin-layout">

  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <div><div class="sidebar-logo-text">Mero<span>Maidan</span></div><div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px;">Venue Owner Panel</div></div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">My Dashboard</div>
      <a href="index.php" class="nav-link active"><span class="icon">📊</span> Overview</a>
      <a href="venue.php" class="nav-link"><span class="icon">🏟️</span> My Venue</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="slots.php" class="nav-link"><span class="icon">⏰</span> Manage Slots</a>
      <?php include __DIR__ . '/_promotion_nav.php'; ?>
      <div class="nav-section-label">Account</div>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
      <a href="../list-ground.php" class="nav-link"><span class="icon">➕</span> Add Venue</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?=strtoupper(substr($ownerName,0,2))?></div>
        <div class="admin-user-info">
          <div class="admin-user-name"><?=htmlspecialchars($ownerName)?></div>
          <div class="admin-user-role">
            <span class="badge active" style="font-size:9px;padding:2px 8px;"><?=htmlspecialchars($ownerData['plan_name']??'Annual Venue Subscription')?></span>
          </div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">Owner <span>Dashboard</span></div>
      <div class="topbar-actions">
        <a href="bookings.php" class="btn btn-green btn-sm">📅 Today's Bookings</a>
      </div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Welcome, <?=htmlspecialchars(explode(' ',$ownerName)[0])?>! 🏟️</h1>
        <p>Here's your venue performance summary — <?=date('l, F j, Y')?></p>
      </div>

      <!-- Stats -->
      <div class="stats-row">
        <div class="stat-card">
          <div class="stat-icon-wrap orange">📅</div>
          <div><div class="stat-num"><?=$todayBookings?></div><div class="stat-label">Today's Bookings</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap amber">💰</div>
          <div><div class="stat-num">NPR <?=number_format($monthRevenue)?></div><div class="stat-label">Monthly Revenue</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap navy">📋</div>
          <div><div class="stat-num"><?=$totalBookings?></div><div class="stat-label">Total Bookings</div></div>
        </div>
        <div class="stat-card">
          <div class="stat-icon-wrap green">🏟️</div>
          <div><div class="stat-num"><?=count($myVenues)?>/<?=$ownerData['max_venues']??1?></div><div class="stat-label">Venues Used</div></div>
        </div>
      </div>

      <div class="promotion-shortcuts">
        <a class="promotion-shortcut recommended" href="recommended-promotion.php"><div><small>PAID LOCATION VISIBILITY</small><h3>Recommended Venue</h3><p>Pay for one month, then Super Admin sets and activates the exact venue placement.</p></div><div class="price">NPR 1,000<span><?=$recommendedCount?> current order<?=$recommendedCount===1?'':'s'?> →</span></div></a>
        <a class="promotion-shortcut event" href="event-promotion.php"><div><small>ONE-WEEK HERO CAMPAIGN</small><h3>Event Promotion</h3><p>Submit a 1600×600 venue banner and optional booking coupon for Super Admin review.</p></div><div class="price">NPR <?=number_format($eventPrice)?> / week<span><?=$eventCount?> current campaign<?=$eventCount===1?'':'s'?> →</span></div></a>
      </div>

      <!-- My Venues -->
      <?php if(!empty($myVenues)): ?>
      <div class="data-card" style="margin-bottom:20px;">
        <div class="data-card-header">
          <h3>🏟️ My Venues</h3>
          <a href="venue.php" class="btn btn-ghost btn-sm">Manage</a>
        </div>
        <table class="data-table">
          <thead><tr><th>Venue</th><th>Sport</th><th>Location</th><th>Rate/hr</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach($myVenues as $v): ?>
          <tr>
            <td>
              <div class="venue-name-cell"><?=htmlspecialchars($v['name'])?></div>
              <div class="venue-location"><?=htmlspecialchars($v['capacity'])?></div>
            </td>
            <td><span class="badge pending"><?=$v['sport_type']?></span></td>
            <td><?=htmlspecialchars($v['city'])?></td>
            <td>NPR <?=number_format($v['price_per_hour'])?></td>
            <td><span class="badge <?=$v['status']?>"><?=ucfirst($v['status'])?></span></td>
            <td>
              <a href="../venue.php?slug=<?=urlencode($v['slug'])?>" target="_blank" class="btn btn-ghost btn-sm">👁 View</a>
              <a href="slots.php?venue_id=<?=$v['id']?>" class="btn btn-ghost btn-sm">⏰ Slots</a>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
      <div style="text-align:center;padding:40px;background:#fff;border-radius:16px;margin-bottom:20px;">
        <div style="font-size:48px;margin-bottom:12px;">🏟️</div>
        <h3 style="color:#0f2740;font-size:18px;font-weight:800;margin-bottom:8px;">No Venues Yet</h3>
        <p style="color:#64748b;font-size:13px;margin-bottom:20px;">Your account is pending approval. Our team will activate your venue shortly.</p>
        <a href="../list-ground.php" class="btn btn-green">+ Add Your Ground</a>
      </div>
      <?php endif; ?>

      <!-- Recent Bookings -->
      <div class="data-card">
        <div class="data-card-header">
          <h3>📅 Recent Bookings</h3>
          <a href="bookings.php" class="btn btn-ghost btn-sm">View All</a>
        </div>
        <table class="data-table">
          <thead><tr><th>Ref</th><th>Customer</th><th>Venue</th><th>Date</th><th>Time</th><th>Amount</th><th>Payment</th><th>Status</th></tr></thead>
          <tbody>
          <?php foreach($recentBookings as $b): ?>
          <tr>
            <td><code style="font-size:11px;background:#f1f5f9;padding:3px 8px;border-radius:6px;"><?=htmlspecialchars($b['booking_ref'])?></code></td>
            <td>
              <div class="owner-name"><?=htmlspecialchars($b['customer_name'])?></div>
              <div class="owner-email"><?=htmlspecialchars($b['customer_phone'])?></div>
            </td>
            <td><?=htmlspecialchars($b['venue_name'])?></td>
            <td><?=date('M j, Y', strtotime($b['booking_date']))?></td>
            <td><?=substr($b['start_time'],0,5)?> – <?=substr($b['end_time'],0,5)?></td>
            <td><strong>NPR <?=number_format($b['total_price'])?></strong></td>
            <td><?=ucfirst($b['payment_method'])?></td>
            <td><span class="badge <?=$b['status']?>"><?=ucfirst($b['status'])?></span></td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($recentBookings)): ?>
          <tr><td colspan="8" style="text-align:center;padding:40px;color:#64748b;">No bookings yet</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
</body>
</html>
