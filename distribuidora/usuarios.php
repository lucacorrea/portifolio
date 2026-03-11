<?php

declare(strict_types=1);

@date_default_timezone_set('America/Manaus');
if (session_status() !== PHP_SESSION_ACTIVE) session_start();

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/usuarios/_helpers.php';

require_db_or_die();
$pdo = db();

/* =========================
   JSON OUT (local)
========================= */
function json_out(array $payload, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* =========================
   AJAX ENDPOINT
========================= */
$action = strtolower(get_str('action', ''));
if ($action === 'ajax') {
    try {
        $q = trim(get_str('q', ''));
        $params = [];
        $where = " WHERE 1=1 ";

        if ($q !== '') {
            $where .= " AND (nome LIKE :q OR email LIKE :q) ";
            $params['q'] = '%' . $q . '%';
        }

        $sql = "SELECT id, nome, email, perfil, status, created_at FROM usuarios $where ORDER BY id DESC";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC) ?: [];

        json_out(['ok' => true, 'rows' => $rows]);
    } catch (Throwable $e) {
        json_out(['ok' => false, 'msg' => 'Erro: ' . $e->getMessage()], 500);
    }
}

$csrf = csrf_token();
$return_to = (string)($_SERVER['REQUEST_URI'] ?? url_here('usuarios.php'));

$flashOk  = flash_pop('flash_ok');
$flashErr = flash_pop('flash_err');

$sql = "SELECT id, nome, email, perfil, status, created_at FROM usuarios ORDER BY id DESC";
$rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC) ?: [];
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
    <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
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
            min-width: 800px;
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
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- ======== sidebar-nav start =========== -->
    <aside class="sidebar-nav-wrapper">
        <div class="navbar-logo">
            <a href="dashboard.php" class="brand-vertical">
                <span class="brand-name">DISTRIBUIDORA<br>PLHB</span>
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item">
                    <a href="dashboard.php">
                        <span class="icon">
                            <i class="lni lni-dashboard"></i>
                        </span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <li class="nav-item">
                    <a href="vendas.php">
                        <span class="icon">
                            <i class="lni lni-cart"></i>
                        </span>
                        <span class="text">Vendas</span>
                    </a>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-layers"></i>
                        </span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
                        <li><a href="vendidos.php">Vendidos</a></li>
                        <li><a href="fiados.php">À Prazo</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-package"></i>
                        </span>
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
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-users"></i>
                        </span>
                        <span class="text">Cadastros</span>
                    </a>
                    <ul id="ddmenu_cadastros" class="collapse dropdown-nav">
                        <li><a href="clientes.php">Clientes</a></li>
                        <li><a href="fornecedores.php">Fornecedores</a></li>
                        <li><a href="categorias.php">Categorias</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="relatorios.php">
                        <span class="icon">
                            <i class="lni lni-clipboard"></i>
                        </span>
                        <span class="text">Relatórios</span>
                    </a>
                </li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config" aria-controls="ddmenu_config" aria-expanded="false">
                        <span class="icon">
                            <i class="lni lni-cog"></i>
                        </span>
                        <span class="text">Configurações</span>
                    </a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav show">
                        <li><a href="usuarios.php" class="active">Usuários e Permissões</a></li>
                        <li><a href="parametros.php">Parâmetros do Sistema</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="suporte.php">
                        <span class="icon">
                            <i class="lni lni-whatsapp"></i>
                        </span>
                        <span class="text">Suporte</span>
                    </a>
                </li>
            </ul>
        </nav>
    </aside>

    <div class="overlay"></div>

    <main class="main-wrapper">
        <!-- Header -->
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
                            <div class="header-search d-none d-md-flex"></div>
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
                <?php if ($flashOk): ?><div class="alert alert-success" style="border-radius:14px;"><?= e($flashOk) ?></div><?php endif; ?>
                <?php if ($flashErr): ?><div class="alert alert-danger" style="border-radius:14px;"><?= e($flashErr) ?></div><?php endif; ?>

                <div class="card-style mb-3">
                    <div class="head">
                        <div>
                            <h5 class="mb-0">Usuários e Permissões</h5>
                            <div class="muted">Gerencie quem acessa o sistema</div>
                        </div>
                        <button type="button" class="main-btn primary-btn btn-hover" id="btnNovo"><i class="lni lni-plus me-1"></i> Novo Usuário</button>
                    </div>
                    <div class="body">
                        <input type="text" class="form-control" id="q" placeholder="Buscar por nome ou email..." autocomplete="off">
                    </div>
                </div>

                <div class="cardx">
                    <div class="body">
                        <div class="table-wrap">
                            <table class="table table-hover mb-0" id="tbUsers">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Nome</th>
                                        <th>Email</th>
                                        <th>Perfil</th>
                                        <th>Status</th>
                                        <th class="text-end">Ações</th>
                                    </tr>
                                </thead>
                                <tbody id="tbody">
                                    <?php foreach ($rows as $r): ?>
                                        <tr>
                                            <td><?= $r['id'] ?></td>
                                            <td class="fw-bold"><?= e((string)$r['nome']) ?></td>
                                            <td><?= e((string)$r['email']) ?></td>
                                            <td><span class="pill primary"><?= $r['perfil'] ?></span></td>
                                            <td><span class="pill <?= strtoupper($r['status']) === 'ATIVO' ? 'ok' : 'warn' ?>"><?= $r['status'] ?></span></td>
                                            <td class="text-end">
                                                <button type="button" class="main-btn primary-btn btn-hover btn-action btnEditar"
                                                    data-id="<?= $r['id'] ?>"
                                                    data-nome="<?= e((string)$r['nome']) ?>"
                                                    data-email="<?= e((string)$r['email']) ?>"
                                                    data-perfil="<?= $r['perfil'] ?>"
                                                    data-status="<?= $r['status'] ?>">
                                                    <i class="lni lni-pencil"></i>
                                                </button>
                                                <button type="button" class="main-btn danger-btn-outline btn-hover btn-action btnExcluir" data-id="<?= $r['id'] ?>" data-nome="<?= e((string)$r['nome']) ?>">
                                                    <i class="lni lni-trash-can"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- MODAL FORM -->
    <div class="modal fade" id="mdForm" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content" style="border-radius:16px;">
                <form method="post" action="assets/dados/usuarios/salvarUsuarios.php">
                    <div class="modal-header">
                        <h5 class="modal-title" id="fmTitulo">Novo Usuário</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" id="fmId" value="">
                        <div class="mb-3"><label class="form-label">Nome</label><input type="text" class="form-control" name="nome" id="fmNome" required></div>
                        <div class="mb-3"><label class="form-label">Email</label><input type="email" class="form-control" name="email" id="fmEmail" required></div>
                        <div class="mb-3"><label class="form-label">Senha <small class="text-muted">(Deixe em branco para manter a atual se estiver editando)</small></label><input type="password" class="form-control" name="senha" id="fmSenha"></div>
                        <div class="mb-3">
                            <label class="form-label">Perfil</label>
                            <select class="form-select" name="perfil" id="fmPerfil">
                                <option value="VENDEDOR">Vendedor</option>
                                <option value="ADMIN">Administrador</option>
                            </select>
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

    <!-- EXCLUIR FORM -->
    <form id="formExcluir" method="post" action="assets/dados/usuarios/excluirUsuarios.php" style="display:none;">
        <input type="hidden" name="_csrf" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="delId">
    </form>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>
    <script>
        const modal = new bootstrap.Modal(document.getElementById('mdForm'));
        document.getElementById('btnNovo').addEventListener('click', () => {
            document.getElementById('fmTitulo').textContent = 'Novo Usuário';
            document.getElementById('fmId').value = '';
            document.getElementById('fmNome').value = '';
            document.getElementById('fmEmail').value = '';
            document.getElementById('fmSenha').value = '';
            document.getElementById('fmPerfil').value = 'VENDEDOR';
            document.getElementById('fmStatus').value = 'ATIVO';
            modal.show();
        });

        document.querySelectorAll('.btnEditar').forEach(btn => {
            btn.addEventListener('click', () => {
                document.getElementById('fmTitulo').textContent = 'Editar Usuário';
                document.getElementById('fmId').value = btn.dataset.id;
                document.getElementById('fmNome').value = btn.dataset.nome;
                document.getElementById('fmEmail').value = btn.dataset.email;
                document.getElementById('fmSenha').value = '';
                document.getElementById('fmPerfil').value = btn.dataset.perfil;
                document.getElementById('fmStatus').value = btn.dataset.status;
                modal.show();
            });
        });

        document.querySelectorAll('.btnExcluir').forEach(btn => {
            btn.addEventListener('click', () => {
                if (confirm('Deseja excluir o usuário ' + btn.dataset.nome + '?')) {
                    document.getElementById('delId').value = btn.dataset.id;
                    document.getElementById('formExcluir').submit();
                }
            });
        });

        document.getElementById('q').addEventListener('input', (e) => {
            const q = e.target.value.toLowerCase();
            document.querySelectorAll('#tbody tr').forEach(tr => {
                const text = tr.innerText.toLowerCase();
                tr.style.display = text.includes(q) ? '' : 'none';
            });
        });
    </script>
</body>

</html>