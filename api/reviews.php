<?php
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $venueId = (int)($_GET['venue_id'] ?? 0);
    if (!$venueId) {
        jsonResponse(['status' => 'error', 'message' => 'Venue ID required'], 400);
    }

    $stmt = $db->prepare("
        SELECT r.*, p.name as player_name
        FROM reviews r
        JOIN players p ON r.player_id = p.id
        WHERE r.venue_id = :vid AND r.status = 'approved'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([':vid' => $venueId]);
    $reviews = $stmt->fetchAll();

    jsonResponse([
        'status'  => 'success',
        'reviews' => $reviews
    ]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['player_id'])) {
        jsonResponse(['status' => 'error', 'message' => 'Authentication required to post a review'], 401);
    }

    $playerId = $_SESSION['player_id'];
    $data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $bookingId  = (int)($data['booking_id'] ?? 0);
    $venueId    = (int)($data['venue_id'] ?? 0);
    $rating     = (int)($data['rating'] ?? 5);
    $reviewText = trim($data['review_text'] ?? '');

    if (!$bookingId || !$venueId) {
        jsonResponse(['status' => 'error', 'message' => 'Booking ID and Venue ID required'], 400);
    }

    if ($rating < 1 || $rating > 5) {
        jsonResponse(['status' => 'error', 'message' => 'Rating must be between 1 and 5'], 400);
    }

    // Verify completed booking exists for this player
    $stmt = $db->prepare("SELECT id FROM bookings WHERE id = :bid AND player_id = :pid AND venue_id = :vid AND status = 'completed'");
    $stmt->execute([':bid' => $bookingId, ':pid' => $playerId, ':vid' => $venueId]);
    if (!$stmt->fetch()) {
        jsonResponse(['status' => 'error', 'message' => 'Only completed bookings can be reviewed'], 400);
    }

    // Check if already reviewed
    $stmt = $db->prepare("SELECT id FROM reviews WHERE booking_id = :bid");
    $stmt->execute([':bid' => $bookingId]);
    if ($stmt->fetch()) {
        jsonResponse(['status' => 'error', 'message' => 'You have already submitted a review for this booking'], 400);
    }

    // Insert review
    $stmt = $db->prepare("INSERT INTO reviews (venue_id, player_id, booking_id, rating, review_text, status) VALUES (:vid, :pid, :bid, :rating, :text, 'approved')");
    $stmt->execute([':vid' => $venueId, ':pid' => $playerId, ':bid' => $bookingId, ':rating' => $rating, ':text' => $reviewText]);

    // Recalculate venue average rating
    $avgStmt = $db->prepare("SELECT AVG(rating) as avg_rating, COUNT(*) as cnt FROM reviews WHERE venue_id = :vid AND status = 'approved'");
    $avgStmt->execute([':vid' => $venueId]);
    $stats = $avgStmt->fetch();

    $newRating  = round((float)($stats['avg_rating'] ?? 5), 1);
    $totalRevs  = (int)($stats['cnt'] ?? 0);

    $db->prepare("UPDATE venues SET rating = :r, total_reviews = :c WHERE id = :vid")
       ->execute([':r' => $newRating, ':c' => $totalRevs, ':vid' => $venueId]);

    logAudit('submit_review', 'Reviews', 'venue', $venueId, "Player submitted $rating-star review");

    jsonResponse([
        'status'  => 'success',
        'message' => 'Thank you! Your review has been published.'
    ]);
}
