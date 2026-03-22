<section class="dashboard-page">

    <section class="hero-panel">
        <div class="hero-panel__content">
            <span class="hero-panel__eyebrow">Visão geral do escritório</span>
            <h2 class="hero-panel__title">Controle rápido das rotinas fiscais, financeiras e operacionais</h2>
            <p class="hero-panel__text">
                Acompanhe empresas, obrigações, documentos pendentes e a produtividade do escritório em um único painel.
            </p>

            <div class="hero-panel__actions">
                <a href="#" class="btn btn-primary">Nova obrigação</a>
                <a href="#" class="btn btn-secondary">Emitir relatório</a>
                <a href="#empresas-pendentes" class="btn btn-soft">Ver pendências</a>
            </div>
        </div>

        <div class="hero-panel__stats">
            <div class="hero-mini-card">
                <span class="hero-mini-card__label">Documentos pendentes</span>
                <strong class="hero-mini-card__value">18</strong>
            </div>
            <div class="hero-mini-card">
                <span class="hero-mini-card__label">Clientes sem retorno</span>
                <strong class="hero-mini-card__value">7</strong>
            </div>
            <div class="hero-mini-card">
                <span class="hero-mini-card__label">Guias emitidas hoje</span>
                <strong class="hero-mini-card__value">11</strong>
            </div>
        </div>
    </section>

    <section class="cards-grid">
        <?php foreach (($cards ?? []) as $card): ?>
            <article class="metric-card">
                <div class="metric-header">
                    <span class="metric-title"><?= htmlspecialchars((string)$card['titulo']) ?></span>
                    <div class="metric-icon"></div>
                </div>

                <strong class="metric-value"><?= htmlspecialchars((string)$card['valor']) ?></strong>
                <span class="metric-text"><?= htmlspecialchars((string)$card['desc']) ?></span>
                <span class="metric-trend <?= htmlspecialchars((string)$card['trend_class']) ?>">
                    <?= htmlspecialchars((string)$card['trend']) ?>
                </span>
            </article>
        <?php endforeach; ?>
    </section>

    <section class="dashboard-main-grid">
        <div class="dashboard-main-left">

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Produtividade semanal</h3>
                        <p class="panel-subtitle">Volume visual de entregas do escritório</p>
                    </div>
                </div>

                <div class="panel-body">
                    <div class="mini-chart">
                        <div class="mini-bar-wrap">
                            <div class="mini-bar" style="height: 52%;"></div>
                            <div class="mini-label">Seg</div>
                        </div>
                        <div class="mini-bar-wrap">
                            <div class="mini-bar" style="height: 76%;"></div>
                            <div class="mini-label">Ter</div>
                        </div>
                        <div class="mini-bar-wrap">
                            <div class="mini-bar" style="height: 61%;"></div>
                            <div class="mini-label">Qua</div>
                        </div>
                        <div class="mini-bar-wrap">
                            <div class="mini-bar" style="height: 88%;"></div>
                            <div class="mini-label">Qui</div>
                        </div>
                        <div class="mini-bar-wrap">
                            <div class="mini-bar" style="height: 73%;"></div>
                            <div class="mini-label">Sex</div>
                        </div>
                        <div class="mini-bar-wrap">
                            <div class="mini-bar" style="height: 46%;"></div>
                            <div class="mini-label">Sáb</div>
                        </div>
                    </div>
                </div>
            </article>

            <article class="panel" id="empresas-pendentes">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Empresas com pendências</h3>
                        <p class="panel-subtitle">Itens que precisam de ação da equipe</p>
                    </div>
                    <a href="#" class="panel-link">Ver todas</a>
                </div>

                <div class="panel-body">
                    <div class="table-wrapper-erp">
                        <table class="erp-table">
                            <thead>
                                <tr>
                                    <th>Empresa</th>
                                    <th>Categoria</th>
                                    <th>Status</th>
                                    <th>Prazo</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (($empresasPendentes ?? []) as $empresa): ?>
                                    <tr>
                                        <td><?= htmlspecialchars((string)$empresa['empresa']) ?></td>
                                        <td><?= htmlspecialchars((string)$empresa['categoria']) ?></td>
                                        <td>
                                            <span class="status-pill warning">
                                                <?= htmlspecialchars((string)$empresa['status']) ?>
                                            </span>
                                        </td>
                                        <td><?= htmlspecialchars((string)$empresa['prazo']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </article>
        </div>

        <aside class="dashboard-main-right">
            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Alertas críticos</h3>
                        <p class="panel-subtitle">Acompanhamento imediato</p>
                    </div>
                </div>

                <div class="panel-body alert-list">
                    <?php foreach (($alertas ?? []) as $alerta): ?>
                        <div class="alert-row">
                            <div class="alert-dot"></div>
                            <div class="alert-content">
                                <strong><?= htmlspecialchars((string)$alerta['titulo']) ?></strong>
                                <span><?= htmlspecialchars((string)$alerta['desc']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Obrigações do período</h3>
                        <p class="panel-subtitle">Resumo operacional</p>
                    </div>
                </div>

                <div class="panel-body">
                    <div class="obrigacoes-list">
                        <?php foreach (($obrigacoes ?? []) as $item): ?>
                            <div class="obrigacao-item">
                                <div class="obrigacao-item__left">
                                    <span class="obrigacao-dot <?= htmlspecialchars((string)$item['cor']) ?>"></span>
                                    <span class="obrigacao-nome"><?= htmlspecialchars((string)$item['nome']) ?></span>
                                </div>
                                <strong class="obrigacao-quantidade"><?= htmlspecialchars((string)$item['quantidade']) ?></strong>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </article>

            <article class="panel">
                <div class="panel-header">
                    <div>
                        <h3 class="panel-title">Ações rápidas</h3>
                        <p class="panel-subtitle">Atalhos do escritório</p>
                    </div>
                </div>

                <div class="panel-body">
                    <div class="quick-grid">
                        <a href="#" class="quick-card">Cadastrar cliente</a>
                        <a href="#" class="quick-card">Emitir guia</a>
                        <a href="#" class="quick-card">Lançar documento</a>
                        <a href="#" class="quick-card">Abrir financeiro</a>
                    </div>
                </div>
            </article>
        </aside>
    </section>
</section>
