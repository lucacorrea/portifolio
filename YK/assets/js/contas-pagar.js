document.addEventListener('DOMContentLoaded', function () {
  'use strict';

  const accountFields = [
    'id',
    'fornecedor_id',
    'descricao',
    'documento',
    'data_emissao',
    'vencimento_em',
    'valor',
    'tipo_pagamento',
    'quantidade_parcelas',
    'forma_pagamento',
    'observacao'
  ];

  const paymentLabels = {
    dinheiro: 'Dinheiro',
    pix: 'Pix',
    boleto: 'Boleto',
    cartao_credito: 'Cartão de crédito',
    cartao_debito: 'Cartão de débito',
    transferencia: 'Transferência',
    cheque: 'Cheque',
    outro: 'Outro'
  };

  const statusLabels = {
    pendente: 'Pendente',
    vencida: 'Vencida',
    parcial: 'Parcialmente paga',
    paga: 'Quitada',
    cancelada: 'Cancelada'
  };

  const now = new Date();

  const localToday = [
    now.getFullYear(),
    String(
      now.getMonth() + 1
    ).padStart(2, '0'),
    String(
      now.getDate()
    ).padStart(2, '0')
  ].join('-');

  function payload(button) {
    try {
      return JSON.parse(
        button.dataset.account || '{}'
      );
    } catch (error) {
      return null;
    }
  }

  function value(account, key) {
    const current = account?.[key];

    return (
      current === null
      || current === undefined
      || String(current).trim() === ''
    )
      ? '-'
      : String(current);
  }

  function formatted(account, key, type) {
    const current = value(
      account,
      key
    );

    if (current === '-') {
      return current;
    }

    if (type === 'date') {
      const match = current.match(
        /^(\d{4})-(\d{2})-(\d{2})/
      );

      return match
        ? (
          match[3]
          + '/'
          + match[2]
          + '/'
          + match[1]
        )
        : current;
    }

    if (type === 'money') {
      const number = Number(
        current.replace(',', '.')
      );

      return Number.isFinite(number)
        ? number.toLocaleString(
          'pt-BR',
          {
            style: 'currency',
            currency: 'BRL'
          }
        )
        : current;
    }

    return current;
  }

  function element(
    tag,
    className,
    text
  ) {
    const node = document.createElement(
      tag
    );

    if (className) {
      node.className = className;
    }

    if (text !== undefined) {
      node.textContent = text;
    }

    return node;
  }

  function renderAccount(account) {
    const host = document.getElementById(
      'payable-view-content'
    );

    if (!host) {
      return;
    }

    host.replaceChildren();

    const definitions = [
      [
        'Fornecedor e referência',
        [
          ['codigo', 'Código'],
          ['fornecedor_nome', 'Fornecedor'],
          ['descricao', 'Descrição'],
          ['documento', 'Documento']
        ]
      ],

      [
        'Valores e vencimento',
        [
          ['data_emissao', 'Emissão', 'date'],
          [
            'vencimento_em',
            'Primeiro vencimento',
            'date'
          ],
          ['valor', 'Valor total', 'money'],
          ['status_exibicao', 'Status']
        ]
      ],

      [
        'Condição de pagamento',
        [
          ['tipo_pagamento', 'Modalidade'],
          [
            'quantidade_parcelas',
            'Quantidade de parcelas'
          ],
          [
            'forma_pagamento',
            'Forma prevista'
          ]
        ]
      ],

      [
        'Observações',
        [
          ['observacao', 'Observação']
        ]
      ]
    ];

    definitions.forEach(
      function (definition) {
        const section = element(
          'section',
          'form-section'
        );

        section.appendChild(
          element(
            'h3',
            'form-section-title',
            definition[0]
          )
        );

        const grid = element(
          'div',
          'form-row'
        );

        definition[1].forEach(
          function (field) {
            const group = element(
              'div',
              'form-group'
            );

            let display =
              field[0] === 'status_exibicao'
              && value(
                account,
                field[0]
              ) === '-'
                ? value(
                  account,
                  'status'
                )
                : formatted(
                  account,
                  field[0],
                  field[2]
                );

            if (
              field[0]
              === 'tipo_pagamento'
            ) {
              display =
                display === 'parcelado'
                  ? 'Parcelado'
                  : 'À vista';
            }

            if (
              field[0]
              === 'forma_pagamento'
            ) {
              display =
                paymentLabels[display]
                || 'Não informada';
            }

            if (
              field[0]
              === 'status_exibicao'
            ) {
              display =
                statusLabels[display]
                || display;
            }

            group.append(
              element(
                'span',
                'form-label',
                field[1]
              ),

              element(
                'strong',
                '',
                display
              )
            );

            grid.appendChild(group);
          }
        );

        section.appendChild(grid);
        host.appendChild(section);
      }
    );

    const installments = Array.isArray(
      account.parcelas
    )
      ? account.parcelas
      : [];

    const section = element(
      'section',
      'form-section'
    );

    section.appendChild(
      element(
        'h3',
        'form-section-title',
        'Parcelas por mês'
      )
    );

    const tableWrap = element(
      'div',
      'table-panel-wrap'
    );

    const table = element(
      'table',
      'os-table payable-installment-table'
    );

    const head = element('thead');
    const headRow = element('tr');

    [
      'Parcela',
      'Mês / vencimento',
      'Valor',
      'Situação',
      'Forma',
      'Quitada em',
      'Ação'
    ].forEach(function (label) {
      headRow.appendChild(
        element(
          'th',
          '',
          label
        )
      );
    });

    head.appendChild(headRow);

    const body = element('tbody');

    installments.forEach(
      function (installment) {
        const row = element('tr');

        const overdue =
          installment.status
          === 'pendente'
          && String(
            installment.vencimento_em
          ) < localToday;

        const displayStatus = overdue
          ? 'vencida'
          : installment.status;

        row.className =
          'payable-row payable-row--'
          + displayStatus;

        [
          String(installment.numero)
            + '/'
            + String(
              account.quantidade_parcelas
            ),

          formatted(
            installment,
            'vencimento_em',
            'date'
          ),

          formatted(
            installment,
            'valor',
            'money'
          ),

          statusLabels[displayStatus]
            || displayStatus,

          paymentLabels[
            installment
              .forma_pagamento_quitacao
          ]
            || paymentLabels[
              account.forma_pagamento
            ]
            || 'Não informada',

          formatted(
            installment,
            'quitada_em',
            'date'
          )
        ].forEach(function (text) {
          row.appendChild(
            element(
              'td',
              '',
              text
            )
          );
        });

        const actionCell = element('td');

        if (
          installment.status
          === 'pendente'
          && host.dataset.canSettle
          === '1'
        ) {
          const settle = element(
            'button',
            'btn-filter btn-filter-primary js-payable-settle-installment',
            'Dar baixa'
          );

          settle.type = 'button';

          settle.dataset.installment =
            JSON.stringify(
              installment
            );

          settle.dataset.account =
            JSON.stringify(
              account
            );

          actionCell.appendChild(
            settle
          );
        } else if (
          installment.status
          === 'paga'
          && host.dataset.canReverse
          === '1'
        ) {
          const reverse = element(
            'button',
            'btn-filter btn-filter-ghost js-payable-reverse-installment',
            'Estornar'
          );

          reverse.type = 'button';

          reverse.dataset.installment =
            JSON.stringify(
              installment
            );

          reverse.dataset.account =
            JSON.stringify(
              account
            );

          actionCell.appendChild(
            reverse
          );
        } else {
          actionCell.textContent = '—';
        }

        row.appendChild(actionCell);
        body.appendChild(row);
      }
    );

    table.append(
      head,
      body
    );

    tableWrap.appendChild(table);
    section.appendChild(tableWrap);
    host.appendChild(section);

    const subtitle =
      document.getElementById(
        'payable-view-subtitle'
      );

    if (subtitle) {
      subtitle.textContent =
        value(account, 'codigo')
        + ' — '
        + value(
          account,
          'fornecedor_nome'
        );
    }
  }

  function populateEdit(account) {
    const form = document.querySelector(
      '#modal-conta-pagar-edit form'
    );

    if (!form) {
      return;
    }

    form.reset();

    const supplierSelect =
      form.elements.namedItem(
        'fornecedor_id'
      );

    supplierSelect
      ?.querySelectorAll(
        'option[data-temporary]'
      )
      .forEach(function (option) {
        option.remove();
      });

    if (
      supplierSelect
      && !Array.from(
        supplierSelect.options
      ).some(function (option) {
        return option.value
          === String(
            account.fornecedor_id
          );
      })
    ) {
      const option =
        document.createElement(
          'option'
        );

      option.value =
        account.fornecedor_id
        ?? '';

      option.textContent =
        account.fornecedor_nome
        || 'Fornecedor atual';

      option.dataset.temporary =
        'true';

      supplierSelect.appendChild(
        option
      );
    }

    accountFields.forEach(
      function (name) {
        const field =
          form.elements.namedItem(
            name
          );

        if (
          field
          && !(
            field
            instanceof RadioNodeList
          )
        ) {
          field.value =
            account[name]
            ?? '';
        }
      }
    );

    toggleInstallments(form);

    const subtitle =
      document.getElementById(
        'payable-edit-subtitle'
      );

    if (subtitle) {
      subtitle.textContent =
        value(account, 'codigo')
        + ' — '
        + value(
          account,
          'fornecedor_nome'
        );
    }
  }

  function toggleInstallments(form) {
    const type =
      form?.elements.namedItem(
        'tipo_pagamento'
      );

    const count =
      form?.elements.namedItem(
        'quantidade_parcelas'
      );

    const wrap = form?.querySelector(
      '.js-payable-installment-count'
    );

    if (!type || !count) {
      return;
    }

    const installment =
      type.value === 'parcelado';

    if (wrap) {
      wrap.hidden = !installment;
    }

    /*
     * Campo desabilitado não participa da validação
     * HTML e também não é enviado no POST.
     *
     * O backend já considera uma parcela quando o
     * tipo selecionado é à vista.
     */
    count.disabled = !installment;
    count.required = installment;
    count.min = installment
      ? '2'
      : '1';

    if (installment) {
      const current = Number(
        count.value
      );

      if (
        !Number.isFinite(current)
        || current < 2
      ) {
        count.value = '2';
      }
    } else {
      count.value = '1';
    }
  }

  function switchModal(targetId) {
    const current =
      document.getElementById(
        'modal-conta-pagar-view'
      );

    const target =
      document.getElementById(
        targetId
      );

    if (
      !target
      || !window.bootstrap?.Modal
    ) {
      return;
    }

    const show = function () {
      bootstrap.Modal
        .getOrCreateInstance(target)
        .show();
    };

    if (
      current?.classList.contains(
        'show'
      )
    ) {
      current.addEventListener(
        'hidden.bs.modal',
        show,
        {
          once: true
        }
      );

      bootstrap.Modal
        .getOrCreateInstance(
          current
        )
        .hide();
    } else {
      show();
    }
  }

  function populateCancel(account) {
    const form = document.querySelector(
      '#modal-conta-pagar-cancel form'
    );

    if (!form) {
      return;
    }

    form.reset();

    form.elements.namedItem(
      'id'
    ).value = account.id ?? '';

    const message =
      document.getElementById(
        'payable-cancel-message'
      );

    if (message) {
      message.textContent =
        'Cancelar a conta “'
        + value(
          account,
          'codigo'
        )
        + '” de '
        + value(
          account,
          'fornecedor_nome'
        )
        + '?';
    }
  }

  document.addEventListener(
    'click',
    function (event) {
      const settleButton =
        event.target.closest(
          '.js-payable-settle-installment'
        );

      if (settleButton) {
        const installment = JSON.parse(
          settleButton.dataset.installment
          || '{}'
        );

        const account = JSON.parse(
          settleButton.dataset.account
          || '{}'
        );

        const form =
          document.querySelector(
            '#modal-conta-pagar-quitar form'
          );

        if (!form) {
          return;
        }

        form.elements.namedItem(
          'parcela_id'
        ).value = installment.id || '';

        form.elements.namedItem(
          'forma_pagamento'
        ).value =
          account.forma_pagamento
          || 'outro';

        const message =
          document.getElementById(
            'payable-settle-message'
          );

        if (message) {
          message.textContent =
            'Dar baixa na parcela '
            + installment.numero
            + '/'
            + account.quantidade_parcelas
            + ' de '
            + formatted(
              installment,
              'valor',
              'money'
            )
            + '?';
        }

        switchModal(
          'modal-conta-pagar-quitar'
        );

        return;
      }

      const reverseButton =
        event.target.closest(
          '.js-payable-reverse-installment'
        );

      if (reverseButton) {
        const installment = JSON.parse(
          reverseButton
            .dataset.installment
          || '{}'
        );

        const account = JSON.parse(
          reverseButton
            .dataset.account
          || '{}'
        );

        const form =
          document.querySelector(
            '#modal-conta-pagar-estornar form'
          );

        if (!form) {
          return;
        }

        form.reset();

        form.elements.namedItem(
          'parcela_id'
        ).value = installment.id || '';

        const message =
          document.getElementById(
            'payable-reverse-message'
          );

        if (message) {
          message.textContent =
            'Estornar a parcela '
            + installment.numero
            + '/'
            + account.quantidade_parcelas
            + '?';
        }

        switchModal(
          'modal-conta-pagar-estornar'
        );

        return;
      }

      const statusFilter =
        event.target.closest(
          '.js-payable-status-filter'
        );

      if (statusFilter) {
        event.preventDefault();

        const select =
          document.querySelector(
            'form[data-live-filter="payables"] select[name="status"]'
          );

        if (!select) {
          window.location.assign(
            statusFilter.href
          );

          return;
        }

        select.value =
          statusFilter.dataset.status
          || '';

        select.dispatchEvent(
          new Event(
            'change',
            {
              bubbles: true
            }
          )
        );

        return;
      }

      const button =
        event.target.closest(
          '.js-payable-view, .js-payable-edit, .js-payable-cancel'
        );

      if (!button) {
        return;
      }

      const account = payload(
        button
      );

      if (!account) {
        event.preventDefault();
        return;
      }

      if (
        button.classList.contains(
          'js-payable-view'
        )
      ) {
        renderAccount(account);
      } else if (
        button.classList.contains(
          'js-payable-edit'
        )
      ) {
        populateEdit(account);
      } else {
        populateCancel(account);
      }
    }
  );

  document.addEventListener(
    'change',
    function (event) {
      if (
        event.target.matches(
          '.js-payable-payment-type'
        )
      ) {
        toggleInstallments(
          event.target.form
        );
      }
    }
  );

  document
    .querySelectorAll(
      '.js-payable-payment-type'
    )
    .forEach(function (field) {
      toggleInstallments(
        field.form
      );
    });

  document
    .querySelectorAll(
      '#modal-conta-pagar form, #modal-conta-pagar-edit form'
    )
    .forEach(function (form) {
      form.addEventListener(
        'submit',
        function (event) {
          /*
           * Sincroniza o campo de parcelas antes
           * da validação nativa do navegador.
           */
          toggleInstallments(form);

          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();

            form.classList.add(
              'was-validated'
            );

            const invalidField =
              form.querySelector(
                ':invalid'
              );

            if (invalidField) {
              invalidField.scrollIntoView({
                behavior: 'smooth',
                block: 'center'
              });

              invalidField.focus();
            }

            form.reportValidity();

            return;
          }

          const submitButton =
            form.querySelector(
              'button[type="submit"]'
            );

          if (submitButton) {
            submitButton.disabled = true;

            submitButton.setAttribute(
              'aria-busy',
              'true'
            );

            submitButton.innerHTML =
              '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Salvando...';
          }
        }
      );
    });

  const recoveryModal =
    document.querySelector(
      '.modal[data-recovery-open="true"]'
    );

  if (
    recoveryModal
    && window.bootstrap?.Modal
  ) {
    bootstrap.Modal
      .getOrCreateInstance(
        recoveryModal
      )
      .show();
  }
});