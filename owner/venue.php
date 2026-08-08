<?php
require_once __DIR__ . '/../api/db.php';
requireOwner();
$db = getDB();
$ownerId   = $_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';

// Get owner's venues
$stmt = $db->prepare("SELECT * FROM venues WHERE owner_id=:oid ORDER BY created_at DESC");
$stmt->execute([':oid'=>$ownerId]);
$myVenues = $stmt->fetchAll();
$selectedVenueId = (int)($_GET['venue_id'] ?? ($myVenues[0]['id'] ?? 0));
$selectedVenue = null;
foreach($myVenues as $v){ if($v['id']==$selectedVenueId){ $selectedVenue=$v; break; } }

$msg = '';
$err = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? 'update_venue';
    if ($action === 'create_venue') {
        // Enforce subscription plan venue limit
        if (!checkSubscriptionLimits($ownerId, 'venues')) {
            $err = '❌ Subscription Plan Limit Reached! Upgrade your plan to add more venues.';
        } else {
            $vName = trim($_POST['new_name'] ?? '');
            $vSport = $_POST['new_sport'] ?? 'Futsal';
            $vAddress = trim($_POST['new_address'] ?? '');
            $vPrice = (float)($_POST['new_price'] ?? 1000);
            $vSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $vName)));
            
            if ($vName && $vAddress) {
                $cStmt = $db->prepare("INSERT INTO venues (owner_id, name, slug, sport_type, address, price_per_hour, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $cStmt->execute([$ownerId, $vName, $vSlug . '-' . rand(100,999), $vSport, $vAddress, $vPrice]);
                $newVid = $db->lastInsertId();
                logAudit('create_venue', 'venue', 'venues', $newVid, "Created new venue: $vName");
                createNotification($ownerId, 'owner', $ownerId, "Venue Created", "Your new venue '$vName' is now active!", "system");
                header("Location: venue.php?venue_id=" . $newVid);
                exit;
            }
        }
    } elseif ($selectedVenue) {
        $stmt = $db->prepare("UPDATE venues SET name=:name, address=:address, description=:desc, price_per_hour=:price, open_time=:open, close_time=:close WHERE id=:id AND owner_id=:oid");
        $stmt->execute([
            ':name'  => $_POST['name'],
            ':address'=> $_POST['address'],
            ':desc'  => $_POST['description'],
            ':price' => $_POST['price_per_hour'],
            ':open'  => $_POST['open_time'],
            ':close' => $_POST['close_time'],
            ':id'    => $selectedVenueId,
            ':oid'   => $ownerId,
        ]);
        $msg = '✅ Venue updated successfully!';
        $stmt2 = $db->prepare("SELECT * FROM venues WHERE id=:id");
        $stmt2->execute([':id'=>$selectedVenueId]);
        $selectedVenue = $stmt2->fetch();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>My Venue – MeroMaidan Owner</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-logo"><div><div class="sidebar-logo-text">Mero<span>Maidan</span></div><div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px;">Venue Owner Panel</div></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">My Dashboard</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Overview</a>
      <a href="venue.php" class="nav-link active"><span class="icon">🏟️</span> My Venue</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="slots.php" class="nav-link"><span class="icon">⏰</span> Manage Slots</a>
      <div class="nav-section-label">Account</div>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
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
      <div class="topbar-title">🏟️ My <span>Venue</span></div>
    </div>
    <div class="admin-content">
      <div class="page-header">
        <h1>Venue Management</h1>
        <p>Update your venue details, pricing, and operating hours.</p>
      </div>
      <?php if($msg): ?><div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:12px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:700;color:#166534;"><?=$msg?></div><?php endif;?>

      <?php if(count($myVenues)>1): ?>
      <div class="filter-bar" style="margin-bottom:20px;">
        <?php foreach($myVenues as $v): ?>
        <a href="?venue_id=<?=$v['id']?>" class="btn <?=$v['id']==$selectedVenueId?'btn-green':'btn-ghost'?> btn-sm">🏟️ <?=htmlspecialchars($v['name'])?></a>
        <?php endforeach;?>
      </div>
      <?php endif;?>

      <?php if(!$selectedVenue): ?>
      <div style="text-align:center;padding:60px;background:#fff;border-radius:16px;">
        <div style="font-size:48px;">🏟️</div>
        <h3 style="margin:12px 0 8px;color:#0f2740;">No Active Venue</h3>
        <p style="color:#64748b;font-size:13px;">Your venue is pending approval, or you haven't listed one yet.</p>
        <a href="../list-ground.php" class="btn btn-green" style="margin-top:16px;display:inline-flex;">+ List Your Ground</a>
      </div>
      <?php else: ?>

      <div style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">
        <!-- Edit Form -->
        <div class="data-card">
          <div class="data-card-header">
            <h3>✏️ Edit Venue Details</h3>
            <span class="badge <?=$selectedVenue['status']?>"><?=ucfirst($selectedVenue['status'])?></span>
          </div>
          <form method="POST" style="padding:24px;">
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
              <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Venue Name</label>
                <input type="text" name="name" class="form-input" value="<?=htmlspecialchars($selectedVenue['name'])?>" required>
              </div>
              <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Address</label>
                <input type="text" name="address" class="form-input" value="<?=htmlspecialchars($selectedVenue['address'])?>" required>
              </div>
              <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Description</label>
                <textarea name="description" class="form-input" style="min-height:100px;resize:vertical;"><?=htmlspecialchars($selectedVenue['description']??'')?></textarea>
              </div>
              <div class="form-group">
                <label class="form-label">Price per Hour (NPR)</label>
                <input type="number" name="price_per_hour" class="form-input" value="<?=intval($selectedVenue['price_per_hour'])?>" min="0" required>
              </div>
              <div class="form-group">
                <label class="form-label">Sport Type</label>
                <input type="text" class="form-input" value="<?=htmlspecialchars($selectedVenue['sport_type'])?>" disabled style="opacity:.6;">
                <small style="font-size:11px;color:#94a3b8;">Contact support to change sport type</small>
              </div>
              <div class="form-group">
                <label class="form-label">Opening Time</label>
                <input type="time" name="open_time" class="form-input" value="<?=substr($selectedVenue['open_time'],0,5)?>">
              </div>
              <div class="form-group">
                <label class="form-label">Closing Time</label>
                <input type="time" name="close_time" class="form-input" value="<?=substr($selectedVenue['close_time'],0,5)?>">
              </div>
            </div>
            <button type="submit" class="btn btn-green">💾 Save Changes</button>
          </form>
        </div>

        <!-- Venue Preview -->
        <div>
          <div class="data-card" style="overflow:hidden;">
            <img src="<?=htmlspecialchars($selectedVenue['cover_image']??'')?>" alt="<?=htmlspecialchars($selectedVenue['name'])?>" style="width:100%;height:180px;object-fit:cover;display:block;">
            <div style="padding:20px;">
              <div style="font-size:17px;font-weight:900;color:#0f2740;"><?=htmlspecialchars($selectedVenue['name'])?></div>
              <div style="font-size:12px;color:#64748b;margin:4px 0;">📍 <?=htmlspecialchars($selectedVenue['address'])?></div>
              <div style="display:flex;gap:8px;margin:12px 0;flex-wrap:wrap;">
                <span class="badge pending"><?=$selectedVenue['sport_type']?></span>
                <span class="badge free"><?=$selectedVenue['capacity']?></span>
              </div>
              <div style="font-size:20px;font-weight:900;color:#1BB955;">NPR <?=number_format($selectedVenue['price_per_hour'])?><span style="font-size:12px;color:#64748b;font-weight:500;">/hr</span></div>
              <div style="font-size:12px;color:#64748b;margin-top:4px;">⭐ <?=$selectedVenue['rating']?> · <?=$selectedVenue['total_reviews']?> reviews</div>
              <div style="margin-top:16px;display:flex;gap:8px;">
                <a href="../venue.php?slug=<?=urlencode($selectedVenue['slug'])?>" target="_blank" class="btn btn-green btn-sm">👁 View Live</a>
                <a href="slots.php?venue_id=<?=$selectedVenue['id']?>" class="btn btn-ghost btn-sm">⏰ Slots</a>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endif;?>
    </div>
  </main>
</div>
</body>
</html>
