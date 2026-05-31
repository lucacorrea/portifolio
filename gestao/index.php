<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/bootstrap.php';

use App\Security\Auth;

Auth::requireLogin();

$user = Auth::user();


$pageId = 'dashboard';
$pageTitle = 'Dashboard';
$activeMenu = 'home';
$prefix = '';
require_once __DIR__ . '/pages/layout/header.php';
?>
<header class="blue-area">
  
    <div class="statusbar ">
      <strong data-time>09:41</strong>
      <div class="device-icons" aria-hidden="true">
        <span class="signal"></span>
        <span class="wifi"></span>
        <span class="battery"></span>
      </div>
    </div>
    
  <div class="balance-row">
    <div>
      <p class="micro-label">Vendido hoje</p>
      <h1 id="todayTotal">R$ 0,00</h1>
      <span class="subtle-light">L&J Soluções Tech • <span id="todaySalesCount">0</span> vendas</span>
    </div>
    <a class="avatar-btn" href="pages/configuracoes.php" aria-label="Configurações">
      <img src="assets/icons/icon.svg" alt="L&J" />
    </a>
  </div>

  <nav class="quick-menu" aria-label="Ações rápidas">
    <a href="pages/nova-venda.php"><i data-icon="receipt"></i><span>Venda</span></a>
    <a href="pages/produtos.php"><i data-icon="product"></i><span>Produtos</span></a>
    <a href="pages/clientes.php"><i data-icon="user"></i><span>Clientes</span></a>
    <a href="pages/relatorios.php"><i data-icon="report"></i><span>Relatórios</span></a>
    <a href="pages/historico-vendas.php"><i data-icon="box"></i><span>Histórico</span></a>
  </nav>
</header>

<section class="white-sheet">
  <div class="sheet-title">
    <div>
      <h2>Resumo financeiro</h2>
      <p>Recebimentos e lucro estimado</p>
    </div>
  </div>

  <div class="finance-grid" id="dashboardFinance"></div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Relatório diário</h2>
      <p>Resumo operacional do dia</p>
    </div>
  </div>

  <article class="summary-card" id="dailyReport"></article>

  <div class="sheet-title section-gap">
    <div>
      <h2>Produtos perto da validade</h2>
      <p>Alertas dos próximos dias</p>
    </div>
    <a class="small-link" href="pages/produtos.php">Ver</a>
  </div>

  <div class="list-card" id="expiringProducts"></div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Últimas vendas</h2>
      <p>Movimentações recentes do caixa</p>
    </div>
    <a class="small-link" href="pages/historico-vendas.php">Ver tudo</a>
  </div>

  <div class="list-card" id="latestSales"></div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Produtos em destaque</h2>
      <p>Mais vendidos hoje</p>
    </div>
  </div>

  <div class="list-card" id="featuredProducts"></div>
</section>

<?php require_once __DIR__ . '/pages/layout/footer.php'; ?>