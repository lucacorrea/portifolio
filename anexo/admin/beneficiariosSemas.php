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
  return preg_replace('/\D+/', '', (string)$s) ?? '';
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
   MINI API: ?view=1&id=123  -> dados + ENTREGAS (por CPF) + docs
   (somente ENTREGAS com entregue = "Sim")
   =========================================================== */
if (($_GET['view'] ?? '') === '1') {
  header('Content-Type: application/json; charset=UTF-8');
  $id = (int)($_GET['id'] ?? 0);
  if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'ID inválido']);
    exit;
  }

  try {
    $st = $pdo->prepare("SELECT s.*, COALESCE(b.nome,'') AS bairro_nome
                         FROM solicitantes s
                         LEFT JOIN bairros b ON b.id = s.bairro_id
                         WHERE s.id = :id LIMIT 1");
    $st->execute([':id' => $id]);
    $sol = $st->fetch(PDO::FETCH_ASSOC);
    if (!$sol) {
      echo json_encode(['ok' => false, 'error' => 'Registro não encontrado']);
      exit;
    }

    // CPF normalizado
    $cpfDigits = only_digits((string)($sol['cpf'] ?? ''));
    if (strlen($cpfDigits) !== 11) $cpfDigits = '';

    // ✅ Pega TODAS as entregas do CPF (e também do pessoa_id como garantia)
    // ✅ Somente as que tiveram entregue = "Sim"
    $ent = $pdo->prepare("
      SELECT ae.*, at.nome AS ajuda_nome
        FROM ajudas_entregas ae
        LEFT JOIN ajudas_tipos at ON at.id = ae.ajuda_tipo_id
       WHERE (
              (ae.pessoa_id = :pid)
              OR (:cpf IS NOT NULL AND :cpf <> '' AND ae.pessoa_cpf = :cpf)
            )
         AND UPPER(ae.entregue) = 'SIM'
       ORDER BY ae.data_entrega DESC, ae.hora_entrega DESC, ae.id DESC
       LIMIT 200
    ");
    $ent->execute([
      ':pid' => (int)$sol['id'],
      ':cpf' => ($cpfDigits !== '' ? $cpfDigits : null),
    ]);
    $entregas = $ent->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // última = primeira do array
    $ultima = $entregas[0] ?? null;

    $doc = $pdo->prepare("SELECT id, original_name, arquivo_path, size_bytes, created_at
                          FROM solicitante_documentos
                          WHERE solicitante_id = :sid
                          ORDER BY created_at DESC, id DESC");
    $doc->execute([':sid' => (int)$sol['id']]);
    $docs = $doc->fetchAll(PDO::FETCH_ASSOC) ?: [];

    echo json_encode([
      'ok' => true,
      'solicitante'    => $sol,
      'bairro_nome'    => $sol['bairro_nome'] ?? '',
      'ultima_entrega' => $ultima,
      'entregas'       => $entregas,
      'documentos'     => $docs
    ], JSON_UNESCAPED_UNICODE);
    exit;
  } catch (Throwable $e) {
    echo json_encode(['ok' => false, 'error' => 'Falha ao consultar'], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

/* ===========================================================
   LISTA PRINCIPAL:
   ✅ AGORA TRAZ SOMENTE QUEM EXISTE NA ajudas_entregas
   ✅ E SOMENTE ENTREGAS com entregue = "Sim"
   (agrupa por pessoa_id e pega a ÚLTIMA entrega de cada pessoa)
   =========================================================== */
$qPrefill = trim((string)($_GET['q'] ?? ''));

try {
  $rows = $pdo->query("
    SELECT
      s.id,
      s.nome,
      s.cpf,
      s.numero,
      s.endereco,
      s.telefone,
      le.responsavel AS responsavel_ultima,
      le.valor_aplicado AS valor_ultima
    FROM solicitantes s
    INNER JOIN (
      SELECT
        ae.pessoa_id,
        ae.responsavel,
        ae.valor_aplicado
      FROM ajudas_entregas ae
      INNER JOIN (
        SELECT
          pessoa_id,
          MAX(CONCAT(data_entrega,' ',IFNULL(hora_entrega,'00:00:00'), '#', LPAD(id, 20, '0'))) AS mxkey
        FROM ajudas_entregas
        WHERE UPPER(entregue) = 'SIM'
        GROUP BY pessoa_id
      ) mx ON mx.pessoa_id = ae.pessoa_id
          AND mx.mxkey = CONCAT(ae.data_entrega,' ',IFNULL(ae.hora_entrega,'00:00:00'), '#', LPAD(ae.id, 20, '0'))
      WHERE UPPER(ae.entregue) = 'SIM'
    ) le ON le.pessoa_id = s.id
    ORDER BY s.nome ASC, s.id ASC
  ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  echo "<div class='alert alert-danger'>Erro ao consultar beneficiários (ajudas_entregas).</div>";
  exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="UTF-8" />
  <title>Beneficiários – ANEXO</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="../dist/assets/css/bootstrap.css" />
  <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css" />
  <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css" />
  <link rel="stylesheet" href="../dist/assets/css/app.css" />
  <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg" />

  <style>
    .table-actions .btn {
      margin: 0 2px;
    }

    .td-endereco {
      max-width: 250px;
    }

    .td-endereco .cell-truncate,
    .td-responsavel .cell-truncate {
      display: block;
      max-width: 100%;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .td-responsavel {
      max-width: 200px;
    }

    .table-actions {
      min-width: 160px;
      width: 160px;
      white-space: nowrap;
    }

    @media (max-width:991.98px) {
      .td-endereco {
        max-width: 160px;
      }

      .td-responsavel {
        max-width: 140px;
      }

      .table-actions {
        min-width: 130px;
        width: 130px;
      }
    }

    .profile-wrap {
      display: flex;
      gap: 1rem;
      align-items: center;
      flex-wrap: wrap;
      padding: .25rem 0 .75rem;
      border-bottom: 1px solid rgba(0, 0, 0, .08);
      margin-bottom: 1rem;
    }

    .modal-photo {
      width: 110px;
      height: 110px;
      object-fit: cover;
      border-radius: 12px;
      border: 1px solid rgba(0, 0, 0, .08);
      background: #f8f9fa;
    }

    .profile-info h5.profile-name {
      margin: 0 0 .25rem;
    }

    .profile-subline {
      display: flex;
      flex-wrap: wrap;
      gap: .35rem;
    }

    .pill {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .25rem .6rem;
      border-radius: 999px;
      background: #f1f3f5;
      font-size: .85rem;
    }

    /* mobile topo */
    .profile-top {
      text-align: center;
      padding: .35rem 0 1rem;
      border-bottom: 1px solid rgba(0, 0, 0, .08);
      margin-bottom: 1rem;
    }

    .avatar-box {
      width: 92px;
      height: 92px;
      border-radius: 14px;
      border: 1px solid rgba(0, 0, 0, .10);
      background: #f8f9fa;
      margin: 0 auto;
      overflow: hidden;
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .avatar-box img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      display: block;
    }

    .avatar-label {
      position: absolute;
      top: 6px;
      left: 8px;
      right: 8px;
      font-size: .75rem;
      color: #6c757d;
      text-align: center;
      background: rgba(255, 255, 255, .80);
      border-radius: 999px;
      padding: 2px 6px;
      backdrop-filter: blur(2px);
      pointer-events: none;
    }

    .profile-name-top {
      margin-top: .65rem;
      margin-bottom: .45rem;
      font-weight: 800;
      font-size: 1.25rem;
      color: #25396f;
      line-height: 1.15;
    }

    .profile-badges {
      display: flex;
      justify-content: center;
      align-items: center;
      gap: .4rem;
      flex-wrap: wrap;
      margin-bottom: .25rem;
    }

    .pill2 {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .25rem .6rem;
      border-radius: 999px;
      background: #f1f3f5;
      font-size: .85rem;
      color: #6c757d;
      border: 1px solid rgba(0, 0, 0, .04);
    }

    .profile-cadastro {
      font-size: .9rem;
      color: #6c757d;
    }

    /* cards */
    .kv-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: .6rem .8rem;
    }

    .kv {
      background: #fff;
      border: 1px solid rgba(0, 0, 0, .06);
      border-radius: .5rem;
      padding: .6rem .7rem;
    }

    .kv-label {
      font-size: .8rem;
      color: #6c757d;
    }

    .kv-value {
      font-weight: 600;
    }

    .docs-list .doc-item {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: .4rem .6rem;
      border: 1px solid rgba(0, 0, 0, .06);
      border-radius: .5rem;
      margin-bottom: .4rem;
    }

    .docs-list .doc-name {
      font-weight: 600;
    }

    .docs-list .doc-meta {
      font-size: .85rem;
      color: #6c757d;
    }

    .tfoot-pager {
      padding: 1rem 1.25rem;
    }

    /* Foto última entrega */
    .entrega-photo-wrap {
      display: none;
      margin-top: .75rem;
      text-align: center;
    }

    .entrega-photo {
      width: min(360px, 100%);
      max-height: 240px;
      object-fit: cover;
      border-radius: 12px;
      border: 1px solid rgba(0, 0, 0, .10);
      background: #f8f9fa;
    }

    .entrega-photo-meta {
      margin-top: .45rem;
      font-size: .9rem;
      color: #6c757d;
      display: flex;
      justify-content: center;
      gap: .75rem;
      flex-wrap: wrap;
    }

    /* Histórico */
    .entregas-list .entrega-item {
      border: 1px solid rgba(0, 0, 0, .06);
      border-radius: .65rem;
      padding: .6rem .7rem;
      margin-bottom: .5rem;
      background: #fff;
      display: flex;
      gap: .75rem;
      align-items: flex-start;
      flex-wrap: wrap;
    }

    .entrega-thumb {
      width: 120px;
      height: 90px;
      border-radius: 12px;
      object-fit: cover;
      border: 1px solid rgba(0, 0, 0, .10);
      background: #f8f9fa;
    }

    .entrega-main {
      flex: 1;
      min-width: 240px;
    }

    .entrega-title {
      font-weight: 800;
      color: #25396f;
      line-height: 1.15;
      margin-bottom: .15rem;
    }

    .entrega-meta {
      display: flex;
      flex-wrap: wrap;
      gap: .35rem .5rem;
      margin-bottom: .35rem;
      color: #6c757d;
      font-size: .9rem;
    }

    .badge-lite {
      display: inline-flex;
      align-items: center;
      gap: .35rem;
      padding: .2rem .55rem;
      border-radius: 999px;
      background: #f1f3f5;
      border: 1px solid rgba(0, 0, 0, .04);
      font-size: .85rem;
      color: #6c757d;
    }

    .entrega-extra {
      font-size: .92rem;
      color: #495057;
    }

    .entrega-actions {
      display: flex;
      gap: .35rem;
      align-items: center;
      margin-left: auto;
    }

    /* Paginação */
    .pagination .page-link {
      border-radius: 0.375rem;
      margin: 0 0.125rem;
      font-weight: 500;
    }

    .pagination .page-link:hover {
      background-color: #e9ecef;
    }

    .pagination .page-item.disabled .page-link {
      cursor: not-allowed;
      opacity: 0.5;
    }

    .pagination .page-item.active .page-link {
      background-color: #0d6efd;
      border-color: #0d6efd;
      color: #fff;
    }

    .pagination .page-link.page-num {
      min-width: 2.2rem;
      text-align: center;
    }

    #lblPagina {
      font-size: 0.875rem;
      font-weight: 600;
      letter-spacing: 0.025em;
    }

    #selPerPage {
      cursor: pointer;
      font-weight: 500;
    }

    #selPerPage:focus {
      border-color: #0d6efd;
      box-shadow: 0 0 0 0.25rem rgba(13, 110, 253, 0.25);
    }

    @media (max-width: 991.98px) {
      .table-actions .btn {
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
      }

      .table-actions .btn i {
        font-size: 0.875rem;
      }
    }

    @media (max-width: 767.98px) {
      .btn-group {
        display: flex;
        flex-direction: column;
        width: 100%;
      }

      .btn-group .btn {
        border-radius: 0.375rem !important;
        margin-bottom: 0.25rem;
      }
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
                <li class="submenu-item"><a href="beneficiariosMunicipal.php">Municipal</a></li>
                <li class="submenu-item active"><a href="#">ANEXO</a></li>
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
              <h3>Beneficiários – Anexo</h3>
              <p class="text-subtitle text-muted">Visualize os solicitantes aos quais o benefício foi atribuído.</p>
            </div>
            <div class="col-12 col-md-6 order-md-2 order-first">
              <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="#">Beneficiarios</a></li>
                  <li class="breadcrumb-item active" aria-current="page">ANEXO</li>
                </ol>
              </nav>
            </div>
          </div>
        </div>

        <section class="section mb-4">
          <div class="card">
            <div class="card-header d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center">
              <span>Lista de Beneficiários (via ajudas_entregas)</span>
              <div class="d-flex gap-2 align-items-center">
                <input id="qLive" name="q" value="<?= e($qPrefill) ?>" class="form-control form-control-sm" placeholder="Buscar por nome/CPF/telefone/endereço/número/responsável..." autocomplete="off" />
                <button class="btn btn-sm btn-outline-secondary" type="button" id="btnClear"><i class="bi bi-x-circle"></i></button>
              </div>
            </div>

            <div class="card-body p-0">
              <div class="table-responsive">
                <table class="table table-striped table-hover align-middle w-100 text-nowrap mb-0" id="tbl">
                  <thead class="table-light">
                    <tr>
                      <th>CPF</th>
                      <th>Nome</th>
                      <th class="text-end">Valor (última entrega)</th>
                      <th>Número</th>
                      <th>Endereço</th>
                      <th>Telefone</th>
                      <th>Responsável (última)</th>
                      <th class="text-center text-nowrap">Ações</th>
                    </tr>
                  </thead>
                  <tbody id="tbody">
                    <?php if (!$rows): ?>
                      <tr>
                        <td colspan="8" class="text-center text-muted py-4">
                          <i class="bi bi-inbox"></i> Nenhum beneficiário encontrado (Entregue = Sim).
                        </td>
                      </tr>
                      <?php else: foreach ($rows as $r): ?>
                        <?php
                        $cpfDigits = only_digits($r['cpf']);
                        $telDigits = only_digits($r['telefone']);
                        $rowNome   = mb_strtolower((string)($r['nome'] ?? ''), 'UTF-8');
                        $rowEnd    = mb_strtolower((string)($r['endereco'] ?? ''), 'UTF-8');
                        $rowNum    = (string)($r['numero'] ?? '');
                        $respUlt   = (string)($r['responsavel_ultima'] ?? '');
                        $respKey   = mb_strtolower($respUlt, 'UTF-8');
                        ?>
                        <tr
                          data-id="<?= (int)$r['id'] ?>"
                          data-nome="<?= e($rowNome) ?>"
                          data-cpf="<?= e($cpfDigits) ?>"
                          data-telefone="<?= e($telDigits) ?>"
                          data-endereco="<?= e($rowEnd) ?>"
                          data-numero="<?= e(mb_strtolower($rowNum, 'UTF-8')) ?>"
                          data-responsavel="<?= e($respKey) ?>">
                          <td class="nowrap"><?= e(formatCpf($r['cpf'])) ?></td>
                          <td><?= e((string)$r['nome']) ?></td>
                          <td class="text-end"><?= e(moneyBR($r['valor_ultima'] ?? null)) ?></td>
                          <td><?= e((string)$r['numero']) ?></td>
                          <td class="td-endereco">
                            <div class="cell-truncate" title="<?= e((string)$r['endereco']) ?>"><?= e((string)$r['endereco']) ?></div>
                          </td>
                          <td class="nowrap"><?= e(formatPhone($r['telefone'])) ?></td>
                          <td class="td-responsavel">
                            <div class="cell-truncate" title="<?= e($respUlt !== '' ? $respUlt : '—') ?>"><?= e($respUlt !== '' ? $respUlt : '—') ?></div>
                          </td>
                          <td class="text-center table-actions">
                            <div class="btn-group btn-group-sm" role="group">
                              <a href="editar.php?cpf=<?= e(only_digits($r['cpf'])) ?>"
                                class="btn btn-outline-primary"
                                title="Editar beneficiário">
                                <i class="bi bi-pencil"></i> Editar
                              </a>
                              <button type="button"
                                class="btn btn-outline-secondary btnVer"
                                title="Ver detalhes">
                                <i class="bi bi-eye"></i> Ver
                              </button>
                            </div>
                          </td>
                        </tr>
                    <?php endforeach;
                    endif; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Paginação client-side -->
            <div class="card-footer tfoot-pager">
              <div class="row align-items-center gy-2">

                <!-- Botões e páginas -->
                <div class="col-md-6">
                  <nav aria-label="Paginação">
                    <ul class="pagination pagination-sm mb-0" id="paginacao">
                      <li class="page-item">
                        <button class="page-link" id="btnPrev">‹ Anterior</button>
                      </li>
                      <!-- Números de página gerados via JS -->
                      <li class="page-item">
                        <button class="page-link" id="btnNext">Próxima ›</button>
                      </li>
                    </ul>
                  </nav>
                </div>

                <!-- Status -->
                <div class="col-md-3 text-md-center">
                  <span class="badge bg-light text-dark" id="lblPagina">Página 1 de 1</span>
                </div>

                <!-- Registros por página -->
                <div class="col-md-3 ms-auto">
                  <div class="d-flex align-items-center justify-content-end gap-2">
                    <label for="selPerPage" class="form-label m-0 fw-semibold">Registros:</label>
                    <select id="selPerPage" class="form-select form-select-sm w-auto">
                      <option value="10">10</option>
                      <option value="20">20</option>
                      <option value="50" selected>50</option>
                      <option value="100">100</option>
                    </select>
                  </div>
                </div>

              </div>
            </div>
            <!-- /Paginação -->

          </div><!-- /.card -->
        </section>

      </div><!-- /.page-heading -->

      <footer>
        <div class="footer clearfix mb-0 text-muted">
          <div class="float-start text-black">
            <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de Coari-AM.</b></p>
          </div>
          <div class="float-end text-black">
            <p>Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p>
          </div>
        </div>
      </footer>
    </div><!-- /#main -->
  </div><!-- /#app -->

  <!-- ===== MODAL DE DETALHES ===== -->
  <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalhes do Beneficiário (ANEXO)</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">

          <!-- topo mobile -->
          <div class="profile-top d-block d-lg-none">
            <div class="avatar-box">
              <span class="avatar-label">Foto</span>
              <img data-bind-src="foto" src="../dist/assets/images/placeholder-user.jpg" alt="Foto">
            </div>
            <div class="profile-name-top" data-bind="nome">—</div>
            <div class="profile-badges">
              <span class="pill2"><i class="bi bi-person"></i> <span data-bind="genero">—</span></span>
              <span class="pill2"><i class="bi bi-heart"></i> <span data-bind="estado_civil">—</span></span>
              <span class="pill2"><i class="bi bi-calendar-event"></i> <span data-bind="nascimento">—</span></span>
            </div>
            <div class="profile-cadastro">
              Cadastro: <span data-bind="criado">—</span>
            </div>
          </div>

          <!-- topo desktop -->
          <div class="profile-wrap d-none d-lg-flex">
            <img data-bind-src="foto" class="modal-photo" src="../dist/assets/images/placeholder-user.jpg" alt="Foto">
            <div class="profile-info">
              <h5 class="profile-name" data-bind="nome">—</h5>
              <div class="profile-subline">
                <span class="pill"><i class="bi bi-telephone"></i> <span data-bind="telefone">—</span></span>
                <span class="pill"><i class="bi bi-card-list"></i> <span data-bind="nascimento">—</span> • <span data-bind="estado_civil">—</span></span>
              </div>
              <div class="text-muted mt-1" style="font-size:.875rem;">Cadastro: <span data-bind="criado">—</span></div>
            </div>
          </div>

          <h6 class="mb-2">I. Identificação</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">CPF</div>
              <div class="kv-value" id="md-cpf">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Telefone</div>
              <div class="kv-value" id="md-tel">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Endereço</div>
              <div class="kv-value" id="md-endereco">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Bairro</div>
              <div class="kv-value" id="md-bairro">—</div>
            </div>
          </div>

          <h6 class="mb-2">II. Última Entrega (Entregue = Sim)</h6>
          <div class="kv-grid mb-3">
            <div class="kv">
              <div class="kv-label">Última Ajuda</div>
              <div class="kv-value" id="md-ultima-ajuda">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Data / Hora / Quantidade</div>
              <div class="kv-value" id="md-ultima-detalhes">—</div>
            </div>
            <div class="kv">
              <div class="kv-label">Responsável (Servidor)</div>
              <div class="kv-value" id="md-responsavel">—</div>
            </div>
          </div>

          <h6 class="mb-2">III. Documentos</h6>
          <div id="md-docs" class="docs-list"></div>

          <h6 class="mt-2 mb-2">Foto da Última Entrega</h6>
          <div class="entrega-photo-wrap" id="entregaPhotoWrap">
            <img id="md-foto-entrega" class="entrega-photo" src="" alt="Foto da entrega">
            <div class="entrega-photo-meta">
              <span><i class="bi bi-calendar-event"></i> <span id="md-entrega-data">—</span></span>
              <span><i class="bi bi-clock"></i> <span id="md-entrega-hora">—</span></span>
            </div>
          </div>

          <h6 class="mt-3 mb-2">IV. Histórico de Entregas (todas do CPF)</h6>
          <div id="md-entregas" class="entregas-list"></div>

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

      document.getElementById('current-year').textContent = String(new Date().getFullYear());

      // ===== Busca + paginação CLIENT-SIDE =====
      const tbody = document.getElementById('tbody');
      const allRows = Array.from(tbody?.querySelectorAll('tr') || []);
      const inpSearch = document.getElementById('qLive');
      const btnClear = document.getElementById('btnClear');
      const selPerPage = document.getElementById('selPerPage');
      const btnPrev = document.getElementById('btnPrev');
      const btnNext = document.getElementById('btnNext');
      const lblPagina = document.getElementById('lblPagina');

      selPerPage.value = '10';
      let page = 1;
      let perPage = parseInt(selPerPage.value, 10);
      let filtered = allRows.slice();

      const paginacao = document.getElementById('paginacao');

      function renderPageNumbers(pages) {
        // Remove old page number buttons
        paginacao.querySelectorAll('.page-num-item').forEach(el => el.remove());

        const btnNextLi = btnNext.closest('.page-item');
        const maxVisible = 7;
        let start = 1,
          end = pages;

        if (pages > maxVisible) {
          const half = Math.floor(maxVisible / 2);
          start = Math.max(1, page - half);
          end = start + maxVisible - 1;
          if (end > pages) {
            end = pages;
            start = Math.max(1, end - maxVisible + 1);
          }
        }

        // Ellipsis / first page
        if (start > 1) {
          const li = document.createElement('li');
          li.className = 'page-item page-num-item';
          const btn = document.createElement('button');
          btn.className = 'page-link page-num';
          btn.textContent = '1';
          btn.addEventListener('click', () => {
            page = 1;
            renderPage();
          });
          li.appendChild(btn);
          paginacao.insertBefore(li, btnNextLi);

          if (start > 2) {
            const eLi = document.createElement('li');
            eLi.className = 'page-item disabled page-num-item';
            eLi.innerHTML = '<span class="page-link">…</span>';
            paginacao.insertBefore(eLi, btnNextLi);
          }
        }

        for (let i = start; i <= end; i++) {
          const li = document.createElement('li');
          li.className = 'page-item page-num-item' + (i === page ? ' active' : '');
          const btn = document.createElement('button');
          btn.className = 'page-link page-num';
          btn.textContent = String(i);
          const pg = i;
          btn.addEventListener('click', () => {
            page = pg;
            renderPage();
          });
          li.appendChild(btn);
          paginacao.insertBefore(li, btnNextLi);
        }

        // Ellipsis / last page
        if (end < pages) {
          if (end < pages - 1) {
            const eLi = document.createElement('li');
            eLi.className = 'page-item disabled page-num-item';
            eLi.innerHTML = '<span class="page-link">…</span>';
            paginacao.insertBefore(eLi, btnNextLi);
          }

          const li = document.createElement('li');
          li.className = 'page-item page-num-item';
          const btn = document.createElement('button');
          btn.className = 'page-link page-num';
          btn.textContent = String(pages);
          btn.addEventListener('click', () => {
            page = pages;
            renderPage();
          });
          li.appendChild(btn);
          paginacao.insertBefore(li, btnNextLi);
        }
      }

      function renderPage() {
        const total = filtered.length;
        const pages = Math.max(1, Math.ceil(total / perPage));
        if (page > pages) page = pages;
        if (page < 1) page = 1;

        const start = (page - 1) * perPage;
        const end = start + perPage;

        allRows.forEach(r => r.style.display = 'none');
        filtered.slice(start, end).forEach(r => r.style.display = '');

        lblPagina.textContent = `Página ${page} de ${pages}`;
        btnPrev.disabled = page <= 1;
        btnNext.disabled = page >= pages;

        renderPageNumbers(pages);
      }

      function applyFilter() {
        const q = (inpSearch.value || '').trim().toLowerCase();
        const qDigits = q.replace(/\D+/g, '');
        filtered = allRows.filter(tr => {
          if (!q) return true;

          const nome = tr.dataset.nome || '';
          const cpf = tr.dataset.cpf || '';
          const tel = tr.dataset.telefone || '';
          const end = tr.dataset.endereco || '';
          const num = tr.dataset.numero || '';
          const resp = tr.dataset.responsavel || '';

          const hitText = nome.includes(q) || end.includes(q) || num.includes(q) || resp.includes(q);
          const hitDigits = qDigits && (cpf.startsWith(qDigits) || tel.includes(qDigits));
          return hitText || !!hitDigits;
        });
        page = 1;
        renderPage();
      }

      inpSearch.addEventListener('input', applyFilter);
      btnClear.addEventListener('click', () => {
        inpSearch.value = '';
        applyFilter();
        inpSearch.focus();
      });
      selPerPage.addEventListener('change', () => {
        perPage = parseInt(selPerPage.value, 10) || 10;
        page = 1;
        renderPage();
      });
      btnPrev.addEventListener('click', () => {
        if (page > 1) {
          page--;
          renderPage();
        }
      });
      btnNext.addEventListener('click', () => {
        page++;
        renderPage();
      });

      applyFilter();

      // ===== Helpers Modal =====
      const fmtMoney = (v) => (v == null || v === '') ? '—' :
        Number(v).toLocaleString('pt-BR', {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2
        });

      const fmtDateBR = (s) => {
        if (!s) return '—';
        const m = String(s).match(/^(\d{4})-(\d{2})-(\d{2})/);
        return m ? `${m[3]}/${m[2]}/${m[1]}` : s;
      };

      const fmtDateTimeBR = (s) => {
        if (!s) return '—';
        const str = String(s);
        const m = str.match(/^(\d{4})-(\d{2})-(\d{2})[ T](\d{2}):(\d{2})(?::(\d{2}))?/);
        if (!m) return str;
        const dd = m[3],
          mm = m[2],
          yy = m[1];
        const hh = m[4],
          mi = m[5],
          ss = m[6] ? m[6] : '00';
        return `${dd}/${mm}/${yy} ${hh}:${mi}:${ss}`;
      };

      const fmtTime = (t) => {
        if (!t) return '—';
        const s = String(t);
        const m = s.match(/^(\d{2}):(\d{2})(?::(\d{2}))?/);
        return m ? `${m[1]}:${m[2]}${m[3] ? ':' + m[3] : ''}` : s;
      };

      const maskCPF = (cpf) => {
        const d = String(cpf || '').replace(/\D+/g, '');
        return d.length === 11 ? d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4') : (cpf || '—');
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

      const setBindText = (key, val) => {
        const text = (val ?? '') !== '' ? String(val) : '—';
        document.querySelectorAll(`[data-bind="${key}"]`).forEach(el => el.textContent = text);
      };

      const setBindSrc = (key, src, placeholder) => {
        document.querySelectorAll(`[data-bind-src="${key}"]`).forEach(img => {
          img.src = src || placeholder;
          img.onerror = () => {
            img.src = placeholder;
          };
        });
      };

      const mk = (tag, cls) => {
        const el = document.createElement(tag);
        if (cls) el.className = cls;
        return el;
      };

      const badge = (iconHtml, text) => {
        const b = mk('span', 'badge-lite');
        b.innerHTML = iconHtml;
        b.appendChild(document.createTextNode(' ' + (text ?? '—')));
        return b;
      };

      document.addEventListener('click', async (ev) => {
        const btn = ev.target.closest?.('.btnVer');
        if (!btn) return;

        const tr = btn.closest('tr');
        const id = tr?.dataset?.id;
        if (!id) return;

        const url = new URL(location.pathname, location.origin);
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

        const s = j.solicitante || {};
        const entregas = Array.isArray(j.entregas) ? j.entregas : [];
        const ultima = entregas.length ? entregas[0] : (j.ultima_entrega || null);
        const bairro = j.bairro_nome || '';
        const docs = Array.isArray(j.documentos) ? j.documentos : [];

        // topo
        const placeholder = 'assets/images/placeholder-user.jpg';
        const fotoSolic = (s.foto_path && String(s.foto_path).trim()) ?
          (
            String(s.foto_path).trim().startsWith('../dist/') ?
            String(s.foto_path).trim() :
            '../dist/' + String(s.foto_path).trim().replace(/^(\.\/|\/)+/, '')
          ) :
          '';


        setBindSrc('foto', fotoSolic, placeholder);
        setBindText('nome', s.nome || '—');
        setBindText('genero', s.genero || '—');
        setBindText('estado_civil', s.estado_civil || '—');
        setBindText('nascimento', s.data_nascimento ? fmtDateBR(s.data_nascimento) : '—');
        setBindText('telefone', maskPhone(s.telefone || ''));
        setBindText('criado', s.created_at ? fmtDateTimeBR(s.created_at) : '—');

        // identificação
        setText('md-cpf', maskCPF(s.cpf));
        setText('md-tel', maskPhone(s.telefone || ''));
        setText('md-endereco', [s.endereco || '', s.numero || ''].filter(Boolean).join(', ') || '—');
        setText('md-bairro', bairro || '—');

        // última entrega (resumo + foto)
        const wrapEntrega = document.getElementById('entregaPhotoWrap');
        const imgEntrega = document.getElementById('md-foto-entrega');

        if (ultima) {
          setText('md-ultima-ajuda', ultima.ajuda_nome || '—');

          const dt = fmtDateBR(ultima.data_entrega || '');
          const hr = ultima.hora_entrega ? fmtTime(ultima.hora_entrega) : '—';
          const qtd = (ultima.quantidade != null) ? String(ultima.quantidade) : '—';

          setText('md-ultima-detalhes', `${dt} · ${hr} · Qtde: ${qtd}`);
          setText('md-responsavel', ultima.responsavel || '—');

          const fotoEntrega = (ultima.foto_path && String(ultima.foto_path).trim()) ?
            (
              String(ultima.foto_path).trim().startsWith('../dist/') ?
              String(ultima.foto_path).trim() :
              '../dist/' + String(ultima.foto_path).trim().replace(/^(\.\/|\/)+/, '')
            ) :
            '';

          if (wrapEntrega && imgEntrega && fotoEntrega) {
            imgEntrega.src = fotoEntrega;
            imgEntrega.onerror = () => {
              imgEntrega.src = placeholder;
            };
            wrapEntrega.style.display = 'block';
          } else if (wrapEntrega) {
            if (imgEntrega) imgEntrega.src = '';
            wrapEntrega.style.display = 'none';
          }
          setText('md-entrega-data', dt || '—');
          setText('md-entrega-hora', hr || '—');
        } else {
          setText('md-ultima-ajuda', '—');
          setText('md-ultima-detalhes', '—');
          setText('md-responsavel', '—');
          setText('md-entrega-data', '—');
          setText('md-entrega-hora', '—');
          if (wrapEntrega && imgEntrega) {
            imgEntrega.src = '';
            wrapEntrega.style.display = 'none';
          }
        }

        // documentos
        const boxDocs = document.getElementById('md-docs');
        boxDocs.innerHTML = '';
        if (!docs.length) {
          boxDocs.innerHTML = '<div class="text-muted">Nenhum documento anexado.</div>';
        } else {
          docs.forEach(d => {
            const div = mk('div', 'doc-item');

            const left = mk('div');
            const nm = mk('div', 'doc-name');
            nm.textContent = (d.original_name || 'Documento');
            const meta = mk('div', 'doc-meta');
            const size = d.size_bytes ? ` • ${(Number(d.size_bytes) / 1024 / 1024).toFixed(2)} MB` : '';
            meta.textContent = (d.created_at || '') + size;
            left.appendChild(nm);
            left.appendChild(meta);

            const right = mk('div');
            if (d.arquivo_path) {
              // CORREÇÃO: Adicionar ../dist/ ao caminho do documento da mesma forma que para as fotos
              const docPath = String(d.arquivo_path).trim().startsWith('../dist/') ?
                String(d.arquivo_path).trim() :
                '../dist/' + String(d.arquivo_path).trim().replace(/^(\.\/|\/)+/, '');

              const a = mk('a', 'btn btn-sm btn-outline-primary');
              a.href = docPath;
              a.target = '_blank';
              a.rel = 'noopener';
              a.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Abrir';
              right.appendChild(a);
            }

            div.appendChild(left);
            div.appendChild(right);
            boxDocs.appendChild(div);
          });
        }

        // histórico completo
        const boxEnt = document.getElementById('md-entregas');
        boxEnt.innerHTML = '';

        if (!entregas.length) {
          boxEnt.innerHTML = '<div class="text-muted">Nenhuma entrega registrada para este CPF.</div>';
        } else {
          entregas.forEach(ent => {
            const dt = fmtDateBR(ent.data_entrega || '');
            const hr = ent.hora_entrega ? fmtTime(ent.hora_entrega) : '—';
            const qtd = (ent.quantidade != null) ? String(ent.quantidade) : '—';
            const val = (ent.valor_aplicado != null && ent.valor_aplicado !== '') ? fmtMoney(ent.valor_aplicado) : '—';
            const resp = ent.responsavel || '—';
            const ajuda = ent.ajuda_nome || 'Ajuda';
            const entregue = (ent.entregue != null && String(ent.entregue).trim() !== '') ? String(ent.entregue) : '—';
            const obs = (ent.observacao != null && String(ent.observacao).trim() !== '') ? String(ent.observacao) : '';
            // CORREÇÃO: Adicionar ../dist/ ao caminho da foto da entrega no histórico
            const fotoEntrega = (ent.foto_path && String(ent.foto_path).trim()) ?
              (
                String(ent.foto_path).trim().startsWith('../dist/') ?
                String(ent.foto_path).trim() :
                '../dist/' + String(ent.foto_path).trim().replace(/^(\.\/|\/)+/, '')
              ) : '';

            const item = mk('div', 'entrega-item');

            if (fotoEntrega) {
              const img = mk('img', 'entrega-thumb');
              img.src = fotoEntrega;
              img.alt = 'Foto da entrega';
              img.onerror = () => {
                img.src = placeholder;
              };
              item.appendChild(img);
            }

            const main = mk('div', 'entrega-main');

            const title = mk('div', 'entrega-title');
            title.textContent = ajuda;
            main.appendChild(title);

            const metaRow = mk('div', 'entrega-meta');
            metaRow.appendChild(badge('<i class="bi bi-calendar-event"></i>', dt));
            metaRow.appendChild(badge('<i class="bi bi-clock"></i>', hr));
            metaRow.appendChild(badge('<i class="bi bi-box-seam"></i>', `Qtde: ${qtd}`));
            metaRow.appendChild(badge('<i class="bi bi-cash-coin"></i>', `R$ ${val}`));
            metaRow.appendChild(badge('<i class="bi bi-person-check"></i>', resp));
            metaRow.appendChild(badge('<i class="bi bi-check2-circle"></i>', `Entregue: ${entregue}`));
            main.appendChild(metaRow);

            if (obs) {
              const extra = mk('div', 'entrega-extra');
              extra.textContent = `Obs: ${obs}`;
              main.appendChild(extra);
            }

            item.appendChild(main);

            const actions = mk('div', 'entrega-actions');
            if (fotoEntrega) {
              const a = mk('a', 'btn btn-sm btn-outline-primary');
              a.href = fotoEntrega;
              a.target = '_blank';
              a.rel = 'noopener';
              a.innerHTML = '<i class="bi bi-image"></i> Abrir foto';
              actions.appendChild(a);
            }
            item.appendChild(actions);

            boxEnt.appendChild(item);
          });
        }

        new bootstrap.Modal(document.getElementById('modalDetalhes')).show();
      });
    })();
  </script>

</body>

</html>