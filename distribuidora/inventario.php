<?php

declare(strict_types=1);

require_once __DIR__ . '/assets/conexao.php';
require_once __DIR__ . '/assets/dados/inventario/_helpers.php';

$csrf  = csrf_token();
$flash = flash_pop();

$pdo = db();

/**
 * Banco: images/arquivo.png
 * Exibir: ./assets/dados/produtos/images/arquivo.png
 */
function img_url_from_db(string $dbValue): string
{
    $v = trim($dbValue);
    if ($v === '') return '';

    // Se já vier absoluto/URL, mantém
    if (preg_match('~^(https?://|/|assets/)~i', $v)) return $v;

    $v = ltrim($v, '/');
    return 'assets/dados/produtos/' . $v; // assets/dados/produtos/images/xxx.png
}

// Categorias (filtro)
$categorias = $pdo->query("SELECT id, nome, status FROM categorias ORDER BY nome ASC")
    ->fetchAll(PDO::FETCH_ASSOC);

// Produtos (para modal “Lançar”)
$prodList = $pdo->query("
  SELECT p.id, p.codigo, p.nome, p.unidade, p.estoque, p.categoria_id,
         c.nome AS categoria_nome
  FROM produtos p
  LEFT JOIN categorias c ON c.id = p.categoria_id
  ORDER BY p.nome ASC
  LIMIT 5000
")->fetchAll(PDO::FETCH_ASSOC);

// Itens do inventário (lista todos os produtos, com inventário se existir)
$rows = $pdo->query("
  SELECT p.id AS produto_id, p.codigo, p.nome, p.unidade, p.estoque, p.categoria_id,
         c.nome AS categoria_nome,
         p.imagem,
         i.contagem, i.diferenca, i.situacao
  FROM produtos p
  LEFT JOIN categorias c ON c.id = p.categoria_id
  LEFT JOIN inventario_itens i ON i.produto_id = p.id
  ORDER BY p.nome ASC
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
    <title>Painel da Distribuidora | Inventário</title>

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

        .table-responsive {
            -webkit-overflow-scrolling: touch;
        }

        #tbInventario {
            width: 100%;
            min-width: 1280px;
        }

        #tbInventario th,
        #tbInventario td {
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
            min-width: 92px;
        }

        .badge-soft-success {
            background: rgba(34, 197, 94, .12);
            color: #16a34a;
        }

        .badge-soft-warning {
            background: rgba(245, 158, 11, .12);
            color: #b45309;
        }

        .badge-soft-gray {
            background: rgba(148, 163, 184, .18);
            color: #475569;
        }

        .prod-img {
            width: 42px;
            height: 42px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid rgba(148, 163, 184, .35);
            background: #fff;
        }

        .count-input {
            height: 34px;
            padding: 6px 10px;
            font-size: 13px;
            width: 120px;
            text-align: center;
            display: inline-block;
        }

        .td-center {
            text-align: center;
        }

        .flash-auto-hide {
            transition: opacity .35s ease, transform .35s ease;
        }

        .flash-auto-hide.hide {
            opacity: 0;
            transform: translateY(-6px);
            pointer-events: none;
        }

        .muted {
            font-size: 12px;
            color: #64748b;
        }
    </style>
</head>

<body>
    <div id="preloader">
        <div class="spinner"></div>
    </div>

    <!-- Sidebar (mantive simples aqui, você pode colar seu SVG completo se quiser) -->
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
                    <a href="#0" class="collapsed" data-bs-toggle="collapse" data-bs-target="#ddmenu_operacoes" aria-expanded="false">
                        <span class="text">Operações</span>
                    </a>
                    <ul id="ddmenu_operacoes" class="collapse dropdown-nav">
                        <li><a href="pedidos.php">Pedidos</a></li>
                        <li><a href="vendas.php">Vendas</a></li>
                        <li><a href="devolucoes.php">Devoluções</a></li>
                    </ul>
                </li>

                <li class="nav-item nav-item-has-children active">
                    <a href="#0" data-bs-toggle="collapse" data-bs-target="#ddmenu_estoque" aria-expanded="true">
                        <span class="text">Estoque</span>
                    </a>
                    <ul id="ddmenu_estoque" class="collapse show dropdown-nav">
                        <li><a href="produtos.php">Produtos</a></li>
                        <li><a href="inventario.php" class="active">Inventário</a></li>
                        <li><a href="entradas.php">Entradas</a></li>
                        <li><a href="saidas.php">Saídas</a></li>
                        <li><a href="estoque-minimo.php">Estoque Mínimo</a></li>
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
                                <h2>Inventário</h2>
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
                            <input type="text" class="form-control" id="qInv" placeholder="Código, produto, categoria..." />
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Categoria</label>
                            <select class="form-select" id="fCategoria">
                                <option value="">Todas</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= (int)$c['id'] ?>"><?= e((string)$c['nome']) ?><?= (strtoupper((string)$c['status']) === 'INATIVO' ? ' (INATIVO)' : '') ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <label class="form-label">Situação</label>
                            <select class="form-select" id="fSituacao">
                                <option value="">Todas</option>
                                <option value="OK">OK</option>
                                <option value="DIVERGENTE">DIVERGENTE</option>
                                <option value="NAO_CONFERIDO">NÃO CONFERIDO</option>
                            </select>
                        </div>

                        <div class="col-12 col-md-6 col-lg-3">
                            <div class="d-grid gap-2 d-sm-flex justify-content-sm-end flex-wrap">
                                <button class="main-btn primary-btn btn-hover btn-compact" data-bs-toggle="modal" data-bs-target="#modalLancamento" id="btnNovo" type="button">
                                    <i class="lni lni-plus me-1"></i> Lançar
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
                        <table class="table text-nowrap" id="tbInventario">
                            <thead>
                                <tr>
                                    <th class="minw-120">Imagem</th>
                                    <th class="minw-140">Código</th>
                                    <th class="minw-160">Produto</th>
                                    <th class="minw-140">Categoria</th>
                                    <th class="minw-140">Unidade</th>
                                    <th class="minw-140 td-center">Sistema</th>
                                    <th class="minw-160 td-center">Contagem</th>
                                    <th class="minw-140 td-center">Diferença</th>
                                    <th class="minw-160 td-center">Situação</th>
                                    <th class="minw-140 text-end">Ações</th>
                                </tr>
                            </thead>

                            <tbody>
                                <?php foreach ($rows as $r): ?>
                                    <?php
                                    $produtoId = (int)$r['produto_id'];
                                    $catId = (int)($r['categoria_id'] ?? 0);
                                    $catNome = trim((string)($r['categoria_nome'] ?? '')) ?: '—';
                                    $unidade = trim((string)($r['unidade'] ?? '')) ?: '—';
                                    $sistema = (int)($r['estoque'] ?? 0);

                                    $contagem = $r['contagem'];
                                    $hasCount = ($contagem !== null && $contagem !== '');

                                    if (!$hasCount) {
                                        $sit = 'NAO_CONFERIDO';
                                        $diffTxt = '—';
                                        $badgeCls = 'badge-soft badge-soft-gray st';
                                        $badgeTxt = 'NÃO CONFERIDO';
                                        $countVal = '';
                                    } else {
                                        $countVal = (string)(int)$contagem;
                                        $diff = ((int)$contagem) - $sistema;
                                        $diffTxt = (string)$diff;
                                        if ($diff === 0) {
                                            $sit = 'OK';
                                            $badgeCls = 'badge-soft badge-soft-success st';
                                            $badgeTxt = 'OK';
                                        } else {
                                            $sit = 'DIVERGENTE';
                                            $badgeCls = 'badge-soft badge-soft-warning st';
                                            $badgeTxt = 'DIVERGENTE';
                                        }
                                    }

                                    $imgDb  = trim((string)($r['imagem'] ?? ''));
                                    $imgUrl = img_url_from_db($imgDb);
                                    ?>
                                    <tr data-produto-id="<?= $produtoId ?>" data-categoria="<?= $catId ?>" data-situacao="<?= e($sit) ?>">
                                        <td><img class="prod-img" alt="<?= e((string)$r['nome']) ?>" src="<?= e($imgUrl) ?>" /></td>
                                        <td><?= e((string)$r['codigo']) ?></td>
                                        <td><?= e((string)$r['nome']) ?></td>
                                        <td><?= e($catNome) ?></td>
                                        <td><?= e($unidade) ?></td>
                                        <td class="td-center sys"><?= $sistema ?></td>
                                        <td class="td-center">
                                            <input type="number" class="form-control count-input count" min="0" value="<?= e($countVal) ?>" placeholder="—" />
                                        </td>
                                        <td class="td-center diff"><?= e($diffTxt) ?></td>
                                        <td class="td-center">
                                            <span class="<?= e($badgeCls) ?>"><?= e($badgeTxt) ?></span>
                                        </td>
                                        <td class="text-end">
                                            <button class="main-btn light-btn btn-hover icon-btn btnSave" type="button" title="Salvar"><i class="lni lni-save"></i></button>
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

    <!-- SAVE FORM -->
    <form id="frmSave" action="assets/dados/inventario/salvarInventario.php" method="post" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="produto_id" id="svProdutoId" value="">
        <input type="hidden" name="contagem" id="svContagem" value="">
    </form>

    <!-- DELETE FORM -->
    <form id="frmDelete" action="assets/dados/inventario/excluirInventario.php" method="post" style="display:none;">
        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">
        <input type="hidden" name="produto_id" id="dlProdutoId" value="">
    </form>

    <!-- Modal Lançamento -->
    <div class="modal fade" id="modalLancamento" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Lançar Contagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                </div>

                <div class="modal-body">
                    <form id="formLanc" action="assets/dados/inventario/salvarInventario.php" method="post">
                        <input type="hidden" name="csrf_token" value="<?= e($csrf) ?>">

                        <div class="row g-3">
                            <div class="col-md-12">
                                <label class="form-label">Produto *</label>
                                <select class="form-select" id="mProdutoId" name="produto_id" required>
                                    <option value="">Selecione…</option>
                                    <?php foreach ($prodList as $p): ?>
                                        <option
                                            value="<?= (int)$p['id'] ?>"
                                            data-codigo="<?= e((string)$p['codigo']) ?>"
                                            data-nome="<?= e((string)$p['nome']) ?>"
                                            data-categoria="<?= e((string)($p['categoria_nome'] ?? '—')) ?>"
                                            data-unidade="<?= e((string)($p['unidade'] ?? '—')) ?>"
                                            data-sistema="<?= e((string)($p['estoque'] ?? 0)) ?>">
                                            <?= e((string)$p['nome']) ?> (<?= e((string)$p['codigo']) ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Código</label>
                                <input type="text" class="form-control" id="mCodigo" value="—" readonly />
                            </div>

                            <div class="col-md-6">
                                <label class="form-label">Produto</label>
                                <input type="text" class="form-control" id="mNome" value="—" readonly />
                            </div>

                            <div class="col-md-3">
                                <label class="form-label">Unidade</label>
                                <input type="text" class="form-control" id="mUnidade" value="—" readonly />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Categoria</label>
                                <input type="text" class="form-control" id="mCategoria" value="—" readonly />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Sistema</label>
                                <input type="number" class="form-control" id="mSistema" value="0" readonly />
                            </div>

                            <div class="col-md-4">
                                <label class="form-label">Contagem *</label>
                                <input type="number" class="form-control" id="mContagem" name="contagem" min="0" value="" placeholder="Informe a contagem" required />
                            </div>
                        </div>
                    </form>

                    <p class="text-sm text-gray mt-3 mb-0">
                        A situação é calculada automaticamente pela diferença (Contagem - Sistema).
                    </p>
                </div>

                <div class="modal-footer">
                    <button type="button" class="main-btn light-btn btn-hover btn-compact" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" form="formLanc" class="main-btn primary-btn btn-hover btn-compact">
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

        // fallback imagem
        document.querySelectorAll("img.prod-img").forEach(img => {
            const src = img.getAttribute('src') || '';
            if (!src) img.src = DEFAULT_IMG;
            img.addEventListener('error', () => img.src = DEFAULT_IMG, {
                once: true
            });
        });

        const tb = document.getElementById('tbInventario');
        const qInv = document.getElementById('qInv');
        const qGlobal = document.getElementById('qGlobal');
        const fCategoria = document.getElementById('fCategoria');
        const fSituacao = document.getElementById('fSituacao');
        const infoCount = document.getElementById('infoCount');

        function norm(s) {
            return String(s ?? '').toLowerCase().trim();
        }

        function calcularLinha(tr) {
            const sys = Number(tr.querySelector('.sys')?.innerText || 0);
            const inp = tr.querySelector('input.count');
            const diffEl = tr.querySelector('.diff');
            const stEl = tr.querySelector('.st');

            const hasValue = inp && inp.value !== '';
            if (!hasValue) {
                tr.setAttribute('data-situacao', 'NAO_CONFERIDO');
                diffEl.innerText = '—';
                stEl.className = 'badge-soft badge-soft-gray st';
                stEl.innerText = 'NÃO CONFERIDO';
                return;
            }

            const count = Number(inp.value);
            if (Number.isNaN(count)) return;

            const diff = count - sys;
            diffEl.innerText = String(diff);

            if (diff === 0) {
                tr.setAttribute('data-situacao', 'OK');
                stEl.className = 'badge-soft badge-soft-success st';
                stEl.innerText = 'OK';
            } else {
                tr.setAttribute('data-situacao', 'DIVERGENTE');
                stEl.className = 'badge-soft badge-soft-warning st';
                stEl.innerText = 'DIVERGENTE';
            }
        }

        function aplicarFiltros() {
            const q = norm(qInv.value || qGlobal.value);
            const cat = String(fCategoria.value || '').trim(); // id
            const sit = String(fSituacao.value || '').trim();

            const rows = Array.from(tb.querySelectorAll('tbody tr'));
            let shown = 0;

            rows.forEach(tr => {
                const text = norm(tr.innerText);
                const rCat = String(tr.getAttribute('data-categoria') || '').trim();
                const rSit = String(tr.getAttribute('data-situacao') || '').trim();

                let ok = true;
                if (q && !text.includes(q)) ok = false;
                if (cat && rCat !== cat) ok = false;
                if (sit && rSit !== sit) ok = false;

                tr.style.display = ok ? '' : 'none';
                if (ok) shown++;
            });

            infoCount.textContent = `Mostrando ${shown} item(ns) no inventário.`;
        }

        qInv.addEventListener('input', aplicarFiltros);
        qGlobal.addEventListener('input', aplicarFiltros);
        fCategoria.addEventListener('change', aplicarFiltros);
        fSituacao.addEventListener('change', aplicarFiltros);

        tb.addEventListener('input', (e) => {
            const inp = e.target.closest('input.count');
            if (!inp) return;
            const tr = inp.closest('tr');
            if (!tr) return;
            calcularLinha(tr);
            aplicarFiltros();
        });

        // salvar/excluir (POST externo)
        tb.addEventListener('click', (e) => {
            const tr = e.target.closest('tr');
            if (!tr) return;

            const btnDel = e.target.closest('.btnDel');
            if (btnDel) {
                const nome = tr.children[2].innerText.trim();
                if (confirm(`Remover do inventário: "${nome}"?`)) {
                    document.getElementById('dlProdutoId').value = tr.getAttribute('data-produto-id') || '';
                    document.getElementById('frmDelete').submit();
                }
                return;
            }

            const btnSave = e.target.closest('.btnSave');
            if (btnSave) {
                calcularLinha(tr);
                const pid = tr.getAttribute('data-produto-id') || '';
                const count = tr.querySelector('input.count')?.value ?? '';
                document.getElementById('svProdutoId').value = pid;
                document.getElementById('svContagem').value = String(count);
                document.getElementById('frmSave').submit();
                return;
            }
        });

        // modal: preencher infos
        const mProdutoId = document.getElementById('mProdutoId');
        const mCodigo = document.getElementById('mCodigo');
        const mNome = document.getElementById('mNome');
        const mCategoria = document.getElementById('mCategoria');
        const mUnidade = document.getElementById('mUnidade');
        const mSistema = document.getElementById('mSistema');
        const mContagem = document.getElementById('mContagem');

        mProdutoId.addEventListener('change', () => {
            const opt = mProdutoId.options[mProdutoId.selectedIndex];
            if (!opt || !opt.value) {
                mCodigo.value = '—';
                mNome.value = '—';
                mCategoria.value = '—';
                mUnidade.value = '—';
                mSistema.value = 0;
                return;
            }
            mCodigo.value = opt.getAttribute('data-codigo') || '—';
            mNome.value = opt.getAttribute('data-nome') || '—';
            mCategoria.value = opt.getAttribute('data-categoria') || '—';
            mUnidade.value = opt.getAttribute('data-unidade') || '—';
            mSistema.value = Number(opt.getAttribute('data-sistema') || 0);
            mContagem.value = '';
            mContagem.focus();
        });

        // init
        Array.from(tb.querySelectorAll('tbody tr')).forEach(calcularLinha);
        aplicarFiltros();

        // ✅ Excel (igual ao seu modelo)
        function exportExcel() {
            const rows = Array.from(tb.querySelectorAll('tbody tr')).filter(tr => tr.style.display !== 'none');

            const now = new Date();
            const dt = now.toLocaleDateString('pt-BR') + ' ' + now.toLocaleTimeString('pt-BR');

            const catTxt = fCategoria.value ? (fCategoria.options[fCategoria.selectedIndex].text) : 'Todas';
            const sitTxt = fSituacao.value ? (fSituacao.options[fSituacao.selectedIndex].text) : 'Todas';

            const header = ['Código', 'Produto', 'Categoria', 'Unidade', 'Sistema', 'Contagem', 'Diferença', 'Situação'];

            const body = rows.map(tr => {
                const sys = tr.querySelector('.sys')?.innerText.trim() || '';
                const count = tr.querySelector('input.count')?.value ?? '';
                const diff = tr.querySelector('.diff')?.innerText.trim() || '';
                const st = tr.querySelector('.st')?.innerText.trim() || '';
                return [
                    tr.children[1].innerText.trim(),
                    tr.children[2].innerText.trim(),
                    tr.children[3].innerText.trim(),
                    tr.children[4].innerText.trim(),
                    sys,
                    String(count),
                    diff,
                    st
                ];
            });

            const isCenterCol = (idx) => (idx === 4 || idx === 5 || idx === 6 || idx === 7);

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

            html += `<tr><td class="title" colspan="8">PAINEL DA DISTRIBUIDORA - INVENTÁRIO</td></tr>`;
            html += `<tr><td class="muted">Gerado em:</td><td colspan="7">${dt}</td></tr>`;
            html += `<tr><td class="muted">Categoria:</td><td>${catTxt}</td><td class="muted">Situação:</td><td>${sitTxt}</td><td colspan="4"></td></tr>`;

            html += `<tr>${header.map((h, idx) => `<th class="${isCenterCol(idx) ? 'center' : ''}">${h}</th>`).join('')}</tr>`;

            body.forEach(r => {
                html += `<tr>${r.map((c, idx) => {
          const safe = String(c).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;');
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
            a.download = 'inventario.xls';
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

            const catTxt = fCategoria.value ? (fCategoria.options[fCategoria.selectedIndex].text) : 'Todas';
            const sitTxt = fSituacao.value ? (fSituacao.options[fSituacao.selectedIndex].text) : 'Todas';

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
            doc.text('PAINEL DA DISTRIBUIDORA - INVENTÁRIO', M, 55);

            doc.setFont('helvetica', 'normal');
            doc.setFontSize(10);
            doc.text(`Gerado em:  ${dt}`, M, 75);
            doc.text(`Categoria:  ${catTxt} | Situação:  ${sitTxt}`, M, 92);

            const head = [
                ['Código', 'Produto', 'Categoria', 'Unidade', 'Sistema', 'Contagem', 'Diferença', 'Situação']
            ];

            const body = rows.map(tr => {
                const sys = tr.querySelector('.sys')?.innerText.trim() || '';
                const count = tr.querySelector('input.count')?.value ?? '';
                const diff = tr.querySelector('.diff')?.innerText.trim() || '';
                const st = tr.querySelector('.st')?.innerText.trim() || '';
                return [
                    tr.children[1].innerText.trim(),
                    tr.children[2].innerText.trim(),
                    tr.children[3].innerText.trim(),
                    tr.children[4].innerText.trim(),
                    sys,
                    String(count),
                    diff,
                    st
                ];
            });

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
                    4: {
                        halign: 'center'
                    },
                    5: {
                        halign: 'center'
                    },
                    6: {
                        halign: 'center'
                    },
                    7: {
                        halign: 'center'
                    }
                },
                didParseCell: function(data) {
                    data.cell.styles.lineWidth = 0;
                }
            });

            doc.save('inventario.pdf');
        }
        document.getElementById('btnPDF').addEventListener('click', exportPDF);
    </script>
</body>

</html>