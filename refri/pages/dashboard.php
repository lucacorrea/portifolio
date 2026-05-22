<div class="page-body">

      <!-- Metric Cards -->
      <div class="metrics-grid" id="metrics-grid">
        <!-- Gerados via JS -->
        <div class="metric-card skeleton" style="height:110px"></div>
        <div class="metric-card skeleton" style="height:110px"></div>
        <div class="metric-card skeleton" style="height:110px"></div>
        <div class="metric-card skeleton" style="height:110px"></div>
      </div>

      <!-- Filter Bar -->
      <div class="filter-bar">
        <div class="search-wrap">
          <i class="bi bi-search"></i>
          <input type="text" class="search-input" id="search-input"
                 placeholder="Buscar OS, cliente, número…">
        </div>
        <select class="filter-select" id="filter-status">
          <option value="">Todos os status</option>
          <option value="aberta">Aberta</option>
          <option value="em_andamento">Em andamento</option>
          <option value="aguardando_peca">Aguardando peça</option>
          <option value="aguardando_aprovacao">Aguardando aprovação</option>
          <option value="finalizada">Finalizada</option>
          <option value="cancelada">Cancelada</option>
        </select>
        <select class="filter-select" id="filter-prior">
          <option value="">Prioridade</option>
          <option value="baixa">Baixa</option>
          <option value="media">Média</option>
          <option value="alta">Alta</option>
          <option value="urgente">Urgente</option>
        </select>
        <button class="btn-filter btn-filter-primary" onclick="loadOS(1)">
          <i class="bi bi-funnel"></i> Filtrar
        </button>
        <button class="btn-filter btn-filter-ghost" onclick="clearFilters()">
          <i class="bi bi-x-lg"></i> Limpar
        </button>
      </div>

      <!-- Content Area -->
      <div class="content-area">

        <div class="dashboard-main-column">
          <!-- Tabela de OS -->
          <div class="panel">
            <div class="panel-header">
              <div class="panel-title">
                <i class="bi bi-card-list"></i>
                Ordens de Serviço
                <span id="os-total-label" style="font-size:12px;font-weight:500;color:var(--slate-400);background:var(--slate-100);padding:2px 9px;border-radius:20px;margin-left:2px;"></span>
              </div>
              <div class="panel-actions">
                <div class="tb-icon-btn" style="width:30px;height:30px;border-radius:9px;" title="Exportar">
                  <i class="bi bi-download" style="font-size:14px"></i>
                </div>
                <div class="tb-icon-btn" style="width:30px;height:30px;border-radius:9px;" title="Colunas">
                  <i class="bi bi-layout-three-columns" style="font-size:14px"></i>
                </div>
              </div>
            </div>

            <div class="os-table-wrap">
              <table class="os-table">
                <thead>
                  <tr>
                    <th>Número</th>
                    <th>Título / Cliente</th>
                    <th>Status</th>
                    <th>Prioridade</th>
                    <th>Técnico</th>
                    <th>Abertura</th>
                    <th>Valor</th>
                    <th style="text-align:center">Ações</th>
                  </tr>
                </thead>
                <tbody id="os-tbody">
                  <tr><td colspan="8">
                    <div class="skeleton sk-row"></div>
                    <div class="skeleton sk-row"></div>
                    <div class="skeleton sk-row"></div>
                    <div class="skeleton sk-row"></div>
                    <div class="skeleton sk-row"></div>
                  </td></tr>
                </tbody>
              </table>
            </div>

            <div class="pagination-bar">
              <span id="pagination-info" style="font-size:12.5px;color:var(--slate-400)">—</span>
              <div class="pagination-controls" id="pagination-controls"></div>
            </div>
          </div>

          <!-- Painel operacional -->
          <div class="panel operations-panel">
            <div class="panel-header">
              <div class="panel-title">
                <i class="bi bi-clipboard2-pulse"></i> Prioridades e prazos
              </div>
              <span class="panel-tag">Atualizado agora</span>
            </div>

            <div class="operations-body">
              <div class="operations-kpis">
                <div class="operation-kpi danger">
                  <div class="operation-icon"><i class="bi bi-exclamation-triangle"></i></div>
                  <div>
                    <span class="operation-label">Urgentes</span>
                    <strong>2 OS</strong>
                    <small>Acompanhamento imediato</small>
                  </div>
                </div>

                <div class="operation-kpi warning">
                  <div class="operation-icon"><i class="bi bi-calendar-check"></i></div>
                  <div>
                    <span class="operation-label">Vencem hoje</span>
                    <strong>5 visitas</strong>
                    <small>Confirmar agenda</small>
                  </div>
                </div>

                <div class="operation-kpi info">
                  <div class="operation-icon"><i class="bi bi-person-x"></i></div>
                  <div>
                    <span class="operation-label">Sem técnico</span>
                    <strong>11 OS</strong>
                    <small>Definir responsável</small>
                  </div>
                </div>

                <div class="operation-kpi success">
                  <div class="operation-icon"><i class="bi bi-cash-coin"></i></div>
                  <div>
                    <span class="operation-label">Orçamentos</span>
                    <strong>R$ 6.830</strong>
                    <small>Pendentes de aprovação</small>
                  </div>
                </div>
              </div>

              <div class="deadline-board">
                <div class="deadline-head">
                  <span>Próximas ações</span>
                  <button class="btn-filter btn-filter-ghost" type="button" onclick="toast('Agenda será criada na próxima etapa', 'info')">
                    Ver agenda
                  </button>
                </div>

                <div class="deadline-list">
                  <div class="deadline-item">
                    <span class="deadline-time">09:30</span>
                    <div>
                      <strong>Retorno para Logística Sul</strong>
                      <small>VPN corporativa aguardando validação do cliente</small>
                    </div>
                    <span class="deadline-status status-danger">Urgente</span>
                  </div>

                  <div class="deadline-item">
                    <span class="deadline-time">14:00</span>
                    <div>
                      <strong>Visita técnica no Supermercado Rio</strong>
                      <small>Instalação CFTV com Lucas Ferreira</small>
                    </div>
                    <span class="deadline-status status-warning">Hoje</span>
                  </div>

                  <div class="deadline-item">
                    <span class="deadline-time">16:30</span>
                    <div>
                      <strong>Aprovar orçamento da Acme Corp</strong>
                      <small>Manutenção preventiva do servidor</small>
                    </div>
                    <span class="deadline-status status-success">R$ 1.200</span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Sidebar direita -->
        <div class="side-panels">

          <!-- Gráfico Donut Status -->
          <div class="panel">
            <div class="panel-header">
              <div class="panel-title">
                <i class="bi bi-pie-chart"></i> Por Status
              </div>
            </div>
            <div class="chart-wrap">
              <canvas id="chart-status" height="220"></canvas>
            </div>
          </div>

          <!-- Gráfico Mensal -->
          <div class="panel">
            <div class="panel-header">
              <div class="panel-title">
                <i class="bi bi-bar-chart"></i> Últimos 6 Meses
              </div>
            </div>
            <div class="chart-wrap" style="padding-top:10px">
              <canvas id="chart-monthly" height="200"></canvas>
            </div>
          </div>

          <!-- OS Recentes -->
          <div class="panel">
            <div class="panel-header">
              <div class="panel-title">
                <i class="bi bi-clock-history"></i> Recentes
              </div>
              <button class="btn-filter btn-filter-ghost" style="height:28px;padding:0 10px;font-size:12px;" onclick="loadOS(1)">
                Ver todas
              </button>
            </div>
            <div id="recent-list" class="recent-list">
              <div style="padding:20px;text-align:center"><div class="skeleton" style="height:20px;margin-bottom:8px"></div><div class="skeleton" style="height:20px;margin-bottom:8px"></div><div class="skeleton" style="height:20px"></div></div>
            </div>
          </div>

        </div><!-- /side-panels -->
      </div><!-- /content-area -->

    </div><!-- /page-body -->
