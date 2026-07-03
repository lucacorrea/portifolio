<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

/** ===== DB helper (db():PDO OU $pdo) ===== */
function pdo_conn(): PDO {
  $con1 = __DIR__ . '/assets/php/conexao.php';
  if (file_exists($con1)) {
    require_once $con1;
    if (function_exists('db')) {
      $pdo = db();
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      return $pdo;
    }
  }

  $con2 = __DIR__ . '/assets/conexao.php';
  if (file_exists($con2)) {
    require_once $con2;
    if (isset($pdo) && $pdo instanceof PDO) {
      $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
      return $pdo;
    }
  }

  throw new RuntimeException('Conexão com o banco não encontrada.');
}

function h($v): string { return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8'); }
function only_digits(?string $s): string { return preg_replace('/\D+/', '', (string)$s); }

function norm_key(string $s): string {
  $s = trim($s);
  $s = preg_replace('/\s+/', ' ', $s);
  if (function_exists('mb_strtolower')) $s = mb_strtolower($s, 'UTF-8'); else $s = strtolower($s);
  $t = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
  if ($t !== false) $s = $t;
  $s = preg_replace('/[^a-z0-9 ]+/', ' ', $s);
  $s = preg_replace('/\s+/', ' ', trim($s));
  return $s;
}

/** Regras (pode ajustar depois) */
function rules(): array {
  return [
    ['nome' => 'CESTA BÁSICA',                 'rx' => '/\b(cesta|alimento|rancho)\b/i'],
    ['nome' => 'ASSUNTO PARTICULAR',           'rx' => '/\b(assunto particular|motivo particular)\b/i'],
    ['nome' => 'ALUGUEL',                      'rx' => '/\b(aluguel|aluga|alugado|alugada)\b/i'],
    ['nome' => 'LUZ',                          'rx' => '/\b(luz|energia|eletric)\b/i'],
    ['nome' => 'REMÉDIO',                      'rx' => '/\b(remedio|rem[eé]dio|medic|farm[aá]cia)\b/i'],
    ['nome' => 'EXAMES',                       'rx' => '/\b(exame|ultrassom|raio[\s-]?x|tomografia|resson[aâ]ncia)\b/i'],
    ['nome' => 'COMBUSTÍVEL',                  'rx' => '/\b(combust[ií]vel|gasolina|diesel)\b/i'],
    ['nome' => 'PASSAGEM',                     'rx' => '/\b(passagem|passagens|viagem)\b/i'],
    ['nome' => 'AJUDA DE CUSTO',               'rx' => '/\b(ajuda de custo)\b/i'],
    ['nome' => 'DENTADURA',                    'rx' => '/\b(dentadur|pr[oó]tese|dente)\b/i'],
    ['nome' => 'CIRURGIA',                     'rx' => '/\b(cirurg|opera[cç][aã]o|procedimento)\b/i'],
    ['nome' => 'APARELHO DE AUDIÇÃO',          'rx' => '/\b(auditivo|audi[cç][aã]o|aparelho)\b/i'],
    ['nome' => 'EMPREGO',                      'rx' => '/\b(emprego|trabalh|vaga|curricul|sine)\b/i'],
    ['nome' => 'TERRENO',                      'rx' => '/\b(terreno|lote)\b/i'],
    ['nome' => 'REFORMA',                      'rx' => '/\b(reforma|reformar)\b/i'],
    ['nome' => 'MATERIAL PARA CONSTRUÇÃO OU REFORMA', 'rx' => '/\b(material|cimento|tijolo|areia|seixo|telha|ferro|madeira|tinta)\b/i'],
    ['nome' => 'CASA',                         'rx' => '/\b(constru|construir|fazer (uma )?casa)\b/i'],
    ['nome' => 'PONTO COMERCIAL',              'rx' => '/\b(ponto comercial)\b/i'],
  ];
}

function suggest_tipo_id(string $text, array $tipoMap): array {
  $hits = [];
  foreach (rules() as $r) {
    if (preg_match($r['rx'], $text)) {
      $k = norm_key($r['nome']);
      if (isset($tipoMap[$k])) $hits[] = $tipoMap[$k];
    }
  }
  $hits = array_values(array_unique($hits));
  if (count($hits) === 1) return [$hits[0], $hits];
  return [null, $hits];
}

$pdo = pdo_conn();

/** CSRF */
if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(16));
$csrf = $_SESSION['csrf'];

/** Tipos */
$tipos = $pdo->query("SELECT id, nome, categoria, status FROM ajudas_tipos ORDER BY nome")
  ->fetchAll(PDO::FETCH_ASSOC) ?: [];
$tipoMap = [];
foreach ($tipos as $t) $tipoMap[norm_key((string)$t['nome'])] = (int)$t['id'];

/** Stats topo */
$stats = $pdo->query("
  SELECT
    COUNT(*) AS total,
    SUM(CASE WHEN ajuda_tipo_id IS NULL OR ajuda_tipo_id=0 THEN 1 ELSE 0 END) AS sem_tipo,
    SUM(CASE WHEN ajuda_tipo_id IS NOT NULL AND ajuda_tipo_id<>0 THEN 1 ELSE 0 END) AS com_tipo
  FROM solicitantes
")->fetch(PDO::FETCH_ASSOC) ?: ['total'=>0,'sem_tipo'=>0,'com_tipo'=>0];

/** Filtros */
$q      = trim((string)($_GET['q'] ?? ''));
$dt_ini = trim((string)($_GET['dt_ini'] ?? ''));
$dt_fim = trim((string)($_GET['dt_fim'] ?? ''));
$modo   = (string)($_GET['modo'] ?? 'todos'); // todos | com_sugestao | sem_sugestao

$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$offset = ($page - 1) * $perPage;

$where = [];
$params = [];

/** apenas sem tipo */
$where[] = "(s.ajuda_tipo_id IS NULL OR s.ajuda_tipo_id=0)";

if ($q !== '') {
  $qd = only_digits($q);
  if ($qd !== '' && strlen($qd) >= 6) {
    $where[] = "(REPLACE(REPLACE(REPLACE(REPLACE(s.cpf,'.',''),'-',''),' ',''),'/','') LIKE :cpf OR s.nome LIKE :nome)";
    $params[':cpf'] = '%' . $qd . '%';
    $params[':nome'] = '%' . $q . '%';
  } else {
    $where[] = "s.nome LIKE :nome";
    $params[':nome'] = '%' . $q . '%';
  }
}
if ($dt_ini !== '') { $where[] = "DATE(s.hora_cadastro) >= :dt_ini"; $params[':dt_ini'] = $dt_ini; }
if ($dt_fim !== '') { $where[] = "DATE(s.hora_cadastro) <= :dt_fim"; $params[':dt_fim'] = $dt_fim; }

$whereSql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

$stmtCount = $pdo->prepare("SELECT COUNT(*) FROM solicitantes s $whereSql");
$stmtCount->execute($params);
$totalRows = (int)$stmtCount->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

$stmt = $pdo->prepare("
  SELECT s.id, s.nome, s.cpf, s.telefone, s.bairro_id, s.tipificacao, s.resumo_caso, s.hora_cadastro, s.responsavel
  FROM solicitantes s
  $whereSql
  ORDER BY s.hora_cadastro DESC, s.id DESC
  LIMIT $perPage OFFSET $offset
");
$stmt->execute($params);
$rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

/** aplica modo com/sem sugestão na página atual */
$out = [];
foreach ($rows as $r) {
  $txt = trim((string)($r['tipificacao'] ?? '') . ' ' . (string)($r['resumo_caso'] ?? ''));
  [$sugId, $hits] = suggest_tipo_id($txt, $tipoMap);
  $r['_sug_id'] = $sugId;
  $r['_hits'] = $hits;
  if ($modo === 'com_sugestao' && !$sugId) continue;
  if ($modo === 'sem_sugestao' && $sugId) continue;
  $out[] = $r;
}
$rows = $out;

function page_link(int $p, array $q): string { $q['page'] = $p; return '?' . http_build_query($q); }

$queryBase = ['q'=>$q,'dt_ini'=>$dt_ini,'dt_fim'=>$dt_fim,'modo'=>$modo];
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Revisar Tipo de Ajuda</title>

  <!-- Se seu template já tem Bootstrap/CSS, pode remover os CDNs abaixo -->
  <link rel="preconnect" href="https://cdn.jsdelivr.net">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">

  <style>
    :root{
      --bg: #f6f7fb;
      --card: #ffffff;
      --text: #1f2937;
      --muted: #6b7280;
      --line: rgba(0,0,0,.08);
      --shadow: 0 10px 25px rgba(16, 24, 40, .08);
      --radius: 16px;
      --primary: #231475;
      --primary2: #3a27b5;
      --success: #19a974;
      --warning: #f59e0b;
      --info: #2563eb;
      --danger: #ef4444;
    }

    body{ background: var(--bg); color: var(--text); }

    .wrap { max-width: 1400px; margin: 0 auto; }

    .card-soft{
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: var(--radius);
      box-shadow: var(--shadow);
    }

    .topbar{
      background: linear-gradient(135deg, var(--primary), var(--primary2));
      color:#fff;
      border-radius: var(--radius);
      padding: 16px 18px;
      box-shadow: var(--shadow);
      border: 1px solid rgba(255,255,255,.12);
    }

    .kpi{
      display:flex; gap:10px; flex-wrap:wrap; align-items:center;
    }

    .kpi .pill{
      background: rgba(255,255,255,.16);
      border: 1px solid rgba(255,255,255,.18);
      padding: 6px 10px;
      border-radius: 999px;
      font-size: 12px;
      white-space: nowrap;
    }

    .kpi .pill b{ color:#fff; }

    .btn-primary{
      background: var(--primary);
      border-color: var(--primary);
    }
    .btn-primary:hover{ background: #1c0f61; border-color:#1c0f61; }

    .btn-soft{
      background: rgba(35,20,117,.08);
      border: 1px solid rgba(35,20,117,.18);
      color: var(--primary);
    }
    .btn-soft:hover{ background: rgba(35,20,117,.12); }

    .muted{ color: var(--muted); }
    .smalltxt{ font-size: 12px; }

    .filters .form-control{
      border-radius: 12px;
      border: 1px solid var(--line);
      background: #fff;
    }
    .filters .btn{
      border-radius: 12px;
      font-weight: 600;
    }

    .table-wrap{
      border-radius: 14px;
      overflow: hidden;
      border: 1px solid var(--line);
    }

    .table{
      margin:0;
      background:#fff;
    }

    .table thead th{
      position: sticky;
      top: 0;
      z-index: 2;
      background: #f8fafc;
      border-bottom: 1px solid var(--line) !important;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .04em;
      color: #374151;
      white-space: nowrap;
    }

    .table tbody td{
      vertical-align: middle;
      border-top: 1px solid rgba(0,0,0,.05);
    }

    .nowrap{ white-space: nowrap; }

    .case{
      max-width: 560px;
      font-size: 12px;
      line-height: 1.35;
      color: #111827;
    }

    .case .label{
      display:inline-block;
      font-weight:700;
      margin-right:6px;
      color:#374151;
    }

    .case .text{
      display:block;
      color:#111827;
    }

    .case .clamp{
      display:-webkit-box;
      -webkit-line-clamp: 3;
      -webkit-box-orient: vertical;
      overflow: hidden;
    }

    .badge-soft{
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 700;
      font-size: 11px;
      border: 1px solid transparent;
      white-space: nowrap;
      display:inline-flex;
      align-items:center;
      gap:6px;
    }
    .badge-info-soft{ background: rgba(37,99,235,.08); color: var(--info); border-color: rgba(37,99,235,.18); }
    .badge-warn-soft{ background: rgba(245,158,11,.10); color: #92400e; border-color: rgba(245,158,11,.25); }
    .badge-danger-soft{ background: rgba(239,68,68,.08); color: var(--danger); border-color: rgba(239,68,68,.18); }
    .badge-ok-soft{ background: rgba(25,169,116,.10); color: var(--success); border-color: rgba(25,169,116,.22); }

    .tipoSelect{
      min-width: 240px;
      border-radius: 12px;
      border: 1px solid var(--line);
    }

    .btn-save{
      border-radius: 12px;
      font-weight: 700;
      padding: 8px 12px;
    }

    .row-done{
      opacity:.38;
      filter: grayscale(1);
    }

    .pagination .page-link{
      border-radius: 12px !important;
      margin: 2px;
      border: 1px solid var(--line);
      color: #374151;
    }
    .pagination .page-item.active .page-link{
      background: var(--primary);
      border-color: var(--primary);
      color:#fff;
    }

    /* Mobile */
    @media (max-width: 576px){
      .topbar{ padding: 14px 14px; }
      .tipoSelect{ min-width: 180px; }
      .case{ max-width: 86vw; }
      .btn-block-mobile{ width:100%; }
      .table thead th{ font-size: 11px; }
    }
  </style>
</head>

<body>
  <div class="container-fluid py-3">
    <div class="wrap">

      <div class="topbar mb-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
          <div class="mb-2">
            <div class="h5 mb-1" style="font-weight:800;">Revisão de Tipo de Ajuda</div>
            <div class="kpi">
              <span class="pill">Total: <b><?= (int)$stats['total'] ?></b></span>
              <span class="pill">Sem tipo: <b><?= (int)$stats['sem_tipo'] ?></b></span>
              <span class="pill">Com tipo: <b><?= (int)$stats['com_tipo'] ?></b></span>
              <span class="pill">Página: <b><?= $page ?></b>/<b><?= $totalPages ?></b></span>
            </div>
          </div>

          <div class="mb-2">
            <button id="btnAutofill" class="btn btn-light btn-sm btn-block-mobile" style="border-radius:12px;font-weight:800;">
              Rodar autopreenchimento (lote)
            </button>
          </div>
        </div>
      </div>

      <div class="card-soft mb-3">
        <div class="card-body">

          <form class="filters row" method="get">
            <div class="col-12 col-md-4 mb-2">
              <input type="text" name="q" value="<?= h($q) ?>" class="form-control" placeholder="Buscar por Nome ou CPF">
            </div>
            <div class="col-6 col-md-2 mb-2">
              <input type="date" name="dt_ini" value="<?= h($dt_ini) ?>" class="form-control">
            </div>
            <div class="col-6 col-md-2 mb-2">
              <input type="date" name="dt_fim" value="<?= h($dt_fim) ?>" class="form-control">
            </div>
            <div class="col-12 col-md-2 mb-2">
              <select name="modo" class="form-control">
                <option value="todos" <?= $modo==='todos'?'selected':'' ?>>Todos</option>
                <option value="com_sugestao" <?= $modo==='com_sugestao'?'selected':'' ?>>Com sugestão</option>
                <option value="sem_sugestao" <?= $modo==='sem_sugestao'?'selected':'' ?>>Sem sugestão</option>
              </select>
            </div>
            <div class="col-12 col-md-2 mb-2">
              <button class="btn btn-primary btn-block btn-block-mobile">Filtrar</button>
            </div>
          </form>

          <div class="d-flex flex-wrap justify-content-between align-items-center mt-2">
            <div class="smalltxt muted">
              Encontrados: <b><?= $totalRows ?></b>
              <?php if ($q||$dt_ini||$dt_fim||$modo!=='todos'): ?>
                • <a class="text-decoration-none" href="?">limpar filtros</a>
              <?php endif; ?>
            </div>

            <div class="smalltxt muted">
              Dica: clique em <b>Salvar</b> e o foco vai para o próximo registro.
            </div>
          </div>

        </div>
      </div>

      <div class="card-soft">
        <div class="card-body">

          <div class="table-responsive table-wrap">
            <table class="table table-sm">
              <thead>
                <tr>
                  <th class="nowrap">ID</th>
                  <th class="nowrap">Nome</th>
                  <th class="nowrap">CPF</th>
                  <th class="nowrap">Data</th>
                  <th class="nowrap">Responsável pelo Cadastro</th>
                  <th>Resumo / Tipificação</th>
                  <th class="nowrap">Tipo de Ajuda</th>
                  <th class="nowrap">Ação</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$rows): ?>
                  <tr>
                    <td colspan="7" class="text-center py-4 muted">
                      Nada para revisar com esses filtros.
                    </td>
                  </tr>
                <?php endif; ?>

                <?php foreach ($rows as $r): ?>
                  <?php
                    $id = (int)$r['id'];
                    $txt = trim((string)($r['tipificacao'] ?? '') . ' ' . (string)($r['resumo_caso'] ?? ''));
                    $sugId = (int)($r['_sug_id'] ?? 0);
                    $hits = $r['_hits'] ?? [];
                    $amb = is_array($hits) ? count($hits) : 0;
                  ?>
                  <tr id="row-<?= $id ?>" class="<?= $sugId ? '' : '' ?>">
                    <td class="nowrap"><?= $id ?></td>
                    <td class="nowrap"><?= h($r['nome']) ?></td>
                    <td class="nowrap"><?= h($r['cpf']) ?></td>
                    <td class="nowrap"><?= h($r['hora_cadastro']) ?></td>
                    <td class="nowrap"><?= h($r['responsavel'] ?? 'Não informado') ?></td>

                    <td>
                      <div class="case">
                        <?php if (!empty($r['tipificacao'])): ?>
                          <div class="mb-1">
                            <span class="label">Tipificação:</span>
                            <span class="text clamp" data-clamp><?= h($r['tipificacao']) ?></span>
                          </div>
                        <?php endif; ?>

                        <?php if (!empty($r['resumo_caso'])): ?>
                          <div>
                            <span class="label">Resumo:</span>
                            <span class="text clamp" data-clamp><?= h($r['resumo_caso']) ?></span>
                          </div>
                        <?php endif; ?>

                        <div class="mt-2">
                          <?php if ($sugId): ?>
                            <span class="badge-soft badge-info-soft">Sugestão automática</span>
                          <?php elseif ($amb > 1): ?>
                            <span class="badge-soft badge-warn-soft">Ambíguo (<?= $amb ?>)</span>
                          <?php else: ?>
                            <span class="badge-soft badge-danger-soft">Sem sugestão</span>
                          <?php endif; ?>

                          <?php if (mb_strlen($txt) > 120): ?>
                            <button type="button" class="btn btn-sm btn-soft ml-2 btnToggleText" data-id="<?= $id ?>" style="padding:4px 10px;">
                              Ver mais
                            </button>
                          <?php endif; ?>
                        </div>
                      </div>
                    </td>

                    <td class="nowrap">
                      <select class="form-control form-control-sm tipoSelect" data-id="<?= $id ?>">
                        <option value="">— selecione —</option>
                        <?php foreach ($tipos as $t): ?>
                          <?php $tid=(int)$t['id']; ?>
                          <option value="<?= $tid ?>" <?= ($sugId && $tid===$sugId) ? 'selected' : '' ?>>
                            <?= h($t['nome']) ?>
                          </option>
                        <?php endforeach; ?>
                      </select>
                      <div class="smalltxt muted mt-1">
                        <?php if ($sugId): ?>pré-selecionado pela sugestão<?php else: ?>&nbsp;<?php endif; ?>
                      </div>
                    </td>

                    <td class="nowrap">
                      <button class="btn btn-sm btn-success btn-save btnSalvar" data-id="<?= $id ?>">Salvar</button>
                    </td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>

          <div class="d-flex flex-wrap justify-content-between align-items-center mt-3">
            <div class="smalltxt muted">
              Mostrando <b><?= count($rows) ?></b> registro(s) na tela • limite <b><?= $perPage ?></b> por página
            </div>

            <nav>
              <ul class="pagination pagination-sm mb-0 flex-wrap">
                <li class="page-item <?= $page<=1?'disabled':'' ?>">
                  <a class="page-link" href="<?= h(page_link(max(1,$page-1), $queryBase)) ?>">Anterior</a>
                </li>

                <?php
                  $start = max(1, $page-3);
                  $end = min($totalPages, $page+3);
                  for ($p=$start; $p<=$end; $p++):
                ?>
                  <li class="page-item <?= $p===$page?'active':'' ?>">
                    <a class="page-link" href="<?= h(page_link($p, $queryBase)) ?>"><?= $p ?></a>
                  </li>
                <?php endfor; ?>

                <li class="page-item <?= $page>=$totalPages?'disabled':'' ?>">
                  <a class="page-link" href="<?= h(page_link(min($totalPages,$page+1), $queryBase)) ?>">Próximo</a>
                </li>
              </ul>
            </nav>
          </div>

        </div>
      </div>

    </div>
  </div>

<script>
const csrf = <?= json_encode($csrf) ?>;

function alertMsg(msg){ alert(msg); }

async function postJSON(url, data){
  const res = await fetch(url, {
    method: 'POST',
    headers: {'Content-Type':'application/json'},
    body: JSON.stringify(data)
  });
  return res.json();
}

document.querySelectorAll('.btnSalvar').forEach(btn=>{
  btn.addEventListener('click', async (e)=>{
    e.preventDefault();
    const id = btn.dataset.id;
    const sel = document.querySelector('.tipoSelect[data-id="'+id+'"]');
    const tipo = sel.value;

    if (!tipo) return alertMsg('Selecione um tipo antes de salvar.');

    btn.disabled = true;
    const old = btn.textContent;
    btn.textContent = 'Salvando...';

    try{
      const out = await postJSON('api/revisar_ajuda_tipo_save.php', {csrf, id, tipo});
      if (!out.ok) throw new Error(out.msg || 'Erro ao salvar.');

      const row = document.getElementById('row-'+id);
      row.classList.add('row-done');

      btn.textContent = 'Salvo ✓';

      // foca o próximo select
      const selects = Array.from(document.querySelectorAll('.tipoSelect'));
      const i = selects.indexOf(sel);
      if (i >= 0 && selects[i+1]) selects[i+1].focus();

    }catch(err){
      btn.textContent = old;
      btn.disabled = false;
      alertMsg(err.message);
    }
  });
});

document.getElementById('btnAutofill').addEventListener('click', async ()=>{
  if (!confirm('Rodar autopreenchimento em lote? (faça backup antes!)')) return;
  const btn = document.getElementById('btnAutofill');
  btn.disabled = true;
  const old = btn.textContent;
  btn.textContent = 'Processando...';

  try{
    const out = await postJSON('api/revisar_autofill.php', {csrf});
    if (!out.ok) throw new Error(out.msg || 'Falha no autopreenchimento.');

    alertMsg(
      `Autopreenchimento concluído!\n` +
      `Preenchidos por texto igual: ${out.filled_exact}\n` +
      `Preenchidos por regras: ${out.filled_rules}\n` +
      `Restantes sem tipo: ${out.remaining}`
    );
    location.reload();
  }catch(e){
    btn.disabled = false;
    btn.textContent = old;
    alertMsg(e.message);
  }
});

// Ver mais / Ver menos
document.querySelectorAll('.btnToggleText').forEach(b=>{
  b.addEventListener('click', ()=>{
    const row = b.closest('tr');
    const clamps = row.querySelectorAll('[data-clamp]');
    const isClamped = clamps.length && clamps[0].classList.contains('clamp');
    clamps.forEach(el=>{
      if (isClamped) el.classList.remove('clamp');
      else el.classList.add('clamp');
    });
    b.textContent = isClamped ? 'Ver menos' : 'Ver mais';
  });
});
</script>
</body>
</html>
