<?php require_once '../app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Histórico de Vendas</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="?url=vendas/pdv" class="btn btn-sm btn-success">
            <i class="bi bi-cart-plus"></i> Abrir PDV
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Data</th>
                        <th>Cliente</th>
                        <th>Vendedor</th>
                        <th>Total</th>
                        <th>Pagamento</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($vendas as $venda): ?>
                    <tr>
                        <td><?php echo $venda['id']; ?></td>
                        <td><?php echo date('d/m/Y H:i', strtotime($venda['created_at'])); ?></td>
                        <td><?php echo $venda['cliente_nome']; ?></td>
                        <td><?php echo $venda['vendedor_nome']; ?></td>
                        <td class="fw-bold">R$ <?php echo number_format($venda['total'], 2, ',', '.'); ?></td>
                        <td>
                             <span class="badge bg-light text-dark border"><?php echo $venda['forma_pagamento']; ?></span>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-printer"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../app/views/partials/footer.php'; ?>
