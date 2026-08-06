document.addEventListener(
  'DOMContentLoaded',
  function () {
    'use strict';

    const dataNode =
      document.getElementById(
        'weekly-page-data'
      );

    let pageData = {};

    try {
      pageData = dataNode
        ? JSON.parse(
          dataNode.textContent || '{}'
        )
        : {};
    } catch (error) {
      pageData = {};
    }

    const recoveryModal =
      pageData.recoveryModal || '';

    const recoveryData =
      pageData.recoveryData || {};

    const recoveryError =
      pageData.recoveryError || '';

    function setValue(id, value) {
      const field =
        document.getElementById(id);

      if (field) {
        field.value =
          value === null
          || value === undefined
            ? ''
            : String(value);
      }
    }

    function setText(id, value) {
      const node =
        document.getElementById(id);

      if (node) {
        node.textContent =
          value === null
          || value === undefined
          || String(value).trim() === ''
            ? '—'
            : String(value);
      }
    }

    function toLocalInput(value) {
      if (!value) {
        return '';
      }

      return String(value)
        .replace(' ', 'T')
        .slice(0, 16);
    }

    function formatDateTime(value) {
      if (!value) {
        return '—';
      }

      const normalized =
        String(value).replace(' ', 'T');

      const date =
        new Date(normalized);

      if (
        Number.isNaN(
          date.getTime()
        )
      ) {
        return String(value);
      }

      return new Intl.DateTimeFormat(
        'pt-BR',
        {
          dateStyle: 'short',
          timeStyle: 'short'
        }
      ).format(date);
    }

    function formatSchedule(
      start,
      end
    ) {
      const startText =
        formatDateTime(start);

      const endText =
        formatDateTime(end);

      if (
        startText === '—'
        && endText === '—'
      ) {
        return '—';
      }

      return startText
        + ' até '
        + endText;
    }

    function syncEmployeeOptions(
      scope
    ) {
      const primary =
        scope?.querySelector(
          '.js-week-primary-employee'
        );

      const support =
        scope?.querySelector(
          '.js-week-support-employee'
        );

      if (!primary || !support) {
        return;
      }

      const primaryValue =
        primary.value;

      const supportValue =
        support.value;

      support
        .querySelectorAll('option')
        .forEach(function (option) {
          option.disabled =
            option.value !== ''
            && option.value
              === primaryValue;
        });

      primary
        .querySelectorAll('option')
        .forEach(function (option) {
          option.disabled =
            option.value !== ''
            && option.value
              === supportValue;
        });
    }

    function calculateEndFromService() {
      const start =
        document.getElementById(
          'week-create-start'
        );

      const end =
        document.getElementById(
          'week-create-end'
        );

      const service =
        document.getElementById(
          'week-create-service'
        );

      if (
        !start
        || !end
        || !service
        || !start.value
      ) {
        return;
      }

      /*
       * Não sobrescreve um horário final
       * já preenchido manualmente.
       */
      if (
        end.value
        && end.dataset.autoCalculated
          !== 'true'
      ) {
        return;
      }

      const duration =
        Number.parseInt(
          service
            .selectedOptions[0]
            ?.dataset.duration
          || '60',
          10
        );

      const startDate =
        new Date(start.value);

      if (
        Number.isNaN(
          startDate.getTime()
        )
      ) {
        return;
      }

      startDate.setMinutes(
        startDate.getMinutes()
        + (
          duration > 0
            ? duration
            : 60
        )
      );

      const pad = function (value) {
        return String(value)
          .padStart(2, '0');
      };

      end.value =
        startDate.getFullYear()
        + '-'
        + pad(
          startDate.getMonth() + 1
        )
        + '-'
        + pad(
          startDate.getDate()
        )
        + 'T'
        + pad(
          startDate.getHours()
        )
        + ':'
        + pad(
          startDate.getMinutes()
        );

      end.dataset.autoCalculated =
        'true';
    }

    function populateConfirmModal(
      button
    ) {
      setValue(
        'week-confirm-id',
        button.dataset.planningId
      );

      setText(
        'week-confirm-code',
        button.dataset.planningCode
      );

      setText(
        'week-confirm-client',
        button.dataset.clientName
      );

      setText(
        'week-confirm-service',
        button.dataset.serviceName
      );

      setText(
        'week-confirm-schedule',
        formatSchedule(
          button.dataset.scheduledStart,
          button.dataset.scheduledEnd
        )
      );

      setText(
        'week-confirm-team',
        button.dataset.teamName
      );
    }

    function showRecoveryError(
      modal
    ) {
      if (
        !modal
        || !recoveryError
      ) {
        return;
      }

      const oldAlert =
        modal.querySelector(
          '.js-week-recovery-error'
        );

      oldAlert?.remove();

      const body =
        modal.querySelector(
          '.modal-body'
        );

      if (!body) {
        return;
      }

      const alert =
        document.createElement(
          'div'
        );

      alert.className =
        'alert alert-danger js-week-recovery-error';

      alert.setAttribute(
        'role',
        'alert'
      );

      alert.textContent =
        recoveryError;

      body.prepend(alert);
    }

    document
      .querySelectorAll(
        '.js-week-primary-employee, .js-week-support-employee'
      )
      .forEach(function (field) {
        field.addEventListener(
          'change',
          function () {
            syncEmployeeOptions(
              field.form
              || document
            );
          }
        );
      });

    const createStart =
      document.getElementById(
        'week-create-start'
      );

    const createEnd =
      document.getElementById(
        'week-create-end'
      );

    const createService =
      document.getElementById(
        'week-create-service'
      );

    createStart?.addEventListener(
      'change',
      calculateEndFromService
    );

    createService?.addEventListener(
      'change',
      function () {
        if (createEnd) {
          createEnd.value = '';

          createEnd.dataset
            .autoCalculated = 'true';
        }

        calculateEndFromService();
      }
    );

    createEnd?.addEventListener(
      'input',
      function () {
        createEnd.dataset
          .autoCalculated = 'false';
      }
    );

    document.addEventListener(
      'click',
      function (event) {
        const button =
          event.target.closest?.(
            '.js-weekly-confirm'
          );

        if (!button) {
          return;
        }

        populateConfirmModal(
          button
        );
      }
    );

    document
      .querySelectorAll(
        '#modal-week-create form, #modal-week-confirm form'
      )
      .forEach(function (form) {
        form.addEventListener(
          'submit',
          function (event) {
            if (
              !form.checkValidity()
            ) {
              event.preventDefault();
              event.stopPropagation();

              form.classList.add(
                'was-validated'
              );

              const invalidField =
                form.querySelector(
                  ':invalid'
                );

              invalidField
                ?.scrollIntoView({
                  behavior: 'smooth',
                  block: 'center'
                });

              invalidField?.focus();

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
                '<span class="spinner-border spinner-border-sm" aria-hidden="true"></span> Processando...';
            }
          }
        );
      });

    function restoreCreateModal() {
      const modal =
        document.getElementById(
          'modal-week-create'
        );

      if (
        !modal
        || !window.bootstrap?.Modal
      ) {
        return;
      }

      setValue(
        'week-create-client',
        recoveryData.client_id
        || recoveryData.cliente_id
      );

      setValue(
        'week-create-service',
        recoveryData.service_id
        || recoveryData.servico_id
      );

      setValue(
        'week-create-priority',
        recoveryData.priority
        || recoveryData.prioridade
        || 'media'
      );

      setValue(
        'week-create-location',
        recoveryData.equipment_location
        || recoveryData.local_servico
      );

      setValue(
        'week-create-start',
        toLocalInput(
          recoveryData.agendado_inicio
        )
      );

      setValue(
        'week-create-end',
        toLocalInput(
          recoveryData.agendado_fim
        )
      );

      setValue(
        'week-create-primary',
        recoveryData
          .funcionario_principal_id
      );

      setValue(
        'week-create-support',
        recoveryData
          .funcionario_apoio_id
      );

      setValue(
        'week-create-notes',
        recoveryData.notes
        || recoveryData.observacao
      );

      syncEmployeeOptions(modal);
      showRecoveryError(modal);

      bootstrap.Modal
        .getOrCreateInstance(modal)
        .show();
    }

    function restoreConfirmModal() {
      const planningId =
        String(
          recoveryData.id || ''
        );

      const modal =
        document.getElementById(
          'modal-week-confirm'
        );

      if (
        !planningId
        || !modal
        || !window.bootstrap?.Modal
      ) {
        return;
      }

      const button =
        document.querySelector(
          '.js-weekly-confirm'
          + '[data-planning-id="'
          + CSS.escape(planningId)
          + '"]'
        );

      if (button) {
        populateConfirmModal(
          button
        );
      } else {
        setValue(
          'week-confirm-id',
          planningId
        );

        setText(
          'week-confirm-code',
          'Serviço #' + planningId
        );
      }

      showRecoveryError(modal);

      bootstrap.Modal
        .getOrCreateInstance(modal)
        .show();
    }

    if (
      recoveryModal === 'create'
    ) {
      restoreCreateModal();
    }

    if (
      recoveryModal === 'confirm'
    ) {
      restoreConfirmModal();
    }

    const createModal =
      document.getElementById(
        'modal-week-create'
      );

    if (createModal) {
      syncEmployeeOptions(
        createModal
      );
    }
  }
);