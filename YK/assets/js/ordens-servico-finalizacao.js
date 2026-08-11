document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const modal = document.getElementById(
    'modal-os-finalize'
  );

  const form = modal?.querySelector('form');

  if (!modal || !form || !window.bootstrap) {
    return;
  }

  const dataNode = document.getElementById(
    'os-page-data'
  );

  const pageData = dataNode
    ? JSON.parse(dataNode.textContent || '{}')
    : {};

  const query = new URLSearchParams(
    window.location.search
  );

  const recoveryModal = pageData.recoveryModal
    || query.get('modal');

  const recoveryData = pageData.recoveryData || {};

  const queryOrderId = query.get('finalize_id');

  const orderIdInput = document.getElementById(
    'os-finalize-id'
  );

  const discountInput = document.getElementById(
    'os-finalize-discount'
  );

  const increaseInput = document.getElementById(
    'os-finalize-increase'
  );

  const itemsBody = document.getElementById(
    'os-finalize-items'
  );

  const loading = document.getElementById(
    'os-finalize-loading'
  );

  const content = document.getElementById(
    'os-finalize-content'
  );

  const messageBox = document.getElementById(
    'os-finalize-error'
  );

  const warningBox = document.getElementById(
    'os-finalize-value-warning'
  );

  const submitButton = document.getElementById(
    'os-finalize-submit'
  );

  const csrfToken = form.querySelector(
    'input[name="csrf_token"]'
  )?.value || '';

  let loadedOrderId = null;
  let hasStockShortage = false;

  let itemTotals = {
    servico: 0,
    produto: 0,
    outro: 0
  };

  function parseNumber(value) {
    let normalized = String(value ?? '0')
      .trim()
      .replace(/\s/g, '');

    if (normalized.includes(',')) {
      normalized = normalized
        .replace(/\./g, '')
        .replace(',', '.');
    }

    const number = Number.parseFloat(normalized);

    return Number.isFinite(number)
      ? Math.max(0, number)
      : 0;
  }

  function money(value) {
    return Number(value || 0).toLocaleString(
      'pt-BR',
      {
        style: 'currency',
        currency: 'BRL'
      }
    );
  }

  function decimalInput(value) {
    return Number(value || 0).toLocaleString(
      'pt-BR',
      {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      }
    );
  }

  function quantity(value) {
    return Number(value || 0).toLocaleString(
      'pt-BR',
      {
        minimumFractionDigits: 0,
        maximumFractionDigits: 3
      }
    );
  }

  function setText(id, value) {
    const node = document.getElementById(id);

    if (node) {
      node.textContent = String(value ?? '');
    }
  }

  function setModalUrl(orderId) {
    const url = new URL(window.location.href);

    url.searchParams.set(
      'modal',
      'finalize'
    );

    url.searchParams.set(
      'finalize_id',
      String(orderId)
    );

    window.history.replaceState(
      {},
      '',
      url.pathname
        + url.search
        + url.hash
    );
  }

  function clearModalUrl() {
    const url = new URL(window.location.href);

    if (
      url.searchParams.get('modal')
      !== 'finalize'
    ) {
      return;
    }

    url.searchParams.delete('modal');
    url.searchParams.delete('finalize_id');

    window.history.replaceState(
      {},
      '',
      url.pathname
        + url.search
        + url.hash
    );
  }

  function removeLegacyRecoveryAlerts() {
    modal.querySelectorAll(
      '.modal-body > .alert-danger:not(#os-finalize-error)'
    ).forEach(function (alert) {
      alert.remove();
    });
  }

  function setLoadingState(isLoading) {
    loading.hidden = !isLoading;
    content.hidden = isLoading;

    if (submitButton) {
      submitButton.disabled = isLoading;
    }
  }

  function clearMessage() {
    messageBox.hidden = true;
    messageBox.textContent = '';

    messageBox.classList.remove(
      'alert-danger',
      'alert-success',
      'alert-warning'
    );

    messageBox.classList.add(
      'alert-danger'
    );
  }

  function showMessage(message, type) {
    messageBox.textContent = String(message || '');
    messageBox.hidden = false;

    messageBox.classList.remove(
      'alert-danger',
      'alert-success',
      'alert-warning'
    );

    messageBox.classList.add(
      type === 'success'
        ? 'alert-success'
        : type === 'warning'
          ? 'alert-warning'
          : 'alert-danger'
    );
  }

  function showLoadError(message) {
    showMessage(message, 'danger');

    content.hidden = true;

    if (submitButton) {
      submitButton.disabled = true;
    }
  }

  function typeLabel(type) {
    return {
      servico: 'Serviço',
      produto: 'Produto',
      outro: 'Outro'
    }[type] || 'Item';
  }

  function currentFormState() {
    return {
      desconto: discountInput?.value || '0,00',
      acrescimo: increaseInput?.value || '0,00',

      vencimento_em:
        document.getElementById(
          'os-finalize-due-date'
        )?.value || '',

      proximo_lembrete_em:
        document.getElementById(
          'os-finalize-reminder-date'
        )?.value || '',

      observacao:
        document.getElementById(
          'os-finalize-notes'
        )?.value || '',

      saldo_observacao:
        document.getElementById(
          'os-finalize-balance-notes'
        )?.value || ''
    };
  }

  function applyRecoveredState(state) {
    if (!state) {
      return;
    }

    const fields = {
      'os-finalize-due-date':
        state.vencimento_em,

      'os-finalize-reminder-date':
        state.proximo_lembrete_em,

      'os-finalize-notes':
        state.observacao,

      'os-finalize-balance-notes':
        state.saldo_observacao
    };

    Object.entries(fields).forEach(
      function ([id, value]) {
        if (value === undefined) {
          return;
        }

        const input = document.getElementById(id);

        if (input) {
          input.value = String(value || '');
        }
      }
    );
  }

  function calculateItemTotals(items) {
    const totals = {
      servico: 0,
      produto: 0,
      outro: 0
    };

    items.forEach(function (item) {
      const type = [
        'servico',
        'produto',
        'outro'
      ].includes(item.type)
        ? item.type
        : 'outro';

      const subtotal = Math.max(
        0,
        (
          parseNumber(item.quantity)
          * parseNumber(item.unit_price)
        )
        - parseNumber(item.discount)
      );

      totals[type] += subtotal;
    });

    return totals;
  }

  async function saveItemQuantity(
    item,
    input,
    button
  ) {
    const newQuantity = parseNumber(
      input.value
    );

    if (newQuantity <= 0) {
      showMessage(
        'Informe uma quantidade maior que zero.',
        'danger'
      );

      input.focus();
      return;
    }

    const previousButtonHtml = button.innerHTML;

    button.disabled = true;
    input.disabled = true;

    button.innerHTML =
      '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span>';

    try {
      const body = new FormData();

      body.set(
        'csrf_token',
        csrfToken
      );

      body.set(
        'order_id',
        String(loadedOrderId)
      );

      body.set(
        'item_id',
        String(item.id)
      );

      body.set(
        'quantity',
        String(input.value)
      );

      const response = await fetch(
        'actions/os-item-quantidade-atualizar.php',
        {
          method: 'POST',
          body: body,
          credentials: 'same-origin',

          headers: {
            Accept: 'application/json'
          },

          cache: 'no-store'
        }
      );

      const payload = await response.json()
        .catch(function () {
          return {};
        });

      if (!response.ok || !payload.success) {
        throw new Error(
          payload.error
          || 'Não foi possível atualizar a quantidade.'
        );
      }

      const preservedState = currentFormState();

      showMessage(
        'Quantidade atualizada. O estoque está sendo revalidado.',
        'success'
      );

      await loadFinalizationData(
        loadedOrderId,
        preservedState,
        false
      );

      showMessage(
        payload.message
        || 'Quantidade atualizada com sucesso.',
        'success'
      );
    } catch (error) {
      showMessage(
        error instanceof Error
          ? error.message
          : 'Não foi possível atualizar a quantidade.',
        'danger'
      );
    } finally {
      button.disabled = false;
      input.disabled = false;
      button.innerHTML = previousButtonHtml;
    }
  }

  function createStockBadge(item) {
    const badge = document.createElement('span');

    badge.className =
      'os-finalize-stock-badge';

    if (item.stock_missing) {
      badge.classList.add('is-danger');
      badge.textContent =
        'Produto indisponível no estoque';

      return badge;
    }

    const available = parseNumber(
      item.stock_available
    );

    const required = parseNumber(
      item.quantity
    );

    badge.classList.add(
      item.stock_insufficient
        ? 'is-danger'
        : 'is-success'
    );

    badge.textContent =
      'Necessário: '
      + quantity(required)
      + ' '
      + (item.unit || '')
      + ' · Disponível: '
      + quantity(available)
      + ' '
      + (item.unit || '');

    return badge;
  }

  function renderItems(items) {
    itemsBody.replaceChildren();

    hasStockShortage = items.some(
      function (item) {
        return Boolean(
          item.stock_insufficient
        );
      }
    );

    items.forEach(function (item) {
      const row = document.createElement('tr');

      if (item.stock_insufficient) {
        row.classList.add(
          'os-stock-insufficient'
        );

        row.title =
          'Estoque insuficiente. Ajuste a quantidade utilizada.';
      }

      const subtotal = Math.max(
        0,
        (
          parseNumber(item.quantity)
          * parseNumber(item.unit_price)
        )
        - parseNumber(item.discount)
      );

      const typeCell = document.createElement('td');

      typeCell.textContent = typeLabel(
        item.type
      );

      const descriptionCell =
        document.createElement('td');

      const description =
        document.createElement('strong');

      description.textContent =
        item.description || '—';

      descriptionCell.appendChild(description);

      if (item.type === 'produto') {
        descriptionCell.appendChild(
          createStockBadge(item)
        );
      }

      const quantityCell =
        document.createElement('td');

      if (
        item.type === 'produto'
        && item.quantity_editable
      ) {
        const editor =
          document.createElement('div');

        editor.className =
          'os-finalize-quantity-editor';

        const input =
          document.createElement('input');

        input.className =
          'form-control-os os-finalize-quantity-input';

        input.type = 'number';
        input.min = '0.001';
        input.step = '0.001';

        input.value = String(
          parseNumber(item.quantity)
        );

        input.setAttribute(
          'aria-label',
          'Quantidade utilizada de '
            + (item.description || 'produto')
        );

        const unit =
          document.createElement('span');

        unit.className =
          'os-finalize-quantity-unit';

        unit.textContent =
          item.unit || 'un';

        const saveButton =
          document.createElement('button');

        saveButton.type = 'button';

        saveButton.className =
          'btn-filter btn-filter-ghost os-finalize-save-quantity';

        saveButton.innerHTML =
          '<i class="bi bi-check-lg"></i> Atualizar';

        saveButton.addEventListener(
          'click',
          function () {
            void saveItemQuantity(
              item,
              input,
              saveButton
            );
          }
        );

        input.addEventListener(
          'keydown',
          function (event) {
            if (event.key !== 'Enter') {
              return;
            }

            event.preventDefault();

            void saveItemQuantity(
              item,
              input,
              saveButton
            );
          }
        );

        editor.append(
          input,
          unit,
          saveButton
        );

        quantityCell.appendChild(editor);
      } else {
        quantityCell.textContent =
          quantity(
            parseNumber(item.quantity)
          )
          + ' '
          + (item.unit || '');
      }

      const unitPriceCell =
        document.createElement('td');

      unitPriceCell.textContent = money(
        parseNumber(item.unit_price)
      );

      const discountCell =
        document.createElement('td');

      discountCell.textContent = money(
        parseNumber(item.discount)
      );

      const subtotalCell =
        document.createElement('td');

      subtotalCell.textContent = money(
        subtotal
      );

      row.append(
        typeCell,
        descriptionCell,
        quantityCell,
        unitPriceCell,
        discountCell,
        subtotalCell
      );

      itemsBody.appendChild(row);
    });
  }

  function recalculateFinalTotal() {
    const discount = parseNumber(
      discountInput?.value
    );

    const increase = parseNumber(
      increaseInput?.value
    );

    const grossTotal =
      itemTotals.servico
      + itemTotals.produto
      + itemTotals.outro;

    const total =
      grossTotal
      - discount
      + increase;

    setText(
      'os-finalize-services-total',
      money(itemTotals.servico)
    );

    setText(
      'os-finalize-products-total',
      money(itemTotals.produto)
    );

    setText(
      'os-finalize-others-total',
      money(itemTotals.outro)
    );

    setText(
      'os-finalize-gross-total',
      money(grossTotal)
    );

    setText(
      'os-finalize-total',
      money(Math.max(0, total))
    );

    const monetaryValid =
      total > 0
      && discount <= grossTotal + increase;

    const finalizationValid =
      monetaryValid
      && !hasStockShortage
      && loadedOrderId !== null;

    if (submitButton) {
      submitButton.disabled =
        !finalizationValid;
    }

    if (!warningBox) {
      return;
    }

    if (hasStockShortage) {
      warningBox.hidden = false;

      warningBox.textContent =
        'Existem produtos com estoque insuficiente. Ajuste a quantidade utilizada ou faça uma reposição em Produtos / Peças antes de finalizar.';

      return;
    }

    if (
      discount > grossTotal + increase
    ) {
      warningBox.hidden = false;

      warningBox.textContent =
        'O desconto não pode ser maior que o valor da OS.';

      return;
    }

    if (total <= 0) {
      warningBox.hidden = false;

      warningBox.textContent =
        'O valor final da OS deve ser maior que zero.';

      return;
    }

    warningBox.hidden = true;
    warningBox.textContent = '';
  }

  async function loadFinalizationData(
    orderId,
    recoveredValues,
    resetMessage
  ) {
    loadedOrderId = null;

    if (resetMessage !== false) {
      clearMessage();
    }

    removeLegacyRecoveryAlerts();
    setLoadingState(true);

    if (orderIdInput) {
      orderIdInput.value =
        String(orderId);
    }

    setModalUrl(orderId);

    try {
      const response = await fetch(
        'actions/os-finalizacao-dados.php?id='
          + encodeURIComponent(orderId),
        {
          method: 'GET',
          credentials: 'same-origin',

          headers: {
            Accept: 'application/json'
          },

          cache: 'no-store'
        }
      );

      const payload = await response.json()
        .catch(function () {
          return {};
        });

      if (!response.ok) {
        throw new Error(
          payload.error
          || 'Não foi possível carregar os dados da OS.'
        );
      }

      const order = payload.order || {};

      const items = Array.isArray(
        payload.items
      )
        ? payload.items
        : [];

      if (items.length === 0) {
        throw new Error(
          'A OS não possui itens cadastrados.'
        );
      }

      loadedOrderId =
        Number(order.id) || null;

      itemTotals =
        calculateItemTotals(items);

      setText(
        'os-finalize-number',
        order.number
        || ('OS #' + orderId)
      );

      renderItems(items);

      const recoveredDiscount =
        recoveredValues
        && Object.prototype.hasOwnProperty.call(
          recoveredValues,
          'desconto'
        )
          ? recoveredValues.desconto
          : order.discount;

      const recoveredIncrease =
        recoveredValues
        && Object.prototype.hasOwnProperty.call(
          recoveredValues,
          'acrescimo'
        )
          ? recoveredValues.acrescimo
          : order.increase;

      if (discountInput) {
        discountInput.value =
          decimalInput(
            parseNumber(recoveredDiscount)
          );
      }

      if (increaseInput) {
        increaseInput.value =
          decimalInput(
            parseNumber(recoveredIncrease)
          );
      }

      applyRecoveredState(
        recoveredValues
      );

      setLoadingState(false);
      recalculateFinalTotal();
    } catch (error) {
      setLoadingState(false);

      showLoadError(
        error instanceof Error
          ? error.message
          : 'Não foi possível carregar os dados da OS.'
      );
    }
  }

  modal.addEventListener(
    'show.bs.modal',
    function (event) {
      const trigger =
        event.relatedTarget?.closest?.(
          '.js-os-finalize'
        );

      if (!trigger) {
        return;
      }

      const orderId =
        trigger.dataset.orderId;

      if (!orderId) {
        showLoadError(
          'Identificador da OS não informado.'
        );

        return;
      }

      void loadFinalizationData(
        orderId,
        null,
        true
      );
    }
  );

  modal.addEventListener(
    'hidden.bs.modal',
    function () {
      clearModalUrl();
      clearMessage();
    }
  );

  discountInput?.addEventListener(
    'input',
    recalculateFinalTotal
  );

  increaseInput?.addEventListener(
    'input',
    recalculateFinalTotal
  );

  form.addEventListener(
    'submit',
    function (event) {
      recalculateFinalTotal();

      if (
        loadedOrderId === null
        || hasStockShortage
        || submitButton?.disabled
        || !form.checkValidity()
      ) {
        event.preventDefault();
        event.stopPropagation();

        if (hasStockShortage) {
          showMessage(
            'Corrija os produtos com estoque insuficiente antes de finalizar.',
            'danger'
          );
        }

        form.reportValidity();
        return;
      }

      if (submitButton) {
        submitButton.disabled = true;

        submitButton.setAttribute(
          'aria-busy',
          'true'
        );
      }
    }
  );

  /*
   * Reabre automaticamente a modal depois de:
   * - erro retornado pelo servidor;
   * - atualização da página;
   * - retorno com modal=finalize&finalize_id=...
   */
  const automaticOrderId =
    recoveryData.id
    || queryOrderId;

  if (
    recoveryModal === 'finalize'
    && automaticOrderId
  ) {
    bootstrap.Modal
      .getOrCreateInstance(modal)
      .show();

    void loadFinalizationData(
      automaticOrderId,
      recoveryData,
      true
    );
  }
});