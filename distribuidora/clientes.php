<?php

declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/clientes/_helpers.php';

require_db_or_die();
$pdo = db();

/* =========================
   ENDPOINTS JSON (no mesmo arquivo)
========================= */
$action = strtolower(get_str('action'));

function build_where_clientes(array &$params): string
{
    $where = " WHERE 1=1 ";
    $status = strtoupper(get_str('status', 'TODOS'));
    $q = get_str('q', '');

    if ($status !== '' && $status !== 'TODOS') {
        $where .= " AND c.status = :status ";
        $params['status'] = $status;
    }

    if ($q !== '') {
        $qd = only_digits($q);

        // se for número e curto: pode ser ID
        if (ctype_digit($q) && strlen($q) <= 9) {
            $where .= " AND c.id = :id ";
            $params['id'] = (int)$q;
            return $where;
        }

        // se digitou CPF/telefone (números)
        if ($qd !== '') {
            $where .= " AND (c.cpf LIKE :qd OR c.telefone LIKE :qd) ";
            $params['qd'] = '%' . $qd . '%';
        } else {
            $where .= " AND (c.nome LIKE :q OR c.obs LIKE :q) ";
            $params['q'] = '%' . $q . '%';
        }
    }

    return $where;
}

if ($action === 'fetch') {
    $page = max(1, get_int('page', 1));
    $per  = get_int('per', 25);
    $per  = in_array($per, [10, 25, 50, 100], true) ? $per : 25;
    $off  = ($page - 1) * $per;

    $params = [];
    $where = build_where_clientes($params);

    // totais
    $sqlTot = "SELECT
              COUNT(*) AS total,
              SUM(CASE WHEN c.status='ATIVO' THEN 1 ELSE 0 END) AS ativos,
              SUM(CASE WHEN c.status='INATIVO' THEN 1 ELSE 0 END) AS inativos
            FROM clientes c $where";
    $stTot = $pdo->prepare($sqlTot);
    $stTot->execute($params);
    $tot = $stTot->fetch(PDO::FETCH_ASSOC) ?: ['total' => 0, 'ativos' => 0, 'inativos' => 0];

    // lista
    $sql = "SELECT
            c.id, c.nome, c.cpf, c.telefone, c.status, c.obs,
            c.created_at, c.updated_at
          FROM clientes c
          $where
          ORDER BY c.id DESC
          LIMIT $per OFFSET $off";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    // formatações para UI
    $out = [];
    foreach ($rows as $r) {
        $out[] = [
            'id' => (int)$r['id'],
            'nome' => (string)$r['nome'],
            'cpf' => (string)$r['cpf'],
            'cpf_fmt' => cpf_fmt((string)$r['cpf']),
            'telefone' => (string)$r['telefone'],
            'telefone_fmt' => tel_fmt((string)$r['telefone']),
            'status' => (string)$r['status'],
            'obs' => (string)($r['obs'] ?? ''),
            'created_at' => (string)($r['created_at'] ?? ''),
            'updated_at' => (string)($r['updated_at'] ?? ''),
        ];
    }

    $totalCount = (int)($tot['total'] ?? 0);
    $pages = (int)max(1, ceil($totalCount / $per));

    json_out([
        'ok' => true,
        'meta' => [
            'page' => $page,
            'per' => $per,
            'pages' => $pages,
            'total' => $totalCount,
        ],
        'totais' => [
            'total' => $totalCount,
            'ativos' => (int)($tot['ativos'] ?? 0),
            'inativos' => (int)($tot['inativos'] ?? 0),
        ],
        'rows' => $out
    ]);
}

if ($action === 'one') {
    $id = get_int('id', 0);
    if ($id <= 0) json_out(['ok' => false, 'msg' => 'ID inválido'], 400);

    $st = $pdo->prepare("SELECT * FROM clientes WHERE id = :id LIMIT 1");
    $st->execute(['id' => $id]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    if (!$r) json_out(['ok' => false, 'msg' => 'Cliente não encontrado'], 404);

    json_out([
        'ok' => true,
        'data' => [
            'id' => (int)$r['id'],
            'nome' => (string)$r['nome'],
            'cpf' => (string)$r['cpf'],
            'cpf_fmt' => cpf_fmt((string)$r['cpf']),
            'telefone' => (string)$r['telefone'],
            'telefone_fmt' => tel_fmt((string)$r['telefone']),
            'status' => (string)$r['status'],
            'obs' => (string)($r['obs'] ?? ''),
            'created_at' => (string)($r['created_at'] ?? ''),
            'updated_at' => (string)($r['updated_at'] ?? ''),
        ]
    ]);
}

if ($action === 'suggest') {
    $q = get_str('q', '');
    if (mb_strlen($q) < 2) json_out(['ok' => true, 'items' => []]);

    $qd = only_digits($q);

    if ($qd !== '') {
        $st = $pdo->prepare("
      SELECT id, nome, cpf, telefone
      FROM clientes
      WHERE cpf LIKE :q OR telefone LIKE :q
      ORDER BY nome
      LIMIT 10
    ");
        $st->execute(['q' => $qd . '%']);
    } else {
        $st = $pdo->prepare("
      SELECT id, nome, cpf, telefone
      FROM clientes
      WHERE nome LIKE :q
      ORDER BY nome
      LIMIT 10
    ");
        $st->execute(['q' => $q . '%']);
    }

    $items = [];
    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $items[] = [
            'id' => (int)$r['id'],
            'nome' => (string)$r['nome'],
            'cpf_fmt' => cpf_fmt((string)$r['cpf']),
            'telefone_fmt' => tel_fmt((string)$r['telefone']),
        ];
    }
    json_out(['ok' => true, 'items' => $items]);
}

/* =========================
   HTML
========================= */
$csrf = csrf_token();
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="<?= e($csrf) ?>">

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Clientes</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <style>
        .main-btn.btn-compact {
            height: 36px !important;
            padding: 8px 12px !important;
            font-size: 13px !important;
            line-height: 1 !important
        }

        .form-control.compact,
        .form-select.compact {
            height: 38px;
            padding: 8px 12px;
            font-size: 13px
        }

        .section {
            padding-top: 18px
        }

        .page-pad {
            padding-top: 8px
        }

        .cardx {
            border: 1px solid rgba(148, 163, 184, .24);
            border-radius: 16px;
            background: #fff;
            overflow: hidden
        }

        .cardx .head {
            padding: 12px 14px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
            flex-wrap: wrap
        }

        .cardx .body {
            padding: 14px
        }

        .muted {
            font-size: 12px;
            color: #64748b
        }

        .pill {
            padding: 6px 10px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, .22);
            font-weight: 900;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(248, 250, 252, .7);
            white-space: nowrap
        }

        .pill.ok {
            border-color: rgba(34, 197, 94, .25);
            background: rgba(240, 253, 244, .9);
            color: #166534
        }

        .pill.warn {
            border-color: rgba(245, 158, 11, .25);
            background: rgba(255, 251, 235, .95);
            color: #92400e
        }

        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end
        }

        .table-wrap {
            overflow: auto;
            border-radius: 14px
        }

        #tbClientes {
            width: 100%;
            min-width: 980px;
            table-layout: fixed
        }

        #tbClientes thead th {
            position: sticky;
            top: 0;
            z-index: 2;
            background: #f8fafc;
            border-bottom: 1px solid rgba(148, 163, 184, .25);
            font-size: 12px;
            color: #0f172a;
            padding: 10px 10px;
            white-space: nowrap
        }

        #tbClientes tbody td {
            border-top: 1px solid rgba(148, 163, 184, .18);
            padding: 10px 10px;
            font-size: 13px;
            vertical-align: middle;
            color: #0f172a;
            background: #fff
        }

        .page-nav {
            display: flex;
            gap: 8px;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            margin-top: 10px;
            padding-top: 6px
        }

        .page-btn {
            border: 1px solid rgba(148, 163, 184, .35);
            background: #fff;
            border-radius: 10px;
            padding: 8px 10px;
            font-weight: 900;
            font-size: 12px;
            cursor: pointer
        }

        .page-btn[disabled] {
            opacity: .55;
            cursor: not-allowed
        }

        .page-info {
            font-size: 12px;
            color: #64748b;
            font-weight: 900
        }

        .col-id {
            width: 70px
        }

        .col-nome {
            width: 320px
        }

        .col-cpf {
            width: 150px
        }

        .col-tel {
            width: 170px
        }

        .col-status {
            width: 120px
        }

        .col-created {
            width: 170px
        }

        .col-acoes {
            width: 260px
        }

        .td-nowrap {
            white-space: nowrap
        }

        .td-clip {
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            display: block;
            max-width: 100%
        }

        .mini {
            font-size: 12px;
            color: #475569;
            font-weight: 800
        }

        .badge-soft {
            font-weight: 1000;
            border-radius: 999px;
            padding: 6px 10px;
            font-size: 11px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            white-space: nowrap;
            border: 1px solid transparent
        }

        .b-ativo {
            background: rgba(240, 253, 244, .95);
            color: #166534;
            border-color: rgba(34, 197, 94, .25)
        }

        .b-inativo {
            background: rgba(241, 245, 249, .9);
            color: #334155;
            border-color: rgba(148, 163, 184, .28)
        }

        .actions-wrap {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: flex-start
        }

        .btn-action {
            height: 34px !important;
            padding: 8px 10px !important;
            font-size: 12px !important;
            border-radius: 10px !important;
            white-space: nowrap
        }

        .search-wrap {
            position: relative
        }

        .suggest {
            position: absolute;
            top: calc(100% + 6px);
            left: 0;
            right: 0;
            background: #fff;
            border: 1px solid rgba(148, 163, 184, .25);
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 18px 40px rgba(2, 6, 23, .10);
            display: none;
            z-index: 15;
            max-height: 240px;
            overflow: auto
        }

        .suggest .it {
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid rgba(148, 163, 184, .14);
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            gap: 10px;
            align-items: center
        }

        .suggest .it:hover {
            background: rgba(241, 245, 249, .8)
        }

        .suggest .it:last-child {
            border-bottom: none
        }

        .suggest .nm {
            font-weight: 900;
            color: #0f172a;
            min-width: 0
        }

        .suggest .meta {
            font-size: 12px;
            color: #64748b;
            white-space: nowrap
        }

        @media(max-width:991.98px) {
            #tbClientes {
                min-width: 940px
            }
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- SIDEBAR (mantido como seu template; você pode colar o seu inteiro aqui) -->
    <aside class="sidebar-nav-wrapper">
        <div class="navbar-logo">
            <a href="dashboard.php" class="d-flex align-items-center gap-2">
                <img src="assets/images/logo/logo.svg" alt="logo" />
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item"><a href="dashboard.php"><span class="text">Dashboard</span></a></li>
                <li class="nav-item nav-item-has-children active">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros" aria-expanded="true">
                        <span class="text">Cadastros</span>
                    </a>
                    <ul id="ddmenu_cadastros" class="collapse dropdown-nav active show">
                        <li><a href="clientes.php" class="active">Clientes</a></li>
                        <li><a href="fornecedores.php">Fornecedores</a></li>
                        <li><a href="categorias.php">Categorias</a></li>
                    </ul>
                </li>
                <li class="nav-item"><a href="suporte.php"><span class="text">Suporte</span></a></li>
            </ul>
        </nav>
    </aside>

    <div class="overlay"></div>

    <main class="main-wrapper">
        <header class="header">
            <div class="container-fluid">
                <div class="row align-items-center">
                    <div class="col-6">
                        <div class="header-left d-flex align-items-center">
                            <div class="menu-toggle-btn mr-20">
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-sm">
                                    <i class="lni lni-menu"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="col-6 text-end">
                        <span class="muted">Clientes</span>
                    </div>
                </div>
            </div>
        </header>

        <section class="section">
            <div class="container-fluid page-pad">

                <!-- FILTROS -->
                <div class="cardx mb-3">
                    <div class="head">
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="pill ok" id="pillCount">0 clientes</span>
                                <span class="muted" id="lblRange">—</span>
                            </div>
                            <div class="muted mt-1">
                                Nome/CPF/Telefone obrigatórios • CPF único • (dados do banco)
                            </div>
                        </div>

                        <div class="toolbar">
                            <button class="main-btn primary-btn btn-hover btn-compact" id="btnNovo">
                                <i class="lni lni-plus me-1"></i> Novo
                            </button>
                            <select id="per" class="form-select compact" style="min-width:190px;">
                                <option value="10">10 por página</option>
                                <option value="25" selected>25 por página</option>
                                <option value="50">50 por página</option>
                                <option value="100">100 por página</option>
                            </select>
                        </div>
                    </div>

                    <div class="body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-3">
                                <label class="form-label mini">Status</label>
                                <select class="form-select compact" id="status">
                                    <option value="TODOS" selected>Todos</option>
                                    <option value="ATIVO">Ativo</option>
                                    <option value="INATIVO">Inativo</option>
                                </select>
                            </div>

                            <div class="col-md-7">
                                <label class="form-label mini">Buscar (Nome / CPF / Telefone / ID)</label>
                                <div class="search-wrap">
                                    <input type="text" class="form-control compact" id="q" placeholder="Ex.: Maria / 123.456.789-00 / (92)..." autocomplete="off">
                                    <div class="suggest" id="suggest"></div>
                                </div>
                            </div>

                            <div class="col-md-2 d-flex gap-2 flex-wrap">
                                <button class="main-btn primary-btn btn-hover btn-compact w-100" id="btnFiltrar">
                                    <i class="lni lni-funnel me-1"></i> Filtrar
                                </button>
                                <button class="main-btn light-btn btn-hover btn-compact w-100" id="btnLimpar">
                                    <i class="lni lni-close me-1"></i> Limpar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TABELA (SÓ UMA) -->
                <div class="cardx">
                    <div class="head">
                        <div class="muted"><b>Clientes</b> • ações: Detalhes / Editar / Excluir</div>
                        <div class="toolbar">
                            <span class="pill warn" id="pillLoading" style="display:none;">Carregando…</span>
                        </div>
                    </div>

                    <div class="body">
                        <div class="table-wrap">
                            <table class="table table-hover mb-0" id="tbClientes">
                                <thead>
                                    <tr>
                                        <th class="col-id">ID</th>
                                        <th class="col-nome">Nome</th>
                                        <th class="col-cpf">CPF</th>
                                        <th class="col-tel">Telefone</th>
                                        <th class="col-status">Status</th>
                                        <th class="col-created">Criado em</th>
                                        <th class="col-acoes">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody">
                                    <tr>
                                        <td colspan="7" class="muted">Carregando…</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <div class="page-nav">
                            <button class="page-btn" id="btnPrev">←</button>
                            <span class="page-info" id="pageInfo">Página 1</span>
                            <button class="page-btn" id="btnNext">→</button>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <p class="text-sm muted mb-0">© Painel da Distribuidora • Clientes</p>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <!-- MODAL: FORM -->
    <div class="modal fade" id="mdForm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:16px;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="fmTitulo">Novo cliente</h5>
                        <div class="muted" id="fmSub">Preencha Nome, CPF e Telefone</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="cardx">
                        <div class="head">
                            <div class="fw-1000">Dados do cliente</div>
                            <span class="pill">Obrigatório *</span>
                        </div>
                        <div class="body">
                            <input type="hidden" id="fmId" value="">
                            <input type="hidden" id="fmCsrf" value="<?= e($csrf) ?>">

                            <div class="row g-2">
                                <div class="col-md-7">
                                    <label class="form-label mini">Nome *</label>
                                    <input type="text" class="form-control compact" id="fmNome" placeholder="Ex.: Maria do Carmo">
                                </div>
                                <div class="col-md-5">
                                    <label class="form-label mini">Status</label>
                                    <select class="form-select compact" id="fmStatus">
                                        <option value="ATIVO" selected>ATIVO</option>
                                        <option value="INATIVO">INATIVO</option>
                                    </select>
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label mini">CPF *</label>
                                    <input type="text" class="form-control compact" id="fmCpf" placeholder="000.000.000-00" inputmode="numeric" maxlength="14">
                                </div>

                                <div class="col-md-6">
                                    <label class="form-label mini">Telefone *</label>
                                    <input type="text" class="form-control compact" id="fmTel" placeholder="(00) 00000-0000" inputmode="tel" maxlength="16">
                                </div>

                                <div class="col-12">
                                    <label class="form-label mini">Observações</label>
                                    <input type="text" class="form-control compact" id="fmObs" placeholder="Opcional">
                                </div>

                                <div class="col-12 mt-2">
                                    <div class="muted" id="fmMsg">—</div>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="modal-footer d-flex justify-content-between">
                    <div class="muted">Campos com * são obrigatórios.</div>
                    <div class="d-flex gap-2">
                        <button class="main-btn primary-btn btn-hover btn-compact" id="fmSalvar">
                            <i class="lni lni-save me-1"></i> Salvar
                        </button>
                        <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">
                            <i class="lni lni-close me-1"></i> Cancelar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: DETALHES -->
    <div class="modal fade" id="mdDetalhes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:16px;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="dtTitulo">Detalhes do cliente</h5>
                        <div class="muted" id="dtSub">—</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="cardx">
                        <div class="head">
                            <div class="fw-1000">Informações</div>
                            <span class="pill" id="dtStatusPill">—</span>
                        </div>
                        <div class="body">
                            <div class="row g-2">
                                <div class="col-sm-7">
                                    <div class="mini">Nome</div>
                                    <div class="fw-1000" id="dtNome">—</div>
                                </div>
                                <div class="col-sm-5">
                                    <div class="mini">ID</div>
                                    <div class="fw-1000" id="dtId">—</div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="mini">CPF</div>
                                    <div class="fw-1000" id="dtCpf">—</div>
                                </div>

                                <div class="col-sm-6">
                                    <div class="mini">Telefone</div>
                                    <div class="fw-1000" id="dtTel">—</div>
                                </div>

                                <div class="col-12">
                                    <div class="mini">Observações</div>
                                    <div class="fw-1000" id="dtObs">—</div>
                                </div>

                                <div class="col-12">
                                    <div class="mini">Criado em</div>
                                    <div class="fw-1000" id="dtCreated">—</div>
                                </div>
                            </div>

                            <div class="mt-3 d-flex gap-2 flex-wrap">
                                <button class="main-btn primary-btn btn-hover btn-compact" id="dtEditar">
                                    <i class="lni lni-pencil me-1"></i> Editar
                                </button>
                                <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">
                                    <i class="lni lni-close me-1"></i> Fechar
                                </button>
                            </div>

                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <div class="muted">Ações registradas no banco.</div>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL: EXCLUIR -->
    <div class="modal fade" id="mdExcluir" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:16px;">
                <div class="modal-header">
                    <h5 class="modal-title">Excluir cliente</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <div class="muted">Tem certeza que deseja excluir este cliente?</div>
                    <div class="fw-1000 mt-2" id="exNome">—</div>
                    <div class="muted" id="exMeta">—</div>
                </div>

                <div class="modal-footer">
                    <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">
                        <i class="lni lni-close me-1"></i> Cancelar
                    </button>
                    <button class="main-btn primary-btn btn-hover btn-compact" id="exConfirmar">
                        <i class="lni lni-trash-can me-1"></i> Excluir
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        /* =========================
       CLIENTES (AJAX)
    ========================== */
        const CSRF = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        const state = {
            page: 1,
            per: 25,
            pages: 1,
            total: 0,
            currentId: null
        };

        const mdForm = new bootstrap.Modal(document.getElementById('mdForm'));
        const mdDetalhes = new bootstrap.Modal(document.getElementById('mdDetalhes'));
        const mdExcluir = new bootstrap.Modal(document.getElementById('mdExcluir'));

        const $ = (id) => document.getElementById(id);

        function badgeStatus(st) {
            if (st === 'ATIVO') return `<span class="badge-soft b-ativo">ATIVO</span>`;
            return `<span class="badge-soft b-inativo">INATIVO</span>`;
        }

        function onlyDigits(s) {
            return String(s || '').replace(/\D+/g, '');
        }

        function maskCpf(v) {
            const d = onlyDigits(v).slice(0, 11);
            if (d.length <= 3) return d;
            if (d.length <= 6) return d.replace(/(\d{3})(\d+)/, '$1.$2');
            if (d.length <= 9) return d.replace(/(\d{3})(\d{3})(\d+)/, '$1.$2.$3');
            return d.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
        }

        function maskTel(v) {
            const d = onlyDigits(v).slice(0, 11);
            if (d.length <= 2) return d ? `(${d}` : '';
            const dd = d.slice(0, 2);
            const rest = d.slice(2);
            if (rest.length <= 4) return `(${dd}) ${rest}`;
            if (rest.length <= 8) return `(${dd}) ${rest.slice(0,4)}-${rest.slice(4)}`;
            return `(${dd}) ${rest.slice(0,5)}-${rest.slice(5)}`;
        }

        async function apiFetch(params) {
            const url = new URL(window.location.href);
            url.searchParams.set('action', params.action);
            Object.entries(params).forEach(([k, v]) => {
                if (k === 'action') return;
                url.searchParams.set(k, String(v ?? ''));
            });

            const res = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json'
                }
            });
            return res.json();
        }

        function setLoading(on) {
            const pill = $('pillLoading');
            if (!pill) return;
            pill.style.display = on ? 'inline-flex' : 'none';
        }

        function renderMeta(meta) {
            $('pillCount').textContent = `${meta.total} clientes`;
            $('pageInfo').textContent = `Página ${meta.page} de ${meta.pages}`;
            $('btnPrev').disabled = meta.page <= 1;
            $('btnNext').disabled = meta.page >= meta.pages;

            const st = $('status').value;
            const q = $('q').value.trim();
            const parts = [];
            if (st !== 'TODOS') parts.push(`Status: ${st}`);
            if (q) parts.push(`Busca: ${q}`);
            $('lblRange').textContent = parts.length ? parts.join(' • ') : '—';
        }

        function renderRows(rows) {
            const tb = $('tbody');
            if (!rows.length) {
                tb.innerHTML = `<tr><td colspan="7" class="muted">Nenhum cliente encontrado.</td></tr>`;
                return;
            }

            tb.innerHTML = rows.map(r => `
        <tr>
          <td class="td-nowrap fw-1000">${r.id}</td>
          <td><span class="td-clip" title="${r.nome}">${r.nome}</span></td>
          <td class="td-nowrap">${r.cpf_fmt}</td>
          <td class="td-nowrap">${r.telefone_fmt}</td>
          <td>${badgeStatus(r.status)}</td>
          <td class="td-nowrap">${r.created_at || '—'}</td>
          <td>
            <div class="actions-wrap">
              <button class="main-btn light-btn btn-hover btn-action" data-act="detalhes" data-id="${r.id}">
                <i class="lni lni-eye me-1"></i> Detalhes
              </button>
              <button class="main-btn primary-btn btn-hover btn-action" data-act="editar" data-id="${r.id}">
                <i class="lni lni-pencil me-1"></i> Editar
              </button>
              <button class="main-btn light-btn btn-hover btn-action" data-act="excluir" data-id="${r.id}">
                <i class="lni lni-trash-can me-1"></i> Excluir
              </button>
            </div>
          </td>
        </tr>
      `).join('');

            tb.querySelectorAll('[data-act]').forEach(btn => {
                btn.addEventListener('click', async () => {
                    const id = Number(btn.getAttribute('data-id'));
                    const act = btn.getAttribute('data-act');
                    if (act === 'detalhes') openDetalhes(id);
                    if (act === 'editar') openEditar(id);
                    if (act === 'excluir') openExcluir(id);
                });
            });
        }

        async function loadList() {
            setLoading(true);
            const status = $('status').value;
            const q = $('q').value.trim();
            const per = Number($('per').value) || 25;

            const data = await apiFetch({
                action: 'fetch',
                page: state.page,
                per,
                status,
                q
            });

            setLoading(false);

            if (!data.ok) {
                alert(data.msg || 'Erro ao carregar clientes.');
                return;
            }

            state.per = data.meta.per;
            state.page = data.meta.page;
            state.pages = data.meta.pages;
            state.total = data.meta.total;

            renderMeta(data.meta);
            renderRows(data.rows || []);
        }

        async function openDetalhes(id) {
            const data = await apiFetch({
                action: 'one',
                id
            });
            if (!data.ok) return alert(data.msg || 'Erro ao abrir detalhes.');

            const c = data.data;

            $('dtTitulo').textContent = `Detalhes do cliente #${c.id}`;
            $('dtSub').textContent = 'Cadastro de clientes';

            const pill = $('dtStatusPill');
            pill.className = 'pill ' + (c.status === 'ATIVO' ? 'ok' : 'warn');
            pill.textContent = c.status;

            $('dtId').textContent = c.id;
            $('dtNome').textContent = c.nome;
            $('dtCpf').textContent = c.cpf_fmt;
            $('dtTel').textContent = c.telefone_fmt;
            $('dtObs').textContent = c.obs ? c.obs : '—';
            $('dtCreated').textContent = c.created_at || '—';

            $('dtEditar').onclick = () => {
                mdDetalhes.hide();
                openEditar(c.id);
            };

            mdDetalhes.show();
        }

        function openNovo() {
            state.currentId = null;
            $('fmTitulo').textContent = 'Novo cliente';
            $('fmSub').textContent = 'Preencha Nome, CPF e Telefone';
            $('fmId').value = '';
            $('fmNome').value = '';
            $('fmCpf').value = '';
            $('fmTel').value = '';
            $('fmStatus').value = 'ATIVO';
            $('fmObs').value = '';
            $('fmMsg').textContent = '—';
            mdForm.show();
            setTimeout(() => $('fmNome').focus(), 200);
        }

        async function openEditar(id) {
            const data = await apiFetch({
                action: 'one',
                id
            });
            if (!data.ok) return alert(data.msg || 'Erro ao abrir edição.');

            const c = data.data;
            state.currentId = c.id;

            $('fmTitulo').textContent = `Editar cliente #${c.id}`;
            $('fmSub').textContent = 'Altere e salve';
            $('fmId').value = c.id;
            $('fmNome').value = c.nome;
            $('fmCpf').value = c.cpf_fmt;
            $('fmTel').value = c.telefone_fmt;
            $('fmStatus').value = c.status;
            $('fmObs').value = c.obs || '';
            $('fmMsg').textContent = '—';
            mdForm.show();
        }

        function openExcluir(id) {
            state.currentId = id;

            const tr = document.querySelector(`[data-act="excluir"][data-id="${id}"]`)?.closest('tr');
            const nome = tr?.children?.[1]?.innerText?.trim() || `Cliente #${id}`;
            const cpf = tr?.children?.[2]?.innerText?.trim() || '';
            const tel = tr?.children?.[3]?.innerText?.trim() || '';

            $('exNome').textContent = nome;
            $('exMeta').textContent = `${cpf} • ${tel}`;
            mdExcluir.show();
        }

        async function salvarForm() {
            const id = Number($('fmId').value || 0);
            const payload = {
                _csrf: CSRF,
                id: id || undefined,
                nome: $('fmNome').value.trim(),
                cpf: $('fmCpf').value.trim(),
                telefone: $('fmTel').value.trim(),
                status: $('fmStatus').value,
                obs: $('fmObs').value.trim()
            };

            $('fmMsg').textContent = 'Salvando...';

            const url = id ? 'editarClientes.php' : 'salvarClientes.php';

            const res = await fetch(url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await res.json().catch(() => ({
                ok: false,
                msg: 'Resposta inválida do servidor.'
            }));

            if (!data.ok) {
                $('fmMsg').textContent = '⚠ ' + (data.msg || 'Erro ao salvar.');
                return;
            }

            mdForm.hide();
            await loadList();
            alert(data.msg || 'Salvo!');
        }

        async function confirmarExcluir() {
            const id = Number(state.currentId || 0);
            if (!id) return;

            const res = await fetch('excluirClientes.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
                body: JSON.stringify({
                    _csrf: CSRF,
                    id
                })
            });

            const data = await res.json().catch(() => ({
                ok: false,
                msg: 'Resposta inválida do servidor.'
            }));
            if (!data.ok) return alert(data.msg || 'Erro ao excluir.');

            mdExcluir.hide();
            await loadList();
            alert(data.msg || 'Excluído!');
        }

        function buildSuggestUI(items) {
            const sug = $('suggest');
            if (!items.length) {
                sug.style.display = 'none';
                sug.innerHTML = '';
                return;
            }

            sug.innerHTML = items.map(it => `
        <div class="it" data-nome="${it.nome}">
          <div class="nm td-clip" title="${it.nome}">${it.nome}</div>
          <div class="meta">${it.cpf_fmt} • ${it.telefone_fmt}</div>
        </div>
      `).join('');

            sug.style.display = 'block';

            sug.querySelectorAll('.it').forEach(el => {
                el.addEventListener('click', () => {
                    $('q').value = el.getAttribute('data-nome') || '';
                    sug.style.display = 'none';
                    state.page = 1;
                    loadList();
                });
            });
        }

        let sugTimer = null;

        function scheduleSuggest() {
            clearTimeout(sugTimer);
            const v = $('q').value.trim();
            if (v.length < 2) {
                $('suggest').style.display = 'none';
                return;
            }
            sugTimer = setTimeout(async () => {
                const data = await apiFetch({
                    action: 'suggest',
                    q: v
                });
                if (!data.ok) return;
                buildSuggestUI(data.items || []);
            }, 120);
        }

        /* =========================
           EVENTOS + INIT (CORRIGIDO)
           - o erro que você mostrou acontece quando o comentário fica quebrado.
           Aqui está certinho, em múltiplas linhas e com DOMContentLoaded correto.
        ========================== */
        document.addEventListener('DOMContentLoaded', () => {
            $('fmCpf').addEventListener('input', (e) => e.target.value = maskCpf(e.target.value));
            $('fmTel').addEventListener('input', (e) => e.target.value = maskTel(e.target.value));

            $('btnNovo').addEventListener('click', () => openNovo());
            $('fmSalvar').addEventListener('click', () => salvarForm());
            $('exConfirmar').addEventListener('click', () => confirmarExcluir());

            $('per').addEventListener('change', () => {
                state.page = 1;
                loadList();
            });

            $('btnPrev').addEventListener('click', () => {
                state.page = Math.max(1, state.page - 1);
                loadList();
            });

            $('btnNext').addEventListener('click', () => {
                state.page = Math.min(state.pages || 1, state.page + 1);
                loadList();
            });

            $('btnFiltrar').addEventListener('click', () => {
                state.page = 1;
                loadList();
            });

            $('btnLimpar').addEventListener('click', () => {
                $('status').value = 'TODOS';
                $('q').value = '';
                $('suggest').style.display = 'none';
                state.page = 1;
                loadList();
            });

            $('q').addEventListener('input', scheduleSuggest);
            $('q').addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    $('suggest').style.display = 'none';
                    state.page = 1;
                    loadList();
                }
            });

            document.addEventListener('click', (e) => {
                const sug = $('suggest');
                const wrap = document.querySelector('.search-wrap');
                if (!sug || !wrap) return;
                if (!wrap.contains(e.target)) sug.style.display = 'none';
            });

            // INIT
            loadList();
        });
    </script>
</body>

</html>