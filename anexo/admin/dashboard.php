<?php

declare(strict_types=1);

/* AUTH (já é privado) */
require_once __DIR__ . '/auth/authGuard.php';
auth_guard();

/* DEBUG (remova em produção) */
ini_set('display_errors', '1');
error_reporting(E_ALL);

date_default_timezone_set('America/Manaus');

/* CONEXÃO */
require_once __DIR__ . '/../dist/assets/conexao.php';
if (!isset($pdo) || !$pdo instanceof PDO) {
    die('Erro de conexão');
}

/* =========================
   KPIs FINANCEIROS
========================= */

/* Total de entregas */
$totalEntregas = (int)$pdo->query("
    SELECT COUNT(*) 
    FROM ajudas_entregas
")->fetchColumn();

/* Valor total aplicado */
$valorTotal = (float)$pdo->query("
    SELECT COALESCE(SUM(valor_aplicado),0)
    FROM ajudas_entregas
    WHERE entregue = 'sim'
")->fetchColumn();

/* Entregas acima de 1000 */
$entregasAltas = (int)$pdo->query("
    SELECT COUNT(*)
    FROM ajudas_entregas
    WHERE valor_aplicado >= 1000
")->fetchColumn();

/* Média por entrega */
$mediaEntrega = $totalEntregas > 0 ? $valorTotal / $totalEntregas : 0;

/* =========================
   MAPA DE MESES PT-BR
========================= */

$MESES_PT = [
    1  => 'Jan',
    2  => 'Fev',
    3  => 'Mar',
    4  => 'Abr',
    5  => 'Mai',
    6  => 'Jun',
    7  => 'Jul',
    8  => 'Ago',
    9  => 'Set',
    10 => 'Out',
    11 => 'Nov',
    12 => 'Dez'
];

/* =========================
   SÉRIES DOS GRÁFICOS
========================= */

/* Entregas por mês */
$entMes = $pdo->query("
    SELECT MONTH(data_entrega) mes, COUNT(*) total
    FROM ajudas_entregas
    GROUP BY MONTH(data_entrega)
    ORDER BY MONTH(data_entrega)
")->fetchAll(PDO::FETCH_ASSOC);

$MES_LABELS = [];
$MES_SERIE  = [];

foreach ($entMes as $r) {
    $mesNum = (int)$r['mes'];
    $MES_LABELS[] = $MESES_PT[$mesNum] ?? '—';
    $MES_SERIE[]  = (int)$r['total'];
}

/* Valor aplicado por mês */
$valorMes = $pdo->query("
    SELECT MONTH(data_entrega) mes, SUM(valor_aplicado) total
    FROM ajudas_entregas
    WHERE entregue = 'sim'
    GROUP BY MONTH(data_entrega)
    ORDER BY MONTH(data_entrega)
")->fetchAll(PDO::FETCH_ASSOC);

$VALOR_LABELS = [];
$VALOR_SERIE  = [];

foreach ($valorMes as $r) {
    $mesNum = (int)$r['mes'];
    $VALOR_LABELS[] = $MESES_PT[$mesNum] ?? '—';
    $VALOR_SERIE[]  = (float)$r['total'];
}

/* Benefícios mais usados */
$benefMap = $pdo->query("
    SELECT a.nome, COUNT(*) total
    FROM ajudas_entregas e
    JOIN ajudas_tipos a ON a.id = e.ajuda_tipo_id
    GROUP BY a.nome
    ORDER BY total DESC
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$BEN_LABELS = [];
$BEN_SERIE  = [];

foreach ($benefMap as $b) {
    $BEN_LABELS[] = $b['nome'];
    $BEN_SERIE[]  = (int)$b['total'];
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - ANEXO</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
    <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../dist/assets/vendors/iconly/bold.css">
    <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="../dist/assets/css/app.css">
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

    <style>
        .kpi-card {
            height: 130px;
            display: flex;
            margin-bottom: 0 !important;
        }

        .kpi-row {
            --bs-gutter-y: 0.8rem;
            --bs-gutter-x: 0.95rem;
        }

        .kpi-card .card-body {
            padding: 20px 22px;
        }

        .avatar.icon-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: #eef2f7;
            color: #0d6efd;
            display: grid;
            place-items: center;
            line-height: 0;
            flex: 0 0 48px
        }

        .avatar.icon-avatar i {
            display: block;
            font-size: 1.5rem
        }

        .stats-icon {
            display: flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            border-radius: 12px;
            color: #fff;
            font-size: 22px
        }

        .stats-icon.purple {
            background: #6d5efc
        }

        .stats-icon.blue {
            background: #2d8cff
        }

        .stats-icon.green {
            background: #17c964
        }

        .stats-icon.red {
            background: #ff4d4f
        }

        .card.h-100 {
            height: 100%
        }

        .chart-box {
            min-height: 280px;
            width: 100%
        }

        .gap-row>[class*="col-"] {
            margin-bottom: 1rem
        }

        @media (min-width:1200px) {
            .chart-box {
                min-height: 320px
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
                        <div class="logo"><a href="#"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo"></a></div>
                        <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a></div>
                    </div>
                </div>

                <!-- MENU (ANEXO RESTRITO) -->
                <div class="sidebar-menu">
                    <ul class="menu">

                        <!-- DASHBOARD FINANCEIRO -->
                        <li class="sidebar-item active">
                            <a href="dashboard.php" class="sidebar-link">
                                <i class="bi bi-grid-fill"></i>
                                <span>Dashboard</span>
                            </a>
                        </li>

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

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-hand-thumbs-up-fill"></i><span>Ajuda Social</span></a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="cadastrarBeneficio.php">Cadastrar Benefício</a></li>
                                <li class="submenu-item"><a href="beneficiosCadastrados.php">Benefícios Cadastrados</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link"><i class="bi bi-bar-chart-line-fill"></i><span>Relatórios</span></a>
                            <ul class="submenu">
                                <li class="submenu-item "><a href="relatoriosCadastros.php">Cadastros</a></li>
                                <li class="submenu-item"><a href="relatorioAtendimentos.php">Atendimentos</a></li>
                                <li class="submenu-item"><a href="relatoriosBeneficios.php">Benefícios</a></li>
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

                        <!-- SAIR -->
                        <li class="sidebar-item">
                            <a href="./auth/logout.php" class="sidebar-link">
                                <i class="bi bi-box-arrow-right"></i>
                                <span>Sair</span>
                            </a>
                        </li>

                    </ul>
                </div>
                <!-- /MENU -->

            </div>
        </div>

        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none"><i class="bi bi-justify fs-3"></i></a>
            </header>

            <div class="page-heading">
                <h3>Dashboard Estatísticas</h3>
            </div>

            <div class="page-content">
                <section class="row">

                    <!-- KPIs -->
                    <div class="col-12 mb-3">
                        <div class="row row-cols-1 row-cols-md-2 kpi-row">

                            <div class="col">
                                <div class="card kpi-card">
                                    <div class="card-body">
                                        <h6>Total de Entregas</h6>
                                        <h3><?= number_format($totalEntregas, 0, ',', '.') ?></h3>
                                    </div>
                                </div>
                            </div>

                            <div class="col">
                                <div class="card kpi-card">
                                    <div class="card-body">
                                        <h6>Valor Total Aplicado</h6>
                                        <h3>R$ <?= number_format($valorTotal, 2, ',', '.') ?></h3>
                                    </div>
                                </div>
                            </div>

                            <div class="col">
                                <div class="card kpi-card">
                                    <div class="card-body">
                                        <h6>Entregas ≥ R$ 1.000</h6>
                                        <h3><?= number_format($entregasAltas, 0, ',', '.') ?></h3>
                                    </div>
                                </div>
                            </div>

                            <div class="col">
                                <div class="card kpi-card">
                                    <div class="card-body">
                                        <h6>Média por Entrega</h6>
                                        <h3>R$ <?= number_format($mediaEntrega, 2, ',', '.') ?></h3>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

                    <!-- GRÁFICOS -->
                    <div class="col-lg-6 col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Entregas por Mês</h4>
                            </div>
                            <div class="card-body">
                                <div id="chart-entregas"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        <div class="card">
                            <div class="card-header">
                                <h4>Valor Aplicado por Mês</h4>
                            </div>
                            <div class="card-body">
                                <div id="chart-valores"></div>
                            </div>
                        </div>
                    </div>

                    <div class="col-12">
                        <div class="card">
                            <div class="card-header">
                                <h4>Benefícios Mais Utilizados</h4>
                            </div>
                            <div class="card-body">
                                <div id="chart-beneficios"></div>
                            </div>
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

    <!-- ApexCharts -->
    <script src="https://cdn.jsdelivr.net/npm/apexcharts"></script>
    <script>
        new ApexCharts(document.querySelector("#chart-entregas"), {
            chart: {
                type: 'bar',
                height: 300,
                toolbar: {
                    show: false
                }
            },
            series: [{
                name: 'Entregas',
                data: <?= json_encode($MES_SERIE) ?>
            }],
            xaxis: {
                categories: <?= json_encode($MES_LABELS) ?>
            },
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    columnWidth: '45%'
                }
            },
            dataLabels: {
                enabled: true
            },
            colors: ['#2d8cff']
        }).render();

        new ApexCharts(document.querySelector("#chart-valores"), {
            chart: {
                type: 'bar',
                height: 300
            },
            series: [{
                name: 'Valor (R$)',
                data: <?= json_encode($VALOR_SERIE) ?>
            }],
            xaxis: {
                categories: <?= json_encode($VALOR_LABELS) ?>
            },
            dataLabels: {
                enabled: true
            }
        }).render();

        new ApexCharts(document.querySelector("#chart-beneficios"), {
            chart: {
                type: 'donut',
                height: 320
            },
            series: <?= json_encode($BEN_SERIE) ?>,
            labels: <?= json_encode($BEN_LABELS) ?>
        }).render();
    </script>

    <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
    <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
    <script src="../dist/assets/js/main.js"></script>
</body>

</html>