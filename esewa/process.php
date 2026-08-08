<?php
// MeroMaidan - Mock eSewa Payment Processing & Atomic Confirmation Callback
require_once __DIR__ . '/../api/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . APP_URL);
    exit;
}

$db = getDB();

$subscription_upgrade = (int)($_POST['subscription_upgrade'] ?? 0);
$plan_id = (int)($_POST['plan_id'] ?? 0);

if ($subscription_upgrade && $plan_id) {
    // Process Subscription Upgrade
    requireOwner();
    $owner_id = $_SESSION['owner_id'];
    
    $uStmt = $db->prepare("UPDATE venue_owners SET plan_id = ? WHERE id = ?");
    $uStmt->execute([$plan_id, $owner_id]);
    
    logAudit('upgrade_subscription', 'subscription', 'venue_owners', $owner_id, "Upgraded to plan ID $plan_id via eSewa.");
    createNotification($owner_id, 'owner', $owner_id, "Subscription Upgraded", "Your business subscription plan has been upgraded successfully!", "system");
    createNotification(NULL, 'superadmin', NULL, "Subscription Upgrade", "Owner ID $owner_id upgraded subscription plan.", "system");

    header('Location: ' . APP_URL . '/owner/subscription.php?msg=upgraded');
    exit;
}

// Process Venue Booking Payment
$venue_id      = (int)($_POST['venue_id'] ?? 0);
$booking_date  = $_POST['booking_date'] ?? '';
$start_time    = $_POST['start_time'] ?? '';
$end_time      = $_POST['end_time'] ?? '';
$total_price   = (float)($_POST['total_price'] ?? 0);
$customer_name = trim($_POST['customer_name'] ?? '');
$customer_phone = trim($_POST['customer_phone'] ?? '');
$customer_email = trim($_POST['customer_email'] ?? '');
$booking_ref   = trim($_POST['booking_ref'] ?? generateRef());
$esewa_phone   = trim($_POST['esewa_id'] ?? '9841111111');
$player_id     = $_SESSION['player_id'] ?? NULL;

if (!$venue_id || !$booking_date || !$start_time || !$end_time) {
    die("Invalid request parameters.");
}

// Concurrency check 1: Maintenance blocks
$mStmt = $db->prepare("SELECT id FROM maintenance_blocks WHERE venue_id = ? AND block_date = ? AND start_time < ? AND end_time > ?");
$mStmt->execute([$venue_id, $booking_date, $end_time, $start_time]);
if ($mStmt->fetch()) {
    die("Selected time slot is under maintenance. Payment cancelled.");
}

// Concurrency check 2: Existing active bookings
$cStmt = $db->prepare("SELECT id FROM bookings WHERE venue_id = ? AND booking_date = ? AND start_time = ? AND status IN ('confirmed','checked_in','in_progress')");
$cStmt->execute([$venue_id, $booking_date, $start_time]);
if ($cStmt->fetch()) {
    die("Selected slot was reserved by another player seconds ago. Payment cancelled.");
}

// 1. Insert Booking
$stmt = $db->prepare("INSERT INTO bookings (venue_id, player_id, customer_name, customer_phone, customer_email, booking_date, start_time, end_time, total_price, status, payment_method, booking_ref) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'confirmed', 'esewa', ?)");
$stmt->execute([$venue_id, $player_id, $customer_name, $customer_phone, $customer_email, $booking_date, $start_time, $end_time, $total_price, $booking_ref]);
$booking_id = $db->lastInsertId();

// Fetch venue details for notifications & invoice
$vStmt = $db->prepare("SELECT name, owner_id FROM venues WHERE id = ?");
$vStmt->execute([$venue_id]);
$venue = $vStmt->fetch();
$venue_name = $venue['name'] ?? 'Venue';
$owner_id   = $venue['owner_id'] ?? NULL;

// 2. Insert eSewa Mock Transaction Record
$tx_code = "ESEWA-MM-" . rand(10000, 99999);
$txStmt = $db->prepare("INSERT INTO mock_esewa_transactions (booking_id, transaction_code, amount, esewa_phone, status) VALUES (?, ?, ?, ?, 'completed')");
$txStmt->execute([$booking_id, $tx_code, $total_price, $esewa_phone]);

// 3. Generate Invoice
$inv_no = "INV-" . date('Y') . "-" . str_pad($booking_id, 4, '0', STR_PAD_LEFT);
$invStmt = $db->prepare("INSERT INTO invoices (booking_id, invoice_no, total_amount, tax_amount, discount_amount, net_amount, payment_method, status) VALUES (?, ?, ?, 0.00, 0.00, ?, 'esewa', 'paid')");
$invStmt->execute([$booking_id, $inv_no, $total_price, $total_price]);

// 4. Audit & Notifications
logAudit('payment_success', 'booking', 'bookings', $booking_id, "Paid NPR $total_price via eSewa ($tx_code) for $venue_name.");
if ($player_id) {
    createNotification($player_id, 'player', NULL, "Booking Confirmed!", "Your booking ($booking_ref) for $venue_name on $booking_date ($start_time) is confirmed via eSewa.", 'booking');
}
if ($owner_id) {
    createNotification($owner_id, 'owner', $owner_id, "New eSewa Booking Received", "$customer_name booked $venue_name for NPR $total_price via eSewa.", 'payment');
}

// Redirect to printable invoice / confirmation
header("Location: " . APP_URL . "/esewa/invoice.php?booking_id=" . $booking_id);
exit;
