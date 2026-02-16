<?php require_once '../app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Relatórios Gerenciais</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <button type="button" class="btn btn-sm btn-outline-secondary">Exportar PDF</button>
            <button type="button" class="btn btn-sm btn-outline-secondary">Exportar Excel</button>
        </div>
    </div>
</div>

<!-- Key Metrics -->
<div class="row g-4 mb-4">
    <div class="col-md-4">
        <div class="card border-start border-4 border-primary shadow-sm">
            <div class="card-body">
                <h6 class="text-uppercase text-muted">Vendas Hoje</h6>
                <h2 class="mb-0">R$ <?php echo number_format($totalToday, 2, ',', '.'); ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-start border-4 border-success shadow-sm">
            <div class="card-body">
                <h6 class="text-uppercase text-muted">Ticket Médio</h6>
                <h2 class="mb-0">R$ 150,00</h2> <!-- Placeholder calculation -->
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card border-start border-4 border-info shadow-sm">
            <div class="card-body">
                <h6 class="text-uppercase text-muted">Produtos Vendidos</h6>
                <h2 class="mb-0">124</h2> <!-- Placeholder -->
            </div>
        </div>
    </div>
</div>

<!-- Payment Methods Table -->
<div class="card mb-4 shadow-sm">
    <div class="card-header">
        Vendas por Forma de Pagamento
    </div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Forma de Pagamento</th>
                    <th>Qtd. Vendas</th>
                    <th>Total (R$)</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($paymentStats as $stat): ?>
                <tr>
                    <td><?php echo $stat['forma_pagamento']; ?></td>
                    <td><?php echo $stat['qtd']; ?></td>
                    <td>R$ <?php echo number_format($stat['total'], 2, ',', '.'); ?></td>
                </tr>
                <?php endforeach; ?>
                <?php if (empty($paymentStats)): ?>
                <tr><td colspan="3" class="text-center text-muted">Nenhuma venda registrada ainda.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Placeholder for more charts -->
<div class="row">
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header">Curva ABC de Produtos</div>
            <div class="card-body text-center p-5">
                <i class="bi bi-pie-chart-fill display-1 text-muted opacity-25"></i>
                <p class="mt-3 text-muted">Gráfico demonstrativo</p>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow-sm">
            <div class="card-header">Evolução de Vendas (Mensal)</div>
             <div class="card-body text-center p-5">
                <i class="bi bi-graph-up-arrow display-1 text-muted opacity-25"></i>
                <p class="mt-3 text-muted">Gráfico demonstrativo</p>
            </div>
        </div>
    </div>
</div>

<?php require_once '../app/views/partials/footer.php'; ?>
