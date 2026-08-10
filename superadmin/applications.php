<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db = getDB();

// Application decisions are POST-only and CSRF-protected.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'], $_POST['id'])) {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        die('Your session expired. Please return and try again.');
    }
    $id = (int)$_POST['id'];
    $act = $_POST['action'];

    if ($act === 'approve') {
        $db->beginTransaction();
        try {
        $stmt = $db->prepare("SELECT * FROM owner_applications WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $id]);
        $app = $stmt->fetch();

        if ($app && in_array($app['status'], ['new','reviewed'], true)) {
            // Update application status
            $db->prepare("UPDATE owner_applications SET status='approved' WHERE id=:id")->execute([':id'=>$id]);

            // Check or create venue owner account
            $oStmt = $db->prepare("SELECT id FROM venue_owners WHERE email = :e LIMIT 1");
            $oStmt->execute([':e' => $app['email']]);
            $owner = $oStmt->fetch();

            if (!$owner) {
                // Provision owner account with default password Owner@1234
                $passHash = password_hash('Owner@1234', PASSWORD_BCRYPT);
                $annualPlanId = (int)$db->query("SELECT id FROM subscription_plans WHERE slug='annual-venue' LIMIT 1")->fetchColumn();
                $insOwner = $db->prepare("INSERT INTO venue_owners (name, email, phone, password_hash, business_name, plan_id, status, approved_at) VALUES (:n, :e, :p, :ph, :bn, :plan, 'active', NOW())");
                $insOwner->execute([
                    ':n' => $app['owner_name'],
                    ':e' => $app['email'],
                    ':p' => $app['phone'],
                    ':ph' => $passHash,
                    ':bn' => $app['business_name'],
                    ':plan' => $annualPlanId
                ]);
                $ownerId = $db->lastInsertId();
            } else {
                $ownerId = $owner['id'];
            }

            // Provision venue workspace
            $venueId = null;
            if (!empty($app['venue_name'])) {
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $app['venue_name']), '-'));
                $vIns = $db->prepare("INSERT INTO venues (owner_id, name, slug, sport_type, address, city, district, price_per_hour, open_time, close_time, status) VALUES (:oid, :n, :slug, :st, :addr, :city, :dist, :price, :open, :close, 'active')");
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
                $venueId = (int)$db->lastInsertId();
            }

            $annualPlanId = (int)$db->query("SELECT id FROM subscription_plans WHERE slug='annual-venue' LIMIT 1")->fetchColumn();
            $existingSub = $db->prepare("SELECT id FROM venue_subscriptions WHERE owner_id=? LIMIT 1");
            $existingSub->execute([$ownerId]);
            if (!$existingSub->fetchColumn()) {
                $db->prepare("INSERT INTO venue_subscriptions (tenant_id,owner_id,venue_id,plan_id,amount_npr,starts_at,expires_at,status) VALUES (?,?,?,?,9999,CURDATE(),DATE_ADD(CURDATE(),INTERVAL 1 YEAR),'pending_payment')")
                   ->execute([$ownerId,$ownerId,$venueId,$annualPlanId]);
            }

            logAudit('approve_application', 'Tenant', 'owner_application', $id, "SuperAdmin approved application and provisioned workspace for {$app['business_name']}");
        }
        $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            http_response_code(500);
            die('The application could not be approved safely. No partial workspace was created.');
        }
    } elseif ($act === 'reject') {
        $db->prepare("UPDATE owner_applications SET status='rejected' WHERE id=:id")->execute([':id'=>$id]);
        logAudit('reject_application', 'Tenant', 'owner_application', $id, "SuperAdmin rejected application #$id");
    } else {
        http_response_code(400);
        die('Unsupported application action.');
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
            <td><span class="badge active">Annual Venue Subscription</span></td>
            <td style="font-size:12px;color:#64748b;"><?=date('M j, Y', strtotime($a['created_at']))?></td>
            <td><span class="badge <?=$a['status']?>"><?=ucfirst($a['status'])?></span></td>
            <td>
              <div style="display:flex;gap:6px;flex-wrap:wrap;">
                <?php if($a['status']==='new'||$a['status']==='reviewed'): ?>
                <form method="post" style="display:inline"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="id" value="<?=$a['id']?>"><button name="action" value="approve" class="btn btn-green btn-sm">✓ Approve</button></form>
                <form method="post" style="display:inline" onsubmit="return confirm('Reject this application?')"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="id" value="<?=$a['id']?>"><button name="action" value="reject" class="btn btn-red btn-sm">✕ Reject</button></form>
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
