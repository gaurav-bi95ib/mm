<?php
// MeroMaidan - Centralized MySQL DB Connection
// All other PHP files include this

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'meromaidan');
define('APP_NAME', 'MeroMaidan');

// Dynamic APP_URL works on both Apache (/mm) and built-in server (localhost:8000)
if (!defined('APP_URL')) {
    $scheme   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host     = $_SERVER['HTTP_HOST'] ?? 'localhost:8000';
    $scriptDir = dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    // If running via built-in server at root, scriptDir is '/'
    $base = ($scriptDir === '/' || $scriptDir === '\\') ? '' : rtrim($scriptDir, '/\\');
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
            http_response_code(500);
            header('Content-Type: application/json');
            die(json_encode(['status' => 'error', 'message' => 'Database connection failed: ' . $e->getMessage()]));
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

// Helper: Generate booking ref
function generateRef(): string {
    return 'MM' . strtoupper(substr(uniqid(), -8));
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
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);
}

// Auth: require superadmin session
function requireSuperAdmin(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['superadmin_id'])) {
        $loginUrl = APP_URL . '/auth/login.php?role=admin';
        header('Location: ' . $loginUrl);
        exit;
    }
}

// Auth: require owner session
function requireOwner(): void {
    if (session_status() === PHP_SESSION_NONE) session_start();
    if (empty($_SESSION['owner_id'])) {
        $loginUrl = APP_URL . '/auth/login.php?role=owner';
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

// SaaS Plan Limits Check
function checkSubscriptionLimits(int $ownerId, string $resourceType = 'venues'): bool {
    try {
        $db = getDB();
        $stmt = $db->prepare("SELECT vo.plan_id, sp.max_venues, sp.max_bookings_per_month 
                              FROM venue_owners vo 
                              LEFT JOIN subscription_plans sp ON vo.plan_id = sp.id 
                              WHERE vo.id = ?");
        $stmt->execute([$ownerId]);
        $plan = $stmt->fetch();
        if (!$plan) return true;

        if ($resourceType === 'venues') {
            $countStmt = $db->prepare("SELECT COUNT(*) FROM venues WHERE owner_id = ?");
            $countStmt->execute([$ownerId]);
            $current = $countStmt->fetchColumn();
            return $current < $plan['max_venues'];
        } elseif ($resourceType === 'staff') {
            $countStmt = $db->prepare("SELECT COUNT(*) FROM tenant_staff WHERE owner_id = ?");
            $countStmt->execute([$ownerId]);
            $current = $countStmt->fetchColumn();
            // Free: 1 staff, Standard: 5 staff, Premium: Unlimited
            $maxStaff = ($plan['plan_id'] == 1) ? 1 : (($plan['plan_id'] == 2) ? 5 : 999);
            return $current < $maxStaff;
        }
        return true;
    } catch (\Throwable $e) {
        return true;
    }
}

