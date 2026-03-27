<?php
ob_start();
session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCP - Sistema de Controle de Processos (PGM)</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
</head>
<body>

<header class="navbar">
    <div class="logo">
        <i class="fas fa-balance-scale"></i>
        <span>SCP PGM</span>
    </div>
    <nav class="nav-links">
        <a href="index.php" class="nav-link active"><i class="fas fa-home"></i> Dashboard</a>
        <a href="cadastro.php" class="nav-link"><i class="fas fa-plus-circle"></i> Novo</a>
        <a href="prazos.php" class="nav-link"><i class="fas fa-clock"></i> Prazos</a>
        <a href="relatorios.php" class="nav-link"><i class="fas fa-chart-line"></i> Relatórios</a>
        <?php if ($_SESSION['usuario_perfil'] === 'ADMIN'): ?>
        <a href="usuarios.php" class="nav-link"><i class="fas fa-users"></i> Usuários</a>
        <a href="configuracoes.php" class="nav-link"><i class="fas fa-cog"></i></a>
        <?php endif; ?>
    </nav>
    <div style="display: flex; align-items: center; gap: 1rem;">
        <div id="nome-analisador" style="font-weight: 600; font-size: 0.9rem; color: var(--text-main);">
            <i class="fas fa-user-circle" style="color: var(--primary); margin-right: 5px;"></i>
            <?php echo $_SESSION['usuario_nome']; ?>
        </div>
        <a href="api.php?acao=logout" class="btn-quick" style="color: #f87171; border:none;" title="Sair">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</header>

<main class="main-content">
    <header class="header">
        <div class="title-group">
            <h1>Controle de Processos</h1>
            <p>Gerenciamento dinâmico e automatizado de prazos judiciais.</p>
        </div>
    </header>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="label">Total de Processos</div>
            <div class="value" id="total-processos">0</div>
        </div>
        <div class="stat-card">
            <div class="label">Pendentes</div>
            <div class="value" id="total-pendentes" style="color: var(--status-pendente)">0</div>
        </div>
        <div class="stat-card">
            <div class="label">Protocolados</div>
            <div class="value" id="total-protocolados" style="color: var(--status-protocolado)">0</div>
        </div>
        <div class="stat-card">
            <div class="label">Prazos para Hoje</div>
            <div class="value" id="total-hoje">0</div>
        </div>
    </div>

    <section id="section-urgente" class="data-section" style="display: none; border-left: 5px solid #ef4444; margin-bottom: 2rem; background: rgba(239, 68, 68, 0.05);">
        <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 1.5rem;">
            <i class="fas fa-exclamation-circle" style="color: #ef4444; font-size: 1.5rem;"></i>
            <h2 style="font-size: 1.25rem; color: #b91c1c;">⚠️ Atenção Prioritária</h2>
        </div>
        <div style="overflow-x: auto;">
            <table class="table-urgente">
                <thead>
                    <tr style="background: rgba(239, 68, 68, 0.1);">
                        <th>Nº PROCESSO</th>
                        <th>ATO / NATUREZA</th>
                        <th>PRAZO FINAL</th>
                        <th>DIAS RESTANTES</th>
                        <th>ANALISADOR</th>
                        <th>AÇÕES</th>
                    </tr>
                </thead>
                <tbody id="lista-prioridade">
                    <!-- Preenchido via JS -->
                </tbody>
            </table>
        </div>
    </section>

    <section class="data-section">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.5rem;">
            <h2 style="font-size: 1.25rem;">Lista de Processos</h2>
            <div style="display: flex; gap: 1rem; align-items: center;">
                <div class="filter-toggle" id="filtro-meus-prazos">
                    <i class="fas fa-user-check"></i> Meus Prazos
                </div>
                <input type="text" id="filtro-busca" placeholder="Pequisar nº ou tipo..." style="width: 280px; padding: 0.5rem 1rem; height: 38px;">
                <a href="cadastro.php" class="btn btn-primary" style="padding: 0.5rem 1rem; height: 38px;"><i class="fas fa-plus"></i> Novo</a>
            </div>
        </div>

        <div style="overflow-x: auto;">
            <table id="tabela-processos">
                <thead>
                    <tr>
                        <th>Nº PROCESSO</th>
                        <th>ATO / NATUREZA</th>
                        <th>PRAZO FINAL</th>
                        <th>ANALISADOR</th>
                        <th>STATUS</th>
                        <th>AÇÕES</th>
                    </tr>
                </thead>
                <tbody id="lista-processos">
                    <!-- Preenchido via JS -->
                </tbody>
            </table>
        </div>
        <div id="paginacao-processos" class="pagination" style="margin-top: 1.5rem;"></div>
    </section>
</main>

<script src="assets/js/script.js"></script>
</body>
</html>
