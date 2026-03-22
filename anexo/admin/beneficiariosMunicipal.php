<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* ===== CONEXÃO (PDO) ===== */
require_once __DIR__ . '/../dist/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  echo "<div class='alert alert-danger'>Erro: conexão com o banco não encontrada.</div>";
  exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Helpers ===== */
function e(?string $v): string
{
  return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
function only_digits(?string $s): string
{
  return preg_replace('/\D+/', '', (string)$s);
}
function formatCpf(?string $cpf): string
{
  $d = only_digits($cpf);
  if (strlen($d) !== 11) return (string)$cpf;
  return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}
function formatPhone(?string $t): string
{
  $d = only_digits($t);
  if (strlen($d) === 11) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 5), substr($d, 7));
  if (strlen($d) === 10) return sprintf('(%s) %s-%s', substr($d, 0, 2), substr($d, 2, 4), substr($d, 6));
  return (string)$t;
}
function moneyBR($v): string
{
  return ($v === null || $v === '') ? '—' : number_format((float)$v, 2, ',', '.');
}

/* ===========================================================
   MINI API: /beneficiariosMunicipal.php?view=1&id=123
   =========================================================== */
if (($_GET['view'] ?? '') === '1') {
  header('Content-Type: application/json; charset=UTF-8');
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
  }

  try {
    $st = $pdo->prepare("SELECT * FROM solicitantes WHERE id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $sol = $st->fetch(PDO::FETCH_ASSOC);
    if (!$sol) {
      echo json_encode(['ok' => false, 'error' => 'Registro não encontrado']);
      exit;
    }

    $bairroNome = null;
    try {
      $b = $pdo->prepare("SELECT nome FROM bairros WHERE id = :bid");
      $b->execute([':bid' => (int)($sol['bairro_id'] ?? 0)]);
      $bairroNome = $b->fetchColumn() ?: null;
    } catch (Throwable $e) {
    }

    $familiares = [];
    try {
      $f = $pdo->prepare("SELECT nome, data_nascimento, parentesco, escolaridade, obs
                                FROM familiares
                                WHERE solicitante_id = :sid
                                ORDER BY nome ASC");
      $f->execute([':sid' => $id]);
      $familiares = $f->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
    }

    $documentos = [];
    try {
      $d = $pdo->prepare("SELECT id, original_name, arquivo_path, size_bytes, created_at
                                FROM solicitante_documentos
                                WHERE solicitante_id = :sid
                                ORDER BY id DESC");
      $d->execute([':sid' => $id]);
      $documentos = $d->fetchAll(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {
    }

    echo json_encode([
      'ok' => true,
      'data' => $sol,
      'bairro_nome' => $bairroNome,
      'familiares' => $familiares,
      'documentos' => $documentos
    ], JSON_UNESCAPED_UNICODE);
    exit;
  } catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Falha ao consultar'], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

/* ===== Handler: excluir (POST) ===== */
$msgOk = $msgErr = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && isset($_POST['delete_id'])) {
  $deleteId = (int)$_POST['delete_id'];
  try {
    $pdo->beginTransaction();
    $pdo->prepare("DELETE FROM solicitante_documentos WHERE solicitante_id = :id")->execute([':id' => $deleteId]);
    $pdo->prepare("DELETE FROM familiares WHERE solicitante_id = :id")->execute([':id' => $deleteId]);
    $st = $pdo->prepare("DELETE FROM solicitantes WHERE id = :id");
    $st->execute([':id' => $deleteId]);
    if ($st->rowCount() > 0) {
      $pdo->commit();
      $msgOk = 'Registro excluído com sucesso.';
    } else {
      $pdo->rollBack();
      $msgErr = 'Não foi possível excluir (ID inválido ou já removido).';
    }
  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    $msgErr = 'Falha ao excluir o registro.';
  }
}

/* ===== Dados (client-side pagination/search) ===== */
try {
  $rows = $pdo->query("
        SELECT id, nome, cpf, beneficio_municipal_valor, numero, endereco, telefone, responsavel
        FROM solicitantes
        WHERE beneficio_municipal = 'Sim'
        ORDER BY nome ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  echo "<div class='alert alert-danger'>Erro ao consultar beneficiários: " . e($e->getMessage()) . "</div>";
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <title>Beneficiários – Municipal</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../dist/assets/css/bootstrap.css" />
  <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css" />
  <link rel="stylesheet" href="../dist/assets/css/app.css" />
  <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg" />
  <style>
    .table-actions .btn {
      margin: 0 2px
    }

    .td-endereco {
      max-width: 200px
    }

    .td-responsavel {
      max-width: 180px
    }

    @media (max-width:991.98px) {
      .td-endereco {
        max-width: 150px
      }

      .td-responsavel {
        max-width: 140px
      }
    }

    .profile-wrap {
      display: flex;
      gap: 1rem;
      align-items: center;
      flex-wrap: wrap;
      padding: .25rem 0 .75rem;
      border-bottom: 1px solid rgba(0, 0, 0, .08);
      margin-bottom: 1rem
    }

    .modal-photo {
      width: 110px;
      height: 110px;
      object-fit: cover;
      border-radius: 12px;
      border: 1px solid rgba(0, 0, 0, .08);
      background: #f8f9fa
    }

    .profile-subline {
      display: flex;
      flex-wrap: wrap;
      gap: .35rem
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .25rem .6rem;
      border-radius: 999px;
      background: #f1f3f5;
      font-size: .85rem
    }

    .kv-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: .6rem .8rem
    }

    .kv {
      background: #fff;
      border: 1px solid rgba(0, 0, 0, .06);
      border-radius: .5rem;
      padding: .6rem .7rem
    }

    .kv-label {
      font-size: .8rem;
      color: #6c757d
    }

    .kv-value {
      font-weight: 600
    }

    .scroll-x {
      overflow-x: auto
    }

    .docs-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .5rem;
      flex-wrap: wrap;
      margin-bottom: 0
    }

    #md-docs {
      margin-top: .35rem
    }

    #md-docs .doc-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      padding: .5rem .6rem;
      border-radius: .65rem;
      background: #f8f9fa;
      margin: .4rem 0
    }

    #md-docs .doc-meta {
      display: flex;
      align-items: start;
      gap: .5rem
    }

    #md-docs .doc-name {
      font-weight: 600;
      word-break: break-word
    }

    #md-docs .doc-sub {
      font-size: .8rem;
      color: #6c757d
    }

    .tfoot-pager {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: .75rem 1rem;
      flex-wrap: wrap
    }
  </style>
</head>

<body>
  <div id="app">
    <div id="sidebar" class="active">
      <div class="sidebar-wrapper active">
        <div class="sidebar-header">
          <div class="d-flex justify-content-between">
            <div class="logo"><a href="dashboard.php"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo" /></a></div>
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

            <li class="sidebar-item has-sub">
              <a href="#" class="sidebar-link"><i class="bi bi-geo-alt-fill"></i><span>Bairros</span></a>
              <ul class="submenu">
                <li class="submenu-item"><a href="bairrosCadastrados.php">Bairros Cadastrados</a></li>
                <li class="submenu-item"><a href="cadastrarBairro.php">Cadastrar Bairro</a></li>
              </ul>
            </li>
            <li class="sidebar-item has-sub active">
              <a href="#" class="sidebar-link"><i class="bi bi-house-fill"></i><span>Beneficiarios</span></a>
              <ul class="submenu active">
                <li class="submenu-item"><a href="beneficiariosBolsaFamilia.php">Bolsa Família</a></li>
                <li class="submenu-item"><a href="beneficiariosEstadual.php">Estadual</a></li>
                <li class="submenu-item active"><a href="#">Municipal</a></li>
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

    <div id="main" class="d-flex flex-column min-vh-100">
      <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>

      <div class="page-heading">
        <div class="page-title">
          <div class="row">
            <div class="col-12 col-md-6 order-md-1 order-last">
              <h3>Beneficiários – Municipal</h3>
              <p class="text-subtitle text-muted">Lista de beneficiários de programas municipais</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
              <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="#">Beneficiarios</a></li>
                  <li class="breadcrumb-item active" aria-current="page">Municipal</li>
                </ol>
              </nav>
            </div>
          </div>
        </div>

        <section class="section mb-4">
          <div class="card">
            <div class="card-header d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
              <span>Lista de Beneficiários</span>
              <div class="d-flex gap-2 align-items-center">
                <input id="qLive" class="form-control form-control-sm" placeholder="Buscar por nome/CPF/telefone/endereço/responsável..." autocomplete="off" />
                <button class="btn btn-sm btn-outline-secondary" type="button" id="btnClear" title="Limpar">
                  <i class="bi bi-x-circle"></i>
                </button>
              </div>
            </div>

            <div class="card-body">
              <?php if ($msgOk): ?>
                <div class="alert alert-success"><?= e($msgOk) ?></div>
              <?php elseif ($msgErr): ?>
                <div class="alert alert-danger"><?= e($msgErr) ?></div>
              <?php endif; ?>

              <div class="table-responsive-md">
                <table class="table table-striped table-hover align-middle w-100 text-nowrap" id="tbl">
                  <thead class="table-light">
                    <tr>
                      <th>CPF</th>
                      <th>Nome</th>
                      <th class="text-end">Valor (R$)</th>
                      <th>Número</th>
                      <th>Endereço</th>
                      <th>Telefone</th>
                      <th>Responsável</th>
                      <th class="text-center text-nowrap">Ações</th>
                    </tr>
                  </thead>
                  <tbody id="tbody">
                    <?php if (!$rows): ?>
                      <tr>
                        <td colspan="8" class="text-center text-muted">Nenhum beneficiário encontrado.</td>
                      </tr>
                      <?php else: foreach ($rows as $r): ?>
                        <?php
                        $cpfDigits = only_digits($r['cpf']);
                        $telDigits = only_digits($r['telefone']);
                        $rowNome   = mb_strtolower($r['nome'] ?? '');
                        $rowEnd    = mb_strtolower($r['endereco'] ?? '');
                        $rowNum    = (string)($r['numero'] ?? '');
                        $rowResp   = mb_strtolower($r['responsavel'] ?? '');
                        ?>
                        <tr
                          data-id="<?= (int)$r['id'] ?>"
                          data-nome="<?= e($rowNome) ?>"
                          data-cpf="<?= e($cpfDigits) ?>"
                          data-telefone="<?= e($telDigits) ?>"
                          data-endereco="<?= e($rowEnd) ?>"
                          data-numero="<?= e($rowNum) ?>"
                          data-responsavel="<?= e($rowResp) ?>">
                          <td class="nowrap"><?= e(formatCpf($r['cpf'])) ?></td>
                          <td><?= e($r['nome']) ?></td>
                          <td class="text-end"><?= e(moneyBR($r['beneficio_municipal_valor'])) ?></td>
                          <td><?= e($r['numero']) ?></td>
                          <td class="td-endereco"><?= e($r['endereco']) ?></td>
                          <td class="nowrap"><?= e(formatPhone($r['telefone'])) ?></td>
                          <td class="td-responsavel"><?= e($r['responsavel']) ?></td>
                          <td class="text-center table-actions">
                            <button type="button" class="btn btn-sm btn-outline-secondary btnVer">Ver</button>
                          </td>
                        </tr>
                    <?php endforeach;
                    endif; ?>
                  </tbody>
                </table>
              </div>

              <!-- Paginação client-side -->
              <div class="mt-2 tfoot-pager">
                <div class="d-flex align-items-center gap-2">
                  <button class="btn btn-outline-secondary btn-sm" id="btnPrev">Anterior</button>
                  <button class="btn btn-outline-secondary btn-sm" id="btnNext">Próxima</button>
                </div>
                <div class="flex-grow-1 d-flex justify-content-center">
                  <strong id="lblPagina">Página 1 de 1</strong>
                </div>
                <div class="d-flex align-items-center gap-2">
                  <label for="selPerPage" class="form-label m-0">por página</label>
                  <select id="selPerPage" class="form-select form-select-sm" style="width:auto">
                    <option>10</option>
                    <option>20</option>
                    <option>50</option>
                    <option>100</option>
                  </select>
                </div>
              </div>
              <!-- /Paginação -->
            </div>
          </div>
        </section>
      </div>

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
    </div>
  </div>

  <!-- ========== MODAL ========== -->
  <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalhes do Beneficiário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <div class="profile-wrap">
            <img id="md-foto" class="modal-photo" src="../dist/assets/images/placeholders/user-placeholder.svg" alt="Foto">
            <div class="profile-info">
              <h5 class="profile-name" id="md-nome">—</h5>
              <div class="profile-subline">
                <span class="pill"><i class="bi bi-person"></i> <span id="md-genero">—</span></span>
                <span class="pill"><i class="bi bi-heart"></i> <span id="md-ec">—</span></span>
                <span class="pill"><i class="bi bi-calendar2"></i> <span id="md-nasc">—</span></span>
              </div>
              <div class="text-muted mt-1" style="font-size:.875rem;">Cadastro: <span id="md-criado">—</span></div>
            </div>
          </div>

          <h6 class="mb-2">I. Identificação</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">CPF</div>
              <div class="kv-value" id="md-cpf">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">NIS</div>
              <div class="kv-value" id="md-nis">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">RG</div>
              <div class="kv-value" id="md-rg">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Emissão do RG</div>
              <div class="kv-value" id="md-rg-emissao">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">UF (RG)</div>
              <div class="kv-value" id="md-rg-uf">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Telefone</div>
              <div class="kv-value" id="md-tel">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Gênero</div>
              <div class="kv-value" id="md-genero-2">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Estado Civil</div>
              <div class="kv-value" id="md-ec-2">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Nascimento</div>
              <div class="kv-value" id="md-nasc-2">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Nacionalidade</div>
              <div class="kv-value" id="md-nac">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Naturalidade</div>
              <div class="kv-value" id="md-nat">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Tempo de Moradia</div>
              <div class="kv-value" id="md-tempo">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Responsável</div>
              <div class="kv-value" id="md-responsavel">—</div>
            </div>
          </div>

          <h6 class="mb-2">II. Endereço</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">Endereço</div>
              <div class="kv-value" id="md-endereco">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Número</div>
              <div class="kv-value" id="md-numero">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Complemento</div>
              <div class="kv-value" id="md-complemento">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Bairro</div>
              <div class="kv-value" id="md-bairro">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Referência</div>
              <div class="kv-value" id="md-referencia">—</div>
            </div>
          </div>

          <h6 class="mb-2">III. Grupos, Benefícios e Renda</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">Grupo Tradicional</div>
              <div class="kv-value" id="md-grupo">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Grupo (Outros)</div>
              <div class="kv-value" id="md-grupo-outros">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">PCD</div>
              <div class="kv-value" id="md-pcd">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Tipo PCD</div>
              <div class="kv-value" id="md-pcd-tipo">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">BPC</div>
              <div class="kv-value" id="md-bpc">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">BPC Valor</div>
              <div class="kv-value" id="md-bpc-valor">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">PBF</div>
              <div class="kv-value" id="md-pbf">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">PBF Valor</div>
              <div class="kv-value" id="md-pbf-valor">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Benef. Municipal</div>
              <div class="kv-value" id="md-ben-mun">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Valor Municipal</div>
              <div class="kv-value" id="md-ben-mun-valor">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Benef. Estadual</div>
              <div class="kv-value" id="md-ben-est">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Valor Estadual</div>
              <div class="kv-value" id="md-ben-est-valor">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Faixa Renda</div>
              <div class="kv-value" id="md-faixa">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Renda (Outros)</div>
              <div class="kv-value" id="md-faixa-outros">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Trabalho</div>
              <div class="kv-value" id="md-trabalho">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Renda Individual</div>
              <div class="kv-value" id="md-renda-ind">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Renda Familiar</div>
              <div class="kv-value" id="md-renda-fam">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Total Rendimentos</div>
              <div class="kv-value" id="md-rend-tot">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Tipificação</div>
              <div class="kv-value" id="md-tipificacao">—</div>
            </div>
          </div>

          <h6 class="mb-2">IV. Composição Familiar (Totais)</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">Total Moradores</div>
              <div class="kv-value" id="md-tot-mor">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Total Famílias</div>
              <div class="kv-value" id="md-tot-fam">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">PCD na Residência</div>
              <div class="kv-value" id="md-pcd-res">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Qtde PCD</div>
              <div class="kv-value" id="md-tot-pcd">—</div>
            </div>
          </div>

          <h6 class="mb-2">V. Condições Habitacionais</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">Situação do Imóvel</div>
              <div class="kv-value" id="md-sit">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Aluguel (R$)</div>
              <div class="kv-value" id="md-sit-valor">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Tipo da Moradia</div>
              <div class="kv-value" id="md-tipo-moradia">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Abastecimento</div>
              <div class="kv-value" id="md-abast">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Iluminação</div>
              <div class="kv-value" id="md-ilum">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Esgoto</div>
              <div class="kv-value" id="md-esgoto">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Lixo</div>
              <div class="kv-value" id="md-lixo">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Entorno</div>
              <div class="kv-value" id="md-entorno">—</div>
            </div>
          </div>

          <h6 class="mb-2">VI. Resumo do Caso</h6>
          <div class="kv mb-3">
            <div class="kv-value" id="md-resumo">—</div>
          </div>

          <div class="docs-head">
            <h6 class="mb-2">VII. Documentos Anexados</h6>
            <a class="btn btn-outline-primary btn-sm" id="btnSocio" target="_blank" href="#"><i class="bi bi-file-text"></i> Ver Folha Socioeconômica</a>
          </div>
          <div id="md-docs" class="mb-3"></div>

          <h6 class="mb-2">VIII. Familiares</h6>
          <div class="scroll-x mb-3">
            <table class="table table-sm table-striped align-middle text-nowrap">
              <thead class="table-light">
                <tr>
                  <th>Nome</th>
                  <th>Nascimento</th>
                  <th>Parentesco</th>
                  <th>Escolaridade</th>
                  <th>Observação</th>
                </tr>
              </thead>
              <tbody id="md-familiares"></tbody>
            </table>
          </div>

          <h6 class="mb-2">IX. Cônjuge</h6>
          <div class="kv-grid mb-1">
            <div class="kv">
              <div class="kv-label">Nome</div>
              <div class="kv-value" id="md-conj-nome">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">NIS</div>
              <div class="kv-value" id="md-conj-nis">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">CPF</div>
              <div class="kv-value" id="md-conj-cpf">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">RG</div>
              <div class="kv-value" id="md-conj-rg">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Nascimento</div>
              <div class="kv-value" id="md-conj-nasc">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Gênero</div>
              <div class="kv-value" id="md-conj-gen">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Nacionalidade</div>
              <div class="kv-value" id="md-conj-nac">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Naturalidade</div>
              <div class="kv-value" id="md-conj-nat">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Trabalho</div>
              <div class="kv-value" id="md-conj-trab">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Renda</div>
              <div class="kv-value" id="md-conj-renda">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">PCD</div>
              <div class="kv-value" id="md-conj-pcd">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">BPC</div>
              <div class="kv-value" id="md-conj-bpc">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">BPC Valor</div>
              <div class="kv-value" id="md-conj-bpc-valor">—</div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
  <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
  <script src="../dist/assets/js/main.js"></script>
  <script>
    (() => {
      'use strict';

      const cy = document.getElementById('current-year');
      if (cy) cy.textContent = String(new Date().getFullYear());

      // ===== Busca + Paginação (CLIENT-SIDE) =====
      const tbody = document.getElementById('tbody');
      const allRows = Array.from(tbody?.querySelectorAll('tr') || []);
      const inpSearch = document.getElementById('qLive'); // <-- usa o input certo
      const btnClear = document.getElementById('btnClear');
      const selPerPage = document.getElementById('selPerPage');
      const btnPrev = document.getElementById('btnPrev');
      const btnNext = document.getElementById('btnNext');
      const lblPagina = document.getElementById('lblPagina');

      selPerPage.value = '10';
      let page = 1;
      let perPage = parseInt(selPerPage.value, 10);
      let filtered = allRows.slice();
      let tDeb = null;

      function renderPage() {
        const total = filtered.length;
        const pages = Math.max(1, Math.ceil(total / perPage));
        if (page > pages) page = pages;
        const start = (page - 1) * perPage;
        const end = start + perPage;
        allRows.forEach(r => r.style.display = 'none');
        filtered.slice(start, end).forEach(r => r.style.display = '');
        lblPagina.textContent = `Página ${page} de ${pages}`;
        btnPrev.disabled = page <= 1;
        btnNext.disabled = page >= pages;
      }

      function doFilter() {
        const q = (inpSearch?.value || '').trim().toLowerCase();
        const qDigits = q.replace(/\D+/g, '');
        filtered = allRows.filter(tr => {
          if (!q) return true;
          const nome = tr.dataset.nome || '';
          const cpf = tr.dataset.cpf || '';
          const tel = tr.dataset.telefone || '';
          const end = tr.dataset.endereco || '';
          const num = (tr.dataset.numero || '').toLowerCase();
          const resp = (tr.dataset.responsavel || '').toLowerCase();

          // texto livre em nome/endereço/número/responsável
          const hitText = nome.includes(q) || end.includes(q) || (num && num.includes(q)) || resp.includes(q);

          // busca por números (cpf/telefone)
          const hitDigits = qDigits && (cpf.startsWith(qDigits) || tel.includes(qDigits));

          return hitText || !!hitDigits;
        });
        page = 1;
        renderPage();
      }

      function applyFilter() {
        clearTimeout(tDeb);
        tDeb = setTimeout(doFilter, 120);
      }

      inpSearch?.addEventListener('input', applyFilter);
      inpSearch?.addEventListener('keydown', e => {
        if (e.key === 'Enter') e.preventDefault();
      });
      btnClear?.addEventListener('click', () => {
        inpSearch.value = '';
        doFilter();
        inpSearch.focus();
      });
      selPerPage?.addEventListener('change', () => {
        perPage = parseInt(selPerPage.value, 10) || 10;
        page = 1;
        renderPage();
      });
      btnPrev?.addEventListener('click', () => {
        if (page > 1) {
          page--;
          renderPage();
        }
      });
      btnNext?.addEventListener('click', () => {
        page++;
        renderPage();
      });

      doFilter(); // primeira renderização

      // ===== Modal (mini API) =====
      const fmtDate = (s) => {
        if (!s) return '—';
        const p = (s + '').split(' ')[0]?.split('-') || [];
        return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : '—';
      };
      const money = (v) => (v == null || v === '') ? '—' : Number(v).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
      });
      const maskCPF = (cpf) => {
        const d = String(cpf || '').replace(/\D+/g, '');
        return d.length !== 11 ? (cpf || '—') : d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
      };
      const maskPhone = (tel) => {
        const d = String(tel || '').replace(/\D+/g, '');
        if (d.length === 11) return `(${d.slice(0,2)}) ${d.slice(2,7)}-${d.slice(7)}`;
        if (d.length === 10) return `(${d.slice(0,2)}) ${d.slice(2,6)}-${d.slice(6)}`;
        return tel || '—';
      };
      const setText = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = (val ?? '') !== '' ? String(val) : '—';
      };

      document.addEventListener('click', async (ev) => {
        const btn = ev.target.closest?.('.btnVer');
        if (!btn) return;
        const tr = btn.closest('tr');
        const id = tr?.dataset?.id;
        if (!id) return;

        const url = new URL(window.location.pathname, window.location.origin);
        url.searchParams.set('view', '1');
        url.searchParams.set('id', String(id));

        let j;
        try {
          const res = await fetch(url.toString(), {
            headers: {
              'Accept': 'application/json'
            }
          });
          j = await res.json();
        } catch {
          j = {
            ok: false
          };
        }

        if (!j.ok) {
          alert(j.error || 'Não foi possível carregar os dados.');
          return;
        }

        const d = j.data || {};
        const documentos = j.documentos || [];
        const familiares = j.familiares || [];

        const foto = (d.foto_path && String(d.foto_path).trim() !== '') ?
          (
            String(d.foto_path).trim().startsWith('../dist/') ?
            String(d.foto_path).trim() :
            '../dist/' + String(d.foto_path).trim().replace(/^(\.\/|\/)+/, '')
          ) :
          '../dist/assets/images/placeholders/user-placeholder.svg';

        const img = document.getElementById('md-foto');
        if (img) img.src = foto;

        setText('md-nome', d.nome || '—');
        setText('md-genero', d.genero || '—');
        setText('md-ec', d.estado_civil || '—');
        setText('md-nasc', fmtDate(d.data_nascimento));
        setText('md-criado', d.created_at || '—');

        setText('md-cpf', maskCPF(d.cpf));
        setText('md-nis', d.nis || '—');
        setText('md-rg', d.rg || '—');
        setText('md-rg-emissao', fmtDate(d.rg_emissao));
        setText('md-rg-uf', d.rg_uf || '—');
        setText('md-tel', maskPhone(d.telefone));
        setText('md-genero-2', d.genero || '—');
        setText('md-ec-2', d.estado_civil || '—');
        setText('md-nasc-2', fmtDate(d.data_nascimento));
        setText('md-nac', d.nacionalidade || '—');
        setText('md-nat', d.naturalidade || '—');
        setText('md-tempo', `${d.tempo_anos||0} ano(s)${d.tempo_meses?`, ${d.tempo_meses} mês(es)`:''}`);
        setText('md-responsavel', d.responsavel || '—');

        setText('md-endereco', d.endereco || '—');
        setText('md-numero', d.numero || '—');
        setText('md-complemento', d.complemento || '—');
        setText('md-bairro', j.bairro_nome || (d.bairro_id ? `#${d.bairro_id}` : '—'));
        setText('md-referencia', d.referencia || '—');

        setText('md-grupo', d.grupo_tradicional || '—');
        setText('md-grupo-outros', d.grupo_outros || '—');
        setText('md-pcd', d.pcd || '—');
        setText('md-pcd-tipo', d.pcd_tipo || '—');
        setText('md-bpc', d.bpc || '—');
        setText('md-bpc-valor', money(d.bpc_valor));
        setText('md-pbf', d.pbf || '—');
        setText('md-pbf-valor', money(d.pbf_valor));
        setText('md-ben-mun', d.beneficio_municipal || '—');
        setText('md-ben-mun-valor', money(d.beneficio_municipal_valor));
        setText('md-ben-est', d.beneficio_estadual || '—');
        setText('md-ben-est-valor', money(d.beneficio_estadual_valor));
        setText('md-faixa', d.renda_mensal_faixa || '—');
        setText('md-faixa-outros', d.renda_mensal_outros || '—');
        setText('md-trabalho', d.trabalho || '—');
        setText('md-renda-ind', money(d.renda_individual));
        setText('md-renda-fam', money(d.renda_familiar));
        setText('md-rend-tot', money(d.total_rendimentos));
        setText('md-tipificacao', d.tipificacao || '—');

        setText('md-tot-mor', d.total_moradores ?? '—');
        setText('md-tot-fam', d.total_familias ?? '—');
        setText('md-pcd-res', d.pcd_residencia || '—');
        setText('md-tot-pcd', d.total_pcd ?? '—');

        setText('md-sit', d.situacao_imovel || '—');
        setText('md-sit-valor', money(d.situacao_imovel_valor));
        setText('md-tipo-moradia', d.tipo_moradia || '—');
        setText('md-abast', d.abastecimento || '—');
        setText('md-ilum', d.iluminacao || '—');
        setText('md-esgoto', d.esgoto || '—');
        setText('md-lixo', d.lixo || '—');
        setText('md-entorno', d.entorno || '—');

        setText('md-resumo', d.resumo_caso || '—');

        // Documentos
        const docsWrap = document.getElementById('md-docs');
        docsWrap.innerHTML = '';

        if (documentos.length) {
          documentos.forEach(doc => {
            const row = document.createElement('div');
            row.className = 'doc-row';

            const left = document.createElement('div');
            left.className = 'doc-meta';

            const sizeMb = doc.size_bytes ?
              (Number(doc.size_bytes) / 1024 / 1024).toFixed(2) + ' MB' :
              '';

            const created = fmtDate(doc.created_at);

            left.innerHTML = `
              <i class="bi bi-paperclip fs-5"></i>
              <div>
                <div class="doc-name">${doc.original_name || 'Documento'}</div>
                <div class="doc-sub">${[sizeMb, created].filter(Boolean).join(' • ')}</div>
              </div>
            `;

            // 👉 garante ../dist/ antes do caminho
            const arquivoPath = doc.arquivo_path ?
              `../dist/${String(doc.arquivo_path).replace(/^(\.\.\/dist\/)/, '')}` :
              '#';

            const btn = document.createElement('a');
            btn.className = 'btn btn-sm btn-outline-primary';
            btn.target = '_blank';
            btn.href = arquivoPath;
            btn.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Abrir';

            row.appendChild(left);
            row.appendChild(btn);
            docsWrap.appendChild(row);
          });
        } else {
          docsWrap.innerHTML = '<span class="text-muted">Nenhum documento anexado.</span>';
        }


        // Familiares
        const tb = document.getElementById('md-familiares');
        tb.innerHTML = '';
        if (familiares.length) {
          familiares.forEach(f => {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td>${f.nome||'—'}</td>
                        <td>${fmtDate(f.data_nascimento)}</td>
                        <td>${f.parentesco||'—'}</td>
                        <td>${f.escolaridade||'—'}</td>
                        <td>${f.obs||''}</td>`;
            tb.appendChild(tr);
          });
        } else {
          tb.innerHTML = `<tr><td colspan="5" class="text-center text-muted">Sem familiares cadastrados.</td></tr>`;
        }

        const btnSocio = document.getElementById('btnSocio');
        if (btnSocio) btnSocio.href = 'folhaSocio.php?id=' + encodeURIComponent(d.id ?? '');

        new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
      });
    })();
  </script>
</body>

</html>