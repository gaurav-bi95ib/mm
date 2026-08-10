document.addEventListener('DOMContentLoaded', () => {
  let allGrounds   = [];
  let currentSport = 'all';
  let currentRegion= 'all';
  let userLat = null, userLng = null;
  let nearMeActive = false;

  const groundsGrid     = document.getElementById('groundsGrid');
  const resultsCountText= document.getElementById('resultsCountText');
  const sportsGrid      = document.getElementById('sportsGrid');
  const regionGrid      = document.getElementById('regionGrid');
  const nearMeBtn       = document.getElementById('nearMeBtn');
  const searchInput     = document.getElementById('searchInput');
  const mapToggleBtn    = document.getElementById('mapToggleBtn');
  const mapModal        = document.getElementById('mapModal');
  const mapCloseBtn     = document.getElementById('mapCloseBtn');
  const navToggleBtn    = document.getElementById('navToggleBtn');
  const siteNav         = document.getElementById('siteNav');
  const trackedPromotionImpressions = new Set();
  let venueMap = null;
  let venueMarkers = null;

  // ─── RESPONSIVE NAVIGATION ──────────────────────
  navToggleBtn?.addEventListener('click', () => {
    const open = siteNav.classList.toggle('open');
    navToggleBtn.setAttribute('aria-expanded', open ? 'true' : 'false');
    navToggleBtn.setAttribute('aria-label', open ? 'Close menu' : 'Open menu');
    navToggleBtn.textContent = open ? '✕' : '☰';
  });
  siteNav?.addEventListener('click', event => {
    if (event.target.closest('a') && siteNav.classList.contains('open')) navToggleBtn?.click();
  });

  // ─── CMS HERO CAROUSEL ──────────────────────────
  const heroCarousel = document.getElementById('heroCarousel');
  if (heroCarousel) {
    const track = document.getElementById('heroTrack');
    const slides = Array.from(heroCarousel.querySelectorAll('.hero-slide'));
    const dots = Array.from(heroCarousel.querySelectorAll('.hero-dot'));
    const prev = heroCarousel.querySelector('.hero-prev');
    const next = heroCarousel.querySelector('.hero-next');
    const status = document.getElementById('heroStatus');
    const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    let current = 0;
    let timer = null;
    let pointerStart = null;
    let pointerDelta = 0;

    const showSlide = (target, userInitiated = false) => {
      if (slides.length < 2) return;
      current = (target + slides.length) % slides.length;
      track.style.transition = reduceMotion ? 'none' : '';
      track.style.transform = `translate3d(-${current * 100}%,0,0)`;
      slides.forEach((slide, index) => {
        const active = index === current;
        slide.classList.toggle('active', active);
        slide.setAttribute('aria-hidden', active ? 'false' : 'true');
      });
      dots.forEach((dot, index) => {
        const active = index === current;
        dot.classList.toggle('active', active);
        dot.setAttribute('aria-selected', active ? 'true' : 'false');
      });
      if (status && userInitiated) status.textContent = `Slide ${current + 1} of ${slides.length}`;
      restartAutoplay();
    };

    const restartAutoplay = () => {
      window.clearInterval(timer);
      if (!reduceMotion && slides.length > 1 && !heroCarousel.matches(':hover') && !heroCarousel.contains(document.activeElement) && !document.hidden) {
        timer = window.setInterval(() => showSlide(current + 1), 6500);
      }
    };

    prev?.addEventListener('click', () => showSlide(current - 1, true));
    next?.addEventListener('click', () => showSlide(current + 1, true));
    dots.forEach(dot => dot.addEventListener('click', () => showSlide(Number(dot.dataset.slide), true)));
    heroCarousel.addEventListener('keydown', event => {
      if (event.key === 'ArrowLeft') { event.preventDefault(); showSlide(current - 1, true); }
      if (event.key === 'ArrowRight') { event.preventDefault(); showSlide(current + 1, true); }
    });
    heroCarousel.addEventListener('mouseenter', () => window.clearInterval(timer));
    heroCarousel.addEventListener('mouseleave', restartAutoplay);
    heroCarousel.addEventListener('focusin', () => window.clearInterval(timer));
    heroCarousel.addEventListener('focusout', event => { if (!heroCarousel.contains(event.relatedTarget)) restartAutoplay(); });
    document.addEventListener('visibilitychange', restartAutoplay);

    heroCarousel.addEventListener('pointerdown', event => {
      if (event.target.closest('a,button') || slides.length < 2) return;
      pointerStart = event.clientX;
      pointerDelta = 0;
      track.style.transition = 'none';
      heroCarousel.setPointerCapture?.(event.pointerId);
    });
    heroCarousel.addEventListener('pointermove', event => {
      if (pointerStart === null) return;
      pointerDelta = event.clientX - pointerStart;
      track.style.transform = `translate3d(calc(-${current * 100}% + ${pointerDelta}px),0,0)`;
    });
    const finishSwipe = () => {
      if (pointerStart === null) return;
      track.style.transition = '';
      if (Math.abs(pointerDelta) > Math.min(90, heroCarousel.clientWidth * .14)) showSlide(current + (pointerDelta < 0 ? 1 : -1), true);
      else showSlide(current);
      pointerStart = null;
      pointerDelta = 0;
    };
    heroCarousel.addEventListener('pointerup', finishSwipe);
    heroCarousel.addEventListener('pointercancel', finishSwipe);
    restartAutoplay();
    heroCarousel.querySelectorAll('.hero-slide[data-event-promotion] .hero-primary-btn').forEach(link => {
      link.addEventListener('click', () => trackPromotion('event_promotion', link.closest('.hero-slide').dataset.eventPromotion, 'click'));
    });
  }

  // ─── NEAR ME ────────────────────────────────────
  if (nearMeBtn) {
    nearMeBtn.addEventListener('click', () => {
      if (nearMeActive) {
        // Toggle off
        nearMeActive = false;
        userLat = null; userLng = null;
        nearMeBtn.classList.remove('active');
        nearMeBtn.innerHTML = '📍 Near me';
        fetchGrounds();
        return;
      }
      nearMeBtn.innerHTML = '⏳ Locating...';
      nearMeBtn.disabled  = true;
      if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        nearMeBtn.innerHTML = '📍 Near me';
        nearMeBtn.disabled  = false;
        return;
      }
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          userLat = pos.coords.latitude;
          userLng = pos.coords.longitude;
          nearMeActive = true;
          nearMeBtn.innerHTML = '📍 Near me ✓';
          nearMeBtn.classList.add('active');
          nearMeBtn.disabled = false;
          fetchGrounds();
        },
        () => {
          nearMeBtn.innerHTML = '📍 Near me';
          nearMeBtn.disabled  = false;
          alert('Could not get your location. Please allow location access and try again.');
        }
      );
    });
  }

  // ─── SEARCH ─────────────────────────────────────
  if (searchInput) {
    let searchTimer;
    searchInput.addEventListener('input', () => {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(fetchGrounds, 400);
    });
  }

  // ─── FETCH ──────────────────────────────────────
  async function fetchGrounds() {
    if (groundsGrid) groundsGrid.innerHTML = '<div style="text-align:center;padding:40px;color:#64748b;">⏳ Loading venues...</div>';

    let url = `api/grounds.php?sport=${encodeURIComponent(currentSport)}&region=${encodeURIComponent(currentRegion)}`;
    if (userLat !== null) url += `&lat=${userLat}&lng=${userLng}`;
    if (searchInput && searchInput.value.trim()) url += `&search=${encodeURIComponent(searchInput.value.trim())}`;

    try {
      const res  = await fetch(url);
      const data = await res.json();
      if (data.status === 'success') {
        allGrounds = data.grounds;
        renderGrounds(data.grounds);
        renderMapMarkers(data.grounds);
      } else {
        if (groundsGrid) groundsGrid.innerHTML = `<p style="padding:20px;text-align:center;color:#ef4444;">Failed to load venues. Is MySQL running?</p>`;
      }
    } catch (err) {
      console.error('Fetch error:', err);
      if (groundsGrid) groundsGrid.innerHTML = `<p style="padding:20px;text-align:center;color:#ef4444;">⚠ API error. Please ensure XAMPP MySQL is running.</p>`;
    }
  }

  // ─── RENDER ─────────────────────────────────────
  function renderGrounds(grounds) {
    if (!groundsGrid) return;
    if (resultsCountText) {
      const regionLabel = currentRegion === 'all' ? 'Nepal' : currentRegion;
      const nearLabel   = nearMeActive ? ' · sorted by distance' : '';
      resultsCountText.textContent = `Found ${grounds.length} venues in ${regionLabel}${nearLabel}`;
    }

    if (grounds.length === 0) {
      groundsGrid.innerHTML = `
        <div style="text-align:center;padding:40px;background:#fff;border-radius:20px;grid-column:1/-1;">
          <div style="font-size:40px;margin-bottom:12px;">🏟️</div>
          <h4 style="font-size:16px;font-weight:800;color:#0f2740;">No venues found</h4>
          <p style="font-size:12px;color:#64748b;margin-top:4px;">Try a different sport, region, or search term.</p>
        </div>`;
      return;
    }

    groundsGrid.innerHTML = grounds.map(g => {
      const distanceBadge = g.distance_km !== null
        ? `<span class="distance-badge">${g.distance_km} km away</span>`
        : '';
      const sportEmoji = {Football:'⚽',Futsal:'🏟️',Cricket:'🏏',Cricsal:'🎯'}[g.sport_type] || '🏅';
      const fallbackCover = 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=900&q=82';
      const cover = g.cover_image || (g.images && g.images[0]) || fallbackCover;
      const recommendationId = Number(g.is_recommended) === 1 && Number(g.recommended_promotion_id) > 0
        ? Number(g.recommended_promotion_id)
        : 0;
      if (recommendationId && !trackedPromotionImpressions.has(String(recommendationId))) {
        trackedPromotionImpressions.add(String(recommendationId));
        trackPromotion('recommended_venue', recommendationId, 'impression');
      }
      const venueHref = `venue.php?slug=${encodeURIComponent(g.slug)}${recommendationId ? `&recommended=${recommendationId}` : ''}`;
      const amenityIcons = {'Changing Room':'🚿','Parking':'🚗','CCTV':'📹','Floodlights':'💡','Drinking Water':'💧','First Aid':'✚','WiFi':'⌁','Cafeteria':'☕','Locker Room':'▣','AC Waiting Area':'❄','Canteen':'🍽','Coaching Available':'◉','Pavilion':'⌂','Indoor AC':'❄','Nets':'🥅','Sand Court':'◌'};
      const amenityChips = (g.amenities || []).slice(0, 3).map(amenity =>
        `<span class="ground-amenity"><i aria-hidden="true">${amenityIcons[amenity] || '✓'}</i>${escapeHtml(amenity)}</span>`
      ).join('');
      return `
      <article class="ground-card" ${recommendationId ? `data-recommended-id="${recommendationId}"` : ''} onclick="window.location.href='${venueHref}'" tabindex="0" onkeydown="if(event.key==='Enter')this.click()">
        <div class="ground-img-wrap">
          <img src="${escapeHtml(cover)}" alt="${escapeHtml(g.name)}" loading="lazy" onerror="this.onerror=null;this.src='${fallbackCover}'">
          <span class="ground-badge">${escapeHtml(g.capacity||g.sport_type)}</span>
          ${recommendationId ? '<span class="ground-recommended" title="Paid Recommended Venue placement">RECOMMENDED</span>' : ''}
        </div>
        <div class="ground-content">
          <div class="ground-card-top"><span>${sportEmoji} ${escapeHtml(g.sport_type)}</span></div>
          <h3 class="ground-name">${escapeHtml(g.name)}</h3>
          <div class="ground-location">📍 ${escapeHtml(g.address||g.city)}</div>
          <div class="ground-facilities">
            <div class="ground-facilities-head"><span>Popular facilities</span>${distanceBadge}</div>
            <div class="ground-amenities-list">${amenityChips || '<span class="ground-amenity muted"><i aria-hidden="true">✓</i>Details on venue page</span>'}</div>
          </div>
          <div class="ground-card-footer">
            <div class="ground-price"><small>From</small><strong>NPR ${Number(g.price_per_hour).toLocaleString()}</strong><span>/ hour</span></div>
            <button class="ground-book-btn" onclick="event.stopPropagation();window.location.href='${venueHref}'">View slots <span>→</span></button>
          </div>
        </div>
      </article>`;
    }).join('');
    groundsGrid.querySelectorAll('[data-recommended-id]').forEach(card => {
      card.addEventListener('click', () => trackPromotion('recommended_venue', card.dataset.recommendedId, 'click'), {capture:true});
    });
  }

  function trackPromotion(promotionType, promotionId, eventType) {
    if (!promotionId) return;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    fetch('api/track_promotion.php', {method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({promotion_type:promotionType,promotion_id:Number(promotionId),event_type:eventType,csrf_token:csrfToken}),keepalive:true}).catch(() => {});
  }

  function escapeHtml(str) {
    return String(str||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  }

  // ─── LIVE VENUE MAP ─────────────────────────────
  function renderMapMarkers(grounds) {
    if (!venueMap || !window.L) return;
    venueMarkers.clearLayers();
    const bounds = [];
    grounds.forEach(ground => {
      const lat = Number(ground.lat), lng = Number(ground.lng);
      if (!Number.isFinite(lat) || !Number.isFinite(lng)) return;
      const marker = L.marker([lat, lng]).bindPopup(`<div class="map-popup"><strong>${escapeHtml(ground.name)}</strong><span>${escapeHtml(ground.sport_type)} · NPR ${Number(ground.price_per_hour).toLocaleString()}/hr</span><a href="venue.php?slug=${encodeURIComponent(ground.slug)}">View venue →</a></div>`);
      marker.addTo(venueMarkers); bounds.push([lat,lng]);
    });
    if (bounds.length) venueMap.fitBounds(bounds, {padding:[35,35],maxZoom:13});
  }
  function toggleVenueMap(forceOpen) {
    if (!mapModal || !window.L) return;
    const shouldOpen = typeof forceOpen === 'boolean' ? forceOpen : mapModal.hidden;
    mapModal.hidden = !shouldOpen;
    mapToggleBtn?.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
    if (shouldOpen && !venueMap) {
      venueMap = L.map('leafletMap',{scrollWheelZoom:false}).setView([27.7036,85.3199],11);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:18,attribution:'© OpenStreetMap'}).addTo(venueMap);
      venueMarkers = L.layerGroup().addTo(venueMap);
      renderMapMarkers(allGrounds);
    }
    if (shouldOpen) window.setTimeout(() => venueMap?.invalidateSize(), 80);
  }
  mapToggleBtn?.addEventListener('click', () => toggleVenueMap());
  mapCloseBtn?.addEventListener('click', () => toggleVenueMap(false));

  // ─── FILTERS ────────────────────────────────────
  if (sportsGrid) {
    sportsGrid.addEventListener('click', (e) => {
      const btn = e.target.closest('.sport-pill');
      if (!btn) return;
      document.querySelectorAll('.sport-pill').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      currentSport = btn.dataset.sport;
      fetchGrounds();
    });
  }

  if (regionGrid) {
    regionGrid.addEventListener('click', (e) => {
      const btn = e.target.closest('.region-pill');
      if (!btn) return;
      document.querySelectorAll('.region-pill').forEach(p => p.classList.remove('active'));
      btn.classList.add('active');
      currentRegion = btn.dataset.region;
      fetchGrounds();
    });
  }

  // ─── INIT ───────────────────────────────────────
  fetchGrounds();
});
