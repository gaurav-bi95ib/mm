<?php
// MeroMaidan - Dynamic Venue Search & Filter API
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

$db = getDB();

$sport    = trim($_GET['sport'] ?? '');
$location = trim($_GET['location'] ?? '');
$minPrice = (float)($_GET['min_price'] ?? 0);
$maxPrice = (float)($_GET['max_price'] ?? 10000);
$minRating = (float)($_GET['min_rating'] ?? 0);
$sortBy   = $_GET['sort_by'] ?? 'featured';

$sql = "SELECT v.*, vo.business_name 
        FROM venues v 
        LEFT JOIN venue_owners vo ON v.owner_id = vo.id 
        WHERE v.status = 'active'";
$params = [];

if (!empty($sport) && $sport !== 'All') {
    $sql .= " AND v.sport_type = :sport";
    $params[':sport'] = $sport;
}

if (!empty($location)) {
    $sql .= " AND (v.address LIKE :loc OR v.city LIKE :loc OR v.district LIKE :loc)";
    $params[':loc'] = '%' . $location . '%';
}

if ($minPrice > 0) {
    $sql .= " AND v.price_per_hour >= :minPrice";
    $params[':minPrice'] = $minPrice;
}

if ($maxPrice < 10000 && $maxPrice > 0) {
    $sql .= " AND v.price_per_hour <= :maxPrice";
    $params[':maxPrice'] = $maxPrice;
}

if ($minRating > 0) {
    $sql .= " AND v.rating >= :minRating";
    $params[':minRating'] = $minRating;
}

if ($sortBy === 'price_asc') {
    $sql .= " ORDER BY v.price_per_hour ASC";
} elseif ($sortBy === 'price_desc') {
    $sql .= " ORDER BY v.price_per_hour DESC";
} elseif ($sortBy === 'rating') {
    $sql .= " ORDER BY v.rating DESC";
} else {
    $sql .= " ORDER BY v.featured DESC, v.rating DESC, v.created_at DESC";
}

$stmt = $db->prepare($sql);
$stmt->execute($params);
$venues = $stmt->fetchAll();

foreach ($venues as &$v) {
    $v['amenities'] = json_decode($v['amenities'] ?? '[]', true);
    $v['images']    = json_decode($v['images'] ?? '[]', true);
}

jsonResponse([
    'status' => 'success',
    'count'  => count($venues),
    'venues' => $venues
]);
