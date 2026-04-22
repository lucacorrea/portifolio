<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Recepção | Sistema de Protocolo</title>
  <style>
    :root{
      --bg: #f6f4ee;
      --panel: #ffffff;
      --panel-soft: #fbfaf6;
      --border: #e6e1d5;
      --text: #223128;
      --muted: #6a786f;

      --primary: #1f4d3a;
      --primary-dark: #173a2c;
      --primary-soft: #e8f1eb;

      --secondary: #6d8b57;
      --secondary-soft: #eef4ea;

      --accent: #b28a47;
      --accent-soft: #fbf2e2;

      --danger: #b64a4a;
      --danger-soft: #fdeeee;

      --success: #1f7a4d;
      --success-soft: #eaf7ef;

      --shadow: 0 14px 34px rgba(31, 77, 58, 0.08);
      --radius-xl: 24px;
      --radius-lg: 18px;
      --radius-md: 14px;
      --radius-sm: 10px;
    }

    *{
      margin: 0;
      padding: 0;
      box-sizing: border-box;
      font-family: "Inter", "Segoe UI", Arial, sans-serif;
    }

    body{
      background: linear-gradient(180deg, #f8f6f0 0%, #f3efe6 100%);
      color: var(--text);
    }

    a{
      text-decoration: none;
      color: inherit;
    }

    .layout{
      min-height: 100vh;
      display: grid;
      grid-template-columns: 280px 1fr;
    }

    .sidebar{
      background: linear-gradient(180deg, var(--primary) 0%, var(--primary-dark) 100%);
      color: #fff;
      padding: 28px 20px;
      position: sticky;
      top: 0;
      height: 100vh;
      border-right: 1px solid rgba(255,255,255,0.08);
    }

    .brand{
      display: flex;
      align-items: center;
      gap: 14px;
      margin-bottom: 28px;
    }

    .brand-badge{
      width: 54px;
      height: 54px;
      border-radius: 16px;
      background: linear-gradient(135deg, var(--accent), #d1b074);
      color: #fff;
      display: grid;
      place-items: center;
      font-weight: 800;
      font-size: 18px;
      box-shadow: 0 12px 24px rgba(0,0,0,0.18);
    }

    .brand small{
      display: block;
      font-size: 12px;
      color: rgba(255,255,255,0.72);
      text-transform: uppercase;
      letter-spacing: .08em;
      margin-bottom: 4px;
    }

    .brand strong{
      font-size: 18px;
      line-height: 1.2;
      display: block;
    }

    .menu-title{
      margin: 24px 0 12px;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .10em;
      color: rgba(255,255,255,0.55);
    }

    .nav-list{
      display: grid;
      gap: 8px;
    }

    .nav-link{
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 13px 14px;
      border-radius: 14px;
      color: rgba(255,255,255,0.88);
      transition: .25s ease;
      font-weight: 500;
    }

    .nav-link:hover,
    .nav-link.active{
      background: rgba(255,255,255,0.10);
      color: #fff;
      transform: translateX(2px);
    }

    .nav-icon{
      width: 34px;
      height: 34px;
      border-radius: 10px;
      background: rgba(255,255,255,0.08);
      display: grid;
      place-items: center;
      font-size: 15px;
      flex-shrink: 0;
    }

    .sidebar-card{
      margin-top: 28px;
      padding: 18px;
      border-radius: 20px;
      background: rgba(255,255,255,0.08);
      border: 1px solid rgba(255,255,255,0.08);
    }

    .sidebar-card strong{
      display: block;
      margin-bottom: 8px;
      font-size: 15px;
    }

    .sidebar-card p{
      font-size: 13px;
      color: rgba(255,255,255,0.78);
      line-height: 1.55;
      margin-bottom: 14px;
    }

    .sidebar-card button{
      width: 100%;
      border: 0;
      border-radius: 12px;
      padding: 12px 14px;
      background: linear-gradient(135deg, var(--accent), #c9a462);
      color: #fff;
      font-weight: 700;
      cursor: pointer;
    }

    .content{
      padding: 24px;
    }

    .topbar{
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 16px;
      margin-bottom: 22px;
      flex-wrap: wrap;
    }

    .search-wrap{
      position: relative;
      flex: 1;
      min-width: 280px;
      max-width: 520px;
    }

    .search-wrap input{
      width: 100%;
      border: 1px solid var(--border);
      background: rgba(255,255,255,0.92);
      border-radius: 16px;
      padding: 15px 16px 15px 46px;
      outline: none;
      color: var(--text);
      box-shadow: 0 10px 20px rgba(31,77,58,0.04);
    }

    .search-wrap span{
      position: absolute;
      left: 16px;
      top: 50%;
      transform: translateY(-50%);
      color: var(--muted);
    }

    .top-actions{
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
    }

    .chip,
    .btn{
      border-radius: 14px;
      padding: 12px 16px;
      border: 1px solid var(--border);
      background: rgba(255,255,255,0.92);
      color: var(--text);
      font-weight: 600;
    }

    .btn-primary{
      border: 0;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff;
      box-shadow: 0 12px 24px rgba(31,77,58,0.14);
    }

    .hero{
      background:
        radial-gradient(circle at top right, rgba(178,138,71,.16), transparent 28%),
        linear-gradient(135deg, #234635 0%, #2f5a45 60%, #45634f 100%);
      color: #fff;
      border-radius: 28px;
      padding: 28px;
      box-shadow: var(--shadow);
      margin-bottom: 22px;
    }

    .hero-grid{
      display: grid;
      grid-template-columns: 1.4fr .9fr;
      gap: 18px;
      align-items: center;
    }

    .eyebrow{
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 8px 12px;
      background: rgba(255,255,255,0.12);
      border-radius: 999px;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .08em;
      margin-bottom: 14px;
    }

    .hero h1{
      font-size: clamp(28px, 4vw, 38px);
      line-height: 1.1;
      margin-bottom: 12px;
    }

    .hero p{
      font-size: 15px;
      line-height: 1.65;
      color: rgba(255,255,255,0.88);
      max-width: 700px;
      margin-bottom: 18px;
    }

    .hero-stats{
      display: flex;
      flex-wrap: wrap;
      gap: 12px;
    }

    .hero-pill{
      min-width: 155px;
      padding: 14px 16px;
      border-radius: 18px;
      background: rgba(255,255,255,0.10);
      border: 1px solid rgba(255,255,255,0.12);
    }

    .hero-pill small{
      display: block;
      color: rgba(255,255,255,0.72);
      margin-bottom: 6px;
    }

    .hero-pill strong{
      font-size: 22px;
    }

    .hero-aside{
      background: rgba(255,255,255,0.10);
      border: 1px solid rgba(255,255,255,0.12);
      border-radius: 22px;
      padding: 22px;
    }

    .hero-aside h3{
      font-size: 16px;
      margin-bottom: 14px;
    }

    .mini-list{
      display: grid;
      gap: 12px;
    }

    .mini-item{
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding-bottom: 12px;
      border-bottom: 1px solid rgba(255,255,255,0.12);
    }

    .mini-item:last-child{
      border-bottom: 0;
      padding-bottom: 0;
    }

    .mini-item strong{
      display: block;
      font-size: 14px;
      margin-bottom: 4px;
    }

    .mini-item small{
      color: rgba(255,255,255,0.72);
      line-height: 1.45;
    }

    .mini-badge{
      white-space: nowrap;
      padding: 7px 10px;
      border-radius: 999px;
      background: rgba(255,255,255,0.14);
      font-size: 12px;
      font-weight: 700;
      height: fit-content;
    }

    .stats-grid{
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 18px;
      margin-bottom: 22px;
    }

    .card{
      background: rgba(255,255,255,0.94);
      border: 1px solid rgba(31,77,58,0.08);
      border-radius: var(--radius-xl);
      box-shadow: var(--shadow);
    }

    .stat-card{
      padding: 20px;
    }

    .stat-top{
      display: flex;
      justify-content: space-between;
      align-items: flex-start;
      gap: 14px;
      margin-bottom: 16px;
    }

    .stat-icon{
      width: 46px;
      height: 46px;
      border-radius: 16px;
      display: grid;
      place-items: center;
      font-size: 20px;
    }

    .soft-primary{ background: var(--primary-soft); color: var(--primary); }
    .soft-secondary{ background: var(--secondary-soft); color: var(--secondary); }
    .soft-accent{ background: var(--accent-soft); color: var(--accent); }
    .soft-danger{ background: var(--danger-soft); color: var(--danger); }

    .stat-card h3{
      font-size: 28px;
      line-height: 1;
      margin-bottom: 6px;
    }

    .stat-card p{
      color: var(--muted);
      font-size: 14px;
    }

    .trend{
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
    }

    .trend.up{
      background: var(--success-soft);
      color: var(--success);
    }

    .trend.warn{
      background: var(--accent-soft);
      color: var(--accent);
    }

    .trend.down{
      background: var(--danger-soft);
      color: var(--danger);
    }

    .main-grid{
      display: grid;
      grid-template-columns: 1.35fr .85fr;
      gap: 18px;
      margin-bottom: 18px;
    }

    .panel{
      padding: 22px;
    }

    .panel-header{
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 12px;
      margin-bottom: 18px;
      flex-wrap: wrap;
    }

    .panel-header h2,
    .panel-header h3{
      font-size: 20px;
      margin-bottom: 4px;
    }

    .panel-header p{
      font-size: 14px;
      color: var(--muted);
    }

    table{
      width: 100%;
      border-collapse: collapse;
    }

    th{
      text-align: left;
      font-size: 12px;
      text-transform: uppercase;
      letter-spacing: .08em;
      color: var(--muted);
      padding-bottom: 12px;
      border-bottom: 1px solid var(--border);
    }

    td{
      padding: 16px 0;
      border-bottom: 1px solid #ece7db;
      font-size: 14px;
      vertical-align: middle;
    }

    tr:last-child td{
      border-bottom: 0;
    }

    .client-name{
      font-weight: 700;
      margin-bottom: 4px;
    }

    .client-sub{
      color: var(--muted);
      font-size: 13px;
    }

    .status{
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 8px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
      white-space: nowrap;
    }

    .status.ok{
      background: var(--success-soft);
      color: var(--success);
    }

    .status.pending{
      background: var(--accent-soft);
      color: var(--accent);
    }

    .status.progress{
      background: var(--primary-soft);
      color: var(--primary);
    }

    .status.high{
      background: var(--danger-soft);
      color: var(--danger);
    }

    .flow-list{
      display: grid;
      gap: 14px;
    }

    .flow-item{
      display: grid;
      grid-template-columns: 52px 1fr;
      gap: 14px;
      align-items: start;
      padding: 14px;
      border-radius: 18px;
      background: var(--panel-soft);
      border: 1px solid var(--border);
    }

    .flow-step{
      width: 52px;
      height: 52px;
      border-radius: 16px;
      background: linear-gradient(135deg, var(--primary), var(--secondary));
      color: #fff;
      display: grid;
      place-items: center;
      font-size: 18px;
      font-weight: 800;
    }

    .flow-item h4{
      font-size: 15px;
      margin-bottom: 5px;
    }

    .flow-item p{
      font-size: 13px;
      line-height: 1.55;
      color: var(--muted);
    }

    .bottom-grid{
      display: grid;
      grid-template-columns: .95fr 1.05fr;
      gap: 18px;
    }

    .alert-list{
      display: grid;
      gap: 14px;
    }

    .alert-item{
      padding: 16px;
      border-radius: 18px;
      border: 1px solid var(--border);
      background: var(--panel-soft);
    }

    .alert-item strong{
      display: block;
      margin-bottom: 6px;
      font-size: 15px;
    }

    .alert-item p{
      color: var(--muted);
      font-size: 13px;
      line-height: 1.55;
      margin-bottom: 10px;
    }

    .alert-tag{
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 7px 10px;
      border-radius: 999px;
      font-size: 12px;
      font-weight: 700;
    }

    .alert-tag.info{
      background: var(--primary-soft);
      color: var(--primary);
    }

    .alert-tag.urgent{
      background: var(--danger-soft);
      color: var(--danger);
    }

    .alert-tag.attention{
      background: var(--accent-soft);
      color: var(--accent);
    }

    .footer-note{
      margin-top: 18px;
      text-align: center;
      color: var(--muted);
      font-size: 13px;
    }

    @media (max-width: 1240px){
      .stats-grid{
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .hero-grid,
      .main-grid,
      .bottom-grid{
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 980px){
      .layout{
        grid-template-columns: 1fr;
      }

      .sidebar{
        position: relative;
        height: auto;
      }
    }

    @media (max-width: 680px){
      .content{
        padding: 16px;
      }

      .stats-grid{
        grid-template-columns: 1fr;
      }

      .hero,
      .panel,
      .stat-card{
        padding: 18px;
      }
    }
  </style>
</head>
<body>
  <div class="layout">
    <aside class="sidebar">
      <div class="brand">
        <div class="brand-badge">RP</div>
        <div>
          <small>Sistema de Protocolo</small>
          <strong>Recepção</strong>
        </div>
      </div>

      <div class="menu-title">Principal</div>
      <nav class="nav-list">
        <a href="#" class="nav-link active">
          <span class="nav-icon">🏠</span>
          Dashboard
        </a>
        <a href="#" class="nav-link">
          <span class="nav-icon">📝</span>
          Novo Protocolo
        </a>
        <a href="#" class="nav-link">
          <span class="nav-icon">👥</span>
          Clientes
        </a>
        <a href="#" class="nav-link">
          <span class="nav-icon">📂</span>
          Protocolos
        </a>
        <a href="#" class="nav-link">
          <span class="nav-icon">📎</span>
          Documentos
        </a>
      </nav>

      <div class="menu-title">Operação</div>
      <nav class="nav-list">
        <a href="#" class="nav-link">
          <span class="nav-icon">📤</span>
          Encaminhar
        </a>
        <a href="#" class="nav-link">
          <span class="nav-icon">⏳</span>
          Pendências
        </a>
        <a href="#" class="nav-link">
          <span class="nav-icon">📊</span>
          Relatórios
        </a>
        <a href="#" class="nav-link">
          <span class="nav-icon">⚙️</span>
          Configurações
        </a>
      </nav>

      <div class="sidebar-card">
        <strong>Resumo da recepção</strong>
        <p>
          A recepção cadastra o cliente, identifica o tipo de serviço, gera o protocolo
          e encaminha para o administrativo realizar o orçamento.
        </p>
        <button type="button">+ Abrir novo protocolo</button>
      </div>
    </aside>

    <main class="content">
      <div class="topbar">
        <div class="search-wrap">
          <span>🔎</span>
          <input type="text" placeholder="Buscar cliente, protocolo, telefone ou serviço..." />
        </div>

        <div class="top-actions">
          <div class="chip">22 Abril 2026</div>
          <div class="chip">Recepcionista: Maria</div>
          <button class="btn btn-primary">Novo Atendimento</button>
        </div>
      </div>

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

      <div class="footer-note">
        Dashboard da recepção • modelo HTML inicial para sistema de protocolo com níveis de usuário
      </div>
    </main>
  </div>
</body>
</html>