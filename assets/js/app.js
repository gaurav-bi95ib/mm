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

  // ─── NEAR ME ────────────────────────────────────
  if (nearMeBtn) {
    nearMeBtn.addEventListener('click', () => {
      if (nearMeActive) {
        // Toggle off
        nearMeActive = false;
        userLat = null; userLng = null;
        nearMeBtn.classList.remove('active');
        nearMeBtn.innerHTML = '📍 Near Me';
        fetchGrounds();
        return;
      }
      nearMeBtn.innerHTML = '⏳ Locating...';
      nearMeBtn.disabled  = true;
      if (!navigator.geolocation) {
        alert('Geolocation is not supported by your browser.');
        nearMeBtn.innerHTML = '📍 Near Me';
        nearMeBtn.disabled  = false;
        return;
      }
      navigator.geolocation.getCurrentPosition(
        (pos) => {
          userLat = pos.coords.latitude;
          userLng = pos.coords.longitude;
          nearMeActive = true;
          nearMeBtn.innerHTML = '📍 Near Me ✓';
          nearMeBtn.classList.add('active');
          nearMeBtn.disabled = false;
          fetchGrounds();
        },
        () => {
          nearMeBtn.innerHTML = '📍 Near Me';
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
        ? `<span style="background:#dcfce7;color:#166534;font-size:10px;font-weight:700;padding:3px 8px;border-radius:50px;">📍 ${g.distance_km} km</span>`
        : '';
      const sportEmoji = {Football:'⚽',Futsal:'🏟️',Cricket:'🏏',Cricsal:'🎯'}[g.sport_type] || '🏅';
      const cover = g.cover_image || (g.images && g.images[0]) || 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=600&q=80';
      return `
      <div class="ground-card" onclick="window.location.href='venue.php?slug=${encodeURIComponent(g.slug)}'" style="cursor:pointer;">
        <div class="ground-img-wrap">
          <img src="${cover}" alt="${escapeHtml(g.name)}" loading="lazy">
          <span class="ground-badge">${escapeHtml(g.capacity||g.sport_type)}</span>
          ${g.featured ? '<span style="position:absolute;top:10px;left:10px;background:#f9631c;color:#fff;font-size:9px;font-weight:700;padding:3px 8px;border-radius:50px;">⭐ FEATURED</span>' : ''}
        </div>
        <div class="ground-content">
          <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:4px;">
            <h3 class="ground-name">${escapeHtml(g.name)}</h3>
            <span style="font-size:12px;font-weight:700;color:#f59e0b;">★ ${parseFloat(g.rating||0).toFixed(1)}</span>
          </div>
          <div class="ground-location">📍 ${escapeHtml(g.address||g.city)}</div>
          <div style="display:flex;gap:6px;margin:8px 0;flex-wrap:wrap;align-items:center;">
            <span style="background:#f1f5f9;color:#64748b;font-size:10px;font-weight:700;padding:3px 8px;border-radius:50px;">${sportEmoji} ${g.sport_type}</span>
            ${distanceBadge}
          </div>
          <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px;">
            <div style="font-size:13px;font-weight:800;color:#0f2740;">NPR ${Number(g.price_per_hour).toLocaleString()}<span style="font-size:10px;font-weight:500;color:#64748b;">/hr</span></div>
            <button class="ground-book-btn" onclick="event.stopPropagation();window.location.href='venue.php?slug=${encodeURIComponent(g.slug)}'">
              Book Now 📅
            </button>
          </div>
        </div>
      </div>`;
    }).join('');
  }

  function escapeHtml(str) {
    return String(str||'').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }

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
