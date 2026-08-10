<?php
require_once __DIR__ . '/db.php';
requireOwner();
$eventId = (int)($_GET['event_id'] ?? 0);
$ownerId = (int)$_SESSION['owner_id'];
$stmt = getDB()->prepare("SELECT b.image_url FROM promotion_hero_banners b JOIN event_promotions e ON e.id=b.event_promotion_id WHERE e.id=? AND e.owner_id=? LIMIT 1");
$stmt->execute([$eventId,$ownerId]);
$path = $stmt->fetchColumn();
if(!$path){http_response_code(404);exit;}
if(preg_match('~^https?://~i',$path)){header('Location: '.$path, true, 302);exit;}
$file = realpath(__DIR__ . '/../' . ltrim($path,'/'));
$uploads = realpath(__DIR__ . '/../uploads/promotions');
if(!$file || !$uploads || !str_starts_with($file,$uploads) || !is_file($file)){http_response_code(404);exit;}
$mime = mime_content_type($file) ?: 'application/octet-stream';
if(!in_array($mime,['image/jpeg','image/png','image/webp'],true)){http_response_code(415);exit;}
header('Content-Type: '.$mime);
header('Content-Length: '.filesize($file));
readfile($file);
