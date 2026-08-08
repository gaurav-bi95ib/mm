<?php
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'POST required'], 405);
}

$data = json_decode(file_get_contents('php://input'), true);
if (!$data) $data = $_POST;

$required = ['owner_name','business_name','email','phone','sport_type','venue_name','venue_address','city','district'];
foreach ($required as $field) {
    if (empty($data[$field])) {
        jsonResponse(['status' => 'error', 'message' => "Field '$field' is required"], 400);
    }
}

if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
    jsonResponse(['status' => 'error', 'message' => 'Invalid email address'], 400);
}

$db = getDB();

// Check duplicate email
$stmt = $db->prepare("SELECT id FROM owner_applications WHERE email = :email");
$stmt->execute([':email' => $data['email']]);
if ($stmt->fetch()) {
    jsonResponse(['status' => 'error', 'message' => 'An application with this email already exists'], 409);
}

$stmt = $db->prepare("
    INSERT INTO owner_applications 
    (owner_name, business_name, email, phone, sport_type, venue_name, venue_address, city, district,
     lat, lng, price_per_hour, open_time, close_time, amenities, plan_selected)
    VALUES (:owner_name, :business_name, :email, :phone, :sport_type, :venue_name, :venue_address,
            :city, :district, :lat, :lng, :price, :open_time, :close_time, :amenities, :plan)
");
$stmt->execute([
    ':owner_name'    => $data['owner_name'],
    ':business_name' => $data['business_name'],
    ':email'         => $data['email'],
    ':phone'         => $data['phone'],
    ':sport_type'    => $data['sport_type'],
    ':venue_name'    => $data['venue_name'],
    ':venue_address' => $data['venue_address'],
    ':city'          => $data['city'],
    ':district'      => $data['district'],
    ':lat'           => $data['lat'] ?? null,
    ':lng'           => $data['lng'] ?? null,
    ':price'         => $data['price_per_hour'] ?? null,
    ':open_time'     => $data['open_time'] ?? '06:00',
    ':close_time'    => $data['close_time'] ?? '22:00',
    ':amenities'     => json_encode($data['amenities'] ?? []),
    ':plan'          => $data['plan_selected'] ?? 'free',
]);

jsonResponse([
    'status'  => 'success',
    'message' => 'Application submitted! Our team will contact you within 24 hours.',
    'app_id'  => $db->lastInsertId()
]);
