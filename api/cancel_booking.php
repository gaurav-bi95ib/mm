<?php
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'POST method required'], 405);
}

if (session_status() === PHP_SESSION_NONE) session_start();
if (empty($_SESSION['player_id'])) {
    jsonResponse(['status' => 'error', 'message' => 'Authentication required'], 401);
}

$playerId = $_SESSION['player_id'];
$data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$csrf = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verifyCsrfToken($csrf)) {
    jsonResponse(['status' => 'error', 'message' => 'Your session expired. Refresh and try again.'], 403);
}
$bookingId = (int)($data['booking_id'] ?? 0);

if (!$bookingId) {
    jsonResponse(['status' => 'error', 'message' => 'Booking ID required'], 400);
}

$db = getDB();

// Fetch booking & verify ownership
$stmt = $db->prepare("SELECT * FROM bookings WHERE id = :id AND player_id = :pid");
$stmt->execute([':id' => $bookingId, ':pid' => $playerId]);
$booking = $stmt->fetch();

if (!$booking) {
    jsonResponse(['status' => 'error', 'message' => 'Booking not found or unauthorized'], 404);
}

if (!in_array($booking['status'], ['pending','confirmed'], true)) {
    jsonResponse(['status' => 'error', 'message' => 'Only pending or confirmed bookings can be cancelled'], 400);
}

$window = $db->query("SELECT config_value FROM platform_commercial_config WHERE config_key='cancellation_window_hours' LIMIT 1")->fetchColumn();
$windowHours = in_array((int)$window, [2,6,24], true) ? (int)$window : 6;
$bookingAt = strtotime($booking['booking_date'].' '.$booking['start_time']);
if ($bookingAt <= time() + ($windowHours * 3600)) {
    jsonResponse(['status'=>'error','message'=>"Bookings can only be cancelled at least $windowHours hours before start time."],422);
}

// Update status to cancelled
$stmt = $db->prepare("UPDATE bookings SET status='cancelled' WHERE id=:id AND player_id=:pid AND status IN ('pending','confirmed')");
$stmt->execute([':id'=>$bookingId,':pid'=>$playerId]);

// Audit log
logAudit('cancel_booking', 'Booking', 'booking', $bookingId, "Player cancelled booking ref {$booking['booking_ref']}");

jsonResponse([
    'status'  => 'success',
    'message' => 'Booking cancelled successfully. Slot released.'
]);
