<?php
  $paginaAtual = 'dashboard';

  $paginaTitulo = 'Dashboard da Recepção';
  $paginaDescricao = 'Visão geral dos atendimentos, protocolos e encaminhamentos do setor.';
  $usuarioNome = 'Maria Souza';
  $usuarioCargo = 'Recepcionista';
  $textoBotaoAcao = '+ Novo Protocolo';
  $linkBotaoAcao = 'novo-protocolo.php';
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recepção | Sistema de Protocolo</title>
  <link rel="stylesheet" href="/public/assets/css/recepcao.css" />
</head>
<body>
  <div class="layout">
     <?php include __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
      <?php include __DIR__ . '/includes/topbar.php'; ?>

      <section class="hero">
        <div class="hero-grid">
          <div>
            <span class="eyebrow">Painel operacional da recepção</span>
            <h1>Controle de entrada, triagem e encaminhamento de protocolos</h1>
            <p>
              Este painel foi pensado para a recepção operar com rapidez e organização:
              receber os dados do cliente, registrar o serviço solicitado, anexar documentos
              e enviar tudo para o setor administrativo dar sequência no orçamento.
            </p>

            <div class="hero-stats">
              <div class="hero-pill">
                <small>Atendimentos hoje</small>
                <strong>28</strong>
              </div>
              <div class="hero-pill">
                <small>Protocolos abertos</small>
                <strong>14</strong>
              </div>
              <div class="hero-pill">
                <small>Encaminhados</small>
                <strong>09</strong>
              </div>
            </div>
          </div>

          <div class="hero-aside">
            <h3>Foco do turno</h3>
            <div class="mini-list">
              <div class="mini-item">
                <div>
                  <strong>Triagem prioritária</strong>
                  <small>3 atendimentos com urgência aguardando cadastro final.</small>
                </div>
                <span class="mini-badge">Alta</span>
              </div>

              <div class="mini-item">
                <div>
                  <strong>Encaminhamento</strong>
                  <small>5 protocolos prontos para envio ao administrativo.</small>
                </div>
                <span class="mini-badge">Hoje</span>
              </div>

              <div class="mini-item">
                <div>
                  <strong>Documentos incompletos</strong>
                  <small>2 clientes precisam complementar anexos do atendimento.</small>
                </div>
                <span class="mini-badge">Atenção</span>
              </div>
            </div>
          </div>
        </div>
      </section>

      <section class="stats-grid">
        <article class="card stat-card">
          <div class="stat-top">
            <div class="stat-icon soft-primary">📂</div>
            <span class="trend up">+6 hoje</span>
          </div>
          <h3>14</h3>
          <p>Protocolos abertos na recepção</p>
        </article>

        <article class="card stat-card">
          <div class="stat-top">
            <div class="stat-icon soft-secondary">📤</div>
            <span class="trend up">9 enviados</span>
          </div>
          <h3>09</h3>
          <p>Encaminhados ao administrativo</p>
        </article>

        <article class="card stat-card">
          <div class="stat-top">
            <div class="stat-icon soft-accent">📎</div>
            <span class="trend warn">4 revisão</span>
          </div>
          <h3>04</h3>
          <p>Cadastros aguardando documentos</p>
        </article>

        <article class="card stat-card">
          <div class="stat-top">
            <div class="stat-icon soft-danger">⚠️</div>
            <span class="trend down">3 urgentes</span>
          </div>
          <h3>03</h3>
          <p>Atendimentos com prioridade alta</p>
        </article>
      </section>

      <section class="main-grid">
        <article class="card panel">
          <div class="panel-header">
            <div>
              <h2>Protocolos recentes</h2>
              <p>Últimos atendimentos registrados na recepção</p>
            </div>
            <a href="#" class="chip">Ver todos</a>
          </div>

          <table>
            <thead>
              <tr>
                <th>Cliente</th>
                <th>Serviço</th>
                <th>Protocolo</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>
                  <div class="client-name">Carlos Henrique</div>
                  <div class="client-sub">(92) 99999-1020</div>
                </td>
                <td>Solicitação de orçamento</td>
                <td>PRT-2026-0418</td>
                <td><span class="status ok">Encaminhado</span></td>
              </tr>

              <tr>
                <td>
                  <div class="client-name">Ana Beatriz Costa</div>
                  <div class="client-sub">(92) 98888-2451</div>
                </td>
                <td>Análise documental</td>
                <td>PRT-2026-0419</td>
                <td><span class="status pending">Aguardando anexo</span></td>
              </tr>

              <tr>
                <td>
                  <div class="client-name">João Pedro Silva</div>
                  <div class="client-sub">(92) 99777-8874</div>
                </td>
                <td>Cadastro de serviço</td>
                <td>PRT-2026-0420</td>
                <td><span class="status progress">Em triagem</span></td>
              </tr>

              <tr>
                <td>
                  <div class="client-name">Fernanda Martins</div>
                  <div class="client-sub">(92) 99123-4088</div>
                </td>
                <td>Orçamento prioritário</td>
                <td>PRT-2026-0421</td>
                <td><span class="status high">Urgente</span></td>
              </tr>

              <tr>
                <td>
                  <div class="client-name">Raimundo Lopes</div>
                  <div class="client-sub">(92) 99456-7721</div>
                </td>
                <td>Revisão de solicitação</td>
                <td>PRT-2026-0422</td>
                <td><span class="status ok">Encaminhado</span></td>
              </tr>
            </tbody>
          </table>
        </article>

        <article class="card panel">
          <div class="panel-header">
            <div>
              <h3>Fluxo da recepção</h3>
              <p>Etapas operacionais do atendimento</p>
            </div>
          </div>

          <div class="flow-list">
            <div class="flow-item">
              <div class="flow-step">1</div>
              <div>
                <h4>Receber cliente</h4>
                <p>Registrar nome, contato, documento principal e motivo do atendimento.</p>
              </div>
            </div>

            <div class="flow-item">
              <div class="flow-step">2</div>
              <div>
                <h4>Selecionar tipo de serviço</h4>
                <p>Definir corretamente o serviço solicitado para evitar erro no orçamento.</p>
              </div>
            </div>

            <div class="flow-item">
              <div class="flow-step">3</div>
              <div>
                <h4>Gerar protocolo</h4>
                <p>Criar número único do atendimento e anexar observações da recepção.</p>
              </div>
            </div>

            <div class="flow-item">
              <div class="flow-step">4</div>
              <div>
                <h4>Encaminhar ao administrativo</h4>
                <p>Enviar o protocolo completo para o setor responsável elaborar o orçamento.</p>
              </div>
            </div>
          </div>
        </article>
      </section>

      <section class="bottom-grid">
        <article class="card panel">
          <div class="panel-header">
            <div>
              <h3>Pontos de atenção</h3>
              <p>O que precisa ser resolvido ainda hoje</p>
            </div>
          </div>

          <div class="alert-list">
            <div class="alert-item">
              <strong>Cliente com documento pendente</strong>
              <p>O protocolo PRT-2026-0419 não pode seguir para orçamento sem o comprovante solicitado.</p>
              <span class="alert-tag attention">Aguardando cliente</span>
            </div>

            <div class="alert-item">
              <strong>Atendimento prioritário</strong>
              <p>Há 3 protocolos marcados como urgentes que devem ser enviados primeiro ao administrativo.</p>
              <span class="alert-tag urgent">Prioridade alta</span>
            </div>

            <div class="alert-item">
              <strong>Revisar cadastro do turno da manhã</strong>
              <p>Dois atendimentos foram salvos com observação incompleta e precisam de conferência.</p>
              <span class="alert-tag info">Revisão interna</span>
            </div>
          </div>
        </article>

        <article class="card panel">
          <div class="panel-header">
            <div>
              <h3>Resumo operacional</h3>
              <p>Indicadores rápidos da recepção</p>
            </div>
          </div>

          <table>
            <thead>
              <tr>
                <th>Indicador</th>
                <th>Resultado</th>
                <th>Observação</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <td>Tempo médio de cadastro</td>
                <td>08 min</td>
                <td>Bom ritmo no turno atual</td>
              </tr>
              <tr>
                <td>Cadastros completos</td>
                <td>91%</td>
                <td>Meta mínima: 95%</td>
              </tr>
              <tr>
                <td>Envios ao administrativo</td>
                <td>09</td>
                <td>Acima da média do dia</td>
              </tr>
              <tr>
                <td>Pendências abertas</td>
                <td>04</td>
                <td>Exigem retorno ao cliente</td>
              </tr>
              <tr>
                <td>Atendimentos urgentes</td>
                <td>03</td>
                <td>Encaminhar primeiro</td>
              </tr>
            </tbody>
          </table>
        </article>
      </section>

       <?php include __DIR__ . '/includes/footer.php'; ?>
    </main>
  </div>
</body>
</html>