<?php
if (session_status() === PHP_SESSION_NONE) session_start();
$isPlayer = !empty($_SESSION['player_id']);
$isOwner  = !empty($_SESSION['owner_id']);
$isAdmin  = !empty($_SESSION['superadmin_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>MeroMaidan - Nepal's Smart Sports Venue Booking Platform</title>
  <meta name="description" content="MeroMaidan makes sports simple — players, teams & academies instantly connect with top sports venues across Nepal.">
  <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="app-shell">
  <!-- Header Navigation -->
  <header class="site-header">
    <div class="header-left">
      <a href="#" class="logo-wrap">
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
      <nav class="desktop-nav">
        <a href="#services">Services</a>
        <a href="#how-it-works">How It Works</a>
        <a href="#about">About Us</a>
        <a href="#story">Our Story</a>
        <a href="#why-choose-us">Why Choose Us</a>
        <a href="#testimonials">Reviews</a>
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
      <button class="nav-toggle-btn" aria-label="Menu">☰</button>
    </div>
  </header>

  <!-- Hero Section -->
  <section class="hero-section">
    <div class="hero-container">
      <div class="hero-left">
        <h1 class="hero-title">Find & book sports grounds in Nepal · football, futsal, cricket & more</h1>
        <p class="hero-sub">MeroMaidan makes sports simple — players, teams & academies instantly connect with top venues.</p>
        <div class="hero-dots">
          <div class="hero-dot"></div>
          <div class="hero-dot"></div>
          <div class="hero-dot"></div>
          <div class="hero-dot"></div>
          <div class="hero-dot active"></div>
        </div>
      </div>
      <div class="hero-right">
        <div class="hero-highlight-card">
          “Find and book sports grounds near you in minutes. Football, futsal, cricket, and cricsal venues across Nepal—all in one place.”
        </div>
      </div>
    </div>
  </section>

  <!-- Services & Ground Listings -->
  <section class="section-pad" id="services">
    <h2 class="section-title">Our <span>services</span></h2>
    
    <!-- Search + Near Me Bar -->
    <div style="display:flex;gap:12px;margin-bottom:20px;flex-wrap:wrap;align-items:center;">
      <div style="flex:1;min-width:200px;display:flex;align-items:center;gap:8px;background:#fff;border:2px solid #e2e8f0;border-radius:50px;padding:10px 18px;">
        <span>🔍</span>
        <input type="text" id="searchInput" placeholder="Search venues by name, area..." style="border:none;outline:none;font-family:inherit;font-size:14px;font-weight:600;color:#2b3648;background:none;flex:1;">
      </div>
      <button id="nearMeBtn" style="display:flex;align-items:center;gap:8px;padding:12px 22px;background:#0f2740;color:#fff;border:none;border-radius:50px;font-size:14px;font-weight:800;cursor:pointer;transition:all .2s;font-family:inherit;">
        📍 Near Me
      </button>
      <button id="mapToggleBtn" onclick="toggleMapModal()" style="display:flex;align-items:center;gap:8px;padding:12px 22px;background:#f97316;color:#fff;border:none;border-radius:50px;font-size:14px;font-weight:800;cursor:pointer;transition:all .2s;font-family:inherit;">
        🗺️ Map View
      </button>
    </div>

    <!-- Leaflet Map Modal -->
    <div id="mapModal" style="display:none;background:white;border-radius:16px;border:2px solid #e2e8f0;padding:20px;margin-bottom:24px;box-shadow:0 10px 25px rgba(0,0,0,0.05);">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;">
        <h3 style="font-size:16px;font-weight:800;color:#0f2740;">🗺️ Interactive Venue Location Map (Nepal)</h3>
        <button onclick="toggleMapModal()" style="background:#e2e8f0;border:none;padding:6px 12px;border-radius:20px;cursor:pointer;font-weight:700;">✕ Close Map</button>
      </div>
      <div id="leafletMap" style="height:380px;width:100%;border-radius:12px;background:#e2e8f0;"></div>
    </div>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script>
      let map = null;
      function toggleMapModal() {
        const m = document.getElementById('mapModal');
        if (m.style.display === 'none') {
          m.style.display = 'block';
          if (!map) {
            map = L.map('leafletMap').setView([27.7036, 85.3199], 12);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 18, attribution: '© OpenStreetMap' }).addTo(map);
            // Add venue markers
            const pins = [
              { name: 'Royal Futsal', lat: 27.7036, lng: 85.3199, price: '1,500', slug: 'royal-futsal' },
              { name: 'Green Field Football', lat: 27.6679, lng: 85.3169, price: '2,500', slug: 'green-field-football' },
              { name: 'Kathmandu Futsal Center', lat: 27.6929, lng: 85.3385, price: '800', slug: 'kathmandu-futsal-center' }
            ];
            pins.forEach(p => {
              L.marker([p.lat, p.lng]).addTo(map)
               .bindPopup(`<b>${p.name}</b><br>NPR ${p.price}/hr<br><a href="venue.php?slug=${p.slug}" style="color:#f97316;font-weight:700;">Book Now 📅</a>`);
            });
          }
        } else {
          m.style.display = 'none';
        }
      }
    </script>

    <div class="service-heading">
      1. Sports Ground Booking ⚽
    </div>

    <div class="filter-controls-wrap">
      <!-- Sport Filters -->
      <div class="sports-grid" id="sportsGrid">
        <button class="sport-pill active" data-sport="all">🌐 All Sports</button>
        <button class="sport-pill" data-sport="Football">⚽ Football</button>
        <button class="sport-pill" data-sport="Futsal">🏟️ Futsal</button>
        <button class="sport-pill" data-sport="Cricket">🏏 Cricket</button>
        <button class="sport-pill" data-sport="Cricsal">🏏 Cricsal</button>
      </div>

      <!-- Select Region Box -->
      <div class="region-card">
        <div class="region-title">Select Region:</div>
        <div class="region-grid" id="regionGrid">
          <button class="region-pill active" data-region="all">🌐 All Regions</button>
          <button class="region-pill" data-region="Kathmandu">📍 Kathmandu</button>
          <button class="region-pill" data-region="Lalitpur">📍 Lalitpur</button>
          <button class="region-pill" data-region="Bhaktapur">📍 Bhaktapur</button>
          <button class="region-pill" data-region="Pokhara">📍 Pokhara</button>
          <button class="region-pill" data-region="Chitwan">📍 Chitwan</button>
        </div>
      </div>
    </div>

    <div class="results-count-text" id="resultsCountText">Found 37 grounds in all regions</div>

    <!-- Ground Grid Container -->
    <div class="grounds-container" id="groundsGrid">
      <!-- Cards rendered dynamically via app.js -->
    </div>

    <!-- Feature Services (2-7) -->
    <div class="services-grid-wrap">
      <div class="feature-service-card">
        <div class="service-icon-box">🤝</div>
        <h4>2. Wide Partner Network</h4>
        <p>Partnered with top academies & grounds across Nepal.</p>
      </div>

      <div class="feature-service-card">
        <div class="service-icon-box">📍</div>
        <h4>3. Location-Based Search</h4>
        <p>Quickly find grounds near home, work, or training spots.</p>
      </div>

      <div class="feature-service-card">
        <div class="service-icon-box">⚡</div>
        <h4>4. Instant Booking & Confirmation</h4>
        <p>Secure & fast — no calls, confirmation in seconds.</p>
      </div>

      <div class="feature-service-card">
        <div class="service-icon-box">⚽</div>
        <h4>5. Booking for Teams, Events & Training</h4>
        <div class="tag-list">
          <span class="tag-item">friendly matches</span>
          <span class="tag-item">team practice</span>
          <span class="tag-item">coaching / academy</span>
        </div>
      </div>

      <div class="feature-service-card">
        <div class="service-icon-box">🏢</div>
        <h4>6. Support for ground owners</h4>
        <p>List your facility, increase visibility.</p>
      </div>

      <div class="feature-service-card">
        <div class="service-icon-box">🎧</div>
        <h4>7. Customer support</h4>
        <p>We're here to help with bookings & questions.</p>
      </div>
    </div>
  </section>

  <!-- How It Works Section -->
  <section class="section-pad how-it-works-bg" id="how-it-works">
    <h2 class="section-title" style="text-align: center;">How It <span>Works</span></h2>
    
    <div class="steps-grid">
      <!-- Step 1 -->
      <div class="step-card">
        <div class="step-num-badge">1</div>
        <div class="step-icon-wrap">🔍</div>
        <h3 class="step-title">Search</h3>
        <p class="step-desc">Find your perfect ground by location, sport, and availability</p>
        <ul class="step-check-list">
          <li>200+ venues</li>
          <li>Real-time availability</li>
          <li>Filter by sport</li>
        </ul>
      </div>

      <!-- Step 2 -->
      <div class="step-card">
        <div class="step-num-badge">2</div>
        <div class="step-icon-wrap">📅</div>
        <h3 class="step-title">Pick</h3>
        <p class="step-desc">Select your date, time, and duration that works best for you</p>
        <ul class="step-check-list">
          <li>Flexible timing</li>
          <li>1-6 hour slots</li>
          <li>Instant pricing</li>
        </ul>
      </div>

      <!-- Step 3 -->
      <div class="step-card">
        <div class="step-num-badge">3</div>
        <div class="step-icon-wrap">🧡</div>
        <h3 class="step-title">Confirm</h3>
        <p class="step-desc">Book instantly and get confirmation within 30 minutes</p>
        <ul class="step-check-list">
          <li>20% off first booking</li>
          <li>Secure payment</li>
          <li>24/7 support</li>
        </ul>
      </div>
    </div>

    <!-- Callout Box -->
    <div class="callout-box">
      <h3 class="callout-title">Ready to play?</h3>
      <p class="callout-sub">MeroMaidan · instant booking · 200+ venues</p>
      <a href="#groundsGrid" class="btn-orange">Find a ground 📅</a>
    </div>
  </section>

  <!-- About Section -->
  <section class="about-section" id="about">
    <div class="about-grid">
      <div class="about-left">
        <h2 class="about-title">About <span>MeroMaidan</span></h2>
        <div class="about-sub">Your Ultimate Sports Ground Booking Platform in Nepal</div>
        <p class="about-desc">We're on a mission to make sports accessible to everyone by providing a seamless platform to discover and book the best sports grounds across Nepal.</p>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon">📍</div>
          <div class="stat-num">50+</div>
          <div class="stat-label">Sports Grounds</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">⚽</div>
          <div class="stat-num">4</div>
          <div class="stat-label">Sports Categories</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">🙂</div>
          <div class="stat-num">5000+</div>
          <div class="stat-label">Happy Customers</div>
        </div>
        <div class="stat-card">
          <div class="stat-icon">🎧</div>
          <div class="stat-num">24/7</div>
          <div class="stat-label">Booking Support</div>
        </div>
      </div>
    </div>
  </section>

  <!-- Our Story -->
  <section class="story-section" id="story">
    <div class="story-grid">
      <div class="story-left">
        <h2 class="section-title" style="margin-bottom: 20px;">Our <span>Story</span></h2>
        <p class="story-text">Founded in 2024, MeroMaidan was born from a simple idea: making sports ground booking as easy as ordering food online.</p>
        <p class="story-text">What started as a small initiative has now grown into Nepal's most trusted sports ground booking platform, connecting thousands of sports enthusiasts with the best facilities across the country.</p>

        <div class="mission-card">
          <div class="mission-icon">🎯</div>
          <h3 class="mission-title">Our Mission</h3>
          <p class="mission-desc">To create a healthier, more active community by making sports facilities accessible to everyone.</p>
        </div>

        <div class="mission-card">
          <div class="mission-icon">👁️</div>
          <h3 class="mission-title">Our Vision</h3>
          <p class="mission-desc">To be the #1 sports ground booking platform in Nepal.</p>
        </div>
      </div>

      <div class="photo-grid">
        <img src="https://images.unsplash.com/photo-1546519638-68e109498ffc?auto=format&fit=crop&w=600&q=80" alt="Football">
        <img src="https://images.unsplash.com/photo-1519861531473-9200262188bf?auto=format&fit=crop&w=600&q=80" alt="Basketball">
        <img src="https://images.unsplash.com/photo-1595435934249-5df7ed86e1c0?auto=format&fit=crop&w=600&q=80" alt="Tennis">
        <img src="https://images.unsplash.com/photo-1612872087720-bb876e2e67d1?auto=format&fit=crop&w=600&q=80" alt="Volleyball">
      </div>
    </div>
  </section>

  <!-- Why Choose Us -->
  <section class="section-pad" id="why-choose-us" style="text-align: center;">
    <h2 class="section-title">Why Choose <span>Us?</span></h2>
    <p style="font-size: 13px; color: #64748b; margin-top: 6px; margin-bottom: 24px;">Experience the best sports ground booking service in Nepal</p>

    <div class="why-grid">
      <div class="why-card">
        <div class="why-icon-wrap">📅</div>
        <h3 class="why-title">Easy Booking</h3>
        <p class="why-desc">Book your favorite sports ground in just a few clicks with our simple and fast booking process.</p>
      </div>

      <div class="why-card">
        <div class="why-icon-wrap">⏰</div>
        <h3 class="why-title">24/7 Availability</h3>
        <p class="why-desc">Book anytime, anywhere with our round-the-clock booking system. Never miss a game!</p>
      </div>

      <div class="why-card">
        <div class="why-icon-wrap">🏷️</div>
        <h3 class="why-title">Best Prices</h3>
        <p class="why-desc">Get the best rates and exclusive deals on all sports grounds across Nepal.</p>
      </div>

      <div class="why-card">
        <div class="why-icon-wrap">🛡️</div>
        <h3 class="why-title">Secure Payment</h3>
        <p class="why-desc">Your transactions are 100% secure with our encrypted payment gateway.</p>
      </div>

      <div class="why-card">
        <div class="why-icon-wrap">⭐</div>
        <h3 class="why-title">Premium Grounds</h3>
        <p class="why-desc">Access to the best maintained and equipped sports facilities in Nepal.</p>
      </div>

      <div class="why-card">
        <div class="why-icon-wrap">🎧</div>
        <h3 class="why-title">Dedicated Support</h3>
        <p class="why-desc">Our customer support team is ready to assist you with any queries.</p>
      </div>
    </div>
  </section>

  <!-- Testimonials Section -->
  <section class="section-pad" id="testimonials" style="background: #f8fafc; border-radius: 32px;">
    <div style="text-align: center; margin-bottom: 32px;">
      <h2 class="section-title">What Our <span>Customers Say</span></h2>
      <p style="font-size: 13px; color: #64748b; margin-top: 6px;">Join thousands of satisfied sports enthusiasts</p>
    </div>

    <div class="testimonials-grid">
      <div class="testimonial-card">
        <div class="user-profile">
          <img src="https://images.unsplash.com/photo-1534528741775-53994a69daeb?auto=format&fit=crop&w=120&q=80" class="user-avatar" alt="Ahmed">
          <div>
            <div class="user-name">Ahmed Khan</div>
            <div class="user-role">Football Enthusiast</div>
          </div>
        </div>
        <div class="stars">★★★★★</div>
        <p class="review-text">“MeroMaidan has transformed how we book football matches. The process is seamless and the grounds are always top-notch!”</p>
      </div>

      <div class="testimonial-card">
        <div class="user-profile">
          <img src="https://images.unsplash.com/photo-1494790108377-be9c29b29330?auto=format&fit=crop&w=120&q=80" class="user-avatar" alt="Sarah">
          <div>
            <div class="user-name">Sarah Williams</div>
            <div class="user-role">Tennis Player</div>
          </div>
        </div>
        <div class="stars">★★★★★</div>
        <p class="review-text">“I love how easy it is to find and book tennis courts. The location filters save us so much time!”</p>
      </div>

      <div class="testimonial-card">
        <div class="user-profile">
          <img src="https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?auto=format&fit=crop&w=120&q=80" class="user-avatar" alt="Mohammed">
          <div>
            <div class="user-name">Mohammed Al Ali</div>
            <div class="user-role">Cricket Captain</div>
          </div>
        </div>
        <div class="stars">★★★★★</div>
        <p class="review-text">“Our team uses MeroMaidan exclusively for all our cricket matches. Great prices and excellent ground quality!”</p>
      </div>
    </div>
  </section>

  <!-- Ready to Play Section -->
  <section class="section-pad" style="background: #0f2740; border-radius: 32px; margin: 32px 0; text-align: center; color: #ffffff;">
    <h2 style="font-size: 32px; font-weight: 800; margin-bottom: 12px;">Ready to Play?</h2>
    <p style="font-size: 14px; opacity: 0.85; margin-bottom: 32px; max-width: 600px; margin-left: auto; margin-right: auto;">Join thousands of sports enthusiasts who trust MeroMaidan for their game bookings</p>
    
    <div style="display: flex; justify-content: center; gap: 16px; flex-wrap: wrap;">
      <a href="#groundsGrid" class="btn-orange" style="min-width: 220px; justify-content: center;">📙 Book a Ground Now</a>
      <a href="tel:0527132921" class="btn-orange" style="min-width: 220px; justify-content: center; background: rgba(255,255,255,0.15);">📞 Contact Us</a>
    </div>

    <div style="font-size: 12px; opacity: 0.8; margin-top: 24px; display: flex; justify-content: center; gap: 24px; flex-wrap: wrap;">
      <span>✓ 100% Secure</span>
      <span>✓ Instant Confirmation</span>
      <span>✓ 24/7 Support</span>
    </div>
  </section>

  <!-- Site Footer -->
  <footer class="site-footer">
    <div class="footer-grid">
      <div>
        <div class="footer-brand">MEROMAIDAN</div>
        <p class="footer-sub">We make sports simple and accessible by helping players, teams, and academies easily find and book sports grounds across Nepal.</p>
        <div class="social-links">
          <a href="#" class="social-icon">f</a>
          <a href="#" class="social-icon">t</a>
          <a href="#" class="social-icon">ig</a>
          <a href="#" class="social-icon">in</a>
          <a href="#" class="social-icon">yt</a>
        </div>
      </div>

      <div>
        <div class="footer-sec-title">Quick Links</div>
        <ul class="footer-links-list">
          <li><a href="#about">❯ About Us</a></li>
          <li><a href="#how-it-works">❯ How It Works</a></li>
          <li><a href="#services">❯ Popular Grounds</a></li>
          <li><a href="admin/index.php">❯ Become a Partner</a></li>
          <li><a href="#why-choose-us">❯ FAQ</a></li>
        </ul>
      </div>

      <div>
        <div class="footer-sec-title">Contact Us</div>
        <ul class="contact-info-list">
          <li>📍 Kathmandu, Nepal</li>
          <li>📞 9800000000</li>
          <li>✉️ support@meromaidan.com</li>
          <li>⏰ Sun-Sat: 12AM-11PM</li>
        </ul>
      </div>

      <div>
        <div class="footer-sec-title">Newsletter</div>
        <p style="font-size: 13px; opacity: 0.8; margin-bottom: 14px;">Subscribe for updates & offers!</p>
        <div class="newsletter-box">
          <input type="email" placeholder="Your email" class="newsletter-input">
          <button class="newsletter-btn">✈️ Subscribe</button>
        </div>
      </div>
    </div>

    <div class="footer-bottom">
      <p>© 2024 MeroMaidan. Made with ❤️ in Nepal</p>
      <div style="margin-top: 10px; display: flex; justify-content: center; gap: 16px;">
        <a href="#" style="color: rgba(255,255,255,0.7); text-decoration: none;">Privacy</a>
        <a href="#" style="color: rgba(255,255,255,0.7); text-decoration: none;">Terms</a>
        <a href="#" style="color: rgba(255,255,255,0.7); text-decoration: none;">Sitemap</a>
      </div>
    </div>
  </footer>

  <!-- Floating Buttons -->
  <div class="floating-action-bar">
    <a href="tel:9800000000" class="floating-btn floating-call" aria-label="Call">📞</a>
    <a href="https://wa.me/9779800000000" class="floating-btn floating-wa" aria-label="WhatsApp">💬</a>
  </div>
</div>

<!-- Booking Modal -->
<div class="modal-overlay" id="bookingModal">
  <div class="modal-card">
    <div class="modal-header">
      <h3 class="modal-title">Book Ground Slot</h3>
      <button class="modal-close" id="modalCloseBtn">✕</button>
    </div>
    
    <p style="font-size: 14px; font-weight: 700; color: #f9631c; margin-bottom: 20px;" id="modalGroundName">All Sports Ground</p>
    
    <form id="bookingForm">
      <input type="hidden" id="modalGroundId" value="">
      
      <div class="form-group">
        <label class="form-label" for="custName">Your Name</label>
        <input type="text" id="custName" class="form-input" placeholder="e.g. Tariq Al Mansoori" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="custPhone">Phone Number</label>
        <input type="tel" id="custPhone" class="form-input" placeholder="e.g. 0527132921" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="bookDate">Booking Date</label>
        <input type="date" id="bookDate" class="form-input" value="2026-08-01" required>
      </div>

      <div class="form-group">
        <label class="form-label" for="bookSlot">Time Slot</label>
        <select id="bookSlot" class="form-select" required>
          <option value="05:00 PM - 06:00 PM">05:00 PM - 06:00 PM</option>
          <option value="06:00 PM - 07:00 PM">06:00 PM - 07:00 PM</option>
          <option value="07:00 PM - 08:00 PM">07:00 PM - 08:00 PM</option>
          <option value="08:00 PM - 09:00 PM">08:00 PM - 09:00 PM</option>
          <option value="09:00 PM - 10:00 PM">09:00 PM - 10:00 PM</option>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label" for="payMethod">Payment Method</label>
        <select id="payMethod" class="form-select" required>
          <option value="Credit Card">Credit / Debit Card</option>
          <option value="Apple Pay">Apple Pay</option>
          <option value="Cash at Venue">Pay at Venue</option>
        </select>
      </div>

      <button type="submit" class="btn-orange" style="width: 100%; justify-content: center; margin-top: 12px;">
        Confirm Booking 📅
      </button>
    </form>
  </div>
</div>

<!-- Toast Notification -->
<div class="toast" id="toastNotification"></div>

<script src="assets/js/app.js"></script>
</body>
</html>
