<?php

declare(strict_types=1);

require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* ===== CONEXÃO (PDO) ===== */
require_once __DIR__ . '/../dist/assets/conexao.php';
if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "<div class='alert alert-danger'>Erro: conexão com o banco não encontrada.</div>";
    exit;
}
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

/* ===== Helpers ===== */
function e(?string $v): string
{
    return htmlspecialchars($v ?? '', ENT_QUOTES, 'UTF-8');
}
function moneyBR($v): string
{
    return ($v === null || $v === '') ? '—' : 'R$ ' . number_format((float)$v, 2, ',', '.');
}

/* ===== Carrega TODOS os benefícios (para paginação client-side) ===== */
try {
    $beneficios = $pdo->query("
        SELECT id, nome, categoria, descricao, valor_padrao, periodicidade, qtd_padrao, doc_exigido, status
        FROM ajudas_tipos
        ORDER BY status DESC, nome ASC, id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo "<div class='alert alert-danger'>Erro ao consultar benefícios: " . e($e->getMessage()) . "</div>";
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <title>Benefícios Cadastrados - ANEXO</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
    <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="../dist/assets/css/app.css">
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">
    <style>
        .table-actions .btn {
            margin: 0 2px;
        }

        .text-truncate {
            max-width: 260px;
        }

        /* Cabeçalho: “Novo” em cima; busca + limpar embaixo (visual preservado) */
        .card-header-grid {
            display: grid;
            grid-template-columns: 1fr auto;
            gap: .5rem;
            align-items: center;
        }

        .ch-right {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            align-items: flex-end;
        }

        .search-wrap {
            display: flex;
            gap: .5rem;
            align-items: center;
            width: 100%;
        }

        .search-wrap .form-control {
            min-width: 260px;
        }

        /* Pager client-side */
        .tfoot-pager {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: .75rem 1rem;
            flex-wrap: wrap;
        }

        @media (max-width:576px) {
            .card-header-grid {
                grid-template-columns: 1fr;
            }

            .ch-right {
                align-items: stretch;
            }

            .search-wrap .form-control {
                flex: 1 1 auto;
                min-width: 0;
            }

            .tfoot-pager>.flex-grow-1 {
                order: 3;
                width: 100%;
                justify-content: center;
            }
        }
    </style>
</head>

<body>
    <div id="app">
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header">
                    <div class="d-flex justify-content-between">
                        <div class="logo"><a href="dashboard.php"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo"></a></div>
                        <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a></div>
                    </div>
                </div>

                <!-- MENU -->
                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-item"><a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a></li>

                        <!-- ENTREGAS DE BENEFÍCIOS -->
                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-hand-thumbs-up-fill"></i>
                                <span>Entregas</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item">
                                    <a href="registrarEntrega.php">Registrar Entrega</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="entregasRealizadas.php">Histórico de Entregas</a>
                                </li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-geo-alt-fill"></i><span>Bairros</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="bairrosCadastrados.php">Bairros Cadastrados</a></li>
                                <li class="submenu-item"><a href="cadastrarBairro.php">Cadastrar Bairro</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-house-fill"></i><span>Beneficiarios</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="beneficiariosBolsaFamilia.php">Bolsa Família</a></li>
                                <li class="submenu-item"><a href="beneficiariosEstadual.php">Estadual</a></li>
                                <li class="submenu-item"><a href="beneficiariosMunicipal.php">Municipal</a></li>
                                <li class="submenu-item"><a href="beneficiariosSemas.php">ANEXO</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub active">
                            <a href="#" class="sidebar-link"><i class="bi bi-hand-thumbs-up-fill"></i><span>Ajuda Social</span></a>
                            <ul class="submenu active">
                                <li class="submenu-item"><a href="cadastrarBeneficio.php">Cadastrar Benefício</a></li>
                                <li class="submenu-item active"><a href="beneficiosCadastrados.php">Benefícios Cadastrados</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-bar-chart-line-fill"></i><span>Relatórios</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="relatoriosCadastros.php">Cadastros</a></li>
                                <li class="submenu-item"><a href="relatorioAtendimentos.php">Atendimentos</a></li>
                                <li class="submenu-item"><a href="relatorioBeneficios.php">Benefícios</a></li>
                            </ul>
                        </li>

                        <!-- CONTROLE DE VALORES -->
                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-cash-stack"></i>
                                <span>Controle Financeiro</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item">
                                    <a href="valoresAplicados.php">Valores Aplicados</a>
                                </li>
                                <li class="submenu-item">
                                    <a href="beneficiosAcimaMil.php">Acima de R$ 1.000</a>
                                </li>
                            </ul>
                        </li>

                        <!-- 🔒 USUÁRIOS (ÚNICO COM CONTROLE DE PERFIL) -->
                        <?php if (($_SESSION['user_role'] ?? '') === 'suporte'): ?>
                            <li class="sidebar-item has-sub">
                                <a href="#" class="sidebar-link">
                                    <i class="bi bi-people-fill"></i>
                                    <span>Usuários</span>
                                </a>
                                <ul class="submenu">
                                    <li class="submenu-item">
                                        <a href="usuariosPermitidos.php">Permitidos</a>
                                    </li>
                                    <li class="submenu-item">
                                        <a href="usuariosNaoPermitidos.php">Não Permitidos</a>
                                    </li>
                                </ul>
                            </li>
                        <?php endif; ?>

                        <!-- AUDITORIA / LOG -->
                        <li class="sidebar-item">
                            <a href="auditoria.php" class="sidebar-link">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Auditoria</span>
                            </a>
                        </li>

                        <li class="sidebar-item"><a href="./auth/logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a></li>
                    </ul>
                </div>
                <!-- /MENU -->
            </div>
        </div>

        <div id="main" class="d-flex flex-column min-vh-100">
            <header class="mb-3"><a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a></header>

            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last">
                            <h3>Benefícios Cadastrados</h3>
                            <p class="text-subtitle text-muted">Catálogo de benefícios/ajudas do ANEXO</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#">Ajuda Social</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Benefícios Cadastrados</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>

                <section class="section">
                    <div class="card">
                        <!-- Cabeçalho da lista -->
                        <div class="card-header card-header-grid">
                            <span class="fw-semibold">Lista de Benefícios</span>

                            <div class="ch-right">
                                <!-- TOP: botão Novo (permanece em cima) -->
                                <a href="cadastrarBeneficio.php" class="btn btn-sm btn-primary">
                                    <i class="bi bi-plus-lg"></i> Novo Benefício
                                </a>

                                <!-- BOTTOM: busca + limpar (visual preservado) -->
                                <div class="search-wrap">
                                    <input id="qLive" class="form-control form-control-sm"
                                        placeholder="Buscar por nome/categoria/periodicidade/documento..." autocomplete="off">
                                    <button class="btn btn-sm btn-outline-dark" type="button" id="btnClear" title="Limpar">
                                        <i class="bi bi-x-circle"></i>
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="card-body">
                            <div class="table-responsive-md">
                                <table class="table table-striped table-hover align-middle w-100 text-nowrap" id="tbl">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Nome</th>
                                            <th>Categoria</th>
                                            <th class="d-none d-sm-table-cell">Periodicidade</th>
                                            <th class="text-nowrap d-none d-md-table-cell">Valor Padrão</th>
                                            <th class="text-nowrap d-none d-lg-table-cell">Qtd</th>
                                            <th class="d-none d-xl-table-cell">Documento Exigido</th>
                                            <th class="text-nowrap">Status</th>
                                            <th class="text-center text-nowrap">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody id="tbody">
                                        <?php if (!$beneficios): ?>
                                            <tr>
                                                <td colspan="8" class="text-center text-muted">Nenhum benefício encontrado.</td>
                                            </tr>
                                            <?php else: foreach ($beneficios as $b): ?>
                                                <?php
                                                $nome = mb_strtolower($b['nome'] ?? '');
                                                $cat  = mb_strtolower($b['categoria'] ?? '');
                                                $per  = mb_strtolower($b['periodicidade'] ?? '');
                                                $doc  = mb_strtolower($b['doc_exigido'] ?? '');
                                                $sts  = mb_strtolower($b['status'] ?? '');
                                                $valorDigits = preg_replace('/\D+/', '', (string)$b['valor_padrao']);
                                                $qtdDigits   = preg_replace('/\D+/', '', (string)$b['qtd_padrao']);
                                                ?>
                                                <tr
                                                    data-id="<?= (int)$b['id'] ?>"
                                                    data-nome="<?= e($nome) ?>"
                                                    data-categoria="<?= e($cat) ?>"
                                                    data-periodicidade="<?= e($per) ?>"
                                                    data-doc="<?= e($doc) ?>"
                                                    data-status="<?= e($sts) ?>"
                                                    data-valor="<?= e($valorDigits) ?>"
                                                    data-qtd="<?= e($qtdDigits) ?>">
                                                    <td class="text-truncate" title="<?= e($b['descricao'] ?? '') ?>"><?= e($b['nome']) ?></td>
                                                    <td><?= e($b['categoria'] ?? '-') ?></td>
                                                    <td class="d-none d-sm-table-cell"><?= e($b['periodicidade'] ?? '-') ?></td>
                                                    <td class="text-nowrap d-none d-md-table-cell"><?= moneyBR($b['valor_padrao']) ?></td>
                                                    <td class="text-nowrap d-none d-lg-table-cell"><?= (int)($b['qtd_padrao'] ?? 0) ?></td>
                                                    <td class="text-truncate d-none d-xl-table-cell" style="max-width:240px;"><?= e($b['doc_exigido'] ?? '-') ?></td>
                                                    <td class="text-nowrap">
                                                        <?php if (($b['status'] ?? 'Ativa') === 'Ativa'): ?>
                                                            <span class="badge bg-success">Ativa</span>
                                                        <?php else: ?>
                                                            <span class="badge bg-secondary">Inativa</span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td class="text-center table-actions">
                                                        <div class="d-inline-flex align-items-center gap-2 flex-nowrap">

                                                            <?php if (($b['status'] ?? 'Ativa') === 'Ativa'): ?>
                                                                <a href="ajudas/toggleBeneficio.php?id=<?= (int)$b['id'] ?>&to=Inativa"
                                                                    class="btn btn-sm btn-link p-0 text-warning" title="Inativar"
                                                                    onclick="return confirm('Inativar este benefício?');">
                                                                    <i class="bi bi-slash-circle"></i>
                                                                </a>
                                                            <?php else: ?>
                                                                <a href="ajudas/toggleBeneficio.php?id=<?= (int)$b['id'] ?>&to=Ativa"
                                                                    class="btn btn-sm btn-link p-0 text-success" title="Ativar">
                                                                    <i class="bi bi-check2-circle"></i>
                                                                </a>
                                                            <?php endif; ?>

                                                        </div>
                                                    </td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Paginação client-side (igual ANEXO) -->
                            <div class="mt-2 tfoot-pager">
                                <div class="d-flex align-items-center gap-2">
                                    <button class="btn btn-outline-secondary btn-sm" id="btnPrev">Anterior</button>
                                    <button class="btn btn-outline-secondary btn-sm" id="btnNext">Próxima</button>
                                </div>
                                <div class="flex-grow-1 d-flex justify-content-center">
                                    <strong id="lblPagina">Página 1 de 1</strong>
                                </div>
                                <div class="d-flex align-items-center gap-2">
                                    <label for="selPerPage" class="form-label m-0">por página</label>
                                    <select id="selPerPage" class="form-select form-select-sm" style="width:auto">
                                        <option>10</option>
                                        <option>20</option>
                                        <option>50</option>
                                        <option>100</option>
                                    </select>
                                </div>
                            </div>
                            <!-- /Paginação -->
                        </div>
                    </div>
                </section>
            </div>

            <footer>
                <div class="footer clearfix mb-0 text-muted">
                    <div class="float-start text-black">
                        <p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de Coari-AM.</b></p>
                        <script>
                            document.getElementById('current-year').textContent = new Date().getFullYear();
                        </script>
                    </div>
                    <div class="float-end text-black">
                        <p>Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p>
                    </div>
                </div>
            </footer>
        </div>
    </div>

    <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../dist/assets/js/main.js"></script>
    <script>
        (() => {
            'use strict';
            document.getElementById('current-year').textContent = String(new Date().getFullYear());

            // ====== PESQUISA (igual ANEXO) + paginação client-side ======
            const tbody = document.getElementById('tbody');
            const allRows = Array.from(tbody?.querySelectorAll('tr') || []);

            const inpSearch = document.getElementById('qLive');
            const btnClear = document.getElementById('btnClear');

            const selPerPage = document.getElementById('selPerPage');
            const btnPrev = document.getElementById('btnPrev');
            const btnNext = document.getElementById('btnNext');
            const lblPagina = document.getElementById('lblPagina');

            selPerPage.value = '10';
            let page = 1;
            let perPage = parseInt(selPerPage.value, 10);
            let filtered = allRows.slice();
            let tDeb = null;

            function renderPage() {
                const total = filtered.length;
                const pages = Math.max(1, Math.ceil(total / perPage));
                if (page > pages) page = pages;

                const start = (page - 1) * perPage;
                const end = start + perPage;

                allRows.forEach(r => r.style.display = 'none');
                filtered.slice(start, end).forEach(r => r.style.display = '');

                lblPagina.textContent = `Página ${page} de ${pages}`;
                btnPrev.disabled = page <= 1;
                btnNext.disabled = page >= pages;
            }

            function doFilter() {
                const q = (inpSearch.value || '').trim().toLowerCase();
                const digits = q.replace(/\D+/g, '');

                filtered = allRows.filter(tr => {
                    if (!q) return true;

                    const nome = tr.dataset.nome || '';
                    const cat = tr.dataset.categoria || '';
                    const per = tr.dataset.periodicidade || '';
                    const doc = tr.dataset.doc || '';
                    const status = tr.dataset.status || '';

                    const valorD = tr.dataset.valor || '';
                    const qtdD = tr.dataset.qtd || '';

                    // texto livre
                    const hitText = nome.includes(q) || cat.includes(q) || per.includes(q) || doc.includes(q) || status.includes(q);
                    // busca numérica (valor padrão / quantidade)
                    const hitDigits = digits && (valorD.includes(digits) || qtdD.includes(digits));

                    return hitText || !!hitDigits;
                });

                page = 1;
                renderPage();
            }

            function applyFilter() {
                clearTimeout(tDeb);
                tDeb = setTimeout(doFilter, 120); // debounce
            }

            inpSearch.addEventListener('input', applyFilter);
            inpSearch.addEventListener('keydown', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                }
            });
            btnClear.addEventListener('click', () => {
                inpSearch.value = '';
                doFilter();
                inpSearch.focus();
            });

            selPerPage.addEventListener('change', () => {
                perPage = parseInt(selPerPage.value, 10) || 10;
                page = 1;
                renderPage();
            });
            btnPrev.addEventListener('click', () => {
                if (page > 1) {
                    page--;
                    renderPage();
                }
            });
            btnNext.addEventListener('click', () => {
                page++;
                renderPage();
            });

            // primeira renderização
            doFilter();
        })();
    </script>
</body>

</html>