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

require '../../../assets/php/conexao.php';

function h($s): string
{
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function only_digits(string $s): string
{
  $out = preg_replace('/\D+/', '', $s);
  return $out !== null ? $out : '';
}

/* Feira padrão desta página */
$FEIRA_ID = 1; // 1=Feira do Produtor | 2=Feira Alternativa

/* Detecção opcional pela pasta */
$dirLower = strtolower((string)__DIR__);
if (strpos($dirLower, 'alternativa') !== false) $FEIRA_ID = 2;
if (strpos($dirLower, 'produtor')   !== false) $FEIRA_ID = 1;

/* Flash */
$msg = (string)($_SESSION['flash_ok'] ?? '');
$err = (string)($_SESSION['flash_err'] ?? '');
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

/* CSRF */
if (empty($_SESSION['csrf_token'])) {
  $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = (string)$_SESSION['csrf_token'];

$produtores = [];
$totalRows  = 0;
$totalPages = 1;
$errDetail  = '';
$dbName     = '';

/* Paginação */
$perPage = 12;
$page = (int)($_GET['p'] ?? 1);
if ($page < 1) $page = 1;
$offset = ($page - 1) * $perPage;

/* Busca */
$qRaw = trim((string)($_GET['q'] ?? ''));
$qDigits = only_digits($qRaw);

/* Filtro de tipo */
$tipoRaw = trim((string)($_GET['tipo'] ?? 'TODOS'));
$tiposValidos = ['TODOS', 'PRODUTOR RURAL', 'FEIRANTE', 'MARRETEIRO'];
if (!in_array($tipoRaw, $tiposValidos, true)) {
  $tipoRaw = 'TODOS';
}

$cleanErr = function (string $m): string {
  $m = preg_replace('/SQLSTATE\[[^\]]+\]:\s*/', '', $m) ?? $m;
  $m = preg_replace('/\(SQL:\s*.*\)$/', '', $m) ?? $m;
  return trim((string)$m);
};

function buildUrl(array $add = []): string
{
  $base = strtok($_SERVER['REQUEST_URI'], '?') ?: './listaProdutorImpressao.php';
  $cur = $_GET ?? [];
  foreach ($add as $k => $v) {
    if ($v === null) unset($cur[$k]);
    else $cur[$k] = (string)$v;
  }
  $qs = http_build_query($cur);
  return $qs ? ($base . '?' . $qs) : $base;
}

try {
  $pdo = db();
  $dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn();

  /* Checa tabelas */
  $tblP = $pdo->query("SHOW TABLES LIKE 'produtores'")->fetchColumn();
  if (!$tblP) throw new RuntimeException("Tabela 'produtores' não existe neste banco.");

  $tblC = $pdo->query("SHOW TABLES LIKE 'comunidades'")->fetchColumn();
  $hasComunidades = (bool)$tblC;

  $colTipo = $pdo->query("SHOW COLUMNS FROM produtores LIKE 'tipo'")->fetch(PDO::FETCH_ASSOC);
  $hasTipo = (bool)$colTipo;

  /* ===== WHERE ===== */
  $where = ["p.feira_id = :feira"];
  $params = [':feira' => $FEIRA_ID];

  if ($hasTipo && $tipoRaw !== 'TODOS') {
    $where[] = "p.tipo = :tipo";
    $params[':tipo'] = $tipoRaw;
  }

  if ($qRaw !== '') {
    $parts = [];

    $params[':q_nome'] = '%' . $qRaw . '%';
    $parts[] = "p.nome LIKE :q_nome";

    if ($hasTipo) {
      $params[':q_tipo_busca'] = '%' . $qRaw . '%';
      $parts[] = "p.tipo LIKE :q_tipo_busca";
    }

    $params[':q_contato'] = '%' . $qRaw . '%';
    $parts[] = "p.contato LIKE :q_contato";

    $params[':q_doc'] = '%' . $qRaw . '%';
    $parts[] = "p.documento LIKE :q_doc";

    if ($hasComunidades) {
      $params[':q_com'] = '%' . $qRaw . '%';
      $parts[] = "c.nome LIKE :q_com";
    }

    if ($qDigits !== '') {
      $params[':qd_contato'] = '%' . $qDigits . '%';
      $parts[] = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(p.contato,' ',''),'-',''),'(',''),')',''),'+','') LIKE :qd_contato";

      $params[':qd_doc'] = '%' . $qDigits . '%';
      $parts[] = "REPLACE(REPLACE(REPLACE(p.documento,'.',''),'-',''),' ','') LIKE :qd_doc";
    }

    $where[] = '(' . implode(' OR ', $parts) . ')';
  }

  $whereSql = ' WHERE ' . implode(' AND ', $where);

  /* ===== COUNT ===== */
  if ($hasComunidades) {
    $sqlCount = "SELECT COUNT(*)
                 FROM produtores p
                 LEFT JOIN comunidades c
                   ON c.id = p.comunidade_id AND c.feira_id = p.feira_id
                 $whereSql";
  } else {
    $sqlCount = "SELECT COUNT(*)
                 FROM produtores p
                 $whereSql";
  }

  $stCount = $pdo->prepare($sqlCount);
  $stCount->bindValue(':feira', (int)$params[':feira'], PDO::PARAM_INT);

  foreach ($params as $k => $v) {
    if ($k === ':feira') continue;

    if ($k === ':tipo') {
      $stCount->bindValue($k, (string)$v, PDO::PARAM_STR);
    } else {
      $stCount->bindValue($k, (string)$v, PDO::PARAM_STR);
    }
  }

  $stCount->execute();
  $totalRows = (int)$stCount->fetchColumn();

  $totalPages = max(1, (int)ceil($totalRows / $perPage));
  if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
  }

  /* ===== SELECT ===== */
  $campoTipo = $hasTipo ? "p.tipo" : "'PRODUTOR RURAL' AS tipo";

  if ($hasComunidades) {
    $sql = "SELECT
              p.id,
              p.nome,
              {$campoTipo},
              p.contato,
              p.documento,
              p.ativo,
              p.observacao,
              p.comunidade_id,
              c.nome AS comunidade
            FROM produtores p
            LEFT JOIN comunidades c
              ON c.id = p.comunidade_id AND c.feira_id = p.feira_id
            $whereSql
            ORDER BY p.nome ASC
            LIMIT :lim OFFSET :off";
  } else {
    $sql = "SELECT
              p.id,
              p.nome,
              {$campoTipo},
              p.contato,
              p.documento,
              p.ativo,
              p.observacao,
              p.comunidade_id,
              NULL AS comunidade
            FROM produtores p
            $whereSql
            ORDER BY p.nome ASC
            LIMIT :lim OFFSET :off";
  }

  $stmt = $pdo->prepare($sql);
  $stmt->bindValue(':feira', (int)$params[':feira'], PDO::PARAM_INT);

  foreach ($params as $k => $v) {
    if ($k === ':feira') continue;
    $stmt->bindValue($k, (string)$v, PDO::PARAM_STR);
  }

  $stmt->bindValue(':lim', (int)$perPage, PDO::PARAM_INT);
  $stmt->bindValue(':off', (int)$offset, PDO::PARAM_INT);
  $stmt->execute();

  $produtores = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
  $err = $err ?: 'Não foi possível carregar os produtores agora.';
  $errDetail = $cleanErr($e->getMessage());
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <title>Impressão de Produtores</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      background: #f6f8fb;
    }
    .page-card {
      border: 0;
      border-radius: 16px;
      box-shadow: 0 10px 30px rgba(0,0,0,.06);
    }
    .table thead th {
      white-space: nowrap;
      font-size: .9rem;
    }
    .table td, .table th {
      vertical-align: middle;
    }
    .acoes-topo {
      gap: .5rem;
    }
    .check-col {
      width: 46px;
      text-align: center;
    }
    .nome-col {
      min-width: 260px;
    }
    .status-col {
      width: 110px;
    }
    .tipo-col {
      min-width: 160px;
    }
    .muted-mini {
      font-size: .86rem;
      color: #6c757d;
    }
    .toolbar-box {
      background: #fff;
      border: 1px solid #e9ecef;
      border-radius: 14px;
      padding: 1rem;
    }
  </style>
</head>
<body>
  <div class="container py-4 py-lg-5">
    <div class="d-flex flex-wrap align-items-center justify-content-between mb-3 gap-2">
      <div>
        <h1 class="h3 mb-1">Impressão da Lista de Produtores</h1>
        <div class="muted-mini">
          Selecione <strong>todos</strong>, <strong>um</strong> ou <strong>vários produtores</strong> para gerar a impressão.
        </div>
      </div>

      <div class="d-flex flex-wrap acoes-topo">
        <a href="./listaProdutor.php" class="btn btn-outline-secondary">
          <i class="bi bi-arrow-left me-1"></i>Voltar
        </a>
      </div>
    </div>

    <?php if ($msg !== ''): ?>
      <div class="alert alert-success alert-dismissible fade show" role="alert">
        <?= h($msg) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <?php if ($err !== ''): ?>
      <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <?= h($err) ?>
        <?php if ($errDetail !== ''): ?>
          <hr>
          <div class="small"><?= h($errDetail) ?></div>
        <?php endif; ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      </div>
    <?php endif; ?>

    <div class="card page-card">
      <div class="card-body p-3 p-lg-4">

        <form method="get" class="toolbar-box mb-3">
          <div class="row g-2 align-items-end">
            <div class="col-12 col-md-6 col-lg-6">
              <label for="q" class="form-label mb-1">Buscar produtor</label>
              <input
                type="text"
                name="q"
                id="q"
                class="form-control"
                value="<?= h($qRaw) ?>"
                placeholder="Digite nome, contato, CPF ou comunidade">
            </div>

            <div class="col-12 col-md-3 col-lg-3">
              <label for="tipo" class="form-label mb-1">Tipo</label>
              <select name="tipo" id="tipo" class="form-select">
                <option value="TODOS" <?= $tipoRaw === 'TODOS' ? 'selected' : '' ?>>Todos</option>
                <option value="PRODUTOR RURAL" <?= $tipoRaw === 'PRODUTOR RURAL' ? 'selected' : '' ?>>Produtor Rural</option>
                <option value="FEIRANTE" <?= $tipoRaw === 'FEIRANTE' ? 'selected' : '' ?>>Feirante</option>
                <option value="MARRETEIRO" <?= $tipoRaw === 'MARRETEIRO' ? 'selected' : '' ?>>Marreteiro</option>
              </select>
            </div>

            <div class="col-6 col-md-1 col-lg-1">
              <label class="form-label mb-1 d-none d-md-block">&nbsp;</label>
              <button type="submit" class="btn btn-primary w-100">
                <i class="bi bi-search me-1"></i>Buscar
              </button>
            </div>

            <div class="col-6 col-md-2 col-lg-2">
              <label class="form-label mb-1 d-none d-md-block">&nbsp;</label>
              <a href="<?= h(buildUrl(['q' => null, 'tipo' => null, 'p' => null])) ?>" class="btn btn-outline-secondary w-100">
                <i class="bi bi-x-circle me-1"></i>Limpar
              </a>
            </div>
          </div>
        </form>

        <form method="post" action="./imprimirProdutores.php" id="formImpressao">
          <input type="hidden" name="csrf_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="feira_id" value="<?= (int)$FEIRA_ID ?>">
          <input type="hidden" name="q" value="<?= h($qRaw) ?>">
          <input type="hidden" name="tipo" value="<?= h($tipoRaw) ?>">

          <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div class="d-flex flex-wrap gap-2">
              <button type="button" class="btn btn-outline-primary btn-sm" id="btnMarcarPagina">
                <i class="bi bi-check2-square me-1"></i>Marcar página
              </button>

              <button type="button" class="btn btn-outline-secondary btn-sm" id="btnDesmarcarPagina">
                <i class="bi bi-square me-1"></i>Desmarcar página
              </button>

              <button type="button" class="btn btn-outline-dark btn-sm" id="btnToggleTodos">
                <i class="bi bi-list-check me-1"></i>Selecionar todos os resultados
              </button>
            </div>

            <div class="text-muted small">
              Total encontrado: <strong><?= (int)$totalRows ?></strong>
            </div>
          </div>

          <div class="table-responsive">
            <table class="table table-hover align-middle">
              <thead class="table-light">
                <tr>
                  <th class="check-col">
                    <input type="checkbox" class="form-check-input" id="checkAllVisiveis">
                  </th>
                  <th class="nome-col">Produtor</th>
                  <th class="tipo-col">Tipo</th>
                  <th>Contato</th>
                  <th>Documento</th>
                  <th>Comunidade</th>
                  <th class="status-col">Status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$produtores): ?>
                  <tr>
                    <td colspan="7" class="text-center py-4 text-muted">
                      Nenhum produtor encontrado.
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($produtores as $p): ?>
                    <tr>
                      <td class="text-center">
                        <input
                          type="checkbox"
                          class="form-check-input produtor-check"
                          name="produtores[]"
                          value="<?= (int)$p['id'] ?>">
                      </td>

                      <td>
                        <div class="fw-semibold"><?= h((string)$p['nome']) ?></div>
                        <?php if (!empty($p['observacao'])): ?>
                          <div class="text-muted small"><?= h((string)$p['observacao']) ?></div>
                        <?php endif; ?>
                      </td>

                      <td><?= h((string)($p['tipo'] ?? 'PRODUTOR RURAL')) ?></td>
                      <td><?= h((string)($p['contato'] ?? '')) ?></td>
                      <td><?= h((string)($p['documento'] ?? '')) ?></td>
                      <td><?= h((string)($p['comunidade'] ?? '')) ?></td>
                      <td>
                        <?php if ((int)$p['ativo'] === 1): ?>
                          <span class="badge text-bg-success">Ativo</span>
                        <?php else: ?>
                          <span class="badge text-bg-secondary">Inativo</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mt-3">
            <div class="small text-muted">
              Selecionados: <strong id="contadorSelecionados">0</strong>
            </div>

            <div class="d-flex flex-wrap gap-2">
              <button type="submit" name="modo" value="selecionados" class="btn btn-primary">
                <i class="bi bi-printer me-1"></i>Imprimir selecionados
              </button>

              <button type="submit" name="modo" value="todos" class="btn btn-outline-dark">
                <i class="bi bi-printer-fill me-1"></i>Imprimir todos filtrados
              </button>
            </div>
          </div>
        </form>

        <?php if ($totalPages > 1): ?>
          <nav class="mt-4">
            <ul class="pagination justify-content-center flex-wrap">
              <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= h(buildUrl(['p' => max(1, $page - 1)])) ?>">Anterior</a>
              </li>

              <?php
              $inicio = max(1, $page - 2);
              $fim = min($totalPages, $page + 2);

              for ($i = $inicio; $i <= $fim; $i++):
              ?>
                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                  <a class="page-link" href="<?= h(buildUrl(['p' => $i])) ?>"><?= $i ?></a>
                </li>
              <?php endfor; ?>

              <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                <a class="page-link" href="<?= h(buildUrl(['p' => min($totalPages, $page + 1)])) ?>">Próxima</a>
              </li>
            </ul>
          </nav>
        <?php endif; ?>

      </div>
    </div>
  </div>

  <script>
    (function () {
      const form = document.getElementById('formImpressao');
      const checks = () => Array.from(document.querySelectorAll('.produtor-check'));
      const contador = document.getElementById('contadorSelecionados');
      const checkAllVisiveis = document.getElementById('checkAllVisiveis');
      const btnMarcarPagina = document.getElementById('btnMarcarPagina');
      const btnDesmarcarPagina = document.getElementById('btnDesmarcarPagina');
      const btnToggleTodos = document.getElementById('btnToggleTodos');

      function atualizarContador() {
        const total = checks().filter(el => el.checked).length;
        contador.textContent = String(total);

        const todos = checks();
        const marcados = todos.filter(el => el.checked).length;
        checkAllVisiveis.checked = (todos.length > 0 && marcados === todos.length);
        checkAllVisiveis.indeterminate = (marcados > 0 && marcados < todos.length);
      }

      checkAllVisiveis?.addEventListener('change', function () {
        checks().forEach(el => { el.checked = this.checked; });
        atualizarContador();
      });

      btnMarcarPagina?.addEventListener('click', function () {
        checks().forEach(el => { el.checked = true; });
        atualizarContador();
      });

      btnDesmarcarPagina?.addEventListener('click', function () {
        checks().forEach(el => { el.checked = false; });
        atualizarContador();
      });

      btnToggleTodos?.addEventListener('click', function () {
        const todos = checks();
        const totalMarcados = todos.filter(el => el.checked).length;
        const marcar = totalMarcados !== todos.length;
        todos.forEach(el => { el.checked = marcar; });
        atualizarContador();
      });

      document.addEventListener('change', function (e) {
        if (e.target && e.target.classList.contains('produtor-check')) {
          atualizarContador();
        }
      });

      form?.addEventListener('submit', function (e) {
        const btn = document.activeElement;
        const modo = btn && btn.name === 'modo' ? btn.value : '';

        if (modo === 'selecionados') {
          const total = checks().filter(el => el.checked).length;
          if (total === 0) {
            e.preventDefault();
            alert('Selecione pelo menos um produtor para imprimir.');
            return false;
          }
        }
      });

      atualizarContador();
    })();
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>