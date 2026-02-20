<?php
declare(strict_types=1);
session_start();
if (!ob_get_level()) ob_start();

/* ===== Segurança ===== */
if (empty($_SESSION['usuario_logado'])) {
  header('Location: ../../../index.php');
  exit;
}

$perfis = $_SESSION['perfis'] ?? [];
if (!is_array($perfis)) $perfis = [$perfis];
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../../operador/index.php');
  exit;
}

/* ===== Helpers ===== */
function h($s): string { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function only_digits(string $s): string { return preg_replace('/\D+/', '', $s) ?? ''; }

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
  try { return (new DateTime($s))->format('d/m/Y'); }
  catch (Throwable $e) { return $s; }
}

function ensure_dir(string $dir): bool {
  if (is_dir($dir)) return true;
  return @mkdir($dir, 0755, true);
}

/**
 * Salva foto base64 (jpeg/jpg/png/webp) e retorna:
 * [ 'rel' => 'uploads/romaneio/<romaneioId>/item_<id>_1.jpg', 'ok' => true ]
 */
function save_base64_image(?string $dataUrl, string $destDirAbs, string $destDirRel, string $baseNameNoExt, int $maxBytes): ?string
{
  if (!$dataUrl) return null;
  $dataUrl = trim($dataUrl);
  if ($dataUrl === '') return null;

  if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $dataUrl, $m) !== 1) return null;
  $ext = ($m[1] === 'jpeg') ? 'jpg' : $m[1];

  $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
  $bin = base64_decode($base64, true);
  if ($bin === false) return null;
  if (strlen($bin) > $maxBytes) return null;

  if (!ensure_dir($destDirAbs)) return null;

  $file = $baseNameNoExt . '.' . $ext;
  $abs  = rtrim($destDirAbs, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $file;

  if (@file_put_contents($abs, $bin) === false) return null;

  return rtrim($destDirRel, '/') . '/' . $file;
}

/* ===== Flash ===== */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* ===== Nome topo ===== */
$nomeTopo = (string)($_SESSION['nome'] ?? $_SESSION['usuario_nome'] ?? $_SESSION['usuario_logado'] ?? 'Usuário');

/* ===== CSRF ===== */
if (empty($_SESSION['csrf_token'])) $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
$csrf = (string)$_SESSION['csrf_token'];

/* ===== Conexão ===== */
require '../../../assets/php/conexao.php';
$pdo = db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Config ===== */
$feiraId = 1;

/* Dia */
$dia = trim((string)($_GET['dia'] ?? date('Y-m-d')));
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dia)) $dia = date('Y-m-d');

/* Upload */
$BASE_DIR    = realpath(__DIR__ . '/../../../');
$UPLOAD_REL  = 'uploads/romaneio';
$UPLOAD_ABS  = $BASE_DIR ? ($BASE_DIR . DIRECTORY_SEPARATOR . $UPLOAD_REL) : null;
$MAX_IMG_BYTES = 3 * 1024 * 1024;

/* ===== Romaneio do dia ===== */
$romaneioId = 0;
try {
  $st = $pdo->prepare("SELECT id FROM romaneio_dia WHERE feira_id = :f AND data_ref = :d LIMIT 1");
  $st->execute([':f' => $feiraId, ':d' => $dia]);
  $romaneioId = (int)($st->fetchColumn() ?: 0);

  if ($romaneioId <= 0) {
    $ins = $pdo->prepare("
      INSERT INTO romaneio_dia (feira_id, data_ref, status, criado_em)
      VALUES (:f, :d, 'ABERTO', NOW())
    ");
    $ins->execute([':f' => $feiraId, ':d' => $dia]);
    $romaneioId = (int)$pdo->lastInsertId();
  }
} catch (Throwable $e) {
  $_SESSION['flash_err'] = 'Não foi possível abrir o romaneio do dia.';
  header('Location: ./lancamentos.php?dia=' . urlencode($dia));
  exit;
}

/* ===== Combos ===== */
$produtoresAtivos = [];
$produtosAtivos = [];
try {
  // Produtores (com CPF/documento)
  $stP = $pdo->prepare("
    SELECT id, nome, COALESCE(documento,'') AS documento
    FROM produtores
    WHERE feira_id = :f AND ativo = 1
    ORDER BY nome ASC
  ");
  $stP->execute([':f' => $feiraId]);
  $produtoresAtivos = $stP->fetchAll(PDO::FETCH_ASSOC);

  // Produtos (inclui produtor_id para filtrar via JS)
  $stPr = $pdo->prepare("
    SELECT
      p.id, p.nome, p.produtor_id,
      COALESCE(c.nome,'') AS categoria_nome,
      COALESCE(u.sigla,'') AS unidade_sigla,
      p.preco_referencia
    FROM produtos p
    LEFT JOIN categorias c ON c.id = p.categoria_id AND c.feira_id = p.feira_id
    LEFT JOIN unidades u   ON u.id = p.unidade_id   AND u.feira_id = p.feira_id
    WHERE p.feira_id = :f AND p.ativo = 1
    ORDER BY p.nome ASC
  ");
  $stPr->execute([':f' => $feiraId]);
  $produtosAtivos = $stPr->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar produtores/produtos.';
}

/* ===== POST salvar ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    $_SESSION['flash_err'] = 'Sessão expirada. Atualize a página e tente novamente.';
    header('Location: ./lancamentos.php?dia=' . urlencode($dia));
    exit;
  }

  $acao = (string)($_POST['acao'] ?? '');
  if ($acao !== 'salvar') {
    header('Location: ./lancamentos.php?dia=' . urlencode($dia));
    exit;
  }

  $dataRef = trim((string)($_POST['data_ref'] ?? $dia));
  if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataRef)) $dataRef = $dia;
  if ($dataRef !== $dia) {
    header('Location: ./lancamentos.php?dia=' . urlencode($dataRef));
    exit;
  }

  $prodIds    = $_POST['produtor_id'] ?? [];
  $produtoIds = $_POST['produto_id'] ?? [];
  $qtds       = $_POST['quantidade_entrada'] ?? [];
  $precos     = $_POST['preco_unitario_dia'] ?? [];
  $obsArr     = $_POST['observacao_item'] ?? [];
  $fotosArr   = $_POST['foto_base64'] ?? [];

  $n = max(
    count((array)$prodIds),
    count((array)$produtoIds),
    count((array)$qtds),
    count((array)$precos),
    count((array)$obsArr),
    count((array)$fotosArr)
  );

  $itens = [];

  for ($i = 0; $i < $n; $i++) {
    $linha = $i + 1;

    $produtorRaw = trim((string)($prodIds[$i] ?? ''));
    $produtoRaw  = trim((string)($produtoIds[$i] ?? ''));
    $qtdRaw      = trim((string)($qtds[$i] ?? ''));
    $precoRaw    = trim((string)($precos[$i] ?? ''));
    $obs         = trim((string)($obsArr[$i] ?? ''));
    $foto        = trim((string)($fotosArr[$i] ?? ''));

    $produtorId = (int)$produtorRaw;
    $produtoId  = (int)$produtoRaw;
    $q = round(to_decimal($qtdRaw), 3);
    $p = round(to_decimal($precoRaw), 2);

    $temAlgo =
      ($produtorId > 0) ||
      ($produtoId > 0) ||
      ($qtdRaw !== '' && $q > 0) ||
      ($precoRaw !== '' && $p > 0) ||
      ($obs !== '') ||
      ($foto !== '');

    // ✅ ignora linha totalmente vazia
    if (!$temAlgo) continue;

    // ✅ valida apenas linhas com intenção
    if ($produtorId <= 0) {
      $_SESSION['flash_err'] = "Linha {$linha}: selecione o Produtor.";
      header('Location: ./lancamentos.php?dia=' . urlencode($dia));
      exit;
    }
    if ($produtoId <= 0) {
      $_SESSION['flash_err'] = "Linha {$linha}: selecione o Produto.";
      header('Location: ./lancamentos.php?dia=' . urlencode($dia));
      exit;
    }
    if ($q <= 0) {
      $_SESSION['flash_err'] = "Linha {$linha}: informe a Quantidade.";
      header('Location: ./lancamentos.php?dia=' . urlencode($dia));
      exit;
    }
    if ($p <= 0) {
      $_SESSION['flash_err'] = "Linha {$linha}: informe o Preço.";
      header('Location: ./lancamentos.php?dia=' . urlencode($dia));
      exit;
    }

    if ($obs !== '') $obs = mb_substr($obs, 0, 255, 'UTF-8');

    $itens[] = [
      'produtor_id' => $produtorId,
      'produto_id'  => $produtoId,
      'qtd'         => $q,
      'preco'       => $p,
      'obs'         => $obs,
      'foto'        => $foto,
    ];
  }

  if (!$itens) {
    $_SESSION['flash_err'] = 'Adicione pelo menos 1 item válido.';
    header('Location: ./lancamentos.php?dia=' . urlencode($dia));
    exit;
  }

  if (!$UPLOAD_ABS) {
    $_SESSION['flash_err'] = 'Diretório base não encontrado para upload.';
    header('Location: ./lancamentos.php?dia=' . urlencode($dia));
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
        (romaneio_item_id, caminho, criado_em)
      VALUES
        (:i, :c, NOW())
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
        $relPath = save_base64_image($it['foto'], $dayAbs, $dayRel, 'item_' . $itemId . '_1', $MAX_IMG_BYTES);
        if ($relPath) {
          $insFoto->execute([':i' => $itemId, ':c' => $relPath]);
        }
      }
    }

    $pdo->commit();
    $_SESSION['flash_ok'] = 'Entradas salvas com sucesso.';
  } catch (PDOException $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $mysqlCode = (int)($e->errorInfo[1] ?? 0);
    $_SESSION['flash_err'] = ($mysqlCode === 1062)
      ? 'Já existe lançamento para o mesmo produtor + produto neste dia.'
      : 'Erro ao salvar. Tente novamente.';
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $_SESSION['flash_err'] = 'Erro ao salvar. Tente novamente.';
  }

  header('Location: ./lancamentos.php?dia=' . urlencode($dia));
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
    .form-control { height: 42px }
    .btn { height: 42px }
    .helper { font-size: 12px }
    .card { border-radius: 14px }
    .card-header-lite {
      display:flex; align-items:flex-start; justify-content:space-between;
      gap:12px; flex-wrap:wrap;
      border-bottom:1px solid rgba(0,0,0,.06);
      padding-bottom:12px; margin-bottom:12px
    }
    .pill{
      display:inline-flex; align-items:center; gap:6px;
      padding:6px 10px; border-radius:999px;
      font-size:12px; font-weight:700;
      background:#eef2ff; color:#1f2a6b
    }
    .totbox{
      border:1px solid rgba(0,0,0,.08);
      background:#fff; border-radius:12px;
      padding:10px 12px; min-width:170px
    }
    .totlabel{font-size:12px;color:#6c757d;margin:0}
    .totvalue{font-size:20px;font-weight:900;margin:0}
    .line-card{
      border:1px solid rgba(0,0,0,.08);
      background:#fff; border-radius:14px;
      padding:12px; margin-bottom:10px
    }
    .mini{height:38px!important}
    .muted{color:#6c757d}
    .photo-thumb{
      width:76px; height:52px; object-fit:cover;
      border-radius:10px; border:1px solid rgba(0,0,0,.12);
      display:none
    }
    .sticky-actions{
      position:sticky; bottom:10px; z-index:3;
      background:rgba(255,255,255,.92);
      border:1px solid rgba(0,0,0,.08);
      border-radius:14px; padding:10px;
      backdrop-filter:blur(6px);
      display:flex; flex-wrap:wrap; gap:10px;
      justify-content:space-between; align-items:center;
      margin-top:12px
    }
    .sig-flash-wrap{
      position:fixed; top:78px; right:18px;
      width:min(420px, calc(100vw - 36px));
      z-index:9999; pointer-events:none
    }
    .sig-toast.alert{
      pointer-events:auto;
      border:0!important; border-left:6px solid!important;
      border-radius:14px!important;
      padding:10px 12px!important;
      box-shadow:0 10px 28px rgba(0,0,0,.10)!important;
      font-size:13px!important;
      margin-bottom:10px!important;
      opacity:0; transform:translateX(10px);
      animation:sigToastIn .22s ease-out forwards, sigToastOut .25s ease-in forwards 5.75s
    }
    .sig-toast--success{background:#f1fff6!important;border-left-color:#22c55e!important}
    .sig-toast--danger{background:#fff1f2!important;border-left-color:#ef4444!important}
    .sig-toast__row{display:flex;align-items:flex-start;gap:10px}
    .sig-toast__icon i{font-size:16px;margin-top:2px}
    .sig-toast__title{font-weight:900;margin-bottom:1px;line-height:1.1}
    .sig-toast__text{margin:0;line-height:1.25}
    @keyframes sigToastIn{to{opacity:1;transform:translateX(0)}}
    @keyframes sigToastOut{to{opacity:0;transform:translateX(12px);visibility:hidden}}

    .line-actions-simple{display:flex;gap:10px;flex-wrap:wrap;justify-content:flex-end}
    .btn-foto-big{height:46px;font-size:14px;font-weight:800;border-radius:12px;padding:10px 14px}
    .cam-box{border:1px solid rgba(0,0,0,.08);background:#fff;border-radius:14px;padding:10px}
    #camVideo,#camPreview{width:100%;border-radius:12px;background:#111;max-height:60vh;object-fit:cover}
    #camPreview{display:none}
    #camCanvas{display:none}

    .empty-state{
      border:1px dashed rgba(0,0,0,.20);
      border-radius:14px;
      padding:16px;
      background:#fff;
    }

    @media (max-width:576px){
      .card-header-lite{flex-direction:column;align-items:stretch!important;gap:10px!important}
      .totbox{width:100%}
      .totvalue{font-size:22px}
      .line-card{padding:14px}
      .line-card label{font-weight:700}
      .photo-thumb{width:100%!important;height:160px!important;border-radius:12px!important}
      .helper{font-size:13px}
      .sticky-actions{flex-direction:column;align-items:stretch}
      .sticky-actions>div{width:100%;justify-content:stretch!important}
      .sticky-actions .btn{width:100%}
      .line-actions-simple{width:100%;display:grid;grid-template-columns:1fr 1fr;gap:10px}
      .line-actions-simple .btn{height:52px!important;font-size:16px!important;font-weight:800!important;border-radius:12px!important}
      .line-actions-simple .btn i{font-size:18px;margin-right:6px}
    }
  </style>
</head>

<body>
<div class="container-scroller">

  <?php if ($msg || $err): ?>
    <div class="sig-flash-wrap">
      <?php if ($msg): ?>
        <div class="alert sig-toast sig-toast--success" role="alert">
          <div class="sig-toast__row">
            <div class="sig-toast__icon"><i class="ti-check"></i></div>
            <div>
              <div class="sig-toast__title">Tudo certo!</div>
              <p class="sig-toast__text"><?= h($msg) ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($err): ?>
        <div class="alert sig-toast sig-toast--danger" role="alert">
          <div class="sig-toast__row">
            <div class="sig-toast__icon"><i class="ti-alert"></i></div>
            <div>
              <div class="sig-toast__title">Atenção!</div>
              <p class="sig-toast__text"><?= h($err) ?></p>
            </div>
          </div>
        </div>
      <?php endif; ?>
    </div>
  <?php endif; ?>

  <!-- NAVBAR -->
  <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
    <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
      <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
      <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../../images/3.png" alt="logo"/></a>
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

  <div class="container-fluid page-body-wrapper">
    <nav class="sidebar sidebar-offcanvas" id="sidebar">
      <!-- ... seu menu ... -->
    </nav>

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
                    <p class="card-description mb-0">Comece adicionando uma linha. O sistema ignora linhas vazias.</p>
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

                <form method="post" action="./lancamentos.php?dia=<?= h($dia) ?>" autocomplete="off" id="formEntrada">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="acao" value="salvar">
                  <input type="hidden" name="data_ref" value="<?= h($dia) ?>">

                  <div id="linesWrap"></div>

                  <div id="emptyState" class="empty-state">
                    <div class="d-flex flex-column flex-sm-row align-items-start align-items-sm-center justify-content-between" style="gap:12px;">
                      <div>
                        <div style="font-weight:900;">Nenhuma linha adicionada</div>
                        <div class="text-muted helper">Clique em “Adicionar linha” para começar.</div>
                      </div>
                      <button type="button" class="btn btn-primary" id="btnAddFirst">
                        <i class="ti-plus mr-1"></i> Adicionar linha
                      </button>
                    </div>
                  </div>

                  <div class="sticky-actions">
                    <div class="d-flex flex-wrap" style="gap:8px;">
                      <button type="button" class="btn btn-light" id="btnAdd"><i class="ti-plus mr-1"></i> Adicionar linha</button>
                      <button type="button" class="btn btn-light" id="btnRef"><i class="ti-tag mr-1"></i> Preço ref.</button>
                      <button type="button" class="btn btn-light" id="btnLimparFotos"><i class="ti-close mr-1"></i> Limpar fotos</button>
                    </div>
                    <div class="d-flex flex-wrap" style="gap:8px;">
                      <button type="submit" class="btn btn-primary"><i class="ti-save mr-1"></i> Salvar entradas</button>
                      <a class="btn btn-light" href="./lancamentos.php?dia=<?= h($dia) ?>"><i class="ti-reload mr-1"></i> Recarregar</a>
                    </div>
                  </div>
                </form>

                <small class="text-muted d-block mt-3 helper">
                  * Dica: no celular, a câmera só funciona em HTTPS (ou localhost).
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

<!-- TEMPLATE da linha (JS clona) -->
<template id="tplLine">
  <div class="line-card js-line">
    <div class="row">
      <div class="col-lg-4 col-md-6 mb-3">
        <label class="mb-1">Produtor</label>
        <select class="form-control js-produtor" name="produtor_id[]">
          <option value="0">Selecione</option>
          <?php foreach ($produtoresAtivos as $p): ?>
            <?php $cpf = only_digits((string)($p['documento'] ?? '')); ?>
            <option value="<?= (int)$p['id'] ?>">
              <?= h(($p['nome'] ?? '') . ' — CPF: ' . ($cpf !== '' ? $cpf : '—')) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="col-lg-4 col-md-6 mb-3">
        <label class="mb-1">Produto</label>
        <select class="form-control js-produto" name="produto_id[]">
          <option value="0">Selecione</option>
          <?php foreach ($produtosAtivos as $pr): ?>
            <option value="<?= (int)$pr['id'] ?>"
              data-produtor="<?= (int)($pr['produtor_id'] ?? 0) ?>"
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
        <input type="text" class="form-control js-qtd" name="quantidade_entrada[]" value="" placeholder="1">
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
            <button type="button" class="btn btn-light js-remove">
              <i class="ti-trash"></i> Remover linha
            </button>
          </div>
        </div>

        <input type="hidden" class="js-foto-base64" name="foto_base64[]" value="">
        <input type="hidden" name="observacao_item[]" value="">
      </div>
    </div>
  </div>
</template>

<!-- MODAL CÂMERA -->
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
(function () {
  const wrap = document.getElementById('linesWrap');
  const tpl = document.getElementById('tplLine');
  const empty = document.getElementById('emptyState');

  const btnAdd = document.getElementById('btnAdd');
  const btnAddFirst = document.getElementById('btnAddFirst');
  const btnRef = document.getElementById('btnRef');
  const btnLimparFotos = document.getElementById('btnLimparFotos');
  const totalEl = document.getElementById('jsTotal');

  function brMoney(n) {
    try { return n.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }
    catch (e) { return String(Math.round(n * 100) / 100).replace('.', ','); }
  }

  function toNum(s) {
    s = String(s || '').trim();
    if (!s) return 0;
    s = s.replace(/R\$/g,'').replace(/\s/g,'').replace(/\./g,'').replace(',', '.');
    s = s.replace(/[^0-9.\-]/g,'');
    const v = parseFloat(s);
    return isNaN(v) ? 0 : v;
  }

  function setEmptyState() {
    const hasLines = !!wrap.querySelector('.js-line');
    empty.style.display = hasLines ? 'none' : 'block';
  }

  function syncInfo(line) {
    const sel = line.querySelector('.js-produto');
    const opt = sel && sel.options ? sel.options[sel.selectedIndex] : null;
    const un  = opt && opt.dataset ? (opt.dataset.un || '') : '';
    const cat = opt && opt.dataset ? (opt.dataset.cat || '') : '';
    line.querySelector('.js-un').value = un;
    line.querySelector('.js-cat').value = cat;

    // Se escolheu produto e qtd vazia, põe 1
    const qtd = line.querySelector('.js-qtd');
    if (qtd && !String(qtd.value||'').trim() && sel && parseInt(sel.value||'0',10) > 0) qtd.value = '1';
  }

  function calcTotal() {
    let tot = 0;
    wrap.querySelectorAll('.js-line').forEach(line => {
      const produtor = parseInt((line.querySelector('.js-produtor')||{}).value || '0', 10);
      const produto  = parseInt((line.querySelector('.js-produto')||{}).value || '0', 10);
      if (!produtor || !produto) return;

      const qtd   = toNum((line.querySelector('.js-qtd')||{}).value || '0');
      const preco = toNum((line.querySelector('.js-preco')||{}).value || '0');
      if (qtd > 0 && preco > 0) tot += qtd * preco;
    });
    totalEl.textContent = 'R$ ' + brMoney(tot);
  }

  function filterProdutosByProdutor(line) {
    const produtorId = parseInt((line.querySelector('.js-produtor')||{}).value || '0', 10);
    const selProd = line.querySelector('.js-produto');
    if (!selProd) return;

    const current = selProd.value;

    // mostra/oculta options (mantém "Selecione")
    [...selProd.options].forEach((opt, idx) => {
      if (idx === 0) return;
      const pid = parseInt(opt.dataset.produtor || '0', 10);
      opt.hidden = (produtorId > 0 && pid > 0 && pid !== produtorId);
    });

    // se o atual ficou hidden, reseta
    const optNow = selProd.options[selProd.selectedIndex];
    if (optNow && optNow.hidden) selProd.value = '0';

    syncInfo(line);
    calcTotal();
  }

  function wireLine(line) {
    const selProdutor = line.querySelector('.js-produtor');
    const selProduto  = line.querySelector('.js-produto');
    const qtd         = line.querySelector('.js-qtd');
    const preco       = line.querySelector('.js-preco');
    const btnRemove   = line.querySelector('.js-remove');

    selProdutor && selProdutor.addEventListener('change', () => filterProdutosByProdutor(line));
    selProduto  && selProduto.addEventListener('change', () => { syncInfo(line); calcTotal(); });
    qtd && qtd.addEventListener('input', calcTotal);
    preco && preco.addEventListener('input', calcTotal);

    btnRemove && (btnRemove.onclick = () => {
      line.remove();
      setEmptyState();
      calcTotal();
    });

    syncInfo(line);
    calcTotal();
  }

  function addLine() {
    const node = tpl.content.cloneNode(true);
    const line = node.querySelector('.js-line');
    wrap.appendChild(node);
    wireLine(wrap.lastElementChild);
    setEmptyState();
  }

  btnAdd && btnAdd.addEventListener('click', addLine);
  btnAddFirst && btnAddFirst.addEventListener('click', addLine);

  btnRef && btnRef.addEventListener('click', () => {
    wrap.querySelectorAll('.js-line').forEach(line => {
      const sel = line.querySelector('.js-produto');
      const precoIn = line.querySelector('.js-preco');
      if (!sel || !precoIn) return;
      const pid = parseInt(sel.value || '0', 10);
      if (!pid) return;
      const opt = sel.options[sel.selectedIndex];
      const ref = opt && opt.dataset ? (opt.dataset.preco || '') : '';
      if (!String(precoIn.value||'').trim() && ref) {
        const n = toNum(ref);
        if (n > 0) precoIn.value = brMoney(n);
      }
      syncInfo(line);
    });
    calcTotal();
  });

  btnLimparFotos && btnLimparFotos.addEventListener('click', () => {
    wrap.querySelectorAll('.js-line').forEach(line => {
      const hb = line.querySelector('.js-foto-base64');
      if (hb) hb.value = '';
      const thumb = line.querySelector('.js-thumb');
      if (thumb) { thumb.src=''; thumb.style.display='none'; }
    });
  });

  setEmptyState();
  calcTotal();

  // ===== CAMERA =====
  let currentLine = null;
  let stream = null;
  let capturedDataUrl = '';

  const camVideo = document.getElementById('camVideo');
  const camCanvas = document.getElementById('camCanvas');
  const camPreview = document.getElementById('camPreview');

  const btnTirarFoto = document.getElementById('btnTirarFoto');
  const btnRefazer = document.getElementById('btnRefazer');
  const btnUsarFoto = document.getElementById('btnUsarFoto');

  function setCamUI({ on, has }) {
    btnTirarFoto.disabled = !on;
    btnRefazer.disabled = !has;
    btnUsarFoto.disabled = !has;
    camPreview.style.display = has ? 'block' : 'none';
  }

  function closeCam() {
    if (stream) { stream.getTracks().forEach(t => t.stop()); stream = null; }
    if (camVideo) camVideo.srcObject = null;
  }

  async function openCam() {
    try {
      closeCam();
      stream = await navigator.mediaDevices.getUserMedia({
        video: { facingMode: { ideal: 'environment' } },
        audio: false
      });
      camVideo.srcObject = stream;
      await camVideo.play();
      capturedDataUrl = '';
      camPreview.src = '';
      setCamUI({ on: true, has: false });
    } catch (e) {
      alert('Não foi possível acessar a câmera. Verifique permissão e HTTPS (ou localhost).');
      setCamUI({ on: false, has: false });
    }
  }

  function snap() {
    if (!camVideo.videoWidth || !camVideo.videoHeight) return;
    const targetW = 720;
    const ratio = camVideo.videoHeight / camVideo.videoWidth;
    const targetH = Math.round(targetW * ratio);

    camCanvas.width = targetW;
    camCanvas.height = targetH;
    const ctx = camCanvas.getContext('2d', { alpha: false });
    ctx.drawImage(camVideo, 0, 0, targetW, targetH);

    capturedDataUrl = camCanvas.toDataURL('image/jpeg', 0.65);
    camPreview.src = capturedDataUrl;

    closeCam();
    setCamUI({ on: false, has: true });
  }

  function redo() {
    capturedDataUrl = '';
    camPreview.src = '';
    camPreview.style.display = 'none';
    openCam();
  }

  document.addEventListener('click', function (e) {
    const btn = e.target.closest('.js-foto');
    if (!btn) return;

    currentLine = btn.closest('.js-line');
    capturedDataUrl = '';
    camPreview.src = '';
    setCamUI({ on: false, has: false });

    if (window.jQuery && jQuery.fn.modal) {
      jQuery('#modalCamera').modal('show');
      jQuery('#modalCamera').one('shown.bs.modal', function () { openCam(); });
    } else {
      openCam();
    }
  });

  btnTirarFoto && btnTirarFoto.addEventListener('click', snap);
  btnRefazer && btnRefazer.addEventListener('click', redo);

  btnUsarFoto && btnUsarFoto.addEventListener('click', function () {
    if (!currentLine || !capturedDataUrl) return;

    const hb = currentLine.querySelector('.js-foto-base64');
    if (hb) hb.value = capturedDataUrl;

    const thumb = currentLine.querySelector('.js-thumb');
    if (thumb) { thumb.src = capturedDataUrl; thumb.style.display = 'block'; }

    if (window.jQuery && jQuery.fn.modal) jQuery('#modalCamera').modal('hide');
  });

  if (window.jQuery) {
    jQuery('#modalCamera').on('hidden.bs.modal', function () {
      closeCam();
      capturedDataUrl = '';
      camPreview.src = '';
      setCamUI({ on: false, has: false });
    });
  }
})();
</script>
</body>
</html>