<?php

declare(strict_types=1);
session_start();

require_once __DIR__ . '/assets/conexao.php';

function e(string $s): string
{
  return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

$flash = $_SESSION['flash'] ?? null;
unset($_SESSION['flash']);

// Busca no banco (sem filtros server-side obrigatórios)
$pdo = db();
$rows = $pdo->query("SELECT id, nome, status, doc, tel, email, endereco, cidade, uf, contato, obs
                     FROM fornecedores
                     ORDER BY id DESC
                     LIMIT 2000")->fetchAll();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Fornecedores</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    .main-btn.btn-compact {
      height: 38px !important;
      padding: 8px 14px !important;
      font-size: 13px !important;
      line-height: 1 !important;
    }

    .main-btn.btn-compact i {
      font-size: 14px;
      vertical-align: -1px;
    }

    .form-control.compact,
    .form-select.compact {
      height: 38px;
      padding: 8px 12px;
      font-size: 13px;
    }

    .muted {
      font-size: 12px;
      color: #64748b;
    }

    .cardx {
      border: 1px solid rgba(148, 163, 184, .28);
      border-radius: 16px;
      background: #fff;
      overflow: hidden;
    }

    .cardx .head {
      padding: 12px 14px;
      border-bottom: 1px solid rgba(148, 163, 184, .22);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }

    .cardx .body {
      padding: 14px;
    }

    .table td,
    .table th {
      vertical-align: middle;
    }

    .table-responsive {
      -webkit-overflow-scrolling: touch;
    }

    #tbFor {
      width: 100%;
      min-width: 980px;
    }

    #tbFor th,
    #tbFor td {
      white-space: nowrap !important;
    }

    .badge-soft {
      border: 1px solid rgba(148, 163, 184, .30);
      background: rgba(248, 250, 252, .8);
      padding: 5px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 900;
      color: #0f172a;
    }

    .badge-ok {
      border-color: rgba(34, 197, 94, .22);
      background: rgba(240, 253, 244, .9);
      color: #166534;
    }

    .badge-off {
      border-color: rgba(239, 68, 68, .22);
      background: rgba(254, 242, 242, .9);
      color: #991b1b;
    }

    .modal-content {
      border-radius: 16px;
      overflow: hidden;
    }

    .modal-header {
      border-bottom: 1px solid rgba(148, 163, 184, .22);
    }

    .modal-footer {
      border-top: 1px solid rgba(148, 163, 184, .22);
    }

    .flash-auto-hide {
      transition: opacity .35s ease, transform .35s ease;
    }

    .flash-auto-hide.hide {
      opacity: 0;
      transform: translateY(-6px);
      pointer-events: none;
    }

    @media (max-width: 991.98px) {
      #tbFor {
        min-width: 900px;
      }
    }
  </style>
</head>

<body>
  <div id="preloader">
    <div class="spinner"></div>
  </div>

  <!-- sidebar (você pode manter o seu completo; deixei simples) -->
  <aside class="sidebar-nav-wrapper">
    <div class="navbar-logo">
      <a href="index.html" class="d-flex align-items-center gap-2">
        <img src="assets/images/logo/logo.svg" alt="logo" />
      </a>
    </div>
    <nav class="sidebar-nav">
      <ul>
        <li class="nav-item"><a href="index.html"><span class="text">Dashboard</span></a></li>
        <li class="nav-item nav-item-has-children active">
          <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-expanded="true">
            <span class="text">Cadastros</span>
          </a>
          <ul id="ddmenu_cadastros" class="collapse show dropdown-nav">
            <li><a href="clientes.html">Clientes</a></li>
            <li><a href="fornecedores.php" class="active">Fornecedores</a></li>
            <li><a href="categorias.html">Categorias</a></li>
          </ul>
        </li>
        <li class="nav-item"><a href="relatorios.html"><span class="text">Relatórios</span></a></li>
      </ul>
    </nav>
  </aside>

  <div class="overlay"></div>

  <main class="main-wrapper">
    <header class="header">
      <div class="container-fluid">
        <div class="row">
          <div class="col-12 d-flex align-items-center justify-content-between">
            <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-compact" type="button">
              <i class="lni lni-chevron-left me-2"></i> Menu
            </button>

            <button class="main-btn primary-btn btn-hover btn-compact" id="btnNovo" type="button" data-bs-toggle="modal" data-bs-target="#mdFornecedor">
              <i class="lni lni-plus me-1"></i> Novo fornecedor
            </button>
          </div>
        </div>
      </div>
    </header>

    <section class="section">
      <div class="container-fluid">
        <div class="title-wrapper pt-30">
          <h2>Fornecedores</h2>
          <div class="muted">Editar em página separada.</div>
        </div>

        <?php if ($flash): ?>
          <div id="flashBox" class="alert alert-<?= e((string)$flash['type']) ?> flash-auto-hide mt-3">
            <?= e((string)$flash['msg']) ?>
          </div>
        <?php endif; ?>

        <div class="cardx mb-30 mt-3">
          <div class="head">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-users me-1"></i> Lista</div>
              <span class="badge-soft" id="countBadge"><?= count($rows) ?> fornecedores</span>
            </div>

            <div class="d-flex gap-2 flex-wrap align-items-center">
              <select class="form-select compact" id="fStatus" style="min-width: 160px;">
                <option value="">Status: Todos</option>
                <option value="ATIVO">Ativo</option>
                <option value="INATIVO">Inativo</option>
              </select>

              <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar" type="button">
                <i class="lni lni-eraser me-1"></i> Limpar
              </button>
            </div>
          </div>

          <div class="body">
            <div class="row g-2 mb-2">
              <div class="col-12 col-lg-6">
                <input class="form-control compact" id="qFor" placeholder="Buscar por nome, doc, telefone, e-mail..." />
              </div>
              <div class="col-12 col-lg-6 text-lg-end">
                <div class="muted">Atalho: <b>Enter</b> para buscar.</div>
              </div>
            </div>

            <div class="table-responsive">
              <table class="table text-nowrap" id="tbFor">
                <thead>
                  <tr>
                    <th style="min-width:80px;">ID</th>
                    <th style="min-width:260px;">Fornecedor</th>
                    <th style="min-width:150px;">Documento</th>
                    <th style="min-width:160px;">Telefone</th>
                    <th style="min-width:220px;">E-mail</th>
                    <th style="min-width:240px;">Cidade/UF</th>
                    <th style="min-width:120px;" class="text-center">Status</th>
                    <th style="min-width:180px;" class="text-center">Ações</th>
                  </tr>
                </thead>
                <tbody id="tbodyFor">
                  <?php if (!$rows): ?>
                    <tr>
                      <td colspan="8" class="text-center muted py-4">Nenhum fornecedor encontrado.</td>
                    </tr>
                    <?php else: foreach ($rows as $r): ?>
                      <?php
                      $id = (int)$r['id'];
                      $st = strtoupper((string)$r['status']) === 'INATIVO' ? 'INATIVO' : 'ATIVO';
                      $badge = $st === 'ATIVO'
                        ? '<span class="badge-soft badge-ok">ATIVO</span>'
                        : '<span class="badge-soft badge-off">INATIVO</span>';

                      $loc = trim((string)$r['cidade']);
                      $ufv = trim((string)$r['uf']);
                      $locTxt = ($loc || $ufv) ? ($loc . ($ufv ? " / " . $ufv : "")) : "—";
                      ?>
                      <tr data-statusrow="<?= e($st) ?>">
                        <td style="font-weight:1000;color:#0f172a;"><?= $id ?></td>
                        <td>
                          <div style="font-weight:1000;color:#0f172a;line-height:1.1;"><?= e((string)$r['nome']) ?></div>
                          <div class="muted"><?= e(trim((string)$r['contato']) ?: '—') ?></div>
                        </td>
                        <td><?= e(trim((string)$r['doc']) ?: '—') ?></td>
                        <td><?= e(trim((string)$r['tel']) ?: '—') ?></td>
                        <td><?= e(trim((string)$r['email']) ?: '—') ?></td>
                        <td><?= e($locTxt) ?></td>
                        <td class="text-center"><?= $badge ?></td>
                        <td class="text-center">
                          <a class="main-btn light-btn btn-hover btn-compact"
                            href="assets/dados/fornecedores/editarFornecedores.php?id=<?= $id ?>">
                            <i class="lni lni-pencil me-1"></i> Editar
                          </a>
                        </td>
                      </tr>
                  <?php endforeach;
                  endif; ?>
                </tbody>
              </table>
            </div>

            <div class="muted mt-2" id="hintEmpty" style="display:none;">Nenhum fornecedor encontrado.</div>
          </div>
        </div>
      </div>
    </section>

    <footer class="footer">
      <div class="container-fluid">
        <p class="text-sm">Painel da Distribuidora • <span class="text-gray">v1.0</span></p>
      </div>
    </footer>
  </main>

  <!-- MODAL: somente ADICIONAR -->
  <div class="modal fade" id="mdFornecedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" style="font-weight:1000;">Novo fornecedor</h5>
            <div class="muted">Preencha os dados abaixo.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <form action="assets/dados/fornecedores/adicionarFornecedores.php" method="post">
          <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
          <input type="hidden" name="redirect_to" value="../../../fornecedores.php">

          <div class="modal-body">
            <div class="row g-2">
              <div class="col-12 col-lg-8">
                <label class="form-label">Nome / Razão Social *</label>
                <input class="form-control compact" name="nome" placeholder="Ex: Distribuidora X LTDA" />
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">Status</label>
                <select class="form-select compact" name="status">
                  <option value="ATIVO" selected>Ativo</option>
                  <option value="INATIVO">Inativo</option>
                </select>
              </div>

              <div class="col-12 col-lg-4">
                <label class="form-label">CNPJ/CPF</label>
                <input class="form-control compact" name="doc" />
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">Telefone</label>
                <input class="form-control compact" name="tel" />
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">E-mail</label>
                <input class="form-control compact" name="email" />
              </div>

              <div class="col-12">
                <label class="form-label">Endereço</label>
                <input class="form-control compact" name="endereco" />
              </div>

              <div class="col-12 col-lg-6">
                <label class="form-label">Cidade</label>
                <input class="form-control compact" name="cidade" />
              </div>
              <div class="col-12 col-lg-2">
                <label class="form-label">UF</label>
                <input class="form-control compact" name="uf" maxlength="2" />
              </div>
              <div class="col-12 col-lg-4">
                <label class="form-label">Contato (Pessoa)</label>
                <input class="form-control compact" name="contato" />
              </div>

              <div class="col-12">
                <label class="form-label">Observação</label>
                <textarea class="form-control" name="obs" rows="3" style="border-radius:12px;"></textarea>
              </div>
            </div>
          </div>

          <div class="modal-footer d-flex justify-content-end gap-2">
            <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal" type="button">Cancelar</button>
            <button class="main-btn primary-btn btn-hover btn-compact" type="submit">
              <i class="lni lni-save me-1"></i> Salvar
            </button>
          </div>
        </form>

      </div>
    </div>
  </div>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    // flash some em 1.5s
    (function() {
      const box = document.getElementById('flashBox');
      if (!box) return;
      setTimeout(() => {
        box.classList.add('hide');
        setTimeout(() => box.remove(), 400);
      }, 1500);
    })();

    // filtro local
    const qFor = document.getElementById("qFor");
    const fStatus = document.getElementById("fStatus");
    const btnLimpar = document.getElementById("btnLimpar");
    const hintEmpty = document.getElementById("hintEmpty");
    const countBadge = document.getElementById("countBadge");
    const tbodyFor = document.getElementById("tbodyFor");

    function onlyStatus(s) {
      const v = String(s || "").trim().toUpperCase();
      return (v === "ATIVO" || v === "INATIVO") ? v : "";
    }

    function filterRows() {
      const q = (qFor.value || "").toLowerCase().trim();
      const st = onlyStatus(fStatus.value);
      let visible = 0;

      tbodyFor.querySelectorAll("tr").forEach(tr => {
        const statusRow = (tr.getAttribute("data-statusrow") || "").toUpperCase();
        const text = tr.innerText.toLowerCase();
        const okQ = !q || text.includes(q);
        const okS = !st || statusRow === st;
        const show = okQ && okS;
        tr.style.display = show ? "" : "none";
        if (show) visible++;
      });

      countBadge.textContent = `${visible} fornecedor(es)`;
      hintEmpty.style.display = (visible === 0) ? "block" : "none";
    }

    qFor.addEventListener("input", filterRows);
    qFor.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        filterRows();
      }
    });
    fStatus.addEventListener("change", filterRows);

    btnLimpar.addEventListener("click", () => {
      qFor.value = "";
      fStatus.value = "";
      filterRows();
    });

    filterRows();
  </script>
</body>

</html>