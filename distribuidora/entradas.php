<?php

declare(strict_types=1);

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/entradas/_helpers.php';

$csrf  = csrf_token();
$flash = flash_pop();

$pdo = db();

function brDate(string $ymd): string
{
    $ymd = trim($ymd);
    if (!$ymd) return '';
    $p = explode('-', $ymd);
    if (count($p) !== 3) return $ymd;
    return $p[2] . '/' . $p[1] . '/' . $p[0];
}

function fmtMoney($v): string
{
    return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

/**
 * Banco (produtos.imagem): images/arquivo.png
 * Exibir em páginas na raiz: assets/dados/produtos/images/arquivo.png
 */
function img_url_from_db(string $dbValue): string
{
    $v = trim($dbValue);
    if ($v === '') return '';
    if (preg_match('~^(https?://|/|assets/)~i', $v)) return $v;
    $v = ltrim($v, '/');
    return 'assets/dados/produtos/' . $v;
}

// selects
$fornecedores = $pdo->query("SELECT id, nome, status FROM fornecedores ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC);
$produtos = $pdo->query("
  SELECT p.id, p.codigo, p.nome, p.unidade, p.imagem, p.fornecedor_id,
         f.nome AS fornecedor_nome
  FROM produtos p
  LEFT JOIN fornecedores f ON f.id = p.fornecedor_id
  ORDER BY p.nome ASC
  LIMIT 5000
")->fetchAll(PDO::FETCH_ASSOC);

// lista entradas
$entradas = $pdo->query("
  SELECT e.*,
         f.nome AS fornecedor_nome,
         p.codigo AS produto_codigo,
         p.nome AS produto_nome,
         p.imagem AS produto_imagem
  FROM entradas e
  LEFT JOIN fornecedores f ON f.id = e.fornecedor_id
  LEFT JOIN produtos p ON p.id = e.produto_id
  ORDER BY e.data DESC, e.id DESC
  LIMIT 5000
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />

    <link rel="shortcut icon" href="assets/images/favicon.svg" type="image/x-icon" />
    <title>Painel da Distribuidora | Entradas</title>

    <link rel="stylesheet" href="assets/css/bootstrap.min.css" />
    <link rel="stylesheet" href="assets/css/lineicons.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/materialdesignicons.min.css" rel="stylesheet" type="text/css" />
    <link rel="stylesheet" href="assets/css/main.css" />

    <style>
        .profile-box .dropdown-menu {
            width: max-content;
            min-width: 260px;
            max-width: calc(100vw - 24px)
        }

        .profile-box .dropdown-menu .author-info {
            width: max-content;
            max-width: 100%;
            display: flex !important;
            align-items: center;
            gap: 10px
        }

        .profile-box .dropdown-menu .author-info .content {
            min-width: 0;
            max-width: 100%
        }

        .profile-box .dropdown-menu .author-info .content a {
            display: inline-block;
            white-space: nowrap;
            max-width: 100%
        }

        .main-btn.btn-compact {
            height: 38px !important;
            padding: 8px 14px !important;
            font-size: 13px !important;
            line-height: 1 !important
        }

        .main-btn.btn-compact i {
            font-size: 14px;
            vertical-align: -1px
        }

        .icon-btn {
            height: 34px !important;
            width: 42px !important;
            padding: 0 !important;
            display: inline-flex !important;
            align-items: center !important;
            justify-content: center !important
        }

        .table td,
        .table th {
            vertical-align: middle
        }

        .minw-120 {
            min-width: 120px
        }

        .minw-140 {
            min-width: 140px
        }

        .minw-160 {
            min-width: 160px
        }

        .minw-200 {
            min-width: 200px
        }

        .table-responsive {
            -webkit-overflow-scrolling: touch
        }

        #tbEntradas {
            width: 100%;
            min-width: 1320px
        }

        #tbEntradas th,
        #tbEntradas td {
            white-space: nowrap !important;
            word-break: normal !important;
            overflow-wrap: normal !important
        }

        .prod-img {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, .35);
            background: #fff
        }

        .td-center {
            text-align: center
        }

        .td-right {
            text-align: right
        }

        .form-control.compact,
        .form-select.compact {
            height: 38px;
            padding: 8px 12px;
            font-size: 13px
        }

        .img-preview {
            width: 110px;
            height: 110px;
            object-fit: cover;
            border-radius: 16px;
            border: 1px dashed rgba(148, 163, 184, .6);
            background: #fff
        }

        .img-block {
            max-width: 320px;
            width: 100%
        }

        .flash-auto-hide {
            transition: opacity .35s ease, transform .35s ease
        }

        .flash-auto-hide.hide {
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none
        }

        .muted {
            font-size: 12px;
            color: #64748b
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
            <a href="index.php" class="d-flex align-items-center gap-2">
                <img src="assets/images/logo/logo.svg" alt="logo" />
            </a>
        </div>

        <nav class="sidebar-nav">
            <ul>
                <!-- Dashboard -->
                <li class="nav-item">
                    <a href="index.php">
                        <span class="text">Dashboard</span>
                    </a>
                </li>

                <!-- Operações -->
                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes"
                        aria-controls="ddmenu_operacoes" aria-expanded="false">
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
                        <li><a href="pedidos.php">Pedidos</a></li>
                        <li><a href="vendas.php">Vendas</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <!-- Estoque -->
                <li class="nav-item nav-item-has-children active">
                    <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque"
                        aria-controls="ddmenu_estoque" aria-expanded="true">
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse show dropdown-nav">
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php">Inventário</a></li>
                        <li><a href="entradas.php" class="active">Entradas</a></li>
                        <li><a href="saidas.php">Saídas</a></li>
                        <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
                    </ul>
                </li>

                <li class="nav-item"><a href="relatorios.php"><span class="text">Relatórios</span></a></li>

                <span class="divider">
                    <hr />
                </span>

                <li class="nav-item nav-item-has-children">
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_config"
                        aria-controls="ddmenu_config" aria-expanded="false">
                        <span class="text">Configurações</span>
                    </a>
                    <ul id="ddmenu_config" class="collapse dropdown-nav">
                        <li><a href="usuarios.php">Usuários e Permissões</a></li>
                        <li><a href="parametros.php">Parâmetros do Sistema</a></li>
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
                                    <input type="text" placeholder="Buscar produto..." id="qGlobal" />
                                    <button type="submit" onclick="return false"><i class="lni lni-search-alt"></i></button>
                                </form>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-7 col-md-7 col-6">
                        <div class="header-right">
                            <div class="profile-box ml-15">
                                <button class="dropdown-toggle bg-transparent border-0" type="button" id="profile"
                                    data-bs-toggle="dropdown" aria-expanded="false">
                                    <div class="profile-info">
                                        <div class="info">
                                            <div class="image">
                                                <img src="assets/images/profile/profile-image.png" alt="perfil" />
                                            </div>
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
                                <h2>Entradas</h2>
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
                            <input type="text" class="form-control compact" id="qEntradas" placeholder="NF, produto, fornecedor..." />
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Fornecedor</label>
                            <select class="form-select compact" id="fFornecedor">
                                <option value="">Todos</option>
                                <?php foreach ($fornecedores as $f): ?>
                                    <option value="<?= (int)$f['id'] ?>"><?= e((string)$f['nome']) ?><?= (strtoupper((string)$f['status']) === 'INATIVO' ? ' (INATIVO)' : '') ?></option>
                                <?php endforeach; ?>
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
                                <button class="main-btn primary-btn btn-hover btn-compact" data-bs-toggle="modal"
                                    data-bs-target="#modalEntrada" id="btnNovo" type="button">
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
                        <table class="table text-nowrap" id="tbEntradas">
                            <thead>
                                <tr>
                                    <th class="minw-120">Imagem</th>
                                    <th class="minw-140">Data</th>
                                    <th class="minw-140">NF</th>
                                    <th class="minw-200">Fornecedor</th>
                                    <th class="minw-140">Código</th>
                                    <th class="minw-200">Produto</th>
                                    <th class="minw-140">Unidade</th>
                                    <th class="minw-140 td-center">Qtd</th>
                                    <th class="minw-140 td-center">Custo</th>
                                    <th class="minw-160 td-center">Total</th>
                                    <th class="minw-140 text-end">Ações</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($entradas as $eRow): ?>
                                    <?php
                                    $id = (int)$eRow['id'];
                                    $dataYmd = (string)$eRow['data'];
                                    $nf = (string)$eRow['nf'];
                                    $forId = (int)$eRow['fornecedor_id'];
                                    $forNome = trim((string)($eRow['fornecedor_nome'] ?? '')) ?: '—';
                                    $prodId = (int)$eRow['produto_id'];
                                    $prodCod = trim((string)($eRow['produto_codigo'] ?? '')) ?: '—';
                                    $prodNome = trim((string)($eRow['produto_nome'] ?? '')) ?: '—';
                                    $unidade = trim((string)($eRow['unidade'] ?? '')) ?: '—';
                                    $qtd = (int)($eRow['qtd'] ?? 0);
                                    $custo = (float)($eRow['custo'] ?? 0);
                                    $total = (float)($eRow['total'] ?? 0);
                                    $img = img_url_from_db((string)($eRow['produto_imagem'] ?? ''));
                                    ?>
                                    <tr
                                        data-id="<?= $id ?>"
                                        data-data="<?= e($dataYmd) ?>"
                                        data-nf="<?= e($nf) ?>"
                                        data-fornecedor-id="<?= $forId ?>"
                                        data-produto-id="<?= $prodId ?>"
                                        data-unidade="<?= e($unidade) ?>"
                                        data-qtd="<?= $qtd ?>"
                                        data-custo="<?= e((string)$custo) ?>">
                                        <td><img class="prod-img" alt="<?= e($prodNome) ?>" src="<?= e($img) ?>" /></td>
                                        <td class="date"><?= e(brDate($dataYmd)) ?></td>
                                        <td class="nf"><?= e($nf) ?></td>
                                        <td class="forn"><?= e($forNome) ?></td>
                                        <td class="cod"><?= e($prodCod) ?></td>
                                        <td class="prod"><?= e($prodNome) ?></td>
                                        <td class="und"><?= e($unidade) ?></td>
                                        <td class="td-center qtd"><?= $qtd ?></td>
                                        <td class="td-center custo"><?= e(fmtMoney($custo)) ?></td>
                                        <td class="td-center total"><?= e(fmtMoney($total)) ?></td>
                                        <td class="text-end">
                                            <button class="main-btn light-btn btn-hover icon-btn btnEdit" type="button" title="Editar">
                                                <i class="lni lni-pencil"></i>
                                            </button>
                                            <button class="main-btn danger-btn-outline btn-hover icon-btn btnDel" type="button" title="Excluir">
                                                <i class="lni lni-trash-can"></i>
                                            </button>
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
    <form id="frmDelete" action="assets/dados/entradas/excluirEntradas.php" method="post" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="id" id="delId" value="">
    </form>

    <!-- Modal Entrada (MESMO ESTILO) -->
    <div class="modal fade" id="modalEntrada" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="modalEntradaTitle">Nova Entrada</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <form id="formEntrada" action="assets/dados/entradas/salvarEntradas.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
                        <input type="hidden" name="id" id="eId" value="">

                        <div class="row g-3">
                            <!-- IMAGEM CENTRAL (vem do produto) -->
                            <div class="col-12">
                                <div class="d-flex justify-content-center">
                                    <div class="img-block text-center">
                                        <label class="form-label">Imagem (do Produto)</label>
                                        <div class="d-flex flex-column gap-2 align-items-center">
                                            <img id="previewImg" class="img-preview" alt="Prévia" />
                                            <div class="muted">A imagem é puxada do cadastro de Produtos.</div>
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
                                <label class="form-label">NF</label>
                                <input type="text" class="form-control compact" id="pNF" name="nf" placeholder="Ex: NF-1022" required />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Fornecedor</label>
                                <select class="form-select compact" id="pFornecedor" name="fornecedor_id" required>
                                    <option value="">Selecione…</option>
                                    <?php foreach ($fornecedores as $f): ?>
                                        <option value="<?= (int)$f['id'] ?>"><?= e((string)$f['nome']) ?><?= (strtoupper((string)$f['status']) === 'INATIVO' ? ' (INATIVO)' : '') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-12">
                                <label class="form-label">Produto</label>
                                <select class="form-select compact" id="pProdutoId" name="produto_id" required>
                                    <option value="">Selecione…</option>
                                    <?php foreach ($produtos as $p): ?>
                                        <?php $img = img_url_from_db((string)($p['imagem'] ?? '')); ?>
                                        <option
                                            value="<?= (int)$p['id'] ?>"
                                            data-codigo="<?= e((string)$p['codigo']) ?>"
                                            data-nome="<?= e((string)$p['nome']) ?>"
                                            data-unidade="<?= e((string)$p['unidade']) ?>"
                                            data-img="<?= e($img) ?>">
                                            <?= e((string)$p['nome']) ?> (<?= e((string)$p['codigo']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control compact" id="pCodigo" placeholder="—" readonly />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Produto</label>
                                <input type="text" class="form-control compact" id="pProdutoNome" placeholder="—" readonly />
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

                            <div class="col-md-4">
                                <label class="form-label">Qtd</label>
                                <input type="number" class="form-control compact td-center" id="pQtd" name="qtd" min="0" value="0" required />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Custo (un)</label>
                                <input type="text" class="form-control compact td-center" id="pCusto" name="custo" placeholder="0,00" required />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Total</label>
                                <input type="text" class="form-control compact td-center" id="pTotal" placeholder="0,00" readonly />
                            </div>
                        </div>
                    </form>

                    <p class="text-sm text-gray mt-3 mb-0">
                        O total é calculado automaticamente: <b>Qtd × Custo</b>.
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formEntrada" class="main-btn primary-btn btn-hover btn-compact">
                        <i class="lni lni-save me-1"></i> Salvar
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="assets/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/main.js"></script>

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

        // fallback imagens
        document.querySelectorAll("img.prod-img").forEach(img => {
            const src = img.getAttribute('src') || '';
            if (!src) img.src = DEFAULT_IMG;
            img.addEventListener('error', () => img.src = DEFAULT_IMG, {
                once: true
            });
        });

        const tb = document.getElementById('tbEntradas');
        const qEntradas = document.getElementById('qEntradas');
        const qGlobal = document.getElementById('qGlobal');
        const fFornecedor = document.getElementById('fFornecedor');
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

        function brDateFromYMD(ymd) {
            const [y, m, d] = String(ymd || '').split('-');
            if (!y || !m || !d) return '';
            return `${d}/${m}/${y}`;
        }

        function aplicarFiltros() {
            const q = norm(qEntradas.value || qGlobal.value);
            const fornId = String(fFornecedor.value || '').trim();
            const ini = dtIni.value || '';
            const fim = dtFim.value || '';

            const rows = Array.from(tb.querySelectorAll('tbody tr'));
            let shown = 0;

            rows.forEach(tr => {
                const text = norm(tr.innerText);
                const rFornId = String(tr.getAttribute('data-fornecedor-id') || '').trim();
                const rData = tr.getAttribute('data-data') || '';

                let ok = true;
                if (q && !text.includes(q)) ok = false;
                if (fornId && rFornId !== fornId) ok = false;

                if (ini && rData && rData < ini) ok = false;
                if (fim && rData && rData > fim) ok = false;

                tr.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });

            infoCount.textContent = `Mostrando ${shown} entrada(s).`;
        }

        qEntradas.addEventListener('input', aplicarFiltros);
        qGlobal.addEventListener('input', aplicarFiltros);
        fFornecedor.addEventListener('change', aplicarFiltros);
        dtIni.addEventListener('change', aplicarFiltros);
        dtFim.addEventListener('change', aplicarFiltros);
        aplicarFiltros();

        // ===== Modal =====
        const modalEl = document.getElementById('modalEntrada');
        const modal = new bootstrap.Modal(modalEl);
        const modalTitle = document.getElementById('modalEntradaTitle');

        const eId = document.getElementById('eId');
        const previewImg = document.getElementById('previewImg');

        const pData = document.getElementById('pData');
        const pNF = document.getElementById('pNF');
        const pFornecedorSel = document.getElementById('pFornecedor');

        const pProdutoId = document.getElementById('pProdutoId');
        const pCodigo = document.getElementById('pCodigo');
        const pProdutoNome = document.getElementById('pProdutoNome');
        const pUnidade = document.getElementById('pUnidade');

        const pQtd = document.getElementById('pQtd');
        const pCusto = document.getElementById('pCusto');
        const pTotal = document.getElementById('pTotal');

        function setPreview(src) {
            previewImg.src = src || DEFAULT_IMG;
        }

        function hojeYMD() {
            const d = new Date();
            const y = d.getFullYear();
            const m = String(d.getMonth() + 1).padStart(2, '0');
            const dd = String(d.getDate()).padStart(2, '0');
            return `${y}-${m}-${dd}`;
        }

        function limparForm() {
            eId.value = '';
            pData.value = hojeYMD();
            pNF.value = '';
            pFornecedorSel.value = '';
            pProdutoId.value = '';
            pCodigo.value = '—';
            pProdutoNome.value = '—';
            pUnidade.value = '';
            pQtd.value = 0;
            pCusto.value = '';
            pTotal.value = fmtBRL(0);
            setPreview(DEFAULT_IMG);
        }

        function recalcularTotal() {
            const qtd = Number(pQtd.value || 0);
            const custo = parseBRL(pCusto.value);
            pTotal.value = fmtBRL(qtd * custo);
        }
        pQtd.addEventListener('input', recalcularTotal);
        pCusto.addEventListener('input', recalcularTotal);

        // quando muda produto
        pProdutoId.addEventListener('change', () => {
            const opt = pProdutoId.options[pProdutoId.selectedIndex];
            if (!opt || !opt.value) {
                pCodigo.value = '—';
                pProdutoNome.value = '—';
                setPreview(DEFAULT_IMG);
                return;
            }
            pCodigo.value = opt.getAttribute('data-codigo') || '—';
            pProdutoNome.value = opt.getAttribute('data-nome') || '—';
            const und = opt.getAttribute('data-unidade') || '';
            if (und) pUnidade.value = und;
            const img = opt.getAttribute('data-img') || '';
            setPreview(img || DEFAULT_IMG);
        });

        document.getElementById('btnNovo').addEventListener('click', () => {
            modalTitle.textContent = 'Nova Entrada';
            limparForm();
        });

        // editar / excluir
        tb.addEventListener('click', (e) => {
            const tr = e.target.closest('tr');
            if (!tr) return;

            const btnDel = e.target.closest('.btnDel');
            if (btnDel) {
                const id = tr.getAttribute('data-id') || '';
                const nf = tr.querySelector('.nf')?.innerText.trim() || '';
                if (confirm(`Remover entrada ${nf}?`)) {
                    document.getElementById('delId').value = id;
                    document.getElementById('frmDelete').submit();
                }
                return;
            }

            const btnEdit = e.target.closest('.btnEdit');
            if (btnEdit) {
                modalTitle.textContent = 'Editar Entrada';

                eId.value = tr.getAttribute('data-id') || '';
                pData.value = tr.getAttribute('data-data') || hojeYMD();
                pNF.value = tr.getAttribute('data-nf') || '';
                pFornecedorSel.value = tr.getAttribute('data-fornecedor-id') || '';

                pProdutoId.value = tr.getAttribute('data-produto-id') || '';
                // dispara change pra preencher código/nome/unidade/img
                pProdutoId.dispatchEvent(new Event('change'));

                pUnidade.value = tr.getAttribute('data-unidade') || pUnidade.value || '';
                pQtd.value = tr.getAttribute('data-qtd') || 0;

                const custoNum = String(tr.getAttribute('data-custo') || '0').replace('.', ',');
                pCusto.value = custoNum;
                recalcularTotal();

                modal.show();
            }
        });

        // init preview no modal
        setPreview(DEFAULT_IMG);

        // ✅ Excel (igual ao seu modelo)
        function exportExcel() {
            const rows = Array.from(tb.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');

            const now = new Date();
            const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');

            const fornTxt = fFornecedor.value ? fFornecedor.options[fFornecedor.selectedIndex].text : 'Todos';
            const ini = dtIni.value || '—';
            const fim = dtFim.value || '—';

            const header = ['Data', 'NF', 'Fornecedor', 'Código', 'Produto', 'Unidade', 'Qtd', 'Custo', 'Total'];

            const body = rows.map(tr => ([
                tr.querySelector('.date')?.innerText.trim() || '',
                tr.querySelector('.nf')?.innerText.trim() || '',
                tr.querySelector('.forn')?.innerText.trim() || '',
                tr.querySelector('.cod')?.innerText.trim() || '',
                tr.querySelector('.prod')?.innerText.trim() || '',
                tr.querySelector('.und')?.innerText.trim() || '',
                tr.querySelector('.qtd')?.innerText.trim() || '',
                tr.querySelector('.custo')?.innerText.trim() || '',
                tr.querySelector('.total')?.innerText.trim() || ''
            ]));

            const isCenterCol = (idx) => (idx === 6 || idx === 7 || idx === 8);

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

            html += `<tr><td class="title" colspan="9">PAINEL DA DISTRIBUIDORA - ENTRADAS</td></tr>`;
            html += `<tr><td class="muted">Gerado em:</td><td colspan="8">${dt}</td></tr>`;
            html += `<tr><td class="muted">Fornecedor:</td><td>${fornTxt}</td><td class="muted">Período:</td><td colspan="6">${ini} até ${fim}</td></tr>`;
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
            a.download = 'entradas.xls';
            document.body.appendChild(a);
            a.click();
            a.remove();
            URL.revokeObjectURL(url);
        }
        document.getElementById('btnExcel').addEventListener('click', exportExcel);

        // ✅ PDF (igual ao seu modelo)
        function exportPDF() {
            if (!window.jspdf || !window.jspdf.jsPDF) {
                alert('Biblioteca do PDF não carregou.');
                return;
            }

            const rows = Array.from(tb.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');
            const now = new Date();
            const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');

            const fornTxt = fFornecedor.value ? fFornecedor.options[fFornecedor.selectedIndex].text : 'Todos';
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
            doc.text('PAINEL DA DISTRIBUIDORA - ENTRADAS', M, 55);

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.text(`Gerado em:  ${dt}`, M, 75);
            doc.text(`Fornecedor:  ${fornTxt} | Período:  ${ini} até ${fim}`, M, 92);

            const head = [
                ['Data', 'NF', 'Fornecedor', 'Código', 'Produto', 'Unidade', 'Qtd', 'Custo', 'Total']
            ];

            const body = rows.map(tr => ([
                tr.querySelector('.date')?.innerText.trim() || '',
                tr.querySelector('.nf')?.innerText.trim() || '',
                tr.querySelector('.forn')?.innerText.trim() || '',
                tr.querySelector('.cod')?.innerText.trim() || '',
                tr.querySelector('.prod')?.innerText.trim() || '',
                tr.querySelector('.und')?.innerText.trim() || '',
                tr.querySelector('.qtd')?.innerText.trim() || '',
                tr.querySelector('.custo')?.innerText.trim() || '',
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
                    6: {
                        halign: 'center'
                    },
                    7: {
                        halign: 'center'
                    },
                    8: {
                        halign: 'center'
                    }
                },
                didParseCell: function(data) {
                    data.cell.styles.lineWidth = 0;
                }
            });

            doc.save('entradas.pdf');
        }
        document.getElementById('btnPDF').addEventListener('click', exportPDF);
    </script>
</body>

</html>