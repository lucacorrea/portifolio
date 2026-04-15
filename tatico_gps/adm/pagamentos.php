<?php
require_once __DIR__ . '/php/conexao.php';
require_once __DIR__ . '/php/clientes/processarDados.php'; // Para carregar h() e outras helpers
?>
<!doctype html>
<html lang="pt-BR" class="layout-menu-fixed layout-compact" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tático GPS - Pagamentos</title>

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

        .layout-menu {
            height: 100vh !important;
            position: sticky;
            top: 0;
            overflow: hidden;
        }

        .layout-menu .menu-inner {
            height: calc(100vh - 90px);
            overflow-y: auto !important;
            padding-bottom: 2rem;
        }

        .page-banner p {
            color: #697a8d;
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
           <?php $paginaAtiva = 'pagamentos'; ?>
            <?php require_once __DIR__ . '/includes/menu.php'; ?>

            <div class="layout-page">
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card page-banner">
                                    <div class="card-body">
                                        <h3 class="text-primary">Pagamentos</h3>
                                        <p>Confirme comprovantes, registre baixas e acompanhe o histórico de pagamentos.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Confirmados</div>
                                        <h2 class="mb-0 text-success">193</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Pendentes de conferência</div>
                                        <h2 class="mb-0 text-warning">12</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Baixa manual</div>
                                        <h2 class="mb-0">21</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Comprovantes recebidos</div>
                                        <h2 class="mb-0">34</h2>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
                                <h5 class="mb-0">Lista de Pagamentos</h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#modalPagamento"><i class="bx bx-plus me-1"></i>Registrar
                                        Pagamento</button>
                                    <button class="btn btn-outline-primary" data-bs-toggle="modal"
                                        data-bs-target="#modalComprovante">Ler Comprovante</button>
                                    <select class="form-select" style="width:180px">
                                        <option>Todos os status</option>
                                        <option>Confirmado</option>
                                        <option>Pendente</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>Data</th>
                                                <th>Valor</th>
                                                <th>Forma</th>
                                                <th>Status</th>
                                                <th>Comprovante</th>
                                                <th class="text-center">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php
                                            $stmtPag = $pdo->query("
                                                SELECT p.*, c.nome as cliente_nome 
                                                FROM pagamentos p
                                                JOIN clientes c ON p.cliente_id = c.id
                                                ORDER BY p.data_pagamento DESC
                                            ");
                                            $pagamentos = $stmtPag->fetchAll();

                                            if (count($pagamentos) > 0):
                                                foreach ($pagamentos as $pag):
                                                    $badgeClass = $pag['status'] === 'Confirmado' ? 'bg-label-success' : 'bg-label-warning';
                                            ?>
                                            <tr>
                                                <td><?= h($pag['cliente_nome']) ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($pag['data_pagamento'])) ?></td>
                                                <td>R$ <?= number_format($pag['valor'], 2, ',', '.') ?></td>
                                                <td><?= h($pag['forma_pagamento']) ?></td>
                                                <td><span class="badge <?= $badgeClass ?>"><?= h($pag['status']) ?></span></td>
                                                <td><?= $pag['comprovante_url'] ? 'Sim' : 'Automático' ?></td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-outline-secondary">Ver</button>
                                                </td>
                                            </tr>
                                            <?php 
                                                endforeach;
                                            else:
                                            ?>
                                            <tr>
                                                <td colspan="7" class="text-center">Nenhum pagamento registrado ainda.</td>
                                            </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                      <?php require_once __DIR__ . '/includes/footer.php'; ?>

                </div>
            </div>
        </div>
        <div class="layout-overlay layout-menu-toggle"></div>
    </div>

    <div class="modal fade" id="modalPagamento" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Registrar Pagamento</h5><button class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Cliente</label><select class="form-select">
                                <option>João da Silva</option>
                                <option>Maria Oliveira</option>
                            </select></div>
                        <div class="col-md-6"><label class="form-label">Valor pago</label><input class="form-control"
                                value="R$ 89,90" /></div>
                        <div class="col-md-6"><label class="form-label">Forma</label><select class="form-select">
                                <option>PIX</option>
                                <option>Dinheiro</option>
                                <option>Transferência</option>
                            </select></div>
                        <div class="col-12"><label class="form-label">Observações</label><textarea class="form-control"
                                rows="3"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary">Salvar Pagamento</button>
                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="modalComprovante" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Ler Comprovante</h5><button class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label class="form-label">Selecionar comprovante</label>
                    <input type="file" class="form-control" />
                    <div class="alert alert-info mt-3 mb-0">Depois você pode ligar essa tela à leitura automática de
                        comprovantes.</div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
                    <button class="btn btn-primary">Analisar</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../assets/vendor/libs/jquery/jquery.js"></script>
    <script src="../assets/vendor/libs/popper/popper.js"></script>
    <script src="../assets/vendor/js/bootstrap.js"></script>
    <script src="../assets/vendor/libs/perfect-scrollbar/perfect-scrollbar.js"></script>
    <script src="../assets/vendor/js/menu.js"></script>
    <script src="../assets/js/main.js"></script>
</body>

</html>