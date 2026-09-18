(function () {
  const MAPBOX_GL_CSS = 'https://api.mapbox.com/mapbox-gl-js/v3.6.0/mapbox-gl.css';
  const MAPBOX_GL_JS = 'https://api.mapbox.com/mapbox-gl-js/v3.6.0/mapbox-gl.js';
  let mapboxReady = null;

  function mapboxToken() {
    return String(window.MAPBOX_TOKEN || '').trim();
  }

  function withToken(url) {
    const token = mapboxToken();
    if (!token || url.indexOf('mapbox.com') === -1 || url.indexOf('access_token=') !== -1) return { url: url };
    return { url: url + (url.indexOf('?') === -1 ? '?' : '&') + 'access_token=' + encodeURIComponent(token) };
  }

  function loadMapbox() {
    const token = mapboxToken();
    if (!token) return Promise.reject(new Error('MAPBOX_TOKEN vazio'));
    if (window.mapboxgl) {
      window.mapboxgl.accessToken = token;
      return Promise.resolve(window.mapboxgl);
    }
    if (mapboxReady) return mapboxReady;
    mapboxReady = new Promise(function (resolve, reject) {
      if (!document.querySelector('link[data-mapbox-gl]')) {
        const css = document.createElement('link');
        css.rel = 'stylesheet';
        css.href = MAPBOX_GL_CSS;
        css.setAttribute('data-mapbox-gl', '1');
        document.head.appendChild(css);
      }
      const s = document.createElement('script');
      s.src = MAPBOX_GL_JS;
      s.onload = function () {
        if (!window.mapboxgl) {
          reject(new Error('Mapbox GL não carregou'));
          return;
        }
        window.mapboxgl.accessToken = mapboxToken();
        resolve(window.mapboxgl);
      };
      s.onerror = function () { reject(new Error('Falha ao baixar Mapbox GL')); };
      document.head.appendChild(s);
    });
    return mapboxReady;
  }
  window.loadMapbox = loadMapbox;

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
      loadMapbox().then(function (mapboxgl) {
        if (!map) {
          map = new mapboxgl.Map({
            container: mapEl,
            style: 'mapbox://styles/mapbox/streets-v12',
            center: [lng, lat],
            zoom: 16,
            attributionControl: true,
            transformRequest: function (url) { return withToken(url); }
          });
          marker = new mapboxgl.Marker({ draggable: true })
            .setLngLat([lng, lat])
            .addTo(map);
          marker.on('dragend', function () {
            const p = marker.getLngLat();
            if (latEl) latEl.value = p.lat.toFixed(6);
            if (lngEl) lngEl.value = p.lng.toFixed(6);
          });
          map.on('load', function () { map.resize(); });
        } else {
          map.setCenter([lng, lat]);
          map.setZoom(16);
          marker.setLngLat([lng, lat]);
        }
        setTimeout(function () { map.resize(); }, 80);
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

  function showMapError(el, msg) {
    if (!el) return;
    let box = el.parentElement && el.parentElement.querySelector('.cv-empty');
    if (!box) {
      box = document.createElement('div');
      box.className = 'cv-empty';
      el.parentElement?.appendChild(box);
    }
    box.hidden = false;
    box.textContent = msg;
  }

  function bindCoverageMap() {
    const el = document.getElementById('map');
    const raw = document.getElementById('coverage-pins');
    if (!el || !raw) return;
    loadMapbox().then(function (mapboxgl) {
      if (mapboxgl.supported && !mapboxgl.supported()) {
        showMapError(el, 'Este navegador não desenha o mapa. Atualize o Chrome ou o Edge.');
        return;
      }
      const map = new mapboxgl.Map({
        container: el,
        style: 'mapbox://styles/mapbox/streets-v12',
        center: [-47.9292, -15.7801],
        zoom: 4,
        attributionControl: true,
        failIfMajorPerformanceCaveat: false,
        transformRequest: function (url) { return withToken(url); }
      });
      let pins = [];
      try { pins = JSON.parse(raw.textContent || '[]'); } catch (e) { pins = []; }
      const place = function () {
        const bounds = [];
        pins.forEach(function (p) {
          const lat = Number(p && p.lat);
          const lng = Number(p && p.lng);
          if (!p || !isFinite(lat) || !isFinite(lng)) return;
          if (lat === 0 && lng === 0) return;
          if (lat < -35 || lat > 6 || lng < -75 || lng > -32) return;
          const name = p.name || 'Local';
          const phone = p.phone && p.phone !== '—' ? p.phone : 'Telefone não informado';
          const info = p.info || '';
          const html = '<strong>' + name.replace(/</g,'') + '</strong><br>' +
            (p.kindLabel ? p.kindLabel.replace(/</g,'') + '<br>' : '') +
            'Tel.: ' + phone.replace(/</g,'') +
            (info ? '<br>' + info.replace(/</g,'') : '');
          const pin = document.createElement('div');
          pin.className = 'cv-pin cv-' + p.kind;
          pin.innerHTML = '<i></i>';
          new mapboxgl.Marker({ element: pin, anchor: 'bottom' })
            .setLngLat([lng, lat])
            .setPopup(new mapboxgl.Popup({ offset: 16 }).setHTML(html))
            .addTo(map);
          bounds.push([lng, lat]);
        });
        if (bounds.length === 1) map.jumpTo({ center: bounds[0], zoom: 13 });
        else if (bounds.length > 1) {
          const box = bounds.reduce(function (b, ll) { return b.extend(ll); }, new mapboxgl.LngLatBounds(bounds[0], bounds[0]));
          map.fitBounds(box, { padding: 48, maxZoom: 13, duration: 0 });
        }
        map.resize();
      };
      map.on('load', place);
      map.on('error', function (e) {
        const err = e && e.error ? String(e.error.message || e.error) : '';
        if (/token|unauthorized|401|403|not authorized/i.test(err)) {
          showMapError(el, 'O mapa não autorizou o token Mapbox. Confira MAPBOX_ACCESS_TOKEN no servidor e as URLs permitidas no token.');
        }
      });
      setTimeout(function () { map.resize(); }, 120);
    }).catch(function () {
      showMapError(el, 'O mapa não carregou. Falta o token Mapbox no servidor (MAPBOX_ACCESS_TOKEN).');
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-geo-box]').forEach(bindGeoBox);
    document.querySelectorAll('[data-geo-line]').forEach(window.bindGeoLine);
    bindCoverageMap();
  });
})();
