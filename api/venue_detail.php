<?php
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

$db = getDB();
$slug = trim($_GET['slug'] ?? '');
$date = trim($_GET['date'] ?? date('Y-m-d'));

if (!$slug) {
    jsonResponse(['status' => 'error', 'message' => 'Venue slug required'], 400);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) || !strtotime($date)) {
    jsonResponse(['status' => 'error', 'message' => 'A valid booking date is required'], 422);
}

// Get venue
$stmt = $db->prepare("
    SELECT v.*, vo.name as owner_name, vo.phone as owner_phone, vo.business_name
    FROM venues v
    LEFT JOIN venue_owners vo ON v.owner_id = vo.id
    WHERE v.slug = :slug AND v.status = 'active'
");
$stmt->execute([':slug' => $slug]);
$venue = $stmt->fetch();

if (!$venue) {
    jsonResponse(['status' => 'error', 'message' => 'Venue not found'], 404);
}

$venue['amenities'] = json_decode($venue['amenities'] ?? '[]', true) ?? [];
$venue['images']    = json_decode($venue['images']    ?? '[]', true) ?? [];

// Day of week for date
$dayOfWeek = (int) date('w', strtotime($date)); // 0=Sun

// Get slots for this day, taking into account bookings AND maintenance blocks
$stmt = $db->prepare("
    SELECT vs.*, 
           CASE 
               WHEN b.id IS NOT NULL THEN 1 
               WHEN mb.id IS NOT NULL THEN 1 
               ELSE 0 
           END as is_booked
    FROM venue_slots vs
    LEFT JOIN bookings b ON (
        b.venue_id = vs.venue_id AND
        b.booking_date = :booking_date AND
        b.start_time = vs.start_time AND
        b.status IN ('confirmed','pending','checked_in','in_progress')
    )
    LEFT JOIN maintenance_blocks mb ON (
        mb.venue_id = vs.venue_id AND
        mb.block_date = :maintenance_date AND
        vs.start_time < mb.end_time AND
        vs.end_time > mb.start_time
    )
    WHERE vs.venue_id = :venue_id AND vs.day_of_week = :day AND vs.is_available = 1
    ORDER BY vs.start_time
");
$stmt->execute([
    ':booking_date'     => $date,
    ':maintenance_date' => $date,
    ':venue_id'         => $venue['id'],
    ':day'              => $dayOfWeek,
]);
$slots = $stmt->fetchAll();

jsonResponse([
    'status' => 'success',
    'venue'  => $venue,
    'slots'  => $slots,
    'date'   => $date
]);
