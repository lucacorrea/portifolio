<?php

declare(strict_types=1);
session_start();

/* Obrigatório estar logado */
if (empty($_SESSION['usuario_logado'])) {
  header('Location: ../../../index.php');
  exit;
}

/* Obrigatório ser ADMIN */
$perfis = $_SESSION['perfis'] ?? [];
if (!is_array($perfis)) $perfis = [$perfis];
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../../operador/index.php');
  exit;
}

function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function to_decimal($v): float
{
  $s = trim((string)$v);
  if ($s === '') return 0.0;
  $s = str_replace(['R$', ' '], '', $s);
  $s = str_replace('.', '', $s);
  $s = str_replace(',', '.', $s);
  $s = preg_replace('/[^0-9\.\-]/', '', $s) ?? '0';
  if ($s === '' || $s === '-' || $s === '.') return 0.0;
  return (float)$s;
}

function fmt_date(string $s): string
{
  try {
    return (new DateTime($s))->format('d/m/Y');
  } catch (Throwable $e) {
    return $s;
  }
}

function ensure_dir(string $dir): bool
{
  if (is_dir($dir)) return true;
  return @mkdir($dir, 0755, true);
}

function save_base64_image(?string $dataUrl, string $destAbsPath, int $maxBytes): bool
{
  if (!$dataUrl) return false;
  $dataUrl = trim($dataUrl);
  if ($dataUrl === '') return false;

  if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $dataUrl) !== 1) return false;
  $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
  $bin = base64_decode($base64, true);
  if ($bin === false) return false;
  if (strlen($bin) > $maxBytes) return false;

  return @file_put_contents($destAbsPath, $bin) !== false;
}

/* Flash */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/* ===== Conexão ===== */
require '../../../assets/php/conexao.php';
$pdo = db();

/* Feira */
$feiraId = 1;

/* Dia */
$dia = trim((string)($_GET['dia'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) $dia = date('Y-m-d');

/* ===== garante romaneio do dia ===== */
$romaneioId = 0;
try {
  $st = $pdo->prepare("SELECT id FROM romaneio_dia WHERE feira_id = :f AND data_ref = :d LIMIT 1");
  $st->execute([':f' => $feiraId, ':d' => $dia]);
  $romaneioId = (int)($st->fetchColumn() ?: 0);

  if ($romaneioId <= 0) {
    $ins = $pdo->prepare("INSERT INTO romaneio_dia (feira_id, data_ref, status, criado_em) VALUES (:f, :d, 'ABERTO', NOW())");
    $ins->execute([':f' => $feiraId, ':d' => $dia]);
    $romaneioId = (int)$pdo->lastInsertId();
  }
} catch (Throwable $e) {
  $_SESSION['flash_err'] = 'Não foi possível abrir o romaneio do dia.';
  header('Location: ./romaneioEntrada.php');
  exit;
}

/* ===== Combos ===== */
$produtoresAtivos = [];
$produtosAtivos   = [];
try {
  $stP = $pdo->prepare("SELECT id, nome FROM produtores WHERE feira_id = :f AND ativo = 1 ORDER BY nome ASC");
  $stP->execute([':f' => $feiraId]);
  $produtoresAtivos = $stP->fetchAll(PDO::FETCH_ASSOC);

  $stPr = $pdo->prepare("
    SELECT
      p.id, p.nome,
      COALESCE(c.nome,'')  AS categoria_nome,
      COALESCE(u.sigla,'') AS unidade_sigla,
      p.preco_referencia
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id AND c.feira_id = p.feira_id
    LEFT JOIN unidades   u ON u.id = p.unidade_id   AND u.feira_id = p.feira_id
    WHERE p.feira_id = :f AND p.ativo = 1
    ORDER BY p.nome ASC
  ");
  $stPr->execute([':f' => $feiraId]);
  $produtosAtivos = $stPr->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar produtores/produtos.';
}

/* ===== Upload config ===== */
$BASE_DIR = realpath(__DIR__ . '/../../../'); // raiz do projeto (ajuste se precisar)
$UPLOAD_REL = 'uploads/romaneio';
$UPLOAD_ABS = $BASE_DIR ? ($BASE_DIR . DIRECTORY_SEPARATOR . $UPLOAD_REL) : null;
$MAX_IMG_BYTES = 3 * 1024 * 1024; // 3MB

/* ===== POST: salvar entrada ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    $_SESSION['flash_err'] = 'Sessão expirada. Atualize a página e tente novamente.';
    header('Location: ./romaneioEntrada.php?dia=' . urlencode($dia));
    exit;
  }

  $acao = (string)($_POST['acao'] ?? '');
  if ($acao !== 'salvar') {
    header('Location: ./romaneioEntrada.php?dia=' . urlencode($dia));
    exit;
  }

  $dataRef = trim((string)($_POST['data_ref'] ?? $dia));
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRef)) $dataRef = $dia;

  if ($dataRef !== $dia) {
    header('Location: ./romaneioEntrada.php?dia=' . urlencode($dataRef));
    exit;
  }

  $prodIds    = $_POST['produtor_id'] ?? [];
  $produtoIds = $_POST['produto_id'] ?? [];
  $qtds       = $_POST['quantidade_entrada'] ?? [];
  $precos     = $_POST['preco_unitario_dia'] ?? [];
  $obsArr     = $_POST['observacao_item'] ?? [];
  $fotosArr   = $_POST['foto_base64'] ?? [];

  $itens = [];
  $n = max(count((array)$prodIds), count((array)$produtoIds), count((array)$qtds), count((array)$precos));

  for ($i = 0; $i < $n; $i++) {
    $produtorId = (int)($prodIds[$i] ?? 0);
    $produtoId  = (int)($produtoIds[$i] ?? 0);
    if ($produtorId <= 0 || $produtoId <= 0) continue;

    $q = round(to_decimal($qtds[$i] ?? '0'), 3);
    if ($q <= 0) continue;

    $p = round(to_decimal($precos[$i] ?? '0'), 2);
    if ($p <= 0) continue;

    $obs = trim((string)($obsArr[$i] ?? ''));
    if ($obs !== '') $obs = mb_substr($obs, 0, 255, 'UTF-8');

    $foto = trim((string)($fotosArr[$i] ?? ''));

    $itens[] = [
      'produtor_id' => $produtorId,
      'produto_id'  => $produtoId,
      'qtd'         => $q,
      'preco'       => $p,
      'obs'         => $obs,
      'foto'        => $foto,
    ];
  }

  if (empty($itens)) {
    $_SESSION['flash_err'] = 'Adicione pelo menos 1 item válido (produtor + produto + quantidade + preço).';
    header('Location: ./romaneioEntrada.php?dia=' . urlencode($dia));
    exit;
  }

  if (!$UPLOAD_ABS) {
    $_SESSION['flash_err'] = 'Diretório base não encontrado para upload.';
    header('Location: ./romaneioEntrada.php?dia=' . urlencode($dia));
    exit;
  }

  try {
    $pdo->beginTransaction();

    $dayAbs = $UPLOAD_ABS . DIRECTORY_SEPARATOR . (string)$romaneioId;
    $dayRel = $UPLOAD_REL . '/' . (string)$romaneioId;

    if (!ensure_dir($dayAbs)) throw new RuntimeException('Falha ao criar pasta de upload.');

    $insItem = $pdo->prepare("
      INSERT INTO romaneio_itens
        (feira_id, romaneio_id, produtor_id, produto_id, quantidade_entrada, preco_unitario_dia, observacao, criado_em)
      VALUES
        (:f, :r, :pr, :pd, :q, :p, :obs, NOW())
    ");

    $insFoto = $pdo->prepare("
      INSERT INTO romaneio_item_fotos
        (feira_id, romaneio_id, romaneio_item_id, caminho, criado_em)
      VALUES
        (:f, :r, :i, :c, NOW())
    ");

    foreach ($itens as $it) {
      $insItem->execute([
        ':f'   => $feiraId,
        ':r'   => $romaneioId,
        ':pr'  => $it['produtor_id'],
        ':pd'  => $it['produto_id'],
        ':q'   => $it['qtd'],
        ':p'   => $it['preco'],
        ':obs' => ($it['obs'] !== '' ? $it['obs'] : null),
      ]);

      $itemId = (int)$pdo->lastInsertId();

      if ($it['foto'] !== '') {
        $fileName = 'item_' . $itemId . '_1.jpg';
        $absPath  = $dayAbs . DIRECTORY_SEPARATOR . $fileName;
        $relPath  = $dayRel . '/' . $fileName;

        if (save_base64_image($it['foto'], $absPath, $MAX_IMG_BYTES)) {
          $insFoto->execute([
            ':f' => $feiraId,
            ':r' => $romaneioId,
            ':i' => $itemId,
            ':c' => $relPath,
          ]);
        }
      }
    }

    $pdo->commit();
    $_SESSION['flash_ok'] = 'Entrada lançada com sucesso.';
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $mysqlCode = (int)($e->errorInfo[1] ?? 0);
    if ($mysqlCode === 1062) $_SESSION['flash_err'] = 'Já existe lançamento para o mesmo produtor + produto neste dia.';
    else $_SESSION['flash_err'] = 'Não foi possível salvar o lançamento agora.';
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_err'] = 'Não foi possível salvar o lançamento agora.';
  }

  header('Location: ./romaneioEntrada.php?dia=' . urlencode($dia));
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios — Romaneio (Entrada)</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../../../images/3.png" />

  <style>
    .form-control {
      height: 42px;
    }

    .btn {
      height: 42px;
    }

    .helper {
      font-size: 12px;
    }

    .card {
      border-radius: 14px;
    }

    .card-header-lite {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 12px;
      flex-wrap: wrap;
      border-bottom: 1px solid rgba(0, 0, 0, .06);
      padding-bottom: 12px;
      margin-bottom: 12px;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      background: #eef2ff;
      color: #1f2a6b;
    }

    .totbox {
      border: 1px solid rgba(0, 0, 0, .08);
      background: #fff;
      border-radius: 12px;
      padding: 10px 12px;
      min-width: 170px;
    }

    .totlabel {
      font-size: 12px;
      color: #6c757d;
      margin: 0;
    }

    .totvalue {
      font-size: 20px;
      font-weight: 900;
      margin: 0;
    }

    .line-card {
      border: 1px solid rgba(0, 0, 0, .08);
      background: #fff;
      border-radius: 14px;
      padding: 12px;
      margin-bottom: 10px;
    }

    .mini {
      height: 38px !important;
    }

    .muted {
      color: #6c757d;
    }

    .photo-thumb {
      width: 76px;
      height: 52px;
      object-fit: cover;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, .12);
      display: none;
    }

    .sticky-actions {
      position: sticky;
      bottom: 10px;
      z-index: 3;
      background: rgba(255, 255, 255, .92);
      border: 1px solid rgba(0, 0, 0, .08);
      border-radius: 14px;
      padding: 10px;
      backdrop-filter: blur(6px);
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: space-between;
      align-items: center;
      margin-top: 12px;
    }

    /* Flash */
    .sig-flash-wrap {
      position: fixed;
      top: 78px;
      right: 18px;
      width: min(420px, calc(100vw - 36px));
      z-index: 9999;
      pointer-events: none;
    }

    .sig-toast.alert {
      pointer-events: auto;
      border: 0 !important;
      border-left: 6px solid !important;
      border-radius: 14px !important;
      padding: 10px 12px !important;
      box-shadow: 0 10px 28px rgba(0, 0, 0, .10) !important;
      font-size: 13px !important;
      margin-bottom: 10px !important;
      opacity: 0;
      transform: translateX(10px);
      animation: sigToastIn .22s ease-out forwards, sigToastOut .25s ease-in forwards 5.75s;
    }

    .sig-toast--success {
      background: #f1fff6 !important;
      border-left-color: #22c55e !important;
    }

    .sig-toast--danger {
      background: #fff1f2 !important;
      border-left-color: #ef4444 !important;
    }

    .sig-toast__row {
      display: flex;
      align-items: flex-start;
      gap: 10px;
    }

    .sig-toast__icon i {
      font-size: 16px;
      margin-top: 2px;
    }

    .sig-toast__title {
      font-weight: 900;
      margin-bottom: 1px;
      line-height: 1.1;
    }

    .sig-toast__text {
      margin: 0;
      line-height: 1.25;
    }

    @keyframes sigToastIn {
      to {
        opacity: 1;
        transform: translateX(0);
      }
    }

    @keyframes sigToastOut {
      to {
        opacity: 0;
        transform: translateX(12px);
        visibility: hidden;
      }
    }

    /* Ações simples na linha */
    .line-actions-simple {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
      justify-content: flex-end;
    }

    /* Desktop/tablet: botão Foto maior */
    .btn-foto-big {
      height: 46px;
      font-size: 14px;
      font-weight: 800;
      border-radius: 12px;
      padding: 10px 14px;
    }

    /* Camera */
    .cam-box {
      border: 1px solid rgba(0, 0, 0, .08);
      background: #fff;
      border-radius: 14px;
      padding: 10px;
    }

    #camVideo,
    #camPreview {
      width: 100%;
      border-radius: 12px;
      background: #111;
      max-height: 60vh;
      object-fit: cover;
    }

    #camPreview {
      display: none;
    }

    #camCanvas {
      display: none;
    }

    /* Mobile first */
    @media (max-width:576px) {
      .card-header-lite {
        flex-direction: column;
        align-items: stretch !important;
        gap: 10px !important;
      }

      .totbox {
        width: 100%;
      }

      .totvalue {
        font-size: 22px;
      }

      .line-card {
        padding: 14px;
      }

      .line-card label {
        font-weight: 700;
      }

      .photo-thumb {
        width: 100% !important;
        height: 160px !important;
        border-radius: 12px !important;
      }

      .helper {
        font-size: 13px;
      }

      .sticky-actions {
        flex-direction: column;
        align-items: stretch;
      }

      .sticky-actions>div {
        width: 100%;
        justify-content: stretch !important;
      }

      .sticky-actions .btn {
        width: 100%;
      }

      .line-actions-simple {
        width: 100%;
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 10px;
      }

      .line-actions-simple .btn {
        height: 52px !important;
        font-size: 16px !important;
        font-weight: 800 !important;
        border-radius: 12px !important;
      }

      .line-actions-simple .btn i {
        font-size: 18px;
        margin-right: 6px;
      }
    }
  </style>
</head>

<body>
  <div class="container-scroller">

    <!-- NAVBAR -->
    <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
      <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
        <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
        <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../../images/3.png" alt="logo" /></a>
      </div>

      <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
        <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
          <span class="icon-menu"></span>
        </button>

        <ul class="navbar-nav navbar-nav-right">
          <li class="nav-item nav-profile dropdown">
            <a class="nav-link dropdown-toggle" href="#" data-toggle="dropdown" id="profileDropdown">
              <i class="ti-user"></i>
              <span class="ml-1"><?= h($nomeTopo) ?></span>
            </a>
            <div class="dropdown-menu dropdown-menu-right navbar-dropdown" aria-labelledby="profileDropdown">
              <a class="dropdown-item" href="../../../controle/auth/logout.php">
                <i class="ti-power-off text-primary"></i> Sair
              </a>
            </div>
          </li>
        </ul>

        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
      </div>
    </nav>
    <!-- /NAVBAR -->

    <div class="container-fluid page-body-wrapper">

      <nav class="sidebar sidebar-offcanvas" id="sidebar">
        <ul class="nav">

          <!-- Dashboard -->
          <li class="nav-item">
            <a class="nav-link" href="./index.php">
              <i class="icon-grid menu-icon"></i>
              <span class="menu-title">Dashboard</span>
            </a>
          </li>

          <!-- Cadastros -->
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraCadastros" aria-expanded="false" aria-controls="feiraCadastros">
              <i class="ti-id-badge menu-icon"></i>
              <span class="menu-title">Cadastros</span>
              <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="feiraCadastros">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item"><a class="nav-link" href="./listaProduto.php"><i class="ti-clipboard mr-2"></i> Lista de Produtos</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaCategoria.php"><i class="ti-layers mr-2"></i> Categorias</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaUnidade.php"><i class="ti-ruler-pencil mr-2"></i> Unidades</a></li>
                <li class="nav-item"><a class="nav-link" href="./listaProdutor.php"><i class="ti-user mr-2"></i> Produtores</a></li>
              </ul>
            </div>
          </li>

          <!-- Movimento -->
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraMovimento" aria-expanded="false" aria-controls="feiraMovimento">
              <i class="ti-exchange-vertical menu-icon"></i>
              <span class="menu-title">Movimento</span>
              <i class="menu-arrow"></i>
            </a>
            <div class="collapse" id="feiraMovimento">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item"><a class="nav-link active" href="./lancamentos.php"><i class="ti-write mr-2"></i> Lançamentos (Vendas)</a></li>
                <li class="nav-item"><a class="nav-link" href="./fechamentoDia.php"><i class="ti-check-box mr-2"></i> Fechamento do Dia</a></li>
              </ul>
            </div>
          </li>

          <!-- Relatórios -->
          <li class="nav-item">
            <a class="nav-link" data-toggle="collapse" href="#feiraRelatorios" aria-expanded="false" aria-controls="feiraRelatorios">
              <i class="ti-clipboard menu-icon"></i>
              <span class="menu-title">Relatórios</span>
              <i class="menu-arrow"></i>
            </a>
            <div class="collapse text-black" id="feiraRelatorios">
              <ul class="nav flex-column sub-menu" style="background:#fff !important;">
                <li class="nav-item"><a class="nav-link" href="./relatorioFinanceiro.php"><i class="ti-bar-chart mr-2"></i> Relatório Financeiro</a></li>
                <li class="nav-item"><a class="nav-link" href="./relatorioProdutos.php"><i class="ti-list mr-2"></i> Produtos Comercializados</a></li>
                <li class="nav-item"><a class="nav-link" href="./relatorioMensal.php"><i class="ti-calendar mr-2"></i> Resumo Mensal</a></li>
                <li class="nav-item"><a class="nav-link" href="./configRelatorio.php"><i class="ti-settings mr-2"></i> Configurar</a></li>
              </ul>
            </div>
          </li>

          <!-- Título DIVERSOS -->
          <li class="nav-item" style="pointer-events:none;">
            <span style="
                  display:block;
                  padding: 5px 15px 5px;
                  font-size: 11px;
                  font-weight: 600;
                  letter-spacing: 1px;
                  color: #6c757d;
                  text-transform: uppercase;
                ">
              Links Diversos
            </span>
          </li>

          <!-- Linha abaixo do título -->
          <li class="nav-item">
            <a class="nav-link" href="../index.php">
              <i class="ti-home menu-icon"></i>
              <span class="menu-title"> Painel Principal</span>
            </a>
          </li>
          <li class="nav-item">
            <a href="../alternativa/" class="nav-link">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Feira do Alternativa</span>

            </a>
          </li>
          <li class="nav-item">
            <a href="../mercado/" class="nav-link">
              <i class="ti-shopping-cart menu-icon"></i>
              <span class="menu-title">Mercado Municipal</span>

            </a>
          </li>
          <li class="nav-item">

            <a class="nav-link" href="https://wa.me/92991515710" target="_blank">
              <i class="ti-headphone-alt menu-icon"></i>
              <span class="menu-title">Suporte</span>
            </a>
          </li>

        </ul>
      </nav>


      <!-- /SIDEBAR -->
      <div class="main-panel">
        <div class="content-wrapper">

          <div class="row">
            <div class="col-12 mb-3">
              <h3 class="font-weight-bold mb-1">Entrada do Dia</h3>
              <div class="d-flex flex-wrap align-items-center" style="gap:8px;">
                <span class="pill"><i class="ti-calendar"></i> <?= h(fmt_date($dia)) ?></span>
                <span class="pill"><i class="ti-agenda"></i> Romaneio #<?= (int)$romaneioId ?></span>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">

                  <div class="card-header-lite">
                    <div>
                      <h4 class="card-title mb-0">Lançar Remessas</h4>
                      <p class="card-description mb-0">Preencha as linhas. Foto é opcional (1 por linha).</p>
                    </div>

                    <div class="d-flex align-items-center" style="gap:10px;">
                      <div>
                        <label class="mb-1 muted" style="font-size:12px;">Data</label>
                        <input type="date" class="form-control mini" value="<?= h($dia) ?>"
                          onchange="location.href='?dia='+this.value;">
                      </div>

                      <div class="totbox">
                        <p class="totlabel">Total estimado</p>
                        <p class="totvalue" id="jsTotal">R$ 0,00</p>
                      </div>
                    </div>
                  </div>

                  <form method="post" action="./romaneioEntrada.php?dia=<?= h($dia) ?>" autocomplete="off" id="formEntrada">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                    <input type="hidden" name="acao" value="salvar">
                    <input type="hidden" name="data_ref" value="<?= h($dia) ?>">

                    <div id="linesWrap">

                      <!-- LINHA BASE -->
                      <div class="line-card js-line">
                        <div class="row">
                          <div class="col-lg-4 col-md-6 mb-3">
                            <label class="mb-1">Produtor</label>
                            <select class="form-control js-produtor" name="produtor_id[]">
                              <option value="0">Selecione</option>
                              <?php foreach ($produtoresAtivos as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= h($p['nome'] ?? '') ?></option>
                              <?php endforeach; ?>
                            </select>
                          </div>

                          <div class="col-lg-4 col-md-6 mb-3">
                            <label class="mb-1">Produto</label>
                            <select class="form-control js-produto" name="produto_id[]">
                              <option value="0">Selecione</option>
                              <?php foreach ($produtosAtivos as $pr): ?>
                                <option value="<?= (int)$pr['id'] ?>"
                                  data-un="<?= h($pr['unidade_sigla'] ?? '') ?>"
                                  data-cat="<?= h($pr['categoria_nome'] ?? '') ?>"
                                  data-preco="<?= h((string)($pr['preco_referencia'] ?? '')) ?>">
                                  <?= h($pr['nome'] ?? '') ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                            <small class="helper text-muted">Unid/Categoria preenche automático.</small>
                          </div>

                          <div class="col-lg-4 col-md-6 mb-3">
                            <label class="mb-1">Qtd</label>
                            <input type="text" class="form-control js-qtd" name="quantidade_entrada[]" value="1">
                          </div>

                          <div class="col-lg-4 col-md-6 mb-3">
                            <label class="mb-1">Preço</label>
                            <input type="text" class="form-control js-preco" name="preco_unitario_dia[]" placeholder="0,00">
                          </div>

                          <div class="col-lg-4 col-md-6 mb-3">
                            <label class="mb-1">Unid</label>
                            <input type="text" class="form-control js-un" value="" readonly>
                          </div>

                          <div class="col-lg-4 col-md-6 mb-3">
                            <label class="mb-1">Categoria</label>
                            <input type="text" class="form-control js-cat" value="" readonly>
                          </div>

                          <!-- ações -->
                          <div class="col-12">
                            <div class="d-flex flex-wrap align-items-center justify-content-between" style="gap:10px;">
                              <div class="d-flex align-items-center" style="gap:10px; min-width: 220px;">
                                <img class="photo-thumb js-thumb" src="" alt="">
                                <small class="text-muted helper mb-0">Foto opcional.</small>
                              </div>

                              <div class="line-actions-simple">
                                <button type="button" class="btn btn-primary btn-foto-big js-foto">
                                  <i class="ti-camera"></i> Tirar foto
                                </button>

                                <button type="button" class="btn btn-light js-remove" disabled>
                                  <i class="ti-trash"></i> Remover linha
                                </button>
                              </div>
                            </div>

                            <input type="hidden" class="js-foto-base64" name="foto_base64[]" value="">
                            <input type="hidden" name="observacao_item[]" value="">
                          </div>
                          <!-- /ações -->
                        </div>
                      </div>
                      <!-- /LINHA BASE -->

                    </div><!-- /linesWrap -->

                    <div class="sticky-actions">
                      <div class="d-flex flex-wrap" style="gap:8px;">
                        <button type="button" class="btn btn-light" id="btnAdd"><i class="ti-plus mr-1"></i> Nova linha</button>
                        <button type="button" class="btn btn-light" id="btnRef"><i class="ti-tag mr-1"></i> Preço ref.</button>
                        <button type="button" class="btn btn-light" id="btnLimparFotos"><i class="ti-close mr-1"></i> Limpar fotos</button>
                      </div>
                      <div class="d-flex flex-wrap" style="gap:8px;">
                        <button type="submit" class="btn btn-primary"><i class="ti-save mr-1"></i> Salvar entradas</button>
                        <a class="btn btn-light" href="./romaneioEntrada.php?dia=<?= h($dia) ?>"><i class="ti-reload mr-1"></i> Recarregar</a>
                      </div>
                    </div>

                  </form>

                  <small class="text-muted d-block mt-3 helper">
                    * Dica: no celular, a câmera só funciona em HTTPS (ou localhost). A foto é comprimida pra ficar leve.
                  </small>

                </div>
              </div>
            </div>
          </div>

        </div>

        <footer class="footer">
          <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center">
            <span class="text-muted text-center text-sm-left d-block mb-2 mb-sm-0">
              © <?= date('Y') ?> SIGRelatórios —
              <a href="https://www.lucascorrea.pro/" target="_blank" rel="noopener">lucascorrea.pro</a>.
            </span>
          </div>
        </footer>

      </div>
    </div>
  </div>

  <!-- MODAL CÂMERA (abre já com câmera ligada) -->
  <div class="modal fade" id="modalCamera" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
      <div class="modal-content" style="border-radius:14px;">
        <div class="modal-header">
          <h5 class="modal-title">Tirar foto</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>

        <div class="modal-body">
          <div class="cam-box">
            <video id="camVideo" autoplay playsinline></video>
            <canvas id="camCanvas"></canvas>
            <img id="camPreview" alt="Prévia">
          </div>

          <div class="mt-2 d-flex flex-wrap" style="gap:8px;">
            <button type="button" class="btn btn-primary" id="btnTirarFoto" disabled>
              <i class="ti-image mr-1"></i> Tirar
            </button>

            <button type="button" class="btn btn-light" id="btnRefazer" disabled>
              <i class="ti-reload mr-1"></i> Refazer
            </button>

            <button type="button" class="btn btn-success" id="btnUsarFoto" disabled>
              <i class="ti-check mr-1"></i> Usar foto
            </button>
          </div>

          <small class="text-muted helper d-block mt-2">
            Ao abrir, a câmera já inicia. Se não pedir permissão, use HTTPS (ou localhost).
          </small>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-light" data-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="../../../vendors/js/vendor.bundle.base.js"></script>
  <script src="../../../js/off-canvas.js"></script>
  <script src="../../../js/hoverable-collapse.js"></script>
  <script src="../../../js/template.js"></script>
  <script src="../../../js/settings.js"></script>
  <script src="../../../js/todolist.js"></script>

  <script>
    (function() {
      const wrap = document.getElementById('linesWrap');
      const btnAdd = document.getElementById('btnAdd');
      const btnRef = document.getElementById('btnRef');
      const btnLimparFotos = document.getElementById('btnLimparFotos');
      const totalEl = document.getElementById('jsTotal');

      function brMoney(n) {
        try {
          return n.toLocaleString('pt-BR', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
          });
        } catch (e) {
          const x = Math.round(n * 100) / 100;
          return String(x).replace('.', ',');
        }
      }

      function toNum(s) {
        s = String(s || '').trim();
        if (!s) return 0;
        s = s.replace(/R\$/g, '').replace(/\s/g, '');
        s = s.replace(/\./g, '').replace(',', '.');
        s = s.replace(/[^0-9.\-]/g, '');
        const v = parseFloat(s);
        return isNaN(v) ? 0 : v;
      }

      function syncInfo(line) {
        const sel = line.querySelector('.js-produto');
        const opt = sel && sel.options ? sel.options[sel.selectedIndex] : null;
        const un = opt && opt.dataset ? (opt.dataset.un || '') : '';
        const cat = opt && opt.dataset ? (opt.dataset.cat || '') : '';
        line.querySelector('.js-un').value = un;
        line.querySelector('.js-cat').value = cat;
      }

      function calcTotal() {
        let tot = 0;
        document.querySelectorAll('.js-line').forEach(line => {
          const produtor = parseInt((line.querySelector('.js-produtor') || {}).value || '0', 10);
          const produto = parseInt((line.querySelector('.js-produto') || {}).value || '0', 10);
          if (!produtor || !produto) return;
          const qtd = toNum((line.querySelector('.js-qtd') || {}).value || '0');
          const preco = toNum((line.querySelector('.js-preco') || {}).value || '0');
          if (qtd > 0 && preco > 0) tot += (qtd * preco);
        });
        totalEl.textContent = 'R$ ' + brMoney(tot);
      }

      function updateRemoveButtons() {
        const lines = document.querySelectorAll('.js-line');
        lines.forEach(line => {
          const btn = line.querySelector('.js-remove');
          btn.disabled = (lines.length <= 1);
          btn.onclick = () => {
            if (lines.length <= 1) return;
            line.remove();
            updateRemoveButtons();
            calcTotal();
          };
        });
      }

      function wire(line) {
        const prod = line.querySelector('.js-produto');
        const qtd = line.querySelector('.js-qtd');
        const preco = line.querySelector('.js-preco');

        prod.addEventListener('change', () => {
          syncInfo(line);
          calcTotal();
        });
        qtd.addEventListener('input', calcTotal);
        preco.addEventListener('input', calcTotal);

        syncInfo(line);
      }

      btnAdd && btnAdd.addEventListener('click', () => {
        const base = document.querySelector('.js-line');
        if (!base) return;
        const clone = base.cloneNode(true);

        clone.querySelector('.js-produtor').value = '0';
        clone.querySelector('.js-produto').value = '0';
        clone.querySelector('.js-qtd').value = '1';
        clone.querySelector('.js-preco').value = '';
        clone.querySelector('.js-un').value = '';
        clone.querySelector('.js-cat').value = '';
        clone.querySelector('.js-foto-base64').value = '';

        const thumb = clone.querySelector('.js-thumb');
        thumb.src = '';
        thumb.style.display = 'none';

        wrap.appendChild(clone);
        wire(clone);
        updateRemoveButtons();
        calcTotal();
      });

      btnRef && btnRef.addEventListener('click', () => {
        document.querySelectorAll('.js-line').forEach(line => {
          const sel = line.querySelector('.js-produto');
          const precoIn = line.querySelector('.js-preco');
          if (!sel || !precoIn) return;
          const pid = parseInt(sel.value || '0', 10);
          if (!pid) return;
          const opt = sel.options[sel.selectedIndex];
          const ref = opt && opt.dataset ? (opt.dataset.preco || '') : '';
          if (!precoIn.value && ref) {
            const n = toNum(ref);
            if (n > 0) precoIn.value = brMoney(n);
          }
        });
        calcTotal();
      });

      btnLimparFotos && btnLimparFotos.addEventListener('click', () => {
        document.querySelectorAll('.js-line').forEach(line => {
          line.querySelector('.js-foto-base64').value = '';
          const thumb = line.querySelector('.js-thumb');
          thumb.src = '';
          thumb.style.display = 'none';
        });
      });

      document.querySelectorAll('.js-line').forEach(wire);
      updateRemoveButtons();
      calcTotal();

      // ===== CAMERA (AUTO-OPEN) =====
      let currentLine = null;
      let stream = null;
      let capturedDataUrl = '';

      const camVideo = document.getElementById('camVideo');
      const camCanvas = document.getElementById('camCanvas');
      const camPreview = document.getElementById('camPreview');

      const btnTirarFoto = document.getElementById('btnTirarFoto');
      const btnRefazer = document.getElementById('btnRefazer');
      const btnUsarFoto = document.getElementById('btnUsarFoto');

      function setCamUI({
        on,
        has
      }) {
        btnTirarFoto.disabled = !on;
        btnRefazer.disabled = !has;
        btnUsarFoto.disabled = !has;
        camPreview.style.display = has ? 'block' : 'none';
      }

      function closeCam() {
        if (stream) {
          stream.getTracks().forEach(t => t.stop());
          stream = null;
        }
        if (camVideo) camVideo.srcObject = null;
      }

      async function openCam() {
        try {
          closeCam();

          stream = await navigator.mediaDevices.getUserMedia({
            video: {
              facingMode: {
                ideal: 'environment'
              }
            },
            audio: false
          });

          camVideo.srcObject = stream;
          await camVideo.play();

          capturedDataUrl = '';
          camPreview.src = '';
          setCamUI({
            on: true,
            has: false
          });
        } catch (e) {
          alert('Não foi possível acessar a câmera. Verifique permissão e HTTPS (ou localhost).');
          setCamUI({
            on: false,
            has: false
          });
        }
      }

      function snap() {
        if (!camVideo.videoWidth || !camVideo.videoHeight) return;

        const targetW = 720;
        const ratio = camVideo.videoHeight / camVideo.videoWidth;
        const targetH = Math.round(targetW * ratio);

        camCanvas.width = targetW;
        camCanvas.height = targetH;
        const ctx = camCanvas.getContext('2d', {
          alpha: false
        });
        ctx.drawImage(camVideo, 0, 0, targetW, targetH);

        capturedDataUrl = camCanvas.toDataURL('image/jpeg', 0.65);
        camPreview.src = capturedDataUrl;

        closeCam(); // economiza bateria
        setCamUI({
          on: false,
          has: true
        });
      }

      function redo() {
        capturedDataUrl = '';
        camPreview.src = '';
        camPreview.style.display = 'none';
        openCam();
      }

      // clique no botão "Tirar foto" da linha
      document.addEventListener('click', function(e) {
        const btn = e.target.closest('.js-foto');
        if (!btn) return;

        currentLine = btn.closest('.js-line');
        capturedDataUrl = '';
        camPreview.src = '';
        setCamUI({
          on: false,
          has: false
        });

        if (window.jQuery && jQuery.fn.modal) {
          jQuery('#modalCamera').modal('show');
          jQuery('#modalCamera').one('shown.bs.modal', function() {
            openCam(); // abre câmera automaticamente
          });
        } else {
          openCam();
        }
      });

      btnTirarFoto.addEventListener('click', snap);
      btnRefazer.addEventListener('click', redo);

      btnUsarFoto.addEventListener('click', function() {
        if (!currentLine || !capturedDataUrl) return;

        currentLine.querySelector('.js-foto-base64').value = capturedDataUrl;

        const thumb = currentLine.querySelector('.js-thumb');
        thumb.src = capturedDataUrl;
        thumb.style.display = 'block';

        if (window.jQuery && jQuery.fn.modal) {
          jQuery('#modalCamera').modal('hide');
        }
      });

      // quando fechar o modal, garante que a câmera feche
      if (window.jQuery) {
        jQuery('#modalCamera').on('hidden.bs.modal', function() {
          closeCam();
          capturedDataUrl = '';
          camPreview.src = '';
          setCamUI({
            on: false,
            has: false
          });
        });
      }
    })();
  </script>
</body>

</html>