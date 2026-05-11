<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Suporte e chat';
$pageDescription = 'Atendimento dos chamados abertos pelas empresas.';
$suporteDisponivel = suporte_ensure_tables();
$metricas = ['total' => 0, 'em_andamento' => 0, 'aguardando' => 0, 'criticos' => 0];
$chamados = [];
$chamadoSelecionado = null;
$mensagens = [];

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

    $requestedId = (int) ($_GET['chamado_id'] ?? 0);

    if ($requestedId > 0) {
        $stmt = db()->prepare(
            "SELECT sc.*, e.nome AS empresa_nome, e.email AS empresa_email, u.nome AS usuario_nome
             FROM suporte_chamados sc
             INNER JOIN empresas e ON e.id = sc.empresa_id
             LEFT JOIN usuarios u ON u.id = sc.usuario_id
             WHERE sc.id = :id
             LIMIT 1"
        );
        $stmt->execute([':id' => $requestedId]);
        $chamadoSelecionado = $stmt->fetch() ?: null;
    }

    if (!$chamadoSelecionado && $chamados) {
        $chamadoSelecionado = $chamados[0];
    }

    if ($chamadoSelecionado) {
        $stmt = db()->prepare(
            "SELECT *
             FROM suporte_mensagens
             WHERE chamado_id = :chamado_id
             ORDER BY criado_em ASC, id ASC"
        );
        $stmt->execute([':chamado_id' => (int) $chamadoSelecionado['id']]);
        $mensagens = $stmt->fetchAll();
    }
}

$statusOptions = [
    'aberto' => 'Aberto',
    'em_atendimento' => 'Em atendimento',
    'aguardando_empresa' => 'Aguardando empresa',
    'resolvido' => 'Resolvido',
    'fechado' => 'Fechado',
];
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

        <section class="support-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Fila de suporte</h2>
                        <p class="muted">Chamados enviados pelas empresas e usuários do painel.</p>
                    </div>
                    <span class="soft-label warning">Atendimento</span>
                </div>

                <div class="ticket-list">
                    <?php foreach ($chamados as $chamado): ?>
                        <?php $isActive = $chamadoSelecionado && (int) $chamadoSelecionado['id'] === (int) $chamado['id']; ?>
                        <a class="ticket-item<?= $isActive ? ' is-active' : '' ?>" href="<?= e(public_url('/admin/suporte.php?chamado_id=' . (int) $chamado['id'])) ?>">
                            <div>
                                <strong>#<?= (int) $chamado['id'] ?> · <?= e($chamado['empresa_nome']) ?></strong>
                                <span><?= e($chamado['assunto']) ?></span>
                                <span><?= e(suporte_categoria_label((string) $chamado['categoria'])) ?> · <?= e((string) ($chamado['total_mensagens'] ?? 0)) ?> mensagens</span>
                            </div>
                            <div class="ticket-meta">
                                <span class="badge <?= e(suporte_status_badge((string) $chamado['status'])) ?>"><?= e(suporte_status_label((string) $chamado['status'])) ?></span>
                                <small><?= e(suporte_prioridade_label((string) $chamado['prioridade'])) ?> · <?= e(data_hora_br($chamado['ultima_mensagem'] ?? $chamado['criado_em'])) ?></small>
                            </div>
                        </a>
                    <?php endforeach; ?>
                    <?php if (!$chamados): ?>
                        <div class="empty-state">Nenhum chamado de suporte registrado.</div>
                    <?php endif; ?>
                </div>
            </article>

            <article class="card chat-panel">
                <div class="section-heading">
                    <div>
                        <h2><?= $chamadoSelecionado ? e((string) $chamadoSelecionado['assunto']) : 'Atendimento' ?></h2>
                        <p class="muted">
                            <?php if ($chamadoSelecionado): ?>
                                <?= e((string) $chamadoSelecionado['empresa_nome']) ?> · chamado #<?= (int) $chamadoSelecionado['id'] ?>
                            <?php else: ?>
                                Selecione um chamado para responder.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($chamadoSelecionado): ?>
                        <span class="badge <?= e(suporte_status_badge((string) $chamadoSelecionado['status'])) ?>"><?= e(suporte_status_label((string) $chamadoSelecionado['status'])) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($chamadoSelecionado): ?>
                    <div class="support-meta">
                        <span>Usuário: <strong><?= e((string) ($chamadoSelecionado['usuario_nome'] ?? 'Não informado')) ?></strong></span>
                        <span>E-mail empresa: <strong><?= e((string) ($chamadoSelecionado['empresa_email'] ?? '-')) ?></strong></span>
                        <span>Prioridade: <strong><?= e(suporte_prioridade_label((string) $chamadoSelecionado['prioridade'])) ?></strong></span>
                        <span>Categoria: <strong><?= e(suporte_categoria_label((string) $chamadoSelecionado['categoria'])) ?></strong></span>
                    </div>

                    <div class="chat-thread">
                        <?php foreach ($mensagens as $mensagem): ?>
                            <div class="chat-message <?= $mensagem['autor_tipo'] === 'admin' ? 'from-admin' : 'from-company' ?>">
                                <strong><?= e($mensagem['autor_nome']) ?> <small><?= e(data_hora_br($mensagem['criado_em'])) ?></small></strong>
                                <p><?= nl2br(e($mensagem['mensagem'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                        <?php if (!$mensagens): ?>
                            <div class="empty-state">Nenhuma mensagem registrada neste chamado.</div>
                        <?php endif; ?>
                    </div>

                    <form class="support-admin-form" action="<?= e(public_url('/actions/admin/responder_suporte_chamado.php')) ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="chamado_id" value="<?= (int) $chamadoSelecionado['id'] ?>">
                        <label>Status
                            <select name="status" required>
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= (string) $chamadoSelecionado['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Resposta do suporte
                            <textarea name="mensagem" rows="4" maxlength="5000" placeholder="Escreva uma orientação para a empresa. Para apenas mudar o status, deixe em branco."></textarea>
                        </label>
                        <button type="submit" class="btn btn-primary">Atualizar atendimento</button>
                    </form>
                <?php else: ?>
                    <div class="empty-state">Nenhum chamado selecionado.</div>
                <?php endif; ?>
            </article>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
