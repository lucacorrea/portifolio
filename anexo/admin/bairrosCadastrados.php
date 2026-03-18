<?php

declare(strict_types=1);
require_once __DIR__ . '/./auth/authGuard.php';
auth_guard();

/* ===== CONEXÃO (PDO) ===== */
require_once __DIR__ . '/../dist/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  echo "<div class='alert alert-danger'>Erro: conexão com o banco não encontrada.</div>";
  exit;
}

/* ===== Handler: excluir dentro da página (POST) ===== */
$msgOk = $msgErr = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['delete_id'])) {
  $deleteId = (int)$_POST['delete_id'];

  // (opcional) checagens extras antes de excluir, ex: se bairro é usado em outra tabela
  try {
    $st = $pdo->prepare("DELETE FROM bairros WHERE id = :id");
    $ok = $st->execute([':id' => $deleteId]);
    if ($ok && $st->rowCount() > 0) {
      $msgOk = 'Bairro excluído com sucesso.';
    } else {
      $msgErr = 'Não foi possível excluir (ID inválido ou já removido).';
    }
  } catch (Throwable $e) {
    // Se houver FK em outras tabelas, cairá aqui
    $msgErr = 'Falha ao excluir. Este bairro pode estar vinculado a outros registros.';
  }
}

/* ===== Busca dos bairros ===== */
try {
  $sql = "SELECT id, nome FROM bairros ORDER BY nome ASC";
  $stmt = $pdo->query($sql);
  $bairros = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  echo "<div class='alert alert-danger'>Erro ao consultar bairros: " . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . "</div>";
  exit;
}

/* ===== Helpers ===== */
function e(string $v): string
{
  return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Bairros Cadastrados - ANEXO</title>

  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
  <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
  <link rel="stylesheet" href="../dist/assets/vendors/iconly/bold.css">
  <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../dist/assets/css/app.css">
  <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

</head>

<body>
  <div id="app">
    <div id="sidebar" class="active">
      <div class="sidebar-wrapper active">
        <div class="sidebar-header">
          <div class="d-flex justify-content-between">
            <div class="logo"><a href="dashboard.php"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo"></a></div>
            <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a></div>
          </div>
        </div>

        <div class="sidebar-menu">
          <ul class="menu">
            <li class="sidebar-item"><a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a></li>

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

            <li class="sidebar-item has-sub active">
              <a href="#" class="sidebar-link"><i class="bi bi-geo-alt-fill"></i><span>Bairros</span></a>
              <ul class="submenu active">
                <li class="submenu-item active"><a href="bairrosCadastrados.php">Bairros Cadastrados</a></li>
                <li class="submenu-item"><a href="cadastrarBairro.php">Cadastrar Bairro</a></li>
              </ul>
            </li>

            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link"><i class="bi bi-house-fill"></i><span>Beneficiarios</span></a>
              <ul class="submenu">
                <li class="submenu-item"><a href="beneficiariosBolsaFamilia.php">Bolsa Família</a></li>
                <li class="submenu-item"><a href="beneficiariosEstadual.php">Estadual</a></li>
                <li class="submenu-item"><a href="beneficiariosMunicipal.php">Municipal</a></li>
                <li class="submenu-item"><a href="beneficiariosSemas.php">ANEXO</a></li>
              </ul>
            </li>

            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link"><i class="bi bi-hand-thumbs-up-fill"></i><span>Ajuda Social</span></a>
              <ul class="submenu">
                <li class="submenu-item"><a href="cadastrarBeneficio.php">Cadastrar Benefício</a></li>
                <li class="submenu-item"><a href="beneficiosCadastrados.php">Benefícios Cadastrados</a></li>
              </ul>
            </li>

            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link"><i class="bi bi-bar-chart-line-fill"></i><span>Relatórios</span></a>
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

            <li class="sidebar-item"><a href="./auth/logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a></li>
          </ul>
        </div>
      </div>
    </div>

    <!-- MAIN -->
    <div id="main" class="d-flex flex-column min-vh-100">
      <header class="mb-3">
        <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
      </header>

      <div class="page-heading">
        <div class="page-title">
          <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
              <h3>Bairros Cadastrados</h3>
              <p class="text-subtitle text-muted">Visualize os Bairros Cadastrados</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
              <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="#">Bairros</a></li>
                  <li class="breadcrumb-item active" aria-current="page">Bairros Cadastrados</li>
                </ol>
              </nav>
            </div>
          </div>
        </div>

        <section class="section mb-4">
          <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
              <span>Lista de Bairros</span>
              <a href="cadastrarBairro.php" class="btn btn-sm btn-primary">
                <i class="bi bi-plus-lg"></i> Novo Bairro
              </a>
            </div>

            <div class="card-body">
              <?php if ($msgOk): ?>
                <div class="alert alert-success"><?= e($msgOk) ?></div>
              <?php elseif ($msgErr): ?>
                <div class="alert alert-danger"><?= e($msgErr) ?></div>
              <?php endif; ?>

              <div class="table-responsive-md">
                <table class="table table-striped table-hover align-middle w-100" id="table1">
                  <thead>
                    <tr>
                      <th scope="col">Nome</th>
                      <th scope="col" class="text-center text-nowrap" style="width:1%;white-space:nowrap;">Ações</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php if (empty($bairros)): ?>
                      <tr>
                        <td colspan="2" class="text-center text-muted">Nenhum bairro cadastrado.</td>
                      </tr>
                    <?php else: ?>
                      <?php foreach ($bairros as $b): ?>
                        <tr>
                          <td class="text-truncate" style="max-width:320px;"><?= e($b['nome']) ?></td>
                          <td class="text-center">
                            <form method="POST" class="d-inline"
                              onsubmit="return confirm('Excluir definitivamente este bairro?');">
                              <input type="hidden" name="delete_id" value="<?= (int)$b['id'] ?>">
                              <button type="submit" class="btn btn-sm btn-link p-0 text-danger" title="Excluir">
                                <i class="bi bi-trash"></i>
                              </button>
                            </form>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>

              <script>
                (function() {
                  if (!window.simpleDatatables) return;
                  const el = document.querySelector("#table1");
                  if (!el.dataset.dtInit) {
                    new simpleDatatables.DataTable(el, {
                      searchable: true,
                      fixedHeight: true,
                      perPage: 10,
                      perPageSelect: [5, 10, 25, 50],
                      labels: {
                        placeholder: "Buscar...",
                        perPage: "{select} por página",
                        noRows: "Nenhum bairro encontrado",
                        info: "Mostrando {start} a {end} de {rows} entradas",
                        noResults: "Nenhum resultado para \"{query}\"",
                        sort: "Ordenar por {column}"
                      }
                    });
                    el.dataset.dtInit = "1";
                  }
                })();
              </script>
            </div>
          </div>
        </section>
      </div>

      <footer class="mt-auto py-3 bg-body-tertiary">
        <div class="container d-flex flex-wrap justify-content-between align-items-center gap-3 small text-muted">
          <div><span id="current-year"></span> &copy; Todos os direitos reservados à <strong class="text-body">Prefeitura Municipal de Coari-AM.</strong></div>
          <div>Desenvolvido por <strong class="text-body">Junior Praia, Lucas Correa e Luiz Frota.</strong></div>
        </div>
      </footer>
    </div>
  </div>

  <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
  <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
  <script src="../dist/assets/js/main.js"></script>
  <script>
    document.getElementById('current-year').textContent = new Date().getFullYear();
  </script>
</body>

</html>