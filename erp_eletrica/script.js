// Sistema ERP Elétrica - JavaScript

// Aguardar carregamento do DOM
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar componentes
    initModals();
    initForms();
    initDataTables();
    initCharts();
});

// Gerenciamento de Modais
function initModals() {
    const modals = document.querySelectorAll('.modal');
    const closeBtns = document.querySelectorAll('.close');
    
    closeBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const modal = this.closest('.modal');
            modal.style.display = 'none';
        });
    });
    
    window.addEventListener('click', function(event) {
        modals.forEach(modal => {
            if (event.target == modal) {
                modal.style.display = 'none';
            }
        });
    });
}

// Abrir Modal
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
    }
}

// Fechar Modal
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Validação de Formulários
function initForms() {
    const forms = document.querySelectorAll('form[data-validate]');
    
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!validateForm(this)) {
                e.preventDefault();
                showNotification('Por favor, preencha todos os campos obrigatórios', 'error');
            }
        });
    });
}

function validateForm(form) {
    let isValid = true;
    const requiredFields = form.querySelectorAll('[required]');
    
    requiredFields.forEach(field => {
        if (!field.value.trim()) {
            field.classList.add('error');
            isValid = false;
        } else {
            field.classList.remove('error');
        }
    });
    
    return isValid;
}

// Formatação de Moeda
function formatMoney(value) {
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL'
    }).format(value);
}

// Formatação de Data
function formatDate(date) {
    return new Date(date).toLocaleDateString('pt-BR');
}

// Notificações
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <i class="fas fa-${type === 'success' ? 'check-circle' : 
                           type === 'error' ? 'exclamation-circle' : 
                           'info-circle'}"></i>
        <span>${message}</span>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 100);
    
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Gráficos (usando Chart.js)
function initCharts() {
    // Gráfico de Vendas Mensais
    const salesChart = document.getElementById('salesChart');
    if (salesChart) {
        new Chart(salesChart, {
            type: 'line',
            data: {
                labels: ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun'],
                datasets: [{
                    label: 'Vendas (R$)',
                    data: [12000, 15000, 18000, 16000, 22000, 25000],
                    borderColor: '#3498db',
                    backgroundColor: 'rgba(52, 152, 219, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    }
    
    // Gráfico de Status OS
    const statusChart = document.getElementById('statusChart');
    if (statusChart) {
        new Chart(statusChart, {
            type: 'doughnut',
            data: {
                labels: ['Abertas', 'Em Andamento', 'Concluídas', 'Canceladas'],
                datasets: [{
                    data: [15, 25, 45, 5],
                    backgroundColor: [
                        '#f39c12',
                        '#3498db',
                        '#27ae60',
                        '#e74c3c'
                    ]
                }]
            }
        });
    }
}

// DataTables
function initDataTables() {
    const tables = document.querySelectorAll('.datatable');
    
    tables.forEach(table => {
        // Implementação simples de busca
        const searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.placeholder = 'Buscar...';
        searchInput.className = 'form-control table-search';
        searchInput.style.marginBottom = '15px';
        searchInput.style.maxWidth = '300px';
        
        table.parentNode.insertBefore(searchInput, table);
        
        searchInput.addEventListener('keyup', function() {
            const searchTerm = this.value.toLowerCase();
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                row.style.display = text.includes(searchTerm) ? '' : 'none';
            });
        });
    });
}

// Cálculo automático de valores em OS
function calculateOSValues() {
    const items = document.querySelectorAll('.os-item');
    let total = 0;
    
    items.forEach(item => {
        const qty = parseFloat(item.querySelector('.qty').value) || 0;
        const price = parseFloat(item.querySelector('.price').value) || 0;
        const subtotal = qty * price;
        
        item.querySelector('.subtotal').value = formatMoney(subtotal);
        total += subtotal;
    });
    
    document.getElementById('osTotal').value = formatMoney(total);
}

// Adicionar item à OS
function addOSItem() {
    const container = document.getElementById('osItems');
    const newItem = document.createElement('div');
    newItem.className = 'form-row os-item';
    newItem.innerHTML = `
        <div class="form-group">
            <label>Produto</label>
            <select class="form-control" required>
                <option value="">Selecione...</option>
                <!-- Options will be loaded dynamically -->
            </select>
        </div>
        <div class="form-group">
            <label>Quantidade</label>
            <input type="number" class="form-control qty" min="1" value="1" onchange="calculateOSValues()">
        </div>
        <div class="form-group">
            <label>Valor Unit.</label>
            <input type="text" class="form-control price" onchange="calculateOSValues()">
        </div>
        <div class="form-group">
            <label>Subtotal</label>
            <input type="text" class="form-control subtotal" readonly>
        </div>
        <div class="form-group">
            <button type="button" class="btn btn-danger" onclick="this.closest('.os-item').remove(); calculateOSValues()">
                <i class="fas fa-trash"></i>
            </button>
        </div>
    `;
    
    container.appendChild(newItem);
}

// Exportar para PDF
function exportToPDF() {
    const element = document.getElementById('contentToExport');
    
    html2pdf().from(element).save();
}

// Exportar para Excel
function exportToExcel() {
    const table = document.querySelector('table');
    const wb = XLSX.utils.table_to_book(table, {sheet: "Sheet1"});
    XLSX.writeFile(wb, "exportacao.xlsx");
}

// Buscar CEP
async function buscarCEP(cep) {
    cep = cep.replace(/\D/g, '');
    
    if (cep.length !== 8) {
        showNotification('CEP inválido', 'error');
        return;
    }
    
    try {
        const response = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
        const data = await response.json();
        
        if (data.erro) {
            showNotification('CEP não encontrado', 'error');
            return;
        }
        
        document.getElementById('endereco').value = data.logradouro;
        document.getElementById('bairro').value = data.bairro;
        document.getElementById('cidade').value = data.localidade;
        document.getElementById('estado').value = data.uf;
        
    } catch (error) {
        showNotification('Erro ao buscar CEP', 'error');
    }
}

// Máscaras de input
document.addEventListener('input', function(e) {
    // CPF/CNPJ
    if (e.target.matches('#cpf_cnpj')) {
        let value = e.target.value.replace(/\D/g, '');
        
        if (value.length <= 11) {
            value = value.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        } else {
            value = value.replace(/^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
        }
        
        e.target.value = value;
    }
    
    // Telefone
    if (e.target.matches('#telefone')) {
        let value = e.target.value.replace(/\D/g, '');
        
        if (value.length <= 10) {
            value = value.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
        } else {
            value = value.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
        }
        
        e.target.value = value;
    }
    
    // CEP
    if (e.target.matches('#cep')) {
        let value = e.target.value.replace(/\D/g, '');
        value = value.replace(/(\d{5})(\d{3})/, '$1-$2');
        e.target.value = value;
    }
    
    // Moeda
    if (e.target.matches('.money')) {
        let value = e.target.value.replace(/\D/g, '');
        value = (parseInt(value) / 100).toFixed(2);
        value = value.replace('.', ',');
        value = value.replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
        e.target.value = 'R$ ' + value;
    }
});

// Carregar produtos via AJAX
async function loadProducts() {
    try {
        const response = await fetch('api/get_products.php');
        const products = await response.json();
        
        const selects = document.querySelectorAll('select[name="produto"]');
        selects.forEach(select => {
            select.innerHTML = '<option value="">Selecione...</option>';
            products.forEach(product => {
                select.innerHTML += `<option value="${product.id}">${product.nome}</option>`;
            });
        });
    } catch (error) {
        console.error('Erro ao carregar produtos:', error);
    }
}