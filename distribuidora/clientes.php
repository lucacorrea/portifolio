<?php

declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/clientes/_helpers.php';

require_db_or_die();
$pdo = db();

$csrf = csrf_token();
$return_to = (string)($_SERVER['REQUEST_URI'] ?? url_here('clientes.php'));

$flashOk  = flash_pop('flash_ok');
$flashErr = flash_pop('flash_err');

/* =========================
   FILTROS / PAGINAÇÃO
   (SEM status, pq sua tabela não tem)
========================= */
$q    = get_str('q', '');
$page = max(1, get_int('page', 1));
$per  = get_int('per', 25);
$per  = in_array($per, [10, 25, 50, 100], true) ? $per : 25;
$off  = ($page - 1) * $per;

$params = [];
$where  = " WHERE 1=1 ";

if ($q !== '') {
    $qd = only_digits($q);

    if (ctype_digit($q) && strlen($q) <= 9) {
        $where .= " AND c.id = :id ";
        $params['id'] = (int)$q;
    } elseif ($qd !== '') {
        $where .= " AND (c.cpf LIKE :qd OR c.telefone LIKE :qd) ";
        $params['qd'] = '%' . $qd . '%';
    } else {
        $where .= " AND (c.nome LIKE :q OR c.endereco LIKE :q) ";
        $params['q'] = '%' . $q . '%';
    }
}

$sqlTot = "SELECT COUNT(*) AS total FROM clientes c $where";
$stTot  = $pdo->prepare($sqlTot);
$stTot->execute($params);
$totalCount = (int)($stTot->fetchColumn() ?: 0);

$pages = (int)max(1, (int)ceil($totalCount / $per));
if ($page > $pages) $page = $pages;
$off = ($page - 1) * $per;

$sql = "SELECT c.id, c.nome, c.cpf, c.telefone, c.endereco, c.created_at
        FROM clientes c
        $where
        ORDER BY c.id DESC
        LIMIT $per OFFSET $off";
$st = $pdo->prepare($sql);
$st->execute($params);
$rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

function url_with(array $over): string
{
    $cur = $_GET;
    foreach ($over as $k => $v) {
        if ($v === null) unset($cur[$k]);
        else $cur[$k] = (string)$v;
    }
    $qs = http_build_query($cur);
    $self = (string)($_SERVER['SCRIPT_NAME'] ?? '/clientes.php');
    return $self . ($qs ? ('?' . $qs) : '');
}
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
        /* =========================
       AJUSTES VISUAIS (NOTEBOOK/RESPONSIVO)
    ========================== */
        .section {
            padding-top: 18px
        }

        .page-pad {
            padding-top: 12px
        }

        .main-btn.btn-compact {
            height: 36px !important;
            padding: 8px 12px !important;
            font-size: 13px !important;
            line-height: 1 !important;
        }

        .form-control.compact,
        .form-select.compact {
            height: 40px;
            padding: 10px 12px;
            font-size: 14px;
        }

        .cardx {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 16px;
            background: #fff;
            overflow: hidden;
        }

        .cardx .head {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .cardx .body {
            padding: 14px 16px
        }

        .muted {
            font-size: 13px;
            color: #64748b
        }

        .pill {
            padding: 7px 12px;
            border-radius: 999px;
            border: 1px solid rgba(148, 163, 184, .22);
            font-weight: 900;
            font-size: 12px;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(248, 250, 252, .7);
            white-space: nowrap;
        }

        .pill.ok {
            border-color: rgba(34, 197, 94, .25);
            background: rgba(240, 253, 244, .9);
            color: #166534
        }

        .toolbar {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            align-items: center;
            justify-content: flex-end;
        }

        /* =========================
       TABELA (FONTE MAIOR)
    ========================== */
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
            font-size: 13.5px;
            color: #0f172a;
            padding: 12px 12px;
            white-space: nowrap;
        }

        #tbClientes tbody td {
            border-top: 1px solid rgba(148, 163, 184, .15);
            padding: 14px 12px;
            font-size: 14.5px;
            /* <<< AUMENTO DA FONTE */
            line-height: 1.25;
            vertical-align: middle;
            color: #0f172a;
            background: #fff;
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

        /* col widths */
        .col-id {
            width: 70px
        }

        .col-nome {
            width: 300px
        }

        .col-cpf {
            width: 150px
        }

        .col-tel {
            width: 170px
        }

        .col-end {
            width: 260px
        }

        .col-created {
            width: 190px
        }

        .col-acoes {
            width: 260px
        }

        /* =========================
       AÇÕES (ARRUMADAS)
       - Em notebook, vira “ícone-only” pra não quebrar linha
    ========================== */
        .actions-wrap {
            display: flex;
            gap: 8px;
            flex-wrap: nowrap;
            justify-content: flex-end;
            align-items: center;
        }

        .btn-action {
            height: 36px !important;
            padding: 8px 12px !important;
            font-size: 13px !important;
            border-radius: 10px !important;
            white-space: nowrap;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-action i {
            font-size: 16px
        }

        /* notebook/menor: esconde textos e deixa só ícones */
        @media (max-width: 1400px) {
            .btn-action .act-text {
                display: none
            }

            .btn-action {
                padding: 8px 10px !important
            }
        }

        /* mobile: deixa ações quebrar se precisar */
        @media (max-width: 992px) {
            #tbClientes {
                min-width: 920px
            }

            .actions-wrap {
                flex-wrap: wrap;
                justify-content: flex-start
            }

            .btn-action .act-text {
                display: inline
            }
        }

        /* Paginação */
        .page-nav {
            display: flex;
            gap: 10px;
            align-items: center;
            justify-content: flex-end;
            flex-wrap: wrap;
            margin-top: 12px;
            padding-top: 6px;
        }

        .page-btn {
            border: 1px solid rgba(148, 163, 184, .35);
            background: #fff;
            border-radius: 10px;
            padding: 9px 12px;
            font-weight: 900;
            font-size: 12.5px;
            cursor: pointer;
            text-decoration: none;
            color: #0f172a;
        }

        .page-btn.disabled {
            opacity: .55;
            pointer-events: none
        }

        .page-info {
            font-size: 12.5px;
            color: #64748b;
            font-weight: 900
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- SIDEBAR: mantenha o seu template aqui (não mexi no menu) -->
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
                    <div class="col-6 text-end"><span class="muted">Clientes</span></div>
                </div>
            </div>
        </header>

        <section class="section">
            <div class="container-fluid page-pad">

                <?php if ($flashOk): ?>
                    <div class="alert alert-success" style="border-radius:14px;"><?= e($flashOk) ?></div>
                <?php endif; ?>
                <?php if ($flashErr): ?>
                    <div class="alert alert-danger" style="border-radius:14px;"><?= e($flashErr) ?></div>
                <?php endif; ?>

                <!-- FILTROS -->
                <form method="get" id="formFiltros" class="cardx mb-3">
                    <input type="hidden" name="page" id="page" value="<?= (int)$page ?>">

                    <div class="head">
                        <div>
                            <div class="d-flex align-items-center gap-2 flex-wrap">
                                <span class="pill ok"><?= (int)$totalCount ?> clientes</span>
                                <span class="muted"><?= $q ? ('Busca: ' . e($q)) : '—' ?></span>
                            </div>
                            <div class="muted mt-1">Digite no campo e a tabela atualiza automaticamente (sem clicar em Filtrar).</div>
                        </div>

                        <div class="toolbar">
                            <button type="button" class="main-btn primary-btn btn-hover btn-compact" id="btnNovo">
                                <i class="lni lni-plus me-1"></i> Novo
                            </button>

                            <select name="per" id="per" class="form-select compact" style="min-width:190px;">
                                <option value="10" <?= $per === 10 ? 'selected' : '' ?>>10 por página</option>
                                <option value="25" <?= $per === 25 ? 'selected' : '' ?>>25 por página</option>
                                <option value="50" <?= $per === 50 ? 'selected' : '' ?>>50 por página</option>
                                <option value="100" <?= $per === 100 ? 'selected' : '' ?>>100 por página</option>
                            </select>
                        </div>
                    </div>

                    <div class="body">
                        <div class="row g-2 align-items-end">
                            <div class="col-md-10">
                                <label class="form-label" style="font-weight:900;color:#0f172a;">Buscar (Nome / CPF / Telefone / ID / Endereço)</label>
                                <input type="text" class="form-control compact" name="q" id="q" value="<?= e($q) ?>" placeholder="Comece a digitar…">
                            </div>
                            <div class="col-md-2 d-flex gap-2 flex-wrap">
                                <!-- Filtrar ainda existe, mas não precisa -->
                                <button class="main-btn primary-btn btn-hover btn-compact w-100" type="submit">
                                    <i class="lni lni-funnel me-1"></i> Filtrar
                                </button>
                                <a class="main-btn light-btn btn-hover btn-compact w-100" href="<?= e(url_here('clientes.php')) ?>">
                                    <i class="lni lni-close me-1"></i> Limpar
                                </a>
                            </div>
                        </div>
                    </div>
                </form>

                <!-- TABELA -->
                <div class="cardx">
                    <div class="head">
                        <div class="muted"><b>Clientes</b> • ações: Detalhes / Editar / Excluir</div>
                        <div class="muted">Página <?= (int)$page ?> de <?= (int)$pages ?></div>
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
                                        <th class="col-end">Endereço</th>
                                        <th class="col-created">Criado em</th>
                                        <th class="col-acoes text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!$rows): ?>
                                        <tr>
                                            <td colspan="7" class="muted">Nenhum cliente encontrado.</td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($rows as $r):
                                            $id = (int)$r['id'];
                                            $nome = (string)$r['nome'];
                                            $cpfRaw = (string)($r['cpf'] ?? '');
                                            $telRaw = (string)($r['telefone'] ?? '');
                                            $end = (string)($r['endereco'] ?? '');
                                            $created = (string)($r['created_at'] ?? '');
                                            $cpfF = cpf_fmt($cpfRaw);
                                            $telF = tel_fmt($telRaw);
                                        ?>
                                            <tr>
                                                <td class="td-nowrap fw-1000"><?= $id ?></td>
                                                <td><span class="td-clip" title="<?= e($nome) ?>"><?= e($nome) ?></span></td>
                                                <td class="td-nowrap"><?= e($cpfF) ?></td>
                                                <td class="td-nowrap"><?= e($telF) ?></td>
                                                <td><span class="td-clip" title="<?= e($end) ?>"><?= e($end ?: '—') ?></span></td>
                                                <td class="td-nowrap"><?= e($created ?: '—') ?></td>
                                                <td class="text-end">
                                                    <div class="actions-wrap">
                                                        <button type="button"
                                                            class="main-btn light-btn btn-hover btn-action btnDetalhes"
                                                            title="Detalhes"
                                                            data-id="<?= $id ?>"
                                                            data-nome="<?= e($nome) ?>"
                                                            data-cpf="<?= e($cpfF) ?>"
                                                            data-tel="<?= e($telF) ?>"
                                                            data-end="<?= e($end) ?>"
                                                            data-created="<?= e($created) ?>">
                                                            <i class="lni lni-eye"></i> <span class="act-text">Detalhes</span>
                                                        </button>

                                                        <button type="button"
                                                            class="main-btn primary-btn btn-hover btn-action btnEditar"
                                                            title="Editar"
                                                            data-id="<?= $id ?>"
                                                            data-nome="<?= e($nome) ?>"
                                                            data-cpfraw="<?= e($cpfRaw) ?>"
                                                            data-telraw="<?= e($telRaw) ?>"
                                                            data-end="<?= e($end) ?>">
                                                            <i class="lni lni-pencil"></i> <span class="act-text">Editar</span>
                                                        </button>

                                                        <button type="button"
                                                            class="main-btn light-btn btn-hover btn-action btnExcluir"
                                                            title="Excluir"
                                                            data-id="<?= $id ?>"
                                                            data-nome="<?= e($nome) ?>"
                                                            data-cpf="<?= e($cpfF) ?>"
                                                            data-tel="<?= e($telF) ?>">
                                                            <i class="lni lni-trash-can"></i> <span class="act-text">Excluir</span>
                                                        </button>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="page-nav">
                            <?php
                            $prevUrl = url_with(['page' => max(1, $page - 1)]);
                            $nextUrl = url_with(['page' => min($pages, $page + 1)]);
                            ?>
                            <a class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= e($prevUrl) ?>">←</a>
                            <span class="page-info">Página <?= (int)$page ?> de <?= (int)$pages ?></span>
                            <a class="page-btn <?= $page >= $pages ? 'disabled' : '' ?>" href="<?= e($nextUrl) ?>">→</a>
                        </div>
                    </div>
                </div>

            </div>
        </section>

        <footer class="footer">
            <div class="container-fluid">
                <p class="text-sm muted mb-0">© Painel da Distribuidora • Clientes</p>
            </div>
        </footer>
    </main>

    <!-- MODAL FORM -->
    <div class="modal fade" id="mdForm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:16px;">
                <form method="post" id="formCliente" action="assets/dados/clientes/salvarClientes.php">
                    <div class="modal-header">
                        <div>
                            <h5 class="modal-title" id="fmTitulo">Novo cliente</h5>
                            <div class="muted" id="fmSub">Preencha Nome, CPF e Telefone</div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="return_to" value="<?= e($return_to) ?>">
                        <input type="hidden" name="id" id="fmId" value="">

                        <div class="row g-2">
                            <div class="col-md-6">
                                <label class="form-label" style="font-weight:900;">Nome *</label>
                                <input type="text" class="form-control compact" name="nome" id="fmNome" required>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-weight:900;">CPF *</label>
                                <input type="text" class="form-control compact" name="cpf" id="fmCpf" required maxlength="14">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label" style="font-weight:900;">Telefone *</label>
                                <input type="text" class="form-control compact" name="telefone" id="fmTel" required maxlength="16">
                            </div>
                            <div class="col-12">
                                <label class="form-label" style="font-weight:900;">Endereço</label>
                                <input type="text" class="form-control compact" name="endereco" id="fmEnd">
                            </div>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="main-btn primary-btn btn-hover btn-compact" type="submit">
                            <i class="lni lni-save me-1"></i> Salvar
                        </button>
                        <button class="main-btn light-btn btn-hover btn-compact" type="button" data-bs-dismiss="modal">
                            <i class="lni lni-close me-1"></i> Cancelar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- MODAL DETALHES -->
    <div class="modal fade" id="mdDetalhes" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content" style="border-radius:16px;">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="dtTitulo">Detalhes</h5>
                        <div class="muted">—</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">
                    <div class="row g-2">
                        <div class="col-sm-7">
                            <div class="mini">Nome</div>
                            <div class="fw-1000" id="dtNome">—</div>
                        </div>
                        <div class="col-sm-5">
                            <div class="mini">Criado em</div>
                            <div class="fw-1000" id="dtCreated">—</div>
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
                            <div class="mini">Endereço</div>
                            <div class="fw-1000" id="dtEnd">—</div>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal" type="button">
                        <i class="lni lni-close me-1"></i> Fechar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- MODAL EXCLUIR -->
    <div class="modal fade" id="mdExcluir" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:16px;">
                <form method="post" action="assets/dados/clientes/excluirClientes.php">
                    <div class="modal-header">
                        <h5 class="modal-title">Excluir cliente</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="return_to" value="<?= e($return_to) ?>">
                        <input type="hidden" name="id" id="exId" value="">
                        <div class="muted">Tem certeza que deseja excluir?</div>
                        <div class="fw-1000 mt-2" id="exNome">—</div>
                        <div class="muted" id="exMeta">—</div>
                    </div>

                    <div class="modal-footer">
                        <button class="main-btn light-btn btn-hover btn-compact" type="button" data-bs-dismiss="modal">
                            <i class="lni lni-close me-1"></i> Cancelar
                        </button>
                        <button class="main-btn primary-btn btn-hover btn-compact" type="submit">
                            <i class="lni lni-trash-can me-1"></i> Excluir
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <script>
        /* =========================
     AUTO-PESQUISA (AO DIGITAR)
     - sem AJAX: só faz submit GET com debounce
  ========================== */
        const formFiltros = document.getElementById('formFiltros');
        const qInput = document.getElementById('q');
        const perSel = document.getElementById('per');
        const pageInp = document.getElementById('page');

        let t = null;

        function submitAuto() {
            if (!formFiltros) return;
            if (pageInp) pageInp.value = '1'; // sempre volta pra primeira página
            formFiltros.submit();
        }

        qInput?.addEventListener('input', () => {
            clearTimeout(t);
            t = setTimeout(submitAuto, 350);
        });

        // Enter: aplica imediato
        qInput?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                clearTimeout(t);
                submitAuto();
            }
        });

        // Mudou "por página": atualiza sozinho
        perSel?.addEventListener('change', () => {
            clearTimeout(t);
            submitAuto();
        });

        /* =========================
           MODAIS + AÇÕES
        ========================== */
        const mdForm = new bootstrap.Modal(document.getElementById('mdForm'));
        const mdDetalhes = new bootstrap.Modal(document.getElementById('mdDetalhes'));
        const mdExcluir = new bootstrap.Modal(document.getElementById('mdExcluir'));

        const formCliente = document.getElementById('formCliente');
        const fmTitulo = document.getElementById('fmTitulo');
        const fmSub = document.getElementById('fmSub');
        const fmId = document.getElementById('fmId');
        const fmNome = document.getElementById('fmNome');
        const fmCpf = document.getElementById('fmCpf');
        const fmTel = document.getElementById('fmTel');
        const fmEnd = document.getElementById('fmEnd');

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
            if (!d) return '';
            if (d.length <= 2) return `(${d}`;
            const dd = d.slice(0, 2),
                rest = d.slice(2);
            if (rest.length <= 4) return `(${dd}) ${rest}`;
            if (rest.length <= 8) return `(${dd}) ${rest.slice(0,4)}-${rest.slice(4)}`;
            return `(${dd}) ${rest.slice(0,5)}-${rest.slice(5)}`;
        }
        fmCpf?.addEventListener('input', e => e.target.value = maskCpf(e.target.value));
        fmTel?.addEventListener('input', e => e.target.value = maskTel(e.target.value));

        document.getElementById('btnNovo')?.addEventListener('click', () => {
            formCliente.action = 'assets/dados/clientes/salvarClientes.php';
            fmTitulo.textContent = 'Novo cliente';
            fmSub.textContent = 'Preencha Nome, CPF e Telefone';
            fmId.value = '';
            fmNome.value = '';
            fmCpf.value = '';
            fmTel.value = '';
            fmEnd.value = '';
            mdForm.show();
            setTimeout(() => fmNome.focus(), 150);
        });

        document.querySelectorAll('.btnDetalhes').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('dtTitulo').textContent = `Detalhes do cliente #${btn.dataset.id}`;
                document.getElementById('dtNome').textContent = btn.dataset.nome || '—';
                document.getElementById('dtCpf').textContent = btn.dataset.cpf || '—';
                document.getElementById('dtTel').textContent = btn.dataset.tel || '—';
                document.getElementById('dtEnd').textContent = btn.dataset.end || '—';
                document.getElementById('dtCreated').textContent = btn.dataset.created || '—';
                mdDetalhes.show();
            });
        });

        document.querySelectorAll('.btnEditar').forEach(btn => {
            btn.addEventListener('click', () => {
                formCliente.action = 'assets/dados/clientes/editarClientes.php';
                fmTitulo.textContent = `Editar cliente #${btn.dataset.id}`;
                fmSub.textContent = 'Altere e salve';
                fmId.value = btn.dataset.id || '';
                fmNome.value = btn.dataset.nome || '';
                fmCpf.value = maskCpf(btn.dataset.cpfraw || '');
                fmTel.value = maskTel(btn.dataset.telraw || '');
                fmEnd.value = btn.dataset.end || '';
                mdForm.show();
                setTimeout(() => fmNome.focus(), 150);
            });
        });

        document.querySelectorAll('.btnExcluir').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('exId').value = btn.dataset.id || '';
                document.getElementById('exNome').textContent = btn.dataset.nome || '—';
                document.getElementById('exMeta').textContent = `${btn.dataset.cpf || ''} • ${btn.dataset.tel || ''}`;
                mdExcluir.show();
            });
        });
    </script>
</body>

</html>