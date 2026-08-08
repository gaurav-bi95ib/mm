<?php
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();
$db        = getDB();
$adminName = $_SESSION['superadmin_name'] ?? 'Admin';

$msg = '';

// Handle review moderation
if (isset($_GET['action'], $_GET['id'])) {
    $revId  = (int)$_GET['id'];
    $act    = $_GET['action'];
    $status = ($act === 'approve') ? 'approved' : 'hidden';

    $db->prepare("UPDATE reviews SET status = :st WHERE id = :id")->execute([':st' => $status, ':id' => $revId]);
    logAudit('moderate_review', 'SuperAdmin', 'review', $revId, "SuperAdmin set review #$revId status to $status");
    $msg = "✅ Review status set to $status.";
}

// Fetch all reviews
$reviews = $db->query("
    SELECT r.*, v.name as venue_name, p.name as player_name, p.email as player_email
    FROM reviews r
    JOIN venues v ON r.venue_id = v.id
    JOIN players p ON r.player_id = p.id
    ORDER BY r.created_at DESC
")->fetchAll();

$pendingCount = $db->query("SELECT COUNT(*) FROM venues WHERE status='pending'")->fetchColumn();
$appsCount    = $db->query("SELECT COUNT(*) FROM owner_applications WHERE status='new'")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Review Moderation – MeroMaidan Admin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">

  <aside class="admin-sidebar">
    <div class="sidebar-logo">
      <div>
        <div class="sidebar-logo-text">Mero<span>Maidan</span> <span class="sidebar-badge">ADMIN</span></div>
        <div style="font-size:10px;color:rgba(255,255,255,.4);margin-top:2px;">Super Admin Panel</div>
      </div>
    </div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Overview</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Dashboard</a>
      <a href="reports.php" class="nav-link"><span class="icon">📈</span> Reports</a>

      <div class="nav-section-label">Management</div>
      <a href="venues.php" class="nav-link"><span class="icon">🏟️</span> Venues <?php if($pendingCount>0): ?><span class="badge orange"><?=$pendingCount?></span><?php endif; ?></a>
      <a href="owners.php" class="nav-link"><span class="icon">👤</span> Owners</a>
      <a href="bookings.php" class="nav-link"><span class="icon">📅</span> Bookings</a>
      <a href="reviews.php" class="nav-link active"><span class="icon">⭐</span> Review Moderation</a>
      <a href="applications.php" class="nav-link"><span class="icon">📋</span> Applications <?php if($appsCount>0): ?><span class="badge orange"><?=$appsCount?></span><?php endif; ?></a>
      <a href="plans.php" class="nav-link"><span class="icon">⭐</span> Plans</a>

      <div class="nav-section-label">System Governance</div>
      <a href="audit.php" class="nav-link"><span class="icon">🛡️</span> Audit Logs</a>
      <a href="settings.php" class="nav-link"><span class="icon">⚙️</span> Settings</a>
      <a href="../index.php" class="nav-link" target="_blank"><span class="icon">🌐</span> View Site</a>
    </nav>
    <div class="sidebar-footer">
      <div class="admin-user-row">
        <div class="admin-avatar"><?= strtoupper(substr($adminName,0,2)) ?></div>
        <div class="admin-user-info">
          <div class="admin-user-name"><?= htmlspecialchars($adminName) ?></div>
          <div class="admin-user-role">Super Admin</div>
        </div>
      </div>
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">Review <span>Moderation Queue</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Content & Review Moderation</h1>
        <p>Ensure player feedback quality and policy compliance across the platform (FR-REV-002, SAD-06).</p>
      </div>

      <?php if ($msg): ?><div class="alert success" style="background:#f0fdf4;color:#16a34a;padding:12px;border-radius:8px;margin-bottom:16px;font-weight:700;"><?= $msg ?></div><?php endif; ?>

      <div class="data-card">
        <div class="data-card-header">
          <h3>⭐ Player Reviews (<?= count($reviews) ?>)</h3>
        </div>
        <table class="data-table">
          <thead>
            <tr>
              <th>Rating</th>
              <th>Venue</th>
              <th>Player</th>
              <th>Review Comment</th>
              <th>Submitted</th>
              <th>Status</th>
              <th>Moderation Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($reviews as $r): ?>
              <tr>
                <td><strong style="color:#f59e0b;font-size:15px;"><?= str_repeat('★', $r['rating']) ?></strong></td>
                <td><strong><?= htmlspecialchars($r['venue_name']) ?></strong></td>
                <td>
                  <strong><?= htmlspecialchars($r['player_name']) ?></strong><br>
                  <span style="font-size:11px;color:#64748b;"><?= htmlspecialchars($r['player_email']) ?></span>
                </td>
                <td style="max-width:280px;"><?= htmlspecialchars($r['review_text'] ?: '(Rating only)') ?></td>
                <td style="font-size:12px;color:#64748b;"><?= date('M j, Y', strtotime($r['created_at'])) ?></td>
                <td><span class="badge <?= $r['status']==='approved'?'active':'red' ?>"><?= ucfirst($r['status']) ?></span></td>
                <td>
                  <?php if ($r['status'] === 'approved'): ?>
                    <a href="?action=hide&id=<?= $r['id'] ?>" class="btn btn-red btn-sm">🙈 Hide Review</a>
                  <?php else: ?>
                    <a href="?action=approve&id=<?= $r['id'] ?>" class="btn btn-green btn-sm">✓ Approve</a>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            <?php if (empty($reviews)): ?>
              <tr><td colspan="7" style="text-align:center;padding:40px;color:#64748b;">No player reviews submitted yet.</td></tr>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </div>
  </main>
</div>
</body>
</html>
