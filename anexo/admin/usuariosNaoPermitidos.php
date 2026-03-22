<?php

declare(strict_types=1);
require_once __DIR__ . '/./auth/authGuard.php';
auth_guard();

require_once __DIR__ . '/../dist/assets/conexao.php'; // $pdo (PDO)

if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo "<script>alert('Erro: conexão com o banco não encontrada.');</script>";
    exit;
}

function e(string $v): string
{
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// Consulta somente não autorizados
try {
    $sql = "SELECT id, nome, email FROM contas_acesso_privado WHERE autorizado='nao' ORDER BY nome ASC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo "<script>alert('Erro ao consultar contas: " . e($e->getMessage()) . "');</script>";
    exit;
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Usuários Não Permitidos - ANEXO</title>

    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@300;400;600;700;800&display=swap" rel="stylesheet">

    <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
    <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
    <link rel="stylesheet" href="../dist/assets/vendors/iconly/bold.css">
    <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
    <link rel="stylesheet" href="../dist/assets/css/app.css">
    <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

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

                <!-- MENU (ANEXO padrão) -->
                <div class="sidebar-menu">
                    <ul class="menu">
                        <li class="sidebar-item">
                            <a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Dashboard</span></a>
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
                                <li class="submenu-item "><a href="#">Cadastros</a></li>
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

                        <li class="sidebar-item has-sub active">
                            <a href="#" class="sidebar-link"><i class="bi bi-person-fill"></i><span>Usuários</span></a>
                            <ul class="submenu active">
                                <li class="submenu-item"><a href="usuariosPermitidos.php">Permitidos</a></li>
                                <li class="submenu-item active"><a href="usuariosNaoPermitidos.php">Não Permitidos</a></li>
                            </ul>
                        </li>

                        <!-- AUDITORIA / LOG -->
                        <li class="sidebar-item">
                            <a href="auditoria.php" class="sidebar-link">
                                <i class="bi bi-shield-lock-fill"></i>
                                <span>Auditoria</span>
                            </a>
                        </li>

                        <li class="sidebar-item">
                            <a href="./auth/logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a>
                        </li>
                    </ul>
                </div>
                <!-- /MENU -->
            </div>
        </div>

        <div id="main">
            <header class="mb-3">
                <a href="#" class="burger-btn d-block d-xl-none">
                    <i class="bi bi-justify fs-3"></i>
                </a>
            </header>

            <div class="page-heading">
                <div class="page-title">
                    <div class="row">
                        <div class="col-12 col-md-6 order-md-1 order-last">
                            <h3>Usuários Não Permitidos</h3>
                            <p class="text-subtitle text-muted">Visualize os Usuários Não Permitidos</p>
                        </div>
                        <div class="col-12 col-md-6 order-md-2 order-first">
                            <nav aria-label="breadcrumb" class="breadcrumb-header float-start float-lg-end">
                                <ol class="breadcrumb">
                                    <li class="breadcrumb-item"><a href="#">Usuários</a></li>
                                    <li class="breadcrumb-item active" aria-current="page">Usuários Não Permitidos</li>
                                </ol>
                            </nav>
                        </div>
                    </div>
                </div>
                <section class="section">
                    <div class="card">
                        <div class="card-header">
                            Lista de Usuários Não Permitidos
                        </div>
                        <div class="card-body">

                            <div class="table-responsive">
                                <table class="table table-striped align-middle" id="table1">
                                    <colgroup>
                                        <col> <!-- Nome -->
                                        <col> <!-- Email -->
                                        <col style="width: 150px;"> <!-- Status -->
                                        <col style="width: 96px;"> <!-- Ações -->
                                    </colgroup>
                                    <thead>
                                        <tr>
                                            <th>Nome</th>
                                            <th>Email</th>
                                            <th>Status</th>
                                            <th>Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($rows)): ?>
                                            <tr>
                                                <td colspan="4">Nenhuma conta pendente de autorização.</td>
                                            </tr>
                                            <?php else: foreach ($rows as $r): ?>
                                                <tr>
                                                    <td class="text-truncate"><?= e($r['nome']) ?></td>
                                                    <td class="text-truncate"><?= e($r['email']) ?></td>
                                                    <td><span class="badge bg-danger">Não Permitido</span></td>
                                                    <td class="text-nowrap">
                                                        <!-- Botão que abre a modal -->
                                                        <button type="button"
                                                            class="btn btn-sm btn-outline-primary"
                                                            title="Autorizar"
                                                            data-bs-toggle="modal"
                                                            data-bs-target="#modalAutorizar"
                                                            data-id="<?= (int)$r['id'] ?>"
                                                            data-nome="<?= e($r['nome']) ?>">
                                                            <i class="bi bi-check2-circle"></i>
                                                        </button>

                                                    </td>
                                                </tr>
                                        <?php endforeach;
                                        endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Modal única -->
                            <div class="modal fade" id="modalAutorizar" tabindex="-1" aria-labelledby="modalAutorizarLabel" aria-hidden="true">
                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content">
                                        <form action="./user/autorizarAcesso.php" method="post">
                                            <input type="hidden" name="id" id="autorizarId">
                                            <div class="modal-header">
                                                <h5 class="modal-title" id="modalAutorizarLabel">Autorizar acesso</h5>
                                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
                                            </div>
                                            <div class="modal-body">
                                                Deseja autorizar o acesso de <strong id="autorizarNome"></strong>?
                                            </div>
                                            <div class="modal-footer">
                                                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancelar</button>
                                                <button type="submit" class="btn btn-primary">Autorizar</button>
                                            </div>
                                        </form>
                                    </div>
                                </div>
                            </div>

                            <script>
                                // Tradução do DataTable (se estiver usando)
                                if (window.simpleDatatables) {
                                    new simpleDatatables.DataTable("#table1", {
                                        searchable: true,
                                        fixedHeight: true,
                                        perPage: 10,
                                        perPageSelect: [5, 10, 25, 50],
                                        labels: {
                                            placeholder: "Buscar...",
                                            perPage: "{select} por página",
                                            noRows: "Nenhuma conta pendente",
                                            info: "Mostrando {start} a {end} de {rows} entradas",
                                            noResults: "Nenhum resultado para \"{query}\"",
                                            sort: "Ordenar por {column}"
                                        }
                                    });
                                }

                                // Preenche a modal
                                document.getElementById('modalAutorizar').addEventListener('show.bs.modal', function(event) {
                                    const btn = event.relatedTarget;
                                    const id = btn.getAttribute('data-id');
                                    const nome = btn.getAttribute('data-nome');
                                    document.getElementById('autorizarId').value = id;
                                    document.getElementById('autorizarNome').textContent = nome || '';
                                });
                            </script>

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
        // Simple Datatable
        let table1 = document.querySelector('#table1');
        let dataTable = new simpleDatatables.DataTable(table1);
    </script>

    <script src="assets/js/main.js"></script>