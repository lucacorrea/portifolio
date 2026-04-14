<?php
require_once '../dist/assets/php/login/verificaLogin.php';
?>

<!doctype html>
<html lang="en" dir="ltr">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>AutoERP - Configurações</title>
    <link rel="icon" type="image/png" sizes="512x512" href="../dist/dashboard/assets/images/dashboard/icon.png">

    <!-- Favicon -->
    <link rel="shortcut icon" href="../dist/dashboard/assets/images/favicon.ico">

    <!-- Library / Plugin Css Build -->
    <link rel="stylesheet" href="../dist/dashboard/assets/css/core/libs.min.css">

    <!-- Aos Animation Css -->
    <link rel="stylesheet" href="../dist/dashboard/assets/vendor/aos/dist/aos.css">

    <!-- Hope Ui Design System Css -->
    <link rel="stylesheet" href="../dist/dashboard/assets/css/hope-ui.min.css?v=4.0.0">

    <!-- Custom Css -->
    <link rel="stylesheet" href="../dist/dashboard/assets/css/custom.min.css?v=4.0.0">

    <!-- Dark Css -->
    <link rel="stylesheet" href="../dist/dashboard/assets/css/dark.min.css">

    <!-- Customizer Css -->
    <link rel="stylesheet" href="../dist/dashboard/assets/css/customizer.min.css">
    <link rel="stylesheet" href="../dist/dashboard/assets/css/customizer.css">

    <!-- RTL Css -->
    <link rel="stylesheet" href="../dist/dashboard/assets/css/rtl.min.css">

    <!--Icons-->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">

</head>

<body class="  ">

    <aside class="sidebar sidebar-default sidebar-white sidebar-base navs-rounded-all ">
        <div class="sidebar-header d-flex align-items-center justify-content-start">
            <a href="../dist/dashboard/index.php" class="navbar-brand">
                <div class="logo-main">
                    <div class="logo-normal">
                        <img src="../dist/dashboard/assets/images/auth/ode.png" alt="logo" class="logo-dashboard">
                    </div>
                </div>
                <h4 class="logo-title title-dashboard">AutoERP</h4>
            </a>
            <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
                <i class="icon">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path d="M4.25 12.2744L19.25 12.2744" stroke="currentColor" stroke-width="1.5"
                            stroke-linecap="round" stroke-linejoin="round"></path>
                        <path d="M10.2998 18.2988L4.2498 12.2748L10.2998 6.24976" stroke="currentColor"
                            stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                    </svg>
                </i>
            </div>
        </div>
        <div class="sidebar-body pt-0 data-scrollbar">
            <div class="sidebar-list">
                <ul class="navbar-nav iq-main-menu" id="sidebar-menu">
                    <!-- DASHBOARD -->
                    <li class="nav-item">
                        <a class="nav-link " aria-current="page" href="#">
                            <i class="bi bi-grid icon"></i>
                            <span class="item-name">Dashboard</span>
                        </a>
                    </li>
                    <li>
                        <hr class="hr-horizontal">
                    </li>

                    <!-- GRUPO: Vendas -->
                    <li class="nav-item">
                        <a class="nav-link" href="../dist/vendas/vendaRapida.php">
                            <i class="bi bi-cash-coin icon"></i>
                            <span class="item-name">Venda Rápida</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../dist/vendas/orcamentos.php">
                            <i class="bi bi-file-earmark-text icon"></i>
                            <span class="item-name">Orçamentos</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../dist/lavaJato/lavagemRapida.php">
                            <i class="bi bi-plus-circle icon"></i>
                            <span class="item-name">Lavagem Rápida</span>
                        </a>
                    </li>
                    <!-- GRUPO: AUTOPEÇAS -->
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-autopecas" role="button"
                            aria-expanded="false" aria-controls="sidebar-autopecas">
                            <i class="bi bi-truck icon"></i>
                            <span class="item-name">Estoque</span>
                            <i class="bi bi-chevron-right right-icon"></i>
                        </a>
                        <ul class="sub-nav collapse" id="sidebar-autopecas" data-bs-parent="#sidebar-menu">
                            <li class="nav-item">
                                <a class="nav-link" href="../dist/estoque/estoque.php">
                                    <i class="bi bi-box icon"></i>
                                    <span class="item-name">Estoque</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../dist/estoque/produtos.php">
                                    <i class="bi bi-gear icon"></i>
                                    <span class="item-name">Produtos</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../dist/estoque/fornecedores.php">
                                    <i class="bi bi-person-check icon"></i>
                                    <span class="item-name">Fornecedores</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- GRUPO: LAVA JATO -->
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-lavajato" role="button"
                            aria-expanded="false" aria-controls="sidebar-lavajato">
                            <i class="bi bi-droplet-half icon"></i>
                            <span class="item-name">Lava Jato</span>
                            <i class="bi bi-chevron-right right-icon"></i>
                        </a>
                        <ul class="sub-nav collapse" id="sidebar-lavajato" data-bs-parent="#sidebar-menu">
                            <li class="nav-item">
                                <a class="nav-link" href="../dist/lavaJato/lavagensLista.php">
                                    <i class="bi bi-list-ul icon"></i>
                                    <span class="item-name">Lista de Lavagens</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../dist/lavaJato/servicos.php">
                                    <i class="bi bi-person-badge icon"></i>
                                    <span class="item-name">Cadastrar Lavadores</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../dist/lavaJato/precos.php">
                                    <i class="bi bi-currency-exchange icon"></i>
                                    <span class="item-name">Cadastrar Preços</span>
                                </a>
                            </li>

                        </ul>
                    </li>

                    <!-- GRUPO: RELATÓRIOS -->
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-relatorios" role="button"
                            aria-expanded="false" aria-controls="sidebar-relatorios">
                            <i class="bi bi-clipboard-data icon"></i>
                            <span class="item-name">Relatórios</span>
                            <i class="bi bi-chevron-right right-icon"></i>
                        </a>
                        <ul class="sub-nav collapse" id="sidebar-relatorios" data-bs-parent="#sidebar-menu">
                            <li class="nav-item">
                                <a class="nav-link" href="../dist/vendas/relatorioVendas.php">
                                    <i class="bi bi-bar-chart icon"></i>
                                    <span class="item-name">Vendas</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="../dist/vendas/relatorioFinanceiro.php">
                                    <i class="bi bi-graph-up-arrow icon"></i>
                                    <span class="item-name">Financeiro</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                    <!-- GRUPO: CONFIGURAÇÕES -->
                    <li class="nav-item">
                        <a class="nav-link" data-bs-toggle="collapse" href="#sidebar-config" role="button"
                            aria-expanded="false" aria-controls="sidebar-config">
                            <i class="bi bi-gear icon"></i>
                            <span class="item-name">Configurações</span>
                            <i class="bi bi-chevron-right right-icon"></i>
                        </a>
                        <ul class="sub-nav collapse" id="sidebar-config" data-bs-parent="#sidebar-menu">
                            <li class="nav-item">
                                <a class="nav-link" href="usuarios.php">
                                    <i class="bi bi-person-gear icon"></i>
                                    <span class="item-name">Usuários</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link active" href="cadastrar_usuario.php">
                                    <i class="bi bi-person-plus icon"></i>
                                    <span class="item-name">Cadastrar Usuários</span>
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="empresa.php">
                                    <i class="bi bi-building icon"></i>
                                    <span class="item-name">Dados da Empresa</span>
                                </a>
                            </li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
        <div class="sidebar-footer"></div>
    </aside>

    <main class="main-content">
        <div class="position-relative iq-banner">
            <!--Nav Start-->
            <nav class="nav navbar navbar-expand-lg navbar-light iq-navbar">
                <div class="container-fluid navbar-inner">
                    <a href="../dist/dashboard/index.php" class="navbar-brand">
                        <h4 class="logo-title">AutoERP</h4>
                    </a>
                    <div class="sidebar-toggle" data-toggle="sidebar" data-active="true">
                        <i class="icon">
                            <svg width="20px" class="icon-20" viewBox="0 0 24 24">
                                <path fill="currentColor"
                                    d="M4,11V13H16L10.5,18.5L11.92,19.92L19.84,12L11.92,4.08L10.5,5.5L16,11H4Z" />
                            </svg>
                        </i>
                    </div>
                    <div class="input-group search-input">
                        <span class="input-group-text" id="search-input">
                            <svg class="icon-18" width="18" viewBox="0 0 24 24" fill="none"
                                xmlns="http://www.w3.org/2000/svg">
                                <circle cx="11.7669" cy="11.7666" r="8.98856" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round"></circle>
                                <path d="M18.0186 18.4851L21.5426 22" stroke="currentColor" stroke-width="1.5"
                                    stroke-linecap="round" stroke-linejoin="round"></path>
                            </svg>
                        </span>
                        <input type="search" class="form-control" placeholder="Search...">
                    </div>
                </div>
            </nav> <!-- Nav Header Component Start -->
            <div class="iq-navbar-header" style="height: 215px;">
                <div class="container-fluid iq-container">
                    <div class="row">
                        <div class="col-md-12">
                            <div class="flex-wrap d-flex justify-content-between align-items-center">
                                <div>
                                    <h1>Cadastro de Usuário</h1>
                                    <p>Adicione novos usuários para o seu sistema de autopeças.
                                        Você pode criar quantas contas forem necessárias, garantindo que cada pessoa tenha seu próprio acesso e permissões personalizadas.</p>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
                <div class="iq-header-img">
                    <img src="../dist/dashboard/assets/images/dashboard/top-header.png" alt="header"
                        class="theme-color-default-img img-fluid w-100 h-100 animated-scaleX">

                </div>
            </div> <!-- Nav Header Component End -->
            <!--Nav End-->
        </div>
        <div class="container-fluid content-inner mt-n5 py-0">
            <div class="row">
                <div class="col-sm-12">
                    <div class="card">
                        <div class="card-header">
                            <h4 class="card-title mb-0">Cadastro de Usuário</h4>
                        </div>
                        <div class="card-body">
                            <form action="php/processarCadastro.php" method="POST">
                                <div class="row g-3">

                                    <div class="col-md-6">
                                        <label for="nome" class="form-label">Nome Completo</label>
                                        <input type="text" class="form-control" id="nome" name="nome" placeholder="Digite o nome" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="empresa" class="form-label">Empresa</label>
                                        <input type="text" class="form-control" id="empresa" name="empresa" placeholder="Nome da empresa" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="cnpj" class="form-label">CNPJ</label>
                                        <input type="text" class="form-control" id="cnpj" name="cnpj" placeholder="00.000.000/0000-00" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="endereco" class="form-label">Endereço</label>
                                        <input type="text" class="form-control" id="endereco" name="endereco" placeholder="Endereço da empresa" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="email" class="form-label">E-mail</label>
                                        <input type="email" class="form-control" id="email" name="email" placeholder="Digite o e-mail" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="cpf" class="form-label">CPF</label>
                                        <input type="text" class="form-control" id="cpf" name="cpf" placeholder="000.000.000-00" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="telefone" class="form-label">Telefone</label>
                                        <input type="text" class="form-control" id="telefone" name="telefone" placeholder="(00) 00000-0000">
                                    </div>

                                    <div class="col-md-6">
                                        <label for="senha" class="form-label">Senha</label>
                                        <input type="password" class="form-control" id="senha" name="senha" placeholder="Digite a senha" required>
                                    </div>

                                    <div class="col-md-6">
                                        <label for="perfil" class="form-label">Perfil</label>
                                        <select class="form-select" id="perfil" name="perfil" required>
                                            <option value="">Selecione...</option>
                                            <option value="Administrador">Administrador</option>
                                            <option value="Usuário">Usuário</option>
                                            <option value="Gerente">Gerente</option>
                                        </select>
                                    </div>

                                    <div class="col-md-6">
                                        <label class="form-label d-block">Status</label>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="status" id="ativo" value="1" checked>
                                            <label class="form-check-label" for="ativo">Ativo</label>
                                        </div>
                                        <div class="form-check form-check-inline">
                                            <input class="form-check-input" type="radio" name="status" id="inativo" value="0">
                                            <label class="form-check-label" for="inativo">Inativo</label>
                                        </div>
                                    </div>

                                </div>

                                <div class="mt-4 text-center">
                                    <button type="submit" class="btn btn-primary w-100">Salvar</button>

                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

    </main>


    <!-- Wrapper End-->
    <!-- offcanvas start -->


    <!-- Library Bundle Script -->
    <script src="../dist/dashboard/assets/js/core/libs.min.js"></script>

    <!-- External Library Bundle Script -->
    <script src="../dist/dashboard/assets/js/core/external.min.js"></script>

    <!-- Widgetchart Script -->
    <script src="../dist/dashboard/assets/js/charts/widgetcharts.js"></script>

    <!-- mapchart Script -->
    <script src="../dist/dashboard/assets/js/charts/vectore-chart.js"></script>
    <script src="../dist/dashboard/assets/js/charts/dashboard.js"></script>

    <!-- fslightbox Script -->
    <script src="../dist/dashboard/assets/js/plugins/fslightbox.js"></script>

    <!-- Settings Script -->
    <script src="../dist/dashboard/assets/js/plugins/setting.js"></script>

    <!-- Slider-tab Script -->
    <script src="../dist/dashboard/assets/js/plugins/slider-tabs.js"></script>

    <!-- Form Wizard Script -->
    <script src="../dist/dashboard/assets/js/plugins/form-wizard.js"></script>

    <!-- AOS Animation Plugin-->
    <script src="../dist/dashboard/assets/vendor/aos/dist/aos.js"></script>

    <!-- App Script -->
    <script src="../dist/dashboard/assets/js/hope-ui.js" defer></script>


</body>

</html>