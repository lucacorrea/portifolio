<?php
$paginaAtual = 'relatorios';
$paginaTitulo = 'Relatórios Gerenciais';
$paginaDescricao = 'Indicadores globais de protocolos, usuários e operação.';
$usuarioNome = 'Usuário Demo';
$usuarioCargo = 'Dono';
$tituloPagina = 'Dono - Relatórios Gerenciais';
$cssPagina = ['assets/css/administrativo/styleadm.css', 'assets/css/dono/dono.css'];

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="owner-report-hero card">
            <div class="owner-report-hero-content">
                <span class="owner-report-tag">Visão executiva</span>

                <h1>Relatórios Gerenciais</h1>

                <p>
                    Acompanhe o desempenho geral da operação, identifique gargalos,
                    monitore produtividade por setor e tome decisões com mais segurança.
                </p>

                <div class="owner-report-actions">
                    <a href="#" class="btn-owner-primary">
                        Exportar PDF
                    </a>

                    <a href="#" class="btn-owner-secondary">
                        Exportar Excel
                    </a>
                </div>
            </div>

            <div class="owner-report-filter">
                <label for="periodoRelatorio">Período analisado</label>
                <select id="periodoRelatorio" name="periodoRelatorio">
                    <option value="trimestre">Último trimestre</option>
                    <option value="mes">Este mês</option>
                    <option value="semestre">Último semestre</option>
                    <option value="ano">Este ano</option>
                </select>

                <div class="owner-report-date">
                    Atualizado em
                    <strong><?= date('d/m/Y') ?></strong>
                </div>
            </div>
        </section>

        <section class="stats-grid owner-kpi-grid">
            <article class="card stat-card owner-kpi-card">
                <div class="stat-top">
                    <div class="stat-icon soft-primary">📂</div>
                    <span class="trend up">+18%</span>
                </div>

                <h3>342</h3>
                <p>Protocolos no trimestre</p>

                <div class="owner-kpi-footer">
                    <span>Meta: 300</span>
                    <strong>114%</strong>
                </div>

                <div class="owner-progress">
                    <span style="width: 86%;"></span>
                </div>
            </article>

            <article class="card stat-card owner-kpi-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">💰</div>
                    <span class="trend up">+11%</span>
                </div>

                <h3>89</h3>
                <p>Orçamentos finalizados</p>

                <div class="owner-kpi-footer">
                    <span>Conversão operacional</span>
                    <strong>72%</strong>
                </div>

                <div class="owner-progress">
                    <span style="width: 72%;"></span>
                </div>
            </article>

            <article class="card stat-card owner-kpi-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">⏳</div>
                    <span class="trend warn">Atenção</span>
                </div>

                <h3>14</h3>
                <p>Pendências abertas</p>

                <div class="owner-kpi-footer">
                    <span>Acima do ideal</span>
                    <strong>+4</strong>
                </div>

                <div class="owner-progress danger">
                    <span style="width: 58%;"></span>
                </div>
            </article>

            <article class="card stat-card owner-kpi-card">
                <div class="stat-top">
                    <div class="stat-icon soft-info">👤</div>
                    <span class="trend up">9 ativos</span>
                </div>

                <h3>12</h3>
                <p>Usuários cadastrados</p>

                <div class="owner-kpi-footer">
                    <span>Equipe ativa</span>
                    <strong>75%</strong>
                </div>

                <div class="owner-progress">
                    <span style="width: 75%;"></span>
                </div>
            </article>
        </section>

        <section class="owner-report-grid">
            <article class="card owner-chart-card owner-chart-large">
                <div class="panel-header owner-panel-header">
                    <div>
                        <h2>Evolução de protocolos</h2>
                        <p>Comparativo mensal entre protocolos abertos, concluídos e pendentes.</p>
                    </div>

                    <span class="owner-chip success">Crescimento controlado</span>
                </div>

                <div class="owner-chart-box">
                    <canvas id="protocolosChart"></canvas>
                </div>
            </article>

            <article class="card owner-chart-card">
                <div class="panel-header owner-panel-header">
                    <div>
                        <h2>Status geral</h2>
                        <p>Distribuição atual da operação.</p>
                    </div>
                </div>

                <div class="owner-chart-box owner-chart-donut">
                    <canvas id="statusChart"></canvas>
                </div>

                <div class="owner-status-list">
                    <div>
                        <span class="dot ok"></span>
                        Concluídos
                        <strong>72%</strong>
                    </div>

                    <div>
                        <span class="dot warn"></span>
                        Em análise
                        <strong>18%</strong>
                    </div>

                    <div>
                        <span class="dot danger"></span>
                        Pendentes
                        <strong>10%</strong>
                    </div>
                </div>
            </article>
        </section>

        <section class="owner-insights-grid">
            <article class="card owner-insight-card">
                <div class="owner-insight-icon success">✓</div>

                <div>
                    <h3>Melhor desempenho</h3>
                    <p>
                        O administrativo finalizou 72 análises no período, mantendo boa taxa de entrega.
                    </p>
                </div>
            </article>

            <article class="card owner-insight-card">
                <div class="owner-insight-icon warning">!</div>

                <div>
                    <h3>Ponto de atenção</h3>
                    <p>
                        Existem 14 pendências abertas. O ideal é reduzir esse número para evitar acúmulo operacional.
                    </p>
                </div>
            </article>

            <article class="card owner-insight-card">
                <div class="owner-insight-icon info">i</div>

                <div>
                    <h3>Leitura gerencial</h3>
                    <p>
                        O volume aumentou, mas a operação ainda está estável. O foco deve ser tempo de resposta.
                    </p>
                </div>
            </article>
        </section>

        <section class="card panel owner-table-panel">
            <div class="panel-header owner-panel-header">
                <div>
                    <h2>Resumo por área</h2>
                    <p>Relatório consolidado para acompanhamento do dono.</p>
                </div>

                <a href="#" class="owner-link-action">Ver detalhes</a>
            </div>

            <div class="table-responsive">
                <table class="owner-table">
                    <thead>
                        <tr>
                            <th>Área</th>
                            <th>Volume</th>
                            <th>Concluídos</th>
                            <th>Pendências</th>
                            <th>Eficiência</th>
                            <th>Leitura</th>
                        </tr>
                    </thead>

                    <tbody>
                        <tr>
                            <td>
                                <strong>Recepção</strong>
                                <small>Entrada e triagem</small>
                            </td>

                            <td>128 atendimentos</td>
                            <td>89 encaminhados</td>
                            <td>17</td>

                            <td>
                                <div class="owner-mini-progress">
                                    <span style="width: 69%;"></span>
                                </div>
                                <small>69%</small>
                            </td>

                            <td>
                                <span class="status progress">Operação estável</span>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <strong>Administrativo</strong>
                                <small>Análise e orçamento</small>
                            </td>

                            <td>94 análises</td>
                            <td>72 finalizadas</td>
                            <td>11</td>

                            <td>
                                <div class="owner-mini-progress">
                                    <span style="width: 76%;"></span>
                                </div>
                                <small>76%</small>
                            </td>

                            <td>
                                <span class="status ok">Boa entrega</span>
                            </td>
                        </tr>

                        <tr>
                            <td>
                                <strong>Gestão</strong>
                                <small>Usuários e permissões</small>
                            </td>

                            <td>12 usuários</td>
                            <td>9 ativos</td>
                            <td>3 revisões</td>

                            <td>
                                <div class="owner-mini-progress danger">
                                    <span style="width: 75%;"></span>
                                </div>
                                <small>75%</small>
                            </td>

                            <td>
                                <span class="status pending">Acompanhar acessos</span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="owner-bottom-grid">
            <article class="card owner-ranking-card">
                <div class="panel-header owner-panel-header">
                    <div>
                        <h2>Ranking operacional</h2>
                        <p>Setores com maior desempenho no período.</p>
                    </div>
                </div>

                <div class="owner-ranking-list">
                    <div class="owner-ranking-item">
                        <span class="rank-number">1</span>

                        <div>
                            <strong>Administrativo</strong>
                            <small>Maior taxa de conclusão</small>
                        </div>

                        <b>76%</b>
                    </div>

                    <div class="owner-ranking-item">
                        <span class="rank-number">2</span>

                        <div>
                            <strong>Gestão</strong>
                            <small>Equipe ativa e controlada</small>
                        </div>

                        <b>75%</b>
                    </div>

                    <div class="owner-ranking-item">
                        <span class="rank-number">3</span>

                        <div>
                            <strong>Recepção</strong>
                            <small>Bom volume de atendimento</small>
                        </div>

                        <b>69%</b>
                    </div>
                </div>
            </article>

            <article class="card owner-alert-card">
                <div class="panel-header owner-panel-header">
                    <div>
                        <h2>Alertas do dono</h2>
                        <p>Itens que merecem acompanhamento.</p>
                    </div>
                </div>

                <div class="owner-alert-list">
                    <div class="owner-alert-item danger">
                        <strong>14 pendências abertas</strong>
                        <span>Recomenda-se revisar os protocolos parados ainda nesta semana.</span>
                    </div>

                    <div class="owner-alert-item warning">
                        <strong>3 usuários precisam de revisão</strong>
                        <span>Verifique permissões, acessos e usuários inativos.</span>
                    </div>

                    <div class="owner-alert-item success">
                        <strong>Orçamentos em crescimento</strong>
                        <span>O volume finalizado subiu 11% em relação ao período anterior.</span>
                    </div>
                </div>
            </article>

            <article class="card owner-timeline-card">
                <div class="panel-header owner-panel-header">
                    <div>
                        <h2>Últimos movimentos</h2>
                        <p>Resumo recente da operação.</p>
                    </div>
                </div>

                <div class="owner-timeline">
                    <div class="owner-timeline-item">
                        <span></span>
                        <div>
                            <strong>Orçamento aprovado</strong>
                            <small>Administrativo finalizou nova análise.</small>
                        </div>
                    </div>

                    <div class="owner-timeline-item">
                        <span></span>
                        <div>
                            <strong>Novo protocolo recebido</strong>
                            <small>Recepção registrou atendimento prioritário.</small>
                        </div>
                    </div>

                    <div class="owner-timeline-item">
                        <span></span>
                        <div>
                            <strong>Usuário inativo identificado</strong>
                            <small>Gestão precisa revisar permissões.</small>
                        </div>
                    </div>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="assets/js/dono/relatoriosDono.js"></script>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>