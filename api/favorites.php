<?php
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['status' => 'error', 'message' => 'POST required'], 405);
}
if (empty($_SESSION['player_id'])) {
    jsonResponse(['status' => 'error', 'message' => 'Please log in to save favorites'], 401);
}

$playerId = $_SESSION['player_id'];
$data     = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$csrf = $data['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!verifyCsrfToken($csrf)) {
    jsonResponse(['status' => 'error', 'message' => 'Your session expired. Refresh and try again.'], 403);
}
$venueId  = (int)($data['venue_id'] ?? 0);
$action   = $data['action'] ?? 'toggle';

if (!$venueId) {
    jsonResponse(['status' => 'error', 'message' => 'Venue ID required'], 400);
}

$db = getDB();

// Check if already favorited
$stmt = $db->prepare("SELECT id FROM player_favorites WHERE player_id = :pid AND venue_id = :vid");
$stmt->execute([':pid' => $playerId, ':vid' => $venueId]);
$fav = $stmt->fetch();

if ($action === 'remove' || ($action === 'toggle' && $fav)) {
    if ($fav) {
        $db->prepare("DELETE FROM player_favorites WHERE id = :id")->execute([':id' => $fav['id']]);
    }
    jsonResponse(['status' => 'success', 'is_favorite' => false, 'message' => 'Removed from favorites']);
} else {
    if (!$fav) {
        $db->prepare("INSERT INTO player_favorites (player_id, venue_id) VALUES (:pid, :vid)")
           ->execute([':pid' => $playerId, ':vid' => $venueId]);
    }
    jsonResponse(['status' => 'success', 'is_favorite' => true, 'message' => 'Saved to favorites!']);
}
