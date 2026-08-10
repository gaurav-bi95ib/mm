<?php
require_once __DIR__ . '/api/db.php';
if (session_status() === PHP_SESSION_NONE) session_start();
$playerName = $_SESSION['player_name'] ?? '';
$playerEmail = $_SESSION['player_email'] ?? '';
$isPlayer = !empty($_SESSION['player_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title id="pageTitle">Venue - MeroMaidan</title>
  <meta name="description" content="Book your sports ground slot online - real-time availability, instant confirmation.">
  <meta name="csrf-token" content="<?=csrfToken()?>">
  <link rel="stylesheet" href="assets/css/venue.css?v=20260810-coupon1">
</head>
<body>

<!-- Header -->
<header class="venue-header">
  <a href="index.php" class="logo-wrap">
    <svg class="logo-badge-svg" viewBox="0 0 100 100" fill="none" xmlns="http://www.w3.org/2000/svg">
      <rect width="100" height="100" rx="28" fill="#1BB955"/>
      <path d="M25 20H55C68 20 75 27 75 37C75 44 70 50 62 52C72 55 78 62 78 72C78 83 69 90 53 90H25V20Z" fill="#1BB955"/>
      <path fill-rule="evenodd" clip-rule="evenodd" d="M32 28H54C62 28 67 32 67 38C67 43 62 47 54 47H32V28ZM32 54H56C65 54 70 59 70 66C70 73 64 78 55 78H32V54Z" stroke="white" stroke-width="6"/>
      <circle cx="50" cy="50" r="10" fill="white"/>
      <circle cx="50" cy="50" r="4" fill="#1BB955"/>
    </svg>
    <div class="logo-text">Mero<span>Maidan</span></div>
  </a>
  <nav>
    <a href="index.php">Home</a>
    <a href="index.php#services" class="active">Find Venues</a>
    <a href="index.php#how-it-works">How It Works</a>
    <a href="about.php">About Us</a>
  </nav>
  <div class="header-actions">
    <?php if ($isPlayer): ?>
      <a href="player/index.php" class="btn-signup" style="background:#1BB955;">⚽ My Dashboard</a>
    <?php else: ?>
      <a href="auth/login.php" class="btn-login">Log In</a>
      <a href="auth/register.php" class="btn-signup">Create Account</a>
    <?php endif; ?>
  </div>
</header>

<!-- Breadcrumb -->
<div class="breadcrumb-bar">
  <a href="index.php">Home</a>
  <span class="sep">›</span>
  <a href="index.php#services" id="breadSport">Futsal</a>
  <span class="sep">›</span>
  <span class="current" id="breadName">Loading...</span>
</div>

<!-- Main Content -->
<div class="venue-wrapper">

  <!-- Hero Info Row -->
  <div class="venue-hero-info">
    <div class="venue-title-block">
      <h1 id="venueName">Loading venue...</h1>
      <div class="venue-location-tag">
        <span>📍</span>
        <span id="venueAddress">-</span>
      </div>
      <span class="venue-sport-badge" id="venueSport">Futsal</span>
    </div>
    <div class="venue-action-row">
      <button class="btn-share" id="shareVenueBtn" type="button" title="Share this venue" aria-label="Share this venue">↗</button>
      <a href="#booking-panel" class="btn-book-now">Book a slot <span>→</span></a>
    </div>
  </div>

  <!-- Content Grid -->
  <div class="venue-content-grid">

    <!-- LEFT COLUMN -->
    <div>

      <!-- Hero Image -->
      <div class="venue-hero-img" id="venueHeroImg">
        <img id="heroCoverImg" src="" alt="Venue">
        <div class="venue-hours-bar">
          <div class="hour-chip">
            <div class="dot"></div>
            Open: <strong id="openTime">6:00 am</strong>
          </div>
          <div class="hour-chip">
            <div class="dot" style="background:#ef4444"></div>
            Close: <strong id="closeTime">11:00 pm</strong>
          </div>
        </div>
      </div>

      <!-- Venue Experience Chips -->
      <div class="experience-section">
        <div class="section-label" id="experienceLabel">Venue highlights</div>
        <div class="experience-chips" id="experienceChips">
          <!-- Dynamic -->
        </div>
      </div>

      <!-- Availability Buttons -->
      <div class="avail-section" style="margin-top:20px;">
        <div class="avail-buttons">
          <button class="btn-avail primary" onclick="scrollToDate()">
            🟢 Real-Time Availability
          </button>
          <button class="btn-avail secondary" id="btnGetDirections">
            📍 Secure Location
          </button>
        </div>
      </div>

      <!-- Date Picker -->
      <div class="date-section" id="date-section">
        <div class="section-title-main">Choose Date</div>
        <div class="calendar-wrapper">
          <div class="cal-header">
            <button class="cal-nav" id="prevMonth">‹</button>
            <h3 id="calMonthYear">July 2026</h3>
            <button class="cal-nav" id="nextMonth">›</button>
          </div>
          <div class="cal-grid" id="calGrid"></div>
        </div>
      </div>

      <!-- Time Slots -->
      <div class="slots-section" style="margin-top:28px;">
        <div class="section-title-main">Choose Time</div>
        <div id="slotsContainer">
          <div class="slots-loading">📅 Select a date to view available time slots</div>
        </div>
      </div>

      <!-- Booking Note -->
      <div class="booking-note">
        <span class="icon">ℹ</span>
        <div>
          <strong>Availability guide:</strong> Available times can be selected online. Slots marked “Full” are already reserved—choose another time or contact the venue for help.
        </div>
      </div>

      <!-- Gallery -->
      <div class="gallery-section">
        <div class="section-title-main">Photo Gallery</div>
        <div class="gallery-grid" id="galleryGrid">
          <!-- Dynamic -->
        </div>
      </div>

      <!-- Amenities -->
      <div class="amenities-section">
        <div class="content-section-head">
          <div><span class="content-kicker">What’s included</span><div class="section-title-main">Facilities & Amenities</div><p>Useful features available when you visit this venue.</p></div>
          <span class="facility-status"><i></i> Venue facilities</span>
        </div>
        <div class="amenities-grid" id="amenitiesGrid">
          <!-- Dynamic -->
        </div>
      </div>

    </div><!-- /LEFT COLUMN -->

    <!-- RIGHT COLUMN - Sticky Booking Panel -->
    <div>

      <!-- Rates Card -->
      <div class="rates-card" id="ratesCard">
        <div class="rates-card-title"><span>रू</span> Venue rate</div>
        <div class="rate-item">
          <div class="rate-label">Starting hourly rate</div>
          <div class="rate-value">
            <sup>NPR</sup><span id="rateStandard">1,500</span><sub>/hr</sub>
          </div>
        </div>
        <div class="rates-contact-box">
          <p>Need help or planning a group booking? Contact the venue directly.</p>
          <a href="tel:" class="phone-link" id="venuePhone">
            📞 <span id="phoneTxt">-</span>
          </a>
          <a href="https://wa.me/" class="phone-link" id="venueWA" style="margin-top:6px;">
            💬 WhatsApp
          </a>
        </div>
      </div>

      <!-- Booking Confirm Panel -->
      <div class="booking-confirm-panel" id="booking-panel" style="margin-top:20px;">
        <h3>📅 Confirm Booking</h3>

        <div class="booking-summary-row">
          <span>Date</span>
          <span class="val" id="summaryDate">Not selected</span>
        </div>
        <div class="booking-summary-row">
          <span>Time Slot</span>
          <span class="val" id="summarySlot">Not selected</span>
        </div>
        <div class="booking-summary-row">
          <span>Duration</span>
          <span class="val">1 Hour</span>
        </div>
        <div class="booking-summary-row price-row" id="summarySubtotalRow" hidden>
          <span>Subtotal</span>
          <span class="val" id="summarySubtotal">NPR 0</span>
        </div>
        <div class="booking-summary-row price-row discount-row" id="summaryDiscountRow" hidden>
          <span>Coupon discount</span>
          <span class="val" id="summaryDiscount">- NPR 0</span>
        </div>
        <div class="booking-summary-row price-row" id="summaryFeesRow" hidden>
          <span>Fees</span>
          <span class="val" id="summaryFees">NPR 0</span>
        </div>
        <div class="booking-summary-row price-row" id="summaryTaxRow" hidden>
          <span>Taxes</span>
          <span class="val" id="summaryTax">NPR 0</span>
        </div>
        <div class="booking-total">
          <span id="summaryTotalLabel">Total</span>
          <span id="summaryTotal">NPR —</span>
        </div>

        <form id="bookingForm" style="margin-top:16px;">
          <input type="hidden" id="hiddenVenueId">
          <input type="hidden" id="hiddenStartTime">
          <input type="hidden" id="hiddenEndTime">
          <input type="hidden" id="hiddenPrice">

          <div class="form-group">
            <label class="form-label" for="custName">Your Name</label>
            <input type="text" id="custName" class="form-input" placeholder="e.g. Anil Maharjan" value="<?= htmlspecialchars($playerName) ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label" for="custPhone">Phone Number</label>
            <input type="tel" id="custPhone" class="form-input" placeholder="98XXXXXXXX" required>
          </div>
          <div class="coupon-box">
            <label class="form-label" for="couponCode">Coupon / Promo Code</label>
            <div class="coupon-input-row"><input type="text" id="couponCode" class="form-input" maxlength="50" placeholder="Enter code"><button type="button" id="applyCouponBtn">Apply</button></div>
            <p id="couponMessage" aria-live="polite">Optional. Invalid coupons will not change your price.</p>
          </div>
          <div class="form-group">
            <label class="form-label">Payment Method</label>
            <div class="payment-methods">
              <div class="pay-method-btn selected" data-pay="cash">💵 Cash</div>
              <div class="pay-method-btn" data-pay="esewa">🟢 eSewa</div>
            </div>
          </div>

          <button type="submit" class="btn-confirm-booking" id="btnConfirmBooking" disabled>
            📅 Confirm Booking
          </button>
          <p style="font-size:11px;color:#64748b;text-align:center;margin-top:8px;">
            🔒 Secure & instant confirmation
          </p>
        </form>
      </div>

    </div><!-- /RIGHT COLUMN -->
  </div><!-- /grid -->
</div><!-- /wrapper -->

<!-- Success Overlay -->
<div class="success-overlay" id="successOverlay">
  <div class="success-card">
    <div class="success-icon">🎉</div>
    <h2>Booking Confirmed!</h2>
    <p>Your slot has been reserved successfully.</p>
    <div class="booking-ref-badge" id="successRef">MM000000</div>
    <p style="font-size:12px;color:#64748b;">Show this reference at the venue</p>
    <div style="margin-top:16px;font-size:13px;color:#64748b;" id="successDetails"></div>
    <button class="btn-done" onclick="document.getElementById('successOverlay').classList.remove('show')">
      Done ✓
    </button>
  </div>
</div>

<!-- Footer -->
<footer class="venue-footer">
  <p>© <?=date('Y')?> MeroMaidan · Nepal's smart sports venue booking platform.</p>
</footer>

<script src="assets/js/venue.js?v=20260810-coupon1"></script>
</body>
</html>
