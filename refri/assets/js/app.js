(function () {
  'use strict';

  const statusMap = {
    'Aberta': 'blue',
    'Agendada': 'purple',
    'Em andamento': 'amber',
    'Aguardando peça': 'orange',
    'Aguardando aprovação': 'amber',
    'Aprovado': 'green',
    'Aprovada': 'green',
    'Ativo': 'green',
    'Normal': 'green',
    'Pago': 'green',
    'Finalizada': 'final',
    'Concluído': 'final',
    'Cancelada': 'gray',
    'Inativo': 'gray',
    'Não emitida': 'gray',
    'Recusado': 'red',
    'Recusada': 'red',
    'Sem estoque': 'red',
    'Pendente': 'pending',
    'Emitida': 'green',
    'Rejeitada': 'red',
    'Estoque baixo': 'orange',
    'Enviado': 'teal',
    'Rascunho': 'gray'
  };

  const chartColors = ['#0f766e', '#2563eb', '#b45309', '#c2410c', '#15803d', '#6d28d9'];

  function money(value) {
    if (typeof value === 'string' && value.trim().startsWith('R$')) return value;
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(Number(value || 0));
  }

  function normalize(value) {
    return String(value || '')
      .toLowerCase()
      .normalize('NFD')
      .replace(/[\u0300-\u036f]/g, '');
  }

  function escapeHtml(value) {
    return String(value ?? '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#039;');
  }

  function badge(status) {
    const color = statusMap[status] || 'gray';
    return `<span class="badge badge--${color}">${escapeHtml(status || '-')}</span>`;
  }

  function fetchJson(url, options = {}) {
    const headers = new Headers(options.headers || {});
    headers.set('X-Requested-With', 'XMLHttpRequest');

    return fetch(url, { ...options, headers })
      .then((response) => {
        if (!response.ok) throw new Error('Falha ao carregar dados.');
        return response.json();
      });
  }

  function renderStats(selector, stats = []) {
    const root = typeof selector === 'string' ? document.querySelector(selector) : selector;
    if (!root) return;

    root.innerHTML = stats.map((item) => `
      <article class="stat-card stat-card--${escapeHtml(item.tone || 'blue')}">
        <div>
          <span class="stat-card__label">${escapeHtml(item.label)}</span>
          <strong class="stat-card__value">${escapeHtml(item.value)}</strong>
          ${item.helper ? `<span class="stat-card__helper">${escapeHtml(item.helper)}</span>` : ''}
        </div>
        <div class="stat-card__icon">${escapeHtml(item.icon || '')}</div>
      </article>
    `).join('');
  }

  function toast(message) {
    let el = document.getElementById('toast');
    if (!el) {
      el = document.createElement('div');
      el.id = 'toast';
      el.className = 'toast';
      el.setAttribute('role', 'status');
      el.setAttribute('aria-live', 'polite');
      document.body.appendChild(el);
    }

    el.textContent = message;
    el.classList.add('is-visible');
    window.clearTimeout(toast.timer);
    toast.timer = window.setTimeout(() => el.classList.remove('is-visible'), 2400);
  }

  function prepareCanvas(canvas, fallbackHeight = 240) {
    if (!canvas) return null;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    const width = rect.width || canvas.parentElement?.clientWidth || 600;
    const height = Number(canvas.getAttribute('height')) || rect.height || fallbackHeight;

    canvas.width = width * dpr;
    canvas.height = height * dpr;
    ctx.setTransform(dpr, 0, 0, dpr, 0, 0);
    ctx.clearRect(0, 0, width, height);
    return { ctx, width, height };
  }

  function roundRect(ctx, x, y, width, height, radius, fillStyle) {
    const r = Math.min(radius, width / 2, height / 2);
    ctx.beginPath();
    ctx.moveTo(x + r, y);
    ctx.arcTo(x + width, y, x + width, y + height, r);
    ctx.arcTo(x + width, y + height, x, y + height, r);
    ctx.arcTo(x, y + height, x, y, r);
    ctx.arcTo(x, y, x + width, y, r);
    ctx.closePath();
    ctx.fillStyle = fillStyle;
    ctx.fill();
  }

  function drawBarChart(canvas, data = [], options = {}) {
    const prepared = prepareCanvas(canvas);
    if (!prepared || !data.length) return;

    const { ctx, width, height } = prepared;
    const labels = data.map((item) => item.label || item.name || '');
    const values = data.map((item) => Number(item.value || 0));
    const padding = { top: 18, right: 14, bottom: 38, left: 42 };
    const chartW = width - padding.left - padding.right;
    const chartH = height - padding.top - padding.bottom;
    const max = Math.max(...values, 1) * 1.15;

    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i += 1) {
      const y = padding.top + chartH - (chartH / 4) * i;
      ctx.beginPath();
      ctx.moveTo(padding.left, y);
      ctx.lineTo(width - padding.right, y);
      ctx.stroke();
    }

    const gap = 14;
    const barW = Math.max(22, (chartW - gap * (values.length - 1)) / values.length);
    values.forEach((value, index) => {
      const x = padding.left + index * (barW + gap);
      const barH = (value / max) * chartH;
      const y = padding.top + chartH - barH;
      roundRect(ctx, x, y, barW, barH, 4, options.color || chartColors[index % chartColors.length]);
      ctx.fillStyle = '#6b7280';
      ctx.font = '12px Inter, sans-serif';
      ctx.textAlign = 'center';
      ctx.fillText(labels[index], x + barW / 2, height - 14);
    });
  }

  function drawHorizontalChart(canvas, data = [], options = {}) {
    const prepared = prepareCanvas(canvas);
    if (!prepared || !data.length) return;

    const { ctx, width, height } = prepared;
    const max = Math.max(...data.map((item) => Number(item.value || 0)), 1);
    const rowH = Math.min(38, (height - 24) / data.length);
    const labelW = 118;

    data.forEach((item, index) => {
      const y = 14 + index * rowH;
      const value = Number(item.value || 0);
      const barW = ((width - labelW - 42) * value) / max;

      ctx.fillStyle = '#6b7280';
      ctx.font = '12px Inter, sans-serif';
      ctx.textAlign = 'left';
      ctx.fillText(item.label || item.name || '', 10, y + 18);
      roundRect(ctx, labelW, y + 5, width - labelW - 42, 12, 4, '#eef2f7');
      roundRect(ctx, labelW, y + 5, barW, 12, 4, options.color || '#0f766e');
      ctx.fillStyle = '#111827';
      ctx.font = '700 12px Inter, sans-serif';
      ctx.textAlign = 'right';
      ctx.fillText(String(value), width - 10, y + 18);
    });
  }

  function drawDonut(canvas, data = [], legendEl) {
    const prepared = prepareCanvas(canvas, 220);
    if (!prepared || !data.length) return;

    const { ctx, width, height } = prepared;
    const total = data.reduce((sum, item) => sum + Number(item.value || 0), 0) || 1;
    const centerX = width / 2;
    const centerY = height / 2;
    const radius = Math.min(width, height) / 2 - 16;
    const inner = radius * .64;
    let start = -Math.PI / 2;

    data.forEach((item, index) => {
      const angle = (Number(item.value || 0) / total) * Math.PI * 2;
      ctx.beginPath();
      ctx.arc(centerX, centerY, radius, start, start + angle);
      ctx.arc(centerX, centerY, inner, start + angle, start, true);
      ctx.closePath();
      ctx.fillStyle = chartColors[index % chartColors.length];
      ctx.fill();
      start += angle;
    });

    ctx.fillStyle = '#111827';
    ctx.font = '700 22px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(String(total), centerX, centerY - 2);
    ctx.fillStyle = '#6b7280';
    ctx.font = '12px Inter, sans-serif';
    ctx.fillText('total', centerX, centerY + 18);

    const legend = typeof legendEl === 'string' ? document.querySelector(legendEl) : legendEl;
    if (legend) {
      legend.innerHTML = data.map((item, index) => `
        <div class="legend-item">
          <span class="legend-dot" style="background:${chartColors[index % chartColors.length]}"></span>
          <span>${escapeHtml(item.label || item.name)}: <strong>${escapeHtml(item.value)}</strong></span>
        </div>
      `).join('');
    }
  }

  function debounce(fn, wait = 180) {
    let timer;
    return (...args) => {
      window.clearTimeout(timer);
      timer = window.setTimeout(() => fn(...args), wait);
    };
  }

  const api = {
    money,
    normalize,
    escapeHtml,
    badge,
    fetchJson,
    renderStats,
    toast,
    drawBarChart,
    drawHorizontalChart,
    drawDonut,
    debounce,
    chartColors
  };

  window.KY = api;
  window.App = api;
})();
