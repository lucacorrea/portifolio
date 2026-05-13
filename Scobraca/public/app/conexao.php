<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once APP_PATH . '/Services/WhatsAppService.php';

require_tenant_user();

$pageTitle = 'Conexão';
$pageDescription = 'Conecte o WhatsApp da empresa para cobranças automáticas.';
$empresaId = current_empresa_id();

if (!$empresaId) {
    http_response_code(403);
    exit('Empresa não identificada.');
}

$conexaoPagePath = '/app/conexao.php';
$conexaoActionUrl = public_url($conexaoPagePath);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf();

    $action = (string) ($_POST['acao'] ?? '');
    $connection = whatsapp_get_connection($empresaId);
    $instanceName = whatsapp_sanitize_instance_name((string) ($_POST['instancia_nome'] ?? ($connection['instancia_nome'] ?? '')), $empresaId);
    $phone = (string) ($_POST['telefone_conectado'] ?? ($connection['telefone_conectado'] ?? ''));

    try {
        if ($action === 'salvar') {
            whatsapp_update_connection($empresaId, [
                'instancia_nome' => $instanceName,
                'telefone_conectado' => whatsapp_normalize_phone($phone) ?: null,
                'ultima_sincronizacao' => date('Y-m-d H:i:s'),
            ]);

            flash('success', 'Dados da conexão atualizados.');
            redirect($conexaoPagePath);
        }

        if ($action === 'gerar_qr') {
            $result = whatsapp_connect_instance($empresaId, $instanceName, $phone);
            flash($result['ok'] ? 'success' : 'error', $result['message']);
            redirect($conexaoPagePath);
        }

        if ($action === 'atualizar_status') {
            $result = whatsapp_refresh_connection($empresaId);
            flash($result['ok'] ? 'success' : 'error', $result['message']);
            redirect($conexaoPagePath);
        }

        if ($action === 'desconectar') {
            $result = whatsapp_disconnect_instance($empresaId);
            flash($result['ok'] ? 'success' : 'error', $result['message']);
            redirect($conexaoPagePath);
        }

        if ($action === 'processar_cobrancas') {
            $summary = whatsapp_processar_cobrancas_empresa($empresaId);
            flash(
                'success',
                'Processamento concluído: '
                . (int) $summary['enviadas'] . ' enviadas, '
                . (int) $summary['falhas'] . ' falhas, '
                . (int) $summary['duplicadas'] . ' duplicadas ignoradas e '
                . (int) $summary['sem_telefone'] . ' sem telefone válido.'
            );
            redirect($conexaoPagePath);
        }
    } catch (Throwable $e) {
        error_log('[WHATSAPP CONEXAO PAGE] ' . $e->getMessage());
        flash('error', 'Não foi possível processar a conexão do WhatsApp. Verifique a configuração e tente novamente.');
        redirect($conexaoPagePath);
    }

    flash('error', 'Ação inválida para conexão do WhatsApp.');
    redirect($conexaoPagePath);
}

$connection = whatsapp_get_connection($empresaId);
$integrationConfigured = whatsapp_integration_configured();
$providerLabel = whatsapp_provider() === 'bridge' ? 'Bridge Baileys' : 'Evolution API';

$stmt = db()->prepare(
    "SELECT
        COUNT(*) AS total,
        SUM(status_envio = 'enviado') AS enviados,
        SUM(status_envio = 'falhou') AS falhas,
        SUM(status_envio = 'pendente') AS pendentes
     FROM whatsapp_envios
     WHERE empresa_id = :empresa_id"
);
$stmt->execute([':empresa_id' => $empresaId]);
$stats = $stmt->fetch() ?: ['total' => 0, 'enviados' => 0, 'falhas' => 0, 'pendentes' => 0];

$stmt = db()->prepare(
    "SELECT w.*, c.nome AS cliente
     FROM whatsapp_envios w
     LEFT JOIN clientes c ON c.id = w.cliente_id
     WHERE w.empresa_id = :empresa_id
     ORDER BY w.criado_em DESC, w.id DESC
     LIMIT 5"
);
$stmt->execute([':empresa_id' => $empresaId]);
$ultimosEnvios = $stmt->fetchAll();

$stmt = db()->prepare(
    "SELECT COUNT(*)
     FROM cobrancas cb
     INNER JOIN clientes c ON c.id = cb.cliente_id AND c.empresa_id = cb.empresa_id
     LEFT JOIN (
        SELECT empresa_id, cobranca_id, SUM(valor_pago) AS total_pago
        FROM pagamentos
        WHERE empresa_id = :empresa_pagamentos
        GROUP BY empresa_id, cobranca_id
     ) pg ON pg.cobranca_id = cb.id AND pg.empresa_id = cb.empresa_id
     WHERE cb.empresa_id = :empresa_id
       AND cb.status IN ('Em aberto','Vencida')
       AND cb.data_vencimento BETWEEN DATE_SUB(CURDATE(), INTERVAL 7 DAY) AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
       AND DATEDIFF(cb.data_vencimento, CURDATE()) IN (10, 5, 0, -7)
       AND c.telefone IS NOT NULL
       AND GREATEST(cb.valor - COALESCE(pg.total_pago, 0), 0) > 0"
);
$stmt->execute([
    ':empresa_id' => $empresaId,
    ':empresa_pagamentos' => $empresaId,
]);
$cobrancasElegiveis = (int) $stmt->fetchColumn();

$status = (string) ($connection['status'] ?? 'desconectado');
$qrImage = (string) ($connection['qr_code_imagem'] ?? '');
$hasQrToken = trim((string) ($connection['qr_code'] ?? '')) !== '';
$pairingCode = (string) ($connection['pairing_code'] ?? '');
$shouldRenderQrToken = $status !== 'conectado' && $qrImage === '' && $hasQrToken;
$qrVendorPath = PUBLIC_PATH . '/assets/vendor/qrcode.min.js';
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

        <?php if (!$integrationConfigured): ?>
            <div class="alert alert-danger">
                <?= e(whatsapp_config_error_message()) ?> Provedor atual: <strong><?= e($providerLabel) ?></strong>.
            </div>
        <?php endif; ?>

        <section class="grid four">
            <article class="card metric accent-blue"><span>Status</span><strong><?= e(whatsapp_status_label($status)) ?></strong><small class="metric-note"><?= e(!empty($connection['ultima_sincronizacao']) ? data_hora_br($connection['ultima_sincronizacao']) : 'Ainda não sincronizado') ?></small></article>
            <article class="card metric accent-green"><span>Enviadas</span><strong><?= (int) ($stats['enviados'] ?? 0) ?></strong><small class="metric-note">Mensagens com sucesso</small></article>
            <article class="card metric accent-yellow"><span>Pendentes</span><strong><?= (int) ($stats['pendentes'] ?? 0) ?></strong><small class="metric-note">Aguardando processamento</small></article>
            <article class="card metric accent-red"><span>Falhas</span><strong><?= (int) ($stats['falhas'] ?? 0) ?></strong><small class="metric-note">Revisar conexão/API</small></article>
        </section>

        <section class="connection-grid">
            <article class="card connection-card">
                <div class="section-heading">
                    <div>
                        <h2>WhatsApp da empresa</h2>
                        <p class="muted">Use uma instância por empresa para isolar sessões e envios.</p>
                    </div>
                    <span class="badge <?= e(whatsapp_status_badge($status)) ?>"><?= e(whatsapp_status_label($status)) ?></span>
                </div>

                <form class="form-stack" method="post" action="<?= e($conexaoActionUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="salvar">
                    <label>Nome da instância
                        <input name="instancia_nome" value="<?= e($connection['instancia_nome'] ?? whatsapp_default_instance_name($empresaId)) ?>" placeholder="fluxpay-empresa-<?= (int) $empresaId ?>">
                    </label>
                    <label>Telefone do WhatsApp conectado
                        <input name="telefone_conectado" value="<?= e($connection['telefone_conectado'] ?? '') ?>" placeholder="(00) 00000-0000">
                    </label>
                    <div class="connection-actions">
                        <button class="btn" type="submit">Salvar dados</button>
                    </div>
                </form>

                <div class="connection-actions">
                    <form method="post" action="<?= e($conexaoActionUrl) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="gerar_qr">
                        <input type="hidden" name="instancia_nome" value="<?= e($connection['instancia_nome'] ?? whatsapp_default_instance_name($empresaId)) ?>">
                        <input type="hidden" name="telefone_conectado" value="<?= e($connection['telefone_conectado'] ?? '') ?>">
                        <button class="btn btn-primary" type="submit" <?= !$integrationConfigured ? 'disabled' : '' ?>>Gerar QR Code</button>
                    </form>
                    <form method="post" action="<?= e($conexaoActionUrl) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="atualizar_status">
                        <button class="btn" type="submit" <?= !$integrationConfigured ? 'disabled' : '' ?>>Atualizar status</button>
                    </form>
                    <form method="post" action="<?= e($conexaoActionUrl) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="desconectar">
                        <button class="btn btn-secondary" type="submit">Desconectar</button>
                    </form>
                </div>

                <?php if (!empty($connection['ultimo_erro'])): ?>
                    <div class="connection-warning">
                        <strong>Último erro</strong>
                        <span><?= e($connection['ultimo_erro']) ?></span>
                    </div>
                <?php endif; ?>
            </article>

            <article class="card qr-card">
                <div class="section-heading">
                    <div>
                        <h2>QR Code de conexão</h2>
                        <p class="muted">Abra o WhatsApp no celular da empresa e leia o código para conectar.</p>
                    </div>
                    <?php if ($pairingCode !== ''): ?>
                        <span class="soft-label"><?= e($pairingCode) ?></span>
                    <?php endif; ?>
                </div>

                <div class="qr-box">
                    <?php if ($status === 'conectado'): ?>
                        <div class="qr-empty success-state">
                            <strong>WhatsApp conectado</strong>
                            <span>As cobranças automáticas já podem usar esta sessão.</span>
                        </div>
                    <?php elseif ($qrImage !== ''): ?>
                        <img class="qr-image" src="<?= e($qrImage) ?>" alt="QR Code para conectar WhatsApp">
                    <?php elseif ($hasQrToken): ?>
                        <div class="qr-render-wrap">
                            <div class="qr-render" data-whatsapp-qr-code="<?= e((string) $connection['qr_code']) ?>" aria-label="QR Code para conectar WhatsApp"></div>
                            <span>Leia este QR Code no WhatsApp da empresa.</span>
                        </div>
                    <?php else: ?>
                        <div class="qr-empty">
                            <strong>Nenhum QR Code gerado</strong>
                            <span>Clique em Gerar QR Code para iniciar a conexão.</span>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        </section>

        <section class="connection-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Cobranças automáticas</h2>
                        <p class="muted">Processa lembretes com 10 dias, 5 dias, no vencimento e 7 dias após atraso.</p>
                    </div>
                    <span class="soft-label warning"><?= $cobrancasElegiveis ?> elegíveis hoje</span>
                </div>
                <div class="automation-summary">
                    <div><strong>Envio único por evento</strong><span>O sistema evita duplicar a mesma cobrança no mesmo gatilho.</span></div>
                    <div><strong>Telefone validado</strong><span>Números locais recebem DDI 55 automaticamente.</span></div>
                    <div><strong>Execução por cron</strong><span>Use <code>/cron/processar_whatsapp_cobrancas.php?token=SEU_TOKEN</code> diariamente.</span></div>
                </div>
                <form class="connection-actions" method="post" action="<?= e($conexaoActionUrl) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="acao" value="processar_cobrancas">
                    <button class="btn btn-primary" type="submit">Processar cobranças agora</button>
                </form>
            </article>

            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Últimos envios</h2>
                        <p class="muted">Registros recentes gerados por envio manual ou automático.</p>
                    </div>
                    <a class="btn btn-sm" href="<?= e(public_url('/app/mensagens.php')) ?>">Ver todos</a>
                </div>
                <div class="insight-list">
                    <?php foreach ($ultimosEnvios as $envio): ?>
                        <div class="insight-item">
                            <span class="insight-dot <?= $envio['status_envio'] === 'falhou' ? 'red' : ($envio['status_envio'] === 'enviado' ? 'green' : 'yellow') ?>"></span>
                            <div>
                                <strong><?= e($envio['cliente'] ?? 'Contato avulso') ?> · <?= e($envio['status_envio']) ?></strong>
                                <span><?= e($envio['tipo'] ?? 'manual') ?> em <?= e(data_hora_br($envio['criado_em'])) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php if (!$ultimosEnvios): ?>
                        <div class="empty-state">Nenhuma mensagem enviada ainda.</div>
                    <?php endif; ?>
                </div>
            </article>
        </section>
    </main>
</div>
<?php if ($shouldRenderQrToken && is_file($qrVendorPath)): ?>
<script>
<?= file_get_contents($qrVendorPath) ?>
</script>
<?php endif; ?>
<script>
document.addEventListener('DOMContentLoaded', function () {
    var target = document.querySelector('[data-whatsapp-qr-code]');

    if (!target) {
        return;
    }

    var code = target.getAttribute('data-whatsapp-qr-code') || '';

    if (!code) {
        return;
    }

    if (!window.QRCode) {
        target.textContent = 'Não foi possível carregar o renderizador do QR Code.';
        return;
    }

    target.textContent = '';

    try {
        new window.QRCode(target, {
            text: code,
            width: 280,
            height: 280,
            colorDark: '#1a2c3e',
            colorLight: '#ffffff',
            correctLevel: window.QRCode.CorrectLevel.H
        });

        target.removeAttribute('title');
    } catch (error) {
        target.textContent = 'Não foi possível renderizar o QR Code.';
    }
});
</script>
</body>
</html>
