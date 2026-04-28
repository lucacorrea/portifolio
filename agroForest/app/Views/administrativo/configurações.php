<?php
$paginaAtual = 'configuracoes';
$paginaTitulo = 'Configurações';
$paginaDescricao = 'Defina regras operacionais, preferências do setor e padrões usados pelo administrativo.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Salvar Configurações';
$linkBotaoAcao = '#';
$tituloPagina = 'Administrativo - Configurações';
$cssPagina = 'assets/css/administrativo/styleAdministrativo.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-primary">⚙️</div>
                    <span class="trend up">ativo</span>
                </div>
                <h3>08</h3>
                <p>Regras configuradas no setor</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">🔔</div>
                    <span class="trend up">ligado</span>
                </div>
                <h3>03</h3>
                <p>Alertas automáticos ativos</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">📋</div>
                    <span class="trend warn">padrão</span>
                </div>
                <h3>05</h3>
                <p>Checklists em uso</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-danger">🕒</div>
                    <span class="trend down">12h</span>
                </div>
                <h3>12h</h3>
                <p>Prazo padrão de retorno</p>
            </article>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div>
                    <h2>Configurações do Administrativo</h2>
                    <p>Defina os parâmetros principais do setor para análise, orçamento e tratamento de pendências.</p>
                </div>
            </div>

            <form class="form-grid" method="POST" action="">
                <div class="form-group">
                    <label for="nome_setor">Nome do setor</label>
                    <input type="text" id="nome_setor" name="nome_setor" value="Administrativo">
                </div>

                <div class="form-group">
                    <label for="responsavel_setor">Responsável do setor</label>
                    <input type="text" id="responsavel_setor" name="responsavel_setor" value="Paulo Martins">
                </div>

                <div class="form-group">
                    <label for="prazo_padrao">Prazo padrão de retorno</label>
                    <select id="prazo_padrao" name="prazo_padrao">
                        <option value="4h">4 horas</option>
                        <option value="8h">8 horas</option>
                        <option value="12h" selected>12 horas</option>
                        <option value="24h">24 horas</option>
                        <option value="48h">48 horas</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="prioridade_padrao">Prioridade padrão</label>
                    <select id="prioridade_padrao" name="prioridade_padrao">
                        <option value="normal" selected>Normal</option>
                        <option value="media">Média</option>
                        <option value="alta">Alta</option>
                    </select>
                </div>

                <div class="form-group col-2">
                    <label for="observacoes_padrao">Observações padrão do orçamento</label>
                    <textarea id="observacoes_padrao" name="observacoes_padrao" rows="4" placeholder="Digite o texto padrão usado nas observações dos orçamentos.">Prazo sujeito à confirmação documental e validação final do setor administrativo.</textarea>
                </div>

                <div class="form-group col-2">
                    <h3>Preferências operacionais</h3>
                </div>

                <div class="col-2 config-grid">
                    <div class="switch-field">
                        <div class="switch-field-info">
                            <strong>Alertar protocolos urgentes</strong>
                            <small>Destaca automaticamente protocolos com prioridade alta no dashboard.</small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="alertar_urgentes" checked>
                            <span class="switch-slider"></span>
                        </label>
                    </div>

                    <div class="switch-field">
                        <div class="switch-field-info">
                            <strong>Exigir checklist documental</strong>
                            <small>Obriga a conferência dos documentos antes de concluir o orçamento.</small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="checklist_documental" checked>
                            <span class="switch-slider"></span>
                        </label>
                    </div>

                    <div class="switch-field">
                        <div class="switch-field-info">
                            <strong>Notificar pendências abertas</strong>
                            <small>Exibe alertas internos quando uma pendência permanece sem retorno.</small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="notificar_pendencias" checked>
                            <span class="switch-slider"></span>
                        </label>
                    </div>

                    <div class="switch-field">
                        <div class="switch-field-info">
                            <strong>Bloquear conclusão sem validação</strong>
                            <small>Impede o fechamento do processo sem validação documental final.</small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="bloquear_sem_validacao">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="form-group col-2">
                    <h3>Padronização do fluxo</h3>
                </div>

                <div class="form-group">
                    <label for="origem_padrao">Origem padrão das revisões</label>
                    <select id="origem_padrao" name="origem_padrao">
                        <option value="recepcao" selected>Recepção</option>
                        <option value="administrativo">Administrativo</option>
                        <option value="ambos">Ambos</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="modelo_orcamento">Modelo de orçamento</label>
                    <select id="modelo_orcamento" name="modelo_orcamento">
                        <option value="simples" selected>Simples</option>
                        <option value="detalhado">Detalhado</option>
                        <option value="interno">Interno</option>
                    </select>
                </div>

                <div class="form-group col-2">
                    <label for="mensagem_pendencia">Mensagem padrão para pendência</label>
                    <textarea id="mensagem_pendencia" name="mensagem_pendencia" rows="4" placeholder="Digite a mensagem padrão usada ao registrar pendências.">Seu processo precisa de complementação documental para continuidade da análise administrativa.</textarea>
                </div>

                <div class="form-actions col-2">
                    <a href="<?= route_url('administrativo', 'dashboard') ?>" class="btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-primary">Salvar Alterações</button>
                </div>
            </form>
        </section>

        <section class="bottom-grid">
            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h3>Pontos de configuração</h3>
                        <p>Itens que impactam diretamente o fluxo do administrativo.</p>
                    </div>
                </div>

                <div class="alert-list">
                    <div class="alert-item">
                        <strong>Prazo de retorno</strong>
                        <p>O prazo padrão influencia o nível de cobrança interna e a organização da fila de trabalho.</p>
                        <span class="alert-tag info">Regra operacional</span>
                    </div>

                    <div class="alert-item">
                        <strong>Checklist documental</strong>
                        <p>Essa configuração reduz retrabalho e impede que processos incompletos avancem no fluxo.</p>
                        <span class="alert-tag attention">Importante</span>
                    </div>

                    <div class="alert-item">
                        <strong>Bloqueio de conclusão</strong>
                        <p>Ativar essa regra aumenta a segurança do processo, mas exige mais disciplina do time.</p>
                        <span class="alert-tag urgent">Avaliar uso</span>
                    </div>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h3>Resumo da área</h3>
                        <p>Visão rápida do impacto das configurações atuais.</p>
                    </div>
                </div>

                <div class="config-grid">
                    <div class="setting-block">
                        <h3>Fluxo mais seguro</h3>
                        <p>Com checklist ativo e validação obrigatória, o setor reduz risco de erro na conclusão.</p>
                    </div>

                    <div class="setting-block">
                        <h3>Resposta mais rápida</h3>
                        <p>Alertas automáticos ajudam a equipe a priorizar urgências e controlar a fila.</p>
                    </div>

                    <div class="setting-block">
                        <h3>Padronização</h3>
                        <p>Mensagens e regras padrão deixam o processo mais claro para quem opera o sistema.</p>
                    </div>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>