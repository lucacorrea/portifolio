<!doctype html>
<html lang="pt-BR" class="layout-menu-fixed layout-compact" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0, user-scalable=no, minimum-scale=1.0, maximum-scale=1.0" />

    <title>Tático GPS - Configurações</title>
    <meta name="description" content="Configurações do sistema Tático GPS" />

    <link rel="icon" type="image/x-icon" href="../assets/img/favicon/favicon.ico" />
    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Public+Sans:wght@300;400;500;600;700&display=swap"
        rel="stylesheet" />

    <link rel="stylesheet" href="../assets/vendor/fonts/iconify-icons.css" />
    <link rel="stylesheet" href="../assets/vendor/css/core.css" />
    <link rel="stylesheet" href="../assets/css/demo.css" />
    <link rel="stylesheet" href="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.css" />

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
        }

        .page-banner p {
            color: #697a8d;
            margin-bottom: 0;
        }

        .settings-card .card-header h5 {
            margin: 0;
        }

        .form-section-title {
            font-size: .95rem;
            font-weight: 700;
            color: #566a7f;
            margin-bottom: .85rem;
            padding-bottom: .45rem;
            border-bottom: 1px solid #eceef1;
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
            <aside id="layout-menu" class="layout-menu menu-vertical menu bg-menu-theme">
                <div class="app-brand demo">
                    <a href="dashboard.html" class="app-brand-link">
                        <span class="app-brand-logo demo">
                            <span class="text-primary">
                                <svg width="25" viewBox="0 0 25 42" xmlns="http://www.w3.org/2000/svg">
                                    <path fill="currentColor"
                                        d="M13.7918663,0.358365126 L3.39788168,7.44174259 C0.566865006,9.69408886 -0.379795268,12.4788597 0.557900856,15.7960551 C0.68998853,16.2305145 1.09562888,17.7872135 3.12357076,19.2293357 C3.8146334,19.7207684 5.32369333,20.3834223 7.65075054,21.2172976 L7.59773219,21.2525164 L2.63468769,24.5493413 C0.445452254,26.3002124 0.0884951797,28.5083815 1.56381646,31.1738486 C2.83770406,32.8170431 5.20850219,33.2640127 7.09180128,32.5391577 C8.347334,32.0559211 11.4559176,30.0011079 16.4175519,26.3747182 C18.0338572,24.4997857 18.6973423,22.4544883 18.4080071,20.2388261 C17.963753,17.5346866 16.1776345,15.5799961 13.0496516,14.3747546 L10.9194936,13.4715819 L18.6192054,7.984237 L13.7918663,0.358365126 Z" />
                                </svg>
                            </span>
                        </span>
                        <span class="app-brand-text demo menu-text fw-bold ms-2">Tático GPS</span>
                    </a>
                </div>

                <div class="menu-divider mt-0"></div>
                <ul class="menu-inner py-1">
                    <li class="menu-item"><a href="dashboard.html" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-home-circle"></i>
                            <div>Painel Geral</div>
                        </a></li>
                    <li class="menu-item"><a href="clientes.html" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-user"></i>
                            <div>Clientes</div>
                        </a></li>
                    <li class="menu-item"><a href="cobrancas.html" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-wallet"></i>
                            <div>Cobranças</div>
                        </a></li>
                    <li class="menu-item"><a href="pagamentos.html" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-credit-card"></i>
                            <div>Pagamentos</div>
                        </a></li>
                    <li class="menu-item"><a href="mensagens.html" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-message-detail"></i>
                            <div>Mensagens</div>
                        </a></li>
                    <li class="menu-item"><a href="relatorios.html" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-bar-chart-alt-2"></i>
                            <div>Relatórios</div>
                        </a></li>
                    <li class="menu-item active"><a href="configuracoes.html" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-cog"></i>
                            <div>Configurações</div>
                        </a></li>
                    <li class="menu-item"><a href="logout.html" class="menu-link"><i
                                class="menu-icon tf-icons bx bx-power-off"></i>
                            <div>Sair</div>
                        </a></li>
                </ul>
            </aside>

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
                                <div class="card page-banner">
                                    <div class="card-body">
                                        <h3 class="text-primary">Configurações</h3>
                                        <p>Ajuste as regras gerais do sistema, cobrança, PIX e mensagens automáticas.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card settings-card">
                                    <div class="card-header">
                                        <h5>Dados da Empresa</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Nome da empresa</label>
                                                <input class="form-control" value="Tático GPS" />
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">CNPJ</label>
                                                <input class="form-control" value="12.345.678/0001-99" />
                                            </div>
                                            <div class="col-md-3">
                                                <label class="form-label">Telefone</label>
                                                <input class="form-control" value="(68) 99999-0000" />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">E-mail</label>
                                                <input class="form-control" value="contato@taticogps.com" />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Endereço</label>
                                                <input class="form-control" value="Rua Exemplo, 123 - Centro" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card settings-card h-100">
                                    <div class="card-header">
                                        <h5>Regras de Cobrança</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Dia padrão de vencimento</label>
                                                <select class="form-select">
                                                    <option>05</option>
                                                    <option selected>10</option>
                                                    <option>15</option>
                                                    <option>20</option>
                                                    <option>25</option>
                                                    <option>30</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Mensalidade padrão</label>
                                                <input class="form-control" value="R$ 89,90" />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Multa por atraso</label>
                                                <input class="form-control" value="2%" />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Juros por atraso</label>
                                                <input class="form-control" value="1% ao mês" />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Marcar pendente após</label>
                                                <input class="form-control" value="3 dias" />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Bloquear após</label>
                                                <input class="form-control" value="7 dias" />
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card settings-card h-100">
                                    <div class="card-header">
                                        <h5>PIX</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label class="form-label">Nome do recebedor</label>
                                                <input class="form-control" value="Tático GPS LTDA" />
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Tipo da chave</label>
                                                <select class="form-select">
                                                    <option>CPF</option>
                                                    <option>CNPJ</option>
                                                    <option>E-mail</option>
                                                    <option selected>Telefone</option>
                                                    <option>Chave aleatória</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Chave PIX</label>
                                                <input class="form-control" value="(68) 99999-0000" />
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Texto padrão da cobrança com PIX</label>
                                                <textarea class="form-control"
                                                    rows="4">Olá, sua mensalidade do Tático GPS está em aberto. Segue a chave PIX para pagamento e o valor atualizado da cobrança.</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card settings-card h-100">
                                    <div class="card-header">
                                        <h5>Mensagens Automáticas</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">Ativar automação de mensagens</label>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-12">
                                                <label class="form-label">Mensagem 10 dias antes</label>
                                                <textarea class="form-control"
                                                    rows="2">Olá, sua mensalidade vence em 10 dias.</textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Mensagem 7 dias antes</label>
                                                <textarea class="form-control"
                                                    rows="2">Aviso: faltam 7 dias para o vencimento da sua mensalidade.</textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Mensagem 5 dias antes</label>
                                                <textarea class="form-control"
                                                    rows="2">Lembrete: sua cobrança vence em 5 dias.</textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Mensagem 3 dias antes</label>
                                                <textarea class="form-control"
                                                    rows="2">Último lembrete antes do vencimento da sua mensalidade.</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-lg-6">
                                <div class="card settings-card h-100">
                                    <div class="card-header">
                                        <h5>Status e Pagamentos</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">Permitir baixa manual</label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox" checked>
                                            <label class="form-check-label">Permitir leitura de comprovante</label>
                                        </div>
                                        <div class="form-check form-switch mb-3">
                                            <input class="form-check-input" type="checkbox">
                                            <label class="form-check-label">Baixa automática após conferência</label>
                                        </div>

                                        <div class="row g-3 mt-1">
                                            <div class="col-md-6">
                                                <label class="form-label">Status padrão do cliente</label>
                                                <select class="form-select">
                                                    <option selected>Ativo</option>
                                                    <option>Pendente</option>
                                                    <option>Bloqueado</option>
                                                    <option>Inativo</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Status após pagamento</label>
                                                <select class="form-select">
                                                    <option selected>Ativo</option>
                                                    <option>Pendente</option>
                                                </select>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Mensagem de bloqueio</label>
                                                <textarea class="form-control"
                                                    rows="3">Seu rastreador poderá ser bloqueado por falta de pagamento. Regularize sua mensalidade o quanto antes.</textarea>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-primary"><i class="bx bx-save me-1"></i>Salvar
                                        Configurações</button>
                                    <button class="btn btn-outline-secondary">Restaurar Padrões</button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <footer class="content-footer footer bg-footer-theme">
                        <div class="container-xxl py-4">©
                            <script>document.write(new Date().getFullYear())</script> - Tático GPS
                        </div>
                    </footer>
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
    <script src="../assets/js/main.js"></script>
</body>

</html>