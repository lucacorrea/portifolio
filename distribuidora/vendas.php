<?php

declare(strict_types=1);
session_start();

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/vendas/_helpers.php';

$pdo = db();

$flash = flash_pop();

$IMG_PREFIX = 'assets/dados/produtos/'; // pq no banco fica: images/arquivo.png

function img_url(string $img, string $prefix): string
{
    $img = trim($img);
    if ($img === '') return '';
    if (str_starts_with($img, 'http://') || str_starts_with($img, 'https://') || str_starts_with($img, 'data:')) return $img;
    return $prefix . ltrim($img, '/'); // vira: assets/dados/produtos/images/...
}

// produtos p/ montar itens no modal
$produtos = $pdo->query("
  SELECT id, codigo, nome, unidade, preco, estoque, minimo, imagem
  FROM produtos
  ORDER BY nome ASC
")->fetchAll(PDO::FETCH_ASSOC);

// lista de vendas (últimas 500)
$vendas = $pdo->query("
  SELECT v.*,
         (SELECT COUNT(*) FROM venda_itens vi WHERE vi.venda_id = v.id) AS itens_qtd
  FROM vendas v
  ORDER BY v.id DESC
  LIMIT 500
")->fetchAll(PDO::FETCH_ASSOC);

// itens das vendas (pra editar no modal)
$itensByVenda = [];
if ($vendas) {
    $ids = array_map(fn($x) => (int)$x['id'], $vendas);
    $in  = implode(',', array_fill(0, count($ids), '?'));

    $st = $pdo->prepare("
    SELECT vi.venda_id, vi.produto_id, vi.qtd, vi.preco, vi.total,
           p.codigo, p.nome, p.unidade, p.imagem
    FROM venda_itens vi
    JOIN produtos p ON p.id = vi.produto_id
    WHERE vi.venda_id IN ($in)
    ORDER BY vi.id ASC
  ");
    $st->execute($ids);

    while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
        $vid = (int)$r['venda_id'];
        if (!isset($itensByVenda[$vid])) $itensByVenda[$vid] = [];
        $itensByVenda[$vid][] = [
            'produto_id' => (int)$r['produto_id'],
            'codigo'     => (string)$r['codigo'],
            'nome'       => (string)$r['nome'],
            'unidade'    => (string)$r['unidade'],
            'imagem'     => img_url((string)($r['imagem'] ?? ''), $IMG_PREFIX),
            'qtd'        => (int)$r['qtd'],
            'preco'      => (float)$r['preco'],
            'total'      => (float)$r['total'],
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Vendas</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <style>
        .profile-box .dropdown-menu {
            width: max-content;
            min-width: 260px;
            max-width: calc(100vw - 24px);
        }

        .main-btn.btn-compact {
            height: 38px !important;
            padding: 8px 14px !important;
            font-size: 13px !important;
            line-height: 1 !important;
        }

        .main-btn.btn-compact i {
            font-size: 14px;
            vertical-align: -1px;
        }

        .icon-btn {
            height: 34px !important;
            width: 42px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important;
        }

        .form-control.compact,
        .form-select.compact {
            height: 38px;
            padding: 8px 12px;
            font-size: 13px;
        }

        .table td,
        .table th {
            vertical-align: middle;
        }

        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        #tbVendas {
            width: 100%;
            min-width: 1320px;
        }

        #tbVendas th,
        #tbVendas td {
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
        }

        .prod-img {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, .35);
            background: #fff;
        }

        .img-preview {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px dashed rgba(148, 163, 184, .6);
            background: #fff;
        }

        .img-block {
            max-width: 320px;
            width: 100%;
        }

        .badge-soft {
            padding: .35rem .6rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: .72rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 92px;
        }

        .badge-soft-success {
            background: rgba(34, 197, 94, .12);
            color: #16a34a;
        }

        .badge-soft-gray {
            background: rgba(148, 163, 184, .18);
            color: #475569;
        }

        .badge-soft-warning {
            background: rgba(245, 158, 11, .12);
            color: #b45309;
        }

        .muted {
            font-size: 12px;
            color: #64748b;
        }

        .td-center {
            text-align: center;
        }

        .td-right {
            text-align: right;
        }

        .flash-auto-hide {
            transition: opacity .35s ease, transform .35s ease;
        }

        .flash-auto-hide.hide {
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
        }

        /* tabela itens do modal */
        #tbItens th,
        #tbItens td {
            vertical-align: middle;
        }

        #tbItens select,
        #tbItens input {
            height: 38px;
            font-size: 13px;
        }

        .mini {
            font-size: 12px;
            color: #64748b;
        }

        .modal-content {
            border-radius: 16px;
            overflow: hidden;
        }

        .modal-header {
            border-bottom: 1px solid rgba(148, 163, 184, .22);
        }

        .modal-footer {
            border-top: 1px solid rgba(148, 163, 184, .22);
        }
    </style>
</head>

<body>
    <!-- ======== sidebar-nav start =========== -->
    <aside class="sidebar-nav-wrapper">
        <div class="navbar-logo">
            <a href="index.php" class="d-flex align-items-center gap-2">
                <img src="assets/images/logo/logo.svg" alt="logo" />
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item">
                    <a href="index.php">
                        <span class="icon"><i class="lni lni-dashboard"></i></span>
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <!-- Operações -->
                <li class="nav-item nav-item-has-children active">
                    <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-controls="ddmenu_operacoes" aria-expanded="true">
                        <span class="icon"><i class="lni lni-grid-alt"></i></span>
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse show dropdown-nav">
                        <li><a href="pedidos.php">Pedidos</a></li>
                        <li class="active"><a href="vendas.php" class="active">Vendas</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <!-- Estoque -->
                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-controls="ddmenu_estoque" aria-expanded="false">
                        <span class="icon"><i class="lni lni-archive"></i></span>
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

                <!-- Cadastros -->
                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros" aria-controls="ddmenu_cadastros" aria-expanded="false">
                        <span class="icon"><i class="lni lni-users"></i></span>
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
                        <span class="icon"><i class="lni lni-book"></i></span>
                        <span class="text">Relatórios</span>
                    </a>
                </li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config" aria-controls="ddmenu_config" aria-expanded="false">
                        <span class="icon"><i class="lni lni-cog"></i></span>
                        <span class="text">Configurações</span>
                    </a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav">
                        <li><a href="usuarios.php">Usuários e Permissões</a></li>
                        <li><a href="parametros.php">Parâmetros do Sistema</a></li>
                    </ul>
                </li>

                <li class="nav-item">
                    <a href="suporte.php">
                        <span class="icon"><i class="lni lni-support"></i></span>
                        <span class="text">Suporte</span>
                    </a>
                </li>
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
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-compact" type="button">
                                    <i class="lni lni-chevron-left me-2"></i> Menu
                                </button>
                            </div>
                            <div class="header-search d-none d-md-flex">
                                <form action="#" onsubmit="return false;">
                                    <input type="text" placeholder="Buscar venda..." id="qGlobal" />
                                    <button type="submit" onclick="return false"><i class="lni lni-search-alt"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7 col-md-7 col-6">
                        <div class="header-right">
                            <div class="profile-box ml-15">
                                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile" data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="profile-info">
                                        <div class="info">
                                            <div class="image"><img src="assets/images/profile/profile-image.png" alt="perfil" /></div>
                                            <div>
                                                <h6 class="fw-500">Administrador</h6>
                                                <p>Distribuidora</p>
                                            </div>
                                        </div>
                                    </div>
                                </button>

                                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="profile">
                                    <li><a href="perfil.php"><i class="lni lni-user"></i> Meu Perfil</a></li>
                                    <li><a href="usuarios.php"><i class="lni lni-cog"></i> Usuários</a></li>
                                    <li class="divider"></li>
                                    <li><a href="logout.php"><i class="lni lni-exit"></i> Sair</a></li>
                                </ul>
                            </div>
                        </div>
                    </div>

                </div>
            </div>
        </header>

        <section class="section">
            <div class="container-fluid">
                <div class="title-wrapper pt-30">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <div class="title">
                                <h2>Vendas</h2>
                            </div>
                            <div class="muted">Ao salvar, o sistema dá baixa no estoque do produto.</div>
                        </div>
                    </div>
                </div>

                <?php if ($flash):
                    $t = (string)$flash['type'];
                    $cls = in_array($t, ['success', 'danger', 'warning', 'info'], true) ? $t : 'info';
                ?>
                    <div id="flashBox" class="alert alert-<?= e($cls) ?> flash-auto-hide mt-2">
                        <?= e((string)$flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <!-- Toolbar -->
                <div class="card-style mb-30">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Pesquisar</label>
                            <input type="text" class="form-control compact" id="qVendas" placeholder="Pedido, cliente, produto..." />
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Canal</label>
                            <select class="form-select compact" id="fCanal">
                                <option value="">Todos</option>
                                <option value="PRESENCIAL">Presencial</option>
                                <option value="DELIVERY">Delivery</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Período</label>
                            <div class="d-flex gap-2">
                                <input type="date" class="form-control compact" id="dtIni" />
                                <input type="date" class="form-control compact" id="dtFim" />
                            </div>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="d-grid gap-2 d-sm-flex justify-content-sm-end flex-wrap">
                                <button class="main-btn primary-btn btn-hover btn-compact" data-bs-toggle="modal" data-bs-target="#modalVenda" id="btnNovo" type="button">
                                    <i class="lni lni-plus me-1"></i> Nova
                                </button>
                                <button class="main-btn light-btn btn-hover btn-compact" id="btnExcel" type="button">
                                    <i class="lni lni-download me-1"></i> Excel
                                </button>
                                <button class="main-btn light-btn btn-hover btn-compact" id="btnPDF" type="button">
                                    <i class="lni lni-printer me-1"></i> PDF
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tabela -->
                <div class="card-style mb-30">
                    <div class="table-responsive">
                        <table class="table text-nowrap" id="tbVendas">
                            <thead>
                                <tr>
                                    <th style="min-width:120px;">Imagem</th>
                                    <th style="min-width:140px;">Data</th>
                                    <th style="min-width:140px;">Pedido</th>
                                    <th style="min-width:240px;">Cliente</th>
                                    <th style="min-width:140px;" class="td-center">Canal</th>
                                    <th style="min-width:140px;" class="td-center">Pagamento</th>
                                    <th style="min-width:120px;" class="td-center">Itens</th>
                                    <th style="min-width:160px;" class="td-center">Total</th>
                                    <th style="min-width:160px;" class="text-end">Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($vendas as $v):
                                    $id = (int)$v['id'];
                                    $dataYmd = (string)$v['data'];
                                    $dataBR = $dataYmd ? date('d/m/Y', strtotime($dataYmd)) : '';
                                    $canal = strtoupper((string)$v['canal']);
                                    $pag = strtoupper((string)$v['pagamento']);
                                    $pedido = (string)($v['pedido'] ?? '');
                                    $cliente = (string)$v['cliente'];
                                    $itensQtd = (int)($v['itens_qtd'] ?? 0);
                                    $total = (float)($v['total'] ?? 0);

                                    $firstImg = '';
                                    $items = $itensByVenda[$id] ?? [];
                                    if ($items) $firstImg = (string)($items[0]['imagem'] ?? '');
                                ?>
                                    <tr
                                        data-id="<?= $id ?>"
                                        data-data="<?= e($dataYmd) ?>"
                                        data-canal="<?= e($canal) ?>"
                                        data-pedido="<?= e($pedido) ?>"
                                        data-cliente="<?= e($cliente) ?>"
                                        data-pagamento="<?= e($pag) ?>"
                                        data-obs="<?= e((string)($v['obs'] ?? '')) ?>"
                                        data-total="<?= e((string)$total) ?>">
                                        <td>
                                            <img class="prod-img" src="<?= e($firstImg) ?>" alt="img" />
                                        </td>
                                        <td class="date"><?= e($dataBR) ?></td>
                                        <td class="ped"><?= e($pedido ?: ('VENDA-' . $id)) ?></td>
                                        <td class="cli"><?= e($cliente) ?></td>
                                        <td class="td-center canal"><?= e($canal) ?></td>
                                        <td class="td-center pagto"><?= e($pag) ?></td>
                                        <td class="td-center itens"><?= $itensQtd ?></td>
                                        <td class="td-center total"><?= e(float_to_brl($total)) ?></td>
                                        <td class="text-end">
                                            <button class="main-btn light-btn btn-hover icon-btn btnEdit" type="button" title="Editar">
                                                <i class="lni lni-pencil"></i>
                                            </button>
                                            <button class="main-btn danger-btn-outline btn-hover icon-btn btnDel" type="button" title="Excluir">
                                                <i class="lni lni-trash-can"></i>
                                            </button>
                                        </td>
                                    </tr>

                                    <script type="application/json" id="itens-<?= $id ?>">
                                        <?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
                                    </script>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <p class="text-sm text-gray mt-2 mb-0" id="infoCount"></p>
                </div>

            </div>
        </section>

        <footer class="footer">
            <div class="container-fluid">
                <div class="row">
                    <div class="col-md-6 order-last order-md-first">
                        <div class="copyright text-center text-md-start">
                            <p class="text-sm">Painel da Distribuidora • <span class="text-gray">v1.0</span></p>
                        </div>
                    </div>
                </div>
            </div>
        </footer>
    </main>

    <!-- DELETE FORM -->
    <form id="frmDelete" action="assets/dados/vendas/excluirVendas.php" method="post" style="display:none;">
        <?= csrf_input() ?>
        <input type="hidden" name="id" id="delId" value="">
    </form>

    <!-- Modal Venda -->
    <div class="modal fade" id="modalVenda" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-xl modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <div>
                        <h5 class="modal-title" id="modalVendaTitle" style="font-weight:1000;">Nova Venda</h5>
                        <div class="muted" id="modalVendaSub">Preencha os dados e adicione os itens.</div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <form id="formVenda" action="assets/dados/vendas/salvarVendas.php" method="post">
                        <?= csrf_input() ?>
                        <input type="hidden" name="id" id="vId" value="">

                        <div class="row g-3 mb-2">
                            <div class="col-md-3">
                                <label class="form-label">Data</label>
                                <input type="date" class="form-control compact" name="data" id="vData" required>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Pedido</label>
                                <input type="text" class="form-control compact" name="pedido" id="vPedido" placeholder="Ex: PED-0009">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Cliente *</label>
                                <input type="text" class="form-control compact" name="cliente" id="vCliente" placeholder="Nome do cliente" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Canal</label>
                                <select class="form-select compact" name="canal" id="vCanal" required>
                                    <option value="PRESENCIAL">Presencial</option>
                                    <option value="DELIVERY">Delivery</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Pagamento</label>
                                <select class="form-select compact" name="pagamento" id="vPagamento" required>
                                    <option>DINHEIRO</option>
                                    <option>PIX</option>
                                    <option>CARTÃO</option>
                                    <option>TRANSFERÊNCIA</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Observação</label>
                                <input type="text" class="form-control compact" name="obs" id="vObs" placeholder="Opcional">
                            </div>
                        </div>

                        <hr class="my-2">

                        <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-2">
                            <div style="font-weight:1000;color:#0f172a;"><i class="lni lni-cart me-1"></i> Itens da venda</div>
                            <button type="button" class="main-btn light-btn btn-hover btn-compact" id="btnAddItem">
                                <i class="lni lni-plus me-1"></i> Adicionar item
                            </button>
                        </div>

                        <div class="table-responsive">
                            <table class="table" id="tbItens">
                                <thead>
                                    <tr>
                                        <th style="min-width:420px;">Produto</th>
                                        <th style="min-width:130px;" class="td-center">Unidade</th>
                                        <th style="min-width:130px;" class="td-center">Estoque</th>
                                        <th style="min-width:140px;" class="td-center">Preço</th>
                                        <th style="min-width:120px;" class="td-center">Qtd</th>
                                        <th style="min-width:160px;" class="td-center">Subtotal</th>
                                        <th style="min-width:90px;" class="td-center">Rem</th>
                                    </tr>
                                </thead>
                                <tbody id="tbodyItens"></tbody>
                            </table>
                        </div>

                        <div class="d-flex justify-content-end mt-2">
                            <div style="min-width:280px;">
                                <div class="d-flex justify-content-between">
                                    <div style="font-weight:900;color:#0f172a;">Total</div>
                                    <div style="font-weight:900;color:#0f172a;" id="vTotalTxt">R$ 0,00</div>
                                </div>
                                <div class="mini">* Ao salvar: baixa no estoque do(s) produto(s).</div>
                            </div>
                        </div>

                    </form>
                </div>

                <div class="modal-footer">
                    <button type="button" class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formVenda" class="main-btn primary-btn btn-hover btn-compact">
                        <i class="lni lni-save me-1"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

    <!-- jsPDF + AutoTable (PDF) -->
    <script src="https://cdn.jsdelivr.net/npm/jspdf@2.5.1/dist/jspdf.umd.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/jspdf-autotable@3.8.2/dist/jspdf.plugin.autotable.min.js"></script>

    <script>
        // flash 1.5s
        (function() {
            const box = document.getElementById('flashBox');
            if (!box) return;
            setTimeout(() => {
                box.classList.add('hide');
                setTimeout(() => box.remove(), 400);
            }, 1500);
        })();

        const DEFAULT_IMG = "data:image/svg+xml;charset=utf-8," + encodeURIComponent(`
      <svg xmlns="http://www.w3.org/2000/svg" width="96" height="96">
        <rect width="100%" height="100%" fill="#f1f5f9"/>
        <path d="M18 68l18-18 12 12 10-10 20 20" fill="none" stroke="#94a3b8" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
        <circle cx="34" cy="34" r="7" fill="#94a3b8"/>
        <text x="50%" y="86%" text-anchor="middle" font-family="Arial" font-size="10" fill="#64748b">Sem imagem</text>
      </svg>
    `);

        // fallback img na lista
        document.querySelectorAll("img.prod-img").forEach(img => {
            const src = img.getAttribute('src') || '';
            if (!src) img.src = DEFAULT_IMG;
            img.addEventListener('error', () => img.src = DEFAULT_IMG, {
                once: true
            });
        });

        const tb = document.getElementById('tbVendas');
        const qVendas = document.getElementById('qVendas');
        const qGlobal = document.getElementById('qGlobal');
        const fCanal = document.getElementById('fCanal');
        const dtIni = document.getElementById('dtIni');
        const dtFim = document.getElementById('dtFim');
        const infoCount = document.getElementById('infoCount');

        function norm(s) {
            return String(s ?? '').toLowerCase().trim();
        }

        function aplicarFiltros() {
            const q = norm(qVendas.value || qGlobal.value);
            const canal = (fCanal.value || '').toUpperCase();
            const ini = dtIni.value || '';
            const fim = dtFim.value || '';

            const rows = Array.from(tb.querySelectorAll('tbody tr'));
            let shown = 0;

            rows.forEach(tr => {
                const text = norm(tr.innerText);
                const rCanal = (tr.getAttribute('data-canal') || '').toUpperCase();
                const rData = tr.getAttribute('data-data') || '';

                let ok = true;
                if (q && !text.includes(q)) ok = false;
                if (canal && rCanal !== canal) ok = false;

                if (ini && rData && rData < ini) ok = false;
                if (fim && rData && rData > fim) ok = false;

                tr.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });

            infoCount.textContent = `Mostrando ${shown} venda(s).`;
        }

        qVendas.addEventListener('input', aplicarFiltros);
        qGlobal.addEventListener('input', aplicarFiltros);
        fCanal.addEventListener('change', aplicarFiltros);
        dtIni.addEventListener('change', aplicarFiltros);
        dtFim.addEventListener('change', aplicarFiltros);
        aplicarFiltros();

        // ===== Modal =====
        const modalEl = document.getElementById('modalVenda');
        const modal = new bootstrap.Modal(modalEl);

        const modalTitle = document.getElementById('modalVendaTitle');
        const modalSub = document.getElementById('modalVendaSub');

        const vId = document.getElementById('vId');
        const vData = document.getElementById('vData');
        const vPedido = document.getElementById('vPedido');
        const vCliente = document.getElementById('vCliente');
        const vCanal = document.getElementById('vCanal');
        const vPagamento = document.getElementById('vPagamento');
        const vObs = document.getElementById('vObs');

        const tbodyItens = document.getElementById('tbodyItens');
        const vTotalTxt = document.getElementById('vTotalTxt');

        function parseBRL(txt) {
            let s = String(txt ?? '').trim();
            s = s.replace(/\s/g, '').replace('R$', '').replace(/\./g, '').replace(',', '.');
            const n = Number(s);
            return isNaN(n) ? 0 : n;
        }

        function fmtBRL(n) {
            return 'R$ ' + Number(n || 0).toFixed(2).replace('.', ',');
        }

        const PROD_OPTIONS_HTML = `
      <option value="">Selecione…</option>
      <?php foreach ($produtos as $p):
            $pid = (int)$p['id'];
            $cod = (string)$p['codigo'];
            $nome = (string)$p['nome'];
            $und = (string)($p['unidade'] ?? '');
            $preco = (float)($p['preco'] ?? 0);
            $est = (int)($p['estoque'] ?? 0);
            $img = img_url((string)($p['imagem'] ?? ''), $IMG_PREFIX);
        ?>
        <option
          value="<?= $pid ?>"
          data-codigo="<?= e($cod) ?>"
          data-nome="<?= e($nome) ?>"
          data-unidade="<?= e($und) ?>"
          data-preco="<?= e((string)$preco) ?>"
          data-estoque="<?= e((string)$est) ?>"
          data-img="<?= e($img) ?>"
        ><?= e($cod . ' - ' . $nome) ?></option>
      <?php endforeach; ?>
    `;

        function makeItemRow(item) {
            // item: {produto_id, qtd, preco}
            const pid = item && item.produto_id ? String(item.produto_id) : '';
            const qtd = item && item.qtd ? Number(item.qtd) : 1;
            const preco = item && (item.preco !== undefined) ? Number(item.preco) : 0;

            const tr = document.createElement('tr');
            tr.innerHTML = `
        <td>
          <select class="form-select compact it-prod" name="produto_id[]">${PROD_OPTIONS_HTML}</select>
          <div class="mini it-mini"></div>
        </td>
        <td class="td-center"><span class="it-und">—</span></td>
        <td class="td-center"><span class="it-est">—</span></td>
        <td class="td-center">
          <input class="form-control compact td-center it-preco" name="preco[]" value="${preco ? String(preco).replace('.',',') : ''}" placeholder="0,00">
        </td>
        <td class="td-center">
          <input type="number" min="1" class="form-control compact td-center it-qtd" name="qtd[]" value="${qtd}">
        </td>
        <td class="td-center"><span class="it-sub">R$ 0,00</span></td>
        <td class="td-center">
          <button type="button" class="main-btn danger-btn-outline btn-hover icon-btn it-del" title="Remover">
            <i class="lni lni-trash-can"></i>
          </button>
        </td>
      `;

            const sel = tr.querySelector('.it-prod');
            const inpQtd = tr.querySelector('.it-qtd');
            const inpPreco = tr.querySelector('.it-preco');

            if (pid) sel.value = pid;

            function syncFromSelect() {
                const opt = sel.options[sel.selectedIndex];
                if (!opt || !sel.value) {
                    tr.querySelector('.it-und').textContent = '—';
                    tr.querySelector('.it-est').textContent = '—';
                    tr.querySelector('.it-mini').textContent = '';
                    calcRow();
                    return;
                }
                const und = opt.getAttribute('data-unidade') || '';
                const est = opt.getAttribute('data-estoque') || '0';
                const precoOpt = opt.getAttribute('data-preco') || '0';
                const cod = opt.getAttribute('data-codigo') || '';
                const nome = opt.getAttribute('data-nome') || '';
                tr.querySelector('.it-und').textContent = und || '—';
                tr.querySelector('.it-est').textContent = est;
                tr.querySelector('.it-mini').textContent = `Produto: ${cod} • ${nome}`;
                // se preço vazio, seta o do produto
                if (!String(inpPreco.value || '').trim()) {
                    inpPreco.value = String(Number(precoOpt || 0)).toFixed(2).replace('.', ',');
                }
                calcRow();
            }

            function calcRow() {
                const q = Number(inpQtd.value || 0);
                const p = parseBRL(inpPreco.value);
                const sub = q * p;
                tr.querySelector('.it-sub').textContent = fmtBRL(sub);
                calcTotal();
            }

            sel.addEventListener('change', syncFromSelect);
            inpQtd.addEventListener('input', calcRow);
            inpPreco.addEventListener('input', calcRow);

            tr.querySelector('.it-del').addEventListener('click', () => {
                tr.remove();
                calcTotal();
            });

            // inicial
            syncFromSelect();
            return tr;
        }

        function calcTotal() {
            const rows = Array.from(tbodyItens.querySelectorAll('tr'));
            let total = 0;
            rows.forEach(tr => {
                const subTxt = tr.querySelector('.it-sub')?.textContent || '0';
                total += parseBRL(subTxt);
            });
            vTotalTxt.textContent = fmtBRL(total);
        }

        function setToday() {
            const d = new Date();
            const yyyy = d.getFullYear();
            const mm = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            vData.value = `${yyyy}-${mm}-${dd}`;
        }

        function openNew() {
            modalTitle.textContent = 'Nova Venda';
            modalSub.textContent = 'Preencha os dados e adicione os itens.';
            vId.value = '';
            setToday();
            vPedido.value = '';
            vCliente.value = '';
            vCanal.value = 'PRESENCIAL';
            vPagamento.value = 'DINHEIRO';
            vObs.value = '';
            tbodyItens.innerHTML = '';
            tbodyItens.appendChild(makeItemRow(null));
            calcTotal();
            modal.show();
            setTimeout(() => vCliente.focus(), 150);
        }

        document.getElementById('btnNovo').addEventListener('click', openNew);
        document.getElementById('btnAddItem').addEventListener('click', () => {
            tbodyItens.appendChild(makeItemRow(null));
            calcTotal();
        });

        // editar/excluir na tabela
        tb.addEventListener('click', (e) => {
            const tr = e.target.closest('tr');
            if (!tr) return;

            const btnDel = e.target.closest('.btnDel');
            if (btnDel) {
                const id = tr.getAttribute('data-id');
                const ped = tr.getAttribute('data-pedido') || ('VENDA-' + id);
                if (confirm(`Excluir ${ped}? (o estoque será devolvido)`)) {
                    document.getElementById('delId').value = id || '';
                    document.getElementById('frmDelete').submit();
                }
                return;
            }

            const btnEdit = e.target.closest('.btnEdit');
            if (btnEdit) {
                const id = tr.getAttribute('data-id') || '';
                modalTitle.textContent = 'Editar Venda';
                modalSub.textContent = 'Altere e salve. (o sistema ajusta estoque pela diferença)';
                vId.value = id;

                vData.value = tr.getAttribute('data-data') || '';
                vPedido.value = tr.getAttribute('data-pedido') || '';
                vCliente.value = tr.getAttribute('data-cliente') || '';
                vCanal.value = (tr.getAttribute('data-canal') || 'PRESENCIAL').toUpperCase();
                vPagamento.value = (tr.getAttribute('data-pagamento') || 'DINHEIRO').toUpperCase();
                vObs.value = tr.getAttribute('data-obs') || '';

                // itens do script json
                const js = document.getElementById('itens-' + id);
                let itens = [];
                try {
                    itens = js ? JSON.parse(js.textContent || '[]') : [];
                } catch {
                    itens = [];
                }

                tbodyItens.innerHTML = '';
                if (!itens.length) {
                    tbodyItens.appendChild(makeItemRow(null));
                } else {
                    itens.forEach(it => {
                        tbodyItens.appendChild(makeItemRow({
                            produto_id: it.produto_id,
                            qtd: it.qtd,
                            preco: it.preco
                        }));
                    });
                }
                calcTotal();

                modal.show();
            }
        });

        // ✅ Excel (.xls) estilizado
        function exportExcel() {
            const rows = Array.from(tb.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');

            const now = new Date();
            const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');

            const canal = fCanal.value || 'Todos';
            const ini = dtIni.value || '—';
            const fim = dtFim.value || '—';

            const header = ['Data', 'Pedido', 'Cliente', 'Canal', 'Pagamento', 'Itens', 'Total'];

            const body = rows.map(tr => ([
                tr.querySelector('.date')?.innerText.trim() || '',
                tr.querySelector('.ped')?.innerText.trim() || '',
                tr.querySelector('.cli')?.innerText.trim() || '',
                tr.querySelector('.canal')?.innerText.trim() || '',
                tr.querySelector('.pagto')?.innerText.trim() || '',
                tr.querySelector('.itens')?.innerText.trim() || '',
                tr.querySelector('.total')?.innerText.trim() || ''
            ]));

            const isCenterCol = (idx) => (idx === 3 || idx === 4 || idx === 5 || idx === 6);

            let html = `
        <html><head><meta charset="utf-8">
          <style>
            table { border:0.6px solid #999; font-family:Arial; font-size:12px; }
            td,th { border:1px solid #999; padding:6px 8px; vertical-align:middle; }
            th { background:#f1f5f9; font-weight:700; }
            .title { font-size:16px; font-weight:700; background:#eef2ff; text-align:center; }
            .muted { color:#555; font-weight:700; }
            .center { text-align:center; }
          </style>
        </head><body><table>
      `;

            html += `<tr><td class="title" colspan="7">PAINEL DA DISTRIBUIDORA - VENDAS</td></tr>`;
            html += `<tr><td class="muted">Gerado em:</td><td colspan="6">${dt}</td></tr>`;
            html += `<tr><td class="muted">Canal:</td><td>${canal}</td><td class="muted">Período:</td><td colspan="4">${ini} até ${fim}</td></tr>`;

            html += `<tr>${header.map((h, idx) => `<th class="${isCenterCol(idx)?'center':''}">${h}</th>`).join('')}</tr>`;
            body.forEach(r => {
                html += `<tr>${r.map((c, idx) => {
          const safe = String(c).replaceAll('&','&amp;').replaceAll('<','&lt;').replaceAll('>','&gt;');
          const cls = isCenterCol(idx) ? 'center' : '';
          return `<td class="${cls}">${safe}</td>`;
        }).join('')}</tr>`;
            });

            html += `</table></body></html>`;

            const blob = new Blob(["\ufeff" + html], {
                type: 'application/vnd.ms-excel;charset=utf-8;'
            });
            const url = URL.createObjectURL(blob);

            const a = document.createElement('a');
            a.href = url;
            a.download = 'vendas.xls';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }
        document.getElementById('btnExcel').addEventListener('click', exportExcel);

        // ✅ PDF
        function exportPDF() {
            if (!window.jspdf || !window.jspdf.jsPDF) {
                alert('Biblioteca do PDF não carregou.');
                return;
            }

            const rows = Array.from(tb.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');

            const now = new Date();
            const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');

            const canal = fCanal.value || 'Todos';
            const ini = dtIni.value || '—';
            const fim = dtFim.value || '—';

            const {
                jsPDF
            } = window.jspdf;
            const doc = new jsPDF({
                orientation: 'landscape',
                unit: 'pt',
                format: 'a4'
            });

            const M = 70;

            doc.setTextColor(0, 0, 0);
            doc.setFont('helvetica', 'bold');
            doc.setFontSize(14);
            doc.text('PAINEL DA DISTRIBUIDORA - VENDAS', M, 55);

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.text(`Gerado em:  ${dt}`, M, 75);
            doc.text(`Canal:  ${canal} | Período:  ${ini} até ${fim}`, M, 92);

            const head = [
                ['Data', 'Pedido', 'Cliente', 'Canal', 'Pagamento', 'Itens', 'Total']
            ];

            const body = rows.map(tr => ([
                tr.querySelector('.date')?.innerText.trim() || '',
                tr.querySelector('.ped')?.innerText.trim() || '',
                tr.querySelector('.cli')?.innerText.trim() || '',
                tr.querySelector('.canal')?.innerText.trim() || '',
                tr.querySelector('.pagto')?.innerText.trim() || '',
                tr.querySelector('.itens')?.innerText.trim() || '',
                tr.querySelector('.total')?.innerText.trim() || '',
            ]));

            doc.autoTable({
                head,
                body,
                startY: 115,
                margin: {
                    left: M,
                    right: M
                },
                theme: 'plain',
                styles: {
                    font: 'helvetica',
                    fontSize: 9,
                    cellPadding: {
                        top: 6,
                        right: 6,
                        bottom: 6,
                        left: 6
                    },
                    lineWidth: 0
                },
                headStyles: {
                    fillColor: [241, 245, 249],
                    fontStyle: 'bold',
                    lineWidth: 0
                },
                alternateRowStyles: {
                    fillColor: [248, 250, 252]
                },
                columnStyles: {
                    3: {
                        halign: 'center'
                    },
                    4: {
                        halign: 'center'
                    },
                    5: {
                        halign: 'center'
                    },
                    6: {
                        halign: 'center'
                    }
                },
                didParseCell: function(data) {
                    data.cell.styles.lineWidth = 0;
                }
            });

            doc.save('vendas.pdf');
        }
        document.getElementById('btnPDF').addEventListener('click', exportPDF);
    </script>
</body>

</html>