// Front-end behaviour for the Day-Visit Planner
(function () {
  const meta = name => (document.querySelector('meta[name="' + name + '"]') || {}).content || '';
  const BASE = meta('base-url');
  const CSRF = meta('csrf');

  // ---- Mobile menu --------------------------------------------------
  const menuBtn = document.getElementById('menuBtn');
  const sidebar = document.getElementById('sidebar');
  if (menuBtn && sidebar) {
    menuBtn.addEventListener('click', () => sidebar.classList.toggle('open'));
    document.querySelector('.main').addEventListener('click', () => sidebar.classList.remove('open'));
  }

  // ---- Toast message ------------------------------------------------
  const toastEl = document.getElementById('toast');
  let toastTimer;
  function toast(text) {
    if (!toastEl) return;
    toastEl.textContent = text;
    toastEl.hidden = false;
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => { toastEl.hidden = true; }, 2800);
  }

  function setBadge(count) {
    ['planBadge', 'planBadgeTop'].forEach(id => {
      const b = document.getElementById(id);
      if (!b) return;
      b.textContent = count;
      b.hidden = count === 0;
    });
    const dash = document.getElementById('dashPlan');
    if (dash) dash.textContent = count;
  }

  // ---- Add to itinerary without reloading the page ---------------------
  document.querySelectorAll('.js-add').forEach(btn => {
    btn.addEventListener('click', async () => {
      const body = new FormData();
      body.append('csrf', CSRF);
      body.append('id', btn.dataset.id);
      btn.disabled = true;
      try {
        const res = await fetch(BASE + '/api/plan_add.php', { method: 'POST', body });
        const data = await res.json();
        if (data.ok) {
          setBadge(data.count);
          toast(data.message);
          btn.textContent = '✓ In itinerary';
        } else {
          toast(data.message || 'Could not add this place.');
          btn.disabled = false;
        }
      } catch (err) {
        toast('Network problem. Please try again.');
        btn.disabled = false;
      }
    });
  });

  // ---- Photo gallery on the attraction page ---------------------------
  const hero = document.getElementById('heroPic');
  document.querySelectorAll('.thumbs img').forEach(img => {
    img.addEventListener('click', () => {
      if (hero) {
        hero.style.backgroundImage = 'url("' + img.dataset.full + '")';
        hero.textContent = '';
      }
      document.querySelectorAll('.thumbs img').forEach(i => i.classList.remove('on'));
      img.classList.add('on');
    });
  });

  // ---- Confirm dialogs and auto-submitting filters ----------------------
  document.querySelectorAll('form[data-confirm]').forEach(f => {
    f.addEventListener('submit', ev => {
      if (!confirm(f.dataset.confirm)) ev.preventDefault();
    });
  });
  document.querySelectorAll('[data-autosubmit]').forEach(el => {
    el.addEventListener('change', () => el.form.submit());
  });

  // ---- Live weather (Open-Meteo, free, no key) -------------------------
  const codes = {
    0: ['☀️', 'Clear sky'], 1: ['🌤️', 'Mostly clear'], 2: ['⛅', 'Partly cloudy'], 3: ['☁️', 'Cloudy'],
    45: ['🌫️', 'Foggy'], 48: ['🌫️', 'Foggy'], 51: ['🌦️', 'Light drizzle'], 53: ['🌦️', 'Drizzle'], 55: ['🌧️', 'Heavy drizzle'],
    61: ['🌧️', 'Light rain'], 63: ['🌧️', 'Rain'], 65: ['🌧️', 'Heavy rain'], 80: ['🌦️', 'Showers'], 81: ['🌧️', 'Showers'],
    82: ['⛈️', 'Heavy showers'], 95: ['⛈️', 'Thunderstorm'], 96: ['⛈️', 'Thunderstorm'], 99: ['⛈️', 'Thunderstorm']
  };
  document.querySelectorAll('[data-weather]').forEach(async box => {
    const lat = box.dataset.lat, lng = box.dataset.lng;
    try {
      const api = 'https://api.open-meteo.com/v1/forecast?latitude=' + lat + '&longitude=' + lng +
        '&current=temperature_2m,weather_code&daily=precipitation_probability_max&timezone=auto&forecast_days=1';
      const res = await fetch(api);
      const d = await res.json();
      const code = d.current.weather_code;
      const info = codes[code] || ['🌡️', 'Weather'];
      const rain = d.daily && d.daily.precipitation_probability_max ? d.daily.precipitation_probability_max[0] : null;
      let tip = '';
      if (rain !== null && rain >= 60) tip = ' Bring an umbrella - rain is likely.';
      else if (d.current.temperature_2m >= 32) tip = ' It is hot - carry water and sun protection.';
      box.innerHTML = '<span class="big">' + info[0] + '</span><div><b>' + Math.round(d.current.temperature_2m) +
        '°C</b> · ' + info[1] + '<br><small>' + (rain !== null ? 'Chance of rain today: ' + rain + '%.' : '') + tip + '</small></div>';
    } catch (err) {
      box.innerHTML = '<span class="big">🌡️</span><div><small>Live weather is unavailable right now (check your internet connection).</small></div>';
    }
  });
})();
