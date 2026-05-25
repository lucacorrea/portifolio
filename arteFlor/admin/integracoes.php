<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/whatsapp.php';

$adminUser = require_admin();

function integration_user_can_manage_bridge_credentials(array $adminUser): bool
{
    $email = strtolower((string) ($adminUser['email'] ?? ''));
    $name = strtolower((string) ($adminUser['nome'] ?? ''));

    return str_contains($email, 'suporte')
        || str_contains($name, 'suporte')
        || $email === 'suportelucacorrea@gmail.com';
}

$canManageBridgeCredentials = integration_user_can_manage_bridge_credentials($adminUser);

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!admin_csrf_is_valid($_POST['csrf_token'] ?? null)) {
        header('Location: ' . site_url('admin/integracoes.php?error=csrf'));
        exit;
    }

    $action = order_clean_text($_POST['action'] ?? 'save', 30);
    if ($action === 'save') {
        try {
            if (!$canManageBridgeCredentials) {
                header('Location: ' . site_url('admin/integracoes.php?error=restrito'));
                exit;
            }

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
            $qrInput = $_POST;
            if (!$canManageBridgeCredentials) {
                unset(
                    $qrInput['baileys_bridge_url'],
                    $qrInput['baileys_bridge_api_key'],
                    $qrInput['evolution_api_url'],
                    $qrInput['evolution_api_key']
                );
            }

            whatsapp_save_qr_settings($qrInput, (int) ($adminUser['id'] ?? 0));
            header('Location: ' . site_url('admin/integracoes.php?success=qr_salvo'));
            exit;
        } catch (Throwable $error) {
            error_log('[ArteFlor][integrations-qr-save] ' . $error->getMessage());
            header('Location: ' . site_url('admin/integracoes.php?error=salvar'));
            exit;
        }
    }

    if ($action === 'bridge_qr' || $action === 'evolution_qr') {
        header('Location: ' . site_url('admin/integracoes.php'));
        exit;
    }
}

$adminTitle = 'Integrações';
$activeAdmin = 'integracoes';
$config = whatsapp_config();
$csrf = admin_csrf_token();
$bridgeConfigured = whatsapp_bridge_configured($config);
$bridgeOwnerNumber = whatsapp_link_phone_digits((string) ($config['baileys_owner_number'] ?? ''));
$successMessages = ['salvo' => 'Configurações salvas com segurança.', 'qr_salvo' => 'Número do WhatsApp salvo. O QR será carregado automaticamente.'];
$errorMessages = ['csrf' => 'Sessão expirada. Recarregue a página e tente novamente.', 'salvar' => 'Não foi possível salvar as configurações.', 'restrito' => 'Essa configuração é exclusiva do suporte.'];
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
  .whatsapp-live-actions form { margin: 0; }
  .whatsapp-number-form { margin-top: 18px; padding-top: 18px; border-top: 1px solid #e5e7eb; }
  .whatsapp-support-settings { grid-column: 1 / -1; padding: 14px; border-radius: 12px; background: #f8fafc; border: 1px solid #e2e8f0; }
  .whatsapp-support-settings summary { cursor: pointer; font-weight: 800; color: #364152; }
  .whatsapp-bridge-warning { margin-top: 14px; color: #8a5b00; font-size: .9rem; font-weight: 700; }
  @media (max-width: 980px) { .whatsapp-live-grid { grid-template-columns: 1fr; } .whatsapp-live-hero { align-items: flex-start; } }
</style>

<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Integrações</span>
    <h1>Conexão WhatsApp</h1>
    <p>Conecte o número da empresa por QR Code.</p>
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
    <div class="whatsapp-live-icon">WA</div>
    <div>
      <h2>Conexão WhatsApp</h2>
      <p>Conecte o WhatsApp da empresa ao sistema.</p>
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
        <form id="disconnectWhatsAppForm" action="<?= site_url('admin/actions/whatsapp-bridge.php?action=logout') ?>" method="post" hidden>
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <button class="btn btn-outline" type="submit">Desconectar WhatsApp</button>
        </form>
        <span class="admin-badge-info" id="whatsappLastUpdate">Aguardando consulta...</span>
      </div>

      <form class="admin-form-grid whatsapp-number-form" method="post" action="<?= site_url('admin/integracoes.php') ?>">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="action" value="qr_save">
        <label class="admin-field"><span>Número do WhatsApp</span><input name="baileys_owner_number" value="<?= e($bridgeOwnerNumber) ?>" placeholder="5597000000000"></label>
        <div class="admin-field"><span>&nbsp;</span><button class="btn btn-primary" type="submit">Salvar número</button></div>

        <?php if ($canManageBridgeCredentials): ?>
          <details class="whatsapp-support-settings">
            <summary>Configuração técnica do suporte</summary>
            <div class="admin-form-grid" style="margin-top: 14px;">
              <label class="admin-field"><span>Link do serviço QR</span><input name="baileys_bridge_url" value="<?= e((string) $config['baileys_bridge_url']) ?>" placeholder="https://whatsapp.seudominio.com"></label>
              <label class="admin-field"><span>Chave do serviço</span><input type="password" name="baileys_bridge_api_key" value="" placeholder="<?= $config['baileys_bridge_api_key'] !== '' ? 'Já salva. Preencha só para trocar.' : 'Opcional, se o bridge exigir' ?>"></label>
            </div>
            <p class="whatsapp-bridge-warning">Área exclusiva do suporte. O cliente configura apenas o número do WhatsApp.</p>
          </details>
        <?php endif; ?>
      </form>
    </article>

    <article class="whatsapp-live-card">
      <h3>Escaneie o QR Code</h3>
      <p class="muted">O código é atualizado automaticamente.</p>
      <div class="whatsapp-qr-frame">
        <img id="whatsappQrImage" src="" alt="QR Code para conectar WhatsApp" hidden>
        <div id="whatsappQrEmpty" class="whatsapp-qr-empty">Carregando QR Code...</div>
      </div>
      <div class="whatsapp-live-note">i <span id="whatsappQrMessage">O código é atualizado automaticamente a cada 30 segundos.</span></div>
    </article>
  </div>
</section>

<script>
(function () {
  const endpoints = {
    status: <?= json_encode(site_url('admin/actions/whatsapp-bridge.php?action=status'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    qrcode: <?= json_encode(site_url('admin/actions/whatsapp-bridge.php?action=qrcode'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    logout: <?= json_encode(site_url('admin/actions/whatsapp-bridge.php?action=logout'), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>,
    csrfToken: <?= json_encode($csrf, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>
  };
  const statusDot = document.getElementById('whatsappStatusDot');
  const statusText = document.getElementById('whatsappStatusText');
  const qrImage = document.getElementById('whatsappQrImage');
  const qrEmpty = document.getElementById('whatsappQrEmpty');
  const qrMessage = document.getElementById('whatsappQrMessage');
  const lastUpdate = document.getElementById('whatsappLastUpdate');
  const refreshButton = document.getElementById('refreshWhatsAppQr');
  const disconnectForm = document.getElementById('disconnectWhatsAppForm');

  async function getJson(url, options) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      headers: { 'Accept': 'application/json' },
      cache: 'no-store',
      ...(options || {})
    });
    return response.json();
  }

  function showQr(data) {
    if (data.qr) {
      qrImage.src = data.qr;
      qrImage.hidden = false;
      qrEmpty.hidden = true;
      qrMessage.textContent = data.message || 'Escaneie o QR com o WhatsApp da empresa.';
      return;
    }

    qrImage.hidden = true;
    qrImage.removeAttribute('src');
    qrEmpty.hidden = false;
    qrEmpty.textContent = data.message || 'Aguardando QR Code...';
    qrMessage.textContent = data.message || 'O código é atualizado automaticamente a cada 30 segundos.';
  }

  async function fetchQRCode() {
    const data = await getJson(endpoints.qrcode);
    if (data.status === 'connected' || data.message === 'Já conectado') {
      await updateStatus();
      return;
    }
    showQr(data);
  }

  async function updateStatus() {
    lastUpdate.textContent = 'Consultando bridge...';
    const data = await getJson(endpoints.status);
    const status = data.status || 'erro';
    statusDot.classList.remove('waiting', 'connected');

    if (data.connected || status === 'connected') {
      statusDot.classList.add('connected');
      statusText.textContent = data.number ? 'Conectado (' + data.number + ')' : 'Conectado';
      disconnectForm.hidden = false;
      showQr({ message: 'WhatsApp conectado' });
    } else if (status === 'waiting_qr' || status === 'qr' || status === 'connecting' || status === 'aguardando') {
      statusDot.classList.add('waiting');
      statusText.textContent = 'Aguardando leitura do QR Code';
      disconnectForm.hidden = true;
      await fetchQRCode();
    } else if (status === 'not_configured') {
      statusText.textContent = 'Desconfigurado';
      disconnectForm.hidden = true;
      showQr(data);
    } else if (status === 'offline') {
      statusText.textContent = 'Bridge Offline (inicie o Node.js)';
      disconnectForm.hidden = true;
      showQr(data);
    } else {
      statusText.textContent = 'Desconectado';
      disconnectForm.hidden = true;
      await fetchQRCode();
    }

    lastUpdate.textContent = data.updated_at ? 'Atualizado às ' + data.updated_at : 'Consulta finalizada';
  }

  async function safeUpdateStatus() {
    try {
      await updateStatus();
    } catch (error) {
      statusDot.classList.remove('waiting', 'connected');
      statusText.textContent = 'Erro na API PHP';
      showQr({ message: 'Falha ao consultar o bridge. Verifique se o Node.js está rodando na hospedagem.' });
      lastUpdate.textContent = 'Erro na consulta';
    }
  }

  refreshButton.addEventListener('click', safeUpdateStatus);
  disconnectForm.addEventListener('submit', async function (event) {
    event.preventDefault();
    if (!confirm('Tem certeza que deseja desconectar este WhatsApp?')) {
      return;
    }

    const body = new URLSearchParams();
    body.set('csrf_token', endpoints.csrfToken);
    lastUpdate.textContent = 'Desconectando...';

    try {
      const data = await getJson(endpoints.logout, {
        method: 'POST',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/x-www-form-urlencoded'
        },
        body
      });
      showQr(data);
      await safeUpdateStatus();
    } catch (error) {
      showQr({ message: 'Não foi possível desconectar agora.' });
      lastUpdate.textContent = 'Erro ao desconectar';
    }
  });

  safeUpdateStatus();
  setInterval(safeUpdateStatus, 5000);
})();
</script>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
