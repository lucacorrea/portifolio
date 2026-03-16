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

/* Conexão */
require '../../../assets/php/conexao.php';

function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function trunc255(string $s): string
{
  $s = trim($s);
  if ($s === '') return '';
  if (function_exists('mb_substr')) return mb_substr($s, 0, 255, 'UTF-8');
  return substr($s, 0, 255);
}

function truncLen(string $s, int $max): string
{
  $s = trim($s);
  if ($s === '') return '';
  if (function_exists('mb_substr')) return mb_substr($s, 0, $max, 'UTF-8');
  return substr($s, 0, $max);
}

function only_digits(string $s): string
{
  $out = preg_replace('/\D+/', '', $s);
  return $out !== null ? $out : '';
}

function ensure_dir(string $absDir): bool
{
  if (is_dir($absDir)) return true;
  return @mkdir($absDir, 0755, true);
}

/* Feira padrão desta página */
$FEIRA_ID = 3; // 1=Feira do Produtor | 2=Feira Alternativa | 3=Mercado Municipal

/* Detecção opcional pela pasta */
$dirLower = strtolower((string)__DIR__);
if (strpos($dirLower, 'alternativa') !== false) $FEIRA_ID = 2;
if (strpos($dirLower, 'produtor') !== false) $FEIRA_ID = 1;
if (strpos($dirLower, 'mercado') !== false) $FEIRA_ID = 3;

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

/* ===== Comunidades / Bairros (para o SELECT agrupado) ===== */
$comunidades = [];
try {
  $sqlC = "SELECT id, nome, tipo
           FROM comunidades
           WHERE feira_id = :feira AND ativo = 1
           ORDER BY
             CASE WHEN UPPER(COALESCE(tipo,'')) = 'BAIRRO' THEN 0 ELSE 1 END,
             nome ASC";
  $stC = $pdo->prepare($sqlC);
  $stC->bindValue(':feira', $FEIRA_ID, PDO::PARAM_INT);
  $stC->execute();
  $comunidades = $stC->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $comunidades = [];
}

/* Separa bairros e comunidades para optgroup */
$bairrosLista = [];
$comunidadesLista = [];

foreach ($comunidades as $c) {
  if (strtoupper((string)($c['tipo'] ?? '')) === 'BAIRRO') {
    $bairrosLista[] = $c;
  } else {
    $comunidadesLista[] = $c;
  }
}

/* Valores antigos */
$old = [
  'nome'           => '',
  'documento'      => '',
  'contato'        => '',
  'comunidade_id'  => '',
  'box_numero'     => '',
  'setor'          => '',
  'ramo_atividade' => '',
  'ativo'          => '1',
  'observacao'     => '',
];

/* Upload (base64 da câmera via navegador) */
$BASE_DIR = realpath(__DIR__ . '/../../../');
$UPLOAD_REL_DIR = 'uploads/permissionarios';
$UPLOAD_ABS_DIR = $BASE_DIR ? ($BASE_DIR . DIRECTORY_SEPARATOR . $UPLOAD_REL_DIR) : null;
$MAX_BASE64_BYTES = 3 * 1024 * 1024; // 3MB em bytes decodificados

/* POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tokenPost = (string)($_POST['csrf_token'] ?? '');
  if (!$tokenPost || !hash_equals($csrf, $tokenPost)) {
    $_SESSION['flash_err'] = 'Falha de segurança (CSRF). Recarregue a página e tente novamente.';
    header('Location: ./adicionarProdutor.php');
    exit;
  }

  $old['nome']           = trim((string)($_POST['nome'] ?? ''));
  $old['documento']      = trim((string)($_POST['documento'] ?? ''));
  $old['contato']        = trim((string)($_POST['contato'] ?? ''));
  $old['comunidade_id']  = trim((string)($_POST['comunidade_id'] ?? ''));
  $old['box_numero']     = trim((string)($_POST['box_numero'] ?? ''));
  $old['setor']          = trim((string)($_POST['setor'] ?? ''));
  $old['ramo_atividade'] = trim((string)($_POST['ramo_atividade'] ?? ''));
  $old['ativo']          = (string)($_POST['ativo'] ?? '1');
  $old['observacao']     = trim((string)($_POST['observacao'] ?? ''));

  if ($old['nome'] === '') {
    $err = 'Informe o nome do permissionário.';
  } elseif ($old['comunidade_id'] === '' || !ctype_digit($old['comunidade_id'])) {
    $err = 'Selecione a localidade do permissionário.';
  } else {
    $nome = truncLen($old['nome'], 160);
    $contato = truncLen($old['contato'], 60);
    $boxNumero = truncLen($old['box_numero'], 30);
    $setor = truncLen($old['setor'], 100);
    $ramoAtividade = truncLen($old['ramo_atividade'], 120);

    /* documento: salva somente dígitos */
    $docDigits = only_digits($old['documento']);
    $documento = $docDigits !== '' ? truncLen($docDigits, 30) : null;

    $observacao = trunc255($old['observacao']);
    $ativo = ($old['ativo'] === '1') ? 1 : 0;
    $comunidadeId = (int)$old['comunidade_id'];

    /* ========= Foto (base64 vinda da câmera) ========= */
    $fotoDbValue = null;

    if (!empty($_POST['foto_base64'])) {
      $dataUrl = (string)$_POST['foto_base64'];

      if (preg_match('/^data:image\/(jpeg|jpg|png|webp);base64,/', $dataUrl) !== 1) {
        $err = 'Foto inválida (formato não suportado).';
      } else {
        $base64 = substr($dataUrl, strpos($dataUrl, ',') + 1);
        $bin = base64_decode($base64, true);

        if ($bin === false) {
          $err = 'Foto inválida (base64).';
        } elseif (strlen($bin) > $MAX_BASE64_BYTES) {
          $err = 'Foto muito grande. Máximo: 3MB.';
        } elseif (!$UPLOAD_ABS_DIR) {
          $err = 'Diretório base não encontrado para upload.';
        } else {
          if (!ensure_dir($UPLOAD_ABS_DIR)) {
            $err = 'Não foi possível criar a pasta de upload.';
          } else {
            $fileName = 'permissionario_' . bin2hex(random_bytes(10)) . '.jpg';
            $destAbs = $UPLOAD_ABS_DIR . DIRECTORY_SEPARATOR . $fileName;

            if (@file_put_contents($destAbs, $bin) === false) {
              $err = 'Falha ao salvar a foto no servidor.';
            } else {
              $fotoDbValue = $UPLOAD_REL_DIR . '/' . $fileName;
            }
          }
        }
      }
    }

    if ($err === '') {
      try {
        $chk = $pdo->prepare("
          SELECT COUNT(*)
          FROM comunidades
          WHERE id = :id
            AND feira_id = :feira
            AND ativo = 1
        ");
        $chk->bindValue(':id', $comunidadeId, PDO::PARAM_INT);
        $chk->bindValue(':feira', $FEIRA_ID, PDO::PARAM_INT);
        $chk->execute();
        $okCom = (int)$chk->fetchColumn() > 0;

        if (!$okCom) {
          if ($fotoDbValue) {
            @unlink($UPLOAD_ABS_DIR . DIRECTORY_SEPARATOR . basename($fotoDbValue));
          }
          $err = 'Localidade inválida (não encontrada ou inativa).';
        } else {
          $sql = "INSERT INTO permissionarios
                    (
                      feira_id, nome, contato, comunidade_id, documento, foto,
                      box_numero, setor, ramo_atividade, ativo, observacao
                    )
                  VALUES
                    (
                      :feira_id, :nome, :contato, :comunidade_id, :documento, :foto,
                      :box_numero, :setor, :ramo_atividade, :ativo, :observacao
                    )";
          $stmt = $pdo->prepare($sql);
          $stmt->execute([
            ':feira_id'      => $FEIRA_ID,
            ':nome'          => $nome,
            ':contato'       => ($contato !== '' ? $contato : null),
            ':comunidade_id' => $comunidadeId,
            ':documento'     => $documento,
            ':foto'          => $fotoDbValue,
            ':box_numero'    => ($boxNumero !== '' ? $boxNumero : null),
            ':setor'         => ($setor !== '' ? $setor : null),
            ':ramo_atividade' => ($ramoAtividade !== '' ? $ramoAtividade : null),
            ':ativo'         => $ativo,
            ':observacao'    => ($observacao !== '' ? $observacao : null),
          ]);

          $_SESSION['flash_ok'] = 'Permissionário cadastrado com sucesso!';
          header('Location: ./listaProdutor.php');
          exit;
        }
      } catch (Throwable $e) {
        if ($fotoDbValue && $UPLOAD_ABS_DIR) {
          @unlink($UPLOAD_ABS_DIR . DIRECTORY_SEPARATOR . basename($fotoDbValue));
        }
        $err = 'Erro ao salvar permissionário: ' . $e->getMessage();
      }
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira do Produtor — Adicionar Permissionário</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">

  <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" type="text/css" href="../../../js/select.dataTables.min.css">

  <link rel="stylesheet" href="../../../css/vertical-layout-light/style.css">
  <link rel="shortcut icon" href="../../../images/3.png" />

  <style>
    ul .nav-link:hover {
      color: blue !important;
    }

    .nav-link {
      color: black !important;
    }

    .sidebar .sub-menu .nav-item .nav-link {
      margin-left: -35px !important;
    }

    .sidebar .sub-menu li {
      list-style: none !important;
    }

    .form-control {
      min-height: 42px;
      height: auto;
    }

    .btn {
      min-height: 42px;
    }

    .help-hint {
      font-size: 12px;
    }

    .card-title-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
    }

    .req-badge {
      display: inline-block;
      font-size: 11px;
      padding: 2px 8px;
      border-radius: 999px;
      background: #eef2ff;
      color: #1f2a6b;
      font-weight: 700;
      margin-left: 6px;
      vertical-align: middle;
    }

    .form-section {
      background: #fff;
      border: 1px solid rgba(0, 0, 0, .06);
      border-radius: 12px;
      padding: 14px 14px 6px 14px;
      margin-bottom: 12px;
    }

    .form-section .section-title {
      font-weight: 800;
      font-size: 13px;
      margin-bottom: 10px;
      color: #111827;
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .form-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      align-items: center;
      justify-content: flex-start;
    }

    .cam-box {
      border: 1px solid rgba(0, 0, 0, .08);
      border-radius: 12px;
      padding: 10px;
      background: #f8f9fa;
    }

    #cameraVideo,
    #fotoPreview {
      width: 100%;
      border-radius: 10px;
      background: #111;
    }

    #cameraVideo {
      display: none;
    }

    #fotoPreview {
      display: none;
    }

    @media (max-width: 576px) {
      .content-wrapper {
        padding: 1rem !important;
      }

      .form-actions .btn {
        width: 100%;
      }

      .card-title-row a.btn {
        width: 100%;
      }
    }
  </style>
</head>

<body>
  <nav class="navbar col-lg-12 col-12 p-0 fixed-top d-flex flex-row">
    <div class="text-center navbar-brand-wrapper d-flex align-items-center justify-content-center">
      <a class="navbar-brand brand-logo mr-5" href="index.php">SIGRelatórios</a>
      <a class="navbar-brand brand-logo-mini" href="index.php"><img src="../../../images/3.png" alt="logo" /></a>
    </div>
    <div class="navbar-menu-wrapper d-flex align-items-center justify-content-end">
      <button class="navbar-toggler navbar-toggler align-self-center" type="button" data-toggle="minimize">
        <span class="icon-menu"></span>
      </button>

      <ul class="navbar-nav mr-lg-2">
        <li class="nav-item nav-search d-none d-lg-block"></li>
      </ul>

      <ul class="navbar-nav navbar-nav-right"></ul>

      <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
        <span class="icon-menu"></span>
      </button>
    </div>
  </nav>

  <div class="container-fluid page-body-wrapper">

    <div id="right-sidebar" class="settings-panel">
      <i class="settings-close ti-close"></i>
      <ul class="nav nav-tabs border-top" id="setting-panel" role="tablist">
        <li class="nav-item">
          <a class="nav-link active" id="todo-tab" data-toggle="tab" href="#todo-section" role="tab" aria-controls="todo-section" aria-expanded="true">TO DO LIST</a>
        </li>
        <li class="nav-item">
          <a class="nav-link" id="chats-tab" data-toggle="tab" href="#chats-section" role="tab" aria-controls="chats-section">CHATS</a>
        </li>
      </ul>
    </div>

    <nav class="sidebar sidebar-offcanvas" id="sidebar">
      <ul class="nav">

        <li class="nav-item">
          <a class="nav-link" href="index.php">
            <i class="icon-grid menu-icon"></i>
            <span class="menu-title">Dashboard</span>
          </a>
        </li>

        <li class="nav-item active">
          <a class="nav-link open" data-toggle="collapse" href="#feiraCadastros" aria-expanded="true" aria-controls="feiraCadastros">
            <i class="ti-id-badge menu-icon"></i>
            <span class="menu-title">Cadastros</span>
            <i class="menu-arrow"></i>
          </a>

          <div class="collapse show" id="feiraCadastros">
            <style>
              .sub-menu .nav-item .nav-link {
                color: black !important;
              }

              .sub-menu .nav-item .nav-link:hover {
                color: blue !important;
              }
            </style>

            <ul class="nav flex-column sub-menu" style="background: white !important;">
              <li class="nav-item">
                <a class="nav-link" href="./listaProduto.php">
                  <i class="ti-clipboard mr-2"></i> Lista de Produtos
                </a>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="./listaCategoria.php">
                  <i class="ti-layers mr-2"></i> Categorias
                </a>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="./listaUnidade.php">
                  <i class="ti-ruler-pencil mr-2"></i> Unidades
                </a>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="./listaProdutor.php">
                  <i class="ti-user mr-2"></i> Permissionários
                </a>
              </li>

              <li class="nav-item active">
                <a class="nav-link" href="./adicionarPermissionario.php" style="color:white !important; background: #231475C5 !important;">
                  <i class="ti-plus mr-2"></i> Adicionar Permissionário
                </a>
              </li>
            </ul>
          </div>
        </li>

        <li class="nav-item">
          <a class="nav-link" data-toggle="collapse" href="#feiraMovimento" aria-expanded="false" aria-controls="feiraMovimento">
            <i class="ti-exchange-vertical menu-icon"></i>
            <span class="menu-title">Movimento</span>
            <i class="menu-arrow"></i>
          </a>

          <div class="collapse" id="feiraMovimento">
            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
              <li class="nav-item">
                <a class="nav-link" href="./lancamentos.php">
                  <i class="ti-write mr-2"></i> Lançamentos (Vendas)
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

          <div class="collapse text-black" id="feiraRelatorios">
            <ul class="nav flex-column sub-menu" style="background:#fff !important;">
              <li class="nav-item">
                <a class="nav-link" href="./relatorioFinanceiro.php">
                  <i class="ti-bar-chart mr-2"></i> Relatório Financeiro
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="./relatorioProdutos.php">
                  <i class="ti-list mr-2"></i> Produtos Comercializados
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="./relatorioMensal.php">
                  <i class="ti-calendar mr-2"></i> Resumo Mensal
                </a>
              </li>
              <li class="nav-item">
                <a class="nav-link" href="./configRelatorio.php">
                  <i class="ti-settings mr-2"></i> Configurar
                </a>
              </li>
            </ul>
          </div>
        </li>

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

        <li class="nav-item">
          <a class="nav-link" href="../index.php">
            <i class="ti-home menu-icon"></i>
            <span class="menu-title"> Painel Principal</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="../produtor/" class="nav-link">
            <i class="ti-shopping-cart menu-icon"></i>
            <span class="menu-title">Feira do Produtor</span>
          </a>
        </li>
        <li class="nav-item">
          <a href="../alternativa/" class="nav-link">
            <i class="ti-shopping-cart menu-icon"></i>
            <span class="menu-title">Feira Alternativa</span>
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

    <div class="main-panel">
      <div class="content-wrapper">

        <div class="row">
          <div class="col-12 mb-3">
            <h3 class="font-weight-bold">Adicionar Permissionário</h3>
            <h6 class="font-weight-normal mb-0">Cadastro de permissionário do mercado/feira.</h6>
          </div>
        </div>

        <?php if (!empty($msg)): ?>
          <div class="alert alert-success"><?= h($msg) ?></div>
        <?php endif; ?>
        <?php if (!empty($err)): ?>
          <div class="alert alert-danger"><?= h($err) ?></div>
        <?php endif; ?>

        <div class="row">
          <div class="col-lg-12 grid-margin stretch-card">
            <div class="card">
              <div class="card-body">

                <div class="card-title-row">
                  <div>
                    <h4 class="card-title mb-0">Dados do Permissionário</h4>
                    <p class="card-description mb-0">
                      Localidade é obrigatória e vem do cadastro de comunidades.
                      <span class="req-badge">Obrigatório</span>
                    </p>
                  </div>
                  <a href="./listaPermissionario.php" class="btn btn-light btn-sm">
                    <i class="ti-arrow-left"></i> Voltar
                  </a>
                </div>

                <?php if (empty($comunidades)): ?>
                  <div class="alert alert-warning mt-3">
                    Nenhuma comunidade ativa cadastrada para esta feira.
                    Cadastre comunidades primeiro para poder cadastrar permissionários.
                  </div>
                <?php endif; ?>

                <form class="pt-4" method="post" action="">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="foto_base64" id="foto_base64" value="">

                  <div class="form-section">
                    <div class="section-title">
                      <i class="ti-user"></i> Identificação
                    </div>

                    <div class="row">
                      <div class="col-12 col-lg-6 mb-3">
                        <label>Nome do permissionário <span class="text-danger">*</span></label>
                        <input
                          name="nome"
                          type="text"
                          class="form-control"
                          placeholder="Ex.: João Batista da Silva"
                          required
                          value="<?= h($old['nome']) ?>">
                        <small class="text-muted help-hint">Nome completo ou como é conhecido.</small>
                      </div>

                      <div class="col-12 col-md-6 col-lg-3 mb-3">
                        <label>CPF / Documento</label>
                        <input
                          name="documento"
                          type="text"
                          class="form-control"
                          placeholder="Somente números"
                          inputmode="numeric"
                          value="<?= h($old['documento']) ?>">
                        <small class="text-muted help-hint">Opcional.</small>
                      </div>

                      <div class="col-12 col-md-6 col-lg-3 mb-3">
                        <label>Telefone / WhatsApp</label>
                        <input
                          name="contato"
                          type="text"
                          class="form-control"
                          placeholder="Ex.: 92991112222"
                          value="<?= h($old['contato']) ?>">
                        <small class="text-muted help-hint">Opcional.</small>
                      </div>

                      <div class="col-12 col-md-6 col-lg-3 mb-3">
                        <label>Nº do Box</label>
                        <input
                          name="box_numero"
                          type="text"
                          class="form-control"
                          placeholder="Ex.: B-12"
                          value="<?= h($old['box_numero']) ?>">
                        <small class="text-muted help-hint">Opcional.</small>
                      </div>

                      <div class="col-12 col-md-6 col-lg-3 mb-3">
                        <label>Setor</label>
                        <input
                          name="setor"
                          type="text"
                          class="form-control"
                          placeholder="Ex.: Hortifruti"
                          value="<?= h($old['setor']) ?>">
                        <small class="text-muted help-hint">Opcional.</small>
                      </div>

                      <div class="col-12 col-lg-6 mb-3">
                        <label>Ramo de atividade</label>
                        <input
                          name="ramo_atividade"
                          type="text"
                          class="form-control"
                          placeholder="Ex.: Venda de verduras e legumes"
                          value="<?= h($old['ramo_atividade']) ?>">
                        <small class="text-muted help-hint">Opcional.</small>
                      </div>

                      <div class="col-12 col-lg-6 mb-3">
                        <label>Foto do permissionário (câmera)</label>

                        <div class="cam-box">
                          <video id="cameraVideo" autoplay playsinline></video>
                          <canvas id="cameraCanvas" style="display:none;"></canvas>
                          <img id="fotoPreview" alt="Prévia da foto">
                        </div>

                        <div class="mt-2 d-flex flex-wrap" style="gap:8px;">
                          <button type="button" class="btn btn-secondary btn-sm" id="btnAbrirCam">
                            <i class="ti-camera mr-1"></i> Abrir Câmera
                          </button>
                          <button type="button" class="btn btn-primary btn-sm" id="btnTirarFoto" disabled>
                            <i class="ti-image mr-1"></i> Tirar Foto
                          </button>
                          <button type="button" class="btn btn-light btn-sm" id="btnRefazer" disabled>
                            <i class="ti-reload mr-1"></i> Refazer
                          </button>
                          <button type="button" class="btn btn-danger btn-sm" id="btnFecharCam" disabled>
                            <i class="ti-close mr-1"></i> Fechar
                          </button>
                        </div>

                        <small class="text-muted help-hint d-block mt-1">
                          A foto é comprimida em JPEG antes de enviar.
                        </small>
                      </div>
                    </div>
                  </div>

                  <div class="form-section">
                    <div class="section-title">
                      <i class="ti-map-alt"></i> Localidade
                    </div>

                    <div class="row">
                      <div class="col-12 col-lg-6 mb-3">
                        <label>Localidade <span class="text-danger">*</span></label>

                        <select
                          name="comunidade_id"
                          class="form-control"
                          <?= empty($comunidades) ? 'disabled' : 'required' ?>>

                          <option value="">Selecione</option>

                          <?php if (!empty($bairrosLista)): ?>
                            <optgroup label="Bairros">
                              <?php foreach ($bairrosLista as $b): ?>
                                <option
                                  value="<?= (int)$b['id'] ?>"
                                  <?= ($old['comunidade_id'] !== '' && (int)$old['comunidade_id'] === (int)$b['id']) ? 'selected' : '' ?>>
                                  <?= h($b['nome']) ?>
                                </option>
                              <?php endforeach; ?>
                            </optgroup>
                          <?php endif; ?>

                          <?php if (!empty($comunidadesLista)): ?>
                            <optgroup label="Comunidades">
                              <?php foreach ($comunidadesLista as $c): ?>
                                <option
                                  value="<?= (int)$c['id'] ?>"
                                  <?= ($old['comunidade_id'] !== '' && (int)$old['comunidade_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                                  <?= h($c['nome']) ?>
                                </option>
                              <?php endforeach; ?>
                            </optgroup>
                          <?php endif; ?>

                        </select>

                        <small class="text-muted help-hint">
                          Selecione o bairro ou a comunidade de origem.
                        </small>
                      </div>

                      <div class="col-12 col-md-6 col-lg-3 mb-3">
                        <label>Status geral</label>
                        <select name="ativo" class="form-control">
                          <option value="1" <?= ($old['ativo'] === '1' ? 'selected' : '') ?>>Ativo</option>
                          <option value="0" <?= ($old['ativo'] === '0' ? 'selected' : '') ?>>Inativo</option>
                        </select>
                        <small class="text-muted help-hint">Você pode desativar sem excluir.</small>
                      </div>

                      <div class="col-12 mb-3">
                        <label>Observações</label>
                        <textarea
                          name="observacao"
                          class="form-control"
                          rows="4"
                          placeholder="Ex.: trabalha no box 12, documentação pendente..."><?= h($old['observacao']) ?></textarea>
                        <small class="text-muted help-hint">Opcional (até 255 caracteres).</small>
                      </div>
                    </div>
                  </div>

                  <hr>

                  <div class="form-actions">
                    <button type="submit" class="btn btn-primary" <?= empty($comunidades) ? 'disabled' : '' ?>>
                      <i class="ti-save mr-1"></i> Salvar
                    </button>
                    <button type="reset" class="btn btn-light" id="btnLimparForm">
                      <i class="ti-close mr-1"></i> Limpar
                    </button>
                  </div>

                  <small class="text-muted d-block mt-3">
                    * Campos obrigatórios.
                  </small>
                </form>

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
      const preview = document.getElementById('fotoPreview');
      const inputBase64 = document.getElementById('foto_base64');

      const btnAbrir = document.getElementById('btnAbrirCam');
      const btnTirar = document.getElementById('btnTirarFoto');
      const btnFechar = document.getElementById('btnFecharCam');
      const btnRefazer = document.getElementById('btnRefazer');
      const btnLimpar = document.getElementById('btnLimparForm');

      function setState({
        camOn,
        hasPhoto
      }) {
        video.style.display = camOn ? 'block' : 'none';
        btnTirar.disabled = !camOn;
        btnFechar.disabled = !camOn;
        preview.style.display = hasPhoto ? 'block' : 'none';
        btnRefazer.disabled = !hasPhoto;
        btnAbrir.disabled = camOn;
      }

      async function abrirCamera() {
        try {
          stream = await navigator.mediaDevices.getUserMedia({
            video: {
              facingMode: {
                ideal: 'environment'
              }
            },
            audio: false
          });

          video.srcObject = stream;
          await video.play();
          setState({
            camOn: true,
            hasPhoto: false
          });
        } catch (e) {
          alert('Não foi possível acessar a câmera. Verifique permissão/HTTPS.');
          setState({
            camOn: false,
            hasPhoto: false
          });
        }
      }

      function fecharCamera() {
        if (stream) {
          stream.getTracks().forEach(t => t.stop());
          stream = null;
        }
        video.srcObject = null;
        setState({
          camOn: false,
          hasPhoto: (inputBase64.value !== '')
        });
      }

      function tirarFoto() {
        if (!video.videoWidth || !video.videoHeight) return;

        const targetW = 720;
        const ratio = video.videoHeight / video.videoWidth;
        const targetH = Math.round(targetW * ratio);

        canvas.width = targetW;
        canvas.height = targetH;

        const ctx = canvas.getContext('2d', {
          alpha: false
        });
        ctx.drawImage(video, 0, 0, targetW, targetH);

        const dataUrl = canvas.toDataURL('image/jpeg', 0.65);

        preview.src = dataUrl;
        inputBase64.value = dataUrl;

        setState({
          camOn: true,
          hasPhoto: true
        });
        fecharCamera();
      }

      function refazerFoto() {
        inputBase64.value = '';
        preview.src = '';
        preview.style.display = 'none';
        abrirCamera();
      }

      function limparFoto() {
        inputBase64.value = '';
        preview.src = '';
        setState({
          camOn: false,
          hasPhoto: false
        });
        fecharCamera();
      }

      btnAbrir.addEventListener('click', abrirCamera);
      btnFechar.addEventListener('click', fecharCamera);
      btnTirar.addEventListener('click', tirarFoto);
      btnRefazer.addEventListener('click', refazerFoto);

      btnLimpar.addEventListener('click', function() {
        setTimeout(limparFoto, 0);
      });

      setState({
        camOn: false,
        hasPhoto: false
      });

      window.addEventListener('beforeunload', fecharCamera);
    })();
  </script>
</body>

</html>