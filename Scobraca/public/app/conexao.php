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

function conexao_json_response(array $payload, int $httpCode = 200): never
{
    http_response_code($httpCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function conexao_verify_ajax_csrf(): void
{
    $token = (string) ($_POST['_csrf_token'] ?? '');
    $sessionToken = (string) ($_SESSION['_csrf_token'] ?? '');

    if ($token === '' || $sessionToken === '' || !hash_equals($sessionToken, $token)) {
        conexao_json_response([
            'success' => false,
            'message' => 'Sessão expirada. Recarregue a página e tente novamente.',
        ], 419);
    }
}

function conexao_ajax_status(int $empresaId): never
{
    if (!whatsapp_integration_configured()) {
        whatsapp_update_connection($empresaId, [
            'status' => 'erro',
            'ultimo_erro' => whatsapp_config_error_message(),
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        ]);

        conexao_json_response([
            'connected' => false,
            'status' => 'offline',
            'appStatus' => 'erro',
            'number' => null,
            'message' => whatsapp_config_error_message(),
        ]);
    }

    if (whatsapp_provider() !== 'bridge') {
        $result = whatsapp_refresh_connection($empresaId);
        $connection = whatsapp_get_connection($empresaId);
        $status = (string) ($connection['status'] ?? 'desconectado');

        conexao_json_response([
            'connected' => $status === 'conectado',
            'status' => $status,
            'appStatus' => $status,
            'number' => $connection['telefone_conectado'] ?? null,
            'message' => $result['message'] ?? null,
        ]);
    }

    $currentConnection = whatsapp_get_connection($empresaId);
    $instanceName = (string) ($currentConnection['instancia_nome'] ?? whatsapp_default_instance_name($empresaId));
    $response = whatsapp_bridge_request('GET', whatsapp_bridge_instance_path('/status', $instanceName));

    if (!$response['ok']) {
        whatsapp_update_connection($empresaId, [
            'status' => 'erro',
            'ultimo_erro' => $response['error'],
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        ]);

        conexao_json_response([
            'connected' => false,
            'status' => 'offline',
            'appStatus' => 'erro',
            'number' => null,
            'message' => (string) $response['error'],
        ]);
    }

    $data = is_array($response['data']) ? $response['data'] : [];
    $appStatus = whatsapp_extract_bridge_state($data);
    $connected = $appStatus === 'conectado';
    $connectedNumber = whatsapp_normalize_phone((string) ($data['number'] ?? ''));
    whatsapp_update_connection($empresaId, [
        'status' => $appStatus,
        'telefone_conectado' => $connectedNumber ?: ($currentConnection['telefone_conectado'] ?? null),
        'ultimo_erro' => null,
        'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        'conectado_em' => $connected ? date('Y-m-d H:i:s') : null,
        'qr_code' => $connected ? null : ($currentConnection['qr_code'] ?? null),
        'qr_code_imagem' => $connected ? null : ($currentConnection['qr_code_imagem'] ?? null),
        'pairing_code' => null,
    ]);

    conexao_json_response([
        'connected' => $connected,
        'status' => (string) ($data['status'] ?? ($connected ? 'connected' : 'disconnected')),
        'appStatus' => $appStatus,
        'number' => $connectedNumber ?: ($data['number'] ?? null),
        'message' => $data['message'] ?? null,
    ]);
}

function conexao_ajax_qrcode(int $empresaId): never
{
    if (!whatsapp_integration_configured()) {
        conexao_json_response([
            'qr' => null,
            'code' => null,
            'status' => 'offline',
            'message' => whatsapp_config_error_message(),
        ]);
    }

    if (whatsapp_provider() !== 'bridge') {
        $connection = whatsapp_get_connection($empresaId);
        $result = whatsapp_connect_instance(
            $empresaId,
            (string) ($connection['instancia_nome'] ?? whatsapp_default_instance_name($empresaId)),
            (string) ($connection['telefone_conectado'] ?? '')
        );
        $connection = whatsapp_get_connection($empresaId);

        conexao_json_response([
            'qr' => $connection['qr_code_imagem'] ?? null,
            'code' => $connection['qr_code'] ?? null,
            'status' => $connection['status'] ?? 'desconectado',
            'message' => $result['message'] ?? null,
        ]);
    }

    $connection = whatsapp_get_connection($empresaId);
    $instanceName = (string) ($connection['instancia_nome'] ?? whatsapp_default_instance_name($empresaId));
    $response = whatsapp_bridge_request('GET', whatsapp_bridge_instance_path('/qrcode', $instanceName));

    if (!$response['ok']) {
        whatsapp_update_connection($empresaId, [
            'status' => 'erro',
            'ultimo_erro' => $response['error'],
            'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        ]);

        conexao_json_response([
            'qr' => null,
            'code' => null,
            'status' => 'offline',
            'message' => (string) $response['error'],
        ]);
    }

    $data = is_array($response['data']) ? $response['data'] : [];
    $qrImage = whatsapp_extract_qr_image($data);
    $qrCode = whatsapp_find_first_value($data, ['code', 'qrCode', 'qrcode']);
    $appStatus = whatsapp_extract_bridge_state($data);

    if (($qrImage !== null || $qrCode !== null) && $appStatus !== 'conectado') {
        $appStatus = 'conectando';
    }

    $connected = $appStatus === 'conectado';

    whatsapp_update_connection($empresaId, [
        'status' => $appStatus,
        'qr_code' => $connected ? null : $qrCode,
        'qr_code_imagem' => $connected ? null : $qrImage,
        'pairing_code' => null,
        'ultimo_erro' => null,
        'ultima_sincronizacao' => date('Y-m-d H:i:s'),
        'conectado_em' => $connected ? date('Y-m-d H:i:s') : null,
    ]);

    conexao_json_response([
        'qr' => $connected ? null : $qrImage,
        'code' => $connected ? null : $qrCode,
        'status' => (string) ($data['status'] ?? ($connected ? 'connected' : 'waiting_qr')),
        'appStatus' => $appStatus,
        'connected' => $connected,
        'message' => $data['message'] ?? ($connected ? 'Já conectado' : null),
    ]);
}

function conexao_ajax_logout(int $empresaId): never
{
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        conexao_json_response([
            'success' => false,
            'message' => 'Método inválido para desconectar o WhatsApp.',
        ], 405);
    }

    conexao_verify_ajax_csrf();
    $result = whatsapp_disconnect_instance($empresaId);

    conexao_json_response([
        'success' => (bool) $result['ok'],
        'status' => $result['ok'] ? 'disconnected' : 'error',
        'message' => (string) $result['message'],
    ], $result['ok'] ? 200 : 500);
}

if (isset($_GET['whatsapp_ajax'])) {
    $ajaxAction = (string) $_GET['whatsapp_ajax'];

    if ($ajaxAction === 'status') {
        conexao_ajax_status($empresaId);
    }

    if ($ajaxAction === 'qrcode') {
        conexao_ajax_qrcode($empresaId);
    }

    if ($ajaxAction === 'logout') {
        conexao_ajax_logout($empresaId);
    }

    conexao_json_response([
        'success' => false,
        'message' => 'Ação AJAX inválida para conexão do WhatsApp.',
    ], 400);
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    verify_csrf($conexaoPagePath);

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
            <article class="card metric accent-blue"><span>Status</span><strong id="whatsapp-status-text"><?= e(whatsapp_status_label($status)) ?></strong><small class="metric-note" id="whatsapp-status-sync"><?= e(!empty($connection['ultima_sincronizacao']) ? data_hora_br($connection['ultima_sincronizacao']) : 'Ainda não sincronizado') ?></small></article>
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
                    <span class="badge <?= e(whatsapp_status_badge($status)) ?>" id="whatsapp-status-badge"><?= e(whatsapp_status_label($status)) ?></span>
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
                    <button class="btn btn-primary" type="button" id="btn-gerar-qr" <?= !$integrationConfigured ? 'disabled' : '' ?>>Gerar QR Code</button>
                    <button class="btn" type="button" id="btn-atualizar-status" <?= !$integrationConfigured ? 'disabled' : '' ?>>Atualizar status</button>
                    <button class="btn btn-secondary" type="button" id="btn-desconectar">Desconectar</button>
                </div>

                <div class="connection-warning" id="whatsapp-warning" style="<?= !empty($connection['ultimo_erro']) ? '' : 'display: none;' ?>">
                        <strong>Último erro</strong>
                        <span id="whatsapp-warning-text"><?= e($connection['ultimo_erro'] ?? '') ?></span>
                </div>
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

                <div class="qr-box" id="qrcode-container">
                    <div class="qr-empty success-state" id="qr-connected" style="<?= $status === 'conectado' ? '' : 'display: none;' ?>">
                        <strong>WhatsApp conectado</strong>
                        <span>As cobranças automáticas já podem usar esta sessão.</span>
                    </div>

                    <img class="qr-image" id="qrcode-img" src="<?= e($qrImage) ?>" alt="QR Code para conectar WhatsApp" style="<?= $qrImage !== '' && $status !== 'conectado' ? '' : 'display: none;' ?>">

                    <div class="qr-render-wrap" id="qr-render-wrap" style="<?= $qrImage === '' && $hasQrToken && $status !== 'conectado' ? '' : 'display: none;' ?>">
                        <div class="qr-render" id="qr-render" data-whatsapp-qr-code="<?= e((string) ($connection['qr_code'] ?? '')) ?>" aria-label="QR Code para conectar WhatsApp"></div>
                        <span>Leia este QR Code no WhatsApp da empresa.</span>
                    </div>

                    <div class="qr-empty" id="qr-placeholder" style="<?= $status !== 'conectado' && $qrImage === '' && !$hasQrToken ? '' : 'display: none;' ?>">
                        <strong>Aguardando QR Code</strong>
                        <span id="qr-placeholder-text">O código será carregado automaticamente.</span>
                    </div>
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
<?php if (is_file($qrVendorPath)): ?>
<script>
<?= file_get_contents($qrVendorPath) ?>
</script>
<?php endif; ?>
<script>
(function () {
    var endpoints = {
        status: <?= json_encode($conexaoActionUrl . '?whatsapp_ajax=status', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        qrcode: <?= json_encode($conexaoActionUrl . '?whatsapp_ajax=qrcode', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
        logout: <?= json_encode($conexaoActionUrl . '?whatsapp_ajax=logout', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
    };
    var csrfToken = <?= json_encode(csrf_token(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
    var statusTimer = null;
    var statusLoading = false;
    var qrLoading = false;

    function byId(id) {
        return document.getElementById(id);
    }

    function display(element, show) {
        if (element) {
            element.style.display = show ? '' : 'none';
        }
    }

    function nowBr() {
        try {
            return new Intl.DateTimeFormat('pt-BR', {
                day: '2-digit',
                month: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(new Date()).replace(',', '');
        } catch (error) {
            return '';
        }
    }

    function setWarning(message) {
        var warning = byId('whatsapp-warning');
        var text = byId('whatsapp-warning-text');

        if (text) {
            text.textContent = message || '';
        }

        display(warning, Boolean(message));
    }

    function setStatusVisual(label, statusClass) {
        var text = byId('whatsapp-status-text');
        var sync = byId('whatsapp-status-sync');
        var badge = byId('whatsapp-status-badge');

        if (text) {
            text.textContent = label;
        }

        if (sync) {
            sync.textContent = nowBr();
        }

        if (badge) {
            badge.className = 'badge ' + statusClass;
            badge.textContent = label;
        }
    }

    function setQrPlaceholder(title, message) {
        var placeholder = byId('qr-placeholder');
        var placeholderText = byId('qr-placeholder-text');
        var placeholderTitle = placeholder ? placeholder.querySelector('strong') : null;

        if (placeholderTitle) {
            placeholderTitle.textContent = title;
        }

        if (placeholderText) {
            placeholderText.textContent = message || '';
        }

        display(byId('qr-connected'), false);
        display(byId('qrcode-img'), false);
        display(byId('qr-render-wrap'), false);
        display(placeholder, true);
    }

    function setQrConnected() {
        display(byId('qr-placeholder'), false);
        display(byId('qrcode-img'), false);
        display(byId('qr-render-wrap'), false);
        display(byId('qr-connected'), true);
    }

    function setQrImage(src) {
        var image = byId('qrcode-img');

        if (image) {
            image.src = src;
        }

        display(byId('qr-placeholder'), false);
        display(byId('qr-render-wrap'), false);
        display(byId('qr-connected'), false);
        display(image, true);
    }

    function setQrCode(code) {
        var target = byId('qr-render');

        if (!target || !code) {
            setQrPlaceholder('QR Code indisponível', 'A bridge não retornou um QR Code válido.');
            return;
        }

        if (!window.QRCode) {
            setQrPlaceholder('Renderizador indisponível', 'Não foi possível carregar o renderizador do QR Code.');
            return;
        }

        target.textContent = '';
        target.setAttribute('data-whatsapp-qr-code', code);

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
            setQrPlaceholder('QR Code indisponível', 'Não foi possível renderizar o QR Code.');
            return;
        }

        display(byId('qr-placeholder'), false);
        display(byId('qrcode-img'), false);
        display(byId('qr-connected'), false);
        display(byId('qr-render-wrap'), true);
    }

    function requestJson(url, options) {
        var requestOptions = options || {};
        requestOptions.credentials = 'same-origin';
        requestOptions.headers = Object.assign({
            'Accept': 'application/json'
        }, requestOptions.headers || {});

        return fetch(url, requestOptions).then(function (response) {
            return response.text().then(function (text) {
                var data = {};

                try {
                    data = text ? JSON.parse(text) : {};
                } catch (error) {
                    throw new Error('Resposta inválida do servidor.');
                }

                if (!response.ok) {
                    throw new Error(data.message || 'Não foi possível comunicar com a tela de conexão.');
                }

                return data;
            });
        });
    }

    function applyStatus(data) {
        var rawStatus = data.status || '';
        var appStatus = data.appStatus || rawStatus;
        var connected = Boolean(data.connected);
        var label = 'Desconectado';
        var badgeClass = 'pendente';

        if (connected) {
            label = data.number ? 'Conectado (' + data.number + ')' : 'Conectado';
            badgeClass = 'ativa';
            setStatusVisual(label, badgeClass);
            setQrConnected();
            setWarning('');
            return { connected: true, offline: false };
        }

        if (rawStatus === 'offline' || appStatus === 'erro') {
            label = rawStatus === 'offline' ? 'Bridge offline' : 'Erro de conexão';
            badgeClass = 'vencida';
            setWarning(data.message || 'Falha ao comunicar com a bridge de WhatsApp.');
            setQrPlaceholder('Bridge offline', 'Verifique se a bridge Node.js está rodando.');
        } else if (appStatus === 'conectando' || rawStatus === 'waiting_qr' || rawStatus === 'connecting') {
            label = 'Aguardando leitura';
            badgeClass = 'pendente';
            setWarning('');
        } else {
            setWarning('');
        }

        setStatusVisual(label, badgeClass);
        return { connected: false, offline: rawStatus === 'offline' || appStatus === 'erro' };
    }

    function updateStatus(loadQr) {
        if (statusLoading) {
            return Promise.resolve();
        }

        statusLoading = true;

        return requestJson(endpoints.status)
            .then(function (data) {
                var state = applyStatus(data);

                if (!state.connected && !state.offline && loadQr) {
                    return fetchQRCode();
                }

                return null;
            })
            .catch(function (error) {
                setStatusVisual('Erro de conexão', 'vencida');
                setWarning(error.message || 'Erro na API PHP.');
                setQrPlaceholder('Erro na API PHP', 'Recarregue a página e tente novamente.');
            })
            .finally(function () {
                statusLoading = false;
            });
    }

    function fetchQRCode() {
        if (qrLoading) {
            return Promise.resolve();
        }

        qrLoading = true;
        setQrPlaceholder('Aguardando QR Code', 'Gerando código de conexão...');

        return requestJson(endpoints.qrcode)
            .then(function (data) {
                if (data.connected || data.message === 'Já conectado') {
                    setQrConnected();
                    return updateStatus(false);
                }

                if (data.qr) {
                    setStatusVisual('Aguardando leitura', 'pendente');
                    setWarning('');
                    setQrImage(data.qr);
                    return null;
                }

                if (data.code) {
                    setStatusVisual('Aguardando leitura', 'pendente');
                    setWarning('');
                    setQrCode(data.code);
                    return null;
                }

                if (data.status === 'offline') {
                    setStatusVisual('Bridge offline', 'vencida');
                    setWarning(data.message || 'Falha ao comunicar com a bridge de WhatsApp.');
                    setQrPlaceholder('Bridge offline', 'Verifique se a bridge Node.js está rodando.');
                    return null;
                }

                setQrPlaceholder('Aguardando QR Code', data.message || 'A bridge ainda está gerando o código.');
                return null;
            })
            .catch(function (error) {
                setWarning(error.message || 'Erro ao buscar QR Code.');
                setQrPlaceholder('Erro ao buscar QR Code', error.message || 'Tente novamente em alguns segundos.');
            })
            .finally(function () {
                qrLoading = false;
            });
    }

    function disconnectWhatsApp() {
        if (!confirm('Tem certeza que deseja desconectar o WhatsApp?')) {
            return;
        }

        requestJson(endpoints.logout, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: '_csrf_token=' + encodeURIComponent(csrfToken)
        })
            .then(function () {
                setStatusVisual('Desconectado', 'pendente');
                setWarning('');
                setQrPlaceholder('Aguardando QR Code', 'Gerando nova sessão de conexão...');
                return fetchQRCode();
            })
            .catch(function (error) {
                setWarning(error.message || 'Não foi possível desconectar o WhatsApp.');
            });
    }

    document.addEventListener('DOMContentLoaded', function () {
        var btnQr = byId('btn-gerar-qr');
        var btnStatus = byId('btn-atualizar-status');
        var btnLogout = byId('btn-desconectar');

        if (btnQr) {
            btnQr.addEventListener('click', fetchQRCode);
        }

        if (btnStatus) {
            btnStatus.addEventListener('click', function () {
                updateStatus(true);
            });
        }

        if (btnLogout) {
            btnLogout.addEventListener('click', disconnectWhatsApp);
        }

        updateStatus(true);
        statusTimer = window.setInterval(function () {
            updateStatus(true);
        }, 5000);

        window.addEventListener('beforeunload', function () {
            if (statusTimer) {
                window.clearInterval(statusTimer);
            }
        });
    });
})();
</script>
</body>
</html>
