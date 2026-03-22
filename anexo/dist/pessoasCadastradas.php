<?php

declare(strict_types=1);

/* ====== Auth ====== */
require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* ====== DB ====== */
require_once __DIR__ . '/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
  http_response_code(500);
  echo "<script>alert('Erro de conexão com o banco.');location.href='./index.php';</script>";
  exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ====== Helpers (PHP) ====== */
function h($v): string
{
  return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}
function only_digits(?string $s): string
{
  return preg_replace('/\D+/', '', (string)$s);
}
function fmt_money($n): string
{
  return ($n === null || $n === '') ? '—' : 'R$ ' . number_format((float)$n, 2, ',', '.');
}
function fmt_phone(?string $f): string
{
  $d = only_digits($f);
  if (strlen($d) === 11) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7, 4);
  if (strlen($d) === 10) return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6, 4);
  return $f ?: '—';
}
function fmt_cpf(?string $cpf): string
{
  $d = only_digits($cpf);
  if (strlen($d) !== 11) return $cpf ? $cpf : '—';
  return substr($d, 0, 3) . '.' . substr($d, 3, 3) . '.' . substr($d, 6, 3) . '-' . substr($d, 9, 2);
}
function fmt_date_br(?string $ymd): string
{
  if (!$ymd || $ymd === '0000-00-00') return '—';
  $p = explode('-', $ymd);
  return (count($p) === 3) ? ($p[2] . '/' . $p[1] . '/' . $p[0]) : '—';
}
function safe_int($v): ?int
{
  return ($v !== null && $v !== '') ? (int)$v : null;
}
function mb_lower(string $s): string
{
  if (function_exists('mb_strtolower')) return mb_strtolower($s, 'UTF-8');
  return strtolower($s);
}

/* ============================================================
   Detecta qual coluna em "solicitantes" guarda o responsável
   (servidor) do cadastro, para não quebrar caso o nome varie.
   ============================================================ */
$respCol = null;
$respExpr = "NULL"; // fallback
try {
  $cols = $pdo->query("
    SELECT COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE()
       AND TABLE_NAME = 'solicitantes'
  ")->fetchAll(PDO::FETCH_COLUMN) ?: [];

  $colsMap = array_fill_keys(array_map('strtolower', $cols), true);

  $candidates = [
    'responsavel',
    'responsavel_cadastro',
    'servidor',
    'servidor_cadastro',
    'usuario_responsavel',
    'usuario_cadastro',
    'criado_por',
    'created_by',
    'usuario_nome'
  ];

  foreach ($candidates as $cand) {
    if (isset($colsMap[strtolower($cand)])) {
      $respCol = $cand;
      break;
    }
  }

  if ($respCol && preg_match('/^[a-zA-Z0-9_]+$/', $respCol)) {
    $respExpr = "s.`{$respCol}`";
  } else {
    $respCol = null;
    $respExpr = "NULL";
  }
} catch (Throwable $e) {
  $respCol = null;
  $respExpr = "NULL";
}

/* ====== AJAX: Detalhes por ID (JSON) ====== */
if (isset($_GET['ajax']) && $_GET['ajax'] === 'detalhes' && isset($_GET['id'])) {
  $id = (int)$_GET['id'];

  $sql = "SELECT s.*,
                 COALESCE(b.nome,'') AS bairro_nome,
                 {$respExpr} AS responsavel_cadastro,
                 at.nome as ajuda_tipo_nome,
                 at.categoria as ajuda_tipo_categoria
          FROM solicitantes s
          LEFT JOIN bairros b ON b.id = s.bairro_id
          LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
          WHERE s.id = :id";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([':id' => $id]);
  $s = $stmt->fetch(PDO::FETCH_ASSOC);

  if (!$s) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false]);
    exit;
  }

  // Preparar a solicitação inicial (do cadastro) como primeira solicitação
  $solicitacaoInicial = null;
  if ($s['ajuda_tipo_id'] || $s['resumo_caso']) {
    $solicitacaoInicial = [
      'id' => 0, // ID especial para indicar que é do cadastro
      'ajuda_tipo_id' => $s['ajuda_tipo_id'],
      'ajuda_nome' => $s['ajuda_tipo_nome'],
      'ajuda_categoria' => $s['ajuda_tipo_categoria'],
      'resumo_caso' => $s['resumo_caso'],
      'data_solicitacao' => $s['created_at'],
      'status' => 'Cadastro',
      'created_by' => $s['responsavel_cadastro'] ?? null
    ];
  }

  // Buscar histórico de solicitações adicionais
  $solicitacoesAdicionais = [];
  try {
    $stmtSolic = $pdo->prepare("
      SELECT s.id, s.ajuda_tipo_id, s.resumo_caso, s.data_solicitacao, s.status, 
             s.created_by, at.nome as ajuda_nome, at.categoria as ajuda_categoria
      FROM solicitacoes s
      LEFT JOIN ajudas_tipos at ON at.id = s.ajuda_tipo_id
      WHERE s.solicitante_id = :sid
      ORDER BY s.data_solicitacao DESC
    ");
    $stmtSolic->execute([':sid' => $id]);
    $solicitacoesAdicionais = $stmtSolic->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    // Se a tabela não existir ainda, não quebra
    $solicitacoesAdicionais = [];
  }

  // Combinar todas as solicitações (inicial + adicionais)
  $todasSolicitacoes = [];
  if ($solicitacaoInicial) {
    $todasSolicitacoes[] = $solicitacaoInicial;
  }
  $todasSolicitacoes = array_merge($todasSolicitacoes, $solicitacoesAdicionais);

  $fam = $pdo->prepare("SELECT nome, data_nascimento, parentesco, escolaridade, obs
                        FROM familiares
                        WHERE solicitante_id = :sid
                        ORDER BY id");
  $fam->execute([':sid' => $id]);
  $familiares = $fam->fetchAll(PDO::FETCH_ASSOC);

  /* documentos: tenta com size_bytes, se não existir cai no fallback */
  try {
    $doc = $pdo->prepare("SELECT arquivo_path, original_name, mime_type, size_bytes, created_at
                          FROM solicitante_documentos
                          WHERE solicitante_id = :sid
                          ORDER BY created_at DESC, id DESC");
    $doc->execute([':sid' => $id]);
    $documentos = $doc->fetchAll(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $doc = $pdo->prepare("SELECT arquivo_path, original_name, mime_type, created_at
                          FROM solicitante_documentos
                          WHERE solicitante_id = :sid
                          ORDER BY created_at DESC, id DESC");
    $doc->execute([':sid' => $id]);
    $documentos = $doc->fetchAll(PDO::FETCH_ASSOC);
  }

  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
    'solicitante' => $s,
    'solicitacoes' => $todasSolicitacoes,
    'familiares' => $familiares,
    'documentos' => $documentos
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ====== Filtro (Bairro) & Busca (Nome/CPF/Responsável) ====== */
$bairro_id = safe_int($_GET['bairro_id'] ?? null);
$q = trim($_GET['q'] ?? '');
$qDigits = only_digits($q);

$where = [];
$params = [];

if ($bairro_id !== null && $bairro_id > 0) {
  $where[] = 's.bairro_id = :bid';
  $params[':bid'] = $bairro_id;
}

if ($q !== '') {
  $cond = '( s.nome LIKE :q OR s.cpf = :cpfq';
  if ($respCol) {
    $cond .= " OR {$respExpr} LIKE :q";
  }
  $cond .= ' )';
  $where[] = $cond;

  $params[':q'] = '%' . $q . '%';
  $params[':cpfq'] = ($qDigits !== '' && strlen($qDigits) <= 11)
    ? str_pad($qDigits, 11, '0', STR_PAD_LEFT)
    : '___INVALID___';
}

$whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

$sqlBase = "SELECT s.id, s.nome, s.cpf, s.telefone,
                   {$respExpr} AS responsavel_cadastro,
                   s.pbf, s.bpc,
                   s.beneficio_municipal, s.beneficio_estadual,
                   s.renda_familiar, s.renda_mensal_faixa, s.trabalho,s.local_trabalho,
                   COALESCE(b.nome,'—') AS bairro_nome,
                   s.created_at
            FROM solicitantes s
            LEFT JOIN bairros b ON b.id = s.bairro_id
            $whereSql
            ORDER BY s.created_at DESC, s.id DESC";
$stmt = $pdo->prepare($sqlBase);
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

/* Bairros para o filtro */
$bairros = $pdo->query("SELECT id, nome FROM bairros ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC);

/* Ajudas Tipos para Nova Solicitação (Modal) */
$ajudasTipos = [];
try {
  $ajudasTipos = $pdo->query("SELECT id, nome FROM ajudas_tipos WHERE status='Ativa' ORDER BY nome")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $ajudasTipos = [];
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <title>Pessoas Cadastradas - ANEXO</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <!-- CSS -->
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/css/bootstrap.css">
  <link rel="stylesheet" href="assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
  <link rel="stylesheet" href="assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="assets/css/app.css">
  <link rel="shortcut icon" href="assets/images/logo/logo_pmc_2025.jpg">

  <!-- (Opcional) DataTable CSS -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/simple-datatables@9.1.0/dist/style.css">

  <style>
    .table-actions .btn {
      margin: 0 2px;
    }

    .td-endereco {
      max-width: 280px;
    }

    @media (max-width:991.98px) {
      .td-endereco {
        max-width: 180px;
      }
    }

    /* ===== Modal layout ===== */
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

    .scroll-x {
      overflow-x: auto;
    }

    /* ===== WhatsApp (telefone na modal) ===== */
    .whats-wrap {
      display: flex;
      align-items: center;
      gap: .55rem;
      flex-wrap: wrap;
    }

    .whats-link {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      width: 36px;
      height: 36px;
      border-radius: 10px;
      border: 1px solid rgba(0, 0, 0, .08);
      background: #f1f3f5;
      text-decoration: none;
      color: #25D366;
    }

    .whats-link:hover {
      filter: brightness(.95);
    }

    .whats-link.disabled {
      opacity: .45;
      pointer-events: none;
    }

    /* ===== Documentos (linhas + botão Abrir) ===== */
    .docs-head {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .5rem;
      flex-wrap: wrap;
      margin-bottom: 0;
    }

    #md-docs {
      margin-top: .35rem;
    }

    /* desktop */
    #md-docs .doc-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: .75rem;
      padding: .5rem .6rem;
      border-radius: .65rem;
      background: #f8f9fa;
      margin: .4rem 0;
    }

    #md-docs .doc-meta {
      display: flex;
      align-items: start;
      gap: .5rem;
    }

    #md-docs .doc-name {
      font-weight: 600;
      word-break: break-word;
    }

    #md-docs .doc-sub {
      font-size: .8rem;
      color: #6c757d;
    }

    /* ===== Paginação (sem "Mostrando X–Y de Z") ===== */
    .tfoot-pager {
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: .75rem 1rem;
      flex-wrap: wrap;
    }

    /* ===== Histórico de Solicitações ===== */
    .solicitacao-card {
      border: 1px solid #dee2e6;
      border-radius: 0.5rem;
      padding: 1rem;
      margin-bottom: 1rem;
      background: #f8f9fa;
    }

    .solicitacao-header {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 0.5rem;
    }

    .solicitacao-tipo {
      font-weight: 600;
      color: #0d6efd;
    }

    .solicitacao-data {
      font-size: 0.875rem;
      color: #6c757d;
    }

    .solicitacao-status {
      display: inline-block;
      padding: 0.25rem 0.5rem;
      border-radius: 0.375rem;
      font-size: 0.75rem;
      font-weight: 600;
    }

    .status-cadastro {
      background-color: #6c757d;
      color: white;
    }

    .status-aberto {
      background-color: #d1ecf1;
      color: #0c5460;
    }

    .status-em-andamento {
      background-color: #fff3cd;
      color: #856404;
    }

    .status-concluido {
      background-color: #d4edda;
      color: #155724;
    }

    .status-cancelado {
      background-color: #f8d7da;
      color: #721c24;
    }

    .solicitacao-resumo {
      margin-top: 0.5rem;
      padding-top: 0.5rem;
      border-top: 1px solid #dee2e6;
      font-size: 0.875rem;
    }

    /* ===== Mobile ===== */
    @media (max-width:576px) {
      .profile-wrap {
        justify-content: center;
        text-align: center;
      }

      .modal-photo {
        margin: 0 auto;
      }

      .profile-info {
        flex-basis: 100%;
        text-align: center;
      }

      .profile-subline {
        justify-content: center;
      }

      .docs-head {
        gap: .5rem;
        margin-bottom: 0;
      }

      #md-docs {
        margin-top: 1rem;
      }

      #md-docs .doc-row {
        flex-direction: column;
        align-items: stretch;
      }

      #md-docs .doc-row a.btn {
        width: 100%;
        margin-top: .25rem;
      }

      .solicitacao-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 0.5rem;
      }
    }
  </style>
</head>

<body>
  <div id="app">
    <!-- Sidebar -->
    <div id="sidebar" class="active">
      <div class="sidebar-wrapper active">
        <div class="sidebar-header">
          <div class="d-flex justify-content-between align-items-center">
            <div class="logo">
              <a href="dashboard.php"><img src="assets/images/logo/logo_pmc_2025.jpg" alt="Logo" style="height:48px"></a>
            </div>
            <div class="toggler">
              <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
            </div>
          </div>
        </div>

        <div class="sidebar-menu">
          <ul class="menu">
            <li class="sidebar-item">
              <a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a>
            </li>

            <li class="sidebar-item has-sub active">
              <a href="#" class="sidebar-link"><i class="bi bi-person-lines-fill"></i><span>Solicitantes</span></a>
              <ul class="submenu active">
                <li class="submenu-item active"><a href="#">Cadastrados</a></li>
                <li class="submenu-item"><a href="cadastrarSolicitante.php">Novo Cadastro</a></li>
              </ul>
            </li>

            <?php
            $role = $_SESSION['user_role'] ?? '';

            if ($role === 'prefeito' || $role === 'secretario'):
            ?>
              <li class="sidebar-item has-sub">
                <a href="#" class="sidebar-link">
                  <i class="bi bi-person-fill"></i>
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

            <li class="sidebar-item">
              <a href="../../gpsemas/index.php" class="sidebar-link"><i class="bi bi-map-fill"></i><span>Rastreamento</span></a>
            </li>
            <?php if (!empty($_SESSION['user_role']) && $_SESSION['user_role'] === 'secretario'): ?>
              <li class="sidebar-item">
                <a href="../admin/index.php" class="sidebar-link" target="_blank" rel="noopener">
                  <i class="bi bi-shield-lock-fill"></i>
                  <span>Administrador</span>
                </a>
              </li>
            <?php endif; ?>


            <li class="sidebar-item">
              <a href="./auth/logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a>
            </li>
          </ul>
        </div>

      </div>
    </div>

    <!-- Main -->
    <div id="main">
      <header class="mb-3">
        <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
      </header>

      <div class="page-heading">
        <div class="page-title">
          <div class="row">
            <div class="col-12 col-md-6">
              <h3>Pessoas Cadastradas</h3>
            </div>
            <div class="col-12 col-md-6">
              <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                <ol class="breadcrumb">
                  <li class="breadcrumb-item"><a href="#">Pessoas</a></li>
                  <li class="breadcrumb-item active">Listar</li>
                </ol>
              </nav>
            </div>
          </div>
        </div>

        <div class="card">
          <div class="card-header">
            <div class="row filters-row g-2 justify-content-md-end">
              <div class="col-12 col-md-6 col-lg-5">
                <label class="form-label">Pesquisar (Nome, CPF ou Responsável)</label>
                <input type="text" id="inpSearch" class="form-control" placeholder="Digite para filtrar…"
                  value="<?= h($q) ?>">
              </div>
              <div class="col-12 col-md-3 col-lg-2">
                <label class="form-label">Bairro</label>
                <select id="selBairro" class="form-select">
                  <option value="">Todos</option>
                  <?php foreach ($bairros as $b): ?>
                    <option value="<?= (int)$b['id'] ?>" <?= ($bairro_id && $bairro_id == (int)$b['id']) ? 'selected' : '' ?>>
                      <?= h((string)$b['nome']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="col-12 col-md-auto">
                <label class="form-label d-none d-md-block">&nbsp;</label>
                <button class="btn btn-outline-secondary w-100" id="btnLimpar">
                  <i class="bi bi-eraser me-1"></i> Limpar
                </button>
              </div>
            </div>
          </div>

          <div class="card-body">
            <div class="table-responsive">
              <table class="table table-striped align-middle text-nowrap" id="tbl">
                <thead class="table-light">
                  <tr>
                    <th>Nome</th>
                    <th>Bairro</th>
                    <th>CPF</th>
                    <th>Telefone</th>
                    <th>Responsável (Servidor)</th>
                    <th class="text-center">PBF</th>
                    <th class="text-center">BPC</th>
                    <th class="text-end">Renda Fam.</th>
                    <th>S. Profissional</th>
                    <th class="text-center">Detalhes</th>
                  </tr>
                </thead>

                <tbody id="tbody">
                  <?php if (!$rows): ?>
                    <tr>
                      <td colspan="10" class="text-center text-muted">Nenhum registro encontrado.</td>
                    </tr>
                    <?php else: foreach ($rows as $r): ?>
                      <?php
                      $resp = (string)($r['responsavel_cadastro'] ?? '');
                      $respLower = mb_lower($resp);
                      ?>
                      <tr
                        data-id="<?= (int)$r['id'] ?>"
                        data-nome="<?= h(mb_lower((string)($r['nome'] ?? ''))) ?>"
                        data-cpf="<?= h(only_digits($r['cpf'])) ?>"
                        data-bairro="<?= h((string)$r['bairro_nome']) ?>"
                        data-responsavel="<?= h($respLower) ?>">

                        <td><?= h((string)$r['nome']) ?></td>
                        <td><?= h((string)$r['bairro_nome']) ?></td>
                        <td class="nowrap"><?= h(fmt_cpf($r['cpf'])) ?></td>
                        <td class="nowrap"><?= h(fmt_phone($r['telefone'])) ?></td>
                        <td><?= h($resp !== '' ? $resp : '—') ?></td>

                        <td class="text-center"><?= h($r['pbf'] ?? 'Não') ?></td>
                        <td class="text-center"><?= h($r['bpc'] ?? 'Não') ?></td>

                        <td class="text-end"><?= h(fmt_money($r['renda_familiar'])) ?></td>
                        <td><?= h($r['trabalho'] ?? '—') ?></td>

                        <td class="text-center">
                          <button class="btn btn-sm btn-secondary btnDetalhes" title="Ver detalhes">Ver</button>
                          <a href="editarSolicitante.php?id=<?= (int)$r['id'] ?>&cpf=<?= h(only_digits($r['cpf'])) ?>"
                            class="btn btn-sm btn-primary" title="Editar">Editar</a>
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

          </div>

        </div>

      </div>

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
    </div>
  </div>

  <!-- Modal Detalhes -->
  <div class="modal fade" id="modalDetalhes" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Detalhes do Beneficiário</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <!-- Cabeçalho do perfil -->
          <div class="profile-wrap">
            <img id="md-foto" class="modal-photo" src="assets/images/placeholder-user.jpg" alt="Foto">
            <div class="profile-info">
              <h5 class="profile-name" id="md-nome">—</h5>
              <div class="profile-subline">
                <span class="pill"><i class="bi bi-person"></i> <span id="md-genero">—</span></span>
                <span class="pill"><i class="bi bi-heart"></i> <span id="md-ec">—</span></span>
                <span class="pill"><i class="bi bi-calendar2"></i> <span id="md-nasc">—</span></span>
                <span class="pill"><i class="bi bi-person-badge"></i> <span id="md-resp-pill">—</span></span>
              </div>
              <div class="text-muted mt-1" style="font-size:.875rem;">Cadastro: <span id="md-criado">—</span></div>
            </div>
          </div>

          <!-- Seção de Histórico de Solicitações -->
          <h6 class="mb-2">Histórico de Solicitações</h6>
          <div id="md-solicitacoes" class="mb-3">
            <div class="alert alert-info">
              Carregando histórico de solicitações...
            </div>
          </div>

          <!-- I. Identificação -->
          <h6 class="mb-2">I. Identificação</h6>
            <div class="kv-grid mb-3">
             <div class="kv">
              <div class="kv-label">Situação Profissional</div>
              <div class="kv-value" id="md-trabalho">—</div>
            </div>
           
             <div class="kv">
              <div class="kv-label">Local de Trabalho</div>
              <div class="kv-value" id="md-local">—</div>
            </div>
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

            <!-- Telefone + WhatsApp -->
            <div class="kv">
              <div class="kv-label">Telefone</div>
              <div class="kv-value whats-wrap">
                <span id="md-tel">—</span>
                <a id="md-whats" class="whats-link disabled" href="#" target="_blank" rel="noopener" title="Abrir WhatsApp">
                  <i class="bi bi-whatsapp fs-5"></i>
                </a>
              </div>
            </div>

            <!-- Responsável (Servidor) do cadastro -->
            <div class="kv">
              <div class="kv-label">Responsável (Servidor)</div>
              <div class="kv-value" id="md-responsavel">—</div>
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
          </div>

          <!-- II. Endereço -->
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

          <!-- III. Grupos, Benefícios e Renda -->
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

          <!-- IV. Composição Familiar (Totais) -->
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

          <!-- V. Condições Habitacionais -->
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

          <!-- VI. Documentos -->
          <div class="docs-head">
            <h6 class="mb-2">VI. Documentos Anexados</h6>
            <a class="btn btn-outline-primary btn-sm" id="btnSocio" target="_blank" href="#"><i class="bi bi-file-text"></i> Ver Folha Socioeconômica</a>
          </div>
          <div id="md-docs" class="mb-3"></div>

          <!-- VII. Familiares -->
          <h6 class="mb-2">VII. Familiares</h6>
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

          <!-- VIII. Cônjuge -->
          <h6 class="mb-2">VIII. Cônjuge</h6>
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
          <a id="btnAtrib" href="#" class="btn btn-primary">Atribuir Benefício</a>
          <button type="button" class="btn btn-success text-white" id="btnNovaSol">Nova Solicitação</button>
          <button class="btn btn-secondary" data-bs-dismiss="modal">Fechar</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Modal Nova Solicitação -->
  <div class="modal fade" id="modalNovaSolicitacao" tabindex="-1" aria-hidden="true" style="z-index: 1065;">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Nova Solicitação</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>

        <div class="modal-body">
          <form id="formNovaSol">

            <input type="hidden" id="novaSol_pid">

            <div class="mb-3">
              <label class="form-label">Tipo de Ajuda</label>
              <select id="novaSol_ajuda" class="form-select" required>
                <option value="">Selecione...</option>
                <?php foreach ($ajudasTipos as $at): ?>
                  <option value="<?= (int)$at['id'] ?>"><?= h($at['nome']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>

            <div class="mb-3">
              <label class="form-label">Resumo do Caso</label>
              <textarea
                id="novaSol_resumo"
                class="form-control"
                rows="4"
                required
                placeholder="Descreva a nova necessidade..."></textarea>
            </div>

            <!-- Campo hidden com data/hora em tempo real -->
            <input type="hidden" name="data_solicitacao" id="data_solicitacao">

            <div class="mb-2">
              <small class="text-muted">
                Data/Hora da solicitação:
                <strong><span id="dataHoraExibicao">--/--/---- --:--:--</span></strong>
              </small>
            </div>

          </form>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
          <button type="button" class="btn btn-primary" id="btnSalvarSol">Salvar Solicitação</button>
        </div>
      </div>
    </div>
  </div>

  <script>
    /* =========================
   CONTROLE DE DATA/HORA
========================= */

    let intervaloDataHora = null;

    // Retorna data/hora no formato MySQL
    function getCurrentDateTimeForMySQL() {
      const agora = new Date();

      const ano = agora.getFullYear();
      const mes = String(agora.getMonth() + 1).padStart(2, '0');
      const dia = String(agora.getDate()).padStart(2, '0');
      const hora = String(agora.getHours()).padStart(2, '0');
      const minuto = String(agora.getMinutes()).padStart(2, '0');
      const segundo = String(agora.getSeconds()).padStart(2, '0');

      return `${ano}-${mes}-${dia} ${hora}:${minuto}:${segundo}`;
    }

    // Retorna data/hora para exibição
    function getFormattedDateTime() {
      const agora = new Date();
      return agora.toLocaleString('pt-BR', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit'
      });
    }

    // Atualiza campo hidden + texto visível
    function atualizarDataHora() {
      document.getElementById('data_solicitacao').value = getCurrentDateTimeForMySQL();
      document.getElementById('dataHoraExibicao').textContent = getFormattedDateTime();
    }

    /* =========================
       EVENTOS DO MODAL
    ========================= */

    const modalNovaSolEl = document.getElementById('modalNovaSolicitacao');

    if (modalNovaSolEl) {

      // Quando abrir o modal → começa o tempo real
      modalNovaSolEl.addEventListener('shown.bs.modal', function() {
        atualizarDataHora();

        intervaloDataHora = setInterval(atualizarDataHora, 1000);
      });

      // Quando fechar o modal → para o relógio
      modalNovaSolEl.addEventListener('hidden.bs.modal', function() {
        if (intervaloDataHora) {
          clearInterval(intervaloDataHora);
          intervaloDataHora = null;
        }
      });

    }
  </script>


  <!-- Modal Selecionar Solicitação -->
  <div class="modal fade" id="modalSelecionarSolicitacao" tabindex="-1" aria-hidden="true" style="z-index: 1070;">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Selecionar Solicitação</h5>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info">
            Este usuário possui múltiplas solicitações. Escolha para qual delas o benefício será atribuído.
          </div>
          <div class="list-group" id="listaSolicitacoes">
            <!-- JS preenche -->
          </div>
        </div>
      </div>
    </div>
  </div>

  <script src="assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
  <script src="assets/js/bootstrap.bundle.min.js"></script>
  <script>
    (() => {
      'use strict';

      // Ano rodapé
      const cy = document.getElementById('current-year');
      if (cy) cy.textContent = String(new Date().getFullYear());

      // ====== Filtros ======
      const selBairro = document.getElementById('selBairro');
      const inpSearch = document.getElementById('inpSearch');
      const btnLimpar = document.getElementById('btnLimpar');

      selBairro?.addEventListener('change', () => {
        const params = new URLSearchParams(window.location.search);
        const val = selBairro.value;
        if (val) params.set('bairro_id', val);
        else params.delete('bairro_id');
        const q = inpSearch.value.trim();
        if (q) params.set('q', q);
        else params.delete('q');
        window.location.search = params.toString();
      });

      btnLimpar?.addEventListener('click', () => {
        inpSearch.value = '';
        selBairro.value = '';
        const params = new URLSearchParams(window.location.search);
        params.delete('q');
        params.delete('bairro_id');
        window.location.search = params.toString();
      });

      // ====== Paginação client-side ======
      const tbody = document.getElementById('tbody');
      const allRows = Array.from(tbody?.querySelectorAll('tr') || []);
      const selPerPage = document.getElementById('selPerPage');
      const btnPrev = document.getElementById('btnPrev');
      const btnNext = document.getElementById('btnNext');
      const lblPagina = document.getElementById('lblPagina');

      selPerPage.value = '10';
      let page = 1;
      let perPage = parseInt(selPerPage.value, 10);
      let filtered = allRows.slice();

      function paginateAndShow() {
        const total = filtered.length;
        const pages = Math.max(1, Math.ceil(total / perPage));
        if (page > pages) page = pages;
        const start = (page - 1) * perPage;
        const end = start + perPage;

        allRows.forEach(r => (r.style.display = 'none'));
        filtered.slice(start, end).forEach(r => (r.style.display = ''));

        if (lblPagina) lblPagina.textContent = `Página ${page} de ${pages}`;
        if (btnPrev) btnPrev.disabled = page <= 1;
        if (btnNext) btnNext.disabled = page >= pages;
      }

      selPerPage?.addEventListener('change', () => {
        perPage = parseInt(selPerPage.value, 10) || 10;
        page = 1;
        paginateAndShow();
      });

      btnPrev?.addEventListener('click', () => {
        if (page > 1) {
          page--;
          paginateAndShow();
        }
      });

      btnNext?.addEventListener('click', () => {
        page++;
        paginateAndShow();
      });

      // ====== Busca instantânea (nome / CPF / responsável) ======
      function filterAndRender() {
        const q = (inpSearch.value || '').trim().toLowerCase();
        const qDigits = q.replace(/\D+/g, '');

        filtered = allRows.filter(r => {
          const nome = (r.dataset.nome || '');
          const cpf = (r.dataset.cpf || '');
          const resp = (r.dataset.responsavel || '');

          if (!q) return true;

          return (
            nome.includes(q) ||
            resp.includes(q) ||
            (qDigits && cpf.startsWith(qDigits))
          );
        });

        page = 1;
        paginateAndShow();
      }
      inpSearch?.addEventListener('input', filterAndRender);

      // Primeira renderização
      filterAndRender();

      // ====== Utilitários do Modal ======
      const setText = (id, val) => {
        const el = document.getElementById(id);
        if (el) el.textContent = (val ?? '').toString().trim() !== '' ? String(val) : '—';
      };

      const escapeHtml = (s) => (s ?? '').toString().replace(/[&<>"']/g, m => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
      } [m]));

      const money = (n) => (n === null || n === undefined || n === '') ? '—' : 'R$ ' + (Number(n) || 0).toLocaleString('pt-BR', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2
      });

      const brDate = (ymd) => {
        const p = (ymd || '').split('-');
        return p.length === 3 ? `${p[2]}/${p[1]}/${p[0]}` : '—';
      };

      const brDateTime = (dt) => {
        const [d, t] = (dt || '').split(' ');
        return d ? (brDate(d) + (t ? (' ' + t) : '')) : '—';
      };

      const formatCPF = (cpf) => {
        const d = (cpf || '').replace(/\D+/g, '');
        return d.length !== 11 ? (cpf || '—') : `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
      };

      const formatPhone = (f) => {
        const d = (f || '').replace(/\D+/g, '');
        if (d.length === 11) return `(${d.slice(0, 2)}) ${d.slice(2, 7)}-${d.slice(7)}`;
        if (d.length === 10) return `(${d.slice(0, 2)}) ${d.slice(2, 6)}-${d.slice(6)}`;
        return f || '—';
      };

      const num = (x) => Number.isFinite(Number(x)) ? Number(x) : 0;

      // pega responsável do cadastro (nome pode variar dependendo do banco)
      const getResp = (s) => {
        const keys = [
          'responsavel_cadastro', 'responsavel', 'servidor', 'servidor_cadastro',
          'usuario_responsavel', 'usuario_cadastro', 'criado_por', 'created_by', 'usuario_nome'
        ];
        for (const k of keys) {
          const v = (s && s[k] != null) ? String(s[k]).trim() : '';
          if (v) return v;
        }
        return '';
      };

      // monta link do WhatsApp pro número da modal
      const setWhatsAppLink = (telefone) => {
        const a = document.getElementById('md-whats');
        if (!a) return;

        let d = String(telefone || '').replace(/\D+/g, '');
        d = d.replace(/^0+/, ''); // remove zeros à esquerda, se existirem

        // aceita 10/11 (sem DDI) ou 12/13 (com 55)
        if (d.startsWith('55') && (d.length === 12 || d.length === 13)) {
          // ok
        } else if (d.length === 10 || d.length === 11) {
          d = '55' + d;
        } else {
          a.href = '#';
          a.classList.add('disabled');
          return;
        }

        a.href = `https://wa.me/${encodeURIComponent(d)}`;
        a.classList.remove('disabled');
      };

      // Função para renderizar histórico de solicitações
      const renderSolicitacoes = (solicitacoes) => {
        const container = document.getElementById('md-solicitacoes');
        if (!container) return;

        if (!Array.isArray(solicitacoes) || solicitacoes.length === 0) {
          container.innerHTML = '<div class="alert alert-info">Nenhuma solicitação registrada para este beneficiário.</div>';
          return;
        }

        let html = '';
        solicitacoes.forEach(sol => {
          // Determinar classe CSS do status
          let statusClass = 'status-aberto';
          if (sol.status === 'Cadastro') statusClass = 'status-cadastro';
          else if (sol.status === 'Em andamento') statusClass = 'status-em-andamento';
          else if (sol.status === 'Concluído' || sol.status === 'Concluido') statusClass = 'status-concluido';
          else if (sol.status === 'Cancelado') statusClass = 'status-cancelado';

          // Para a solicitação do cadastro (id = 0), mostrar texto especial
          const isCadastro = sol.id === 0;
          const tipoLabel = isCadastro ? 'Solicitação Inicial (Cadastro)' : 'Solicitação Adicional';

          html += `
            <div class="solicitacao-card">
              <div class="solicitacao-header">
                <div>
                  <span class="solicitacao-tipo">${escapeHtml(sol.ajuda_nome || sol.ajuda_tipo_nome || 'Não informado')}</span>
                  ${sol.ajuda_categoria || sol.ajuda_tipo_categoria ? 
                    `<span class="badge bg-secondary ms-2">${escapeHtml(sol.ajuda_categoria || sol.ajuda_tipo_categoria)}</span>` : ''}
                  <span class="badge bg-info ms-2">${tipoLabel}</span>
                </div>
                <div class="solicitacao-data">${brDateTime(sol.data_solicitacao)}</div>
              </div>
              <div>
                <span class="solicitacao-status ${statusClass}">${escapeHtml(sol.status)}</span>
                ${sol.created_by ? `<span class="ms-2 text-muted">Criado por: ${escapeHtml(sol.created_by)}</span>` : ''}
              </div>
              <div class="solicitacao-resumo">
                <strong>Resumo:</strong> ${escapeHtml(sol.resumo_caso || 'Não informado')}
              </div>
            </div>
          `;
        });

        container.innerHTML = html;
      };

      // ====== Abrir modal com AJAX ======
      document.addEventListener('click', async (e) => {
        const btn = e.target.closest?.('.btnDetalhes');
        if (!btn) return;
        const tr = btn.closest('tr');
        const id = tr?.dataset?.id;
        if (!id) return;

        const url = new URL(window.location.href);
        url.searchParams.set('ajax', 'detalhes');
        url.searchParams.set('id', id);

        let j;
        try {
          const res = await fetch(url.toString(), {
            headers: {
              'X-Requested-With': 'fetch'
            }
          });
          j = await res.json();
        } catch {
          j = {
            ok: false
          };
        }

        if (!j.ok) {
          alert('Falha ao carregar detalhes.');
          return;
        }

        const s = j.solicitante || {};
        const foto = (s.foto_path && String(s.foto_path).trim() !== '') ? String(s.foto_path) : 'assets/images/placeholder-user.jpg';
        const img = document.getElementById('md-foto');
        if (img) img.src = foto;

        const respCad = getResp(s);

        // Cabeçalho
        setText('md-nome', s.nome || '—');
        setText('md-genero', s.genero || '—');
        setText('md-ec', s.estado_civil || '—');
        setText('md-nasc', s.data_nascimento ? brDate(s.data_nascimento) : '—');
        setText('md-criado', s.created_at ? brDateTime(s.created_at) : '—');

        // Responsável (pill + campo)
        setText('md-resp-pill', respCad || '—');
        setText('md-responsavel', respCad || '—');

        // Renderizar histórico de solicitações (incluindo a do cadastro)
        renderSolicitacoes(j.solicitacoes || []);

        // I. Identificação
        setText('md-cpf', formatCPF(s.cpf || ''));
        setText('md-rg', s.rg || '—');
        setText('md-nis', s.nis || '—');
        setText('md-trabalho', s.trabalho || '—');
        setText('md-local', s.local_trabalho || '—');
        setText('md-rg-emissao', s.rg_emissao ? brDate(s.rg_emissao) : '—');
        setText('md-rg-uf', s.rg_uf || '—');

        // Telefone + WhatsApp
        setText('md-tel', formatPhone(s.telefone || ''));
        setWhatsAppLink(s.telefone || '');

        setText('md-genero-2', s.genero || '—');
        setText('md-ec-2', s.estado_civil || '—');
        setText('md-nasc-2', s.data_nascimento ? brDate(s.data_nascimento) : '—');
        setText('md-nac', s.nacionalidade || '—');
        setText('md-nat', s.naturalidade || '—');
        setText('md-tempo', `${num(s.tempo_anos) || 0} ano(s)${s.tempo_meses ? `, ${num(s.tempo_meses)} mês(es)` : ''}`);

        // II. Endereço
        setText('md-endereco', s.endereco || '—');
        setText('md-numero', s.numero || '—');
        setText('md-complemento', s.complemento || '—');
        setText('md-bairro', s.bairro_nome || '—');
        setText('md-referencia', s.referencia || '—');

        // III. Benefícios/Renda
        setText('md-grupo', s.grupo_tradicional || '—');
        setText('md-grupo-outros', s.grupo_outros || '—');
        setText('md-pcd', s.pcd || 'Não');
        setText('md-pcd-tipo', s.pcd_tipo || '—');
        setText('md-bpc', s.bpc || 'Não');
        setText('md-bpc-valor', money(s.bpc_valor));
        setText('md-pbf', s.pbf || 'Não');
        setText('md-pbf-valor', money(s.pbf_valor));
        setText('md-ben-mun', s.beneficio_municipal || 'Não');
        setText('md-ben-mun-valor', money(s.beneficio_municipal_valor));
        setText('md-ben-est', s.beneficio_estadual || 'Não');
        setText('md-ben-est-valor', money(s.beneficio_estadual_valor));
        setText('md-faixa', s.renda_mensal_faixa || '—');
        setText('md-faixa-outros', s.renda_mensal_outros || '—');
        setText('md-trabalho', s.trabalho || '—');
        setText('md-renda-ind', money(s.renda_individual));
        setText('md-renda-fam', money(s.renda_familiar));
        setText('md-rend-tot', money(s.total_rendimentos));
        setText('md-tipificacao', s.tipificacao || '—');

        // IV. Totais
        setText('md-tot-mor', (s.total_moradores ?? '—'));
        setText('md-tot-fam', (s.total_familias ?? '—'));
        setText('md-pcd-res', s.pcd_residencia || '—');
        setText('md-tot-pcd', (s.total_pcd ?? '—'));

        // V. Habitação
        setText('md-sit', s.situacao_imovel || '—');
        setText('md-sit-valor', (s.situacao_imovel === 'Alugado') ? money(s.situacao_imovel_valor) : '—');
        setText('md-tipo-moradia', s.tipo_moradia || '—');
        setText('md-abast', s.abastecimento || '—');
        setText('md-ilum', s.iluminacao || '—');
        setText('md-esgoto', s.esgoto || '—');
        setText('md-lixo', s.lixo || '—');
        setText('md-entorno', s.entorno || '—');

        // VI. Documentos -> linhas + botão "Abrir"
        const docsWrap = document.getElementById('md-docs');
        docsWrap.innerHTML = '';
        if (Array.isArray(j.documentos) && j.documentos.length) {
          j.documentos.forEach(d => {
            const row = document.createElement('div');
            row.className = 'doc-row';

            const left = document.createElement('div');
            left.className = 'doc-meta';
            const sizeMb = d.size_bytes ? (Number(d.size_bytes) / 1024 / 1024).toFixed(2) + ' MB' : '';
            const when = d.created_at ? brDateTime(d.created_at) : '';
            left.innerHTML = `
              <i class="bi bi-paperclip fs-5"></i>
              <div>
                <div class="doc-name">${escapeHtml(d.original_name || 'Documento')}</div>
                <div class="doc-sub">${[sizeMb, when].filter(Boolean).join(' • ')}</div>
              </div>`;

            const a = document.createElement('a');
            a.className = 'btn btn-sm btn-outline-primary';
            a.target = '_blank';
            a.rel = 'noopener';
            a.href = (d.arquivo_path || '#');
            a.innerHTML = '<i class="bi bi-box-arrow-up-right"></i> Abrir';

            row.appendChild(left);
            row.appendChild(a);
            docsWrap.appendChild(row);
          });
        } else {
          docsWrap.innerHTML = '<span class="text-muted">Nenhum documento anexado.</span>';
        }

        // VII. Familiares
        const tb = document.getElementById('md-familiares');
        if (tb) {
          tb.innerHTML = '';
          if (Array.isArray(j.familiares) && j.familiares.length) {
            j.familiares.forEach(f => {
              const tr = document.createElement('tr');
              tr.innerHTML = `
                <td>${escapeHtml(f.nome || '—')}</td>
                <td>${f.data_nascimento ? brDate(f.data_nascimento) : '—'}</td>
                <td>${escapeHtml(f.parentesco || '—')}</td>
                <td>${escapeHtml(f.escolaridade || '—')}</td>
                <td>${escapeHtml(f.obs || '')}</td>`;
              tb.appendChild(tr);
            });
          } else {
            const tr = document.createElement('tr');
            tr.innerHTML = `<td colspan="5" class="text-center text-muted">Sem familiares cadastrados.</td>`;
            tb.appendChild(tr);
          }
        }

        // VIII. Cônjuge
        setText('md-conj-nome', s.conj_nome || '—');
        setText('md-conj-nis', s.conj_nis || '—');
        setText('md-conj-cpf', formatCPF(s.conj_cpf || ''));
        setText('md-conj-rg', s.conj_rg || '—');
        setText('md-conj-nasc', s.conj_nasc ? brDate(s.conj_nasc) : '—');
        setText('md-conj-gen', s.conj_genero || '—');
        setText('md-conj-nac', s.conj_nacionalidade || '—');
        setText('md-conj-nat', s.conj_naturalidade || '—');
        setText('md-conj-trab', s.conj_trabalho || '—');
        setText('md-conj-renda', money(s.conj_renda));
        setText('md-conj-pcd', s.conj_pcd || '—');
        setText('md-conj-bpc', s.conj_bpc || '—');
        setText('md-conj-bpc-valor', money(s.conj_bpc_valor));

        // Botões dinâmicos
        const cpfDigits = (s.cpf || '').replace(/\D+/g, '');
        const btnSocio = document.getElementById('btnSocio');
        if (btnSocio) btnSocio.href = `imprimirSocioeconomico.php?cpf=${encodeURIComponent(cpfDigits)}`;

        const btnAtrib = document.getElementById('btnAtrib');
        if (btnAtrib) {
          // Filtrar apenas solicitações que podem receber benefícios (excluir a do cadastro)
          const solicitacoesAtivas = (j.solicitacoes || []).filter(sol => sol.id !== 0);

          if (solicitacoesAtivas.length > 1) {
            btnAtrib.href = '#';
            btnAtrib.onclick = (e) => {
              e.preventDefault();
              mostrarModalSelecaoSolicitacao(solicitacoesAtivas, cpfDigits);
            };
          } else if (solicitacoesAtivas.length === 1) {
            btnAtrib.href = `atribuirBeneficio.php?cpf=${encodeURIComponent(cpfDigits)}&solicitacao_id=${solicitacoesAtivas[0].id}`;
            btnAtrib.onclick = null;
          } else {
            // Se não há solicitações ativas, vai para criar nova solicitação primeiro?
            btnAtrib.href = `atribuirBeneficio.php?cpf=${encodeURIComponent(cpfDigits)}`;
            btnAtrib.onclick = null;
          }
        }

        // Guardar ID atual para uso posterior
        currentPessoaId = id;

        // Abre modal
        const modalEl = document.getElementById('modalDetalhes');
        if (modalEl) new bootstrap.Modal(modalEl).show();
      });

      // ====== Modal de Seleção de Solicitação ======
      function mostrarModalSelecaoSolicitacao(solicitacoes, cpfDigits) {
        const list = document.getElementById('listaSolicitacoes');
        list.innerHTML = '';

        solicitacoes.forEach(s => {
          const a = document.createElement('a');
          a.className = 'list-group-item list-group-item-action';
          a.style.cursor = 'pointer';

          let statusClass = 'bg-secondary';
          if (s.status === 'Em andamento') statusClass = 'bg-warning';
          else if (s.status === 'Concluído' || s.status === 'Concluido') statusClass = 'bg-success';
          else if (s.status === 'Cancelado') statusClass = 'bg-danger';

          a.innerHTML = `
            <div class="d-flex w-100 justify-content-between">
              <h6 class="mb-1">${escapeHtml(s.ajuda_nome || 'Não informado')}</h6>
              <small>${brDateTime(s.data_solicitacao)}</small>
            </div>
            <p class="mb-1">${escapeHtml(s.resumo_caso?.substring(0, 100) || 'Sem resumo')}${s.resumo_caso?.length > 100 ? '...' : ''}</p>
            <div class="d-flex justify-content-between align-items-center">
              <small>
                <span class="badge ${statusClass}">${escapeHtml(s.status)}</span>
                ${s.ajuda_categoria ? `<span class="ms-1">${escapeHtml(s.ajuda_categoria)}</span>` : ''}
              </small>
              <button class="btn btn-sm btn-outline-primary">Selecionar</button>
            </div>
          `;

          a.onclick = () => {
            window.location.href = `atribuirBeneficio.php?cpf=${encodeURIComponent(cpfDigits)}&solicitacao_id=${s.id}`;
          };

          list.appendChild(a);
        });

        const modalSelSol = new bootstrap.Modal(document.getElementById('modalSelecionarSolicitacao'));
        modalSelSol.show();
      }

      // ====== Nova Solicitação ======
      const btnNovaSol = document.getElementById('btnNovaSol');
      const modalNovaSolEl = document.getElementById('modalNovaSolicitacao');
      const bsNovaSol = modalNovaSolEl ? new bootstrap.Modal(modalNovaSolEl) : null;

      // Variavel global para ID atual
      let currentPessoaId = null;

      // Listener global para salvar ID ao clicar em VerDetalhes
      document.addEventListener('click', (e) => {
        const btn = e.target.closest?.('.btnDetalhes');
        if (btn) {
          const tr = btn.closest('tr');
          if (tr) currentPessoaId = tr.dataset.id;
        }
      });

      btnNovaSol?.addEventListener('click', () => {
        if (!currentPessoaId) {
          alert('Nenhum beneficiário selecionado.');
          return;
        }
        document.getElementById('novaSol_pid').value = currentPessoaId;
        document.getElementById('novaSol_ajuda').value = '';
        document.getElementById('novaSol_resumo').value = '';
        bsNovaSol.show();
      });

      document.getElementById('btnSalvarSol')?.addEventListener('click', async () => {
        const btn = document.getElementById('btnSalvarSol');
        const pid = document.getElementById('novaSol_pid').value;
        const aid = document.getElementById('novaSol_ajuda').value;
        const resu = document.getElementById('novaSol_resumo').value;

        // Captura a data/hora atualizada no momento do envio
        atualizarDataHora();
        const dataSolic = document.getElementById('data_solicitacao').value;

        if (!aid || !resu) {
          alert('Preencha todos os campos!');
          return;
        }

        const fd = new FormData();
        fd.append('solicitante_id', pid);
        fd.append('ajuda_tipo_id', aid);
        fd.append('resumo_caso', resu);
        fd.append('data_solicitacao', dataSolic);

        btn.disabled = true;
        btn.textContent = 'Salvando...';

        try {
          const res = await fetch('dados/novaSolicitacao.php', {
            method: 'POST',
            body: fd
          });
          const j = await res.json();
          if (j.ok) {
            alert('Solicitação criada com sucesso!');
            bsNovaSol.hide();
            // Recarregar página para atualizar o histórico
            location.reload();
          } else {
            alert('Erro: ' + (j.msg || 'Desconhecido'));
          }
        } catch (e) {
          alert('Erro de conexão ao salvar.');
          console.error(e);
        }
        btn.disabled = false;
        btn.textContent = 'Salvar Solicitação';
      });

    })();
  </script>

  <script src="assets/js/main.js"></script>
</body>

</html>