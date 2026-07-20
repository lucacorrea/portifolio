(function () {
  'use strict';

  var form = document.getElementById('cash-pos-form');
  if (form) {
    var productSelect = document.getElementById('pos-product');
    var quantityInput = document.getElementById('pos-quantity');
    var searchInput = document.getElementById('pos-product-search');
    var cartBody = document.getElementById('pos-cart-body');
    var hiddenItems = document.getElementById('pos-hidden-items');
    var discountInput = document.getElementById('pos-discount');
    var increaseInput = document.getElementById('pos-increase');
    var cart = new Map();
    var clientSearch = document.getElementById('pos-client-search');
    var clientId = document.getElementById('pos-client-id');
    var clientResults = document.getElementById('pos-client-results');
    var clientTimer = 0;
    var clientController = null;

    function number(value) {
      var text = String(value || '').trim().replace(/\s/g, '');
      if (text.indexOf(',') >= 0) text = text.replace(/\./g, '').replace(',', '.');
      var parsed = Number(text);
      return Number.isFinite(parsed) ? parsed : 0;
    }

    function currency(value) {
      return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
    }

    function cell(text, className) {
      var td = document.createElement('td');
      td.textContent = text;
      if (className) td.className = className;
      return td;
    }

    function render() {
      cartBody.replaceChildren();
      hiddenItems.replaceChildren();
      var subtotal = 0;
      if (cart.size === 0) {
        var emptyRow = document.createElement('tr');
        emptyRow.className = 'pos-cart-empty';
        var emptyCell = cell('Nenhum produto adicionado.');
        emptyCell.colSpan = 5;
        emptyRow.appendChild(emptyCell);
        cartBody.appendChild(emptyRow);
      }
      Array.from(cart.values()).forEach(function (item, index) {
        subtotal += item.quantity * item.price;
        var row = document.createElement('tr');
        row.append(cell(item.name), cell(item.quantity.toLocaleString('pt-BR')), cell(currency(item.price)), cell(currency(item.quantity * item.price), 'fw-bold'));
        var action = document.createElement('td');
        var remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn-action text-danger';
        remove.setAttribute('aria-label', 'Remover ' + item.name);
        var icon = document.createElement('i');
        icon.className = 'bi bi-trash';
        remove.appendChild(icon);
        remove.addEventListener('click', function () { cart.delete(item.id); render(); });
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
      var total = Math.max(0, subtotal - number(discountInput.value) + number(increaseInput.value));
      document.getElementById('pos-subtotal').textContent = currency(subtotal);
      document.getElementById('pos-total').textContent = currency(total);
    }

    document.getElementById('pos-add-product').addEventListener('click', function () {
      var option = productSelect.options[productSelect.selectedIndex];
      var quantity = number(quantityInput.value);
      if (!option || !option.value || quantity <= 0) {
        productSelect.focus();
        return;
      }
      var stock = number(option.dataset.stock);
      var existing = cart.get(option.value);
      var nextQuantity = quantity + (existing ? existing.quantity : 0);
      if (nextQuantity > stock) {
        window.alert('Quantidade maior que o estoque disponível (' + stock.toLocaleString('pt-BR') + ').');
        return;
      }
      cart.set(option.value, { id: option.value, name: option.textContent.split(' · ')[0], quantity: nextQuantity, price: number(option.dataset.price), stock: stock });
      quantityInput.value = '1';
      render();
    });

    searchInput.addEventListener('input', function () {
      var search = searchInput.value.trim().toLocaleLowerCase('pt-BR');
      Array.from(productSelect.options).forEach(function (option, index) {
        if (index === 0) return;
        option.hidden = search !== '' && String(option.dataset.search || '').indexOf(search) === -1;
      });
      var match = Array.from(productSelect.options).find(function (option, index) { return index > 0 && !option.hidden; });
      if (match) productSelect.value = match.value;
    });
    [discountInput, increaseInput].forEach(function (input) { input.addEventListener('input', render); });
    form.addEventListener('submit', function (event) {
      if (cart.size === 0) {
        event.preventDefault();
        window.alert('Adicione ao menos um produto à venda.');
      }
    });

    if (clientSearch && !clientSearch.disabled) {
      clientSearch.addEventListener('input', function () {
        clientId.value = '';
        window.clearTimeout(clientTimer);
        var search = clientSearch.value.trim();
        if (search.length < 2) {
          clientResults.replaceChildren();
          clientResults.classList.add('d-none');
          return;
        }
        clientTimer = window.setTimeout(async function () {
          try {
            if (clientController) clientController.abort();
            clientController = new AbortController();
            var params = new URLSearchParams({ search: search, status: 'ativo' });
            var response = await fetch('actions/clientes-buscar.php?' + params.toString(), { headers: { Accept: 'application/json' }, credentials: 'same-origin', cache: 'no-store', signal: clientController.signal });
            var payload = await response.json();
            if (!response.ok || !payload.ok) throw new Error(payload.error || 'Falha na pesquisa.');
            clientResults.replaceChildren();
            payload.clients.slice(0, 8).forEach(function (client) {
              var button = document.createElement('button');
              button.type = 'button';
              button.className = 'pos-client-option';
              button.setAttribute('role', 'option');
              button.textContent = client.name + ' · ' + client.code + ' · ' + client.document_label;
              button.addEventListener('click', function () {
                clientId.value = String(client.id);
                clientSearch.value = client.name + ' · ' + client.code;
                clientResults.classList.add('d-none');
              });
              clientResults.appendChild(button);
            });
            if (clientResults.childElementCount === 0) {
              var empty = document.createElement('span');
              empty.className = 'pos-client-empty';
              empty.textContent = 'Nenhum cliente ativo encontrado.';
              clientResults.appendChild(empty);
            }
            clientResults.classList.remove('d-none');
          } catch (error) {
            if (error && error.name === 'AbortError') return;
            clientResults.replaceChildren();
            var message = document.createElement('span');
            message.className = 'pos-client-empty text-danger';
            message.textContent = 'Não foi possível buscar clientes.';
            clientResults.appendChild(message);
            clientResults.classList.remove('d-none');
          }
        }, 300);
      });
      document.addEventListener('click', function (event) {
        if (!event.target.closest('.pos-client-picker')) clientResults.classList.add('d-none');
      });
    }
  }

  document.addEventListener('click', function (event) {
    var button = event.target.closest('.js-reverse-sale');
    if (!button) return;
    var saleId = document.getElementById('cash-reversal-sale-id');
    var saleNumber = document.getElementById('cash-reversal-sale-number');
    if (saleId) saleId.value = button.dataset.saleId || '';
    if (saleNumber) saleNumber.textContent = button.dataset.saleNumber || '—';
  });
}());
