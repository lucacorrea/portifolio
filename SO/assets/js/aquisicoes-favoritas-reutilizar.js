/* SO | Conferência de modelos favoritos: itens, totais e opção Sem ofício. */
document.addEventListener('DOMContentLoaded', function () {
    'use strict';
    const form = document.getElementById('fav-reuse-form');
    if (!form) return;
    const container = document.getElementById('fav-items-container');
    const countInput = document.getElementById('fav-count-input');
    const numeroInput = document.getElementById('fav-numero-oficio');
    const semCheckbox = document.getElementById('fav-sem-oficio');
    const totalElement = document.getElementById('fav-total-itens');
    const deviceDate = document.getElementById('fav-criado-em-device');
    const devicePreview = document.getElementById('fav-datahora-dispositivo');
    const MAX_ITEMS = 200;
    const moneyFormatter = new Intl.NumberFormat('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    let previewCode = '';
    let manualNumber = numeroInput ? numeroInput.value : '';

    function parseMoney(value) {
        let raw = String(value == null ? '' : value).trim().replace(/R\$/gi, '').replace(/\s/g, '');
        if (!raw) return 0;
        if (raw.includes(',')) raw = raw.replace(/\./g, '').replace(',', '.');
        else if (/^\d{1,3}(\.\d{3})+$/.test(raw)) raw = raw.replace(/\./g, '');
        const number = Number(raw);
        return Number.isFinite(number) && number >= 0 ? number : 0;
    }
    function formatPrice(value) { return moneyFormatter.format(Number(value) || 0); }
    function formatCurrency(value) { return 'R$ ' + formatPrice(value); }

    function updateDeviceTime() {
        const date = new Date();
        const pad = value => String(value).padStart(2, '0');
        const timestamp = date.getFullYear() + '-' + pad(date.getMonth() + 1) + '-' + pad(date.getDate()) + ' '
            + pad(date.getHours()) + ':' + pad(date.getMinutes()) + ':' + pad(date.getSeconds());
        if (deviceDate) deviceDate.value = timestamp;
        if (devicePreview) devicePreview.textContent = date.toLocaleString('pt-BR');
    }

    function previewNumber() {
        if (!previewCode) {
            const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
            const values = new Uint8Array(4);
            if (window.crypto && window.crypto.getRandomValues) window.crypto.getRandomValues(values);
            else for (let i = 0; i < 4; i++) values[i] = Math.floor(Math.random() * 256);
            previewCode = Array.from(values).map(v => alphabet[v % alphabet.length]).join('');
        }
        return 'SEM OFÍCIO Nº ' + previewCode + '/' + new Date().getFullYear() + '/' + (numeroInput.dataset.sigla || 'SEC');
    }

    function syncNumero() {
        if (!numeroInput || !semCheckbox) return;
        if (semCheckbox.checked) {
            if (!numeroInput.readOnly) manualNumber = numeroInput.value;
            numeroInput.value = previewNumber();
            numeroInput.readOnly = true;
            numeroInput.required = false;
            numeroInput.classList.add('fav-reuse-auto');
        } else {
            numeroInput.readOnly = false;
            numeroInput.required = true;
            numeroInput.classList.remove('fav-reuse-auto');
            numeroInput.value = manualNumber;
        }
    }

    function rows() { return Array.from(container.querySelectorAll('.fav-reuse-row')); }

    function refreshIndices() {
        const all = rows();
        all.forEach((row, index) => {
            row.querySelector('.fav-item-index').value = String(index + 1);
            row.querySelectorAll('[data-field]').forEach(input => {
                input.name = 'produtos[' + index + '][' + input.dataset.field + ']';
            });
        });
        countInput.value = String(all.length);
        for (const row of all) row.querySelector('.fav-remove-item').disabled = all.length === 1;
    }

    function updateTotals() {
        let total = 0;
        rows().forEach(row => {
            const qtd = Number(String(row.querySelector('.fav-item-qtd').value).replace(',', '.')) || 0;
            const preco = parseMoney(row.querySelector('.fav-item-price').value);
            const subtotal = Math.round(qtd * preco * 100) / 100;
            row.querySelector('.fav-item-total').value = formatPrice(subtotal);
            total += subtotal;
        });
        totalElement.textContent = formatCurrency(Math.round(total * 100) / 100);
    }

    function newRow() {
        const row = document.createElement('div');
        row.className = 'fav-reuse-row';
        row.innerHTML = '<div class="form-group"><label class="form-label">Nº</label><input class="form-control fav-item-index" readonly></div>'
            + '<div class="form-group"><label class="form-label">Nome do Item</label><input class="form-control fav-item-name" data-field="nome" maxlength="255" placeholder="Ex: Papel A4" required></div>'
            + '<div class="form-group"><label class="form-label">Quantidade</label><input class="form-control fav-item-qtd" data-field="qtd" type="number" min="0.01" step="0.01" value="1" required></div>'
            + '<div class="form-group"><label class="form-label">Unidade</label><input class="form-control fav-item-unit" data-field="unidade" maxlength="20" value="UN" required></div>'
            + '<div class="form-group"><label class="form-label">Valor Unitário</label><input class="form-control fav-item-price" data-field="valor" inputmode="decimal" value="0,00" required></div>'
            + '<div class="form-group"><label class="form-label">Valor Total</label><input class="form-control fav-item-total" inputmode="decimal" value="0,00" title="Informe o total para calcular o unitário"></div>'
            + '<div><button type="button" class="btn btn-outline btn-sm fav-remove-item" title="Remover item" aria-label="Remover item"><i class="fas fa-trash" aria-hidden="true"></i></button></div>';
        return row;
    }

    if (semCheckbox) semCheckbox.addEventListener('change', syncNumero);
    if (numeroInput) numeroInput.addEventListener('input', () => {
        if (!semCheckbox.checked) manualNumber = numeroInput.value.toUpperCase();
    });

    document.getElementById('fav-generate-items').addEventListener('click', function () {
        const desired = Number(countInput.value);
        if (!Number.isInteger(desired) || desired < 1 || desired > MAX_ITEMS) {
            alert('Escolha entre 1 e 200 itens.');
            countInput.value = rows().length;
            return;
        }
        const current = rows();
        if (desired < current.length && !confirm('Remover os últimos ' + (current.length - desired) + ' itens da lista?')) {
            countInput.value = current.length;
            return;
        }
        while (rows().length > desired) rows()[rows().length - 1].remove();
        while (rows().length < desired) container.appendChild(newRow());
        refreshIndices();
        updateTotals();
    });

    document.getElementById('fav-add-item').addEventListener('click', function () {
        if (rows().length >= MAX_ITEMS) { alert('Limite de 200 itens atingido.'); return; }
        const row = newRow();
        container.appendChild(row);
        refreshIndices();
        updateTotals();
        row.querySelector('.fav-item-name').focus();
    });

    container.addEventListener('click', function (event) {
        const remove = event.target.closest('.fav-remove-item');
        if (!remove) return;
        if (rows().length < 2) return;
        remove.closest('.fav-reuse-row').remove();
        refreshIndices();
        updateTotals();
    });

    container.addEventListener('input', function (event) {
        const target = event.target;
        if (!target.closest('.fav-reuse-row') || target.classList.contains('fav-item-total')) return;
        updateTotals();
    });

    container.addEventListener('change', function (event) {
        const target = event.target;
        if (!target.classList.contains('fav-item-total')) return;
        const row = target.closest('.fav-reuse-row');
        if (!row) return;
        const qtd = Number(String(row.querySelector('.fav-item-qtd').value).replace(',', '.')) || 0;
        const unit = qtd > 0 ? Math.round(parseMoney(target.value) / qtd * 100) / 100 : 0;
        row.querySelector('.fav-item-price').value = formatPrice(unit);
        updateTotals();
    });

    form.addEventListener('submit', function (event) {
        if (!rows().length) {event.preventDefault();alert('Adicione pelo menos um item.');return;}
        if (semCheckbox && !semCheckbox.checked && !numeroInput.value.trim()) {
            event.preventDefault();numeroInput.focus();return;
        }
        if (!form.reportValidity()) {event.preventDefault();return;}
        if (!confirm('Criar uma nova solicitação com estes itens e preços? Ela seguirá o fluxo normal de aprovação.')) {
            event.preventDefault();return;
        }
        updateDeviceTime();
        const saveButton = document.getElementById('fav-submit');
        if (saveButton) {saveButton.disabled = true;saveButton.textContent = 'Salvando solicitação...';}
    });

    refreshIndices();
    syncNumero();
    updateTotals();
    updateDeviceTime();
});
