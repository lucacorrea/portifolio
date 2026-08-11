
(function(){
  const body = document.body;
  const sidebar = document.querySelector('.sidebar');
  const overlay = document.querySelector('.mobile-overlay');

  function isMobile(){ return window.matchMedia('(max-width:900px)').matches; }

  document.querySelectorAll('[data-sidebar-toggle]').forEach(btn=>{
    btn.addEventListener('click', ()=>{
      if(isMobile()){
        sidebar?.classList.toggle('mobile-open');
        overlay?.classList.toggle('show');
      }else{
        body.classList.toggle('sidebar-collapsed');
        localStorage.setItem('secom_sidebar_collapsed', body.classList.contains('sidebar-collapsed') ? '1' : '0');
      }
    });
  });

  overlay?.addEventListener('click', ()=>{
    sidebar?.classList.remove('mobile-open');
    overlay.classList.remove('show');
  });

  if(!isMobile() && localStorage.getItem('secom_sidebar_collapsed') === '1'){
    body.classList.add('sidebar-collapsed');
  }

  document.querySelectorAll('[data-demo]').forEach(el=>{
    el.addEventListener('click', e=>{
      e.preventDefault();
      alert(el.getAttribute('data-demo'));
    });
  });

  document.querySelectorAll('[data-tab]').forEach(tab=>{
    tab.addEventListener('click',e=>{
      e.preventDefault();
      const group = tab.closest('.tabs');
      group?.querySelectorAll('[data-tab]').forEach(x=>x.classList.remove('active'));
      tab.classList.add('active');
      const target = document.getElementById(tab.dataset.tab);
      if(target) target.scrollIntoView({behavior:'smooth',block:'start'});
    });
  });

  document.querySelectorAll('[data-filter-table]').forEach(input=>{
    const selector = input.dataset.filterTable;
    const table = document.querySelector(selector);
    if(!table) return;
    input.addEventListener('input', ()=>{
      const q = input.value.toLowerCase().trim();
      table.querySelectorAll('tbody tr').forEach(row=>{
        row.style.display = !q || row.textContent.toLowerCase().includes(q) ? '' : 'none';
      });
    });
  });

  const globalSearch = document.querySelector('[data-global-search]');
  globalSearch?.addEventListener('keydown', e=>{
    if(e.key==='Enter'){
      e.preventDefault();
      alert('Busca global do protótipo: ' + (globalSearch.value || 'sem termo'));
    }
  });
})();
