<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Chat de suporte';
$pageDescription = 'Atendimento detalhado do chamado.';
$suporteDisponivel = suporte_ensure_tables();
$chamadoId = (int) ($_GET['id'] ?? $_GET['chamado_id'] ?? 0);
$chamado = null;
$mensagens = [];

if (!$suporteDisponivel) {
    flash('error', 'O suporte ainda não está preparado no banco de dados.');
} elseif ($chamadoId <= 0) {
    flash('error', 'Selecione um chamado para abrir o chat.');
    redirect('/admin/suporte.php');
} else {
    $stmt = db()->prepare(
        "SELECT sc.*, e.nome AS empresa_nome, e.email AS empresa_email, e.telefone AS empresa_telefone, u.nome AS usuario_nome, u.email AS usuario_email
         FROM suporte_chamados sc
         INNER JOIN empresas e ON e.id = sc.empresa_id
         LEFT JOIN usuarios u ON u.id = sc.usuario_id
         WHERE sc.id = :id
         LIMIT 1"
    );
    $stmt->execute([':id' => $chamadoId]);
    $chamado = $stmt->fetch() ?: null;

    if (!$chamado) {
        flash('error', 'Chamado de suporte não encontrado.');
        redirect('/admin/suporte.php');
    }

    $stmt = db()->prepare(
        "SELECT *
         FROM suporte_mensagens
         WHERE chamado_id = :chamado_id
         ORDER BY criado_em ASC, id ASC"
    );
    $stmt->execute([':chamado_id' => $chamadoId]);
    $mensagens = $stmt->fetchAll();

    $pageTitle = 'Chamado #' . $chamadoId;
    $pageDescription = (string) $chamado['assunto'];
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
    <main class="content support-chat-page">
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
        <?php elseif ($chamado): ?>
            <section class="support-chat-shell">
                <aside class="support-chat-sidebar card">
                    <a class="btn btn-secondary support-back-link" href="<?= e(public_url('/admin/suporte.php')) ?>">Voltar à fila</a>

                    <div class="support-contact-card">
                        <div class="support-avatar"><?= e(strtoupper(substr((string) $chamado['empresa_nome'], 0, 2))) ?></div>
                        <div>
                            <strong><?= e((string) $chamado['empresa_nome']) ?></strong>
                            <span><?= e((string) ($chamado['usuario_nome'] ?? 'Usuário não informado')) ?></span>
                        </div>
                    </div>

                    <div class="support-side-section">
                        <span class="card-title">Dados da empresa</span>
                        <div class="support-detail-list">
                            <span>E-mail <strong><?= e((string) ($chamado['empresa_email'] ?? '-')) ?></strong></span>
                            <span>Telefone <strong><?= e((string) ($chamado['empresa_telefone'] ?? '-')) ?></strong></span>
                            <span>Usuário <strong><?= e((string) ($chamado['usuario_email'] ?? '-')) ?></strong></span>
                        </div>
                    </div>

                    <div class="support-side-section">
                        <span class="card-title">Classificação</span>
                        <div class="support-detail-list">
                            <span>Status <strong><?= e(suporte_status_label((string) $chamado['status'])) ?></strong></span>
                            <span>Prioridade <strong><?= e(suporte_prioridade_label((string) $chamado['prioridade'])) ?></strong></span>
                            <span>Categoria <strong><?= e(suporte_categoria_label((string) $chamado['categoria'])) ?></strong></span>
                            <span>Aberto em <strong><?= e(data_hora_br($chamado['criado_em'])) ?></strong></span>
                        </div>
                    </div>
                </aside>

                <article class="support-chat-window for-admin card">
                    <header class="support-chat-header">
                        <div>
                            <span class="soft-label warning">Atendimento #<?= (int) $chamado['id'] ?></span>
                            <h2><?= e((string) $chamado['assunto']) ?></h2>
                            <p><?= e((string) $chamado['empresa_nome']) ?> · <?= count($mensagens) ?> mensagens</p>
                        </div>
                        <span class="badge <?= e(suporte_status_badge((string) $chamado['status'])) ?>"><?= e(suporte_status_label((string) $chamado['status'])) ?></span>
                    </header>

                    <div class="support-chat-thread">
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

                    <form class="support-chat-composer admin-composer" action="<?= e(public_url('/actions/admin/responder_suporte_chamado.php')) ?>" method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="chamado_id" value="<?= (int) $chamado['id'] ?>">
                        <label>Status
                            <select name="status" required>
                                <?php foreach ($statusOptions as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= (string) $chamado['status'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Resposta
                            <textarea name="mensagem" rows="3" maxlength="5000" placeholder="Escreva a resposta para a empresa. Para apenas mudar o status, deixe em branco."></textarea>
                        </label>
                        <button type="submit" class="btn btn-primary">Atualizar</button>
                    </form>
                </article>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
