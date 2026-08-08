<?php
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'POST required'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;

$required = ['venue_id','customer_name','customer_phone','booking_date','start_time','end_time'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        jsonResponse(['status' => 'error', 'message' => "Field '$field' is required"], 400);
    }
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
    SELECT price FROM venue_slots
    WHERE venue_id = :vid AND day_of_week = :day AND start_time = :start
    LIMIT 1
");
$stmt->execute([':vid' => $data['venue_id'], ':day' => $dayOfWeek, ':start' => $data['start_time']]);
$slot = $stmt->fetch();
$price = $slot ? (float)$slot['price'] : (float)($data['total_price'] ?? 0);

// Coupon / Promotion discount validation
$couponCode = strtoupper(trim($data['coupon_code'] ?? ''));
if ($couponCode) {
    $pStmt = $db->prepare("
        SELECT * FROM promotions
        WHERE code = :code AND is_active = 1
        AND valid_from <= :date AND valid_to >= :date
        LIMIT 1
    ");
    $pStmt->execute([':code' => $couponCode, ':date' => $data['booking_date']]);
    $promo = $pStmt->fetch();

    if ($promo) {
        if ($promo['type'] === 'percentage') {
            $discount = ($price * (float)$promo['value']) / 100;
        } else {
            $discount = (float)$promo['value'];
        }
        $price = max(0, $price - $discount);
        // Increment uses
        $db->prepare("UPDATE promotions SET uses_count = uses_count + 1 WHERE id = :id")->execute([':id' => $promo['id']]);
    }
}

$ref = generateRef();

if (session_status() === PHP_SESSION_NONE) session_start();
$playerId = $_SESSION['player_id'] ?? ($data['player_id'] ?? null);

$stmt = $db->prepare("
    INSERT INTO bookings (venue_id, player_id, customer_name, customer_phone, customer_email,
                          booking_date, start_time, end_time, total_price,
                          status, payment_method, notes, booking_ref)
    VALUES (:vid, :pid, :name, :phone, :email, :date, :start, :end, :price,
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
    ':price' => $price,
    ':pay'   => $data['payment_method'] ?? 'cash',
    ':notes' => $data['notes'] ?? null,
    ':ref'   => $ref,
]);

$bookingId = $db->lastInsertId();

logAudit('create_booking', 'Booking', 'booking', $bookingId, "Booking created ref $ref for venue {$data['venue_id']}");

jsonResponse([
    'status'  => 'success',
    'message' => 'Booking confirmed!',
    'booking' => [
        'id'          => $bookingId,
        'ref'         => $ref,
        'total_price' => $price,
        'date'        => $data['booking_date'],
        'time'        => $data['start_time'] . ' - ' . $data['end_time'],
    ]
]);
