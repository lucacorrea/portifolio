<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Mensagens';
$pageDescription = 'Envios de WhatsApp e comunicação com clientes.';
$empresaId = current_empresa_id();

$stmt = db()->prepare(
    "SELECT w.*, c.nome AS cliente
     FROM whatsapp_envios w
     LEFT JOIN clientes c ON c.id = w.cliente_id
     WHERE w.empresa_id = :empresa_id
     ORDER BY w.criado_em DESC, w.id DESC"
);
$stmt->execute([':empresa_id' => $empresaId]);
$mensagens = $stmt->fetchAll();
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

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Mensagens enviadas</h2>
                    <p class="muted">Histórico de lembretes, cobranças e notificações para clientes.</p>
                </div>
                <a class="btn btn-primary" href="<?= e(public_url('/app/mensagens-enviar.php')) ?>">Nova mensagem</a>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr><th>Cliente</th><th>Telefone</th><th>Tipo</th><th>Status</th><th>Mensagem</th><th>Data</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($mensagens as $mensagem): ?>
                        <tr>
                            <td><strong><?= e($mensagem['cliente'] ?? 'Contato avulso') ?></strong></td>
                            <td><?= e($mensagem['telefone']) ?></td>
                            <td><?= e($mensagem['tipo'] ?? '-') ?></td>
                            <td><span class="badge <?= $mensagem['status_envio'] === 'enviado' ? 'ativa' : ($mensagem['status_envio'] === 'falhou' ? 'vencida' : 'pendente') ?>"><?= e($mensagem['status_envio']) ?></span></td>
                            <?php $textoMensagem = (string) $mensagem['mensagem']; ?>
                            <td><?= e(strlen($textoMensagem) > 90 ? substr($textoMensagem, 0, 90) . '...' : $textoMensagem) ?></td>
                            <td><?= e(data_br($mensagem['criado_em'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$mensagens): ?>
                        <tr><td colspan="6">Nenhuma mensagem registrada.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
