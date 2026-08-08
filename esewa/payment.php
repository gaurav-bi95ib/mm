<?php
// MeroMaidan - Mock eSewa Payment Gateway Page
require_once __DIR__ . '/../api/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$venue_id     = (int)($_POST['venue_id'] ?? $_GET['venue_id'] ?? 0);
$booking_date = $_POST['booking_date'] ?? $_GET['booking_date'] ?? date('Y-m-d');
$start_time   = $_POST['start_time'] ?? $_GET['start_time'] ?? '18:00:00';
$end_time     = $_POST['end_time'] ?? $_GET['end_time'] ?? '19:00:00';
$coupon_code  = trim($_POST['coupon_code'] ?? $_GET['coupon_code'] ?? '');
$customer_name = $_POST['customer_name'] ?? $_GET['customer_name'] ?? ($_SESSION['player_name'] ?? 'Player');
$customer_phone = $_POST['customer_phone'] ?? $_GET['customer_phone'] ?? ($_SESSION['player_phone'] ?? '9841000000');
$customer_email = $_POST['customer_email'] ?? $_GET['customer_email'] ?? ($_SESSION['player_email'] ?? 'player@example.com');
$subscription_upgrade = (int)($_GET['subscription_upgrade'] ?? $_POST['subscription_upgrade'] ?? 0);
$plan_id = (int)($_GET['plan_id'] ?? $_POST['plan_id'] ?? 0);

$db = getDB();

if ($subscription_upgrade && $plan_id) {
    $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();
    if (!$plan) die("Invalid subscription plan");
    $title = "MeroMaidan " . $plan['name'] . " Subscription";
    $amount = $plan['price_monthly'];
    $ref = "SUB-" . strtoupper(substr(uniqid(), -6));
} else {
    $stmt = $db->prepare("SELECT * FROM venues WHERE id = ?");
    $stmt->execute([$venue_id]);
    $venue = $stmt->fetch();
    if (!$venue) die("Invalid venue selection.");
    $title = "Booking for " . htmlspecialchars($venue['name']);
    $amount = (float)$venue['price_per_hour'];
    
    // Apply promotion discount if valid
    if (!empty($coupon_code)) {
        $pStmt = $db->prepare("SELECT * FROM promotions WHERE code = ? AND is_active = 1 AND valid_from <= CURDATE() AND valid_to >= CURDATE()");
        $pStmt->execute([$coupon_code]);
        $promo = $pStmt->fetch();
        if ($promo) {
            if ($promo['type'] === 'percentage') {
                $discount = ($amount * $promo['value']) / 100;
            } else {
                $discount = $promo['value'];
            }
            $amount = max(0, $amount - $discount);
        }
    }
    $ref = generateRef();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>eSewa Payment Gateway - MeroMaidan</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; }
    body { background-color: #f4f6f8; display: flex; justify-content: center; align-items: center; min-height: 100vh; padding: 20px; }
    .esewa-card { background: #ffffff; border-radius: 12px; box-shadow: 0 10px 25px rgba(0,0,0,0.1); width: 100%; max-width: 440px; overflow: hidden; border: 1px solid #e1e4e8; }
    .esewa-header { background: #60bb46; color: #ffffff; padding: 24px; text-align: center; position: relative; }
    .esewa-logo { font-size: 28px; font-weight: 900; letter-spacing: -1px; text-transform: lowercase; }
    .esewa-logo span { color: #ffffff; background: #449230; padding: 2px 8px; border-radius: 4px; margin-left: 2px; }
    .esewa-sub { font-size: 13px; opacity: 0.9; margin-top: 4px; }
    .merchant-box { background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 16px 24px; display: flex; justify-content: space-between; align-items: center; }
    .merchant-info h4 { font-size: 14px; color: #64748b; font-weight: 500; }
    .merchant-info p { font-size: 16px; color: #1e293b; font-weight: 700; }
    .amount-tag { font-size: 20px; font-weight: 800; color: #60bb46; }
    .esewa-body { padding: 24px; }
    .form-group { margin-bottom: 16px; }
    .form-group label { display: block; font-size: 13px; color: #475569; font-weight: 600; margin-bottom: 6px; }
    .form-control { width: 100%; padding: 12px; font-size: 15px; border: 1px solid #cbd5e1; border-radius: 8px; transition: border 0.2s; }
    .form-control:focus { outline: none; border-color: #60bb46; box-shadow: 0 0 0 3px rgba(96,187,70,0.2); }
    .btn-esewa { width: 100%; background: #60bb46; color: white; border: none; padding: 14px; font-size: 16px; font-weight: 700; border-radius: 8px; cursor: pointer; transition: background 0.2s; }
    .btn-esewa:hover { background: #4e9c37; }
    .btn-cancel { display: block; text-align: center; margin-top: 12px; color: #94a3b8; text-decoration: none; font-size: 14px; font-weight: 500; }
    .btn-cancel:hover { color: #64748b; }
    .mock-badge { background: #fff3cd; color: #856404; font-size: 11px; padding: 6px 12px; text-align: center; border-radius: 20px; margin-bottom: 16px; font-weight: 600; display: inline-block; }
  </style>
</head>
<body>

<div class="esewa-card">
  <div class="esewa-header">
    <div class="esewa-logo">e<span>sewa</span></div>
    <div class="esewa-sub">Secure Payment Gateway</div>
  </div>

  <div class="merchant-box">
    <div class="merchant-info">
      <h4>Merchant</h4>
      <p>MeroMaidan Nepal</p>
    </div>
    <div class="amount-tag">
      NPR <?= number_format($amount, 2) ?>
    </div>
  </div>

  <div class="esewa-body">
    <div style="text-align: center;">
      <span class="mock-badge">TEST MODE - Mock Gateway Simulation</span>
    </div>

    <form action="process.php" method="POST">
      <input type="hidden" name="venue_id" value="<?= $venue_id ?>">
      <input type="hidden" name="booking_date" value="<?= htmlspecialchars($booking_date) ?>">
      <input type="hidden" name="start_time" value="<?= htmlspecialchars($start_time) ?>">
      <input type="hidden" name="end_time" value="<?= htmlspecialchars($end_time) ?>">
      <input type="hidden" name="total_price" value="<?= $amount ?>">
      <input type="hidden" name="customer_name" value="<?= htmlspecialchars($customer_name) ?>">
      <input type="hidden" name="customer_phone" value="<?= htmlspecialchars($customer_phone) ?>">
      <input type="hidden" name="customer_email" value="<?= htmlspecialchars($customer_email) ?>">
      <input type="hidden" name="booking_ref" value="<?= htmlspecialchars($ref) ?>">
      <input type="hidden" name="subscription_upgrade" value="<?= $subscription_upgrade ?>">
      <input type="hidden" name="plan_id" value="<?= $plan_id ?>">

      <div class="form-group">
        <label>eSewa ID (Mobile Number)</label>
        <input type="text" class="form-control" name="esewa_id" value="9841111111" required placeholder="98XXXXXXXX">
      </div>

      <div class="form-group">
        <label>Password / MPIN</label>
        <input type="password" class="form-control" name="esewa_pin" value="1234" required placeholder="••••">
      </div>

      <div class="form-group">
        <label>Transaction Purpose</label>
        <input type="text" class="form-control" readonly value="<?= htmlspecialchars($title) ?>">
      </div>

      <button type="submit" class="btn-esewa">Login & Pay NPR <?= number_format($amount, 2) ?></button>
      <a href="<?= APP_URL ?>" class="btn-cancel">Cancel and Return to MeroMaidan</a>
    </form>
  </div>
</div>

</body>
</html>
