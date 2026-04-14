<!doctype html>
<html lang="pt-BR" class="layout-menu-fixed layout-compact" data-assets-path="../assets/"
    data-template="vertical-menu-template-free">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Tático GPS - Mensagens</title>

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
           <?php $paginaAtiva = 'mensagens'; ?>
            <?php require_once __DIR__ . '/includes/menu.php'; ?>

            <div class="layout-page">
                <div class="content-wrapper">
                    <div class="container-xxl flex-grow-1 container-p-y">
                        <div class="row mb-4">
                            <div class="col-12">
                                <div class="card page-banner">
                                    <div class="card-body">
                                        <h3 class="text-primary">Mensagens</h3>
                                        <p>Acompanhe envios, status e disparos manuais de mensagens de cobrança.</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="row g-4 mb-4">
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Enviadas hoje</div>
                                        <h2 class="mb-0">46</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Pendentes</div>
                                        <h2 class="mb-0 text-warning">12</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Falhas</div>
                                        <h2 class="mb-0 text-danger">3</h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Bloqueio enviado</div>
                                        <h2 class="mb-0">7</h2>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header d-flex flex-wrap justify-content-between gap-2 align-items-center">
                                <h5 class="mb-0">Histórico de Mensagens</h5>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button class="btn btn-primary" data-bs-toggle="modal"
                                        data-bs-target="#modalMensagem"><i class="bx bx-send me-1"></i>Enviar
                                        Mensagem</button>
                                    <select class="form-select" style="width:190px">
                                        <option>Todos os tipos</option>
                                        <option>10 dias antes</option>
                                        <option>7 dias antes</option>
                                        <option>5 dias antes</option>
                                        <option>3 dias antes</option>
                                        <option>Bloqueio</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover align-middle">
                                        <thead>
                                            <tr>
                                                <th>Cliente</th>
                                                <th>Tipo</th>
                                                <th>Data/Hora</th>
                                                <th>Canal</th>
                                                <th>Status</th>
                                                <th>Valor</th>
                                                <th class="text-center">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>João da Silva</td>
                                                <td>7 dias antes</td>
                                                <td>08/05/2026 09:12</td>
                                                <td>WhatsApp</td>
                                                <td><span class="badge bg-label-success">Enviada</span></td>
                                                <td>R$ 89,90</td>
                                                <td class="text-center"><button
                                                        class="btn btn-sm btn-outline-primary">Reenviar</button></td>
                                            </tr>
                                            <tr>
                                                <td>Maria Oliveira</td>
                                                <td>Bloqueio</td>
                                                <td>15/05/2026 08:47</td>
                                                <td>WhatsApp</td>
                                                <td><span class="badge bg-label-warning">Pendente</span></td>
                                                <td>R$ 119,90</td>
                                                <td class="text-center"><button
                                                        class="btn btn-sm btn-outline-primary">Reenviar</button></td>
                                            </tr>
                                            <tr>
                                                <td>Carlos Mendes</td>
                                                <td>3 dias antes</td>
                                                <td>13/05/2026 10:20</td>
                                                <td>SMS</td>
                                                <td><span class="badge bg-label-danger">Falhou</span></td>
                                                <td>R$ 149,90</td>
                                                <td class="text-center"><button
                                                        class="btn btn-sm btn-outline-primary">Tentar de novo</button>
                                                </td>
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

    <div class="modal fade" id="modalMensagem" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Enviar Mensagem</h5><button class="btn-close"
                        data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Cliente</label><select class="form-select">
                                <option>João da Silva</option>
                                <option>Maria Oliveira</option>
                            </select></div>
                        <div class="col-md-6"><label class="form-label">Tipo</label><select class="form-select">
                                <option>10 dias antes</option>
                                <option>7 dias antes</option>
                                <option>5 dias antes</option>
                                <option>3 dias antes</option>
                                <option>Bloqueio</option>
                                <option>Manual</option>
                            </select></div>
                        <div class="col-12"><label class="form-label">Mensagem</label><textarea class="form-control"
                                rows="5">Olá, sua mensalidade do Tático GPS está próxima do vencimento. Segue a chave PIX para pagamento.</textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button class="btn btn-primary">Enviar Agora</button>
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