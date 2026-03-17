<div class="row">
    <?php foreach ($sugestoes as $s): ?>
    <div class="col-md-6 mb-3">
        <div class="card border-start border-4 border-warning shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="flex-shrink-0 bg-warning text-white rounded-circle d-flex align-items-center justify-content-center" style="width: 45px; height: 45px;">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <div class="ms-3 flex-grow-1">
                    <div class="fw-bold"><?= $s['nome'] ?></div>
                    <div class="small text-muted">
                        Estoque: <b><?= $s['estoque'] ?></b> | Média Vendas: <b><?= number_format($s['media_mensal'], 1) ?>/mês</b>
                    </div>
                </div>
                <div>
                    <a href="estoque.php?action=edit&id=<?= $s['id'] ?>" class="btn btn-sm btn-outline-warning">Ver Produto</a>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <?php if (empty($sugestoes)): ?>
        <div class="col-12 text-center py-5">
            <i class="fas fa-check-circle text-success fs-1 mb-3"></i>
            <h5 class="fw-bold">Estoque Saudável!</h5>
            <p class="text-muted small">Todos os produtos possuem estoque acima da média de vendas dos últimos 3 meses.</p>
        </div>
    <?php endif; ?>
</div>
