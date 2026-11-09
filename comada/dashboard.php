<?php $pageTitle='Dashboard'; $currentPage='dashboard'; include 'includes/header.php'; ?>

<div class="page-head dashboard-head">
    <div>
        <h1>Visão geral</h1>
        <p>Resumo claro da operação, vendas e desempenho do estabelecimento.</p>
        <div class="date-label">Operação ativa agora</div>
    </div>
    <div class="dashboard-actions">
        <select class="select" aria-label="Período">
            <option>Hoje</option>
            <option>Últimos 7 dias</option>
            <option>Últimos 30 dias</option>
        </select>
        <button class="btn btn-secondary" data-demo-action="Dashboard atualizado">Atualizar dados</button>
    </div>
</div>

<div class="grid grid-4">
    <div class="card metric">
        <div class="metric-top">
            <div><div class="metric-label">Vendas hoje</div></div>
            <span class="metric-icon violet"><svg viewBox="0 0 24 24"><path d="M4 19V9m5 10V5m5 14v-7m5 7V3"/></svg></span>
        </div>
        <div class="metric-value">R$ 8.420,50</div>
        <div class="metric-foot good">▲ 12,4% em relação a ontem</div>
        <span class="metric-spark"><svg viewBox="0 0 70 30"><path d="M2 25c8-8 13-6 19-13 5-6 10 4 16-1 7-6 10-12 16-8 5 3 8-2 15-1"/></svg></span>
    </div>

    <div class="card metric">
        <div class="metric-top">
            <div><div class="metric-label">Comandas abertas</div></div>
            <span class="metric-icon green"><svg viewBox="0 0 24 24"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3zm3 5h6m-6 4h6"/></svg></span>
        </div>
        <div class="metric-value">18</div>
        <div class="metric-foot">5 aguardando fechamento</div>
    </div>

    <div class="card metric">
        <div class="metric-top">
            <div><div class="metric-label">Ticket médio</div></div>
            <span class="metric-icon blue"><svg viewBox="0 0 24 24"><path d="M4 7h16v10H4V7zm4 5h8M7 9h.01M17 15h.01"/></svg></span>
        </div>
        <div class="metric-value">R$ 87,30</div>
        <div class="metric-foot good">▲ 4,8% no período</div>
        <span class="metric-spark green"><svg viewBox="0 0 70 30"><path d="M2 25c7-3 11-11 17-8 8 4 10-8 17-5 7 3 11 5 16-2 5-6 9-2 16-8"/></svg></span>
    </div>

    <div class="card metric">
        <div class="metric-top">
            <div><div class="metric-label">Pedidos hoje</div></div>
            <span class="metric-icon orange"><svg viewBox="0 0 24 24"><path d="M4 7h16l-1.5 12h-13L4 7zm3 0a5 5 0 0110 0"/></svg></span>
        </div>
        <div class="metric-value">132</div>
        <div class="metric-foot warn">21 em produção agora</div>
    </div>
</div>

<div class="dashboard-grid">
    <section class="card">
        <div class="section-title">
            <div><h2>Vendas por horário</h2></div>
            <span class="pill success">Tempo real</span>
        </div>
        <div class="sales-chart">
            <div class="chart-legend">
                <div class="chart-total"><strong>R$ 8.420,50</strong><small>Faturamento acumulado hoje</small></div>
                <small>Pico às 21h · R$ 1.480</small>
            </div>
            <div class="bars" aria-label="Gráfico de vendas por horário">
                <div class="bar-col"><div class="bar" style="height:22%"></div><span>12h</span></div>
                <div class="bar-col"><div class="bar" style="height:34%"></div><span>13h</span></div>
                <div class="bar-col"><div class="bar" style="height:29%"></div><span>14h</span></div>
                <div class="bar-col"><div class="bar" style="height:41%"></div><span>15h</span></div>
                <div class="bar-col"><div class="bar" style="height:36%"></div><span>16h</span></div>
                <div class="bar-col"><div class="bar" style="height:48%"></div><span>17h</span></div>
                <div class="bar-col"><div class="bar" style="height:58%"></div><span>18h</span></div>
                <div class="bar-col"><div class="bar" style="height:71%"></div><span>19h</span></div>
                <div class="bar-col"><div class="bar" style="height:82%"></div><span>20h</span></div>
                <div class="bar-col"><div class="bar" style="height:94%"></div><span>21h</span></div>
                <div class="bar-col"><div class="bar" style="height:73%"></div><span>22h</span></div>
                <div class="bar-col"><div class="bar" style="height:49%"></div><span>23h</span></div>
            </div>
        </div>
    </section>

    <section class="card">
        <div class="section-title"><h2>Indicadores operacionais</h2><a href="relatorios.php">Detalhes</a></div>
        <div class="ops-list">
            <div class="ops-row">
                <span class="ops-icon"><svg viewBox="0 0 24 24"><path d="M12 9v4m0 4h.01M10 3h4l8 16H2L10 3z"/></svg></span>
                <div class="ops-copy"><span>Cancelamentos</span><small>3 ocorrências hoje</small></div><strong class="ops-value">R$ 96,00</strong>
            </div>
            <div class="ops-row">
                <span class="ops-icon"><svg viewBox="0 0 24 24"><path d="M4 12h16M12 4v16"/></svg></span>
                <div class="ops-copy"><span>Descontos aplicados</span><small>7 comandas receberam desconto</small></div><strong class="ops-value">R$ 184,50</strong>
            </div>
            <div class="ops-row">
                <span class="ops-icon"><svg viewBox="0 0 24 24"><path d="M4 7l8-4 8 4-8 4-8-4zm0 0v10l8 4 8-4V7m-8 4v10"/></svg></span>
                <div class="ops-copy"><span>Estoque crítico</span><small>Itens abaixo do mínimo</small></div><strong class="ops-value">14 itens</strong>
            </div>
            <div class="ops-row">
                <span class="ops-icon"><svg viewBox="0 0 24 24"><path d="M4 6h16v13H4V6zm0 4h16M8 15h3"/></svg></span>
                <div class="ops-copy"><span>Caixa atual</span><small>Saldo esperado em operação</small></div><strong class="ops-value">R$ 4.820,00</strong>
            </div>
        </div>
    </section>
</div>

<div class="dashboard-bottom">
    <section class="card">
        <div class="section-title"><h2>Produtos mais vendidos</h2><a href="relatorio-vendas.php">Ver relatório</a></div>
        <div class="product-rank">
            <div class="rank-row"><span class="rank-num">01</span><div class="rank-product"><strong>Heineken 600ml</strong><small>Cervejas</small></div><div class="rank-progress"><span style="width:92%"></span></div><div class="rank-value"><strong>86 un.</strong><small>R$ 1.032</small></div></div>
            <div class="rank-row"><span class="rank-num">02</span><div class="rank-product"><strong>Caipirinha</strong><small>Drinks</small></div><div class="rank-progress"><span style="width:71%"></span></div><div class="rank-value"><strong>48 un.</strong><small>R$ 864</small></div></div>
            <div class="rank-row"><span class="rank-num">03</span><div class="rank-product"><strong>X-Bacon</strong><small>Lanches</small></div><div class="rank-progress"><span style="width:58%"></span></div><div class="rank-value"><strong>31 un.</strong><small>R$ 992</small></div></div>
            <div class="rank-row"><span class="rank-num">04</span><div class="rank-product"><strong>Batata frita</strong><small>Porções</small></div><div class="rank-progress"><span style="width:44%"></span></div><div class="rank-value"><strong>27 un.</strong><small>R$ 756</small></div></div>
        </div>
    </section>

    <section class="card">
        <div class="section-title"><h2>Movimentações recentes</h2><a href="auditoria.php">Auditoria</a></div>
        <div class="timeline">
            <div class="timeline-item"><span class="timeline-dot"></span><div class="timeline-copy"><strong>Mesa 08 recebeu novo pedido</strong><small>Marcos adicionou 4 itens à comanda #153</small></div><span class="timeline-time">14:34</span></div>
            <div class="timeline-item"><span class="timeline-dot"></span><div class="timeline-copy"><strong>Sangria registrada no caixa</strong><small>Ana registrou retirada de R$ 280,00</small></div><span class="timeline-time">14:25</span></div>
            <div class="timeline-item"><span class="timeline-dot"></span><div class="timeline-copy"><strong>Comanda #153 finalizada</strong><small>Pagamento concluído via PIX</small></div><span class="timeline-time">14:18</span></div>
            <div class="timeline-item"><span class="timeline-dot"></span><div class="timeline-copy"><strong>Estoque atualizado</strong><small>Entrada de 48 unidades de Heineken</small></div><span class="timeline-time">13:57</span></div>
        </div>
    </section>
</div>

<?php include 'includes/footer.php'; ?>
