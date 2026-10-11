// SR Computer app.js
document.addEventListener('DOMContentLoaded', ()=>{
  const sidebar=document.getElementById('sidebar');
  const toggle=document.getElementById('sidebarToggle');
  const overlay=document.getElementById('overlay');
  if(toggle){
    toggle.addEventListener('click', ()=>{
      sidebar.classList.toggle('open');
      if(overlay) overlay.classList.toggle('show');
    });
  }
  if(overlay) overlay.addEventListener('click', ()=>{
    sidebar.classList.remove('open');
    overlay.classList.remove('show');
  });
  // Auto hide alerts
  setTimeout(()=>{ document.querySelectorAll('.alert-auto').forEach(a=>a.style.display='none'); }, 4000);

  // Bulk checkbox
  const master=document.getElementById('checkAll');
  if(master){
    master.addEventListener('change', e=>{
      document.querySelectorAll('.row-check').forEach(c=>c.checked=e.target.checked);
      updateBulkBar();
    });
  }
  document.querySelectorAll('.row-check').forEach(c=>c.addEventListener('change', updateBulkBar));
  function updateBulkBar(){
    const count=document.querySelectorAll('.row-check:checked').length;
    const bar=document.getElementById('bulkBar');
    if(!bar) return;
    if(count>0){ bar.classList.remove('d-none'); bar.querySelector('.count').textContent=count; }
    else bar.classList.add('d-none');
  }

  // Confirm dialogs
  document.querySelectorAll('[data-confirm]').forEach(btn=>{
    btn.addEventListener('click', e=>{
      if(!confirm(btn.getAttribute('data-confirm'))) e.preventDefault();
    });
  });

  // Live search debounce (if needed)
  // CSRF handled server side

  // Bangla number toggle? not needed
});

// Simple toast
function toast(msg, type='success'){
  const el=document.createElement('div');
  el.className=`alert alert-${type} position-fixed top-0 end-0 m-3 shadow`;
  el.style.zIndex=2000;
  el.textContent=msg;
  document.body.appendChild(el);
  setTimeout(()=>el.remove(),3000);
}
