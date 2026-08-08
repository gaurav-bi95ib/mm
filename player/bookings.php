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

// Check if review already exists for each completed booking
$reviewedBookings = [];
$revStmt = $db->prepare("SELECT booking_id FROM reviews WHERE player_id = :pid");
$revStmt->execute([':pid' => $playerId]);
$reviewedBookings = $revStmt->fetchAll(PDO::FETCH_COLUMN);
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
        <p>View, cancel, or rate your sports venue reservations.</p>
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
                    <?php elseif ($b['status'] === 'completed'): ?>
                      <?php if (in_array($b['id'], $reviewedBookings)): ?>
                        <span style="font-size:12px;color:#16a34a;font-weight:700;">✓ Reviewed</span>
                      <?php else: ?>
                        <button class="btn-action primary" onclick="openReviewModal(<?= $b['id'] ?>, <?= $b['venue_id'] ?>, '<?= htmlspecialchars($b['venue_name'], ENT_QUOTES) ?>')">⭐ Rate</button>
                      <?php endif; ?>
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

<!-- Review Modal -->
<div class="modal-overlay" id="reviewModal">
  <div class="modal-card">
    <div class="modal-title">⭐ Leave a Review</div>
    <div class="modal-sub" id="reviewModalVenueName">Rate your experience at the ground</div>

    <form id="reviewForm">
      <input type="hidden" id="reviewBookingId">
      <input type="hidden" id="reviewVenueId">

      <div class="star-rating-input">
        <input type="radio" id="star5" name="rating" value="5" checked><label for="star5">★</label>
        <input type="radio" id="star4" name="rating" value="4"><label for="star4">★</label>
        <input type="radio" id="star3" name="rating" value="3"><label for="star3">★</label>
        <input type="radio" id="star2" name="rating" value="2"><label for="star2">★</label>
        <input type="radio" id="star1" name="rating" value="1"><label for="star1">★</label>
      </div>

      <div style="margin-bottom:16px;">
        <label style="display:block;font-size:12px;font-weight:700;color:#64748b;margin-bottom:6px;">Your Review (Optional)</label>
        <textarea id="reviewText" style="width:100%;height:90px;padding:10px;border:1px solid #cbd5e1;border-radius:8px;font-family:inherit;font-size:13px;" placeholder="Great turf, good lighting..."></textarea>
      </div>

      <div style="display:flex;gap:10px;justify-content:flex-end;">
        <button type="button" class="btn-action secondary" onclick="closeReviewModal()">Cancel</button>
        <button type="submit" class="btn-action primary">Submit Review</button>
      </div>
    </form>
  </div>
</div>

<script>
function cancelBooking(id) {
  if (!confirm('Are you sure you want to cancel this booking?')) return;
  fetch('../api/cancel_booking.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ booking_id: id })
  })
  .then(res => res.json())
  .then(data => {
    alert(data.message);
    if (data.status === 'success') location.reload();
  });
}

function openReviewModal(bId, vId, vName) {
  document.getElementById('reviewBookingId').value = bId;
  document.getElementById('reviewVenueId').value = vId;
  document.getElementById('reviewModalVenueName').textContent = 'Rate ' + vName;
  document.getElementById('reviewModal').classList.add('show');
}

function closeReviewModal() {
  document.getElementById('reviewModal').classList.remove('show');
}

document.getElementById('reviewForm').addEventListener('submit', function(e) {
  e.preventDefault();
  const bId = document.getElementById('reviewBookingId').value;
  const vId = document.getElementById('reviewVenueId').value;
  const rating = document.querySelector('input[name="rating"]:checked').value;
  const text = document.getElementById('reviewText').value;

  fetch('../api/reviews.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ booking_id: bId, venue_id: vId, rating: rating, review_text: text })
  })
  .then(res => res.json())
  .then(data => {
    alert(data.message);
    if (data.status === 'success') {
      closeReviewModal();
      location.reload();
    }
  });
});
</script>
</body>
</html>
