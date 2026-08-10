<?php
require_once __DIR__ . '/../api/db.php';
requirePlayer();
$db       = getDB();
$playerId = $_SESSION['player_id'];
$playerName = $_SESSION['player_name'] ?? 'Player';

$filter = $_GET['status'] ?? 'all';
$sql = "
    SELECT b.*, v.name as venue_name, v.address, v.slug
    FROM bookings b
    JOIN venues v ON b.venue_id = v.id
    WHERE b.player_id = :pid
";
$params = [':pid' => $playerId];

if ($filter !== 'all') {
    $sql .= " AND b.status = :status";
    $params[':status'] = $filter;
}

$sql .= " ORDER BY b.booking_date DESC, b.start_time DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Bookings – MeroMaidan</title>
  <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/player.css">
</head>
<body>
<div class="player-layout">

  <!-- Sidebar -->
  <aside class="player-sidebar">
    <div class="sidebar-logo">
      <div class="logo-badge">
        <svg viewBox="0 0 30 30" fill="none">
          <path fill-rule="evenodd" clip-rule="evenodd" d="M8 8H15C18 8 20 9.5 20 12C20 14 18 15.5 15 15.5H8V8ZM8 18H16C20 18 22 20 22 23C22 26 19 27.5 15.5 27.5H8V18Z" stroke="white" stroke-width="2.2"/>
          <circle cx="15" cy="15" r="3.5" fill="white"/>
          <circle cx="15" cy="15" r="1.5" fill="#1BB955"/>
        </svg>
      </div>
      <div class="logo-title">Mero<span>Maidan</span></div>
    </div>

    <nav class="sidebar-nav">
      <div class="nav-section-label">Player Dashboard</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Overview</a>
      <a href="bookings.php" class="nav-link active"><span class="icon">📅</span> My Bookings</a>
      <a href="favorites.php" class="nav-link"><span class="icon">❤️</span> Saved Venues</a>
      <a href="profile.php" class="nav-link"><span class="icon">⚙️</span> Account Profile</a>
      
      <div class="nav-section-label">Explore</div>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🔍</span> Browse Venues</a>
    </nav>

    <div class="sidebar-footer">
      <div class="user-profile-summary">
        <div class="user-avatar"><?= strtoupper(substr($playerName, 0, 2)) ?></div>
        <div class="user-info">
          <div class="name"><?= htmlspecialchars($playerName) ?></div>
          <div class="role">Verified Player</div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-signout">🚪 Sign Out</a>
    </div>
  </aside>

  <!-- Main Content -->
  <main class="player-main">
    <header class="player-topbar">
      <div class="topbar-title">My <span>Bookings</span></div>
      <div class="topbar-actions">
        <a href="../index.php" class="btn-book-new">⚽ Book New Slot</a>
      </div>
    </header>

    <div class="player-content">
      <div class="page-header">
        <h1>Booking History</h1>
        <p>View, manage, or cancel your sports venue reservations.</p>
      </div>

      <!-- Filter Bar -->
      <div style="display:flex;gap:10px;margin-bottom:24px;flex-wrap:wrap;">
        <a href="?status=all" class="btn-action <?= $filter==='all'?'primary':'secondary' ?>">All</a>
        <a href="?status=confirmed" class="btn-action <?= $filter==='confirmed'?'primary':'secondary' ?>">Confirmed</a>
        <a href="?status=completed" class="btn-action <?= $filter==='completed'?'primary':'secondary' ?>">Completed</a>
        <a href="?status=cancelled" class="btn-action <?= $filter==='cancelled'?'primary':'secondary' ?>">Cancelled</a>
      </div>

      <!-- Bookings Table -->
      <div class="content-card">
        <?php if (!empty($bookings)): ?>
          <table class="custom-table">
            <thead>
              <tr>
                <th>Booking Ref</th>
                <th>Venue & Address</th>
                <th>Date</th>
                <th>Time Slot</th>
                <th>Payment</th>
                <th>Amount</th>
                <th>Status</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bookings as $b): ?>
                <tr>
                  <td><code style="background:#f1f5f9;padding:4px 8px;border-radius:6px;font-weight:700;"><?= htmlspecialchars($b['booking_ref']) ?></code></td>
                  <td>
                    <strong><?= htmlspecialchars($b['venue_name']) ?></strong><br>
                    <span style="font-size:11px;color:#64748b;">📍 <?= htmlspecialchars($b['address']) ?></span>
                  </td>
                  <td><strong><?= date('M j, Y', strtotime($b['booking_date'])) ?></strong></td>
                  <td><?= substr($b['start_time'], 0, 5) ?> – <?= substr($b['end_time'], 0, 5) ?></td>
                  <td><span style="text-transform:uppercase;font-size:11px;font-weight:700;"><?= htmlspecialchars($b['payment_method']) ?></span></td>
                  <td><strong>NPR <?= number_format($b['total_price']) ?></strong></td>
                  <td><span class="status-badge <?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                  <td>
                    <a href="../esewa/invoice.php?booking_id=<?= $b['id'] ?>" target="_blank" class="btn-action secondary" style="padding:4px 8px;font-size:11px;" title="View Invoice">🧾 Invoice</a>
                    <?php if (in_array($b['status'], ['confirmed', 'pending'])): ?>
                      <button class="btn-action danger" onclick="cancelBooking(<?= $b['id'] ?>)">✕ Cancel</button>
                    <?php else: ?>
                      <span style="font-size:12px;color:#94a3b8;">-</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div style="text-align:center;padding:48px;color:#64748b;">
            <div style="font-size:44px;margin-bottom:12px;">📅</div>
            <p style="font-size:15px;font-weight:600;">No bookings found matching filter.</p>
          </div>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>

<script>
function cancelBooking(id) {
  if (!confirm('Are you sure you want to cancel this booking?')) return;
  fetch('../api/cancel_booking.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ booking_id: id, csrf_token: '<?=csrfToken()?>' })
  })
  .then(res => res.json())
  .then(data => {
    alert(data.message);
    if (data.status === 'success') location.reload();
  });
}

</script>
</body>
</html>
