<?php
require_once __DIR__ . '/../api/db.php';
requireOwner();
$db        = getDB();
$ownerId   = $_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';

$msg = '';
$error = '';

// Get owner venues
$vStmt = $db->prepare("SELECT id, name FROM venues WHERE owner_id = :oid");
$vStmt->execute([':oid' => $ownerId]);
$myVenues = $vStmt->fetchAll();
$venueIds = array_column($myVenues, 'id');

// Handle coupon creation
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title     = trim($_POST['title'] ?? '');
    $code      = strtoupper(trim($_POST['code'] ?? ''));
    $type      = $_POST['type'] ?? 'percentage';
    $value     = (float)($_POST['value'] ?? 0);
    $minAmount = (float)($_POST['min_amount'] ?? 0);
    $validFrom = $_POST['valid_from'] ?? date('Y-m-d');
    $validTo   = $_POST['valid_to'] ?? date('Y-m-d', strtotime('+30 days'));
    $venueId   = !empty($_POST['venue_id']) ? (int)$_POST['venue_id'] : null;

    if (!$title || !$code || $value <= 0) {
        $error = 'Please provide a valid promotion title, coupon code, and discount value.';
    } else {
        try {
            $stmt = $db->prepare("
                INSERT INTO promotions (owner_id, venue_id, title, code, type, value, min_amount, valid_from, valid_to, is_active)
                VALUES (:oid, :vid, :title, :code, :type, :val, :min, :vf, :vt, 1)
            ");
            $stmt->execute([
                ':oid'   => $ownerId,
                ':vid'   => $venueId,
                ':title' => $title,
                ':code'  => $code,
                ':type'  => $type,
                ':val'   => $value,
                ':min'   => $minAmount,
                ':vf'    => $validFrom,
                ':vt'    => $validTo
            ]);
            logAudit('create_promotion', 'Promotions', 'promotion', $db->lastInsertId(), "Created promo code $code ($value $type)");
            $msg = '✅ Promotion code created successfully!';
        } catch (Exception $e) {
            $error = 'Error creating promotion code. Code might already exist.';
        }
    }
}

// Fetch active promotions for this owner
$promos = [];
if (!empty($ownerId)) {
    $stmt = $db->prepare("
        SELECT p.*, v.name as venue_name
        FROM promotions p
        LEFT JOIN venues v ON p.venue_id = v.id
        WHERE p.owner_id = :oid
        ORDER BY p.created_at DESC
    ");
    $stmt->execute([':oid' => $ownerId]);
    $promos = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Promotions & Coupons – MeroMaidan Owner</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">

  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <div>
        <div class="sidebar-logo-text">Mero<span>Maidan</span></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px;">Venue Owner Panel</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">My Dashboard</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Overview</a>
      <a href="venue.php" class="nav-link"><span class="icon">🏟️</span> My Venue</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="field_ops.php" class="nav-link"><span class="icon">📋</span> Field Ops & Check-in</a>
      <a href="slots.php" class="nav-link"><span class="icon">⏰</span> Manage Slots</a>
      <a href="maintenance.php" class="nav-link"><span class="icon">🚧</span> Maintenance Blocks</a>
      <a href="promotions.php" class="nav-link active"><span class="icon">🎟️</span> Promotions & Coupons</a>
      <a href="customers.php" class="nav-link"><span class="icon">👥</span> Customers (CRM)</a>
      <a href="reports.php" class="nav-link"><span class="icon">📈</span> Reports & Analytics</a>
      <a href="settings.php" class="nav-link"><span class="icon">⚙️</span> Business Settings</a>
      <div class="nav-section-label">Account</div>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?= strtoupper(substr($ownerName, 0, 2)) ?></div>
        <div class="admin-user-info">
          <div class="admin-user-name"><?= htmlspecialchars($ownerName) ?></div>
          <div class="admin-user-role">Venue Owner</div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">Promotions & <span>Coupons</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Discount Coupons & Offers</h1>
        <p>Create promotional codes to boost ground occupancy and repeat bookings (FR-PRO-001).</p>
      </div>

      <?php if ($msg): ?><div class="alert success" style="background:#f0fdf4;color:#16a34a;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;"><?= $msg ?></div><?php endif; ?>
      <?php if ($error): ?><div class="alert error" style="background:#fef2f2;color:#dc2626;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;"><?= $error ?></div><?php endif; ?>

      <div style="display:grid;grid-template-columns:360px 1fr;gap:24px;">

        <!-- Create Coupon Form -->
        <div class="data-card">
          <div class="data-card-header">
            <h3>🎟️ Create Promo Code</h3>
          </div>
          <form method="POST" style="padding:20px;">
            <div class="form-group" style="margin-bottom:14px;">
              <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">Offer Title</label>
              <input type="text" name="title" placeholder="e.g. Weekend Discount" class="form-input" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
            </div>

            <div class="form-group" style="margin-bottom:14px;">
              <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">Coupon Code</label>
              <input type="text" name="code" placeholder="e.g. MAIDAN10" class="form-input" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;text-transform:uppercase;font-weight:800;">
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
              <div>
                <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">Discount Type</label>
                <select name="type" class="form-input" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
                  <option value="percentage">Percentage (%)</option>
                  <option value="fixed">Fixed NPR</option>
                </select>
              </div>
              <div>
                <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">Value</label>
                <input type="number" step="0.5" name="value" placeholder="10" class="form-input" required style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
              </div>
            </div>

            <div class="form-group" style="margin-bottom:14px;">
              <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">Applicable Venue</label>
              <select name="venue_id" class="form-input" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
                <option value="">All My Venues</option>
                <?php foreach ($myVenues as $v): ?>
                  <option value="<?= $v['id'] ?>"><?= htmlspecialchars($v['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:20px;">
              <div>
                <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">Valid From</label>
                <input type="date" name="valid_from" value="<?= date('Y-m-d') ?>" class="form-input" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
              </div>
              <div>
                <label style="display:block;font-size:12px;font-weight:800;color:#64748b;margin-bottom:4px;">Valid To</label>
                <input type="date" name="valid_to" value="<?= date('Y-m-d', strtotime('+30 days')) ?>" class="form-input" style="width:100%;padding:10px;border:1px solid #cbd5e1;border-radius:8px;">
              </div>
            </div>

            <button type="submit" class="btn btn-green" style="width:100%;padding:12px;font-weight:800;">🚀 Launch Promotion</button>
          </form>
        </div>

        <!-- Active Promotions List -->
        <div class="data-card">
          <div class="data-card-header">
            <h3>📋 Active & Past Promotions</h3>
          </div>
          <table class="data-table">
            <thead>
              <tr>
                <th>Code</th>
                <th>Title</th>
                <th>Discount</th>
                <th>Venue</th>
                <th>Valid Until</th>
                <th>Uses</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($promos as $p): ?>
                <tr>
                  <td><code style="background:#e6f7ec;color:#16a34a;padding:4px 8px;border-radius:6px;font-weight:800;"><?= htmlspecialchars($p['code']) ?></code></td>
                  <td><strong><?= htmlspecialchars($p['title']) ?></strong></td>
                  <td>
                    <?php if ($p['type'] === 'percentage'): ?>
                      <span class="badge green"><?= $p['value'] ?>% OFF</span>
                    <?php else: ?>
                      <span class="badge green">NPR <?= number_format($p['value']) ?> OFF</span>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($p['venue_name'] ?: 'All Venues') ?></td>
                  <td style="font-size:12px;color:#64748b;"><?= date('M j, Y', strtotime($p['valid_to'])) ?></td>
                  <td><?= $p['uses_count'] ?> uses</td>
                  <td>
                    <?php if ($p['valid_to'] >= date('Y-m-d') && $p['is_active']): ?>
                      <span class="badge active">Active</span>
                    <?php else: ?>
                      <span class="badge red">Expired</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
              <?php if (empty($promos)): ?>
                <tr><td colspan="7" style="text-align:center;padding:40px;color:#64748b;">No promotion codes created yet. Use the form to create your first coupon offer!</td></tr>
              <?php endif; ?>
            </tbody>
          </table>
        </div>

      </div>

    </div>
  </main>
</div>
</body>
</html>
