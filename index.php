<?php
require_once __DIR__ . '/api/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$isPlayer = !empty($_SESSION['player_id']);
$isOwner  = !empty($_SESSION['owner_id']);
$isAdmin  = !empty($_SESSION['superadmin_id']);

$cms = [];
$heroSlides = [];
$eventSlides = [];
try {
    $db = getDB();
    syncPromotionStatuses();
    foreach ($db->query("SELECT * FROM cms_content WHERE page_slug='home' AND is_published=1 ORDER BY sort_order,id")->fetchAll() as $item) {
        $cms[$item['section_key']] = $item;
        if ($item['content_type'] === 'hero') {
            $item['hero_source'] = 'cms';
            $heroSlides[] = $item;
        }
    }
    $eventSlides = $db->query("SELECT e.id event_promotion_id,e.tenant_id,e.title,e.short_description,e.event_date,e.discount_label,e.cta_text,b.image_url,b.sort_order,v.name venue_name,v.slug,c.code coupon_code
        FROM event_promotions e JOIN promotion_hero_banners b ON b.event_promotion_id=e.id AND b.is_published=1
        JOIN venues v ON v.id=e.venue_id LEFT JOIN coupons c ON c.event_promotion_id=e.id AND c.status='active'
        WHERE e.status='active' AND e.promotion_starts_at<=NOW() AND e.promotion_expires_at>=NOW()
        ORDER BY b.sort_order,e.approved_at DESC")->fetchAll();
    foreach ($eventSlides as $event) {
        $heroSlides[] = [
            'title'=>$event['title'], 'subtitle'=>$event['discount_label'] ?: ('Event at '.$event['venue_name']),
            'content_text'=>$event['short_description'], 'image_url'=>$event['image_url'],
            'button_text'=>$event['cta_text'] ?: 'View Venue',
            'button_url'=>'venue.php?slug='.rawurlencode($event['slug']).'&event='.(int)$event['event_promotion_id'].($event['coupon_code']?'&coupon='.rawurlencode($event['coupon_code']):''),
            'event_promotion_id'=>$event['event_promotion_id'], 'event_date'=>$event['event_date'],
            'venue_name'=>$event['venue_name'], 'coupon_code'=>$event['coupon_code'],
            'sort_order'=>(int)$event['sort_order'],'hero_source'=>'event'
        ];
        $track=$db->prepare("INSERT INTO promotion_analytics (tenant_id,promotion_type,promotion_id,event_type,event_date,metadata_json) VALUES (?,'event_promotion',?,'impression',CURDATE(),?)");
        $track->execute([(int)$event['tenant_id'],(int)$event['event_promotion_id'],json_encode(['surface'=>'homepage_hero'])]);
    }
    usort($heroSlides, fn(array $a,array $b): int => ((int)($a['sort_order']??0) <=> (int)($b['sort_order']??0)) ?: strcmp(($a['hero_source']??'cms').'-'.($a['id']??$a['event_promotion_id']??0),($b['hero_source']??'cms').'-'.($b['id']??$b['event_promotion_id']??0)));
} catch (Throwable $e) { /* Keep public homepage available with safe defaults. */ }
function cmsValue(array $cms, string $key, string $field, string $fallback): string {
    $value = trim((string)($cms[$key][$field] ?? ''));
    return $value !== '' ? $value : $fallback;
}
if (!$heroSlides) {
    $heroSlides[] = ['title'=>'Find & book sports grounds in Nepal','subtitle'=>'Your next game starts here.','content_text'=>'Discover trusted venues, compare live slots, and book in minutes.','image_url'=>'','button_text'=>'Find a ground','button_url'=>'#services'];
}
$fallbackHeroImages = [
    'https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=1600&q=85',
    'https://images.unsplash.com/photo-1529900748604-07564a03e7a6?auto=format&fit=crop&w=1600&q=85',
    'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1600&q=85',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="csrf-token" content="<?= htmlspecialchars(csrfToken(), ENT_QUOTES) ?>">
  <title>MeroMaidan - Nepal's Smart Sports Venue Booking Platform</title>
  <meta name="description" content="MeroMaidan makes sports simple — players, teams & academies instantly connect with top sports venues across Nepal.">
  <link rel="stylesheet" href="assets/css/style.css?v=20260810-recommended1">
</head>
<body>

<div class="app-shell">
  <!-- Header Navigation -->
  <header class="site-header">
    <div class="header-left">
      <a href="index.php" class="logo-wrap" aria-label="MeroMaidan home">
        <svg class="logo-badge-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
          <rect width="100" height="100" rx="28" fill="#1BB955"/>
          <path d="M25 20H55C68 20 75 27 75 37C75 44 70 50 62 52C72 55 78 62 78 72C78 83 69 90 53 90H25V20Z" fill="#1BB955"/>
          <path fill-rule="evenodd" clip-rule="evenodd" d="M32 28H54C62 28 67 32 67 38C67 43 62 47 54 47H32V28ZM32 54H56C65 54 70 59 70 66C70 73 64 78 55 78H32V54Z" stroke="white" stroke-width="6"/>
          <circle cx="50" cy="50" r="10" fill="white"/>
          <circle cx="50" cy="50" r="4" fill="#1BB955"/>
        </svg>
        <div class="logo-text">Mero<span>Maidan</span></div>
      </a>

      <!-- Desktop Nav Bar -->
      <nav class="desktop-nav" id="siteNav" aria-label="Primary navigation">
        <a href="#services">Find Venues</a>
        <a href="#how-it-works">How It Works</a>
        <a href="about.php">About Us</a>
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
        <a href="player/index.php" class="list-ground-btn" style="background:#1BB955;color:#fff;margin-right:8px;">⚽ My Dashboard</a>
        <a href="auth/logout.php" class="list-ground-btn" style="background:transparent;color:#64748b;border:1px solid #cbd5e1;">Sign Out</a>
      <?php elseif ($isOwner): ?>
        <a href="owner/index.php" class="list-ground-btn" style="background:#0f2740;color:#fff;margin-right:8px;">🏟️ Owner Panel</a>
        <a href="auth/logout.php" class="list-ground-btn" style="background:transparent;color:#64748b;border:1px solid #cbd5e1;">Sign Out</a>
      <?php elseif ($isAdmin): ?>
        <a href="superadmin/index.php" class="list-ground-btn" style="background:#0f2740;color:#fff;margin-right:8px;">🛡️ Admin Panel</a>
        <a href="auth/logout.php" class="list-ground-btn" style="background:transparent;color:#64748b;border:1px solid #cbd5e1;">Sign Out</a>
      <?php else: ?>
        <a href="auth/login.php" class="list-ground-btn" style="background:transparent;color:#0f2740;border:2px solid #e2e8f0;margin-right:6px;">Log In</a>
        <a href="auth/register.php" class="list-ground-btn" style="background:#1BB955;color:#fff;margin-right:8px;">Sign Up</a>
        <a href="list-ground.php" class="list-ground-btn" style="background:#0f2740;">🏟️ List Ground</a>
      <?php endif; ?>
      <button class="nav-toggle-btn" id="navToggleBtn" type="button" aria-label="Open menu" aria-controls="siteNav" aria-expanded="false">☰</button>
    </div>
  </header>

  <!-- CMS-powered Hero Carousel -->
  <section class="hero-carousel" id="heroCarousel" aria-roledescription="carousel" aria-label="MeroMaidan highlights" tabindex="0">
    <div class="hero-track" id="heroTrack">
      <?php foreach($heroSlides as $index => $slide):
        $image = trim((string)($slide['image_url'] ?? ''));
        if (!preg_match('~^https?://[^\s"\'()]+$~', $image)) $image = $fallbackHeroImages[$index % count($fallbackHeroImages)];
      ?>
      <article class="hero-slide<?=$index===0?' active':''?>" style="--hero-image:url('<?=htmlspecialchars($image,ENT_QUOTES)?>')" aria-hidden="<?=$index===0?'false':'true'?>"<?php if(!empty($slide['event_promotion_id'])):?> data-event-promotion="<?=intval($slide['event_promotion_id'])?>"<?php endif;?>>
        <div class="hero-slide-inner">
          <div class="hero-copy">
            <div class="hero-eyebrow"><span></span> <?=!empty($slide['event_promotion_id'])?'Promoted event · '.htmlspecialchars($slide['venue_name']):'Nepal’s smart sports venue platform'?></div>
            <h1 class="hero-title"><?=htmlspecialchars($slide['title'])?></h1>
            <p class="hero-sub"><?=htmlspecialchars((string)$slide['content_text'])?></p>
            <?php if(!empty($slide['event_date'])||!empty($slide['coupon_code'])):?><div class="event-hero-meta"><?php if(!empty($slide['event_date'])):?><span>📅 <?=date('d M Y',strtotime($slide['event_date']))?></span><?php endif;?><?php if(!empty($slide['coupon_code'])):?><span>Coupon: <strong><?=htmlspecialchars($slide['coupon_code'])?></strong></span><?php endif;?></div><?php endif;?>
            <div class="hero-actions">
              <a class="hero-primary-btn" href="<?=htmlspecialchars($slide['button_url'] ?: '#services')?>"><?=htmlspecialchars($slide['button_text'] ?: 'Explore venues')?> <span>→</span></a>
              <a class="hero-secondary-btn" href="#how-it-works">See how it works</a>
            </div>
            <div class="hero-trust-row"><span>✓ Verified venues</span><span>✓ Live slots</span><span>✓ Instant confirmation</span></div>
          </div>
          <aside class="hero-highlight-card">
            <div class="hero-highlight-icon">✦</div><div><small>WHY MEROMAIDAN</small><p><?=htmlspecialchars($slide['subtitle'] ?: 'Find, compare, and book with confidence.')?></p></div>
          </aside>
        </div>
      </article>
      <?php endforeach; ?>
    </div>
    <?php if(count($heroSlides)>1): ?>
    <button class="hero-arrow hero-prev" type="button" aria-label="Previous slide">←</button>
    <button class="hero-arrow hero-next" type="button" aria-label="Next slide">→</button>
    <div class="hero-controls" role="tablist" aria-label="Choose hero slide">
      <?php foreach($heroSlides as $index=>$slide): ?><button class="hero-dot<?=$index===0?' active':''?>" type="button" role="tab" aria-label="Show slide <?=$index+1?>" aria-selected="<?=$index===0?'true':'false'?>" data-slide="<?=$index?>"><span></span></button><?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div class="sr-only" id="heroStatus" aria-live="polite">Slide 1 of <?=count($heroSlides)?></div>
  </section>

  <!-- Venue Discovery -->
  <section class="discovery-section" id="services">
    <div class="section-heading-row"><div><div class="section-kicker">Discover venues</div><h2 class="section-title">Find the right ground for <span>your game.</span></h2><p class="section-lead">Search verified venues, compare rates, and see what is available near you.</p></div><div class="live-data-badge"><span></span> Live marketplace</div></div>
    <div class="discovery-panel">
      <div class="discovery-search"><span>⌕</span><input type="search" id="searchInput" placeholder="Search venue, area, or city…" aria-label="Search venues"></div>
      <button id="nearMeBtn" class="discovery-action secondary" type="button">📍 Near me</button>
      <button id="mapToggleBtn" class="discovery-action primary" type="button">🗺️ Map view</button>
    </div>
    <div class="filter-panel">
      <div class="filter-group"><div class="filter-label">Sport</div><div class="sports-grid" id="sportsGrid"><button class="sport-pill active" data-sport="all">All sports</button><button class="sport-pill" data-sport="Football">⚽ Football</button><button class="sport-pill" data-sport="Futsal">🏟️ Futsal</button><button class="sport-pill" data-sport="Cricket">🏏 Cricket</button><button class="sport-pill" data-sport="Cricsal">🎯 Cricsal</button></div></div>
      <div class="filter-divider"></div>
      <div class="filter-group"><div class="filter-label">Location</div><div class="region-grid" id="regionGrid"><button class="region-pill active" data-region="all">All Nepal</button><button class="region-pill" data-region="Kathmandu">Kathmandu</button><button class="region-pill" data-region="Lalitpur">Lalitpur</button><button class="region-pill" data-region="Bhaktapur">Bhaktapur</button><button class="region-pill" data-region="Pokhara">Pokhara</button><button class="region-pill" data-region="Chitwan">Chitwan</button></div></div>
    </div>
    <div class="map-panel" id="mapModal" hidden><div class="map-panel-head"><div><strong>Explore on the map</strong><span>Showing venues from your current filters</span></div><button id="mapCloseBtn" type="button" aria-label="Close map">✕</button></div><div id="leafletMap"></div></div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"><script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <div class="results-toolbar"><div class="results-count-text" id="resultsCountText">Loading venues…</div><span>Verified listings only</span></div>
    <div class="grounds-container" id="groundsGrid"><div class="marketplace-loading">Loading available venues…</div></div>
  </section>

  <!-- How It Works Section -->
  <section class="process-section" id="how-it-works">
    <div class="centered-heading"><div class="section-kicker">Simple by design</div><h2 class="section-title">From search to kickoff in <span>three steps.</span></h2><p class="section-lead">No phone calls, confusing price lists, or uncertain availability.</p></div>
    <div class="process-grid">
      <article class="process-card"><div class="process-top"><span class="process-number">01</span><div class="process-icon">⌕</div></div><h3>Discover</h3><p>Filter trusted venues by sport, location, price, and distance.</p></article>
      <article class="process-card highlighted"><div class="process-top"><span class="process-number">02</span><div class="process-icon">▣</div></div><h3>Choose a slot</h3><p>Open a venue, select an available date and time, and see the price upfront.</p></article>
      <article class="process-card"><div class="process-top"><span class="process-number">03</span><div class="process-icon">✓</div></div><h3>Book with confidence</h3><p>Confirm your details and receive a booking reference immediately.</p></article>
    </div>
  </section>

  <!-- Ready to Play Section -->
  <section class="final-cta">
    <div class="cta-orb one"></div><div class="cta-orb two"></div><div class="final-cta-content"><div class="section-kicker light">Your game is waiting</div><h2><?=htmlspecialchars(cmsValue($cms,'cta_section','title','Ready to Play?'))?></h2><p><?=htmlspecialchars(cmsValue($cms,'cta_section','content_text','Find the right venue and reserve your next game today.'))?></p><div class="final-cta-actions"><a href="<?=htmlspecialchars(cmsValue($cms,'cta_section','button_url','#groundsGrid'))?>" class="cta-main"><?=htmlspecialchars(cmsValue($cms,'cta_section','button_text','Find a Ground'))?> →</a><a href="list-ground.php" class="cta-alt">Own a venue? Partner with us</a></div></div>
  </section>

  <!-- Site Footer -->
  <footer class="site-footer">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">Mero<span>Maidan</span></div>
        <p class="footer-sub">We make sports simple and accessible by helping players, teams, and academies easily find and book sports grounds across Nepal.</p>
      </div>

      <div>
        <div class="footer-sec-title">Quick Links</div>
        <ul class="footer-links-list">
          <li><a href="#services">Find venues</a></li><li><a href="#how-it-works">How it works</a></li><li><a href="about.php">About Us</a></li>
        </ul>
      </div>

      <div>
        <div class="footer-sec-title">For venue owners</div><ul class="footer-links-list"><li><a href="list-ground.php">List your venue</a></li><li><a href="auth/owner-login.php">Owner portal</a></li><li><a href="auth/admin-login.php">Administration</a></li></ul>
      </div>

      <div>
        <div class="footer-sec-title">Get started</div><p class="footer-sub">Create a player account to save venues and manage all your bookings in one place.</p><a href="auth/register.php" class="footer-account-link">Create an account →</a>
      </div>
    </div>

    <div class="footer-bottom">
      <p>© <?=date('Y')?> MeroMaidan · Built for Nepal’s sports community.</p>
    </div>
  </footer>

</div>

<script src="assets/js/app.js?v=20260810-recommended2"></script>
</body>
</html>
