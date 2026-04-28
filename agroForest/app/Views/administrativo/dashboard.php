<?php
$paginaAtual = 'dashboard';
$paginaTitulo = 'Dashboard do Administrativo';
$paginaDescricao = 'Visão geral dos protocolos recebidos, orçamentos em elaboração e pendências do setor.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Novo Orçamento';
$linkBotaoAcao = route_url('administrativo', 'orcamentos');
$tituloPagina = 'Administrativo - Dashboard';
$cssPagina = 'app/assets/css/administrativo/administrativo.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="hero">
            <div class="hero-grid">
                <div>
                    <span class="eyebrow">Painel administrativo</span>
                    <h1>Controle de análise, orçamento e acompanhamento dos protocolos</h1>
                    <p>
                        O administrativo recebe as solicitações da recepção, valida documentos,
                        organiza prioridades, estrutura os orçamentos e acompanha o andamento
                        até a conclusão ou devolução para ajuste.
                    </p>

                    <div class="hero-stats">
                        <div class="hero-pill">
                            <small>Recebidos hoje</small>
                            <strong>18</strong>
                        </div>
                        <div class="hero-pill">
                            <small>Orçamentos em andamento</small>
                            <strong>11</strong>
                        </div>
                        <div class="hero-pill">
                            <small>Finalizados</small>
                            <strong>07</strong>
                        </div>
                    </div>
                </div>

                <div class="hero-aside">
                    <h3>Foco do setor</h3>

                    <div class="mini-list">
                        <div class="mini-item">
                            <div>
                                <strong>Protocolos urgentes</strong>
                                <small>4 solicitações precisam de orçamento com prioridade alta.</small>
                            </div>
                            <span class="mini-badge">Urgente</span>
                        </div>

                        <div class="mini-item">
                            <div>
                                <strong>Documentos pendentes</strong>
                                <small>3 processos aguardam conferência antes da aprovação.</small>
                            </div>
                            <span class="mini-badge">Hoje</span>
                        </div>

                        <div class="mini-item">
                            <div>
                                <strong>Meta do dia</strong>
                                <small>Concluir 8 orçamentos e reduzir a fila de pendências.</small>
                            </div>
                            <span class="mini-badge">Meta</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="stats-grid">
            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-primary">📥</div>
                    <span class="trend up">+5 hoje</span>
                </div>
                <h3>18</h3>
                <p>Protocolos recebidos do setor de recepção</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">💰</div>
                    <span class="trend up">11 ativos</span>
                </div>
                <h3>11</h3>
                <p>Orçamentos em elaboração</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">📎</div>
                    <span class="trend warn">3 revisão</span>
                </div>
                <h3>03</h3>
                <p>Processos aguardando validação documental</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-danger">⏳</div>
                    <span class="trend down">4 urgentes</span>
                </div>
                <h3>04</h3>
                <p>Pendências com prioridade alta</p>
            </article>
        </section>

        <section class="main-grid">
            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h2>Protocolos recebidos</h2>
                        <p>Solicitações que chegaram da recepção para análise administrativa</p>
                    </div>
                    <a href="<?= route_url('administrativo', 'protocolosRecebidos') ?>" class="chip">Ver todos</a>
                </div>

                <div class="table-responsive">
                    <table>
                        <thead>
                            <tr>
                                <th>Protocolo</th>
                                <th>Cliente</th>
                                <th>Serviço</th>
                                <th>Prioridade</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>PRT-2026-0501</td>
                                <td>Carlos Henrique</td>
                                <td>Solicitação de orçamento</td>
                                <td>Normal</td>
                                <td><span class="status progress">Em análise</span></td>
                            </tr>
                            <tr>
                                <td>PRT-2026-0502</td>
                                <td>Fernanda Martins</td>
                                <td>Atendimento prioritário</td>
                                <td>Alta</td>
                                <td><span class="status high">Urgente</span></td>
                            </tr>
                            <tr>
                                <td>PRT-2026-0503</td>
                                <td>Ana Beatriz Costa</td>
                                <td>Análise documental</td>
                                <td>Média</td>
                                <td><span class="status pending">Pendente</span></td>
                            </tr>
                            <tr>
                                <td>PRT-2026-0504</td>
                                <td>João Pedro Silva</td>
                                <td>Cadastro de serviço</td>
                                <td>Normal</td>
                                <td><span class="status ok">Concluído</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h3>Fluxo do administrativo</h3>
                        <p>Etapas operacionais do setor</p>
                    </div>
                </div>

                <div class="flow-list">
                    <div class="flow-item">
                        <div class="flow-step">1</div>
                        <div>
                            <h4>Receber protocolo</h4>
                            <p>Conferir dados enviados pela recepção e validar a entrada da solicitação.</p>
                        </div>
                    </div>

                    <div class="flow-item">
                        <div class="flow-step">2</div>
                        <div>
                            <h4>Verificar documentos</h4>
                            <p>Confirmar se toda a documentação obrigatória está correta.</p>
                        </div>
                    </div>

                    <div class="flow-item">
                        <div class="flow-step">3</div>
                        <div>
                            <h4>Montar orçamento</h4>
                            <p>Definir valores, observações e condições para retorno ao cliente.</p>
                        </div>
                    </div>

                    <div class="flow-item">
                        <div class="flow-step">4</div>
                        <div>
                            <h4>Finalizar ou devolver</h4>
                            <p>Concluir o processo ou devolver para correção de pendências.</p>
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
                        <p>Itens que exigem ação imediata do administrativo</p>
                    </div>
                </div>

                <div class="alert-list">
                    <div class="alert-item">
                        <strong>Orçamentos atrasados</strong>
                        <p>Há 2 processos com prazo de retorno ultrapassado e que precisam ser priorizados.</p>
                        <span class="alert-tag urgent">Ação imediata</span>
                    </div>

                    <div class="alert-item">
                        <strong>Documentos incompletos</strong>
                        <p>3 protocolos dependem de revisão documental antes de seguir para conclusão.</p>
                        <span class="alert-tag attention">Conferência</span>
                    </div>

                    <div class="alert-item">
                        <strong>Fila crescente</strong>
                        <p>O volume de protocolos recebidos aumentou nesta semana e exige reorganização de prioridade.</p>
                        <span class="alert-tag info">Monitorar</span>
                    </div>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h3>Resumo gerencial</h3>
                        <p>Indicadores rápidos do setor administrativo</p>
                    </div>
                </div>

                <div class="table-responsive">
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
                                <td>Tempo médio de análise</td>
                                <td>18 min</td>
                                <td>Dentro do esperado</td>
                            </tr>
                            <tr>
                                <td>Orçamentos concluídos</td>
                                <td>07</td>
                                <td>Meta parcial atingida</td>
                            </tr>
                            <tr>
                                <td>Pendências abertas</td>
                                <td>03</td>
                                <td>Revisão documental</td>
                            </tr>
                            <tr>
                                <td>Protocolos urgentes</td>
                                <td>04</td>
                                <td>Prioridade máxima</td>
                            </tr>
                            <tr>
                                <td>Fila total</td>
                                <td>18</td>
                                <td>Volume atual do dia</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>