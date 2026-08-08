<?php
require_once __DIR__ . '/../api/db.php';
requireOwner();
$db        = getDB();
$ownerId   = $_SESSION['owner_id'];
$ownerName = $_SESSION['owner_name'] ?? 'Owner';

// Fetch owner's venues
$venuesStmt = $db->prepare("SELECT id FROM venues WHERE owner_id = :oid");
$venuesStmt->execute([':oid' => $ownerId]);
$venueIds = array_column($venuesStmt->fetchAll(), 'id');

$customers = [];
if (!empty($venueIds)) {
    $inList = implode(',', $venueIds);
    $stmt = $db->query("
        SELECT customer_name, customer_phone, customer_email,
               COUNT(*) as total_bookings,
               SUM(CASE WHEN status='confirmed' THEN total_price ELSE 0 END) as total_spent,
               MAX(booking_date) as last_booking
        FROM bookings
        WHERE venue_id IN ($inList)
        GROUP BY customer_phone
        ORDER BY last_booking DESC
    ");
    $customers = $stmt->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Customer CRM – MeroMaidan Owner</title>
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
      <a href="slots.php" class="nav-link"><span class="icon">⏰</span> Manage Slots</a>
      <a href="customers.php" class="nav-link active"><span class="icon">👥</span> Customers (CRM)</a>
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
      <div class="topbar-title">Customer <span>CRM</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Customer Directory</h1>
        <p>Tenant-scoped view of players who have reserved your venues.</p>
      </div>

      <div class="data-card">
        <div class="data-card-header">
          <h3>👥 Registered & Direct Customers (<?= count($customers) ?>)</h3>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Customer Name</th>
              <th>Phone</th>
              <th>Email</th>
              <th>Total Bookings</th>
              <th>Total Spent</th>
              <th>Last Booking</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($customers as $c): ?>
              <tr>
                <td><strong><?= htmlspecialchars($c['customer_name']) ?></strong></td>
                <td><a href="tel:<?= htmlspecialchars($c['customer_phone']) ?>" style="color:#1BB955;font-weight:700;text-decoration:none;"><?= htmlspecialchars($c['customer_phone']) ?></a></td>
                <td><?= htmlspecialchars($c['customer_email'] ?: '-') ?></td>
                <td><span class="badge green"><?= $c['total_bookings'] ?> bookings</span></td>
                <td><strong>NPR <?= number_format($c['total_spent']) ?></strong></td>
                <td><?= date('M j, Y', strtotime($c['last_booking'])) ?></td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($customers)): ?>
              <tr><td colspan="6" style="text-align:center;color:#64748b;padding:32px;">No customer records available yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>
</body>
</html>
