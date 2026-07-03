<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/auth/csrf.php';
whatsapp_auth_guard();
$csrf = whatsapp_csrf_token();
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Conexão WhatsApp - Central SEMAS</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">
  <style>
    body { background: #eef4ff; font-family: Inter, system-ui, sans-serif; }
    .layout { display: grid; grid-template-columns: 280px 1fr; min-height: 100vh; }
    .side { background: #25396f; color: #fff; padding: 1.25rem; }
    .side a { color: rgba(255,255,255,.82); display: block; padding: .7rem .8rem; border-radius: .6rem; text-decoration: none; font-weight: 600; }
    .side a.active, .side a:hover { background: rgba(255,255,255,.12); color: #fff; }
    .main { padding: 1.5rem; }
    .card { border: 0; border-radius: 16px; box-shadow: 0 1px 3px rgba(16,24,40,.1); }
    .status-dot { width: .85rem; height: .85rem; border-radius: 50%; display: inline-block; background: #94a3b8; }
    .status-dot.online { background: #22c55e; }
    .status-dot.waiting { background: #f59e0b; }
    .status-dot.offline { background: #ef4444; }
    #qrImg { max-width: 280px; width: 100%; height: auto; }
    @media (max-width: 900px) { .layout { grid-template-columns: 1fr; } .side { position: static; } }
  </style>
</head>
<body>
  <div class="layout">
    <aside class="side">
      <h1 class="h5 mb-1">Central SEMAS</h1>
      <p class="small opacity-75 mb-4">Comunicação e atualização cadastral</p>
      <a href="dashboard.php"><i class="bi bi-grid me-2"></i>Visão Geral</a>
      <a href="conexao.php" class="active"><i class="bi bi-phone me-2"></i>Conexão WhatsApp</a>
      <a href="pessoas.php"><i class="bi bi-people me-2"></i>Selecionar Pessoas</a>
      <a href="campanhas.php"><i class="bi bi-megaphone me-2"></i>Campanhas</a>
      <a href="conversas.php"><i class="bi bi-chat-dots me-2"></i>Conversas</a>
      <a href="revisoes.php"><i class="bi bi-clipboard-check me-2"></i>Revisões</a>
      <a href="configuracoes.php"><i class="bi bi-gear me-2"></i>Configurações</a>
      <a href="logout.php"><i class="bi bi-box-arrow-right me-2"></i>Sair</a>
    </aside>
    <main class="main">
      <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-4">
        <div>
          <h2 class="mb-1">Conexão WhatsApp</h2>
          <p class="text-muted mb-0">Controle da instância exclusiva da Central SEMAS.</p>
        </div>
        <button class="btn btn-primary" id="btnStatus"><i class="bi bi-arrow-repeat me-1"></i> Atualizar status</button>
      </div>
      <input type="hidden" id="csrfToken" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
      <div class="row g-4">
        <section class="col-lg-5">
          <div class="card h-100">
            <div class="card-body">
              <h3 class="h5">Estado da sessão</h3>
              <div class="d-flex align-items-center gap-2 my-3">
                <span id="statusDot" class="status-dot"></span>
                <strong id="statusText">Verificando...</strong>
              </div>
              <p class="text-muted" id="statusDetail">Aguardando consulta.</p>
              <div class="d-grid gap-2">
                <button class="btn btn-outline-secondary" id="btnRestart" type="button">Reiniciar cliente SEMAS</button>
                <button class="btn btn-outline-danger" id="btnDisconnect" type="button">Desconectar conta SEMAS</button>
                <button class="btn btn-danger" id="btnResetSession" type="button">Apagar sessão SEMAS e gerar novo QR</button>
              </div>
            </div>
          </div>
        </section>
        <section class="col-lg-7">
          <div class="card h-100">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap">
                <h3 class="h5 mb-0">QR Code</h3>
                <button class="btn btn-outline-primary btn-sm" id="btnQr">Conectar por QR Code</button>
              </div>
              <div class="text-center border rounded-3 p-4 mt-3 bg-light">
                <div id="qrEmpty" class="text-muted">Carregue o QR Code apenas se a sessão estiver aguardando conexão.</div>
                <img id="qrImg" alt="QR Code WhatsApp" class="d-none">
              </div>
              <ol class="small text-muted mt-3 mb-0">
                <li>Abra o WhatsApp no celular.</li>
                <li>Acesse Aparelhos conectados.</li>
                <li>Escolha Conectar um aparelho.</li>
                <li>Leia o QR Code e aguarde a confirmação.</li>
              </ol>
            </div>
          </div>
        </section>
        <section class="col-lg-12">
          <div class="card">
            <div class="card-body">
              <h3 class="h5">Conexão por número</h3>
              <p class="text-muted small">Use apenas se o WhatsApp liberar pairing code para esta sessão. O código pertence somente à instância SEMAS.</p>
              <div class="row g-2 align-items-end">
                <div class="col-sm-2">
                  <label class="form-label" for="ddi">DDI</label>
                  <input class="form-control" id="ddi" value="55" inputmode="numeric">
                </div>
                <div class="col-sm-2">
                  <label class="form-label" for="ddd">DDD</label>
                  <input class="form-control" id="ddd" inputmode="numeric" maxlength="2">
                </div>
                <div class="col-sm-4">
                  <label class="form-label" for="numero">Número</label>
                  <input class="form-control" id="numero" inputmode="numeric">
                </div>
                <div class="col-sm-4">
                  <button class="btn btn-outline-primary w-100" id="btnPairing" type="button">Gerar código de pareamento</button>
                </div>
              </div>
              <div class="alert alert-light border mt-3 mb-0 d-none" id="pairingBox">
                Código: <strong id="pairingCode"></strong>
                <button class="btn btn-sm btn-outline-secondary ms-2" id="btnCopyPairing" type="button">Copiar</button>
                <span class="text-muted small ms-2" id="pairingExpires"></span>
              </div>
            </div>
          </div>
        </section>
      </div>
    </main>
  </div>
  <script>
    const csrf = document.getElementById('csrfToken').value;
    const dot = document.getElementById('statusDot');
    const text = document.getElementById('statusText');
    const detail = document.getElementById('statusDetail');
    const qrImg = document.getElementById('qrImg');
    const qrEmpty = document.getElementById('qrEmpty');
    const pairingBox = document.getElementById('pairingBox');
    const pairingCode = document.getElementById('pairingCode');
    const pairingExpires = document.getElementById('pairingExpires');
    async function request(url, payload = {}, method = 'POST') {
      const options = { method, headers: { 'Accept': 'application/json' } };
      if (method !== 'GET') {
        options.headers['Content-Type'] = 'application/json';
        options.body = JSON.stringify(payload);
      }
      const res = await fetch(url, options);
      const json = await res.json();
      if (!json.sucesso) throw new Error(json.mensagem || 'Falha na consulta.');
      return json.dados;
    }
    async function status() {
      try {
        const data = await request('api/status-conexao.php');
        const statusData = data.dados || {};
        dot.className = 'status-dot ' + (statusData.conectado ? 'online' : (['waiting_qr', 'qr_available', 'connecting', 'authenticating', 'restoring_session', 'reconnecting'].includes(statusData.status) ? 'waiting' : 'offline'));
        text.textContent = data.mensagem || 'Status consultado';
        const phone = statusData.phoneMasked ? ` | Número: ${statusData.phoneMasked}` : '';
        detail.textContent = 'Última verificação: ' + new Date().toLocaleString('pt-BR') + phone;
      } catch (e) {
        dot.className = 'status-dot offline';
        text.textContent = 'Erro de integração';
        detail.textContent = e.message;
      }
    }
    async function qrcode() {
      try {
        qrEmpty.textContent = 'Solicitando QR Code da instância SEMAS...';
        const data = await request('api/gerar-qrcode.php');
        if (data.qr) {
          qrImg.src = data.qr;
          qrImg.classList.remove('d-none');
          qrEmpty.classList.add('d-none');
        } else {
          qrImg.classList.add('d-none');
          qrEmpty.classList.remove('d-none');
          qrEmpty.textContent = data.message || 'QR Code indisponível no momento.';
        }
      } catch (e) {
        qrImg.classList.add('d-none');
        qrEmpty.classList.remove('d-none');
        qrEmpty.textContent = e.message;
      }
    }
    async function pairing() {
      try {
        const data = await request('api/conectar-numero.php', {
          csrf_token: csrf,
          ddi: document.getElementById('ddi').value,
          ddd: document.getElementById('ddd').value,
          numero: document.getElementById('numero').value
        });
        pairingCode.textContent = data.pairingCode || '';
        pairingExpires.textContent = data.expiresAt ? 'Expira em ' + new Date(data.expiresAt).toLocaleTimeString('pt-BR') : '';
        pairingBox.classList.remove('d-none');
      } catch (e) {
        pairingCode.textContent = '';
        pairingExpires.textContent = e.message;
        pairingBox.classList.remove('d-none');
      }
    }
    async function action(url, payload = {}) {
      await request(url, { csrf_token: csrf, ...payload });
      await status();
    }
    document.getElementById('btnStatus').addEventListener('click', status);
    document.getElementById('btnQr').addEventListener('click', qrcode);
    document.getElementById('btnPairing').addEventListener('click', pairing);
    document.getElementById('btnCopyPairing').addEventListener('click', () => navigator.clipboard?.writeText(pairingCode.textContent || ''));
    document.getElementById('btnRestart').addEventListener('click', () => action('api/reiniciar-cliente.php', { csrf_token: csrf }));
    document.getElementById('btnDisconnect').addEventListener('click', () => {
      if (confirm('Desconectar somente a conta WhatsApp da SEMAS?')) action('api/desconectar.php', { csrf_token: csrf });
    });
    document.getElementById('btnResetSession').addEventListener('click', () => {
      const confirmacao = prompt('Digite APAGAR SESSAO SEMAS para remover somente a sessão da SEMAS.');
      if (confirmacao) action('api/apagar-sessao.php', { csrf_token: csrf, confirmacao });
    });
    status();
  </script>
</body>
</html>
