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

  const recoveryModal = pageData.recoveryModal
    || new URLSearchParams(
      window.location.search
    ).get('modal');

  const recoveryData = pageData.recoveryData || {};

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

  const errorBox = document.getElementById(
    'os-finalize-error'
  );

  const submitButton = document.getElementById(
    'os-finalize-submit'
  );

  let loadedOrderId = null;

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

  function setLoadingState(isLoading) {
    loading.hidden = !isLoading;
    content.hidden = isLoading;

    if (submitButton) {
      submitButton.disabled = isLoading;
    }
  }

  function showError(message) {
    errorBox.textContent = String(
      message
      || 'Não foi possível carregar a OS.'
    );

    errorBox.hidden = false;
    content.hidden = true;

    if (submitButton) {
      submitButton.disabled = true;
    }
  }

  function clearError() {
    errorBox.textContent = '';
    errorBox.hidden = true;
  }

  function typeLabel(type) {
    return {
      servico: 'Serviço',
      produto: 'Produto',
      outro: 'Outro'
    }[type] || 'Item';
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

  function renderItems(items) {
    itemsBody.replaceChildren();

    items.forEach(function (item) {
      const row = document.createElement('tr');

      const subtotal = Math.max(
        0,
        (
          parseNumber(item.quantity)
          * parseNumber(item.unit_price)
        )
        - parseNumber(item.discount)
      );

      const values = [
        typeLabel(item.type),

        item.description || '—',

        quantity(
          parseNumber(item.quantity)
        ) + ' ' + (item.unit || ''),

        money(
          parseNumber(item.unit_price)
        ),

        money(
          parseNumber(item.discount)
        ),

        money(subtotal)
      ];

      values.forEach(function (value, index) {
        const cell = document.createElement('td');

        cell.textContent = value;

        if (index === 1) {
          cell.style.whiteSpace = 'normal';
          cell.style.minWidth = '220px';
        }

        row.appendChild(cell);
      });

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

    const grossTotal = itemTotals.servico
      + itemTotals.produto
      + itemTotals.outro;

    const total = grossTotal
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

    const valid = total > 0
      && discount <= grossTotal + increase;

    if (submitButton) {
      submitButton.disabled = !valid
        || loadedOrderId === null;
    }

    const validation = document.getElementById(
      'os-finalize-value-warning'
    );

    if (validation) {
      validation.hidden = valid;

      validation.textContent =
        discount > grossTotal + increase
          ? 'O desconto não pode ser maior que o valor da OS.'
          : 'O total final deve ser maior que zero.';
    }
  }

  async function loadFinalizationData(
    orderId,
    recoveredValues = null
  ) {
    loadedOrderId = null;

    clearError();
    setLoadingState(true);

    if (orderIdInput) {
      orderIdInput.value = String(orderId);
    }

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

      const order = payload.order || [];

      const items = Array.isArray(payload.items)
        ? payload.items
        : [];

      if (items.length === 0) {
        throw new Error(
          'A OS não possui itens cadastrados.'
        );
      }

      loadedOrderId = Number(order.id) || null;

      itemTotals = calculateItemTotals(items);

      setText(
        'os-finalize-number',
        order.number || ('OS #' + orderId)
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
        discountInput.value = decimalInput(
          parseNumber(recoveredDiscount)
        );
      }

      if (increaseInput) {
        increaseInput.value = decimalInput(
          parseNumber(recoveredIncrease)
        );
      }

      setLoadingState(false);
      recalculateFinalTotal();
    } catch (error) {
      setLoadingState(false);

      showError(
        error instanceof Error
          ? error.message
          : 'Não foi possível carregar os dados da OS.'
      );
    }
  }

  modal.addEventListener(
    'show.bs.modal',
    function (event) {
      const trigger = event.relatedTarget?.closest?.(
        '.js-os-finalize'
      );

      if (!trigger) {
        return;
      }

      const orderId = trigger.dataset.orderId;

      if (!orderId) {
        showError(
          'Identificador da OS não informado.'
        );

        return;
      }

      void loadFinalizationData(orderId);
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
        || submitButton?.disabled
        || !form.checkValidity()
      ) {
        event.preventDefault();
        event.stopPropagation();

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
   * Recupera os dados quando o servidor devolve uma validação
   * para a modal de finalização.
   */
  if (
    recoveryModal === 'finalize'
    && recoveryData.id
  ) {
    void loadFinalizationData(
      recoveryData.id,
      recoveryData
    );
  }
});