<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/whatsapp.php';

$adminUser = require_admin();
$testPreview = null;
$testPhone = '';
$bridgeQrResult = null;

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
$successMessages = ['salvo' => 'Configurações salvas com segurança.', 'qr_salvo' => 'Bridge Baileys salvo. Agora gere o QR e escaneie pelo WhatsApp da empresa.'];
$errorMessages = ['csrf' => 'Sessão expirada. Recarregue a página e tente novamente.', 'salvar' => 'Não foi possível salvar as configurações.'];
$successKey = is_string($_GET['success'] ?? null) ? (string) $_GET['success'] : '';
$errorKey = is_string($_GET['error'] ?? null) ? (string) $_GET['error'] : '';

require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Integrações</span>
    <h1>Pix manual e WhatsApp via bridge Baileys</h1>
    <p>Pix permanece manual. O WhatsApp agora pode usar o mesmo modelo do Tático GPS: Node.js + Baileys + QR Code próprio.</p>
  </div>
  <span class="status <?= $config['whatsapp_enabled'] ? 'status-ok' : 'status-warn' ?>"><?= $config['whatsapp_enabled'] ? 'WhatsApp ativo' : 'WhatsApp inativo' ?></span>
</section>

<?php if ($successKey !== '' && isset($successMessages[$successKey])): ?>
  <section class="admin-alert-card admin-alert-success"><strong>Sucesso</strong><?= e($successMessages[$successKey]) ?></section>
<?php endif; ?>
<?php if ($errorKey !== '' && isset($errorMessages[$errorKey])): ?>
  <section class="admin-alert-card admin-alert-danger"><strong>Atenção</strong><?= e($errorMessages[$errorKey]) ?></section>
<?php endif; ?>

<section class="integration-hero card">
  <div>
    <span class="badge">Fluxo correto</span>
    <h2>Pedido primeiro, WhatsApp depois</h2>
    <p class="muted">O checkout salva pedido, itens, pagamento e estoque no banco. A notificação WhatsApp é complementar e usa o bridge apenas depois que o pedido existe.</p>
  </div>
  <div class="integration-flow">
    <span>Checkout</span>
    <span>Banco</span>
    <span>Bridge</span>
  </div>
</section>

<section class="admin-form-card whatsapp-connect-panel">
  <div class="admin-panel-header">
    <div>
      <span class="badge">Conectar WhatsApp</span>
      <h2>Conexão rápida por QR com Baileys</h2>
      <p>Rode o bridge Node.js do ArteFlor, salve a URL e a API key, gere o QR e escaneie em Dispositivos conectados no WhatsApp da empresa.</p>
    </div>
    <span class="<?= $bridgeConfigured ? 'admin-badge-ok' : 'admin-badge-warn' ?>"><?= $bridgeConfigured ? 'Pronto para QR' : 'Falta configurar' ?></span>
  </div>

  <div class="whatsapp-connect-grid">
    <article class="whatsapp-qr-card">
      <div>
        <strong>1. Dados do bridge</strong>
        <p>Use o serviço Node.js em <code>arteFlor/bridge</code>. Em produção, deixe atrás de HTTPS e protegido por API key.</p>
      </div>
      <form class="whatsapp-quick-form" method="post" action="<?= site_url('admin/integracoes.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="qr_save">
        <label class="admin-field"><span>URL do bridge Baileys</span><input name="baileys_bridge_url" value="<?= e((string) $config['baileys_bridge_url']) ?>" placeholder="https://whatsapp.seudominio.com"></label>
        <label class="admin-field"><span>API key do bridge</span><input type="password" name="baileys_bridge_api_key" value="" placeholder="<?= $config['baileys_bridge_api_key'] !== '' ? 'Já salva. Preencha só para trocar.' : 'Cole a mesma BRIDGE_API_KEY do Node.js' ?>"></label>
        <label class="admin-field"><span>Número do WhatsApp</span><input name="baileys_owner_number" value="<?= e($bridgeOwnerNumber) ?>" placeholder="5597000000000"></label>
        <button class="btn btn-primary" type="submit">Salvar bridge QR</button>
      </form>
    </article>

    <article class="whatsapp-qr-card">
      <div>
        <strong>2. Escanear QR</strong>
        <p>No celular da empresa: WhatsApp > Dispositivos conectados > Conectar dispositivo.</p>
      </div>
      <?php if ($bridgeQrResult && ($bridgeQrResult['status'] ?? '') === 'connected'): ?>
        <div class="qr-placeholder">OK</div>
        <small><?= e((string) $bridgeQrResult['message']) ?><?= !empty($bridgeQrResult['number']) ? ' · ' . e((string) $bridgeQrResult['number']) : '' ?></small>
      <?php elseif ($bridgeQrResult && !empty($bridgeQrResult['qr'])): ?>
        <img src="<?= e((string) $bridgeQrResult['qr']) ?>" alt="QR Code para conectar WhatsApp no bridge Baileys" loading="lazy">
        <small><?= e((string) $bridgeQrResult['message']) ?></small>
      <?php else: ?>
        <div class="qr-placeholder">QR</div>
        <small><?= $bridgeQrResult ? e((string) $bridgeQrResult['message']) : 'Salve a configuração e gere o QR.' ?></small>
      <?php endif; ?>
      <form method="post" action="<?= site_url('admin/integracoes.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="bridge_qr">
        <button class="btn btn-primary" type="submit">Gerar QR de conexão</button>
      </form>
    </article>
  </div>

  <div class="admin-alert-card admin-alert-warning">
    <strong>Atenção</strong>
    Esse método usa sessão WhatsApp Web via Baileys. É prático, mas pode desconectar quando o WhatsApp derruba a sessão. Se acontecer, gere um novo QR. Não exponha o bridge sem API key.
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
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
