function toggleSide(){ document.querySelector('.sidebar').classList.toggle('open'); }
function closeModal(){
  const url = new URL(location.href);
  ['ver','converter','nova','new','edit','block','convert','delblock','client_id','from'].forEach(k => url.searchParams.delete(k));
  location.href = url.pathname + url.search;
}
document.addEventListener('click', e=>{
  if(e.target.classList.contains('overlay')) closeModal();
});
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
