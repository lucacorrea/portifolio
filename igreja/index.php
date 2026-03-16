<?php include 'conexao.php'; ?>
<?php
$total = (int)$pdo->query("SELECT COUNT(*) FROM membros")->fetchColumn();
$totalBatismo = (int)$pdo->query("SELECT COUNT(*) FROM membros WHERE tipo_ingresso = 'BATISMO'")->fetchColumn();
$totalMasc = (int)$pdo->query("SELECT COUNT(*) FROM membros WHERE sexo = 'M'")->fetchColumn();
$totalFem = (int)$pdo->query("SELECT COUNT(*) FROM membros WHERE sexo = 'F'")->fetchColumn();
$ultimos = $pdo->query("SELECT id, nome_completo, congregacao, criado_em FROM membros ORDER BY id DESC LIMIT 5")->fetchAll();
?>
<?php include 'includes/header.php'; ?>

<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card shadow-sm border-0 card-kpi">
            <div class="card-body">
                <small class="text-muted">Total de membros</small>
                <h2 class="mb-0"><?= $total ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 card-kpi">
            <div class="card-body">
                <small class="text-muted">Batismo</small>
                <h2 class="mb-0"><?= $totalBatismo ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 card-kpi">
            <div class="card-body">
                <small class="text-muted">Masculino</small>
                <h2 class="mb-0"><?= $totalMasc ?></h2>
            </div>
        </div>
    </div>
    <div class="col-md-3">
        <div class="card shadow-sm border-0 card-kpi">
            <div class="card-body">
                <small class="text-muted">Feminino</small>
                <h2 class="mb-0"><?= $totalFem ?></h2>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm border-0">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <strong>Últimos cadastros</strong>
        <a href="cadastrar.php" class="btn btn-primary btn-sm">Novo cadastro</a>
    </div>
    <div class="card-body">
        <?php if (!$ultimos): ?>
            <p class="text-muted mb-0">Nenhum membro cadastrado ainda.</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table align-middle">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Congregação</th>
                            <th>Data</th>
                            <th width="140">Ações</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($ultimos as $m): ?>
                            <tr>
                                <td><?= htmlspecialchars($m['nome_completo']) ?></td>
                                <td><?= htmlspecialchars($m['congregacao']) ?></td>
                                <td><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></td>
                                <td>
                                    <a href="visualizar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                                    <a href="editar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
