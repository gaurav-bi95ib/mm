<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();

$db = getDB();
$adminName = $_SESSION['superadmin_name'] ?? 'Admin';
$id = (int)($_GET['id'] ?? $_POST['id'] ?? 0);
$error = '';
$message = (($_GET['msg'] ?? '') === 'saved') ? 'Campaign details updated successfully.' : '';

$load = function () use ($db, $id): array|false {
    $stmt = $db->prepare("SELECT e.*,v.name venue_name,v.city,vo.name owner_name,b.image_url,c.id coupon_id,c.code coupon_code,c.discount_type,c.discount_value,c.minimum_booking_amount,c.maximum_discount_amount,c.usage_limit,c.usage_limit_per_player,c.uses_count,c.status coupon_status,(SELECT status FROM promotion_payments p WHERE p.service_type='event_promotion' AND p.service_id=e.id ORDER BY p.id DESC LIMIT 1) payment_status,(SELECT provider_reference FROM promotion_payments p WHERE p.service_type='event_promotion' AND p.service_id=e.id AND p.status='paid' ORDER BY p.id DESC LIMIT 1) payment_reference FROM event_promotions e JOIN venues v ON v.id=e.venue_id JOIN venue_owners vo ON vo.id=e.owner_id LEFT JOIN promotion_hero_banners b ON b.event_promotion_id=e.id LEFT JOIN coupons c ON c.event_promotion_id=e.id WHERE e.id=? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch();
};

$campaign = $load();
if (!$campaign) {
    http_response_code(404);
    die('Event Promotion campaign not found.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['short_description'] ?? '');
        $eventDate = $_POST['event_date'] ?? '';
        $discountLabel = trim($_POST['discount_label'] ?? '');
        $cta = in_array($_POST['cta_text'] ?? '', ['View Venue','Book Now'], true) ? $_POST['cta_text'] : 'View Venue';
        $hasCoupon = isset($_POST['has_coupon']);
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $discountType = $_POST['discount_type'] ?? 'percentage';
        $discountValue = (float)($_POST['discount_value'] ?? 0);
        $minimum = max(0, (float)($_POST['minimum_booking_amount'] ?? 0));
        $maximum = ($_POST['maximum_discount_amount'] ?? '') !== '' ? max(0, (float)$_POST['maximum_discount_amount']) : null;
        $usageLimit = ($_POST['usage_limit'] ?? '') !== '' ? max(1, (int)$_POST['usage_limit']) : null;
        $perPlayer = max(1, (int)($_POST['usage_limit_per_player'] ?? 1));

        if ($title === '' || mb_strlen($title) > 180) {
            $error = 'Enter a campaign title up to 180 characters.';
        } elseif ($description === '' || mb_strlen($description) > 500) {
            $error = 'Enter a short description up to 500 characters.';
        } elseif (mb_strlen($discountLabel) > 120) {
            $error = 'Discount label must be 120 characters or fewer.';
        } elseif (!$eventDate || !strtotime($eventDate)) {
            $error = 'Choose a valid event date.';
        } elseif (strtotime($eventDate) < strtotime(date('Y-m-d', strtotime($campaign['promotion_starts_at']))) || strtotime($eventDate) > strtotime(date('Y-m-d', strtotime($campaign['promotion_expires_at'])))) {
            $error = 'Event date must remain inside the campaign period.';
        } elseif ($hasCoupon && !preg_match('/^[A-Z0-9_-]{3,50}$/', $code)) {
            $error = 'Coupon code must contain 3–50 letters, numbers, hyphens, or underscores.';
        } elseif ($hasCoupon && (!in_array($discountType, ['percentage','fixed'], true) || $discountValue <= 0 || ($discountType === 'percentage' && $discountValue > 100))) {
            $error = 'Enter a valid coupon discount.';
        }

        if (!$error && $hasCoupon) {
            $unique = $db->prepare("SELECT COUNT(*) FROM coupons WHERE code=? AND id<>?");
            $unique->execute([$code, (int)($campaign['coupon_id'] ?? 0)]);
            if ((int)$unique->fetchColumn()) $error = 'That coupon code already belongs to another campaign.';
        }

        if (!$error) {
            $db->beginTransaction();
            try {
                $db->prepare("UPDATE event_promotions SET title=?,short_description=?,event_date=?,discount_label=?,cta_text=? WHERE id=?")
                    ->execute([$title,$description,$eventDate,$discountLabel ?: null,$cta,$id]);

                if ($hasCoupon) {
                    $couponStatus = $campaign['status'] === 'active' ? 'active' : ($campaign['status'] === 'suspended' ? 'suspended' : 'draft');
                    if ($campaign['coupon_id']) {
                        $db->prepare("UPDATE coupons SET code=?,discount_type=?,discount_value=?,minimum_booking_amount=?,maximum_discount_amount=?,usage_limit=?,usage_limit_per_player=?,valid_from=?,valid_to=?,status=? WHERE id=?")
                            ->execute([$code,$discountType,$discountValue,$minimum,$maximum,$usageLimit,$perPlayer,$campaign['promotion_starts_at'],$campaign['promotion_expires_at'],$couponStatus,$campaign['coupon_id']]);
                    } else {
                        $db->prepare("INSERT INTO coupons (tenant_id,owner_id,venue_id,event_promotion_id,code,discount_type,discount_value,minimum_booking_amount,maximum_discount_amount,usage_limit,usage_limit_per_player,valid_from,valid_to,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)")
                            ->execute([$campaign['tenant_id'],$campaign['owner_id'],$campaign['venue_id'],$id,$code,$discountType,$discountValue,$minimum,$maximum,$usageLimit,$perPlayer,$campaign['promotion_starts_at'],$campaign['promotion_expires_at'],$couponStatus]);
                    }
                } elseif ($campaign['coupon_id']) {
                    if ((int)$campaign['uses_count'] > 0) {
                        $db->prepare("UPDATE coupons SET status='cancelled' WHERE id=?")->execute([$campaign['coupon_id']]);
                    } else {
                        $db->prepare("DELETE FROM coupons WHERE id=?")->execute([$campaign['coupon_id']]);
                    }
                }

                logAudit('update_event_promotion','Promotions','event_promotion',$id,'Super Admin updated Event Promotion campaign details');
                $db->commit();
                createNotification((int)$campaign['owner_id'],'owner',(int)$campaign['tenant_id'],'Event Promotion Updated','Super Admin updated the details for '.$title.'.','system');
                header('Location: event-promotion-edit.php?id='.$id.'&msg=saved');
                exit;
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                error_log('Event Promotion edit failed: '.$e->getMessage());
                $error = 'Unable to save the campaign safely. Please try again.';
            }
        }
    }
    $campaign = $load();
}

function statusLabel(string $status): string {
    return ucwords(str_replace('_', ' ', $status));
}

$bannerUrl = preg_match('~^https?://~i', (string)$campaign['image_url'])
    ? $campaign['image_url']
    : '../' . ltrim((string)$campaign['image_url'], '/');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Edit Event Promotion - MeroMaidan Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
  <style>
    .edit-grid{display:grid;grid-template-columns:minmax(0,1fr) 390px;gap:20px}.form-card,.preview-card{padding:22px;border:1px solid #e1e8ee;border-radius:18px;background:#fff}.field{margin:14px 0}.field label{display:block;margin-bottom:6px;font-size:10px;font-weight:900;letter-spacing:.05em;color:#52677a;text-transform:uppercase}.field input,.field select,.field textarea{width:100%;padding:11px;border:1px solid #d7e1e9;border-radius:10px;font:inherit;font-size:12px}.field textarea{min-height:105px;resize:vertical}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}.coupon-panel{padding:16px;border:1px solid #fed7aa;border-radius:14px;background:#fffaf5}.check-row{display:flex;align-items:center;gap:8px;color:#9a3412;font-size:12px;font-weight:800}.check-row input{width:auto}.banner{width:100%;aspect-ratio:8/3;object-fit:cover;border-radius:14px;background:#e8eef3}.readonly{display:grid;gap:10px;margin-top:16px}.readonly div{padding:11px;border-radius:10px;background:#f7f9fb;color:#52677a;font-size:11px}.readonly strong{display:block;color:#0f2740;margin-bottom:3px}.notice{padding:12px 14px;border-radius:10px;margin-bottom:16px}.notice.success{background:#effaf3;color:#13783a}.notice.error{background:#fff1f2;color:#b42318}.actions-row{display:flex;gap:10px;flex-wrap:wrap;margin-top:18px}@media(max-width:950px){.edit-grid{grid-template-columns:1fr}}@media(max-width:600px){.grid2{grid-template-columns:1fr}}
  </style>
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-logo"><div class="sidebar-logo-text">Mero<span>Maidan</span> <span class="sidebar-badge">ADMIN</span></div></div>
    <nav class="sidebar-nav"><a href="index.php" class="nav-link">Dashboard</a><a href="venues.php" class="nav-link">Venues</a><a href="owners.php" class="nav-link">Owners</a><a href="bookings.php" class="nav-link">Bookings</a><a href="plans.php" class="nav-link">Commercial Services</a><div class="nav-section-label">Promotions</div><a href="recommended-promotions.php" class="nav-link">Recommended Venue</a><a href="event-promotions.php" class="nav-link active">Event Campaigns</a><a href="cms.php" class="nav-link">CMS</a><a href="audit.php" class="nav-link">Audit Logs</a></nav>
    <div class="sidebar-footer"><div class="admin-user-name"><?=htmlspecialchars($adminName)?></div><a href="../auth/logout.php" class="btn-logout">Sign Out</a></div>
  </aside>
  <main class="admin-main">
    <div class="admin-topbar"><div class="topbar-title">Edit Event <span>Promotion</span></div></div>
    <div class="admin-content">
      <div class="page-header"><h1><?=htmlspecialchars($campaign['title'])?></h1><p>Update marketplace content and coupon settings without changing the recorded payment.</p></div>
      <?php if($error):?><div class="notice error"><?=htmlspecialchars($error)?></div><?php endif;?>
      <?php if($message):?><div class="notice success"><?=htmlspecialchars($message)?></div><?php endif;?>
      <div class="edit-grid">
        <form method="post" class="form-card">
          <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
          <input type="hidden" name="id" value="<?=$id?>">
          <h3>Campaign content</h3>
          <div class="field"><label>Event title</label><input name="title" maxlength="180" value="<?=htmlspecialchars($campaign['title'])?>" required></div>
          <div class="field"><label>Short hero description</label><textarea name="short_description" maxlength="500" required><?=htmlspecialchars($campaign['short_description'])?></textarea></div>
          <div class="grid2"><div class="field"><label>Event date</label><input type="date" name="event_date" value="<?=htmlspecialchars($campaign['event_date'])?>" required></div><div class="field"><label>CTA button</label><select name="cta_text"><option<?=$campaign['cta_text']==='View Venue'?' selected':''?>>View Venue</option><option<?=$campaign['cta_text']==='Book Now'?' selected':''?>>Book Now</option></select></div></div>
          <div class="field"><label>Offer text</label><input name="discount_label" maxlength="120" value="<?=htmlspecialchars($campaign['discount_label'] ?? '')?>" placeholder="15% off with KTFUTSAL15"></div>
          <div class="coupon-panel">
            <label class="check-row"><input type="checkbox" name="has_coupon" id="hasCoupon"<?=$campaign['coupon_id']?' checked':''?>> Include venue coupon</label>
            <div id="couponFields">
              <div class="grid2"><div class="field"><label>Coupon code</label><input name="coupon_code" maxlength="50" value="<?=htmlspecialchars($campaign['coupon_code'] ?? '')?>"></div><div class="field"><label>Discount type</label><select name="discount_type"><option value="percentage"<?=$campaign['discount_type']==='percentage'?' selected':''?>>Percentage</option><option value="fixed"<?=$campaign['discount_type']==='fixed'?' selected':''?>>Fixed NPR</option></select></div></div>
              <div class="grid2"><div class="field"><label>Discount value</label><input type="number" min="0" step="0.01" name="discount_value" value="<?=htmlspecialchars($campaign['discount_value'] ?? '')?>"></div><div class="field"><label>Minimum booking</label><input type="number" min="0" step="0.01" name="minimum_booking_amount" value="<?=htmlspecialchars($campaign['minimum_booking_amount'] ?? '0')?>"></div></div>
              <div class="grid2"><div class="field"><label>Maximum discount</label><input type="number" min="0" step="0.01" name="maximum_discount_amount" value="<?=htmlspecialchars($campaign['maximum_discount_amount'] ?? '')?>"></div><div class="field"><label>Total usage limit</label><input type="number" min="1" name="usage_limit" value="<?=htmlspecialchars($campaign['usage_limit'] ?? '')?>"></div></div>
              <div class="field"><label>Uses per player</label><input type="number" min="1" name="usage_limit_per_player" value="<?=htmlspecialchars($campaign['usage_limit_per_player'] ?? '1')?>"></div>
            </div>
          </div>
          <div class="actions-row"><button class="btn btn-green">Save Changes</button><a class="btn" href="event-promotions.php">Back to Event Campaigns</a></div>
        </form>
        <aside class="preview-card">
          <img class="banner" src="<?=htmlspecialchars($bannerUrl)?>" alt="<?=htmlspecialchars($campaign['title'])?> banner">
          <div class="readonly"><div><strong>Owner and venue</strong><?=htmlspecialchars($campaign['owner_name'].' · '.$campaign['venue_name'].' · '.$campaign['city'])?></div><div><strong>Commercial record</strong>NPR <?=number_format($campaign['amount_npr'])?> · <?=htmlspecialchars(strtoupper($campaign['payment_status'] ?? 'unpaid'))?> · <?=htmlspecialchars($campaign['payment_reference'] ?? '-')?></div><div><strong>Current status</strong><?=htmlspecialchars(statusLabel($campaign['status']))?><?php if($campaign['coupon_id']):?> · Coupon <?=htmlspecialchars(statusLabel($campaign['coupon_status']))?><?php endif;?></div><div><strong>Campaign validity</strong><?=date('d M Y, g:i A',strtotime($campaign['promotion_starts_at']))?> to <?=date('d M Y, g:i A',strtotime($campaign['promotion_expires_at']))?></div></div>
          <div class="actions-row"><a class="btn" href="download-promotion-banner.php?event_id=<?=$id?>">Download Banner</a></div>
        </aside>
      </div>
    </div>
  </main>
</div>
<script>
const couponToggle=document.getElementById('hasCoupon');
const couponFields=document.getElementById('couponFields');
function updateCouponFields(){couponFields.hidden=!couponToggle.checked;}
couponToggle.addEventListener('change',updateCouponFields);updateCouponFields();
</script>
</body>
</html>
