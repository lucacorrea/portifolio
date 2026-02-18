<?php require_once '../app/views/partials/header.php'; ?>

<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Controle de Estoque</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="?url=estoque/movimentacao" class="btn btn-sm btn-primary">
            <i class="bi bi-arrow-left-right"></i> Nova Movimentação
        </a>
    </div>
</div>

<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover table-striped">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Categoria</th>
                        <th>Saldo Atual (Geral)</th> <!-- Placeholder for total stock across branches -->
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($produtos as $produto): ?>
                    <tr>
                        <td>
                            <strong><?php echo $produto['nome']; ?></strong><br>
                            <small class="text-muted"><?php echo $produto['codigo_interno']; ?></small>
                        </td>
                        <td><?php echo $produto['categoria_nome']; ?></td>
                        <td>
                             <!-- We need to fetch actual stock here or in controller. For now a placeholder or a separate query if performance allows -->
                             <span class="badge bg-secondary">Ver Detalhes</span>
                        </td>
                        <td>
                            <a href="?url=produtos/edit/<?php echo $produto['id']; ?>" class="btn btn-sm btn-outline-secondary">
                                <i class="bi bi-eye"></i> Detalhes
                            </a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once '../app/views/partials/footer.php'; ?>
