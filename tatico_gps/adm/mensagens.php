<?php
require_once __DIR__ . '/php/conexao.php';
require_once __DIR__ . '/php/whatsapp/functions.php';

function h($str) { return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8'); }
?>
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
                            <?php
                            $hj = date('Y-m-d');
                            $enviadasHj = $pdo->query("SELECT COUNT(*) FROM whatsapp_envios WHERE DATE(criado_em) = '$hj' AND status_envio = 'enviado'")->fetchColumn();
                            $pendentes = $pdo->query("SELECT COUNT(*) FROM whatsapp_envios WHERE status_envio = 'pendente'")->fetchColumn();
                            $falhas = $pdo->query("SELECT COUNT(*) FROM whatsapp_envios WHERE status_envio = 'falhou'")->fetchColumn();
                            $bloqueios = $pdo->query("SELECT COUNT(*) FROM whatsapp_envios WHERE resposta_api LIKE '%regra: atraso_bloqueio%'")->fetchColumn();
                            ?>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Enviadas hoje</div>
                                        <h2 class="mb-0"><?= $enviadasHj ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Pendentes</div>
                                        <h2 class="mb-0 text-warning"><?= $pendentes ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Falhas</div>
                                        <h2 class="mb-0 text-danger"><?= $falhas ?></h2>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="text-muted">Regras de Bloqueio</div>
                                        <h2 class="mb-0"><?= $bloqueios ?></h2>
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
                                            <?php
                                            $stmtMsg = $pdo->query("
                                                SELECT w.*, c.nome as cliente_nome, c.mensalidade
                                                FROM whatsapp_envios w
                                                LEFT JOIN clientes c ON w.cliente_id = c.id
                                                ORDER BY w.criado_em DESC
                                                LIMIT 50
                                            ");
                                            $envios = $stmtMsg->fetchAll();

                                            if (count($envios) > 0):
                                                foreach ($envios as $env):
                                                    $badgeClass = 'bg-label-secondary';
                                                    if ($env['status_envio'] === 'enviado') $badgeClass = 'bg-label-success';
                                                    if ($env['status_envio'] === 'falhou') $badgeClass = 'bg-label-danger';
                                                    
                                                    // Extrair tipo da regra da resposta_api se existir
                                                    $tipo = 'Outro';
                                                    if (preg_match('/Regra: ([^|]+)/', $env['resposta_api'] ?? '', $matches)) {
                                                        $tipo = str_replace('_', ' ', $matches[1]);
                                                    }
                                            ?>
                                            <tr>
                                                <td><?= h($env['cliente_nome'] ?? $env['telefone']) ?></td>
                                                <td class="text-capitalize"><?= h($tipo) ?></td>
                                                <td><?= date('d/m/Y H:i', strtotime($env['criado_em'])) ?></td>
                                                <td>WhatsApp</td>
                                                <td><span class="badge <?= $badgeClass ?>"><?= ucfirst(h($env['status_envio'])) ?></span></td>
                                                <td>R$ <?= number_format((float)($env['mensalidade'] ?? 0), 2, ',', '.') ?></td>
                                                <td class="text-center">
                                                    <button class="btn btn-sm btn-outline-primary btn-ver-msg" 
                                                        data-msg="<?= h($env['mensagem']) ?>"
                                                        data-cliente="<?= h($env['cliente_nome'] ?? $env['telefone']) ?>"
                                                        data-data="<?= date('d/m/Y H:i', strtotime($env['criado_em'])) ?>">
                                                        Ver
                                                    </button>
                                                </td>
                                            </tr>
                                            <?php 
                                                endforeach;
                                            else:
                                            ?>
                                            <tr>
                                                <td colspan="7" class="text-center">Histórico de mensagens vazio.</td>
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

    <!-- Modal Enviar Mensagem -->
    <div class="modal fade" id="modalMensagem" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form id="formEnviarManual">
                    <div class="modal-header">
                        <h5 class="modal-title">Enviar Mensagem Manual</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Cliente</label>
                                <select name="cliente_id" id="manual_cliente_id" class="form-select" required>
                                    <option value="">Selecione um cliente...</option>
                                    <?php
                                    $stmtCl = $pdo->query("SELECT id, nome, telefone, whatsapp_principal FROM clientes WHERE status = 'Ativo' ORDER BY nome ASC");
                                    while($cl = $stmtCl->fetch()):
                                        $tel = $cl['whatsapp_principal'] ?: $cl['telefone'];
                                    ?>
                                        <option value="<?= $cl['id'] ?>" data-tel="<?= h($tel) ?>"><?= h($cl['nome']) ?> (<?= h($tel) ?>)</option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Tipo de Mensagem</label>
                                <select name="tipo" id="manual_tipo" class="form-select">
                                    <option value="manual">Manual / Livre</option>
                                    <option value="cobranca">Cobrança Direta</option>
                                    <option value="aviso">Aviso Geral</option>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Mensagem</label>
                                <textarea name="mensagem" id="manual_mensagem" class="form-control" rows="5" required></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                        <button type="submit" class="btn btn-primary" id="btnEnviarManual">
                            <i class="bx bx-send me-1"></i> Enviar Agora
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modal Ver Detalhes -->
    <div class="modal fade" id="modalVer" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Conteúdo da Mensagem</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <strong>Cliente:</strong> <span id="ver_cliente">-</span><br>
                        <strong>Data:</strong> <span id="ver_data">-</span>
                    </div>
                    <div class="p-3 bg-light rounded border" id="ver_texto" style="white-space: pre-wrap;">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Fechar</button>
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

    <script>
        $(document).ready(function() {
            // Ver detalhes da mensagem (ID modalVer)
            $(document).on('click', '.btn-ver-msg', function() {
                const msg = $(this).data('msg');
                const cliente = $(this).data('cliente');
                const data = $(this).data('data');

                $('#ver_cliente').text(cliente);
                $('#ver_data').text(data);
                $('#ver_texto').text(msg);
                
                const myModal = new bootstrap.Modal(document.getElementById('modalVer'));
                myModal.show();
            });

            // Enviar mensagem manualmente
            $('#formEnviarManual').on('submit', function(e) {
                e.preventDefault();
                
                const btn = $('#btnEnviarManual');
                const originalText = btn.html();
                
                btn.prop('disabled', true).html('<span class="spinner-border spinner-border-sm me-1"></span> Enviando...');

                $.ajax({
                    url: 'php/whatsapp/enviar_manual.php',
                    method: 'POST',
                    data: $(this).serialize(),
                    dataType: 'json',
                    success: function(res) {
                        if (res.ok) {
                            alert('Mensagem enviada com sucesso!');
                            location.reload();
                        } else {
                            alert('Erro: ' + (res.error || 'Falha ao enviar.'));
                            btn.prop('disabled', false).html(originalText);
                        }
                    },
                    error: function() {
                        alert('Erro na comunicação com o servidor. Verifique sua conexão.');
                        btn.prop('disabled', false).html(originalText);
                    }
                });
            });
        });
    </script>
</body>
</html>