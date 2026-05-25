const brl = new Intl.NumberFormat('pt-BR', {
  style: 'currency',
  currency: 'BRL'
});

const data = {
  todaySales: [
    {
      title: 'Venda balcão',
      subtitle: 'PIX • 09:42',
      amount: 246.80,
      status: 'Aprovada',
      icon: 'receipt',
      type: 'positive'
    },
    {
      title: 'Mariana Costa',
      subtitle: 'Cartão • 10:16',
      amount: 589.90,
      status: 'Aprovada',
      icon: 'card',
      type: 'positive'
    }
  ],

  yesterdaySales: [
    {
      title: 'Rafael Lima',
      subtitle: 'Dinheiro • Ontem',
      amount: 79.90,
      status: 'Finalizada',
      icon: 'cash',
      type: 'positive'
    },
    {
      title: 'Cancelamento',
      subtitle: 'Estorno • Ontem',
      amount: -56.80,
      status: 'Estornado',
      icon: 'refund',
      type: 'negative'
    }
  ],

  finance: [
    { title: 'Total vendido', value: 8742.90, note: '91 vendas' },
    { title: 'PIX', value: 3820.40, note: '44% do caixa' },
    { title: 'Cartão', value: 3654.10, note: '42% do caixa' },
    { title: 'Dinheiro', value: 1268.40, note: '14% do caixa' }
  ],

  topProducts: [
    { title: 'Mouse Sem Fio Pro', subtitle: '28 un. vendidas', amount: 2237.20, status: 'Top 1', icon: 'product' },
    { title: 'Cabo USB-C 2m', subtitle: '41 un. vendidas', amount: 697.00, status: 'Top 2', icon: 'product' },
    { title: 'Teclado Mecânico RGB', subtitle: '12 un. vendidas', amount: 1798.80, status: 'Top 3', icon: 'product' }
  ],

  cart: [
    { title: 'Teclado Mecânico RGB', subtitle: '1 x R$ 149,90', amount: 149.90, status: 'Item', icon: 'product' },
    { title: 'Mouse Sem Fio Pro', subtitle: '1 x R$ 79,90', amount: 79.90, status: 'Item', icon: 'product' },
    { title: 'Cabo USB-C 2m', subtitle: '1 x R$ 17,00', amount: 17.00, status: 'Item', icon: 'product' }
  ],

  catalog: [
    { title: 'Teclado Mecânico RGB', subtitle: 'SKU-1009 • Estoque 18', amount: 149.90, status: 'Ativo', icon: 'product' },
    { title: 'Mouse Sem Fio Pro', subtitle: 'SKU-1011 • Estoque 42', amount: 79.90, status: 'Ativo', icon: 'product' },
    { title: 'Cabo USB-C 2m', subtitle: 'SKU-1018 • Estoque 76', amount: 17.00, status: 'Ativo', icon: 'product' },
    { title: 'Hub USB-C Premium', subtitle: 'SKU-1054 • Estoque 2', amount: 119.90, status: 'Baixo', icon: 'product' },
    { title: 'Fonte 12V 5A', subtitle: 'SKU-1088 • Estoque 3', amount: 59.90, status: 'Baixo', icon: 'product' }
  ],

  week: [
    { day: 'Seg', height: 54 },
    { day: 'Ter', height: 67 },
    { day: 'Qua', height: 48 },
    { day: 'Qui', height: 76 },
    { day: 'Sex', height: 92 },
    { day: 'Sáb', height: 70 },
    { day: 'Dom', height: 42 }
  ]
};

const icons = {
  receipt: '<svg viewBox="0 0 24 24"><path d="M7 4h10v16l-2-1-2 1-2-1-2 1-2-1z"/><path d="M9 8h6"/><path d="M9 12h5"/></svg>',
  card: '<svg viewBox="0 0 24 24"><path d="M4 7h16v10H4z"/><path d="M4 10h16"/></svg>',
  cash: '<svg viewBox="0 0 24 24"><path d="M4 7h16v10H4z"/><circle cx="12" cy="12" r="3"/></svg>',
  refund: '<svg viewBox="0 0 24 24"><path d="M9 8H5V4"/><path d="M5 8a8 8 0 1 1 2 5.3"/></svg>',
  product: '<svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg>'
};

function updateTime() {
  const now = new Date();
  const time = now.toLocaleTimeString('pt-BR', {
    hour: '2-digit',
    minute: '2-digit'
  });

  document.querySelectorAll('[data-time]').forEach(item => {
    item.textContent = time;
  });
}

function rowTemplate(item) {
  const amountClass = item.type === 'negative' ? 'negative' : 'positive';
  const formatted = item.amount < 0
    ? `- ${brl.format(Math.abs(item.amount))}`
    : brl.format(item.amount);

  return `
    <button class="row-item" data-toast="${item.title}">
      <div class="row-left">
        <span class="row-icon">${icons[item.icon] || icons.receipt}</span>
        <span class="row-text">
          <strong>${item.title}</strong>
          <span>${item.subtitle}</span>
        </span>
      </div>

      <span class="row-right">
        <strong class="${amountClass}">${formatted}</strong>
        <span>${item.status}</span>
      </span>
    </button>
  `;
}

function renderLists() {
  document.querySelector('#todaySales').innerHTML = data.todaySales.map(rowTemplate).join('');
  document.querySelector('#yesterdaySales').innerHTML = data.yesterdaySales.map(rowTemplate).join('');
  document.querySelector('#topProducts').innerHTML = data.topProducts.map(rowTemplate).join('');
  document.querySelector('#cartItems').innerHTML = data.cart.map(rowTemplate).join('');
  document.querySelector('#catalogList').innerHTML = data.catalog.map(rowTemplate).join('');
}

function renderFinance() {
  document.querySelector('#financeGrid').innerHTML = data.finance.map(item => `
    <article class="finance-card">
      <span>${item.title}</span>
      <strong>${brl.format(item.value)}</strong>
      <small>${item.note}</small>
    </article>
  `).join('');
}

function renderChart() {
  document.querySelector('#weeklyBars').innerHTML = data.week.map(item => `
    <span class="bar" style="height:${item.height}%" data-day="${item.day}"></span>
  `).join('');
}

function navigate(screenName) {
  document.querySelectorAll('.screen').forEach(screen => {
    const isActive = screen.dataset.screen === screenName;
    screen.classList.toggle('active', isActive);

    if (isActive) {
      screen.scrollTop = 0;
    }
  });

  document.querySelectorAll('.bottom-nav button').forEach(button => {
    const target = button.dataset.screenTarget;
    button.classList.toggle('active', target === screenName && !button.classList.contains('center-action'));
  });
}

function showToast(message) {
  const toast = document.querySelector('#toast');
  toast.textContent = message;
  toast.classList.add('show');

  clearTimeout(window.toastTimer);
  window.toastTimer = setTimeout(() => {
    toast.classList.remove('show');
  }, 1900);
}

function bindEvents() {
  document.body.addEventListener('click', event => {
    const navigation = event.target.closest('[data-screen-target]');
    if (navigation) {
      navigate(navigation.dataset.screenTarget);
      return;
    }

    const toastTarget = event.target.closest('[data-toast]');
    if (toastTarget) {
      showToast(toastTarget.dataset.toast);
    }
  });

  const search = document.querySelector('#productSearch');

  search.addEventListener('input', () => {
    const term = search.value.trim().toLowerCase();
    const filtered = data.catalog.filter(item => {
      return `${item.title} ${item.subtitle}`.toLowerCase().includes(term);
    });

    document.querySelector('#catalogList').innerHTML = filtered.length
      ? filtered.map(rowTemplate).join('')
      : `
        <div class="row-item">
          <div class="row-left">
            <span class="row-icon">${icons.product}</span>
            <span class="row-text">
              <strong>Nenhum produto</strong>
              <span>Tente buscar por outro termo</span>
            </span>
          </div>
        </div>
      `;
  });
}

function registerPWA() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./service-worker.js').catch(() => {});
  }
}

function boot() {
  updateTime();
  setInterval(updateTime, 30000);

  renderLists();
  renderFinance();
  renderChart();
  bindEvents();
  registerPWA();
}

document.addEventListener('DOMContentLoaded', boot);
