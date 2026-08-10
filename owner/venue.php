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
if (!$selectedVenue && !empty($myVenues)) {
    $selectedVenue = $myVenues[0];
    $selectedVenueId = (int)$selectedVenue['id'];
}
$facilityOptions = ['Parking','Changing Room','Drinking Water','Washroom','Shower','Floodlights','First Aid','Cafeteria','Equipment Rental','Locker','Seating'];

$msg = '';
$err = '';
if($_SERVER['REQUEST_METHOD']==='POST'){
    if(!verifyCsrfToken($_POST['csrf_token']??'')){http_response_code(403);die('Your session expired.');}
    $action = $_POST['action'] ?? 'update_venue';
    if ($action === 'create_venue') {
        // Enforce subscription plan venue limit
        if (!checkSubscriptionLimits($ownerId, 'venues')) {
            $err = 'Your annual subscription includes one venue. Contact support if this venue needs to be replaced.';
        } else {
            $vName = trim($_POST['new_name'] ?? '');
            $vSport = $_POST['new_sport'] ?? 'Futsal';
            $vAddress = trim($_POST['new_address'] ?? '');
            $vPrice = (float)($_POST['new_price'] ?? 1000);
            $vSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $vName)));
            
            if (!in_array($vSport, ['Futsal','Football','Cricket','Cricsal'], true)) {
                $err = 'Choose a supported sport.';
            } elseif ($vName && mb_strlen($vName) <= 200 && $vAddress && mb_strlen($vAddress) <= 500 && $vPrice > 0) {
                $cStmt = $db->prepare("INSERT INTO venues (owner_id, name, slug, sport_type, address, price_per_hour, status) VALUES (?, ?, ?, ?, ?, ?, 'active')");
                $cStmt->execute([$ownerId, $vName, $vSlug . '-' . rand(100,999), $vSport, $vAddress, $vPrice]);
                $newVid = $db->lastInsertId();
                logAudit('create_venue', 'venue', 'venues', $newVid, "Created new venue: $vName");
                createNotification($ownerId, 'owner', $ownerId, "Venue Created", "Your new venue '$vName' is now active!", "system");
                header("Location: venue.php?venue_id=" . $newVid);
                exit;
            } else $err = 'Enter a valid venue name, address, and positive hourly price.';
        }
    } elseif ($selectedVenue) {
        $name=trim($_POST['name']??'');$address=trim($_POST['address']??'');$description=trim($_POST['description']??'');
        $price=(float)($_POST['price_per_hour']??0);$open=$_POST['open_time']??'';$close=$_POST['close_time']??'';
        $amenities = array_values(array_unique(array_filter(array_map(fn($value) => trim(strip_tags((string)$value)), (array)($_POST['amenities'] ?? [])), fn($value) => $value !== '' && mb_strlen($value) <= 50)));
        foreach (preg_split('/[,\r\n]+/', (string)($_POST['other_amenities'] ?? '')) as $extra) {
            $extra = trim(strip_tags($extra));
            if ($extra !== '' && mb_strlen($extra) <= 50) $amenities[] = $extra;
        }
        $amenities = array_slice(array_values(array_unique($amenities)), 0, 20);
        $coverImage = trim($_POST['cover_image'] ?? '');
        $galleryImages = [];
        foreach (preg_split('/[\r\n]+/', (string)($_POST['gallery_images'] ?? '')) as $imageUrl) {
            $imageUrl = trim($imageUrl);
            if ($imageUrl !== '') $galleryImages[] = $imageUrl;
        }
        $galleryImages = array_slice(array_values(array_unique($galleryImages)), 0, 8);
        $validHttpUrl = static fn(string $url): bool => filter_var($url, FILTER_VALIDATE_URL) !== false && preg_match('~^https?://~i', $url);
        $invalidGallery = array_filter($galleryImages, fn($url) => !$validHttpUrl($url));
        if($name===''||mb_strlen($name)>200||$address===''||mb_strlen($address)>500||$price<=0||!$open||!$close||$open>=$close){$err='Enter a valid name, address, positive price, and operating time range.';}
        elseif($coverImage!==''&&!$validHttpUrl($coverImage)){$err='Cover image must be a valid http or https URL.';}
        elseif($invalidGallery){$err='Every gallery image must be a valid http or https URL, one per line.';}
        else{
        $stmt = $db->prepare("UPDATE venues SET name=:name, address=:address, description=:desc, amenities=:amenities,images=:images,cover_image=:cover,price_per_hour=:price, open_time=:open, close_time=:close WHERE id=:id AND owner_id=:oid");
        $stmt->execute([
            ':name'  => $name,
            ':address'=> $address,
            ':desc'  => $description,
            ':amenities' => json_encode($amenities, JSON_UNESCAPED_UNICODE),
            ':images' => json_encode($galleryImages, JSON_UNESCAPED_SLASHES),
            ':cover' => $coverImage ?: null,
            ':price' => $price,
            ':open'  => $open,
            ':close' => $close,
            ':id'    => $selectedVenueId,
            ':oid'   => $ownerId,
        ]);
        $msg = '✅ Venue updated successfully!';
        $stmt2 = $db->prepare("SELECT * FROM venues WHERE id=:id");
        $stmt2->execute([':id'=>$selectedVenueId]);
        $selectedVenue = $stmt2->fetch();
        }
    }
}
$selectedAmenities = $selectedVenue ? (json_decode($selectedVenue['amenities'] ?? '[]', true) ?: []) : [];
$otherAmenities = array_values(array_diff($selectedAmenities, $facilityOptions));
$selectedGallery = $selectedVenue ? (json_decode($selectedVenue['images'] ?? '[]', true) ?: []) : [];
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
      <?php include __DIR__ . '/_promotion_nav.php'; ?>
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
        <p>Manage venue details, facilities, photos, pricing, and operating hours.</p>
      </div>
      <?php if($msg): ?><div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:12px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:700;color:#166534;"><?=$msg?></div><?php endif;?>
      <?php if($err): ?><div style="background:#fef2f2;border:2px solid #fecaca;border-radius:12px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:700;color:#991b1b;"><?=htmlspecialchars($err)?></div><?php endif;?>

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

      <div class="admin-split" style="display:grid;grid-template-columns:1fr 340px;gap:20px;align-items:start;">
        <!-- Edit Form -->
        <div class="data-card">
          <div class="data-card-header">
            <h3>✏️ Edit Venue Details</h3>
            <span class="badge <?=$selectedVenue['status']?>"><?=ucfirst($selectedVenue['status'])?></span>
          </div>
          <form method="POST" style="padding:24px;">
            <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
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
              <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Facilities &amp; Amenities</label>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:9px;padding:14px;border:1px solid #e2e8f0;border-radius:12px;background:#f8fafc;">
                  <?php foreach($facilityOptions as $facility): ?>
                    <label style="display:flex;align-items:center;gap:8px;font-size:12px;font-weight:700;color:#334155;"><input type="checkbox" name="amenities[]" value="<?=htmlspecialchars($facility,ENT_QUOTES)?>" <?=in_array($facility,$selectedAmenities,true)?'checked':''?>> <?=htmlspecialchars($facility)?></label>
                  <?php endforeach; ?>
                </div>
                <input type="text" name="other_amenities" class="form-input" style="margin-top:9px" maxlength="500" value="<?=htmlspecialchars(implode(', ', $otherAmenities))?>" placeholder="Other facilities, separated by commas">
              </div>
              <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Cover Image URL</label>
                <input type="url" name="cover_image" class="form-input" value="<?=htmlspecialchars($selectedVenue['cover_image']??'')?>" placeholder="https://example.com/venue-cover.jpg">
              </div>
              <div class="form-group" style="grid-column:1/-1;">
                <label class="form-label">Gallery Image URLs</label>
                <textarea name="gallery_images" class="form-input" style="min-height:110px;resize:vertical" placeholder="One https image URL per line, maximum 8"><?=htmlspecialchars(implode("\n", $selectedGallery))?></textarea>
                <small style="font-size:11px;color:#94a3b8;">These photos appear in the public venue gallery. Add one URL per line.</small>
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
                <span class="badge active"><?=$selectedVenue['capacity']?></span>
              </div>
              <div style="font-size:20px;font-weight:900;color:#1BB955;">NPR <?=number_format($selectedVenue['price_per_hour'])?><span style="font-size:12px;color:#64748b;font-weight:500;">/hr</span></div>
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
