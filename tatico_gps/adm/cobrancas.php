<?php
require_once __DIR__ . '/php/conexao.php';

function h($str)
{
  return htmlspecialchars($str ?? '', ENT_QUOTES, 'UTF-8');
}
?>
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
              <?php
              $refAtual = date('m/Y');
              $totalMes = $pdo->query("SELECT COUNT(*) FROM cobrancas WHERE referencia = '$refAtual'")->fetchColumn();
              $abertas = $pdo->query("SELECT COUNT(*) FROM cobrancas WHERE status = 'Em aberto'")->fetchColumn();
              $vencidas = $pdo->query("SELECT COUNT(*) FROM cobrancas WHERE status = 'Em aberto' AND data_vencimento < CURDATE()")->fetchColumn();
              $pagas = $pdo->query("SELECT COUNT(*) FROM cobrancas WHERE status = 'Paga'")->fetchColumn();
              ?>
              <div class="col-md-3">
                <div class="card">
                  <div class="card-body">
                    <div class="text-muted">Cobranças do mês</div>
                    <div class="metric-value"><?= $totalMes ?></div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card">
                  <div class="card-body">
                    <div class="text-muted">Em aberto</div>
                    <div class="metric-value text-warning"><?= $abertas ?></div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card">
                  <div class="card-body">
                    <div class="text-muted">Vencidas</div>
                    <div class="metric-value text-danger"><?= $vencidas ?></div>
                  </div>
                </div>
              </div>
              <div class="col-md-3">
                <div class="card">
                  <div class="card-body">
                    <div class="text-muted">Pagas</div>
                    <div class="metric-value text-success"><?= $pagas ?></div>
                  </div>
                </div>
              </div>
            </div>

            <div class="card">
              <div class="card-header d-flex flex-wrap gap-2 justify-content-between align-items-center">
                <h5 class="mb-0">Lista de Cobranças</h5>
                <div class="d-flex gap-2 flex-wrap">
                  <button class="btn btn-outline-info" id="btnGerarLote"><i class="bx bx-sync me-1"></i>Gerar Lote (Mês Atual)</button>
                  <button class="btn btn-primary" data-bs-toggle="modal"
                    data-bs-target="#modalNovaCobranca"><i class="bx bx-plus me-1"></i>Previsão Individual</button>
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
                      <?php
                      $stmtCob = $pdo->query("
                                                SELECT cb.*, cl.nome as cliente_nome
                                                FROM cobrancas cb
                                                JOIN clientes cl ON cb.cliente_id = cl.id
                                                ORDER BY cb.data_vencimento DESC
                                            ");
                      $cobrancas = $stmtCob->fetchAll();

                      if (count($cobrancas) > 0):
                        foreach ($cobrancas as $cob):
                          $status = $cob['status'];
                          $hoje = date('Y-m-d');
                          if ($status === 'Em aberto' && $cob['data_vencimento'] < $hoje) {
                            $status = 'Vencida';
                          }

                          $badgeClass = 'bg-label-warning';
                          if ($status === 'Paga') $badgeClass = 'bg-label-success';
                          if ($status === 'Vencida' || $status === 'Vencida') $badgeClass = 'bg-label-danger';

                          $atraso = 0;
                          if ($status === 'Vencida') {
                            $d1 = new DateTime($cob['data_vencimento']);
                            $d2 = new DateTime($hoje);
                            $diff = $d1->diff($d2);
                            $atraso = $diff->days;
                          }
                      ?>
                          <tr>
                            <td><?= h($cob['cliente_nome']) ?></td>
                            <td><?= h($cob['referencia']) ?></td>
                            <td>R$ <?= number_format($cob['valor'], 2, ',', '.') ?></td>
                            <td><?= date('d/m/Y', strtotime($cob['data_vencimento'])) ?></td>
                            <td><span class="badge <?= $badgeClass ?>"><?= h($status) ?></span></td>
                            <td><?= $atraso > 0 ? $atraso . ' dias' : '-' ?></td>
                            <td class="text-center">
                              <button class="btn btn-sm btn-outline-primary">Ver Histórico</button>
                            </td>
                          </tr>
                        <?php
                        endforeach;
                      else:
                        ?>
                        <tr>
                          <td colspan="7" class="text-center">Nenhuma cobrança encontrada.</td>
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

  <div class="modal fade" id="modalNovaCobranca" tabindex="-1">
    <div class="modal-dialog modal-lg">
      <div class="modal-content">
        <form id="formNovaCobranca">
          <div class="modal-header">
            <h5 class="modal-title">Nova Previsão de Cobrança</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">Cliente</label>
                <select name="cliente_id" id="cad_cliente_id" class="form-select" required>
                  <option value="">Selecione um cliente...</option>
                  <?php
                  $stmtCl = $pdo->query("SELECT id, nome, mensalidade FROM clientes WHERE status = 'Ativo' ORDER BY nome ASC");
                  while ($cl = $stmtCl->fetch()):
                  ?>
                    <option value="<?= $cl['id'] ?>" data-valor="<?= $cl['mensalidade'] ?>"><?= h($cl['nome']) ?></option>
                  <?php endwhile; ?>
                </select>
              </div>
              <div class="col-md-6">
                <label class="form-label">Referência (Mês/Ano)</label>
                <input name="referencia" class="form-control" value="<?= date('m/Y') ?>" required />
              </div>
              <div class="col-md-4">
                <label class="form-label">Valor</label>
                <input name="valor" id="cad_valor" class="form-control" placeholder="0.00" required />
              </div>
              <div class="col-md-4">
                <label class="form-label">Vencimento</label>
                <input type="date" name="data_vencimento" class="form-control" value="<?= date('Y-m-d') ?>" required />
              </div>
              <div class="col-md-4">
                <label class="form-label">Status</label>
                <select name="status" class="form-select">
                  <option value="Em aberto" selected>Em aberto</option>
                  <option value="Paga">Paga</option>
                  <option value="Vencida">Vencida</option>
                </select>
              </div>
              <div class="col-12">
                <label class="form-label">Observações</label>
                <textarea name="observacoes" class="form-control" rows="3"></textarea>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
            <button type="submit" class="btn btn-primary" id="btnSalvarCobranca">Salvar Cobrança</button>
          </div>
        </form>
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
      // Auto preencher valor ao selecionar cliente
      $('#cad_cliente_id').on('change', function() {
        const valor = $(this).find(':selected').data('valor');
        if (valor) $('#cad_valor').val(valor);
      });

      // Salvar cobrança manual
      $('#formNovaCobranca').on('submit', function(e) {
        e.preventDefault();
        const btn = $('#btnSalvarCobranca');
        btn.prop('disabled', true).text('Salvando...');

        $.post('php/cobrancas/processarDados.php', $(this).serialize() + '&acao=salvar', function(res) {
          if (res.ok) {
            location.reload();
          } else {
            alert('Erro: ' + (res.error || 'Falha ao salvar.'));
            btn.prop('disabled', false).text('Salvar Cobrança');
          }
        }, 'json');
      });

      // Gerar Lote
      $('#btnGerarLote').on('click', function() {
        if (!confirm('Deseja gerar as cobranças de todos os clientes ativos para o mês atual?')) return;

        const btn = $(this);
        btn.prop('disabled', true).html('<i class="bx bx-loader-alt bx-spin me-1"></i> Processando...');

        $.post('php/cobrancas/processarDados.php', {
          acao: 'gerar_lote'
        }, function(res) {
          if (res.ok) {
            alert('Sucesso! Foram geradas ' + res.total + ' cobranças.');
            location.reload();
          } else {
            alert('Erro: ' + (res.error || 'Falha ao gerar lote.'));
            btn.prop('disabled', false).html('<i class="bx bx-sync me-1"></i>Gerar Lote (Mês Atual)');
          }
        }, 'json');
      });
    });
  </script>
</body>

</html>
