<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/whatsapp.php';

$adminUser = require_admin();
$testPreview = null;
$testPhone = '';
$bridgeQrResult = null;

if (($_GET['bridge_ajax'] ?? '') === 'status') {
    header('Content-Type: application/json; charset=utf-8');

    try {
        $ajaxConfig = whatsapp_config();
        $ajaxConfigured = whatsapp_bridge_configured($ajaxConfig);

        if (!$ajaxConfigured) {
            echo json_encode([
                'configured' => false,
                'success' => false,
                'connected' => false,
                'status' => 'not_configured',
                'message' => 'Configure URL e API key do bridge Baileys antes de gerar o QR.',
                'qr' => null,
                'number' => null,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            exit;
        }

        $result = whatsapp_bridge_prepare_qr($ajaxConfig);
        $status = (string) ($result['status'] ?? 'desconhecido');

        echo json_encode([
            'configured' => true,
            'success' => !empty($result['success']),
            'connected' => $status === 'connected',
            'status' => $status,
            'message' => (string) ($result['message'] ?? 'Consultando conexão WhatsApp...'),
            'qr' => (string) ($result['qr'] ?? ''),
            'number' => (string) ($result['number'] ?? ''),
            'updated_at' => date('H:i:s'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    } catch (Throwable $error) {
        error_log('[ArteFlor][baileys-bridge-ajax] ' . $error->getMessage());
        http_response_code(500);
        echo json_encode([
            'configured' => false,
            'success' => false,
            'connected' => false,
            'status' => 'erro',
            'message' => 'Não foi possível consultar o bridge agora.',
            'qr' => null,
            'number' => null,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        header('Location: ' . site_url('admin/integracoes.php?error=csrf'));
        exit;
    }

    $action = order_clean_text($_POST['action'] ?? 'save', 30);
    if ($action === 'save') {
        try {
            whatsapp_save_settings($_POST, (int) ($adminUser['id'] ?? 0));
            header('Location: ' . site_url('admin/integracoes.php?success=salvo'));
            exit;
        } catch (Throwable $error) {
            error_log('[ArteFlor][integrations-save] ' . $error->getMessage());
            header('Location: ' . site_url('admin/integracoes.php?error=salvar'));
            exit;
        }
    }

    if ($action === 'qr_save') {
        try {
            whatsapp_save_qr_settings($_POST, (int) ($adminUser['id'] ?? 0));
            header('Location: ' . site_url('admin/integracoes.php?success=qr_salvo'));
            exit;
        } catch (Throwable $error) {
            error_log('[ArteFlor][integrations-qr-save] ' . $error->getMessage());
            header('Location: ' . site_url('admin/integracoes.php?error=salvar'));
            exit;
        }
    }

    if ($action === 'test') {
        $testPhone = order_clean_text($_POST['test_phone'] ?? '', 40);
        $message = trim((string) ($_POST['whatsapp_message_after_order'] ?? whatsapp_default_message()));
        $sampleItems = "- 2x Buquê de Rosas - R$ 129,90\n- 1x Cartão personalizado - R$ 10,00";
        $testPreview = strtr($message, [
            '{{codigo}}' => '#AF-TESTE',
            '{{cliente}}' => 'Cliente Teste',
            '{{total}}' => 'R$ 269,80',
            '{{forma_pagamento}}' => 'Pix manual',
            '{{status}}' => 'Pedido recebido',
            '{{itens}}' => $sampleItems,
            '{{recebimento}}' => 'Entrega',
            '{{endereco}}' => 'Rua Exemplo, 123 - Centro',
            '{{data_desejada}}' => date('Y-m-d'),
            '{{horario_desejado}}' => '16:00',
            '{{link_pedido}}' => site_url('cliente.php?pedido=AF-TESTE'),
        ]);
    }

    if ($action === 'bridge_qr' || $action === 'evolution_qr') {
        try {
            $bridgeQrResult = whatsapp_bridge_prepare_qr(whatsapp_config());
        } catch (Throwable $error) {
            error_log('[ArteFlor][baileys-bridge-qr] ' . $error->getMessage());
            $bridgeQrResult = [
                'success' => false,
                'status' => 'erro',
                'message' => 'Não foi possível consultar o QR do bridge agora.',
            ];
        }
    }
}

$adminTitle = 'Integrações';
$activeAdmin = 'integracoes';
$config = whatsapp_config();
$notifications = whatsapp_recent_notifications(12);
$csrf = admin_csrf_token();
$sandboxJoinCode = whatsapp_clean_sandbox_join_code($config['twilio_sandbox_join_code'] ?? '');
$sandboxNumber = whatsapp_clean_sandbox_number($config['twilio_sandbox_number'] ?? '14155238886');
$bridgeConfigured = whatsapp_bridge_configured($config);
$bridgeOwnerNumber = whatsapp_link_phone_digits((string) ($config['baileys_owner_number'] ?? ''));
$evolutionInstance = whatsapp_clean_instance_name($config['evolution_instance'] ?? 'arteflor');
$evolutionOwnerNumber = whatsapp_link_phone_digits((string) ($config['evolution_owner_number'] ?? ''));
$successMessages = ['salvo' => 'Configurações salvas com segurança.', 'qr_salvo' => 'Bridge Baileys salvo. O QR será carregado automaticamente.'];
$errorMessages = ['csrf' => 'Sessão expirada. Recarregue a página e tente novamente.', 'salvar' => 'Não foi possível salvar as configurações.'];
$successKey = is_string($_GET['success'] ?? null) ? (string) $_GET['success'] : '';
$errorKey = is_string($_GET['error'] ?? null) ? (string) $_GET['error'] : '';

require_once __DIR__ . '/../includes/admin-head.php';
?>
<style>
  .whatsapp-live-shell { display: grid; gap: 18px; }
  .whatsapp-live-hero { display: flex; align-items: center; gap: 16px; padding: 28px; border-radius: 14px; color: #fff; background: linear-gradient(135deg, #635bff 0%, #6d6aff 100%); box-shadow: 0 14px 36px rgba(99, 91, 255, .22); }
  .whatsapp-live-icon { width: 58px; height: 58px; display: grid; place-items: center; border-radius: 12px; background: rgba(255,255,255,.96); color: #25d366; font-size: 32px; font-weight: 800; flex: 0 0 auto; }
  .whatsapp-live-hero h2 { margin: 0 0 4px; color: #fff; font-size: clamp(1.4rem, 2.4vw, 2rem); }
  .whatsapp-live-hero p { margin: 0; color: rgba(255,255,255,.82); font-weight: 600; }
  .whatsapp-live-grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(320px, .95fr); gap: 18px; }
  .whatsapp-live-card { background: #fff; border-radius: 14px; padding: 24px; box-shadow: 0 10px 30px rgba(15, 23, 42, .08); border: 1px solid rgba(15, 23, 42, .06); }
  .whatsapp-live-card h3 { margin: 0 0 6px; font-size: 1.25rem; color: #182235; }
  .whatsapp-live-card .muted { margin: 0 0 18px; color: #64748b; }
  .whatsapp-status-line { display: flex; align-items: center; gap: 10px; margin: 20px 0; font-weight: 800; color: #475569; }
  .whatsapp-status-dot { width: 13px; height: 13px; border-radius: 999px; display: inline-block; background: #e11d48; box-shadow: 0 0 0 4px rgba(225, 29, 72, .12); }
  .whatsapp-status-dot.waiting { background: #f59e0b; box-shadow: 0 0 0 4px rgba(245, 158, 11, .14); }
  .whatsapp-status-dot.connected { background: #22c55e; box-shadow: 0 0 0 4px rgba(34, 197, 94, .14); }
  .whatsapp-instructions { background: #eef2f7; border-radius: 10px; padding: 16px; color: #475569; line-height: 1.7; }
  .whatsapp-instructions strong { color: #243244; }
  .whatsapp-qr-frame { min-height: 340px; display: grid; place-items: center; border: 1px dashed #cbd5e1; background: #f8fafc; border-radius: 14px; padding: 18px; }
  .whatsapp-qr-frame img { width: min(280px, 100%); height: auto; display: block; background: #fff; padding: 8px; }
  .whatsapp-qr-empty { width: min(280px, 100%); aspect-ratio: 1; display: grid; place-items: center; text-align: center; padding: 22px; border-radius: 12px; background: #fff; color: #64748b; font-weight: 700; }
  .whatsapp-live-note { display: flex; justify-content: center; gap: 8px; align-items: center; margin-top: 12px; color: #64748b; font-size: .92rem; }
  .whatsapp-config-details { margin-top: 18px; }
  .whatsapp-config-details summary { cursor: pointer; font-weight: 800; color: #364152; }
  .whatsapp-live-actions { display: flex; gap: 10px; flex-wrap: wrap; margin-top: 16px; }
  @media (max-width: 980px) { .whatsapp-live-grid { grid-template-columns: 1fr; } .whatsapp-live-hero { align-items: flex-start; } }
</style>

<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Integrações</span>
    <h1>Conexão WhatsApp</h1>
    <p>Conecte o número da empresa por QR Code, no mesmo modelo do Tático GPS.</p>
  </div>
  <span class="status <?= $config['whatsapp_enabled'] ? 'status-ok' : 'status-warn' ?>"><?= $config['whatsapp_enabled'] ? 'WhatsApp ativo' : 'WhatsApp inativo' ?></span>
</section>

<?php if ($successKey !== '' && isset($successMessages[$successKey])): ?>
  <section class="admin-alert-card admin-alert-success"><strong>Sucesso</strong><?= e($successMessages[$successKey]) ?></section>
<?php endif; ?>
<?php if ($errorKey !== '' && isset($errorMessages[$errorKey])): ?>
  <section class="admin-alert-card admin-alert-danger"><strong>Atenção</strong><?= e($errorMessages[$errorKey]) ?></section>
<?php endif; ?>

<section class="whatsapp-live-shell">
  <div class="whatsapp-live-hero">
    <div class="whatsapp-live-icon">☏</div>
    <div>
      <h2>Conexão WhatsApp</h2>
      <p>Conecte seu número para ativar as notificações automáticas de pedidos.</p>
    </div>
  </div>

  <div class="whatsapp-live-grid">
    <article class="whatsapp-live-card">
      <h3>Status do Sistema</h3>
      <p class="muted">Monitoramento em tempo real</p>

      <div class="whatsapp-status-line">
        <span id="whatsappStatusDot" class="whatsapp-status-dot"></span>
        <span id="whatsappStatusText"><?= $bridgeConfigured ? 'Consultando...' : 'Desconfigurado' ?></span>
      </div>

      <div class="whatsapp-instructions">
        <strong>Instruções para conexão:</strong><br>
        1. Abra o WhatsApp no seu celular<br>
        2. Toque em <strong>Menu</strong> ou <strong>Configurações</strong> e selecione <strong>Dispositivos conectados</strong><br>
        3. Toque em <strong>Conectar um dispositivo</strong><br>
        4. Aponte seu celular para o QR Code desta tela
      </div>

      <div class="whatsapp-live-actions">
        <button class="btn btn-primary" type="button" id="refreshWhatsAppQr">Atualizar agora</button>
        <span class="admin-badge-info" id="whatsappLastUpdate">Aguardando consulta...</span>
      </div>

      <details class="whatsapp-config-details" <?= !$bridgeConfigured ? 'open' : '' ?>>
        <summary>Configuração do bridge</summary>
        <form class="admin-form-grid" method="post" action="<?= site_url('admin/integracoes.php') ?>" style="margin-top: 16px;">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="action" value="qr_save">
          <label class="admin-field"><span>URL do bridge Baileys</span><input name="baileys_bridge_url" value="<?= e((string) $config['baileys_bridge_url']) ?>" placeholder="https://whatsapp.seudominio.com"></label>
          <label class="admin-field"><span>API key do bridge</span><input type="password" name="baileys_bridge_api_key" value="" placeholder="<?= $config['baileys_bridge_api_key'] !== '' ? 'Já salva. Preencha só para trocar.' : 'Cole a mesma BRIDGE_API_KEY do Node.js' ?>"></label>
          <label class="admin-field"><span>Número do WhatsApp</span><input name="baileys_owner_number" value="<?= e($bridgeOwnerNumber) ?>" placeholder="5597000000000"></label>
          <div class="admin-field"><span>&nbsp;</span><button class="btn btn-primary" type="submit">Salvar configuração</button></div>
        </form>
      </details>
    </article>

    <article class="whatsapp-live-card">
      <h3>Escaneie o QR Code</h3>
      <p class="muted">O código é atualizado automaticamente.</p>
      <div class="whatsapp-qr-frame">
        <img id="whatsappQrImage" src="" alt="QR Code para conectar WhatsApp" hidden>
        <div id="whatsappQrEmpty" class="whatsapp-qr-empty">Carregando QR Code...</div>
      </div>
      <div class="whatsapp-live-note">ⓘ <span id="whatsappQrMessage">O código é atualizado automaticamente a cada 30 segundos.</span></div>
    </article>
  </div>
</section>

<form class="admin-form-card" method="post" action="<?= site_url('admin/integracoes.php') ?>">
  <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
  <input type="hidden" name="action" value="save">

  <div class="admin-panel-header">
    <div><span class="badge">Pix manual</span><h2>Configuração de pagamento</h2></div>
    <button class="btn btn-primary" type="submit">Salvar configurações</button>
  </div>

  <div class="admin-form-grid">
    <label class="admin-field"><span>Chave Pix</span><input name="pix_key" value="<?= e((string) $config['pix_key']) ?>"></label>
    <label class="admin-field"><span>Nome do recebedor</span><input name="pix_receiver_name" value="<?= e((string) $config['pix_receiver_name']) ?>"></label>
    <label class="admin-field full"><span>Instruções Pix manual</span><textarea name="pix_instructions"><?= e((string) $config['pix_instructions']) ?></textarea></label>
  </div>

  <details class="admin-advanced-settings">
    <summary>Configurações avançadas do WhatsApp</summary>
    <div class="admin-panel-header">
      <div><span class="badge">WhatsApp pós-compra</span><h2>Notificação automática</h2></div>
      <span class="admin-badge-info">Bridge: <?= e(whatsapp_mask_secret((string) $config['baileys_bridge_api_key'])) ?> · Meta: <?= e(whatsapp_mask_secret((string) $config['whatsapp_business_token'])) ?> · Twilio: <?= e(whatsapp_mask_secret((string) $config['twilio_auth_token'])) ?></span>
    </div>

    <div class="admin-form-grid">
      <label class="admin-field"><span>Envio automático</span><select name="whatsapp_enabled"><option value="1" <?= $config['whatsapp_enabled'] ? 'selected' : '' ?>>Ativo</option><option value="0" <?= !$config['whatsapp_enabled'] ? 'selected' : '' ?>>Inativo</option></select></label>
      <label class="admin-field"><span>Modo</span><select name="whatsapp_mode"><option value="baileys_bridge" <?= $config['whatsapp_mode'] === 'baileys_bridge' ? 'selected' : '' ?>>Bridge Baileys / QR próprio</option><option value="simulacao" <?= $config['whatsapp_mode'] === 'simulacao' ? 'selected' : '' ?>>Simulação/log</option><option value="cloud_api" <?= $config['whatsapp_mode'] === 'cloud_api' ? 'selected' : '' ?>>Meta Cloud API</option><option value="twilio" <?= $config['whatsapp_mode'] === 'twilio' ? 'selected' : '' ?>>Twilio WhatsApp</option><option value="evolution_api" <?= $config['whatsapp_mode'] === 'evolution_api' ? 'selected' : '' ?>>Evolution API legado</option></select></label>
      <label class="admin-field"><span>Número da empresa</span><input name="whatsapp_company_number" value="<?= e((string) $config['whatsapp_company_number']) ?>" placeholder="5597000000000"></label>
      <label class="admin-field"><span>Bridge Baileys URL</span><input name="baileys_bridge_url" value="<?= e((string) $config['baileys_bridge_url']) ?>" placeholder="https://whatsapp.seudominio.com"></label>
      <label class="admin-field"><span>Bridge Baileys API key</span><input type="password" name="baileys_bridge_api_key" value="" placeholder="Preencha apenas para alterar"></label>
      <label class="admin-field"><span>Número conectado no bridge</span><input name="baileys_owner_number" value="<?= e($bridgeOwnerNumber) ?>" placeholder="5597000000000"></label>
      <label class="admin-field"><span>Phone Number ID</span><input name="whatsapp_phone_number_id" value="<?= e((string) $config['whatsapp_phone_number_id']) ?>"></label>
      <label class="admin-field"><span>Versão da API</span><input name="whatsapp_api_version" value="<?= e((string) $config['whatsapp_api_version']) ?>"></label>
      <label class="admin-field"><span>Token Cloud API</span><input type="password" name="whatsapp_business_token" value="" placeholder="Preencha apenas para alterar"></label>
      <label class="admin-field"><span>Twilio Account SID</span><input name="twilio_account_sid" value="<?= e((string) $config['twilio_account_sid']) ?>" placeholder="ACxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx"></label>
      <label class="admin-field"><span>Twilio Auth Token</span><input type="password" name="twilio_auth_token" value="" placeholder="Preencha apenas para alterar"></label>
      <label class="admin-field"><span>Twilio WhatsApp From</span><input name="twilio_whatsapp_from" value="<?= e((string) $config['twilio_whatsapp_from']) ?>" placeholder="whatsapp:+14155238886"></label>
      <label class="admin-field"><span>Twilio Content SID</span><input name="twilio_content_sid" value="<?= e((string) $config['twilio_content_sid']) ?>" placeholder="HX... para template aprovado"></label>
      <label class="admin-field"><span>Número Twilio Sandbox</span><input name="twilio_sandbox_number" value="<?= e($sandboxNumber) ?>" placeholder="14155238886"></label>
      <label class="admin-field"><span>Código join sandbox</span><input name="twilio_sandbox_join_code" value="<?= e($sandboxJoinCode) ?>" placeholder="seu-codigo"></label>
      <label class="admin-field"><span>Evolution URL legado</span><input name="evolution_api_url" value="<?= e((string) $config['evolution_api_url']) ?>" placeholder="https://evolution.seudominio.com"></label>
      <label class="admin-field"><span>Evolution API key legado</span><input type="password" name="evolution_api_key" value="" placeholder="Preencha apenas para alterar"></label>
      <label class="admin-field"><span>Evolution instância legado</span><input name="evolution_instance" value="<?= e($evolutionInstance) ?>" placeholder="arteflor"></label>
      <label class="admin-field"><span>Evolution número legado</span><input name="evolution_owner_number" value="<?= e($evolutionOwnerNumber) ?>" placeholder="5597000000000"></label>
      <label class="admin-field"><span>Template name</span><input name="whatsapp_template_name" value="<?= e((string) $config['whatsapp_template_name']) ?>"></label>
      <label class="admin-field"><span>Idioma template</span><input name="whatsapp_template_language" value="<?= e((string) $config['whatsapp_template_language']) ?>"></label>
      <label class="admin-field"><span>Enviar ao criar pedido</span><select name="whatsapp_send_after_order"><option value="1" <?= $config['whatsapp_send_after_order'] ? 'selected' : '' ?>>Sim</option><option value="0" <?= !$config['whatsapp_send_after_order'] ? 'selected' : '' ?>>Não</option></select></label>
      <label class="admin-field"><span>Enviar ao mudar status</span><select name="whatsapp_send_on_status_change"><option value="0" <?= !$config['whatsapp_send_on_status_change'] ? 'selected' : '' ?>>Não</option><option value="1" <?= $config['whatsapp_send_on_status_change'] ? 'selected' : '' ?>>Sim</option></select></label>
      <label class="admin-field full"><span>Mensagem automática após pedido</span><textarea name="whatsapp_message_after_order" rows="12"><?= e((string) $config['whatsapp_message_after_order']) ?></textarea></label>
    </div>
    <div class="admin-alert-card admin-alert-info">
      <strong>Regras de envio</strong>
      O bridge Baileys precisa ficar rodando em Node.js e protegido por API key. Mensagens livres pelo Twilio WhatsApp só são adequadas dentro da janela de atendimento de 24 horas; fora dela, configure um Content SID de template aprovado.
    </div>
  </details>
</form>

<section class="admin-form-card">
  <div class="admin-panel-header">
    <div><span class="badge">Teste</span><h2>Preview de mensagem</h2></div>
  </div>
  <form class="admin-form-grid" method="post" action="<?= site_url('admin/integracoes.php') ?>">
    <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
    <input type="hidden" name="action" value="test">
    <label class="admin-field"><span>Telefone teste</span><input name="test_phone" value="<?= e($testPhone) ?>" placeholder="(97) 90000-0000"></label>
    <label class="admin-field full"><span>Mensagem para testar</span><textarea name="whatsapp_message_after_order" rows="8"><?= e((string) $config['whatsapp_message_after_order']) ?></textarea></label>
    <button class="btn btn-soft" type="submit">Gerar simulação</button>
  </form>
  <?php if ($testPreview !== null): ?>
    <div class="integration-preview">
      <strong>Prévia da mensagem<?= $testPhone !== '' ? ' para ' . e($testPhone) : '' ?></strong>
      <pre><?= e($testPreview) ?></pre>
    </div>
  <?php endif; ?>
</section>

<section class="admin-form-card">
  <div class="admin-panel-header">
    <div><span class="badge">Histórico</span><h2>Últimas notificações WhatsApp</h2></div>
  </div>
  <div class="admin-data-table compact">
    <table>
      <thead><tr><th>Pedido</th><th>Telefone</th><th>Tipo</th><th>Status</th><th>Data</th><th>Erro</th></tr></thead>
      <tbody>
        <?php if (empty($notifications)): ?>
          <tr><td colspan="6"><div class="admin-empty-state"><strong>Nenhuma notificação registrada.</strong><p>Use modo simulação para testar sem token.</p></div></td></tr>
        <?php endif; ?>
        <?php foreach ($notifications as $notification): ?>
          <tr>
            <td><?= e((string) $notification['codigo']) ?></td>
            <td><?= e((string) $notification['telefone_destino']) ?></td>
            <td><?= e((string) $notification['tipo']) ?></td>
            <td><span class="<?= e(order_badge_class((string) $notification['status'])) ?>"><?= e((string) $notification['status']) ?></span></td>
            <td><?= e(date('d/m/Y H:i', strtotime((string) $notification['criado_em']))) ?></td>
            <td><?= e((string) ($notification['erro'] ?? '')) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</section>

<script>
(function () {
  const endpoint = <?= json_encode(site_url('admin/integracoes.php?bridge_ajax=status'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
  const statusDot = document.getElementById('whatsappStatusDot');
  const statusText = document.getElementById('whatsappStatusText');
  const qrImage = document.getElementById('whatsappQrImage');
  const qrEmpty = document.getElementById('whatsappQrEmpty');
  const qrMessage = document.getElementById('whatsappQrMessage');
  const lastUpdate = document.getElementById('whatsappLastUpdate');
  const refreshButton = document.getElementById('refreshWhatsAppQr');

  function setStatus(data) {
    const status = data.status || 'erro';
    statusDot.classList.remove('waiting', 'connected');

    if (data.connected || status === 'connected') {
      statusDot.classList.add('connected');
      statusText.textContent = data.number ? 'Conectado · ' + data.number : 'Conectado';
    } else if (status === 'waiting_qr' || status === 'qr' || status === 'connecting' || status === 'aguardando') {
      statusDot.classList.add('waiting');
      statusText.textContent = 'Aguardando leitura do QR Code';
    } else if (status === 'not_configured') {
      statusText.textContent = 'Desconfigurado';
    } else {
      statusText.textContent = 'Desconectado';
    }

    if (data.qr) {
      qrImage.src = data.qr;
      qrImage.hidden = false;
      qrEmpty.hidden = true;
    } else {
      qrImage.hidden = true;
      qrImage.removeAttribute('src');
      qrEmpty.hidden = false;
      qrEmpty.textContent = data.connected ? 'WhatsApp conectado' : (data.message || 'QR Code ainda não disponível.');
    }

    qrMessage.textContent = data.message || 'O código é atualizado automaticamente a cada 30 segundos.';
    lastUpdate.textContent = data.updated_at ? 'Atualizado às ' + data.updated_at : 'Consulta finalizada';
  }

  async function loadStatus() {
    lastUpdate.textContent = 'Consultando bridge...';
    try {
      const response = await fetch(endpoint, {
        credentials: 'same-origin',
        headers: { 'Accept': 'application/json' },
        cache: 'no-store'
      });
      const data = await response.json();
      setStatus(data);
    } catch (error) {
      setStatus({
        status: 'erro',
        connected: false,
        message: 'Falha ao consultar o bridge. Verifique se o Node.js está rodando na hospedagem.',
        qr: null
      });
      lastUpdate.textContent = 'Erro na consulta';
    }
  }

  refreshButton.addEventListener('click', loadStatus);
  loadStatus();
  setInterval(loadStatus, 30000);
})();
</script>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
