<?php
require_once __DIR__ . '/api/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();

$isPlayer = !empty($_SESSION['player_id']);
$isOwner = !empty($_SESSION['owner_id']);
$isAdmin = !empty($_SESSION['superadmin_id']);
$about = [];
$stats = ['venues' => 0, 'bookings' => 0, 'players' => 0, 'sports' => 0];

try {
    $db = getDB();
    $stmt = $db->prepare("SELECT * FROM cms_content WHERE page_slug='home' AND section_key='about_section' AND is_published=1 LIMIT 1");
    $stmt->execute();
    $about = $stmt->fetch() ?: [];
    $stats = $db->query("SELECT
        (SELECT COUNT(*) FROM venues WHERE status='active') AS venues,
        (SELECT COUNT(*) FROM bookings WHERE status<>'cancelled') AS bookings,
        (SELECT COUNT(*) FROM players WHERE status='active') AS players,
        (SELECT COUNT(DISTINCT sport_type) FROM venues WHERE status='active') AS sports")->fetch() ?: $stats;
} catch (Throwable $e) {
    // Keep the public page available with safe fallback content.
}

function aboutValue(array $content, string $field, string $fallback): string {
    $value = trim((string)($content[$field] ?? ''));
    return $value !== '' ? $value : $fallback;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About MeroMaidan — Built for Nepal's Sports Community</title>
  <meta name="description" content="Learn how MeroMaidan helps players discover sports venues and gives venue owners practical booking tools across Nepal.">
  <link rel="stylesheet" href="assets/css/style.css?v=20260810-recommended1">
  <link rel="stylesheet" href="assets/css/about.css?v=20260809-ui2">
</head>
<body class="about-page">
<div class="app-shell">
  <header class="site-header">
    <div class="header-left">
      <a href="index.php" class="logo-wrap" aria-label="MeroMaidan home">
        <svg class="logo-badge-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
          <rect width="100" height="100" rx="28" fill="#1BB955"/>
          <path fill-rule="evenodd" clip-rule="evenodd" d="M32 28H54C62 28 67 32 67 38C67 43 62 47 54 47H32V28ZM32 54H56C65 54 70 59 70 66C70 73 64 78 55 78H32V54Z" stroke="white" stroke-width="6"/>
          <circle cx="50" cy="50" r="10" fill="white"/><circle cx="50" cy="50" r="4" fill="#1BB955"/>
        </svg>
        <div class="logo-text">Mero<span>Maidan</span></div>
      </a>
      <nav class="desktop-nav" id="siteNav" aria-label="Primary navigation">
        <a href="index.php#services">Find Venues</a>
        <a href="index.php#how-it-works">How It Works</a>
        <a href="about.php" class="active" aria-current="page">About Us</a>
        <div class="mobile-nav-account">
          <?php if($isPlayer): ?><a href="player/index.php">My dashboard</a><a href="auth/logout.php">Sign out</a>
          <?php elseif($isOwner): ?><a href="owner/index.php">Owner panel</a><a href="auth/logout.php">Sign out</a>
          <?php elseif($isAdmin): ?><a href="superadmin/index.php">Admin panel</a><a href="auth/logout.php">Sign out</a>
          <?php else: ?><a href="auth/login.php">Player login</a><a href="auth/register.php">Create account</a><a href="list-ground.php">List your venue</a><?php endif; ?>
        </div>
      </nav>
    </div>
    <div class="header-right">
      <?php if ($isPlayer): ?>
        <a href="player/index.php" class="list-ground-btn account-primary">My Dashboard</a>
      <?php elseif ($isOwner): ?>
        <a href="owner/index.php" class="list-ground-btn account-primary navy">Owner Panel</a>
      <?php elseif ($isAdmin): ?>
        <a href="superadmin/index.php" class="list-ground-btn account-primary navy">Admin Panel</a>
      <?php else: ?>
        <a href="auth/login.php" class="about-login-link">Log In</a>
        <a href="auth/register.php" class="list-ground-btn account-primary">Sign Up</a>
      <?php endif; ?>
      <button class="nav-toggle-btn" id="navToggleBtn" type="button" aria-label="Open menu" aria-controls="siteNav" aria-expanded="false">☰</button>
    </div>
  </header>

  <main>
    <section class="about-hero">
      <div class="about-hero-glow glow-one"></div><div class="about-hero-glow glow-two"></div>
      <div class="about-hero-copy">
        <div class="about-eyebrow"><span></span> Our story & purpose</div>
        <h1><?=htmlspecialchars(aboutValue($about, 'title', 'A better way to play, book, and grow sport in Nepal.'))?></h1>
        <p class="about-hero-lead"><?=htmlspecialchars(aboutValue($about, 'subtitle', 'One trusted marketplace connecting players with quality venues.'))?></p>
        <p><?=nl2br(htmlspecialchars(aboutValue($about, 'content_text', "MeroMaidan makes sports venues easier to discover, compare, and book—while giving venue owners practical tools to manage their business.")))?></p>
        <div class="about-hero-actions"><a href="index.php#services">Explore venues <span>→</span></a><a href="list-ground.php" class="secondary">Partner with us</a></div>
      </div>
      <div class="about-purpose-card">
        <span class="purpose-mark">MM</span>
        <div><small>OUR PURPOSE</small><h2>Make every game easier to start.</h2><p>Less searching and uncertainty. More time on the ground with your team.</p></div>
        <div class="purpose-proof"><span>✓ Live availability</span><span>✓ Clear venue information</span><span>✓ Booking records</span></div>
      </div>
    </section>

    <section class="about-stats" aria-label="MeroMaidan platform activity">
      <article><strong><?=number_format((int)$stats['venues'])?></strong><span>Active venues</span></article>
      <article><strong><?=number_format((int)$stats['sports'])?></strong><span>Sports available</span></article>
      <article><strong><?=number_format((int)$stats['bookings'])?></strong><span>Bookings recorded</span></article>
      <article><strong><?=number_format((int)$stats['players'])?></strong><span>Registered players</span></article>
    </section>

    <section class="about-values">
      <div class="about-section-heading"><div class="section-kicker">What guides us</div><h2>Built around the way Nepal <span>actually plays.</span></h2><p>Simple choices, useful information, and a booking experience people can trust.</p></div>
      <div class="value-grid">
        <article><div class="value-icon">⌕</div><span>01</span><h3>Make discovery simple</h3><p>Bring sports, locations, facilities, prices, and available slots into one clear experience.</p></article>
        <article class="highlighted"><div class="value-icon">✓</div><span>02</span><h3>Build confidence</h3><p>Show clear venue details, facilities, rates, and availability before a booking is made.</p></article>
        <article><div class="value-icon">↗</div><span>03</span><h3>Help venues grow</h3><p>Give venue teams a practical place to manage listings, schedules, and customer bookings.</p></article>
      </div>
    </section>

    <section class="community-split">
      <article class="community-panel player-panel"><div class="panel-number">For players</div><h2>Find the right place for the next game.</h2><p>Compare venues without jumping between calls, social pages, and uncertain schedules.</p><ul><li>Search by sport and location</li><li>Compare facilities and rates</li><li>Book available slots in minutes</li></ul><a href="index.php#services">Find a venue →</a></article>
      <article class="community-panel owner-panel"><div class="panel-number">For venue owners</div><h2>Turn availability into more bookings.</h2><p>Present your venue professionally and keep the important daily details organised.</p><ul><li>Manage venue information</li><li>Control schedules and slots</li><li>Track bookings from one panel</li></ul><a href="list-ground.php">List your venue →</a></article>
    </section>

    <section class="about-final-cta"><div><div class="section-kicker light">Ready when you are</div><h2>Your next game already has a place.</h2><p>Discover a venue that fits your sport, location, and schedule.</p></div><a href="index.php#services">Browse venues <span>→</span></a></section>
  </main>

  <footer class="site-footer about-footer">
    <div class="footer-grid"><div><div class="footer-brand">Mero<span>Maidan</span></div><p class="footer-sub">A smarter way to discover and book sports venues across Nepal.</p></div><div><div class="footer-sec-title">Explore</div><ul class="footer-links-list"><li><a href="index.php#services">Find venues</a></li><li><a href="index.php#how-it-works">How it works</a></li><li><a href="about.php">About Us</a></li></ul></div><div><div class="footer-sec-title">Venue owners</div><ul class="footer-links-list"><li><a href="list-ground.php">List your venue</a></li><li><a href="auth/owner-login.php">Owner portal</a></li></ul></div><div><div class="footer-sec-title">Players</div><ul class="footer-links-list"><li><a href="auth/register.php">Create account</a></li><li><a href="auth/login.php">Player login</a></li></ul></div></div>
    <div class="footer-bottom"><p>© <?=date('Y')?> MeroMaidan · Built for Nepal’s sports community.</p></div>
  </footer>
</div>
<script>
(() => {
  const button = document.getElementById('navToggleBtn');
  const nav = document.getElementById('siteNav');
  if (!button || !nav) return;
  button.addEventListener('click', () => {
    const open = nav.classList.toggle('open');
    button.setAttribute('aria-expanded', String(open));
    button.textContent = open ? '×' : '☰';
  });
})();
</script>
</body>
</html>
