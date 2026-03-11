<?php

declare(strict_types=1);

@date_default_timezone_set('America/Manaus');

require_once __DIR__ . '/assets/auth/auth.php';
if (function_exists('auth_require')) {
    auth_require('index.php');
}

require_once __DIR__ . '/assets/conexao.php';

$pdo = db();

/* =========================
   HELPERS LOCAIS (PREFIXADOS)
========================= */
function users_e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function users_csrf_token(): string
{
    if (empty($_SESSION['_csrf']) || !is_string($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function users_get_str(string $key, string $default = ''): string
{
    $value = $_GET[$key] ?? $default;
    return is_string($value) ? trim($value) : $default;
}

function users_get_int(string $key, int $default = 1): int
{
    $value = $_GET[$key] ?? $default;
    return is_numeric($value) ? max(1, (int)$value) : $default;
}

function users_json_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function users_flash_take(string $key): ?string
{
    if (isset($_SESSION[$key]) && is_string($_SESSION[$key])) {
        $msg = $_SESSION[$key];
        unset($_SESSION[$key]);
        return $msg;
    }
    return null;
}

function users_fmt_date(?string $date): string
{
    $date = trim((string)$date);
    if ($date === '') {
        return '-';
    }

    $ts = strtotime($date);
    if ($ts === false) {
        return '-';
    }

    return date('d/m/Y H:i', $ts);
}

function users_fetch_page(PDO $pdo, string $q, int $page, int $perPage): array
{
    $where = ' WHERE 1=1 ';
    $params = [];

    if ($q !== '') {
        $where .= " AND (CAST(id AS CHAR) LIKE :q OR nome LIKE :q OR email LIKE :q) ";
        $params[':q'] = '%' . $q . '%';
    }

    $sqlCount = "SELECT COUNT(*) FROM usuarios {$where}";
    $stCount = $pdo->prepare($sqlCount);
    $stCount->execute($params);
    $total = (int)$stCount->fetchColumn();

    $lastPage = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, $page), $lastPage);
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT id, nome, email, status, created_at
            FROM usuarios
            {$where}
            ORDER BY id DESC
            LIMIT :limite OFFSET :offset";
    $st = $pdo->prepare($sql);

    foreach ($params as $k => $v) {
        $st->bindValue($k, $v, PDO::PARAM_STR);
    }

    $st->bindValue(':limite', $perPage, PDO::PARAM_INT);
    $st->bindValue(':offset', $offset, PDO::PARAM_INT);
    $st->execute();

    $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

    $from = $total > 0 ? ($offset + 1) : 0;
    $to = $total > 0 ? min($offset + $perPage, $total) : 0;

    return [
        'rows'      => $rows,
        'total'     => $total,
        'page'      => $page,
        'per_page'  => $perPage,
        'last_page' => $lastPage,
        'from'      => $from,
        'to'        => $to,
    ];
}

function users_render_rows(array $rows): string
{
    ob_start();

    if (!$rows) {
        echo '<tr><td colspan="6" class="text-center text-muted py-4">Nenhum usuário encontrado.</td></tr>';
        return (string)ob_get_clean();
    }

    foreach ($rows as $r) {
        $id        = (int)($r['id'] ?? 0);
        $nome      = (string)($r['nome'] ?? '');
        $email     = (string)($r['email'] ?? '');
        $status    = (string)($r['status'] ?? 'ATIVO');
        $createdAt = users_fmt_date((string)($r['created_at'] ?? ''));
        $statusCls = strtoupper($status) === 'ATIVO' ? 'ok' : 'warn';
?>
        <tr>
            <td><?= $id ?></td>
            <td class="fw-bold"><?= users_e($nome) ?></td>
            <td><?= users_e($email) ?></td>
            <td><span class="pill <?= $statusCls ?>"><?= users_e($status) ?></span></td>
            <td><?= users_e($createdAt) ?></td>
            <td class="text-end">
                <button
                    type="button"
                    class="main-btn primary-btn btn-hover btn-action btnEditar"
                    data-id="<?= $id ?>"
                    data-nome="<?= users_e($nome) ?>"
                    data-email="<?= users_e($email) ?>"
                    data-status="<?= users_e($status) ?>">
                    <i class="lni lni-pencil"></i>
                </button>

                <button
                    type="button"
                    class="main-btn danger-btn-outline btn-hover btn-action btnExcluir"
                    data-id="<?= $id ?>"
                    data-nome="<?= users_e($nome) ?>">
                    <i class="lni lni-trash-can"></i>
                </button>
            </td>
        </tr>
    <?php
    }

    return (string)ob_get_clean();
}

function users_render_pager(array $data): string
{
    $page = (int)$data['page'];
    $last = (int)$data['last_page'];

    $prevDisabled = $page <= 1 ? 'disabled' : '';
    $nextDisabled = $page >= $last ? 'disabled' : '';

    ob_start();
    ?>
    <div class="pager-box">
        <button type="button" class="pager-btn" data-page="<?= max(1, $page - 1) ?>" <?= $prevDisabled ?>>
            <i class="lni lni-chevron-left"></i>
        </button>

        <div class="pager-text">Página <?= $page ?>/<?= $last ?></div>

        <button type="button" class="pager-btn" data-page="<?= min($last, $page + 1) ?>" <?= $nextDisabled ?>>
            <i class="lni lni-chevron-right"></i>
        </button>
    </div>
<?php
    return (string)ob_get_clean();
}

/* =========================
   AJAX
========================= */
$action = strtolower(users_get_str('action', ''));
if ($action === 'ajax') {
    try {
        $q = users_get_str('q', '');
        $page = users_get_int('page', 1);
        $perPage = 10;

        $data = users_fetch_page($pdo, $q, $page, $perPage);

        users_json_out([
            'ok'         => true,
            'tbody_html' => users_render_rows($data['rows']),
            'info_text'  => "Mostrando {$data['from']}-{$data['to']} de {$data['total']}",
            'pager_html' => users_render_pager($data),
        ]);
    } catch (Throwable $e) {
        users_json_out([
            'ok'  => false,
            'msg' => 'Erro ao carregar usuários: ' . $e->getMessage(),
        ], 500);
    }
}

/* =========================
   PRIMEIRA CARGA
========================= */
$data = users_fetch_page($pdo, '', 1, 10);

$csrf = users_csrf_token();
$flashOk  = users_flash_take('flash_ok');
$flashErr = users_flash_take('flash_err');
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Usuários</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" type="text/css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <style>
        .card-style {
            border: 1px solid rgba(148, 163, 184, .22);
            border-radius: 16px;
            background: #fff;
            overflow: hidden;
        }

        .card-style .head {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(148, 163, 184, .18);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 12px;
            flex-wrap: wrap;
        }

        .card-style .body {
            padding: 14px 16px;
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
            color: #166534;
        }

        .pill.warn {
            border-color: rgba(245, 158, 11, .25);
            background: rgba(255, 251, 235, .95);
            color: #92400e;
        }

        .muted {
            font-size: 13px;
            color: #64748b;
        }

        .table-wrap {
            overflow: auto;
            border-radius: 14px;
        }

        #tbUsers {
            width: 100%;
            min-width: 900px;
        }

        #tbUsers thead th {
            background: #f8fafc;
            border-bottom: 1px solid rgba(148, 163, 184, .25);
            font-size: 13.5px;
            color: #0f172a;
            padding: 12px;
        }

        #tbUsers tbody td {
            border-top: 1px solid rgba(148, 163, 184, .15);
            padding: 14px 12px;
            font-size: 14.5px;
            vertical-align: middle;
            background: #fff;
        }

        .btn-action {
            height: 36px !important;
            padding: 8px 12px !important;
            font-size: 13px !important;
            border-radius: 10px !important;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .logout-btn {
            padding: 8px 14px !important;
            min-width: 88px;
            height: 46px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none !important;
        }

        .logout-btn i {
            font-size: 16px;
        }

        .header-right {
            height: 100%;
        }

        .brand-vertical {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 6px;
            text-decoration: none;
            text-align: center;
        }

        .brand-name {
            display: block;
            font-size: 18px;
            line-height: 1.2;
            font-weight: 600;
            color: #1e2a78;
            white-space: normal;
            word-break: break-word;
        }

        .footer-table {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 14px;
            flex-wrap: wrap;
            margin-top: 14px;
        }

        .footer-info {
            color: #64748b;
            font-size: 14px;
            font-weight: 600;
        }

        .pager-box {
            display: inline-flex;
            align-items: center;
            gap: 12px;
        }

        .pager-btn {
            width: 42px;
            height: 42px;
            border: 1px solid #e2e8f0;
            background: #f8fafc;
            color: #64748b;
            border-radius: 8px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
        }

        .pager-btn:hover:not(:disabled) {
            background: #eef2ff;
            color: #4338ca;
        }

        .pager-btn:disabled {
            opacity: .45;
            cursor: not-allowed;
        }

        .pager-text {
            font-size: 14px;
            font-weight: 700;
            color: #475569;
        }

        @media (max-width: 768px) {
            .footer-table {
                flex-direction: column;
                align-items: stretch;
            }

            .pager-box {
                justify-content: flex-end;
            }
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <aside class="sidebar-nav-wrapper">
        <div class="navbar-logo">
            <a href="dashboard.php" class="brand-vertical">
                <span class="brand-name">DISTRIBUIDORA<br>PLHB</span>
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item"><a href="dashboard.php"><span class="icon"><i class="lni lni-dashboard"></i></span><span class="text">Dashboard</span></a></li>
                <li class="nav-item"><a href="vendas.php"><span class="icon"><i class="lni lni-cart"></i></span><span class="text">Vendas</span></a></li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes">
                        <span class="icon"><i class="lni lni-layers"></i></span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
                        <li><a href="vendidos.php">Vendidos</a></li>
                        <li><a href="fiados.php">À Prazo</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque">
                        <span class="icon"><i class="lni lni-package"></i></span>
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse dropdown-nav">
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php">Inventário</a></li>
                        <li><a href="entradas.php">Entradas</a></li>
                        <li><a href="saidas.php">Saídas</a></li>
                        <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros">
                        <span class="icon"><i class="lni lni-users"></i></span>
                        <span class="text">Cadastros</span>
                    </a>
                    <ul id="ddmenu_cadastros" class="collapse dropdown-nav">
                        <li><a href="clientes.php">Clientes</a></li>
                        <li><a href="fornecedores.php">Fornecedores</a></li>
                        <li><a href="categorias.php">Categorias</a></li>
                    </ul>
                </li>

                <li class="nav-item"><a href="relatorios.php"><span class="icon"><i class="lni lni-clipboard"></i></span><span class="text">Relatórios</span></a></li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config">
                        <span class="icon"><i class="lni lni-cog"></i></span>
                        <span class="text">Configurações</span>
                    </a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav show">
                        <li><a href="usuarios.php" class="active">Usuários</a></li>
                        <li><a href="parametros.php">Parâmetros do Sistema</a></li>
                    </ul>
                </li>

                <li class="nav-item"><a href="suporte.php"><span class="icon"><i class="lni lni-whatsapp"></i></span><span class="text">Suporte</span></a></li>
            </ul>
        </nav>
    </aside>

    <div class="overlay"></div>

    <main class="main-wrapper">
        <header class="header">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-5 col-md-5 col-6">
                        <div class="header-left d-flex align-items-center">
                            <div class="menu-toggle-btn mr-15">
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover" type="button">
                                    <i class="lni lni-chevron-left me-2"></i> Menu
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7 col-md-7 col-6">
                        <div class="header-right d-flex justify-content-end align-items-center">
                            <a href="logout.php" class="main-btn primary-btn btn-hover logout-btn">
                                <i class="lni lni-exit me-1"></i> Sair
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </header>

        <section class="section">
            <div class="container-fluid p-4">
                <?php if ($flashOk): ?>
                    <div class="alert alert-success" style="border-radius:14px;"><?= users_e($flashOk) ?></div>
                <?php endif; ?>

                <?php if ($flashErr): ?>
                    <div class="alert alert-danger" style="border-radius:14px;"><?= users_e($flashErr) ?></div>
                <?php endif; ?>

                <div class="card-style mb-3">
                    <div class="head">
                        <div>
                            <h5 class="mb-0">Usuários</h5>
                            <div class="muted">Gerencie quem acessa o sistema</div>
                        </div>
                        <button type="button" class="main-btn primary-btn btn-hover" id="btnNovo">
                            <i class="lni lni-plus me-1"></i> Novo Usuário
                        </button>
                    </div>
                    <div class="body">
                        <input type="text" class="form-control" id="q" placeholder="Buscar por ID, nome ou email..." autocomplete="off">
                    </div>
                </div>

                <div class="card-style">
                    <div class="body">
                        <div class="table-wrap">
                            <table class="table table-hover mb-0" id="tbUsers">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Status</th>
                                        <th>Cadastro</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody">
                                    <?= users_render_rows($data['rows']) ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="footer-table">
                            <div class="footer-info" id="footerInfo">
                                Mostrando <?= $data['from'] ?>-<?= $data['to'] ?> de <?= $data['total'] ?>
                            </div>

                            <div id="pagerArea">
                                <?= users_render_pager($data) ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <div class="modal fade" id="mdForm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:16px;">
                <form method="post" action="assets/dados/usuarios/salvarUsuarios.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="fmTitulo">Novo Usuário</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>

                    <div class="modal-body">
                        <input type="hidden" name="csrf" value="<?= users_e($csrf) ?>">
                        <input type="hidden" name="id" id="fmId" value="">

                        <div class="mb-3">
                            <label class="form-label">Nome</label>
                            <input type="text" class="form-control" name="nome" id="fmNome" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="fmEmail" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">
                                Senha
                                <small class="text-muted">(deixe em branco para manter a atual ao editar)</small>
                            </label>
                            <input type="password" class="form-control" name="senha" id="fmSenha">
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="status" id="fmStatus">
                                <option value="ATIVO">Ativo</option>
                                <option value="INATIVO">Inativo</option>
                            </select>
                        </div>
                    </div>

                    <div class="modal-footer">
                        <button class="main-btn primary-btn btn-hover" type="submit">Salvar</button>
                        <button class="main-btn light-btn btn-hover" type="button" data-bs-dismiss="modal">Cancelar</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <form id="formExcluir" method="post" action="assets/dados/usuarios/excluirUsuarios.php" style="display:none;">
        <input type="hidden" name="csrf" value="<?= users_e($csrf) ?>">
        <input type="hidden" name="id" id="delId">
    </form>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        const modalEl = document.getElementById('mdForm');
        const modal = new bootstrap.Modal(modalEl);
        const tbody = document.getElementById('tbody');
        const footerInfo = document.getElementById('footerInfo');
        const pagerArea = document.getElementById('pagerArea');
        const inputQ = document.getElementById('q');
        let timer = null;

        function bindRowActions() {
            document.querySelectorAll('.btnEditar').forEach(btn => {
                btn.onclick = () => {
                    document.getElementById('fmTitulo').textContent = 'Editar Usuário';
                    document.getElementById('fmId').value = btn.dataset.id || '';
                    document.getElementById('fmNome').value = btn.dataset.nome || '';
                    document.getElementById('fmEmail').value = btn.dataset.email || '';
                    document.getElementById('fmSenha').value = '';
                    document.getElementById('fmStatus').value = btn.dataset.status || 'ATIVO';
                    modal.show();
                };
            });

            document.querySelectorAll('.btnExcluir').forEach(btn => {
                btn.onclick = () => {
                    if (confirm('Deseja excluir o usuário ' + (btn.dataset.nome || '') + '?')) {
                        document.getElementById('delId').value = btn.dataset.id || '';
                        document.getElementById('formExcluir').submit();
                    }
                };
            });
        }

        function bindPager() {
            pagerArea.querySelectorAll('.pager-btn[data-page]').forEach(btn => {
                btn.onclick = () => {
                    if (btn.disabled) return;
                    const page = parseInt(btn.getAttribute('data-page') || '1', 10);
                    loadUsers(page);
                };
            });
        }

        function loadUsers(page = 1) {
            const q = inputQ.value || '';
            const url = `usuarios.php?action=ajax&q=${encodeURIComponent(q)}&page=${page}`;

            fetch(url, {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(r => r.json())
                .then(res => {
                    if (!res.ok) {
                        alert(res.msg || 'Erro ao carregar usuários.');
                        return;
                    }

                    tbody.innerHTML = res.tbody_html || '';
                    footerInfo.textContent = res.info_text || '';
                    pagerArea.innerHTML = res.pager_html || '';
                    bindRowActions();
                    bindPager();
                })
                .catch(() => {
                    alert('Erro ao carregar usuários.');
                });
        }

        document.getElementById('btnNovo').addEventListener('click', () => {
            document.getElementById('fmTitulo').textContent = 'Novo Usuário';
            document.getElementById('fmId').value = '';
            document.getElementById('fmNome').value = '';
            document.getElementById('fmEmail').value = '';
            document.getElementById('fmSenha').value = '';
            document.getElementById('fmStatus').value = 'ATIVO';
            modal.show();
        });

        inputQ.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(() => loadUsers(1), 250);
        });

        bindRowActions();
        bindPager();
    </script>
</body>

</html>