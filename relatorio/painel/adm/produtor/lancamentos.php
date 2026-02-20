<?php
declare(strict_types=1);
session_start();

/*
  Página: lancamentos.php
  - Faz o "lançamento do dia" (romaneio_dia + romaneio_itens + fotos)
  - Processamento dentro da própria página (POST)

  ✅ Pastas necessárias:
  /uploads/romaneio
  (ou o script cria, se tiver permissão)

  ✅ Tabelas (você já criou):
    romaneio_dia
    romaneio_itens
    romaneio_item_fotos

  Observação:
  - Estou assumindo que existem tabelas:
      produtores (id, feira_id, nome, ativo)
      produtos   (id, feira_id, nome, ativo)
  - Se seus campos forem diferentes, ajuste os SELECTs.
*/

/* Obrigatório estar logado */
if (empty($_SESSION['usuario_logado'])) {
  header('Location: ../../../index.php');
  exit;
}

/* Perfis (ajuste se quiser permitir OPERADOR também) */
$perfis = $_SESSION['perfis'] ?? [];
if (!is_array($perfis)) $perfis = [$perfis];
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../../operador/index.php');
  exit;
}

/* Conexão (padrão do seu sistema: db(): PDO) */
require '../../../assets/php/conexao.php';

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function trunc255(string $s): string {
  $s = trim($s);
  if ($s === '') return '';
  if (function_exists('mb_substr')) return mb_substr($s, 0, 255, 'UTF-8');
  return substr($s, 0, 255);
}

function ensure_dir(string $absDir): bool {
  if (is_dir($absDir)) return true;
  return @mkdir($absDir, 0755, true);
}

function to_decimal_str($v, int $scale): string {
  // aceita "10,5" e "10.5"
  $s = trim((string)$v);
  $s = str_replace(['.', ' '], ['.', ''], $s);
  $s = str_replace(',', '.', $s);
  if ($s === '' || !preg_match('/^-?\d+(\.\d+)?$/', $s)) return number_format(0, $scale, '.', '');
  $f = (float)$s;
  return number_format($f, $scale, '.', '');
}

/* Feira padrão desta página */
$FEIRA_ID = 1; // 1=Feira do Produtor | 2=Feira Alternativa

/* Detecção opcional pela pasta */
$dirLower = strtolower((string)__DIR__);
if (strpos($dirLower, 'alternativa') !== false) $FEIRA_ID = 2;
if (strpos($dirLower, 'produtor') !== false) $FEIRA_ID = 1;

/* Flash */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

$pdo = db();

/* Data do romaneio (por padrão hoje) */
date_default_timezone_set('America/Manaus'); // ajuste se quiser
$dataRef = (string)($_GET['data'] ?? date('Y-m-d'));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRef)) {
  $dataRef = date('Y-m-d');
}

/* Upload fotos (base64) */
$BASE_DIR = realpath(__DIR__ . '/../../../');
$UPLOAD_REL_DIR = 'uploads/romaneio';
$UPLOAD_ABS_DIR = $BASE_DIR ? ($BASE_DIR . DIRECTORY_SEPARATOR . $UPLOAD_REL_DIR) : null;
$MAX_BASE64_BYTES = 3 * 1024 * 1024; // 3MB por foto (bin decodificado)
$MAX_FOTOS = 3;

/* ====== Garante romaneio_dia do dia ====== */
$romaneioId = null;
$romaneioStatus = 'ABERTO';
$romaneioObs = null;

try {
  $st = $pdo->prepare("SELECT id, status, observacao
                       FROM romaneio_dia
                       WHERE feira_id = :feira AND data_ref = :data
                       LIMIT 1");
  $st->execute([
    ':feira' => $FEIRA_ID,
    ':data'  => $dataRef,
  ]);
  $row = $st->fetch(PDO::FETCH_ASSOC);

  if ($row) {
    $romaneioId = (int)$row['id'];
    $romaneioStatus = (string)$row['status'];
    $romaneioObs = $row['observacao'] ?? null;
  } else {
    $ins = $pdo->prepare("INSERT INTO romaneio_dia (feira_id, data_ref, status, observacao)
                          VALUES (:feira, :data, 'ABERTO', NULL)");
    $ins->execute([
      ':feira' => $FEIRA_ID,
      ':data'  => $dataRef,
    ]);
    $romaneioId = (int)$pdo->lastInsertId();
    $romaneioStatus = 'ABERTO';
    $romaneioObs = null;
  }
} catch (Throwable $e) {
  $err = 'Erro ao preparar romaneio do dia: ' . $e->getMessage();
}

/* ===== selects produtores / produtos ===== */
$produtores = [];
$produtos   = [];

if (!$err) {
  try {
    $stP = $pdo->prepare("SELECT id, nome
                          FROM produtores
                          WHERE feira_id = :feira AND ativo = 1
                          ORDER BY nome ASC");
    $stP->execute([':feira' => $FEIRA_ID]);
    $produtores = $stP->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $produtores = [];
  }

  try {
    $stPr = $pdo->prepare("SELECT id, nome
                           FROM produtos
                           WHERE feira_id = :feira AND (ativo = 1 OR ativo IS NULL)
                           ORDER BY nome ASC");
    $stPr->execute([':feira' => $FEIRA_ID]);
    $produtos = $stPr->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $produtos = [];
  }
}

/* ===== valores antigos ===== */
$old = [
  'produtor_id' => '',
  'produto_id'  => '',
  'quantidade_entrada' => '',
  'preco_unitario_dia' => '',
  'observacao' => '',
];

/* ===== POST handlers ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$err) {
  $tokenPost = (string)($_POST['csrf_token'] ?? '');
  if (!$tokenPost || !hash_equals($csrf, $tokenPost)) {
    $_SESSION['flash_err'] = 'Falha de segurança (CSRF). Recarregue a página e tente novamente.';
    header('Location: ./lancamentos.php?data=' . urlencode($dataRef));
    exit;
  }

  $action = (string)($_POST['action'] ?? '');

  /* ===== excluir item ===== */
  if ($action === 'delete_item') {
    $itemId = (string)($_POST['item_id'] ?? '');
    if ($itemId === '' || !ctype_digit($itemId)) {
      $_SESSION['flash_err'] = 'Item inválido.';
      header('Location: ./lancamentos.php?data=' . urlencode($dataRef));
      exit;
    }

    try {
      $pdo->beginTransaction();

      // confere se item é do romaneio do dia/feira
      $chk = $pdo->prepare("SELECT ri.id
                            FROM romaneio_itens ri
                            WHERE ri.id = :id AND ri.romaneio_id = :rom AND ri.feira_id = :feira
                            LIMIT 1");
      $chk->execute([
        ':id'    => (int)$itemId,
        ':rom'   => (int)$romaneioId,
        ':feira' => (int)$FEIRA_ID
      ]);
      $ok = (bool)$chk->fetchColumn();

      if (!$ok) {
        $pdo->rollBack();
        $_SESSION['flash_err'] = 'Item não encontrado (ou não pertence ao romaneio do dia).';
        header('Location: ./lancamentos.php?data=' . urlencode($dataRef));
        exit;
      }

      // pega fotos para remover arquivo
      $fotos = [];
      $stF = $pdo->prepare("SELECT id, caminho
                            FROM romaneio_item_fotos
                            WHERE romaneio_item_id = :item");
      $stF->execute([':item' => (int)$itemId]);
      $fotos = $stF->fetchAll(PDO::FETCH_ASSOC);

      // apaga fotos do banco
      $delF = $pdo->prepare("DELETE FROM romaneio_item_fotos WHERE romaneio_item_id = :item");
      $delF->execute([':item' => (int)$itemId]);

      // apaga item
      $delI = $pdo->prepare("DELETE FROM romaneio_itens WHERE id = :id LIMIT 1");
      $delI->execute([':id' => (int)$itemId]);

      $pdo->commit();

      // remove arquivos (fora da transação)
      if ($UPLOAD_ABS_DIR) {
        foreach ($fotos as $f) {
          $caminho = (string)($f['caminho'] ?? '');
          if ($caminho !== '') {
            $abs = $BASE_DIR ? ($BASE_DIR . DIRECTORY_SEPARATOR . $caminho) : null;
            if ($abs && is_file($abs)) @unlink($abs);
          }
        }
      }

      $_SESSION['flash_ok'] = 'Item removido.';
      header('Location: ./lancamentos.php?data=' . urlencode($dataRef));
      exit;

    } catch (Throwable $e) {
      if ($pdo->inTransaction()) $pdo->rollBack();
      $_SESSION['flash_err'] = 'Erro ao remover item: ' . $e->getMessage();
      header('Location: ./lancamentos.php?data=' . urlencode($dataRef));
      exit;
    }
  }

  /* ===== inserir item ===== */
  if ($action === 'create_item') {
    $old['produtor_id'] = trim((string)($_POST['produtor_id'] ?? ''));
    $old['produto_id']  = trim((string)($_POST['produto_id'] ?? ''));
    $old['quantidade_entrada'] = trim((string)($_POST['quantidade_entrada'] ?? ''));
    $old['preco_unitario_dia'] = trim((string)($_POST['preco_unitario_dia'] ?? ''));
    $old['observacao'] = trim((string)($_POST['observacao'] ?? ''));

    if ($romaneioStatus !== 'ABERTO') {
      $err = 'Romaneio do dia está FECHADO. Não é possível lançar itens.';
    } elseif ($old['produtor_id'] === '' || !ctype_digit($old['produtor_id'])) {
      $err = 'Selecione o produtor.';
    } elseif ($old['produto_id'] === '' || !ctype_digit($old['produto_id'])) {
      $err = 'Selecione o produto.';
    } else {
      $produtorId = (int)$old['produtor_id'];
      $produtoId  = (int)$old['produto_id'];

      $qtdEntrada = to_decimal_str($old['quantidade_entrada'], 3);
      $precoDia   = to_decimal_str($old['preco_unitario_dia'], 2);
      $obs        = trunc255($old['observacao']);

      if ((float)$qtdEntrada <= 0) {
        $err = 'Informe uma quantidade de entrada maior que 0.';
      } elseif ((float)$precoDia < 0) {
        $err = 'Preço unitário inválido.';
      } else {
        // valida produtor/produto da feira e ativos
        try {
          $chk1 = $pdo->prepare("SELECT COUNT(*) FROM produtores
                                 WHERE id = :id AND feira_id = :feira AND ativo = 1");
          $chk1->execute([':id' => $produtorId, ':feira' => $FEIRA_ID]);
          if ((int)$chk1->fetchColumn() <= 0) {
            $err = 'Produtor inválido (não encontrado/fora da feira/inativo).';
          }

          $chk2 = $pdo->prepare("SELECT COUNT(*) FROM produtos
                                 WHERE id = :id AND feira_id = :feira AND (ativo = 1 OR ativo IS NULL)");
          $chk2->execute([':id' => $produtoId, ':feira' => $FEIRA_ID]);
          if ((int)$chk2->fetchColumn() <= 0) {
            $err = 'Produto inválido (não encontrado/fora da feira/inativo).';
          }
        } catch (Throwable $e) {
          $err = 'Erro ao validar produtor/produto: ' . $e->getMessage();
        }

        // fotos (base64) - input: fotos_base64[] (até $MAX_FOTOS)
        $fotosBase64 = $_POST['fotos_base64'] ?? [];
        if (!is_array($fotosBase64)) $fotosBase64 = [];

        if ($err === '') {
          try {
            $pdo->beginTransaction();

            $ins = $pdo->prepare("INSERT INTO romaneio_itens
              (feira_id, romaneio_id, produtor_id, produto_id, quantidade_entrada, preco_unitario_dia, observacao)
              VALUES
              (:feira, :rom, :produtor, :produto, :qtd, :preco, :obs)");

            $ins->execute([
              ':feira'    => $FEIRA_ID,
              ':rom'      => (int)$romaneioId,
              ':produtor' => $produtorId,
              ':produto'  => $produtoId,
              ':qtd'      => $qtdEntrada,
              ':preco'    => $precoDia,
              ':obs'      => ($obs !== '' ? $obs : null),
            ]);

            $itemIdNew = (int)$pdo->lastInsertId();

            // salva fotos, se existirem
            $savedRelPaths = [];
            if (!empty($fotosBase64)) {
              if (!$UPLOAD_ABS_DIR || !$BASE_DIR) {
                throw new RuntimeException('Diretório base não encontrado para upload.');
              }

              if (!ensure_dir($UPLOAD_ABS_DIR)) {
                throw new RuntimeException('Não foi possível criar a pasta de upload.');
              }

              // subpasta por data
              $subRel = $UPLOAD_REL_DIR . '/' . $dataRef;
              $subAbs = $UPLOAD_ABS_DIR . DIRECTORY_SEPARATOR . $dataRef;
              if (!ensure_dir($subAbs)) {
                throw new RuntimeException('Não foi possível criar a pasta de upload do dia.');
              }

              $count = 0;
              foreach ($fotosBase64 as $dataUrl) {
                if ($count >= $MAX_FOTOS) break;
                $dataUrl = (string)$dataUrl;
                if ($dataUrl === '') continue;

                if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $dataUrl) !== 1) {
                  continue; // ignora inválida
                }

                $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
                $bin = base64_decode($base64, true);
                if ($bin === false) continue;
                if (strlen($bin) > $MAX_BASE64_BYTES) continue;

                $fileName = 'rom_' . $romaneioId . '_item_' . $itemIdNew . '_' . bin2hex(random_bytes(6)) . '.jpg';
                $destAbs = $subAbs . DIRECTORY_SEPARATOR . $fileName;

                if (@file_put_contents($destAbs, $bin) === false) {
                  continue;
                }

                $rel = $subRel . '/' . $fileName;
                $savedRelPaths[] = $rel;
                $count++;
              }

              if (!empty($savedRelPaths)) {
                $insF = $pdo->prepare("INSERT INTO romaneio_item_fotos (romaneio_item_id, caminho)
                                       VALUES (:item, :caminho)");
                foreach ($savedRelPaths as $rel) {
                  $insF->execute([
                    ':item'   => $itemIdNew,
                    ':caminho'=> $rel
                  ]);
                }
              }
            }

            $pdo->commit();

            $_SESSION['flash_ok'] = 'Lançamento salvo com sucesso!';
            header('Location: ./lancamentos.php?data=' . urlencode($dataRef));
            exit;

          } catch (Throwable $e) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $err = 'Erro ao salvar lançamento: ' . $e->getMessage();
          }
        }
      }
    }
  }
}

/* ===== Lista itens do romaneio ===== */
$itens = [];
if (!$err && $romaneioId) {
  try {
    $stI = $pdo->prepare("
      SELECT
        ri.id,
        ri.quantidade_entrada,
        ri.preco_unitario_dia,
        ri.observacao,
        p.nome  AS produtor_nome,
        pr.nome AS produto_nome,
        (SELECT COUNT(*) FROM romaneio_item_fotos f WHERE f.romaneio_item_id = ri.id) AS fotos_qtd,
        ri.criado_em
      FROM romaneio_itens ri
      JOIN produtores p ON p.id = ri.produtor_id
      JOIN produtos pr  ON pr.id = ri.produto_id
      WHERE ri.feira_id = :feira AND ri.romaneio_id = :rom
      ORDER BY ri.id DESC
    ");
    $stI->execute([':feira' => $FEIRA_ID, ':rom' => (int)$romaneioId]);
    $itens = $stI->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $itens = [];
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios — Lançamentos (Romaneio do Dia)</title>

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
    .form-control { min-height: 42px; height: auto; }
    .btn { min-height: 42px; }
    .help-hint { font-size: 12px; }
    .card-title-row { display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; }
    .req-badge { display:inline-block; font-size:11px; padding:2px 8px; border-radius:999px; background:#eef2ff; color:#1f2a6b; font-weight:700; margin-left:6px; vertical-align:middle; }
    .form-section { background:#fff; border:1px solid rgba(0,0,0,.06); border-radius:12px; padding:14px 14px 6px; margin-bottom:12px; }
    .form-section .section-title { font-weight:800; font-size:13px; margin-bottom:10px; color:#111827; display:flex; align-items:center; gap:8px; }
    .form-actions { display:flex; flex-wrap:wrap; gap:8px; align-items:center; justify-content:flex-start; }
    .cam-box { border:1px solid rgba(0,0,0,.08); border-radius:12px; padding:10px; background:#f8f9fa; }
    #cameraVideo, .fotoPreview { width:100%; border-radius:10px; background:#111; }
    #cameraVideo { display:none; }
    .fotoPreview { display:none; margin-top:8px; }
    .pill { display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; background:#f3f4f6; font-weight:700; font-size:12px; }
    .status-aberto { background:#ecfdf5; color:#065f46; }
    .status-fechado { background:#fef2f2; color:#991b1b; }

    @media (max-width: 576px) {
      .content-wrapper { padding: 1rem !important; }
      .form-actions .btn { width: 100%; }
      .card-title-row a.btn { width: 100%; }
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
      <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
        <span class="icon-menu"></span>
      </button>
    </div>
  </nav>

  <div class="container-fluid page-body-wrapper">

    <!-- SIDEBAR (mantido seu padrão; ajuste links se necessário) -->
    <nav class="sidebar sidebar-offcanvas" id="sidebar">
      <ul class="nav">
        <li class="nav-item">
          <a class="nav-link" href="index.php">
            <i class="icon-grid menu-icon"></i>
            <span class="menu-title">Dashboard</span>
          </a>
        </li>

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

        <li class="nav-item active">
          <a class="nav-link open" data-toggle="collapse" href="#feiraMovimento" aria-expanded="true" aria-controls="feiraMovimento">
            <i class="ti-exchange-vertical menu-icon"></i>
            <span class="menu-title">Movimento</span>
            <i class="menu-arrow"></i>
          </a>
          <div class="collapse show" id="feiraMovimento">
            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
              <li class="nav-item active">
                <a class="nav-link" href="./lancamentos.php" style="color:white !important; background: #231475C5 !important;">
                  <i class="ti-write mr-2"></i> Lançamentos (Romaneio)
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="./fechamentoDia.php">
                  <i class="ti-check-box mr-2"></i> Fechamento do Dia
                </a>
              </li>
            </ul>
          </div>
        </li>

        <li class="nav-item">
          <a class="nav-link" data-toggle="collapse" href="#feiraRelatorios" aria-expanded="false" aria-controls="feiraRelatorios">
            <i class="ti-clipboard menu-icon"></i>
            <span class="menu-title">Relatórios</span>
            <i class="menu-arrow"></i>
          </a>
          <div class="collapse" id="feiraRelatorios">
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
            <h3 class="font-weight-bold">Lançamentos do Dia (Romaneio)</h3>
            <h6 class="font-weight-normal mb-0">
              Feira <?= (int)$FEIRA_ID ?> — Data: <b><?= h($dataRef) ?></b>
            </h6>

            <div class="mt-2" style="display:flex; gap:10px; flex-wrap:wrap;">
              <span class="pill <?= ($romaneioStatus === 'ABERTO' ? 'status-aberto' : 'status-fechado') ?>">
                <i class="ti-flag"></i> Status: <?= h($romaneioStatus) ?>
              </span>
              <span class="pill">
                <i class="ti-receipt"></i> Romaneio ID: <?= (int)$romaneioId ?>
              </span>
            </div>
          </div>
        </div>

        <?php if (!empty($msg)): ?>
          <div class="alert alert-success"><?= h($msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($err)): ?>
          <div class="alert alert-danger"><?= h($err) ?></div>
        <?php endif; ?>

        <div class="row">
          <!-- FORM -->
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="card-title-row">
                  <div>
                    <h4 class="card-title mb-0">Novo Lançamento</h4>
                    <p class="card-description mb-0">
                      Registre a entrada do dia por produtor e produto.
                      <span class="req-badge">Obrigatório</span>
                    </p>
                  </div>

                  <form method="get" action="" style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                    <input type="date" class="form-control form-control-sm" name="data" value="<?= h($dataRef) ?>" style="max-width:220px;">
                    <button class="btn btn-light btn-sm" type="submit">
                      <i class="ti-search"></i> Ir
                    </button>
                  </form>
                </div>

                <?php if (empty($produtores) || empty($produtos)): ?>
                  <div class="alert alert-warning mt-3">
                    <?= empty($produtores) ? 'Nenhum produtor ativo encontrado para esta feira.' : '' ?>
                    <?= empty($produtos) ? 'Nenhum produto ativo encontrado para esta feira.' : '' ?>
                    <div class="mt-2"><small>Cadastre/ative produtores e produtos para poder lançar.</small></div>
                  </div>
                <?php endif; ?>

                <form class="pt-4" method="post" action="">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="create_item">

                  <!-- fotos base64 (até 3) -->
                  <input type="hidden" name="fotos_base64[]" id="foto_base64_1" value="">
                  <input type="hidden" name="fotos_base64[]" id="foto_base64_2" value="">
                  <input type="hidden" name="fotos_base64[]" id="foto_base64_3" value="">

                  <div class="form-section">
                    <div class="section-title"><i class="ti-package"></i> Item</div>

                    <div class="row">
                      <div class="col-12 col-lg-6 mb-3">
                        <label>Produtor <span class="text-danger">*</span></label>
                        <select name="produtor_id" class="form-control" required <?= (empty($produtores) || $romaneioStatus !== 'ABERTO') ? 'disabled' : '' ?>>
                          <option value="">Selecione</option>
                          <?php foreach ($produtores as $p): ?>
                            <option value="<?= (int)$p['id'] ?>" <?= ($old['produtor_id'] !== '' && (int)$old['produtor_id'] === (int)$p['id']) ? 'selected' : '' ?>>
                              <?= h($p['nome']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <small class="text-muted help-hint">Somente produtores ativos da feira.</small>
                      </div>

                      <div class="col-12 col-lg-6 mb-3">
                        <label>Produto <span class="text-danger">*</span></label>
                        <select name="produto_id" class="form-control" required <?= (empty($produtos) || $romaneioStatus !== 'ABERTO') ? 'disabled' : '' ?>>
                          <option value="">Selecione</option>
                          <?php foreach ($produtos as $pr): ?>
                            <option value="<?= (int)$pr['id'] ?>" <?= ($old['produto_id'] !== '' && (int)$old['produto_id'] === (int)$pr['id']) ? 'selected' : '' ?>>
                              <?= h($pr['nome']) ?>
                            </option>
                          <?php endforeach; ?>
                        </select>
                        <small class="text-muted help-hint">Somente produtos ativos da feira.</small>
                      </div>

                      <div class="col-12 col-md-6 col-lg-3 mb-3">
                        <label>Qtd. entrada <span class="text-danger">*</span></label>
                        <input
                          name="quantidade_entrada"
                          type="text"
                          class="form-control"
                          placeholder="Ex.: 10,500"
                          required
                          <?= ($romaneioStatus !== 'ABERTO') ? 'disabled' : '' ?>
                          value="<?= h($old['quantidade_entrada']) ?>">
                        <small class="text-muted help-hint">Decimal com 3 casas (aceita vírgula).</small>
                      </div>

                      <div class="col-12 col-md-6 col-lg-3 mb-3">
                        <label>Preço unitário do dia <span class="text-danger">*</span></label>
                        <input
                          name="preco_unitario_dia"
                          type="text"
                          class="form-control"
                          placeholder="Ex.: 7,50"
                          required
                          <?= ($romaneioStatus !== 'ABERTO') ? 'disabled' : '' ?>
                          value="<?= h($old['preco_unitario_dia']) ?>">
                        <small class="text-muted help-hint">Decimal com 2 casas (aceita vírgula).</small>
                      </div>

                      <div class="col-12 col-lg-6 mb-3">
                        <label>Observação</label>
                        <input
                          name="observacao"
                          type="text"
                          class="form-control"
                          placeholder="Opcional (até 255)"
                          <?= ($romaneioStatus !== 'ABERTO') ? 'disabled' : '' ?>
                          value="<?= h($old['observacao']) ?>">
                        <small class="text-muted help-hint">Ex.: lote, qualidade, aviso etc.</small>
                      </div>
                    </div>
                  </div>

                  <div class="form-section">
                    <div class="section-title"><i class="ti-camera"></i> Fotos (opcional)</div>

                    <div class="row">
                      <div class="col-12 col-lg-6 mb-3">
                        <label>Capturar fotos (até <?= (int)$MAX_FOTOS ?>)</label>

                        <div class="cam-box">
                          <video id="cameraVideo" autoplay playsinline></video>
                          <canvas id="cameraCanvas" style="display:none;"></canvas>

                          <img id="fotoPreview_1" class="fotoPreview" alt="Prévia foto 1">
                          <img id="fotoPreview_2" class="fotoPreview" alt="Prévia foto 2">
                          <img id="fotoPreview_3" class="fotoPreview" alt="Prévia foto 3">
                        </div>

                        <div class="mt-2 d-flex flex-wrap" style="gap:8px;">
                          <button type="button" class="btn btn-secondary btn-sm" id="btnAbrirCam" <?= ($romaneioStatus !== 'ABERTO') ? 'disabled' : '' ?>>
                            <i class="ti-camera mr-1"></i> Abrir Câmera
                          </button>
                          <button type="button" class="btn btn-primary btn-sm" id="btnTirarFoto" disabled>
                            <i class="ti-image mr-1"></i> Tirar Foto
                          </button>
                          <button type="button" class="btn btn-light btn-sm" id="btnLimparFotos" <?= ($romaneioStatus !== 'ABERTO') ? 'disabled' : '' ?>>
                            <i class="ti-trash mr-1"></i> Limpar Fotos
                          </button>
                          <button type="button" class="btn btn-danger btn-sm" id="btnFecharCam" disabled>
                            <i class="ti-close mr-1"></i> Fechar
                          </button>
                        </div>

                        <small class="text-muted help-hint d-block mt-1">
                          As fotos são comprimidas em JPEG antes de enviar.
                        </small>
                      </div>
                    </div>
                  </div>

                  <hr>

                  <div class="form-actions">
                    <button type="submit" class="btn btn-primary"
                      <?= (empty($produtores) || empty($produtos) || $romaneioStatus !== 'ABERTO') ? 'disabled' : '' ?>>
                      <i class="ti-save mr-1"></i> Salvar Lançamento
                    </button>
                    <a href="./lancamentos.php?data=<?= h(urlencode($dataRef)) ?>" class="btn btn-light">
                      <i class="ti-reload mr-1"></i> Recarregar
                    </a>
                  </div>

                  <?php if ($romaneioStatus !== 'ABERTO'): ?>
                    <div class="alert alert-warning mt-3">
                      Romaneio está <b>FECHADO</b>. Para lançar novamente, reabra no fechamento (ou ajuste o status no banco).
                    </div>
                  <?php endif; ?>
                </form>

              </div>
            </div>
          </div>
        </div>

        <!-- LISTA -->
        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">
                <div class="card-title-row">
                  <div>
                    <h4 class="card-title mb-0">Itens lançados</h4>
                    <p class="card-description mb-0">Registros do romaneio (entrada do dia).</p>
                  </div>
                </div>

                <?php if (empty($itens)): ?>
                  <div class="alert alert-info mt-3">Nenhum lançamento para esta data.</div>
                <?php else: ?>
                  <div class="table-responsive mt-3">
                    <table class="table table-striped">
                      <thead>
                        <tr>
                          <th>#</th>
                          <th>Produtor</th>
                          <th>Produto</th>
                          <th class="text-right">Qtd</th>
                          <th class="text-right">Preço</th>
                          <th>Obs</th>
                          <th class="text-center">Fotos</th>
                          <th class="text-right">Ações</th>
                        </tr>
                      </thead>
                      <tbody>
                        <?php foreach ($itens as $i): ?>
                          <tr>
                            <td><?= (int)$i['id'] ?></td>
                            <td><?= h($i['produtor_nome']) ?></td>
                            <td><?= h($i['produto_nome']) ?></td>
                            <td class="text-right"><?= h((string)$i['quantidade_entrada']) ?></td>
                            <td class="text-right">R$ <?= h((string)$i['preco_unitario_dia']) ?></td>
                            <td><?= h((string)($i['observacao'] ?? '')) ?></td>
                            <td class="text-center"><?= (int)$i['fotos_qtd'] ?></td>
                            <td class="text-right">
                              <form method="post" action="" onsubmit="return confirm('Remover este item?');" style="display:inline;">
                                <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                                <input type="hidden" name="action" value="delete_item">
                                <input type="hidden" name="item_id" value="<?= (int)$i['id'] ?>">
                                <button class="btn btn-danger btn-sm" type="submit" <?= ($romaneioStatus !== 'ABERTO') ? 'disabled' : '' ?>>
                                  <i class="ti-trash"></i>
                                </button>
                              </form>
                            </td>
                          </tr>
                        <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                <?php endif; ?>

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
  let stream = null;

  const video = document.getElementById('cameraVideo');
  const canvas = document.getElementById('cameraCanvas');

  const previews = [
    document.getElementById('fotoPreview_1'),
    document.getElementById('fotoPreview_2'),
    document.getElementById('fotoPreview_3')
  ];

  const inputs = [
    document.getElementById('foto_base64_1'),
    document.getElementById('foto_base64_2'),
    document.getElementById('foto_base64_3')
  ];

  const btnAbrir  = document.getElementById('btnAbrirCam');
  const btnTirar  = document.getElementById('btnTirarFoto');
  const btnFechar = document.getElementById('btnFecharCam');
  const btnLimpar = document.getElementById('btnLimparFotos');

  function countFotos() {
    let n = 0;
    for (const i of inputs) if (i && i.value) n++;
    return n;
  }

  function setState({ camOn }) {
    if (!video) return;
    video.style.display = camOn ? 'block' : 'none';
    btnTirar.disabled = !camOn;
    btnFechar.disabled = !camOn;
    btnAbrir.disabled = camOn;
  }

  async function abrirCamera() {
    try {
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false
      });
      video.srcObject = stream;
      await video.play();
      setState({ camOn: true });
    } catch (e) {
      alert('Não foi possível acessar a câmera. Verifique permissão/HTTPS.');
      setState({ camOn: false });
    }
  }

  function fecharCamera() {
    if (stream) {
      stream.getTracks().forEach(t => t.stop());
      stream = null;
    }
    if (video) video.srcObject = null;
    setState({ camOn: false });
  }

  function tirarFoto() {
    if (!video.videoWidth || !video.videoHeight) return;

    const idx = countFotos();
    if (idx >= inputs.length) {
      alert('Você já capturou o máximo de fotos.');
      return;
    }

    const targetW = 720;
    const ratio = video.videoHeight / video.videoWidth;
    const targetH = Math.round(targetW * ratio);

    canvas.width = targetW;
    canvas.height = targetH;

    const ctx = canvas.getContext('2d', { alpha: false });
    ctx.drawImage(video, 0, 0, targetW, targetH);

    const dataUrl = canvas.toDataURL('image/jpeg', 0.65);

    inputs[idx].value = dataUrl;
    previews[idx].src = dataUrl;
    previews[idx].style.display = 'block';

    // se já atingiu o máximo, fecha câmera
    if (countFotos() >= inputs.length) {
      fecharCamera();
    }
  }

  function limparFotos() {
    for (let k = 0; k < inputs.length; k++) {
      inputs[k].value = '';
      previews[k].src = '';
      previews[k].style.display = 'none';
    }
  }

  if (btnAbrir) btnAbrir.addEventListener('click', abrirCamera);
  if (btnFechar) btnFechar.addEventListener('click', fecharCamera);
  if (btnTirar) btnTirar.addEventListener('click', tirarFoto);
  if (btnLimpar) btnLimpar.addEventListener('click', limparFotos);

  setState({ camOn: false });
  window.addEventListener('beforeunload', fecharCamera);
})();
</script>

</body>
</html>