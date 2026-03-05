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
$pdo = db();

/* Helpers */
function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

/* aceita "1.234,56" ou "1234.56" */
function parse_decimal($v): float
{
  $s = trim((string)$v);
  if ($s === '') return 0.0;
  $s = str_replace(['R$', ' '], '', $s);
  if (strpos($s, ',') !== false) {
    $s = str_replace('.', '', $s);
    $s = str_replace(',', '.', $s);
  }
  return is_numeric($s) ? (float)$s : 0.0;
}
function parse_qty($v): float
{
  return parse_decimal($v);
}

/* Feira desta página */
$FEIRA_ID = 1; // 1=Feira do Produtor | 2=Feira Alternativa

/* Flash */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

/* Produtos (select) */
$produtos = [];
try {
  $st = $pdo->prepare("
    SELECT p.id, p.nome
    FROM produtos p
    WHERE p.feira_id = :feira AND p.ativo = 1
    ORDER BY p.nome ASC
  ");
  $st->bindValue(':feira', $FEIRA_ID, PDO::PARAM_INT);
  $st->execute();
  $produtos = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $produtos = [];
}

/* valores antigos */
$old = [
  'ano' => (int)date('Y'),
  'mes' => (int)date('n'),
  'hora' => '08:00',
  'forma_pagamento' => 'RELATORIO',
  'observacao' => '',
];

/* POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tokenPost = (string)($_POST['csrf_token'] ?? '');
  if (!$tokenPost || !hash_equals($csrf, $tokenPost)) {
    $_SESSION['flash_err'] = 'Falha de segurança (CSRF). Recarregue a página e tente novamente.';
    header('Location: ./lancamentosMensal.php');
    exit;
  }

  $old['ano'] = (int)($_POST['ano'] ?? (int)date('Y'));
  $old['mes'] = (int)($_POST['mes'] ?? (int)date('n'));
  $old['hora'] = trim((string)($_POST['hora'] ?? '08:00'));
  $old['forma_pagamento'] = trim((string)($_POST['forma_pagamento'] ?? 'RELATORIO'));
  $old['observacao'] = trim((string)($_POST['observacao'] ?? ''));

  $produto_ids = $_POST['produto_id'] ?? [];
  $quantidades = $_POST['quantidade_total'] ?? [];
  $valores     = $_POST['valor_unitario'] ?? [];

  if ($old['ano'] < 2000 || $old['ano'] > 2100) {
    $err = 'Informe um ano válido.';
  } elseif ($old['mes'] < 1 || $old['mes'] > 12) {
    $err = 'Informe um mês válido.';
  } elseif ($old['hora'] === '' || !preg_match('/^\d{2}:\d{2}$/', $old['hora'])) {
    $err = 'Informe uma hora válida.';
  } elseif (!is_array($produto_ids) || !is_array($quantidades) || !is_array($valores)) {
    $err = 'Itens inválidos.';
  } else {
    // Data do lançamento mensal = 1º dia do mês
    $mesStr = str_pad((string)$old['mes'], 2, '0', STR_PAD_LEFT);
    $dataHora = $old['ano'] . '-' . $mesStr . '-01 ' . $old['hora'] . ':00';

    // agrega itens por produto_id (se repetir, soma)
    $itens = []; // produto_id => ['q'=>, 'vu'=>]
    $n = max(count($produto_ids), count($quantidades), count($valores));

    for ($i = 0; $i < $n; $i++) {
      $pid = isset($produto_ids[$i]) ? (int)$produto_ids[$i] : 0;
      $q   = isset($quantidades[$i]) ? parse_qty($quantidades[$i]) : 0.0; // Q.Total
      $vu  = isset($valores[$i]) ? parse_decimal($valores[$i]) : 0.0;     // P.Unitário

      if ($pid <= 0) continue;
      if ($q <= 0) continue;
      if ($vu < 0) $vu = 0.0;

      if (!isset($itens[$pid])) {
        $itens[$pid] = ['q' => 0.0, 'vu' => $vu];
      }
      $itens[$pid]['q'] += $q;
      $itens[$pid]['vu'] = $vu; // mantém último preço informado
    }

    if (empty($itens)) {
      $err = 'Adicione pelo menos 1 produto com Q.Total maior que zero.';
    } else {
      try {
        // valida produtos pertencem à feira e ativos
        $ids = array_keys($itens);
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $chk = $pdo->prepare("
          SELECT id
          FROM produtos
          WHERE feira_id = ? AND ativo = 1 AND id IN ($placeholders)
        ");
        $params = array_merge([$FEIRA_ID], $ids);
        $chk->execute($params);
        $valid = $chk->fetchAll(PDO::FETCH_COLUMN, 0);
        $valid = array_map('intval', $valid);

        foreach ($ids as $pid) {
          if (!in_array((int)$pid, $valid, true)) {
            throw new RuntimeException('Produto inválido/inativo (ID: ' . (int)$pid . ').');
          }
        }

        // calcula total
        $total = 0.0;
        foreach ($itens as $pid => $x) {
          $total += round($x['q'] * $x['vu'], 2);
        }

        $pdo->beginTransaction();

        // cria venda (mensal)
        $insV = $pdo->prepare("
          INSERT INTO vendas (feira_id, data_hora, forma_pagamento, total, status, observacao)
          VALUES (:feira, :data_hora, :forma, :total, :status, :obs)
        ");
        $insV->execute([
          ':feira' => $FEIRA_ID,
          ':data_hora' => $dataHora,
          ':forma' => ($old['forma_pagamento'] !== '' ? $old['forma_pagamento'] : 'RELATORIO'),
          ':total' => $total,
          ':status' => 'FECHADA',
          ':obs' => ($old['observacao'] !== '' ? $old['observacao'] : ('Lançamento mensal ' . $mesStr . '/' . $old['ano'])),
        ]);

        $vendaId = (int)$pdo->lastInsertId();

        // insere itens
        $insI = $pdo->prepare("
          INSERT INTO venda_itens
          (feira_id, venda_id, produto_id, quantidade, valor_unitario, subtotal, observacao)
          VALUES
          (:feira, :venda, :produto, :qtd, :vu, :sub, :obs)
        ");

        foreach ($itens as $pid => $x) {
          $sub = round($x['q'] * $x['vu'], 2);
          $insI->execute([
            ':feira' => $FEIRA_ID,
            ':venda' => $vendaId,
            ':produto' => (int)$pid,
            ':qtd' => (float)$x['q'],
            ':vu' => (float)$x['vu'],
            ':sub' => $sub,
            ':obs' => null,
          ]);
        }

        $pdo->commit();

        $_SESSION['flash_ok'] = 'Lançamento mensal cadastrado com sucesso! (Venda ID: ' . $vendaId . ')';
        header('Location: ./lancamentosMensal.php');
        exit;
      } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        $err = 'Erro ao salvar: ' . $e->getMessage();
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
  <title>SIGRelatórios Feira do Produtor — Lançamento Mensal</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">
  <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
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

    /* ✅ Slim */
    .content-wrapper {
      padding: 1.2rem !important;
    }

    .card {
      border-radius: 12px;
    }

    .card-body {
      padding: 14px !important;
    }

    .card-title {
      font-size: 16px;
      font-weight: 800;
      margin: 0;
    }

    .card-subtitle {
      font-size: 12px;
      color: #6b7280;
      margin: 2px 0 0 0;
    }

    .form-control {
      min-height: 36px;
      padding: .35rem .6rem;
      font-size: 13px;
    }

    .btn {
      min-height: 36px;
      padding: .35rem .6rem;
      font-size: 13px;
    }

    .slim-row {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    .slim-col {
      flex: 1 1 180px;
      min-width: 180px;
    }

    .slim-col.sm {
      flex: 0 0 140px;
      min-width: 140px;
    }

    .slim-col.md {
      flex: 0 0 180px;
      min-width: 180px;
    }

    .form-section {
      background: #fff;
      border: 1px solid rgba(0, 0, 0, .06);
      border-radius: 12px;
      padding: 12px;
      margin-bottom: 10px;
    }

    .section-title {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      font-weight: 800;
      font-size: 12px;
      color: #111827;
      margin-bottom: 10px;
    }

    .section-title .hint {
      font-weight: 600;
      font-size: 11px;
      color: #6b7280;
    }

    .table-slim {
      margin-bottom: 0;
    }

    .table-slim th,
    .table-slim td {
      padding: .35rem .45rem;
      vertical-align: middle;
    }

    .table-slim th {
      font-size: 12px;
    }

    .table-slim input,
    .table-slim select {
      font-size: 13px;
    }

    .total-bar {
      margin-top: 10px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
      flex-wrap: wrap;
      background: #f8fafc;
      border: 1px solid rgba(0, 0, 0, .06);
      border-radius: 12px;
      padding: 10px 12px;
    }

    .total-bar .label {
      font-size: 12px;
      font-weight: 800;
      color: #111827;
    }

    .total-bar .value {
      font-size: 16px;
      font-weight: 900;
    }

    .toolbar {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      margin: 10px 0 0 0;
    }

    .toolbar .left {
      display: flex;
      gap: 8px;
      flex-wrap: wrap;
    }

    .btn-icon {
      width: 36px;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      padding: 0;
    }

    @media (max-width: 576px) {
      .content-wrapper {
        padding: 0.9rem !important;
      }

      .slim-col,
      .slim-col.sm,
      .slim-col.md {
        flex: 1 1 100%;
        min-width: 100%;
      }

      .btn {
        width: 100%;
      }

      .btn-icon {
        width: 100%;
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
        <button class="navbar-toggler navbar-toggler-right d-lg-none align-self-center" type="button" data-toggle="offcanvas">
          <span class="icon-menu"></span>
        </button>
      </div>
    </nav>

    <div class="container-fluid page-body-wrapper">

      <!-- SIDEBAR (mantida) -->
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
                  <a class="nav-link" href="./lancamentosMensal.php" style="color:white !important; background:#231475C5 !important;">
                    <i class="ti-calendar mr-2"></i> Lançamento Mensal
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
              <h3 class="font-weight-bold">Lançamento Mensal</h3>
              <h6 class="font-weight-normal mb-0">Cadastre o total do mês (Q.Total e P.Unitário do Excel).</h6>
            </div>
          </div>

          <?php if (!empty($msg)): ?>
            <div class="alert alert-success"><?= h($msg) ?></div>
          <?php endif; ?>
          <?php if (!empty($err)): ?>
            <div class="alert alert-danger"><?= h($err) ?></div>
          <?php endif; ?>

          <?php if (empty($produtos)): ?>
            <div class="alert alert-warning">
              Nenhum produto ativo encontrado para a feira. Cadastre produtos antes de lançar.
            </div>
          <?php endif; ?>

          <div class="row">
            <div class="col-lg-12 grid-margin stretch-card">
              <div class="card">
                <div class="card-body">

                  <div class="card-title-row">
                    <div>
                      <h4 class="card-title mb-0">Dados do Mês</h4>
                      <p class="card-description mb-0">
                        1 registro em <b>vendas</b> + itens em <b>venda_itens</b>.
                        <span class="req-badge">Mensal</span>
                      </p>
                    </div>
                    <a href="./lancamentos.php" class="btn btn-light btn-sm">
                      <i class="ti-arrow-left"></i> Voltar
                    </a>
                  </div>

                  <form class="pt-4" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                    <div class="form-section">
                      <div class="section-title">
                        <i class="ti-calendar"></i> Competência / Pagamento
                      </div>

                      <div class="row">
                        <div class="col-12 col-md-3 mb-3">
                          <label>Ano <span class="text-danger">*</span></label>
                          <input type="number" name="ano" class="form-control" min="2000" max="2100" required value="<?= (int)$old['ano'] ?>">
                        </div>

                        <div class="col-12 col-md-3 mb-3">
                          <label>Mês <span class="text-danger">*</span></label>
                          <select name="mes" class="form-control" required>
                            <?php
                            $meses = [
                              1 => 'Janeiro',
                              2 => 'Fevereiro',
                              3 => 'Março',
                              4 => 'Abril',
                              5 => 'Maio',
                              6 => 'Junho',
                              7 => 'Julho',
                              8 => 'Agosto',
                              9 => 'Setembro',
                              10 => 'Outubro',
                              11 => 'Novembro',
                              12 => 'Dezembro'
                            ];
                            foreach ($meses as $k => $label):
                            ?>
                              <option value="<?= (int)$k ?>" <?= ((int)$old['mes'] === (int)$k ? 'selected' : '') ?>><?= h($label) ?></option>
                            <?php endforeach; ?>
                          </select>
                        </div>

                        <div class="col-12 col-md-3 mb-3">
                          <label>Hora (1º dia do mês)</label>
                          <input type="time" name="hora" class="form-control" value="<?= h($old['hora']) ?>">
                        </div>

                        <div class="col-12 col-md-3 mb-3">
                          <label>Forma de pagamento <span class="text-danger">*</span></label>
                          <select name="forma_pagamento" class="form-control" required>
                            <?php
                            $formas = ['RELATORIO', 'DINHEIRO', 'PIX', 'CARTAO', 'OUTROS'];
                            foreach ($formas as $f):
                            ?>
                              <option value="<?= h($f) ?>" <?= ($old['forma_pagamento'] === $f ? 'selected' : '') ?>>
                                <?= h($f) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <small class="text-muted help-hint">Recomendado: <b>RELATORIO</b>.</small>
                        </div>

                        <div class="col-12 mb-2">
                          <label>Observação</label>
                          <input type="text" name="observacao" class="form-control" placeholder="Opcional" value="<?= h($old['observacao']) ?>">
                        </div>
                      </div>
                    </div>

                    <div class="form-section">
                      <div class="section-title">
                        <i class="ti-list"></i> Itens do Mês (Q.Total e P.Unitário)
                      </div>

                      <div class="table-responsive">
                        <table class="table table-bordered items-table" id="itensTable">
                          <thead>
                            <tr>
                              <th style="width:45%;">Produto</th>
                              <th style="width:15%;">Q.Total</th>
                              <th style="width:15%;">P.Unitário (R$)</th>
                              <th style="width:15%;">Subtotal (R$)</th>
                              <th style="width:10%;">Ação</th>
                            </tr>
                          </thead>
                          <tbody>
                            <tr>
                              <td>
                                <select name="produto_id[]" class="form-control produto">
                                  <option value="">Selecione</option>
                                  <?php foreach ($produtos as $p): ?>
                                    <option value="<?= (int)$p['id'] ?>"><?= h($p['nome']) ?> (ID: <?= (int)$p['id'] ?>)</option>
                                  <?php endforeach; ?>
                                </select>
                              </td>
                              <td><input name="quantidade_total[]" type="text" class="form-control qtd" placeholder="0"></td>
                              <td><input name="valor_unitario[]" type="text" class="form-control vu" placeholder="0,00"></td>
                              <td><input type="text" class="form-control sub" value="0,00" readonly></td>
                              <td>
                                <button type="button" class="btn btn-danger btn-sm btnRemover">
                                  <i class="ti-trash"></i>
                                </button>
                              </td>
                            </tr>
                          </tbody>
                        </table>
                      </div>

                      <div class="d-flex flex-wrap" style="gap:8px;">
                        <button type="button" class="btn btn-secondary" id="btnAddItem">
                          <i class="ti-plus mr-1"></i> Adicionar Item
                        </button>
                        <small class="text-muted help-hint d-block mt-2">
                          Dica: se repetir o mesmo produto, o sistema soma as quantidades e usa o último preço informado.
                        </small>
                      </div>

                      <div class="total-box mt-3">
                        <div class="label">Total do mês</div>
                        <div class="value" id="totalVenda">R$ 0,00</div>
                      </div>
                    </div>

                    <hr>

                    <button type="submit" class="btn btn-primary" <?= empty($produtos) ? 'disabled' : '' ?>>
                      <i class="ti-save mr-1"></i> Salvar Lançamento Mensal
                    </button>

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
      const tableBody = document.querySelector('#itensTable tbody');
      const btnAdd = document.getElementById('btnAddItem');
      const totalEl = document.getElementById('totalVenda');

      function brl(n) {
        const v = isFinite(n) ? n : 0;
        return 'R$ ' + v.toLocaleString('pt-BR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
      }

      function parseNum(s) {
        if (!s) return 0;
        s = String(s).trim().replace('R$', '').replace(/\s+/g, '');
        if (s.indexOf(',') !== -1) s = s.replace(/\./g, '').replace(',', '.');
        const v = Number(s);
        return isFinite(v) ? v : 0;
      }

      function recalcRow(tr) {
        const qtd = parseNum(tr.querySelector('.qtd').value);
        const vu = parseNum(tr.querySelector('.vu').value);
        const sub = Math.round(qtd * vu * 100) / 100;
        tr.querySelector('.sub').value = sub.toLocaleString('pt-BR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });
        return sub;
      }

      function recalcTotal() {
        let total = 0;
        tableBody.querySelectorAll('tr').forEach(tr => {
          total += recalcRow(tr);
        });
        totalEl.textContent = brl(total);
      }

      function bindRow(tr) {
        tr.querySelectorAll('.qtd,.vu').forEach(inp => {
          inp.addEventListener('input', recalcTotal);
          inp.addEventListener('blur', recalcTotal);
        });

        tr.querySelector('.btnRemover').addEventListener('click', () => {
          if (tableBody.querySelectorAll('tr').length <= 1) {
            tr.querySelector('.produto').value = '';
            tr.querySelector('.qtd').value = '';
            tr.querySelector('.vu').value = '';
            recalcTotal();
            return;
          }
          tr.remove();
          recalcTotal();
        });
      }

      btnAdd.addEventListener('click', () => {
        const first = tableBody.querySelector('tr');
        const clone = first.cloneNode(true);
        clone.querySelector('.produto').value = '';
        clone.querySelector('.qtd').value = '';
        clone.querySelector('.vu').value = '';
        clone.querySelector('.sub').value = '0,00';
        tableBody.appendChild(clone);
        bindRow(clone);
        recalcTotal();
      });

      bindRow(tableBody.querySelector('tr'));
      recalcTotal();
    })();
  </script>

</body>

</html>