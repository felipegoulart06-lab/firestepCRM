function toggleSide(){ document.querySelector('.sidebar').classList.toggle('open'); }
function closeModal(){
  const url = new URL(location.href);
  ['ver','converter','nova','new','edit','block','convert','delblock','client_id','from'].forEach(k => url.searchParams.delete(k));
  location.href = url.pathname + url.search;
}
function lockBehindModal(){
  const overlay = document.querySelector('.overlay') || document.querySelector('.token-modal:not([hidden])');
  const on = !!overlay;
  document.documentElement.classList.toggle('is-modal-open', on);
  document.body.classList.toggle('is-modal-open', on);
}
function blockScrollBehindModal(e){
  if (!document.body.classList.contains('is-modal-open')) return;
  if (e.target.closest('.overlay-panel')) return;
  e.preventDefault();
}
document.addEventListener('click', e=>{
  if(e.target.classList.contains('overlay')) closeModal();
});
document.addEventListener('wheel', blockScrollBehindModal, {passive:false, capture:true});
document.addEventListener('touchmove', blockScrollBehindModal, {passive:false, capture:true});
document.addEventListener('DOMContentLoaded', lockBehindModal);
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
    const kind = kindWrap.querySelector('.js-doc-kind');
    if (!kind) return;
    const numWrap = form.querySelector('[data-doc-group-number]');
    const num = (numWrap || form).querySelector('.js-doc-number');
    const label = (numWrap || form).querySelector('.js-doc-label');
    if (!num) return;
    const apply = ()=>{
      const k = kind.value;
      num.readOnly = !k;
      if (num.getAttribute('data-required') === '1') num.required = !!k;
      num.placeholder = k==='cpf' ? '000.000.000-00' : (k==='cnpj' ? '00.000.000/0001-00' : 'Selecione CPF ou CNPJ');
      num.maxLength = k==='cpf' ? 14 : 18;
      if (label) label.textContent = k==='cpf' ? 'CPF' : (k==='cnpj' ? 'CNPJ' : 'Número do documento');
      if (k==='cpf') num.value = maskCpf(num.value);
      if (k==='cnpj') num.value = maskCnpj(num.value);
      if (!k) num.value = '';
    };
    kind.addEventListener('change', apply);
    num.addEventListener('input', ()=>{
      if (kind.value==='cpf') num.value = maskCpf(num.value);
      if (kind.value==='cnpj') num.value = maskCnpj(num.value);
    });
    apply();
  });
}
document.addEventListener('DOMContentLoaded', ()=> bindDocFields(document));

function bindReportsExplorer(){
  const root = document.getElementById('fx-root');
  if (!root) return;
  const pathEl = document.getElementById('fx-path');
  const filters = document.getElementById('fx-filters');
  const preview = document.getElementById('fx-preview');
  const form = document.getElementById('fx-form');
  const err = document.getElementById('fx-err');
  const typesAll = document.getElementById('fx-types-all');
  const usersAll = document.getElementById('fx-users-all');
  const servicesAll = document.getElementById('fx-services-all');
  const lock = (on)=>{
    document.documentElement.classList.toggle('is-modal-open', on);
    document.body.classList.toggle('is-modal-open', on);
  };
  const openOverlay = (el)=>{ el.hidden = false; lock(true); };
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
  const setChecks = (box, on)=>{
    box.querySelectorAll('input[type=checkbox]').forEach(i=>{ i.checked = on; });
  };
  const qsFromForm = ()=>{
    const data = new FormData(form);
    if (usersAll?.checked) data.delete('users[]');
    if (servicesAll?.checked) data.delete('services[]');
    if (!form.querySelector('[name=totals]')?.checked) data.set('totals', '0');
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
    const kind = btn.dataset.kind || 'atendimentos';
    if (pathEl) {
      pathEl.innerHTML = '';
      pathEl.append(root.dataset.root || 'Relatórios', document.createTextNode(' '));
      const s1 = document.createElement('span'); s1.textContent = '›';
      const s2 = document.createElement('span'); s2.textContent = '›';
      const b = document.createElement('b'); b.textContent = file;
      pathEl.append(s1, ' ' + folder + ' ', s2, ' ', b);
    }
    document.getElementById('fx-filter-title').textContent = file;
    document.getElementById('fx-filter-hint').textContent = btn.dataset.hint || 'Defina o recorte e o que entra no documento.';
    form.querySelectorAll('input[name="types[]"]').forEach(i=>{ i.checked = i.value === kind; });
    if (typesAll) typesAll.checked = false;
    if (kind === 'cliente_resumo') form.querySelector('[name=client_summary]').checked = true;
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

  typesAll?.addEventListener('change', ()=> setChecks(document.getElementById('fx-types'), typesAll.checked));
  usersAll?.addEventListener('change', ()=>{
    if (usersAll.checked) setChecks(document.getElementById('fx-users'), false);
  });
  servicesAll?.addEventListener('change', ()=>{
    if (servicesAll.checked) setChecks(document.getElementById('fx-services'), false);
  });
  document.getElementById('fx-users')?.addEventListener('change', (e)=>{
    if (e.target.matches('input[name="users[]"]') && e.target.checked && usersAll) usersAll.checked = false;
  });
  document.getElementById('fx-services')?.addEventListener('change', (e)=>{
    if (e.target.matches('input[name="services[]"]') && e.target.checked && servicesAll) servicesAll.checked = false;
  });

  document.querySelectorAll('.js-fx-close').forEach(btn=> btn.addEventListener('click', closeOverlays));
  [filters, preview].forEach(el=>{
    el?.addEventListener('click', (e)=>{ if (e.target === el) closeOverlays(); });
  });
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Escape' && (filters && !filters.hidden || preview && !preview.hidden)) closeOverlays();
  });

  form?.addEventListener('submit', async (e)=>{
    e.preventDefault();
    const types = [...form.querySelectorAll('input[name="types[]"]:checked')];
    if (!types.length) {
      err.hidden = false;
      return;
    }
    err.hidden = true;
    const qs = qsFromForm();
    const res = await fetch('/app/relatorios/preview?' + qs.toString(), { credentials: 'same-origin' });
    if (!res.ok) {
      err.hidden = false;
      err.textContent = 'Não foi possível gerar a prévia.';
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
    html += '</article>';
    wrap.innerHTML = html;
    filters.hidden = true;
    openOverlay(preview);
  });
}
document.addEventListener('DOMContentLoaded', bindReportsExplorer);

function maskCep(v){
  const d = String(v||'').replace(/\D/g,'').slice(0,8);
  return d.length > 5 ? d.slice(0,5)+'-'+d.slice(5) : d;
}
function bindLetterhead(){
  const pal = document.getElementById('lh-palette');
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
document.addEventListener('DOMContentLoaded', bindLetterhead);
