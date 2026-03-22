<section class="dashboard-grid">

    <!-- CARDS -->
    <div class="cards-grid">
        <?php foreach ($cards as $c): ?>
            <div class="metric-card">

                <div class="metric-header">
                    <span class="metric-title"><?= $c['titulo'] ?></span>
                    <div class="metric-icon">📊</div>
                </div>

                <strong class="metric-value"><?= $c['valor'] ?></strong>

                <span class="metric-text"><?= $c['desc'] ?></span>

                <div class="metric-trend is-positive">
                    <?= $c['trend'] ?>
                </div>

            </div>
        <?php endforeach; ?>
    </div>

    <!-- GRID PRINCIPAL -->
    <div class="dashboard-columns">

        <!-- ESQUERDA -->
        <div class="panel">

            <div class="panel-header">
                <h2>Resumo geral</h2>
            </div>

            <div class="panel-body">
                <p>
                    Esse é o início do seu SaaS. Aqui você vai ter visão geral do sistema,
                    métricas e tudo que é importante para o usuário.
                </p>

                <ul class="summary-list">
                    <li>Sistema modular</li>
                    <li>Menu dinâmico</li>
                    <li>CSS separado</li>
                    <li>Pronto para multiempresa</li>
                </ul>

                <!-- GRÁFICO VISUAL -->
                <div class="mini-chart">
                    <div class="mini-bar-wrap">
                        <div class="mini-bar" style="height:60%"></div>
                        <div class="mini-label">Seg</div>
                    </div>
                    <div class="mini-bar-wrap">
                        <div class="mini-bar" style="height:80%"></div>
                        <div class="mini-label">Ter</div>
                    </div>
                    <div class="mini-bar-wrap">
                        <div class="mini-bar" style="height:40%"></div>
                        <div class="mini-label">Qua</div>
                    </div>
                    <div class="mini-bar-wrap">
                        <div class="mini-bar" style="height:90%"></div>
                        <div class="mini-label">Qui</div>
                    </div>
                    <div class="mini-bar-wrap">
                        <div class="mini-bar" style="height:70%"></div>
                        <div class="mini-label">Sex</div>
                    </div>
                </div>

            </div>
        </div>

        <!-- DIREITA -->
        <div class="panel">

            <div class="panel-header">
                <h2>Alertas</h2>
            </div>

            <div class="panel-body alert-list">
                <?php foreach ($alertas as $a): ?>
                    <div class="alert-row">
                        <div class="alert-dot"></div>
                        <div class="alert-content">
                            <strong><?= $a['titulo'] ?></strong>
                            <span><?= $a['desc'] ?></span>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

        </div>

    </div>

    <!-- AÇÕES RÁPIDAS -->
    <div class="panel">

        <div class="panel-header">
            <h2>Ações rápidas</h2>
        </div>

        <div class="panel-body">
            <div class="quick-actions">
                <a href="#" class="btn btn-primary">Novo cliente</a>
                <a href="#" class="btn btn-secondary">Gerar relatório</a>
                <a href="#" class="btn btn-soft">Nova cobrança</a>
                <a href="#" class="btn btn-secondary">Ver financeiro</a>
            </div>
        </div>

    </div>

</section>