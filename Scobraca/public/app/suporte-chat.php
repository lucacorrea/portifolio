<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Chat de suporte';
$pageDescription = 'Conversa com o suporte FluxPay.';
$empresaId = current_empresa_id();
$usuarioAtual = current_user() ?? [];
$suporteDisponivel = suporte_ensure_tables();
$chamadoId = (int) ($_GET['id'] ?? $_GET['chamado_id'] ?? 0);
$chamado = null;
$mensagens = [];

if (!$suporteDisponivel) {
    flash('error', 'O suporte ainda não está preparado no banco de dados.');
} elseif ($chamadoId <= 0) {
    flash('error', 'Selecione um chamado para abrir o chat.');
    redirect('/app/suporte.php');
} else {
    $stmt = db()->prepare(
        "SELECT sc.*, u.nome AS usuario_nome
         FROM suporte_chamados sc
         LEFT JOIN usuarios u ON u.id = sc.usuario_id
         WHERE sc.id = :id AND sc.empresa_id = :empresa_id
         LIMIT 1"
    );
    $stmt->execute([
        ':id' => $chamadoId,
        ':empresa_id' => $empresaId,
    ]);
    $chamado = $stmt->fetch() ?: null;

    if (!$chamado) {
        flash('error', 'Chamado não encontrado para esta empresa.');
        redirect('/app/suporte.php');
    }

    $stmt = db()->prepare(
        "SELECT *
         FROM suporte_mensagens
         WHERE chamado_id = :chamado_id AND empresa_id = :empresa_id
         ORDER BY criado_em ASC, id ASC"
    );
    $stmt->execute([
        ':chamado_id' => $chamadoId,
        ':empresa_id' => $empresaId,
    ]);
    $mensagens = $stmt->fetchAll();

    $pageTitle = 'Chamado #' . $chamadoId;
    $pageDescription = (string) $chamado['assunto'];
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
    <?php require APP_PATH . '/Includes/tenant_sidebar.php'; ?>
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
                    <a class="btn btn-secondary support-back-link" href="<?= e(public_url('/app/suporte.php')) ?>">Voltar aos chamados</a>

                    <div class="support-contact-card">
                        <div class="support-avatar">FP</div>
                        <div>
                            <strong>Suporte FluxPay</strong>
                            <span>Atendimento da plataforma</span>
                        </div>
                    </div>

                    <div class="support-side-section">
                        <span class="card-title">Detalhes do chamado</span>
                        <div class="support-detail-list">
                            <span>Status <strong><?= e(suporte_status_label((string) $chamado['status'])) ?></strong></span>
                            <span>Prioridade <strong><?= e(suporte_prioridade_label((string) $chamado['prioridade'])) ?></strong></span>
                            <span>Categoria <strong><?= e(suporte_categoria_label((string) $chamado['categoria'])) ?></strong></span>
                            <span>Aberto em <strong><?= e(data_hora_br($chamado['criado_em'])) ?></strong></span>
                        </div>
                    </div>

                    <div class="support-side-note">
                        <strong>Para acelerar o retorno</strong>
                        <span>Envie prints, nomes de clientes, cobrança ou pagamento relacionado quando fizer sentido.</span>
                    </div>
                </aside>

                <article class="support-chat-window for-tenant card">
                    <header class="support-chat-header">
                        <div>
                            <span class="soft-label success">Chamado #<?= (int) $chamado['id'] ?></span>
                            <h2><?= e((string) $chamado['assunto']) ?></h2>
                            <p><?= e(suporte_status_label((string) $chamado['status'])) ?> · <?= count($mensagens) ?> mensagens</p>
                        </div>
                        <span class="badge <?= e(suporte_prioridade_badge((string) $chamado['prioridade'])) ?>"><?= e(suporte_prioridade_label((string) $chamado['prioridade'])) ?></span>
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

                    <?php if (!in_array((string) $chamado['status'], ['resolvido', 'fechado'], true)): ?>
                        <form class="support-chat-composer" action="<?= e(public_url('/actions/app/responder_suporte_chamado.php')) ?>" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="chamado_id" value="<?= (int) $chamado['id'] ?>">
                            <label class="sr-only" for="mensagem_suporte">Mensagem para o suporte</label>
                            <textarea id="mensagem_suporte" name="mensagem" rows="3" required maxlength="5000" placeholder="Escreva sua mensagem..."></textarea>
                            <button type="submit" class="btn btn-primary">Enviar mensagem</button>
                        </form>
                    <?php else: ?>
                        <div class="support-closed-state">Chamado encerrado. Abra uma nova solicitação se precisar continuar.</div>
                    <?php endif; ?>
                </article>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
