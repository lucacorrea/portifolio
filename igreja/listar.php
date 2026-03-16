<?php include 'conexao.php'; ?>
<?php
$busca = trim($_GET['busca'] ?? '');
$sql = "SELECT * FROM membros";
$params = [];

if ($busca !== '') {
    $sql .= " WHERE nome_completo LIKE :busca OR congregacao LIKE :busca OR telefone LIKE :busca";
    $params[':busca'] = '%' . $busca . '%';
}

$sql .= " ORDER BY id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$membros = $stmt->fetchAll();
?>
<?php include 'includes/header.php'; ?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <div>
        <h3 class="mb-0">Lista de membros</h3>
        <small class="text-muted">Busca rápida por nome, congregação ou telefone</small>
    </div>
    <a href="cadastrar.php" class="btn btn-primary">Cadastrar membro</a>
</div>

<div class="card shadow-sm border-0">
    <div class="card-body">
        <form method="get" class="row g-2 mb-3">
            <div class="col-md-10">
                <input type="text" name="busca" class="form-control" placeholder="Digite para buscar..." value="<?= htmlspecialchars($busca) ?>">
            </div>
            <div class="col-md-2 d-grid">
                <button class="btn btn-dark">Buscar</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead>
                    <tr>
                        <th width="70">Foto</th>
                        <th>Nome</th>
                        <th>Telefone</th>
                        <th>Congregação</th>
                        <th>Área</th>
                        <th>Ingresso</th>
                        <th width="220">Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$membros): ?>
                        <tr><td colspan="7" class="text-center text-muted py-4">Nenhum registro encontrado.</td></tr>
                    <?php endif; ?>

                    <?php foreach ($membros as $m): ?>
                        <tr>
                            <td>
                                <?php if (!empty($m['foto'])): ?>
                                    <img src="uploads/<?= htmlspecialchars($m['foto']) ?>" class="thumb-foto" alt="foto">
                                <?php else: ?>
                                    <div class="thumb-vazio">-</div>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($m['nome_completo']) ?></td>
                            <td><?= htmlspecialchars($m['telefone']) ?></td>
                            <td><?= htmlspecialchars($m['congregacao']) ?></td>
                            <td><?= htmlspecialchars($m['area']) ?></td>
                            <td><?= htmlspecialchars($m['tipo_ingresso']) ?></td>
                            <td class="acoes">
                                <a href="visualizar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary">Ver</a>
                                <a href="editar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-warning">Editar</a>
                                <a href="ficha.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary">Ficha</a>
                                <a href="excluir.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-danger btn-excluir">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>
