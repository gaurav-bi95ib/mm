<?php
require_once __DIR__ . '/../api/db.php';
requirePlayer();
$db       = getDB();
$playerId = $_SESSION['player_id'];
$playerName = $_SESSION['player_name'] ?? 'Player';

// Fetch Player stats
$totalBookings = $db->query("SELECT COUNT(*) FROM bookings WHERE player_id = $playerId")->fetchColumn();
$upcomingBookingsCount = $db->query("SELECT COUNT(*) FROM bookings WHERE player_id = $playerId AND booking_date >= CURDATE() AND status IN ('confirmed','pending')")->fetchColumn();
$totalSpent = $db->query("SELECT COALESCE(SUM(total_price),0) FROM bookings WHERE player_id = $playerId AND status = 'confirmed'")->fetchColumn();
$favCount = $db->query("SELECT COUNT(*) FROM player_favorites WHERE player_id = $playerId")->fetchColumn();

// Upcoming bookings list
$upcomingStmt = $db->prepare("
    SELECT b.*, v.name as venue_name, v.address, v.cover_image, v.slug
    FROM bookings b
    JOIN venues v ON b.venue_id = v.id
    WHERE b.player_id = :pid AND b.booking_date >= CURDATE() AND b.status IN ('confirmed','pending')
    ORDER BY b.booking_date ASC, b.start_time ASC
    LIMIT 5
");
$upcomingStmt->execute([':pid' => $playerId]);
$upcomingBookings = $upcomingStmt->fetchAll();

// Favorites list
$favStmt = $db->prepare("
    SELECT v.*
    FROM player_favorites pf
    JOIN venues v ON pf.venue_id = v.id
    WHERE pf.player_id = :pid
    LIMIT 4
");
$favStmt->execute([':pid' => $playerId]);
$favorites = $favStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Player Dashboard – MeroMaidan</title>
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
      <a href="index.php" class="nav-link active"><span class="icon">📊</span> Overview</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> My Bookings</a>
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
      <div class="topbar-title">Dashboard <span>Overview</span></div>
      <div class="topbar-actions">
        <a href="../index.php" class="btn-book-new">⚽ Book New Slot</a>
      </div>
    </header>

    <div class="player-content">
      <div class="page-header">
        <h1>Welcome back, <?= htmlspecialchars(explode(' ', $playerName)[0]) ?>! 👋</h1>
        <p>Manage your upcoming sports matches and view booking history.</p>
      </div>

      <!-- Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon green">📅</div>
          <div class="stat-data">
            <div class="val"><?= $upcomingBookingsCount ?></div>
            <div class="lbl">Upcoming Matches</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon blue">⚡</div>
          <div class="stat-data">
            <div class="val"><?= $totalBookings ?></div>
            <div class="lbl">Total Bookings</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon amber">💰</div>
          <div class="stat-data">
            <div class="val">NPR <?= number_format($totalSpent) ?></div>
            <div class="lbl">Total Spent</div>
          </div>
        </div>

        <div class="stat-card">
          <div class="stat-icon purple">❤️</div>
          <div class="stat-data">
            <div class="val"><?= $favCount ?></div>
            <div class="lbl">Saved Venues</div>
          </div>
        </div>
      </div>

      <!-- Upcoming Bookings Section -->
      <div class="content-card">
        <div class="card-header">
          <h3>📅 Upcoming Bookings</h3>
          <a href="bookings.php" class="btn-action secondary">View All Bookings</a>
        </div>

        <?php if (!empty($upcomingBookings)): ?>
          <table class="custom-table">
            <thead>
              <tr>
                <th>Booking Ref</th>
                <th>Venue & Location</th>
                <th>Date</th>
                <th>Time Slot</th>
                <th>Price</th>
                <th>Status</th>
                <th>Action</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($upcomingBookings as $b): ?>
                <tr>
                  <td><code style="background:#f1f5f9;padding:4px 8px;border-radius:6px;font-weight:700;"><?= htmlspecialchars($b['booking_ref']) ?></code></td>
                  <td>
                    <strong><?= htmlspecialchars($b['venue_name']) ?></strong><br>
                    <span style="font-size:11px;color:#64748b;">📍 <?= htmlspecialchars($b['address']) ?></span>
                  </td>
                  <td><strong><?= date('M j, Y', strtotime($b['booking_date'])) ?></strong></td>
                  <td><?= substr($b['start_time'], 0, 5) ?> – <?= substr($b['end_time'], 0, 5) ?></td>
                  <td><strong>NPR <?= number_format($b['total_price']) ?></strong></td>
                  <td><span class="status-badge <?= $b['status'] ?>"><?= ucfirst($b['status']) ?></span></td>
                  <td>
                    <a href="../venue.php?slug=<?= urlencode($b['slug']) ?>" class="btn-action secondary" target="_blank">View Ground</a>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php else: ?>
          <div style="text-align:center;padding:40px;color:#64748b;">
            <div style="font-size:40px;margin-bottom:10px;">⚽</div>
            <p>No upcoming bookings scheduled.</p>
            <a href="../index.php" class="btn-action primary" style="margin-top:14px;">Find & Book Ground</a>
          </div>
        <?php endif; ?>
      </div>

      <!-- Favorite Venues Grid -->
      <div class="content-card">
        <div class="card-header">
          <h3>❤️ Saved Venues</h3>
          <a href="favorites.php" class="btn-action secondary">View All Favorites</a>
        </div>

        <?php if (!empty($favorites)): ?>
          <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:16px;">
            <?php foreach ($favorites as $v): ?>
              <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:12px;overflow:hidden;">
                <img src="<?= htmlspecialchars($v['cover_image'] ?? 'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=600&q=80') ?>" style="width:100%;height:120px;object-fit:cover;">
                <div style="padding:14px;">
                  <h4 style="font-size:14px;font-weight:800;color:#0f2740;"><?= htmlspecialchars($v['name']) ?></h4>
                  <div style="font-size:12px;color:#64748b;margin:4px 0;">📍 <?= htmlspecialchars($v['city']) ?></div>
                  <a href="../venue.php?slug=<?= urlencode($v['slug']) ?>" class="btn-action primary" style="width:100%;justify-content:center;margin-top:10px;">Book Now</a>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <p style="color:#64748b;font-size:13px;">You haven't saved any favorite venues yet. Browse grounds and click the heart icon to save them!</p>
        <?php endif; ?>
      </div>

    </div>
  </main>
</div>
</body>
</html>
