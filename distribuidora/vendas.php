<?php

declare(strict_types=1);

/**
 * vendas.php (PDV)
 * - HTML normal
 * - AJAX interno SOMENTE para "ultimasVendas" (refresh dos cupons)
 * - Busca de produtos: SEM AJAX, SEM ENTER pra buscar (filtra LOCAL no JS enquanto digita)
 */

// ✅ BLINDA: evita “JSON quebrado” por warnings/avisos
@ini_set('display_errors', '0');
@error_reporting(0);
if (function_exists('ob_start')) {
  @ob_start();
}

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/vendas/_helpers.php';

$pdo = db();

/* =========================
   JSON (limpa qualquer saída)
========================= */
function json_out(array $data, int $code = 200): void
{
  if (function_exists('ob_get_length') && ob_get_length()) {
    @ob_clean();
  }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
  echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

/* =========================
   Monta URL da imagem
   - banco salva: images/xxx.png
   - precisa virar: assets/dados/produtos/images/xxx.png
========================= */
function img_url(string $raw): string
{
  $raw = trim($raw);
  if ($raw === '') return '';

  // normaliza \ do windows
  $raw = str_replace('\\', '/', $raw);

  if (preg_match('~^https?://~i', $raw)) return $raw;

  $raw = ltrim($raw, '/');

  // se já veio com assets..., não duplica
  if (strpos($raw, 'assets/') === 0) return $raw;

  // base onde ficam as imagens
  $base = 'assets/dados/produtos/';

  return rtrim($base, '/') . '/' . $raw; // images/xxx -> assets/dados/produtos/images/xxx
}

/* =========================
   ENDPOINT INTERNO (AJAX)
   - só pra "ultimasVendas"
========================= */
if (isset($_GET['ajax'])) {
  $ajax = (string)($_GET['ajax'] ?? '');

  try {
    if ($ajax === 'ultimasVendas') {
      try {
        $st = $pdo->query("SELECT id, total, created_at, canal FROM vendas ORDER BY id DESC LIMIT 10");
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];
      } catch (Throwable $e) {
        json_out(['ok' => false, 'msg' => 'Tabela vendas não encontrada. Rode o SQL de criação da tabela vendas.'], 500);
      }

      $items = array_map(static function (array $r): array {
        return [
          'id'    => (int)($r['id'] ?? 0),
          'date'  => (string)($r['created_at'] ?? ''),
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
   CARREGA PRODUTOS 1x (SEM AJAX)
   - Filtra no JS enquanto digita
   - Ajuste LIMIT se tiver MUITO produto
========================= */
$PRODUTOS_CACHE = [];
try {
  $stP = $pdo->query("
    SELECT id, codigo, nome, unidade, preco, estoque, obs, imagem, status
    FROM produtos
    WHERE (status IS NULL OR status = '' OR UPPER(TRIM(status))='ATIVO')
    ORDER BY nome ASC
    LIMIT 6000
  ");
  $rowsP = $stP->fetchAll(PDO::FETCH_ASSOC) ?: [];

  $PRODUTOS_CACHE = array_map(static function (array $r): array {
    $img = trim((string)($r['imagem'] ?? ''));

    // fallback: se imagem estiver vazia e você salvou o caminho no OBS
    if ($img === '') {
      $obs = trim((string)($r['obs'] ?? ''));
      if (preg_match('~^(images/|uploads/|img/|prod_).+~i', $obs)) $img = $obs;
    }

    return [
      'id'    => (int)($r['id'] ?? 0),
      'code'  => (string)($r['codigo'] ?? ''),
      'name'  => (string)($r['nome'] ?? ''),
      'unit'  => (string)($r['unidade'] ?? ''),
      'price' => (float)($r['preco'] ?? 0),
      'stock' => (int)($r['estoque'] ?? 0),
      'img'   => img_url($img),
    ];
  }, $rowsP);
} catch (Throwable $e) {
  $PRODUTOS_CACHE = [];
}

/* =========================
   HTML NORMAL
========================= */
$csrf  = csrf_token();
$flash = flash_pop();

// carrega últimos cupons e próximo número (pra não dar variável indefinida)
$last = [];
$nextNo = 1;
try {
  $last = $pdo->query("SELECT id, total, created_at FROM vendas ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC) ?: [];
  $nextNo = (int)$pdo->query("SELECT COALESCE(MAX(id),0)+1 FROM vendas")->fetchColumn();
} catch (Throwable $e) {
  $last = [];
  $nextNo = 1;
}

function fmtMoney($v): string
{
  return 'R$ ' . number_format((float)$v, 2, ',', '.');
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

  <!-- ========== CSS ========= -->
  <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
  <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
  <link rel="stylesheet" href="assets/css/main.css" />

  <style>
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

    .pdv-right-col .pdv-card {
      height: 100%;
      display: flex;
      flex-direction: column;
    }

    .pdv-right-col .checkout-body {
      flex: 1 1 auto;
      min-height: 0;
      overflow: auto;
      -webkit-overflow-scrolling: touch;
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
      overflow-x: hidden;
      display: none;
      -webkit-overflow-scrolling: touch;
      overscroll-behavior: contain;
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

    .it .meta {
      min-width: 0;
      flex: 1 1 auto;
    }

    .it .meta .t {
      font-weight: 900;
      font-size: 13px;
      color: #0f172a;
      line-height: 1.1;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .it .meta .s {
      font-size: 12px;
      color: #64748b;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }

    .it .price {
      font-weight: 900;
      font-size: 13px;
      color: #0f172a;
      white-space: nowrap;
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

    .table td,
    .table th {
      vertical-align: middle;
    }

    #tbItens {
      width: 100%;
      min-width: 720px;
    }

    #tbItens th,
    #tbItens td {
      white-space: nowrap !important;
    }

    .qty-ctrl {
      display: inline-flex;
      align-items: center;
      gap: 6px;
    }

    .qty-btn {
      height: 34px !important;
      width: 34px !important;
      padding: 0 !important;
      display: inline-flex !important;
      align-items: center !important;
      justify-content: center !important;
      border-radius: 10px !important;
    }

    .qty-pill {
      width: 64px !important;
      height: 34px !important;
      text-align: center;
      font-weight: 900;
      border: 1px solid rgba(148, 163, 184, .30);
      border-radius: 10px;
      padding: 4px 6px;
      background: #fff;
      font-size: 13px;
    }

    .qty-pill::-webkit-outer-spin-button,
    .qty-pill::-webkit-inner-spin-button {
      -webkit-appearance: none;
      margin: 0;
    }

    .qty-pill {
      -moz-appearance: textfield;
    }

    .checkout-head {
      background: #0b5ed7;
      color: #fff;
      padding: 12px 14px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .checkout-head h6 {
      margin: 0;
      font-weight: 900;
      letter-spacing: .2px;
    }

    .checkout-body {
      padding: 14px;
    }

    .pay-grid {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 10px;
    }

    .pay-btn {
      border: 1px solid rgba(148, 163, 184, .35);
      background: #fff;
      border-radius: 12px;
      padding: 12px 12px;
      display: flex;
      align-items: center;
      gap: 10px;
      justify-content: flex-start;
      font-weight: 900;
      cursor: pointer;
      user-select: none;
      transition: .12s ease;
      min-height: 44px;
    }

    .pay-btn:hover {
      transform: translateY(-1px);
      box-shadow: 0 10px 22px rgba(15, 23, 42, .08);
    }

    .pay-btn.active {
      outline: 2px solid rgba(37, 99, 235, .35);
      border-color: rgba(37, 99, 235, .55);
      background: rgba(239, 246, 255, .65);
    }

    .pay-btn i {
      font-size: 18px;
    }

    .totals {
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      background: #fff;
      padding: 12px;
    }

    .tot-row {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      font-size: 13px;
      color: #334155;
      margin-bottom: 8px;
      font-weight: 800;
    }

    .tot-row:last-child {
      margin-bottom: 0;
    }

    .tot-hr {
      height: 1px;
      background: rgba(148, 163, 184, .22);
      margin: 10px 0;
    }

    .grand {
      display: flex;
      justify-content: space-between;
      align-items: baseline;
      gap: 10px;
      margin-top: 6px;
    }

    .grand .lbl {
      font-weight: 900;
      color: #0f172a;
      font-size: 18px;
    }

    .grand .val {
      font-weight: 1000;
      color: #0b5ed7;
      font-size: 30px;
      letter-spacing: .2px;
    }

    .chip-toggle {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .chip {
      border: 1px solid rgba(148, 163, 184, .35);
      border-radius: 999px;
      padding: 8px 12px;
      cursor: pointer;
      font-weight: 900;
      font-size: 12px;
      user-select: none;
      background: #fff;
    }

    .chip.active {
      background: rgba(239, 246, 255, .75);
      border-color: rgba(37, 99, 235, .55);
      outline: 2px solid rgba(37, 99, 235, .25);
    }

    .muted {
      font-size: 12px;
      color: #64748b;
    }

    .pay-split-row {
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      padding: 12px;
      background: #fff;
      margin-bottom: 10px;
    }

    .msg-ok {
      display: none;
      color: #16a34a;
      font-weight: 900;
      font-size: 12px;
    }

    .msg-err {
      display: none;
      color: #b91c1c;
      font-weight: 900;
      font-size: 12px;
    }

    .last-box {
      border: 1px solid rgba(148, 163, 184, .25);
      border-radius: 14px;
      overflow: hidden;
      background: #fff;
      margin-top: 12px;
    }

    .last-box .head {
      padding: 10px 12px;
      border-bottom: 1px solid rgba(148, 163, 184, .18);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .last-box .head .t {
      font-weight: 900;
      font-size: 12px;
      color: #0f172a;
      text-transform: uppercase;
      letter-spacing: .4px;
    }

    .last-box .list {
      max-height: 220px;
      overflow: auto;
    }

    .cup {
      padding: 10px 12px;
      border-bottom: 1px solid rgba(148, 163, 184, .12);
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      font-size: 12px;
    }

    .cup:last-child {
      border-bottom: none;
    }

    .cup .left .n {
      font-weight: 900;
      color: #0f172a;
    }

    .cup .left .s {
      color: #64748b;
      font-size: 12px;
    }

    .cup .right {
      text-align: right;
      white-space: nowrap;
    }

    .cup .right .v {
      font-weight: 1000;
      color: #0b5ed7;
    }

    .cup .right .st {
      font-weight: 900;
      color: #16a34a;
      font-size: 11px;
    }

    @media (max-width: 991.98px) {
      .pay-grid {
        grid-template-columns: 1fr;
      }

      #tbItens {
        min-width: 720px;
      }

      .grand .val {
        font-size: 26px;
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
      <a href="dashboard.php" class="d-flex align-items-center gap-2">
        <img src="assets/images/logo/logo.svg" alt="logo" />
      </a>
    </div>

    <nav class="sidebar-nav">
      <ul>
        <li class="nav-item">
          <a href="dashboard.php">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M8.74999 18.3333C12.2376 18.3333 15.1364 15.8128 15.7244 12.4941C15.8448 11.8143 15.2737 11.25 14.5833 11.25H9.99999C9.30966 11.25 8.74999 10.6903 8.74999 10V5.41666C8.74999 4.7263 8.18563 4.15512 7.50586 4.27556C4.18711 4.86357 1.66666 7.76243 1.66666 11.25C1.66666 15.162 4.83797 18.3333 8.74999 18.3333Z" />
                <path
                  d="M17.0833 10C17.7737 10 18.3432 9.43708 18.2408 8.75433C17.7005 5.14918 14.8508 2.29947 11.2457 1.75912C10.5629 1.6568 10 2.2263 10 2.91665V9.16666C10 9.62691 10.3731 10 10.8333 10H17.0833Z" />
              </svg>
            </span>
            <span class="text">Dashboard</span>
          </a>
        </li>

        <li class="nav-item nav-item-has-children active">
          <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes"
            aria-controls="ddmenu_operacoes" aria-expanded="true">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M3.33334 3.35442C3.33334 2.4223 4.07954 1.66666 5.00001 1.66666H15C15.9205 1.66666 16.6667 2.4223 16.6667 3.35442V16.8565C16.6667 17.5519 15.8827 17.9489 15.3333 17.5317L13.8333 16.3924C13.537 16.1673 13.1297 16.1673 12.8333 16.3924L10.5 18.1646C10.2037 18.3896 9.79634 18.3896 9.50001 18.1646L7.16668 16.3924C6.87038 16.1673 6.46298 16.1673 6.16668 16.3924L4.66668 17.5317C4.11731 17.9489 3.33334 17.5519 3.33334 16.8565V3.35442Z" />
              </svg>
            </span>
            <span class="text">Operações</span>
          </a>
          <ul id="ddmenu_operacoes" class="collapse show dropdown-nav">
            <li><a href="pedidos.php">Pedidos</a></li>
            <li><a href="vendas.php" class="active">Vendas</a></li>
            <li><a href="devolucoes.php">Devoluções</a></li>
          </ul>
        </li>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque"
            aria-controls="ddmenu_estoque" aria-expanded="false">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M2.49999 5.83331C2.03976 5.83331 1.66666 6.2064 1.66666 6.66665V10.8333C1.66666 13.5948 3.90523 15.8333 6.66666 15.8333H9.99999C12.1856 15.8333 14.0436 14.431 14.7235 12.4772C14.8134 12.4922 14.9058 12.5 15 12.5H16.6667C17.5872 12.5 18.3333 11.7538 18.3333 10.8333V8.33331C18.3333 7.41284 17.5872 6.66665 16.6667 6.66665H15C15 6.2064 14.6269 5.83331 14.1667 5.83331H2.49999Z" />
                <path
                  d="M2.49999 16.6667C2.03976 16.6667 1.66666 17.0398 1.66666 17.5C1.66666 17.9602 2.03976 18.3334 2.49999 18.3334H14.1667C14.6269 18.3334 15 17.9602 15 17.5C15 17.0398 14.6269 16.6667 14.1667 16.6667H2.49999Z" />
              </svg>
            </span>
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

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros"
            aria-controls="ddmenu_cadastros" aria-expanded="false">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M1.66666 5.41669C1.66666 3.34562 3.34559 1.66669 5.41666 1.66669C7.48772 1.66669 9.16666 3.34562 9.16666 5.41669C9.16666 7.48775 7.48772 9.16669 5.41666 9.16669C3.34559 9.16669 1.66666 7.48775 1.66666 5.41669Z" />
                <path
                  d="M1.66666 14.5834C1.66666 12.5123 3.34559 10.8334 5.41666 10.8334C7.48772 10.8334 9.16666 12.5123 9.16666 14.5834C9.16666 16.6545 7.48772 18.3334 5.41666 18.3334C3.34559 18.3334 1.66666 16.6545 1.66666 14.5834Z" />
                <path
                  d="M10.8333 5.41669C10.8333 3.34562 12.5123 1.66669 14.5833 1.66669C16.6544 1.66669 18.3333 3.34562 18.3333 5.41669C18.3333 7.48775 16.6544 9.16669 14.5833 9.16669C12.5123 9.16669 10.8333 7.48775 10.8333 5.41669Z" />
                <path
                  d="M10.8333 14.5834C10.8333 12.5123 12.5123 10.8334 14.5833 10.8334C16.6544 10.8334 18.3333 12.5123 18.3333 14.5834C18.3333 16.6545 16.6544 18.3334 14.5833 18.3334C12.5123 18.3334 10.8333 16.6545 10.8333 14.5834Z" />
              </svg>
            </span>
            <span class="text">Cadastros</span>
          </a>
          <ul id="ddmenu_cadastros" class="collapse dropdown-nav">
            <li><a href="clientes.php">Clientes</a></li>
            <li><a href="fornecedores.php">Fornecedores</a></li>
            <li><a href="categorias.php">Categorias</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a href="relatorios.php">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M4.16666 3.33335C4.16666 2.41288 4.91285 1.66669 5.83332 1.66669H14.1667C15.0872 1.66669 15.8333 2.41288 15.8333 3.33335V16.6667C15.8333 17.5872 15.0872 18.3334 14.1667 18.3334H5.83332C4.91285 18.3334 4.16666 17.5872 4.16666 16.6667V3.33335Z" />
              </svg>
            </span>
            <span class="text">Relatórios</span>
          </a>
        </li>

        <span class="divider">
          <hr />
        </span>

        <li class="nav-item nav-item-has-children">
          <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config"
            aria-controls="ddmenu_config" aria-expanded="false">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M10 1.66669C5.39763 1.66669 1.66666 5.39766 1.66666 10C1.66666 14.6024 5.39763 18.3334 10 18.3334C14.6024 18.3334 18.3333 14.6024 18.3333 10C18.3333 5.39766 14.6024 1.66669 10 1.66669Z" />
              </svg>
            </span>
            <span class="text">Configurações</span>
          </a>
          <ul id="ddmenu_config" class="collapse dropdown-nav">
            <li><a href="usuarios.php">Usuários e Permissões</a></li>
            <li><a href="parametros.php">Parâmetros do Sistema</a></li>
          </ul>
        </li>

        <li class="nav-item">
          <a href="suporte.php">
            <span class="icon">
              <svg width="20" height="20" viewBox="0 0 20 20" fill="none"
                xmlns="http://www.w3.org/2000/svg">
                <path
                  d="M10.8333 2.50008C10.8333 2.03984 10.4602 1.66675 9.99999 1.66675C9.53975 1.66675 9.16666 2.03984 9.16666 2.50008C9.16666 2.96032 9.53975 3.33341 9.99999 3.33341C10.4602 3.33341 10.8333 2.96032 10.8333 2.50008Z" />
                <path
                  d="M11.4272 2.69637C10.9734 2.56848 10.4947 2.50006 10 2.50006C7.10054 2.50006 4.75003 4.85057 4.75003 7.75006V9.20873C4.75003 9.72814 4.62082 10.2393 4.37404 10.6963L3.36705 12.5611C2.89938 13.4272 3.26806 14.5081 4.16749 14.9078C7.88074 16.5581 12.1193 16.5581 15.8326 14.9078C16.732 14.5081 17.1007 13.4272 16.633 12.5611L15.626 10.6963C15.43 10.3333 15.3081 9.93606 15.2663 9.52773C15.0441 9.56431 14.8159 9.58339 14.5833 9.58339C12.2822 9.58339 10.4167 7.71791 10.4167 5.41673C10.4167 4.37705 10.7975 3.42631 11.4272 2.69637Z" />
              </svg>
            </span>
            <span class="text">Suporte</span>
          </a>
        </li>
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
                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile"
                  data-bs-toggle="dropdown" aria-expanded="false">
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
                  <li>
                    <div class="author-info flex items-center !p-1">
                      <div class="image"><img src="assets/images/profile/profile-image.png" alt="image" /></div>
                      <div class="content">
                        <h4 class="text-sm">Administrador</h4>
                        <a class="text-black/40 dark:text-white/40 hover:text-black dark:hover:text-white text-xs" href="#">Admin</a>
                      </div>
                    </div>
                  </li>
                  <li class="divider"></li>
                  <li><a href="perfil.php"><i class="lni lni-user"></i> Meu Perfil</a></li>
                  <li><a href="usuarios.php"><i class="lni lni-cog"></i> Usuários</a></li>
                  <li class="divider"></li>
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
          <!-- LEFT -->
          <div class="col-12 col-lg-8">
            <div class="pdv-left-col">
              <!-- Search + Preview -->
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

              <!-- Itens -->
              <div class="pdv-card items-card">
                <div class="pdv-head">
                  <div style="font-weight: 1000; color:#0f172a;">
                    <i class="lni lni-cart me-1"></i> Itens da Venda
                  </div>
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

          <!-- RIGHT: Checkout -->
          <div class="col-12 col-lg-4">
            <div class="pdv-right-col">
              <div class="pdv-card">
                <div class="checkout-head">
                  <h6 style="color:#fff;"><i class="lni lni-calculator me-1"></i> Checkout</h6>
                  <span class="badge bg-light text-dark" id="saleNo">Venda #<?= (int)$nextNo ?></span>
                </div>

                <div class="checkout-body">
                  <div class="mb-3">
                    <label class="form-label">Cliente</label>
                    <input class="form-control compact" id="cCliente" placeholder="CPF ou Nome (Opcional)" />
                    <div class="muted mt-1">Consumidor final (se vazio).</div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Forma de Entrega</label>
                    <div class="chip-toggle">
                      <div class="chip active" id="chipPres">Presencial</div>
                      <div class="chip" id="chipDel">Delivery</div>
                    </div>
                  </div>

                  <div class="mb-3" id="wrapDelivery" style="display:none;">
                    <label class="form-label">Endereço</label>
                    <input class="form-control compact mb-2" id="cEndereco" placeholder="Rua, nº, bairro, referência..." />
                    <div class="row g-2">
                      <div class="col-6">
                        <label class="form-label">Taxa entrega</label>
                        <input class="form-control compact" id="cEntrega" placeholder="0,00" value="0,00" />
                      </div>
                      <div class="col-6">
                        <label class="form-label">Observação</label>
                        <input class="form-control compact" id="cObs" placeholder="Opcional" />
                      </div>
                    </div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Desconto</label>
                    <div class="row g-2">
                      <div class="col-5">
                        <select class="form-select compact" id="dTipo">
                          <option value="PERC">%</option>
                          <option value="VALOR">R$</option>
                        </select>
                      </div>
                      <div class="col-7">
                        <input class="form-control compact" id="dValor" placeholder="0" value="0" />
                      </div>
                    </div>
                    <div class="muted mt-1">Desconto aplicado no subtotal (antes da taxa).</div>
                  </div>

                  <div class="mb-3">
                    <label class="form-label">Método de Pagamento</label>

                    <div class="chip-toggle mb-2">
                      <div class="chip active" id="chipPagUnico">Único</div>
                      <div class="chip" id="chipPagMulti">Múltiplos</div>
                    </div>

                    <!-- único -->
                    <div id="wrapPagUnico">
                      <div class="pay-grid mb-2" id="payBtns">
                        <div class="pay-btn active" data-pay="DINHEIRO"><i class="lni lni-coin"></i> Dinheiro</div>
                        <div class="pay-btn" data-pay="PIX"><i class="lni lni-telegram-original"></i> Pix</div>
                        <div class="pay-btn" data-pay="CARTAO"><i class="lni lni-credit-cards"></i> Cartão</div>
                        <div class="pay-btn" data-pay="BOLETO"><i class="lni lni-ticket-alt"></i> Boleto</div>
                      </div>

                      <div class="row g-2">
                        <div class="col-6">
                          <label class="form-label">Valor pago</label>
                          <input class="form-control compact" id="pValor" placeholder="0,00" value="0,00" />
                        </div>
                        <div class="col-6">
                          <label class="form-label">Troco</label>
                          <input class="form-control compact" id="pTroco" value="0,00" readonly />
                        </div>
                      </div>
                      <div class="muted mt-1" id="hintTroco" style="display:none;">Em dinheiro pode ser maior que o total (troco automático).</div>
                    </div>

                    <!-- múltiplos -->
                    <div id="wrapPagMulti" style="display:none;">
                      <div class="d-flex align-items-center justify-content-between gap-2 flex-wrap mb-2">
                        <div class="muted">Some os pagamentos para fechar o total. Se passar do total, precisa ter Dinheiro (troco).</div>
                        <button class="main-btn light-btn btn-hover btn-compact" id="btnAddPay" type="button">
                          <i class="lni lni-plus me-1"></i> Adicionar
                        </button>
                      </div>

                      <div id="paysWrap"></div>

                      <div class="totals mt-2">
                        <div class="tot-row"><span>Somatório</span><span id="mSum">R$ 0,00</span></div>
                        <div class="tot-row"><span>Diferença (Pag - Total)</span><span id="mDiff">R$ 0,00</span></div>
                        <div class="tot-row"><span>Troco</span><span id="mTroco">R$ 0,00</span></div>
                        <div class="msg-ok mt-2" id="mOk">✅ Pagamento OK.</div>
                        <div class="msg-err mt-2" id="mErr">⚠️ Pagamento inválido. Ajuste os valores.</div>
                      </div>
                    </div>
                  </div>

                  <div class="totals mb-3">
                    <div class="tot-row"><span>Subtotal</span><span id="tSub">R$ 0,00</span></div>
                    <div class="tot-row"><span>Desconto</span><span id="tDesc">- R$ 0,00</span></div>
                    <div class="tot-row"><span>Taxa entrega</span><span id="tEnt">R$ 0,00</span></div>
                    <div class="tot-hr"></div>
                    <div class="grand">
                      <span class="lbl">TOTAL</span>
                      <span class="val" id="tTotal">R$ 0,00</span>
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

                  <!-- Últimos cupons -->
                  <div class="last-box">
                    <div class="head">
                      <div class="t">Últimos cupons</div>
                      <button class="main-btn light-btn btn-hover btn-compact" id="btnRefreshLast"
                        type="button" style="height:32px!important;padding:6px 10px!important;">
                        <i class="lni lni-reload"></i>
                      </button>
                    </div>
                    <div class="list" id="lastList">
                      <?php if (!$last): ?>
                        <div class="cup">
                          <div class="left">
                            <div class="n">—</div>
                            <div class="s">Sem cupons ainda</div>
                          </div>
                          <div class="right">
                            <div class="v">R$ 0,00</div>
                          </div>
                        </div>
                        <?php else: foreach ($last as $s): ?>
                          <div class="cup" style="cursor:pointer;" data-id="<?= (int)$s['id'] ?>" title="Clique para imprimir">
                            <div class="left">
                              <div class="n">Venda #<?= (int)$s['id'] ?></div>
                              <div class="s"><?= e((string)$s['created_at']) ?></div>
                            </div>
                            <div class="right">
                              <div class="v"><?= e(fmtMoney((float)$s['total'])) ?></div>
                              <div class="st">CONCLUÍDO</div>
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

  <!-- ========= JS ========= -->
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script src="assets/js/main.js"></script>

  <script>
    /* ==============================
       PRODUTOS (SEM AJAX) - vem do PHP
    ============================== */
    const PRODUCTS = <?= json_encode($PRODUTOS_CACHE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

    /* ==============================
       PDV - JS
    ============================== */
    const CSRF = document.querySelector('meta[name="csrf-token"]').content;
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
      return String(s ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
    }

    function moneyToNumber(txt) {
      let s = String(txt ?? "").trim();
      if (!s) return 0;
      s = s.replace(/[^\d,.-]/g, "").replace(/\./g, "").replace(",", ".");
      const n = Number(s);
      return isNaN(n) ? 0 : n;
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

    function bindImgFallback(scope = document) {
      scope.querySelectorAll("img").forEach(img => {
        img.addEventListener("error", () => {
          img.src = DEFAULT_IMG;
        }, {
          once: true
        });
      });
    }

    /* ==============================
       Estado
    ============================== */
    let CART = [];
    let PAY_MODE = "UNICO";
    let PAY_SELECTED = "DINHEIRO";
    let DELIVERY_MODE = "PRESENCIAL";
    let LAST_SUGG = [];
    let searchTimer = null;

    /* ==============================
       DOM
    ============================== */
    const qProd = document.getElementById("qProd");
    const suggest = document.getElementById("suggest");
    const qGlobal = document.getElementById("qGlobal");

    const previewImg = document.getElementById("previewImg");
    const previewName = document.getElementById("previewName");

    const tbodyItens = document.getElementById("tbodyItens");
    const hintEmpty = document.getElementById("hintEmpty");
    const btnLimpar = document.getElementById("btnLimpar");

    const saleNo = document.getElementById("saleNo");

    const cCliente = document.getElementById("cCliente");
    const chipPres = document.getElementById("chipPres");
    const chipDel = document.getElementById("chipDel");
    const wrapDelivery = document.getElementById("wrapDelivery");
    const cEndereco = document.getElementById("cEndereco");
    const cEntrega = document.getElementById("cEntrega");
    const cObs = document.getElementById("cObs");

    const dTipo = document.getElementById("dTipo");
    const dValor = document.getElementById("dValor");

    const chipPagUnico = document.getElementById("chipPagUnico");
    const chipPagMulti = document.getElementById("chipPagMulti");
    const wrapPagUnico = document.getElementById("wrapPagUnico");
    const wrapPagMulti = document.getElementById("wrapPagMulti");

    const payBtns = document.getElementById("payBtns");
    const pValor = document.getElementById("pValor");
    const pTroco = document.getElementById("pTroco");
    const hintTroco = document.getElementById("hintTroco");

    const btnAddPay = document.getElementById("btnAddPay");
    const paysWrap = document.getElementById("paysWrap");
    const mSum = document.getElementById("mSum");
    const mDiff = document.getElementById("mDiff");
    const mTroco = document.getElementById("mTroco");
    const mOk = document.getElementById("mOk");
    const mErr = document.getElementById("mErr");

    const tSub = document.getElementById("tSub");
    const tDesc = document.getElementById("tDesc");
    const tEnt = document.getElementById("tEnt");
    const tTotal = document.getElementById("tTotal");

    const btnConfirmar = document.getElementById("btnConfirmar");
    const chkPrint = document.getElementById("chkPrint");

    const lastList = document.getElementById("lastList");
    const btnRefreshLast = document.getElementById("btnRefreshLast");

    /* ==============================
       UI
    ============================== */
    function setPreview(prod) {
      const img = (prod && prod.img) ? prod.img : DEFAULT_IMG;
      previewImg.src = img || DEFAULT_IMG;
      previewName.textContent = prod ? prod.name : "AGUARDANDO...";
      bindImgFallback(document);
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
            <div class="t">${safeText(p.name)}</div>
            <div class="s">${safeText(p.code)} • Estoque: ${Number(p.stock ?? 0)}</div>
          </div>
          <div class="price">${numberToMoney(p.price)}</div>
        </div>
      `).join("");
      suggest.style.display = "block";
      suggest.scrollTop = 0;
      bindImgFallback(suggest);
    }

    function hideSuggest() {
      suggest.style.display = "none";
      suggest.innerHTML = "";
    }

    /* ==============================
       Busca LOCAL (SEM AJAX)
       - aceita "0005" pra achar "P0005"
    ============================== */
    function onlyDigits(s) {
      return String(s || "").replace(/\D+/g, "");
    }

    function buildCandidates(q) {
      q = String(q || "").trim();
      if (!q) return [];
      const cands = [q];

      if (/^\d+$/.test(q)) {
        const nz = q.replace(/^0+/, "") || "0";
        for (let len = nz.length; len <= 5; len++) cands.push(nz.padStart(len, "0"));
        if (q.length < 5) cands.push(q.padStart(5, "0"));
        cands.push(nz);
      }

      const out = [];
      const seen = new Set();
      for (const c of cands) {
        const v = String(c).trim();
        if (!v || seen.has(v)) continue;
        seen.add(v);
        out.push(v);
      }
      return out;
    }

    function filterProductsLocal(q) {
      const sRaw = String(q || "").trim().toLowerCase();
      if (!sRaw) return [];

      const sDigits = onlyDigits(sRaw);
      const cands = buildCandidates(sDigits || sRaw);

      const res = [];
      for (const p of PRODUCTS) {
        const code = String(p.code || "").toLowerCase();
        const name = String(p.name || "").toLowerCase();
        const codeDigits = onlyDigits(code);

        let hit = false;

        // busca normal
        if (code.includes(sRaw) || name.includes(sRaw)) hit = true;

        // busca por dígitos (0005 => P0005)
        if (!hit && sDigits) {
          if (codeDigits.includes(sDigits)) hit = true;
          else if (cands.some(c => codeDigits.includes(c))) hit = true;
        }

        if (hit) res.push(p);
      }

      // ordena melhor match
      res.sort((a, b) => {
        const ac = String(a.code || "").toLowerCase();
        const bc = String(b.code || "").toLowerCase();
        const an = String(a.name || "").toLowerCase();
        const bn = String(b.name || "").toLowerCase();
        const ad = onlyDigits(ac);
        const bd = onlyDigits(bc);

        function score(code, name, digits) {
          if (sDigits && digits === sDigits) return 0;
          if (sDigits && digits.startsWith(sDigits)) return 1;
          if (code.startsWith(sRaw)) return 2;
          if (name.startsWith(sRaw)) return 3;
          return 4;
        }

        const sa = score(ac, an, ad);
        const sb = score(bc, bn, bd);
        if (sa !== sb) return sa - sb;
        return an.localeCompare(bn);
      });

      return res.slice(0, 30);
    }

    function refreshSuggestDebounced() {
      clearTimeout(searchTimer);
      searchTimer = setTimeout(() => {
        const q = qProd.value.trim();
        if (!q) {
          LAST_SUGG = [];
          showSuggest([]);
          return;
        }
        LAST_SUGG = filterProductsLocal(q);
        showSuggest(LAST_SUGG);
      }, 120);
    }

    qProd.addEventListener("input", refreshSuggestDebounced);
    qProd.addEventListener("focus", refreshSuggestDebounced);

    qProd.addEventListener("keydown", async (e) => {
      if (e.key === "Enter") {
        e.preventDefault();
        if (!LAST_SUGG.length) {
          LAST_SUGG = filterProductsLocal(qProd.value);
          showSuggest(LAST_SUGG);
        }
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
      const prod = LAST_SUGG.find(p => Number(p.id) === id);
      addToCart(prod);
      qProd.value = "";
      hideSuggest();
      qProd.focus();
    });

    document.addEventListener("click", (e) => {
      if (!e.target.closest(".search-wrap")) hideSuggest();
    });

    /* ==============================
       Carrinho
    ============================== */
    function addToCart(prod) {
      if (!prod) return;

      const idx = CART.findIndex(x => x.product_id === prod.id);
      if (idx >= 0) CART[idx].qty += 1;
      else CART.push({
        product_id: prod.id,
        code: prod.code,
        name: prod.name,
        price: Number(prod.price || 0),
        img: (prod.img && String(prod.img).trim() !== "") ? prod.img : DEFAULT_IMG,
        unit: prod.unit || "",
        qty: 1
      });

      setPreview(prod);
      renderCart();
      recalcAll();
    }

    function removeFromCart(product_id) {
      CART = CART.filter(x => x.product_id !== product_id);
      renderCart();
      recalcAll();
    }

    function changeQty(product_id, delta) {
      const it = CART.find(x => x.product_id === product_id);
      if (!it) return;
      it.qty = Math.max(1, Number(it.qty || 1) + delta);
      renderCart();
      recalcAll();
    }

    function setQty(product_id, qty) {
      const it = CART.find(x => x.product_id === product_id);
      if (!it) return;
      it.qty = Math.max(1, Number(qty || 1));
      renderCart();
      recalcAll();
    }

    function calcSubtotal() {
      return CART.reduce((acc, it) => acc + (Number(it.qty || 0) * Number(it.price || 0)), 0);
    }

    function calcDiscount(sub) {
      const tipo = dTipo.value;
      const v = moneyToNumber(String(dValor.value ?? "").trim());
      if (!v || v <= 0) return 0;
      if (tipo === "PERC") return (sub * Math.min(100, v)) / 100;
      return Math.min(sub, v);
    }

    function calcDeliveryFee() {
      if (DELIVERY_MODE !== "DELIVERY") return 0;
      return moneyToNumber(cEntrega.value);
    }

    function calcTotal() {
      const sub = calcSubtotal();
      const desc = calcDiscount(sub);
      const ent = calcDeliveryFee();
      return Math.max(0, (sub - desc) + ent);
    }

    /* ==============================
       Pagamento
    ============================== */
    function payRowTpl(method = "PIX", value = "0,00") {
      return `
        <div class="pay-split-row">
          <div class="row g-2 align-items-end">
            <div class="col-6">
              <label class="form-label">Forma</label>
              <select class="form-select compact mMethod">
                <option value="DINHEIRO" ${method==="DINHEIRO"?"selected":""}>Dinheiro</option>
                <option value="PIX" ${method==="PIX"?"selected":""}>Pix</option>
                <option value="CARTAO" ${method==="CARTAO"?"selected":""}>Cartão</option>
                <option value="BOLETO" ${method==="BOLETO"?"selected":""}>Boleto</option>
              </select>
            </div>
            <div class="col-4">
              <label class="form-label">Valor</label>
              <input class="form-control compact mValue" value="${safeText(value)}" placeholder="0,00" />
            </div>
            <div class="col-2 d-grid">
              <button class="main-btn danger-btn-outline btn-hover btn-compact btnRemPay" type="button" style="height:38px!important;padding:0!important;">
                <i class="lni lni-trash-can"></i>
              </button>
            </div>
          </div>
        </div>
      `;
    }

    function ensureOnePayRow() {
      if (!paysWrap.querySelector(".pay-split-row")) paysWrap.innerHTML = payRowTpl("PIX", "0,00");
    }

    function computeMultiPay() {
      const total = calcTotal();
      const rows = Array.from(paysWrap.querySelectorAll(".pay-split-row")).map(row => {
        const m = row.querySelector(".mMethod")?.value || "PIX";
        const v = moneyToNumber(row.querySelector(".mValue")?.value || "0");
        return {
          method: m,
          value: v
        };
      }).filter(x => x.value > 0);

      const sum = rows.reduce((a, x) => a + x.value, 0);
      const diff = sum - total;
      const hasCash = rows.some(x => x.method === "DINHEIRO");

      let ok = false,
        troco = 0;
      if (Math.abs(diff) < 0.009) ok = true;
      else if (diff > 0.009 && hasCash) {
        ok = true;
        troco = diff;
      }

      mSum.textContent = numberToMoney(sum);
      mDiff.textContent = numberToMoney(diff);
      mTroco.textContent = numberToMoney(troco);

      mOk.style.display = ok ? "block" : "none";
      mErr.style.display = ok ? "none" : "block";

      return {
        ok,
        rows,
        sum,
        diff,
        troco,
        total
      };
    }

    function computeSinglePay() {
      const total = calcTotal();
      const paid = moneyToNumber(pValor.value);
      const method = PAY_SELECTED;

      let ok = false,
        troco = 0;
      if (method === "DINHEIRO") {
        ok = paid >= total && total > 0;
        troco = ok ? (paid - total) : 0;
        hintTroco.style.display = "block";
      } else {
        hintTroco.style.display = "none";
        ok = (Math.abs(paid - total) < 0.009) && total > 0;
        troco = 0;
      }
      pTroco.value = troco.toFixed(2).replace(".", ",");
      return {
        ok,
        method,
        paid,
        troco,
        total
      };
    }

    /* ==============================
       Render
    ============================== */
    function renderCart() {
      tbodyItens.innerHTML = "";
      hintEmpty.style.display = CART.length ? "none" : "block";

      CART.forEach((it, i) => {
        const sub = Number(it.qty || 0) * Number(it.price || 0);
        tbodyItens.insertAdjacentHTML("beforeend", `
          <tr data-pid="${Number(it.product_id)}">
            <td>${i + 1}</td>
            <td>
              <div class="d-flex align-items-center gap-2">
                <img class="pimg" src="${safeText(it.img || DEFAULT_IMG)}" alt="">
                <div style="min-width:0;">
                  <div style="font-weight:1000;color:#0f172a;line-height:1.1;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:340px;">
                    ${safeText(it.name)}
                  </div>
                  <div class="muted">${safeText(it.code)}</div>
                </div>
              </div>
            </td>
            <td>
              <div class="qty-ctrl">
                <button class="main-btn light-btn btn-hover btn-compact qty-btn btnMinus" type="button" title="-1"><i class="lni lni-minus"></i></button>
                <input class="qty-pill iQty" type="number" min="1" value="${Number(it.qty || 1)}" />
                <button class="main-btn light-btn btn-hover btn-compact qty-btn btnPlus" type="button" title="+1"><i class="lni lni-plus"></i></button>
              </div>
            </td>
            <td class="text-end">${numberToMoney(it.price)}</td>
            <td class="text-end" style="font-weight:1000;">${numberToMoney(sub)}</td>
            <td class="text-center">
              <button class="main-btn danger-btn-outline btn-hover btn-compact icon-btn btnRemove" type="button" title="Remover"><i class="lni lni-trash-can"></i></button>
            </td>
          </tr>
        `);
      });

      bindImgFallback(tbodyItens);
    }

    function recalcAll() {
      const sub = calcSubtotal();
      const desc = calcDiscount(sub);
      const ent = calcDeliveryFee();
      const total = Math.max(0, (sub - desc) + ent);

      tSub.textContent = numberToMoney(sub);
      tDesc.textContent = "- " + numberToMoney(desc);
      tEnt.textContent = numberToMoney(ent);
      tTotal.textContent = numberToMoney(total);

      if (PAY_MODE === "UNICO") computeSinglePay();
      else computeMultiPay();
    }

    /* ==============================
       Últimos cupons (server)
    ============================== */
    async function renderLastSales() {
      try {
        const r = await fetchJSON(`${AJAX_URL}?ajax=ultimasVendas`);
        const all = (r.items || []).slice(0, 10);

        if (!all.length) {
          lastList.innerHTML = `<div class="cup"><div class="left"><div class="n">—</div><div class="s">Sem cupons ainda</div></div><div class="right"><div class="v">R$ 0,00</div></div></div>`;
          return;
        }

        lastList.innerHTML = all.map(s => `
          <div class="cup" style="cursor:pointer;" data-id="${Number(s.id)}" title="Clique para imprimir">
            <div class="left">
              <div class="n">Venda #${Number(s.id)}</div>
              <div class="s">${safeText(s.date || "")}</div>
            </div>
            <div class="right">
              <div class="v">${numberToMoney(s.total || 0)}</div>
              <div class="st">CONCLUÍDO</div>
            </div>
          </div>
        `).join("");

        if (r.next) saleNo.textContent = `Venda #${Number(r.next)}`;
      } catch (e) {
        console.error("ultimasVendas:", e);
      }
    }

    /* ==============================
       Entrega toggle
    ============================== */
    function setDeliveryMode(mode) {
      DELIVERY_MODE = mode;
      const isDel = mode === "DELIVERY";
      chipDel.classList.toggle("active", isDel);
      chipPres.classList.toggle("active", !isDel);
      wrapDelivery.style.display = isDel ? "block" : "none";
      if (!isDel) {
        cEndereco.value = "";
        cEntrega.value = "0,00";
        cObs.value = "";
      }
      recalcAll();
    }
    chipPres.addEventListener("click", () => setDeliveryMode("PRESENCIAL"));
    chipDel.addEventListener("click", () => setDeliveryMode("DELIVERY"));
    cEntrega.addEventListener("input", recalcAll);

    dTipo.addEventListener("change", recalcAll);
    dValor.addEventListener("input", recalcAll);

    /* ==============================
       Pagamento toggle
    ============================== */
    function setPayMode(mode) {
      PAY_MODE = mode;
      const isMulti = mode === "MULTI";
      chipPagMulti.classList.toggle("active", isMulti);
      chipPagUnico.classList.toggle("active", !isMulti);
      wrapPagMulti.style.display = isMulti ? "block" : "none";
      wrapPagUnico.style.display = isMulti ? "none" : "block";
      if (isMulti) ensureOnePayRow();
      recalcAll();
    }
    chipPagUnico.addEventListener("click", () => setPayMode("UNICO"));
    chipPagMulti.addEventListener("click", () => setPayMode("MULTI"));

    payBtns.addEventListener("click", (e) => {
      const btn = e.target.closest(".pay-btn");
      if (!btn) return;
      PAY_SELECTED = btn.getAttribute("data-pay") || "DINHEIRO";
      payBtns.querySelectorAll(".pay-btn").forEach(b => b.classList.remove("active"));
      btn.classList.add("active");
      recalcAll();
    });
    pValor.addEventListener("input", recalcAll);

    btnAddPay.addEventListener("click", () => {
      paysWrap.insertAdjacentHTML("beforeend", payRowTpl("PIX", "0,00"));
      recalcAll();
    });
    paysWrap.addEventListener("click", (e) => {
      const btn = e.target.closest(".btnRemPay");
      if (!btn) return;
      const row = btn.closest(".pay-split-row");
      if (row) row.remove();
      ensureOnePayRow();
      recalcAll();
    });
    paysWrap.addEventListener("input", (e) => {
      if (e.target.closest(".pay-split-row")) recalcAll();
    });
    paysWrap.addEventListener("change", (e) => {
      if (e.target.closest(".pay-split-row")) recalcAll();
    });

    /* ==============================
       Carrinho events
    ============================== */
    tbodyItens.addEventListener("click", (e) => {
      const tr = e.target.closest("tr");
      if (!tr) return;
      const pid = Number(tr.getAttribute("data-pid") || 0);
      if (!pid) return;

      if (e.target.closest(".btnRemove")) return removeFromCart(pid);
      if (e.target.closest(".btnMinus")) return changeQty(pid, -1);
      if (e.target.closest(".btnPlus")) return changeQty(pid, +1);
    });
    tbodyItens.addEventListener("input", (e) => {
      const tr = e.target.closest("tr");
      if (!tr) return;
      const pid = Number(tr.getAttribute("data-pid") || 0);
      if (!pid) return;
      if (e.target.classList.contains("iQty")) setQty(pid, e.target.value);
    });
    btnLimpar.addEventListener("click", () => {
      if (CART.length && !confirm("Limpar todos os itens da venda?")) return;
      CART = [];
      renderCart();
      recalcAll();
      setPreview(null);
    });

    /* ==============================
       Confirmar venda (server)
       (continua usando salvarVendas.php)
    ============================== */
    function validateSaleClient() {
      if (!CART.length) return {
        ok: false,
        msg: "Adicione pelo menos 1 item."
      };
      if (DELIVERY_MODE === "DELIVERY" && !String(cEndereco.value || "").trim()) return {
        ok: false,
        msg: "Informe o endereço do Delivery."
      };
      const total = calcTotal();
      if (total <= 0) return {
        ok: false,
        msg: "Total inválido."
      };

      if (PAY_MODE === "UNICO") {
        const r = computeSinglePay();
        if (!r.ok) {
          if (r.method === "DINHEIRO") return {
            ok: false,
            msg: "No dinheiro, o valor pago deve ser >= total."
          };
          return {
            ok: false,
            msg: "Para Pix/Cartão/Boleto, o valor pago deve ser igual ao total."
          };
        }
        return {
          ok: true
        };
      }

      const m = computeMultiPay();
      if (!m.ok) return {
        ok: false,
        msg: "Pagamento múltiplo inválido. Ajuste os valores."
      };
      return {
        ok: true
      };
    }

    async function confirmSale() {
      const v = validateSaleClient();
      if (!v.ok) {
        alert(v.msg);
        return;
      }

      const payload = {
        csrf_token: CSRF,
        customer: String(cCliente.value || "").trim(),
        delivery: {
          mode: DELIVERY_MODE,
          address: DELIVERY_MODE === "DELIVERY" ? String(cEndereco.value || "").trim() : "",
          fee: DELIVERY_MODE === "DELIVERY" ? moneyToNumber(cEntrega.value) : 0,
          obs: DELIVERY_MODE === "DELIVERY" ? String(cObs.value || "").trim() : ""
        },
        discount: {
          tipo: dTipo.value,
          valor: moneyToNumber(dValor.value)
        },
        pay: (PAY_MODE === "UNICO") ? {
          mode: "UNICO",
          method: PAY_SELECTED,
          paid: moneyToNumber(pValor.value)
        } : {
          mode: "MULTI",
          parts: Array.from(paysWrap.querySelectorAll(".pay-split-row")).map(row => ({
            method: row.querySelector(".mMethod")?.value || "PIX",
            value: moneyToNumber(row.querySelector(".mValue")?.value || "0")
          }))
        },
        items: CART.map(it => ({
          product_id: it.product_id,
          qty: Number(it.qty || 0)
        }))
      };

      btnConfirmar.disabled = true;

      try {
        const r = await fetchJSON("assets/dados/vendas/salvarVendas.php", {
          method: "POST",
          headers: {
            "Content-Type": "application/json"
          },
          body: JSON.stringify(payload)
        });

        alert(r.msg || "Venda confirmada!");

        if (chkPrint.checked && r.sale && r.sale.print_url) {
          const w = window.open(r.sale.print_url, "_blank");
          if (!w) alert("Pop-up bloqueado. Permita pop-ups para imprimir.");
        }

        CART = [];
        renderCart();
        setPreview(null);

        cCliente.value = "";
        setDeliveryMode("PRESENCIAL");

        dTipo.value = "PERC";
        dValor.value = "0";

        setPayMode("UNICO");
        PAY_SELECTED = "DINHEIRO";
        payBtns.querySelectorAll(".pay-btn").forEach(b => b.classList.remove("active"));
        payBtns.querySelector('.pay-btn[data-pay="DINHEIRO"]').classList.add("active");
        pValor.value = "0,00";
        pTroco.value = "0,00";

        paysWrap.innerHTML = "";
        ensureOnePayRow();

        recalcAll();
        saleNo.textContent = `Venda #${Number(r.next || (Number(r.sale?.no || 0) + 1) || 1)}`;

        await renderLastSales();
        qProd.focus();

      } catch (e) {
        alert(e.message || "Erro ao salvar venda.");
      } finally {
        btnConfirmar.disabled = false;
      }
    }
    btnConfirmar.addEventListener("click", confirmSale);

    /* ==============================
       Atalhos teclado
    ============================== */
    document.addEventListener("keydown", (e) => {
      if (e.key === "F4") {
        e.preventDefault();
        qProd.focus();
        return;
      }
      if (e.key === "F2") {
        e.preventDefault();
        confirmSale();
        return;
      }
      if (e.key === "Escape") hideSuggest();
    });

    /* ==============================
       Últimos cupons click (imprimir)
    ============================== */
    lastList.addEventListener("click", (e) => {
      const cup = e.target.closest(".cup");
      if (!cup) return;
      const id = Number(cup.getAttribute("data-id") || 0);
      if (id > 0) window.open(`assets/dados/vendas/cupom.php?id=${id}&auto=1`, "_blank");
    });

    /* ==============================
       Init
    ============================== */
    function init() {
      setPreview(null);
      setDeliveryMode("PRESENCIAL");
      setPayMode("UNICO");
      ensureOnePayRow();

      renderCart();
      recalcAll();
      renderLastSales();

      qGlobal.addEventListener("input", () => {
        qProd.value = qGlobal.value;
        refreshSuggestDebounced();
      });

      btnRefreshLast.addEventListener("click", renderLastSales);

      setTimeout(() => qProd.focus(), 200);
    }
    init();
  </script>
</body>

</html>