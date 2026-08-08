<?php
// MeroMaidan - Official Printable Invoice & Payment Receipt
require_once __DIR__ . '/../api/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$booking_id = (int)($_GET['booking_id'] ?? 0);
if (!$booking_id) die("Invoice not found.");

$db = getDB();
$stmt = $db->prepare("SELECT b.*, v.name as venue_name, v.address as venue_address, v.sport_type, i.invoice_no, i.total_amount, i.net_amount, i.created_at as inv_date, tx.transaction_code, tx.esewa_phone 
                      FROM bookings b 
                      JOIN venues v ON b.venue_id = v.id 
                      LEFT JOIN invoices i ON i.booking_id = b.id 
                      LEFT JOIN mock_esewa_transactions tx ON tx.booking_id = b.id 
                      WHERE b.id = ?");
$stmt->execute([$booking_id]);
$invoice = $stmt->fetch();

if (!$invoice) die("Booking or invoice record missing.");
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Invoice <?= htmlspecialchars($invoice['invoice_no'] ?? 'MM-RECEIPT') ?> - MeroMaidan</title>
  <style>
    * { box-sizing: border-box; margin: 0; padding: 0; font-family: 'Inter', system-ui, sans-serif; }
    body { background: #f8fafc; color: #1e293b; padding: 40px 20px; display: flex; justify-content: center; }
    .invoice-card { background: white; border-radius: 12px; border: 1px solid #e2e8f0; box-shadow: 0 10px 30px rgba(0,0,0,0.05); width: 100%; max-width: 680px; padding: 40px; }
    .inv-header { display: flex; justify-content: space-between; border-bottom: 2px solid #f1f5f9; padding-bottom: 24px; margin-bottom: 24px; }
    .logo { font-size: 24px; font-weight: 800; color: #0f172a; text-decoration: none; }
    .logo span { color: #f97316; }
    .inv-title { text-align: right; }
    .inv-title h2 { font-size: 20px; color: #0f172a; text-transform: uppercase; letter-spacing: 1px; }
    .inv-title p { font-size: 13px; color: #64748b; margin-top: 4px; }
    .paid-stamp { display: inline-block; background: #dcfce7; color: #15803d; font-size: 12px; font-weight: 800; padding: 4px 12px; border-radius: 20px; text-transform: uppercase; margin-top: 6px; }
    .grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; margin-bottom: 32px; }
    .info-block h4 { font-size: 12px; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 6px; }
    .info-block p { font-size: 14px; color: #334155; font-weight: 500; line-height: 1.5; }
    .table { width: 100%; border-collapse: collapse; margin-bottom: 32px; }
    .table th { background: #f8fafc; text-align: left; padding: 12px 16px; font-size: 12px; color: #64748b; font-weight: 600; border-bottom: 1px solid #e2e8f0; }
    .table td { padding: 16px; font-size: 14px; border-bottom: 1px solid #f1f5f9; }
    .total-box { background: #f8fafc; border-radius: 8px; padding: 20px; text-align: right; }
    .total-box div { font-size: 14px; color: #64748b; margin-bottom: 6px; }
    .total-box h3 { font-size: 22px; color: #0f172a; font-weight: 800; }
    .actions { display: flex; gap: 12px; margin-top: 32px; }
    .btn { flex: 1; padding: 12px; border-radius: 8px; font-weight: 600; text-align: center; text-decoration: none; cursor: pointer; border: none; font-size: 14px; }
    .btn-primary { background: #f97316; color: white; }
    .btn-secondary { background: #e2e8f0; color: #334155; }
    @media print {
      body { background: white; padding: 0; }
      .invoice-card { border: none; box-shadow: none; width: 100%; max-width: 100%; }
      .actions { display: none; }
    }
  </style>
</head>
<body>

<div class="invoice-card">
  <div class="inv-header">
    <div>
      <a href="<?= APP_URL ?>" class="logo">Mero<span>Maidan</span></a>
      <p style="font-size: 13px; color: #64748b; margin-top: 6px;">Official Payment Receipt</p>
    </div>
    <div class="inv-title">
      <h2>Invoice</h2>
      <p><?= htmlspecialchars($invoice['invoice_no'] ?? 'INV-LOCAL') ?></p>
      <span class="paid-stamp">✓ <?= strtoupper($invoice['status'] ?? 'PAID') ?></span>
    </div>
  </div>

  <div class="grid-2">
    <div class="info-block">
      <h4>Billed To (Customer)</h4>
      <p><strong><?= htmlspecialchars($invoice['customer_name']) ?></strong></p>
      <p>Phone: <?= htmlspecialchars($invoice['customer_phone']) ?></p>
      <p>Email: <?= htmlspecialchars($invoice['customer_email'] ?? 'N/A') ?></p>
    </div>
    <div class="info-block" style="text-align: right;">
      <h4>Payment Details</h4>
      <p>Method: <strong><?= strtoupper($invoice['payment_method']) ?></strong></p>
      <?php if (!empty($invoice['transaction_code'])): ?>
        <p>Txn Ref: <code><?= htmlspecialchars($invoice['transaction_code']) ?></code></p>
        <p>eSewa ID: <?= htmlspecialchars($invoice['esewa_phone']) ?></p>
      <?php endif; ?>
      <p>Date: <?= date('d M Y, h:i A', strtotime($invoice['inv_date'] ?? $invoice['created_at'])) ?></p>
    </div>
  </div>

  <table class="table">
    <thead>
      <tr>
        <th>Description</th>
        <th>Booking Ref</th>
        <th>Date & Time</th>
        <th style="text-align: right;">Amount</th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td>
          <strong><?= htmlspecialchars($invoice['venue_name']) ?></strong><br>
          <small style="color: #64748b;"><?= htmlspecialchars($invoice['sport_type']) ?> · <?= htmlspecialchars($invoice['venue_address']) ?></small>
        </td>
        <td><code><?= htmlspecialchars($invoice['booking_ref']) ?></code></td>
        <td>
          <?= date('d M Y', strtotime($invoice['booking_date'])) ?><br>
          <small style="color: #64748b;"><?= date('h:i A', strtotime($invoice['start_time'])) ?> - <?= date('h:i A', strtotime($invoice['end_time'])) ?></small>
        </td>
        <td style="text-align: right;">NPR <?= number_format($invoice['total_price'], 2) ?></td>
      </tr>
    </tbody>
  </table>

  <div class="total-box">
    <div>Total Paid Amount</div>
    <h3>NPR <?= number_format($invoice['total_price'], 2) ?></h3>
  </div>

  <div class="actions">
    <button onclick="window.print()" class="btn btn-primary">🖨️ Print / Download PDF</button>
    <a href="<?= APP_URL ?>/player/bookings.php" class="btn btn-secondary">Return to My Bookings</a>
  </div>
</div>

</body>
</html>
