<?php
// MeroMaidan - Mock eSewa Payment Processing & Atomic Confirmation Callback
require_once __DIR__ . '/../api/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL);
    exit;
}
if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    die('Your payment session expired. Return to MeroMaidan and try again.');
}

$db = getDB();
$gatewaySetting = $db->query("SELECT config_value FROM platform_commercial_config WHERE config_key='mock_esewa_enabled' LIMIT 1")->fetchColumn();
if ($gatewaySetting !== false && (string)$gatewaySetting !== '1') {
    http_response_code(503);
    die('Mock eSewa payments are temporarily unavailable. Please choose cash or try again later.');
}

$subscription_upgrade = (int)($_POST['subscription_upgrade'] ?? 0);
$plan_id = (int)($_POST['plan_id'] ?? 0);
$service_type = trim($_POST['service_type'] ?? '');
$service_id = (int)($_POST['service_id'] ?? 0);

if ($service_type && $service_id) {
    requireOwner();
    $owner_id = (int)$_SESSION['owner_id'];
    $amount = (float)($_POST['total_price'] ?? 0);
    $reference = 'ESEWA-PROMO-'.strtoupper(substr(uniqid(),-7));
    $promotionName = '';
    $venueName = '';
    $db->beginTransaction();
    try {
        if ($service_type === 'recommended_venue') {
            $stmt=$db->prepare("SELECT r.*,v.name venue_name FROM recommended_venue_promotions r JOIN venues v ON v.id=r.venue_id WHERE r.id=? AND r.owner_id=? FOR UPDATE");$stmt->execute([$service_id,$owner_id]);$promotion=$stmt->fetch();
            if(!$promotion || $promotion['status']!=='pending_payment' || abs($amount-1000)>0.01) throw new RuntimeException('This Recommended Venue order is invalid or has already been paid.');
            $promotionName = 'Recommended Venue';
            $venueName = $promotion['venue_name'];
            $db->prepare("UPDATE recommended_venue_promotions SET amount_npr=1000,payment_reference=?,status='pending_review' WHERE id=?")->execute([$reference,$service_id]);
        } elseif ($service_type === 'event_promotion') {
            $stmt=$db->prepare("SELECT e.*,v.name venue_name FROM event_promotions e JOIN venues v ON v.id=e.venue_id WHERE e.id=? AND e.owner_id=? FOR UPDATE");$stmt->execute([$service_id,$owner_id]);$promotion=$stmt->fetch();
            $durationSeconds=$promotion ? strtotime($promotion['promotion_expires_at'])-strtotime($promotion['promotion_starts_at']) : 0;
            if(!$promotion || $promotion['status']!=='pending_payment' || abs((float)$promotion['amount_npr']-EVENT_PROMOTION_PRICE_NPR)>0.01 || abs($amount-EVENT_PROMOTION_PRICE_NPR)>0.01 || $durationSeconds!==EVENT_PROMOTION_DURATION_DAYS*86400) throw new RuntimeException('This one-week Event Promotion order is invalid or has already been paid.');
            $banner=$db->prepare("SELECT COUNT(*) FROM promotion_hero_banners WHERE event_promotion_id=? AND image_url<>''");$banner->execute([$service_id]);
            if(!(int)$banner->fetchColumn()) throw new RuntimeException('A banner is required before Event Promotion payment.');
            $promotionName = $promotion['title'];
            $venueName = $promotion['venue_name'];
            $db->prepare("UPDATE event_promotions SET status='pending_review' WHERE id=?")->execute([$service_id]);
        } else { throw new RuntimeException('Invalid promotion type.'); }
        $duplicate=$db->prepare("SELECT COUNT(*) FROM promotion_payments WHERE service_type=? AND service_id=? AND status='paid'");
        $duplicate->execute([$service_type,$service_id]);
        if((int)$duplicate->fetchColumn()>0) throw new RuntimeException('This promotion has already been paid.');
        $db->prepare("INSERT INTO promotion_payments (tenant_id,owner_id,service_type,service_id,amount_npr,payment_method,provider_reference,status,paid_at) VALUES (?,?,?,?,?,'esewa',?,'paid',NOW())")
           ->execute([$owner_id,$owner_id,$service_type,$service_id,$amount,$reference]);
        logAudit('promotion_payment','Promotions',$service_type,$service_id,"Paid NPR $amount for $service_type");
        $db->commit();
        createNotification($owner_id,'owner',$owner_id,'Promotion Payment Received',"Payment for $promotionName at $venueName was received. Super Admin will review the paid order before it goes live.",'system');
        createNotification(null,'superadmin',$owner_id,'Paid Promotion Awaiting Action',"$promotionName for $venueName has been paid (reference $reference) and is ready for review.",'system');
        $ownerPage=$service_type==='event_promotion'?'event-promotion.php':'recommended-promotion.php';
        header('Location: '.APP_URL.'/owner/'.$ownerPage.'?msg=payment_complete'); exit;
    } catch(Throwable $e){if($db->inTransaction())$db->rollBack();die(htmlspecialchars($e->getMessage()));}
}

if ($subscription_upgrade && $plan_id) {
    requireOwner();
    $owner_id = (int)$_SESSION['owner_id'];
    $planStmt=$db->prepare("SELECT * FROM subscription_plans WHERE id=? AND slug='annual-venue' AND is_active=1");$planStmt->execute([$plan_id]);$plan=$planStmt->fetch();
    if(!$plan) die('Invalid annual subscription.');
    $amount=(float)($_POST['total_price']??0);if(abs($amount-(float)$plan['price_yearly'])>0.01)die('Invalid subscription amount.');
    $venueStmt=$db->prepare("SELECT id FROM venues WHERE owner_id=? ORDER BY id LIMIT 1");$venueStmt->execute([$owner_id]);$venueId=$venueStmt->fetchColumn()?:null;
    $current=$db->prepare("SELECT * FROM venue_subscriptions WHERE owner_id=? ORDER BY expires_at DESC LIMIT 1");$current->execute([$owner_id]);$existing=$current->fetch();
    $start=($existing&&$existing['status']==='active'&&strtotime($existing['expires_at'])>=strtotime(date('Y-m-d')))?$existing['expires_at']:date('Y-m-d');
    $expiry=date('Y-m-d',strtotime($start.' +1 year'));
    $reference='ESEWA-SUB-'.strtoupper(substr(uniqid(),-7));
    $db->beginTransaction();
    try{
      $db->prepare("UPDATE venue_subscriptions SET status='expired' WHERE owner_id=? AND status='active'")->execute([$owner_id]);
      $db->prepare("INSERT INTO venue_subscriptions (tenant_id,owner_id,venue_id,plan_id,amount_npr,starts_at,expires_at,status,payment_reference) VALUES (?,?,?,?,?,?,?,'active',?)")->execute([$owner_id,$owner_id,$venueId,$plan_id,$amount,$start,$expiry,$reference]);
      $subId=(int)$db->lastInsertId();
      $db->prepare("INSERT INTO promotion_payments (tenant_id,owner_id,service_type,service_id,amount_npr,payment_method,provider_reference,status,paid_at) VALUES (?,?, 'annual_subscription', ?,?,'esewa',?,'paid',NOW())")->execute([$owner_id,$owner_id,$subId,$amount,$reference]);
      $db->prepare("UPDATE venue_owners SET plan_id=? WHERE id=?")->execute([$plan_id,$owner_id]);
      logAudit('renew_subscription','Subscription','venue_subscription',$subId,"Annual subscription renewed through $expiry");$db->commit();
    }catch(Throwable $e){if($db->inTransaction())$db->rollBack();die('Subscription payment failed.');}
    createNotification($owner_id,'owner',$owner_id,'Annual Subscription Active',"Your MeroMaidan subscription is active through $expiry.",'system');

    header('Location: ' . APP_URL . '/owner/subscription.php?msg=renewed');
    exit;
}

// Process Venue Booking Payment
$venue_id      = (int)($_POST['venue_id'] ?? 0);
$booking_date  = $_POST['booking_date'] ?? '';
$start_time    = $_POST['start_time'] ?? '';
$end_time      = $_POST['end_time'] ?? '';
$total_price   = (float)($_POST['total_price'] ?? 0);
$customer_name = trim($_POST['customer_name'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');
$customer_email = trim($_POST['customer_email'] ?? '');
$booking_ref   = generateRef();
$esewa_phone   = trim($_POST['esewa_id'] ?? '9841111111');
$player_id     = $_SESSION['player_id'] ?? NULL;
$coupon_code   = strtoupper(trim($_POST['coupon_code'] ?? ''));
$event_promotion_id = (int)($_POST['event_promotion_id'] ?? 0);
$recommended_promotion_id = (int)($_POST['recommended_promotion_id'] ?? 0);

if (!$venue_id || !$booking_date || !$start_time || !$end_time || mb_strlen($customer_name) < 2) {
    die("Invalid request parameters.");
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $booking_date) || strtotime($booking_date) < strtotime(date('Y-m-d'))) {
    die('Choose today or a future booking date.');
}
if (!preg_match('/^(?:\+977[- ]?)?9[678]\d{8}$/', $customer_phone)) {
    die('Enter a valid Nepal mobile number.');
}
if ($customer_email !== '' && !filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
    die('Enter a valid email address.');
}

$dayOfWeek=(int)date('w',strtotime($booking_date));
$slotStmt=$db->prepare("SELECT vs.price,vs.end_time FROM venue_slots vs JOIN venues v ON v.id=vs.venue_id AND v.status='active' WHERE vs.venue_id=? AND vs.day_of_week=? AND vs.start_time=? AND vs.is_available=1 LIMIT 1");
$slotStmt->execute([$venue_id,$dayOfWeek,$start_time]);
$slot=$slotStmt->fetch();
if(!$slot) die('The selected slot is no longer available. Payment cancelled.');
if(substr((string)$slot['end_time'],0,5)!==substr($end_time,0,5)) die('The selected slot time is invalid. Payment cancelled.');
$basePrice=(float)$slot['price'];
try{$pricing=calculateBookingPrice($venue_id,(float)$basePrice,$coupon_code,$player_id,$customer_phone);}
catch(RuntimeException $e){die(htmlspecialchars($e->getMessage()).' Payment cancelled and the price was not changed.');}
if(abs($total_price-(float)$pricing['final_amount'])>0.01) die('The booking price changed. Please return to the venue and try again.');

$db->beginTransaction();
try {
    $mStmt = $db->prepare("SELECT id FROM maintenance_blocks WHERE venue_id=? AND block_date=? AND start_time<? AND end_time>? FOR UPDATE");
    $mStmt->execute([$venue_id,$booking_date,$end_time,$start_time]);
    if($mStmt->fetch()) throw new RuntimeException('Selected time slot is under maintenance. Payment cancelled.');
    $cStmt=$db->prepare("SELECT id FROM bookings WHERE venue_id=? AND booking_date=? AND start_time=? AND status IN ('confirmed','pending','checked_in','in_progress') FOR UPDATE");
    $cStmt->execute([$venue_id,$booking_date,$start_time]);
    if($cStmt->fetch()) throw new RuntimeException('Selected slot was reserved by another player seconds ago. Payment cancelled.');
    if($coupon_code!==''){
        $couponLock=$db->prepare("SELECT id FROM coupons WHERE code=? FOR UPDATE");$couponLock->execute([$coupon_code]);
        $pricing=calculateBookingPrice($venue_id,(float)$basePrice,$coupon_code,$player_id,$customer_phone);
        if(abs($total_price-(float)$pricing['final_amount'])>0.01) throw new RuntimeException('The coupon availability changed. Payment cancelled.');
    }

    $stmt=$db->prepare("INSERT INTO bookings (venue_id,player_id,customer_name,customer_phone,customer_email,booking_date,start_time,end_time,base_price,coupon_id,coupon_code,discount_amount,fees_amount,tax_amount,total_price,status,payment_method,booking_ref) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,'confirmed','esewa',?)");
    $stmt->execute([$venue_id,$player_id,$customer_name,$customer_phone,$customer_email,$booking_date,$start_time,$end_time,$pricing['base_price'],$pricing['coupon_id'],$pricing['coupon_code'],$pricing['discount_amount'],$pricing['fees_amount'],$pricing['tax_amount'],$pricing['final_amount'],$booking_ref]);
    $booking_id=(int)$db->lastInsertId();

    $vStmt=$db->prepare("SELECT name,owner_id FROM venues WHERE id=?");$vStmt->execute([$venue_id]);$venue=$vStmt->fetch();
    $venue_name=$venue['name']??'Venue';$owner_id=$venue['owner_id']??NULL;
    if($pricing['coupon_id']){
        $db->prepare("UPDATE coupons SET uses_count=uses_count+1 WHERE id=?")->execute([$pricing['coupon_id']]);
        $db->prepare("INSERT INTO coupon_usages (coupon_id,booking_id,player_id,tenant_id,original_amount,discount_amount,final_amount) VALUES (?,?,?,?,?,?,?)")->execute([$pricing['coupon_id'],$booking_id,$player_id,$owner_id,$pricing['base_price'],$pricing['discount_amount'],$pricing['final_amount']]);
    }
    if($event_promotion_id){$validEvent=$db->prepare("SELECT id FROM event_promotions WHERE id=? AND venue_id=? AND status='active' AND promotion_starts_at<=NOW() AND promotion_expires_at>=NOW()");$validEvent->execute([$event_promotion_id,$venue_id]);if($validEvent->fetchColumn()){$db->prepare("INSERT INTO promotion_analytics (tenant_id,promotion_type,promotion_id,event_type,player_id,booking_id,event_date) VALUES (?,'event_promotion',?,'booking',?,?,CURDATE())")->execute([$owner_id,$event_promotion_id,$player_id,$booking_id]);if($pricing['coupon_id'])$db->prepare("INSERT INTO promotion_analytics (tenant_id,promotion_type,promotion_id,event_type,player_id,booking_id,event_date) VALUES (?,'event_promotion',?,'coupon_use',?,?,CURDATE())")->execute([$owner_id,$event_promotion_id,$player_id,$booking_id]);}}
    if($recommended_promotion_id){$validRecommended=$db->prepare("SELECT id FROM recommended_venue_promotions WHERE id=? AND venue_id=? AND status='active' AND starts_at<=CURDATE() AND expires_at>=CURDATE()");$validRecommended->execute([$recommended_promotion_id,$venue_id]);if($validRecommended->fetchColumn())$db->prepare("INSERT INTO promotion_analytics (tenant_id,promotion_type,promotion_id,event_type,player_id,booking_id,event_date) VALUES (?,'recommended_venue',?,'booking',?,?,CURDATE())")->execute([$owner_id,$recommended_promotion_id,$player_id,$booking_id]);}

    $tx_code='ESEWA-MM-'.strtoupper(substr(bin2hex(random_bytes(8)),0,12));
    $db->prepare("INSERT INTO mock_esewa_transactions (booking_id,transaction_code,amount,esewa_phone,status) VALUES (?,?,?,?,'completed')")->execute([$booking_id,$tx_code,$pricing['final_amount'],$esewa_phone]);
    $inv_no="INV-".date('Y')."-".str_pad($booking_id,4,'0',STR_PAD_LEFT);
    $db->prepare("INSERT INTO invoices (booking_id,invoice_no,total_amount,tax_amount,discount_amount,net_amount,payment_method,status) VALUES (?,?,?,?,?,?,'esewa','paid')")->execute([$booking_id,$inv_no,$pricing['base_price'],$pricing['tax_amount'],$pricing['discount_amount'],$pricing['final_amount']]);
    $total_price=$pricing['final_amount'];
    $db->commit();
} catch(Throwable $e) {
    if($db->inTransaction())$db->rollBack();
    die(htmlspecialchars($e->getMessage()));
}

// 4. Audit & Notifications
logAudit('payment_success', 'booking', 'bookings', $booking_id, "Paid NPR $total_price via eSewa ($tx_code) for $venue_name.");
if ($player_id) {
    createNotification($player_id, 'player', NULL, "Booking Confirmed!", "Your booking ($booking_ref) for $venue_name on $booking_date ($start_time) is confirmed via eSewa.", 'booking');
}
if ($owner_id) {
    createNotification($owner_id, 'owner', $owner_id, "New eSewa Booking Received", "$customer_name booked $venue_name for NPR $total_price via eSewa.", 'payment');
}

// Redirect to printable invoice / confirmation
header("Location: " . APP_URL . "/esewa/invoice.php?booking_id=" . $booking_id . "&ref=" . rawurlencode($booking_ref));
exit;
