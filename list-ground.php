<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>List Your Ground - MeroMaidan</title>
  <meta name="description" content="List your sports ground on MeroMaidan and start getting bookings instantly.">
  <link rel="stylesheet" href="assets/css/venue.css">
  <style>
    body { background: #f5f7fa; }
    .list-hero {
      background: linear-gradient(135deg, #0f2740 0%, #14355d 100%);
      color: #fff;
      text-align: center;
      padding: 60px 24px 40px;
    }
    .list-hero h1 { font-size: clamp(26px,4vw,40px); font-weight: 900; margin-bottom: 12px; }
    .list-hero h1 span { color: #1BB955; }
    .list-hero p { font-size: 16px; opacity: .85; max-width: 560px; margin: 0 auto 28px; }
    .plan-badges { display: flex; gap: 12px; justify-content: center; flex-wrap: wrap; }
    .plan-badge { background: rgba(255,255,255,.12); border: 1px solid rgba(255,255,255,.2); backdrop-filter: blur(8px); color: #fff; padding: 8px 20px; border-radius: 50px; font-size: 13px; font-weight: 600; }
    .plan-badge strong { color: #1BB955; }

    .form-shell {
      max-width: 740px;
      margin: -24px auto 60px;
      padding: 0 16px;
    }

    /* Step Indicator */
    .steps-indicator {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 0;
      margin-bottom: 28px;
    }
    .step-dot {
      width: 36px; height: 36px;
      border-radius: 50%;
      background: #e2e8f0;
      color: #64748b;
      font-size: 14px;
      font-weight: 800;
      display: flex;
      align-items: center;
      justify-content: center;
      transition: all .3s;
      position: relative;
      z-index: 1;
    }
    .step-dot.active { background: #1BB955; color: #fff; box-shadow: 0 0 0 4px rgba(27,185,85,.2); }
    .step-dot.done { background: #0f2740; color: #fff; }
    .step-line {
      flex: 1;
      height: 3px;
      background: #e2e8f0;
      max-width: 80px;
      transition: background .3s;
    }
    .step-line.done { background: #0f2740; }
    .step-label {
      display: flex;
      justify-content: space-between;
      padding: 0 0 24px;
      font-size: 11px;
      font-weight: 700;
      color: #64748b;
      text-transform: uppercase;
      letter-spacing: .3px;
    }

    /* Step Card */
    .step-card {
      background: #fff;
      border-radius: 20px;
      padding: 36px 32px;
      box-shadow: 0 4px 32px rgba(0,0,0,.08);
      display: none;
    }
    .step-card.active { display: block; animation: fadeIn .3s ease; }
    @keyframes fadeIn { from { opacity:0; transform:translateY(12px); } to { opacity:1; transform:translateY(0); } }
    .step-card h2 { font-size: 22px; font-weight: 900; color: #0f2740; margin-bottom: 6px; }
    .step-card .step-sub { font-size: 13px; color: #64748b; margin-bottom: 28px; }
    .step-icon-title { font-size: 28px; margin-bottom: 8px; }

    .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    @media (max-width: 600px) { .form-row { grid-template-columns: 1fr; } }
    .form-group { margin-bottom: 18px; }
    .form-label { display: block; font-size: 12px; font-weight: 700; color: #64748b; margin-bottom: 6px; text-transform: uppercase; letter-spacing: .3px; }
    .form-label span { color: #ef4444; }
    .form-input, .form-select, .form-textarea {
      width: 100%;
      padding: 12px 16px;
      border: 2px solid #e2e8f0;
      border-radius: 12px;
      font-family: 'Plus Jakarta Sans', sans-serif;
      font-size: 14px;
      font-weight: 600;
      color: #2b3648;
      background: #f8fafc;
      outline: none;
      transition: border-color .2s;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus { border-color: #1BB955; background: #fff; }
    .form-textarea { min-height: 90px; resize: vertical; }

    /* Sport Type Selector */
    .sport-selector { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
    .sport-option {
      border: 2.5px solid #e2e8f0;
      border-radius: 14px;
      padding: 16px 14px;
      cursor: pointer;
      text-align: center;
      transition: all .2s;
    }
    .sport-option:hover { border-color: #1BB955; }
    .sport-option.selected { border-color: #1BB955; background: rgba(27,185,85,.07); }
    .sport-option .sport-emoji { font-size: 28px; display: block; margin-bottom: 6px; }
    .sport-option .sport-name { font-size: 13px; font-weight: 800; color: #0f2740; }
    .sport-option .sport-desc { font-size: 11px; color: #64748b; margin-top: 2px; }

    /* Amenities Checkboxes */
    .amenity-checks { display: grid; grid-template-columns: repeat(2, 1fr); gap: 10px; }
    @media (max-width: 600px) { .amenity-checks { grid-template-columns: 1fr; } }
    .amenity-check {
      display: flex;
      align-items: center;
      gap: 10px;
      border: 2px solid #e2e8f0;
      border-radius: 10px;
      padding: 10px 14px;
      cursor: pointer;
      transition: all .2s;
      font-size: 13px;
      font-weight: 600;
      color: #2b3648;
      user-select: none;
    }
    .amenity-check:hover { border-color: #1BB955; }
    .amenity-check input { display: none; }
    .amenity-check .check-box {
      width: 20px; height: 20px;
      border: 2px solid #cbd5e1;
      border-radius: 6px;
      display: flex; align-items: center; justify-content: center;
      font-size: 12px;
      flex-shrink: 0;
      transition: all .2s;
    }
    .amenity-check input:checked ~ .check-content .check-box,
    .amenity-check.checked .check-box {
      background: #1BB955;
      border-color: #1BB955;
      color: #fff;
    }
    .amenity-check.checked { border-color: #1BB955; background: rgba(27,185,85,.06); }

    /* Plan Cards */
    .plan-cards { display: grid; grid-template-columns: repeat(3, 1fr); gap: 14px; }
    @media (max-width: 600px) { .plan-cards { grid-template-columns: 1fr; } }
    .plan-card {
      border: 2.5px solid #e2e8f0;
      border-radius: 16px;
      padding: 22px 18px;
      cursor: pointer;
      transition: all .2s;
      text-align: center;
      position: relative;
    }
    .plan-card:hover { border-color: #1BB955; transform: translateY(-2px); }
    .plan-card.selected { border-color: #1BB955; background: rgba(27,185,85,.06); box-shadow: 0 8px 24px rgba(27,185,85,.15); }
    .plan-card.popular { border-color: #f9631c; }
    .plan-card.popular.selected { border-color: #f9631c; background: rgba(249,99,28,.05); }
    .popular-badge { position: absolute; top: -12px; left: 50%; transform: translateX(-50%); background: #f9631c; color: #fff; font-size: 10px; font-weight: 800; padding: 4px 14px; border-radius: 50px; white-space: nowrap; }
    .plan-name { font-size: 15px; font-weight: 900; color: #0f2740; margin-bottom: 6px; }
    .plan-price { font-size: 26px; font-weight: 900; color: #1BB955; margin-bottom: 4px; }
    .plan-price sup { font-size: 14px; }
    .plan-price sub { font-size: 13px; color: #64748b; }
    .plan-features { list-style: none; text-align: left; margin-top: 12px; }
    .plan-features li { font-size: 12px; color: #64748b; padding: 4px 0; display: flex; align-items: center; gap: 6px; }
    .plan-features li::before { content: '✓'; color: #1BB955; font-weight: 700; }

    /* Nav Buttons */
    .step-nav { display: flex; justify-content: space-between; align-items: center; margin-top: 28px; }
    .btn-prev {
      background: #f1f5f9;
      color: #64748b;
      border: none;
      padding: 12px 28px;
      border-radius: 50px;
      font-size: 14px;
      font-weight: 700;
      cursor: pointer;
      transition: all .2s;
    }
    .btn-prev:hover { background: #e2e8f0; }
    .btn-next {
      background: #1BB955;
      color: #fff;
      border: none;
      padding: 12px 32px;
      border-radius: 50px;
      font-size: 14px;
      font-weight: 800;
      cursor: pointer;
      transition: all .2s;
      display: flex;
      align-items: center;
      gap: 8px;
    }
    .btn-next:hover { background: #159943; transform: translateY(-2px); box-shadow: 0 8px 20px rgba(27,185,85,.3); }

    /* Success State */
    .success-state {
      text-align: center;
      padding: 20px 0;
      display: none;
    }
    .success-state .big-icon { font-size: 64px; margin-bottom: 16px; }
    .success-state h2 { font-size: 26px; font-weight: 900; color: #0f2740; margin-bottom: 10px; }
    .success-state p { font-size: 14px; color: #64748b; max-width: 380px; margin: 0 auto 24px; }

    /* Map Placeholder */
    .map-placeholder {
      border: 2px dashed #e2e8f0;
      border-radius: 14px;
      height: 200px;
      display: flex;
      align-items: center;
      justify-content: center;
      flex-direction: column;
      gap: 8px;
      cursor: pointer;
      transition: border-color .2s;
      background: #f8fafc;
    }
    .map-placeholder:hover { border-color: #1BB955; }
    .map-placeholder .map-icon { font-size: 32px; }
    .map-placeholder p { font-size: 13px; color: #64748b; font-weight: 600; }
    .map-placeholder small { font-size: 11px; color: #94a3b8; }
  </style>
</head>
<body>

<!-- Header -->
<header class="venue-header">
  <a href="index.php" class="logo-wrap">
    <svg class="logo-badge-svg" viewBox="0 0 100 100" fill="none">
      <rect width="100" height="100" rx="28" fill="#1BB955"/>
      <path d="M25 20H55C68 20 75 27 75 37C75 44 70 50 62 52C72 55 78 62 78 72C78 83 69 90 53 90H25V20Z" fill="#1BB955"/>
      <path fill-rule="evenodd" clip-rule="evenodd" d="M32 28H54C62 28 67 32 67 38C67 43 62 47 54 47H32V28ZM32 54H56C65 54 70 59 70 66C70 73 64 78 55 78H32V54Z" stroke="white" stroke-width="6"/>
      <circle cx="50" cy="50" r="10" fill="white"/><circle cx="50" cy="50" r="4" fill="#1BB955"/>
    </svg>
    <div class="logo-text">Mero<span>Maidan</span></div>
  </a>
  <nav>
    <a href="index.php">Home</a>
    <a href="index.php#services">Venues</a>
    <a href="index.php#about">About</a>
  </nav>
  <div class="header-actions">
    <a href="auth/login.php" class="btn-login">Log In</a>
    <a href="list-ground.php" class="btn-signup" style="background:#f9631c;">Sign Up Free</a>
  </div>
</header>

<!-- Hero -->
<div class="list-hero">
  <div class="step-icon-title">🏟️</div>
  <h1>List Your <span>Ground</span> on MeroMaidan</h1>
  <p>Join Nepal's fastest growing sports venue platform. Get more bookings, manage your ground effortlessly, and grow your business.</p>
  <div class="plan-badges">
    <span class="plan-badge">✅ Free to List</span>
    <span class="plan-badge">📅 Instant Bookings</span>
    <span class="plan-badge">📊 Analytics Dashboard</span>
    <span class="plan-badge">🛡️ <strong>Premium</strong> Plans Available</span>
  </div>
</div>

<!-- Multi-step Form -->
<div class="form-shell">

  <!-- Step Indicators -->
  <div class="steps-indicator" id="stepsIndicator">
    <div class="step-dot active" id="dot1">1</div>
    <div class="step-line" id="line1"></div>
    <div class="step-dot" id="dot2">2</div>
    <div class="step-line" id="line2"></div>
    <div class="step-dot" id="dot3">3</div>
    <div class="step-line" id="line3"></div>
    <div class="step-dot" id="dot4">4</div>
    <div class="step-line" id="line4"></div>
    <div class="step-dot" id="dot5">5</div>
  </div>

  <!-- ─── STEP 1: Business Info ─── -->
  <div class="step-card active" id="step1">
    <div class="step-icon-title">👤</div>
    <h2>Business Information</h2>
    <p class="step-sub">Tell us about you and your business</p>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Owner Full Name <span>*</span></label>
        <input type="text" id="ownerName" class="form-input" placeholder="e.g. Ramesh Shrestha">
      </div>
      <div class="form-group">
        <label class="form-label">Business Name <span>*</span></label>
        <input type="text" id="businessName" class="form-input" placeholder="e.g. Royal Futsal Pvt Ltd">
      </div>
    </div>
    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Email Address <span>*</span></label>
        <input type="email" id="ownerEmail" class="form-input" placeholder="you@business.com">
      </div>
      <div class="form-group">
        <label class="form-label">Phone Number <span>*</span></label>
        <input type="tel" id="ownerPhone" class="form-input" placeholder="98XXXXXXXX">
      </div>
    </div>
    <div class="step-nav">
      <span></span>
      <button class="btn-next" onclick="nextStep(1)">Next → Venue Details</button>
    </div>
  </div>

  <!-- ─── STEP 2: Venue Details ─── -->
  <div class="step-card" id="step2">
    <div class="step-icon-title">📍</div>
    <h2>Venue Details</h2>
    <p class="step-sub">Where is your venue located and what sport does it offer?</p>

    <div class="form-group">
      <label class="form-label">Venue Name <span>*</span></label>
      <input type="text" id="venueName" class="form-input" placeholder="e.g. Royal Futsal Arena">
    </div>

    <div class="form-group">
      <label class="form-label">Sport Type <span>*</span></label>
      <div class="sport-selector" id="sportSelector">
        <div class="sport-option selected" data-sport="Futsal">
          <span class="sport-emoji">🏟️</span>
          <div class="sport-name">Futsal</div>
          <div class="sport-desc">Indoor 5-a-side court</div>
        </div>
        <div class="sport-option" data-sport="Football">
          <span class="sport-emoji">⚽</span>
          <div class="sport-name">Football</div>
          <div class="sport-desc">7-a-side or 11-a-side pitch</div>
        </div>
        <div class="sport-option" data-sport="Cricket">
          <span class="sport-emoji">🏏</span>
          <div class="sport-name">Cricket</div>
          <div class="sport-desc">Full cricket ground</div>
        </div>
        <div class="sport-option" data-sport="Cricsal">
          <span class="sport-emoji">🎯</span>
          <div class="sport-name">Cricsal</div>
          <div class="sport-desc">Indoor cricket net facility</div>
        </div>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">City <span>*</span></label>
        <select id="venueCity" class="form-select">
          <option value="Kathmandu">Kathmandu</option>
          <option value="Lalitpur">Lalitpur (Patan)</option>
          <option value="Bhaktapur">Bhaktapur</option>
          <option value="Pokhara">Pokhara</option>
          <option value="Chitwan">Chitwan</option>
          <option value="Biratnagar">Biratnagar</option>
          <option value="Butwal">Butwal</option>
        </select>
      </div>
      <div class="form-group">
        <label class="form-label">District <span>*</span></label>
        <select id="venueDistrict" class="form-select">
          <option value="Kathmandu">Kathmandu</option>
          <option value="Lalitpur">Lalitpur</option>
          <option value="Bhaktapur">Bhaktapur</option>
          <option value="Kaski">Kaski</option>
          <option value="Chitwan">Chitwan</option>
          <option value="Morang">Morang</option>
          <option value="Rupandehi">Rupandehi</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Full Address <span>*</span></label>
      <input type="text" id="venueAddress" class="form-input" placeholder="e.g. Thapagaun, Anamnagar, Kathmandu">
    </div>

    <div class="form-group">
      <label class="form-label">Pin Your Location on Map</label>
      <div class="map-placeholder" id="mapPlaceholder" onclick="pinLocation()">
        <span class="map-icon">🗺️</span>
        <p>Click to pin your venue location</p>
        <small>Or enter GPS coordinates below</small>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Latitude (optional)</label>
        <input type="number" id="venueLat" class="form-input" placeholder="e.g. 27.7036" step="0.0001">
      </div>
      <div class="form-group">
        <label class="form-label">Longitude (optional)</label>
        <input type="number" id="venueLng" class="form-input" placeholder="e.g. 85.3199" step="0.0001">
      </div>
    </div>

    <div class="step-nav">
      <button class="btn-prev" onclick="prevStep(2)">← Back</button>
      <button class="btn-next" onclick="nextStep(2)">Next → Pricing</button>
    </div>
  </div>

  <!-- ─── STEP 3: Pricing & Hours ─── -->
  <div class="step-card" id="step3">
    <div class="step-icon-title">💰</div>
    <h2>Pricing & Operating Hours</h2>
    <p class="step-sub">Set your rates and when your venue is open</p>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Price per Hour (NPR) <span>*</span></label>
        <input type="number" id="priceHour" class="form-input" placeholder="e.g. 1500" min="100" step="50">
      </div>
      <div class="form-group">
        <label class="form-label">Ground Capacity</label>
        <select id="groundCapacity" class="form-select">
          <option value="5-a-side">5-a-side (Futsal)</option>
          <option value="7-a-side">7-a-side</option>
          <option value="11-a-side">11-a-side (Full)</option>
          <option value="Indoor Net">Indoor Net (Cricsal)</option>
          <option value="Match Ground">Match Ground (Cricket)</option>
        </select>
      </div>
    </div>

    <div class="form-row">
      <div class="form-group">
        <label class="form-label">Opening Time <span>*</span></label>
        <input type="time" id="openTime" class="form-input" value="06:00">
      </div>
      <div class="form-group">
        <label class="form-label">Closing Time <span>*</span></label>
        <input type="time" id="closeTime" class="form-input" value="22:00">
      </div>
    </div>

    <div class="form-group">
      <label class="form-label">Venue Description</label>
      <textarea id="venueDesc" class="form-textarea" placeholder="Describe your venue — surface type, unique features, nearby landmarks..."></textarea>
    </div>

    <div class="step-nav">
      <button class="btn-prev" onclick="prevStep(3)">← Back</button>
      <button class="btn-next" onclick="nextStep(3)">Next → Amenities</button>
    </div>
  </div>

  <!-- ─── STEP 4: Amenities ─── -->
  <div class="step-card" id="step4">
    <div class="step-icon-title">🏢</div>
    <h2>Facilities & Amenities</h2>
    <p class="step-sub">Select all facilities available at your venue</p>

    <div class="amenity-checks" id="amenityChecks">
      <!-- Generated by JS -->
    </div>

    <div class="step-nav">
      <button class="btn-prev" onclick="prevStep(4)">← Back</button>
      <button class="btn-next" onclick="nextStep(4)">Next → Choose Plan</button>
    </div>
  </div>

  <!-- ─── STEP 5: Choose Plan ─── -->
  <div class="step-card" id="step5">
    <div class="step-icon-title">⭐</div>
    <h2>Choose Your Plan</h2>
    <p class="step-sub">Select a subscription plan that fits your needs. Upgrade anytime.</p>

    <div class="plan-cards">
      <div class="plan-card selected" data-plan="free">
        <div class="plan-name">🆓 Free</div>
        <div class="plan-price">NPR <sup></sup>0<sub>/mo</sub></div>
        <ul class="plan-features">
          <li>1 Venue listing</li>
          <li>30 bookings/month</li>
          <li>Basic analytics</li>
          <li>Email support</li>
        </ul>
      </div>
      <div class="plan-card popular" data-plan="standard">
        <div class="popular-badge">⚡ Most Popular</div>
        <div class="plan-name">🥈 Standard</div>
        <div class="plan-price">NPR <sup></sup>1,499<sub>/mo</sub></div>
        <ul class="plan-features">
          <li>3 Venue listings</li>
          <li>200 bookings/month</li>
          <li>Priority listing</li>
          <li>Analytics dashboard</li>
          <li>WhatsApp alerts</li>
        </ul>
      </div>
      <div class="plan-card" data-plan="premium">
        <div class="plan-name">👑 Premium</div>
        <div class="plan-price">NPR <sup></sup>3,999<sub>/mo</sub></div>
        <ul class="plan-features">
          <li>Unlimited venues</li>
          <li>Unlimited bookings</li>
          <li>Top placement</li>
          <li>Advanced analytics</li>
          <li>Dedicated support</li>
          <li>Custom branding</li>
        </ul>
      </div>
    </div>

    <div id="submitError" style="display:none;color:#ef4444;font-size:13px;font-weight:700;margin-top:16px;padding:10px;background:#fef2f2;border-radius:10px;"></div>

    <div class="step-nav">
      <button class="btn-prev" onclick="prevStep(5)">← Back</button>
      <button class="btn-next" id="submitBtn" onclick="submitApplication()" style="background:#f9631c;">
        🚀 Submit Application
      </button>
    </div>

    <!-- Success -->
    <div class="success-state" id="successState">
      <div class="big-icon">🎉</div>
      <h2>Application Submitted!</h2>
      <p>Our team will review your application and contact you within <strong>24 hours</strong> to activate your listing.</p>
      <div style="background:#f0fdf4;border:2px solid #bbf7d0;border-radius:14px;padding:16px;font-size:13px;color:#166534;font-weight:600;margin-bottom:24px;">
        📧 Check your email <strong id="confirmEmail"></strong> for confirmation details.
      </div>
      <a href="index.php" class="btn-next" style="display:inline-flex;text-decoration:none;">← Back to Home</a>
    </div>
  </div>

</div><!-- /form-shell -->

<footer class="venue-footer">
  <p>© 2024 MeroMaidan. Nepal's Smart Sports Venue Booking Platform.</p>
</footer>

<script>
let currentStep = 1;
const totalSteps = 5;
let selectedSport = 'Futsal';
let selectedPlan  = 'free';
let selectedAmenities = [];

const amenityList = [
  {icon:'🚿', name:'Changing Room'},
  {icon:'🚗', name:'Parking'},
  {icon:'📷', name:'CCTV'},
  {icon:'💡', name:'Floodlights'},
  {icon:'💧', name:'Drinking Water'},
  {icon:'🏥', name:'First Aid'},
  {icon:'📶', name:'WiFi'},
  {icon:'☕', name:'Cafeteria'},
  {icon:'🔒', name:'Locker Room'},
  {icon:'❄️', name:'AC Waiting Area'},
  {icon:'🍽️', name:'Canteen'},
  {icon:'🎓', name:'Coaching Available'},
  {icon:'🏛️', name:'Pavilion'},
  {icon:'🥅', name:'Nets'},
  {icon:'⚡', name:'Generator Backup'},
  {icon:'🏪', name:'Pro Shop'},
];

// Render amenities
const amenGrid = document.getElementById('amenityChecks');
amenGrid.innerHTML = amenityList.map(a => `
  <label class="amenity-check" onclick="toggleAmenity(this,'${a.name}')">
    <div style="display:flex;align-items:center;gap:10px;flex:1;">
      <span style="font-size:18px;">${a.icon}</span>
      <span>${a.name}</span>
    </div>
    <div class="check-box">✓</div>
  </label>`).join('');

function toggleAmenity(el, name) {
  el.classList.toggle('checked');
  if (el.classList.contains('checked')) {
    selectedAmenities.push(name);
  } else {
    selectedAmenities = selectedAmenities.filter(a => a !== name);
  }
}

// Sport selector
document.getElementById('sportSelector').querySelectorAll('.sport-option').forEach(opt => {
  opt.addEventListener('click', () => {
    document.querySelectorAll('.sport-option').forEach(o => o.classList.remove('selected'));
    opt.classList.add('selected');
    selectedSport = opt.dataset.sport;
  });
});

// Plan selector
document.querySelectorAll('.plan-card').forEach(card => {
  card.addEventListener('click', () => {
    document.querySelectorAll('.plan-card').forEach(c => c.classList.remove('selected'));
    card.classList.add('selected');
    selectedPlan = card.dataset.plan;
  });
});

// Step navigation
function nextStep(from) {
  if (!validateStep(from)) return;
  currentStep++;
  updateSteps();
}
function prevStep(from) {
  currentStep--;
  updateSteps();
}

function updateSteps() {
  for (let i = 1; i <= totalSteps; i++) {
    const card = document.getElementById('step' + i);
    card.classList.toggle('active', i === currentStep);
  }
  for (let i = 1; i <= totalSteps; i++) {
    const dot = document.getElementById('dot' + i);
    dot.classList.remove('active','done');
    if (i === currentStep) dot.classList.add('active');
    else if (i < currentStep) dot.classList.add('done');
  }
  for (let i = 1; i < totalSteps; i++) {
    document.getElementById('line' + i)?.classList.toggle('done', i < currentStep);
  }
  window.scrollTo({top: 0, behavior: 'smooth'});
}

function validateStep(step) {
  const rules = {
    1: [['ownerName','Owner name'], ['businessName','Business name'], ['ownerEmail','Email'], ['ownerPhone','Phone']],
    2: [['venueName','Venue name'], ['venueAddress','Address']],
    3: [['priceHour','Price per hour']],
    4: [],
  };
  const fields = rules[step] || [];
  for (const [id, label] of fields) {
    const el = document.getElementById(id);
    if (!el || !el.value.trim()) {
      alert(`Please fill in: ${label}`);
      el?.focus();
      return false;
    }
  }
  if (step === 1) {
    if (!/\S+@\S+\.\S+/.test(document.getElementById('ownerEmail').value)) {
      alert('Please enter a valid email address');
      return false;
    }
  }
  return true;
}

// Pin location using browser GPS
function pinLocation() {
  if (navigator.geolocation) {
    navigator.geolocation.getCurrentPosition(pos => {
      document.getElementById('venueLat').value = pos.coords.latitude.toFixed(6);
      document.getElementById('venueLng').value = pos.coords.longitude.toFixed(6);
      document.getElementById('mapPlaceholder').innerHTML = `<span class="map-icon">📍</span><p style="color:#1BB955;font-weight:700;">Location pinned!</p><small>${pos.coords.latitude.toFixed(4)}, ${pos.coords.longitude.toFixed(4)}</small>`;
    }, () => alert('Could not get location. Please enter coordinates manually.'));
  } else {
    alert('Geolocation not supported. Please enter coordinates manually.');
  }
}

// Submit
async function submitApplication() {
  if (!validateStep(5)) return;
  const btn = document.getElementById('submitBtn');
  btn.disabled = true;
  btn.innerHTML = '⏳ Submitting...';

  const payload = {
    owner_name:     document.getElementById('ownerName').value,
    business_name:  document.getElementById('businessName').value,
    email:          document.getElementById('ownerEmail').value,
    phone:          document.getElementById('ownerPhone').value,
    sport_type:     selectedSport,
    venue_name:     document.getElementById('venueName').value,
    venue_address:  document.getElementById('venueAddress').value,
    city:           document.getElementById('venueCity').value,
    district:       document.getElementById('venueDistrict').value,
    lat:            document.getElementById('venueLat').value || null,
    lng:            document.getElementById('venueLng').value || null,
    price_per_hour: document.getElementById('priceHour').value,
    open_time:      document.getElementById('openTime').value,
    close_time:     document.getElementById('closeTime').value,
    amenities:      selectedAmenities,
    plan_selected:  selectedPlan,
  };

  try {
    const res  = await fetch('api/list_owner.php', {
      method:  'POST',
      headers: {'Content-Type':'application/json'},
      body:    JSON.stringify(payload),
    });
    const data = await res.json();
    if (data.status === 'success') {
      document.getElementById('confirmEmail').textContent = payload.email;
      document.querySelector('#step5 .step-nav').style.display = 'none';
      document.getElementById('successState').style.display = 'block';
      document.querySelectorAll('#step5 > *:not(#successState):not(.step-icon-title):not(h2):not(.step-sub)').forEach(el => {
        if (!el.classList.contains('success-state')) el.style.opacity = '0.3';
      });
    } else {
      document.getElementById('submitError').style.display = 'block';
      document.getElementById('submitError').textContent = '❌ ' + data.message;
    }
  } catch (e) {
    document.getElementById('submitError').style.display = 'block';
    document.getElementById('submitError').textContent = '❌ Server error. Please try again.';
  } finally {
    btn.disabled = false;
    btn.innerHTML = '🚀 Submit Application';
  }
}
</script>
</body>
</html>
