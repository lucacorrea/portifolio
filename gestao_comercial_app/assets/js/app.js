// ===================================
// Gestão Comercial Premium - App JS
// ===================================

document.addEventListener('DOMContentLoaded', function() {
  console.log('Gestão Comercial Premium carregada');
  
  // Registrar Service Worker
  if ('serviceWorker' in navigator) {
    navigator.serviceWorker.register('./service-worker.js')
      .then(registration => {
        console.log('Service Worker registrado:', registration);
      })
      .catch(error => {
        console.log('Erro ao registrar Service Worker:', error);
      });
  }

  // Inicializar app
  initApp();
});

function initApp() {
  // Função para carregar dados do localStorage
  loadData();
  
  // Configurar event listeners
  setupEventListeners();
  
  // Atualizar dashboard
  updateDashboard();
}

function loadData() {
  const savedData = localStorage.getItem('gestaoComercial');
  if (savedData) {
    window.appData = JSON.parse(savedData);
  } else {
    window.appData = {
      vendas: [],
      clientes: [],
      produtos: [],
      totalVendas: 0
    };
  }
}

function saveData() {
  localStorage.setItem('gestaoComercial', JSON.stringify(window.appData));
}

function setupEventListeners() {
  // Adicionar listeners conforme necessário
  console.log('Event listeners configurados');
}

function updateDashboard() {
  // Atualizar valores do dashboard
  const totalVendas = document.querySelector('.stat-card:nth-child(1) .stat-value');
  const transacoes = document.querySelector('.stat-card:nth-child(2) .stat-value');
  const clientes = document.querySelector('.stat-card:nth-child(3) .stat-value');
  const produtos = document.querySelector('.stat-card:nth-child(4) .stat-value');

  if (totalVendas) {
    totalVendas.textContent = formatCurrency(window.appData.totalVendas);
  }
  if (transacoes) {
    transacoes.textContent = window.appData.vendas.length;
  }
  if (clientes) {
    clientes.textContent = window.appData.clientes.length;
  }
  if (produtos) {
    produtos.textContent = window.appData.produtos.length;
  }
}

function formatCurrency(value) {
  return new Intl.NumberFormat('pt-BR', {
    style: 'currency',
    currency: 'BRL'
  }).format(value);
}

function addVenda(venda) {
  window.appData.vendas.push(venda);
  window.appData.totalVendas += venda.valor;
  saveData();
  updateDashboard();
}

function addCliente(cliente) {
  window.appData.clientes.push(cliente);
  saveData();
  updateDashboard();
}

function addProduto(produto) {
  window.appData.produtos.push(produto);
  saveData();
  updateDashboard();
}

// Exportar funções para uso global
window.gestaoComercial = {
  addVenda,
  addCliente,
  addProduto,
  updateDashboard,
  saveData,
  loadData
};
