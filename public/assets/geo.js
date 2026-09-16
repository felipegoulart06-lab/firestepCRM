(function () {
  let leafletReady = null;

  function loadLeaflet() {
    if (window.L) return Promise.resolve(window.L);
    if (leafletReady) return leafletReady;
    leafletReady = new Promise(function (resolve, reject) {
      const css = document.createElement('link');
      css.rel = 'stylesheet';
      css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
      document.head.appendChild(css);
      const s = document.createElement('script');
      s.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
      s.onload = function () { resolve(window.L); };
      s.onerror = reject;
      document.head.appendChild(s);
    });
    return leafletReady;
  }

  function debounce(fn, ms) {
    let t;
    return function () {
      const args = arguments;
      const ctx = this;
      clearTimeout(t);
      t = setTimeout(function () { fn.apply(ctx, args); }, ms);
    };
  }

  async function search(q) {
    const r = await fetch('/app/geo/search?q=' + encodeURIComponent(q), { credentials: 'same-origin' });
    const j = await r.json();
    return Array.isArray(j.items) ? j.items : [];
  }

  function applyHit(root, hit, qEl) {
    if (root.matches('[data-geo-line]')) {
      if (qEl) qEl.value = hit.address || hit.label || '';
      const lat = root.querySelector('[name="visit_lats[]"]');
      const lng = root.querySelector('[name="visit_lngs[]"]');
      if (lat) lat.value = hit.lat;
      if (lng) lng.value = hit.lng;
      return;
    }
    const set = function (name, val) {
      const el = root.querySelector('[name="' + name + '"]');
      if (el) el.value = val == null ? '' : val;
    };
    set('address', hit.address || hit.label);
    set('city', hit.city);
    set('state', hit.state);
    set('cep', hit.cep);
    set('lat', hit.lat);
    set('lng', hit.lng);
  }

  function renderSuggest(ul, items, onPick) {
    ul.innerHTML = '';
    if (!items.length) {
      ul.hidden = true;
      return;
    }
    items.forEach(function (hit) {
      const li = document.createElement('li');
      li.textContent = hit.label || hit.address;
      li.addEventListener('mousedown', function (e) {
        e.preventDefault();
        onPick(hit);
      });
      ul.appendChild(li);
    });
    ul.hidden = false;
  }

  function bindMap(box, latEl, lngEl) {
    const mapEl = box.querySelector('.geo-map');
    if (!mapEl) return;
    let map, marker;
    function show(lat, lng) {
      lat = parseFloat(lat);
      lng = parseFloat(lng);
      if (isNaN(lat) || isNaN(lng)) return;
      mapEl.hidden = false;
      loadLeaflet().then(function (L) {
        if (!map) {
          map = L.map(mapEl).setView([lat, lng], 16);
          L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap'
          }).addTo(map);
          marker = L.marker([lat, lng], { draggable: true }).addTo(map);
          marker.on('dragend', function () {
            const p = marker.getLatLng();
            if (latEl) latEl.value = p.lat.toFixed(6);
            if (lngEl) lngEl.value = p.lng.toFixed(6);
          });
        } else {
          map.setView([lat, lng], 16);
          marker.setLatLng([lat, lng]);
        }
        setTimeout(function () { map.invalidateSize(); }, 80);
      }).catch(function () {});
    }
    box._geoShowMap = show;
    show(latEl && latEl.value, lngEl && lngEl.value);
  }

  function bindSuggest(root, qEl, ul, onHit) {
    if (!qEl || !ul || qEl.dataset.geoBound) return;
    qEl.dataset.geoBound = '1';
    const run = debounce(async function () {
      const q = qEl.value.trim();
      if (q.length < 3) {
        ul.hidden = true;
        return;
      }
      try {
        const items = await search(q);
        renderSuggest(ul, items, function (hit) {
          ul.hidden = true;
          onHit(hit);
        });
      } catch (e) {
        ul.hidden = true;
      }
    }, 280);
    qEl.addEventListener('input', run);
    qEl.addEventListener('blur', function () {
      setTimeout(function () { ul.hidden = true; }, 180);
    });
  }

  window.bindGeoLine = function (line) {
    if (!line || line.dataset.geoBound) return;
    line.dataset.geoBound = '1';
    const qEl = line.querySelector('.geo-q');
    const ul = line.querySelector('.geo-suggest');
    bindSuggest(line, qEl, ul, function (hit) {
      applyHit(line, hit, qEl);
    });
  };

  function bindGeoBox(box) {
    if (!box || box.dataset.geoBound) return;
    box.dataset.geoBound = '1';
    const qEl = box.querySelector('.geo-q');
    const ul = box.querySelector('.geo-suggest');
    const latEl = box.querySelector('[name="lat"]');
    const lngEl = box.querySelector('[name="lng"]');
    bindMap(box, latEl, lngEl);
    bindSuggest(box, qEl, ul, function (hit) {
      applyHit(box, hit, qEl);
      if (box._geoShowMap) box._geoShowMap(hit.lat, hit.lng);
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-geo-box]').forEach(bindGeoBox);
    document.querySelectorAll('[data-geo-line]').forEach(window.bindGeoLine);
  });
})();
