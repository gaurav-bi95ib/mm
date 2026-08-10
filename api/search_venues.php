<?php
// MeroMaidan - Dynamic Venue Search & Filter API
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

$db = getDB();
syncPromotionStatuses();

$sport    = trim($_GET['sport'] ?? '');
$location = trim($_GET['location'] ?? '');
$minPrice = (float)($_GET['min_price'] ?? 0);
$maxPrice = (float)($_GET['max_price'] ?? 10000);
$sortBy   = $_GET['sort_by'] ?? 'name_asc';

$paidRecommendationWhere = "r.venue_id=v.id
        AND r.status='active'
        AND r.starts_at<=CURDATE()
        AND r.expires_at>=CURDATE()
        AND EXISTS (
            SELECT 1 FROM promotion_payments pp
            WHERE pp.service_type='recommended_venue'
              AND pp.service_id=r.id
              AND pp.tenant_id=r.tenant_id
              AND pp.owner_id=r.owner_id
              AND pp.status='paid'
              AND pp.payment_method='esewa'
              AND pp.amount_npr>=r.amount_npr
        )";

$sql = "SELECT v.*, vo.business_name,
        EXISTS(SELECT 1 FROM recommended_venue_promotions r WHERE {$paidRecommendationWhere}) AS is_recommended,
        (SELECT r.id FROM recommended_venue_promotions r WHERE {$paidRecommendationWhere} ORDER BY r.approved_at DESC LIMIT 1) AS recommended_promotion_id
        FROM venues v 
        LEFT JOIN venue_owners vo ON v.owner_id = vo.id 
        WHERE v.status = 'active'";
$params = [];

if (!empty($sport) && $sport !== 'All') {
    $sql .= " AND v.sport_type = :sport";
    $params[':sport'] = $sport;
}

if (!empty($location)) {
    $sql .= " AND (v.address LIKE :address_loc OR v.city LIKE :city_loc OR v.district LIKE :district_loc)";
    $locationPattern = '%' . $location . '%';
    $params[':address_loc'] = $locationPattern;
    $params[':city_loc'] = $locationPattern;
    $params[':district_loc'] = $locationPattern;
}

if ($minPrice > 0) {
    $sql .= " AND v.price_per_hour >= :minPrice";
    $params[':minPrice'] = $minPrice;
}

if ($maxPrice < 10000 && $maxPrice > 0) {
    $sql .= " AND v.price_per_hour <= :maxPrice";
    $params[':maxPrice'] = $maxPrice;
}

if ($sortBy === 'price_asc') {
    $sql .= " ORDER BY is_recommended DESC, v.price_per_hour ASC";
} elseif ($sortBy === 'price_desc') {
    $sql .= " ORDER BY is_recommended DESC, v.price_per_hour DESC";
} else {
    $sql .= " ORDER BY is_recommended DESC, v.name ASC";
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
