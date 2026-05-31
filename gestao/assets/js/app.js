const prefix = document.body.dataset.prefix || '';
const page = document.body.dataset.page || 'dashboard';
const data = window.AppData;
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
let cart = JSON.parse(localStorage.getItem('cart') || '[{"productId":1,"qty":1},{"productId":2,"qty":1},{"productId":4,"qty":1}]');
let currentPayment = localStorage.getItem('payment') || 'PIX';
let receivedAmount = Number(localStorage.getItem('receivedAmount') || 0);
let scannerStream = null;

function $(sel, parent = document) { return parent.querySelector(sel); }
function $all(sel, parent = document) { return [...parent.querySelectorAll(sel)]; }
function img(name) { return `${prefix}assets/img/${name}`; }
function qs(name) { return new URLSearchParams(location.search).get(name); }

function updateTime() {
  const time = new Date().toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
  $all('[data-time]').forEach(el => el.textContent = time);
}

function formatDate(date) {
  if (!date) return '-';
  const [y, m, d] = date.split('-');
  return `${d}/${m}/${y}`;
}

function daysTo(date) {
  const today = new Date('2026-05-28T00:00:00');
  const target = new Date(`${date}T00:00:00`);
  return Math.ceil((target - today) / 86400000);
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
    ? `<img class="row-thumb" src="${image}" alt="">`
    : `<span class="row-icon">${icons[icon] || icons.receipt}</span>`;
  const amountHtml = amount !== undefined ? `<strong class="${type}">${amount < 0 ? '- ' : ''}${brl.format(Math.abs(amount))}</strong>` : '';

  return `
    <${tag} class="row-item" ${link} ${attr}>
      <div class="row-left">
        ${visual}
        <span class="row-text">
          <strong>${title}</strong>
          <span>${subtitle}</span>
        </span>
      </div>
      <span class="row-right">
        ${amountHtml}
        <span>${status || ''}</span>
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
}

function addToCart(id) {
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
  const info = data.dashboardInfo || { sales_count: 0, total_sales: 0 };
  const total = Number(info.total_sales);
  const count = Number(info.sales_count);
  
  $('#todayTotal').textContent = brl.format(total);
  $('#todaySalesCount').textContent = count;

  $('#dashboardFinance').innerHTML = [
    financeCard('Total vendido', total, `${count} vendas`),
    financeCard('Lucro estimado', total * 0.32, 'Margem 32%')
  ].join('');

  $('#dailyReport').innerHTML = [
    summaryLine('Vendas realizadas', String(count)),
    summaryLine('Ticket médio', count > 0 ? brl.format(total / count) : 'R$ 0,00'),
  ].join('');

  $('#expiringProducts').innerHTML = data.products
    .filter(p => daysTo(p.expiry) <= data.settings.expirationAlertDays)
    .slice(0, 3)
    .map(p => rowItem({
      title: p.name,
      subtitle: `Lote ${p.lot} • Validade ${formatDate(p.expiry)}`,
      status: `${Math.max(daysTo(p.expiry), 0)} dias`,
      image: img(p.image),
      type: daysTo(p.expiry) <= 3 ? 'negative' : 'warning',
      href: 'pages/produtos.php'
    })).join('');

  $('#latestSales').innerHTML = sales.map(s => rowItem({
    title: `Venda nº ${String(s.id).padStart(6, '0')}`,
    subtitle: `${s.payment} • ${s.time} • ${s.seller}`,
    amount: s.total,
    status: s.status,
    icon: 'receipt',
    href: `pages/venda-detalhes.php?id=${s.id}`
  })).join('');

  $('#featuredProducts').innerHTML = data.products.slice(0, 3).map((p, index) => rowItem({
    title: p.name,
    subtitle: `${index + 8} un. vendidas • ${p.category}`,
    amount: p.price * (index + 8),
    status: `Top ${index + 1}`,
    image: img(p.image),
    href: 'pages/produtos.php'
  })).join('');
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
    .map(productCardForSale).join('');
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
  const change = Math.max(receivedAmount - total, 0);

  return `
    <div class="sheet-title"><div><h2>Forma de pagamento</h2><p>Escolha como o cliente vai pagar</p></div></div>
    <article class="checkout-summary">
      <p>Total da venda</p>
      <h2>${brl.format(total)}</h2>
      <span>${cartItems().length} itens adicionados</span>
    </article>
    <div class="payment-methods section-gap-small">
      ${['PIX', 'Crédito', 'Débito', 'Dinheiro', 'Conta do cliente', 'Misto'].map(m => `<button class="${currentPayment === m ? 'active' : ''}" data-payment="${m}">${m}</button>`).join('')}
    </div>

    ${currentPayment === 'Dinheiro' ? `
      <div class="form-card section-gap-small">
        <div class="field"><label>Valor recebido</label><input id="receivedAmount" type="number" min="0" step="0.01" value="${receivedAmount || ''}" placeholder="Ex.: 50,00"></div>
        <div class="summary-line"><span>Troco</span><strong>${brl.format(change)}</strong></div>
      </div>
    ` : ''}

    ${currentPayment === 'Conta do cliente' ? `
      <div class="form-card section-gap-small">
        <div class="field"><label>Cliente</label><select>${data.clients.map(c => `<option>${c.name}</option>`).join('')}</select></div>
        <div class="field"><label>Data de vencimento</label><input type="date" value="2026-06-10"></div>
      </div>
    ` : ''}

    <div class="button-row section-gap-small">
      <button class="ghost-btn" data-sale-step="2">Voltar</button>
      <button class="primary-btn" data-finish-sale>Finalizar venda</button>
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
      <a class="primary-btn" href="comprovante.php?id=128">Ver comprovante</a>
    </div>
    <button class="secondary-btn section-gap-small" data-reset-sale>Nova venda</button>
  `;
}

function cartFooter() {
  return `
    <aside class="cart-sticky">
      <div class="cart-sticky-row">
        <span>${cartItems().length} itens no carrinho</span>
        <strong>${brl.format(cartTotal())}</strong>
      </div>
      <button class="primary-btn" data-next-sale-step>Continuar</button>
    </aside>
  `;
}

function finishSale() {
  openModal(`
    <h2>Deseja gerar comprovante?</h2>
    <p>A venda foi finalizada. Você pode gerar comprovante agora ou apenas concluir a venda.</p>
    <div class="button-row">
      <button class="ghost-btn" data-close-modal data-sale-step="4">Não</button>
      <a class="primary-btn" href="comprovante.php?id=128">Sim, gerar</a>
    </div>
  `);
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
  }
}

function initReports() {
  const total = data.sales.reduce((sum, s) => sum + s.total, 0);
  $('#reportFinance').innerHTML = [
    financeCard('Faturamento', total, 'Período'),
    financeCard('Lucro', total * 0.32, 'Estimado'),
    financeCard('Ticket médio', total / 91, 'Média'),
    financeCard('Total de vendas', '871', 'Movimentos')
  ].join('');

  $('#weeklyBars').innerHTML = [['Seg',54],['Ter',67],['Qua',48],['Qui',76],['Sex',92],['Sáb',70],['Dom',42]]
    .map(([day, h]) => `<span class="bar" style="height:${h}%" data-day="${day}"></span>`).join('');

  $('#reportTables').innerHTML = [
    reportTable('Produtos mais vendidos', ['Produto', 'Qtde', 'Receita'], [
      ['Mouse Sem Fio Pro', '28', brl.format(2237.20)],
      ['Cabo USB-C 2m', '41', brl.format(697)],
      ['Teclado Mecânico RGB', '12', brl.format(1798.80)]
    ]),
    reportTable('Produtos próximos da validade', ['Produto', 'Lote', 'Validade'], data.products.filter(p => daysTo(p.expiry) <= data.settings.expirationAlertDays).map(p => [p.name, p.lot, formatDate(p.expiry)])),
    reportTable('Estoque baixo', ['Produto', 'Atual', 'Mínimo'], data.products.filter(p => p.stock <= p.minStock).map(p => [p.name, p.stock, p.minStock]))
  ].join('');
}

function reportTable(title, headers, rows) {
  return `
    <div class="sheet-title section-gap"><div><h2>${title}</h2><p>Dados exportáveis</p></div></div>
    <div class="table-card">
      <table class="mobile-table">
        <thead><tr>${headers.map(h => `<th>${h}</th>`).join('')}</tr></thead>
        <tbody>${rows.map(row => `<tr>${row.map(col => `<td>${col}</td>`).join('')}</tr>`).join('')}</tbody>
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

  $('#clientsList').innerHTML = clients.map(clientCard).join('');
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
        <button data-client-actions="${c.id}">Mais ações</button>
      </div>
    </article>
  `;
}

function initClientDetail() {
  const c = data.clients.find(x => x.id === Number(qs('id') || 1)) || data.clients[0];
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
      ${rowItem({ title: 'Compra nº 000129', subtitle: `Vencimento ${c.due ? formatDate(c.due) : '-'}`, amount: c.debt, status: c.status, icon: 'receipt', type: c.status === 'Atrasado' ? 'negative' : 'warning' })}
    </div>

    <div class="sheet-title section-gap"><div><h2>Histórico financeiro</h2><p>Auditoria da conta</p></div></div>
    <div class="list-card">
      ${c.history.map(item => rowItem({ title: item.split('—')[1] || item, subtitle: item.split('—')[0] || '', icon: 'receipt' })).join('')}
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
    return text.includes(query);
  }).map(saleCard).join('');
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
  const s = data.sales.find(x => x.id === Number(qs('id') || 128)) || data.sales[0];
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
  const s = data.sales.find(x => x.id === Number(qs('id') || 128)) || data.sales[0];
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
      <div class="field"><label>Código manual</label><input id="manualBarcode" value="7891000000011"></div>
      <div class="button-row">
        <button class="ghost-btn" data-close-modal>Fechar</button>
        <button class="primary-btn" data-use-barcode>Buscar</button>
      </div>
      <button class="secondary-btn" data-simulate-scan>Simular leitura</button>
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
      <button data-toast="Vencimento renegociado"><span>Renegociar vencimento</span><strong>›</strong></button>
      <a href="cliente-detalhes.php?id=${c.id}"><span>Ver histórico</span><strong>›</strong></a>
    </div>
    <button class="ghost-btn section-gap-small" data-close-modal>Fechar</button>
  `);
}

function openPaymentModal(id) {
  const c = data.clients.find(x => x.id === id);
  openModal(`
    <h2>Registrar pagamento</h2>
    <p>Saldo atual: <strong>${brl.format(c.debt)}</strong></p>
    <div class="form-grid">
      <div class="field"><label>Valor pago agora</label><input id="partialAmount" type="number" min="0" step="0.01" value="100"></div>
      <div class="field"><label>Novo vencimento do restante</label><input id="newDueDate" type="date" value="2026-06-10"></div>
      <div class="field"><label>Forma de pagamento</label><select><option>PIX</option><option>Dinheiro</option><option>Cartão</option></select></div>
      <div class="button-row">
        <button class="ghost-btn" data-close-modal>Cancelar</button>
        <button class="primary-btn" data-toast="Pagamento registrado">Confirmar</button>
      </div>
    </div>
  `);
}

function sendWarning(id) {
  const c = data.clients.find(x => x.id === id);
  const msg = `Olá, ${c.name}. Consta um saldo em aberto de ${brl.format(c.debt)} com vencimento em ${formatDate(c.due)}. Você pode pagar por PIX, dinheiro ou cartão.`;
  navigator.clipboard?.writeText(msg);
  window.open(`https://wa.me/55${c.phone.replace(/\D/g, '')}?text=${encodeURIComponent(msg)}`, '_blank');
}

function openSetting(key) {
  const html = {
    company: `<h2>Empresa</h2><div class="form-grid section-gap-small"><div class="field"><label>Nome da empresa</label><input value="${data.settings.companyName}"></div><div class="field"><label>Telefone</label><input value="${data.settings.companyPhone}"></div><div class="field"><label>Endereço</label><input value="${data.settings.companyAddress}"></div><button class="primary-btn" data-close-modal data-toast="Configuração salva">Salvar</button></div>`,
    users: `<h2>Usuários e permissões</h2><div class="list-card section-gap-small">${data.users.map(u => rowItem({ title: u.name, subtitle: u.role, status: u.status, icon: 'user' })).join('')}</div><button class="primary-btn section-gap-small" data-toast="Usuário adicionado">Adicionar usuário</button>`,
    receipt: `<h2>Comprovantes</h2><div class="form-grid section-gap-small"><div class="field"><label>Ao finalizar venda</label><select><option>Sempre perguntar</option><option>Sempre gerar</option><option>Nunca gerar</option></select></div><div class="field"><label>Modelo</label><select><option>Detalhado</option><option>Simples</option></select></div><button class="primary-btn" data-close-modal data-toast="Configuração salva">Salvar</button></div>`,
    due: `<h2>Regras de vencimento</h2><div class="form-grid section-gap-small"><div class="field"><label>Alerta de validade</label><input type="number" value="${data.settings.expirationAlertDays}"></div><div class="field"><label>Prazo padrão de dívida</label><input type="number" value="${data.settings.debtDueDays}"></div><button class="primary-btn" data-close-modal data-toast="Configuração salva">Salvar</button></div>`
  }[key] || `<h2>Configuração</h2><p>Opção preparada para produção.</p>`;

  openModal(`${html}<button class="ghost-btn section-gap-small" data-close-modal>Fechar</button>`);
}

function downloadCSV() {
  const rows = [['Venda', 'Data', 'Hora', 'Cliente', 'Pagamento', 'Total'], ...data.sales.map(s => [s.id, formatDate(s.date), s.time, s.customer, s.payment, s.total])];
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
  doc.text('Gerado em 28/05/2026', 14, 36);
  let y = 50;
  data.sales.forEach(s => {
    doc.text(`Venda ${String(s.id).padStart(6, '0')} - ${s.customer} - ${brl.format(s.total)}`, 14, y);
    y += 8;
  });
  doc.save(`${title.toLowerCase().replace(/\s+/g, '-')}.pdf`);
}

function shareReceipt() {
  const s = data.sales.find(x => x.id === Number(qs('id') || 128)) || data.sales[0];
  const message = `${data.settings.companyName}\nVenda nº ${String(s.id).padStart(6, '0')}\nTotal: ${brl.format(s.total)}\nPagamento: ${s.payment}\nObrigado pela preferência!`;
  if (navigator.share) navigator.share({ title: 'Comprovante', text: message }).catch(() => {});
  else {
    navigator.clipboard?.writeText(message);
    window.open(`https://wa.me/?text=${encodeURIComponent(message)}`, '_blank');
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
      saveCart();
      if (page === 'nova-venda') renderSale();
      showToast('Nova venda iniciada');
    }

    if (e.target.closest('[data-open-scanner]')) openScanner();
    if (e.target.closest('[data-use-barcode]')) useBarcode($('#manualBarcode').value.trim());
    if (e.target.closest('[data-simulate-scan]')) useBarcode('7891000000011');

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

    const clientActions = e.target.closest('[data-client-actions]');
    if (clientActions) openClientActions(Number(clientActions.dataset.clientActions));

    const openPayment = e.target.closest('[data-open-payment]');
    if (openPayment) openPaymentModal(Number(openPayment.dataset.openPayment));

    const sendWarningBtn = e.target.closest('[data-send-warning]');
    if (sendWarningBtn) sendWarning(Number(sendWarningBtn.dataset.sendWarning));

    const del = e.target.closest('[data-delete-product]');
    if (del) {
      const p = data.products.find(x => x.id === Number(del.dataset.deleteProduct));
      openModal(`<h2>Excluir produto?</h2><p>${p.name}<br>Na produção, o ideal é inativar para não quebrar relatórios antigos.</p><div class="button-row"><button class="ghost-btn" data-close-modal>Cancelar</button><button class="danger-btn" data-close-modal data-toast="Produto excluído">Excluir</button></div>`);
    }

    const cancelSale = e.target.closest('[data-cancel-sale]');
    if (cancelSale) {
      openModal(`<h2>Cancelar venda?</h2><p>Informe o motivo para auditoria.</p><div class="field"><label>Motivo</label><textarea placeholder="Ex.: Produto lançado errado"></textarea></div><div class="button-row section-gap-small"><button class="ghost-btn" data-close-modal>Voltar</button><button class="danger-btn" data-close-modal data-toast="Venda cancelada">Confirmar</button></div>`);
    }

    const setting = e.target.closest('[data-setting]');
    if (setting) openSetting(setting.dataset.setting);

    if (e.target.closest('[data-download-report-pdf]')) downloadPdf('Relatório Comercial');
    if (e.target.closest('[data-export-csv]')) downloadCSV();
    if (e.target.closest('[data-share-report]')) showToast('Compartilhamento preparado');
    if (e.target.closest('[data-download-sale-pdf]')) downloadPdf('Venda');
    if (e.target.closest('[data-print-receipt]')) window.print();
    if (e.target.closest('[data-share-receipt]')) shareReceipt();
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
  });

  document.body.addEventListener('change', e => {
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
      showToast('Produto salvo');
      setTimeout(() => location.href = 'produtos.php', 500);
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

document.addEventListener('DOMContentLoaded', async () => {
  try {
    const res = await fetch(`${prefix}api/produtos/listar.php`);
    const json = await res.json();
    if (json.success) {
      window.AppData.products = json.data;
    }
  } catch (e) {
    console.error('Falha ao carregar produtos:', e);
  }

  if (page === 'dashboard') {
    try {
      const res = await fetch(`${prefix}api/dashboard/resumo.php`);
      const json = await res.json();
      if (json.success) {
        window.AppData.dashboardInfo = json.data;
      }
    } catch (e) {
      console.error('Falha ao carregar dashboard:', e);
    }
  }

  bindEvents();
  initPage();
  registerPWA();
});
