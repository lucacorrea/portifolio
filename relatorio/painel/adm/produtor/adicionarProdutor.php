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

require '../../../assets/php/conexao.php';

function h($s): string {
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}
function trunc255(string $s): string {
  if (function_exists('mb_substr')) return mb_substr($s, 0, 255, 'UTF-8');
  return substr($s, 0, 255);
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

/* ===== DB ===== */
$pdo = db();

/* ===== Helpers de schema (robusto) ===== */
function table_exists(PDO $pdo, string $table): bool {
  $stmt = $pdo->prepare("SHOW TABLES LIKE :t");
  $stmt->execute([':t' => $table]);
  return (bool)$stmt->fetchColumn();
}
function column_exists(PDO $pdo, string $table, string $col): bool {
  $stmt = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
  $stmt->execute([':c' => $col]);
  return (bool)$stmt->fetch(PDO::FETCH_ASSOC);
}

/* ===== Carregar comunidades (para o SELECT) ===== */
$comunidades = [];
$temComunidadesTable = false;
$comunidadesTemFeiraId = false;
$comunidadesTemAtivo = false;

try {
  $temComunidadesTable = table_exists($pdo, 'comunidades');
  if ($temComunidadesTable) {
    $comunidadesTemFeiraId = column_exists($pdo, 'comunidades', 'feira_id');
    $comunidadesTemAtivo   = column_exists($pdo, 'comunidades', 'ativo');

    $where = [];
    $params = [];
    if ($comunidadesTemFeiraId) {
      $where[] = "feira_id = :feira";
      $params[':feira'] = $FEIRA_ID;
    }
    if ($comunidadesTemAtivo) {
      $where[] = "ativo = 1";
    }

    $sqlC = "SELECT id, nome FROM comunidades"
      . (count($where) ? " WHERE " . implode(" AND ", $where) : "")
      . " ORDER BY nome ASC";

    $stmt = $pdo->prepare($sqlC);
    if (isset($params[':feira'])) $stmt->bindValue(':feira', $FEIRA_ID, PDO::PARAM_INT);
    $stmt->execute();
    $comunidades = $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
} catch (Throwable $e) {
  // não quebra a página
  $temComunidadesTable = false;
  $comunidades = [];
}

/* ===== Detectar schema do produtores ===== */
$produtoresTemComunidadeId = false;
$produtoresTemComunidadeTexto = false;
try {
  if (table_exists($pdo, 'produtores')) {
    $produtoresTemComunidadeId = column_exists($pdo, 'produtores', 'comunidade_id');
    $produtoresTemComunidadeTexto = column_exists($pdo, 'produtores', 'comunidade');
  }
} catch (Throwable $e) {
  // segue
}

/* Valores antigos */
$old = [
  'nome' => '',
  'cpf' => '',
  'telefone' => '',
  'ativo' => '1',
  'comunidade_id' => '',
  'tipo' => 'Produtor rural',
  'observacao' => '',
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
  $old['cpf']           = trim((string)($_POST['cpf'] ?? ''));
  $old['telefone']      = trim((string)($_POST['telefone'] ?? ''));
  $old['ativo']         = (string)($_POST['ativo'] ?? '1');
  $old['comunidade_id'] = trim((string)($_POST['comunidade_id'] ?? ''));
  $old['tipo']          = trim((string)($_POST['tipo'] ?? 'Produtor rural'));
  $old['observacao']    = trim((string)($_POST['observacao'] ?? ''));

  if ($old['nome'] === '') {
    $err = 'Informe o nome do produtor.';
  } else {
    $ativo = ($old['ativo'] === '1') ? 1 : 0;
    $contato = trunc255($old['telefone']);

    $comunidadeId = (int)$old['comunidade_id'];
    if ($comunidadeId <= 0) $comunidadeId = 0;

    // se for salvar texto, pega o nome da comunidade escolhida
    $comunidadeNome = '';
    if ($comunidadeId > 0) {
      foreach ($comunidades as $c) {
        if ((int)($c['id'] ?? 0) === $comunidadeId) {
          $comunidadeNome = (string)($c['nome'] ?? '');
          break;
        }
      }
    }
    $comunidadeNome = trunc255(trim($comunidadeNome));

    /* Compacta cpf/tipo em observacao (se sua tabela não tem esses campos) */
    $extras = [];
    if ($old['cpf'] !== '')  $extras[] = 'CPF: ' . $old['cpf'];
    if ($old['tipo'] !== '') $extras[] = 'Tipo: ' . $old['tipo'];

    $obs = $old['observacao'];
    $obsFinal = trim(implode(' | ', $extras));
    if ($obs !== '') $obsFinal = trim($obsFinal . ' | Obs: ' . $obs);
    $obsFinal = trunc255($obsFinal);

    try {
      // Monta INSERT conforme seu schema
      if ($produtoresTemComunidadeId) {
        $sql = "INSERT INTO produtores (feira_id, nome, contato, comunidade_id, ativo, observacao)
                VALUES (:feira_id, :nome, :contato, :comunidade_id, :ativo, :observacao)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          ':feira_id'       => $FEIRA_ID,
          ':nome'           => $old['nome'],
          ':contato'        => $contato !== '' ? $contato : null,
          ':comunidade_id'  => ($comunidadeId > 0) ? $comunidadeId : null,
          ':ativo'          => $ativo,
          ':observacao'     => $obsFinal !== '' ? $obsFinal : null,
        ]);
      } else {
        // fallback: salva texto em "comunidade" (se existir)
        $comuTexto = $comunidadeNome;
        if ($comuTexto === '' && !$temComunidadesTable) {
          // se não tem tabela comunidades, pode vir de input antigo (caso você queira)
          $comuTexto = trunc255(trim((string)($_POST['comunidade_texto'] ?? '')));
        }

        if (!$produtoresTemComunidadeTexto) {
          throw new RuntimeException("Sua tabela produtores não tem 'comunidade_id' nem 'comunidade'. Ajuste o schema.");
        }

        $sql = "INSERT INTO produtores (feira_id, nome, contato, comunidade, ativo, observacao)
                VALUES (:feira_id, :nome, :contato, :comunidade, :ativo, :observacao)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          ':feira_id'    => $FEIRA_ID,
          ':nome'        => $old['nome'],
          ':contato'     => $contato !== '' ? $contato : null,
          ':comunidade'  => ($comuTexto !== '') ? $comuTexto : null,
          ':ativo'       => $ativo,
          ':observacao'  => $obsFinal !== '' ? $obsFinal : null,
        ]);
      }

      $_SESSION['flash_ok'] = 'Produtor cadastrado com sucesso!';
      header('Location: ./listaProdutor.php');
      exit;

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
  <title>SIGRelatórios Feira do Produtor — Adicionar Produtor</title>

  <link rel="stylesheet" href="../../../vendors/feather/feather.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
  <link rel="stylesheet" href="../../../vendors/css/vendor.bundle.base.css">

  <link rel="stylesheet" href="../../../vendors/datatables.net-bs4/dataTables.bootstrap4.css">
  <link rel="stylesheet" href="../../../vendors/ti-icons/css/themify-icons.css">
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
    .help-hint { font-size: 12px; }
    .section-title { font-weight: 700; margin: 0; }
    .section-sub { margin: 0; opacity: .75; }
    .form-divider { margin: 14px 0; }
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

  <div class="container-fluid page-body-wrapper">

    <div id="right-sidebar" class="settings-panel">
      <i class="settings-close ti-close"></i>
      <ul class="nav nav-tabs border-top" id="setting-panel" role="tablist">
        <li class="nav-item"><a class="nav-link active" id="todo-tab" data-toggle="tab" href="#todo-section" role="tab">TO DO LIST</a></li>
        <li class="nav-item"><a class="nav-link" id="chats-tab" data-toggle="tab" href="#chats-section" role="tab">CHATS</a></li>
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
              .sub-menu .nav-item .nav-link { color: black !important; }
              .sub-menu .nav-item .nav-link:hover { color: blue !important; }
            </style>

            <ul class="nav flex-column sub-menu" style="background: white !important;">
              <li class="nav-item"><a class="nav-link" href="./listaProduto.php"><i class="ti-clipboard mr-2"></i> Lista de Produtos</a></li>
              <li class="nav-item"><a class="nav-link" href="./listaCategoria.php"><i class="ti-layers mr-2"></i> Categorias</a></li>
              <li class="nav-item"><a class="nav-link" href="./listaUnidade.php"><i class="ti-ruler-pencil mr-2"></i> Unidades</a></li>
              <li class="nav-item"><a class="nav-link" href="./listaProdutor.php"><i class="ti-user mr-2"></i> Produtores</a></li>

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
              <li class="nav-item"><a class="nav-link" href="./lancamentos.php"><i class="ti-write mr-2"></i> Lançamentos (Vendas)</a></li>
              <li class="nav-item"><a class="nav-link" href="./fechamentoDia.php"><i class="ti-check-box mr-2"></i> Fechamento do Dia</a></li>
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
              <li class="nav-item"><a class="nav-link" href="./relatorioFinanceiro.php"><i class="ti-bar-chart mr-2"></i> Relatório Financeiro</a></li>
              <li class="nav-item"><a class="nav-link" href="./relatorioProdutos.php"><i class="ti-list mr-2"></i> Produtos Comercializados</a></li>
              <li class="nav-item"><a class="nav-link" href="./relatorioMensal.php"><i class="ti-calendar mr-2"></i> Resumo Mensal</a></li>
              <li class="nav-item"><a class="nav-link" href="./configRelatorio.php"><i class="ti-settings mr-2"></i> Configurar</a></li>
            </ul>
          </div>
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

                <div class="d-flex align-items-center justify-content-between flex-wrap">
                  <div>
                    <h4 class="card-title mb-0">Dados do Produtor</h4>
                    <p class="card-description mb-0">Agora a comunidade vem de um select (cadastro de comunidades).</p>
                  </div>
                  <a href="./listaProdutor.php" class="btn btn-light btn-sm mt-2 mt-md-0">
                    <i class="ti-arrow-left"></i> Voltar
                  </a>
                </div>

                <form class="pt-4" method="post" action="">
                  <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">

                  <div class="row">

                    <!-- Bloco 1 -->
                    <div class="col-12 mb-2">
                      <p class="section-title">Identificação</p>
                      <p class="section-sub">Quem é o produtor e como contatar.</p>
                      <hr class="form-divider">
                    </div>

                    <div class="col-md-6 mb-3">
                      <label>Nome do produtor <span class="text-danger">*</span></label>
                      <input name="nome" type="text" class="form-control" placeholder="Ex.: João da Silva" required value="<?= h($old['nome']) ?>">
                      <small class="text-muted help-hint">Nome completo ou como é conhecido na feira.</small>
                    </div>

                    <div class="col-md-3 mb-3">
                      <label>CPF (opcional)</label>
                      <input name="cpf" type="text" class="form-control" placeholder="000.000.000-00" value="<?= h($old['cpf']) ?>">
                    </div>

                    <div class="col-md-3 mb-3">
                      <label>Status</label>
                      <select name="ativo" class="form-control">
                        <option value="1" <?= ($old['ativo'] === '1' ? 'selected' : '') ?>>Ativo</option>
                        <option value="0" <?= ($old['ativo'] === '0' ? 'selected' : '') ?>>Inativo</option>
                      </select>
                    </div>

                    <div class="col-md-6 mb-3">
                      <label>Telefone / WhatsApp</label>
                      <input name="telefone" type="text" class="form-control" placeholder="(92) 9xxxx-xxxx" value="<?= h($old['telefone']) ?>">
                    </div>

                    <div class="col-md-6 mb-3">
                      <label>Tipo</label>
                      <select name="tipo" class="form-control">
                        <option <?= ($old['tipo'] === 'Produtor rural' ? 'selected' : '') ?>>Produtor rural</option>
                        <option <?= ($old['tipo'] === 'Associação / Cooperativa' ? 'selected' : '') ?>>Associação / Cooperativa</option>
                        <option <?= ($old['tipo'] === 'Revendedor (se houver)' ? 'selected' : '') ?>>Revendedor (se houver)</option>
                      </select>
                      <small class="text-muted help-hint">Você pode ajustar essas opções depois.</small>
                    </div>

                    <!-- Bloco 2 -->
                    <div class="col-12 mt-2 mb-2">
                      <p class="section-title">Comunidade</p>
                      <p class="section-sub">Selecione uma comunidade cadastrada.</p>
                      <hr class="form-divider">
                    </div>

                    <div class="col-md-6 mb-3">
                      <label>Comunidade / Localidade</label>

                      <?php if ($temComunidadesTable): ?>
                        <select name="comunidade_id" class="form-control">
                          <option value="">— Selecione —</option>
                          <?php foreach ($comunidades as $c): ?>
                            <?php
                              $cid = (int)($c['id'] ?? 0);
                              $cnome = (string)($c['nome'] ?? '');
                              $sel = ((string)$cid === (string)$old['comunidade_id']) ? 'selected' : '';
                            ?>
                            <option value="<?= $cid ?>" <?= $sel ?>><?= h($cnome) ?></option>
                          <?php endforeach; ?>
                        </select>

                        <?php if (empty($comunidades)): ?>
                          <small class="text-danger d-block mt-2">
                            Nenhuma comunidade cadastrada ainda. Cadastre comunidades para aparecer aqui.
                          </small>
                        <?php else: ?>
                          <small class="text-muted help-hint">Lista carregada da tabela <b>comunidades</b>.</small>
                        <?php endif; ?>
                      <?php else: ?>
                        <div class="alert alert-warning mb-2">
                          Tabela <b>comunidades</b> não encontrada. (Assim que você criar, este campo vira select automático.)
                        </div>
                        <input name="comunidade_texto" type="text" class="form-control" placeholder="Ex.: Comunidade X / Ramal Y" value="">
                      <?php endif; ?>
                    </div>

                    <!-- Bloco 3 -->
                    <div class="col-12 mt-2 mb-2">
                      <p class="section-title">Observações</p>
                      <p class="section-sub">Informações extras do produtor.</p>
                      <hr class="form-divider">
                    </div>

                    <div class="col-md-12 mb-3">
                      <label>Observações</label>
                      <textarea name="observacao" class="form-control" rows="4" placeholder="Ex.: entrega só na sexta, produtos principais, etc."><?= h($old['observacao']) ?></textarea>
                      <small class="text-muted help-hint">CPF/Tipo também são gravados em observação (se sua tabela não tiver campos próprios).</small>
                    </div>

                  </div>

                  <hr>

                  <div class="d-flex flex-wrap" style="gap:8px;">
                    <button type="submit" class="btn btn-primary">
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
