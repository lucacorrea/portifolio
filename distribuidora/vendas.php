<?php

declare(strict_types=1);

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/vendas/_helpers.php';

$pdo = db();

/* =========================
   Helpers locais (JSON)
========================= */
function json_out(array $data, int $code = 200): void
{
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/* =========================
   BUSCA PRODUTOS (COM "PARCIAL")
   - tenta: query completa
   - se não achar:
       - se for numérico: tira zeros à esquerda
       - vai cortando o final: 0005 -> 000 -> 00 (mín 2 chars)
========================= */
function buscar_produtos(PDO $pdo, string $q): array
{
  $q = trim($q);
  if ($q === '') return [];

  $candidates = [];
  $candidates[] = $q;

  // se for somente números, tenta remover zeros à esquerda (0005 -> 5)
  if (preg_match('/^\d+$/', $q)) {
    $noZeros = ltrim($q, '0');
    if ($noZeros !== '' && $noZeros !== $q) $candidates[] = $noZeros;
  }

  // fallback parcial: vai cortando no final até no mínimo 2 chars
  $len = mb_strlen($q);
  for ($i = $len - 1; $i >= 2; $i--) {
    $candidates[] = mb_substr($q, 0, $i);
    if (count($candidates) >= 6) break; // evita muitas tentativas
  }

  // remove duplicados
  $uniq = [];
  foreach ($candidates as $cand) {
    $cand = trim($cand);
    if ($cand === '') continue;
    $uniq[$cand] = true;
  }
  $candidates = array_keys($uniq);

  $sql = "
    SELECT id, codigo, nome, unidade, preco, estoque, imagem
    FROM produtos
    WHERE (status IS NULL OR status = '' OR UPPER(status) = 'ATIVO')
      AND (codigo LIKE :q OR nome LIKE :q)
    ORDER BY
      CASE
        WHEN codigo = :qExact THEN 0
        WHEN codigo LIKE :qStart THEN 1
        WHEN nome   LIKE :qStart THEN 2
        ELSE 3
      END,
      nome ASC
    LIMIT 30
  ";

  $st = $pdo->prepare($sql);

  foreach ($candidates as $cand) {
    $like = '%' . $cand . '%';
    $st->execute([
      ':q'      => $like,
      ':qExact' => $cand,
      ':qStart' => $cand . '%'
    ]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
    if (count($rows) > 0) return $rows; // ✅ achou: retorna já
  }

  return [];
}

/* =========================
   ENDPOINTS INTERNOS (AJAX) - DENTRO DO VENDAS.PHP
   - vendas.php?ajax=buscarProdutos&q=...
   - vendas.php?ajax=ultimasVendas
========================= */
if (isset($_GET['ajax'])) {
  $ajax = (string)$_GET['ajax'];

  try {
    if ($ajax === 'buscarProdutos') {
      $q = trim((string)($_GET['q'] ?? ''));
      if ($q === '') json_out(['ok' => true, 'items' => []]);

      $rows = buscar_produtos($pdo, $q);

      $items = array_map(static function (array $r): array {
        return [
          'id'    => (int)($r['id'] ?? 0),
          'code'  => (string)($r['codigo'] ?? ''),
          'name'  => (string)($r['nome'] ?? ''),
          'unit'  => (string)($r['unidade'] ?? ''),
          'price' => (float)($r['preco'] ?? 0),
          'stock' => (int)($r['estoque'] ?? 0),
          'img'   => (string)($r['imagem'] ?? ''),
        ];
      }, $rows);

      json_out(['ok' => true, 'items' => $items]);
    }

    if ($ajax === 'ultimasVendas') {
      try {
        $st = $pdo->query("SELECT id, data, total, created_at, canal FROM vendas ORDER BY id DESC LIMIT 10");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      } catch (Throwable $e) {
        json_out(['ok' => false, 'msg' => 'Tabela vendas não encontrada. Rode o SQL de criação da tabela vendas.'], 500);
      }

      $items = array_map(static function (array $r): array {
        return [
          'id'    => (int)($r['id'] ?? 0),
          'date'  => (string)($r['created_at'] ?? ''),
          'data'  => (string)($r['data'] ?? ''),
          'total' => (float)($r['total'] ?? 0),
          'canal' => (string)($r['canal'] ?? ''),
        ];
      }, $rows);

      $next = 1;
      try {
        $next = (int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM vendas")->fetchColumn();
      } catch (Throwable $e) {
        $next = 1;
      }

      json_out(['ok' => true, 'items' => $items, 'next' => $next]);
    }

    json_out(['ok' => false, 'msg' => 'Ação ajax inválida.'], 400);
  } catch (Throwable $e) {
    json_out(['ok' => false, 'msg' => $e->getMessage()], 500);
  }
}

/* =========================
   PÁGINA NORMAL (HTML)
========================= */
$csrf  = csrf_token();
$flash = flash_pop();

function fmtMoney($v): string
{
  return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

$nextNo = 1;
try {
  $nextNo = (int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM vendas")->fetchColumn();
} catch (Throwable $e) {
  $nextNo = 1;
}

$last = [];
try {
  $st = $pdo->query("SELECT id, data, total, created_at FROM vendas ORDER BY id DESC LIMIT 10");
  $last = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $last = [];
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="UTF-8" />
  <meta http-equiv="X-UA-Compatible" content="IE=edge" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="<?= e($csrf) ?>">
  <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
  <title>Painel da Distribuidora | Vendas (PDV)</title>

  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
    /* ===== (SEU CSS ORIGINAL) ===== */
    .profile-box .dropdown-menu {
      width: max-content;
      min-width: 260px;
      max-width: calc(100vw - 24px);
    }

    .main-btn.btn-compact {
      height: 38px !important;
      padding: 8px 14px !important;
      font-size: 13px !important;
      line-height: 1 !important;
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

    .pdv-row {
      align-items: stretch;
    }

    .pdv-left-col,
    .pdv-right-col {
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .pdv-card {
      border: 1px solid rgba(148, 163, 184, .28);
      border-radius: 16px;
      background: #fff;
      overflow: hidden;
    }

    .pdv-card .pdv-head {
      padding: 12px 14px;
      border-bottom: 1px solid rgba(148, 163, 184, .22);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }

    .pdv-card .pdv-body {
      padding: 14px;
    }

    .pdv-card.pdv-search {
      overflow: visible;
      position: relative;
      z-index: 50;
    }

    .pdv-card.items-card {
      flex: 1 1 auto;
      min-height: 520px;
      display: flex;
      flex-direction: column;
    }

    .pdv-card.items-card .pdv-body {
      flex: 1 1 auto;
      display: flex;
      flex-direction: column;
      min-height: 0;
    }

    .items-scroll {
      flex: 1 1 auto;
      min-height: 320px;
      overflow: auto;
      -webkit-overflow-scrolling: touch;
      border-radius: 12px;
    }

    .search-wrap {
      position: relative;
    }

    .suggest {
      position: absolute;
      z-index: 9999;
      left: 0;
      right: 0;
      top: calc(100% + 6px);
      background: #fff;
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      box-shadow: 0 10px 30px rgba(15, 23, 42, .10);
      max-height: 340px;
      overflow-y: auto;
      display: none;
    }

    .suggest .it {
      padding: 10px 12px;
      display: flex;
      align-items: center;
      gap: 10px;
      cursor: pointer;
    }

    .suggest .it:hover {
      background: rgba(241, 245, 249, .9);
    }

    .pimg {
      width: 38px;
      height: 38px;
      border-radius: 10px;
      object-fit: cover;
      border: 1px solid rgba(148, 163, 184, .30);
      background: #fff;
      flex: 0 0 auto;
    }

    .preview-box {
      width: 100%;
      height: 130px;
      border-radius: 16px;
      border: 1px dashed rgba(148, 163, 184, .55);
      background: rgba(248, 250, 252, .7);
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 10px;
      text-align: center;
    }

    .preview-box img {
      width: 86px;
      height: 86px;
      border-radius: 16px;
      object-fit: cover;
      border: 1px solid rgba(148, 163, 184, .30);
      background: #fff;
      margin-bottom: 6px;
    }

    .preview-name {
      font-weight: 900;
      font-size: 12px;
      color: #0f172a;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      max-width: 220px;
    }

    #tbItens {
      width: 100%;
      min-width: 720px;
    }

    #tbItens th,
    #tbItens td {
      white-space: nowrap !important;
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
      <a href="index.php" class="d-flex align-items-center gap-2">
        <img src="assets/images/logo/logo.svg" alt="logo" />
      </a>
    </div>
    <nav class="sidebar-nav">
      <ul>
        <li class="nav-item"><a href="index.php"><span class="text">Dashboard</span></a></li>

        <li class="nav-item nav-item-has-children active">
          <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="true">
            <span class="text">Operações</span>
          </a>
          <ul id="ddmenu_operacoes" class="collapse show dropdown-nav">
            <li><a href="pedidos.php">Pedidos</a></li>
            <li><a href="vendas.php" class="active">Vendas</a></li>
            <li><a href="devolucoes.php">Devoluções</a></li>
          </ul>
        </li>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
            <span class="text">Estoque</span>
          </a>
          <ul id="ddmenu_estoque" class="collapse dropdown-nav">
            <li><a href="produtos.php">Produtos</a></li>
            <li><a href="inventario.php">Inventário</a></li>
            <li><a href="entradas.php">Entradas</a></li>
            <li><a href="saidas.php">Saídas</a></li>
            <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
          </ul>
        </li>

        <li class="nav-item"><a href="relatorios.php"><span class="text">Relatórios</span></a></li>
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
                <form action="#">
                  <input type="text" placeholder="Atalho: F4 pesquisar..." id="qGlobal" />
                  <button type="submit" onclick="return false"><i class="lni lni-search-alt"></i></button>
                </form>
              </div>
            </div>
          </div>

          <div class="col-lg-7 col-md-7 col-6">
            <div class="header-right">
              <div class="profile-box ml-15">
                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile" data-bs-toggle="dropdown" aria-expanded="false">
                  <div class="profile-info">
                    <div class="info">
                      <div class="image"><img src="assets/images/profile/profile-image.png" alt="perfil" /></div>
                      <div>
                        <h6 class="fw-500">Administrador</h6>
                        <p>Distribuidora</p>
                      </div>
                    </div>
                  </div>
                </button>

                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile">
                  <li><a href="logout.php"><i class="lni lni-exit"></i> Sair</a></li>
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
            <div class="col-md-6">
              <div class="title">
                <h2>Terminal de Vendas (PDV)</h2>
                <div class="muted">Ponto de Venda & Checkout — <b>F4</b> pesquisar | <b>F2</b> confirmar</div>
              </div>
            </div>
          </div>
        </div>

        <?php if ($flash): ?>
          <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
        <?php endif; ?>

        <div class="row g-3 mb-30 pdv-row">
          <div class="col-12 col-lg-8">
            <div class="pdv-left-col">
              <div class="pdv-card pdv-search mb-3">
                <div class="pdv-body">
                  <div class="row g-3 align-items-stretch">
                    <div class="col-12 col-md-8">
                      <label class="form-label">Pesquisar Produto (F4)</label>
                      <div class="search-wrap">
                        <input class="form-control compact" id="qProd" placeholder="Nome ou código..." autocomplete="off" />
                        <div class="suggest" id="suggest"></div>
                      </div>
                      <div class="muted mt-2">Dica: digite e pressione <b>Enter</b> para adicionar o 1º resultado.</div>
                    </div>

                    <div class="col-12 col-md-4">
                      <label class="form-label">Imagem</label>
                      <div class="preview-box">
                        <div>
                          <img id="previewImg" alt="Prévia" />
                          <div class="preview-name" id="previewName">AGUARDANDO...</div>
                        </div>
                      </div>
                    </div>
                  </div>

                </div>
              </div>

              <div class="pdv-card items-card">
                <div class="pdv-head">
                  <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-cart me-1"></i> Itens da Venda</div>
                  <div class="d-flex gap-2 flex-wrap">
                    <button class="main-btn light-btn btn-hover btn-compact" id="btnLimpar" type="button">
                      <i class="lni lni-trash-can me-1"></i> Limpar
                    </button>
                  </div>
                </div>

                <div class="pdv-body">
                  <div class="items-scroll">
                    <div class="table-responsive">
                      <table class="table text-nowrap mb-0" id="tbItens">
                        <thead>
                          <tr>
                            <th style="min-width:70px;">Item</th>
                            <th style="min-width:320px;">Produto</th>
                            <th style="min-width:140px;">Qtd</th>
                            <th style="min-width:140px;" class="text-end">Unitário</th>
                            <th style="min-width:160px;" class="text-end">Subtotal</th>
                            <th style="min-width:120px;" class="text-center">Ações</th>
                          </tr>
                        </thead>
                        <tbody id="tbodyItens"></tbody>
                      </table>
                    </div>
                    <div class="muted p-3" id="hintEmpty" style="display:none;">Aguardando inclusão de produtos...</div>
                  </div>
                </div>
              </div>

            </div>
          </div>

          <div class="col-12 col-lg-4">
            <div class="pdv-right-col">
              <div class="pdv-card">
                <div class="checkout-head" style="background:#0b5ed7;color:#fff;padding:12px 14px;display:flex;justify-content:space-between;">
                  <h6 style="margin:0;font-weight:900;color:#fff;">Checkout</h6>
                  <span class="badge bg-light text-dark" id="saleNo">Venda #<?= (int)$nextNo ?></span>
                </div>

                <div class="checkout-body" style="padding:14px;">
                  <div class="mb-3">
                    <label class="form-label">Cliente</label>
                    <input class="form-control compact" id="cCliente" placeholder="CPF ou Nome (Opcional)" />
                  </div>

                  <div class="totals mb-3" style="border:1px solid rgba(148,163,184,.25);border-radius:14px;background:#fff;padding:12px;">
                    <div class="tot-row" style="display:flex;justify-content:space-between;font-weight:800;"><span>Subtotal</span><span id="tSub">R$ 0,00</span></div>
                    <div class="tot-row" style="display:flex;justify-content:space-between;font-weight:800;"><span>Desconto</span><span id="tDesc">- R$ 0,00</span></div>
                    <div class="tot-row" style="display:flex;justify-content:space-between;font-weight:800;"><span>Taxa entrega</span><span id="tEnt">R$ 0,00</span></div>
                    <hr>
                    <div style="display:flex;justify-content:space-between;align-items:baseline;">
                      <span style="font-weight:900;font-size:18px;">TOTAL</span>
                      <span style="font-weight:1000;font-size:26px;color:#0b5ed7;" id="tTotal">R$ 0,00</span>
                    </div>
                  </div>

                  <div class="d-grid gap-2">
                    <button class="main-btn primary-btn btn-hover btn-compact" id="btnConfirmar" type="button">
                      <i class="lni lni-checkmark-circle me-1"></i> CONFIRMAR VENDA (F2)
                    </button>
                    <div class="form-check">
                      <input class="form-check-input" type="checkbox" id="chkPrint" checked />
                      <label class="form-check-label" for="chkPrint">Imprimir cupom após confirmar</label>
                    </div>
                  </div>

                  <div class="last-box" style="border:1px solid rgba(148,163,184,.25);border-radius:14px;overflow:hidden;background:#fff;margin-top:12px;">
                    <div class="head" style="padding:10px 12px;border-bottom:1px solid rgba(148,163,184,.18);display:flex;justify-content:space-between;">
                      <div class="t" style="font-weight:900;font-size:12px;text-transform:uppercase;">Últimos cupons</div>
                      <button class="main-btn light-btn btn-hover btn-compact" id="btnRefreshLast" type="button" style="height:32px!important;padding:6px 10px!important;">
                        <i class="lni lni-reload"></i>
                      </button>
                    </div>
                    <div class="list" id="lastList" style="max-height:220px;overflow:auto;">
                      <?php if (!$last): ?>
                        <div class="cup" style="padding:10px 12px;">
                          <div>Sem cupons ainda</div>
                        </div>
                        <?php else: foreach ($last as $s): ?>
                          <div class="cup" style="padding:10px 12px;display:flex;justify-content:space-between;gap:10px;">
                            <div class="left">
                              <div style="font-weight:900;">Venda #<?= (int)$s['id'] ?></div>
                              <div style="color:#64748b;font-size:12px;"><?= e((string)$s['created_at']) ?></div>
                            </div>
                            <div class="right" style="text-align:right;">
                              <div style="font-weight:1000;color:#0b5ed7;"><?= e(fmtMoney((float)$s['total'])) ?></div>
                              <div style="font-weight:900;color:#16a34a;font-size:11px;">CONCLUÍDO</div>
                            </div>
                          </div>
                      <?php endforeach;
                      endif; ?>
                    </div>
                  </div>

                </div>
              </div>
            </div>
          </div>

        </div>
      </div>
    </section>
  </main>

  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    const AJAX_URL = "vendas.php";

    const DEFAULT_IMG = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(`
<svg xmlns="http://www.w3.org/2000/svg" width="120" height="120">
  <rect width="100%" height="100%" fill="#f1f5f9"/>
  <path d="M18 86l22-22 14 14 12-12 26 26" fill="none" stroke="#94a3b8" stroke-width="5" stroke-linecap="round" stroke-linejoin="round"/>
  <circle cx="42" cy="42" r="10" fill="#94a3b8"/>
  <text x="50%" y="92%" text-anchor="middle" font-family="Arial" font-size="12" fill="#64748b">Sem imagem</text>
</svg>
`);

    function safeText(s) {
      return String(s ?? "").replaceAll("&", "&amp;").replaceAll("<", "&lt;").replaceAll(">", "&gt;").replaceAll('"', "&quot;").replaceAll("'", "&#039;");
    }

    function numberToMoney(n) {
      const v = Number(n || 0);
      return "R$ " + v.toFixed(2).replace(".", ",");
    }

    function fetchJSON(url, opts = {}) {
      return fetch(url, opts).then(async (r) => {
        const data = await r.json().catch(() => ({}));
        if (!r.ok || data.ok === false) throw new Error(data.msg || "Erro na requisição.");
        return data;
      });
    }

    let CART = [];
    let LAST_SUGG = [];
    let searchTimer = null;
    let searchAbort = null;

    const qProd = document.getElementById("qProd");
    const suggest = document.getElementById("suggest");
    const qGlobal = document.getElementById("qGlobal");

    const previewImg = document.getElementById("previewImg");
    const previewName = document.getElementById("previewName");

    const tbodyItens = document.getElementById("tbodyItens");
    const hintEmpty = document.getElementById("hintEmpty");
    const btnLimpar = document.getElementById("btnLimpar");

    const saleNo = document.getElementById("saleNo");

    const tSub = document.getElementById("tSub");
    const tDesc = document.getElementById("tDesc");
    const tEnt = document.getElementById("tEnt");
    const tTotal = document.getElementById("tTotal");

    const btnConfirmar = document.getElementById("btnConfirmar");
    const chkPrint = document.getElementById("chkPrint");

    const lastList = document.getElementById("lastList");
    const btnRefreshLast = document.getElementById("btnRefreshLast");

    function setPreview(prod) {
      const img = (prod && prod.img) ? prod.img : DEFAULT_IMG;
      previewImg.src = img;
      previewName.textContent = prod ? prod.name : "AGUARDANDO...";
    }

    function showSuggest(list) {
      if (!list.length) {
        suggest.style.display = "none";
        suggest.innerHTML = "";
        return;
      }
      suggest.innerHTML = list.map(p => `
    <div class="it" data-id="${Number(p.id)}">
      <img class="pimg" src="${safeText(p.img || DEFAULT_IMG)}" alt="">
      <div class="meta">
        <div class="t" style="font-weight:900;font-size:13px;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${safeText(p.name)}</div>
        <div class="s" style="font-size:12px;color:#64748b;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${safeText(p.code)} • Estoque: ${Number(p.stock ?? 0)}</div>
      </div>
      <div class="price" style="font-weight:900;font-size:13px;color:#0f172a;white-space:nowrap;">${numberToMoney(p.price)}</div>
    </div>
  `).join("");
      suggest.style.display = "block";
      suggest.scrollTop = 0;
    }

    function hideSuggest() {
      suggest.style.display = "none";
      suggest.innerHTML = "";
    }

    function addToCart(prod) {
      if (!prod) return;
      const idx = CART.findIndex(x => x.product_id === prod.id);
      if (idx >= 0) CART[idx].qty += 1;
      else CART.push({
        product_id: prod.id,
        code: prod.code,
        name: prod.name,
        price: Number(prod.price || 0),
        img: prod.img || DEFAULT_IMG,
        qty: 1
      });
      setPreview(prod);
      renderCart();
      recalcAll();
    }

    function calcSubtotal() {
      return CART.reduce((a, it) => a + (Number(it.qty || 0) * Number(it.price || 0)), 0);
    }

    function renderCart() {
      tbodyItens.innerHTML = "";
      hintEmpty.style.display = CART.length ? "none" : "block";
      CART.forEach((it, i) => {
        const sub = Number(it.qty || 0) * Number(it.price || 0);
        tbodyItens.insertAdjacentHTML("beforeend", `
      <tr data-pid="${Number(it.product_id)}">
        <td>${i+1}</td>
        <td>
          <div class="d-flex align-items-center gap-2">
            <img class="pimg" src="${safeText(it.img||DEFAULT_IMG)}" alt="">
            <div style="min-width:0;">
              <div style="font-weight:1000;color:#0f172a;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:340px;">${safeText(it.name)}</div>
              <div class="muted">${safeText(it.code)}</div>
            </div>
          </div>
        </td>
        <td>${Number(it.qty||1)}</td>
        <td class="text-end">${numberToMoney(it.price)}</td>
        <td class="text-end" style="font-weight:1000;">${numberToMoney(sub)}</td>
        <td class="text-center">
          <button class="main-btn danger-btn-outline btn-hover btn-compact icon-btn btnRemove" type="button"><i class="lni lni-trash-can"></i></button>
        </td>
      </tr>
    `);
      });
    }

    function recalcAll() {
      const sub = calcSubtotal();
      tSub.textContent = numberToMoney(sub);
      tDesc.textContent = "- R$ 0,00";
      tEnt.textContent = "R$ 0,00";
      tTotal.textContent = numberToMoney(sub);
    }

    tbodyItens.addEventListener("click", (e) => {
      const tr = e.target.closest("tr");
      if (!tr) return;
      const pid = Number(tr.getAttribute("data-pid") || 0);
      if (!pid) return;
      if (e.target.closest(".btnRemove")) {
        CART = CART.filter(x => x.product_id !== pid);
        renderCart();
        recalcAll();
      }
    });

    btnLimpar.addEventListener("click", () => {
      if (CART.length && !confirm("Limpar todos os itens da venda?")) return;
      CART = [];
      renderCart();
      recalcAll();
      setPreview(null);
    });

    async function renderLastSales() {
      try {
        const r = await fetchJSON(`${AJAX_URL}?ajax=ultimasVendas`);
        const all = (r.items || []).slice(0, 10);
        if (!all.length) {
          lastList.innerHTML = `<div class="cup" style="padding:10px 12px;">Sem cupons ainda</div>`;
          return;
        }

        lastList.innerHTML = all.map(s => `
      <div class="cup" style="padding:10px 12px;display:flex;justify-content:space-between;gap:10px;cursor:pointer;" data-id="${Number(s.id)}">
        <div class="left">
          <div style="font-weight:900;">Venda #${Number(s.id)}</div>
          <div style="color:#64748b;font-size:12px;">${safeText(s.date||"")}</div>
        </div>
        <div class="right" style="text-align:right;">
          <div style="font-weight:1000;color:#0b5ed7;">${numberToMoney(s.total||0)}</div>
          <div style="font-weight:900;color:#16a34a;font-size:11px;">CONCLUÍDO</div>
        </div>
      </div>
    `).join("");

        if (r.next) saleNo.textContent = `Venda #${Number(r.next)}`;
      } catch (e) {
        console.error(e);
      }
    }

    lastList.addEventListener("click", (e) => {
      const cup = e.target.closest(".cup");
      if (!cup) return;
      const id = Number(cup.getAttribute("data-id") || 0);
      if (id > 0) window.open(`assets/dados/vendas/cupom.php?id=${id}`, "_blank");
    });

    async function searchProducts(q) {
      const s = String(q || "").trim();
      if (!s) return [];

      if (searchAbort) searchAbort.abort();
      searchAbort = new AbortController();

      const url = `${AJAX_URL}?ajax=buscarProdutos&q=` + encodeURIComponent(s);
      const r = await fetch(url, {
        signal: searchAbort.signal
      });
      const data = await r.json().catch(() => ({}));
      if (!r.ok || data.ok === false) return [];

      return (data.items || []).map(p => ({
        id: Number(p.id),
        code: String(p.code || ""),
        name: String(p.name || ""),
        price: Number(p.price || 0),
        stock: Number(p.stock || 0),
        img: (p.img && String(p.img).trim() !== "") ? String(p.img) : DEFAULT_IMG
      }));
    }

    function refreshSuggestDebounced() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(async () => {
        try {
          LAST_SUGG = await searchProducts(qProd.value);
          showSuggest(LAST_SUGG);
        } catch {
          LAST_SUGG = [];
          showSuggest([]);
        }
      }, 120);
    }

    qProd.addEventListener("input", refreshSuggestDebounced);
    qProd.addEventListener("focus", refreshSuggestDebounced);

    qProd.addEventListener("keydown", async (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        if (!LAST_SUGG.length) LAST_SUGG = await searchProducts(qProd.value);
        if (LAST_SUGG.length) {
          addToCart(LAST_SUGG[0]);
          qProd.value = "";
          hideSuggest();
        }
      }
      if (e.key === "Escape") hideSuggest();
    });

    suggest.addEventListener("click", (e) => {
      const it = e.target.closest(".it");
      if (!it) return;
      const id = Number(it.getAttribute("data-id") || 0);
      const prod = LAST_SUGG.find(p => p.id === id);
      addToCart(prod);
      qProd.value = "";
      hideSuggest();
      qProd.focus();
    });

    document.addEventListener("click", (e) => {
      if (!e.target.closest(".search-wrap")) hideSuggest();
    });

    document.addEventListener("keydown", (e) => {
      if (e.key === "F4") {
        e.preventDefault();
        qProd.focus();
        return;
      }
    });

    function init() {
      setPreview(null);
      renderCart();
      recalcAll();
      renderLastSales();
      qGlobal.addEventListener("input", () => {
        qProd.value = qGlobal.value;
        refreshSuggestDebounced();
      });
      btnRefreshLast.addEventListener("click", renderLastSales);
    }
    init();
  </script>
</body>

</html>