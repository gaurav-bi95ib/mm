// MeroMaidan - Venue Detail Page JS

(function() {
  const params   = new URLSearchParams(window.location.search);
  const slug     = params.get('slug') || 'royal-futsal';
  const eventPromotionId = Number(params.get('event')) || null;
  const recommendedPromotionId = Number(params.get('recommended')) || null;
  const couponFromUrl = (params.get('coupon') || '').trim().toUpperCase();
  let venueData  = null;
  let selectedDate = null;
  let selectedSlot = null;
  let selectedPay  = 'cash';
  let appliedPricing = null;

  // ─── INIT ───────────────────────────────────────
  async function init() {
    await loadVenue(slug);
    initCalendar();
    const couponInput = document.getElementById('couponCode');
    if (couponInput && couponFromUrl) couponInput.value = couponFromUrl;
  }

  // ─── LOAD VENUE ─────────────────────────────────
  async function loadVenue(slug) {
    try {
      const today = new Date().toISOString().split('T')[0];
      const res   = await fetch(`api/venue_detail.php?slug=${encodeURIComponent(slug)}&date=${encodeURIComponent(today)}`);
      const data  = await res.json();
      if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Unable to load venue');
      venueData = data.venue;
      renderVenueMeta(venueData);
      selectedDate = today;
      renderSlots(data.slots);
    } catch (err) {
      console.error(err);
      document.getElementById('venueName').textContent = 'Venue not found';
    }
  }

  function renderVenueMeta(v) {
    document.title = v.name + ' – MeroMaidan';
    document.getElementById('pageTitle').textContent = v.name + ' – MeroMaidan';
    document.getElementById('venueName').textContent  = v.name;
    document.getElementById('venueAddress').textContent = v.address;
    document.getElementById('venueSport').textContent   = v.sport_type;
    document.getElementById('breadName').textContent    = v.name;
    document.getElementById('breadSport').textContent   = v.sport_type;
    document.getElementById('openTime').textContent     = formatTime(v.open_time);
    document.getElementById('closeTime').textContent    = formatTime(v.close_time);

    // Hero image
    const img = document.getElementById('heroCoverImg');
    img.src = v.cover_image || (v.images && v.images[0]) || '';
    img.alt = v.name;
    img.onerror = () => {
      img.onerror = null;
      img.src = 'https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=1400&q=84';
    };

    // Rates
    document.getElementById('rateStandard').textContent = Number(v.price_per_hour).toLocaleString();

    // Contact
    if (v.owner_phone) {
      document.getElementById('phoneTxt').textContent = v.owner_phone;
      document.getElementById('venuePhone').href = 'tel:' + v.owner_phone;
      document.getElementById('venueWA').href = 'https://wa.me/977' + v.owner_phone.replace(/^0/, '');
    }
    document.getElementById('hiddenVenueId').value = v.id;

    // Gallery
    const gallery = document.getElementById('galleryGrid');
    const images  = v.images || [];
    gallery.innerHTML = images.length
      ? images.map(image => `<img src="${escapeHtml(image)}" alt="${escapeHtml(v.name)}" loading="lazy" onerror="this.onerror=null;this.src='https://images.unsplash.com/photo-1574629810360-7efbbe195018?auto=format&fit=crop&w=700&q=80'">`).join('')
      : '<p style="color:#64748b;font-size:13px;">No photos yet.</p>';

    // Amenities
    const amenGrid = document.getElementById('amenitiesGrid');
    const amenIcons = {'Changing Room':'🚿','Parking':'🚗','CCTV':'📹','Floodlights':'💡','Drinking Water':'💧','First Aid':'✚','WiFi':'⌁','Cafeteria':'☕','Locker Room':'▣','AC Waiting Area':'❄','Canteen':'🍽','Coaching Available':'◉','Pavilion':'⌂','Indoor AC':'❄','Nets':'🥅','Sand Court':'◌'};
    amenGrid.innerHTML = (v.amenities || [])
      .map(a => `<article class="amenity-card"><span class="amenity-icon" aria-hidden="true">${amenIcons[a] || '✓'}</span><span class="amenity-copy"><strong>${escapeHtml(a)}</strong><small>Available at this venue</small></span><span class="amenity-check" aria-label="Available">✓</span></article>`)
      .join('') || '<div class="amenities-empty"><span>⌁</span><strong>Facility details are being updated.</strong><small>Contact the venue if you need to confirm a specific facility.</small></div>';

    // Experience chips based on capacity/sport
    const chips = document.getElementById('experienceChips');
    const capMap = {
      '5-a-side':  [{icon:'⚽',label:'5-a-side'},{icon:'🌿',label:'Turf Surface'},{icon:'💡',label:'Floodlights'},{icon:'📅',label:'Easy Booking'}],
      '7-a-side':  [{icon:'⚽',label:'7-a-side'},{icon:'🌿',label:'Natural Grass'},{icon:'💡',label:'Floodlights'},{icon:'📅',label:'Easy Booking'}],
      '11-a-side': [{icon:'⚽',label:'11-a-side'},{icon:'🌿',label:'Full Pitch'},{icon:'💡',label:'Floodlights'},{icon:'📅',label:'Easy Booking'}],
      'Indoor Net': [{icon:'🎯',label:'Indoor Net'},{icon:'❄️',label:'Indoor AC'},{icon:'🏏',label:'Cricket Net'},{icon:'📅',label:'Easy Booking'}],
      'Match Ground': [{icon:'🏏',label:'Match Ground'},{icon:'🌿',label:'Natural Turf'},{icon:'📌',label:'Boundary Marked'},{icon:'📅',label:'Easy Booking'}],
    };
    const expChips = capMap[v.capacity] || [{icon:'🏟️',label:v.capacity},{icon:'💡',label:'Floodlights'},{icon:'📅',label:'Easy Booking'}];
    const experienceLabel = document.getElementById('experienceLabel');
    if (experienceLabel) experienceLabel.textContent = v.sport_type + ' venue highlights';
    chips.innerHTML = expChips.map(c => `
      <div class="exp-chip">
        <span class="icon">${c.icon}</span>
        <span class="label">${escapeHtml(c.label)}</span>
      </div>`).join('');

    // Directions
    document.getElementById('btnGetDirections').onclick = () => {
      const lat = v.lat || 27.7172;
      const lng = v.lng || 85.3240;
      window.open(`https://maps.google.com/?q=${lat},${lng}`, '_blank');
    };

    const shareButton = document.getElementById('shareVenueBtn');
    if (shareButton) shareButton.onclick = async () => {
      try {
        if (navigator.share) await navigator.share({title: document.title, url: window.location.href});
        else {
          await navigator.clipboard.writeText(window.location.href);
          const original = shareButton.textContent;
          shareButton.textContent = '✓';
          shareButton.title = 'Link copied';
          setTimeout(() => { shareButton.textContent = original; shareButton.title = 'Share this venue'; }, 1600);
        }
      } catch (error) {
        if (error.name !== 'AbortError') console.error('Unable to share venue:', error);
      }
    };
  }

  // ─── CALENDAR ───────────────────────────────────
  let calYear, calMonth;
  function initCalendar() {
    const now = new Date();
    calYear  = now.getFullYear();
    calMonth = now.getMonth();
    renderCalendar();
    document.getElementById('prevMonth').onclick = () => { calMonth--; if(calMonth < 0){calMonth=11;calYear--;} renderCalendar(); };
    document.getElementById('nextMonth').onclick = () => { calMonth++; if(calMonth > 11){calMonth=0;calYear++;} renderCalendar(); };
  }

  function renderCalendar() {
    const monthNames = ['January','February','March','April','May','June','July','August','September','October','November','December'];
    document.getElementById('calMonthYear').textContent = monthNames[calMonth] + ' ' + calYear;
    const grid = document.getElementById('calGrid');
    const dayNames = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
    let html = dayNames.map(d => `<div class="cal-day-name">${d}</div>`).join('');
    const firstDay = new Date(calYear, calMonth, 1).getDay();
    const daysInMonth = new Date(calYear, calMonth + 1, 0).getDate();
    const today = new Date(); today.setHours(0,0,0,0);
    for (let i = 0; i < firstDay; i++) html += `<div class="cal-day empty"></div>`;
    for (let d = 1; d <= daysInMonth; d++) {
      const dateStr = `${calYear}-${String(calMonth+1).padStart(2,'0')}-${String(d).padStart(2,'0')}`;
      const dateObj = new Date(calYear, calMonth, d);
      const isPast  = dateObj < today;
      const isToday = dateObj.getTime() === today.getTime();
      const isSel   = dateStr === selectedDate;
      html += `<div class="cal-day ${isPast?'past':''} ${isToday&&!isSel?'today':''} ${isSel?'selected':''}"
                    data-date="${dateStr}"
                    onclick="${isPast?'':''}">${d}</div>`;
    }
    grid.innerHTML = html;
    grid.querySelectorAll('.cal-day:not(.empty):not(.past)').forEach(el => {
      el.addEventListener('click', () => selectDate(el.dataset.date, el));
    });
  }

  async function selectDate(dateStr, el) {
    selectedDate = dateStr;
    selectedSlot = null;
    resetCoupon(false);
    document.querySelectorAll('.cal-day').forEach(d => d.classList.remove('selected'));
    el.classList.add('selected');
    updateSummary();
    await loadSlots(dateStr);
  }

  // ─── SLOTS ──────────────────────────────────────
  async function loadSlots(date) {
    const container = document.getElementById('slotsContainer');
    container.innerHTML = '<div class="slots-loading">⏳ Loading slots...</div>';
    try {
      const res  = await fetch(`api/venue_detail.php?slug=${encodeURIComponent(slug)}&date=${encodeURIComponent(date)}`);
      const data = await res.json();
      if (!res.ok || data.status !== 'success') {
        throw new Error(data.message || 'Unable to load slots');
      }
      renderSlots(data.slots || []);
    } catch(e) {
      console.error('Slot loading failed:', e);
      container.innerHTML = '<div class="slots-loading" style="color:#ef4444;">Failed to load slots.</div>';
    }
  }

  function renderSlots(slots) {
    const container = document.getElementById('slotsContainer');
    if (!slots || slots.length === 0) {
      container.innerHTML = '<div class="slots-loading">No slots available for this day.</div>';
      return;
    }
    const html = `<div class="slots-grid">${slots.map(s => {
      const booked  = s.is_booked == 1;
      const cls     = booked ? 'booked' : 'available';
      const status  = booked ? 'Full' : 'Available';
      return `<div class="slot-chip ${cls}" 
                   data-start="${s.start_time}" data-end="${s.end_time}" data-price="${s.price}"
                   onclick="${booked ? '' : 'selectSlot(this)'}">
        <span class="slot-time">${formatTime(s.start_time)} – ${formatTime(s.end_time)}</span>
        <span class="slot-price">NPR ${Number(s.price).toLocaleString()}</span>
        <span class="slot-status">${status}</span>
      </div>`;
    }).join('')}</div>`;
    container.innerHTML = html;
  }

  window.selectSlot = function(el) {
    document.querySelectorAll('.slot-chip.available').forEach(c => c.classList.remove('selected'));
    el.classList.add('selected');
    selectedSlot = {
      start: el.dataset.start,
      end:   el.dataset.end,
      price: el.dataset.price
    };
    resetCoupon(false);
    document.getElementById('hiddenStartTime').value = selectedSlot.start;
    document.getElementById('hiddenEndTime').value   = selectedSlot.end;
    document.getElementById('hiddenPrice').value     = selectedSlot.price;
    updateSummary();
    document.getElementById('booking-panel').scrollIntoView({behavior:'smooth', block:'center'});
  };

  function updateSummary() {
    document.getElementById('summaryDate').textContent  = selectedDate ? formatDisplayDate(selectedDate) : 'Not selected';
    document.getElementById('summarySlot').textContent  = selectedSlot ? formatTime(selectedSlot.start) + ' – ' + formatTime(selectedSlot.end) : 'Not selected';
    document.getElementById('summaryTotal').textContent = selectedSlot ? 'NPR ' + Number(selectedSlot.price).toLocaleString() : 'NPR —';
    if (appliedPricing) document.getElementById('summaryTotal').textContent = 'NPR ' + Number(appliedPricing.final_amount).toLocaleString();
    document.getElementById('btnConfirmBooking').disabled = !(selectedDate && selectedSlot);
  }

  function renderPriceBreakdown() {
    const subtotalRow = document.getElementById('summarySubtotalRow');
    const discountRow = document.getElementById('summaryDiscountRow');
    const feesRow = document.getElementById('summaryFeesRow');
    const taxRow = document.getElementById('summaryTaxRow');
    const totalLabel = document.getElementById('summaryTotalLabel');
    if (!appliedPricing) {
      subtotalRow.hidden = true;
      discountRow.hidden = true;
      feesRow.hidden = true;
      taxRow.hidden = true;
      totalLabel.textContent = 'Total';
      return;
    }
    const fees = Number(appliedPricing.fees_amount || 0);
    const taxes = Number(appliedPricing.tax_amount || 0);
    subtotalRow.hidden = false;
    discountRow.hidden = false;
    feesRow.hidden = fees === 0;
    taxRow.hidden = taxes === 0;
    totalLabel.textContent = 'Final Total';
    document.getElementById('summarySubtotal').textContent = 'NPR ' + Number(appliedPricing.base_price).toLocaleString();
    document.getElementById('summaryDiscount').textContent = '- NPR ' + Number(appliedPricing.discount_amount).toLocaleString();
    document.getElementById('summaryFees').textContent = 'NPR ' + fees.toLocaleString();
    document.getElementById('summaryTax').textContent = 'NPR ' + taxes.toLocaleString();
  }

  function resetCoupon(clearInput = false) {
    appliedPricing = null;
    const input = document.getElementById('couponCode');
    const message = document.getElementById('couponMessage');
    if (clearInput && input) input.value = '';
    if (message) {
      message.className = '';
      message.textContent = 'Optional. Invalid coupons will not change your price.';
    }
    renderPriceBreakdown();
    updateSummary();
  }

  document.getElementById('couponCode').addEventListener('input', () => {
    if (appliedPricing) resetCoupon(false);
  });

  document.getElementById('applyCouponBtn').addEventListener('click', async () => {
    const input = document.getElementById('couponCode');
    const message = document.getElementById('couponMessage');
    const button = document.getElementById('applyCouponBtn');
    const code = input.value.trim().toUpperCase();
    input.value = code;
    if (!selectedSlot) {
      message.className = 'error';
      message.textContent = 'Choose a date and time before applying a coupon.';
      return;
    }
    if (!code) {
      resetCoupon(false);
      message.className = 'error';
      message.textContent = 'Enter a coupon code first.';
      return;
    }
    button.disabled = true;
    button.textContent = 'Checking...';
    try {
      const response = await fetch('api/validate_coupon.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({
          venue_id: Number(document.getElementById('hiddenVenueId').value),
          booking_date: selectedDate,
          start_time: selectedSlot.start,
          coupon_code: code,
          customer_phone: document.getElementById('custPhone').value.trim(),
          csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || ''
        })
      });
      const result = await response.json();
      if (!response.ok || result.status !== 'success') throw new Error(result.message || 'Coupon could not be applied.');
      appliedPricing = result.pricing;
      message.className = 'success';
      message.textContent = `${code} applied · NPR ${Number(appliedPricing.discount_amount).toLocaleString()} discount · Final total NPR ${Number(appliedPricing.final_amount).toLocaleString()}.`;
      renderPriceBreakdown();
      updateSummary();
    } catch (error) {
      appliedPricing = null;
      message.className = 'error';
      message.textContent = error.message || 'Coupon could not be applied.';
      renderPriceBreakdown();
      updateSummary();
    } finally {
      button.disabled = false;
      button.textContent = 'Apply';
    }
  });

  // ─── PAYMENT METHODS ────────────────────────────
  document.querySelectorAll('.pay-method-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.pay-method-btn').forEach(b => b.classList.remove('selected'));
      btn.classList.add('selected');
      selectedPay = btn.dataset.pay;
    });
  });

  // ─── BOOKING FORM ───────────────────────────────
  document.getElementById('bookingForm').addEventListener('submit', async (e) => {
    e.preventDefault();
    if (!selectedDate || !selectedSlot) return;

    const btn = document.getElementById('btnConfirmBooking');
    btn.disabled = true;
    btn.textContent = '⏳ Confirming...';

    const payload = {
      csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || '',
      venue_id:       document.getElementById('hiddenVenueId').value,
      customer_name:  document.getElementById('custName').value,
      customer_phone: document.getElementById('custPhone').value,
      booking_date:   selectedDate,
      start_time:     selectedSlot.start,
      end_time:       selectedSlot.end,
      total_price:    selectedSlot.price,
      coupon_code:    appliedPricing ? appliedPricing.coupon_code : '',
      event_promotion_id: eventPromotionId,
      recommended_promotion_id: recommendedPromotionId,
      payment_method: selectedPay,
    };

    if (selectedPay === 'esewa') {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'esewa/payment.php';
      
      const fields = {
        csrf_token: document.querySelector('meta[name="csrf-token"]')?.content || '',
        venue_id: document.getElementById('hiddenVenueId').value,
        customer_name: document.getElementById('custName').value,
        customer_phone: document.getElementById('custPhone').value,
        booking_date: selectedDate,
        start_time: selectedSlot.start,
        end_time: selectedSlot.end,
        total_price: selectedSlot.price,
        coupon_code: appliedPricing ? appliedPricing.coupon_code : '',
        event_promotion_id: eventPromotionId || '',
        recommended_promotion_id: recommendedPromotionId || ''
      };
      
      for (const [k, v] of Object.entries(fields)) {
        const inp = document.createElement('input');
        inp.type = 'hidden';
        inp.name = k;
        inp.value = v;
        form.appendChild(inp);
      }
      document.body.appendChild(form);
      form.submit();
      return;
    }

    try {
      const res    = await fetch('api/book.php', {
        method:  'POST',
        headers: {'Content-Type':'application/json'},
        body:    JSON.stringify(payload),
      });
      const result = await res.json();
      if (result.status === 'success') {
        showSuccess(result.booking);
        await loadSlots(selectedDate);
        document.getElementById('bookingForm').reset();
        selectedSlot = null;
        resetCoupon(true);
        updateSummary();
      } else {
        alert('❌ ' + result.message);
      }
    } catch(err) {
      alert('Failed to submit booking. Please try again.');
    } finally {
      btn.disabled = false;
      btn.innerHTML = '📅 Confirm Booking';
    }
  });

  function showSuccess(booking) {
    document.getElementById('successRef').textContent = booking.ref;
    document.getElementById('successDetails').innerHTML = `
      <strong>📅 Date:</strong> ${formatDisplayDate(booking.date)}<br>
      <strong>⏰ Time:</strong> ${booking.time}<br>
      <strong>💰 Amount:</strong> NPR ${Number(booking.total_price).toLocaleString()}`;
    document.getElementById('successOverlay').classList.add('show');
  }

  // ─── HELPERS ────────────────────────────────────
  function formatTime(t) {
    if (!t) return '-';
    const [h, m] = t.split(':');
    const hr = parseInt(h);
    const ampm = hr >= 12 ? 'PM' : 'AM';
    const h12 = hr % 12 || 12;
    return `${h12}:${m} ${ampm}`;
  }
  function escapeHtml(value) {
    return String(value).replace(/[&<>'"]/g, char => ({'&':'&amp;','<':'&lt;','>':'&gt;',"'":'&#39;','"':'&quot;'}[char]));
  }
  function formatDisplayDate(d) {
    return new Date(d + 'T00:00:00').toLocaleDateString('en-US', {weekday:'short',year:'numeric',month:'short',day:'numeric'});
  }
  window.scrollToDate = function() {
    document.getElementById('date-section').scrollIntoView({behavior:'smooth'});
  };

  init();
})();
