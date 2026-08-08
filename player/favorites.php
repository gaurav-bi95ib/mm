<?php
require_once __DIR__ . '/../api/db.php';
requirePlayer();
$db       = getDB();
$playerId = $_SESSION['player_id'];
$playerName = $_SESSION['player_name'] ?? 'Player';

$stmt = $db->prepare("
    SELECT v.*, pf.id as fav_id
    FROM player_favorites pf
    JOIN venues v ON pf.venue_id = v.id
    WHERE pf.player_id = :pid
    ORDER BY pf.created_at DESC
");
$stmt->execute([':pid' => $playerId]);
$favorites = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Saved Venues – MeroMaidan</title>
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
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> My Bookings</a>
      <a href="favorites.php" class="nav-link active"><span class="icon">❤️</span> Saved Venues</a>
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
      <div class="topbar-title">Saved <span>Venues</span></div>
      <div class="topbar-actions">
        <a href="../index.php" class="btn-book-new">⚽ Discover Venues</a>
      </div>
    </header>

    <div class="player-content">
      <div class="page-header">
        <h1>Your Favorite Grounds</h1>
        <p>Quick access to your preferred sports venues in Nepal.</p>
      </div>

      <?php if (!empty($favorites)): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill, minmax(280px, 1fr));gap:24px;">
          <?php foreach ($favorites as $v): ?>
            <div style="background:#fff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;box-shadow:0 4px 12px rgba(0,0,0,.04);display:flex;flex-direction:column;">
              <img src="<?= htmlspecialchars($v['cover_image'] ?? 'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=600&q=80') ?>" style="width:100%;height:160px;object-fit:cover;">
              <div style="padding:18px;flex:1;display:flex;flex-direction:column;justify-space-between;">
                <div>
                  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:6px;">
                    <span style="font-size:11px;font-weight:800;background:#e6f7ec;color:#16a34a;padding:2px 8px;border-radius:50px;"><?= htmlspecialchars($v['sport_type']) ?></span>
                    <span style="font-size:12px;font-weight:700;color:#f59e0b;">⭐ <?= $v['rating'] ?></span>
                  </div>
                  <h3 style="font-size:16px;font-weight:800;color:#0f2740;"><?= htmlspecialchars($v['name']) ?></h3>
                  <p style="font-size:12px;color:#64748b;margin:4px 0 12px;">📍 <?= htmlspecialchars($v['address']) ?></p>
                </div>

                <div style="display:flex;gap:8px;margin-top:12px;">
                  <a href="../venue.php?slug=<?= urlencode($v['slug']) ?>" class="btn-action primary" style="flex:1;justify-content:center;">Book Slot</a>
                  <button class="btn-action danger" onclick="removeFavorite(<?= $v['id'] ?>)">💔</button>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <div class="content-card" style="text-align:center;padding:56px 20px;">
          <div style="font-size:48px;margin-bottom:12px;">❤️</div>
          <h3 style="font-size:18px;font-weight:800;color:#0f2740;margin-bottom:6px;">No Saved Venues Yet</h3>
          <p style="color:#64748b;font-size:14px;margin-bottom:20px;">Save venues by clicking the heart button when browsing grounds.</p>
          <a href="../index.php" class="btn-action primary">Browse All Grounds</a>
        </div>
      <?php endif; ?>

    </div>
  </main>
</div>

<script>
function removeFavorite(venueId) {
  if (!confirm('Remove this venue from your favorites?')) return;
  fetch('../api/favorites.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ venue_id: venueId, action: 'remove' })
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
