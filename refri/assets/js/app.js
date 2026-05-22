const KY = {
  money(value) {
    return new Intl.NumberFormat('pt-BR', {
      style: 'currency',
      currency: 'BRL'
    }).format(Number(value || 0));
  },

  badge(status) {
    const map = {
      'Aberta': 'blue',
      'Agendada': 'purple',
      'Em andamento': 'amber',
      'Aguardando peça': 'orange',
      'Aguardando aprovação': 'amber',
      'Aprovado': 'green',
      'Aprovada': 'green',
      'Finalizada': 'green',
      'Concluído': 'green',
      'Cancelada': 'red',
      'Recusado': 'red',
      'Pendente': 'amber',
      'Emitida': 'green',
      'Rejeitada': 'red',
      'Estoque baixo': 'orange',
      'Enviado': 'teal'
    };
    const color = map[status] || 'gray';
    return `<span class="badge badge--${color}">${status}</span>`;
  },

  fetchJson(url) {
    return fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' }})
      .then((response) => {
        if (!response.ok) throw new Error('Falha ao carregar dados.');
        return response.json();
      });
  },

  chartColors: ['#0f766e', '#2563eb', '#f59e0b', '#ea580c', '#16a34a', '#7c3aed'],

  drawBars(canvas, labels, values, options = {}) {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = (Number(canvas.getAttribute('height')) || rect.height || 260) * dpr;
    ctx.scale(dpr, dpr);

    const width = rect.width;
    const height = Number(canvas.getAttribute('height')) || 260;
    ctx.clearRect(0, 0, width, height);

    const padding = { top: 18, right: 16, bottom: 42, left: 42 };
    const chartW = width - padding.left - padding.right;
    const chartH = height - padding.top - padding.bottom;
    const max = Math.max(...values, 1) * 1.15;

    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 1;
    ctx.font = '12px Inter, sans-serif';
    ctx.fillStyle = '#94a3b8';

    for (let i = 0; i <= 4; i++) {
      const y = padding.top + chartH - (chartH / 4) * i;
      ctx.beginPath();
      ctx.moveTo(padding.left, y);
      ctx.lineTo(width - padding.right, y);
      ctx.stroke();
    }

    const gap = 14;
    const barW = Math.max(24, (chartW - gap * (values.length - 1)) / values.length);

    values.forEach((value, index) => {
      const x = padding.left + index * (barW + gap);
      const barH = (value / max) * chartH;
      const y = padding.top + chartH - barH;

      const gradient = ctx.createLinearGradient(0, y, 0, y + barH);
      gradient.addColorStop(0, options.color || '#0f766e');
      gradient.addColorStop(1, '#99f6e4');

      KY.roundRect(ctx, x, y, barW, barH, 10, gradient);
      ctx.fillStyle = '#64748b';
      ctx.textAlign = 'center';
      ctx.fillText(labels[index], x + barW / 2, height - 16);
    });
  },

  drawLineArea(canvas, labels, values) {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    canvas.width = rect.width * dpr;
    canvas.height = (Number(canvas.getAttribute('height')) || rect.height || 260) * dpr;
    ctx.scale(dpr, dpr);

    const width = rect.width;
    const height = Number(canvas.getAttribute('height')) || 260;
    ctx.clearRect(0, 0, width, height);

    const padding = { top: 18, right: 20, bottom: 42, left: 42 };
    const chartW = width - padding.left - padding.right;
    const chartH = height - padding.top - padding.bottom;
    const max = Math.max(...values, 1) * 1.15;
    const min = 0;
    const points = values.map((value, index) => {
      const x = padding.left + (chartW / Math.max(values.length - 1, 1)) * index;
      const y = padding.top + chartH - ((value - min) / (max - min)) * chartH;
      return { x, y, value };
    });

    ctx.strokeStyle = '#e5e7eb';
    ctx.lineWidth = 1;
    for (let i = 0; i <= 4; i++) {
      const y = padding.top + chartH - (chartH / 4) * i;
      ctx.beginPath();
      ctx.moveTo(padding.left, y);
      ctx.lineTo(width - padding.right, y);
      ctx.stroke();
    }

    const gradient = ctx.createLinearGradient(0, padding.top, 0, padding.top + chartH);
    gradient.addColorStop(0, 'rgba(37, 99, 235, .24)');
    gradient.addColorStop(1, 'rgba(37, 99, 235, 0)');

    ctx.beginPath();
    ctx.moveTo(points[0].x, padding.top + chartH);
    points.forEach((point, index) => {
      if (index === 0) ctx.lineTo(point.x, point.y);
      else {
        const prev = points[index - 1];
        const cpX = (prev.x + point.x) / 2;
        ctx.bezierCurveTo(cpX, prev.y, cpX, point.y, point.x, point.y);
      }
    });
    ctx.lineTo(points[points.length - 1].x, padding.top + chartH);
    ctx.closePath();
    ctx.fillStyle = gradient;
    ctx.fill();

    ctx.beginPath();
    points.forEach((point, index) => {
      if (index === 0) ctx.moveTo(point.x, point.y);
      else {
        const prev = points[index - 1];
        const cpX = (prev.x + point.x) / 2;
        ctx.bezierCurveTo(cpX, prev.y, cpX, point.y, point.x, point.y);
      }
    });
    ctx.strokeStyle = '#2563eb';
    ctx.lineWidth = 3;
    ctx.stroke();

    ctx.fillStyle = '#64748b';
    ctx.font = '12px Inter, sans-serif';
    ctx.textAlign = 'center';
    labels.forEach((label, index) => {
      ctx.fillText(label, points[index].x, height - 16);
    });
  },

  drawDonut(canvas, data, legendEl) {
    if (!canvas) return;
    const ctx = canvas.getContext('2d');
    const dpr = window.devicePixelRatio || 1;
    const rect = canvas.getBoundingClientRect();
    const sizeW = canvas.getAttribute('width') || rect.width || 280;
    const sizeH = canvas.getAttribute('height') || rect.height || 220;
    canvas.width = sizeW * dpr;
    canvas.height = sizeH * dpr;
    ctx.scale(dpr, dpr);

    const width = Number(sizeW);
    const height = Number(sizeH);
    const total = data.reduce((sum, item) => sum + item.value, 0) || 1;
    const centerX = width / 2;
    const centerY = height / 2;
    const radius = Math.min(width, height) / 2 - 14;
    const inner = radius * .62;
    let start = -Math.PI / 2;

    ctx.clearRect(0, 0, width, height);

    data.forEach((item, index) => {
      const angle = (item.value / total) * Math.PI * 2;
      ctx.beginPath();
      ctx.arc(centerX, centerY, radius, start, start + angle);
      ctx.arc(centerX, centerY, inner, start + angle, start, true);
      ctx.closePath();
      ctx.fillStyle = KY.chartColors[index % KY.chartColors.length];
      ctx.fill();
      start += angle;
    });

    ctx.fillStyle = '#172033';
    ctx.font = '800 22px Inter, sans-serif';
    ctx.textAlign = 'center';
    ctx.fillText(`${total}`, centerX, centerY - 2);
    ctx.fillStyle = '#64748b';
    ctx.font = '12px Inter, sans-serif';
    ctx.fillText('total', centerX, centerY + 18);

    if (legendEl) {
      legendEl.innerHTML = data.map((item, index) => `
        <div class="legend-item">
          <span class="legend-dot" style="background:${KY.chartColors[index % KY.chartColors.length]}"></span>
          <span>${item.name}: <strong>${item.value}</strong></span>
        </div>
      `).join('');
    }
  },

  roundRect(ctx, x, y, width, height, radius, fillStyle) {
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
};

document.addEventListener('DOMContentLoaded', () => {
  const menuToggle = document.getElementById('menuToggle');
  const sidebar = document.getElementById('sidebar');
  const overlay = document.getElementById('sidebarOverlay');

  const closeSidebar = () => {
    sidebar?.classList.remove('is-open');
    overlay?.classList.remove('is-open');
  };

  menuToggle?.addEventListener('click', () => {
    sidebar?.classList.toggle('is-open');
    overlay?.classList.toggle('is-open');
  });

  overlay?.addEventListener('click', closeSidebar);
});
