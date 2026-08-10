<?php
if (basename($_SERVER['SCRIPT_NAME'] ?? '') === 'promotions.php') {
    header('Location: event-promotions.php');
    exit;
}
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db = getDB();
$adminId = (int)$_SESSION['superadmin_id'];
$adminName = $_SESSION['superadmin_name'] ?? 'Admin';
$flash = '';
$flashType = 'success';

function promotionLabel(?string $value): string {
    return ucwords(str_replace('_', ' ', $value ?? ''));
}
function promotionMediaUrl(?string $path): string {
    if (!$path) return '';
    return preg_match('~^https?://~i', $path) ? $path : '../' . ltrim($path, '/');
}
function hasPaidPromotion(PDO $db, string $service, int $id): bool {
    $stmt = $db->prepare("SELECT COUNT(*) FROM promotion_payments WHERE service_type=? AND service_id=? AND status='paid' AND payment_method='esewa'");
    $stmt->execute([$service, $id]);
    return (int)$stmt->fetchColumn() > 0;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $flash = 'Your session expired. Please try again.';
        $flashType = 'error';
    } else {
        $type = $_POST['type'] ?? '';
        $action = $_POST['action'] ?? '';
        $id = (int)($_POST['id'] ?? 0);
        $reason = trim($_POST['reason'] ?? '');
        try {
            if ($type === 'event') {
                $stmt = $db->prepare("SELECT * FROM event_promotions WHERE id=?");
                $stmt->execute([$id]);
                $item = $stmt->fetch();
                if (!$item) throw new RuntimeException('Event campaign not found.');
                if ($action === 'approve') {
                    if ($item['status'] !== 'pending_review' || !hasPaidPromotion($db, 'event_promotion', $id)) throw new RuntimeException('Only a paid campaign awaiting review can be approved.');
                    // Approval is intentionally one click: publish now for exactly seven days.
                    $publishStart = date('Y-m-d H:i:s');
                    $publishEnd = date('Y-m-d H:i:s',strtotime($publishStart.' +'.EVENT_PROMOTION_DURATION_DAYS.' days'));
                    $status = 'active';
                    $db->beginTransaction();
                    $db->prepare("UPDATE event_promotions SET promotion_starts_at=?,promotion_expires_at=?,status=?,approved_by=?,approved_at=NOW(),rejection_reason=NULL WHERE id=?")->execute([$publishStart,$publishEnd,$status,$adminId,$id]);
                    $db->prepare("UPDATE promotion_hero_banners SET is_published=1 WHERE event_promotion_id=?")->execute([$id]);
                    $db->prepare("UPDATE coupons SET valid_from=?,valid_to=?,status='active' WHERE event_promotion_id=?")->execute([$publishStart,$publishEnd,$id]);
                    $db->commit();
                    createNotification((int)$item['owner_id'],'owner',(int)$item['tenant_id'],'Event Promotion Live','Your event banner is live now. Its submitted coupon is enabled for the selected venue for the same seven-day period.','system');
                    $flash = 'Event approved and shown now. The banner and coupon are live for seven days.';
                } elseif ($action === 'reactivate') {
                    if (!in_array($item['status'], ['suspended','rejected','cancelled'], true) || !hasPaidPromotion($db, 'event_promotion', $id)) {
                        throw new RuntimeException('Only a paid suspended, rejected, or cancelled campaign can be shown again.');
                    }
                    $publishStart = date('Y-m-d H:i:s');
                    $publishEnd = date('Y-m-d H:i:s', strtotime($publishStart . ' +' . EVENT_PROMOTION_DURATION_DAYS . ' days'));
                    $db->beginTransaction();
                    $db->prepare("UPDATE event_promotions SET event_date=CASE WHEN event_date<CURDATE() OR event_date>DATE_ADD(CURDATE(),INTERVAL 7 DAY) THEN CURDATE() ELSE event_date END,promotion_starts_at=?,promotion_expires_at=?,status='active',approved_by=?,approved_at=NOW(),rejection_reason=NULL WHERE id=?")
                        ->execute([$publishStart,$publishEnd,$adminId,$id]);
                    $db->prepare("UPDATE promotion_hero_banners SET is_published=1 WHERE event_promotion_id=?")->execute([$id]);
                    $db->prepare("UPDATE coupons SET valid_from=?,valid_to=?,status='active' WHERE event_promotion_id=?")->execute([$publishStart,$publishEnd,$id]);
                    $db->commit();
                    createNotification((int)$item['owner_id'],'owner',(int)$item['tenant_id'],'Event Promotion Live Again','Super Admin approved your paid campaign again. Its banner and coupon are live for seven days.','system');
                    $flash = 'Campaign approved and shown again. The banner and coupon are live for seven days.';
                } elseif ($action === 'restore') {
                    if (!in_array($item['status'], ['suspended','rejected','cancelled'], true) || !hasPaidPromotion($db, 'event_promotion', $id)) {
                        throw new RuntimeException('Only a paid suspended, rejected, or cancelled campaign can be restored.');
                    }
                    $reviewStart = date('Y-m-d H:i:s');
                    $reviewEnd = date('Y-m-d H:i:s', strtotime($reviewStart . ' +' . EVENT_PROMOTION_DURATION_DAYS . ' days'));
                    $db->beginTransaction();
                    $db->prepare("UPDATE event_promotions SET event_date=CASE WHEN event_date<CURDATE() OR event_date>DATE_ADD(CURDATE(),INTERVAL 7 DAY) THEN CURDATE() ELSE event_date END,promotion_starts_at=?,promotion_expires_at=?,status='pending_review',approved_by=NULL,approved_at=NULL,rejection_reason=NULL WHERE id=?")->execute([$reviewStart,$reviewEnd,$id]);
                    $db->prepare("UPDATE promotion_hero_banners SET is_published=0 WHERE event_promotion_id=?")->execute([$id]);
                    $db->prepare("UPDATE coupons SET valid_from=?,valid_to=?,status='draft' WHERE event_promotion_id=?")->execute([$reviewStart,$reviewEnd,$id]);
                    $db->commit();
                    createNotification((int)$item['owner_id'],'owner',(int)$item['tenant_id'],'Event Promotion Restored','Your paid campaign was restored and is waiting for Super Admin approval.','system');
                    $flash = 'Campaign restored to Pending Review. It can now be approved and shown immediately.';
                } elseif ($action === 'delete') {
                    $paid = hasPaidPromotion($db, 'event_promotion', $id);
                    $db->beginTransaction();
                    if ($paid) {
                        $db->prepare("UPDATE event_promotions SET status='cancelled',rejection_reason=? WHERE id=?")->execute([$reason ?: 'Archived by Super Admin',$id]);
                        $db->prepare("UPDATE promotion_hero_banners SET is_published=0 WHERE event_promotion_id=?")->execute([$id]);
                        $db->prepare("UPDATE coupons SET status='cancelled' WHERE event_promotion_id=?")->execute([$id]);
                        $flash = 'Paid campaign archived. Its payment and audit history were preserved.';
                    } else {
                        $db->prepare("DELETE FROM promotion_analytics WHERE promotion_type='event_promotion' AND promotion_id=?")->execute([$id]);
                        $db->prepare("DELETE FROM promotion_payments WHERE service_type='event_promotion' AND service_id=? AND status<>'paid'")->execute([$id]);
                        $db->prepare("DELETE FROM event_promotions WHERE id=?")->execute([$id]);
                        $flash = 'Unpaid campaign permanently deleted.';
                    }
                    $db->commit();
                    createNotification((int)$item['owner_id'],'owner',(int)$item['tenant_id'],'Event Promotion Removed',$paid?'Your paid campaign was archived by Super Admin.':'Your unpaid campaign was deleted by Super Admin.','system');
                } elseif (in_array($action, ['reject','suspend','cancel'], true)) {
                    $status = $action === 'reject' ? 'rejected' : ($action === 'suspend' ? 'suspended' : 'cancelled');
                    $db->beginTransaction();
                    $db->prepare("UPDATE event_promotions SET status=?,rejection_reason=? WHERE id=?")->execute([$status,$reason ?: null,$id]);
                    $db->prepare("UPDATE promotion_hero_banners SET is_published=0 WHERE event_promotion_id=?")->execute([$id]);
                    $couponStatus = $status === 'suspended' ? 'suspended' : 'cancelled';
                    $db->prepare("UPDATE coupons SET status=? WHERE event_promotion_id=?")->execute([$couponStatus,$id]);
                    $db->commit();
                    createNotification((int)$item['owner_id'],'owner',(int)$item['tenant_id'],'Event Promotion Updated','Your Event Promotion was '.promotionLabel($status).($reason?': '.$reason:''),'system');
                    $flash = 'Event Promotion updated.';
                } else throw new RuntimeException('Unsupported event action.');
                logAudit('moderate_event','Promotions','event_promotion',$id,$action . ' Event Promotion');
            } elseif ($type === 'coupon' && in_array($action, ['suspend','activate'], true)) {
                if ($action === 'suspend') {
                    $stmt = $db->prepare("UPDATE coupons SET status='suspended' WHERE id=? AND status='active'");
                    $stmt->execute([$id]);
                    if (!$stmt->rowCount()) throw new RuntimeException('Only an active coupon can be suspended.');
                    logAudit('suspend_coupon','Promotions','coupon',$id,'Coupon suspended by Super Admin');
                    $flash = 'Coupon turned off.';
                } else {
                    $stmt = $db->prepare("UPDATE coupons c JOIN event_promotions e ON e.id=c.event_promotion_id SET c.status='active',c.valid_from=e.promotion_starts_at,c.valid_to=e.promotion_expires_at WHERE c.id=? AND e.status='active' AND EXISTS (SELECT 1 FROM promotion_payments p WHERE p.service_type='event_promotion' AND p.service_id=e.id AND p.status='paid' AND p.payment_method='esewa')");
                    $stmt->execute([$id]);
                    if (!$stmt->rowCount()) throw new RuntimeException('The paid event campaign must be active before its coupon can be turned on.');
                    logAudit('activate_coupon','Promotions','coupon',$id,'Coupon activated by Super Admin');
                    $flash = 'Coupon turned on.';
                }
            } else throw new RuntimeException('Unsupported promotion action.');
        } catch (Throwable $e) {
            if ($db->inTransaction()) $db->rollBack();
            $flash = $e->getMessage();
            $flashType = 'error';
        }
    }
}

syncPromotionStatuses();

$events = $db->query("SELECT e.*,v.name venue_name,v.city,v.district,vo.name owner_name,b.image_url,c.id coupon_id,c.code coupon_code,c.status coupon_status,c.discount_type,c.discount_value,c.minimum_booking_amount,c.maximum_discount_amount,c.usage_limit,c.usage_limit_per_player,c.uses_count,(SELECT status FROM promotion_payments p WHERE p.service_type='event_promotion' AND p.service_id=e.id ORDER BY id DESC LIMIT 1) payment_status,(SELECT provider_reference FROM promotion_payments p WHERE p.service_type='event_promotion' AND p.service_id=e.id AND p.status='paid' ORDER BY id DESC LIMIT 1) paid_reference,(SELECT paid_at FROM promotion_payments p WHERE p.service_type='event_promotion' AND p.service_id=e.id AND p.status='paid' ORDER BY id DESC LIMIT 1) paid_at,(SELECT COUNT(*) FROM promotion_analytics a WHERE a.promotion_type='event_promotion' AND a.promotion_id=e.id AND a.event_type='impression') impressions,(SELECT COUNT(*) FROM promotion_analytics a WHERE a.promotion_type='event_promotion' AND a.promotion_id=e.id AND a.event_type='click') clicks,(SELECT COUNT(*) FROM promotion_analytics a WHERE a.promotion_type='event_promotion' AND a.promotion_id=e.id AND a.event_type='booking') attributed_bookings FROM event_promotions e JOIN venues v ON v.id=e.venue_id JOIN venue_owners vo ON vo.id=e.owner_id LEFT JOIN promotion_hero_banners b ON b.event_promotion_id=e.id LEFT JOIN coupons c ON c.event_promotion_id=e.id ORDER BY e.created_at DESC")->fetchAll();
$coupons = $db->query("SELECT c.*,v.name venue_name,e.title event_title,e.status event_status FROM coupons c JOIN venues v ON v.id=c.venue_id LEFT JOIN event_promotions e ON e.id=c.event_promotion_id ORDER BY c.created_at DESC LIMIT 100")->fetchAll();
$payments = $db->query("SELECT p.*,vo.name owner_name FROM promotion_payments p JOIN venue_owners vo ON vo.id=p.owner_id WHERE p.service_type='event_promotion' ORDER BY p.created_at DESC LIMIT 50")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Event Promotions - MeroMaidan Admin</title><link rel="stylesheet" href="../assets/css/admin.css">
<style>
.summary{display:grid;grid-template-columns:repeat(3,1fr);gap:13px;margin-bottom:20px}.summary div{padding:18px;border:1px solid #e1e8ee;border-radius:14px;background:#fff}.summary strong{display:block;font-size:25px}.service-tabs{display:flex;gap:9px;flex-wrap:wrap;margin-bottom:20px}.service-tabs a{padding:10px 15px;border:1px solid #dbe5eb;border-radius:999px;background:#fff;color:#52677a;text-decoration:none;font-size:11px;font-weight:800}.service-tabs a.active{border-color:#f97316;background:#fff7ed;color:#c2410c}.campaign{display:grid;grid-template-columns:105px minmax(0,1fr) auto;gap:16px;align-items:center;padding:18px;border-bottom:1px solid #edf1f4}.campaign:last-child{border-bottom:0}.campaign img{width:105px;height:76px;object-fit:cover;border-radius:12px;background:#edf2f5}.campaign-icon{display:grid;place-items:center;width:76px;height:76px;background:#eafaf0;border-radius:15px;color:#118642;font-weight:900}.meta{font-size:11px;color:#64748b;line-height:1.65}.actions{display:flex;gap:8px;flex-wrap:wrap;justify-content:flex-end;max-width:330px}.actions form{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}.flash{padding:12px;border-radius:10px;margin-bottom:15px}.flash.success{background:#f0fdf4;color:#166534}.flash.error{background:#fef2f2;color:#991b1b}.badge.pending_review{background:#fff7ed;color:#c2410c}.badge.scheduled{background:#eff6ff;color:#1d4ed8}.badge.suspended,.badge.rejected,.badge.expired,.badge.cancelled{background:#fef2f2;color:#b91c1c}.reason,.schedule-input{padding:7px;border:1px solid #dce5eb;border-radius:7px;font-size:10px}.reason{width:145px}.schedule-input{width:145px}.paid-order{display:inline-flex;align-items:center;gap:5px;margin:0 0 7px;padding:5px 9px;border-radius:999px;background:#dcfce7;color:#166534;font-size:10px;font-weight:900;text-transform:uppercase;letter-spacing:.04em}.campaign-title{font-size:14px;color:#102a43}.coupon-box{margin-top:7px;padding:8px 10px;border-radius:9px;background:#f8fafc;border:1px solid #e7edf2;color:#334155}.download-link{text-decoration:none;display:inline-flex;align-items:center}.review-note{padding:13px 17px;background:#fff9f0;border:1px solid #fed7aa;border-radius:12px;color:#9a3412;font-size:11px;margin-bottom:16px}.queue-count{color:#ea580c}.schedule-fields{display:flex;gap:5px;flex-wrap:wrap;width:100%;justify-content:flex-end}.schedule-fields label{font-size:9px;color:#64748b;display:grid;gap:3px}@media(max-width:850px){.summary{grid-template-columns:1fr}.campaign{grid-template-columns:1fr}.campaign img{width:100%;height:180px}.actions,.actions form,.schedule-fields{justify-content:flex-start}.data-table{min-width:850px}.data-card{overflow-x:auto}}
</style></head><body><div class="admin-layout">
<aside class="admin-sidebar"><div class="sidebar-logo"><div class="sidebar-logo-text">Mero<span>Maidan</span> <span class="sidebar-badge">ADMIN</span></div></div><nav class="sidebar-nav"><a href="index.php" class="nav-link">Dashboard</a><a href="venues.php" class="nav-link">Venues</a><a href="owners.php" class="nav-link">Owners</a><a href="bookings.php" class="nav-link">Bookings</a><a href="plans.php" class="nav-link">Commercial Services</a><div class="nav-section-label">Promotions</div><a href="recommended-promotions.php" class="nav-link">Recommended Venue</a><a href="event-promotions.php" class="nav-link active">Event Campaigns</a><a href="cms.php" class="nav-link">CMS</a><a href="audit.php" class="nav-link">Audit Logs</a></nav><div class="sidebar-footer"><div class="admin-user-name"><?=htmlspecialchars($adminName)?></div><a href="../auth/logout.php" class="btn-logout">Sign Out</a></div></aside>
<main class="admin-main"><div class="admin-topbar"><div class="topbar-title">Event <span>Campaigns</span></div></div><div class="admin-content"><div class="page-header"><h1>Event Campaign management</h1><p>A dedicated workspace for paid hero banners, campaign coupons, approvals and performance.</p></div>
<?php if($flash):?><div class="flash <?=htmlspecialchars($flashType)?>"><?=htmlspecialchars($flash)?></div><?php endif;?>
<?php $reviewEvents=array_values(array_filter($events,fn($e)=>$e['status']==='pending_review'&&$e['payment_status']==='paid'));$paidQueue=count($reviewEvents);?>
<div class="summary"><div><strong><?=count($events)?></strong><span>Total event campaigns</span></div><div><strong class="queue-count"><?=$paidQueue?></strong><span>Paid campaigns awaiting approval</span></div><div><strong>NPR <?=number_format(array_sum(array_map(fn($p)=>$p['status']==='paid'?(float)$p['amount_npr']:0,$payments)))?></strong><span>Event Promotion revenue</span></div></div>
<?php if($paidQueue):?><div class="review-note"><strong>Ready to publish:</strong> <?=$paidQueue?> paid Event Promotion order<?=$paidQueue===1?' is':'s are'?> waiting. Review the venue, banner and coupon, then use the single approval button to show it immediately.</div><?php endif;?>

<section class="data-card" style="margin-top:18px"><div class="data-card-header"><h3>Approval queue</h3><span>Paid campaigns ready for banner and coupon review</span></div>
<?php if($reviewEvents):foreach($reviewEvents as $event):?><div class="campaign"><img src="<?=htmlspecialchars(promotionMediaUrl($event['image_url']??''))?>" alt="<?=htmlspecialchars($event['title'])?>"><div><span class="paid-order">Paid event order · NPR 2,000 / week</span><div class="campaign-title"><strong><?=htmlspecialchars($event['title'])?></strong> <small>#<?=$event['id']?></small></div><div class="meta"><strong>Venue:</strong> <?=htmlspecialchars($event['venue_name'].' · '.$event['city'])?> · <strong>Owner:</strong> <?=htmlspecialchars($event['owner_name'])?><br><strong>Event:</strong> <?=date('d M Y',strtotime($event['event_date']))?> · <strong>Payment:</strong> <?=htmlspecialchars($event['paid_reference']??'-')?><?php if($event['coupon_code']):?><div class="coupon-box"><strong>Coupon:</strong> <?=htmlspecialchars($event['coupon_code'])?> · <?=number_format($event['discount_value'])?>% off</div><?php else:?><div class="coupon-box">No coupon submitted.</div><?php endif;?></div><span class="badge pending_review">Pending Review</span></div><div class="actions"><a class="btn btn-sm download-link" href="download-promotion-banner.php?event_id=<?=$event['id']?>">Download banner</a><form method="post"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="type" value="event"><input type="hidden" name="id" value="<?=$event['id']?>"><button name="action" value="approve" class="btn btn-green btn-sm">Approve &amp; Show Now</button><button name="action" value="reject" class="btn btn-red btn-sm">Reject</button></form></div></div><?php endforeach;else:?><p class="meta" style="padding:25px">No paid campaigns are waiting for approval.</p><?php endif;?></section>

<section class="data-card" style="margin-top:18px">
  <div class="data-card-header"><h3>Event campaign management</h3><span>Super Admin controls: edit, approve, turn off, show again and archive</span></div>
  <table class="data-table">
    <thead><tr><th>Campaign</th><th>Venue / Owner</th><th>Payment</th><th>Status</th><th>Management</th></tr></thead>
    <tbody>
    <?php if($events):foreach($events as $event): $isPaid=$event['payment_status']==='paid'; ?>
      <tr>
        <td><strong><?=htmlspecialchars($event['title'])?></strong><br><small>#<?=$event['id']?> · Event <?=date('d M Y',strtotime($event['event_date']))?></small></td>
        <td><?=htmlspecialchars($event['venue_name'])?><br><small><?=htmlspecialchars($event['owner_name'])?></small></td>
        <td><span class="badge <?=htmlspecialchars($event['payment_status']??'pending')?>"><?=htmlspecialchars(promotionLabel($event['payment_status']??'unpaid'))?></span><br><small><?=htmlspecialchars($event['paid_reference']??'-')?></small></td>
        <td><span class="badge <?=htmlspecialchars($event['status'])?>"><?=htmlspecialchars(promotionLabel($event['status']))?></span></td>
        <td><div class="actions" style="justify-content:flex-start;max-width:none">
          <a class="btn btn-sm" href="event-promotion-edit.php?id=<?=$event['id']?>">Edit Details</a>
          <?php if(in_array($event['status'],['suspended','rejected','cancelled'],true)&&$isPaid):?><form method="post" onsubmit="return confirm('Approve this paid campaign and show it again for seven days?')"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="type" value="event"><input type="hidden" name="id" value="<?=$event['id']?>"><button name="action" value="reactivate" class="btn btn-green btn-sm">Approve &amp; Show Again</button></form><?php endif;?>
          <?php if($event['status']==='pending_review'&&$isPaid):?><form method="post"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="type" value="event"><input type="hidden" name="id" value="<?=$event['id']?>"><button name="action" value="approve" class="btn btn-green btn-sm">Approve &amp; Show Now</button></form><?php endif;?>
          <?php if(in_array($event['status'],['scheduled','active'],true)):?><form method="post" onsubmit="return confirm('Temporarily turn off this campaign and its coupon?')"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="type" value="event"><input type="hidden" name="id" value="<?=$event['id']?>"><input type="hidden" name="reason" value="Turned off by Super Admin"><button name="action" value="suspend" class="btn btn-red btn-sm">Turn Off</button></form><?php endif;?>
          <?php if($event['status']==='active'):?><a class="btn btn-sm" href="../index.php" target="_blank">View Live</a><?php endif;?>
          <?php if($event['status']!=='cancelled'):?><form method="post" onsubmit="return confirm('<?=$isPaid?'Archive this paid campaign? Payment and audit history will be preserved.':'Permanently delete this unpaid campaign?'?>')"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="type" value="event"><input type="hidden" name="id" value="<?=$event['id']?>"><input type="hidden" name="reason" value="Removed from Campaign Management"><button name="action" value="delete" class="btn btn-red btn-sm"><?=$isPaid?'Archive':'Delete'?></button></form><?php endif;?>
        </div></td>
      </tr>
    <?php endforeach;else:?><tr><td colspan="5">No Event Promotion campaigns.</td></tr><?php endif;?>
    </tbody>
  </table>
</section>

<section class="data-card" style="margin-top:18px">
  <div class="data-card-header"><h3>Campaign coupons</h3><span>Turn individual coupons on or off while their paid event is active</span></div>
  <table class="data-table"><thead><tr><th>Code</th><th>Venue / Event</th><th>Discount</th><th>Validity</th><th>Usage</th><th>Status</th><th>Action</th></tr></thead><tbody>
  <?php if($coupons):foreach($coupons as $coupon):?><tr>
    <td><strong><?=htmlspecialchars($coupon['code'])?></strong></td><td><?=htmlspecialchars($coupon['venue_name'])?><br><small><?=htmlspecialchars($coupon['event_title']??'Standalone venue coupon')?></small></td><td><?=number_format($coupon['discount_value'])?><?=$coupon['discount_type']==='percentage'?'%':' NPR'?></td><td><?=date('d M Y',strtotime($coupon['valid_from']))?> to <?=date('d M Y',strtotime($coupon['valid_to']))?></td><td><?=$coupon['uses_count']?> / <?=$coupon['usage_limit']===null?'No total cap':$coupon['usage_limit']?></td><td><span class="badge <?=htmlspecialchars($coupon['status'])?>"><?=htmlspecialchars(promotionLabel($coupon['status']))?></span></td>
    <td><?php if($coupon['status']==='active'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="type" value="coupon"><input type="hidden" name="id" value="<?=$coupon['id']?>"><button name="action" value="suspend" class="btn btn-red btn-sm">Turn Off</button></form><?php elseif($coupon['event_status']==='active'):?><form method="post"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><input type="hidden" name="type" value="coupon"><input type="hidden" name="id" value="<?=$coupon['id']?>"><button name="action" value="activate" class="btn btn-green btn-sm">Turn On</button></form><?php else:?><small>Activate event first</small><?php endif;?></td>
  </tr><?php endforeach;else:?><tr><td colspan="7">No campaign coupons.</td></tr><?php endif;?></tbody></table>
</section>

<section class="data-card" style="margin-top:18px"><div class="data-card-header"><h3>Event payment history</h3><span>Mock eSewa payments for Event Campaigns only</span></div><table class="data-table"><thead><tr><th>Date</th><th>Owner</th><th>Amount</th><th>Method</th><th>Status</th><th>Reference</th></tr></thead><tbody><?php if($payments):foreach($payments as $payment):?><tr><td><?=date('d M Y',strtotime($payment['created_at']))?></td><td><?=htmlspecialchars($payment['owner_name'])?></td><td>NPR <?=number_format($payment['amount_npr'])?></td><td><?=htmlspecialchars(strtoupper($payment['payment_method']))?></td><td><span class="badge <?=htmlspecialchars($payment['status'])?>"><?=htmlspecialchars(promotionLabel($payment['status']))?></span></td><td><?=htmlspecialchars($payment['provider_reference']??'-')?></td></tr><?php endforeach;else:?><tr><td colspan="6">No Event Promotion payments.</td></tr><?php endif;?></tbody></table></section>
</div></main></div></body></html>
