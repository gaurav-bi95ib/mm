<?php
// MeroMaidan - SuperAdmin CMS & Platform Content Manager
require_once __DIR__ . '/../api/db.php';
requireSuperAdmin();

$db = getDB();
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $content = trim($_POST['content_text'] ?? '');
    $key = trim($_POST['section_key'] ?? '');

    if ($key && $title) {
        $stmt = $db->prepare("INSERT INTO cms_content (page_slug, section_key, title, content_text) VALUES ('home', ?, ?, ?) ON DUPLICATE KEY UPDATE title = VALUES(title), content_text = VALUES(content_text)");
        $stmt->execute([$key, $title, $content]);
        logAudit('update_cms', 'cms', 'cms_content', 0, "Updated CMS section: $key");
        $msg = '✅ Homepage CMS content updated successfully!';
    }
}

$stmt = $db->query("SELECT * FROM cms_content ORDER BY id ASC");
$cmsItems = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>CMS & Content Management - SuperAdmin</title>
  <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>
<div class="admin-layout">
  <aside class="admin-sidebar">
    <div class="sidebar-logo"><div><div class="sidebar-logo-text">Mero<span>Admin</span></div></div></div>
    <nav class="sidebar-nav">
      <div class="nav-section-label">Super Admin Panel</div>
      <a href="index.php" class="nav-link"><span class="icon">📊</span> Governance</a>
      <a href="applications.php" class="nav-link"><span class="icon">📋</span> Applications</a>
      <a href="owners.php" class="nav-link"><span class="icon">🏢</span> Tenants</a>
      <a href="plans.php" class="nav-link"><span class="icon">💳</span> SaaS Plans</a>
      <a href="cms.php" class="nav-link active"><span class="icon">📝</span> CMS & Content</a>
      <a href="audit.php" class="nav-link"><span class="icon">🛡️</span> Audit Logs</a>
      <a href="notifications.php" class="nav-link"><span class="icon">🔔</span> System Alerts</a>
    </nav>
    <div class="sidebar-footer">
      <a href="../auth/logout.php" class="btn-logout">🚪 Sign Out</a>
    </div>
  </aside>

  <main class="admin-main">
    <div class="admin-topbar">
      <div class="topbar-title">📝 Platform <span>Content Management (CMS)</span></div>
    </div>

    <div class="admin-content">
      <div class="page-header">
        <h1>Homepage CMS Content</h1>
        <p>Edit banner announcements, hero headers, and static copy shown on the marketplace.</p>
      </div>

      <?php if ($msg): ?><div style="background:#f0fdf4;border:1px solid #bbf7d0;padding:12px;border-radius:8px;color:#166534;margin-bottom:20px;font-weight:700;"><?=$msg?></div><?php endif; ?>

      <div style="display:grid;grid-template-columns:1fr 340px;gap:24px;">
        <div class="data-card">
          <div class="data-card-header">
            <h3>📑 Active CMS Sections</h3>
          </div>
          <table class="custom-table">
            <thead>
              <tr>
                <th>Section Key</th>
                <th>Title</th>
                <th>Content Text</th>
                <th>Last Updated</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($cmsItems as $item): ?>
                <tr>
                  <td><code><?= htmlspecialchars($item['section_key']) ?></code></td>
                  <td><strong><?= htmlspecialchars($item['title']) ?></strong></td>
                  <td><?= htmlspecialchars(substr($item['content_text'], 0, 60)) ?>...</td>
                  <td><?= date('d M Y, h:i A', strtotime($item['updated_at'])) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>

        <div class="data-card" style="padding:20px;">
          <h3>✏️ Edit / Add CMS Content</h3>
          <form method="POST" style="margin-top:16px;">
            <div class="form-group" style="margin-bottom:12px;">
              <label class="form-label">Section Key</label>
              <select name="section_key" class="form-input">
                <option value="hero_banner">hero_banner (Main Title)</option>
                <option value="announcement_bar">announcement_bar (Top Bar Banner)</option>
              </select>
            </div>

            <div class="form-group" style="margin-bottom:12px;">
              <label class="form-label">Headline / Title</label>
              <input type="text" name="title" class="form-input" required placeholder="Header Text">
            </div>

            <div class="form-group" style="margin-bottom:16px;">
              <label class="form-label">Content Description</label>
              <textarea name="content_text" class="form-input" style="min-height:100px;" required placeholder="Subheading / details..."></textarea>
            </div>

            <button type="submit" class="btn btn-green" style="width:100%;">💾 Save CMS Section</button>
          </form>
        </div>
      </div>
    </div>
  </main>
</div>
</body>
</html>
