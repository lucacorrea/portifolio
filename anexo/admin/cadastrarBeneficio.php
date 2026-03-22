<?php

declare(strict_types=1);
require_once __DIR__ . '/./auth/authGuard.php';
auth_guard();
// ... auth_guard() já executado

// CONEXÃO
require_once __DIR__ . '/../dist/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  die("<div class='alert alert-danger'>Conexão indisponível.</div>");
}

// Se vier com ?id=, carregamos os dados para edição
$editing = false;
$beneficio = [
  'id' => null,
  'nome' => '',
  'categoria' => '',
  'descricao' => '',
  'valor_padrao' => null,
  'periodicidade' => 'Única',
  'qtd_padrao' => 1,
  'doc_exigido' => '',
  'status' => 'Ativa'
];

if (isset($_GET['id']) && ctype_digit($_GET['id'])) {
  $id = (int)$_GET['id'];
  $st = $pdo->prepare("SELECT * FROM ajudas_tipos WHERE id = :id LIMIT 1");
  $st->execute([':id' => $id]);
  $row = $st->fetch(PDO::FETCH_ASSOC);
  if ($row) {
    $beneficio = $row;
    $editing = true;
  } else {
    echo "<div class='alert alert-warning m-3'>Benefício não encontrado.</div>";
  }
}

// helpers view
function v($arr, $key, $default = '')
{
  return htmlspecialchars((string)($arr[$key] ?? $default), ENT_QUOTES, 'UTF-8');
}
function sel($a, $b)
{
  return ($a === $b) ? 'selected' : '';
}
?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- <title> ... </title> -->
  <title><?= $editing ? 'Editar Benefício' : 'Cadastrar Benefício' ?> - ANEXO</title>


  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
  <link rel="stylesheet" href="../dist/assets/vendors/simple-datatables/style.css">
  <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
  <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../dist/assets/css/app.css">
  <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">
</head>

<body>
  <div id="app">
    <!-- SIDEBAR (use o menu que guardamos) -->
    <div id="sidebar" class="active">
      <div class="sidebar-wrapper active">
        <div class="sidebar-header">
          <div class="d-flex justify-content-between align-items-center">
            <div class="logo">
              <a href="dashboard.php"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo" style="height:48px;width:auto;"></a>
            </div>
            <div class="toggler">
              <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
            </div>
          </div>
        </div>

        <!-- MENU RESUMIDO ANEXO -->
        <div class="sidebar-menu">
          <ul class="menu">

            <li class="sidebar-item">
              <a href="dashboard.php" class="sidebar-link">
                <i class="bi bi-grid-fill"></i>
                <span>Dashboard</span>
              </a>
            </li>

            <!-- ENTREGAS DE BENEFÍCIOS -->
            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link">
                <i class="bi bi-hand-thumbs-up-fill"></i>
                <span>Entregas</span>
              </a>
              <ul class="submenu">
                <li class="submenu-item">
                  <a href="registrarEntrega.php">Registrar Entrega</a>
                </li>
                <li class="submenu-item">
                  <a href="entregasRealizadas.php">Histórico de Entregas</a>
                </li>
              </ul>
            </li>

            <!-- NOVO: Bairros -->
            <li class="sidebar-item has-sub ">
              <a href="#" class="sidebar-link">
                <i class="bi bi-geo-alt-fill"></i>
                <span>Bairros</span>
              </a>
              <ul class="submenu">
                <li class="submenu-item"><a href="bairrosCadastrados.php">Bairros Cadastrados</a></li>
                <li class="submenu-item"><a href="cadastrarBairro.php">Cadastrar Bairro</a></li>
              </ul>
            </li>

            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link">
                <i class="bi bi-house-fill"></i>
                <span>Beneficiarios</span>
              </a>
              <ul class="submenu">
                <li class="submenu-item"><a href="beneficiariosBolsaFamilia.php">Bolsa Família</a></li>
                <li class="submenu-item"><a href="beneficiariosEstadual.php">Estadual</a></li>
                <li class="submenu-item"><a href="beneficiariosMunicipal.php">Municipal</a></li>
                <li class="submenu-item"><a href="beneficiariosSemas.php">ANEXO</a></li>
              </ul>
            </li>

            <!-- Ajuda Social -->
            <?php
            // Páginas ativas
            $pg        = basename($_SERVER['PHP_SELF']);
            $isCadastro = ($pg === 'cadastrarBeneficio.php' && empty($_GET['id']));
            $isEditar  = ($pg === 'cadastrarBeneficio.php' && !empty($_GET['id']));
            ?>
            <li class="sidebar-item has-sub <?= ($pg === 'cadastrarBeneficio.php') ? 'active' : '' ?>">
              <a href="#" class="sidebar-link">
                <i class="bi bi-hand-thumbs-up-fill"></i>
                <span>Ajuda Social</span>
              </a>

              <ul class="submenu <?= ($pg === 'cadastrarBeneficio.php') ? 'active' : '' ?>">
                <!-- Cadastrar -->
                <li class="submenu-item <?= $isCadastro ? 'active' : '' ?>">
                  <a href="cadastrarBeneficio.php">Cadastrar Benefício</a>
                </li>

                <!-- Editar (somente quando houver ?id=) -->
                <?php if ($isEditar): ?>
                  <li class="submenu-item active">
                    <a href="cadastrarBeneficio.php?id=<?= (int)($_GET['id'] ?? 0) ?>">Editar Benefício</a>
                  </li>
                <?php endif; ?>
                <li class="submenu-item"><a href="beneficiosCadastrados.php">Benefícios Cadastrados</a></li>
              </ul>
            </li>


            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link">
                <i class="bi bi-bar-chart-line-fill"></i>
                <span>Relatórios</span>
              </a>
              <ul class="submenu">
                <li class="submenu-item"><a href="relatoriosCadastros.php">Cadastros</a></li>
                <li class="submenu-item"><a href="relatorioAtendimentos.php">Atendimentos</a></li>
                <li class="submenu-item"><a href="relatorioBeneficios.php">Benefícios</a></li>
              </ul>
            </li>

            <!-- CONTROLE DE VALORES -->
            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link">
                <i class="bi bi-cash-stack"></i>
                <span>Controle Financeiro</span>
              </a>
              <ul class="submenu">
                <li class="submenu-item">
                  <a href="valoresAplicados.php">Valores Aplicados</a>
                </li>
                <li class="submenu-item">
                  <a href="beneficiosAcimaMil.php">Acima de R$ 1.000</a>
                </li>
              </ul>
            </li>

            <!-- 🔒 USUÁRIOS (ÚNICO COM CONTROLE DE PERFIL) -->
            <?php if (($_SESSION['user_role'] ?? '') === 'suporte'): ?>
              <li class="sidebar-item has-sub">
                <a href="#" class="sidebar-link">
                  <i class="bi bi-people-fill"></i>
                  <span>Usuários</span>
                </a>
                <ul class="submenu">
                  <li class="submenu-item">
                    <a href="usuariosPermitidos.php">Permitidos</a>
                  </li>
                  <li class="submenu-item">
                    <a href="usuariosNaoPermitidos.php">Não Permitidos</a>
                  </li>
                </ul>
              </li>
            <?php endif; ?>

            <!-- AUDITORIA / LOG -->
            <li class="sidebar-item">
              <a href="auditoria.php" class="sidebar-link">
                <i class="bi bi-shield-lock-fill"></i>
                <span>Auditoria</span>
              </a>
            </li>


            <li class="sidebar-item">
              <a href="./auth/logout.php" class="sidebar-link">
                <i class="bi bi-box-arrow-right"></i>
                <span>Sair</span>
              </a>
            </li>

          </ul>
        </div>

        <!-- /MENU -->
      </div>
    </div>

    <!-- MAIN -->

    <div id="main" class="d-flex flex-column min-vh-100">
      <header class="mb-3">
        <a href="#" class="burger-btn d-block d-xl-none">
          <i class="bi bi-justify fs-3"></i>
        </a>
      </header>

      <div class="page-heading">
        <div class="page-title">
          <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
              <!-- Título e subtítulo da página -->
              <h3><?= $editing ? 'Editar Benefício' : 'Cadastrar Benefício (Ajuda Social)' ?></h3>
              <p class="text-subtitle text-muted">
                <?= $editing ? 'Atualize as informações do benefício selecionado' : 'Cadastre os tipos de benefícios oferecidos pelo ANEXO' ?>
              </p>

            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
              <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="#">Ajuda Social</a></li>

                  <?php if (!empty($_GET['id'])): ?>

                    <li class="breadcrumb-item active" aria-current="page">Editar Benefício</li>
                  <?php else: ?>
                    <li class="breadcrumb-item active" aria-current="page">Cadastrar Benefício</li>
                  <?php endif; ?>
                </ol>
              </nav>
            </div>

          </div>
        </div>

        <section id="multiple-column-form">
          <div class="row match-height">
            <div class="col-12">
              <div class="card">
                <div class="card-content">
                  <div class="card-body">

                    <?php
                    // feedback (?ok=1 ou ?err=msg)
                    $ok  = isset($_GET['ok']) ? (int)$_GET['ok'] : 0;
                    $err = isset($_GET['err']) ? trim((string)$_GET['err']) : '';
                    if ($ok === 1) {
                      echo '<div class="alert alert-success">' . ($editing ? 'Benefício atualizado' : 'Benefício cadastrado') . ' com sucesso.</div>';
                    } elseif ($err !== '') {
                      echo '<div class="alert alert-danger">Erro: ' . htmlspecialchars($err, ENT_QUOTES, 'UTF-8') . '</div>';
                    }
                    ?>

                    <form action="<?= $editing ? './ajudas/atualizarBeneficio.php' : './ajudas/processarBeneficio.php' ?>"
                      method="POST" autocomplete="off">
                      <?php if ($editing): ?>
                        <input type="hidden" name="id" value="<?= (int)$beneficio['id'] ?>">
                      <?php endif; ?>

                      <div class="row g-3">
                        <!-- Nome -->
                        <div class="col-12 col-md-6">
                          <label for="nome" class="form-label">Nome do benefício *</label>
                          <input type="text" class="form-control" id="nome" name="nome" required
                            value="<?= v($beneficio, 'nome') ?>"
                            placeholder="Ex.: Cesta Básica, Aluguel Social">
                        </div>

                        <!-- Categoria -->
                        <div class="col-12 col-md-6">
                          <label for="categoria" class="form-label">Categoria</label>
                          <?php $cat = $beneficio['categoria'] ?? ''; ?>
                          <select id="categoria" name="categoria" class="form-select">
                            <option value="">Selecione...</option>
                            <option <?= sel($cat, 'Alimentação') ?>>Alimentação</option>
                            <option <?= sel($cat, 'Moradia') ?>>Moradia</option>
                            <option <?= sel($cat, 'Transporte') ?>>Transporte</option>
                            <option <?= sel($cat, 'Saúde') ?>>Saúde</option>
                            <option <?= sel($cat, 'Assistência Eventual') ?>>Assistência Eventual</option>
                            <option <?= sel($cat, 'Outros') ?>>Outros</option>
                          </select>
                        </div>

                        <!-- Descrição -->
                        <div class="col-12">
                          <label for="descricao" class="form-label">Descrição / Critérios</label>
                          <textarea id="descricao" name="descricao" class="form-control" rows="3"
                            placeholder="Critérios, observações..."><?= v($beneficio, 'descricao') ?></textarea>
                        </div>

                        <!-- Valor padrão -->
                        <div class="col-12 col-md-4">
                          <label for="valor_padrao" class="form-label">Valor padrão (R$)</label>
                          <div class="input-group">
                            <span class="input-group-text">R$</span>
                            <input type="number" step="0.01" min="0" class="form-control"
                              id="valor_padrao" name="valor_padrao"
                              value="<?= htmlspecialchars($beneficio['valor_padrao'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                              placeholder="Ex.: 300,00">
                          </div>
                        </div>

                        <!-- Periodicidade -->
                        <div class="col-12 col-md-4">
                          <label for="periodicidade" class="form-label">Periodicidade</label>
                          <?php $per = $beneficio['periodicidade'] ?? 'Única'; ?>
                          <select id="periodicidade" name="periodicidade" class="form-select">
                            <option <?= sel($per, 'Única') ?>>Única</option>
                            <option <?= sel($per, 'Mensal') ?>>Mensal</option>
                            <option <?= sel($per, 'Trimestral') ?>>Trimestral</option>
                            <option <?= sel($per, 'Eventual') ?>>Eventual</option>
                          </select>
                        </div>

                        <!-- Quantidade padrão -->
                        <div class="col-12 col-md-4">
                          <label for="qtd_padrao" class="form-label">Qtd. padrão por entrega</label>
                          <input type="number" min="1" class="form-control" id="qtd_padrao" name="qtd_padrao"
                            value="<?= (int)($beneficio['qtd_padrao'] ?? 1) ?>">
                        </div>

                        <!-- Doc. exigido -->
                        <div class="col-12 col-md-8">
                          <label for="doc_exigido" class="form-label">Documento exigido</label>
                          <input type="text" class="form-control" id="doc_exigido" name="doc_exigido"
                            value="<?= v($beneficio, 'doc_exigido') ?>"
                            placeholder="RG/CPF, NIS, Laudo, Comprovante...">
                        </div>

                        <!-- Status -->
                        <div class="col-12 col-md-4">
                          <label for="status" class="form-label">Status</label>
                          <?php $stt = $beneficio['status'] ?? 'Ativa'; ?>
                          <select id="status" name="status" class="form-select">
                            <option <?= sel($stt, 'Ativa') ?>>Ativa</option>
                            <option <?= sel($stt, 'Inativa') ?>>Inativa</option>
                          </select>
                        </div>
                      </div>

                      <div class="mt-4 d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                          <?= $editing ? 'Atualizar' : 'Cadastrar' ?>
                        </button>
                        <a href="beneficiosCadastrados.php" class="btn btn-outline-secondary">Voltar para a lista</a>
                      </div>
                    </form>
                  </div><!-- card-body -->

                </div>
              </div>
            </div>
          </div>
        </section>

      </div><!-- /page-heading -->

      <footer>
        <div class="footer clearfix mb-0 text-muted">
          <div class="float-start text-black">
            <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de Coari-AM.</b></p>
            <script>
              document.getElementById('current-year').textContent = new Date().getFullYear();
            </script>
          </div>
          <div class="float-end text-black">
            <p>Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p>
          </div>
        </div>
      </footer>

    </div><!-- /main -->
  </div><!-- /app -->

  <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
  <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
  <script src="../dist/assets/js/main.js"></script>
</body>

</html>