<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db = getDB();

// Handle CRUD
$msg = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? '';
    if($action === 'add'){
        $stmt = $db->prepare("INSERT INTO subscription_plans (name, slug, price_monthly, max_venues, max_bookings_per_month, features) VALUES (:name,:slug,:price,:max_v,:max_b,:features)");
        $stmt->execute([':name'=>$_POST['name'],':slug'=>strtolower(str_replace(' ','-',$_POST['name'])).'-'.time(),':price'=>$_POST['price'],':max_v'=>$_POST['max_venues'],':max_b'=>$_POST['max_bookings'],':features'=>$_POST['features']]);
        $msg = '✅ Plan added successfully!';
    } elseif($action==='toggle' && isset($_POST['plan_id'])){
        $db->prepare("UPDATE subscription_plans SET is_active = 1 - is_active WHERE id=:id")->execute([':id'=>$_POST['plan_id']]);
        $msg = '✅ Plan status toggled!';
    }
}

$plans = $db->query("SELECT sp.*, COUNT(vo.id) as owner_count FROM subscription_plans sp LEFT JOIN venue_owners vo ON vo.plan_id=sp.id GROUP BY sp.id ORDER BY sp.price_monthly")->fetchAll();
$pendingCount = $db->query("SELECT COUNT(*) FROM venues WHERE status='pending'")->fetchColumn();
$appsCount    = $db->query("SELECT COUNT(*) FROM owner_applications WHERE status='new'")->fetchColumn();
$adminName    = $_SESSION['superadmin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Plans – MeroMaidan Admin</title>
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
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="applications.php" class="nav-link"><span class="icon">📋</span> Applications <?php if($appsCount>0): ?><span class="badge orange"><?=$appsCount?></span><?php endif;?></a>
      <a href="plans.php" class="nav-link active"><span class="icon">⭐</span> Plans</a>
      <a href="cms.php" class="nav-link"><span class="icon">📝</span> CMS & Content</a>
      <a href="audit.php" class="nav-link"><span class="icon">🛡️</span> Audit Logs</a>
      <a href="notifications.php" class="nav-link"><span class="icon">🔔</span> System Alerts</a>
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
      <div class="topbar-title">⭐ Subscription <span>Plans</span></div>
    </div>
    <div class="admin-content">
      <div class="page-header">
        <h1>Subscription Plans</h1>
        <p>Manage pricing plans for venue owners. Active plans are shown on the List My Ground page.</p>
      </div>
      <?php if($msg): ?><div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:12px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:700;color:#166534;"><?=$msg?></div><?php endif;?>

      <!-- Plan Cards -->
      <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:20px;margin-bottom:32px;">
        <?php foreach($plans as $p): ?>
        <div class="data-card" style="padding:24px;<?=!$p['is_active']?'opacity:.6;':''?>">
          <div style="display:flex;justify-content:space-between;align-items:start;margin-bottom:12px;">
            <div>
              <div style="font-size:18px;font-weight:900;color:#0f2740;"><?=htmlspecialchars($p['name'])?></div>
              <span class="badge <?=strtolower($p['name'])?>"><?=ucfirst($p['name'])?></span>
            </div>
            <form method="POST" style="display:inline;">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="plan_id" value="<?=$p['id']?>">
              <button type="submit" class="btn <?=$p['is_active']?'btn-ghost':'btn-green'?> btn-sm">
                <?=$p['is_active']?'⏸ Disable':'▶ Enable'?>
              </button>
            </form>
          </div>
          <div style="font-size:28px;font-weight:900;color:#1BB955;margin-bottom:4px;">
            NPR <?=number_format($p['price_monthly'])?><span style="font-size:13px;font-weight:500;color:#64748b;">/mo</span>
          </div>
          <div style="font-size:12px;color:#64748b;margin-bottom:16px;">
            <?=$p['owner_count']?> owner<?=$p['owner_count']!=1?'s':''?> subscribed
          </div>
          <div style="border-top:1px solid #e2e8f0;padding-top:12px;">
            <div style="font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px;">LIMITS</div>
            <div style="font-size:13px;color:#2b3648;display:flex;flex-direction:column;gap:4px;">
              <span>🏟️ Max venues: <strong><?=$p['max_venues']==999?'Unlimited':$p['max_venues']?></strong></span>
              <span>📅 Max bookings/mo: <strong><?=$p['max_bookings_per_month']==9999?'Unlimited':$p['max_bookings_per_month']?></strong></span>
            </div>
          </div>
          <?php $features = json_decode($p['features']??'[]',true)??[]; ?>
          <?php if(!empty($features)): ?>
          <div style="border-top:1px solid #e2e8f0;padding-top:12px;margin-top:12px;">
            <div style="font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px;">FEATURES</div>
            <?php foreach($features as $f): ?>
            <div style="font-size:12px;color:#2b3648;padding:2px 0;">✓ <?=htmlspecialchars($f)?></div>
            <?php endforeach;?>
          </div>
          <?php endif;?>
        </div>
        <?php endforeach;?>
      </div>

      <!-- Add New Plan -->
      <div class="data-card">
        <div class="data-card-header"><h3>➕ Add New Plan</h3></div>
        <form method="POST" style="padding:20px;display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;">
          <input type="hidden" name="action" value="add">
          <div class="form-group">
            <label class="form-label">Plan Name</label>
            <input type="text" name="name" class="form-input" placeholder="e.g. Gold" required>
          </div>
          <div class="form-group">
            <label class="form-label">Price/Month (NPR)</label>
            <input type="number" name="price" class="form-input" placeholder="e.g. 2499" required>
          </div>
          <div class="form-group">
            <label class="form-label">Max Venues</label>
            <input type="number" name="max_venues" class="form-input" placeholder="e.g. 5" required>
          </div>
          <div class="form-group">
            <label class="form-label">Max Bookings/Month</label>
            <input type="number" name="max_bookings" class="form-input" placeholder="e.g. 500" required>
          </div>
          <div class="form-group" style="grid-column:1/-1;">
            <label class="form-label">Features (JSON array, e.g. ["Feature 1","Feature 2"])</label>
            <input type="text" name="features" class="form-input" placeholder='["5 venues","500 bookings","Priority support"]'>
          </div>
          <div style="grid-column:1/-1;">
            <button type="submit" class="btn btn-green">➕ Create Plan</button>
          </div>
        </form>
      </div>
    </div>
  </main>
</div>
</body>
</html>
