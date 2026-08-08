<?php
// MeroMaidan - Owner Subscription & Billing Workspace
require_once __DIR__ . '/../api/db.php';
requireOwner();

$db = getDB();
$ownerId   = $_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';

// Fetch owner info & current plan
$stmt = $db->prepare("SELECT vo.*, sp.name as plan_name, sp.price_monthly, sp.max_venues, sp.max_bookings_per_month 
                      FROM venue_owners vo 
                      LEFT JOIN subscription_plans sp ON vo.plan_id = sp.id 
                      WHERE vo.id = ?");
$stmt->execute([$ownerId]);
$owner = $stmt->fetch();

// Fetch resource usage
$vCountStmt = $db->prepare("SELECT COUNT(*) FROM venues WHERE owner_id = ?");
$vCountStmt->execute([$ownerId]);
$venuesCount = $vCountStmt->fetchColumn();

$sCountStmt = $db->prepare("SELECT COUNT(*) FROM tenant_staff WHERE owner_id = ?");
$sCountStmt->execute([$ownerId]);
$staffCount = $sCountStmt->fetchColumn();

// Fetch all available subscription plans
$pStmt = $db->query("SELECT * FROM subscription_plans WHERE is_active = 1 ORDER BY price_monthly ASC");
$plans = $pStmt->fetchAll();

$msg = $_GET['msg'] ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Subscription & Billing - MeroMaidan Owner</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
  <style>
    .plans-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin-top: 24px; }
    .plan-card { background: white; border-radius: 12px; border: 2px solid #e2e8f0; padding: 24px; position: relative; display: flex; flex-direction: column; }
    .plan-card.current { border-color: #10b981; background: #f0fdf4; }
    .plan-card.featured { border-color: #f97316; }
    .plan-title { font-size: 20px; font-weight: 800; color: #0f172a; }
    .plan-price { font-size: 28px; font-weight: 900; color: #10b981; margin: 12px 0; }
    .plan-price sub { font-size: 13px; color: #64748b; font-weight: 500; }
    .plan-features { list-style: none; margin: 16px 0; font-size: 13px; color: #475569; display: flex; flex-direction: column; gap: 8px; flex: 1; }
    .progress-bar { background: #e2e8f0; height: 8px; border-radius: 4px; overflow: hidden; margin-top: 6px; }
    .progress-fill { background: #10b981; height: 100%; border-radius: 4px; }
  </style>
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <div>
        <div class="sidebar-logo-text">Mero<span>Maidan</span></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px;">Venue Owner Panel</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">My Dashboard</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Overview</a>
      <a href="venue.php" class="nav-link"><span class="icon">🏟️</span> My Venue</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="slots.php" class="nav-link"><span class="icon">⏰</span> Manage Slots</a>
      <a href="field_ops.php" class="nav-link"><span class="icon">📋</span> Field Operations</a>
      <a href="staff.php" class="nav-link"><span class="icon">👥</span> Staff & Roles</a>
      <a href="subscription.php" class="nav-link active"><span class="icon">⭐</span> Subscription</a>
      <a href="notifications.php" class="nav-link"><span class="icon">🔔</span> Notifications</a>
      <div class="nav-section-label">Account</div>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?=strtoupper(substr($ownerName,0,2))?></div>
        <div class="admin-user-info">
          <div class="admin-user-name"><?=htmlspecialchars($ownerName)?></div>
          <div class="admin-user-role">Venue Owner</div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">⭐ Subscription & <span>SaaS Billing</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>SaaS Subscription Workspace</h1>
        <p>Monitor plan resource limits and upgrade using eSewa.</p>
      </div>

      <?php if ($msg === 'upgraded'): ?>
        <div style="background:#dcfce7;border:1px solid #86efac;padding:14px;border-radius:8px;color:#166534;margin-bottom:20px;font-weight:700;">
          🎉 Subscription upgraded successfully via eSewa!
        </div>
      <?php endif; ?>

      <!-- Current Usage Card -->
      <div class="data-card" style="padding:24px;margin-bottom:32px;">
        <h3>📊 Active Plan Usage: <span style="color:#10b981;"><?= htmlspecialchars($owner['plan_name'] ?? 'Free') ?> Plan</span></h3>
        <div style="display:grid;grid-template-columns:1fr 1fr;gap:24px;margin-top:16px;">
          <div>
            <div style="display:flex;justify-space-between;font-size:13px;font-weight:600;">
              <span>Venues Allowed</span>
              <span><?= $venuesCount ?> / <?= $owner['max_venues'] ?? 1 ?> Venues</span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill" style="width: <?= min(100, ($venuesCount / ($owner['max_venues'] ?: 1)) * 100) ?>%;"></div>
            </div>
          </div>

          <div>
            <div style="display:flex;justify-space-between;font-size:13px;font-weight:600;">
              <span>Staff Accounts Allowed</span>
              <span><?= $staffCount ?> Accounts</span>
            </div>
            <div class="progress-bar">
              <div class="progress-fill" style="width: <?= min(100, ($staffCount / 5) * 100) ?>%;"></div>
            </div>
          </div>
        </div>
      </div>

      <!-- Subscription Plans Comparison -->
      <h2>Available SaaS Subscription Plans</h2>
      <div class="plans-grid">
        <?php foreach ($plans as $p): ?>
          <?php $isCurrent = ($owner['plan_id'] == $p['id']); ?>
          <div class="plan-card <?= $isCurrent ? 'current' : '' ?>">
            <?php if ($isCurrent): ?>
              <span class="badge active" style="position:absolute;top:16px;right:16px;">Active Plan</span>
            <?php endif; ?>
            <div class="plan-title"><?= htmlspecialchars($p['name']) ?></div>
            <div class="plan-price">NPR <?= number_format($p['price_monthly']) ?><sub>/month</sub></div>
            <ul class="plan-features">
              <li>✔️ Max Venues: <strong><?= $p['max_venues'] ?></strong></li>
              <li>✔️ Max Bookings/Month: <strong><?= $p['max_bookings_per_month'] ?></strong></li>
              <?php 
                $feats = json_decode($p['features'] ?? '[]', true);
                if (is_array($feats)) {
                    foreach ($feats as $f) {
                        echo "<li>✔️ " . htmlspecialchars($f) . "</li>";
                    }
                }
              ?>
            </ul>

            <?php if ($isCurrent): ?>
              <button class="btn btn-ghost" disabled style="width:100%;margin-top:auto;">Current Plan</button>
            <?php else: ?>
              <a href="../esewa/payment.php?subscription_upgrade=1&plan_id=<?= $p['id'] ?>" class="btn btn-green" style="width:100%;margin-top:auto;text-align:center;text-decoration:none;">
                ⚡ Upgrade with eSewa
              </a>
            <?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>

    </div>
  </main>
</div>
</body>
</html>
