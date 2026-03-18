<?php

declare(strict_types=1);
require_once __DIR__ . '/./auth/authGuard.php';
auth_guard();

?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cadastrar Bairro - ANEXO</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">

    <link rel="stylesheet" href="../dist/assets/vendors/simple-datatables/style.css">

    <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="../dist/assets/css/app.css">
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">
</head>

<body>
    <div id="app">
        <!-- SIDEBAR -->
        <div id="sidebar" class="active">
            <div class="sidebar-wrapper active">
                <div class="sidebar-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="logo">
                            <a href="dashboard.php"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo" style="height:48px;width:auto;"></a>
                        </div>
                        <div class="toggler">
                            <a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a>
                        </div>
                    </div>
                </div>
                <!-- MENU RESUMIDO ANEXO -->
                <div class="sidebar-menu">
                    <ul class="menu">

                        <li class="sidebar-item">
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

                        <!-- NOVO: Bairros -->
                        <li class="sidebar-item has-sub active">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-geo-alt-fill"></i>
                                <span>Bairros</span>
                            </a>
                            <ul class="submenu active">
                                <li class="submenu-item"><a href="bairrosCadastrados.php">Bairros Cadastrados</a></li>
                                <li class="submenu-item active"><a href="cadastrarBairro.php">Cadastrar Bairro</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-house-fill"></i>
                                <span>Beneficiarios</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="beneficiariosBolsaFamilia.php">Bolsa Família</a></li>
                                <li class="submenu-item"><a href="beneficiariosEstadual.php">Estadual</a></li>
                                <li class="submenu-item"><a href="beneficiariosMunicipal.php">Municipal</a></li>
                                <li class="submenu-item"><a href="beneficiariosSemas.php">ANEXO</a></li>
                            </ul>
                        </li>

                        <!-- Ajuda Social -->
                        <li class="sidebar-item has-sub ">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-hand-thumbs-up-fill"></i>
                                <span>Ajuda Social</span>
                            </a>
                            <ul class="submenu">
                                <li class="submenu-item"><a href="cadastrarBeneficio.php">Cadastrar Benefício</a></li>
                                <li class="submenu-item"><a href="beneficiosCadastrados.php">Benefícios Cadastrados</a></li>
                            </ul>
                        </li>

                        <li class="sidebar-item has-sub">
                            <a href="#" class="sidebar-link">
                                <i class="bi bi-bar-chart-line-fill"></i>
                                <span>Relatórios</span>
                            </a>
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

        <div id="main" class="d-flex flex-column min-vh-100">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last">
                            <h3>Cadastrar Bairros</h3>
                            <p class="text-subtitle text-muted">Cadastre o bairro</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#">Bairros </a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Cadastrar Bairros</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
                <section id="multiple-column-form">
                    <div class="row match-height">
                        <div class="col-12">

                            <div class="card">
                                <div class="card-content">
                                    <div class="card-body">
                                        <form action="./bairros/processarBairros.php" method="POST">
                                            <div class="mb-3 col-12 col-md-6">
                                                <label for="bairro_nome" class="form-label">Nome do Bairro</label>
                                                <input type="text" class="form-control" id="bairro_nome"
                                                    name="bairro_nome" required>
                                            </div>
                                            <button type="submit" class="btn btn-primary">Cadastrar</button>
                                        </form>
                                    </div>
                                </div>
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
        <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
        <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>

        <script src="../dist/assets/js/main.js"></script>

</body>

</html>