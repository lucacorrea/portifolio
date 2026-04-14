<!doctype html>
<html lang="pt-BR" class="layout-menu-fixed layout-compact" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tático GPS - Cobranças</title>

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

        .metric-value {
            font-size: 1.9rem;
            font-weight: 700;
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
           <?php $paginaAtiva = 'cobrancas'; ?>
            <?php require_once __DIR__ . '/includes/menu.php'; ?>

            <div class="layout-page">
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card page-banner">
                                    <div class="card-body">
                                        <h3 class="text-primary">Cobranças</h3>
                                        <p>Controle as mensalidades geradas, vencimentos, atrasos e status da carteira.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Cobranças do mês</div>
                                        <div class="metric-value">248</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Em aberto</div>
                                        <div class="metric-value text-warning">37</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Vencidas</div>
                                        <div class="metric-value text-danger">18</div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Pagas</div>
                                        <div class="metric-value text-success">193</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                                <h5 class="mb-0">Lista de Cobranças</h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#modalNovaCobranca"><i class="bx bx-plus me-1"></i>Nova
                                        Cobrança</button>
                                    <select class="form-select" style="width:180px">
                                        <option>Todos os status</option>
                                        <option>Em aberto</option>
                                        <option>Paga</option>
                                        <option>Vencida</option>
                                    </select>
                                    <input class="form-control" style="width:240px" placeholder="Buscar cobrança..." />
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>Referência</th>
                                                <th>Valor</th>
                                                <th>Vencimento</th>
                                                <th>Status</th>
                                                <th>Atraso</th>
                                                <th class="text-center">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>João da Silva</td>
                                                <td>Mensalidade - Maio/2026</td>
                                                <td>R$ 89,90</td>
                                                <td>10/05/2026</td>
                                                <td><span class="badge bg-label-warning">Em aberto</span></td>
                                                <td>0 dias</td>
                                                <td class="text-center"><button
                                                        class="btn btn-sm btn-outline-primary">Cobrar</button></td>
                                            </tr>
                                            <tr>
                                                <td>Maria Oliveira</td>
                                                <td>Mensalidade - Maio/2026</td>
                                                <td>R$ 119,90</td>
                                                <td>15/05/2026</td>
                                                <td><span class="badge bg-label-danger">Vencida</span></td>
                                                <td>4 dias</td>
                                                <td class="text-center"><button
                                                        class="btn btn-sm btn-outline-primary">Cobrar</button></td>
                                            </tr>
                                            <tr>
                                                <td>Ana Souza</td>
                                                <td>Mensalidade - Maio/2026</td>
                                                <td>R$ 99,90</td>
                                                <td>05/05/2026</td>
                                                <td><span class="badge bg-label-success">Paga</span></td>
                                                <td>-</td>
                                                <td class="text-center"><button
                                                        class="btn btn-sm btn-outline-secondary">Ver</button></td>
                                            </tr>
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

    <div class="modal fade" id="modalNovaCobranca" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Nova Cobrança</h5><button class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Cliente</label><select class="form-select">
                                <option>João da Silva</option>
                                <option>Maria Oliveira</option>
                            </select></div>
                        <div class="col-md-6"><label class="form-label">Referência</label><input class="form-control"
                                value="Mensalidade - Maio/2026" /></div>
                        <div class="col-md-4"><label class="form-label">Valor</label><input class="form-control"
                                value="R$ 89,90" /></div>
                        <div class="col-md-4"><label class="form-label">Vencimento</label><input type="date"
                                class="form-control" /></div>
                        <div class="col-md-4"><label class="form-label">Status</label><select class="form-select">
                                <option selected>Em aberto</option>
                                <option>Paga</option>
                                <option>Vencida</option>
                            </select></div>
                        <div class="col-12"><label class="form-label">Observações</label><textarea class="form-control"
                                rows="3"></textarea></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary">Salvar Cobrança</button>
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