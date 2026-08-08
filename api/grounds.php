<?php
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

$db = getDB();

$sport  = $_GET['sport']  ?? 'all';
$region = $_GET['region'] ?? 'all';
$lat    = isset($_GET['lat'])  ? (float)$_GET['lat']  : null;
$lng    = isset($_GET['lng'])  ? (float)$_GET['lng']  : null;
$search = trim($_GET['search'] ?? '');

$sql = "SELECT v.*, vo.business_name as owner_business
        FROM venues v
        LEFT JOIN venue_owners vo ON v.owner_id = vo.id
        WHERE v.status = 'active'";
$params = [];

if ($sport !== 'all') {
    $sql .= " AND v.sport_type = :sport";
    $params[':sport'] = $sport;
}
if ($region !== 'all') {
    $sql .= " AND (v.district = :region OR v.city = :region)";
    $params[':region'] = $region;
}
if ($search !== '') {
    $sql .= " AND (v.name LIKE :search OR v.address LIKE :search OR v.city LIKE :search)";
    $params[':search'] = '%' . $search . '%';
}

$sql .= " ORDER BY v.featured DESC, v.rating DESC";

$stmt = $db->prepare($sql);
$stmt->execute($params);
$venues = $stmt->fetchAll();

// Decode JSON fields and add distance if coords provided
foreach ($venues as &$v) {
    $v['amenities'] = json_decode($v['amenities'] ?? '[]', true) ?? [];
    $v['images']    = json_decode($v['images']    ?? '[]', true) ?? [];

    // Near Me distance calculation
    if ($lat !== null && $lng !== null && $v['lat'] && $v['lng']) {
        $v['distance_km'] = haversineDistance($lat, $lng, (float)$v['lat'], (float)$v['lng']);
    } else {
        $v['distance_km'] = null;
    }
}
unset($v);

// Sort by distance if Near Me
if ($lat !== null && $lng !== null) {
    usort($venues, fn($a, $b) => $a['distance_km'] <=> $b['distance_km']);
}

jsonResponse([
    'status'  => 'success',
    'count'   => count($venues),
    'grounds' => $venues
]);
