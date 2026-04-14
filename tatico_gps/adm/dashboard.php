<!doctype html>
<html lang="pt-BR" class="layout-menu-fixed layout-compact" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Tático GPS - Painel de Cobrança</title>
    <meta name="description" content="Sistema de cobrança e controle de pagamentos do Tático GPS" />

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link
        href="https://fonts.googleapis.com/css2?family=Public+Sans:ital,wght@0,300;0,400;0,500;0,600;0,700;1,300;1,400;1,500;1,600;1,700&display=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="../assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/apex-charts/apex-charts.css" />

    <script src="../assets/vendor/js/helpers.js"></script>
    <script src="../assets/js/config.js"></script>

    <style>
    html,
    body {
        height: 100%;
    }

    body {
        overflow-x: hidden;
    }

    .layout-page {
        min-height: 100vh;
    }

    .layout-menu {
        height: 100vh !important;
        overflow: hidden;
        position: sticky;
        top: 0;
    }

    .layout-menu .menu-inner {
        height: calc(100vh - 90px);
        overflow-y: auto !important;
        overflow-x: hidden;
        padding-bottom: 2rem;
        scrollbar-width: thin;
    }

    .layout-menu .menu-inner::-webkit-scrollbar {
        width: 8px;
    }

    .layout-menu .menu-inner::-webkit-scrollbar-thumb {
        background: rgba(105, 108, 255, 0.35);
        border-radius: 10px;
    }

    .layout-menu .menu-inner::-webkit-scrollbar-track {
        background: transparent;
    }

    .dashboard-banner h3 {
        margin-bottom: 0.4rem;
    }

    .dashboard-banner p {
        margin-bottom: 0;
        color: #697a8d;
    }

    .stat-card .card-body {
        padding: 1.35rem;
    }

    .stat-title {
        color: #566a7f;
        font-size: 0.95rem;
        margin-bottom: 0.45rem;
    }

    .stat-value {
        font-size: 2rem;
        font-weight: 700;
        color: #2b2c40;
        line-height: 1.1;
    }

    .stat-meta {
        margin-top: 0.7rem;
        font-size: 0.88rem;
        font-weight: 600;
    }

    .summary-box {
        border: 1px solid #e7e7ef;
        border-radius: 12px;
        padding: 1rem;
        height: 100%;
        background: #fff;
    }

    .summary-box h6 {
        margin-bottom: 0.65rem;
        font-size: 1rem;
        font-weight: 600;
    }

    .summary-box p {
        margin: 0;
        color: #697a8d;
    }

    .quick-actions .btn {
        font-weight: 600;
    }

    .customer-status-list li {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0.85rem 0;
        border-bottom: 1px solid #eceef1;
    }

    .customer-status-list li:last-child {
        border-bottom: 0;
    }

    .flow-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }

    .flow-list li {
        padding: 0.7rem 0;
        border-bottom: 1px solid #eceef1;
        color: #566a7f;
    }

    .flow-list li:last-child {
        border-bottom: 0;
    }

    .card-header h5 {
        margin-bottom: 0;
    }

    @media (max-width: 1199.98px) {
        .layout-menu {
            position: fixed;
            z-index: 1100;
        }
    }
    </style>
</head>
<body>
    <div class="layout-wrapper layout-content-navbar">
        <div class="layout-container">

            <?php
            $paginaAtiva = 'dashboard';
            require_once __DIR__ . '/includes/menu.php';
            ?>

            <div class="layout-page">
                <!-- Navbar -->
                <nav class="layout-navbar container-xxl navbar-detached navbar navbar-expand-xl align-items-center bg-navbar-theme"
                    id="layout-navbar">
                    <div class="layout-menu-toggle navbar-nav align-items-xl-center me-4 me-xl-0 d-xl-none">
                        <a class="nav-item nav-link px-0 me-xl-6" href="javascript:void(0)">
                            <i class="icon-base bx bx-menu icon-md"></i>
                        </a>
                    </div>

                    <div class="navbar-nav-right d-flex align-items-center justify-content-end w-100">
                        <div class="navbar-nav align-items-center me-auto">
                            <div class="nav-item d-flex align-items-center">
                                <span class="w-px-22 h-px-22"><i class="icon-base bx bx-search icon-md"></i></span>
                                <input type="text"
                                    class="form-control border-0 shadow-none ps-1 ps-sm-2 d-md-block d-none"
                                    placeholder="Buscar cliente, cobrança ou pagamento..." aria-label="Buscar" />
                            </div>
                        </div>

                        <ul class="navbar-nav flex-row align-items-center ms-md-auto">
                            <li class="nav-item me-3">
                                <span class="badge rounded-pill bg-label-warning">37 pendências</span>
                            </li>

                            <li class="nav-item navbar-dropdown dropdown-user dropdown">
                                <a class="nav-link dropdown-toggle hide-arrow p-0" href="javascript:void(0);"
                                    data-bs-toggle="dropdown">
                                    <div class="avatar avatar-online">
                                        <img src="../assets/img/avatars/1.png" alt
                                            class="w-px-40 h-auto rounded-circle" />
                                    </div>
                                </a>
                                <ul class="dropdown-menu dropdown-menu-end">
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <div class="d-flex">
                                                <div class="flex-shrink-0 me-3">
                                                    <div class="avatar avatar-online">
                                                        <img src="../assets/img/avatars/1.png" alt
                                                            class="w-px-40 h-auto rounded-circle" />
                                                    </div>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0">Administrador</h6>
                                                    <small class="text-body-secondary">Tático GPS</small>
                                                </div>
                                            </div>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider my-1"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="icon-base bx bx-user icon-md me-3"></i><span>Meu Perfil</span>
                                        </a>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="icon-base bx bx-cog icon-md me-3"></i><span>Configurações</span>
                                        </a>
                                    </li>
                                    <li>
                                        <div class="dropdown-divider my-1"></div>
                                    </li>
                                    <li>
                                        <a class="dropdown-item" href="#">
                                            <i class="icon-base bx bx-power-off icon-md me-3"></i><span>Sair</span>
                                        </a>
                                    </li>
                                </ul>
                            </li>
                        </ul>
                    </div>

                </nav>
                <!-- / Navbar -->

                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card dashboard-banner">
                                    <div class="card-body">
                                        <h3 class="text-primary">Painel de Cobrança - Tático GPS</h3>
                                        <p>
                                            Acompanhe vencimentos, pagamentos, inadimplência e mensagens automáticas em
                                            um painel mais limpo e
                                            objetivo.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <div class="stat-title">Clientes Ativos</div>
                                        <div class="stat-value">248</div>
                                        <div class="stat-meta text-success">
                                            <i class="bx bx-up-arrow-alt"></i> +12 este mês
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <div class="stat-title">Pendentes</div>
                                        <div class="stat-value">37</div>
                                        <div class="stat-meta text-danger">
                                            <i class="bx bx-error-circle"></i> requer atenção
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <div class="stat-title">Recebido na Semana</div>
                                        <div class="stat-value" style="font-size: 1.7rem;">R$ 18.450,00</div>
                                        <div class="stat-meta text-success">
                                            <i class="bx bx-check-circle"></i> pagamentos confirmados
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-3 col-md-6 mb-4">
                                <div class="card stat-card h-100">
                                    <div class="card-body">
                                        <div class="stat-title">Mensagens Enviadas</div>
                                        <div class="stat-value">126</div>
                                        <div class="stat-meta text-primary">
                                            <i class="bx bx-message-square-detail"></i> cobranças automáticas
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xl-8 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5>Recebimentos por Semana</h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="recebimentosSemanaChart"></div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5>Status da Carteira</h5>
                                    </div>
                                    <div class="card-body">
                                        <div id="statusCarteiraChart"></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xl-8 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5>Resumo Operacional</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <div class="summary-box">
                                                    <h6>Cobranças próximas</h6>
                                                    <p>Clientes com vencimento próximo para disparo automático de aviso.
                                                    </p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="summary-box">
                                                    <h6>Lembretes automáticos</h6>
                                                    <p>Mensagens em 10, 5 e 3 dias antes do vencimento.</p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="summary-box">
                                                    <h6>Comprovantes</h6>
                                                    <p>Leitura de comprovante para marcar pagamento no sistema.</p>
                                                </div>
                                            </div>
                                            <div class="col-md-6">
                                                <div class="summary-box">
                                                    <h6>PIX personalizado</h6>
                                                    <p>Envio da chave PIX, valor e mensagem específica por cliente.</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-4 mb-4">
                                <div class="card h-100 quick-actions">
                                    <div class="card-header">
                                        <h5>Ações Rápidas</h5>
                                    </div>
                                    <div class="card-body d-grid gap-3">
                                        <a href="#" class="btn btn-primary">Cadastrar Cliente</a>
                                        <a href="#" class="btn btn-outline-primary">Gerar Cobrança</a>
                                        <a href="#" class="btn btn-outline-primary">Ler Comprovante</a>
                                        <a href="#" class="btn btn-outline-primary">Enviar Mensagem</a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-xl-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5>Clientes com pendência</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="list-unstyled customer-status-list mb-0">
                                            <li>
                                                <span>João Silva</span>
                                                <span class="badge bg-label-danger">Pendente</span>
                                            </li>
                                            <li>
                                                <span>Maria Oliveira</span>
                                                <span class="badge bg-label-warning">Aviso enviado</span>
                                            </li>
                                            <li>
                                                <span>Carlos Mendes</span>
                                                <span class="badge bg-label-danger">Bloqueio previsto</span>
                                            </li>
                                            <li>
                                                <span>Ana Souza</span>
                                                <span class="badge bg-label-success">Pago</span>
                                            </li>
                                        </ul>
                                    </div>
                                </div>
                            </div>

                            <div class="col-xl-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header">
                                        <h5>Fluxo da cobrança</h5>
                                    </div>
                                    <div class="card-body">
                                        <ul class="flow-list">
                                            <li><strong>7 dias antes:</strong> aviso inicial de vencimento</li>
                                            <li><strong>10 / 5 / 3 dias:</strong> mensagens automáticas</li>
                                            <li><strong>Pagamento:</strong> leitura do comprovante e baixa</li>
                                            <li><strong>Sem pagamento:</strong> cliente fica pendente</li>
                                            <li><strong>Atraso contínuo:</strong> mensagem de bloqueio</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl">
                            <div
                                class="footer-container d-flex align-items-center justify-content-between py-4 flex-md-row flex-column">
                                <div class="mb-2 mb-md-0">
                                    ©
                                    <script>
                                    document.write(new Date().getFullYear());
                                    </script>
                                    - Tático GPS. Todos os direitos reservados.
                                </div>
                            </div>
                        </div>
                    </footer>

                    <div class="content-backdrop fade"></div>
                </div>
            </div>
        </div>

        <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/vendor/libs/apex-charts/apexcharts.js"></script>
    <script src="../assets/js/main.js"></script>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        const recebimentosSemanaOptions = {
            chart: {
                type: 'bar',
                height: 320,
                toolbar: {
                    show: false
                }
            },
            series: [{
                name: 'Recebido',
                data: [9200, 11350, 10400, 12890, 13750, 14900, 16100, 18450]
            }],
            xaxis: {
                categories: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4', 'Sem 5', 'Sem 6', 'Sem 7', 'Sem 8']
            },
            dataLabels: {
                enabled: false
            },
            plotOptions: {
                bar: {
                    borderRadius: 6,
                    columnWidth: '45%'
                }
            },
            yaxis: {
                labels: {
                    formatter: function(value) {
                        return 'R$ ' + value.toLocaleString('pt-BR');
                    }
                }
            },
            tooltip: {
                y: {
                    formatter: function(value) {
                        return 'R$ ' + value.toLocaleString('pt-BR');
                    }
                }
            }
        };

        new ApexCharts(document.querySelector('#recebimentosSemanaChart'), recebimentosSemanaOptions).render();

        const statusCarteiraOptions = {
            chart: {
                type: 'donut',
                height: 320
            },
            labels: ['Pagos', 'Pendentes', 'Em aviso', 'Bloqueados'],
            series: [248, 37, 22, 9],
            legend: {
                position: 'bottom'
            },
            dataLabels: {
                enabled: true
            }
        };

        new ApexCharts(document.querySelector('#statusCarteiraChart'), statusCarteiraOptions).render();
    });
    </script>
</body>

</html>