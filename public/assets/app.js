window.FIRESTEP_DEBUG = true;
function fsLog(kind, area, message, extra){
  const tag = '[Firestep '+area+']';
  const args = extra !== undefined ? [tag, message, extra] : [tag, message];
  if (kind === 'error') console.error.apply(console, args);
  else if (kind === 'warn') console.warn.apply(console, args);
  else console.log.apply(console, args);
}
window.addEventListener('error', function(e){
  console.error('[Firestep erro JS]', e.message, {
    arquivo: e.filename,
    linha: e.lineno,
    coluna: e.colno,
    erro: e.error || null,
  });
});
window.addEventListener('unhandledrejection', function(e){
  console.error('[Firestep promise rejeitada]', e.reason);
});
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('[data-fs-log]').forEach(function(el){
    const kind = el.getAttribute('data-fs-log') === 'error' ? 'error' : 'log';
    fsLog(kind, 'flash', el.textContent.trim());
  });
  fsLog('log', 'boot', 'JS carregado', {path: location.pathname, search: location.search});
});

function closeSide(){
  document.querySelector('.sidebar')?.classList.remove('open');
  document.body.classList.remove('nav-open');
}
function toggleSide(){
  const side = document.querySelector('.sidebar');
  if (!side) return;
  side.classList.toggle('open');
  document.body.classList.toggle('nav-open', side.classList.contains('open'));
}
document.addEventListener('keydown', function(e){
  if (e.key !== 'Escape') return;
  if (visibleLayer('.overlay') || visibleLayer('.token-modal') || visibleLayer('.fx-overlay')) return;
  closeSide();
});
document.addEventListener('click', function(e){
  if (!window.matchMedia('(max-width:1100px)').matches) return;
  if (e.target.closest('.sidebar .nav a')) closeSide();
});
function closeModal(){
  const url = new URL(location.href);
  ['ver','converter','nova','new','edit','block','convert','delblock','client_id','from'].forEach(k => url.searchParams.delete(k));
  softNav(url.pathname + url.search, {replace:true});
}
function visibleLayer(sel){
  return [...document.querySelectorAll(sel)].find(el => {
    if (!(el instanceof Element) || el.hidden || el.getAttribute('hidden') !== null) return false;
    const s = getComputedStyle(el);
    return s.display !== 'none' && s.visibility !== 'hidden' && s.pointerEvents !== 'none' && Number(s.opacity) !== 0;
  }) || null;
}
function hoistModal(el){
  if (!el || el.parentElement === document.body) return el;
  document.body.appendChild(el);
  return el;
}
function dropHoistedModals(){
  document.querySelectorAll('body > .overlay, body > .fx-overlay, body > .token-modal').forEach(el => el.remove());
  document.documentElement.classList.remove('is-modal-open');
  document.body.classList.remove('is-modal-open');
}
function lockBehindModal(){
  const overlay = visibleLayer('.overlay')
    || visibleLayer('.token-modal')
    || visibleLayer('.fx-overlay');
  const on = !!overlay;
  document.documentElement.classList.toggle('is-modal-open', on);
  document.body.classList.toggle('is-modal-open', on);
  if (!on) document.body.classList.remove('is-modal-open');
}
function blockScrollBehindModal(e){
  if (!document.body.classList.contains('is-modal-open')) return;
  const t = e.target;
  if (!(t instanceof Element)) return;
  if (t.closest('.overlay-panel, .overlay, .fx-overlay, .fx-filter-panel, .fx-preview-shell, .fx-a4-wrap, .fx-checks-scroll, .cl-panel, .token-modal, .fs-assist')) return;
  e.preventDefault();
}
document.addEventListener('click', function(e){
  const t = e.target;
  if (!(t instanceof Element)) return;
  if (t.closest('[data-close-modal]')) {
    e.preventDefault();
    closeModal();
    return;
  }
  if (t.matches('.overlay, .fx-overlay, .token-modal')) {
    e.preventDefault();
    e.stopPropagation();
  }
}, true);
document.addEventListener('wheel', blockScrollBehindModal, {passive:false, capture:true});
document.addEventListener('touchmove', blockScrollBehindModal, {passive:false, capture:true});
if (document.body) lockBehindModal();
window.addEventListener('pageshow', function(){
  if (window.matchMedia('(min-width:1101px)').matches) closeSide();
  lockBehindModal();
});
window.addEventListener('resize', function(){
  if (window.matchMedia('(min-width:1101px)').matches) closeSide();
});
function watchModals(){
  document.querySelectorAll('.overlay, .fx-overlay, .token-modal').forEach(function(el){
    if (el.dataset.lockWatch) return;
    el.dataset.lockWatch = '1';
    new MutationObserver(lockBehindModal).observe(el, { attributes: true, attributeFilter: ['hidden', 'style'] });
  });
}
function copyTxt(id){ const el=document.getElementById(id); navigator.clipboard.writeText(el.value); }

function maskCpf(v){
  const d = String(v||'').replace(/\D/g,'').slice(0,11);
  if (d.length <= 3) return d;
  if (d.length <= 6) return d.slice(0,3)+'.'+d.slice(3);
  if (d.length <= 9) return d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6);
  return d.slice(0,3)+'.'+d.slice(3,6)+'.'+d.slice(6,9)+'-'+d.slice(9);
}
function maskCnpj(v){
  const d = String(v||'').replace(/\D/g,'').slice(0,14);
  if (d.length <= 2) return d;
  if (d.length <= 5) return d.slice(0,2)+'.'+d.slice(2);
  if (d.length <= 8) return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5);
  if (d.length <= 12) return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8);
  return d.slice(0,2)+'.'+d.slice(2,5)+'.'+d.slice(5,8)+'/'+d.slice(8,12)+'-'+d.slice(12);
}
function bindDocFields(root){
  (root||document).querySelectorAll('[data-doc-group]').forEach(kindWrap=>{
    const form = kindWrap.closest('form') || document;
    if (kindWrap.dataset.bound) return;
    kindWrap.dataset.bound = '1';
    const kindInputs = kindWrap.querySelectorAll('.js-doc-kind');
    const kind = kindInputs[0];
    if (!kind) return;
    const numWrap = form.querySelector('[data-doc-group-number]');
    const num = (numWrap || form).querySelector('.js-doc-number');
    const label = (numWrap || form).querySelector('.js-doc-label');
    if (!num) return;
    const getKind = ()=>{
      if (kind.type === 'radio' || kindInputs.length > 1) {
        const checked = kindWrap.querySelector('.js-doc-kind:checked') || form.querySelector('[name="document_kind"]:checked');
        return checked ? checked.value : '';
      }
      return kind.value;
    };
    const apply = ()=>{
      const k = getKind();
      num.readOnly = !k;
      if (num.getAttribute('data-required') === '1') num.required = !!k;
      num.placeholder = k==='cpf' ? '000.000.000-00' : (k==='cnpj' ? '00.000.000/0001-00' : 'Selecione CPF ou CNPJ');
      num.maxLength = k==='cpf' ? 14 : 18;
      if (label) label.textContent = k==='cpf' ? 'CPF' : (k==='cnpj' ? 'CNPJ' : 'Número do documento');
      if (k==='cpf') num.value = maskCpf(num.value);
      if (k==='cnpj') num.value = maskCnpj(num.value);
      if (!k) num.value = '';
      const body = form.querySelector('#client-kind-body');
      if (body) body.hidden = !k;
      const nameLabel = form.querySelector('#client-name-label');
      if (nameLabel) nameLabel.textContent = k==='cnpj' ? 'Razão social' : 'Nome completo';
      form.querySelectorAll('.js-cnpj-only').forEach(el=>{ el.hidden = k !== 'cnpj'; });
      form.querySelectorAll('.js-cpf-only').forEach(el=>{ el.hidden = k !== 'cpf'; });
      form.querySelectorAll('[data-req-cnpj]').forEach(el=>{ el.required = k === 'cnpj'; });
    };
    kindInputs.forEach(el=> el.addEventListener('change', apply));
    num.addEventListener('input', ()=>{
      const k = getKind();
      if (k==='cpf') num.value = maskCpf(num.value);
      if (k==='cnpj') num.value = maskCnpj(num.value);
    });
    apply();
  });
}
document.addEventListener('DOMContentLoaded', firestepHydrate);
function maskReais(el){
  let d = String(el.value || '').replace(/\D/g,'');
  if (!d) { el.value = ''; return; }
  d = d.replace(/^0+(?=\d)/, '');
  while (d.length < 3) d = '0' + d;
  const cents = d.slice(-2);
  let reais = d.slice(0, -2);
  reais = reais.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
  el.value = reais + ',' + cents;
}
function bindServicePrice(){
  document.querySelectorAll('form[data-service-price]').forEach(function(form){
    if (form.dataset.priceBound) return;
    form.dataset.priceBound = '1';
    const paid = form.querySelector('[data-price-paid]');
    const free = form.querySelector('[data-price-free]');
    const price = form.querySelector('[name="price"]');
    const deposit = form.querySelector('[name="deposit"]');
    const kinds = form.querySelectorAll('[name="no_price_kind"]');
    const has = function(){
      const el = form.querySelector('[name="has_price"]:checked');
      return el ? el.value !== '0' : true;
    };
    const sync = function(){
      const on = has();
      if (paid) paid.hidden = !on;
      if (free) free.hidden = on;
      if (price) price.required = on;
      kinds.forEach(function(r){ r.required = !on; });
    };
    form.querySelectorAll('[name="has_price"]').forEach(function(r){
      r.addEventListener('change', sync);
    });
    [price, deposit].forEach(function(el){
      if (!el) return;
      el.addEventListener('input', function(){ maskReais(el); });
      if (el.value) maskReais(el);
    });
    form.addEventListener('submit', function(e){
      if (has()) {
        const n = String(price && price.value || '').replace(/\D/g,'');
        if (!n || Number(n) < 1) {
          e.preventDefault();
          alert('Informe o preço em reais, a partir de R$ 0,01.');
          price && price.focus();
        }
        return;
      }
      const picked = form.querySelector('[name="no_price_kind"]:checked');
      if (!picked) {
        e.preventDefault();
        alert('Escolha Convênio, Cortesia ou Reunião.');
      }
    });
    sync();
  });
}
function bindHoursGuard(){
  document.querySelectorAll('form[data-hours-guard], form[data-hours]').forEach(function(form){
    if (form.dataset.hoursBound) return;
    form.dataset.hoursBound = '1';
    let hours = {};
    const blob = form.querySelector('.js-hours-json');
    try {
      hours = JSON.parse((blob && blob.textContent) || form.getAttribute('data-hours') || '{}');
    } catch (err) { hours = {}; }
    const hint = form.querySelector('[data-hours-hint]');
    const dateEl = form.querySelector('[name="date"]');
    const startEl = form.querySelector('[name="start"]');
    const svcEl = form.querySelector('[name="service_id"]');
    const dayClosed = function(cfg){
      if (!cfg) return true;
      const c = cfg.closed;
      return c === true || c === 1 || c === '1' || c === 'true';
    };
    const toMin = function(hm, closing){
      hm = String(hm || '').trim();
      if (hm === '24:00' || (closing && (hm === '' || hm === '00:00'))) return 1440;
      const p = hm.split(':');
      const n = (Number(p[0])||0)*60 + (Number(p[1])||0);
      return closing && n === 0 ? 1440 : n;
    };
    const check = function(){
      if (!hint || !dateEl || !startEl) return;
      const date = dateEl.value;
      const start = startEl.value;
      if (!date || !start) { hint.hidden = true; return; }
      const day = new Date(date + 'T12:00:00').getDay();
      const cfg = hours[day] || hours[String(day)] || null;
      const svcVal = svcEl && svcEl.value ? String(svcEl.value) : '';
      const opt = svcEl && svcEl.options[svcEl.selectedIndex];
      const dur = svcVal && opt ? Number(opt.getAttribute('data-duration') || 0) : 0;
      const [sh, sm] = start.split(':').map(Number);
      const s = (sh||0)*60 + (sm||0);
      const e = s + (dur > 0 ? dur : 0);
      if (dayClosed(cfg)) {
        hint.hidden = false;
        hint.textContent = 'Fora do horário de funcionamento (fechado neste dia).';
        return;
      }
      let open = toMin(cfg.start, false);
      let close = toMin(cfg.end, true);
      if (close <= open) close += 1440;
      let out = s < open || (dur > 0 ? e > close : s >= close);
      (cfg.breaks || []).forEach(function(b){
        const bsRaw = String((b && b.start) || '').trim();
        const beRaw = String((b && b.end) || '').trim();
        if (!bsRaw || !beRaw) return;
        const bs = toMin(bsRaw, false);
        const be = toMin(beRaw, true);
        if (be <= bs) return;
        if (s < be && (dur > 0 ? e : s + 1) > bs) out = true;
      });
      hint.hidden = !out;
      if (out) {
        const endLabel = (cfg.end === '00:00' || cfg.end === '24:00') ? '24:00' : cfg.end;
        hint.textContent = 'Fora do horário de funcionamento (' + (cfg.start || '08:00') + '–' + endLabel + '). O término do serviço também precisa caber no expediente.';
      }
    };
    dateEl?.addEventListener('change', check);
    startEl?.addEventListener('change', check);
    svcEl?.addEventListener('change', check);
    check();
  });
}
function bindCommissionBox(){
  document.querySelectorAll('[data-commission-box]').forEach(function(box){
    const form = box.closest('form');
    if (!form || box.dataset.bound) return;
    box.dataset.bound = '1';
    const toggle = form.querySelector('[name="commission_on"]');
    const fields = form.querySelector('[data-commission-fields]');
    const agent = form.querySelector('[name="commission_agent_id"]');
    const type = form.querySelector('[name="commission_type"]');
    const value = form.querySelector('[name="commission_value"]');
    const preview = form.querySelector('[data-commission-preview]');
    const service = form.querySelector('[data-service-select], select[name="service_id"]');
    const servicePrice = function(){
      const opt = service && service.options[service.selectedIndex];
      return opt ? Number(opt.getAttribute('data-price') || 0) : 0;
    };
    const parsedAmount = function(){
      const raw = String(value && value.value || '').replace(/\s/g,'').replace('R$','').replace(/\./g,'').replace(',', '.');
      const n = Number(raw);
      if (!n || n <= 0) return 0;
      const price = servicePrice();
      if ((type && type.value) === 'percent') return Math.round((price * n / 100) * 100) / 100;
      return n;
    };
    const sync = function(){
      const on = !!(toggle && toggle.checked);
      if (fields) fields.hidden = !on;
      if (agent) agent.required = on;
      if (type) type.required = on;
      if (value) value.required = on;
      if (!preview) return;
      if (!on) { preview.textContent = ''; return; }
      const price = servicePrice();
      const amount = parsedAmount();
      if (!price) { preview.textContent = 'Selecione um serviço com preço.'; return; }
      if (!amount) { preview.textContent = 'Serviço: R$ ' + price.toFixed(2).replace('.', ',') + '. Informe o repasse.'; return; }
      if (amount > price) {
        preview.textContent = 'Repasse maior que o serviço (R$ ' + price.toFixed(2).replace('.', ',') + '). Não será possível confirmar.';
        return;
      }
      preview.textContent = 'Repasse de R$ ' + amount.toFixed(2).replace('.', ',') + ' · líquido presumido R$ ' + (price - amount).toFixed(2).replace('.', ',');
    };
    toggle?.addEventListener('change', sync);
    type?.addEventListener('change', sync);
    value?.addEventListener('input', sync);
    service?.addEventListener('change', sync);
    form.addEventListener('submit', function(e){
      if (!(toggle && toggle.checked)) return;
      const price = servicePrice();
      const amount = parsedAmount();
      if (!agent || !agent.value) {
        e.preventDefault();
        alert('Selecione o agente do repasse.');
        return;
      }
      if (amount <= 0 || amount > price) {
        e.preventDefault();
        alert('O repasse/comissão não pode ser zero nem maior que o valor do serviço.');
      }
    });
    sync();
  });
}
function bindExternalVisit(){
  document.querySelectorAll('[data-external-visit]').forEach(function(box){
    const form = box.closest('form');
    if (!form || box.dataset.bound) return;
    box.dataset.bound = '1';
    const toggle = form.querySelector('[name="external_visit"]');
    const fields = form.querySelector('[data-visit-fields]');
    const list = form.querySelector('[data-visit-list]');
    const add = form.querySelector('[data-visit-add]');
    const sync = function(){
      const on = !!(toggle && toggle.checked);
      if (fields) fields.hidden = !on;
      if (!list) return;
      const inputs = list.querySelectorAll('input[name="visit_addresses[]"]');
      inputs.forEach(function(el, i){ el.required = on && i === 0; });
    };
    toggle?.addEventListener('change', sync);
    add?.addEventListener('click', function(e){
      e.preventDefault();
      if (!list) return;
      const wrap = document.createElement('div');
      wrap.className = 'geo-line';
      wrap.setAttribute('data-geo-line', '');
      wrap.innerHTML = '<input class="input geo-q" name="visit_addresses[]" placeholder="Buscar endereço no mapa" autocomplete="off"><ul class="geo-suggest" hidden></ul><input type="hidden" name="visit_lats[]"><input type="hidden" name="visit_lngs[]">';
      list.appendChild(wrap);
      if (window.bindGeoLine) window.bindGeoLine(wrap);
    });
    sync();
  });
}

function bindReportsExplorer(){
  const root = document.getElementById('fx-root');
  if (!root || root.dataset.bound) return;
  root.dataset.bound = '1';
  const pathEl = document.getElementById('fx-path');
  const filters = document.getElementById('fx-filters');
  const preview = document.getElementById('fx-preview');
  const form = document.getElementById('fx-form');
  const err = document.getElementById('fx-err');
  const kindInput = document.getElementById('fx-kind');
  const lock = (on)=>{
    document.documentElement.classList.toggle('is-modal-open', on);
    document.body.classList.toggle('is-modal-open', on);
  };
  if (filters) hoistModal(filters);
  if (preview) hoistModal(preview);
  const openOverlay = (el)=>{ if (el) { el.hidden = false; lock(true); } };
  const closeOverlays = ()=>{
    if (filters) filters.hidden = true;
    if (preview) preview.hidden = true;
    lock(false);
  };
  const toggleFolder = (folder, force)=>{
    const open = force === undefined ? !folder.classList.contains('is-open') : force;
    folder.classList.toggle('is-open', open);
    const kids = folder.querySelector('.fx-kids');
    if (kids) kids.hidden = !open;
  };
  const activePanel = ()=> form?.querySelector('.fx-kind-panel:not([hidden])');
  const showKind = (kind)=>{
    form?.querySelectorAll('.fx-kind-panel').forEach(p=>{
      const on = p.dataset.fxKind === kind;
      p.hidden = !on;
      p.querySelectorAll('input,select,textarea,button').forEach(el=>{ el.disabled = !on; });
    });
    if (kindInput) kindInput.value = kind || '';
  };
  const qsFromForm = ()=>{
    const data = new FormData(form);
    const panel = activePanel();
    panel?.querySelectorAll('.js-fx-all[data-group]').forEach(all=>{
      if (all.checked) data.delete(all.dataset.group === 'users' ? 'users[]' : 'services[]');
    });
    if (!panel?.querySelector('[name=totals]:not(:disabled)')?.checked) data.set('totals', '0');
    const qs = new URLSearchParams();
    for (const [k, v] of data.entries()) {
      if (v === '' || v === null) continue;
      qs.append(k, v);
    }
    return qs;
  };

  root.querySelectorAll('.fx-folder').forEach(folder=>{
    const twist = folder.querySelector('.fx-twist');
    const name = folder.querySelector('.fx-folder-name');
    twist?.addEventListener('click', (e)=>{
      e.stopPropagation();
      if (e.detail > 1) return;
      toggleFolder(folder);
    });
    name?.addEventListener('dblclick', (e)=>{
      e.preventDefault();
      toggleFolder(folder);
    });
    folder.querySelector('.fx-folder-row')?.addEventListener('click', ()=>{
      root.querySelectorAll('.fx-folder-on').forEach(n=> n.classList.remove('fx-folder-on'));
      folder.classList.add('fx-folder-on');
    });
  });

  const openFile = (btn)=>{
    root.querySelectorAll('.fx-file.is-on').forEach(n=> n.classList.remove('is-on'));
    btn.classList.add('is-on');
    const file = btn.dataset.file || 'arquivo.pdf';
    const folder = btn.dataset.folderName || '';
    const kind = btn.dataset.kind || 'agendamentos';
    if (pathEl) {
      pathEl.innerHTML = '';
      pathEl.append(root.dataset.root || 'Relatórios', document.createTextNode(' '));
      const s1 = document.createElement('span'); s1.textContent = '›';
      const s2 = document.createElement('span'); s2.textContent = '›';
      const b = document.createElement('b'); b.textContent = file;
      pathEl.append(s1, ' ' + folder + ' ', s2, ' ', b);
    }
    document.getElementById('fx-filter-title').textContent = file;
    document.getElementById('fx-filter-hint').textContent = btn.dataset.hint || 'Filtros exclusivos deste relatório.';
    showKind(kind);
    const cid = document.getElementById('fx-contract-id');
    if (cid) cid.value = btn.dataset.contractId || '';
    if (err) err.hidden = true;
    openOverlay(filters);
  };

  root.querySelectorAll('.fx-file').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      root.querySelectorAll('.fx-file.is-on').forEach(n=> n.classList.remove('is-on'));
      btn.classList.add('is-on');
    });
    btn.addEventListener('dblclick', (e)=>{
      e.preventDefault();
      openFile(btn);
    });
  });

  form?.addEventListener('change', (e)=>{
    const all = e.target.closest?.('.js-fx-all');
    if (all) {
      const group = all.dataset.group;
      const box = all.closest('.fx-kind-panel')?.querySelector('.js-fx-group[data-group="'+group+'"]');
      if (all.checked && box) box.querySelectorAll('input[type=checkbox]').forEach(i=>{ i.checked = false; });
      return;
    }
    const input = e.target;
    if (input.matches?.('input[name="users[]"], input[name="services[]"]') && input.checked) {
      const group = input.name === 'users[]' ? 'users' : 'services';
      const allBox = input.closest('.fx-kind-panel')?.querySelector('.js-fx-all[data-group="'+group+'"]');
      if (allBox) allBox.checked = false;
    }
  });

  document.querySelectorAll('.js-fx-close').forEach(btn=> btn.addEventListener('click', closeOverlays));

  form?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const kind = (kindInput?.value || '').trim();
    if (!kind) {
      err.hidden = false;
      fsLog('warn', 'relatorios', 'nenhum tipo selecionado para a prévia');
      return;
    }
    err.hidden = true;
    const qs = qsFromForm();
    const res = await fetch('/app/relatorios/preview?' + qs.toString(), { credentials: 'same-origin' });
    if (!res.ok) {
      err.hidden = false;
      err.textContent = 'Não foi possível gerar a prévia.';
      fsLog('error', 'relatorios', 'prévia HTTP '+res.status, qs.toString());
      return;
    }
    const doc = await res.json();
    document.getElementById('fx-preview-title').textContent = doc.title || 'Prévia';
    document.getElementById('fx-preview-meta').textContent = (doc.company || '') + ' · ' + (doc.period || '');
    document.getElementById('fx-download').href = '/app/relatorios/arquivo.pdf?' + qs.toString();
    const wrap = document.getElementById('fx-a4-wrap');
    const esc = (s)=> String(s ?? '').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
    const lh = doc.letterhead;
    let html = '<article class="fx-a4'+(lh && lh.active ? ' has-lh' : '')+(doc.signature && doc.signature.active ? ' has-sig' : '')+'">';
    if (lh && lh.active) {
      html += '<header class="lh-banner" style="background:'+esc(lh.color)+';color:'+esc(lh.ink)+'">';
      if (lh.logo) html += '<img src="'+String(lh.logo).replace(/"/g,'')+'" alt="">';
      html += '<div><strong>'+esc(lh.name)+'</strong>';
      (lh.lines || []).forEach(line=>{ html += '<span>'+esc(line)+'</span>'; });
      html += '</div></header>';
    } else {
      html += '<div class="fx-a4-brand">FirestepCRM</div>';
    }
    html += '<h1>'+esc(doc.title)+'</h1>';
    html += '<p class="fx-a4-sub">'+esc(doc.company)+' · Período '+esc(doc.period)+' · Gerado em '+esc(doc.generated)+'</p>';
    const ct = doc.contract;
    if (ct) {
      html += '<h2>Serviços prestados / agendados</h2>';
      if (!ct.items || !ct.items.length) {
        html += '<p class="fx-a4-empty">Nenhum serviço no período filtrado.</p>';
      } else {
        html += '<table><thead><tr><th>Data</th><th>Cliente</th><th>Serviço</th><th>Duração</th><th>Valor</th><th>Taxa/sinal</th></tr></thead><tbody>';
        const brl = (n)=> Number(n||0).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
        ct.items.forEach(it=>{
          html += '<tr><td>'+esc(it.when)+'</td><td>'+esc(it.client)+'</td><td>'+esc(it.service)+'</td><td>'+esc(it.duration? it.duration+' min':'—')+'</td><td>'+esc(brl(it.price))+'</td><td>'+esc(brl(it.deposit))+'</td></tr>';
        });
        html += '</tbody><tfoot><tr><td colspan="6">Subtotal '+esc(brl(ct.subtotal))+' · Taxas '+esc(brl(ct.fees))+' · Total '+esc(brl(ct.total))+'</td></tr></tfoot></table>';
      }
      html += '<h2>Cláusulas'+(ct.list_name? ' · '+esc(ct.list_name):(ct.segment? ' · '+esc(ct.segment):''))+'</h2>';
      html += '<div class="cl-body">'+(ct.clauses_html || '<p class="fx-a4-empty">Nenhuma cláusula neste contrato.</p>')+'</div>';
    }
    (doc.sections || []).forEach(sec=>{
      html += '<h2>'+esc(sec.title)+'</h2>';
      if (!sec.rows || !sec.rows.length) {
        html += '<p class="fx-a4-empty">Sem registros neste filtro.</p>';
      } else {
        html += '<table><thead><tr>'+(sec.headers||[]).map(h=>'<th>'+esc(h)+'</th>').join('')+'</tr></thead><tbody>';
        sec.rows.forEach(row=>{ html += '<tr>'+row.map(c=>'<td>'+esc(c)+'</td>').join('')+'</tr>'; });
        html += '</tbody>';
        if (sec.foot && sec.foot.length) {
          html += '<tfoot><tr><td colspan="'+(sec.headers||[]).length+'">'+sec.foot.map(esc).join(' · ')+'</td></tr></tfoot>';
        }
        html += '</table>';
      }
    });
    const sg = doc.signature;
    if (sg && sg.active && sg.image) {
      html += '<div class="sig-mark"><img src="'+String(sg.image).replace(/"/g,'')+'" alt=""><small>Assinatura eletrônica</small></div>';
    }
    html += '<div class="fx-a4-foot"><span>'+esc(doc.company)+' · Contrato / relatório</span><span>Página 1</span></div>';
    html += '</article>';
    wrap.innerHTML = html;
    filters.hidden = true;
    openOverlay(preview);
  });
}

function maskCep(v){
  const d = String(v||'').replace(/\D/g,'').slice(0,8);
  return d.length > 5 ? d.slice(0,5)+'-'+d.slice(5) : d;
}
function bindLetterhead(){
  const pal = document.getElementById('lh-palette');
  if (pal && pal.dataset.bound) return;
  if (pal) pal.dataset.bound = '1';
  const color = document.getElementById('lh-color');
  pal?.querySelectorAll('.lh-swatch').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      pal.querySelectorAll('.lh-swatch').forEach(b=> b.classList.remove('is-on'));
      btn.classList.add('is-on');
      if (color) color.value = btn.dataset.color;
    });
  });
  document.querySelectorAll('.js-cep').forEach(el=>{
    el.addEventListener('input', ()=>{ el.value = maskCep(el.value); });
  });
}

function bindClausesEditor(){
  const form = document.getElementById('cl-form');
  const editor = document.getElementById('cl-editor');
  const hold = document.getElementById('cl-templates');
  const raw = document.getElementById('cl-data');
  if (!form || !raw) {
    fsLog('log', 'contratos', 'editor ausente nesta página');
    return;
  }
  if (form.dataset.bound) return;
  form.dataset.bound = '1';
  fsLog('log', 'contratos', 'editor iniciado');
  let data = { lists: [] };
  try { data = JSON.parse(raw.textContent || '{}'); } catch (e) {
    fsLog('error', 'contratos', 'JSON de listas inválido', e);
    data = { lists: [] };
  }
  const lists = Array.isArray(data.lists) ? data.lists : [];
  fsLog('log', 'contratos', 'listas carregadas', lists);
  const box = document.getElementById('cl-lists');
  const empty = document.getElementById('cl-empty');
  const pick = document.getElementById('cl-pick');
  const work = document.getElementById('cl-work');
  const idle = document.getElementById('cl-idle');
  const nameEl = document.getElementById('cl-name');
  const label = document.getElementById('cl-current');
  const newBtn = document.getElementById('cl-new');
  if (!newBtn) fsLog('error', 'contratos', 'botão #cl-new não encontrado');
  if (!pick) fsLog('error', 'contratos', 'painel #cl-pick não encontrado');
  if (!work) fsLog('error', 'contratos', 'painel #cl-work não encontrado');
  let current = '';
  let drafting = false;
  const boxes = ()=> [...form.querySelectorAll('#cl-pick input[type=checkbox]')];
  const selectedIds = ()=> boxes().filter(i=> i.checked).map(i=> i.value);
  const selectedLabels = ()=> boxes().filter(i=> i.checked).map(i=>{
    const b = i.closest('label')?.querySelector('b');
    return (b?.textContent || '').trim();
  }).filter(Boolean);
  const saveCurrent = ()=>{
    if (!current) return;
    const row = lists.find(l=> l.id === current);
    if (!row) {
      fsLog('warn', 'contratos', 'saveCurrent sem linha', current);
      return;
    }
    row.html = editor ? editor.innerHTML : '';
    if (nameEl) row.name = nameEl.value.trim() || row.name || 'Contrato';
    fsLog('log', 'contratos', 'rascunho salvo', {id: current, name: row.name});
  };
  const renderList = ()=>{
    if (!box) {
      fsLog('error', 'contratos', '#cl-lists ausente');
      return;
    }
    box.innerHTML = '';
    lists.forEach(row=>{
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'cl-cat'+(row.id === current ? ' is-on' : '');
      btn.dataset.id = row.id;
      const n = document.createElement('span');
      n.textContent = row.name || 'Contrato';
      const i = document.createElement('i');
      const nAp = (row.appointment_ids || []).length;
      i.textContent = nAp === 1 ? '1 agendamento' : nAp+' agendamentos';
      btn.append(n, i);
      btn.addEventListener('click', ()=> load(row.id));
      box.append(btn);
    });
    if (empty) empty.hidden = lists.length > 0;
    fsLog('log', 'contratos', 'lista renderizada', lists.map(l=> ({id:l.id, name:l.name, appts:(l.appointment_ids||[]).length})));
  };
  const showIdle = ()=>{
    if (idle) idle.hidden = false;
    if (work) work.hidden = true;
    if (pick) pick.hidden = true;
    fsLog('log', 'contratos', 'tela: idle', {idle: !!idle, hiddenIdle: idle ? idle.hidden : null});
  };
  const showPick = (ids)=>{
    if (idle) idle.hidden = true;
    if (work) work.hidden = true;
    if (pick) pick.hidden = false;
    boxes().forEach(i=> { i.checked = (ids || []).includes(i.value); });
    fsLog('log', 'contratos', 'tela: escolher agendamentos', {ids: ids || [], checkboxes: boxes().length, pickHidden: pick ? pick.hidden : null});
  };
  const showWork = ()=>{
    if (idle) idle.hidden = true;
    if (pick) pick.hidden = true;
    if (work) work.hidden = false;
    fsLog('log', 'contratos', 'tela: editor', {workHidden: work ? work.hidden : null});
  };
  const load = (id)=>{
    saveCurrent();
    drafting = false;
    current = id;
    const row = lists.find(l=> l.id === id);
    if (!row) {
      fsLog('error', 'contratos', 'contrato não encontrado ao carregar', id);
      return;
    }
    if (editor) editor.innerHTML = row.html || '';
    if (nameEl) nameEl.value = row.name || '';
    if (label) {
      const n = (row.appointment_ids || []).length;
      label.textContent = n === 1 ? '1 agendamento neste contrato' : n+' agendamentos neste contrato';
    }
    showWork();
    renderList();
    fsLog('log', 'contratos', 'contrato aberto', row);
  };
  const createContract = (ids)=>{
    const names = selectedLabels();
    const auto = names.slice(0, 2).join(', ') + (names.length > 2 ? ' +'+(names.length-2) : '');
    const id = 'c'+Math.random().toString(36).slice(2, 10);
    const row = { id, name: auto || 'Novo contrato', appointment_ids: ids || [], html: '' };
    lists.push(row);
    drafting = false;
    fsLog('log', 'contratos', 'contrato criado', row);
    load(id);
  };
  newBtn?.addEventListener('click', (ev)=>{
    fsLog('log', 'contratos', 'clique Novo contrato', {checkboxes: boxes().length, current, drafting, type: ev.type});
    try {
      saveCurrent();
      if (!boxes().length) {
        createContract([]);
        return;
      }
      drafting = true;
      current = '';
      renderList();
      showPick([]);
    } catch (err) {
      fsLog('error', 'contratos', 'falha no Novo contrato', err);
    }
  });
  document.getElementById('cl-change-appts')?.addEventListener('click', ()=>{
    fsLog('log', 'contratos', 'clique Agendamentos', current);
    saveCurrent();
    const row = lists.find(l=> l.id === current);
    showPick(row ? row.appointment_ids || [] : []);
  });
  document.getElementById('cl-pick-ok')?.addEventListener('click', ()=>{
    const ids = selectedIds();
    fsLog('log', 'contratos', 'clique Usar selecionados', {ids, drafting, current});
    if (!ids.length && boxes().length) {
      fsLog('warn', 'contratos', 'nenhum agendamento marcado');
      alert('Selecione pelo menos um agendamento.');
      return;
    }
    if (drafting || !current) {
      createContract(ids);
      return;
    }
    const row = lists.find(l=> l.id === current);
    if (row) {
      const names = selectedLabels();
      const auto = names.slice(0, 2).join(', ') + (names.length > 2 ? ' +'+(names.length-2) : '');
      row.appointment_ids = ids;
      if (!row.name || row.name === 'Contrato' || row.name === 'Novo contrato') row.name = auto || row.name;
    }
    load(current);
  });
  form.querySelectorAll('[data-cl]').forEach(btn=>{
    btn.addEventListener('click', (e)=>{
      e.preventDefault();
      document.execCommand(btn.getAttribute('data-cl'), false, null);
      editor?.focus();
    });
  });
  form.addEventListener('submit', ()=>{
    saveCurrent();
    hold.value = JSON.stringify({ lists });
    fsLog('log', 'contratos', 'submit salvar', lists);
  });
  renderList();
  if (lists[0]) load(lists[0].id);
  else showIdle();
}
function bindFinancePay(){
  document.querySelectorAll('[data-finance-pay]').forEach(function(form){
    if (form.dataset.payBound) return;
    form.dataset.payBound = '1';
    const sel = form.querySelector('[name="payment_method"]');
    const hint = form.querySelector('[data-invoice-hint]');
    const client = form.querySelector('[name="client_id"]');
    const opt = form.querySelector('[data-client-opt]');
    const dueL = form.querySelector('[data-due-label]');
    const sync = function(){
      const inv = !!(sel && sel.value === 'fatura');
      if (hint) hint.hidden = !inv;
      if (client) client.required = inv;
      if (opt) opt.hidden = inv;
      if (dueL && dueL.textContent !== 'Data') {
        dueL.textContent = inv ? 'Data de pagamento da fatura' : 'Vencimento';
      }
    };
    sel?.addEventListener('change', sync);
    sync();
  });
}

function bindFinanceReceive(){
  const receive = document.getElementById('fin-receive');
  const charge = document.getElementById('fin-charge');
  if (!receive && !charge) return;
  if (receive && !receive.dataset.finBound) {
    receive.dataset.finBound = '1';
    hoistModal(receive);
  }
  if (charge && !charge.dataset.finBound) {
    charge.dataset.finBound = '1';
    hoistModal(charge);
  }
  const extra = document.getElementById('fin-receive-extra');
  const method = document.getElementById('fin-receive-method');
  const doc = document.getElementById('fin-receive-doc');
  const amount = document.getElementById('fin-receive-amount');
  const inst = document.getElementById('fin-receive-inst');
  const needs = { pix:1, cartao:1, transferencia:1 };
  const open = (el)=>{
    if (!el) return;
    el.hidden = false;
    document.documentElement.classList.add('is-modal-open');
    document.body.classList.add('is-modal-open');
  };
  const close = (el)=>{
    if (el) el.hidden = true;
    const rec = document.getElementById('fin-receive');
    const ch = document.getElementById('fin-charge');
    if ((!rec || rec.hidden) && (!ch || ch.hidden)) {
      document.documentElement.classList.remove('is-modal-open');
      document.body.classList.remove('is-modal-open');
    }
  };
  const syncExtra = ()=>{
    const on = !!(method && needs[method.value]);
    if (extra) extra.hidden = !on;
    extra?.querySelectorAll('input').forEach(i=>{ i.disabled = !on; });
    if (doc) doc.required = on;
  };
  if (method && !method.dataset.finSync) {
    method.dataset.finSync = '1';
    method.addEventListener('change', syncExtra);
  }
  document.querySelectorAll('.js-fin-receive').forEach(btn=>{
    if (btn.dataset.finBound) return;
    btn.dataset.finBound = '1';
    btn.addEventListener('click', ()=>{
      const rec = document.getElementById('fin-receive');
      const id = document.getElementById('fin-receive-id');
      if (id) id.value = btn.dataset.id || '';
      const amountEl = document.getElementById('fin-receive-amount');
      const methodEl = document.getElementById('fin-receive-method');
      const instEl = document.getElementById('fin-receive-inst');
      if (amountEl) amountEl.value = btn.dataset.amount || '';
      if (methodEl) methodEl.value = btn.dataset.method || '';
      if (instEl) instEl.value = '1';
      syncExtra();
      open(rec);
    });
  });
  const imgWrap = document.getElementById('fin-charge-img');
  const imgInput = document.getElementById('fin-charge-image');
  const title = document.getElementById('fin-charge-title');
  const desc = document.getElementById('fin-charge-desc');
  const btns = document.getElementById('fin-charge-btns');
  const addBtn = document.getElementById('fin-charge-add');
  const typeOpts = [
    ['REPLY', 'Resposta'],
    ['URL', 'Link'],
    ['CALL', 'Ligar'],
    ['COPY', 'Copiar']
  ];
  const ph = { REPLY: 'Texto enviado ao clicar', URL: 'https://…', CALL: 'Telefone', COPY: 'Texto a copiar' };
  const paintImg = (src)=>{
    if (!imgWrap) return;
    const ok = src && /^(https:\/\/|data:image\/)/i.test(src);
    imgWrap.hidden = !ok;
    imgWrap.innerHTML = ok ? '<img alt="" src="'+String(src).replace(/"/g,'')+'">' : '';
  };
  const syncAdd = ()=>{
    if (addBtn) addBtn.hidden = !!(btns && btns.children.length >= 3);
  };
  const addChargeRow = (btn)=>{
    if (!btns || btns.children.length >= 3) return;
    const row = document.createElement('div');
    row.className = 'wa-btn-row';
    const lab = document.createElement('input');
    lab.className = 'input';
    lab.name = 'charge_btn_text[]';
    lab.maxLength = 20;
    lab.required = true;
    lab.placeholder = 'Texto do botão';
    lab.value = btn?.label || btn?.text || '';
    const sel = document.createElement('select');
    sel.className = 'select';
    sel.name = 'charge_btn_type[]';
    const cur = String(btn?.type || 'REPLY').toUpperCase();
    typeOpts.forEach(([v, l])=>{
      const o = document.createElement('option');
      o.value = v; o.textContent = l;
      if (v === cur) o.selected = true;
      sel.appendChild(o);
    });
    const val = document.createElement('input');
    val.className = 'input';
    val.name = 'charge_btn_value[]';
    val.maxLength = 500;
    val.placeholder = ph[cur] || '';
    val.value = btn?.value || btn?.id || '';
    sel.addEventListener('change', ()=>{ val.placeholder = ph[sel.value] || ''; });
    const rm = document.createElement('button');
    rm.type = 'button';
    rm.className = 'btn btn-ghost';
    rm.setAttribute('aria-label', 'Remover botão');
    rm.textContent = '×';
    rm.addEventListener('click', ()=>{ row.remove(); syncAdd(); });
    row.appendChild(lab);
    row.appendChild(sel);
    row.appendChild(val);
    row.appendChild(rm);
    btns.appendChild(row);
    syncAdd();
  };
  if (imgInput && !imgInput.dataset.finBound) {
    imgInput.dataset.finBound = '1';
    imgInput.addEventListener('input', ()=> paintImg(imgInput.value.trim()));
  }
  if (addBtn && !addBtn.dataset.finBound) {
    addBtn.dataset.finBound = '1';
    addBtn.addEventListener('click', ()=> addChargeRow({ type: 'REPLY', label: '' }));
  }
  document.querySelectorAll('.js-fin-charge').forEach(btn=>{
    if (btn.dataset.finBound) return;
    btn.dataset.finBound = '1';
    btn.addEventListener('click', ()=>{
      let cards = {};
      try { cards = JSON.parse(document.getElementById('fin-charge-data')?.textContent || '{}'); } catch (e) { cards = {}; }
      const card = cards[btn.dataset.id] || {};
      const id = document.getElementById('fin-charge-id');
      if (id) id.value = btn.dataset.id || '';
      const who = document.getElementById('fin-charge-who');
      if (who) who.textContent = (card.name || btn.dataset.name || 'Cliente') + ' · ' + (card.phone || btn.dataset.phone || '') + ' · ' + (card.amount || btn.dataset.amount || '');
      const titleEl = document.getElementById('fin-charge-title');
      const descEl = document.getElementById('fin-charge-desc');
      const imgEl = document.getElementById('fin-charge-image');
      if (titleEl) titleEl.value = card.title || 'Cobrança';
      if (descEl) descEl.value = card.description || '';
      if (imgEl) imgEl.value = card.image || '';
      paintImg(card.image || '');
      const box = document.getElementById('fin-charge-btns');
      if (box) {
        box.innerHTML = '';
        (card.buttons && card.buttons.length ? card.buttons : []).forEach(addChargeRow);
        if (!box.children.length) addChargeRow({ type: 'REPLY', label: 'Já paguei', value: 'ja_paguei' });
      }
      open(document.getElementById('fin-charge'));
    });
  });
  document.querySelectorAll('.js-fin-close').forEach(btn=>{
    if (btn.dataset.finBound) return;
    btn.dataset.finBound = '1';
    btn.addEventListener('click', ()=> close(document.getElementById(btn.dataset.close || '')));
  });
  syncExtra();
}

function bindKanbanStatus(){
  const overlay = document.getElementById('kanban-confirm');
  const form = document.getElementById('kanban-status-form');
  const text = document.getElementById('kanban-confirm-text');
  const idInput = document.getElementById('kanban-status-id');
  const stInput = document.getElementById('kanban-status-value');
  if (!overlay || !form) return;
  if (overlay.dataset.bound) return;
  overlay.dataset.bound = '1';
  hoistModal(overlay);
  let pending = null;
  const esc = (s)=> String(s||'').replace(/[&<>"]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]));
  const lock = (on)=>{
    overlay.hidden = !on;
    document.documentElement.classList.toggle('is-modal-open', on);
    document.body.classList.toggle('is-modal-open', on);
  };
  const revert = ()=>{
    if (!pending) return;
    pending.value = pending.getAttribute('data-current') || pending.value;
    pending = null;
  };
  const close = ()=>{ lock(false); revert(); };
  document.querySelectorAll('.js-kanban-status').forEach(sel=>{
    sel.addEventListener('change', ()=>{
      const next = sel.value;
      const cur = sel.getAttribute('data-current') || '';
      if (!next || next === cur) return;
      pending = sel;
      const label = sel.options[sel.selectedIndex]?.text || next;
      const who = sel.getAttribute('data-who') || 'este agendamento';
      if (text) text.innerHTML = 'Deseja realmente alterar o status de <b>'+esc(who)+'</b> para <b>'+esc(label)+'</b>?';
      lock(true);
    });
  });
  document.getElementById('kanban-no')?.addEventListener('click', close);
  document.getElementById('kanban-yes')?.addEventListener('click', ()=>{
    if (!pending) { close(); return; }
    if (idInput) idInput.value = pending.getAttribute('data-id') || '';
    if (stInput) stInput.value = pending.value;
    pending = null;
    form.submit();
  });
}

function bindCommunicate(){
  const modal = document.getElementById('communicate-modal');
  const raw = document.getElementById('communicate-data');
  if (!modal || !raw) return;
  if (modal.dataset.bound) return;
  modal.dataset.bound = '1';
  let data = {};
  try { data = JSON.parse(raw.textContent || '{}'); } catch (_) { return; }
  hoistModal(modal);
  const form = document.getElementById('communicate-form');
  const dest = document.getElementById('communicate-destination');
  const kind = document.getElementById('communicate-kind');
  const id = document.getElementById('communicate-id');
  const intro = document.getElementById('communicate-intro');
  const outro = document.getElementById('communicate-outro');
  const vars = document.getElementById('communicate-vars');
  const selectedInput = document.getElementById('communicate-selected');
  const preview = document.getElementById('communicate-preview');
  let current = null;
  let selected = new Set();
  const lock = (on)=>{
    modal.hidden = !on;
    document.documentElement.classList.toggle('is-modal-open', on);
    document.body.classList.toggle('is-modal-open', on);
  };
  const close = ()=> lock(false);
  const render = ()=>{
    if (!current) return;
    const lines = [];
    const top = (intro?.value || '').trim();
    if (top) lines.push(top);
    const variableLines = [];
    Object.entries(current.variables || {}).forEach(([key, pair])=>{
      if (selected.has(key)) variableLines.push('*'+String(pair[0] || key)+':* '+String(pair[1] || ''));
    });
    if (variableLines.length) lines.push(variableLines.join('\n'));
    const bottom = (outro?.value || '').trim();
    if (bottom) lines.push(bottom);
    if (preview) preview.textContent = lines.join('\n\n') || 'A mensagem aparecerá aqui.';
    if (selectedInput) selectedInput.value = Array.from(selected).join(',');
    vars?.querySelectorAll('button[data-key]').forEach(btn=>{
      const on = selected.has(btn.dataset.key || '');
      btn.classList.toggle('is-off', !on);
      btn.setAttribute('aria-pressed', on ? 'true' : 'false');
    });
  };
  document.querySelectorAll('.js-communicate').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      current = data[btn.dataset.communicateKey || ''];
      if (!current) return;
      selected = new Set(current.selected || Object.keys(current.variables || {}));
      if (kind) kind.value = current.kind || '';
      if (id) id.value = current.id || '';
      if (intro) intro.value = current.intro || '';
      if (outro) outro.value = current.outro || '';
      if (dest) dest.textContent = String(current.name || 'Destinatário')+' · '+String(current.phone || 'sem telefone');
      if (vars) {
        vars.innerHTML = '';
        Object.entries(current.variables || {}).forEach(([key, pair])=>{
          const chip = document.createElement('button');
          chip.type = 'button';
          chip.className = 'communicate-chip';
          chip.dataset.key = key;
          chip.textContent = String(pair[0] || key)+': '+String(pair[1] || '');
          chip.addEventListener('click', ()=>{
            selected.has(key) ? selected.delete(key) : selected.add(key);
            render();
          });
          vars.appendChild(chip);
        });
      }
      render();
      lock(true);
      intro?.focus();
    });
  });
  document.querySelectorAll('.js-communicate-close').forEach(btn=> btn.addEventListener('click', close));
  intro?.addEventListener('input', render);
  outro?.addEventListener('input', render);
  form?.addEventListener('submit', ()=>{
    form.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled');
  });
}

function firestepHydrate(){
  closeSide();
  if (window.matchMedia('(min-width:1101px)').matches) {
    document.body.classList.remove('nav-open');
  }
  lockBehindModal();
  watchModals();
  bindDocFields(document);
  bindExternalVisit();
  bindHoursGuard();
  bindCommissionBox();
  bindServicePrice();
  bindReportsExplorer();
  bindLetterhead();
  bindFinancePay();
  try { bindClausesEditor(); }
  catch (err) { fsLog('error', 'contratos', 'bindClausesEditor quebrou', err); }
  bindFinanceReceive();
  bindKanbanStatus();
  bindCommunicate();
  if (typeof window.fsAssistMount === 'function') window.fsAssistMount();
  if (typeof window.firestepGeo === 'function') window.firestepGeo();
}

let fsNavAbort = null;
function fsAppUrl(href){
  try {
    const url = new URL(href, location.href);
    if (url.origin !== location.origin) return null;
    if (!url.pathname.startsWith('/app') && !url.pathname.startsWith('/master')) return null;
    if (/\.(pdf|zip|csv|png|jpe?g|svg)$/i.test(url.pathname)) return null;
    if (url.pathname === '/logout' || url.pathname.startsWith('/login')) return null;
    return url;
  } catch (_) {
    return null;
  }
}
function applyFetchedPage(doc, href, replace){
  const next = doc.querySelector('.wrap');
  const cur = document.querySelector('.wrap');
  if (!next || !cur) throw new Error('layout');
  dropHoistedModals();
  cur.replaceWith(next);
  document.title = doc.title || document.title;
  doc.querySelectorAll('style').forEach(function(s){
    if (!s.textContent || s.textContent.indexOf('--primary') === -1) return;
    let theme = document.getElementById('fs-theme');
    if (!theme) {
      theme = document.createElement('style');
      theme.id = 'fs-theme';
      document.head.appendChild(theme);
    }
    theme.textContent = s.textContent;
  });
  if (replace) history.replaceState({soft:1}, '', href);
  else history.pushState({soft:1}, '', href);
  firestepHydrate();
  window.scrollTo(0, 0);
}
function softNav(href, opts){
  const url = fsAppUrl(href);
  if (!url) {
    location.href = href;
    return Promise.resolve();
  }
  opts = opts || {};
  document.body.classList.add('is-soft-nav');
  if (fsNavAbort) fsNavAbort.abort();
  fsNavAbort = new AbortController();
  return fetch(url.href, {
    credentials: 'same-origin',
    headers: { 'Accept': 'text/html', 'X-Requested-With': 'FirestepNav' },
    signal: fsNavAbort.signal,
  }).then(function(res){
    if (!res.ok) throw new Error('http');
    return res.text();
  }).then(function(html){
    applyFetchedPage(new DOMParser().parseFromString(html, 'text/html'), url.href, !!opts.replace);
  }).catch(function(err){
    if (err && err.name === 'AbortError') return;
    location.href = url.href;
  }).finally(function(){
    document.body.classList.remove('is-soft-nav');
  });
}
document.addEventListener('click', function(e){
  if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
  const a = e.target instanceof Element ? e.target.closest('a[href]') : null;
  if (!a || a.hasAttribute('download') || a.dataset.fullNav === '1') return;
  const target = a.getAttribute('target');
  if (target && target !== '_self') return;
  const href = a.getAttribute('href') || '';
  if (!href || href.startsWith('#') || href.startsWith('mailto:') || href.startsWith('javascript:')) return;
  if (!fsAppUrl(a.href)) return;
  e.preventDefault();
  softNav(a.href);
}, true);
document.addEventListener('submit', function(e){
  const form = e.target;
  if (!(form instanceof HTMLFormElement) || e.defaultPrevented) return;
  if (form.dataset.fullNav === '1' || form.querySelector('input[type=file]')) return;
  const method = String(form.getAttribute('method') || 'get').toLowerCase();
  if (method !== 'get') return;
  if (form.target && form.target !== '_self') return;
  const url = fsAppUrl(form.action || location.href);
  if (!url) return;
  const data = new FormData(form);
  url.search = '';
  data.forEach(function(value, key){
    if (value instanceof File) return;
    url.searchParams.append(key, String(value));
  });
  e.preventDefault();
  softNav(url.href);
});
window.addEventListener('popstate', function(){
  softNav(location.href, {replace:true});
});
