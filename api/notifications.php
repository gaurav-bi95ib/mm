<?php
// MeroMaidan - Notifications API Endpoint
require_once __DIR__ . '/db.php';
setCORSHeaders();
header('Content-Type: application/json');

if (session_status() === PHP_SESSION_NONE) session_start();

$db = getDB();
$userId   = null;
$role     = null;
$tenantId = null;

if (!empty($_SESSION['player_id'])) {
    $userId = $_SESSION['player_id'];
    $role   = 'player';
} elseif (!empty($_SESSION['owner_id'])) {
    $userId   = $_SESSION['owner_id'];
    $role     = 'owner';
    $tenantId = $_SESSION['owner_id'];
} elseif (!empty($_SESSION['superadmin_id'])) {
    $userId = $_SESSION['superadmin_id'];
    $role   = 'superadmin';
} else {
    jsonResponse(['status' => 'error', 'message' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? 'list';

if ($action === 'list') {
    if ($role === 'superadmin') {
        $stmt = $db->prepare("SELECT * FROM notifications WHERE role = 'superadmin' ORDER BY created_at DESC LIMIT 50");
        $stmt->execute();
    } elseif ($role === 'owner') {
        $stmt = $db->prepare("SELECT * FROM notifications WHERE ((user_id = ? AND role = 'owner') OR (tenant_id = ? AND role = 'owner')) ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$userId, $tenantId]);
    } else {
        $stmt = $db->prepare("SELECT * FROM notifications WHERE user_id = ? AND role = 'player' ORDER BY created_at DESC LIMIT 50");
        $stmt->execute([$userId]);
    }
    $notifications = $stmt->fetchAll();

    $unreadCount = 0;
    foreach ($notifications as $n) {
        if ($n['is_read'] == 0) $unreadCount++;
    }

    jsonResponse([
        'status' => 'success',
        'unread_count' => $unreadCount,
        'notifications' => $notifications
    ]);
} elseif ($action === 'mark_read') {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(['status' => 'error', 'message' => 'POST required'], 405);
    }
    $token = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!verifyCsrfToken($token)) {
        jsonResponse(['status' => 'error', 'message' => 'Invalid CSRF token'], 403);
    }
    $id = (int)($_POST['id'] ?? 0);
    if ($id > 0) {
        if ($role === 'superadmin') {
            $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND role='superadmin'");
            $stmt->execute([$id]);
        } elseif ($role === 'owner') {
            $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND role='owner' AND (user_id=? OR tenant_id=?)");
            $stmt->execute([$id,$userId,$tenantId]);
        } else {
            $stmt = $db->prepare("UPDATE notifications SET is_read=1 WHERE id=? AND role='player' AND user_id=?");
            $stmt->execute([$id,$userId]);
        }
    } else {
        // Mark all read
        if ($role === 'superadmin') {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE role = 'superadmin'");
            $stmt->execute();
        } elseif ($role === 'owner') {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE (user_id = ? OR tenant_id = ?) AND role = 'owner'");
            $stmt->execute([$userId, $tenantId]);
        } else {
            $stmt = $db->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ? AND role = 'player'");
            $stmt->execute([$userId]);
        }
    }
    jsonResponse(['status' => 'success', 'message' => 'Notifications updated']);
}
