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

<link rel="stylesheet" href="./assets/css/style.css">

<div class="dashboard-wrapper">

    <div class="dashboard-hero mb-4">
        <div class="row align-items-center g-4">
            <div class="col-lg-8">
                <div class="hero-content">
                    <span class="hero-badge">
                        <i class="bi bi-stars me-2"></i> Painel principal
                    </span>
                    <h1 class="hero-title">Dashboard de membros</h1>
                    <p class="hero-subtitle">
                        Visualize rapidamente os cadastros, acompanhe os números principais
                        e acesse as ações mais importantes do sistema.
                    </p>

                    <div class="hero-actions">
                        <a href="cadastrar.php" class="btn btn-primary btn-lg">
                            <i class="bi bi-person-plus-fill me-2"></i> Novo membro
                        </a>
                        <a href="listar.php" class="btn btn-light btn-lg border">
                            <i class="bi bi-card-list me-2"></i> Ver lista
                        </a>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="hero-mini-card">
                    <div class="mini-card-top">
                        <span class="mini-card-label">Resumo geral</span>
                        <div class="mini-card-icon">
                            <i class="bi bi-bar-chart-line-fill"></i>
                        </div>
                    </div>

                    <div class="mini-stat-list">
                        <div class="mini-stat-item">
                            <span>Total de membros</span>
                            <strong><?= $total ?></strong>
                        </div>
                        <div class="mini-stat-item">
                            <span>Batizados</span>
                            <strong><?= $totalBatismo ?></strong>
                        </div>
                        <div class="mini-stat-item">
                            <span>Masculino</span>
                            <strong><?= $totalMasc ?></strong>
                        </div>
                        <div class="mini-stat-item">
                            <span>Feminino</span>
                            <strong><?= $totalFem ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card kpi-primary h-100">
                <div class="kpi-head">
                    <div>
                        <span class="kpi-label">Total de membros</span>
                        <h2 class="kpi-number"><?= $total ?></h2>
                    </div>
                    <div class="kpi-icon bg-primary-subtle text-primary">
                        <i class="bi bi-people-fill"></i>
                    </div>
                </div>
                <p class="kpi-text">Todos os registros cadastrados no sistema.</p>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card kpi-success h-100">
                <div class="kpi-head">
                    <div>
                        <span class="kpi-label">Batismo</span>
                        <h2 class="kpi-number"><?= $totalBatismo ?></h2>
                    </div>
                    <div class="kpi-icon bg-success-subtle text-success">
                        <i class="bi bi-droplet-half"></i>
                    </div>
                </div>
                <p class="kpi-text">Membros cadastrados com entrada por batismo.</p>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card kpi-info h-100">
                <div class="kpi-head">
                    <div>
                        <span class="kpi-label">Masculino</span>
                        <h2 class="kpi-number"><?= $totalMasc ?></h2>
                    </div>
                    <div class="kpi-icon bg-info-subtle text-info">
                        <i class="bi bi-person-fill"></i>
                    </div>
                </div>
                <p class="kpi-text">Quantidade de membros do sexo masculino.</p>
            </div>
        </div>

        <div class="col-12 col-sm-6 col-xl-3">
            <div class="kpi-card kpi-warning h-100">
                <div class="kpi-head">
                    <div>
                        <span class="kpi-label">Feminino</span>
                        <h2 class="kpi-number"><?= $totalFem ?></h2>
                    </div>
                    <div class="kpi-icon bg-warning-subtle text-warning">
                        <i class="bi bi-person-hearts"></i>
                    </div>
                </div>
                <p class="kpi-text">Quantidade de membros do sexo feminino.</p>
            </div>
        </div>
    </div>

    <div class="row g-4">
        <div class="col-12 col-xxl-8">
            <div class="panel-card h-100">
                <div class="panel-header">
                    <div>
                        <h5 class="panel-title">Últimos cadastros</h5>
                        <p class="panel-subtitle mb-0">Membros adicionados recentemente</p>
                    </div>
                    <a href="listar.php" class="btn btn-outline-dark btn-sm">
                        <i class="bi bi-arrow-right me-1"></i> Ver todos
                    </a>
                </div>

                <div class="panel-body">
                    <?php if (!$ultimos): ?>
                        <div class="empty-box">
                            <div class="empty-box-icon">
                                <i class="bi bi-people"></i>
                            </div>
                            <h5 class="mb-2">Nenhum cadastro encontrado</h5>
                            <p class="text-muted mb-3">Ainda não existem membros cadastrados no sistema.</p>
                            <a href="cadastrar.php" class="btn btn-primary">
                                <i class="bi bi-plus-circle me-1"></i> Cadastrar agora
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-dashboard align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th>Nome</th>
                                        <th>Congregação</th>
                                        <th>Telefone</th>
                                        <th>Ingresso</th>
                                        <th>Data</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($ultimos as $m): ?>
                                        <?php
                                        $tipo = strtoupper((string)($m['tipo_ingresso'] ?? ''));
                                        $badgeClass = 'bg-secondary-subtle text-secondary';

                                        if ($tipo === 'BATISMO') $badgeClass = 'bg-success-subtle text-success';
                                        if ($tipo === 'ACLAMACAO') $badgeClass = 'bg-warning-subtle text-warning';
                                        if ($tipo === 'MUDANCA') $badgeClass = 'bg-primary-subtle text-primary';
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="member-main">
                                                    <div class="member-avatar">
                                                        <?= strtoupper(mb_substr($m['nome_completo'] ?: 'M', 0, 1, 'UTF-8')) ?>
                                                    </div>
                                                    <div>
                                                        <div class="member-name"><?= htmlspecialchars($m['nome_completo']) ?></div>
                                                        <div class="member-meta">Cadastro #<?= (int)$m['id'] ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td><?= htmlspecialchars($m['congregacao'] ?: '-') ?></td>
                                            <td><?= htmlspecialchars($m['telefone'] ?: '-') ?></td>
                                            <td>
                                                <span class="badge rounded-pill <?= $badgeClass ?>">
                                                    <?= htmlspecialchars($tipo ?: 'NÃO INFORMADO') ?>
                                                </span>
                                            </td>
                                            <td><?= date('d/m/Y H:i', strtotime($m['criado_em'])) ?></td>
                                            <td class="text-end">
                                                <div class="action-group">
                                                    <a href="visualizar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-primary" title="Visualizar">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    <a href="editar.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-warning" title="Editar">
                                                        <i class="bi bi-pencil-square"></i>
                                                    </a>
                                                    <a href="ficha.php?id=<?= $m['id'] ?>" class="btn btn-sm btn-outline-secondary" title="Ficha">
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

        <div class="col-12 col-xxl-4">
            <div class="row g-4">
                <div class="col-12">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h5 class="panel-title">Ações rápidas</h5>
                                <p class="panel-subtitle mb-0">Acessos mais usados</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <div class="quick-grid">
                                <a href="cadastrar.php" class="quick-box">
                                    <div class="quick-box-icon bg-primary-subtle text-primary">
                                        <i class="bi bi-person-plus-fill"></i>
                                    </div>
                                    <strong>Novo cadastro</strong>
                                    <small>Adicionar membro</small>
                                </a>

                                <a href="listar.php" class="quick-box">
                                    <div class="quick-box-icon bg-success-subtle text-success">
                                        <i class="bi bi-search"></i>
                                    </div>
                                    <strong>Buscar membros</strong>
                                    <small>Consultar lista</small>
                                </a>

                                <a href="listar.php" class="quick-box">
                                    <div class="quick-box-icon bg-warning-subtle text-warning">
                                        <i class="bi bi-pencil-square"></i>
                                    </div>
                                    <strong>Editar cadastro</strong>
                                    <small>Atualizar dados</small>
                                </a>

                                <a href="listar.php" class="quick-box">
                                    <div class="quick-box-icon bg-dark-subtle text-dark">
                                        <i class="bi bi-file-earmark-text"></i>
                                    </div>
                                    <strong>Ver fichas</strong>
                                    <small>Imprimir cadastro</small>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h5 class="panel-title">Top congregações</h5>
                                <p class="panel-subtitle mb-0">Distribuição por congregação</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <?php if (!$porCongregacao): ?>
                                <p class="text-muted mb-0">Sem dados disponíveis.</p>
                            <?php else: ?>
                                <div class="stat-list">
                                    <?php foreach ($porCongregacao as $item): ?>
                                        <div class="stat-item">
                                            <div>
                                                <strong><?= htmlspecialchars($item['congregacao']) ?></strong>
                                            </div>
                                            <span class="stat-badge"><?= (int)$item['total'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="panel-card">
                        <div class="panel-header">
                            <div>
                                <h5 class="panel-title">Top áreas</h5>
                                <p class="panel-subtitle mb-0">Distribuição por área</p>
                            </div>
                        </div>
                        <div class="panel-body">
                            <?php if (!$porArea): ?>
                                <p class="text-muted mb-0">Sem dados disponíveis.</p>
                            <?php else: ?>
                                <div class="stat-list">
                                    <?php foreach ($porArea as $item): ?>
                                        <div class="stat-item">
                                            <div>
                                                <strong><?= htmlspecialchars($item['area']) ?></strong>
                                            </div>
                                            <span class="stat-badge"><?= (int)$item['total'] ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include 'includes/footer.php'; ?>