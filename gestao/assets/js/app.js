const prefix = document.body.dataset.prefix || '';
const page = document.body.dataset.page || 'dashboard';
const data = {
  settings: {
    companyName: '',
    companyPhone: '',
    companyAddress: '',
    receiptMode: 'perguntar',
    receiptTemplate: 'detalhado',
    expirationAlertDays: 7,
    debtDueDays: 30,
    defaultMinStock: 0,
    blockExpiredProducts: true,
    blockNegativeStock: true,
    lowStockAlert: true,
    paymentPix: true,
    paymentCash: true,
    paymentCredit: true,
    paymentDebit: true,
    paymentAccount: true,
    paymentMixed: true,
    allowDiscount: true,
    discountLimitPercent: 0,
    requireCustomerForAccount: true,
    requireCancellationReason: true,
    auditLogEnabled: true,
    confirmDeletes: true,
    operatorPinEnabled: false,
    notificationsEnabled: true
  },
  products: [],
  clients: [],
  sales: [],
  users: [],
  dashboard: {
    summary: {
      sales_count: 0,
      total_sales: 0,
      estimated_profit: 0
    },
    paymentMethods: [],
    latestSales: [],
    topProducts: [],
    lowStock: [],
    expiringProducts: []
  },
  report: {
    summary: {
      sales_count: 0,
      total_sales: 0,
      average_ticket: 0
    },
    paymentMethods: [],
    sales: [],
    products: {
      sold: [],
      lowStock: []
    },
    validity: []
  },
  currentSale: null,
  dashboardInfo: {
    sales_count: 0,
    total_sales: 0,
    estimated_profit: 0
  }
};
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
const brl = new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' });

const icons = {
  receipt: '<svg viewBox="0 0 24 24"><path d="M7 4h10v16l-2-1-2 1-2-1-2 1-2-1z"/><path d="M9 8h6"/><path d="M9 12h5"/></svg>',
  card: '<svg viewBox="0 0 24 24"><path d="M4 7h16v10H4z"/><path d="M4 10h16"/></svg>',
  cash: '<svg viewBox="0 0 24 24"><path d="M4 7h16v10H4z"/><circle cx="12" cy="12" r="3"/></svg>',
  product: '<svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg>',
  user: '<svg viewBox="0 0 24 24"><path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8z"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>',
  report: '<svg viewBox="0 0 24 24"><path d="M6 18V9"/><path d="M12 18V5"/><path d="M18 18v-7"/></svg>',
  box: '<svg viewBox="0 0 24 24"><path d="M4 8l8-4 8 4-8 4z"/><path d="M4 8v8l8 4 8-4V8"/></svg>',
  camera: '<svg viewBox="0 0 24 24"><path d="M5 7h3l2-2h4l2 2h3v12H5z"/><circle cx="12" cy="13" r="4"/></svg>',
  search: '<svg viewBox="0 0 24 24"><path d="M10.5 18a7.5 7.5 0 1 1 0-15 7.5 7.5 0 0 1 0 15z"/><path d="M16 16l5 5"/></svg>'
};

let saleStep = Number(localStorage.getItem('saleStep') || 1);
let cart = JSON.parse(localStorage.getItem('cart') || '[]');
let currentPayment = localStorage.getItem('payment') || 'PIX';
let receivedAmount = Number(localStorage.getItem('receivedAmount') || 0);
let saleClientId = Number(localStorage.getItem('saleClientId') || 0);
let saleDueDate = localStorage.getItem('saleDueDate') || '';
let reportPeriod = 'dia';
let reportStartDate = '';
let reportEndDate = '';
let salesPeriodFilter = 'Hoje';
let salesPaymentFilter = 'Todos';
let scannerStream = null;

function $(sel, parent = document) { return parent.querySelector(sel); }
function $all(sel, parent = document) { return [...parent.querySelectorAll(sel)]; }
function img(name) {
  if (!name) return `${prefix}assets/img/prod-placeholder.svg`;
  if (/^(https?:|data:)/.test(name)) return name;
  if (name.startsWith('uploads/') || name.startsWith('assets/')) return `${prefix}${name}`;
  return `${prefix}assets/img/${name}`;
}
function qs(name) { return new URLSearchParams(location.search).get(name); }

function escapeHtml(value) {
  return String(value ?? '').replace(/[&<>"']/g, char => ({
    '&': '&amp;',
    '<': '&lt;',
    '>': '&gt;',
    '"': '&quot;',
    "'": '&#039;'
  }[char]));
}

async function postJson(url, payload) {
  const res = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json'
    },
    body: JSON.stringify({ ...payload, csrf_token: csrfToken })
  });
  const json = await res.json();

  if (!res.ok || !json.success) {
    throw new Error(json.message || 'Não foi possível concluir a operação.');
  }

  return json;
}

async function postForm(url, formData) {
  formData.append('csrf_token', csrfToken);
  const res = await fetch(url, {
    method: 'POST',
    headers: { 'Accept': 'application/json' },
    body: formData
  });
  const json = await res.json();

  if (!res.ok || !json.success) {
    throw new Error(json.message || 'Não foi possível concluir a operação.');
  }

  return json;
}

async function fetchJson(url) {
  const res = await fetch(url, { headers: { 'Accept': 'application/json' } });
  const json = await res.json();

  if (!res.ok || !json.success) {
    throw new Error(json.message || 'Não foi possível carregar os dados.');
  }

  return json.data;
}

function updateTime() {
  const time = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  $all('[data-time]').forEach(el => el.textContent = time);
}

function formatDate(date) {
  if (!date) return '-';
  const [y, m, d] = date.split('-');
  return `${d}/${m}/${y}`;
}

function isoLocalDate(date) {
  return [
    date.getFullYear(),
    String(date.getMonth() + 1).padStart(2, '0'),
    String(date.getDate()).padStart(2, '0')
  ].join('-');
}

function defaultDueDate() {
  const date = new Date();
  date.setDate(date.getDate() + Number(data.settings.debtDueDays || 30));
  return isoLocalDate(date);
}

function dateTimeParts(value) {
  if (!value) return { date: '', time: '' };
  const [date, time = ''] = String(value).replace('T', ' ').split(' ');
  return { date, time: time.slice(0, 5) };
}

function paymentLabel(method) {
  return {
    pix: 'PIX',
    credito: 'Crédito',
    debito: 'Débito',
    dinheiro: 'Dinheiro',
    conta_cliente: 'Conta do cliente',
    misto: 'Misto'
  }[method] || method || 'Não informado';
}

function daysTo(date) {
  if (!date) return Number.POSITIVE_INFINITY;
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());
  const target = new Date(`${date}T00:00:00`);
  return Math.ceil((target - today) / 86400000);
}

function emptyState(message, action = '') {
  return `<article class="summary-card"><p>${escapeHtml(message)}</p>${action}</article>`;
}

function showToast(message) {
  const toast = $('#toast');
  toast.textContent = message;
  toast.classList.add('show');
  clearTimeout(window.toastTimer);
  window.toastTimer = setTimeout(() => toast.classList.remove('show'), 1900);
}

function openModal(html) {
  $('#modalCard').innerHTML = html;
  $('#modalBackdrop').hidden = false;
}

function closeModal() {
  stopScanner();
  $('#modalBackdrop').hidden = true;
  $('#modalCard').innerHTML = '';
}

function statusBadges(product) {
  const badges = [];
  const remaining = daysTo(product.expiry);

  if (product.stock <= 0) badges.push(['Sem estoque', 'red']);
  else if (product.stock <= product.minStock) badges.push(['Estoque baixo', 'orange']);

  if (remaining < 0) badges.push(['Vencido', 'red']);
  else if (remaining <= data.settings.expirationAlertDays) badges.push(['Perto da validade', 'orange']);

  if (!badges.length) badges.push(['Ativo', 'green']);

  return badges.map(([label, cls]) => `<span class="badge ${cls}">${label}</span>`).join('');
}

function rowItem({ title, subtitle, amount, status, icon = 'receipt', image = '', type = 'positive', href = '', attr = '' }) {
  const tag = href ? 'a' : 'button';
  const link = href ? `href="${href}"` : '';
  const visual = image
    ? `<img class="row-thumb" src="${escapeHtml(image)}" alt="">`
    : `<span class="row-icon">${icons[icon] || icons.receipt}</span>`;
  const amountHtml = amount !== undefined ? `<strong class="${type}">${amount < 0 ? '- ' : ''}${brl.format(Math.abs(amount))}</strong>` : '';

  return `
    <${tag} class="row-item" ${link} ${attr}>
      <div class="row-left">
        ${visual}
        <span class="row-text">
          <strong>${escapeHtml(title)}</strong>
          <span>${escapeHtml(subtitle)}</span>
        </span>
      </div>
      <span class="row-right">
        ${amountHtml}
        <span>${escapeHtml(status || '')}</span>
      </span>
    </${tag}>
  `;
}

function summaryLine(label, value) {
  return `<div class="summary-line"><span>${label}</span><strong>${value}</strong></div>`;
}

function financeCard(title, value, note) {
  return `
    <article class="finance-card">
      <span>${title}</span>
      <strong>${typeof value === 'number' ? brl.format(value) : value}</strong>
      <small>${note}</small>
    </article>
  `;
}

function cartItems() {
  return cart.map(item => {
    const product = data.products.find(p => p.id === item.productId);
    return product ? { ...item, product, total: item.qty * product.price } : null;
  }).filter(Boolean);
}

function cartTotal() {
  return cartItems().reduce((sum, item) => sum + item.total, 0);
}

function saveCart() {
  localStorage.setItem('cart', JSON.stringify(cart));
  localStorage.setItem('saleStep', String(saleStep));
  localStorage.setItem('payment', currentPayment);
  localStorage.setItem('receivedAmount', String(receivedAmount));
  localStorage.setItem('saleClientId', String(saleClientId));
  localStorage.setItem('saleDueDate', saleDueDate);
}

function addToCart(id) {
  const product = data.products.find(p => p.id === id);

  if (!product) return;

  if (data.settings.blockExpiredProducts && daysTo(product.expiry) < 0) {
    showToast('Produto vencido bloqueado pelas configurações');
    return;
  }

  if (data.settings.blockNegativeStock && product.stock <= 0) {
    showToast('Estoque indisponível pelas configurações');
    return;
  }

  const item = cart.find(i => i.productId === id);
  if (item) item.qty += 1;
  else cart.push({ productId: id, qty: 1 });
  saveCart();
}

function removeFromCart(id) {
  const item = cart.find(i => i.productId === id);
  if (!item) return;
  item.qty -= 1;
  if (item.qty <= 0) cart = cart.filter(i => i.productId !== id);
  saveCart();
}

function initIcons() {
  $all('[data-icon]').forEach(el => {
    el.innerHTML = icons[el.dataset.icon] || '';
  });
}

function initDashboard() {
  const dashboard = data.dashboard || {};
  const info = dashboard.summary || data.dashboardInfo || { sales_count: 0, total_sales: 0, estimated_profit: 0 };
  const total = Number(info.total_sales);
  const count = Number(info.sales_count);
  const profit = Number(info.estimated_profit || 0);
  const expiringProducts = Array.isArray(dashboard.expiringProducts) && dashboard.expiringProducts.length
    ? dashboard.expiringProducts
    : data.products.filter(p => daysTo(p.expiry) <= data.settings.expirationAlertDays).slice(0, 3);
  const latestSales = Array.isArray(dashboard.latestSales) && dashboard.latestSales.length
    ? dashboard.latestSales
    : data.sales.slice(0, 3);
  const topProducts = Array.isArray(dashboard.topProducts) && dashboard.topProducts.length
    ? dashboard.topProducts
    : data.products.slice(0, 3);
  
  $('#todayTotal').textContent = brl.format(total);
  $('#todaySalesCount').textContent = count;

  $('#dashboardFinance').innerHTML = [
    financeCard('Total vendido', total, `${count} vendas`),
    financeCard('Lucro estimado', profit, 'Preço de venda - custo')
  ].join('');

  $('#dailyReport').innerHTML = [
    summaryLine('Vendas realizadas', String(count)),
    summaryLine('Ticket médio', count > 0 ? brl.format(total / count) : 'R$ 0,00'),
  ].join('');

  $('#expiringProducts').innerHTML = expiringProducts.map(p => rowItem({
      title: p.name || p.nome,
      subtitle: `Lote ${p.lot || p.lote || '-'} • Validade ${formatDate(p.expiry || p.validade)}`,
      status: `${Math.max(daysTo(p.expiry || p.validade), 0)} dias`,
      image: p.image ? img(p.image) : '',
      icon: 'product',
      type: daysTo(p.expiry || p.validade) <= 3 ? 'negative' : 'warning',
      href: 'pages/produtos.php'
    })).join('') || emptyState('Nenhum produto próximo da validade.');

  $('#latestSales').innerHTML = latestSales.map(s => {
    const parts = dateTimeParts(s.criado_em);
    return rowItem({
    title: `Venda nº ${String(s.id).padStart(6, '0')}`,
    subtitle: `${paymentLabel(s.payment || s.metodo)} • ${s.time || parts.time || '-'} • ${s.seller || s.vendedor || '-'}`,
    amount: Number(s.total),
    status: s.status || 'Finalizada',
    icon: 'receipt',
    href: `pages/venda-detalhes.php?id=${s.id}`
  });
  }).join('') || emptyState('Nenhuma venda registrada ainda.');

  $('#featuredProducts').innerHTML = topProducts.map(p => rowItem({
    title: p.name || p.nome || p.produto,
    subtitle: p.category || p.categoria || 'Produto vendido',
    amount: p.price !== undefined ? Number(p.price) : undefined,
    status: p.total_vendido !== undefined ? `${Number(p.total_vendido)} vendidos` : 'Cadastrado',
    image: p.image ? img(p.image) : '',
    icon: 'product',
    href: 'pages/produtos.php'
  })).join('') || emptyState('Cadastre produtos para começar.', '<a class="primary-btn section-gap-small" href="pages/produto-form.php">Cadastrar produto</a>');
}

function initSale() {
  renderSale();
}

function renderSale() {
  $('#saleStepper').innerHTML = [1, 2, 3, 4].map(n => `<span class="step ${saleStep >= n ? 'active' : ''}"></span>`).join('');
  const box = $('#saleWizard');

  if (saleStep === 1) box.innerHTML = saleStepProducts();
  if (saleStep === 2) box.innerHTML = saleStepReview();
  if (saleStep === 3) box.innerHTML = saleStepPayment();
  if (saleStep === 4) box.innerHTML = saleStepFinished();

  initIcons();
}

function saleStepProducts() {
  return `
    <div class="sheet-title">
      <div>
        <h2>Escolha os produtos</h2>
        <p>Busque por nome, SKU, código ou câmera</p>
      </div>
    </div>

    <div class="product-search-actions">
      <label class="search-box">
        <span data-icon="search"></span>
        <input id="saleProductSearch" type="search" placeholder="Digite parte do nome ou código">
      </label>
      <button class="scan-btn" data-open-scanner aria-label="Ler código">${icons.camera}</button>
    </div>

    <div id="saleProducts" class="section-gap-small"></div>

    ${cartFooter()}
  `;
}

function renderSaleProducts(query = '') {
  const q = query.toLowerCase();
  $('#saleProducts').innerHTML = data.products
    .filter(p => `${p.name} ${p.sku} ${p.barcode} ${p.category}`.toLowerCase().includes(q))
    .map(productCardForSale).join('') || emptyState('Nenhum produto cadastrado para vender.', '<a class="primary-btn section-gap-small" href="produto-form.php">Cadastrar produto</a>');
}

function productCardForSale(p) {
  const item = cart.find(i => i.productId === p.id);
  const qty = item ? item.qty : 0;

  return `
    <article class="product-card">
      <img src="${img(p.image)}" alt="${p.name}">
      <div class="product-info">
        <h3>${p.name}</h3>
        <p>${p.category} • ${p.sku}</p>
        <div class="product-meta">
          <span>${brl.format(p.price)}</span>
          <span>Estoque ${p.stock}</span>
          <span>Val. ${formatDate(p.expiry)}</span>
        </div>
        <div class="badge-row">${statusBadges(p)}</div>
        <div class="qty-control">
          <button data-cart-minus="${p.id}">−</button>
          <strong>${qty}</strong>
          <button data-cart-plus="${p.id}">+</button>
        </div>
      </div>
    </article>
  `;
}

function saleStepReview() {
  return `
    <div class="sheet-title"><div><h2>Revisar venda</h2><p>Confira quantidades antes do pagamento</p></div></div>
    <div class="list-card">
      ${cartItems().map(item => rowItem({
        title: `${item.qty}x ${item.product.name}`,
        subtitle: `${brl.format(item.product.price)} cada • Lote ${item.product.lot}`,
        amount: item.total,
        status: 'Subtotal',
        image: img(item.product.image)
      })).join('')}
    </div>
    <article class="summary-card section-gap-small">
      ${summaryLine('Subtotal', brl.format(cartTotal()))}
      ${summaryLine('Desconto', 'R$ 0,00')}
      ${summaryLine('Acréscimo', 'R$ 0,00')}
      ${summaryLine('Total', brl.format(cartTotal()))}
    </article>
    <div class="button-row section-gap-small">
      <button class="ghost-btn" data-sale-step="1">Voltar</button>
      <button class="primary-btn" data-sale-step="3">Ir para pagamento</button>
    </div>
  `;
}

function saleStepPayment() {
  const total = cartTotal();
  const methods = paymentMethods();

  if (!methods.includes(currentPayment)) {
    currentPayment = methods[0] || '';
    saveCart();
  }

  return `
    <div class="sheet-title"><div><h2>Forma de pagamento</h2><p>Escolha como o cliente vai pagar</p></div></div>
    <article class="checkout-summary">
      <p>Total da venda</p>
      <h2>${brl.format(total)}</h2>
      <span>${cartItems().length} itens adicionados</span>
    </article>
    <div class="payment-methods section-gap-small">
      ${methods.map(m => `<button class="${currentPayment === m ? 'active' : ''}" data-payment="${m}">${m}</button>`).join('') || emptyState('Nenhuma forma de pagamento ativa. Ajuste em Configurações.')}
    </div>

    ${currentPayment === 'Conta do cliente' ? saleAccountFields() : ''}

    <div class="button-row section-gap-small">
      <button class="ghost-btn" data-sale-step="2">Voltar</button>
      <button class="primary-btn" data-finish-sale>Finalizar venda</button>
    </div>
  `;
}

function saleAccountFields() {
  if (!saleDueDate) {
    saleDueDate = defaultDueDate();
    saveCart();
  }

  return `
    <div class="form-card section-gap-small">
      <div class="form-grid">
        <div class="field">
          <label>Cliente</label>
          <select id="saleClientId">
            <option value="">Selecione o cliente</option>
            ${data.clients.map(c => `<option value="${c.id}" ${Number(c.id) === saleClientId ? 'selected' : ''}>${escapeHtml(c.name)}</option>`).join('')}
          </select>
        </div>
        <div class="field">
          <label>Vencimento</label>
          <input id="saleDueDate" type="date" value="${saleDueDate}">
        </div>
      </div>
      ${data.clients.length ? '' : '<a class="secondary-btn section-gap-small" href="clientes.php">Cadastrar cliente</a>'}
    </div>
  `;
}

function saleStepFinished() {
  return `
    <article class="checkout-summary">
      <p>Venda finalizada com sucesso</p>
      <h2>${brl.format(cartTotal())}</h2>
      <span>Pagamento: ${currentPayment}</span>
    </article>
    <div class="button-row section-gap-small">
      <a class="ghost-btn" href="../index.php">Dashboard</a>
      <a class="primary-btn" href="historico-vendas.php">Histórico</a>
    </div>
    <button class="secondary-btn section-gap-small" data-reset-sale>Nova venda</button>
  `;
}

function cartFooter() {
  const disabled = cartItems().length === 0 ? 'disabled' : '';

  return `
    <aside class="cart-sticky">
      <div class="cart-sticky-row">
        <span>${cartItems().length} itens no carrinho</span>
        <strong>${brl.format(cartTotal())}</strong>
      </div>
      <button class="primary-btn" data-next-sale-step ${disabled}>Continuar</button>
    </aside>
  `;
}

async function finishSale() {
  if (!cartItems().length) {
    showToast('Adicione produtos antes de finalizar');
    return;
  }

  const payload = {
    items: cart.map(item => ({ productId: item.productId, qty: item.qty })),
    payment: currentPayment
  };

  if (currentPayment === 'Conta do cliente') {
    if (saleClientId <= 0) {
      showToast('Selecione o cliente');
      return;
    }

    if (!saleDueDate) {
      showToast('Informe o vencimento');
      return;
    }

    payload.clientId = saleClientId;
    payload.dueDate = saleDueDate;
  }

  try {
    const json = await postJson(`${prefix}api/vendas/finalizar.php`, payload);
    cart = [];
    saleStep = 1;
    saleClientId = 0;
    saleDueDate = '';
    saveCart();
    location.href = `comprovante.php?id=${json.data.id}`;
  } catch (error) {
    showToast(error.message);
  }
}

function initProducts() {
  renderProducts();
}

function renderProducts() {
  const query = ($('#productSearch')?.value || '').toLowerCase();
  const activeFilter = $('#productFilters .active')?.dataset.filter || 'Todos';

  const list = data.products.filter(p => {
    const text = `${p.name} ${p.sku} ${p.lot} ${p.category}`.toLowerCase();
    if (!text.includes(query)) return false;
    if (activeFilter === 'Estoque baixo') return p.stock <= p.minStock;
    if (activeFilter === 'Perto da validade') return daysTo(p.expiry) <= data.settings.expirationAlertDays && daysTo(p.expiry) >= 0;
    if (activeFilter === 'Vencidos') return daysTo(p.expiry) < 0;
    return true;
  });

  $('#productsList').innerHTML = list.map(productListCard).join('') || '<article class="summary-card">Nenhum produto encontrado.</article>';
}

function productListCard(p) {
  return `
    <article class="product-card">
      <img src="${img(p.image)}" alt="${p.name}">
      <div class="product-info">
        <h3>${p.name}</h3>
        <p>${p.category} • ${p.sku}</p>
        <div class="product-meta">
          <span>Lote ${p.lot}</span>
          <span>Val. ${formatDate(p.expiry)}</span>
          <span>Qtd. ${p.stock}</span>
          <span>Mín. ${p.minStock}</span>
          <span>${brl.format(p.price)}</span>
        </div>
        <div class="badge-row">${statusBadges(p)}</div>
        <div class="card-actions">
          <a href="produto-form.php?id=${p.id}">Editar</a>
          <button class="danger-mini" data-delete-product="${p.id}">Excluir</button>
        </div>
      </div>
    </article>
  `;
}

function initProductForm() {
  const id = Number(qs('id') || 0);
  const p = data.products.find(x => x.id === id);

  $('#productFormTitle').textContent = p ? 'Editar produto' : 'Cadastrar produto';

  if (p) {
    $('#productPreview').src = img(p.image);
    $('#productName').value = p.name;
    $('#productSku').value = p.sku;
    $('#productBarcode').value = p.barcode;
    $('#productCategory').value = p.category;
    $('#productCost').value = p.cost;
    $('#productPrice').value = p.price;
    $('#productStock').value = p.stock;
    $('#productMinStock').value = p.minStock;
    $('#productLot').value = p.lot;
    $('#productExpiry').value = p.expiry;
  } else {
    $('#productMinStock').value = Number(data.settings.defaultMinStock || 0);
  }
}

async function saveProductForm(form) {
  const id = Number(qs('id') || 0);
  const preview = $('#productPreview')?.getAttribute('src') || '';
  const image = preview.includes('data:') ? '' : preview.replace(prefix, '');
  const formData = new FormData();

  formData.append('id', String(id));
  formData.append('name', $('#productName')?.value.trim() || '');
  formData.append('sku', $('#productSku')?.value.trim() || '');
  formData.append('barcode', $('#productBarcode')?.value.trim() || '');
  formData.append('category', $('#productCategory')?.value.trim() || '');
  formData.append('cost', String(Number($('#productCost')?.value || 0)));
  formData.append('price', String(Number($('#productPrice')?.value || 0)));
  formData.append('stock', String(Number($('#productStock')?.value || 0)));
  formData.append('minStock', String(Number($('#productMinStock')?.value || 0)));
  formData.append('lot', $('#productLot')?.value.trim() || '');
  formData.append('expiry', $('#productExpiry')?.value || '');
  formData.append('image', image);

  const imageFile = $('#productImageInput')?.files?.[0];
  if (imageFile) {
    formData.append('imageFile', imageFile);
  }

  try {
    await postForm(`${prefix}api/produtos/salvar.php`, formData);
    showToast('Produto salvo');
    setTimeout(() => location.href = 'produtos.php', 500);
  } catch (error) {
    showToast(error.message);
    form.querySelector('button[type="submit"]')?.removeAttribute('disabled');
  }
}

async function deleteProduct(id) {
  try {
    await postJson(`${prefix}api/produtos/excluir.php`, { id });
    await loadProducts();
    closeModal();
    if (page === 'produtos') renderProducts();
    showToast('Produto inativado');
  } catch (error) {
    showToast(error.message);
  }
}

async function initReports() {
  $('#reportFinance').innerHTML = emptyState('Carregando relatório...');
  $('#weeklyBars').innerHTML = emptyState('Carregando vendas...');
  $('#reportTables').innerHTML = '';

  try {
    await loadReport();
    renderReports();
  } catch (error) {
    $('#reportFinance').innerHTML = emptyState(error.message);
    $('#weeklyBars').innerHTML = emptyState('Não foi possível montar o gráfico.');
  }
}

function reportQueryString() {
  const params = new URLSearchParams({ period: reportPeriod });

  if (reportPeriod === 'periodo') {
    params.set('start', reportStartDate);
    params.set('end', reportEndDate);
  }

  return params.toString();
}

async function loadReport() {
  const query = reportQueryString();
  const [summary, sales, products, validity] = await Promise.all([
    fetchJson(`${prefix}api/relatorios/resumo.php?${query}`),
    fetchJson(`${prefix}api/relatorios/vendas.php?${query}`),
    fetchJson(`${prefix}api/relatorios/produtos.php?${query}`),
    fetchJson(`${prefix}api/relatorios/validade.php`)
  ]);

  data.report = {
    summary: summary.summary || {},
    paymentMethods: summary.paymentMethods || [],
    sales: Array.isArray(sales) ? sales : [],
    products: products || { sold: [], lowStock: [] },
    validity: Array.isArray(validity) ? validity : []
  };
}

function renderReports() {
  const summary = data.report.summary || {};
  const paymentMethodsData = data.report.paymentMethods || [];
  const total = Number(summary.total_sales || 0);
  const count = Number(summary.sales_count || 0);

  $('#reportFinance').innerHTML = [
    financeCard('Faturamento', total, 'Período'),
    financeCard('Ticket médio', Number(summary.average_ticket || 0), 'Média'),
    financeCard('Métodos', String(paymentMethodsData.length), 'Formas usadas'),
    financeCard('Total de vendas', String(count), 'Movimentos')
  ].join('');

  $('#weeklyBars').innerHTML = data.report.sales.length
    ? buildWeeklyBars(data.report.sales)
    : emptyState('Sem vendas no período para montar o gráfico.');

  const paymentTotal = paymentMethodsData.reduce((sum, item) => sum + Number(item.total_value || 0), 0);
  const paymentLines = paymentMethodsData.map(item => {
    const method = paymentLabel(item.metodo);
    const value = Number(item.total_value || 0);
    const percent = paymentTotal > 0 ? Math.round((value / paymentTotal) * 100) : 0;
    return `<p><span><span class="dot pix"></span>${escapeHtml(method)}</span><strong>${percent}%</strong></p>`;
  }).join('') || '<p><span>Nenhum pagamento registrado</span><strong>0%</strong></p>';

  const paymentCard = document.querySelector('.payment-card .payment-lines');
  if (paymentCard) paymentCard.innerHTML = paymentLines;

  $('#reportTables').innerHTML = [
    reportTable('Produtos mais vendidos', ['Produto', 'Qtde', 'Receita'], (data.report.products.sold || []).map(p => [p.produto, Number(p.quantidade || 0), brl.format(Number(p.receita || 0))])),
    reportTable('Produtos próximos da validade', ['Produto', 'Lote', 'Validade'], data.report.validity.map(p => [p.nome, p.lote || '-', formatDate(p.validade)])),
    reportTable('Estoque baixo', ['Produto', 'Atual', 'Mínimo'], (data.report.products.lowStock || []).map(p => [p.nome, Number(p.quantidade || 0), Number(p.estoque_minimo || 0)]))
  ].join('');
}

function buildWeeklyBars(sales = data.sales) {
  const days = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];
  const totals = days.map((day, index) => ({
    day,
    total: sales
      .filter(s => {
        const date = s.date || dateTimeParts(s.criado_em).date;
        return new Date(`${date}T00:00:00`).getDay() === index;
      })
      .reduce((sum, s) => sum + Number(s.total || 0), 0)
  }));
  const max = Math.max(...totals.map(item => item.total), 1);

  return totals.map(item => `<span class="bar" style="height:${Math.max((item.total / max) * 100, item.total > 0 ? 8 : 0)}%" data-day="${item.day}"></span>`).join('');
}

function reportTable(title, headers, rows) {
  return `
    <div class="sheet-title section-gap"><div><h2>${title}</h2><p>Dados exportáveis</p></div></div>
    <div class="table-card">
      <table class="mobile-table">
        <thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead>
        <tbody>${rows.length ? rows.map(row => `<tr>${row.map(col => `<td>${escapeHtml(col)}</td>`).join('')}</tr>`).join('') : `<tr><td colspan="${headers.length}">Nenhum dado registrado.</td></tr>`}</tbody>
      </table>
    </div>
  `;
}

function initClients() {
  renderClients();
}

function renderClients() {
  const query = ($('#clientSearch')?.value || '').toLowerCase();
  const filter = $('#clientFilters .active')?.dataset.clientFilter || 'Todos';

  const clients = data.clients.filter(c => {
    if (!`${c.name} ${c.phone}`.toLowerCase().includes(query)) return false;
    if (filter === 'Em dia') return c.status === 'Em dia';
    if (filter === 'Devendo') return c.debt > 0;
    if (filter === 'Atrasados') return c.status === 'Atrasado';
    return true;
  });

  $('#clientsList').innerHTML = clients.map(clientCard).join('') || emptyState('Nenhum cliente cadastrado.');
}

function clientCard(c) {
  const cls = c.status === 'Em dia' ? 'green' : c.status === 'Atrasado' ? 'red' : 'orange';
  return `
    <article class="client-card">
      <div class="client-top">
        <div><h3>${c.name}</h3><p>${c.phone}</p></div>
        <span class="badge ${cls}">${c.status}</span>
      </div>
      <div class="client-summary">
        <div><span>Total em aberto</span><strong>${brl.format(c.debt)}</strong></div>
        <div><span>Vencimento</span><strong>${c.due ? formatDate(c.due) : '-'}</strong></div>
        <div><span>Situação</span><strong>${c.status}</strong></div>
      </div>
      <div class="client-actions">
        <a href="cliente-detalhes.php?id=${c.id}">Visualizar</a>
        <button data-edit-client="${c.id}">Editar</button>
        <button data-client-actions="${c.id}">Mais ações</button>
      </div>
    </article>
  `;
}

function initClientDetail() {
  const id = Number(qs('id') || 0);
  $('#clientNameTitle').textContent = 'Cliente';
  $('#clientDetailContent').innerHTML = emptyState('Carregando cliente...');
  loadClientDetails(id)
    .then(renderClientDetail)
    .catch(() => {
      $('#clientDetailContent').innerHTML = emptyState('Cliente não encontrado.');
    });
}

function renderClientDetail(c) {
  if (!c) {
    $('#clientNameTitle').textContent = 'Cliente';
    $('#clientDetailContent').innerHTML = emptyState('Cliente não encontrado.');
    return;
  }

  $('#clientNameTitle').textContent = c.name;
  $('#clientDetailContent').innerHTML = `
    <article class="summary-card">
      ${summaryLine('Telefone', c.phone)}
      ${summaryLine('CPF/CNPJ', c.cpf)}
      ${summaryLine('Endereço', c.address)}
    </article>

    <div class="sheet-title section-gap"><div><h2>Resumo da conta</h2><p>Controle de débitos e pagamentos parciais</p></div></div>
    <article class="summary-card">
      ${summaryLine('Total pago', brl.format(c.paid))}
      ${summaryLine('Saldo em aberto', brl.format(c.debt))}
      ${summaryLine('Próximo vencimento', c.due ? formatDate(c.due) : '-')}
      ${summaryLine('Status', c.status)}
    </article>

    <div class="button-row section-gap-small">
      <button class="primary-btn" data-open-payment="${c.id}">Registrar pagamento</button>
      <button class="secondary-btn" data-send-warning="${c.id}">Enviar aviso</button>
    </div>

    <div class="sheet-title section-gap"><div><h2>Compras pendentes</h2><p>Valores em aberto</p></div></div>
    <div class="list-card">
      ${c.accounts?.length ? c.accounts.map(account => rowItem({ title: `Conta nº ${String(account.id).padStart(6, '0')}`, subtitle: `Vencimento ${account.due ? formatDate(account.due) : '-'}`, amount: account.balance, status: account.status, icon: 'receipt', type: account.status === 'atrasado' ? 'negative' : 'warning' })).join('') : emptyState('Nenhuma compra pendente.')}
    </div>

    <div class="sheet-title section-gap"><div><h2>Histórico financeiro</h2><p>Auditoria da conta</p></div></div>
    <div class="list-card">
      ${c.history.length ? c.history.map(item => rowItem({ title: item.split('—')[1] || item, subtitle: item.split('—')[0] || '', icon: 'receipt' })).join('') : emptyState('Nenhum histórico financeiro registrado.')}
    </div>
  `;
}

function initSalesHistory() {
  renderSalesHistory();
}

function renderSalesHistory() {
  const query = ($('#salesSearch')?.value || '').toLowerCase();

  $('#salesHistoryList').innerHTML = data.sales.filter(s => {
    const text = `${s.id} ${s.customer} ${s.seller} ${s.payment} ${s.items.map(i => i.name).join(' ')}`.toLowerCase();
    if (!text.includes(query)) return false;
    if (!saleMatchesPeriod(s, salesPeriodFilter)) return false;
    if (salesPaymentFilter === 'PIX') return s.payment === 'PIX';
    if (salesPaymentFilter === 'Cartão') return ['Crédito', 'Débito'].includes(s.payment);
    if (salesPaymentFilter === 'Dinheiro') return s.payment === 'Dinheiro';
    if (salesPaymentFilter === 'Fiado') return s.payment === 'Conta do cliente';
    return true;
  }).map(saleCard).join('') || emptyState('Nenhuma venda registrada.');
}

function saleMatchesPeriod(sale, filter) {
  const date = new Date(`${sale.date}T00:00:00`);
  const now = new Date();
  const today = new Date(now.getFullYear(), now.getMonth(), now.getDate());

  if (Number.isNaN(date.getTime())) return true;
  if (filter === 'Hoje') return date.getTime() === today.getTime();
  if (filter === 'Semana') {
    const start = new Date(today);
    start.setDate(today.getDate() - today.getDay());
    return date >= start && date <= today;
  }
  if (filter === 'Mês') return date.getFullYear() === today.getFullYear() && date.getMonth() === today.getMonth();

  return true;
}

function saleCard(s) {
  return `
    <article class="client-card">
      <div class="client-top">
        <div><h3>Venda nº ${String(s.id).padStart(6, '0')}</h3><p>${formatDate(s.date)} às ${s.time}</p></div>
        <span class="badge ${s.status === 'Finalizada' ? 'green' : 'orange'}">${s.status}</span>
      </div>
      <div class="client-summary">
        <div><span>Vendedor</span><strong>${s.seller}</strong></div>
        <div><span>Cliente</span><strong>${s.customer}</strong></div>
        <div><span>Produtos</span><strong>${s.items.length} itens</strong></div>
        <div><span>Pagamento</span><strong>${s.payment}</strong></div>
        <div><span>Total</span><strong>${brl.format(s.total)}</strong></div>
      </div>
      <div class="client-actions">
        <a href="venda-detalhes.php?id=${s.id}">Visualizar</a>
        <a href="comprovante.php?id=${s.id}">Comprovante</a>
      </div>
    </article>
  `;
}

function initSaleDetail() {
  const id = Number(qs('id') || 0);
  $('#saleTitle').textContent = 'Venda';
  $('#saleDetailContent').innerHTML = emptyState('Carregando venda...');

  loadSaleDetails(id)
    .then(s => {
      data.currentSale = s;
      renderSaleDetail(s);
    })
    .catch(() => {
      $('#saleDetailContent').innerHTML = emptyState('Venda não encontrada.');
    });
}

function renderSaleDetail(s) {
  $('#saleTitle').textContent = `Venda nº ${String(s.id).padStart(6, '0')}`;
  $('#saleDetailContent').innerHTML = `
    <article class="summary-card">
      ${summaryLine('Status', s.status)}
      ${summaryLine('Data e hora', `${formatDate(s.date)} às ${s.time}`)}
      ${summaryLine('Vendedor', s.seller)}
      ${summaryLine('Cliente', s.customer)}
      ${summaryLine('Dispositivo', s.device)}
    </article>

    <div class="sheet-title section-gap"><div><h2>Produtos vendidos</h2><p>Itens, lote e validade</p></div></div>
    <div class="list-card">
      ${s.items.map(item => rowItem({ title: `${item.qty}x ${item.name}`, subtitle: `Lote ${item.lot} • Validade ${formatDate(item.expiry)}`, amount: item.qty * item.unit, status: `${brl.format(item.unit)} un.`, icon: 'product' })).join('')}
    </div>

    <div class="sheet-title section-gap"><div><h2>Resumo financeiro</h2><p>Valores da venda</p></div></div>
    <article class="summary-card">
      ${summaryLine('Subtotal', brl.format(s.subtotal))}
      ${summaryLine('Desconto', brl.format(s.discount))}
      ${summaryLine('Acréscimo', brl.format(s.addition))}
      ${summaryLine('Total', brl.format(s.total))}
    </article>

    <div class="sheet-title section-gap"><div><h2>Pagamento</h2><p>Método e situação</p></div></div>
    <article class="summary-card">
      ${summaryLine('Método', s.payment)}
      ${summaryLine('Valor pago', brl.format(s.paid))}
      ${s.payment === 'Conta do cliente' ? summaryLine('Vencimento', formatDate(s.due)) : summaryLine('Troco', brl.format(s.change))}
      ${summaryLine('Status', s.status)}
    </article>

    <div class="button-row section-gap-small">
      <a class="secondary-btn" href="comprovante.php?id=${s.id}">Comprovante</a>
      <button class="secondary-btn" data-download-sale-pdf="${s.id}">Baixar PDF</button>
    </div>

    <button class="danger-btn section-gap-small" data-cancel-sale="${s.id}">Cancelar venda</button>

    <div class="sheet-title section-gap"><div><h2>Auditoria</h2><p>Controle de segurança</p></div></div>
    <article class="summary-card">
      ${summaryLine('Criada por', s.audit.createdBy)}
      ${summaryLine('Data/hora', s.audit.createdAt)}
      ${summaryLine('Última alteração', s.audit.lastChange)}
    </article>
  `;
}

function initReceipt() {
  const id = Number(qs('id') || 0);
  $('#receiptTitle').textContent = 'Comprovante';
  $('#receiptContentWrap').innerHTML = emptyState('Carregando comprovante...');

  loadReceipt(id)
    .then(s => {
      data.currentSale = s;
      renderReceipt(s);
    })
    .catch(() => {
      $('#receiptContentWrap').innerHTML = emptyState('Venda não encontrada para gerar comprovante.');
    });
}

function renderReceipt(s) {
  $('#receiptTitle').textContent = `Venda nº ${String(s.id).padStart(6, '0')}`;
  $('#receiptContentWrap').innerHTML = `
    <article class="receipt-card" id="receiptContent">
      <div class="receipt-head">
        <img src="${prefix}assets/icons/icon.svg" alt="">
        <h2>${data.settings.companyName}</h2>
        <p>${data.settings.companyPhone} • ${data.settings.companyAddress}</p>
        <p>Venda nº ${String(s.id).padStart(6, '0')} • ${formatDate(s.date)} às ${s.time}</p>
      </div>

      <div class="receipt-section">
        <h3>Dados da venda</h3>
        ${receiptLine('Vendedor', s.seller)}
        ${receiptLine('Cliente', s.customer)}
        ${receiptLine('Status', s.status)}
      </div>

      <div class="receipt-section">
        <h3>Itens</h3>
        ${s.items.map(item => `${receiptLine(`${item.qty}x ${item.name}`, brl.format(item.qty * item.unit))}${receiptLine(`Lote ${item.lot}`, `Val. ${formatDate(item.expiry)}`)}`).join('')}
      </div>

      <div class="receipt-section">
        <h3>Resumo</h3>
        ${receiptLine('Subtotal', brl.format(s.subtotal))}
        ${receiptLine('Desconto', brl.format(s.discount))}
        ${receiptLine('Acréscimo', brl.format(s.addition))}
        ${receiptLine('Total', brl.format(s.total), 'receipt-total')}
      </div>

      <div class="receipt-section">
        <h3>Pagamento</h3>
        ${receiptLine('Método', s.payment)}
        ${receiptLine('Valor pago', brl.format(s.paid))}
        ${s.payment === 'Dinheiro' ? receiptLine('Troco', brl.format(s.change)) : ''}
        ${s.payment === 'Conta do cliente' ? receiptLine('Vencimento', formatDate(s.due)) : ''}
      </div>

      <p style="text-align:center;color:var(--muted);font-size:12px;margin:14px 0 0;">Obrigado pela preferência! Volte sempre.</p>
    </article>

    <div class="button-row section-gap-small">
      <button class="secondary-btn" data-share-receipt="${s.id}">WhatsApp</button>
      <button class="secondary-btn" data-print-receipt>Imprimir</button>
    </div>
    <div class="button-row section-gap-small">
      <button class="secondary-btn" data-download-sale-pdf="${s.id}">Baixar PDF</button>
      <a class="primary-btn" href="nova-venda.php">Nova venda</a>
    </div>
  `;
}

function receiptLine(label, value, cls = '') {
  return `<div class="receipt-line ${cls}"><span>${label}</span><strong>${value}</strong></div>`;
}

function initSettings() {
  const settings = [
    ['Empresa', 'Logo, nome, telefone, endereço e dados do comprovante', 'company'],
    ['Usuários e permissões', 'Administrador, gerente, operador, estoquista e leitor', 'users'],
    ['Comprovantes', 'Sempre perguntar, sempre gerar ou nunca gerar', 'receipt'],
    ['Regras de vencimento', 'Validade de produtos e dívidas de clientes', 'due'],
    ['Produtos e estoque', 'Limite mínimo, bloqueio de vencidos e estoque negativo', 'stock'],
    ['Formas de pagamento', 'PIX, dinheiro, cartão, fiado e misto', 'payments'],
    ['Vendas e caixa', 'Desconto, cancelamento, operador e abertura de caixa', 'cash'],
    ['Notificações e segurança', 'Alertas, PIN, auditoria e confirmação de exclusão', 'security']
  ];

  $('#settingsGrid').innerHTML = settings.map(([title, desc, key]) => `
    <button class="setting-card" data-setting="${key}">
      <h3>${title}</h3>
      <p>${desc}</p>
    </button>
  `).join('');
}

function openScanner() {
  openModal(`
    <h2>Ler QR Code ou código de barras</h2>
    <p>A câmera precisa de HTTPS na hospedagem. Se não liberar, digite o código manualmente.</p>
    <div class="camera-box"><video id="scannerVideo" playsinline muted></video></div>
    <div class="form-grid section-gap-small">
      <div class="field"><label>Código manual</label><input id="manualBarcode" placeholder="Digite o código do produto"></div>
      <div class="button-row">
        <button class="ghost-btn" data-close-modal>Fechar</button>
        <button class="primary-btn" data-use-barcode>Buscar</button>
      </div>
    </div>
  `);

  const video = $('#scannerVideo');
  if (!navigator.mediaDevices?.getUserMedia) {
    showToast('Câmera não suportada');
    return;
  }

  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    .then(stream => {
      scannerStream = stream;
      video.srcObject = stream;
      video.play();
    })
    .catch(() => showToast('Permissão de câmera negada'));
}

function stopScanner() {
  if (scannerStream) {
    scannerStream.getTracks().forEach(track => track.stop());
    scannerStream = null;
  }
}

function useBarcode(code) {
  const product = data.products.find(p => p.barcode === code || p.sku === code);
  if (!product) {
    showToast('Produto não encontrado');
    return;
  }
  addToCart(product.id);
  closeModal();
  renderSale();
  showToast(`${product.name} adicionado`);
}

function openClientActions(id) {
  const c = data.clients.find(x => x.id === id);
  openModal(`
    <h2>Mais ações</h2>
    <p>${c.name}</p>
    <div class="settings-list list-card">
      <button data-open-payment="${c.id}"><span>Registrar pagamento</span><strong>›</strong></button>
      <button data-send-warning="${c.id}"><span>Enviar aviso da conta</span><strong>›</strong></button>
      <a href="cliente-detalhes.php?id=${c.id}"><span>Ver histórico</span><strong>›</strong></a>
    </div>
    <button class="ghost-btn section-gap-small" data-close-modal>Fechar</button>
  `);
}

function openPaymentModal(id) {
  loadClientDetails(id)
    .then(c => {
      const account = c.accounts?.[0];
      if (!account) {
        openModal(`<h2>Registrar pagamento</h2><p>Cliente sem conta em aberto.</p><button class="primary-btn section-gap-small" data-close-modal>Fechar</button>`);
        return;
      }

      openModal(`
        <h2>Registrar pagamento</h2>
        <p>Saldo atual: <strong>${brl.format(account.balance)}</strong></p>
        <div class="form-grid">
          <div class="field"><label>Valor pago agora</label><input id="partialAmount" type="number" min="0.01" step="0.01" value="${account.balance}"></div>
          <div class="field"><label>Novo vencimento do restante</label><input id="newDueDate" type="date"></div>
          <div class="field"><label>Forma de pagamento</label><select id="partialMethod">${paymentMethods().filter(m => m !== 'Conta do cliente' && m !== 'Misto').map(m => `<option>${m}</option>`).join('')}</select></div>
          <div class="button-row">
            <button class="ghost-btn" data-close-modal>Cancelar</button>
            <button class="primary-btn" data-save-client-payment="${account.id}">Confirmar</button>
          </div>
        </div>
      `);
    })
    .catch(error => showToast(error.message));
}

function openClientForm(client = {}) {
  openModal(`
    <h2>${client.id ? 'Editar cliente' : 'Cadastrar cliente'}</h2>
    <div class="form-grid section-gap-small">
      <div class="field"><label>Nome</label><input id="clientFormName" maxlength="180" value="${escapeHtml(client.name || '')}"></div>
      <div class="field"><label>Telefone</label><input id="clientFormPhone" maxlength="30" value="${escapeHtml(client.phone || '')}"></div>
      <div class="field"><label>CPF/CNPJ</label><input id="clientFormCpf" maxlength="20" value="${escapeHtml(client.cpf || '')}"></div>
      <div class="field"><label>Endereço</label><input id="clientFormAddress" maxlength="255" value="${escapeHtml(client.address || '')}"></div>
      <button class="primary-btn" data-save-client="${client.id || 0}">Salvar</button>
    </div>
  `);
}

async function saveClient(id) {
  try {
    await postJson(`${prefix}api/clientes/salvar.php`, {
      id,
      name: $('#clientFormName')?.value.trim() || '',
      phone: $('#clientFormPhone')?.value.trim() || '',
      cpf: $('#clientFormCpf')?.value.trim() || '',
      address: $('#clientFormAddress')?.value.trim() || ''
    });
    await loadClients();
    closeModal();
    if (page === 'clientes') renderClients();
    showToast('Cliente salvo');
  } catch (error) {
    showToast(error.message);
  }
}

async function saveClientPayment(accountId) {
  try {
    await postJson(`${prefix}api/clientes/pagamento.php`, {
      accountId,
      amount: Number($('#partialAmount')?.value || 0),
      method: $('#partialMethod')?.value || 'PIX',
      newDueDate: $('#newDueDate')?.value || ''
    });
    await loadClients();
    closeModal();
    if (page === 'clientes') renderClients();
    showToast('Pagamento registrado');
  } catch (error) {
    showToast(error.message);
  }
}

function sendWarning(id) {
  const c = data.clients.find(x => x.id === id);
  const msg = `Olá, ${c.name}. Consta um saldo em aberto de ${brl.format(c.debt)} com vencimento em ${formatDate(c.due)}. Você pode pagar por PIX, dinheiro ou cartão.`;
  navigator.clipboard?.writeText(msg);
  window.open(`https://wa.me/55${c.phone.replace(/\D/g, '')}?text=${encodeURIComponent(msg)}`, '_blank');
}

function checked(value) {
  return value ? 'checked' : '';
}

function settingToggle(id, label, value) {
  return `<label class="check-row"><input id="${id}" type="checkbox" ${checked(value)}><span>${label}</span></label>`;
}

function paymentMethods() {
  const methods = [
    ['PIX', 'paymentPix'],
    ['Crédito', 'paymentCredit'],
    ['Débito', 'paymentDebit'],
    ['Dinheiro', 'paymentCash'],
    ['Conta do cliente', 'paymentAccount'],
    ['Misto', 'paymentMixed']
  ];

  return methods.filter(([, key]) => data.settings[key]).map(([label]) => label);
}

function openSetting(key) {
  const settings = data.settings;
  const companyName = escapeHtml(settings.companyName);
  const companyPhone = escapeHtml(settings.companyPhone);
  const companyAddress = escapeHtml(settings.companyAddress);
  const receiptMode = settings.receiptMode || 'perguntar';
  const receiptTemplate = settings.receiptTemplate || 'detalhado';
  const usersList = data.users.length
    ? data.users.map(u => rowItem({ title: u.name, subtitle: `${u.role} • ${u.email}`, status: u.status, icon: 'user' })).join('')
    : emptyState('Nenhum usuário cadastrado.');

  const html = {
    company: `<h2>Empresa</h2><div class="form-grid section-gap-small"><div class="field"><label>Nome da empresa</label><input id="settingCompanyName" maxlength="180" value="${companyName}"></div><div class="field"><label>Telefone</label><input id="settingCompanyPhone" maxlength="30" value="${companyPhone}"></div><div class="field"><label>Endereço</label><input id="settingCompanyAddress" maxlength="255" value="${companyAddress}"></div><button class="primary-btn" data-save-setting="company">Salvar</button></div>`,
    users: `<h2>Usuários e permissões</h2><div class="list-card section-gap-small">${usersList}</div><div class="form-grid section-gap-small"><div class="field"><label>Nome</label><input id="settingUserName" maxlength="140"></div><div class="field"><label>E-mail</label><input id="settingUserEmail" type="email" maxlength="180"></div><div class="field"><label>Senha inicial</label><input id="settingUserPassword" type="password" minlength="8"></div><div class="field"><label>Perfil</label><select id="settingUserRole"><option value="operador">Operador</option><option value="gerente">Gerente</option><option value="estoquista">Estoquista</option><option value="leitor">Leitor</option><option value="admin">Administrador</option></select></div><button class="primary-btn" data-save-setting="users">Adicionar usuário</button></div>`,
    receipt: `<h2>Comprovantes</h2><div class="form-grid section-gap-small"><div class="field"><label>Ao finalizar venda</label><select id="settingReceiptMode"><option value="perguntar" ${receiptMode === 'perguntar' ? 'selected' : ''}>Sempre perguntar</option><option value="sempre" ${receiptMode === 'sempre' ? 'selected' : ''}>Sempre gerar</option><option value="nunca" ${receiptMode === 'nunca' ? 'selected' : ''}>Nunca gerar</option></select></div><div class="field"><label>Modelo</label><select id="settingReceiptTemplate"><option value="detalhado" ${receiptTemplate === 'detalhado' ? 'selected' : ''}>Detalhado</option><option value="simples" ${receiptTemplate === 'simples' ? 'selected' : ''}>Simples</option></select></div><button class="primary-btn" data-save-setting="receipt">Salvar</button></div>`,
    due: `<h2>Regras de vencimento</h2><div class="form-grid section-gap-small"><div class="field"><label>Alerta de validade</label><input id="settingExpirationAlertDays" type="number" min="0" max="365" value="${Number(settings.expirationAlertDays || 0)}"></div><div class="field"><label>Prazo padrão de dívida</label><input id="settingDebtDueDays" type="number" min="1" max="365" value="${Number(settings.debtDueDays || 30)}"></div><button class="primary-btn" data-save-setting="due">Salvar</button></div>`,
    stock: `<h2>Produtos e estoque</h2><div class="form-grid section-gap-small"><div class="field"><label>Estoque mínimo padrão</label><input id="settingDefaultMinStock" type="number" min="0" max="999999" value="${Number(settings.defaultMinStock || 0)}"></div>${settingToggle('settingBlockExpiredProducts', 'Bloquear venda de produto vencido', settings.blockExpiredProducts)}${settingToggle('settingBlockNegativeStock', 'Bloquear estoque negativo', settings.blockNegativeStock)}${settingToggle('settingLowStockAlert', 'Alertar estoque baixo', settings.lowStockAlert)}<button class="primary-btn" data-save-setting="stock">Salvar</button></div>`,
    payments: `<h2>Formas de pagamento</h2><div class="form-grid section-gap-small">${settingToggle('settingPaymentPix', 'PIX', settings.paymentPix)}${settingToggle('settingPaymentCash', 'Dinheiro', settings.paymentCash)}${settingToggle('settingPaymentCredit', 'Cartão de crédito', settings.paymentCredit)}${settingToggle('settingPaymentDebit', 'Cartão de débito', settings.paymentDebit)}${settingToggle('settingPaymentAccount', 'Conta do cliente', settings.paymentAccount)}${settingToggle('settingPaymentMixed', 'Pagamento misto', settings.paymentMixed)}<button class="primary-btn" data-save-setting="payments">Salvar</button></div>`,
    cash: `<h2>Vendas e caixa</h2><div class="form-grid section-gap-small">${settingToggle('settingAllowDiscount', 'Permitir desconto na venda', settings.allowDiscount)}<div class="field"><label>Limite de desconto (%)</label><input id="settingDiscountLimitPercent" type="number" min="0" max="100" value="${Number(settings.discountLimitPercent || 0)}"></div>${settingToggle('settingRequireCustomerForAccount', 'Exigir cliente para venda na conta', settings.requireCustomerForAccount)}${settingToggle('settingRequireCancellationReason', 'Exigir motivo para cancelamento', settings.requireCancellationReason)}<button class="primary-btn" data-save-setting="cash">Salvar</button></div>`,
    security: `<h2>Notificações e segurança</h2><div class="form-grid section-gap-small">${settingToggle('settingAuditLogEnabled', 'Manter auditoria ativa', settings.auditLogEnabled)}${settingToggle('settingConfirmDeletes', 'Confirmar exclusões sensíveis', settings.confirmDeletes)}${settingToggle('settingOperatorPinEnabled', 'Exigir PIN de operador', settings.operatorPinEnabled)}${settingToggle('settingNotificationsEnabled', 'Ativar notificações e alertas', settings.notificationsEnabled)}<button class="primary-btn" data-save-setting="security">Salvar</button></div>`
  }[key] || `<h2>Configuração</h2><p>Configuração não encontrada.</p>`;

  openModal(`${html}<button class="ghost-btn section-gap-small" data-close-modal>Fechar</button>`);
}

async function saveSetting(section, button) {
  const payload = { section };

  if (section === 'company') {
    payload.companyName = $('#settingCompanyName')?.value.trim() || '';
    payload.companyPhone = $('#settingCompanyPhone')?.value.trim() || '';
    payload.companyAddress = $('#settingCompanyAddress')?.value.trim() || '';
  }

  if (section === 'receipt') {
    payload.receiptMode = $('#settingReceiptMode')?.value || 'perguntar';
    payload.receiptTemplate = $('#settingReceiptTemplate')?.value || 'detalhado';
  }

  if (section === 'users') {
    payload.userName = $('#settingUserName')?.value.trim() || '';
    payload.userEmail = $('#settingUserEmail')?.value.trim() || '';
    payload.userPassword = $('#settingUserPassword')?.value || '';
    payload.userRole = $('#settingUserRole')?.value || 'operador';
  }

  if (section === 'due') {
    payload.expirationAlertDays = Number($('#settingExpirationAlertDays')?.value || 0);
    payload.debtDueDays = Number($('#settingDebtDueDays')?.value || 0);
  }

  if (section === 'stock') {
    payload.defaultMinStock = Number($('#settingDefaultMinStock')?.value || 0);
    payload.blockExpiredProducts = $('#settingBlockExpiredProducts')?.checked || false;
    payload.blockNegativeStock = $('#settingBlockNegativeStock')?.checked || false;
    payload.lowStockAlert = $('#settingLowStockAlert')?.checked || false;
  }

  if (section === 'payments') {
    payload.paymentPix = $('#settingPaymentPix')?.checked || false;
    payload.paymentCash = $('#settingPaymentCash')?.checked || false;
    payload.paymentCredit = $('#settingPaymentCredit')?.checked || false;
    payload.paymentDebit = $('#settingPaymentDebit')?.checked || false;
    payload.paymentAccount = $('#settingPaymentAccount')?.checked || false;
    payload.paymentMixed = $('#settingPaymentMixed')?.checked || false;
  }

  if (section === 'cash') {
    payload.allowDiscount = $('#settingAllowDiscount')?.checked || false;
    payload.discountLimitPercent = Number($('#settingDiscountLimitPercent')?.value || 0);
    payload.requireCustomerForAccount = $('#settingRequireCustomerForAccount')?.checked || false;
    payload.requireCancellationReason = $('#settingRequireCancellationReason')?.checked || false;
  }

  if (section === 'security') {
    payload.auditLogEnabled = $('#settingAuditLogEnabled')?.checked || false;
    payload.confirmDeletes = $('#settingConfirmDeletes')?.checked || false;
    payload.operatorPinEnabled = $('#settingOperatorPinEnabled')?.checked || false;
    payload.notificationsEnabled = $('#settingNotificationsEnabled')?.checked || false;
  }

  button.disabled = true;

  try {
    await postJson(`${prefix}api/configuracoes/salvar.php`, payload);
    await loadSettings();
    closeModal();
    showToast('Configuração salva');
  } catch (error) {
    showToast(error.message);
  } finally {
    button.disabled = false;
  }
}

function downloadCSV() {
  const sales = page === 'relatorios' ? data.report.sales : data.sales;
  const rows = [['Venda', 'Data', 'Hora', 'Cliente', 'Pagamento', 'Total'], ...sales.map(s => {
    const parts = dateTimeParts(s.criado_em);
    return [
      s.id,
      formatDate(s.date || parts.date),
      s.time || parts.time,
      s.customer || s.cliente || 'Venda balcão',
      paymentLabel(s.payment || s.metodo),
      Number(s.total || 0)
    ];
  })];
  const csv = rows.map(row => row.join(';')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8' });
  const a = document.createElement('a');
  a.href = URL.createObjectURL(blob);
  a.download = 'relatorio-vendas.csv';
  a.click();
  URL.revokeObjectURL(a.href);
}

function downloadPdf(title = 'Relatório Comercial') {
  if (!window.jspdf?.jsPDF) {
    window.print();
    return;
  }
  const { jsPDF } = window.jspdf;
  const doc = new jsPDF();
  doc.setFontSize(16);
  doc.text(data.settings.companyName, 14, 18);
  doc.setFontSize(11);
  doc.text(title, 14, 28);
  doc.text(`Gerado em ${new Date().toLocaleString('pt-BR')}`, 14, 36);
  let y = 50;
  const sales = title === 'Venda' && data.currentSale
    ? [data.currentSale]
    : (page === 'relatorios' ? data.report.sales : data.sales);

  sales.forEach(s => {
    doc.text(`Venda ${String(s.id).padStart(6, '0')} - ${s.customer || s.cliente || 'Venda balcão'} - ${brl.format(Number(s.total || 0))}`, 14, y);
    y += 8;
  });
  doc.save(`${title.toLowerCase().replace(/\s+/g, '-')}.pdf`);
}

function shareReceipt() {
  const id = Number(qs('id') || 0);
  const s = data.currentSale || data.sales.find(x => x.id === id);
  if (!s) {
    showToast('Venda não encontrada');
    return;
  }
  const message = `${data.settings.companyName}\nVenda nº ${String(s.id).padStart(6, '0')}\nTotal: ${brl.format(s.total)}\nPagamento: ${s.payment}\nObrigado pela preferência!`;
  if (navigator.share) navigator.share({ title: 'Comprovante', text: message }).catch(() => {});
  else {
    navigator.clipboard?.writeText(message);
    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
  }
}

function shareReport() {
  const summary = data.report.summary || {};
  const message = [
    data.settings.companyName || 'Relatório',
    `Vendas: ${Number(summary.sales_count || 0)}`,
    `Faturamento: ${brl.format(Number(summary.total_sales || 0))}`,
    `Ticket médio: ${brl.format(Number(summary.average_ticket || 0))}`
  ].join('\n');

  if (navigator.share) {
    navigator.share({ title: 'Relatório comercial', text: message }).catch(() => {});
    return;
  }

  navigator.clipboard?.writeText(message);
  showToast('Relatório copiado');
}

async function submitCancelSale(id) {
  const reason = $('#cancelReason')?.value.trim() || (data.settings.requireCancellationReason ? '' : 'Cancelamento realizado pelo operador.');

  if (!reason) {
    showToast('Informe o motivo do cancelamento');
    return;
  }

  try {
    await postJson(`${prefix}api/vendas/cancelar.php`, { id, reason });
    closeModal();
    await Promise.all([loadSales(), loadProducts()]);

    if (page === 'venda-detalhes') {
      const sale = await loadSaleDetails(id);
      data.currentSale = sale;
      renderSaleDetail(sale);
    }

    showToast('Venda cancelada');
  } catch (error) {
    showToast(error.message);
  }
}

function bindEvents() {
  document.body.addEventListener('click', e => {
    const close = e.target.closest('[data-close-modal]');
    if (close) closeModal();

    const toast = e.target.closest('[data-toast]');
    if (toast) showToast(toast.dataset.toast);

    const step = e.target.closest('[data-sale-step]');
    if (step) {
      saleStep = Number(step.dataset.saleStep);
      saveCart();
      renderSale();
    }

    if (e.target.closest('[data-next-sale-step]')) {
      saleStep = Math.min(saleStep + 1, 4);
      saveCart();
      renderSale();
    }

    const plus = e.target.closest('[data-cart-plus]');
    if (plus) {
      addToCart(Number(plus.dataset.cartPlus));
      renderSale();
    }

    const minus = e.target.closest('[data-cart-minus]');
    if (minus) {
      removeFromCart(Number(minus.dataset.cartMinus));
      renderSale();
    }

    const pay = e.target.closest('[data-payment]');
    if (pay) {
      currentPayment = pay.dataset.payment;
      saveCart();
      renderSale();
    }

    if (e.target.closest('[data-finish-sale]')) finishSale();

    if (e.target.closest('[data-reset-sale]')) {
      cart = [];
      saleStep = 1;
      saleClientId = 0;
      saleDueDate = '';
      saveCart();
      if (page === 'nova-venda') renderSale();
      showToast('Nova venda iniciada');
    }

    if (e.target.closest('[data-open-scanner]')) openScanner();
    if (e.target.closest('[data-use-barcode]')) useBarcode($('#manualBarcode').value.trim());
    if (e.target.closest('[data-select-product-image]')) $('#productImageInput')?.click();

    const productFilter = e.target.closest('[data-filter]');
    if (productFilter) {
      $all('#productFilters button').forEach(b => b.classList.remove('active'));
      productFilter.classList.add('active');
      renderProducts();
    }

    const clientFilter = e.target.closest('[data-client-filter]');
    if (clientFilter) {
      $all('#clientFilters button').forEach(b => b.classList.remove('active'));
      clientFilter.classList.add('active');
      renderClients();
    }

    const salesFilter = e.target.closest('[data-sales-filter]');
    if (salesFilter) {
      $all('#salesFilters button').forEach(b => b.classList.remove('active'));
      salesFilter.classList.add('active');
      salesPeriodFilter = salesFilter.dataset.salesFilter;
      renderSalesHistory();
    }

    const salesPaymentFilterBtn = e.target.closest('[data-sales-payment-filter]');
    if (salesPaymentFilterBtn) {
      $all('#salesPaymentFilters button').forEach(b => b.classList.remove('active'));
      salesPaymentFilterBtn.classList.add('active');
      salesPaymentFilter = salesPaymentFilterBtn.dataset.salesPaymentFilter;
      renderSalesHistory();
    }

    const clientActions = e.target.closest('[data-client-actions]');
    if (clientActions) openClientActions(Number(clientActions.dataset.clientActions));

    const openPayment = e.target.closest('[data-open-payment]');
    if (openPayment) openPaymentModal(Number(openPayment.dataset.openPayment));

    if (e.target.closest('[data-new-client]')) openClientForm();

    const editClient = e.target.closest('[data-edit-client]');
    if (editClient) {
      const client = data.clients.find(c => c.id === Number(editClient.dataset.editClient));
      if (client) openClientForm(client);
    }

    const saveClientBtn = e.target.closest('[data-save-client]');
    if (saveClientBtn) saveClient(Number(saveClientBtn.dataset.saveClient));

    const savePaymentBtn = e.target.closest('[data-save-client-payment]');
    if (savePaymentBtn) saveClientPayment(Number(savePaymentBtn.dataset.saveClientPayment));

    const sendWarningBtn = e.target.closest('[data-send-warning]');
    if (sendWarningBtn) sendWarning(Number(sendWarningBtn.dataset.sendWarning));

    const del = e.target.closest('[data-delete-product]');
    if (del) {
      const p = data.products.find(x => x.id === Number(del.dataset.deleteProduct));
      if (data.settings.confirmDeletes) {
        openModal(`<h2>Excluir produto?</h2><p>${escapeHtml(p.name)}<br>O produto será inativado para preservar relatórios antigos.</p><div class="button-row"><button class="ghost-btn" data-close-modal>Cancelar</button><button class="danger-btn" data-confirm-delete-product="${p.id}">Confirmar</button></div>`);
      } else {
        deleteProduct(Number(del.dataset.deleteProduct));
      }
    }

    const confirmDelete = e.target.closest('[data-confirm-delete-product]');
    if (confirmDelete) deleteProduct(Number(confirmDelete.dataset.confirmDeleteProduct));

    const cancelSaleBtn = e.target.closest('[data-cancel-sale]');
    if (cancelSaleBtn) {
      const reasonField = data.settings.requireCancellationReason
        ? '<div class="field"><label>Motivo</label><textarea id="cancelReason" placeholder="Descreva o motivo do cancelamento"></textarea></div>'
        : '';
      openModal(`<h2>Cancelar venda?</h2><p>${data.settings.auditLogEnabled ? 'A ação ficará registrada na auditoria.' : 'Auditoria desativada nas configurações.'}</p>${reasonField}<div class="button-row section-gap-small"><button class="ghost-btn" data-close-modal>Voltar</button><button class="danger-btn" data-confirm-cancel-sale="${cancelSaleBtn.dataset.cancelSale}">Confirmar</button></div>`);
    }

    const confirmCancelSale = e.target.closest('[data-confirm-cancel-sale]');
    if (confirmCancelSale) submitCancelSale(Number(confirmCancelSale.dataset.confirmCancelSale));

    const setting = e.target.closest('[data-setting]');
    if (setting) openSetting(setting.dataset.setting);

    const saveSettingBtn = e.target.closest('[data-save-setting]');
    if (saveSettingBtn) saveSetting(saveSettingBtn.dataset.saveSetting, saveSettingBtn);

    if (e.target.closest('[data-download-report-pdf]')) downloadPdf('Relatório Comercial');
    if (e.target.closest('[data-export-csv]')) downloadCSV();
    if (e.target.closest('[data-share-report]')) shareReport();
    if (e.target.closest('[data-download-sale-pdf]')) downloadPdf('Venda');
    if (e.target.closest('[data-print-receipt]')) window.print();
    if (e.target.closest('[data-share-receipt]')) shareReceipt();

    const reportFilter = e.target.closest('[data-report-filter]');
    if (reportFilter) {
      $all('#reportFilters button').forEach(b => b.classList.remove('active'));
      reportFilter.classList.add('active');
      reportPeriod = {
        Hoje: 'dia',
        Semana: 'semana',
        Mês: 'mes',
        Personalizado: 'periodo'
      }[reportFilter.dataset.reportFilter] || 'dia';
      const custom = $('#customReportFilter');
      if (custom) custom.hidden = reportPeriod !== 'periodo';
      if (reportPeriod !== 'periodo') initReports();
    }

    if (e.target.closest('[data-apply-report-filter]')) {
      reportStartDate = $('#reportStartDate')?.value || '';
      reportEndDate = $('#reportEndDate')?.value || '';
      initReports();
    }
  });

  document.body.addEventListener('input', e => {
    if (e.target.id === 'saleProductSearch') renderSaleProducts(e.target.value);
    if (e.target.id === 'productSearch') renderProducts();
    if (e.target.id === 'clientSearch') renderClients();
    if (e.target.id === 'salesSearch') renderSalesHistory();
    if (e.target.id === 'receivedAmount') {
      receivedAmount = Number(e.target.value || 0);
      saveCart();
      renderSale();
    }
    if (e.target.id === 'saleDueDate') {
      saleDueDate = e.target.value;
      saveCart();
    }
  });

  document.body.addEventListener('change', e => {
    if (e.target.id === 'saleClientId') {
      saleClientId = Number(e.target.value || 0);
      saveCart();
    }

    if (e.target.id === 'productImageInput') {
      const file = e.target.files?.[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = () => $('#productPreview').src = reader.result;
      reader.readAsDataURL(file);
    }
  });

  document.body.addEventListener('submit', e => {
    if (e.target.id === 'productForm') {
      e.preventDefault();
      e.target.querySelector('button[type="submit"]')?.setAttribute('disabled', 'disabled');
      saveProductForm(e.target);
    }
  });

  $('#modalBackdrop')?.addEventListener('click', e => {
    if (e.target.id === 'modalBackdrop') closeModal();
  });
}

function initPage() {
  initIcons();

  const handlers = {
    dashboard: initDashboard,
    'nova-venda': initSale,
    produtos: initProducts,
    'produto-form': initProductForm,
    relatorios: initReports,
    clientes: initClients,
    'cliente-detalhes': initClientDetail,
    'historico-vendas': initSalesHistory,
    'venda-detalhes': initSaleDetail,
    comprovante: initReceipt,
    configuracoes: initSettings
  };

  handlers[page]?.();

  if (page === 'nova-venda' && saleStep === 1) renderSaleProducts('');

  updateTime();
  setInterval(updateTime, 30000);
}

function registerPWA() {
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register(`${prefix}service-worker.js`).catch(() => {});
  }
}

async function loadSettings() {
  const response = await fetchJson(`${prefix}api/configuracoes/listar.php`);
  data.settings = { ...data.settings, ...response.settings };
  data.users = Array.isArray(response.users) ? response.users : data.users;
}

async function loadClients() {
  const response = await fetchJson(`${prefix}api/clientes/listar.php`);
  data.clients = Array.isArray(response) ? response : [];
}

async function loadClientDetails(id) {
  return fetchJson(`${prefix}api/clientes/detalhes.php?id=${encodeURIComponent(id)}`);
}

async function loadSales() {
  const response = await fetchJson(`${prefix}api/vendas/listar.php`);
  data.sales = Array.isArray(response) ? response : [];
}

async function loadProducts() {
  const response = await fetchJson(`${prefix}api/produtos/listar.php`);
  data.products = Array.isArray(response) ? response : [];
  syncCartWithProducts();
}

async function loadDashboard() {
  const response = await fetchJson(`${prefix}api/dashboard/resumo.php`);
  data.dashboard = {
    ...data.dashboard,
    ...response,
    summary: response.summary || data.dashboard.summary
  };
  data.dashboardInfo = data.dashboard.summary;
}

async function loadSaleDetails(id) {
  return fetchJson(`${prefix}api/vendas/detalhes.php?id=${encodeURIComponent(id)}`);
}

async function loadReceipt(id) {
  return fetchJson(`${prefix}api/vendas/comprovante.php?id=${encodeURIComponent(id)}`);
}

function syncCartWithProducts() {
  const validProductIds = new Set(data.products.map(product => product.id));
  cart = cart.filter(item => validProductIds.has(item.productId));
  saveCart();
}

document.addEventListener('DOMContentLoaded', async () => {
  try {
    await loadSettings();
  } catch (e) {
    console.error('Falha ao carregar configurações:', e);
  }

  try {
    await loadProducts();
  } catch (e) {
    console.error('Falha ao carregar produtos:', e);
  }

  try {
    await Promise.all([loadClients(), loadSales()]);
  } catch (e) {
    console.error('Falha ao carregar dados operacionais:', e);
  }

  if (page === 'dashboard') {
    try {
      await loadDashboard();
    } catch (e) {
      console.error('Falha ao carregar dashboard:', e);
    }
  }

  bindEvents();
  initPage();
  registerPWA();
});
