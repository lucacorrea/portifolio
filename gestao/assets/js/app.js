const currency = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL'
});

const data = {
  money: [
    { label: 'Total vendido', value: 8742.90, note: '+18,4% hoje', color: '#1768D5', bg: 'rgba(23,104,213,.12)' },
    { label: 'PIX', value: 3820.40, note: '44% das vendas', color: '#11BFA5', bg: 'rgba(17,191,165,.13)' },
    { label: 'Cartão', value: 3654.10, note: 'Crédito e débito', color: '#18B8D8', bg: 'rgba(24,184,216,.13)' },
    { label: 'Dinheiro', value: 1268.40, note: '14 pagamentos', color: '#F6B93B', bg: 'rgba(246,185,59,.14)' },
    { label: 'Lucro estimado', value: 2797.72, note: 'Margem 32%', color: '#24C486', bg: 'rgba(36,196,134,.14)' }
  ],

  sales: [
    { name: 'Cliente balcão', meta: 'PIX • 09:42', amount: 246.80, icon: '✓' },
    { name: 'Mariana Costa', meta: 'Cartão • 10:16', amount: 589.90, icon: '✓' },
    { name: 'Rafael Lima', meta: 'Dinheiro • 10:37', amount: 79.90, icon: '✓' },
    { name: 'Mercado São João', meta: 'PIX • 11:04', amount: 1390.00, icon: '✓' }
  ],

  products: [
    { name: 'Mouse Sem Fio Pro', meta: '28 unidades vendidas', amount: 2237.20, icon: '▦' },
    { name: 'Cabo USB-C 2m', meta: '41 unidades vendidas', amount: 697.00, icon: '▦' },
    { name: 'Teclado Mecânico RGB', meta: '12 unidades vendidas', amount: 1798.80, icon: '▦' }
  ],

  stock: [
    { name: 'Fonte 12V 5A', meta: 'Repor estoque', status: '3 un.', icon: '!' },
    { name: 'Hub USB-C Premium', meta: 'Repor estoque', status: '2 un.', icon: '!' },
    { name: 'Adaptador HDMI', meta: 'Repor estoque', status: '5 un.', icon: '!' }
  ],

  cart: [
    { name: 'Teclado Mecânico RGB', meta: '1 x R$ 149,90', amount: 149.90, icon: '▦' },
    { name: 'Mouse Sem Fio Pro', meta: '1 x R$ 79,90', amount: 79.90, icon: '▦' },
    { name: 'Cabo USB-C 2m', meta: '1 x R$ 17,00', amount: 17.00, icon: '▦' }
  ],

  catalog: [
    { name: 'Teclado Mecânico RGB', meta: 'SKU-1009 • Estoque: 18', amount: 149.90, icon: '▦' },
    { name: 'Mouse Sem Fio Pro', meta: 'SKU-1011 • Estoque: 42', amount: 79.90, icon: '▦' },
    { name: 'Cabo USB-C 2m', meta: 'SKU-1018 • Estoque: 76', amount: 17.00, icon: '▦' },
    { name: 'Hub USB-C Premium', meta: 'SKU-1054 • Estoque: 2', amount: 119.90, icon: '▦' },
    { name: 'Fonte 12V 5A', meta: 'SKU-1088 • Estoque: 3', amount: 59.90, icon: '▦' }
  ],

  hour: [
    { label: '08h', value: 32 },
    { label: '09h', value: 54 },
    { label: '10h', value: 76 },
    { label: '11h', value: 66 },
    { label: '12h', value: 43 },
    { label: '13h', value: 58 },
    { label: '14h', value: 82 },
    { label: '15h', value: 96 },
    { label: '16h', value: 72 },
    { label: '17h', value: 90 }
  ],

  week: [48, 61, 44, 82, 96, 70, 39]
};

function setDeviceTime() {
  const now = new Date();
  const time = now.toLocaleTimeString('pt-BR', {
    hour: '2-digit',
    minute: '2-digit'
  });

  document.querySelectorAll('#deviceTime, .deviceTimeClone').forEach(el => {
    el.textContent = time;
  });
}

function moneyCard(item) {
  return `
    <article class="money-card" style="--accent:${item.color};--accent-bg:${item.bg}">
      <span>${item.label}</span>
      <strong>${currency.format(item.value)}</strong>
      <small>${item.note}</small>
    </article>
  `;
}

function row(item, mode = 'amount') {
  const right = mode === 'status'
    ? `<span class="row-status">${item.status}</span>`
    : `<span class="row-amount">${currency.format(item.amount)}</span><span class="row-status">Aprovado</span>`;

  return `
    <button class="row" data-toast="${item.name}">
      <div class="row-left">
        <span class="row-icon">${item.icon}</span>
        <div>
          <span class="row-title">${item.name}</span>
          <span class="row-sub">${item.meta}</span>
        </div>
      </div>
      <div class="row-right">${right}</div>
    </button>
  `;
}

function renderMoney() {
  document.querySelector('#moneyGrid').innerHTML = data.money.map(moneyCard).join('');
}

function renderLists() {
  document.querySelector('#salesList').innerHTML = data.sales.map(item => row(item)).join('');
  document.querySelector('#productsList').innerHTML = data.products.map(item => row(item)).join('');
  document.querySelector('#stockList').innerHTML = data.stock.map(item => row(item, 'status')).join('');
  document.querySelector('#cartList').innerHTML = data.cart.map(item => row(item)).join('');
  document.querySelector('#catalogList').innerHTML = data.catalog.map(item => row(item)).join('');
}

function renderCharts() {
  const hour = document.querySelector('#hourChart');
  hour.innerHTML = data.hour.map((item, index) => `
    <div class="bar" data-label="${item.label}" style="height:${item.value}%; animation-delay:${index * 45}ms"></div>
  `).join('');

  const week = document.querySelector('#weekChart');
  week.innerHTML = data.week.map(value => `
    <div class="mini-bar" style="height:${value}%"></div>
  `).join('');
}

function navigate(screenName) {
  document.querySelectorAll('.screen').forEach(screen => {
    screen.classList.toggle('active', screen.dataset.screen === screenName);
    if (screen.dataset.screen === screenName) screen.scrollTop = 0;
  });

  document.querySelectorAll('.nav').forEach(nav => {
    nav.classList.toggle('active', nav.dataset.screenTarget === screenName);
  });
}

function showToast(message) {
  const toast = document.querySelector('#toast');
  toast.textContent = message;
  toast.classList.add('show');

  clearTimeout(window.__toastTimer);
  window.__toastTimer = setTimeout(() => {
    toast.classList.remove('show');
  }, 2200);
}

function bindEvents() {
  document.body.addEventListener('click', event => {
    const screenButton = event.target.closest('[data-screen-target]');
    if (screenButton) {
      navigate(screenButton.dataset.screenTarget);
      return;
    }

    const toastButton = event.target.closest('[data-toast]');
    if (toastButton) {
      showToast(toastButton.dataset.toast);
    }
  });

  const search = document.querySelector('#productSearch');
  if (search) {
    search.addEventListener('input', () => {
      const term = search.value.trim().toLowerCase();
      const filtered = data.catalog.filter(item => {
        return `${item.name} ${item.meta}`.toLowerCase().includes(term);
      });

      document.querySelector('#catalogList').innerHTML = filtered.map(item => row(item)).join('')
        || `<div class="row"><div class="row-left"><span class="row-icon">⌕</span><div><span class="row-title">Nenhum produto</span><span class="row-sub">Tente outro termo</span></div></div></div>`;
    });
  }
}

function registerServiceWorker() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./service-worker.js').catch(() => {});
  }
}

function boot() {
  setDeviceTime();
  setInterval(setDeviceTime, 30000);

  renderMoney();
  renderLists();
  renderCharts();
  bindEvents();
  registerServiceWorker();
}

document.addEventListener('DOMContentLoaded', boot);
