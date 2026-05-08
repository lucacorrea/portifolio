<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Suporte e chat';
$pageDescription = 'Central de atendimento para empresas locatárias.';

$tickets = [
    ['empresa' => 'Alfa Rastreamento', 'assunto' => 'Dúvida sobre cobrança automática', 'status' => 'Aberto', 'sla' => '18 min', 'prioridade' => 'Alta'],
    ['empresa' => 'Beta Serviços', 'assunto' => 'WhatsApp não enviou lembrete', 'status' => 'Em análise', 'sla' => '42 min', 'prioridade' => 'Média'],
    ['empresa' => 'Delta Soluções', 'assunto' => 'Solicitação de upgrade de plano', 'status' => 'Aguardando cliente', 'sla' => '2 h', 'prioridade' => 'Baixa'],
];
$mensagens = [
    ['origem' => 'cliente', 'nome' => 'Marina · Alfa Rastreamento', 'texto' => 'Bom dia, preciso confirmar se a cobrança de maio já foi enviada aos clientes.', 'hora' => '09:14'],
    ['origem' => 'admin', 'nome' => 'Suporte FluxPay', 'texto' => 'Bom dia, Marina. Vou verificar o lote de envio e retorno com o status em instantes.', 'hora' => '09:16'],
    ['origem' => 'cliente', 'nome' => 'Marina · Alfa Rastreamento', 'texto' => 'Perfeito. Se tiver falha, pode reenviar apenas para os pendentes?', 'hora' => '09:18'],
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

        <section class="support-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Fila de suporte</h2>
                        <p class="muted">Tickets das empresas locatárias e prioridade de resposta.</p>
                    </div>
                    <span class="soft-label warning">Chat</span>
                </div>
                <div class="ticket-list">
                    <?php foreach ($tickets as $ticket): ?>
                        <div class="ticket-item">
                            <div>
                                <strong><?= e($ticket['empresa']) ?></strong>
                                <span><?= e($ticket['assunto']) ?></span>
                            </div>
                            <div class="ticket-meta">
                                <span class="badge <?= $ticket['prioridade'] === 'Alta' ? 'vencida' : ($ticket['prioridade'] === 'Média' ? 'pendente' : 'ativa') ?>"><?= e($ticket['prioridade']) ?></span>
                                <small><?= e($ticket['status']) ?> · SLA <?= e($ticket['sla']) ?></small>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card chat-panel">
                <div class="section-heading">
                    <div>
                        <h2>Prévia do chat</h2>
                        <p class="muted">Estrutura visual para futura integração com mensagens em tempo real.</p>
                    </div>
                    <span class="soft-label success">Atendimento</span>
                </div>
                <div class="chat-thread">
                    <?php foreach ($mensagens as $mensagem): ?>
                        <div class="chat-message <?= $mensagem['origem'] === 'admin' ? 'from-admin' : '' ?>">
                            <strong><?= e($mensagem['nome']) ?> <small><?= e($mensagem['hora']) ?></small></strong>
                            <p><?= e($mensagem['texto']) ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
                <form class="chat-compose">
                    <input type="text" placeholder="Responder atendimento..." disabled>
                    <button type="button" class="btn btn-primary" disabled>Enviar</button>
                </form>
            </article>
        </section>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Próximas integrações</h2>
                    <p class="muted">Itens que deixam o suporte pronto para produção quando o backend for conectado.</p>
                </div>
            </div>
            <div class="insight-list">
                <div class="insight-item"><span class="insight-dot green"></span><div><strong>Histórico por empresa</strong><span>Relacionar tickets, mensagens e status de assinatura em uma única visão.</span></div></div>
                <div class="insight-item"><span class="insight-dot"></span><div><strong>Chat com persistência</strong><span>Criar tabelas de conversas e mensagens antes de ativar atendimento real.</span></div></div>
                <div class="insight-item"><span class="insight-dot yellow"></span><div><strong>Alertas de SLA</strong><span>Notificar administradores quando atendimento crítico passar do prazo.</span></div></div>
            </div>
        </section>
    </main>
</div>
</body>
</html>
