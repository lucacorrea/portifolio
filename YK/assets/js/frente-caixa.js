(function () {
  'use strict';

  var form = document.getElementById('pdv-form');
  if (!form) return;

  var catalog = JSON.parse(document.getElementById('pdv-products-data').textContent || '[]');
  var cart = new Map();
  var selected = null;
  var productSearch = document.getElementById('pdv-product-search');
  var productResults = document.getElementById('pdv-product-results');
  var quantityInput = document.getElementById('pdv-quantity');
  var discountInput = document.getElementById('pdv-discount');
  var receivedInput = document.getElementById('pdv-received');
  var paymentInput = document.getElementById('pdv-payment-form');
  var otherPayment = document.getElementById('pdv-other-payment');
  var cartBody = document.getElementById('pdv-cart-body');
  var hiddenItems = document.getElementById('pdv-hidden-items');
  var finalizeButton = document.getElementById('pdv-finalize');
  var visibleResults = [];

  function number(value) {
    var text = String(value || '').trim().replace(/\s/g, '');
    if (text.indexOf(',') >= 0) text = text.replace(/\./g, '').replace(',', '.');
    var parsed = Number(text);
    return Number.isFinite(parsed) ? parsed : 0;
  }

  function normalize(value) {
    return String(value || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('pt-BR').trim();
  }

  function money(value) {
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
  }

  function quantity(value) {
    return new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 3, maximumFractionDigits: 3 }).format(value);
  }

  function totalValues() {
    var subtotal = Array.from(cart.values()).reduce(function (sum, item) { return sum + item.price * item.quantity; }, 0);
    var discount = Math.max(0, number(discountInput.value));
    return { subtotal: subtotal, total: Math.max(0, subtotal - discount) };
  }

  function element(tag, text, className) {
    var node = document.createElement(tag);
    if (text !== undefined) node.textContent = text;
    if (className) node.className = className;
    return node;
  }

  function selectProduct(product) {
    selected = product;
    productSearch.value = product.name;
    productResults.classList.add('d-none');
    document.getElementById('pdv-current-code').textContent = product.code || product.barcode || 'Sem código';
    document.getElementById('pdv-unit-price').textContent = money(number(product.price)).replace('R$', '').trim();
    document.getElementById('pdv-current-product').textContent = product.name;
    document.getElementById('pdv-current-value').textContent = money(number(product.price));
    updateCurrentItem();
  }

  function clearSelection() {
    selected = null;
    productSearch.value = '';
    productResults.replaceChildren();
    productResults.classList.add('d-none');
    document.getElementById('pdv-current-code').textContent = '—';
    document.getElementById('pdv-unit-price').textContent = '0,00';
    document.getElementById('pdv-current-product').textContent = 'Nenhum produto selecionado';
    document.getElementById('pdv-current-value').textContent = 'R$ 0,00';
    document.getElementById('pdv-item-total').textContent = 'R$ 0,00';
    quantityInput.value = '1,000';
    productSearch.focus();
  }

  function updateCurrentItem() {
    var value = selected ? number(selected.price) * Math.max(0, number(quantityInput.value)) : 0;
    document.getElementById('pdv-item-total').textContent = money(value);
  }

  function renderSearch() {
    var search = normalize(productSearch.value);
    selected = null;
    visibleResults = search === '' ? [] : catalog.filter(function (product) {
      return normalize([product.code, product.barcode, product.name].join(' ')).indexOf(search) >= 0;
    }).sort(function (a, b) {
      var exactA = normalize(a.code) === search || normalize(a.barcode) === search ? 0 : 1;
      var exactB = normalize(b.code) === search || normalize(b.barcode) === search ? 0 : 1;
      return exactA - exactB || a.name.localeCompare(b.name, 'pt-BR');
    }).slice(0, 8);
    productResults.replaceChildren();
    visibleResults.forEach(function (product) {
      var button = element('button', undefined, 'pdv-product-option');
      button.type = 'button';
      button.setAttribute('role', 'option');
      var description = element('span');
      description.append(element('strong', product.name), element('small', (product.code || product.barcode || 'Sem código') + ' · estoque ' + quantity(number(product.stock))));
      button.append(description, element('b', money(number(product.price))));
      button.addEventListener('click', function () { selectProduct(product); });
      productResults.appendChild(button);
    });
    if (search !== '' && visibleResults.length === 0) productResults.appendChild(element('span', 'Nenhuma peça encontrada.', 'pdv-result-empty'));
    productResults.classList.toggle('d-none', search === '');
  }

  function addSelected() {
    if (!selected && visibleResults.length > 0) selectProduct(visibleResults[0]);
    if (!selected) { productSearch.focus(); return; }
    var amount = number(quantityInput.value);
    if (amount <= 0) { quantityInput.focus(); return; }
    var current = cart.get(String(selected.id));
    var next = amount + (current ? current.quantity : 0);
    if (next > number(selected.stock)) {
      window.alert('Quantidade maior que o estoque disponível (' + quantity(number(selected.stock)) + ').');
      return;
    }
    cart.set(String(selected.id), { id: selected.id, code: selected.code, name: selected.name, price: number(selected.price), stock: number(selected.stock), quantity: next });
    renderCart();
    clearSelection();
  }

  function renderCart() {
    cartBody.replaceChildren();
    hiddenItems.replaceChildren();
    var itemNumber = 0;
    Array.from(cart.values()).forEach(function (item, index) {
      itemNumber += 1;
      var row = document.createElement('tr');
      row.append(element('td', String(itemNumber)), element('td', item.name), element('td', quantity(item.quantity)), element('td', money(item.price)), element('td', money(item.price * item.quantity)));
      var action = document.createElement('td');
      var remove = element('button', undefined, 'pdv-remove-item');
      remove.type = 'button';
      remove.setAttribute('aria-label', 'Remover ' + item.name);
      remove.appendChild(element('i'));
      remove.firstChild.className = 'bi bi-trash';
      remove.addEventListener('click', function () { cart.delete(String(item.id)); renderCart(); });
      action.appendChild(remove);
      row.appendChild(action);
      cartBody.appendChild(row);
      [['produto_id', item.id], ['quantidade', item.quantity]].forEach(function (pair) {
        var input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'itens[' + index + '][' + pair[0] + ']';
        input.value = String(pair[1]);
        hiddenItems.appendChild(input);
      });
    });
    if (cart.size === 0) {
      var empty = document.createElement('tr');
      empty.className = 'pdv-empty-row';
      var cell = element('td', 'Nenhuma peça adicionada à venda.');
      cell.colSpan = 6;
      empty.appendChild(cell);
      cartBody.appendChild(empty);
    }
    var totals = totalValues();
    document.getElementById('pdv-items-count').textContent = cart.size + (cart.size === 1 ? ' item' : ' itens');
    document.getElementById('pdv-subtotal').textContent = money(totals.subtotal);
    document.getElementById('pdv-total').textContent = money(totals.total);
    finalizeButton.disabled = cart.size === 0 || totals.total <= 0;
    updateReceived();
  }

  function choosePayment(value) {
    paymentInput.value = value;
    document.querySelectorAll('[data-payment]').forEach(function (button) { button.classList.toggle('active', button.dataset.payment === value); });
    if (['dinheiro', 'pix', 'cartao_debito', 'cartao_credito'].indexOf(value) >= 0) otherPayment.value = '';
    receivedInput.disabled = value !== 'dinheiro';
    if (value !== 'dinheiro') receivedInput.value = totalValues().total.toFixed(2).replace('.', ',');
    updateReceived();
  }

  function updateReceived() {
    var totals = totalValues();
    var change = paymentInput.value === 'dinheiro' ? Math.max(0, number(receivedInput.value) - totals.total) : 0;
    document.getElementById('pdv-change').textContent = money(change);
  }

  productSearch.addEventListener('input', renderSearch);
  productSearch.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); addSelected(); } });
  quantityInput.addEventListener('input', updateCurrentItem);
  quantityInput.addEventListener('keydown', function (event) { if (event.key === 'Enter') { event.preventDefault(); addSelected(); } });
  discountInput.addEventListener('input', renderCart);
  receivedInput.addEventListener('input', updateReceived);
  document.getElementById('pdv-add-product').addEventListener('click', addSelected);
  document.getElementById('pdv-clear-selection').addEventListener('click', clearSelection);
  document.querySelectorAll('[data-payment]').forEach(function (button) { button.addEventListener('click', function () { choosePayment(button.dataset.payment); }); });
  otherPayment.addEventListener('change', function () { if (otherPayment.value) choosePayment(otherPayment.value); });

  var clientSearch = document.getElementById('pdv-client-search');
  var clientId = document.getElementById('pdv-client-id');
  var clientResults = document.getElementById('pdv-client-results');
  var clientTimer = 0;
  var clientController = null;
  if (clientSearch && !clientSearch.disabled) {
    clientSearch.addEventListener('input', function () {
      clientId.value = '';
      window.clearTimeout(clientTimer);
      var search = clientSearch.value.trim();
      if (search.length < 2) { clientResults.classList.add('d-none'); return; }
      clientTimer = window.setTimeout(async function () {
        try {
          if (clientController) clientController.abort();
          clientController = new AbortController();
          var response = await fetch('actions/clientes-buscar.php?' + new URLSearchParams({ search: search, status: 'ativo' }).toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: clientController.signal });
          var payload = await response.json();
          if (!response.ok || !payload.ok) throw new Error(payload.error || 'Falha na pesquisa.');
          clientResults.replaceChildren();
          payload.clients.slice(0, 8).forEach(function (client) {
            var button = element('button', client.name + ' · ' + client.code + ' · ' + client.document_label, 'pdv-client-option');
            button.type = 'button';
            button.addEventListener('click', function () { clientId.value = String(client.id); clientSearch.value = client.name + ' · ' + client.code; clientResults.classList.add('d-none'); });
            clientResults.appendChild(button);
          });
          if (clientResults.childElementCount === 0) clientResults.appendChild(element('span', 'Nenhum cliente ativo encontrado.', 'pdv-result-empty'));
          clientResults.classList.remove('d-none');
        } catch (error) {
          if (error && error.name === 'AbortError') return;
          clientResults.replaceChildren(element('span', 'Não foi possível buscar clientes.', 'pdv-result-empty'));
          clientResults.classList.remove('d-none');
        }
      }, 300);
    });
  }

  document.addEventListener('click', function (event) {
    if (!event.target.closest('.pdv-search-card')) productResults.classList.add('d-none');
    if (!event.target.closest('.pdv-client-picker')) clientResults.classList.add('d-none');
  });
  document.addEventListener('keydown', function (event) {
    if (event.key === 'F2') { event.preventDefault(); quantityInput.focus(); quantityInput.select(); }
    if (event.key === 'F3') { event.preventDefault(); discountInput.focus(); discountInput.select(); }
    if (event.key === 'F4') { event.preventDefault(); if (!finalizeButton.disabled) form.requestSubmit(); }
    if (event.key === 'F6') { event.preventDefault(); receivedInput.focus(); receivedInput.select(); }
  });
  form.addEventListener('submit', function (event) {
    var totals = totalValues();
    if (cart.size === 0 || totals.total <= 0) { event.preventDefault(); window.alert('Adicione ao menos uma peça à venda.'); return; }
    if (paymentInput.value === 'dinheiro' && number(receivedInput.value) < totals.total) { event.preventDefault(); window.alert('O valor recebido é menor que o total da venda.'); receivedInput.focus(); }
  });

  choosePayment('dinheiro');
  renderCart();
}());
