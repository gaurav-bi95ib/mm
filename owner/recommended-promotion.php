<?php
require_once __DIR__ . '/../api/db.php';
requireOwner();

$db = getDB();
syncPromotionStatuses();
$ownerId = (int)$_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';
$error = '';
$message = (($_GET['msg'] ?? '') === 'payment_complete')
    ? 'Payment received. Super Admin will now set and activate this exact venue placement.' : '';

$venueStmt = $db->prepare("SELECT id,name,city,district FROM venues WHERE owner_id=? AND status='active' ORDER BY name");
$venueStmt->execute([$ownerId]);
$venues = $venueStmt->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $venueId = (int)($_POST['venue_id'] ?? 0);
        $owns = $db->prepare("SELECT id,name FROM venues WHERE id=? AND owner_id=? AND status='active'");
        $owns->execute([$venueId,$ownerId]);
        $venue = $owns->fetch();
        $requestedStart = $_POST['starts_at'] ?? date('Y-m-d');
        if (!$venue) {
            $error = 'Choose one of your active venues.';
        } elseif (!strtotime($requestedStart) || strtotime($requestedStart) < strtotime(date('Y-m-d'))) {
            $error = 'Choose a valid requested start date.';
        } else {
            $current = $db->prepare("SELECT id FROM recommended_venue_promotions WHERE venue_id=? AND status IN ('pending_payment','pending_review','scheduled','active') AND expires_at>=CURDATE()");
            $current->execute([$venueId]);
            if ($current->fetchColumn()) {
                $error = 'This venue already has a current or pending Recommended Venue order.';
            } else {
                $expiry = date('Y-m-d', strtotime($requestedStart . ' +1 month'));
                $stmt = $db->prepare("INSERT INTO recommended_venue_promotions (tenant_id,owner_id,venue_id,amount_npr,starts_at,expires_at,status) VALUES (?,?,?,1000,?,?,'pending_payment')");
                $stmt->execute([$ownerId,$ownerId,$venueId,$requestedStart,$expiry]);
                $id = (int)$db->lastInsertId();
                logAudit('create_recommended','Promotions','recommended_venue',$id,'Created Recommended Venue order for '.$venue['name']);
                header('Location: ../esewa/payment.php?service_type=recommended_venue&service_id='.$id);
                exit;
            }
        }
    }
}

$ordersStmt = $db->prepare("SELECT r.*,v.name venue_name,v.city,(SELECT provider_reference FROM promotion_payments p WHERE p.service_type='recommended_venue' AND p.service_id=r.id AND p.status='paid' ORDER BY id DESC LIMIT 1) paid_reference FROM recommended_venue_promotions r JOIN venues v ON v.id=r.venue_id WHERE r.owner_id=? ORDER BY r.created_at DESC");
$ordersStmt->execute([$ownerId]);
$orders = $ordersStmt->fetchAll();
$paymentsStmt = $db->prepare("SELECT * FROM promotion_payments WHERE owner_id=? AND service_type='recommended_venue' ORDER BY created_at DESC LIMIT 20");
$paymentsStmt->execute([$ownerId]);
$payments = $paymentsStmt->fetchAll();

function recommendedStatus(string $status): string { return ucwords(str_replace('_',' ',$status)); }
function recommendedMessage(string $status): string {
    return match($status) {
        'pending_payment'=>'Complete payment to send the order to Super Admin.',
        'pending_review'=>'Paid — waiting for Super Admin to set the placement.',
        'scheduled'=>'Approved and scheduled for the selected start date.',
        'active'=>'Your venue is live in the paid Recommended section.',
        'expired'=>'The one-month placement has ended.',
        'rejected'=>'The order was not approved.',
        'suspended'=>'The placement is temporarily suspended.',
        default=>recommendedStatus($status),
    };
}
?>
<!DOCTYPE html><html lang="en"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Recommended Venue - MeroMaidan Owner</title><link rel="stylesheet" href="../assets/css/admin.css"><style>
.service-hero{display:grid;grid-template-columns:1fr auto;gap:25px;align-items:center;padding:30px;border-radius:23px;background:linear-gradient(130deg,#0b293d,#126044);color:#fff;margin-bottom:20px;overflow:hidden}.service-hero h1{font-size:31px;margin:5px 0}.service-hero p{color:#d5e8df;max-width:680px;font-size:12px;line-height:1.7}.eyebrow{font-size:9px;font-weight:900;letter-spacing:.13em;color:#8ff0b0}.hero-price{padding:20px 25px;border:1px solid rgba(255,255,255,.2);border-radius:16px;background:rgba(255,255,255,.09);font-size:27px;font-weight:900;white-space:nowrap}.hero-price small{display:block;font-size:10px;color:#bfe1cf;margin-top:5px}.workflow{display:grid;grid-template-columns:repeat(4,1fr);gap:10px;margin-bottom:20px}.step{padding:15px;border:1px solid #e1e8ee;border-radius:14px;background:#fff}.step b{display:grid;place-items:center;width:25px;height:25px;border-radius:7px;background:#e8f9ee;color:#15803d;margin-bottom:9px}.step strong{display:block;font-size:11px;color:#0f2740}.step span{font-size:10px;color:#64748b;line-height:1.5}.page-grid{display:grid;grid-template-columns:370px 1fr;gap:18px}.form-card{padding:22px;border:1px solid #e1e8ee;border-radius:18px;background:#fff}.field{margin:14px 0}.field label{display:block;margin-bottom:6px;font-size:9px;font-weight:900;letter-spacing:.07em;color:#5a7084;text-transform:uppercase}.field input,.field select{width:100%;padding:11px;border:1px solid #d7e1e9;border-radius:10px}.order{display:grid;grid-template-columns:64px 1fr auto;gap:14px;align-items:center;padding:16px 18px;border-bottom:1px solid #edf1f4}.mark{display:grid;place-items:center;width:60px;height:60px;border-radius:15px;background:#e9f9ef;color:#15803d;font-weight:900}.help{font-size:10px;color:#64748b;line-height:1.6}.state{font-size:10px;font-weight:700;color:#475569;margin:5px 0}.paid{display:inline-block;margin-top:5px;padding:4px 7px;border-radius:7px;background:#dcfce7;color:#166534;font-size:9px;font-weight:800}.notice{padding:13px;border-radius:10px;margin-bottom:15px}.notice.error{background:#fef2f2;color:#991b1b}.notice.success{background:#f0fdf4;color:#166534}.payment-row{display:flex;justify-content:space-between;padding:13px 18px;border-bottom:1px solid #edf1f4;font-size:11px}@media(max-width:900px){.page-grid,.service-hero{grid-template-columns:1fr}.workflow{grid-template-columns:1fr 1fr}}@media(max-width:600px){.workflow{grid-template-columns:1fr}.order{grid-template-columns:1fr}.hero-price{white-space:normal}}
</style></head><body><div class="admin-layout"><aside class="admin-sidebar"><div class="sidebar-logo"><div><div class="sidebar-logo-text">Mero<span>Maidan</span></div><div style="font-size:10px;color:rgba(255,255,255,.4)">Venue Owner Panel</div></div></div><nav class="sidebar-nav"><div class="nav-section-label">My Dashboard</div><a href="index.php" class="nav-link">Overview</a><a href="venue.php" class="nav-link">My Venue</a><a href="bookings.php" class="nav-link">Bookings</a><a href="slots.php" class="nav-link">Manage Slots</a><?php include __DIR__.'/_promotion_nav.php';?><div class="nav-section-label">Account</div><a href="../index.php" class="nav-link" target="_blank">View Site</a></nav><div class="sidebar-footer"><div class="admin-user-name"><?=htmlspecialchars($ownerName)?></div><a href="../auth/logout.php" class="btn-logout">Sign Out</a></div></aside><main class="admin-main"><div class="admin-topbar"><div class="topbar-title">Recommended <span>Venue</span></div></div><div class="admin-content">
<?php if($error):?><div class="notice error"><?=htmlspecialchars($error)?></div><?php endif;?><?php if($message):?><div class="notice success"><?=htmlspecialchars($message)?></div><?php endif;?>
<section class="service-hero"><div><span class="eyebrow">SEPARATE PAID LOCATION PROMOTION</span><h1>Put your venue where local players look.</h1><p>This placement is connected to one exact venue and appears only in the clearly labelled Recommended Venues area. It never changes permanent organic ranking.</p></div><div class="hero-price">NPR 1,000<small>ONE VENUE · ONE MONTH</small></div></section>
<div class="workflow"><div class="step"><b>1</b><strong>Choose venue</strong><span>Select the exact active venue.</span></div><div class="step"><b>2</b><strong>Pay by mock eSewa</strong><span>Mock eSewa is the only payment method for this service.</span></div><div class="step"><b>3</b><strong>Admin sets placement</strong><span>Super Admin verifies and chooses the actual start.</span></div><div class="step"><b>4</b><strong>Live for one month</strong><span>It activates and expires automatically.</span></div></div>
<div class="page-grid"><form method="post" class="form-card"><input type="hidden" name="csrf_token" value="<?=csrfToken()?>"><h3>Purchase Recommended Venue</h3><p class="help">The requested start can be adjusted by Super Admin during approval. Payment continues through mock eSewa only.</p><div class="field"><label>Venue to recommend</label><select name="venue_id" required><option value="">Choose venue</option><?php foreach($venues as $venue):?><option value="<?=$venue['id']?>"><?=htmlspecialchars($venue['name'].' · '.$venue['city'])?></option><?php endforeach;?></select></div><div class="field"><label>Requested start date</label><input type="date" name="starts_at" min="<?=date('Y-m-d')?>" value="<?=date('Y-m-d')?>" required></div><button class="btn btn-green" style="width:100%">Pay NPR 1,000 with mock eSewa</button></form>
<div><section class="data-card"><div class="data-card-header"><h3>Recommended Venue orders</h3><span><?=count($orders)?> total</span></div><?php if($orders):foreach($orders as $order):?><div class="order"><div class="mark">R</div><div><strong><?=htmlspecialchars($order['venue_name'])?></strong><div class="help"><?=htmlspecialchars($order['city'])?> · <?=$order['starts_at']?> to <?=$order['expires_at']?></div><div class="state"><?=htmlspecialchars(recommendedMessage($order['status']))?></div><span class="badge <?=htmlspecialchars($order['status'])?>"><?=htmlspecialchars(recommendedStatus($order['status']))?></span><?php if($order['paid_reference']):?><div class="paid">Paid · <?=htmlspecialchars($order['paid_reference'])?></div><?php endif;?></div><div><?php if($order['status']==='pending_payment'):?><a class="btn btn-green btn-sm" href="../esewa/payment.php?service_type=recommended_venue&amp;service_id=<?=$order['id']?>">Pay now</a><?php endif;?></div></div><?php endforeach;else:?><p class="help" style="padding:25px">No Recommended Venue orders yet.</p><?php endif;?></section>
<section class="data-card" style="margin-top:18px"><div class="data-card-header"><h3>Mock eSewa payment history</h3></div><?php if($payments):foreach($payments as $payment):?><div class="payment-row"><span><?=date('d M Y, g:i A',strtotime($payment['created_at']))?><br><small><?=htmlspecialchars($payment['provider_reference']??'-')?> · <?=htmlspecialchars(strtoupper($payment['payment_method']))?></small></span><strong>NPR <?=number_format($payment['amount_npr'])?> · <?=htmlspecialchars(recommendedStatus($payment['status']))?></strong></div><?php endforeach;else:?><p class="help" style="padding:25px">No mock eSewa payments yet.</p><?php endif;?></section></div></div>
</div></main></div></body></html>
