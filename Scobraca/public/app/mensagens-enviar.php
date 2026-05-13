<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_tenant_user();

$pageTitle = 'Enviar mensagem';
$pageDescription = 'Prepare mensagens de cobrança e relacionamento por WhatsApp.';
$empresaId = current_empresa_id();

$stmt = db()->prepare("SELECT id, nome, telefone FROM clientes WHERE empresa_id = :empresa_id AND telefone IS NOT NULL ORDER BY nome ASC");
$stmt->execute([':empresa_id' => $empresaId]);
$clientes = $stmt->fetchAll();
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

        <section class="support-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Nova mensagem</h2>
                        <p class="muted">Componha uma mensagem para cliente ou contato avulso.</p>
                    </div>
                    <a class="btn" href="<?= e(public_url('/app/mensagens.php')) ?>">Voltar para listagem</a>
                </div>
                <form class="form-stack" method="post" action="<?= e(public_url('/actions/app/enviar_mensagem.php')) ?>" data-whatsapp-message-form>
                    <?= csrf_field() ?>
                    <label>Cliente
                        <select name="cliente_id" data-whatsapp-client>
                            <option value="">Contato avulso</option>
                            <?php foreach ($clientes as $cliente): ?>
                                <option value="<?= (int) $cliente['id'] ?>" data-telefone="<?= e($cliente['telefone']) ?>"><?= e($cliente['nome']) ?> · <?= e($cliente['telefone']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <label>Telefone<input name="telefone" data-whatsapp-phone placeholder="(00) 00000-0000"></label>
                    <label>Tipo
                        <select name="tipo">
                            <option value="lembrete">Lembrete de vencimento</option>
                            <option value="cobranca">Cobrança em atraso</option>
                            <option value="confirmacao">Confirmação de pagamento</option>
                            <option value="manual">Mensagem manual</option>
                        </select>
                    </label>
                    <label>Mensagem<textarea name="mensagem" rows="7" maxlength="2000">Olá, tudo bem? Passando para lembrar sobre sua mensalidade. Qualquer dúvida, estamos à disposição.</textarea></label>
                    <button type="submit" class="btn btn-primary">Enviar mensagem</button>
                </form>
            </article>

            <article class="card chat-panel">
                <div class="section-heading">
                    <div>
                        <h2>Modelos rápidos</h2>
                        <p class="muted">Templates para padronizar o atendimento.</p>
                    </div>
                    <span class="soft-label">WhatsApp</span>
                </div>
                <div class="insight-list">
                    <div class="insight-item"><span class="insight-dot"></span><div><strong>10 dias antes</strong><span>Olá, sua mensalidade vence em breve. O pagamento pode ser feito via PIX.</span></div></div>
                    <div class="insight-item"><span class="insight-dot yellow"></span><div><strong>No vencimento</strong><span>Hoje é o vencimento da sua mensalidade. Envie o comprovante por aqui.</span></div></div>
                    <div class="insight-item"><span class="insight-dot red"></span><div><strong>Após atraso</strong><span>Identificamos uma pendência. Regularize para evitar bloqueio do serviço.</span></div></div>
                    <div class="insight-item"><span class="insight-dot green"></span><div><strong>Pagamento confirmado</strong><span>Pagamento recebido. Obrigado por manter sua mensalidade em dia.</span></div></div>
                </div>
            </article>
        </section>
    </main>
</div>
</body>
</html>
