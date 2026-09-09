(() => {
  const sidebar = document.getElementById('sidebar');
  document.querySelectorAll('[data-sidebar-toggle]').forEach(el => el.addEventListener('click', () => sidebar?.classList.toggle('is-open')));

  const modal = document.getElementById('genericModal');
  const modalContent = document.getElementById('modalContent');
  const openModal = html => { if(!modal) return; modalContent.innerHTML = html; modal.classList.add('is-open'); modal.setAttribute('aria-hidden','false'); };
  const closeModal = () => { if(!modal) return; modal.classList.remove('is-open'); modal.setAttribute('aria-hidden','true'); };
  document.querySelectorAll('[data-modal-close]').forEach(el => el.addEventListener('click', closeModal));
  document.addEventListener('keydown', e => { if(e.key === 'Escape') closeModal(); });

  window.showToast = (message) => {
    const toast = document.getElementById('toast'); if(!toast) return;
    toast.textContent = message; toast.classList.add('show');
    clearTimeout(window.__toastTimer); window.__toastTimer = setTimeout(() => toast.classList.remove('show'), 2200);
  };

  document.querySelectorAll('[data-demo-action]').forEach(el => el.addEventListener('click', e => {
    e.preventDefault(); showToast(el.dataset.demoAction || 'Ação executada no protótipo.');
  }));

  document.querySelectorAll('.switch').forEach(sw => sw.addEventListener('click', () => sw.classList.toggle('on')));

  document.querySelectorAll('[data-tab-filter]').forEach(btn => btn.addEventListener('click', () => {
    const group = btn.parentElement;
    group.querySelectorAll('.tab').forEach(t => t.classList.remove('active'));
    btn.classList.add('active');
    const target = btn.dataset.tabFilter;
    document.querySelectorAll('[data-status]').forEach(card => {
      card.style.display = (target === 'Todos' || card.dataset.status === target) ? '' : 'none';
    });
  }));

  document.querySelectorAll('[data-product]').forEach(el => el.addEventListener('click', () => {
    const name = el.dataset.product;
    const price = el.dataset.price || '0,00';
    openModal(`
      <h2 style="margin:0 0 6px">${name}</h2>
      <p style="color:#6b7280;margin-top:0">Personalize o item antes de adicionar.</p>
      <div class="field"><label>Quantidade</label><div class="status-row"><button class="btn btn-secondary btn-sm">−</button><input class="input" value="1" style="width:82px;text-align:center"><button class="btn btn-secondary btn-sm">+</button></div></div>
      <div class="field" style="margin-top:14px"><label>Observação</label><textarea class="textarea" rows="3" placeholder="Ex.: sem cebola, pouco açúcar..."></textarea></div>
      <div class="switch-row"><div><strong>Adicional demonstrativo</strong><small style="display:block;color:#6b7280">+ R$ 3,00</small></div><span class="switch"></span></div>
      <div style="display:flex;justify-content:space-between;font-size:20px;font-weight:800;margin:18px 0"><span>Total</span><span>R$ ${price}</span></div>
      <button class="btn btn-primary" style="width:100%" onclick="showToast('Item adicionado à comanda');document.querySelector('[data-modal-close]').click()">Adicionar à comanda</button>`);
  }));

  document.querySelectorAll('[data-open-modal]').forEach(el => el.addEventListener('click', () => {
    openModal(`<h2>${el.dataset.modalTitle || 'Ação'}</h2><p style="color:#6b7280">${el.dataset.modalText || 'Este componente está pronto para receber a lógica do backend.'}</p><button class="btn btn-primary" style="width:100%" data-demo-action="Operação simulada com sucesso" onclick="showToast('Operação simulada com sucesso');document.querySelector('[data-modal-close]').click()">Confirmar</button>`);
  }));
})();
