<?php

declare(strict_types=1);
session_start();

/**
 * =========================
 * CONEXÃO EXTERNA (PDO)
 * =========================
 * Esperado: assets/php/conexao.php com função:
 *   function db(): PDO { ... }
 */
require_once __DIR__ . '/assets/php/conexao.php';

function pdo(): PDO
{
  $pdo = db();
  if (!$pdo instanceof PDO) {
    throw new RuntimeException("A função db() não retornou um PDO.");
  }
  return $pdo;
}

/** Helpers */
function json_out(array $data, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function only_status(string $s): string
{
  $v = strtoupper(trim($s));
  return ($v === 'ATIVO' || $v === 'INATIVO') ? $v : '';
}
function csrf_token(): string
{
  if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
  }
  return $_SESSION['csrf_token'];
}
function csrf_ok(?string $t): bool
{
  return is_string($t) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $t);
}
function read_json_body(): array
{
  $raw = file_get_contents('php://input');
  $j = json_decode($raw ?: '[]', true);
  return is_array($j) ? $j : [];
}
function norm_row(array $x): array
{
  return [
    'nome'     => trim((string)($x['nome'] ?? '')),
    'status'   => only_status((string)($x['status'] ?? 'ATIVO')) ?: 'ATIVO',
    'doc'      => trim((string)($x['doc'] ?? '')),
    'tel'      => trim((string)($x['tel'] ?? '')),
    'email'    => trim((string)($x['email'] ?? '')),
    'endereco' => trim((string)($x['endereco'] ?? '')),
    'cidade'   => trim((string)($x['cidade'] ?? '')),
    'uf'       => strtoupper(substr(trim((string)($x['uf'] ?? '')), 0, 2)),
    'contato'  => trim((string)($x['contato'] ?? '')),
    'obs'      => trim((string)($x['obs'] ?? '')),
  ];
}

/**
 * =========================
 * API (mesmo arquivo)
 * =========================
 * Endpoints:
 *  fornecedores.php?api=list
 *  fornecedores.php?api=add
 *  fornecedores.php?api=edit
 *  fornecedores.php?api=delete
 *  fornecedores.php?api=export
 *  fornecedores.php?api=import
 */
if (isset($_GET['api'])) {
  try {
    $api = (string)$_GET['api'];
    $pdo = pdo();

    if ($api === 'list') {
      $stmt = $pdo->query("SELECT id, nome, status, doc, tel, email, endereco, cidade, uf, contato, obs FROM fornecedores ORDER BY id DESC LIMIT 2000");
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
      json_out(['ok' => true, 'data' => $rows]);
    }

    if ($api === 'add') {
      $body = read_json_body();
      if (!csrf_ok($body['csrf'] ?? null)) json_out(['ok' => false, 'msg' => 'CSRF inválido. Recarregue a página.'], 403);

      $r = norm_row($body);
      if ($r['nome'] === '') json_out(['ok' => false, 'msg' => 'Informe o nome / razão social.'], 400);

      $st = $pdo->prepare("INSERT INTO fornecedores (nome,status,doc,tel,email,endereco,cidade,uf,contato,obs) VALUES (?,?,?,?,?,?,?,?,?,?)");
      $st->execute([$r['nome'], $r['status'], $r['doc'], $r['tel'], $r['email'], $r['endereco'], $r['cidade'], $r['uf'], $r['contato'], $r['obs']]);
      $id = (int)$pdo->lastInsertId();

      json_out(['ok' => true, 'id' => $id]);
    }

    if ($api === 'edit') {
      $body = read_json_body();
      if (!csrf_ok($body['csrf'] ?? null)) json_out(['ok' => false, 'msg' => 'CSRF inválido. Recarregue a página.'], 403);

      $id = (int)($body['id'] ?? 0);
      if ($id <= 0) json_out(['ok' => false, 'msg' => 'ID inválido.'], 400);

      $r = norm_row($body);
      if ($r['nome'] === '') json_out(['ok' => false, 'msg' => 'Informe o nome / razão social.'], 400);

      $st = $pdo->prepare("UPDATE fornecedores SET nome=?, status=?, doc=?, tel=?, email=?, endereco=?, cidade=?, uf=?, contato=?, obs=? WHERE id=?");
      $st->execute([$r['nome'], $r['status'], $r['doc'], $r['tel'], $r['email'], $r['endereco'], $r['cidade'], $r['uf'], $r['contato'], $r['obs'], $id]);

      json_out(['ok' => true]);
    }

    if ($api === 'delete') {
      $body = read_json_body();
      if (!csrf_ok($body['csrf'] ?? null)) json_out(['ok' => false, 'msg' => 'CSRF inválido. Recarregue a página.'], 403);

      $id = (int)($body['id'] ?? 0);
      if ($id <= 0) json_out(['ok' => false, 'msg' => 'ID inválido.'], 400);

      $st = $pdo->prepare("DELETE FROM fornecedores WHERE id=?");
      $st->execute([$id]);

      json_out(['ok' => true]);
    }

    if ($api === 'export') {
      $stmt = $pdo->query("SELECT id, nome, status, doc, tel, email, endereco, cidade, uf, contato, obs, created_at, updated_at FROM fornecedores ORDER BY id DESC");
      $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

      header('Content-Type: application/json; charset=utf-8');
      header('Content-Disposition: attachment; filename="fornecedores.json"');
      echo json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
      exit;
    }

    if ($api === 'import') {
      // multipart/form-data: arquivo + csrf
      $csrf = (string)($_POST['csrf'] ?? '');
      if (!csrf_ok($csrf)) json_out(['ok' => false, 'msg' => 'CSRF inválido. Recarregue a página.'], 403);

      if (empty($_FILES['arquivo']['tmp_name'])) json_out(['ok' => false, 'msg' => 'Nenhum arquivo enviado.'], 400);

      $raw = file_get_contents($_FILES['arquivo']['tmp_name']);
      $arr = json_decode($raw ?: '[]', true);
      if (!is_array($arr)) json_out(['ok' => false, 'msg' => 'JSON inválido (esperado um array).'], 400);

      $pdo->beginTransaction();

      $ins = $pdo->prepare("INSERT INTO fornecedores (nome,status,doc,tel,email,endereco,cidade,uf,contato,obs) VALUES (?,?,?,?,?,?,?,?,?,?)");
      $upd = $pdo->prepare("UPDATE fornecedores SET nome=?, status=?, doc=?, tel=?, email=?, endereco=?, cidade=?, uf=?, contato=?, obs=? WHERE id=?");
      $chk = $pdo->prepare("SELECT id FROM fornecedores WHERE id=?");

      $ok = 0;
      try {
        foreach ($arr as $x) {
          if (!is_array($x)) continue;

          $id = (int)($x['id'] ?? 0);
          $r = norm_row($x);
          if ($r['nome'] === '') continue;

          if ($id > 0) {
            $chk->execute([$id]);
            $exists = (bool)$chk->fetchColumn();
            if ($exists) {
              $upd->execute([$r['nome'], $r['status'], $r['doc'], $r['tel'], $r['email'], $r['endereco'], $r['cidade'], $r['uf'], $r['contato'], $r['obs'], $id]);
              $ok++;
              continue;
            }
          }

          $ins->execute([$r['nome'], $r['status'], $r['doc'], $r['tel'], $r['email'], $r['endereco'], $r['cidade'], $r['uf'], $r['contato'], $r['obs']]);
          $ok++;
        }

        $pdo->commit();
        json_out(['ok' => true, 'msg' => "Importado: {$ok} registro(s)."]);
      } catch (Throwable $e) {
        $pdo->rollBack();
        json_out(['ok' => false, 'msg' => 'Falha ao importar: ' . $e->getMessage()], 500);
      }
    }

    json_out(['ok' => false, 'msg' => 'API inválida.'], 404);
  } catch (Throwable $e) {
    json_out(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
  }
}

// token para o front
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />

  <meta name="csrf-token" content="<?= htmlspecialchars($csrf, ENT_QUOTES) ?>">

  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Fornecedores</title>

  <!-- ========== CSS ========= -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    /* dropdown do profile: largura acompanha conteúdo */
    .profile-box .dropdown-menu {
      width: max-content;
      min-width: 260px;
      max-width: calc(100vw - 24px);
    }

    .profile-box .dropdown-menu .author-info {
      width: max-content;
      max-width: 100%;
      display: flex !important;
      align-items: center;
      gap: 10px;
    }

    .profile-box .dropdown-menu .author-info .content {
      min-width: 0;
      max-width: 100%;
    }

    .profile-box .dropdown-menu .author-info .content a {
      display: inline-block;
      white-space: nowrap;
      max-width: 100%;
    }

    /* Botões compactos */
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

    .icon-btn {
      height: 34px !important;
      width: 42px !important;
      padding: 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
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

    /* Cards */
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

    /* Tabela */
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

    .link-mini {
      font-weight: 900;
      font-size: 12px;
      text-decoration: none;
    }

    .link-mini:hover {
      text-decoration: underline;
    }

    /* Modal */
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

  <!-- ======== sidebar-nav start =========== -->
  <aside class="sidebar-nav-wrapper">
    <div class="navbar-logo">
      <a href="index.html" class="d-flex align-items-center gap-2">
        <img src="assets/images/logo/logo.svg" alt="logo" />
      </a>
    </div>

    <nav class="sidebar-nav">
      <ul>
        <li class="nav-item">
          <a href="index.html">
            <span class="text">Dashboard</span>
          </a>
        </li>

        <li class="nav-item nav-item-has-children active">
          <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros"
            aria-expanded="true">
            <span class="text">Cadastros</span>
          </a>
          <ul id="ddmenu_cadastros" class="collapse show dropdown-nav">
            <li><a href="clientes.html">Clientes</a></li>
            <li><a href="fornecedores.php" class="active">Fornecedores</a></li>
            <li><a href="categorias.html">Categorias</a></li>
          </ul>
        </li>

        <li class="nav-item"><a href="relatorios.html"><span class="text">Relatórios</span></a></li>
        <span class="divider">
          <hr />
        </span>
        <li class="nav-item"><a href="suporte.html"><span class="text">Suporte</span></a></li>
      </ul>
    </nav>
  </aside>

  <div class="overlay"></div>

  <main class="main-wrapper">
    <header class="header">
      <div class="container-fluid">
        <div class="row">
          <div class="col-lg-5 col-md-5 col-6">
            <div class="header-left d-flex align-items-center">
              <div class="menu-toggle-btn mr-15">
                <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-compact" type="button">
                  <i class="lni lni-chevron-left me-2"></i> Menu
                </button>
              </div>
              <div class="header-search d-none d-md-flex">
                <form action="#" onsubmit="return false;">
                  <input type="text" placeholder="Buscar fornecedor..." id="qGlobal" />
                  <button type="submit"><i class="lni lni-search-alt"></i></button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-lg-7 col-md-7 col-6">
            <div class="header-right">
              <div class="profile-box ml-15">
                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile"
                  data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="profile-info">
                    <div class="info">
                      <div class="image">
                        <img src="assets/images/profile/profile-image.png" alt="perfil" />
                      </div>
                      <div>
                        <h6 class="fw-500">Administrador</h6>
                        <p>Distribuidora</p>
                      </div>
                    </div>
                  </div>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile">
                  <li><a href="perfil.html"><i class="lni lni-user"></i> Meu Perfil</a></li>
                  <li><a href="usuarios.html"><i class="lni lni-cog"></i> Usuários</a></li>
                  <li class="divider"></li>
                  <li><a href="logout.html"><i class="lni lni-exit"></i> Sair</a></li>
                </ul>
              </div>
            </div>
          </div>

        </div>
      </div>
    </header>

    <section class="section">
      <div class="container-fluid">
        <div class="title-wrapper pt-30">
          <div class="row align-items-center">
            <div class="col-md-8">
              <div class="title">
                <h2>Fornecedores</h2>
                <div class="muted">Cadastro no banco (PDO) no próprio fornecedores.php?api=...</div>
              </div>
            </div>
            <div class="col-md-4 text-md-end">
              <button class="main-btn primary-btn btn-hover btn-compact" id="btnNovo" type="button">
                <i class="lni lni-plus me-1"></i> Novo fornecedor
              </button>
            </div>
          </div>
        </div>

        <!-- LISTA -->
        <div class="cardx mb-30">
          <div class="head">
            <div class="d-flex align-items-center gap-2 flex-wrap">
              <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-users me-1"></i> Lista</div>
              <span class="badge-soft" id="countBadge">0 fornecedores</span>
            </div>

            <div class="d-flex gap-2 flex-wrap align-items-center">
              <select class="form-select compact" id="fStatus" style="min-width: 160px;">
                <option value="">Status: Todos</option>
                <option value="ATIVO">Ativo</option>
                <option value="INATIVO">Inativo</option>
              </select>

              <button class="main-btn light-btn btn-hover btn-compact" id="btnExport" type="button">
                <i class="lni lni-download me-1"></i> Exportar (JSON)
              </button>

              <label class="main-btn light-btn btn-hover btn-compact" style="margin:0; cursor:pointer;">
                <i class="lni lni-upload me-1"></i> Importar
                <input type="file" id="fileImport" accept="application/json" hidden />
              </label>

              <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar" type="button">
                <i class="lni lni-eraser me-1"></i> Limpar
              </button>
            </div>
          </div>

          <div class="body">
            <div class="row g-2 mb-2">
              <div class="col-12 col-lg-6">
                <input class="form-control compact" id="qFor" placeholder="Buscar por nome, CNPJ/CPF, telefone, e-mail..." />
              </div>
              <div class="col-12 col-lg-6 text-lg-end">
                <div class="muted">Atalho: <b>Enter</b> para buscar • Clique em <b>Editar</b> para alterar.</div>
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
                    <th style="min-width:160px;" class="text-center">Ações</th>
                  </tr>
                </thead>
                <tbody id="tbodyFor"></tbody>
              </table>
            </div>

            <div class="muted mt-2" id="hintEmpty" style="display:none;">Nenhum fornecedor encontrado.</div>
          </div>
        </div>

      </div>
    </section>

    <footer class="footer">
      <div class="container-fluid">
        <div class="row">
          <div class="col-md-6 order-last order-md-first">
            <div class="copyright text-center text-md-start">
              <p class="text-sm">Painel da Distribuidora • <span class="text-gray">v1.0</span></p>
            </div>
          </div>
        </div>
      </div>
    </footer>
  </main>

  <!-- MODAL: Fornecedor -->
  <div class="modal fade" id="mdFornecedor" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <div>
            <h5 class="modal-title" id="mdTitle" style="font-weight:1000;">Novo fornecedor</h5>
            <div class="muted" id="mdSub">Preencha os dados abaixo.</div>
          </div>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <input type="hidden" id="fId" />

          <div class="row g-2">
            <div class="col-12 col-lg-8">
              <label class="form-label">Nome / Razão Social *</label>
              <input class="form-control compact" id="fNome" placeholder="Ex: Distribuidora X LTDA" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">Status</label>
              <select class="form-select compact" id="fStatusEdit">
                <option value="ATIVO" selected>Ativo</option>
                <option value="INATIVO">Inativo</option>
              </select>
            </div>

            <div class="col-12 col-lg-4">
              <label class="form-label">CNPJ/CPF</label>
              <input class="form-control compact" id="fDoc" placeholder="00.000.000/0000-00" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">Telefone</label>
              <input class="form-control compact" id="fTel" placeholder="(92) 9xxxx-xxxx" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">E-mail</label>
              <input class="form-control compact" id="fEmail" placeholder="contato@fornecedor.com" />
            </div>

            <div class="col-12">
              <label class="form-label">Endereço</label>
              <input class="form-control compact" id="fEnd" placeholder="Rua, nº, bairro, referência..." />
            </div>

            <div class="col-12 col-lg-6">
              <label class="form-label">Cidade</label>
              <input class="form-control compact" id="fCidade" placeholder="Coari" />
            </div>
            <div class="col-12 col-lg-2">
              <label class="form-label">UF</label>
              <input class="form-control compact" id="fUF" placeholder="AM" maxlength="2" />
            </div>
            <div class="col-12 col-lg-4">
              <label class="form-label">Contato (Pessoa)</label>
              <input class="form-control compact" id="fContato" placeholder="Nome do contato" />
            </div>

            <div class="col-12">
              <label class="form-label">Observação</label>
              <textarea class="form-control" id="fObs" rows="3" placeholder="Opcional..."
                style="border-radius:12px;"></textarea>
            </div>
          </div>
        </div>

        <div class="modal-footer d-flex justify-content-between">
          <button class="main-btn danger-btn-outline btn-hover btn-compact" id="btnExcluir" type="button" style="display:none;">
            <i class="lni lni-trash-can me-1"></i> Excluir
          </button>
          <div class="d-flex gap-2">
            <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal" type="button">
              Cancelar
            </button>
            <button class="main-btn primary-btn btn-hover btn-compact" id="btnSalvar" type="button">
              <i class="lni lni-save me-1"></i> Salvar
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>

  <!-- ========= JS ========= -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    // ==============================
    // CONFIG
    // ==============================
    const CSRF = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

    function safeText(s) {
      return String(s ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    }

    function onlyStatus(s) {
      const v = String(s || "").trim().toUpperCase();
      return (v === "ATIVO" || v === "INATIVO") ? v : "";
    }

    async function api(apiName, opts = {}) {
      const url = `fornecedores.php?api=${encodeURIComponent(apiName)}`;
      const res = await fetch(url, opts);
      let json = null;
      try {
        json = await res.json();
      } catch {}
      if (!res.ok) {
        const msg = (json && json.msg) ? json.msg : ("Erro HTTP " + res.status);
        throw new Error(msg);
      }
      if (json && json.ok === false) {
        throw new Error(json.msg || "Erro na API");
      }
      return json;
    }

    function matches(row, q) {
      const s = String(q || "").toLowerCase().trim();
      if (!s) return true;
      const blob = `${row.id} ${row.nome} ${row.doc} ${row.tel} ${row.email} ${row.endereco} ${row.cidade} ${row.uf} ${row.contato} ${row.status}`.toLowerCase();
      return blob.includes(s);
    }

    // ==============================
    // DOM
    // ==============================
    const qGlobal = document.getElementById("qGlobal");
    const qFor = document.getElementById("qFor");
    const fStatus = document.getElementById("fStatus");

    const tbodyFor = document.getElementById("tbodyFor");
    const hintEmpty = document.getElementById("hintEmpty");
    const countBadge = document.getElementById("countBadge");

    const btnNovo = document.getElementById("btnNovo");
    const btnExport = document.getElementById("btnExport");
    const btnLimpar = document.getElementById("btnLimpar");
    const fileImport = document.getElementById("fileImport");

    // modal
    const mdEl = document.getElementById("mdFornecedor");
    const md = new bootstrap.Modal(mdEl);

    const mdTitle = document.getElementById("mdTitle");
    const mdSub = document.getElementById("mdSub");

    const fId = document.getElementById("fId");
    const fNome = document.getElementById("fNome");
    const fStatusEdit = document.getElementById("fStatusEdit");
    const fDoc = document.getElementById("fDoc");
    const fTel = document.getElementById("fTel");
    const fEmail = document.getElementById("fEmail");
    const fEnd = document.getElementById("fEnd");
    const fCidade = document.getElementById("fCidade");
    const fUF = document.getElementById("fUF");
    const fContato = document.getElementById("fContato");
    const fObs = document.getElementById("fObs");

    const btnSalvar = document.getElementById("btnSalvar");
    const btnExcluir = document.getElementById("btnExcluir");

    // ==============================
    // Estado
    // ==============================
    let ALL = [];
    let VIEW = [];

    async function fetchAllFromServer() {
      countBadge.textContent = "Carregando...";
      const json = await api("list", {
        method: "GET"
      });
      ALL = (json.data || []).map(x => ({
        id: String(x.id ?? "").trim(),
        nome: String(x.nome ?? "").trim(),
        doc: String(x.doc ?? "").trim(),
        tel: String(x.tel ?? "").trim(),
        email: String(x.email ?? "").trim(),
        endereco: String(x.endereco ?? "").trim(),
        cidade: String(x.cidade ?? "").trim(),
        uf: String(x.uf ?? "").trim().toUpperCase(),
        contato: String(x.contato ?? "").trim(),
        obs: String(x.obs ?? "").trim(),
        status: onlyStatus(x.status) || "ATIVO"
      })).filter(x => x.nome);
    }

    function render() {
      const q = (qFor.value || qGlobal.value || "").trim();
      const st = onlyStatus(fStatus.value);

      VIEW = ALL
        .filter(r => matches(r, q))
        .filter(r => st ? r.status === st : true);

      countBadge.textContent = `${VIEW.length} fornecedor(es)`;
      tbodyFor.innerHTML = "";

      if (!VIEW.length) {
        hintEmpty.style.display = "block";
        return;
      }
      hintEmpty.style.display = "none";

      VIEW.forEach((r) => {
        const statusBadge = r.status === "ATIVO" ?
          `<span class="badge-soft badge-ok">ATIVO</span>` :
          `<span class="badge-soft badge-off">INATIVO</span>`;

        const loc = [r.cidade, r.uf].filter(Boolean).join(" / ") || "—";

        tbodyFor.insertAdjacentHTML("beforeend", `
          <tr data-id="${safeText(r.id)}">
            <td style="font-weight:1000;color:#0f172a;">${safeText(r.id)}</td>
            <td>
              <div style="font-weight:1000;color:#0f172a;line-height:1.1;">${safeText(r.nome)}</div>
              <div class="muted">${safeText(r.contato || "—")}</div>
            </td>
            <td>${safeText(r.doc || "—")}</td>
            <td>${safeText(r.tel || "—")}</td>
            <td>
              ${r.email ? `<a class="link-mini" href="mailto:${safeText(r.email)}">${safeText(r.email)}</a>` : "—"}
            </td>
            <td>${safeText(loc)}</td>
            <td class="text-center">${statusBadge}</td>
            <td class="text-center">
              <button class="main-btn light-btn btn-hover btn-compact" type="button" data-act="edit">
                <i class="lni lni-pencil me-1"></i> Editar
              </button>
            </td>
          </tr>
        `);
      });
    }

    function openNew() {
      mdTitle.textContent = "Novo fornecedor";
      mdSub.textContent = "Preencha os dados abaixo.";
      btnExcluir.style.display = "none";

      fId.value = "";
      fNome.value = "";
      fStatusEdit.value = "ATIVO";
      fDoc.value = "";
      fTel.value = "";
      fEmail.value = "";
      fEnd.value = "";
      fCidade.value = "";
      fUF.value = "";
      fContato.value = "";
      fObs.value = "";

      md.show();
      setTimeout(() => fNome.focus(), 150);
    }

    function openEdit(id) {
      const r = ALL.find(x => String(x.id) === String(id));
      if (!r) return;

      mdTitle.textContent = "Editar fornecedor";
      mdSub.textContent = "Altere e salve.";
      btnExcluir.style.display = "inline-flex";

      fId.value = r.id;
      fNome.value = r.nome;
      fStatusEdit.value = r.status;
      fDoc.value = r.doc || "";
      fTel.value = r.tel || "";
      fEmail.value = r.email || "";
      fEnd.value = r.endereco || "";
      fCidade.value = r.cidade || "";
      fUF.value = r.uf || "";
      fContato.value = r.contato || "";
      fObs.value = r.obs || "";

      md.show();
      setTimeout(() => fNome.focus(), 150);
    }

    async function saveForm() {
      const nome = String(fNome.value || "").trim();
      if (!nome) {
        alert("Informe o nome / razão social.");
        fNome.focus();
        return;
      }

      const payload = {
        csrf: CSRF,
        id: String(fId.value || "").trim(),
        nome,
        status: onlyStatus(fStatusEdit.value) || "ATIVO",
        doc: String(fDoc.value || "").trim(),
        tel: String(fTel.value || "").trim(),
        email: String(fEmail.value || "").trim(),
        endereco: String(fEnd.value || "").trim(),
        cidade: String(fCidade.value || "").trim(),
        uf: String(fUF.value || "").trim().toUpperCase(),
        contato: String(fContato.value || "").trim(),
        obs: String(fObs.value || "").trim()
      };

      try {
        if (!payload.id) {
          await api("add", {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
          });
        } else {
          await api("edit", {
            method: "POST",
            headers: {
              "Content-Type": "application/json"
            },
            body: JSON.stringify(payload)
          });
        }

        await fetchAllFromServer();
        render();
        md.hide();
        alert("Fornecedor salvo!");
      } catch (e) {
        alert("Erro: " + (e && e.message ? e.message : e));
      }
    }

    async function removeCurrent() {
      const id = String(fId.value || "").trim();
      if (!id) return;

      const r = ALL.find(x => String(x.id) === String(id));
      if (!r) return;

      if (!confirm(`Excluir o fornecedor "${r.nome}"?`)) return;

      try {
        await api("delete", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify({
            csrf: CSRF,
            id: id
          })
        });

        await fetchAllFromServer();
        render();
        md.hide();
        alert("Fornecedor excluído.");
      } catch (e) {
        alert("Erro: " + (e && e.message ? e.message : e));
      }
    }

    function exportJson() {
      window.location.href = "fornecedores.php?api=export";
    }

    async function importJson(file) {
      if (!file) return;
      const fd = new FormData();
      fd.append("arquivo", file);
      fd.append("csrf", CSRF);

      try {
        const res = await fetch("fornecedores.php?api=import", {
          method: "POST",
          body: fd
        });
        const json = await res.json();
        if (!res.ok || json.ok === false) throw new Error(json.msg || "Falha ao importar");

        await fetchAllFromServer();
        render();
        alert(json.msg || "Importado com sucesso!");
      } catch (e) {
        alert("Erro: " + (e && e.message ? e.message : e));
      } finally {
        fileImport.value = "";
      }
    }

    function clearFilters() {
      qFor.value = "";
      qGlobal.value = "";
      fStatus.value = "";
      render();
    }

    // ==============================
    // Eventos
    // ==============================
    btnNovo.addEventListener("click", openNew);
    btnSalvar.addEventListener("click", saveForm);
    btnExcluir.addEventListener("click", removeCurrent);

    btnExport.addEventListener("click", exportJson);
    fileImport.addEventListener("change", (e) => importJson(e.target.files && e.target.files[0]));

    btnLimpar.addEventListener("click", clearFilters);

    qFor.addEventListener("input", render);
    qFor.addEventListener("keydown", (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        render();
      }
    });

    qGlobal.addEventListener("input", () => {
      qFor.value = qGlobal.value;
      render();
    });

    fStatus.addEventListener("change", render);

    tbodyFor.addEventListener("click", (e) => {
      const btn = e.target.closest("[data-act]");
      if (!btn) return;
      const tr = e.target.closest("tr");
      if (!tr) return;
      const id = tr.getAttribute("data-id");
      if (!id) return;

      if (btn.getAttribute("data-act") === "edit") openEdit(id);
    });

    // ==============================
    // Init
    // ==============================
    (async function init() {
      await fetchAllFromServer();
      render();
    })();
  </script>
</body>

</html>