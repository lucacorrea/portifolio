<?php
declare(strict_types=1);

require_once __DIR__ . '/auth/guard.php';
require_once __DIR__ . '/auth/csrf.php';
whatsapp_auth_guard();

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/services/EmpregoCentralService.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
  echo "<div class='alert alert-danger'>Erro: conexão com o banco não encontrada.</div>";
  exit;
}

$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

function wpe_page_e(?string $value): string
{
  return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

$bairros = [];
$beneficiosEmprego = [];
$anosDisponiveis = [];
$csrf = whatsapp_csrf_token();

try {
  $bairros = $pdo->query("SELECT id, nome FROM bairros ORDER BY nome ASC")->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {
  $bairros = [];
}

try {
  $service = new EmpregoCentralService($pdo);
  $service->ensureSchema();
  $beneficiosEmprego = $service->findEmploymentTypes()['tipos'] ?? [];
  $anosDisponiveis = $pdo->query("SELECT DISTINCT YEAR(data_solicitacao) AS ano FROM solicitacoes WHERE data_solicitacao IS NOT NULL ORDER BY ano DESC")->fetchAll(PDO::FETCH_COLUMN) ?: [];
} catch (Throwable $e) {
  $beneficiosEmprego = [];
  $anosDisponiveis = [];
}

if (!$anosDisponiveis) {
  $anosDisponiveis = [date('Y')];
}
?>
<!DOCTYPE html>
<html lang="pt-br">

<head>
  <meta charset="utf-8" />
  <title>Central de Comunicação e Atualização Cadastral - SEMAS</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />

  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="../dist/assets/css/bootstrap.css">
  <link rel="stylesheet" href="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.css">
  <link rel="stylesheet" href="../dist/assets/vendors/bootstrap-icons/bootstrap-icons.css">
  <link rel="stylesheet" href="../dist/assets/css/app.css">
  <link rel="shortcut icon" href="../dist/assets/images/logo/logo_pmc_2025.jpg">

  <style>
    :root {
      --card-radius: 14px;
    }

    body {
      font-family: 'Inter', system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: #f2f7ff;
    }

    .card {
      border: 0;
      border-radius: var(--card-radius);
      box-shadow: 0 1px 2px rgba(16, 24, 40, .06), 0 1px 3px rgba(16, 24, 40, .1);
    }

    .wpe-hero {
      background: linear-gradient(135deg, #25396f, #435ebe);
      color: #fff;
      border-radius: 18px;
      padding: 1.35rem;
    }

    .wpe-status-dot {
      width: .85rem;
      height: .85rem;
      display: inline-block;
      border-radius: 50%;
      background: #94a3b8;
    }

    .wpe-status-dot.online {
      background: #22c55e;
    }

    .wpe-status-dot.waiting {
      background: #f59e0b;
    }

    .wpe-status-dot.offline {
      background: #ef4444;
    }

    .wpe-kpi {
      border: 1px solid #edf0f7;
      border-radius: .8rem;
      background: #fff;
      padding: 1rem;
      min-height: 104px;
    }

    .wpe-kpi .icon {
      color: #435ebe;
      font-size: 1.25rem;
    }

    .wpe-kpi .value {
      color: #25396f;
      font-size: 1.45rem;
      font-weight: 800;
      line-height: 1;
    }

    .wpe-kpi .label {
      color: #607080;
      font-size: .82rem;
    }

    .nav-pills .nav-link {
      color: #435ebe;
      border-radius: .55rem;
      font-weight: 700;
    }

    .nav-pills .nav-link.active {
      background: #435ebe;
    }

    .wpe-table-wrap {
      max-height: 560px;
      overflow: auto;
    }

    .wpe-table-wrap thead th {
      position: sticky;
      top: 0;
      z-index: 2;
    }

    .wpe-text-truncate {
      max-width: 260px;
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    .wpe-message {
      max-width: 82%;
      border-radius: .8rem;
      padding: .8rem .95rem;
      background: #f1f5f9;
      white-space: pre-wrap;
    }

    .wpe-message.saida {
      margin-left: auto;
      background: #e0f2fe;
    }

    .wpe-message.entrada {
      margin-right: auto;
      background: #ecfdf5;
    }

    .chart-wrap {
      height: 280px;
      position: relative;
    }

    @media(max-width: 576.98px) {
      .wpe-hero {
        padding: 1rem;
      }

      .wpe-table-wrap {
        max-height: none;
      }
    }
  </style>
</head>

<body>
  <div id="app">
    <div id="sidebar" class="active">
      <div class="sidebar-wrapper active">
        <div class="sidebar-header">
          <div class="d-flex justify-content-between">
            <div class="logo"><a href="dashboard.php"><img src="../dist/assets/images/logo/logo_pmc_2025.jpg" alt="Logo"></a></div>
            <div class="toggler"><a href="#" class="sidebar-hide d-xl-none d-block"><i class="bi bi-x bi-middle"></i></a></div>
          </div>
        </div>

        <div class="sidebar-menu">
          <ul class="menu">
            <li class="sidebar-title">SEMAS Coari</li>
            <li class="sidebar-item active"><a href="dashboard.php" class="sidebar-link"><i class="bi bi-grid-fill"></i><span>Visão Geral</span></a></li>
            <li class="sidebar-item"><a href="conexao.php" class="sidebar-link"><i class="bi bi-phone"></i><span>Conexão WhatsApp</span></a></li>
            <li class="sidebar-item"><a href="pessoas.php" class="sidebar-link"><i class="bi bi-people"></i><span>Selecionar Pessoas</span></a></li>
            <li class="sidebar-item"><a href="campanhas.php" class="sidebar-link"><i class="bi bi-megaphone"></i><span>Campanhas</span></a></li>
            <li class="sidebar-item"><a href="conversas.php" class="sidebar-link"><i class="bi bi-chat-dots"></i><span>Conversas</span></a></li>
            <li class="sidebar-item"><a href="revisoes.php" class="sidebar-link"><i class="bi bi-clipboard-check"></i><span>Revisões</span></a></li>
            <li class="sidebar-item"><a href="configuracoes.php" class="sidebar-link"><i class="bi bi-gear"></i><span>Configurações</span></a></li>
            <li class="sidebar-item"><a href="logout.php" class="sidebar-link"><i class="bi bi-box-arrow-right"></i><span>Sair</span></a></li>
          </ul>
        </div>
        <button class="sidebar-toggler btn x"><i data-feather="x"></i></button>
      </div>
    </div>

    <div id="main" class="d-flex flex-column min-vh-100">
      <header class="mb-3">
        <a href="#" class="burger-btn d-block d-xl-none" aria-label="Alternar menu"><i class="bi bi-justify fs-3"></i></a>
      </header>

      <div class="page-heading">
        <section class="wpe-hero mb-4">
          <div class="row align-items-center g-3">
            <div class="col-lg-7">
              <h3 class="text-white mb-1">Central de Comunicação e Atualização Cadastral</h3>
              <p class="mb-0 opacity-75">Contato, respostas e atualização de interesse profissional por WhatsApp</p>
            </div>
            <div class="col-lg-5">
              <div class="bg-white bg-opacity-10 rounded-3 p-3">
                <div class="d-flex align-items-center justify-content-between gap-3">
                  <div>
                    <div class="small opacity-75">Conexão WhatsApp</div>
                    <div class="fw-bold"><span id="wpeStatusDot" class="wpe-status-dot me-2"></span><span id="wpeStatusText">Verificando...</span></div>
                    <div class="small opacity-75" id="wpeStatusDate">Última verificação: --</div>
                  </div>
                  <button class="btn btn-light btn-sm" type="button" id="wpeBtnStatus"><i class="bi bi-arrow-repeat me-1"></i> Atualizar status</button>
                </div>
              </div>
            </div>
          </div>
        </section>

        <section class="section">
          <div class="card">
            <div class="card-header d-flex flex-column flex-lg-row gap-2 justify-content-between align-items-lg-center">
              <div>
                <span class="fw-semibold">Filtros da Central</span>
                <div class="text-muted small">O envio de campanha é liberado somente para cadastros relacionados a Emprego.</div>
              </div>
              <div class="d-flex gap-2 flex-wrap">
                <button type="button" class="btn btn-outline-secondary" id="wpeBtnLimpar"><i class="bi bi-arrow-counterclockwise me-1"></i> Limpar</button>
                <button type="button" class="btn btn-primary" id="wpeBtnNovaCampanha"><i class="bi bi-megaphone me-1"></i> Nova campanha</button>
              </div>
            </div>
            <div class="card-body">
              <form id="wpeFiltros" class="row g-3">
                <input type="hidden" id="wpeCsrf" value="<?= wpe_page_e($csrf) ?>">
                <div class="col-md-2">
                  <label class="form-label" for="wpeMes">Mês</label>
                  <select id="wpeMes" class="form-select">
                    <?php
                    $meses = [1=>'Janeiro',2=>'Fevereiro',3=>'Março',4=>'Abril',5=>'Maio',6=>'Junho',7=>'Julho',8=>'Agosto',9=>'Setembro',10=>'Outubro',11=>'Novembro',12=>'Dezembro'];
                    foreach ($meses as $numero => $nome):
                    ?>
                      <option value="<?= $numero ?>" <?= ((int)date('n') === $numero) ? 'selected' : '' ?>><?= wpe_page_e($nome) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label" for="wpeAno">Ano</label>
                  <select id="wpeAno" class="form-select">
                    <?php foreach ($anosDisponiveis as $ano): ?>
                      <option value="<?= (int)$ano ?>" <?= ((int)date('Y') === (int)$ano) ? 'selected' : '' ?>><?= (int)$ano ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="wpeBairro">Bairro</label>
                  <select id="wpeBairro" class="form-select">
                    <option value="">Todos</option>
                    <?php foreach ($bairros as $bairro): ?>
                      <option value="<?= (int)$bairro['id'] ?>"><?= wpe_page_e((string)$bairro['nome']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="col-md-2">
                  <label class="form-label" for="wpeSexo">Sexo</label>
                  <select id="wpeSexo" class="form-select">
                    <option value="">Todos</option>
                    <option value="Feminino">Feminino</option>
                    <option value="Masculino">Masculino</option>
                    <option value="Outro">Outro</option>
                  </select>
                </div>
                <div class="col-md-3">
                  <label class="form-label" for="wpeBusca">Busca por nome, CPF ou telefone</label>
                  <input type="search" id="wpeBusca" class="form-control" placeholder="Digite para buscar">
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="wpeTipoEmprego">Tipo de benefício</label>
                  <select id="wpeTipoEmprego" class="form-select">
                    <?php if (!$beneficiosEmprego): ?>
                      <option value="">Emprego não encontrado em ajudas_tipos</option>
                    <?php else: ?>
                      <?php foreach ($beneficiosEmprego as $tipo): ?>
                        <option value="<?= (int)$tipo['id'] ?>"><?= wpe_page_e((string)$tipo['nome']) ?></option>
                      <?php endforeach; ?>
                    <?php endif; ?>
                  </select>
                </div>
                <div class="col-md-4">
                  <label class="form-label" for="wpeCampanhaFiltro">Campanha</label>
                  <select id="wpeCampanhaFiltro" class="form-select">
                    <option value="">Todas</option>
                  </select>
                </div>
              </form>
            </div>
          </div>
        </section>

        <section class="section">
          <ul class="nav nav-pills gap-2 mb-3" id="wpeTabs" role="tablist">
            <li class="nav-item"><button class="nav-link active" data-bs-toggle="pill" data-bs-target="#wpeGeral" type="button">Visão geral</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#wpePessoas" type="button">Pessoas filtradas</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#wpeCampanhas" type="button">Campanhas</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#wpeConversas" type="button">Conversas</button></li>
            <li class="nav-item"><button class="nav-link" data-bs-toggle="pill" data-bs-target="#wpeRevisoes" type="button">Revisões pendentes</button></li>
          </ul>

          <div class="tab-content">
            <div class="tab-pane fade show active" id="wpeGeral">
              <div class="row g-3 mb-3" id="wpeKpis"></div>
              <div class="row g-3">
                <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Funil da campanha</div><div class="card-body"><div class="chart-wrap"><canvas id="wpeChartFunil"></canvas></div></div></div></div>
                <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Profissões informadas</div><div class="card-body"><div class="chart-wrap"><canvas id="wpeChartProfissoes"></canvas></div></div></div></div>
                <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Respostas por período</div><div class="card-body"><div class="chart-wrap"><canvas id="wpeChartPeriodo"></canvas></div></div></div></div>
                <div class="col-lg-6"><div class="card h-100"><div class="card-header fw-semibold">Situação das conversas</div><div class="card-body"><div class="chart-wrap"><canvas id="wpeChartConversas"></canvas></div></div></div></div>
              </div>
            </div>

            <div class="tab-pane fade" id="wpePessoas">
              <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                  <span class="fw-semibold">Pessoas relacionadas a Emprego</span>
                  <div class="d-flex gap-2 flex-wrap">
                    <button class="btn btn-sm btn-outline-primary" id="wpeSelecionarPagina" type="button">Selecionar esta página</button>
                    <button class="btn btn-sm btn-outline-primary" id="wpeSelecionarTodos" type="button">Selecionar todos filtrados</button>
                  </div>
                </div>
                <div class="card-body">
                  <div class="alert alert-light border" id="wpeSelecaoInfo">Nenhuma pessoa selecionada.</div>
                  <div class="table-responsive wpe-table-wrap">
                    <table class="table table-striped table-hover align-middle text-nowrap">
                      <thead class="table-light">
                        <tr>
                          <th></th><th>Nome</th><th>CPF</th><th>Telefone</th><th>Bairro</th><th>Data</th><th>Tipo</th><th>Trabalho</th><th>Resumo</th><th>Profissão</th><th>Campanha</th><th>Status</th><th>Última resposta</th><th>Atualização</th><th>Ações</th>
                        </tr>
                      </thead>
                      <tbody id="wpePessoasBody"></tbody>
                    </table>
                  </div>
                  <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mt-3">
                    <button class="btn btn-outline-secondary btn-sm" id="wpePrev" type="button">Anterior</button>
                    <strong id="wpePageLabel">Página 1 de 1</strong>
                    <button class="btn btn-outline-secondary btn-sm" id="wpeNext" type="button">Próxima</button>
                  </div>
                </div>
              </div>
            </div>

            <div class="tab-pane fade" id="wpeCampanhas">
              <div class="card">
                <div class="card-header fw-semibold">Campanhas</div>
                <div class="card-body"><div class="table-responsive"><table class="table table-striped align-middle"><thead><tr><th>ID</th><th>Título</th><th>Status</th><th>Destinatários</th><th>Fila</th><th>Enviados</th><th>Falhas</th><th>Ações</th></tr></thead><tbody id="wpeCampanhasBody"></tbody></table></div></div>
              </div>
            </div>

            <div class="tab-pane fade" id="wpeConversas">
              <div class="card"><div class="card-body text-muted">Selecione “Ver conversa” em uma pessoa para abrir o histórico completo.</div></div>
            </div>

            <div class="tab-pane fade" id="wpeRevisoes">
              <div class="card"><div class="card-body text-muted">As respostas ambíguas e telefones compartilhados aparecem na conversa e ficam marcados para revisão.</div></div>
            </div>
          </div>
        </section>
      </div>

      <footer>
        <div class="footer clearfix mb-0 text-muted">
          <div class="float-start text-black"><p><span id="current-year"></span> &copy; Todos os direitos reservados à <b>Prefeitura Municipal de Coari-AM.</b></p></div>
          <div class="float-end text-black"><p>Desenvolvido por <b>Junior Praia, Lucas Correa e Luiz Frota.</b></p></div>
        </div>
      </footer>
    </div>
  </div>

  <div class="modal fade" id="wpeCampanhaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Nova campanha profissional</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body">
          <div class="row g-3">
            <div class="col-lg-4"><div class="card border"><div class="card-body"><h6>Público</h6><div id="wpeCampanhaResumo" class="text-muted small">Carregando público filtrado...</div></div></div></div>
            <div class="col-lg-8">
              <label class="form-label" for="wpeTituloCampanha">Título</label>
              <input id="wpeTituloCampanha" class="form-control mb-3" value="Atualização profissional">
              <label class="form-label" for="wpeMensagemCampanha">Mensagem institucional</label>
              <textarea id="wpeMensagemCampanha" class="form-control" rows="14"></textarea>
              <div class="form-text">O aviso de que não há garantia de contratação não pode ser removido.</div>
            </div>
          </div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" type="button" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-primary" type="button" id="wpeConfirmarCampanha">Confirmar campanha</button></div>
      </div>
    </div>
  </div>

  <div class="modal fade" id="wpeConversaModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
      <div class="modal-content">
        <div class="modal-header"><h5 class="modal-title" id="wpeConversaTitulo">Conversa</h5><button class="btn-close" type="button" data-bs-dismiss="modal" aria-label="Fechar"></button></div>
        <div class="modal-body"><div id="wpeConversaConteudo" class="d-grid gap-3"></div></div>
      </div>
    </div>
  </div>

  <script src="../dist/assets/vendors/perfect-scrollbar/perfect-scrollbar.min.js"></script>
  <script src="../dist/assets/js/bootstrap.bundle.min.js"></script>
  <script src="../dist/assets/js/main.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
  <script src="assets/js/whatsapp-emprego-dashboard.js"></script>
</body>

</html>
