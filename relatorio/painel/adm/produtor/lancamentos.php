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

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/** Converte "1.234,56" -> 1234.56 */
function to_decimal($v): float {
  $s = trim((string)$v);
  if ($s === '') return 0.0;
  $s = str_replace(['R$', ' '], '', $s);
  $s = str_replace('.', '', $s);
  $s = str_replace(',', '.', $s);
  $s = preg_replace('/[^0-9\.\-]/', '', $s) ?? '0';
  if ($s === '' || $s === '-' || $s === '.') return 0.0;
  return (float)$s;
}

function fmt_date(string $s): string {
  if ($s === '') return '';
  try {
    $dt = new DateTime($s);
    return $dt->format('d/m/Y');
  } catch (Throwable $e) {
    return $s;
  }
}

function ensure_dir(string $dir): bool {
  if (is_dir($dir)) return true;
  return @mkdir($dir, 0755, true);
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

/* Feira do Produtor = 1 (na Feira Alternativa use 2) */
$feiraId = 1;

/* ===== Filtros ===== */
$dia = trim((string)($_GET['dia'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) $dia = date('Y-m-d');

$verId = (int)($_GET['ver'] ?? 0);

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
  header('Location: ./romaneioLancamentos.php');
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
  $err = $err ?: 'Não foi possível carregar os cadastros agora.';
}

/* ===== Upload config ===== */
$BASE_DIR = realpath(__DIR__ . '/../../../'); // raiz do projeto (ajuste se precisar)
$UPLOAD_REL = 'uploads/romaneio';
$UPLOAD_ABS = $BASE_DIR ? ($BASE_DIR . DIRECTORY_SEPARATOR . $UPLOAD_REL) : null;
$MAX_IMG_BYTES = 3 * 1024 * 1024; // 3MB

function save_base64_image(?string $dataUrl, string $destAbsPath, int $maxBytes): bool {
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

/* ===== POST (Salvar / Excluir) ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    $_SESSION['flash_err'] = 'Sessão expirada. Atualize a página e tente novamente.';
    header('Location: ./romaneioLancamentos.php?dia=' . urlencode($dia));
    exit;
  }

  $acao = (string)($_POST['acao'] ?? '');

  if ($acao === 'excluir_item') {
    $itemId = (int)($_POST['item_id'] ?? 0);
    if ($itemId <= 0) {
      $_SESSION['flash_err'] = 'Item inválido.';
      header('Location: ./romaneioLancamentos.php?dia=' . urlencode($dia));
      exit;
    }

    try {
      $pdo->beginTransaction();

      // apaga fotos (no banco)
      $stF = $pdo->prepare("SELECT caminho FROM romaneio_item_fotos WHERE feira_id = :f AND romaneio_id = :r AND romaneio_item_id = :i");
      $stF->execute([':f' => $feiraId, ':r' => $romaneioId, ':i' => $itemId]);
      $paths = $stF->fetchAll(PDO::FETCH_COLUMN);

      $delF = $pdo->prepare("DELETE FROM romaneio_item_fotos WHERE feira_id = :f AND romaneio_id = :r AND romaneio_item_id = :i");
      $delF->execute([':f' => $feiraId, ':r' => $romaneioId, ':i' => $itemId]);

      $delI = $pdo->prepare("DELETE FROM romaneio_itens WHERE feira_id = :f AND romaneio_id = :r AND id = :i");
      $delI->execute([':f' => $feiraId, ':r' => $romaneioId, ':i' => $itemId]);

      $pdo->commit();

      // remove arquivos físicos depois
      if ($UPLOAD_ABS && is_array($paths)) {
        foreach ($paths as $p) {
          $p = (string)$p;
          if ($p !== '') {
            $abs = $BASE_DIR . DIRECTORY_SEPARATOR . str_replace(['\\','..'], ['/',''], $p);
            @unlink($abs);
          }
        }
      }

      $_SESSION['flash_ok'] = 'Item removido.';
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $_SESSION['flash_err'] = 'Não foi possível remover o item.';
    }

    header('Location: ./romaneioLancamentos.php?dia=' . urlencode($dia));
    exit;
  }

  if ($acao === 'salvar') {
    $dataRef = trim((string)($_POST['data_ref'] ?? $dia));
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRef)) $dataRef = $dia;

    // se trocar data, reabre/cria romaneio
    if ($dataRef !== $dia) {
      header('Location: ./romaneioLancamentos.php?dia=' . urlencode($dataRef));
      exit;
    }

    $prodIds  = $_POST['produtor_id'] ?? [];
    $produtIds= $_POST['produto_id'] ?? [];
    $qtds     = $_POST['quantidade_entrada'] ?? [];
    $precos   = $_POST['preco_unitario_dia'] ?? [];
    $obsArr   = $_POST['observacao_item'] ?? [];
    $fotosArr = $_POST['foto_base64'] ?? []; // 1 foto por linha (pode expandir depois)

    $itens = [];
    $n = max(count((array)$prodIds), count((array)$produtIds), count((array)$qtds), count((array)$precos));

    for ($i=0; $i<$n; $i++) {
      $produtorId = (int)($prodIds[$i] ?? 0);
      $produtoId  = (int)($produtIds[$i] ?? 0);
      if ($produtorId <= 0 || $produtoId <= 0) continue;

      $q = to_decimal($qtds[$i] ?? '0');
      $q = round($q, 3);
      if ($q <= 0) continue;

      $p = to_decimal($precos[$i] ?? '0');
      $p = round($p, 2);
      if ($p <= 0) continue;

      $obsItem = trim((string)($obsArr[$i] ?? ''));
      if ($obsItem !== '') $obsItem = mb_substr($obsItem, 0, 255, 'UTF-8');

      $foto = (string)($fotosArr[$i] ?? '');

      $itens[] = [
        'produtor_id' => $produtorId,
        'produto_id'  => $produtoId,
        'qtd'         => $q,
        'preco'       => $p,
        'obs'         => $obsItem,
        'foto'        => $foto,
      ];
    }

    if (empty($itens)) {
      $_SESSION['flash_err'] = 'Adicione pelo menos 1 item válido (produtor + produto + quantidade + preço).';
      header('Location: ./romaneioLancamentos.php?dia=' . urlencode($dia));
      exit;
    }

    if (!$UPLOAD_ABS) {
      $_SESSION['flash_err'] = 'Diretório base não encontrado para upload.';
      header('Location: ./romaneioLancamentos.php?dia=' . urlencode($dia));
      exit;
    }

    try {
      $pdo->beginTransaction();

      // pasta do dia por romaneio_id
      $dayAbs = $UPLOAD_ABS . DIRECTORY_SEPARATOR . (string)$romaneioId;
      $dayRel = $UPLOAD_REL . '/' . (string)$romaneioId;

      if (!ensure_dir($dayAbs)) {
        throw new RuntimeException('Falha ao criar pasta de upload.');
      }

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

      foreach ($itens as $idx => $it) {
        // tenta inserir (se tiver unique uk_item_dia e duplicar, cai no catch)
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

        // foto (opcional)
        $fotoBase64 = trim((string)$it['foto']);
        if ($fotoBase64 !== '') {
          $fileName = 'item_' . $itemId . '_1.jpg';
          $absPath = $dayAbs . DIRECTORY_SEPARATOR . $fileName;
          $relPath = $dayRel . '/' . $fileName;

          $ok = save_base64_image($fotoBase64, $absPath, $MAX_IMG_BYTES);
          if ($ok) {
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

      // 1062 duplicate key (se você colocou UNIQUE romaneio_id+produtor+produto)
      if ($mysqlCode === 1062) {
        $_SESSION['flash_err'] = 'Já existe lançamento para o mesmo produtor + produto neste dia. Remova o antigo ou ajuste.';
      } else {
        $_SESSION['flash_err'] = 'Não foi possível salvar o lançamento agora.';
      }
    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $_SESSION['flash_err'] = 'Não foi possível salvar o lançamento agora.';
    }

    header('Location: ./romaneioLancamentos.php?dia=' . urlencode($dia));
    exit;
  }
}

/* ===== Listagem do dia ===== */
$itensDia = [];
try {
  $st = $pdo->prepare("
    SELECT
      ri.id,
      ri.quantidade_entrada,
      ri.preco_unitario_dia,
      ri.observacao,
      ri.criado_em,
      pr.nome AS produtor_nome,
      p.nome  AS produto_nome,
      COALESCE(u.sigla,'') AS unidade_sigla,
      COALESCE(c.nome,'')  AS categoria_nome,
      (SELECT COUNT(*) FROM romaneio_item_fotos f
         WHERE f.feira_id = ri.feira_id AND f.romaneio_id = ri.romaneio_id AND f.romaneio_item_id = ri.id
      ) AS qtd_fotos
    FROM romaneio_itens ri
    LEFT JOIN produtores pr ON pr.id = ri.produtor_id
    LEFT JOIN produtos p    ON p.id  = ri.produto_id
    LEFT JOIN unidades u    ON u.id  = p.unidade_id
    LEFT JOIN categorias c  ON c.id  = p.categoria_id
    WHERE ri.feira_id = :f AND ri.romaneio_id = :r
    ORDER BY ri.id DESC
  ");
  $st->execute([':f' => $feiraId, ':r' => $romaneioId]);
  $itensDia = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar os lançamentos do dia.';
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira do Produtor — Romaneio (Entrada do Dia)</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">

  <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" type="text/css" href="../../../js/select.dataTables.min.css">

  <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../../../images/3.png" />

  <style>
    ul .nav-link:hover { color: blue !important; }
    .nav-link { color: black !important; }
    .sidebar .sub-menu .nav-item .nav-link { margin-left: -35px !important; }
    .sidebar .sub-menu li { list-style: none !important; }

    .form-control { height: 42px; }
    .btn { height: 42px; }
    .helper { font-size: 12px; }

    .acoes-wrap { display:flex; flex-wrap:wrap; gap:8px; }
    .btn-xs { padding:.25rem .5rem; font-size:.75rem; line-height:1.2; height:auto; }

    .table td,.table th { vertical-align: middle !important; }

    .totbox {
      border: 1px solid rgba(0,0,0,.08);
      background:#fff;
      border-radius:12px;
      padding:10px 12px;
    }
    .totlabel { font-size:12px; color:#6c757d; margin:0; }
    .totvalue { font-size:20px; font-weight:800; margin:0; }

    /* Flash */
    .sig-flash-wrap{
      position:fixed; top:78px; right:18px; width:min(420px, calc(100vw - 36px));
      z-index:9999; pointer-events:none;
    }
    .sig-toast.alert{
      pointer-events:auto; border:0!important; border-left:6px solid!important;
      border-radius:14px!important; padding:10px 12px!important;
      box-shadow:0 10px 28px rgba(0,0,0,.10)!important;
      font-size:13px!important; margin-bottom:10px!important;
      opacity:0; transform:translateX(10px);
      animation:sigToastIn .22s ease-out forwards, sigToastOut .25s ease-in forwards 5.75s;
    }
    .sig-toast--success{ background:#f1fff6!important; border-left-color:#22c55e!important; }
    .sig-toast--danger{ background:#fff1f2!important; border-left-color:#ef4444!important; }
    .sig-toast__row{ display:flex; align-items:flex-start; gap:10px; }
    .sig-toast__icon i{ font-size:16px; margin-top:2px; }
    .sig-toast__title{ font-weight:800; margin-bottom:1px; line-height:1.1; }
    .sig-toast__text{ margin:0; line-height:1.25; }
    .sig-toast .close{ opacity:.55; font-size:18px; line-height:1; padding:0 6px; }
    .sig-toast .close:hover{ opacity:1; }
    @keyframes sigToastIn{ to{ opacity:1; transform:translateX(0);} }
    @keyframes sigToastOut{ to{ opacity:0; transform:translateX(12px); visibility:hidden;} }

    /* Camera modal */
    .cam-box { border:1px solid rgba(0,0,0,.08); background:#f8f9fa; border-radius:12px; padding:10px; }
    #camVideo, #camPreview { width:100%; border-radius:10px; background:#111; }
    #camPreview { display:none; }
    .photo-thumb { width:72px; height:48px; object-fit:cover; border-radius:8px; border:1px solid rgba(0,0,0,.12); }

    @media (max-width:576px){
      .content-wrapper { padding: 1rem !important; }
      .btn { width: 100%; }
      .acoes-wrap .btn { width: auto; }
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
      <ul class="navbar-nav mr-lg-2"><li class="nav-item nav-search d-none d-lg-block"></li></ul>
      <ul class="navbar-nav navbar-nav-right"></ul>
      <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
        <span class="icon-menu"></span>
      </button>
    </div>
  </nav>

  <?php if ($msg || $err): ?>
    <div class="sig-flash-wrap">
      <?php if ($msg): ?>
        <div class="alert sig-toast sig-toast--success alert-dismissible" role="alert">
          <div class="sig-toast__row">
            <div class="sig-toast__icon"><i class="ti-check"></i></div>
            <div>
              <div class="sig-toast__title">Tudo certo!</div>
              <p class="sig-toast__text"><?= h($msg) ?></p>
            </div>
          </div>
          <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
        </div>
      <?php endif; ?>
      <?php if ($err): ?>
        <div class="alert sig-toast sig-toast--danger alert-dismissible" role="alert">
          <div class="sig-toast__row">
            <div class="sig-toast__icon"><i class="ti-alert"></i></div>
            <div>
              <div class="sig-toast__title">Atenção!</div>
              <p class="sig-toast__text"><?= h($err) ?></p>
            </div>
          </div>
          <button type="button" class="close" data-dismiss="alert" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <div class="container-fluid page-body-wrapper">

    <!-- SIDEBAR (mantive seu padrão e apenas troquei o item ativo no movimento) -->
    <nav class="sidebar sidebar-offcanvas" id="sidebar">
      <ul class="nav">
        <li class="nav-item">
          <a class="nav-link" href="index.php"><i class="icon-grid menu-icon"></i><span class="menu-title">Dashboard</span></a>
        </li>

        <li class="nav-item">
          <a class="nav-link" data-toggle="collapse" href="#feiraCadastros" aria-expanded="false" aria-controls="feiraCadastros">
            <i class="ti-id-badge menu-icon"></i><span class="menu-title">Cadastros</span><i class="menu-arrow"></i>
          </a>
          <div class="collapse" id="feiraCadastros">
            <ul class="nav flex-column sub-menu" style="background: white !important;">
              <li class="nav-item"><a class="nav-link" href="./listaProduto.php"><i class="ti-clipboard mr-2"></i> Lista de Produtos</a></li>
              <li class="nav-item"><a class="nav-link" href="./listaCategoria.php"><i class="ti-layers mr-2"></i> Categorias</a></li>
              <li class="nav-item"><a class="nav-link" href="./listaUnidade.php"><i class="ti-ruler-pencil mr-2"></i> Unidades</a></li>
              <li class="nav-item"><a class="nav-link" href="./listaProdutor.php"><i class="ti-user mr-2"></i> Produtores</a></li>
            </ul>
          </div>
        </li>

        <li class="nav-item active">
          <a class="nav-link open" data-toggle="collapse" href="#feiraMovimento" aria-expanded="true" aria-controls="feiraMovimento">
            <i class="ti-exchange-vertical menu-icon"></i><span class="menu-title">Movimento</span><i class="menu-arrow"></i>
          </a>
          <div class="collapse show" id="feiraMovimento">
            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
              <li class="nav-item active">
                <a class="nav-link" href="./romaneioLancamentos.php" style="color:white !important; background: #231475C5 !important;">
                  <i class="ti-write mr-2"></i> Romaneio (Entrada do Dia)
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="./fechamentoDia.php"><i class="ti-check-box mr-2"></i> Fechamento do Dia</a>
              </li>
            </ul>
          </div>
        </li>

        <li class="nav-item">
          <a class="nav-link" data-toggle="collapse" href="#feiraRelatorios" aria-expanded="false" aria-controls="feiraRelatorios">
            <i class="ti-clipboard menu-icon"></i><span class="menu-title">Relatórios</span><i class="menu-arrow"></i>
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

        <li class="nav-item" style="pointer-events:none;">
          <span style="display:block;padding:5px 15px 5px;font-size:11px;font-weight:600;letter-spacing:1px;color:#6c757d;text-transform:uppercase;">
            Links Diversos
          </span>
        </li>
        <li class="nav-item"><a class="nav-link" href="../index.php"><i class="ti-home menu-icon"></i><span class="menu-title"> Painel Principal</span></a></li>
        <li class="nav-item"><a href="../alternativa/" class="nav-link"><i class="ti-shopping-cart menu-icon"></i><span class="menu-title">Feira do Alternativa</span></a></li>
        <li class="nav-item"><a href="../mercado/" class="nav-link"><i class="ti-shopping-cart menu-icon"></i><span class="menu-title">Mercado Municipal</span></a></li>
        <li class="nav-item"><a class="nav-link" href="https://wa.me/92991515710" target="_blank"><i class="ti-headphone-alt menu-icon"></i><span class="menu-title">Suporte</span></a></li>
      </ul>
    </nav>

    <!-- MAIN -->
    <div class="main-panel">
      <div class="content-wrapper">

        <div class="row">
          <div class="col-12 mb-3">
            <h3 class="font-weight-bold">Romaneio (Entrada do Dia)</h3>
            <h6 class="font-weight-normal mb-0">
              Dia: <b><?= h(fmt_date($dia)) ?></b> — Romaneio #<?= (int)$romaneioId ?>
            </h6>
          </div>
        </div>

        <!-- NOVO LANÇAMENTO -->
        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Lançar Entrada</h4>
                    <p class="card-description mb-0">Informe produtor, produto, quantidade, preço do dia e tire 1 foto (opcional).</p>
                  </div>
                  <div class="totbox mt-2 mt-md-0">
                    <p class="totlabel">Total estimado</p>
                    <p class="totvalue" id="jsTotal">R$ 0,00</p>
                  </div>
                </div>

                <hr>

                <form method="post" action="./romaneioLancamentos.php?dia=<?= h($dia) ?>" autocomplete="off">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="acao" value="salvar">

                  <div class="row">
                    <div class="col-md-3 mb-3">
                      <label class="mb-1">Data</label>
                      <input type="date" class="form-control" name="data_ref" value="<?= h($dia) ?>" required
                        onchange="location.href='?dia='+this.value;">
                      <small class="text-muted helper">Muda o romaneio automaticamente.</small>
                    </div>
                    <div class="col-md-9 mb-3">
                      <label class="mb-1">Dica</label>
                      <div class="text-muted" style="font-size:13px;padding-top:10px;">
                        Preencha as linhas e clique em <b>Salvar</b>. Se você colocou UNIQUE (romaneio_id, produtor_id, produto_id), não duplica o mesmo produtor+produto no mesmo dia.
                      </div>
                    </div>
                  </div>

                  <div class="table-responsive">
                    <table class="table table-striped table-hover mb-0" id="itensTable">
                      <thead>
                        <tr>
                          <th style="width:240px;">Produtor</th>
                          <th>Produto</th>
                          <th style="width:120px;">Qtd</th>
                          <th style="width:170px;">Preço (R$)</th>
                          <th style="width:110px;">Unid</th>
                          <th style="width:220px;">Categoria</th>
                          <th style="width:160px;">Foto</th>
                          <th style="width:90px;" class="text-right">—</th>
                        </tr>
                      </thead>
                      <tbody id="itensBody">

                        <tr class="js-item-row">
                          <td>
                            <select class="form-control js-produtor" name="produtor_id[]">
                              <option value="0">—</option>
                              <?php foreach ($produtoresAtivos as $p): ?>
                                <option value="<?= (int)$p['id'] ?>"><?= h($p['nome'] ?? '') ?></option>
                              <?php endforeach; ?>
                            </select>
                          </td>

                          <td>
                            <select class="form-control js-produto" name="produto_id[]">
                              <option value="0">—</option>
                              <?php foreach ($produtosAtivos as $pr): ?>
                                <option
                                  value="<?= (int)$pr['id'] ?>"
                                  data-un="<?= h($pr['unidade_sigla'] ?? '') ?>"
                                  data-cat="<?= h($pr['categoria_nome'] ?? '') ?>"
                                  data-preco="<?= h((string)($pr['preco_referencia'] ?? '')) ?>">
                                  <?= h($pr['nome'] ?? '') ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </td>

                          <td>
                            <input type="text" class="form-control js-qtd" name="quantidade_entrada[]" value="1">
                          </td>

                          <td>
                            <input type="text" class="form-control js-preco" name="preco_unitario_dia[]" placeholder="0,00">
                          </td>

                          <td><input type="text" class="form-control js-un" value="" readonly></td>
                          <td><input type="text" class="form-control js-cat" value="" readonly></td>

                          <td>
                            <div class="d-flex align-items-center" style="gap:8px;">
                              <img class="photo-thumb js-thumb" src="" alt="" style="display:none;">
                              <button type="button" class="btn btn-light btn-xs js-foto">
                                <i class="ti-camera"></i> Foto
                              </button>
                            </div>
                            <input type="hidden" class="js-foto-base64" name="foto_base64[]" value="">
                          </td>

                          <td class="text-right">
                            <button type="button" class="btn btn-light btn-xs js-remove" title="Remover linha" disabled>
                              <i class="ti-trash"></i>
                            </button>
                          </td>

                          <input type="hidden" name="observacao_item[]" value="">
                        </tr>

                      </tbody>
                    </table>
                  </div>

                  <div class="d-flex flex-wrap justify-content-between align-items-center mt-3" style="gap:8px;">
                    <div class="d-flex flex-wrap" style="gap:8px;">
                      <button type="button" class="btn btn-light" id="btnAddLinha">
                        <i class="ti-plus mr-1"></i> Adicionar linha
                      </button>
                      <button type="button" class="btn btn-light" id="btnPrecoRef">
                        <i class="ti-tag mr-1"></i> Preencher preço ref.
                      </button>
                    </div>

                    <div class="d-flex flex-wrap" style="gap:8px;">
                      <button type="submit" class="btn btn-primary">
                        <i class="ti-save mr-1"></i> Salvar
                      </button>
                      <a href="./romaneioLancamentos.php?dia=<?= h($dia) ?>" class="btn btn-light">
                        <i class="ti-close mr-1"></i> Limpar
                      </a>
                    </div>
                  </div>

                </form>

              </div>
            </div>
          </div>
        </div>

        <!-- LISTAGEM DO DIA -->
        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Entradas do Dia</h4>
                    <p class="card-description mb-0">Mostrando <?= (int)count($itensDia) ?> item(ns).</p>
                  </div>
                </div>

                <div class="table-responsive pt-3">
                  <table class="table table-striped table-hover">
                    <thead>
                      <tr>
                        <th style="width:90px;">ID</th>
                        <th>Produtor</th>
                        <th>Produto</th>
                        <th style="width:110px;">Qtd</th>
                        <th style="width:120px;">Unid</th>
                        <th style="width:140px;">Preço</th>
                        <th style="width:160px;">Total</th>
                        <th style="width:90px;">Fotos</th>
                        <th style="min-width:220px;">Ações</th>
                      </tr>
                    </thead>
                    <tbody>
                      <?php if (empty($itensDia)): ?>
                        <tr><td colspan="9" class="text-center text-muted py-4">Nenhum item lançado hoje.</td></tr>
                      <?php else: ?>
                        <?php foreach ($itensDia as $it): ?>
                          <?php
                            $id = (int)$it['id'];
                            $q  = (float)($it['quantidade_entrada'] ?? 0);
                            $p  = (float)($it['preco_unitario_dia'] ?? 0);
                            $tot = round($q * $p, 2);
                            $qFotos = (int)($it['qtd_fotos'] ?? 0);
                          ?>
                          <tr>
                            <td><?= $id ?></td>
                            <td><?= h((string)($it['produtor_nome'] ?? '')) ?></td>
                            <td><?= h((string)($it['produto_nome'] ?? '')) ?></td>
                            <td><?= number_format($q, 3, ',', '.') ?></td>
                            <td><?= h((string)($it['unidade_sigla'] ?? '')) ?></td>
                            <td>R$ <?= number_format($p, 2, ',', '.') ?></td>
                            <td><b>R$ <?= number_format($tot, 2, ',', '.') ?></b></td>
                            <td><span class="badge badge-light"><?= $qFotos ?></span></td>
                            <td>
                              <div class="acoes-wrap">
                                <form method="post" class="m-0">
                                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                  <input type="hidden" name="acao" value="excluir_item">
                                  <input type="hidden" name="item_id" value="<?= $id ?>">
                                  <button type="submit" class="btn btn-outline-danger btn-xs"
                                    onclick="return confirm('Excluir este item do romaneio?');">
                                    <i class="ti-trash"></i> Excluir
                                  </button>
                                </form>
                              </div>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      <?php endif; ?>
                    </tbody>
                  </table>
                </div>

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
            Todos os direitos reservados.
          </span>
        </div>
      </footer>

    </div>
  </div>
</div>

<!-- MODAL CÂMERA -->
<div class="modal fade" id="modalCamera" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content" style="border-radius:14px;">
      <div class="modal-header">
        <h5 class="modal-title">Tirar foto da remessa</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div class="cam-box">
          <video id="camVideo" autoplay playsinline></video>
          <canvas id="camCanvas" style="display:none;"></canvas>
          <img id="camPreview" alt="Prévia">
        </div>

        <div class="mt-2 d-flex flex-wrap" style="gap:8px;">
          <button type="button" class="btn btn-secondary btn-sm" id="btnAbrirCam"><i class="ti-camera mr-1"></i> Abrir</button>
          <button type="button" class="btn btn-primary btn-sm" id="btnTirarFoto" disabled><i class="ti-image mr-1"></i> Tirar</button>
          <button type="button" class="btn btn-light btn-sm" id="btnRefazer" disabled><i class="ti-reload mr-1"></i> Refazer</button>
          <button type="button" class="btn btn-danger btn-sm" id="btnFecharCam" disabled><i class="ti-close mr-1"></i> Fechar</button>
        </div>

        <small class="text-muted helper d-block mt-2">
          Precisa de HTTPS no celular (ou localhost) para câmera funcionar.
        </small>
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancelar</button>
        <button type="button" class="btn btn-primary" id="btnUsarFoto" disabled>
          <i class="ti-check mr-1"></i> Usar foto
        </button>
      </div>
    </div>
  </div>
</div>

<script src="../../../vendors/js/vendor.bundle.base.js"></script>
<script src="../../../vendors/chart.js/Chart.min.js"></script>
<script src="../../../js/off-canvas.js"></script>
<script src="../../../js/hoverable-collapse.js"></script>
<script src="../../../js/template.js"></script>
<script src="../../../js/settings.js"></script>
<script src="../../../js/todolist.js"></script>
<script src="../../../js/dashboard.js"></script>
<script src="../../../js/Chart.roundedBarCharts.js"></script>

<script>
(function() {
  const body = document.getElementById('itensBody');
  const btnAdd = document.getElementById('btnAddLinha');
  const btnRef = document.getElementById('btnPrecoRef');
  const totalEl = document.getElementById('jsTotal');

  function brMoney(n) {
    try { return n.toLocaleString('pt-BR', { minimumFractionDigits:2, maximumFractionDigits:2 }); }
    catch(e){ const x=Math.round(n*100)/100; return String(x).replace('.',','); }
  }
  function toNum(s) {
    s = String(s||'').trim();
    if (!s) return 0;
    s = s.replace(/R\$/g,'').replace(/\s/g,'');
    s = s.replace(/\./g,'').replace(',', '.');
    s = s.replace(/[^0-9.\-]/g,'');
    const v = parseFloat(s);
    return isNaN(v) ? 0 : v;
  }

  function syncInfo(tr) {
    const sel = tr.querySelector('.js-produto');
    const opt = sel && sel.options ? sel.options[sel.selectedIndex] : null;
    const un = opt && opt.dataset ? (opt.dataset.un || '') : '';
    const cat = opt && opt.dataset ? (opt.dataset.cat || '') : '';
    tr.querySelector('.js-un').value = un;
    tr.querySelector('.js-cat').value = cat;
  }

  function calcTotal() {
    let tot = 0;
    document.querySelectorAll('.js-item-row').forEach(tr => {
      const produtor = parseInt((tr.querySelector('.js-produtor')||{}).value || '0', 10);
      const produto  = parseInt((tr.querySelector('.js-produto')||{}).value || '0', 10);
      if (!produtor || !produto) return;

      const qtd = toNum((tr.querySelector('.js-qtd')||{}).value || '0');
      const preco = toNum((tr.querySelector('.js-preco')||{}).value || '0');
      if (qtd > 0 && preco > 0) tot += (qtd * preco);
    });
    totalEl.textContent = 'R$ ' + brMoney(tot);
  }

  function updateRemoveButtons() {
    const rows = document.querySelectorAll('.js-item-row');
    rows.forEach(tr => {
      const btn = tr.querySelector('.js-remove');
      btn.disabled = (rows.length <= 1);
      btn.onclick = () => {
        if (rows.length <= 1) return;
        tr.remove();
        updateRemoveButtons();
        calcTotal();
      };
    });
  }

  function wireRow(tr) {
    tr.querySelector('.js-produto').addEventListener('change', () => { syncInfo(tr); calcTotal(); });
    tr.querySelector('.js-qtd').addEventListener('input', calcTotal);
    tr.querySelector('.js-preco').addEventListener('input', calcTotal);
    syncInfo(tr);
    calcTotal();
  }

  // Preencher preço referência do produto (se existir)
  btnRef && btnRef.addEventListener('click', () => {
    document.querySelectorAll('.js-item-row').forEach(tr => {
      const sel = tr.querySelector('.js-produto');
      const precoIn = tr.querySelector('.js-preco');
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

  btnAdd && btnAdd.addEventListener('click', () => {
    const base = document.querySelector('.js-item-row');
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

    body.appendChild(clone);
    wireRow(clone);
    updateRemoveButtons();
    calcTotal();
  });

  document.querySelectorAll('.js-item-row').forEach(wireRow);
  updateRemoveButtons();
  calcTotal();

  // ===== CAMERA (1 foto por linha) =====
  let currentRow = null;
  let stream = null;
  let capturedDataUrl = '';

  const modal = document.getElementById('modalCamera');
  const camVideo = document.getElementById('camVideo');
  const camCanvas = document.getElementById('camCanvas');
  const camPreview = document.getElementById('camPreview');

  const btnAbrirCam = document.getElementById('btnAbrirCam');
  const btnTirarFoto = document.getElementById('btnTirarFoto');
  const btnRefazer = document.getElementById('btnRefazer');
  const btnFecharCam = document.getElementById('btnFecharCam');
  const btnUsarFoto = document.getElementById('btnUsarFoto');

  function setCamState({on, has}) {
    btnAbrirCam.disabled = on;
    btnTirarFoto.disabled = !on;
    btnFecharCam.disabled = !on;

    btnRefazer.disabled = !has;
    btnUsarFoto.disabled = !has;

    camPreview.style.display = has ? 'block' : 'none';
  }

  async function openCam() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false
      });
      camVideo.srcObject = stream;
      await camVideo.play();
      capturedDataUrl = '';
      camPreview.src = '';
      setCamState({on:true, has:false});
    } catch (e) {
      alert('Não foi possível acessar a câmera. Verifique permissão e HTTPS.');
      setCamState({on:false, has:false});
    }
  }

  function closeCam() {
    if (stream) {
      stream.getTracks().forEach(t => t.stop());
      stream = null;
    }
    camVideo.srcObject = null;
    setCamState({on:false, has: capturedDataUrl !== ''});
  }

  function snap() {
    if (!camVideo.videoWidth || !camVideo.videoHeight) return;

    const targetW = 720; // leve
    const ratio = camVideo.videoHeight / camVideo.videoWidth;
    const targetH = Math.round(targetW * ratio);

    camCanvas.width = targetW;
    camCanvas.height = targetH;
    const ctx = camCanvas.getContext('2d', { alpha:false });
    ctx.drawImage(camVideo, 0, 0, targetW, targetH);

    capturedDataUrl = camCanvas.toDataURL('image/jpeg', 0.65);
    camPreview.src = capturedDataUrl;

    setCamState({on:true, has:true});
    closeCam(); // fecha pra economizar bateria
  }

  function redo() {
    capturedDataUrl = '';
    camPreview.src = '';
    setCamState({on:false, has:false});
    openCam();
  }

  // abre modal ao clicar no botão "Foto" da linha
  document.addEventListener('click', function(e) {
    const btn = e.target.closest('.js-foto');
    if (!btn) return;
    currentRow = btn.closest('tr');
    capturedDataUrl = '';
    camPreview.src = '';
    setCamState({on:false, has:false});
    if (window.jQuery && jQuery.fn.modal) {
      jQuery('#modalCamera').modal('show');
    }
  });

  btnAbrirCam.addEventListener('click', openCam);
  btnTirarFoto.addEventListener('click', snap);
  btnRefazer.addEventListener('click', redo);
  btnFecharCam.addEventListener('click', closeCam);

  btnUsarFoto.addEventListener('click', function() {
    if (!currentRow || !capturedDataUrl) return;

    currentRow.querySelector('.js-foto-base64').value = capturedDataUrl;

    const thumb = currentRow.querySelector('.js-thumb');
    thumb.src = capturedDataUrl;
    thumb.style.display = 'inline-block';

    if (window.jQuery && jQuery.fn.modal) {
      jQuery('#modalCamera').modal('hide');
    }
  });

  // quando fechar o modal, garante câmera desligada
  if (window.jQuery) {
    jQuery('#modalCamera').on('hidden.bs.modal', function() {
      closeCam();
      capturedDataUrl = '';
      camPreview.src = '';
      setCamState({on:false, has:false});
    });
  }
})();
</script>
</body>
</html>
