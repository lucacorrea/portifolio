<?php
// autoErp/public/lavajato/pages/lavagemRapida.php
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../controllers/lavagemRapidaController.php';

/* ==== Flash (vem do controller/GET) ==== */
$ok  = (int)($vm['ok']  ?? 0);
$err = (int)($vm['err'] ?? 0);
$msg = (string)($vm['msg'] ?? '');

/* ==== CSRF (garantido) ==== */
if (empty($_SESSION['csrf_lavagem_rapida'])) {
  $_SESSION['csrf_lavagem_rapida'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_lavagem_rapida'];

/* ==== Fallbacks ==== */
$empresaNome = (string)($vm['empresaNome'] ?? 'Sua Empresa');
$empresaCnpj = (string)($vm['empresa_cnpj'] ?? '');

$vm['servicos']   = $vm['servicos']   ?? [];
$vm['lavadores']  = $vm['lavadores']  ?? [];
$vm['adicionais'] = $vm['adicionais'] ?? [];

?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>AutoERP — Lavagem Rápida</title>

  <link rel="icon" type="image/png" sizes="512x512" href="../../assets/images/dashboard/icon.png">
  <link rel="shortcut icon" href="../../assets/images/favicon.ico">
  <link rel="stylesheet" href="../../assets/css/core/libs.min.css">
  <link rel="stylesheet" href="../../assets/vendor/aos/dist/aos.css">
  <link rel="stylesheet" href="../../assets/css/hope-ui.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/custom.min.css?v=4.0.0">
  <link rel="stylesheet" href="../../assets/css/dark.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.min.css">
  <link rel="stylesheet" href="../../assets/css/customizer.css">
  <link rel="stylesheet" href="../../assets/css/rtl.min.css">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

  <style>
    .form-control,
    .form-select {
      border-radius: 10px;
    }

    .form-label {
      color: black !important;
    }
  </style>
</head>

<body>
  <?php
  $menuAtivo = 'lavagemRapida';
  include '../../layouts/sidebar.php';
  ?>

  <main class="main-content">
    <div class="position-relative iq-banner">
      <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
        <div class="container-fluid navbar-inner">
          <a href="../../dashboard.php" class="navbar-brand">
            <h4 class="logo-title">AutoERP</h4>
          </a>
        </div>
      </nav>

      <div class="iq-navbar-header" style="height: 150px; margin-bottom: 50px;">
        <div class="container-fluid iq-container">
          <h1 class="mb-0">Cadastrar Lavagem Rápida</h1>
          <p>Informe os dados da lavagem rápida do Lava Jato.</p>

          <?php if ($ok || $err): ?>
            <div class="mt-2">
              <?php if ($ok): ?>
                <div class="alert alert-success py-2 mb-0"><?= htmlspecialchars($msg ?: 'Lavagem registrada com sucesso.', ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
              <?php if ($err): ?>
                <div class="alert alert-danger py-2 mb-0"><?= htmlspecialchars($msg ?: 'Falha ao registrar lavagem.', ENT_QUOTES, 'UTF-8') ?></div>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>

        <div class="iq-header-img">
          <img src="../../assets/images/dashboard/top-header.png" class="img-fluid w-100 h-100" alt="">
        </div>

        <div class="container-fluid content-inner mt-n3 py-0">
          <div class="row">
            <div class="col-12">
              <div class="card">
                <div class="card-header">
                  <h4 class="card-title mb-0">Lavagem Rápida</h4>
                </div>

                <div class="card-body">
                  <form method="post" action="../actions/lavagensSalvar.php">
                    <input type="hidden" name="op" value="lav_rapida_nova">
                    <input type="hidden" name="csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="adicionais_json" id="adicionais_json">

                    <!-- data/hora do cadastro -->
                    <input type="hidden" name="criadoEm" value="<?= date('Y-m-d H:i:s') ?>">

                    <!-- Empresa -->
                    <input type="hidden" name="empresa_cnpj" value="<?= htmlspecialchars($empresaCnpj, ENT_QUOTES, 'UTF-8') ?>">

                    <div class="row g-3">

                      <div class="col-md-4">
                        <label class="form-label">Serviço</label>
                        <select name="categoria_id" id="categoria_id" class="form-select" required>
                          <option value="">— Escolha —</option>
                          <?php foreach ($vm['servicos'] as $s): ?>
                            <option
                              value="<?= (int)($s['id'] ?? 0) ?>"
                              data-nome="<?= htmlspecialchars((string)($s['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                              <?= htmlspecialchars((string)($s['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="categoria_nome" id="categoria_nome">
                      </div>

                      <div class="col-md-4">
                        <label class="form-label">Lavador</label>
                        <select name="lavador_cpf" class="form-select" required>
                          <option value="">— Selecionar —</option>
                          <?php foreach ($vm['lavadores'] as $l): ?>
                            <option value="<?= preg_replace('/\D+/', '', (string)($l['cpf'] ?? '')) ?>">
                              <?= htmlspecialchars((string)($l['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                      </div>

                      <div class="col-md-4">
                        <label class="form-label">Valor</label>
                        <input
                          type="number"
                          step="0.01"
                          min="0"
                          name="valor"
                          id="valor"
                          class="form-control"
                          required
                          placeholder="0,00">
                      </div>

                    </div>

                    <div class="row g-3 mt-2">

                      <div class="col-md-4">
                        <label class="form-label">Nome Cliente</label>
                        <input type="text" name="placa" class="form-control" placeholder="Adicione o nome do cliente">
                      </div>

                      <div class="col-md-5">
                        <label class="form-label">Modelo</label>
                        <input type="text" name="modelo" class="form-control" placeholder="Ex: Gol, Corolla, Hilux">
                      </div>

                      <div class="col-md-3">
                        <label class="form-label">Cor</label>
                        <input type="text" name="cor" class="form-control" placeholder="Ex: Branco">
                      </div>

                    </div>

                    <div class="row g-3 mt-4">
                      <div class="col-md-5">
                        <label class="form-label">Adicionar serviço extra</label>
                        <div class="input-group">

                          <select id="adicionalSelect" class="form-select">
                            <option value="">— Selecionar adicional —</option>
                            <?php foreach ($vm['adicionais'] as $a): ?>
                              <option
                                value="<?= (int)($a['id'] ?? 0) ?>"
                                data-nome="<?= htmlspecialchars((string)($a['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"
                                data-valor="<?= number_format((float)($a['valor'] ?? 0), 2, '.', '') ?>">
                                <?= htmlspecialchars((string)($a['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?> —
                                R$ <?= number_format((float)($a['valor'] ?? 0), 2, ',', '.') ?>
                              </option>
                            <?php endforeach; ?>
                          </select>

                          <button type="button" class="btn btn-outline-primary" id="btnAddAdicional">
                            <i class="bi bi-plus-circle"></i>
                          </button>

                        </div>
                      </div>

                      <div class="col-md-7">
                        <label class="form-label">Adicionais selecionados</label>
                        <ul class="list-group" id="listaAdicionais"></ul>
                      </div>
                    </div>

                    <div class="mt-3">
                      <label class="form-label">Observações</label>
                      <textarea name="observacoes" class="form-control" rows="2"></textarea>
                    </div>

                    <div class="mt-4">
                      <button type="submit" class="btn btn-primary">
                        <i class="bi bi-check-circle"></i> Registrar Lavagem
                      </button>
                    </div>

                  </form>
                </div>

              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </main>

  <script src="../../assets/js/core/libs.min.js"></script>

  <script>
    const adicionais = [];
    const categoriaSelect = document.getElementById('categoria_id');
    const categoriaNomeHidden = document.getElementById('categoria_nome');

    const selectAdicional = document.getElementById('adicionalSelect');
    const lista = document.getElementById('listaAdicionais');
    const hiddenInput = document.getElementById('adicionais_json');

    // ✅ Preenche o nome do serviço escolhido
    categoriaSelect.addEventListener('change', function() {
      const opt = this.options[this.selectedIndex];
      categoriaNomeHidden.value = opt?.dataset?.nome ? opt.dataset.nome : '';
    });

    document.getElementById('btnAddAdicional').addEventListener('click', () => {
      const opt = selectAdicional.options[selectAdicional.selectedIndex];
      if (!opt || !opt.value) return;

      if (adicionais.some(a => String(a.id) === String(opt.value))) return;

      adicionais.push({
        id: opt.value,
        nome: opt.dataset.nome || '',
        valor: parseFloat(opt.dataset.valor || '0')
      });

      render();
    });

    function render() {
      lista.innerHTML = '';

      adicionais.forEach((a, i) => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex justify-content-between';

        li.innerHTML = `
          <span>${a.nome} — R$ ${Number(a.valor || 0).toFixed(2)}</span>
          <button type="button" class="btn btn-sm btn-outline-danger p-0">
            <i class="bi bi-x"></i>
          </button>
        `;

        li.querySelector('button').onclick = () => {
          adicionais.splice(i, 1);
          render();
        };

        lista.appendChild(li);
      });

      hiddenInput.value = JSON.stringify(adicionais);
    }
  </script>

</body>

</html>
