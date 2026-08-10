<?php
// MeroMaidan - Centralized MySQL DB Connection
// All other PHP files include this

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'meromaidan');
define('APP_NAME', 'MeroMaidan');
define('EVENT_PROMOTION_PRICE_NPR', 2000.0);
define('EVENT_PROMOTION_DURATION_DAYS', 7);
define('EVENT_BANNER_WIDTH', 1600);
define('EVENT_BANNER_HEIGHT', 600);
define('EVENT_BANNER_MAX_BYTES', 5 * 1024 * 1024);

// Keep PHP date/slot/coupon decisions aligned with the Nepal-based MySQL data.
date_default_timezone_set('Asia/Kathmandu');

// Secure browser sessions while still supporting local HTTP development.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', 'Lax');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
}
if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(self)');
}

// Dynamic APP_URL works on both Apache (/mm) and built-in server (localhost:8000)
if (!defined('APP_URL')) {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $projectRoot = realpath(dirname(__DIR__));
    $documentRoot = isset($_SERVER['DOCUMENT_ROOT']) ? realpath($_SERVER['DOCUMENT_ROOT']) : false;
    if ($projectRoot && $documentRoot && str_starts_with(strtolower($projectRoot), strtolower($documentRoot))) {
        $base = str_replace('\\', '/', substr($projectRoot, strlen($documentRoot)));
    } else {
        $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
        $base = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/\\');
    }
    $base = ($base === '' || $base === '/') ? '' : '/' . trim($base, '/');
    define('APP_URL', $scheme . '://' . $host . $base);
}

// Singleton PDO connection
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            error_log('MeroMaidan database connection failed: '.$e->getMessage());
            http_response_code(500);
            header('Content-Type: application/json');
            die(json_encode(['status' => 'error', 'message' => 'The service is temporarily unavailable. Please try again shortly.']));
        }
    }
    return $pdo;
}

// Helper: JSON response
function jsonResponse(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

// CSRF protection for authenticated browser forms and JSON requests.
function csrfToken(): string {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verifyCsrfToken(?string $token): bool {
    if (session_status() === PHP_SESSION_NONE) session_start();
    return !empty($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

// Helper: Generate booking ref
function generateRef(): string {
    return 'MM' . strtoupper(bin2hex(random_bytes(6)));
}

// Helper: Haversine distance (km)
function haversineDistance(float $lat1, float $lng1, float $lat2, float $lng2): float {
    $R = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat/2) * sin($dLat/2) +
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
         sin($dLng/2) * sin($dLng/2);
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    return round($R * $c, 1);
}

// CORS headers for API
function setCORSHeaders(): void {
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, X-CSRF-Token');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
}

// Auth: require superadmin session
function requireSuperAdmin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['superadmin_id'])) {
        $loginUrl = APP_URL . '/auth/admin-login.php';
        header('Location: ' . $loginUrl);
        exit;
    }
}

// Auth: require owner session
function requireOwner(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['owner_id'])) {
        $loginUrl = APP_URL . '/auth/owner-login.php';
        header('Location: ' . $loginUrl);
        exit;
    }
}

// Auth: require player session
function requirePlayer(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['player_id'])) {
        $loginUrl = APP_URL . '/auth/login.php?role=player';
        header('Location: ' . $loginUrl);
        exit;
    }
}

// Audit: log an action
function logAudit(string $action, string $module = '', string $resType = '', int $resId = 0, string $desc = ''): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    try {
        $db = getDB();
        $actorType = 'system';
        $actorId   = null;
        $actorName = 'System';
        $tenantId  = null;
        if (!empty($_SESSION['player_id'])) {
            $actorType = 'player'; $actorId = $_SESSION['player_id']; $actorName = $_SESSION['player_name'] ?? 'Player';
        } elseif (!empty($_SESSION['owner_id'])) {
            $actorType = 'owner'; $actorId = $_SESSION['owner_id']; $actorName = $_SESSION['owner_name'] ?? 'Owner'; $tenantId = $_SESSION['owner_id'];
        } elseif (!empty($_SESSION['superadmin_id'])) {
            $actorType = 'superadmin'; $actorId = $_SESSION['superadmin_id']; $actorName = $_SESSION['superadmin_name'] ?? 'Admin';
        }
        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $db->prepare("INSERT INTO audit_logs (actor_type,actor_id,actor_name,tenant_id,module,action,resource_type,resource_id,description,ip_address) VALUES (:at,:ai,:an,:tid,:mod,:act,:rt,:rid,:desc,:ip)");
        $stmt->execute([':at'=>$actorType,':ai'=>$actorId,':an'=>$actorName,':tid'=>$tenantId,':mod'=>$module,':act'=>$action,':rt'=>$resType,':rid'=>$resId?:null,':desc'=>$desc,':ip'=>$ip]);
    } catch (\Throwable $e) { /* audit failure must not break main flow */ }
}

// Notification: create a notification
function createNotification(?int $userId, string $role, ?int $tenantId, string $title, string $message, string $type = 'booking'): void {
    try {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO notifications (user_id, role, tenant_id, title, message, type) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->execute([$userId, $role, $tenantId, $title, $message, $type]);
    } catch (\Throwable $e) { /* silent fail */ }
}

// Apply scheduled starts and expiries whenever a marketplace/admin surface is opened.
function syncPromotionStatuses(): void {
    try {
        $db = getDB();
        $db->exec("UPDATE recommended_venue_promotions SET status='active' WHERE status='scheduled' AND starts_at<=CURDATE() AND expires_at>=CURDATE()");
        $db->exec("UPDATE event_promotions SET status='active' WHERE status='scheduled' AND promotion_starts_at<=NOW() AND promotion_expires_at>=NOW()");
        $db->exec("UPDATE recommended_venue_promotions SET status='expired' WHERE status IN ('active','scheduled') AND expires_at<CURDATE()");
        $db->exec("UPDATE event_promotions SET status='expired' WHERE status IN ('active','scheduled') AND promotion_expires_at<NOW()");
        $db->exec("UPDATE promotion_hero_banners b JOIN event_promotions e ON e.id=b.event_promotion_id SET b.is_published=0 WHERE e.status IN ('expired','rejected','suspended','cancelled')");
        $db->exec("UPDATE coupons c JOIN event_promotions e ON e.id=c.event_promotion_id SET c.status=IF(e.status='expired','expired',IF(e.status='suspended','suspended','cancelled')) WHERE e.status IN ('expired','rejected','suspended','cancelled') AND c.status IN ('draft','active')");
        $db->exec("UPDATE coupons SET status='expired' WHERE status='active' AND valid_to<NOW()");
    } catch (Throwable $e) {
        // Keep public pages available while migrations are being applied.
    }
}

// Annual subscription access and one-venue limit check.
function checkSubscriptionLimits(int $ownerId, string $resourceType = 'venues'): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT vo.plan_id, sp.max_venues, sp.max_bookings_per_month,
                              EXISTS(SELECT 1 FROM venue_subscriptions vs
                                WHERE vs.owner_id=vo.id AND vs.status='active'
                                  AND vs.starts_at<=CURDATE() AND vs.expires_at>=CURDATE()) AS has_active_subscription
                              FROM venue_owners vo 
                              LEFT JOIN subscription_plans sp ON vo.plan_id = sp.id 
                              WHERE vo.id = ?");
        $stmt->execute([$ownerId]);
        $plan = $stmt->fetch();
        if (!$plan || !(int)$plan['has_active_subscription']) return false;

        if ($resourceType === 'venues') {
            $countStmt = $db->prepare("SELECT COUNT(*) FROM venues WHERE owner_id = ?");
            $countStmt->execute([$ownerId]);
            $current = $countStmt->fetchColumn();
            return $current < $plan['max_venues'];
        } elseif ($resourceType === 'staff') {
            $countStmt = $db->prepare("SELECT COUNT(*) FROM tenant_staff WHERE owner_id = ?");
            $countStmt->execute([$ownerId]);
            $current = $countStmt->fetchColumn();
            // Operational staff is part of the single standard venue-management service.
            $maxStaff = 25;
            return $current < $maxStaff;
        }
        return true;
    } catch (\Throwable $e) {
        // Subscription enforcement must fail closed if configuration cannot be read.
        return false;
    }
}

/** Validate a venue/event coupon and return an authoritative price breakdown. */
function calculateBookingPrice(int $venueId, float $basePrice, string $couponCode = '', ?int $playerId = null, string $customerPhone = ''): array {
    $basePrice = max(0, round($basePrice, 2));
    $breakdown = ['base_price'=>$basePrice,'coupon_id'=>null,'coupon_code'=>null,'discount_amount'=>0.0,'fees_amount'=>0.0,'tax_amount'=>0.0,'final_amount'=>$basePrice];
    $couponCode = strtoupper(trim($couponCode));
    if ($couponCode === '') return $breakdown;

    $db = getDB();
    $stmt = $db->prepare("SELECT c.*,e.status event_status FROM coupons c LEFT JOIN event_promotions e ON e.id=c.event_promotion_id WHERE c.code=? LIMIT 1");
    $stmt->execute([$couponCode]); $coupon = $stmt->fetch();
    if (!$coupon) throw new RuntimeException('Coupon code was not found.');
    if ((int)$coupon['venue_id'] !== $venueId) throw new RuntimeException('This coupon is not valid for the selected venue.');
    if ($coupon['status'] !== 'active') throw new RuntimeException('This coupon is not active.');
    if (strtotime($coupon['valid_from']) > time()) throw new RuntimeException('This coupon is not active yet.');
    if (strtotime($coupon['valid_to']) < time()) throw new RuntimeException('This coupon has expired.');
    if ($coupon['event_promotion_id'] && !in_array($coupon['event_status'], ['active','scheduled'], true)) throw new RuntimeException('The related event promotion is not available.');
    if ($coupon['usage_limit'] !== null && (int)$coupon['uses_count'] >= (int)$coupon['usage_limit']) throw new RuntimeException('This coupon has reached its usage limit.');
    if ($basePrice < (float)$coupon['minimum_booking_amount']) throw new RuntimeException('The booking does not meet the coupon minimum of NPR '.number_format((float)$coupon['minimum_booking_amount']).'.');

    $usageSql = "SELECT COUNT(*) FROM coupon_usages cu JOIN bookings b ON b.id=cu.booking_id WHERE cu.coupon_id=? AND ";
    if ($playerId) { $usageSql .= 'cu.player_id=?'; $usageParams=[$coupon['id'],$playerId]; }
    else { $usageSql .= 'b.customer_phone=?'; $usageParams=[$coupon['id'],$customerPhone]; }
    $usageStmt=$db->prepare($usageSql);$usageStmt->execute($usageParams);
    if ((int)$usageStmt->fetchColumn() >= (int)$coupon['usage_limit_per_player']) throw new RuntimeException('You have already used this coupon the maximum allowed number of times.');

    $eligibility=json_decode($coupon['eligibility_json']??'{}',true)?:[];
    if (!empty($eligibility['first_booking_only'])) {
        $check=$playerId?$db->prepare("SELECT COUNT(*) FROM bookings WHERE player_id=? AND status<>'cancelled'"):$db->prepare("SELECT COUNT(*) FROM bookings WHERE customer_phone=? AND status<>'cancelled'");
        $check->execute([$playerId?:$customerPhone]); if((int)$check->fetchColumn()>0) throw new RuntimeException('This coupon is available for first bookings only.');
    }
    if (!empty($eligibility['minimum_completed_bookings']) && $playerId) {
        $check=$db->prepare("SELECT COUNT(*) FROM bookings WHERE player_id=? AND status='completed'");$check->execute([$playerId]);
        if((int)$check->fetchColumn()<(int)$eligibility['minimum_completed_bookings']) throw new RuntimeException('Your account does not yet meet this coupon eligibility rule.');
    }

    $discount=$coupon['discount_type']==='percentage'?$basePrice*((float)$coupon['discount_value']/100):(float)$coupon['discount_value'];
    if ($coupon['maximum_discount_amount'] !== null) $discount=min($discount,(float)$coupon['maximum_discount_amount']);
    $discount=min($basePrice,round($discount,2));
    $breakdown['coupon_id']=(int)$coupon['id'];$breakdown['coupon_code']=$couponCode;$breakdown['discount_amount']=$discount;
    $breakdown['final_amount']=max(0,round($basePrice-$discount+$breakdown['fees_amount']+$breakdown['tax_amount'],2));
    return $breakdown;
}
