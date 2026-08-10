<?php
// MeroMaidan - Mock eSewa Payment Gateway Page
require_once __DIR__ . '/../api/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$venue_id     = (int)($_POST['venue_id'] ?? $_GET['venue_id'] ?? 0);
$booking_date = $_POST['booking_date'] ?? $_GET['booking_date'] ?? date('Y-m-d');
$start_time   = $_POST['start_time'] ?? $_GET['start_time'] ?? '18:00:00';
$end_time     = $_POST['end_time'] ?? $_GET['end_time'] ?? '19:00:00';
$coupon_code  = trim($_POST['coupon_code'] ?? $_GET['coupon_code'] ?? '');
$event_promotion_id = (int)($_POST['event_promotion_id'] ?? $_GET['event_promotion_id'] ?? 0);
$recommended_promotion_id = (int)($_POST['recommended_promotion_id'] ?? $_GET['recommended_promotion_id'] ?? 0);
$customer_name = $_POST['customer_name'] ?? $_GET['customer_name'] ?? ($_SESSION['player_name'] ?? 'Player');
$customer_phone = $_POST['customer_phone'] ?? $_GET['customer_phone'] ?? ($_SESSION['player_phone'] ?? '9841000000');
$customer_email = $_POST['customer_email'] ?? $_GET['customer_email'] ?? ($_SESSION['player_email'] ?? 'player@example.com');
$subscription_upgrade = (int)($_GET['subscription_upgrade'] ?? $_POST['subscription_upgrade'] ?? 0);
$plan_id = (int)($_GET['plan_id'] ?? $_POST['plan_id'] ?? 0);
$service_type = trim($_GET['service_type'] ?? $_POST['service_type'] ?? '');
$service_id = (int)($_GET['service_id'] ?? $_POST['service_id'] ?? 0);

$db = getDB();
$gatewaySetting = $db->query("SELECT config_value FROM platform_commercial_config WHERE config_key='mock_esewa_enabled' LIMIT 1")->fetchColumn();
if ($gatewaySetting !== false && (string)$gatewaySetting !== '1') {
    http_response_code(503);
    die('Mock eSewa payments are temporarily unavailable. Please return and choose cash or try again later.');
}
$pricing = ['base_price'=>0,'coupon_id'=>null,'coupon_code'=>null,'discount_amount'=>0,'fees_amount'=>0,'tax_amount'=>0,'final_amount'=>0];

if ($service_type && $service_id) {
    requireOwner();
    $ownerId = (int)$_SESSION['owner_id'];
    if ($service_type === 'recommended_venue') {
        $stmt = $db->prepare("SELECT r.*,v.name venue_name FROM recommended_venue_promotions r JOIN venues v ON v.id=r.venue_id WHERE r.id=? AND r.owner_id=?");
        $stmt->execute([$service_id,$ownerId]); $service = $stmt->fetch();
        if (!$service || $service['status'] !== 'pending_payment') die('This Recommended Venue order is invalid or has already been paid.');
        $title = 'Recommended Venue - '.$service['venue_name'].' (1 month)';
        $amount = 1000;
    } elseif ($service_type === 'event_promotion') {
        $stmt = $db->prepare("SELECT e.*,v.name venue_name FROM event_promotions e JOIN venues v ON v.id=e.venue_id WHERE e.id=? AND e.owner_id=?");
        $stmt->execute([$service_id,$ownerId]); $service = $stmt->fetch();
        if (!$service || $service['status'] !== 'pending_payment') die('This Event Promotion order is invalid or has already been paid.');
        if (abs((float)$service['amount_npr'] - EVENT_PROMOTION_PRICE_NPR) > 0.01) die('Invalid one-week Event Promotion price.');
        $title = 'Event Promotion - '.$service['title'];
        $amount = EVENT_PROMOTION_PRICE_NPR;
    } else { die('Invalid promotional service.'); }
    $ref = 'PROMO-'.strtoupper(substr(uniqid(),-7));
} elseif ($subscription_upgrade && $plan_id) {
    $stmt = $db->prepare("SELECT * FROM subscription_plans WHERE id = ?");
    $stmt->execute([$plan_id]);
    $plan = $stmt->fetch();
    if (!$plan) die("Invalid subscription plan");
    $title = "MeroMaidan " . $plan['name'] . " Subscription";
    $amount = $plan['price_yearly'];
    $ref = "SUB-" . strtoupper(substr(uniqid(), -6));
} else {
    $stmt = $db->prepare("SELECT * FROM venues WHERE id = ? AND status='active'");
    $stmt->execute([$venue_id]);
    $venue = $stmt->fetch();
    if (!$venue) die("Invalid venue selection.");
    $title = "Booking for " . $venue['name'];
    $dayOfWeek = (int)date('w', strtotime($booking_date));
    $slotStmt = $db->prepare("SELECT price,end_time FROM venue_slots WHERE venue_id=? AND day_of_week=? AND start_time=? AND is_available=1 LIMIT 1");
    $slotStmt->execute([$venue_id,$dayOfWeek,$start_time]);
    $slot = $slotStmt->fetch();
    if (!$slot || substr((string)$slot['end_time'],0,5) !== substr((string)$end_time,0,5)) die('The selected slot is invalid or no longer available.');
    $basePrice = (float)$slot['price'];
    try {
        $pricing = calculateBookingPrice($venue_id,$basePrice,$coupon_code,$_SESSION['player_id']??null,$customer_phone);
    } catch (RuntimeException $e) {
        die(htmlspecialchars($e->getMessage()).' The booking price was not changed.');
    }
    $amount = $pricing['final_amount'];
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
    .gateway-breakdown{padding:14px 24px;border-bottom:1px solid #e2e8f0;background:#fff}.gateway-breakdown div{display:flex;justify-content:space-between;padding:5px 0;color:#64748b;font-size:13px}.gateway-breakdown strong{color:#1e293b}.gateway-breakdown .discount,.gateway-breakdown .discount strong{color:#16823d}.gateway-breakdown .final{margin-top:4px;padding-top:9px;border-top:1px dashed #d8e1e8;font-weight:800}.gateway-breakdown .final strong{color:#60bb46;font-size:16px}
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

  <?php if ((float)$pricing['discount_amount'] > 0): ?>
  <div class="gateway-breakdown">
    <div><span>Booking subtotal</span><strong>NPR <?=number_format((float)$pricing['base_price'],2)?></strong></div>
    <div class="discount"><span>Coupon <?=htmlspecialchars((string)$pricing['coupon_code'])?></span><strong>- NPR <?=number_format((float)$pricing['discount_amount'],2)?></strong></div>
    <div class="final"><span>Final payable amount</span><strong>NPR <?=number_format((float)$pricing['final_amount'],2)?></strong></div>
  </div>
  <?php endif; ?>

  <div class="esewa-body">
    <div style="text-align: center;">
      <span class="mock-badge">TEST MODE - Mock Gateway Simulation</span>
    </div>

    <form action="process.php" method="POST">
      <input type="hidden" name="csrf_token" value="<?=csrfToken()?>">
      <input type="hidden" name="venue_id" value="<?= $venue_id ?>">
      <input type="hidden" name="booking_date" value="<?= htmlspecialchars($booking_date) ?>">
      <input type="hidden" name="start_time" value="<?= htmlspecialchars($start_time) ?>">
      <input type="hidden" name="end_time" value="<?= htmlspecialchars($end_time) ?>">
      <input type="hidden" name="total_price" value="<?= $amount ?>">
      <input type="hidden" name="coupon_code" value="<?= htmlspecialchars($pricing['coupon_code'] ?? '') ?>">
      <input type="hidden" name="event_promotion_id" value="<?= $event_promotion_id ?>">
      <input type="hidden" name="recommended_promotion_id" value="<?= $recommended_promotion_id ?>">
      <input type="hidden" name="customer_name" value="<?= htmlspecialchars($customer_name) ?>">
      <input type="hidden" name="customer_phone" value="<?= htmlspecialchars($customer_phone) ?>">
      <input type="hidden" name="customer_email" value="<?= htmlspecialchars($customer_email) ?>">
      <input type="hidden" name="booking_ref" value="<?= htmlspecialchars($ref) ?>">
      <input type="hidden" name="subscription_upgrade" value="<?= $subscription_upgrade ?>">
      <input type="hidden" name="plan_id" value="<?= $plan_id ?>">
      <input type="hidden" name="service_type" value="<?= htmlspecialchars($service_type) ?>">
      <input type="hidden" name="service_id" value="<?= $service_id ?>">

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
