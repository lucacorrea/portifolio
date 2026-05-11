<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Suporte';
$pageDescription = 'Fila de chamados das empresas.';
$suporteDisponivel = suporte_ensure_tables();
$metricas = ['total' => 0, 'em_andamento' => 0, 'aguardando' => 0, 'criticos' => 0];
$chamados = [];

if ((int) ($_GET['chamado_id'] ?? 0) > 0) {
    redirect('/admin/suporte-chat.php?id=' . (int) $_GET['chamado_id']);
}

if ($suporteDisponivel) {
    $stmt = db()->query(
        "SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status IN ('aberto', 'em_atendimento') THEN 1 ELSE 0 END) AS em_andamento,
            SUM(CASE WHEN status = 'aguardando_empresa' THEN 1 ELSE 0 END) AS aguardando,
            SUM(CASE WHEN prioridade IN ('alta', 'urgente') AND status NOT IN ('resolvido', 'fechado') THEN 1 ELSE 0 END) AS criticos
         FROM suporte_chamados"
    );
    $metricas = $stmt->fetch() ?: $metricas;

    $stmt = db()->query(
        "SELECT sc.*,
                e.nome AS empresa_nome,
                e.email AS empresa_email,
                u.nome AS usuario_nome,
                (SELECT COUNT(*) FROM suporte_mensagens sm WHERE sm.chamado_id = sc.id) AS total_mensagens,
                (SELECT MAX(sm.criado_em) FROM suporte_mensagens sm WHERE sm.chamado_id = sc.id) AS ultima_mensagem
         FROM suporte_chamados sc
         INNER JOIN empresas e ON e.id = sc.empresa_id
         LEFT JOIN usuarios u ON u.id = sc.usuario_id
         ORDER BY FIELD(sc.status, 'aberto', 'em_atendimento', 'aguardando_empresa', 'resolvido', 'fechado'),
                  FIELD(sc.prioridade, 'urgente', 'alta', 'media', 'baixa'),
                  COALESCE((SELECT MAX(sm2.criado_em) FROM suporte_mensagens sm2 WHERE sm2.chamado_id = sc.id), sc.criado_em) DESC,
                  sc.id DESC"
    );
    $chamados = $stmt->fetchAll();
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
</head>
<body>
<div class="layout">
    <?php require APP_PATH . '/Includes/admin_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <?php if (!$suporteDisponivel): ?>
            <section class="card">
                <div class="section-heading">
                    <div>
                        <h2>Suporte em configuração</h2>
                        <p class="muted">As tabelas de suporte ainda não estão disponíveis no banco de dados.</p>
                    </div>
                    <span class="soft-label warning">Banco de dados</span>
                </div>
                <div class="empty-state">Aplique a migration <code>database/migrations/2026_05_11_suporte_chamados.sql</code> ou confirme se o usuário do banco tem permissão para criar tabelas.</div>
            </section>
        <?php else: ?>
            <section class="support-hero-card card admin-support-hero">
                <div>
                    <span class="soft-label warning">Fila de atendimento</span>
                    <h2>Resolva chamados com contexto completo</h2>
                    <p>Priorize solicitações críticas, abra conversas em tela dedicada e mantenha o status claro para a empresa.</p>
                </div>
                <div class="support-hero-metrics">
                    <span><strong><?= (int) ($metricas['em_andamento'] ?? 0) ?></strong> em andamento</span>
                    <span><strong><?= (int) ($metricas['criticos'] ?? 0) ?></strong> prioridade alta</span>
                </div>
            </section>

            <section class="grid four support-status-grid">
                <article class="card metric accent-blue">
                    <span>Total de chamados</span>
                    <strong><?= (int) ($metricas['total'] ?? 0) ?></strong>
                    <small>Histórico registrado</small>
                </article>
                <article class="card metric accent-yellow">
                    <span>Em andamento</span>
                    <strong><?= (int) ($metricas['em_andamento'] ?? 0) ?></strong>
                    <small>Abertos ou em atendimento</small>
                </article>
                <article class="card metric accent-green">
                    <span>Aguardando empresa</span>
                    <strong><?= (int) ($metricas['aguardando'] ?? 0) ?></strong>
                    <small>Dependem de retorno</small>
                </article>
                <article class="card metric accent-red">
                    <span>Prioridade alta</span>
                    <strong><?= (int) ($metricas['criticos'] ?? 0) ?></strong>
                    <small>Alta ou urgente</small>
                </article>
            </section>

            <section class="card support-queue-card">
                <div class="section-heading">
                    <div>
                        <h2>Fila de chamados</h2>
                        <p class="muted">Cada chamado abre uma tela de chat separada para atendimento.</p>
                    </div>
                    <span class="soft-label success"><?= count($chamados) ?> chamados</span>
                </div>

                <div class="ticket-list support-ticket-list">
                    <?php foreach ($chamados as $chamado): ?>
                        <a class="ticket-item support-ticket-card admin-ticket-card" href="<?= e(public_url('/admin/suporte-chat.php?id=' . (int) $chamado['id'])) ?>">
                            <div class="support-ticket-main">
                                <strong>#<?= (int) $chamado['id'] ?> · <?= e($chamado['empresa_nome']) ?></strong>
                                <span><?= e($chamado['assunto']) ?></span>
                                <span><?= e((string) ($chamado['usuario_nome'] ?? 'Usuário não informado')) ?> · <?= e((string) ($chamado['empresa_email'] ?? '-')) ?></span>
                            </div>
                            <div class="ticket-meta">
                                <span class="badge <?= e(suporte_status_badge((string) $chamado['status'])) ?>"><?= e(suporte_status_label((string) $chamado['status'])) ?></span>
                                <small><?= e(suporte_prioridade_label((string) $chamado['prioridade'])) ?> · <?= e((string) ($chamado['total_mensagens'] ?? 0)) ?> mensagens</small>
                                <small><?= e(data_hora_br($chamado['ultima_mensagem'] ?? $chamado['criado_em'])) ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$chamados): ?>
                        <div class="empty-state">Nenhum chamado de suporte registrado.</div>
                    <?php endif; ?>
                </div>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
