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

if ($booking['status'] === 'cancelled') {
    jsonResponse(['status' => 'error', 'message' => 'Booking is already cancelled'], 400);
}

if ($booking['status'] === 'completed') {
    jsonResponse(['status' => 'error', 'message' => 'Completed bookings cannot be cancelled'], 400);
}

// Update status to cancelled
$stmt = $db->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = :id");
$stmt->execute([':id' => $bookingId]);

// Audit log
logAudit('cancel_booking', 'Booking', 'booking', $bookingId, "Player cancelled booking ref {$booking['booking_ref']}");

jsonResponse([
    'status'  => 'success',
    'message' => 'Booking cancelled successfully. Slot released.'
]);
