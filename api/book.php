<?php
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'POST required'], 405);
}

if (session_status() === PHP_SESSION_NONE) session_start();

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;
if (!verifyCsrfToken($data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? ''))) {
    jsonResponse(['status'=>'error','message'=>'Your session expired. Refresh the page and try again.'],403);
}

$required = ['venue_id','customer_name','customer_phone','booking_date','start_time','end_time'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        jsonResponse(['status' => 'error', 'message' => "Field '$field' is required"], 400);
    }
}

$data['venue_id']=(int)$data['venue_id'];
$data['customer_name']=trim((string)$data['customer_name']);
$data['customer_phone']=trim((string)$data['customer_phone']);
if($data['venue_id']<1||mb_strlen($data['customer_name'])<2||!preg_match('/^(?:\+977[- ]?)?9[678]\d{8}$/',$data['customer_phone'])){
    jsonResponse(['status'=>'error','message'=>'Enter a valid venue, customer name and Nepal mobile number.'],422);
}
if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$data['booking_date'])||strtotime($data['booking_date'])<strtotime(date('Y-m-d'))){
    jsonResponse(['status'=>'error','message'=>'Choose today or a future booking date.'],422);
}
if(($data['payment_method']??'cash')!=='cash'){
    jsonResponse(['status'=>'error','message'=>'Online payments must continue through the secure mock gateway.'],422);
}

$db = getDB();

// Check slot not already booked
$stmt = $db->prepare("
    SELECT id FROM bookings
    WHERE venue_id = :vid AND booking_date = :date AND start_time = :start
    AND status IN ('confirmed','pending')
");
$stmt->execute([
    ':vid'   => $data['venue_id'],
    ':date'  => $data['booking_date'],
    ':start' => $data['start_time'],
]);
if ($stmt->fetch()) {
    jsonResponse(['status' => 'error', 'message' => 'This slot is already booked. Please choose another time.'], 409);
}

// Get price from slot
$dayOfWeek = (int) date('w', strtotime($data['booking_date']));
$stmt = $db->prepare("
    SELECT vs.price,vs.end_time FROM venue_slots vs
    JOIN venues v ON v.id=vs.venue_id AND v.status='active'
    WHERE vs.venue_id = :vid AND vs.day_of_week = :day AND vs.start_time = :start AND vs.is_available=1
    LIMIT 1
");
$stmt->execute([':vid' => $data['venue_id'], ':day' => $dayOfWeek, ':start' => $data['start_time']]);
$slot = $stmt->fetch();
if(!$slot) jsonResponse(['status'=>'error','message'=>'The selected slot is no longer available.'],422);
if(substr((string)$slot['end_time'],0,5)!==substr((string)$data['end_time'],0,5)) jsonResponse(['status'=>'error','message'=>'The selected slot time is invalid.'],422);
$basePrice = (float)$slot['price'];

$ref = generateRef();

$playerId = $_SESSION['player_id'] ?? null;
$couponCode = strtoupper(trim($data['coupon_code'] ?? ''));
try { $pricing=calculateBookingPrice((int)$data['venue_id'],$basePrice,$couponCode,$playerId,trim($data['customer_phone'])); }
catch(RuntimeException $e){ jsonResponse(['status'=>'error','message'=>$e->getMessage()],422); }

$db->beginTransaction();
try {
$maintenance=$db->prepare("SELECT id FROM maintenance_blocks WHERE venue_id=? AND block_date=? AND start_time<? AND end_time>? FOR UPDATE");
$maintenance->execute([$data['venue_id'],$data['booking_date'],$data['end_time'],$data['start_time']]);
if($maintenance->fetch())throw new RuntimeException('The selected slot is under maintenance.');
$conflict=$db->prepare("SELECT id FROM bookings WHERE venue_id=? AND booking_date=? AND start_time=? AND status IN ('confirmed','pending','checked_in','in_progress') FOR UPDATE");
$conflict->execute([$data['venue_id'],$data['booking_date'],$data['start_time']]);
if($conflict->fetch())throw new RuntimeException('This slot was just booked. Please choose another time.');
if($couponCode!==''){
  $couponLock=$db->prepare("SELECT id FROM coupons WHERE code=? FOR UPDATE");$couponLock->execute([$couponCode]);
  $pricing=calculateBookingPrice((int)$data['venue_id'],$basePrice,$couponCode,$playerId,trim($data['customer_phone']));
}
$stmt = $db->prepare("
    INSERT INTO bookings (venue_id, player_id, customer_name, customer_phone, customer_email,
                          booking_date, start_time, end_time, base_price,coupon_id,coupon_code,discount_amount,fees_amount,tax_amount,total_price,
                          status, payment_method, notes, booking_ref)
    VALUES (:vid, :pid, :name, :phone, :email, :date, :start, :end, :base,:coupon_id,:coupon_code,:discount,:fees,:tax,:price,
            'confirmed', :pay, :notes, :ref)
");
$stmt->execute([
    ':vid'   => $data['venue_id'],
    ':pid'   => $playerId,
    ':name'  => $data['customer_name'],
    ':phone' => $data['customer_phone'],
    ':email' => $data['customer_email'] ?? null,
    ':date'  => $data['booking_date'],
    ':start' => $data['start_time'],
    ':end'   => $data['end_time'],
    ':base' => $pricing['base_price'],
    ':coupon_id' => $pricing['coupon_id'],
    ':coupon_code' => $pricing['coupon_code'],
    ':discount' => $pricing['discount_amount'],
    ':fees' => $pricing['fees_amount'],
    ':tax' => $pricing['tax_amount'],
    ':price' => $pricing['final_amount'],
    ':pay'   => $data['payment_method'] ?? 'cash',
    ':notes' => $data['notes'] ?? null,
    ':ref'   => $ref,
]);

$bookingId = $db->lastInsertId();
$tenantStmt=$db->prepare("SELECT owner_id FROM venues WHERE id=?");$tenantStmt->execute([$data['venue_id']]);$tenantId=(int)$tenantStmt->fetchColumn();
if($pricing['coupon_id']){
  $db->prepare("UPDATE coupons SET uses_count=uses_count+1 WHERE id=?")->execute([$pricing['coupon_id']]);
  $db->prepare("INSERT INTO coupon_usages (coupon_id,booking_id,player_id,tenant_id,original_amount,discount_amount,final_amount) VALUES (?,?,?,?,?,?,?)")
     ->execute([$pricing['coupon_id'],$bookingId,$playerId,$tenantId,$pricing['base_price'],$pricing['discount_amount'],$pricing['final_amount']]);
}
$eventId=(int)($data['event_promotion_id']??0);if($eventId){$valid=$db->prepare("SELECT id FROM event_promotions WHERE id=? AND venue_id=? AND status='active' AND promotion_starts_at<=NOW() AND promotion_expires_at>=NOW()");$valid->execute([$eventId,$data['venue_id']]);if($valid->fetchColumn()){$db->prepare("INSERT INTO promotion_analytics (tenant_id,promotion_type,promotion_id,event_type,player_id,booking_id,event_date) VALUES (?,'event_promotion',?,'booking',?,?,CURDATE())")->execute([$tenantId,$eventId,$playerId,$bookingId]);if($pricing['coupon_id'])$db->prepare("INSERT INTO promotion_analytics (tenant_id,promotion_type,promotion_id,event_type,player_id,booking_id,event_date) VALUES (?,'event_promotion',?,'coupon_use',?,?,CURDATE())")->execute([$tenantId,$eventId,$playerId,$bookingId]);}}
$recommendedId=(int)($data['recommended_promotion_id']??0);if($recommendedId){$valid=$db->prepare("SELECT id FROM recommended_venue_promotions WHERE id=? AND venue_id=? AND status='active' AND starts_at<=CURDATE() AND expires_at>=CURDATE()");$valid->execute([$recommendedId,$data['venue_id']]);if($valid->fetchColumn())$db->prepare("INSERT INTO promotion_analytics (tenant_id,promotion_type,promotion_id,event_type,player_id,booking_id,event_date) VALUES (?,'recommended_venue',?,'booking',?,?,CURDATE())")->execute([$tenantId,$recommendedId,$playerId,$bookingId]);}
$db->commit();
} catch(RuntimeException $e) { if($db->inTransaction())$db->rollBack(); jsonResponse(['status'=>'error','message'=>$e->getMessage()],409); }
catch(Throwable $e) { if($db->inTransaction())$db->rollBack(); jsonResponse(['status'=>'error','message'=>'Unable to confirm booking. Please try again.'],500); }

logAudit('create_booking', 'Booking', 'booking', $bookingId, "Booking created ref $ref for venue {$data['venue_id']}");

jsonResponse([
    'status'  => 'success',
    'message' => 'Booking confirmed!',
    'booking' => [
        'id'          => $bookingId,
        'ref'         => $ref,
        'base_price'  => $pricing['base_price'],
        'discount_amount' => $pricing['discount_amount'],
        'fees_amount' => $pricing['fees_amount'],
        'tax_amount' => $pricing['tax_amount'],
        'total_price' => $pricing['final_amount'],
        'coupon_code' => $pricing['coupon_code'],
        'date'        => $data['booking_date'],
        'time'        => $data['start_time'] . ' - ' . $data['end_time'],
    ]
]);
