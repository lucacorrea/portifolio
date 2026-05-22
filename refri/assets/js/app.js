const App = (() => {
  const statusClass = (status = '') => {
    const s = status.toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    if (s.includes('aberta')) return 'badge--aberta';
    if (s.includes('agendada')) return 'badge--agendada';
    if (s.includes('andamento') || s.includes('execucao')) return 'badge--andamento';
    if (s.includes('peca') || s.includes('estoque baixo')) return 'badge--peca';
    if (s.includes('aprovado') || s.includes('finalizada') || s.includes('emitida') || s.includes('pago') || s.includes('normal') || s.includes('ativo')) return 'badge--aprovado';
    if (s.includes('recusado') || s.includes('rejeitada') || s.includes('sem estoque')) return 'badge--recusado';
    if (s.includes('cancelada') || s.includes('rascunho') || s.includes('inativo')) return 'badge--cancelada';
    if (s.includes('pendente') || s.includes('aguardando') || s.includes('nao emitida')) return 'badge--pendente';
    return 'badge--rascunho';
  };

  const badge = (status) => `<span class="badge ${statusClass(status)}">${status}</span>`;
  const money = (value) => Number(value || 0).toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const toast = (message) => {
    let el = document.querySelector('.toast');
    if (!el) {
      el = document.createElement('div');
      el.className = 'toast';
      document.body.appendChild(el);
    }
    el.textContent = message;
    el.classList.add('is-visible');
    window.setTimeout(() => el.classList.remove('is-visible'), 3400);
  };
  const fetchJson = async (url, options = {}) => {
    const response = await fetch(url, options);
    if (!response.ok) throw new Error('Falha ao carregar dados.');
    return response.json();
  };
  const renderStats = (target, items = []) => {
    const el = typeof target === 'string' ? document.querySelector(target) : target;
    if (!el) return;
    el.innerHTML = items.map(item => `
      <article class="stat-card">
        <div class="stat-card__icon stat-card__icon--${item.tone || 'blue'}">${item.icon || 'KY'}</div>
        <div><span>${item.label}</span><strong>${item.value}</strong><small>${item.helper || ''}</small></div>
      </article>
    `).join('');
  };
  const drawBarChart = (canvas, data, opts = {}) => {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = Math.max(320, rect.width) * dpr;
    canvas.height = (opts.height || Math.max(220, rect.height || 260)) * dpr;
    ctx.scale(dpr, dpr);
    const w = canvas.width / dpr;
    const h = canvas.height / dpr;
    ctx.clearRect(0, 0, w, h);
    const pad = { top: 18, right: 14, bottom: 38, left: 44 };
    const max = Math.max(...data.map(x => x.value), 1);
    ctx.strokeStyle = '#dde3ea';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
      const y = pad.top + ((h - pad.top - pad.bottom) / 4) * i;
      ctx.beginPath(); ctx.moveTo(pad.left, y); ctx.lineTo(w - pad.right, y); ctx.stroke();
    }
    const chartW = w - pad.left - pad.right;
    const barW = Math.max(18, chartW / data.length * .48);
    data.forEach((item, i) => {
      const x = pad.left + (chartW / data.length) * i + (chartW / data.length - barW) / 2;
      const bh = (item.value / max) * (h - pad.top - pad.bottom);
      const y = h - pad.bottom - bh;
      ctx.fillStyle = opts.color || '#0f766e';
      ctx.fillRect(x, y, barW, bh);
      ctx.fillStyle = '#64748b';
      ctx.font = '12px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(item.label, x + barW / 2, h - 14);
    });
  };
  const drawHorizontalChart = (canvas, data, opts = {}) => {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = Math.max(320, rect.width) * dpr;
    canvas.height = (opts.height || 260) * dpr;
    ctx.scale(dpr, dpr);
    const w = canvas.width / dpr;
    const h = canvas.height / dpr;
    ctx.clearRect(0, 0, w, h);
    const max = Math.max(...data.map(x => x.value), 1);
    const rowH = h / data.length;
    data.forEach((item, i) => {
      const y = i * rowH + 10;
      ctx.fillStyle = '#111827';
      ctx.font = '12px Inter, sans-serif';
      ctx.textAlign = 'left';
      ctx.fillText(item.label, 6, y + 15);
      ctx.fillStyle = '#e5e7eb';
      ctx.fillRect(130, y + 3, w - 176, 16);
      ctx.fillStyle = opts.color || '#2563eb';
      ctx.fillRect(130, y + 3, (w - 176) * item.value / max, 16);
      ctx.fillStyle = '#64748b';
      ctx.textAlign = 'right';
      ctx.fillText(item.value, w - 10, y + 16);
    });
  };
  const drawDonut = (canvas, data) => {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = Math.max(300, rect.width) * dpr;
    canvas.height = Math.max(220, rect.height || 260) * dpr;
    ctx.scale(dpr, dpr);
    const w = canvas.width / dpr;
    const h = canvas.height / dpr;
    ctx.clearRect(0, 0, w, h);
    const cx = w / 2; const cy = h / 2 - 5; const r = Math.min(w, h) / 3;
    const colors = ['#15803d', '#b91c1c', '#b45309', '#2563eb'];
    const total = data.reduce((sum, i) => sum + i.value, 0) || 1;
    let start = -Math.PI / 2;
    data.forEach((item, i) => {
      const angle = Math.PI * 2 * item.value / total;
      ctx.beginPath();
      ctx.moveTo(cx, cy);
      ctx.arc(cx, cy, r, start, start + angle);
      ctx.closePath();
      ctx.fillStyle = colors[i % colors.length];
      ctx.fill();
      start += angle;
    });
    ctx.beginPath(); ctx.arc(cx, cy, r * .58, 0, Math.PI * 2); ctx.fillStyle = '#fff'; ctx.fill();
    ctx.fillStyle = '#111827'; ctx.font = '700 18px Inter, sans-serif'; ctx.textAlign = 'center'; ctx.fillText(String(total), cx, cy + 6);
  };

  const openModal = (type) => {
    const old = document.querySelector('.modal-backdrop');
    if (old) old.remove();
    const titles = { cliente: 'Novo Cliente', os: 'Nova Ordem de Serviço', orcamento: 'Novo Orçamento', peca: 'Nova Peça', servico: 'Novo Tipo de Serviço' };
    const modal = document.createElement('div');
    modal.className = 'modal-backdrop is-open';
    modal.innerHTML = `
      <div class="modal" role="dialog" aria-modal="true">
        <div class="modal__header"><h3>${titles[type] || 'Novo Cadastro'}</h3><button class="icon-btn" data-close-modal>×</button></div>
        <div class="modal__body">
          <div class="form-grid">
            <label class="field"><span>Cliente/Nome</span><input placeholder="Digite o nome"></label>
            <label class="field"><span>Telefone/WhatsApp</span><input placeholder="(92) 90000-0000"></label>
            <label class="field"><span>Tipo/Status</span><select><option>Ativo</option><option>Pendente</option><option>Aguardando aprovação</option></select></label>
            <label class="field"><span>Valor estimado</span><input placeholder="R$ 0,00"></label>
            <label class="field field--full"><span>Observações</span><textarea placeholder="Descreva informações importantes..."></textarea></label>
          </div>
        </div>
        <div class="modal__footer"><button class="btn btn--secondary" data-close-modal>Cancelar</button><button class="btn btn--primary" data-save-modal>Salvar</button></div>
      </div>`;
    document.body.appendChild(modal);
  };

  document.addEventListener('click', (event) => {
    const toggle = event.target.closest('#menuToggle');
    if (toggle) { document.querySelector('#sidebar')?.classList.add('is-open'); document.querySelector('#sidebarOverlay')?.classList.add('is-visible'); }
    if (event.target.closest('#sidebarOverlay')) { document.querySelector('#sidebar')?.classList.remove('is-open'); document.querySelector('#sidebarOverlay')?.classList.remove('is-visible'); }
    const modalTrigger = event.target.closest('[data-modal]');
    if (modalTrigger) openModal(modalTrigger.dataset.modal);
    if (event.target.closest('[data-close-modal]')) event.target.closest('.modal-backdrop')?.remove();
    if (event.target.closest('[data-save-modal]')) { event.target.closest('.modal-backdrop')?.remove(); toast('Registro salvo no protótipo. Integre com o banco na etapa do CRUD.'); }
    if (event.target.closest('[data-refresh]')) { toast('Dados atualizados.'); }
  });

  return { badge, money, toast, fetchJson, renderStats, drawBarChart, drawHorizontalChart, drawDonut };
})();
