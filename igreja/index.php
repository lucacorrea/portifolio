<?php include 'conexao.php'; ?>

<?php
$total = (int)$pdo->query("SELECT COUNT(*) FROM membros")->fetchColumn();
$totalBatismo = (int)$pdo->query("SELECT COUNT(*) FROM membros WHERE tipo_ingresso = 'BATISMO'")->fetchColumn();
$totalMasc = (int)$pdo->query("SELECT COUNT(*) FROM membros WHERE sexo = 'M'")->fetchColumn();
$totalFem = (int)$pdo->query("SELECT COUNT(*) FROM membros WHERE sexo = 'F'")->fetchColumn();

$ultimos = $pdo->query("
    SELECT id, nome_completo, congregacao, telefone, tipo_ingresso, criado_em
    FROM membros
    ORDER BY id DESC
    LIMIT 8
")->fetchAll();

$porCongregacao = $pdo->query("
    SELECT congregacao, COUNT(*) AS total
    FROM membros
    WHERE congregacao IS NOT NULL AND congregacao <> ''
    GROUP BY congregacao
    ORDER BY total DESC
    LIMIT 5
")->fetchAll();

$porArea = $pdo->query("
    SELECT area, COUNT(*) AS total
    FROM membros
    WHERE area IS NOT NULL AND area <> ''
    GROUP BY area
    ORDER BY total DESC
    LIMIT 5
")->fetchAll();
?>

<?php include 'includes/header.php'; ?>

<div class="dashboard-page">
    <div class="row g-3 mb-4">
        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm dashboard-card dashboard-card-primary h-100">
                <div class="card-body">
                    <div class="dashboard-card-top">
                        <div>
                            <span class="dashboard-label">Total de membros</span>
                            <h2 class="dashboard-number"><?= $total ?></h2>
                        </div>
                        <div class="dashboard-icon bg-primary-subtle text-primary">
                            <i class="bi bi-people-fill"></i>
                        </div>
                    </div>
                    <p class="dashboard-text mb-0">Quantidade geral de membros cadastrados.</p>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm dashboard-card dashboard-card-success h-100">
                <div class="card-body">
                    <div class="dashboard-card-top">
                        <div>
                            <span class="dashboard-label">Batismo</span>
                            <h2 class="dashboard-number"><?= $totalBatismo ?></h2>
                        </div>
                        <div class="dashboard-icon bg-success-subtle text-success">
                            <i class="bi bi-droplet-half"></i>
                        </div>
                    </div>
                    <p class="dashboard-text mb-0">Membros que entraram por batismo.</p>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm dashboard-card dashboard-card-info h-100">
                <div class="card-body">
                    <div class="dashboard-card-top">
                        <div>
                            <span class="dashboard-label">Masculino</span>
                            <h2 class="dashboard-number"><?= $totalMasc ?></h2>
                        </div>
                        <div class="dashboard-icon bg-info-subtle text-info">
                            <i class="bi bi-person-fill"></i>
                        </div>
                    </div>
                    <p class="dashboard-text mb-0">Total de membros do sexo masculino.</p>
                </div>
            </div>
        </div>

        <div class="col-12 col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm dashboard-card dashboard-card-warning h-100">
                <div class="card-body">
                    <div class="dashboard-card-top">
                        <div>
                            <span class="dashboard-label">Feminino</span>
                            <h2 class="dashboard-number"><?= $totalFem ?></h2>
                        </div>
                        <div class="dashboard-icon bg-warning-subtle text-warning">
                            <i class="bi bi-person-hearts"></i>
                        </div>
                    </div>
                    <p class="dashboard-text mb-0">Total de membros do sexo feminino.</p>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-xl-8">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-header bg-white border-0 pb-0">
                    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-2">
                        <div>
                            <h5 class="mb-1">Últimos cadastros</h5>
                            <small class="text-muted">Visualize os membros cadastrados recentemente</small>
                        </div>
                        <a href="listar.php" class="btn btn-outline-dark btn-sm">
                            <i class="bi bi-card-list me-1"></i> Ver todos
                        </a>
                    </div>
                </div>

                <div class="card-body">
                    <?php if (!$ultimos): ?>
                        <div class="empty-state text-center py-5">
                            <div class="empty-state-icon mb-3">
                                <i class="bi bi-people"></i>
                            </div>
                            <h5 class="mb-2">Nenhum membro cadastrado</h5>
                            <p class="text-muted mb-3">Comece agora adicionando o primeiro cadastro do sistema.</p>
                            <a href="cadastrar.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> Cadastrar membro
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-modern align-middle">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Congregação</th>
                                        <th>Telefone</th>
                                        <th>Ingresso</th>
                                        <th>Cadastro</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimos as $m): ?>
                                        <tr>
                                            <td>
                                                <div class="fw-semibold"><?= htmlspecialchars($m['nome_completo']) ?></div>
                                            </td>
                                            <td><?= htmlspecialchars($m['congregacao'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars($m['telefone'] ?: '-') ?></td>
                                            <td>
                                                <?php
                                                $tipo = strtoupper((string)($m['tipo_ingresso'] ?? ''));
                                                $badgeClass = 'bg-secondary-subtle text-secondary';

                                                if ($tipo === 'BATISMO') $badgeClass = 'bg-success-subtle text-success';
                                                if ($tipo === 'ACLAMACAO') $badgeClass = 'bg-warning-subtle text-warning';
                                                if ($tipo === 'MUDANCA') $badgeClass = 'bg-primary-subtle text-primary';
                                                ?>
                                                <span class="badge rounded-pill <?= $badgeClass ?>">
                                                    <?= htmlspecialchars($tipo ?: 'NÃO INFORMADO') ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></td>
                                            <td class="text-end">
                                                <div class="d-flex justify-content-end flex-wrap gap-2">
                                                    <a href="visualizar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="editar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-warning">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <a href="ficha.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                                        <i class="bi bi-file-earmark-text"></i>
                                                    </a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="row g-3">
                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-1">Ações rápidas</h5>
                            <small class="text-muted">Atalhos para agilizar seu trabalho</small>
                        </div>
                        <div class="card-body">
                            <div class="quick-actions">
                                <a href="cadastrar.php" class="quick-action-item">
                                    <div class="quick-action-icon bg-primary-subtle text-primary">
                                        <i class="bi bi-person-plus-fill"></i>
                                    </div>
                                    <div>
                                        <strong>Novo membro</strong>
                                        <small>Cadastrar novo registro</small>
                                    </div>
                                </a>

                                <a href="listar.php" class="quick-action-item">
                                    <div class="quick-action-icon bg-dark-subtle text-dark">
                                        <i class="bi bi-list-ul"></i>
                                    </div>
                                    <div>
                                        <strong>Lista geral</strong>
                                        <small>Ver todos os membros</small>
                                    </div>
                                </a>

                                <a href="listar.php" class="quick-action-item">
                                    <div class="quick-action-icon bg-success-subtle text-success">
                                        <i class="bi bi-search"></i>
                                    </div>
                                    <div>
                                        <strong>Buscar cadastro</strong>
                                        <small>Localizar rapidamente</small>
                                    </div>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-1">Top congregações</h5>
                            <small class="text-muted">As mais cadastradas no sistema</small>
                        </div>
                        <div class="card-body">
                            <?php if (!$porCongregacao): ?>
                                <p class="text-muted mb-0">Sem dados no momento.</p>
                            <?php else: ?>
                                <?php foreach ($porCongregacao as $item): ?>
                                    <div class="stat-line">
                                        <div>
                                            <strong><?= htmlspecialchars($item['congregacao']) ?></strong>
                                        </div>
                                        <span class="badge bg-light text-dark border"><?= (int)$item['total'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="card border-0 shadow-sm">
                        <div class="card-header bg-white border-0">
                            <h5 class="mb-1">Top áreas</h5>
                            <small class="text-muted">Distribuição resumida</small>
                        </div>
                        <div class="card-body">
                            <?php if (!$porArea): ?>
                                <p class="text-muted mb-0">Sem dados no momento.</p>
                            <?php else: ?>
                                <?php foreach ($porArea as $item): ?>
                                    <div class="stat-line">
                                        <div>
                                            <strong><?= htmlspecialchars($item['area']) ?></strong>
                                        </div>
                                        <span class="badge bg-light text-dark border"><?= (int)$item['total'] ?></span>
                                    </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include 'includes/footer.php'; ?>