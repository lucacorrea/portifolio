<?php

declare(strict_types=1);
session_start();

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/_helpers.php';

$pdo = db();

$csrf  = csrf_token();
$flash = flash_pop();

function brDate(?string $ymd): string
{
    if (!$ymd) return '';
    $p = explode('-', $ymd);
    if (count($p) !== 3) return $ymd;
    return $p[2] . '/' . $p[1] . '/' . $p[0];
}

function fmtBRL($n): string
{
    return 'R$ ' . number_format((float)$n, 2, ',', '.');
}

/**
 * Banco guarda: images/arquivo.png
 * Página precisa: assets/dados/produtos/images/arquivo.png
 */
function prodImgUrl(?string $dbPath): string
{
    $s = trim((string)$dbPath);
    if ($s === '') return '';
    if (preg_match('~^(https?://|/|data:)~i', $s)) return $s;

    // se já vier completo, mantém
    if (str_starts_with($s, 'assets/')) return $s;

    // padrão do seu banco: images/....
    return 'assets/dados/produtos/' . ltrim($s, '/');
}

// Produtos (para select + imagem)
$produtos = $pdo->query("
  SELECT id, codigo, nome, unidade, preco, imagem, status
  FROM produtos
  ORDER BY nome ASC
")->fetchAll(PDO::FETCH_ASSOC);

// Saídas (lista)
$saidas = $pdo->query("
  SELECT s.*,
         p.codigo   AS produto_codigo,
         p.nome     AS produto_nome,
         p.imagem   AS produto_imagem
  FROM saidas s
  LEFT JOIN produtos p ON p.id = s.produto_id
  ORDER BY s.data DESC, s.id DESC
  LIMIT 3000
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Saídas</title>

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

        .profile-box .dropdown-menu .author-info {
            width: max-content;
            max-width: 100%;
            display: flex !important;
            align-items: center;
            gap: 10px;
        }

        .profile-box .dropdown-menu .author-info .content {
            min-width: 0;
            max-width: 100%;
        }

        .profile-box .dropdown-menu .author-info .content a {
            display: inline-block;
            white-space: nowrap;
            max-width: 100%;
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

        .table td,
        .table th {
            vertical-align: middle;
        }

        .minw-120 {
            min-width: 120px;
        }

        .minw-140 {
            min-width: 140px;
        }

        .minw-160 {
            min-width: 160px;
        }

        .minw-200 {
            min-width: 200px;
        }

        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        #tbSaidas {
            width: 100%;
            min-width: 1480px;
        }

        #tbSaidas th,
        #tbSaidas td {
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important;
        }

        .badge-soft {
            padding: .35rem .6rem;
            border-radius: 999px;
            font-weight: 700;
            font-size: .72rem;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-width: 90px;
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

        .prod-img {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, .35);
            background: #fff;
        }

        .td-center {
            text-align: center;
        }

        .td-right {
            text-align: right;
        }

        .form-control.compact,
        .form-select.compact {
            height: 38px;
            padding: 8px 12px;
            font-size: 13px;
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

        .flash-auto-hide {
            transition: opacity .35s ease, transform .35s ease;
        }

        .flash-auto-hide.hide {
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- sidebar (mantive seu layout, só não repeti tudo do perfil pra não estourar) -->
    <aside class="sidebar-nav-wrapper">
        <div class="navbar-logo">
            <a href="index.php" class="d-flex align-items-center gap-2">
                <img src="assets/images/logo/logo.svg" alt="logo" />
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <li class="nav-item"><a href="index.php"><span class="text">Dashboard</span></a></li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes">Operações</a>
                    <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
                        <li><a href="pedidos.php">Pedidos</a></li>
                        <li><a href="vendas.php">Vendas</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-expanded="true">Estoque</a>
                    <ul id="ddmenu_estoque" class="collapse show dropdown-nav">
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php">Inventário</a></li>
                        <li><a href="entradas.php">Entradas</a></li>
                        <li><a href="saidas.php" class="active">Saídas</a></li>
                        <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_cadastros">Cadastros</a>
                    <ul id="ddmenu_cadastros" class="collapse dropdown-nav">
                        <li><a href="clientes.php">Clientes</a></li>
                        <li><a href="fornecedores.php">Fornecedores</a></li>
                        <li><a href="categorias.php">Categorias</a></li>
                    </ul>
                </li>

                <li class="nav-item"><a href="relatorios.php"><span class="text">Relatórios</span></a></li>
                <span class="divider">
                    <hr />
                </span>
                <li class="nav-item"><a href="suporte.php"><span class="text">Suporte</span></a></li>
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
                                <button id="menu-toggle" class="main-btn primary-btn btn-hover btn-compact">
                                    <i class="lni lni-chevron-left me-2"></i> Menu
                                </button>
                            </div>
                            <div class="header-search d-none d-md-flex">
                                <form action="#" onsubmit="return false;">
                                    <input type="text" placeholder="Buscar saída..." id="qGlobal" />
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
                                <h2>Saídas</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($flash): ?>
                    <div id="flashBox" class="alert alert-<?= e((string)$flash['type']) ?> flash-auto-hide mt-2">
                        <?= e((string)$flash['msg']) ?>
                    </div>
                <?php endif; ?>

                <!-- Toolbar -->
                <div class="card-style mb-30">
                    <div class="row g-3 align-items-end">
                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Pesquisar</label>
                            <input type="text" class="form-control compact" id="qSaidas" placeholder="Pedido, cliente, produto..." />
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
                                <button class="main-btn primary-btn btn-hover btn-compact" data-bs-toggle="modal" data-bs-target="#modalSaida" id="btnNovo" type="button">
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
                        <table class="table text-nowrap" id="tbSaidas">
                            <thead>
                                <tr>
                                    <th class="minw-120">Imagem</th>
                                    <th class="minw-140">Data</th>
                                    <th class="minw-140">Pedido</th>
                                    <th class="minw-200">Cliente</th>
                                    <th class="minw-140 td-center">Canal</th>
                                    <th class="minw-140 td-center">Pagamento</th>
                                    <th class="minw-140">Código</th>
                                    <th class="minw-200">Produto</th>
                                    <th class="minw-140">Unidade</th>
                                    <th class="minw-140 td-center">Qtd</th>
                                    <th class="minw-140 td-center">Preço</th>
                                    <th class="minw-160 td-center">Total</th>
                                    <th class="minw-140 text-end">Ações</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($saidas as $s): ?>
                                    <?php
                                    $id = (int)$s['id'];
                                    $ymd = (string)$s['data'];
                                    $canal = strtoupper((string)$s['canal']);
                                    $pag = strtoupper((string)$s['pagamento']);

                                    $img = prodImgUrl((string)($s['produto_imagem'] ?? ''));
                                    $codigo = trim((string)($s['produto_codigo'] ?? '')) ?: '—';
                                    $prodNome = trim((string)($s['produto_nome'] ?? '')) ?: '—';

                                    $qtd = (float)$s['qtd'];
                                    $preco = (float)$s['preco'];
                                    $total = (float)$s['total'];

                                    $badgeCanal = ($canal === 'DELIVERY')
                                        ? '<span class="badge-soft badge-soft-success">DELIVERY</span>'
                                        : '<span class="badge-soft badge-soft-gray">PRESENCIAL</span>';
                                    ?>
                                    <tr
                                        data-id="<?= $id ?>"
                                        data-data="<?= e($ymd) ?>"
                                        data-canal="<?= e($canal) ?>"
                                        data-pedido="<?= e((string)$s['pedido']) ?>"
                                        data-cliente="<?= e((string)$s['cliente']) ?>"
                                        data-pagamento="<?= e($pag) ?>"
                                        data-produto-id="<?= (int)$s['produto_id'] ?>"
                                        data-unidade="<?= e((string)$s['unidade']) ?>"
                                        data-qtd="<?= e((string)$s['qtd']) ?>"
                                        data-preco="<?= e((string)$s['preco']) ?>"
                                        data-img="<?= e($img) ?>">
                                        <td><img class="prod-img" alt="<?= e($prodNome) ?>" src="<?= e($img) ?>" /></td>
                                        <td class="date"><?= e(brDate($ymd)) ?></td>
                                        <td class="ped"><?= e((string)$s['pedido']) ?></td>
                                        <td class="cli"><?= e((string)$s['cliente']) ?></td>
                                        <td class="td-center canal"><?= $badgeCanal ?></td>
                                        <td class="td-center pagto"><?= e($pag) ?></td>
                                        <td class="cod"><?= e($codigo) ?></td>
                                        <td class="prod"><?= e($prodNome) ?></td>
                                        <td class="und"><?= e((string)$s['unidade']) ?></td>
                                        <td class="td-center qtd"><?= e(number_format($qtd, 3, ',', '.')) ?></td>
                                        <td class="td-center preco"><?= e(fmtBRL($preco)) ?></td>
                                        <td class="td-center total"><?= e(fmtBRL($total)) ?></td>
                                        <td class="text-end">
                                            <button class="main-btn light-btn btn-hover icon-btn btnEdit" type="button" title="Editar"><i class="lni lni-pencil"></i></button>
                                            <button class="main-btn danger-btn-outline btn-hover icon-btn btnDel" type="button" title="Excluir"><i class="lni lni-trash-can"></i></button>
                                        </td>
                                    </tr>
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
    <form id="frmDelete" action="assets/dados/saidas/excluirSaidas.php" method="post" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="delId" value="">
    </form>

    <!-- Modal Saída -->
    <div class="modal fade" id="modalSaida" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalSaidaTitle">Nova Saída</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <form id="formSaida" action="assets/dados/saidas/salvarSaidas.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" id="pId" value="">

                        <div class="row g-3">
                            <!-- IMAGEM CENTRAL (vem do produto) -->
                            <div class="col-12">
                                <div class="d-flex justify-content-center">
                                    <div class="img-block text-center">
                                        <label class="form-label">Imagem (do produto)</label>
                                        <div class="d-flex flex-column gap-2 align-items-center">
                                            <img id="previewImg" class="img-preview" alt="Prévia" />
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <hr class="my-2">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Data</label>
                                <input type="date" class="form-control compact" id="pData" name="data" required />
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Pedido</label>
                                <input type="text" class="form-control compact" id="pPedido" name="pedido" placeholder="Ex: PED-0009" required />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Cliente</label>
                                <input type="text" class="form-control compact" id="pCliente" name="cliente" placeholder="Nome do cliente" required />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Canal</label>
                                <select class="form-select compact" id="pCanal" name="canal" required>
                                    <option value="">Selecione…</option>
                                    <option value="PRESENCIAL">Presencial</option>
                                    <option value="DELIVERY">Delivery</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Pagamento</label>
                                <select class="form-select compact" id="pPagamento" name="pagamento" required>
                                    <option value="">Selecione…</option>
                                    <option>DINHEIRO</option>
                                    <option>PIX</option>
                                    <option>CARTÃO</option>
                                    <option>TRANSFERÊNCIA</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Produto</label>
                                <select class="form-select compact" id="pProdutoId" name="produto_id" required>
                                    <option value="">Selecione…</option>
                                    <?php foreach ($produtos as $p): ?>
                                        <?php
                                        $img = prodImgUrl((string)($p['imagem'] ?? ''));
                                        $cod = (string)($p['codigo'] ?? '');
                                        $nm  = (string)($p['nome'] ?? '');
                                        $un  = (string)($p['unidade'] ?? '');
                                        $pr  = (string)($p['preco'] ?? '0');
                                        ?>
                                        <option
                                            value="<?= (int)$p['id'] ?>"
                                            data-img="<?= e($img) ?>"
                                            data-codigo="<?= e($cod) ?>"
                                            data-nome="<?= e($nm) ?>"
                                            data-unidade="<?= e($un) ?>"
                                            data-preco="<?= e($pr) ?>">
                                            <?= e($cod . ' - ' . $nm) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control compact" id="pCodigo" placeholder="auto" readonly />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Produto</label>
                                <input type="text" class="form-control compact" id="pProduto" placeholder="auto" readonly />
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Unidade</label>
                                <select class="form-select compact" id="pUnidade" name="unidade" required>
                                    <option value="">Selecione…</option>
                                    <option>Unidade</option>
                                    <option>Pacote</option>
                                    <option>Caixa</option>
                                    <option>Kg</option>
                                    <option>Litro</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Qtd</label>
                                <input type="number" step="0.001" class="form-control compact td-center" id="pQtd" name="qtd" min="0" value="0" required />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Preço (un)</label>
                                <input type="text" class="form-control compact td-center" id="pPreco" name="preco" placeholder="0,00" required />
                            </div>

                            <div class="col-md-5">
                                <label class="form-label">Total</label>
                                <input type="text" class="form-control compact td-center" id="pTotal" placeholder="0,00" readonly />
                            </div>
                        </div>
                    </form>

                    <p class="text-sm text-gray mt-3 mb-0">
                        O total é calculado automaticamente: <b>Qtd × Preço</b>.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formSaida" class="main-btn primary-btn btn-hover btn-compact">
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

        // fallback de imagens na tabela
        document.querySelectorAll("img.prod-img").forEach(img => {
            const src = img.getAttribute('src') || '';
            if (!src) img.src = DEFAULT_IMG;
            img.addEventListener('error', () => img.src = DEFAULT_IMG, {
                once: true
            });
        });

        const tb = document.getElementById('tbSaidas');
        const qSaidas = document.getElementById('qSaidas');
        const qGlobal = document.getElementById('qGlobal');
        const fCanal = document.getElementById('fCanal');
        const dtIni = document.getElementById('dtIni');
        const dtFim = document.getElementById('dtFim');
        const infoCount = document.getElementById('infoCount');

        function norm(s) {
            return String(s ?? '').toLowerCase().trim();
        }

        function parseBRL(txt) {
            let s = String(txt ?? '').trim();
            s = s.replace(/\s/g, '').replace('R$', '').replace(/\./g, '').replace(',', '.');
            const n = Number(s);
            return isNaN(n) ? 0 : n;
        }

        function fmtBRL(n) {
            return 'R$ ' + Number(n || 0).toFixed(2).replace('.', ',');
        }

        function aplicarFiltros() {
            const q = norm(qSaidas.value || qGlobal.value);
            const canal = fCanal.value || '';
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

            infoCount.textContent = `Mostrando ${shown} saída(s).`;
        }

        qSaidas.addEventListener('input', aplicarFiltros);
        qGlobal.addEventListener('input', aplicarFiltros);
        fCanal.addEventListener('change', aplicarFiltros);
        dtIni.addEventListener('change', aplicarFiltros);
        dtFim.addEventListener('change', aplicarFiltros);
        aplicarFiltros();

        // ===== Modal =====
        const modalEl = document.getElementById('modalSaida');
        const modal = new bootstrap.Modal(modalEl);
        const modalTitle = document.getElementById('modalSaidaTitle');

        const pId = document.getElementById('pId');
        const previewImg = document.getElementById('previewImg');

        const pData = document.getElementById('pData');
        const pPedido = document.getElementById('pPedido');
        const pCliente = document.getElementById('pCliente');
        const pCanal = document.getElementById('pCanal');
        const pPagamento = document.getElementById('pPagamento');

        const pProdutoId = document.getElementById('pProdutoId');
        const pCodigo = document.getElementById('pCodigo');
        const pProduto = document.getElementById('pProduto');
        const pUnidade = document.getElementById('pUnidade');

        const pQtd = document.getElementById('pQtd');
        const pPreco = document.getElementById('pPreco');
        const pTotal = document.getElementById('pTotal');

        function setPreview(src) {
            previewImg.src = src || DEFAULT_IMG;
        }

        function todayYMD() {
            const t = new Date();
            const yyyy = t.getFullYear();
            const mm = String(t.getMonth() + 1).padStart(2, '0');
            const dd = String(t.getDate()).padStart(2, '0');
            return `${yyyy}-${mm}-${dd}`;
        }

        function limparForm() {
            pId.value = '';
            setPreview(DEFAULT_IMG);

            pData.value = todayYMD();
            pPedido.value = '';
            pCliente.value = '';
            pCanal.value = '';
            pPagamento.value = '';

            pProdutoId.value = '';
            pCodigo.value = '';
            pProduto.value = '';
            pUnidade.value = '';

            pQtd.value = 0;
            pPreco.value = '';
            pTotal.value = fmtBRL(0);
        }

        document.getElementById('btnNovo').addEventListener('click', () => {
            modalTitle.textContent = 'Nova Saída';
            limparForm();
        });

        function recalcularTotal() {
            const qtd = Number(pQtd.value || 0);
            const preco = parseBRL(pPreco.value);
            pTotal.value = fmtBRL(qtd * preco);
        }
        pQtd.addEventListener('input', recalcularTotal);
        pPreco.addEventListener('input', recalcularTotal);

        // quando seleciona produto, preenche dados + imagem
        pProdutoId.addEventListener('change', () => {
            const opt = pProdutoId.selectedOptions && pProdutoId.selectedOptions[0];
            if (!opt || !opt.value) {
                pCodigo.value = '';
                pProduto.value = '';
                setPreview(DEFAULT_IMG);
                return;
            }

            pCodigo.value = opt.getAttribute('data-codigo') || '';
            pProduto.value = opt.getAttribute('data-nome') || '';
            const und = opt.getAttribute('data-unidade') || '';
            if (und) pUnidade.value = und;

            const preco = opt.getAttribute('data-preco') || '';
            if (preco) {
                // joga no input como 0,00
                const n = Number(String(preco).replace(',', '.'));
                if (!Number.isNaN(n)) pPreco.value = String(n.toFixed(2)).replace('.', ',');
            }

            setPreview(opt.getAttribute('data-img') || DEFAULT_IMG);
            recalcularTotal();
        });

        // editar/excluir
        tb.addEventListener('click', (e) => {
            const tr = e.target.closest('tr');
            if (!tr) return;

            const btnDel = e.target.closest('.btnDel');
            if (btnDel) {
                const id = tr.getAttribute('data-id') || '';
                const ped = tr.querySelector('.ped')?.innerText || '';
                if (confirm(`Remover saída ${ped}?`)) {
                    document.getElementById('delId').value = id;
                    document.getElementById('frmDelete').submit();
                }
                return;
            }

            const btnEdit = e.target.closest('.btnEdit');
            if (!btnEdit) return;

            modalTitle.textContent = 'Editar Saída';

            pId.value = tr.getAttribute('data-id') || '';
            pData.value = tr.getAttribute('data-data') || todayYMD();
            pPedido.value = tr.getAttribute('data-pedido') || '';
            pCliente.value = tr.getAttribute('data-cliente') || '';
            pCanal.value = (tr.getAttribute('data-canal') || '').toUpperCase();
            pPagamento.value = (tr.getAttribute('data-pagamento') || '').toUpperCase();

            pProdutoId.value = tr.getAttribute('data-produto-id') || '';
            // força disparar o fill
            pProdutoId.dispatchEvent(new Event('change'));

            pUnidade.value = tr.getAttribute('data-unidade') || pUnidade.value || '';

            const qtd = tr.getAttribute('data-qtd') || '0';
            pQtd.value = qtd;

            const preco = tr.getAttribute('data-preco') || '0';
            const pn = Number(String(preco).replace(',', '.'));
            pPreco.value = Number.isNaN(pn) ? '' : String(pn.toFixed(2)).replace('.', ',');

            const img = tr.getAttribute('data-img') || '';
            setPreview(img || DEFAULT_IMG);

            recalcularTotal();
            modal.show();
        });

        // ✅ Excel (.xls) e PDF funcionando (igual seu código antigo)
        function exportExcel() {
            const rows = Array.from(tb.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');

            const now = new Date();
            const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');

            const canal = fCanal.value || 'Todos';
            const ini = dtIni.value || '';
            const fim = dtFim.value || '';

            const header = ['Data', 'Pedido', 'Cliente', 'Canal', 'Pagamento', 'Código', 'Produto', 'Unidade', 'Qtd', 'Preço', 'Total'];

            const body = rows.map(tr => ([
                tr.querySelector('.date')?.innerText.trim() || '',
                tr.querySelector('.ped')?.innerText.trim() || '',
                tr.querySelector('.cli')?.innerText.trim() || '',
                (tr.getAttribute('data-canal') || '').toUpperCase(),
                tr.querySelector('.pagto')?.innerText.trim() || '',
                tr.querySelector('.cod')?.innerText.trim() || '',
                tr.querySelector('.prod')?.innerText.trim() || '',
                tr.querySelector('.und')?.innerText.trim() || '',
                tr.querySelector('.qtd')?.innerText.trim() || '',
                tr.querySelector('.preco')?.innerText.trim() || '',
                tr.querySelector('.total')?.innerText.trim() || ''
            ]));

            const isCenterCol = (idx) => (idx === 3 || idx === 4 || idx === 8 || idx === 9 || idx === 10);

            let html = `
      <html>
        <head>
          <meta charset="utf-8">
          <style>
            table { border: 0.6px solid #999; font-family: Arial; font-size: 12px; }
            td, th { border: 1px solid #999; padding: 6px 8px; vertical-align: middle; }
            th { background: #f1f5f9; font-weight: 700; }
            .title { font-size: 16px; font-weight: 700; background: #eef2ff; text-align: center; }
            .muted { color: #555; font-weight: 700; }
            .center { text-align: center; }
          </style>
        </head>
        <body>
          <table>
    `;

            html += `<tr><td class="title" colspan="11">PAINEL DA DISTRIBUIDORA - SAÍDAS</td></tr>`;
            html += `<tr><td class="muted">Gerado em:</td><td colspan="10">${dt}</td></tr>`;
            html += `<tr><td class="muted">Canal:</td><td>${canal}</td><td class="muted">Período:</td><td colspan="8">${ini || '—'} até ${fim || '—'}</td></tr>`;

            html += `<tr>${header.map((h, idx) => `<th class="${isCenterCol(idx) ? 'center' : ''}">${h}</th>`).join('')}</tr>`;

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
            a.download = 'saidas.xls';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }
        document.getElementById('btnExcel').addEventListener('click', exportExcel);

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
            doc.text('PAINEL DA DISTRIBUIDORA - SAÍDAS', M, 55);

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.text(`Gerado em:  ${dt}`, M, 75);
            doc.text(`Canal:  ${canal} | Período:  ${ini} até ${fim}`, M, 92);

            const head = [
                ['Data', 'Pedido', 'Cliente', 'Canal', 'Pagamento', 'Código', 'Produto', 'Unidade', 'Qtd', 'Preço', 'Total']
            ];

            const body = rows.map(tr => ([
                tr.querySelector('.date')?.innerText.trim() || '',
                tr.querySelector('.ped')?.innerText.trim() || '',
                tr.querySelector('.cli')?.innerText.trim() || '',
                (tr.getAttribute('data-canal') || '').toUpperCase(),
                tr.querySelector('.pagto')?.innerText.trim() || '',
                tr.querySelector('.cod')?.innerText.trim() || '',
                tr.querySelector('.prod')?.innerText.trim() || '',
                tr.querySelector('.und')?.innerText.trim() || '',
                tr.querySelector('.qtd')?.innerText.trim() || '',
                tr.querySelector('.preco')?.innerText.trim() || '',
                tr.querySelector('.total')?.innerText.trim() || ''
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
                    textColor: [17, 24, 39],
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
                    textColor: [17, 24, 39],
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
                    8: {
                        halign: 'center'
                    },
                    9: {
                        halign: 'center'
                    },
                    10: {
                        halign: 'center'
                    }
                },
                didParseCell: function(data) {
                    data.cell.styles.lineWidth = 0;
                }
            });

            doc.save('saidas.pdf');
        }
        document.getElementById('btnPDF').addEventListener('click', exportPDF);
    </script>
</body>

</html>