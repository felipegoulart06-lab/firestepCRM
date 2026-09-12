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
