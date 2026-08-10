<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();

$eventId = (int)($_GET['event_id'] ?? 0);
if ($eventId < 1) {
    http_response_code(400);
    exit('Invalid event campaign.');
}

$db = getDB();
$stmt = $db->prepare("SELECT e.id,e.title,v.name venue_name,b.image_url FROM event_promotions e JOIN venues v ON v.id=e.venue_id JOIN promotion_hero_banners b ON b.event_promotion_id=e.id WHERE e.id=? LIMIT 1");
$stmt->execute([$eventId]);
$campaign = $stmt->fetch();
if (!$campaign || !$campaign['image_url']) {
    http_response_code(404);
    exit('Promotion banner not found.');
}

$safeBase = preg_replace('/[^a-z0-9-]+/i', '-', strtolower($campaign['venue_name'] . '-' . $campaign['title']));
$safeBase = trim($safeBase, '-') ?: 'event-promotion-banner';
$imagePath = (string)$campaign['image_url'];
$data = null;
$mime = null;
$extension = null;

if (preg_match('~^https?://~i', $imagePath)) {
    $parts = parse_url($imagePath);
    $host = $parts['host'] ?? '';
    $ip = $host ? gethostbyname($host) : '';
    $isPublicIp = $ip && $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE);
    if (!$isPublicIp || !function_exists('curl_init')) {
        http_response_code(400);
        exit('For security, this remote banner cannot be downloaded. Ask the owner to upload the banner file directly.');
    }

    $limit = 8 * 1024 * 1024;
    $buffer = '';
    $ch = curl_init($imagePath);
    curl_setopt_array($ch, [
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_PROTOCOLS => CURLPROTO_HTTP | CURLPROTO_HTTPS,
        CURLOPT_USERAGENT => 'MeroMaidan Banner Review/1.0',
        CURLOPT_WRITEFUNCTION => static function ($curl, string $chunk) use (&$buffer, $limit): int {
            if (strlen($buffer) + strlen($chunk) > $limit) return 0;
            $buffer .= $chunk;
            return strlen($chunk);
        },
    ]);
    $ok = curl_exec($ch);
    $status = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);
    if (!$ok || $status < 200 || $status >= 300 || $buffer === '') {
        http_response_code(502);
        exit('The remote banner could not be downloaded.');
    }
    $data = $buffer;
    $info = @getimagesizefromstring($data);
    $mime = $info['mime'] ?? null;
} else {
    $projectRoot = realpath(__DIR__ . '/..');
    $uploadRoot = realpath(__DIR__ . '/../uploads/promotions');
    $absolute = realpath($projectRoot . DIRECTORY_SEPARATOR . ltrim(str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $imagePath), DIRECTORY_SEPARATOR));
    if (!$uploadRoot || !$absolute || !str_starts_with($absolute, $uploadRoot . DIRECTORY_SEPARATOR) || !is_file($absolute)) {
        http_response_code(404);
        exit('Uploaded promotion banner not found.');
    }
    $data = file_get_contents($absolute);
    $info = @getimagesize($absolute);
    $mime = $info['mime'] ?? null;
}

$allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
if (!$data || !isset($allowed[$mime])) {
    http_response_code(415);
    exit('The stored banner is not a supported image.');
}
$extension = $allowed[$mime];
logAudit('download_banner', 'Promotions', 'event_promotion', $eventId, 'Downloaded event promotion banner for moderation');

header('Content-Type: ' . $mime);
header('Content-Length: ' . strlen($data));
header('Content-Disposition: attachment; filename="' . $safeBase . '.' . $extension . '"');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: private, no-store');
echo $data;
