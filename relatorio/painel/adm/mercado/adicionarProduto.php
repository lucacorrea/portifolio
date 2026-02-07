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
if (!in_array('ADMIN', $perfis, true)) {
  header('Location: ../../operador/index.php');
  exit;
}

function h($s)
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
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
$feiraId = 3;

/* ===== Helpers ===== */
function money_to_decimal(?string $raw): ?string
{
  $raw = trim((string)$raw);
  if ($raw === '') return null;

  // remove espaços
  $raw = preg_replace('/\s+/', '', $raw) ?? $raw;

  // se vier "1.234,56" -> "1234.56"
  // se vier "1234,56" -> "1234.56"
  // se vier "1234.56" -> "1234.56"
  if (strpos($raw, ',') !== false && strpos($raw, '.') !== false) {
    // assume '.' milhar e ',' decimal
    $raw = str_replace('.', '', $raw);
    $raw = str_replace(',', '.', $raw);
  } else {
    $raw = str_replace(',', '.', $raw);
  }

  // valida número
  if (!preg_match('/^\d+(\.\d{1,2})?$/', $raw)) return null;

  return $raw;
}

/* ===== Carregar selects ===== */
$categorias = [];
$unidades   = [];
$produtores = [];

try {
  $stmt = $pdo->prepare("SELECT id, nome FROM categorias WHERE feira_id = :feira AND ativo = 1 ORDER BY nome ASC");
  $stmt->bindValue(':feira', $feiraId, PDO::PARAM_INT);
  $stmt->execute();
  $categorias = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt = $pdo->prepare("SELECT id, nome, sigla FROM unidades WHERE feira_id = :feira AND ativo = 1 ORDER BY nome ASC");
  $stmt->bindValue(':feira', $feiraId, PDO::PARAM_INT);
  $stmt->execute();
  $unidades = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $stmt = $pdo->prepare("SELECT id, nome FROM produtores WHERE feira_id = :feira AND ativo = 1 ORDER BY nome ASC");
  $stmt->bindValue(':feira', $feiraId, PDO::PARAM_INT);
  $stmt->execute();
  $produtores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar categorias/unidades/produtores agora.';
}

/* ===== Valores do form (repopular) ===== */
$nome        = '';
$categoriaId = '';
$unidadeId   = '';
$produtorId  = '';
$preco       = '';
$ativo       = '1';
$observacao  = '';

/* ===== Salvar ===== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

  $postedCsrf = (string)($_POST['csrf_token'] ?? '');
  if (!hash_equals($csrf, $postedCsrf)) {
    $_SESSION['flash_err'] = 'Sessão expirada. Atualize a página e tente novamente.';
    header('Location: ./adicionarProduto.php');
    exit;
  }

  $nome        = trim((string)($_POST['nome'] ?? ''));
  $categoriaId = (string)($_POST['categoria_id'] ?? '');
  $unidadeId   = (string)($_POST['unidade_id'] ?? '');
  $produtorId  = (string)($_POST['produtor_id'] ?? '');
  $preco       = trim((string)($_POST['preco'] ?? ''));
  $ativo       = (string)($_POST['ativo'] ?? '1');
  $observacao  = trim((string)($_POST['observacao'] ?? ''));

  $ativoInt = ($ativo === '0') ? 0 : 1;

  $nomeNorm = preg_replace('/\s+/', ' ', $nome) ?? $nome;
  $obsNorm  = preg_replace('/\s+/', ' ', $observacao) ?? $observacao;

  $categoriaInt = ($categoriaId !== '') ? (int)$categoriaId : 0;
  $unidadeInt   = ($unidadeId !== '') ? (int)$unidadeId : 0;
  $produtorInt  = ($produtorId !== '') ? (int)$produtorId : 0;

  $precoDecimal = money_to_decimal($preco);

  // ===== Validações =====
  if ($nomeNorm === '') {
    $err = 'Informe o nome do produto.';
  } elseif (mb_strlen($nomeNorm) > 160) {
    $err = 'O nome do produto deve ter no máximo 160 caracteres.';
  } elseif ($categoriaInt <= 0) {
    $err = 'Selecione a categoria.';
  } elseif ($unidadeInt <= 0) {
    $err = 'Selecione a unidade.';
  } elseif ($produtorInt <= 0) {
    $err = 'Selecione o produtor.';
  } elseif ($precoDecimal === null) {
    $err = 'Informe um preço válido (ex.: 10,50).';
  } elseif ($obsNorm !== '' && mb_strlen($obsNorm) > 255) {
    $err = 'A observação deve ter no máximo 255 caracteres.';
  } else {
    try {
      // Confere se FK composta existe (feira_id + id)
      $stmt = $pdo->prepare("SELECT 1 FROM categorias WHERE feira_id = :feira AND id = :id LIMIT 1");
      $stmt->execute([':feira' => $feiraId, ':id' => $categoriaInt]);
      if (!$stmt->fetchColumn()) throw new RuntimeException('Categoria inválida para esta feira.');

      $stmt = $pdo->prepare("SELECT 1 FROM unidades WHERE feira_id = :feira AND id = :id LIMIT 1");
      $stmt->execute([':feira' => $feiraId, ':id' => $unidadeInt]);
      if (!$stmt->fetchColumn()) throw new RuntimeException('Unidade inválida para esta feira.');

      $stmt = $pdo->prepare("SELECT 1 FROM produtores WHERE feira_id = :feira AND id = :id LIMIT 1");
      $stmt->execute([':feira' => $feiraId, ':id' => $produtorInt]);
      if (!$stmt->fetchColumn()) throw new RuntimeException('Permissionário inválido.');

      // Evitar duplicado por feira (nome)
      $chk = $pdo->prepare("SELECT id FROM produtos WHERE feira_id = :feira AND nome = :nome LIMIT 1");
      $chk->bindValue(':feira', $feiraId, PDO::PARAM_INT);
      $chk->bindValue(':nome', $nomeNorm, PDO::PARAM_STR);
      $chk->execute();
      $jaExiste = (int)($chk->fetchColumn() ?: 0);

      if ($jaExiste > 0) {
        $err = 'Já existe um produto com esse nome nesta feira.';
      } else {
        $ins = $pdo->prepare("
          INSERT INTO produtos
            (feira_id, nome, categoria_id, unidade_id, produtor_id, preco_referencia, ativo, observacao)
          VALUES
            (:feira_id, :nome, :categoria_id, :unidade_id, :produtor_id, :preco, :ativo, :observacao)
        ");
        $ins->bindValue(':feira_id', $feiraId, PDO::PARAM_INT);
        $ins->bindValue(':nome', $nomeNorm, PDO::PARAM_STR);
        $ins->bindValue(':categoria_id', $categoriaInt, PDO::PARAM_INT);
        $ins->bindValue(':unidade_id', $unidadeInt, PDO::PARAM_INT);
        $ins->bindValue(':produtor_id', $produtorInt, PDO::PARAM_INT);
        $ins->bindValue(':preco', $precoDecimal, PDO::PARAM_STR);
        $ins->bindValue(':ativo', $ativoInt, PDO::PARAM_INT);

        if ($obsNorm === '') {
          $ins->bindValue(':observacao', null, PDO::PARAM_NULL);
        } else {
          $ins->bindValue(':observacao', $obsNorm, PDO::PARAM_STR);
        }

        $ins->execute();

        $_SESSION['flash_ok'] = 'Produto cadastrado com sucesso.';
        header('Location: ./listaProduto.php');
        exit;
      }
    } catch (RuntimeException $e) {
      $err = $e->getMessage();
    } catch (PDOException $e) {
      $mysqlCode = (int)($e->errorInfo[1] ?? 0);

      if ($mysqlCode === 1146) {
        $err = 'Tabela "produtos" não existe. Rode o SQL das tabelas.';
      } elseif ($mysqlCode === 1062) {
        $err = 'Já existe um produto com esse nome nesta feira.';
      } elseif ($mysqlCode === 1452) {
        $err = 'Categoria/Unidade/Permissionário inválido (FK).';
      } else {
        $sqlState = (string)$e->getCode();
        $err = "Não foi possível salvar o produto agora. (SQLSTATE {$sqlState} / MySQL {$mysqlCode})";
      }
    } catch (Throwable $e) {
      $err = 'Não foi possível salvar o produto agora.';
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Mercado Municipal — Adicionar Produto</title>

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
      height: 42px;
    }

    .form-group label {
      font-weight: 600;
    }

    /* ===== Flash “Hostinger style” (top-right, menor, ~6s) ===== */
    .sig-flash-wrap {
      position: fixed;
      top: 78px;
      right: 18px;
      left: auto;
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
      animation:
        sigToastIn .22s ease-out forwards,
        sigToastOut .25s ease-in forwards 5.75s;
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
      font-weight: 800;
      margin-bottom: 1px;
      line-height: 1.1;
    }

    .sig-toast__text {
      margin: 0;
      line-height: 1.25;
    }

    .sig-toast .close {
      opacity: .55;
      font-size: 18px;
      line-height: 1;
      padding: 0 6px;
    }

    .sig-toast .close:hover {
      opacity: 1;
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
  </style>
</head>

<body>
  <div class="container-scroller">

    <!-- NAVBAR (padrão) -->
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
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
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
            <button type="button" class="close" data-dismiss="alert" aria-label="Fechar">
              <span aria-hidden="true">&times;</span>
            </button>
          </div>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <div class="container-fluid page-body-wrapper">

      <!-- settings-panel (mantido) -->
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

      <!-- SIDEBAR (Cadastros ativo + Adicionar Produto ativo) -->
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

                <li class="nav-item active">
                  <a class="nav-link" href="./adicionarProduto.php" style="color:white !important; background: #231475C5 !important;">
                    <i class="ti-plus mr-2"></i> Adicionar Produto
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

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <div class="row">
            <div class="col-12 mb-3">
              <h3 class="font-weight-bold">Adicionar Produto</h3>
              <h6 class="font-weight-normal mb-0">
                Cadastro simples para a feira (sem código de barras, sem caixa próprio — só produto e valores).
              </h6>
            </div>
          </div>

          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">

                  <div class="d-flex align-items-center justify-content-between flex-wrap">
                    <div>
                      <h4 class="card-title mb-0">Dados do Produto</h4>
                      <p class="card-description mb-0">Preencha o básico e salve.</p>
                    </div>

                    <div class="mt-2 mt-md-0">
                      <a href="./listaProduto.php" class="btn btn-light btn-sm">
                        <i class="ti-arrow-left"></i> Voltar
                      </a>
                    </div>
                  </div>

                  <hr>

                  <form action="./adicionarProduto.php" method="post" autocomplete="off">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                    <div class="row">

                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Nome do Produto *</label>
                          <input type="text" class="form-control" name="nome"
                            placeholder="Ex.: Banana pacovã, Farinha d’água, Alface..."
                            required maxlength="160" value="<?= h($nome) ?>">
                        </div>
                      </div>

                      <div class="col-md-3">
                        <div class="form-group">
                          <label>Categoria (Tipo) *</label>
                          <select class="form-control" name="categoria_id" required>
                            <option value="" disabled <?= $categoriaId === '' ? 'selected' : '' ?>>Selecione...</option>
                            <?php foreach ($categorias as $c): ?>
                              <?php $cid = (int)$c['id']; ?>
                              <option value="<?= $cid ?>" <?= ((string)$cid === (string)$categoriaId) ? 'selected' : '' ?>>
                                <?= h($c['nome'] ?? '') ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <small class="text-muted">Ex.: Hortaliças, Frutas, Farinhas, Temperos, etc.</small>
                        </div>
                      </div>

                      <div class="col-md-3">
                        <div class="form-group">
                          <label>Unidade *</label>
                          <select class="form-control" name="unidade_id" required>
                            <option value="" disabled <?= $unidadeId === '' ? 'selected' : '' ?>>Selecione...</option>
                            <?php foreach ($unidades as $u): ?>
                              <?php
                              $uid = (int)$u['id'];
                              $siglaU = trim((string)($u['sigla'] ?? ''));
                              $label = trim((string)($u['nome'] ?? ''));
                              if ($siglaU !== '') $label .= " ({$siglaU})";
                              ?>
                              <option value="<?= $uid ?>" <?= ((string)$uid === (string)$unidadeId) ? 'selected' : '' ?>>
                                <?= h($label) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <small class="text-muted">Ex.: kg, unidade, maço, litro.</small>
                        </div>
                      </div>

                      <div class="col-md-6">
                        <div class="form-group">
                          <label>Permissionário *</label>
                          <select class="form-control" name="produtor_id" required>
                            <option value="" disabled <?= $produtorId === '' ? 'selected' : '' ?>>Selecione...</option>
                            <?php foreach ($produtores as $p): ?>
                              <?php $pid = (int)$p['id']; ?>
                              <option value="<?= $pid ?>" <?= ((string)$pid === (string)$produtorId) ? 'selected' : '' ?>>
                                <?= h($p['nome'] ?? '') ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <small class="text-muted">Permissionários cadastrados no sistema.</small>
                        </div>
                      </div>

                      <div class="col-md-3">
                        <div class="form-group">
                          <label>Preço de referência (R$) *</label>
                          <input type="text" class="form-control" name="preco"
                            placeholder="0,00" required value="<?= h($preco) ?>">
                          <small class="text-muted">Pode ajustar no lançamento da venda.</small>
                        </div>
                      </div>

                      <div class="col-md-3">
                        <div class="form-group">
                          <label>Status *</label>
                          <select class="form-control" name="ativo" required>
                            <option value="1" <?= $ativo === '1' ? 'selected' : '' ?>>Ativo</option>
                            <option value="0" <?= $ativo === '0' ? 'selected' : '' ?>>Inativo</option>
                          </select>
                        </div>
                      </div>

                      <div class="col-md-12">
                        <div class="form-group">
                          <label>Observação</label>
                          <textarea class="form-control" name="observacao" rows="3"
                            placeholder="Ex.: Produto sazonal, vendido por saco, observações do produtor..."><?= h($observacao) ?></textarea>
                        </div>
                      </div>

                    </div>

                    <div class="d-flex flex-wrap" style="gap:8px;">
                      <button type="submit" class="btn btn-primary">
                        <i class="ti-save mr-1"></i> Salvar Produto
                      </button>
                      <button type="reset" class="btn btn-light">
                        <i class="ti-close mr-1"></i> Limpar
                      </button>
                    </div>

                    <?php if (empty($categorias) || empty($unidades) || empty($produtores)): ?>
                      <small class="text-muted d-block mt-3">
                        Obs.: Se algum select estiver vazio, cadastre primeiro: Categoria / Unidade / Permissionário.
                      </small>
                    <?php endif; ?>

                  </form>

                </div>
              </div>
            </div>
          </div>

        </div>

        <!-- FOOTER -->
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
      <!-- main-panel ends -->

    </div>
    <!-- page-body-wrapper ends -->
  </div>
  <!-- container-scroller -->

  <script src="../../../vendors/js/vendor.bundle.base.js"></script>
  <script src="../../../vendors/chart.js/Chart.min.js"></script>

  <script src="../../../js/off-canvas.js"></script>
  <script src="../../../js/hoverable-collapse.js"></script>
  <script src="../../../js/template.js"></script>
  <script src="../../../js/settings.js"></script>
  <script src="../../../js/todolist.js"></script>

  <script src="../../../js/dashboard.js"></script>
  <script src="../../../js/Chart.roundedBarCharts.js"></script>
</body>

</html>