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
// Handle add/toggle slot
if($_SERVER['REQUEST_METHOD']==='POST'){
    $action = $_POST['action'] ?? '';
    if($action==='add_slot' && $selectedVenueId){
        for($day=0;$day<=6;$day++){
            if(isset($_POST['day_'.$day])){
                $stmt = $db->prepare("INSERT INTO venue_slots (venue_id, day_of_week, start_time, end_time, price, is_available) VALUES (:vid,:day,:start,:end,:price,1)");
                $stmt->execute([':vid'=>$selectedVenueId,':day'=>$day,':start'=>$_POST['start_time'],':end'=>$_POST['end_time'],':price'=>$_POST['price']]);
            }
        }
        $msg = '✅ Slot added successfully!';
    }
    if($action==='toggle_slot' && isset($_POST['slot_id'])){
        $db->prepare("UPDATE venue_slots SET is_available = 1 - is_available WHERE id=:id")->execute([':id'=>$_POST['slot_id']]);
        $msg = '✅ Slot toggled!';
    }
    if($action==='delete_slot' && isset($_POST['slot_id'])){
        $db->prepare("DELETE FROM venue_slots WHERE id=:id")->execute([':id'=>$_POST['slot_id']]);
        $msg = '✅ Slot deleted!';
    }
    header("Location: slots.php?venue_id=$selectedVenueId&msg=".urlencode($msg)); exit;
}

if(isset($_GET['msg'])) $msg = htmlspecialchars($_GET['msg']);

// Get slots for selected venue grouped by day
$slots = [];
if($selectedVenueId){
    $stmt = $db->prepare("SELECT * FROM venue_slots WHERE venue_id=:vid ORDER BY day_of_week, start_time");
    $stmt->execute([':vid'=>$selectedVenueId]);
    foreach($stmt->fetchAll() as $s) $slots[$s['day_of_week']][] = $s;
}
$dayNames = ['Sunday','Monday','Tuesday','Wednesday','Thursday','Friday','Saturday'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1.0">
  <title>Manage Slots – MeroMaidan Owner</title>
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
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="slots.php" class="nav-link active"><span class="icon">⏰</span> Manage Slots</a>
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
      <div class="topbar-title">⏰ Manage <span>Slots</span></div>
    </div>
    <div class="admin-content">
      <div class="page-header">
        <h1>Time Slot Management</h1>
        <p>Configure available booking slots and pricing for each day of the week.</p>
      </div>

      <?php if($msg): ?><div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:12px;padding:12px 16px;margin-bottom:20px;font-size:13px;font-weight:700;color:#166534;"><?=$msg?></div><?php endif;?>

      <?php if(count($myVenues) > 1): ?>
      <!-- Venue Selector -->
      <div class="filter-bar" style="margin-bottom:20px;">
        <span style="font-size:13px;font-weight:700;color:#64748b;">Select Venue:</span>
        <?php foreach($myVenues as $v): ?>
        <a href="?venue_id=<?=$v['id']?>" class="btn <?=$v['id']==$selectedVenueId?'btn-green':'btn-ghost'?> btn-sm">🏟️ <?=htmlspecialchars($v['name'])?></a>
        <?php endforeach;?>
      </div>
      <?php endif;?>

      <?php if(!$selectedVenue): ?>
      <div style="text-align:center;padding:60px;background:#fff;border-radius:16px;">
        <div style="font-size:48px;">⏰</div>
        <h3 style="margin:12px 0 8px;color:#0f2740;">No Venue Found</h3>
        <p style="color:#64748b;font-size:13px;">You don't have any active venues. Add a venue first.</p>
        <a href="../list-ground.php" class="btn btn-green" style="margin-top:16px;display:inline-flex;">+ Add Your Ground</a>
      </div>
      <?php else: ?>

      <div style="display:grid;grid-template-columns:1fr 360px;gap:20px;align-items:start;">

        <!-- Slots By Day -->
        <div>
          <?php for($day=0;$day<=6;$day++): ?>
          <div class="data-card" style="margin-bottom:16px;">
            <div class="data-card-header">
              <h3>📅 <?=$dayNames[$day]?></h3>
              <span style="font-size:12px;color:#64748b;"><?=count($slots[$day]??[])?> slots</span>
            </div>
            <?php if(!empty($slots[$day])): ?>
            <table class="data-table">
              <thead><tr><th>Time</th><th>Price (NPR)</th><th>Status</th><th>Actions</th></tr></thead>
              <tbody>
              <?php foreach($slots[$day] as $s): ?>
              <tr>
                <td><strong><?=substr($s['start_time'],0,5)?> – <?=substr($s['end_time'],0,5)?></strong></td>
                <td>NPR <?=number_format($s['price'])?></td>
                <td><span class="badge <?=$s['is_available']?'active':'suspended'?>"><?=$s['is_available']?'Available':'Blocked'?></span></td>
                <td>
                  <form method="POST" style="display:inline;">
                    <input type="hidden" name="action" value="toggle_slot">
                    <input type="hidden" name="slot_id" value="<?=$s['id']?>">
                    <button type="submit" class="btn btn-ghost btn-sm"><?=$s['is_available']?'⏸ Block':'▶ Enable'?></button>
                  </form>
                  <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this slot?')">
                    <input type="hidden" name="action" value="delete_slot">
                    <input type="hidden" name="slot_id" value="<?=$s['id']?>">
                    <button type="submit" class="btn btn-red btn-sm">🗑</button>
                  </form>
                </td>
              </tr>
              <?php endforeach;?>
              </tbody>
            </table>
            <?php else: ?>
            <div style="padding:16px 20px;font-size:13px;color:#94a3b8;">No slots for this day. Add slots using the form →</div>
            <?php endif;?>
          </div>
          <?php endfor;?>
        </div>

        <!-- Add Slot Form -->
        <div class="data-card" style="position:sticky;top:80px;">
          <div class="data-card-header"><h3>➕ Add New Slot</h3></div>
          <form method="POST" style="padding:20px;">
            <input type="hidden" name="action" value="add_slot">
            <div class="form-group">
              <label class="form-label">Start Time</label>
              <input type="time" name="start_time" class="form-input" value="06:00" required>
            </div>
            <div class="form-group">
              <label class="form-label">End Time</label>
              <input type="time" name="end_time" class="form-input" value="07:00" required>
            </div>
            <div class="form-group">
              <label class="form-label">Price (NPR/hr)</label>
              <input type="number" name="price" class="form-input" value="<?=intval($selectedVenue['price_per_hour'])?>" min="0" required>
            </div>
            <div class="form-group">
              <label class="form-label">Apply to Days</label>
              <div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;margin-top:8px;">
                <?php foreach($dayNames as $i=>$d): ?>
                <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;cursor:pointer;">
                  <input type="checkbox" name="day_<?=$i?>" value="1" <?=$i>=1&&$i<=5?'checked':''?> style="width:16px;height:16px;accent-color:#1BB955;">
                  <?=substr($d,0,3)?>
                </label>
                <?php endforeach;?>
              </div>
            </div>
            <button type="submit" class="btn btn-green" style="width:100%;justify-content:center;">➕ Add Slots</button>
          </form>
          <div style="padding:0 20px 20px;">
            <div style="background:#f0fdf4;border-radius:10px;padding:12px;font-size:12px;color:#166534;">
              💡 Tip: Slots with higher prices in peak hours (5–9 PM) earn you more!
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
