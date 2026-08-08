<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db = getDB();

// Actions
if (isset($_GET['action'], $_GET['id'])) {
    $id = (int)$_GET['id'];
    $act = $_GET['action'];

    if ($act === 'approve') {
        $stmt = $db->prepare("SELECT * FROM owner_applications WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $app = $stmt->fetch();

        if ($app && $app['status'] !== 'approved') {
            // Update application status
            $db->prepare("UPDATE owner_applications SET status='approved' WHERE id=:id")->execute([':id'=>$id]);

            // Check or create venue owner account
            $oStmt = $db->prepare("SELECT id FROM venue_owners WHERE email = :e LIMIT 1");
            $oStmt->execute([':e' => $app['email']]);
            $owner = $oStmt->fetch();

            if (!$owner) {
                // Provision owner account with default password Owner@1234
                $passHash = password_hash('Owner@1234', PASSWORD_BCRYPT);
                $insOwner = $db->prepare("INSERT INTO venue_owners (name, email, phone, password_hash, business_name, plan_id, status, approved_at) VALUES (:n, :e, :p, :ph, :bn, 2, 'active', NOW())");
                $insOwner->execute([
                    ':n' => $app['owner_name'],
                    ':e' => $app['email'],
                    ':p' => $app['phone'],
                    ':ph' => $passHash,
                    ':bn' => $app['business_name']
                ]);
                $ownerId = $db->lastInsertId();
            } else {
                $ownerId = $owner['id'];
            }

            // Provision venue workspace
            if (!empty($app['venue_name'])) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $app['venue_name']), '-'));
                $vIns = $db->prepare("INSERT INTO venues (owner_id, name, slug, sport_type, address, city, district, price_per_hour, open_time, close_time, status, featured) VALUES (:oid, :n, :slug, :st, :addr, :city, :dist, :price, :open, :close, 'active', 0)");
                $vIns->execute([
                    ':oid'   => $ownerId,
                    ':n'     => $app['venue_name'],
                    ':slug'  => $slug . '-' . rand(100,999),
                    ':st'    => $app['sport_type'] ?: 'Futsal',
                    ':addr'  => $app['venue_address'] ?: ($app['city'] . ', Nepal'),
                    ':city'  => $app['city'] ?: 'Kathmandu',
                    ':dist'  => $app['district'] ?: 'Kathmandu',
                    ':price' => $app['price_per_hour'] ?: 1200,
                    ':open'  => $app['open_time'] ?: '06:00:00',
                    ':close' => $app['close_time'] ?: '22:00:00',
                ]);
            }

            logAudit('approve_application', 'Tenant', 'owner_application', $id, "SuperAdmin approved application and provisioned workspace for {$app['business_name']}");
        }
    } elseif ($act === 'reject') {
        $db->prepare("UPDATE owner_applications SET status='rejected' WHERE id=:id")->execute([':id'=>$id]);
        logAudit('reject_application', 'Tenant', 'owner_application', $id, "SuperAdmin rejected application #$id");
    }
    header('Location: applications.php');
    exit;
}

$apps = $db->query("SELECT * FROM owner_applications ORDER BY created_at DESC")->fetchAll();
$pendingCount = $db->query("SELECT COUNT(*) FROM venues WHERE status='pending'")->fetchColumn();
$appsCount    = $db->query("SELECT COUNT(*) FROM owner_applications WHERE status='new'")->fetchColumn();
$adminName    = $_SESSION['superadmin_name'] ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Applications – MeroMaidan Admin</title>
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
      <a href="applications.php" class="nav-link active"><span class="icon">📋</span> Applications <?php if($appsCount>0): ?><span class="badge orange"><?=$appsCount?></span><?php endif;?></a>
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
      <div class="topbar-title">📋 Owner <span>Applications</span></div>
    </div>
    <div class="admin-content">
      <div class="page-header">
        <h1>Ground Listing Applications</h1>
        <p>Review applications from owners wanting to list their ground on MeroMaidan.</p>
      </div>
      <div class="data-card">
        <table class="data-table">
          <thead><tr><th>Owner</th><th>Business</th><th>Venue</th><th>Sport</th><th>Location</th><th>Plan</th><th>Applied</th><th>Status</th><th>Actions</th></tr></thead>
          <tbody>
          <?php foreach($apps as $a): ?>
          <tr>
            <td>
              <div class="owner-name"><?=htmlspecialchars($a['owner_name'])?></div>
              <div class="owner-email"><?=htmlspecialchars($a['email'])?></div>
              <div class="owner-email"><?=htmlspecialchars($a['phone'])?></div>
            </td>
            <td><?=htmlspecialchars($a['business_name'])?></td>
            <td>
              <div class="venue-name-cell"><?=htmlspecialchars($a['venue_name']??'-')?></div>
              <div class="venue-location"><?=htmlspecialchars($a['venue_address']??'')?></div>
            </td>
            <td><span class="badge pending"><?=htmlspecialchars($a['sport_type']??'-')?></span></td>
            <td><?=htmlspecialchars($a['city'])?>, <?=htmlspecialchars($a['district'])?></td>
            <td><span class="badge <?=strtolower($a['plan_selected']??'free')?>"><?=ucfirst($a['plan_selected']??'Free')?></span></td>
            <td style="font-size:12px;color:#64748b;"><?=date('M j, Y', strtotime($a['created_at']))?></td>
            <td><span class="badge <?=$a['status']?>"><?=ucfirst($a['status'])?></span></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if($a['status']==='new'||$a['status']==='reviewed'): ?>
                <a href="?action=approve&id=<?=$a['id']?>" class="btn btn-green btn-sm">✓ Approve</a>
                <a href="?action=reject&id=<?=$a['id']?>" class="btn btn-red btn-sm" onclick="return confirm('Reject?')">✕ Reject</a>
                <?php else: ?>
                <span style="font-size:12px;color:#64748b;">Processed</span>
                <?php endif; ?>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
          <?php if(empty($apps)): ?>
          <tr><td colspan="9" style="text-align:center;padding:40px;color:#64748b;">No applications yet</td></tr>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </main>
</div>
</body>
</html>
