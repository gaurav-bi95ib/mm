<?php
// Backward-compatible route: the two paid services now have separate Owner pages.
$service = $_GET['service'] ?? '';
$target = $service === 'event' ? 'event-promotion.php' : 'recommended-promotion.php';
$message = isset($_GET['msg']) ? '?msg=' . rawurlencode((string)$_GET['msg']) : '';
header('Location: ' . $target . $message, true, 302);
exit;
