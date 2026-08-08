// MeroMaidan - Venue Detail Page JS

(function() {
  const params   = new URLSearchParams(window.location.search);
  const slug     = params.get('slug') || 'royal-futsal';
  let venueData  = null;
  let selectedDate = null;
  let selectedSlot = null;
  let selectedPay  = 'cash';

  // ─── INIT ───────────────────────────────────────
  async function init() {
    await loadVenue(slug);
    initCalendar();
  }

  // ─── LOAD VENUE ─────────────────────────────────
  async function loadVenue(slug) {
    try {
      const today = new Date().toISOString().split('T')[0];
      const res   = await fetch(`api/venue_detail.php?slug=${slug}&date=${today}`);
      const data  = await res.json();
      if (data.status !== 'success') throw new Error(data.message || 'Not found');
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

    // Rates
    document.getElementById('rateStandard').textContent = Number(v.price_per_hour).toLocaleString();
    document.getElementById('ratePeak').textContent     = Number(v.price_per_hour * 1.5).toLocaleString();

    // Contact
    if (v.owner_phone) {
      document.getElementById('phoneTxt').textContent = v.owner_phone;
      document.getElementById('venuePhone').href = 'tel:' + v.owner_phone;
      document.getElementById('venueWA').href = 'https://wa.me/977' + v.owner_phone.replace(/^0/, '');
    }
    document.getElementById('hiddenVenueId').value = v.id;

    // Rating
    const rating = parseFloat(v.rating) || 0;
    document.getElementById('ratingScore').textContent  = rating.toFixed(1);
    document.getElementById('reviewsCount').textContent = v.total_reviews + ' reviews';
    document.getElementById('starIcons').textContent    = '★'.repeat(Math.round(rating)) + '☆'.repeat(5 - Math.round(rating));

    // Gallery
    const gallery = document.getElementById('galleryGrid');
    const images  = v.images || [];
    gallery.innerHTML = images.length
      ? images.map(img => `<img src="${img}" alt="${v.name}" loading="lazy">`).join('')
      : '<p style="color:#64748b;font-size:13px;">No photos yet.</p>';

    // Amenities
    const amenGrid = document.getElementById('amenitiesGrid');
    const amenIcons = {'Changing Room':'🚿','Parking':'🚗','CCTV':'📷','Floodlights':'💡','Drinking Water':'💧','First Aid':'🏥','WiFi':'📶','Cafeteria':'☕','Locker Room':'🔒','AC Waiting Area':'❄️','Canteen':'🍽️','Coaching Available':'🎓','Pavilion':'🏛️','Indoor AC':'❄️','Nets':'🥅','Sand Court':'🏖️'};
    amenGrid.innerHTML = (v.amenities || [])
      .map(a => `<span class="amenity-tag">${amenIcons[a] || '✅'} ${a}</span>`)
      .join('') || '<span style="color:#64748b;font-size:13px;">No amenities listed.</span>';

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
    chips.innerHTML = expChips.map(c => `
      <div class="exp-chip">
        <span class="icon">${c.icon}</span>
        <span class="label">${c.label}</span>
      </div>`).join('');

    // Directions
    document.getElementById('btnGetDirections').onclick = () => {
      const lat = v.lat || 27.7172;
      const lng = v.lng || 85.3240;
      window.open(`https://maps.google.com/?q=${lat},${lng}`, '_blank');
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
      const res  = await fetch(`api/venue_detail.php?slug=${slug}&date=${date}`);
      const data = await res.json();
      renderSlots(data.slots || []);
    } catch(e) {
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
    document.getElementById('btnConfirmBooking').disabled = !(selectedDate && selectedSlot);
  }

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
      venue_id:       document.getElementById('hiddenVenueId').value,
      customer_name:  document.getElementById('custName').value,
      customer_phone: document.getElementById('custPhone').value,
      booking_date:   selectedDate,
      start_time:     selectedSlot.start,
      end_time:       selectedSlot.end,
      total_price:    selectedSlot.price,
      payment_method: selectedPay,
    };

    if (selectedPay === 'esewa') {
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = 'esewa/payment.php';
      
      const fields = {
        venue_id: document.getElementById('hiddenVenueId').value,
        customer_name: document.getElementById('custName').value,
        customer_phone: document.getElementById('custPhone').value,
        booking_date: selectedDate,
        start_time: selectedSlot.start,
        end_time: selectedSlot.end,
        total_price: selectedSlot.price
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
  function formatDisplayDate(d) {
    return new Date(d + 'T00:00:00').toLocaleDateString('en-US', {weekday:'short',year:'numeric',month:'short',day:'numeric'});
  }
  window.scrollToDate = function() {
    document.getElementById('date-section').scrollIntoView({behavior:'smooth'});
  };

  init();
})();
