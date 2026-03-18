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
    LIMIT 4
")->fetchAll();

$porCongregacao = $pdo->query("
    SELECT congregacao, COUNT(*) AS total
    FROM membros
    WHERE congregacao IS NOT NULL AND congregacao <> ''
    GROUP BY congregacao
    ORDER BY total DESC
    LIMIT 4
")->fetchAll();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Igreja de Deus Nascer de Novo - Dashboard</title>

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/css.css">
</head>

<body>
    <div class="dashboard">
        <div class="header">
            <div class="logo-area">
                
                <div class="logo-text">
                    <h1>Igreja de Deus Nascer de Novo.</h1>
                    <p>Administração eclesiástica</p>
                </div>
            </div>

            <div class="nav-menu">
                <a href="index.php" class="nav-item active">
                    <i class="fas fa-home"></i> Início
                </a>
                <a href="cadastrar.php" class="nav-item">
                    <i class="fas fa-user-plus"></i> Cadastrar
                </a>
                <a href="listar.php" class="nav-item">
                    <i class="fas fa-users"></i> Membros
                </a>
              
            </div>

            <div class="user-profile">
                <div class="notification-badge">
                    <i class="far fa-bell"></i>
                </div>
                <div class="avatar">
                    <div class="avatar-img">IG</div>
                    <span class="avatar-name">Secretaria</span>
                    <i class="fas fa-chevron-down" style="font-size: 0.8rem; color: #7c9bd4;"></i>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Membros ativos</span>
                    <div class="stat-icon"><i class="fas fa-user-friends"></i></div>
                </div>
                <div class="stat-value"><?= $total ?></div>
                <div class="stat-trend">
                    <span class="trend-up"><i class="fas fa-arrow-up"></i> Sistema</span>
                    <span>cadastros gerais</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Batizados</span>
                    <div class="stat-icon"><i class="fas fa-water"></i></div>
                </div>
                <div class="stat-value"><?= $totalBatismo ?></div>
                <div class="stat-trend">
                    <span class="trend-up"><i class="fas fa-arrow-up"></i> Entrada</span>
                    <span>por batismo</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Masculino</span>
                    <div class="stat-icon"><i class="fas fa-male"></i></div>
                </div>
                <div class="stat-value"><?= $totalMasc ?></div>
                <div class="stat-trend">
                    <span class="trend-up"><i class="fas fa-arrow-up"></i> Total</span>
                    <span>sexo masculino</span>
                </div>
            </div>

            <div class="stat-card">
                <div class="stat-header">
                    <span class="stat-title">Feminino</span>
                    <div class="stat-icon"><i class="fas fa-female"></i></div>
                </div>
                <div class="stat-value"><?= $totalFem ?></div>
                <div class="stat-trend">
                    <span class="trend-down"><i class="fas fa-circle"></i> Total</span>
                    <span>sexo feminino</span>
                </div>
            </div>
        </div>

        <div class="charts-section">

            <!-- ÚLTIMOS MEMBROS -->
            <div class="chart-card">

                <div class="chart-header">
                    <h3>Últimos membros cadastrados</h3>
                    <a href="listar.php" class="info-link">Ver todos</a>
                </div>

                <?php if (!$ultimos): ?>
                    <p class="text-muted">Nenhum membro cadastrado.</p>
                <?php else: ?>

                    <?php foreach ($ultimos as $m): ?>

                        <?php
                        $iniciais = '';
                        $partes = explode(' ', trim($m['nome_completo']));
                        foreach (array_slice($partes, 0, 2) as $p) {
                            $iniciais .= strtoupper(substr($p, 0, 1));
                        }
                        ?>

                        <div class="member-item">

                            <div class="member-avatar">
                                <?= $iniciais ?>
                            </div>

                            <div class="member-info">
                                <h4><?= htmlspecialchars($m['nome_completo']) ?></h4>
                                <p><?= htmlspecialchars($m['congregacao'] ?? 'Sem congregação') ?></p>
                            </div>

                            <div class="member-status">
                                <?= date('d/m/Y', strtotime($m['criado_em'])) ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>


            <!-- CONGREGAÇÕES -->
            <div class="chart-card">

                <div class="chart-header">
                    <h3>Congregações com mais membros</h3>
                </div>

                <?php if (!$porCongregacao): ?>
                    <p class="text-muted">Sem dados cadastrados.</p>
                <?php else: ?>

                    <?php foreach ($porCongregacao as $c): ?>

                        <div class="activity-item">

                            <div class="activity-icon">
                                <i class="fas fa-church"></i>
                            </div>

                            <div class="activity-details">
                                <div class="activity-title">
                                    <?= htmlspecialchars($c['congregacao']) ?>
                                </div>

                                <div class="activity-time">
                                    <?= $c['total'] ?> membros cadastrados
                                </div>
                            </div>

                            <div class="activity-badge">
                                <?= $c['total'] ?>
                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php endif; ?>

            </div>

        </div>

        <div class="bottom-grid">
            <div class="info-card">
                <div class="info-header">
                    <h3>⛪ Congregações com mais membros</h3>
                    <a href="listar.php" class="info-link">Ver todos</a>
                </div>

                <?php if (!$porCongregacao): ?>
                    <div class="empty-box">
                        <p>Nenhuma congregação cadastrada ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($porCongregacao as $item): ?>
                        <div class="event-item">
                            <div class="event-date">
                                <div class="event-day"><?= (int)$item['total'] ?></div>
                                <div class="event-month">memb.</div>
                            </div>
                            <div class="event-info">
                                <h4><?= htmlspecialchars($item['congregacao']) ?></h4>
                                <p><i class="fas fa-users" style="margin-right: 5px;"></i>Total de cadastrados nessa congregação</p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <div class="info-card">
                <div class="info-header">
                    <h3>👥 Novos membros</h3>
                    <a href="listar.php" class="info-link">Ver todos</a>
                </div>

                <?php if (!$ultimos): ?>
                    <div class="empty-box">
                        <p>Nenhum membro cadastrado ainda.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($ultimos as $m): ?>
                        <?php
                        $iniciais = '';
                        $partes = explode(' ', trim((string)$m['nome_completo']));
                        foreach (array_slice($partes, 0, 2) as $parte) {
                            $iniciais .= strtoupper(mb_substr($parte, 0, 1, 'UTF-8'));
                        }

                        $status = !empty($m['tipo_ingresso']) ? $m['tipo_ingresso'] : 'Novo';
                        ?>
                        <div class="member-item">
                            <div class="member-avatar"><?= $iniciais ?: 'M' ?></div>
                            <div class="member-info">
                                <h4><?= htmlspecialchars($m['nome_completo']) ?></h4>
                                <p>Entrou em <?= date('d/m/Y', strtotime($m['criado_em'])) ?></p>
                            </div>
                            <div class="member-status"><?= htmlspecialchars($status) ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="footer">
            <span>© <?= date('Y') ?> Igreja Vida Nova - Sistema de Gestão Eclesiástica</span>
            <div class="footer-links">
                <span>Suporte</span>
                <span>Privacidade</span>
                <span>Termos</span>
            </div>
        </div>
    </div>
</body>

</html>