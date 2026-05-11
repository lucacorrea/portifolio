<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Suporte';
$pageDescription = 'Abra chamados e acompanhe as respostas do suporte FluxPay.';
$empresaId = current_empresa_id();
$usuarioAtual = current_user() ?? [];
$suporteDisponivel = suporte_ensure_tables();
$chamados = [];
$chamadoSelecionado = null;
$mensagens = [];

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

    $requestedId = (int) ($_GET['chamado_id'] ?? 0);

    if ($requestedId > 0) {
        $stmt = db()->prepare(
            "SELECT sc.*, u.nome AS usuario_nome
             FROM suporte_chamados sc
             LEFT JOIN usuarios u ON u.id = sc.usuario_id
             WHERE sc.id = :id AND sc.empresa_id = :empresa_id
             LIMIT 1"
        );
        $stmt->execute([
            ':id' => $requestedId,
            ':empresa_id' => $empresaId,
        ]);
        $chamadoSelecionado = $stmt->fetch() ?: null;
    }

    if (!$chamadoSelecionado && $chamados) {
        $chamadoSelecionado = $chamados[0];
    }

    if ($chamadoSelecionado) {
        $stmt = db()->prepare(
            "SELECT *
             FROM suporte_mensagens
             WHERE chamado_id = :chamado_id AND empresa_id = :empresa_id
             ORDER BY criado_em ASC, id ASC"
        );
        $stmt->execute([
            ':chamado_id' => (int) $chamadoSelecionado['id'],
            ':empresa_id' => $empresaId,
        ]);
        $mensagens = $stmt->fetchAll();
    }
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
        <section class="support-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Meus chamados</h2>
                        <p class="muted">Acompanhe solicitações abertas pela sua empresa.</p>
                    </div>
                    <span class="soft-label success"><?= count($chamados) ?> chamados</span>
                </div>

                <div class="ticket-list">
                    <?php foreach ($chamados as $chamado): ?>
                        <?php $isActive = $chamadoSelecionado && (int) $chamadoSelecionado['id'] === (int) $chamado['id']; ?>
                        <a class="ticket-item<?= $isActive ? ' is-active' : '' ?>" href="<?= e(public_url('/app/suporte.php?chamado_id=' . (int) $chamado['id'])) ?>">
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
                        <div class="empty-state">Nenhum chamado aberto ainda.</div>
                    <?php endif; ?>
                </div>
            </article>

            <article class="card chat-panel">
                <div class="section-heading">
                    <div>
                        <h2><?= $chamadoSelecionado ? e((string) $chamadoSelecionado['assunto']) : 'Conversa do suporte' ?></h2>
                        <p class="muted">
                            <?php if ($chamadoSelecionado): ?>
                                Chamado #<?= (int) $chamadoSelecionado['id'] ?> · <?= e(suporte_status_label((string) $chamadoSelecionado['status'])) ?>
                            <?php else: ?>
                                Selecione um chamado ou abra uma nova solicitação.
                            <?php endif; ?>
                        </p>
                    </div>
                    <?php if ($chamadoSelecionado): ?>
                        <span class="badge <?= e(suporte_prioridade_badge((string) $chamadoSelecionado['prioridade'])) ?>"><?= e(suporte_prioridade_label((string) $chamadoSelecionado['prioridade'])) ?></span>
                    <?php endif; ?>
                </div>

                <?php if ($chamadoSelecionado): ?>
                    <div class="support-meta">
                        <span>Categoria: <strong><?= e(suporte_categoria_label((string) $chamadoSelecionado['categoria'])) ?></strong></span>
                        <span>Aberto por: <strong><?= e((string) ($chamadoSelecionado['usuario_nome'] ?? $usuarioAtual['nome'] ?? 'Usuário')) ?></strong></span>
                        <span>Criado em: <strong><?= e(data_hora_br($chamadoSelecionado['criado_em'])) ?></strong></span>
                    </div>

                    <div class="chat-thread">
                        <?php foreach ($mensagens as $mensagem): ?>
                            <div class="chat-message <?= $mensagem['autor_tipo'] === 'admin' ? 'from-admin' : 'from-company' ?>">
                                <strong><?= e($mensagem['autor_nome']) ?> <small><?= e(data_hora_br($mensagem['criado_em'])) ?></small></strong>
                                <p><?= nl2br(e($mensagem['mensagem'])) ?></p>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (!in_array((string) $chamadoSelecionado['status'], ['resolvido', 'fechado'], true)): ?>
                        <form class="chat-compose support-compose" action="<?= e(public_url('/actions/app/responder_suporte_chamado.php')) ?>" method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="chamado_id" value="<?= (int) $chamadoSelecionado['id'] ?>">
                            <label class="sr-only" for="mensagem_suporte">Responder atendimento</label>
                            <textarea id="mensagem_suporte" name="mensagem" rows="3" required maxlength="5000" placeholder="Escreva sua resposta para o suporte..."></textarea>
                            <button type="submit" class="btn btn-primary">Enviar</button>
                        </form>
                    <?php else: ?>
                        <div class="empty-state">Este chamado foi encerrado. Abra um novo chamado se precisar continuar o atendimento.</div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="empty-state">Nenhuma conversa selecionada.</div>
                <?php endif; ?>
            </article>
        </section>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Abrir novo chamado</h2>
                    <p class="muted">Descreva o que está acontecendo com clareza para acelerar o atendimento.</p>
                </div>
                <span class="soft-label warning">Suporte FluxPay</span>
            </div>
            <form class="form-grid support-form" action="<?= e(public_url('/actions/app/salvar_suporte_chamado.php')) ?>" method="post">
                <?= csrf_field() ?>
                <label>Assunto
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
                    <textarea name="mensagem" rows="5" maxlength="5000" required placeholder="Explique o problema, informe telas envolvidas, cliente, cobrança ou pagamento se fizer sentido."></textarea>
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary">Abrir chamado</button>
                </div>
            </form>
        </section>
        <?php endif; ?>
    </main>
</div>
</body>
</html>
