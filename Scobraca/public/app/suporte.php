<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Suporte';
$pageDescription = 'Abra chamados e acompanhe as respostas do suporte FluxPay.';
$empresaId = current_empresa_id();
$suporteDisponivel = suporte_ensure_tables();
$chamados = [];

if ((int) ($_GET['chamado_id'] ?? 0) > 0) {
    redirect('/app/suporte-chat.php?id=' . (int) $_GET['chamado_id']);
}

if ($suporteDisponivel) {
    $stmt = db()->prepare(
        "SELECT sc.*,
                u.nome AS usuario_nome,
                (SELECT COUNT(*) FROM suporte_mensagens sm WHERE sm.chamado_id = sc.id) AS total_mensagens,
                (SELECT MAX(sm.criado_em) FROM suporte_mensagens sm WHERE sm.chamado_id = sc.id) AS ultima_mensagem
         FROM suporte_chamados sc
         LEFT JOIN usuarios u ON u.id = sc.usuario_id
         WHERE sc.empresa_id = :empresa_id
         ORDER BY FIELD(sc.status, 'aberto', 'em_atendimento', 'aguardando_empresa', 'resolvido', 'fechado'),
                  FIELD(sc.prioridade, 'urgente', 'alta', 'media', 'baixa'),
                  COALESCE((SELECT MAX(sm2.criado_em) FROM suporte_mensagens sm2 WHERE sm2.chamado_id = sc.id), sc.criado_em) DESC,
                  sc.id DESC"
    );
    $stmt->execute([':empresa_id' => $empresaId]);
    $chamados = $stmt->fetchAll();
}

$categorias = [
    'financeiro' => 'Financeiro',
    'tecnico' => 'Técnico',
    'acesso' => 'Acesso',
    'automacao' => 'Automação',
    'outro' => 'Outro',
];

$prioridades = [
    'baixa' => 'Baixa',
    'media' => 'Média',
    'alta' => 'Alta',
    'urgente' => 'Urgente',
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
    <?php require APP_PATH . '/Includes/tenant_sidebar.php'; ?>
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
            <section class="support-hero-card card">
                <div>
                    <span class="soft-label success">Central de atendimento</span>
                    <h2>Fale com o suporte sem perder o histórico</h2>
                    <p>Abra uma solicitação, acompanhe cada resposta e mantenha tudo organizado por chamado.</p>
                </div>
                <div class="support-hero-metrics">
                    <span><strong><?= count($chamados) ?></strong> chamados</span>
                    <span><strong><?= count(array_filter($chamados, static fn (array $item): bool => in_array((string) $item['status'], ['aberto', 'em_atendimento'], true))) ?></strong> em andamento</span>
                </div>
            </section>

            <section class="support-center-grid">
                <article class="card support-queue-card">
                    <div class="section-heading">
                        <div>
                            <h2>Meus chamados</h2>
                            <p class="muted">Clique em um chamado para abrir a conversa em tela dedicada.</p>
                        </div>
                    </div>

                    <div class="ticket-list support-ticket-list">
                        <?php foreach ($chamados as $chamado): ?>
                            <a class="ticket-item support-ticket-card" href="<?= e(public_url('/app/suporte-chat.php?id=' . (int) $chamado['id'])) ?>">
                                <div>
                                    <strong>#<?= (int) $chamado['id'] ?> · <?= e($chamado['assunto']) ?></strong>
                                    <span><?= e(suporte_categoria_label((string) $chamado['categoria'])) ?> · <?= e((string) ($chamado['total_mensagens'] ?? 0)) ?> mensagens</span>
                                    <span>Última atualização: <?= e(data_hora_br($chamado['ultima_mensagem'] ?? $chamado['criado_em'])) ?></span>
                                </div>
                                <div class="ticket-meta">
                                    <span class="badge <?= e(suporte_status_badge((string) $chamado['status'])) ?>"><?= e(suporte_status_label((string) $chamado['status'])) ?></span>
                                    <small><?= e(suporte_prioridade_label((string) $chamado['prioridade'])) ?></small>
                                </div>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!$chamados): ?>
                            <div class="empty-state">Nenhum chamado aberto ainda. Use o formulário ao lado para iniciar seu primeiro atendimento.</div>
                        <?php endif; ?>
                    </div>
                </article>

                <article class="card support-new-ticket-card">
                    <div class="section-heading">
                        <div>
                            <h2>Novo chamado</h2>
                            <p class="muted">Explique o contexto para acelerar o diagnóstico.</p>
                        </div>
                        <span class="soft-label warning">Suporte FluxPay</span>
                    </div>
                    <form class="form-grid support-form" action="<?= e(public_url('/actions/app/salvar_suporte_chamado.php')) ?>" method="post">
                        <?= csrf_field() ?>
                        <label class="span-full">Assunto
                            <input name="assunto" maxlength="160" required placeholder="Ex: dúvida sobre uma cobrança recorrente">
                        </label>
                        <label>Categoria
                            <select name="categoria" required>
                                <?php foreach ($categorias as $value => $label): ?>
                                    <option value="<?= e($value) ?>"><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label>Prioridade
                            <select name="prioridade" required>
                                <?php foreach ($prioridades as $value => $label): ?>
                                    <option value="<?= e($value) ?>" <?= $value === 'media' ? 'selected' : '' ?>><?= e($label) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label class="span-full">Mensagem
                            <textarea name="mensagem" rows="7" maxlength="5000" required placeholder="Informe o que aconteceu, cliente/cobrança relacionada e o que você espera resolver."></textarea>
                        </label>
                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">Abrir chamado</button>
                        </div>
                    </form>
                </article>
            </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
