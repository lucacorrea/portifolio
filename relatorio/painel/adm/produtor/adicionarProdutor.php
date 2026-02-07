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

/* Conexão (padrão do seu sistema: db(): PDO) */
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

function only_digits(string $s): string
{
  $out = preg_replace('/\D+/', '', $s);
  return $out !== null ? $out : '';
}

/* Feira padrão desta página */
$FEIRA_ID = 2; // 1=Feira do Produtor | 2=Feira Alternativa

/* Detecção opcional pela pasta (se você separou em pastas) */
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

/* ===== Comunidades (para o SELECT) ===== */
$comunidades = [];
try {
  $sqlC = "SELECT id, nome
           FROM comunidades
           WHERE feira_id = :feira AND ativo = 1
           ORDER BY nome ASC";
  $stC = $pdo->prepare($sqlC);
  $stC->bindValue(':feira', $FEIRA_ID, PDO::PARAM_INT);
  $stC->execute();
  $comunidades = $stC->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $comunidades = [];
}

/* Valores antigos */
$old = [
  'nome'          => '',
  'documento'     => '',
  'contato'       => '',
  'comunidade_id' => '',
  'ativo'         => '1',
  'observacao'    => '',
];

/* POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $tokenPost = (string)($_POST['csrf_token'] ?? '');
  if (!$tokenPost || !hash_equals($csrf, $tokenPost)) {
    $_SESSION['flash_err'] = 'Falha de segurança (CSRF). Recarregue a página e tente novamente.';
    header('Location: ./adicionarProdutor.php');
    exit;
  }

  $old['nome']          = trim((string)($_POST['nome'] ?? ''));
  $old['documento']     = trim((string)($_POST['documento'] ?? ''));
  $old['contato']       = trim((string)($_POST['contato'] ?? ''));
  $old['comunidade_id'] = trim((string)($_POST['comunidade_id'] ?? ''));
  $old['ativo']         = (string)($_POST['ativo'] ?? '1');
  $old['observacao']    = trim((string)($_POST['observacao'] ?? ''));

  if ($old['nome'] === '') {
    $err = 'Informe o nome do produtor.';
  } elseif ($old['comunidade_id'] === '' || !ctype_digit($old['comunidade_id'])) {
    $err = 'Selecione a comunidade do produtor.';
  } else {
    $nome = trunc255($old['nome']);
    $contato = trunc255($old['contato']);

    // documento: salva somente dígitos (mais limpo)
    $docDigits = only_digits($old['documento']);
    $documento = $docDigits !== '' ? trunc255($docDigits) : null;

    $observacao = trunc255($old['observacao']);
    $ativo = ($old['ativo'] === '1') ? 1 : 0;
    $comunidadeId = (int)$old['comunidade_id'];

    try {
      // Garante que a comunidade existe e é da mesma feira e está ativa
      $chk = $pdo->prepare("SELECT COUNT(*)
                            FROM comunidades
                            WHERE id = :id AND feira_id = :feira AND ativo = 1");
      $chk->bindValue(':id', $comunidadeId, PDO::PARAM_INT);
      $chk->bindValue(':feira', $FEIRA_ID, PDO::PARAM_INT);
      $chk->execute();
      $okCom = (int)$chk->fetchColumn() > 0;

      if (!$okCom) {
        $err = 'Comunidade inválida (não encontrada ou inativa).';
      } else {
        $sql = "INSERT INTO produtores
                  (feira_id, nome, contato, comunidade_id, documento, ativo, observacao)
                VALUES
                  (:feira_id, :nome, :contato, :comunidade_id, :documento, :ativo, :observacao)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          ':feira_id'      => $FEIRA_ID,
          ':nome'          => $nome,
          ':contato'       => ($contato !== '' ? $contato : null),
          ':comunidade_id' => $comunidadeId,
          ':documento'     => $documento,
          ':ativo'         => $ativo,
          ':observacao'    => ($observacao !== '' ? $observacao : null),
        ]);

        $_SESSION['flash_ok'] = 'Produtor cadastrado com sucesso!';
        header('Location: ./listaProdutor.php');
        exit;
      }
    } catch (Throwable $e) {
      $err = 'Erro ao salvar produtor: ' . $e->getMessage();
    }
  }
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <title>SIGRelatórios Feira Alternativa — Adicionar Produtor</title>

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

    .btn {
      height: 42px;
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

      <!-- SIDEBAR -->
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
                    <i class="ti-user mr-2"></i> Produtores
                  </a>
                </li>

                <li class="nav-item active">
                  <a class="nav-link" href="./adicionarProdutor.php" style="color:white !important; background: #231475C5 !important;">
                    <i class="ti-plus mr-2"></i> Adicionar Produtor
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

      <!-- MAIN -->
      <div class="main-panel">
        <div class="content-wrapper">

          <div class="row">
            <div class="col-12 mb-3">
              <h3 class="font-weight-bold">Adicionar Produtor</h3>
              <h6 class="font-weight-normal mb-0">Cadastro de produtor rural (feirante).</h6>
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
                      <h4 class="card-title mb-0">Dados do Produtor</h4>
                      <p class="card-description mb-0">
                        Comunidade é obrigatória e vem do cadastro de comunidades.
                        <span class="req-badge">Obrigatório</span>
                      </p>
                    </div>
                    <a href="./listaProdutor.php" class="btn btn-light btn-sm">
                      <i class="ti-arrow-left"></i> Voltar
                    </a>
                  </div>

                  <?php if (empty($comunidades)): ?>
                    <div class="alert alert-warning mt-3">
                      Nenhuma comunidade ativa cadastrada para esta feira.
                      Cadastre comunidades primeiro para poder cadastrar produtores.
                    </div>
                  <?php endif; ?>

                  <form class="pt-4" method="post" action="">
                    <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                    <div class="form-section">
                      <div class="section-title">
                        <i class="ti-user"></i> Identificação
                      </div>

                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label>Nome do produtor <span class="text-danger">*</span></label>
                          <input
                            name="nome"
                            type="text"
                            class="form-control"
                            placeholder="Ex.: João Batista da Silva"
                            required
                            value="<?= h($old['nome']) ?>">
                          <small class="text-muted help-hint">Nome completo ou como é conhecido na feira.</small>
                        </div>

                        <div class="col-md-3 mb-3">
                          <label>CPF / Documento</label>
                          <input
                            name="documento"
                            type="text"
                            class="form-control"
                            placeholder="Somente números"
                            value="<?= h($old['documento']) ?>">
                          <small class="text-muted help-hint">Opcional (salvo em <b>produtores.documento</b>).</small>
                        </div>

                        <div class="col-md-3 mb-3">
                          <label>Telefone / WhatsApp</label>
                          <input
                            name="contato"
                            type="text"
                            class="form-control"
                            placeholder="Ex.: 92991112222"
                            value="<?= h($old['contato']) ?>">
                          <small class="text-muted help-hint">Opcional (salvo em <b>produtores.contato</b>).</small>
                        </div>
                      </div>
                    </div>

                    <div class="form-section">
                      <div class="section-title">
                        <i class="ti-map-alt"></i> Comunidade
                      </div>

                      <div class="row">
                        <div class="col-md-6 mb-3">
                          <label>Comunidade <span class="text-danger">*</span></label>
                          <select
                            name="comunidade_id"
                            class="form-control"
                            <?= empty($comunidades) ? 'disabled' : 'required' ?>>
                            <option value="">Selecione</option>
                            <?php foreach ($comunidades as $c): ?>
                              <option
                                value="<?= (int)$c['id'] ?>"
                                <?= ($old['comunidade_id'] !== '' && (int)$old['comunidade_id'] === (int)$c['id']) ? 'selected' : '' ?>>
                                <?= h($c['nome']) ?>
                              </option>
                            <?php endforeach; ?>
                          </select>
                          <small class="text-muted help-hint">
                            Vem da tabela <b>comunidades</b> (feira_id = <?= (int)$FEIRA_ID ?>, ativo=1).
                          </small>
                        </div>

                        <div class="col-md-3 mb-3">
                          <label>Status</label>
                          <select name="ativo" class="form-control">
                            <option value="1" <?= ($old['ativo'] === '1' ? 'selected' : '') ?>>Ativo</option>
                            <option value="0" <?= ($old['ativo'] === '0' ? 'selected' : '') ?>>Inativo</option>
                          </select>
                          <small class="text-muted help-hint">Você pode desativar sem excluir.</small>
                        </div>

                        <div class="col-md-12 mb-3">
                          <label>Observações</label>
                          <textarea
                            name="observacao"
                            class="form-control"
                            rows="4"
                            placeholder="Ex.: produtor de farinha tradicional, entrega na sexta..."><?= h($old['observacao']) ?></textarea>
                          <small class="text-muted help-hint">Opcional (até 255 caracteres).</small>
                        </div>
                      </div>
                    </div>

                    <hr>

                    <div class="form-actions">
                      <button type="submit" class="btn btn-primary" <?= empty($comunidades) ? 'disabled' : '' ?>>
                        <i class="ti-save mr-1"></i> Salvar
                      </button>
                      <button type="reset" class="btn btn-light">
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
</body>

</html>