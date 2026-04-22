<?php
$pageTitle = 'Dashboard da Recepção | Sistema de Protocolo';
$cssPath = '../../../public/assets/css/recepcao-dashboard.css';

$kpis = [
    ['icon' => '🧾', 'iconClass' => 'icon-primary', 'value' => '38', 'label' => 'protocolos abertos hoje', 'trend' => '12 novos nesta manhã', 'trendClass' => 'success'],
    ['icon' => '👥', 'iconClass' => 'icon-teal', 'value' => '14', 'label' => 'clientes aguardando cadastro', 'trend' => 'fila sob controle', 'trendClass' => 'success'],
    ['icon' => '📨', 'iconClass' => 'icon-warning', 'value' => '9', 'label' => 'envios ao administrativo', 'trend' => '3 aguardando conferência', 'trendClass' => 'warning'],
    ['icon' => '⚠️', 'iconClass' => 'icon-danger', 'value' => '4', 'label' => 'cadastros com pendência', 'trend' => 'revisar documentos', 'trendClass' => 'danger'],
];

$protocolos = [
    ['cliente' => 'Maria Oliveira', 'servico' => 'Regularização documental', 'protocolo' => 'PROTO-2026-041', 'hora' => '08:10', 'status' => 'Novo cadastro', 'statusClass' => 'status-new'],
    ['cliente' => 'Carlos Mendes', 'servico' => 'Solicitação de orçamento técnico', 'protocolo' => 'PROTO-2026-042', 'hora' => '08:42', 'status' => 'Em triagem', 'statusClass' => 'status-progress'],
    ['cliente' => 'Raimunda Souza', 'servico' => 'Acompanhamento de serviço', 'protocolo' => 'PROTO-2026-043', 'hora' => '09:05', 'status' => 'Enviado ao adm.', 'statusClass' => 'status-sent'],
    ['cliente' => 'João Batista', 'servico' => 'Pedido de orçamento completo', 'protocolo' => 'PROTO-2026-044', 'hora' => '09:26', 'status' => 'Novo cadastro', 'statusClass' => 'status-new'],
];

$fluxos = [
    ['titulo' => 'Cadastros completos', 'percentual' => 86, 'descricao' => 'Atendimentos já registrados com dados principais, documentos e tipo de serviço.'],
    ['titulo' => 'Protocolos encaminhados', 'percentual' => 64, 'descricao' => 'Itens enviados para o administrativo seguir com análise e orçamento.'],
    ['titulo' => 'Retorno do orçamento', 'percentual' => 48, 'descricao' => 'Processos aguardando retorno do setor administrativo para posição ao cliente.'],
];

$timeline = [
    ['hora' => '08:15', 'periodo' => 'Hoje', 'titulo' => 'Abertura do protocolo PROTO-2026-041', 'descricao' => 'Cliente atendido na recepção com serviço de regularização documental.'],
    ['hora' => '08:50', 'periodo' => 'Hoje', 'titulo' => 'Envio ao administrativo', 'descricao' => 'Processo PROTO-2026-039 encaminhado para orçamento com prioridade média.'],
    ['hora' => '09:20', 'periodo' => 'Hoje', 'titulo' => 'Pendência identificada', 'descricao' => 'Cadastro sem documento principal anexado; contato solicitado ao cliente.'],
];

$pendencias = [
    ['titulo' => 'Documentos ausentes', 'texto' => '4 protocolos precisam de anexos ou conferência de CPF/CNPJ antes do envio.'],
    ['titulo' => 'Retorno do administrativo', 'texto' => '7 processos aguardam orçamento para devolutiva ao cliente na recepção.'],
    ['titulo' => 'Atendimento prioritário', 'texto' => '2 clientes marcados com urgência operacional para hoje à tarde.'],
];

require __DIR__ . '/../layouts/header.php';
?>
<div class="layout">
  <?php require __DIR__ . '/../layouts/sidebar-recepcao.php'; ?>

  <main class="main">
    <header class="topbar">
      <div class="search-box">
        <span class="search-icon">🔎</span>
        <input type="text" placeholder="Buscar cliente, protocolo, documento ou serviço...">
      </div>

      <div class="topbar-actions">
        <div class="badge-button">Recepção</div>
        <div class="badge-button">22/04/2026</div>
        <button class="primary-button" type="button">+ Novo protocolo</button>
      </div>
    </header>

    <section class="hero card">
      <div class="hero-grid">
        <div>
          <span class="hero-label">Fluxo de atendimento e protocolo</span>
          <h1>Dashboard da recepção com foco em cadastro, triagem e encaminhamento</h1>
          <p>
            Esta área foi pensada para a recepção trabalhar com agilidade e controle. Aqui entra o cliente, registra o tipo de serviço, valida os dados iniciais e encaminha o protocolo para o setor administrativo elaborar o orçamento. O dono poderá visualizar tudo em uma visão mais ampla com permissões totais.
          </p>

          <div class="hero-highlights">
            <div class="hero-highlight">
              <small>Atendimentos do dia</small>
              <strong>38</strong>
            </div>
            <div class="hero-highlight">
              <small>Encaminhados ao adm.</small>
              <strong>24</strong>
            </div>
            <div class="hero-highlight">
              <small>Tempo médio</small>
              <strong>11 min</strong>
            </div>
          </div>
        </div>

        <div class="hero-side">
          <h3>Prioridades da manhã</h3>
          <div class="hero-side-list">
            <div class="hero-side-item">
              <div>
                <strong>Conferir cadastros pendentes</strong>
                <small>4 protocolos aguardando documento ou ajuste cadastral.</small>
              </div>
              <span class="mini-status">Atenção</span>
            </div>
            <div class="hero-side-item">
              <div>
                <strong>Enviar novos protocolos</strong>
                <small>9 solicitações prontas para seguir ao administrativo.</small>
              </div>
              <span class="mini-status">Fila</span>
            </div>
            <div class="hero-side-item">
              <div>
                <strong>Atualizar clientes</strong>
                <small>7 processos já podem receber retorno de orçamento.</small>
              </div>
              <span class="mini-status">Retorno</span>
            </div>
          </div>
        </div>
      </div>
    </section>

    <section class="kpi-grid">
      <?php foreach ($kpis as $kpi): ?>
        <article class="card kpi-card">
          <div class="kpi-top">
            <div class="kpi-icon <?= htmlspecialchars($kpi['iconClass'], ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($kpi['icon'], ENT_QUOTES, 'UTF-8') ?>
            </div>
            <span class="trend <?= htmlspecialchars($kpi['trendClass'], ENT_QUOTES, 'UTF-8') ?>">
              <?= htmlspecialchars($kpi['trend'], ENT_QUOTES, 'UTF-8') ?>
            </span>
          </div>
          <h3><?= htmlspecialchars($kpi['value'], ENT_QUOTES, 'UTF-8') ?></h3>
          <p><?= htmlspecialchars($kpi['label'], ENT_QUOTES, 'UTF-8') ?></p>
        </article>
      <?php endforeach; ?>
    </section>

    <section class="content-grid">
      <article class="card section-card">
        <div class="section-header">
          <div>
            <h2>Protocolos recentes</h2>
            <p>Últimos atendimentos registrados pela recepção</p>
          </div>
          <a class="badge-button" href="#">Ver fila completa</a>
        </div>

        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Cliente</th>
                <th>Serviço</th>
                <th>Protocolo</th>
                <th>Hora</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($protocolos as $item): ?>
                <tr>
                  <td>
                    <div class="client-name"><?= htmlspecialchars($item['cliente'], ENT_QUOTES, 'UTF-8') ?></div>
                    <div class="client-meta">Cadastro inicial validado na recepção</div>
                  </td>
                  <td><?= htmlspecialchars($item['servico'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($item['protocolo'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td><?= htmlspecialchars($item['hora'], ENT_QUOTES, 'UTF-8') ?></td>
                  <td>
                    <span class="status-badge <?= htmlspecialchars($item['statusClass'], ENT_QUOTES, 'UTF-8') ?>">
                      <?= htmlspecialchars($item['status'], ENT_QUOTES, 'UTF-8') ?>
                    </span>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </article>

      <article class="card section-card">
        <div class="section-header">
          <div>
            <h3>Andamento do fluxo</h3>
            <p>Visão operacional da recepção</p>
          </div>
        </div>

        <div class="workflow-list">
          <?php foreach ($fluxos as $fluxo): ?>
            <div class="workflow-item">
              <div class="progress-row">
                <strong><?= htmlspecialchars($fluxo['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                <span><?= (int) $fluxo['percentual'] ?>%</span>
              </div>
              <div class="progress-bar">
                <span style="width: <?= (int) $fluxo['percentual'] ?>%"></span>
              </div>
              <p class="note"><?= htmlspecialchars($fluxo['descricao'], ENT_QUOTES, 'UTF-8') ?></p>
            </div>
          <?php endforeach; ?>
        </div>
      </article>
    </section>

    <section class="bottom-grid">
      <article class="card section-card">
        <div class="section-header">
          <div>
            <h3>Movimentações do dia</h3>
            <p>Eventos recentes no setor de recepção</p>
          </div>
        </div>

        <div class="timeline-list">
          <?php foreach ($timeline as $evento): ?>
            <div class="timeline-item">
              <div class="timeline-time">
                <strong><?= htmlspecialchars($evento['hora'], ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars($evento['periodo'], ENT_QUOTES, 'UTF-8') ?></small>
              </div>
              <div>
                <h4><?= htmlspecialchars($evento['titulo'], ENT_QUOTES, 'UTF-8') ?></h4>
                <p><?= htmlspecialchars($evento['descricao'], ENT_QUOTES, 'UTF-8') ?></p>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
      </article>

      <article class="card section-card">
        <div class="section-header">
          <div>
            <h3>Pontos de atenção</h3>
            <p>O que precisa de ação na recepção</p>
          </div>
        </div>

        <div class="list-clean">
          <?php foreach ($pendencias as $pendencia): ?>
            <div class="list-clean-item">
              <div>
                <strong><?= htmlspecialchars($pendencia['titulo'], ENT_QUOTES, 'UTF-8') ?></strong>
                <small><?= htmlspecialchars($pendencia['texto'], ENT_QUOTES, 'UTF-8') ?></small>
              </div>
              <span class="mini-status">Revisar</span>
            </div>
          <?php endforeach; ?>
        </div>
      </article>
    </section>

    <div class="footer-note">
      Estrutura inicial do dashboard da recepção para o sistema de protocolo em PHP.
    </div>
  </main>
</div>
<?php require __DIR__ . '/../layouts/footer.php'; ?>
