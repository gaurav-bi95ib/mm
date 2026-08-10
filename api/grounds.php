<?php
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

$db = getDB();
syncPromotionStatuses();

$sport  = $_GET['sport']  ?? 'all';
$region = $_GET['region'] ?? 'all';
$lat    = isset($_GET['lat'])  ? (float)$_GET['lat']  : null;
$lng    = isset($_GET['lng'])  ? (float)$_GET['lng']  : null;
$search = trim($_GET['search'] ?? '');

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

$sql = "SELECT v.*, vo.business_name as owner_business,
        EXISTS(SELECT 1 FROM recommended_venue_promotions r WHERE {$paidRecommendationWhere}) AS is_recommended,
        (SELECT r.id FROM recommended_venue_promotions r WHERE {$paidRecommendationWhere} ORDER BY r.approved_at DESC LIMIT 1) AS recommended_promotion_id
        FROM venues v
        LEFT JOIN venue_owners vo ON v.owner_id = vo.id
        WHERE v.status = 'active'";
$params = [];

if ($sport !== 'all') {
    $sql .= " AND v.sport_type = :sport";
    $params[':sport'] = $sport;
}
if ($region !== 'all') {
    $sql .= " AND (v.district = :district_region OR v.city = :city_region)";
    $params[':district_region'] = $region;
    $params[':city_region'] = $region;
}
if ($search !== '') {
    $sql .= " AND (v.name LIKE :name_search OR v.address LIKE :address_search OR v.city LIKE :city_search)";
    $searchPattern = '%' . $search . '%';
    $params[':name_search'] = $searchPattern;
    $params[':address_search'] = $searchPattern;
    $params[':city_search'] = $searchPattern;
}

$sql .= " ORDER BY is_recommended DESC, v.name ASC";

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
    usort($venues, function (array $a, array $b): int {
        $recommendedOrder = (int)$b['is_recommended'] <=> (int)$a['is_recommended'];
        return $recommendedOrder !== 0 ? $recommendedOrder : ($a['distance_km'] <=> $b['distance_km']);
    });
}

jsonResponse([
    'status'  => 'success',
    'count'   => count($venues),
    'grounds' => $venues
]);
