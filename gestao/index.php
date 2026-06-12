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

require_once __DIR__ . '/backend/Repositories/DashboardRepository.php';
$repo = new \App\Repositories\DashboardRepository();
$empresaId = (int) $user['empresa_id'];

$resumo = $repo->getTodaySummary($empresaId);
$expiring = $repo->getExpiringProducts($empresaId);
$latestSales = $repo->getLatestSales($empresaId);
$featured = $repo->getFeaturedProducts($empresaId);

$totalVendido = (float) $resumo['total_sales'];
$qtdVendas = (int) $resumo['sales_count'];

require_once __DIR__ . '/pages/layout/header.php';
?>
<header class="blue-area">
 
    
  <div class="balance-row">
    <div>
      <p class="micro-label">Vendido hoje</p>
      <h1>R$ <?= number_format($totalVendido, 2, ',', '.') ?></h1>
      <span class="subtle-light"><?= htmlspecialchars($user['nome']) ?> • <span><?= $qtdVendas ?></span> vendas</span>
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

  <div class="finance-grid">
    <article class="finance-card">
      <p>Total vendido</p>
      <h3>R$ <?= number_format($totalVendido, 2, ',', '.') ?></h3>
      <span><?= $qtdVendas ?> vendas</span>
    </article>
    <article class="finance-card">
      <p>Lucro estimado</p>
      <h3>R$ <?= number_format($totalVendido * 0.32, 2, ',', '.') ?></h3>
      <span>Margem 32%</span>
    </article>
  </div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Relatório diário</h2>
      <p>Resumo operacional do dia</p>
    </div>
  </div>

  <article class="summary-card">
    <div class="summary-line">
      <span>Vendas realizadas</span>
      <strong><?= $qtdVendas ?></strong>
    </div>
    <div class="summary-line">
      <span>Ticket médio</span>
      <strong>R$ <?= number_format($qtdVendas > 0 ? $totalVendido / $qtdVendas : 0, 2, ',', '.') ?></strong>
    </div>
  </article>

  <div class="sheet-title section-gap">
    <div>
      <h2>Produtos perto da validade</h2>
      <p>Alertas dos próximos dias</p>
    </div>
    <a class="small-link" href="pages/produtos.php">Ver</a>
  </div>

  <div class="list-card">
    <?php if (empty($expiring)): ?>
      <p class="empty-state">Nenhum produto perto de vencer.</p>
    <?php else: ?>
      <?php foreach ($expiring as $p): ?>
        <div class="row-item">
          <div class="row-icon"><svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg></div>
          <div class="row-content">
            <h4><?= htmlspecialchars($p['nome']) ?></h4>
            <p>Lote <?= htmlspecialchars($p['lote'] ?? '-') ?> • Validade <?= date('d/m/Y', strtotime($p['validade'])) ?></p>
          </div>
          <div class="row-value">
            <strong class="text-orange"><?= number_format((float)$p['quantidade'], 0, '', '') ?> un.</strong>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Últimas vendas</h2>
      <p>Movimentações recentes do caixa</p>
    </div>
    <a class="small-link" href="pages/historico-vendas.php">Ver tudo</a>
  </div>

  <div class="list-card">
    <?php if (empty($latestSales)): ?>
      <p class="empty-state">Nenhuma venda registrada.</p>
    <?php else: ?>
      <?php foreach ($latestSales as $s): ?>
        <div class="row-item" onclick="location.href='pages/venda-detalhes.php?id=<?= $s['id'] ?>'" style="cursor:pointer">
          <div class="row-icon"><svg viewBox="0 0 24 24"><path d="M7 4h10v16l-2-1-2 1-2-1-2 1-2-1z"/><path d="M9 8h6"/><path d="M9 12h5"/></svg></div>
          <div class="row-content">
            <h4>Venda #<?= str_pad((string)$s['numero_venda'], 4, '0', STR_PAD_LEFT) ?></h4>
            <p><?= htmlspecialchars($s['vendedor'] ?? 'Operador') ?> • <?= date('d/m/Y H:i', strtotime($s['criado_em'])) ?></p>
          </div>
          <div class="row-value">
            <strong class="text-green">R$ <?= number_format((float)$s['total'], 2, ',', '.') ?></strong>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <div class="sheet-title section-gap">
    <div>
      <h2>Produtos em destaque</h2>
      <p>Mais vendidos hoje</p>
    </div>
  </div>

  <div class="list-card">
    <?php if (empty($featured)): ?>
      <p class="empty-state">Nenhum produto vendido hoje.</p>
    <?php else: ?>
      <?php foreach ($featured as $p): ?>
        <div class="row-item">
          <div class="row-icon"><svg viewBox="0 0 24 24"><path d="M5 7h14v12H5z"/><path d="M8 7a4 4 0 0 1 8 0"/></svg></div>
          <div class="row-content">
            <h4><?= htmlspecialchars($p['nome']) ?></h4>
            <p>Em destaque</p>
          </div>
          <div class="row-value">
            <strong class="text-blue"><?= number_format((float)$p['total_vendido'], 0, '', '') ?> un. vendidas</strong>
          </div>
        </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>
</section>

<?php require_once __DIR__ . '/pages/layout/footer.php'; ?>