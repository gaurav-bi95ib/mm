<?php
require_once __DIR__ . '/../api/db.php';
requireOwner();

$db = getDB();
syncPromotionStatuses();
$ownerId = (int)$_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';
$eventPrice = EVENT_PROMOTION_PRICE_NPR;
$error = '';
$message = (($_GET['msg'] ?? '') === 'payment_complete')
    ? 'Mock eSewa payment received. Super Admin must approve the campaign once; it will then show immediately for seven days.' : '';

$venueStmt = $db->prepare("SELECT id,name,city,district FROM venues WHERE owner_id=? AND status='active' ORDER BY name");
$venueStmt->execute([$ownerId]);
$venues = $venueStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $storedBanner = null;
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $venueId = (int)($_POST['venue_id'] ?? 0);
        $owns = $db->prepare("SELECT id,name FROM venues WHERE id=? AND owner_id=? AND status='active'");
        $owns->execute([$venueId,$ownerId]);
        $venue = $owns->fetch();
        $title = trim($_POST['title'] ?? '');
        $description = trim($_POST['short_description'] ?? '');
        $eventDate = $_POST['event_date'] ?? '';
        // Owners only supply the campaign content. The seven-day live period
        // starts when Super Admin approves it.
        $starts = date('Y-m-d H:i:s');
        $expires = date('Y-m-d H:i:s', strtotime($starts . ' +' . EVENT_PROMOTION_DURATION_DAYS . ' days'));
        $cta = 'Book Now';
        $code = strtoupper(trim($_POST['coupon_code'] ?? ''));
        $discountType = 'percentage';
        $discount = (float)($_POST['discount_percent'] ?? 0);
        $minimum = 0.0;
        $maximum = null;
        $usageLimit = null;
        $perPlayer = 1;
        $hasCoupon = $code !== '' || $discount > 0;
        $discountLabel = $hasCoupon ? number_format($discount, 0) . '% off with ' . $code : null;

        if (!$venue) {
            $error = 'Choose one of your active venues.';
        } elseif ($title === '' || mb_strlen($title) > 180 || $description === '' || mb_strlen($description) > 500 || !$eventDate) {
            $error = 'Complete the venue, event title, description and event date.';
        } elseif (strtotime($eventDate) < strtotime(date('Y-m-d',strtotime($starts))) || strtotime($eventDate) > strtotime(date('Y-m-d',strtotime($expires)))) {
            $error = 'Choose an event date within the next seven days.';
        } elseif (empty($_FILES['banner_image']['tmp_name']) || !is_uploaded_file($_FILES['banner_image']['tmp_name'])) {
            $error = 'Upload the required 1600 × 600 event banner.';
        } else {
            $image = @getimagesize($_FILES['banner_image']['tmp_name']);
            if (!$image || !in_array($image['mime'],['image/jpeg','image/png','image/webp'],true)) {
                $error = 'Banner must be a JPG, PNG, or WebP image.';
            } elseif ((int)$image[0] !== EVENT_BANNER_WIDTH || (int)$image[1] !== EVENT_BANNER_HEIGHT) {
                $error = 'Banner dimensions must be exactly 1600 × 600 pixels.';
            } elseif ((int)$_FILES['banner_image']['size'] > EVENT_BANNER_MAX_BYTES) {
                $error = 'Banner file must be 5 MB or smaller.';
            }
        }

        if (!$error && $hasCoupon && ($code === '' || $discount <= 0)) {
            $error = 'For a coupon, provide both the coupon code and discount value.';
        } elseif (!$error && $hasCoupon && !preg_match('/^[A-Z0-9_-]{3,50}$/',$code)) {
            $error = 'Coupon code must be 3–50 letters, numbers, hyphens, or underscores.';
        } elseif (!$error && $hasCoupon && ($discount < 1 || $discount > 100)) {
            $error = 'Discount percentage must be between 1% and 100%.';
        }

        if (!$error) {
            $ext = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'][$image['mime']];
            $dir = __DIR__.'/../uploads/promotions';
            if (!is_dir($dir) && !mkdir($dir,0755,true)) {
                $error = 'Unable to prepare secure banner storage.';
            } else {
                $filename = 'event-'.$ownerId.'-'.bin2hex(random_bytes(7)).'.'.$ext;
                $storedBanner = $dir.'/'.$filename;
                if (!move_uploaded_file($_FILES['banner_image']['tmp_name'],$storedBanner)) $error = 'Unable to store the banner.';
            }
        }

        if (!$error) {
            $db->beginTransaction();
            try {
                $stmt = $db->prepare("INSERT INTO event_promotions (tenant_id,owner_id,venue_id,title,short_description,event_date,promotion_starts_at,promotion_expires_at,discount_label,cta_text,amount_npr,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,'pending_payment')");
                $stmt->execute([$ownerId,$ownerId,$venueId,$title,$description,$eventDate,$starts,$expires,$discountLabel?:null,$cta,EVENT_PROMOTION_PRICE_NPR]);
                $eventId = (int)$db->lastInsertId();
                $db->prepare("INSERT INTO promotion_hero_banners (event_promotion_id,image_url,alt_text) VALUES (?,?,?)")->execute([$eventId,'uploads/promotions/'.$filename,$title.' event banner']);
                if ($hasCoupon) {
                    $db->prepare("INSERT INTO coupons (tenant_id,owner_id,venue_id,event_promotion_id,code,discount_type,discount_value,minimum_booking_amount,maximum_discount_amount,usage_limit,usage_limit_per_player,valid_from,valid_to,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,'draft')")
                        ->execute([$ownerId,$ownerId,$venueId,$eventId,$code,$discountType,$discount,$minimum,$maximum,$usageLimit,$perPlayer,$starts,$expires]);
                }
                logAudit('create_event_promotion','Promotions','event_promotion',$eventId,'Created one-week Event Promotion for '.$venue['name']);
                $db->commit();
                header('Location: ../esewa/payment.php?service_type=event_promotion&service_id='.$eventId);
                exit;
            } catch (Throwable $e) {
                if ($db->inTransaction()) $db->rollBack();
                if ($storedBanner && is_file($storedBanner)) unlink($storedBanner);
                error_log('Event Promotion creation failed: '.$e->getMessage());
                $error = str_contains($e->getMessage(),'Duplicate') ? 'That coupon code is already in use.' : 'Unable to save the campaign safely. Please try again.';
            }
        }
    }
}

$campaignStmt = $db->prepare("SELECT e.*,v.name venue_name,c.code coupon_code,c.status coupon_status,c.discount_type,c.discount_value,c.uses_count,(SELECT provider_reference FROM promotion_payments p WHERE p.service_type='event_promotion' AND p.service_id=e.id AND p.status='paid' ORDER BY id DESC LIMIT 1) paid_reference,(SELECT COUNT(*) FROM promotion_analytics a WHERE a.promotion_type='event_promotion' AND a.promotion_id=e.id AND a.event_type='impression') impressions,(SELECT COUNT(*) FROM promotion_analytics a WHERE a.promotion_type='event_promotion' AND a.promotion_id=e.id AND a.event_type='click') clicks,(SELECT COUNT(*) FROM promotion_analytics a WHERE a.promotion_type='event_promotion' AND a.promotion_id=e.id AND a.event_type='booking') bookings FROM event_promotions e JOIN venues v ON v.id=e.venue_id LEFT JOIN coupons c ON c.event_promotion_id=e.id WHERE e.owner_id=? ORDER BY e.created_at DESC");
$campaignStmt->execute([$ownerId]);
$campaigns = $campaignStmt->fetchAll();
$paymentsStmt = $db->prepare("SELECT * FROM promotion_payments WHERE owner_id=? AND service_type='event_promotion' ORDER BY created_at DESC LIMIT 20");
$paymentsStmt->execute([$ownerId]);
$payments = $paymentsStmt->fetchAll();

function eventStatus(string $status): string { return ucwords(str_replace('_',' ',$status)); }
function eventState(string $status): string {
    return match($status) {
        'pending_payment'=>'Campaign saved — complete the NPR 2,000 payment.',
        'pending_review'=>'Paid — waiting for Super Admin to approve and show it now.',
        'scheduled'=>'Approved — scheduled for its one-week period.',
        'active'=>'Live in the marketplace hero section.',
        'expired'=>'The one-week promotion has ended.',
        'rejected'=>'Not approved. Check the Admin reason before submitting again.',
        'suspended'=>'Temporarily disabled by Super Admin.',
        default=>eventStatus($status),
    };
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Event Promotion - MeroMaidan Owner</title><link rel="stylesheet" href="../assets/css/admin.css"><style>
.service-hero{display:grid;grid-template-columns:1fr auto;gap:25px;align-items:center;padding:30px;border-radius:23px;background:linear-gradient(130deg,#251b31,#9a3412);color:#fff;margin-bottom:20px}.service-hero h1{font-size:31px;margin:5px 0}.service-hero p{color:#f1dcd1;max-width:700px;font-size:12px;line-height:1.7}.eyebrow{font-size:9px;font-weight:900;letter-spacing:.13em;color:#fdba74}.hero-price{padding:20px 25px;border:1px solid rgba(255,255,255,.2);border-radius:16px;background:rgba(255,255,255,.09);font-size:27px;font-weight:900;white-space:nowrap}.hero-price small{display:block;font-size:10px;color:#fed7aa;margin-top:5px}.workflow{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px}.step{padding:15px;border:1px solid #e1e8ee;border-radius:14px;background:#fff}.step b{display:grid;place-items:center;width:25px;height:25px;border-radius:7px;background:#fff1e8;color:#c2410c;margin-bottom:9px}.step strong{display:block;font-size:11px;color:#0f2740}.step span,.help{font-size:10px;color:#64748b;line-height:1.55}.page-grid{display:grid;grid-template-columns:420px 1fr;gap:18px}.form-card{padding:22px;border:1px solid #e1e8ee;border-radius:18px;background:#fff}.field{margin:13px 0}.field label{display:block;margin-bottom:6px;font-size:9px;font-weight:900;letter-spacing:.07em;color:#5a7084;text-transform:uppercase}.field input,.field select,.field textarea{width:100%;padding:11px;border:1px solid #d7e1e9;border-radius:10px;font:inherit;font-size:12px}.field textarea{height:80px;resize:vertical}.grid2{display:grid;grid-template-columns:1fr 1fr;gap:10px}.banner-spec{padding:15px;margin:13px 0;border:2px dashed #fb923c;border-radius:13px;background:#fff8f2;color:#9a3412}.banner-spec strong{display:block;font-size:14px}.banner-spec span{font-size:10px;line-height:1.5}.coupon{padding:15px;margin-top:16px;border:1px solid #fed7aa;border-radius:13px;background:#fffaf6}.coupon h4{margin:0 0 5px;color:#9a3412}.campaign{display:grid;grid-template-columns:120px 1fr auto;gap:14px;align-items:center;padding:16px;border-bottom:1px solid #edf1f4}.campaign img{width:120px;height:70px;object-fit:cover;border-radius:10px}.state{font-size:10px;font-weight:700;color:#475569;margin:5px 0}.paid{display:inline-block;margin-top:5px;padding:4px 7px;border-radius:7px;background:#dcfce7;color:#166534;font-size:9px;font-weight:800}.metrics{display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;font-size:9px;color:#64748b}.notice{padding:13px;border-radius:10px;margin-bottom:15px}.notice.error{background:#fef2f2;color:#991b1b}.notice.success{background:#f0fdf4;color:#166534}.payment-row{display:flex;justify-content:space-between;padding:13px 18px;border-bottom:1px solid #edf1f4;font-size:11px}@media(max-width:1000px){.page-grid,.service-hero{grid-template-columns:1fr}.workflow{grid-template-columns:1fr 1fr}}@media(max-width:650px){.workflow,.grid2{grid-template-columns:1fr}.campaign{grid-template-columns:1fr}.campaign img{width:100%;height:160px}.hero-price{white-space:normal}}
.workflow{grid-template-columns:repeat(3,1fr)}.simple-campaign-form{padding:28px;box-shadow:0 16px 42px rgba(15,39,64,.08)}.form-heading{padding-bottom:18px;margin-bottom:18px;border-bottom:1px solid #e7edf2}.form-heading>span,.optional-tag{display:inline-flex;padding:5px 8px;border-radius:999px;background:#eafaf0;color:#16813e;font-size:8px;font-weight:900;letter-spacing:.09em}.form-heading h3{font-size:22px;margin:9px 0 4px;color:#0f2740}.field-note{display:block;margin-top:6px;color:#77899a;font-size:9px}.simple-offer{padding:17px;background:#fffaf5}.simple-offer h4{margin-top:8px}.percent-input{position:relative}.percent-input input{padding-right:38px}.percent-input span{position:absolute;right:14px;top:50%;transform:translateY(-50%);color:#159447;font-size:14px;font-weight:900}.campaign-submit{width:100%;min-height:48px;margin-top:17px;display:flex;align-items:center;justify-content:space-between;padding:0 18px}.campaign-submit span{font-size:10px}.payment-note{margin:10px 0 0;text-align:center;color:#77899a;font-size:9px;line-height:1.5}
</style></head><body><div class="admin-layout"><aside class="admin-sidebar"><div class="sidebar-logo"><div><div class="sidebar-logo-text">Mero<span>Maidan</span></div><div style="font-size:10px;color:rgba(255,255,255,.4)">Venue Owner Panel</div></div></div><nav class="sidebar-nav"><div class="nav-section-label">My Dashboard</div><a href="index.php" class="nav-link">Overview</a><a href="venue.php" class="nav-link">My Venue</a><a href="bookings.php" class="nav-link">Bookings</a><a href="slots.php" class="nav-link">Manage Slots</a><?php include __DIR__.'/_promotion_nav.php';?><div class="nav-section-label">Account</div><a href="../index.php" class="nav-link" target="_blank">View Site</a></nav><div class="sidebar-footer"><div class="admin-user-name"><?=htmlspecialchars($ownerName)?></div><a href="../auth/logout.php" class="btn-logout">Sign Out</a></div></aside><main class="admin-main"><div class="admin-topbar"><div class="topbar-title">Event <span>Promotion</span></div></div><div class="admin-content">
<?php if($error):?><div class="notice error"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($message):?><div class="notice success"><?=htmlspecialchars($message)?></div><?php endif;?>
<section class="service-hero"><div><span class="eyebrow">ONE-WEEK EVENT CAMPAIGN</span><h1>Promote your event in three simple steps.</h1><p>Add your event, banner and optional percentage offer. Pay through mock eSewa, then Super Admin reviews and publishes it for seven days.</p></div><div class="hero-price">NPR 2,000<small>ONE CAMPAIGN · 7 DAYS</small></div></section>
<div class="workflow"><div class="step"><b>1</b><strong>Add event and banner</strong><span>Use the short form below.</span></div><div class="step"><b>2</b><strong>Pay with mock eSewa</strong><span>Complete the NPR 2,000 payment.</span></div><div class="step"><b>3</b><strong>Admin publishes</strong><span>Your hero and coupon go live together.</span></div></div>
<?php if($campaigns): $preview=$campaigns[0]; ?>
<section style="margin-bottom:18px;padding:18px;border:1px solid #dce5eb;border-radius:18px;background:#fff">
  <div style="display:flex;align-items:center;justify-content:space-between;gap:14px;flex-wrap:wrap;margin-bottom:12px">
    <div><h3 style="margin:0;color:#0f2740">Marketplace hero preview</h3><p class="help" style="margin-top:5px">This is how your latest campaign will look after Super Admin approval.</p></div>
    <span class="badge <?=htmlspecialchars($preview['status'])?>"><?=htmlspecialchars(eventStatus($preview['status']))?></span>
  </div>
  <div style="position:relative;min-height:300px;overflow:hidden;border-radius:20px;background-image:linear-gradient(90deg,rgba(6,22,39,.98) 0%,rgba(8,28,46,.88) 43%,rgba(8,28,46,.14) 82%),url('../api/promotion_image.php?event_id=<?=(int)$preview['id']?>');background-position:center;background-size:cover;box-shadow:0 18px 36px rgba(15,39,64,.18)">
    <div style="max-width:620px;padding:38px;color:#fff">
      <div style="display:flex;align-items:center;gap:8px;color:#86efac;font-size:10px;font-weight:900;letter-spacing:.1em;text-transform:uppercase"><span style="width:22px;height:3px;border-radius:3px;background:#1bb955"></span>Promoted event · <?=htmlspecialchars($preview['venue_name'])?></div>
      <h2 style="margin:11px 0 9px;font-size:30px;line-height:1.15;color:#fff"><?=htmlspecialchars($preview['title'])?></h2>
      <p style="max-width:540px;margin:0;color:#d8e2ea;font-size:12px;line-height:1.7"><?=htmlspecialchars($preview['short_description'])?></p>
      <div style="display:flex;gap:8px;flex-wrap:wrap;margin:15px 0">
        <span style="padding:7px 10px;border:1px solid rgba(255,255,255,.18);border-radius:999px;background:rgba(255,255,255,.1);font-size:10px;font-weight:800">Event <?=date('d M Y',strtotime($preview['event_date']))?></span>
        <?php if($preview['coupon_code']):?><span style="padding:7px 10px;border:1px solid rgba(255,255,255,.18);border-radius:999px;background:rgba(255,255,255,.1);font-size:10px;font-weight:800">Coupon <?=htmlspecialchars($preview['coupon_code'])?></span><?php endif;?>
      </div>
      <span style="display:inline-flex;padding:10px 15px;border-radius:10px;background:#1bb955;color:#fff;font-size:11px;font-weight:900"><?=htmlspecialchars($preview['cta_text'] ?: 'View Venue')?> →</span>
    </div>
  </div>
  <p class="help" style="margin-top:10px">Preview only. It becomes visible to players after mock eSewa payment and Super Admin approval.</p>
</section>
<?php endif; ?>
<div class="page-grid">
<form method="post" enctype="multipart/form-data" class="form-card simple-campaign-form">
  <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
  <div class="form-heading"><span>Simple campaign setup</span><h3>Create Event Promotion</h3><p class="help">Add the event and optional percentage discount. We handle the seven-day timing automatically after Admin approval.</p></div>
  <div class="field"><label>1. Venue</label><select name="venue_id" required><option value="">Choose your venue</option><?php foreach($venues as $venue):?><option value="<?=$venue['id']?>"><?=htmlspecialchars($venue['name'].' · '.$venue['city'])?></option><?php endforeach;?></select></div>
  <div class="field"><label>2. Event title</label><input name="title" maxlength="180" placeholder="Example: Dashain Futsal Festival" required></div>
  <div class="field"><label>3. Short banner message</label><textarea name="short_description" maxlength="500" placeholder="Tell players what is happening and why they should book." required></textarea></div>
  <div class="field"><label>4. Event date</label><input type="date" name="event_date" min="<?=date('Y-m-d')?>" max="<?=date('Y-m-d',strtotime('+'.EVENT_PROMOTION_DURATION_DAYS.' days'))?>" required><span class="field-note">Choose a date within the upcoming seven-day campaign.</span></div>
  <div class="banner-spec"><strong>5. Upload your 1600 × 600 banner</strong><span>JPG, PNG or WebP · maximum 5 MB. The exact size keeps the marketplace hero sharp on every screen.</span></div>
  <div class="field"><input type="file" name="banner_image" accept="image/jpeg,image/png,image/webp" required></div>
  <div class="coupon simple-offer"><div class="optional-tag">OPTIONAL DISCOUNT</div><h4>Add a booking offer</h4><p class="help">Enter only a coupon code and the percentage players receive.</p><div class="grid2"><div class="field"><label>Coupon code</label><input name="coupon_code" maxlength="50" placeholder="DASHAIN20"></div><div class="field"><label>Discount percentage</label><div class="percent-input"><input type="number" min="1" max="100" step="1" name="discount_percent" placeholder="20"><span>%</span></div></div></div></div>
  <button class="btn btn-green campaign-submit">Continue to mock eSewa <span>NPR 2,000 →</span></button>
  <p class="payment-note">You will pay once. Super Admin reviews the banner and publishes it with one approval.</p>
</form>
<div><section class="data-card"><div class="data-card-header"><h3>Event Promotion campaigns</h3><span>One week each</span></div><?php if($campaigns):foreach($campaigns as $campaign):?><div class="campaign"><img src="../api/promotion_image.php?event_id=<?=$campaign['id']?>" alt="<?=htmlspecialchars($campaign['title'])?>"><div><strong><?=htmlspecialchars($campaign['title'])?></strong><div class="help"><?=htmlspecialchars($campaign['venue_name'])?> · <?=date('d M Y',strtotime($campaign['promotion_starts_at']))?> to <?=date('d M Y',strtotime($campaign['promotion_expires_at']))?></div><?php if($campaign['coupon_code']):?><div class="help">Coupon <strong><?=htmlspecialchars($campaign['coupon_code'])?></strong> · <?=number_format($campaign['discount_value'])?><?=$campaign['discount_type']==='percentage'?'%':' NPR'?> · <?=htmlspecialchars(eventStatus($campaign['coupon_status']))?></div><?php endif;?><div class="state"><?=htmlspecialchars(eventState($campaign['status']))?></div><span class="badge <?=htmlspecialchars($campaign['status'])?>"><?=htmlspecialchars(eventStatus($campaign['status']))?></span><?php if($campaign['paid_reference']):?><div class="paid">Paid · <?=htmlspecialchars($campaign['paid_reference'])?></div><?php endif;?><div class="metrics"><span><?=$campaign['impressions']?> views</span><span><?=$campaign['clicks']?> clicks</span><span><?=$campaign['bookings']?> bookings</span><?php if($campaign['coupon_code']):?><span><?=$campaign['uses_count']?> coupon uses</span><?php endif;?></div></div><div><?php if($campaign['status']==='pending_payment'):?><a class="btn btn-green btn-sm" href="../esewa/payment.php?service_type=event_promotion&amp;service_id=<?=$campaign['id']?>">Pay NPR 2,000</a><?php endif;?></div></div><?php endforeach;else:?><p class="help" style="padding:25px">No Event Promotion campaigns yet.</p><?php endif;?></section><section class="data-card" style="margin-top:18px"><div class="data-card-header"><h3>Event payment history</h3></div><?php if($payments):foreach($payments as $payment):?><div class="payment-row"><span><?=date('d M Y, g:i A',strtotime($payment['created_at']))?><br><small><?=htmlspecialchars($payment['provider_reference']??'-')?></small></span><strong>NPR <?=number_format($payment['amount_npr'])?> · <?=htmlspecialchars(eventStatus($payment['status']))?></strong></div><?php endforeach;else:?><p class="help" style="padding:25px">No Event Promotion payments yet.</p><?php endif;?></section></div></div>
</div></main></div></body></html>
