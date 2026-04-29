<?php
$paginaAtual = 'orcamentos';
$paginaTitulo = 'Editar Orçamento';
$paginaDescricao = 'Edite os dados do orçamento selecionado e atualize as informações da análise administrativa.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Voltar para Orçamentos';
$linkBotaoAcao = route_url('administrativo', 'orcamentos');
$tituloPagina = 'Administrativo - Editar Orçamento';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-primary">✏️</div>
                    <span class="trend up">edição</span>
                </div>
                <h3>ORC-2026-0102</h3>
                <p>Orçamento selecionado para atualização</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-secondary">📄</div>
                    <span class="trend up">vinculado</span>
                </div>
                <h3>PRT-2026-0502</h3>
                <p>Protocolo relacionado</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-accent">⏱️</div>
                    <span class="trend warn">atenção</span>
                </div>
                <h3>28/04/2026</h3>
                <p>Prazo atual de retorno</p>
            </article>

            <article class="card stat-card">
                <div class="stat-top">
                    <div class="stat-icon soft-danger">⚠️</div>
                    <span class="trend down">urgente</span>
                </div>
                <h3>Alta</h3>
                <p>Prioridade atual do orçamento</p>
            </article>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div>
                    <h2>Edição do orçamento</h2>
                    <p>Atualize os dados do orçamento conforme o andamento da análise administrativa.</p>
                </div>
            </div>

            <form class="form-grid" method="POST" action="">
                <div class="form-group">
                    <label for="codigo">Código do orçamento</label>
                    <input type="text" id="codigo" name="codigo" value="ORC-2026-0102" readonly>
                </div>

                <div class="form-group">
                    <label for="protocolo">Protocolo</label>
                    <input type="text" id="protocolo" name="protocolo" value="PRT-2026-0502" readonly>
                </div>

                <div class="form-group">
                    <label for="cliente">Cliente</label>
                    <input type="text" id="cliente" name="cliente" value="Fernanda Martins">
                </div>

                <div class="form-group">
                    <label for="responsavel">Responsável</label>
                    <input type="text" id="responsavel" name="responsavel" value="Paulo Martins">
                </div>

                <div class="form-group col-2">
                    <label for="servico">Serviço</label>
                    <input type="text" id="servico" name="servico" value="Atendimento prioritário">
                </div>

                <div class="form-group">
                    <label for="categoria">Categoria do orçamento</label>
                    <select id="categoria" name="categoria">
                        <option value="simples">Simples</option>
                        <option value="detalhado">Detalhado</option>
                        <option value="urgente" selected>Urgente</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="prioridade">Prioridade</label>
                    <select id="prioridade" name="prioridade">
                        <option value="normal">Normal</option>
                        <option value="media">Média</option>
                        <option value="alta" selected>Alta</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="valor">Valor estimado</label>
                    <input type="text" id="valor" name="valor" value="R$ 3.980,00">
                </div>

                <div class="form-group">
                    <label for="prazoRetorno">Prazo de retorno</label>
                    <input type="date" id="prazoRetorno" name="prazoRetorno" value="2026-04-28">
                </div>

                <div class="form-group">
                    <label for="statusAtual">Status atual</label>
                    <select id="statusAtual" name="statusAtual">
                        <option value="em_elaboracao">Em elaboração</option>
                        <option value="aguardando_aprovacao">Aguardando aprovação</option>
                        <option value="urgente" selected>Urgente</option>
                        <option value="concluido">Concluído</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="ultimaAtualizacao">Última atualização</label>
                    <input type="text" id="ultimaAtualizacao" name="ultimaAtualizacao" value="29/04/2026 09:30" readonly>
                </div>

                <div class="form-group col-2">
                    <label for="itensInclusos">Itens inclusos</label>
                    <textarea id="itensInclusos" name="itensInclusos" rows="4">Atendimento prioritário, análise documental completa, validação de anexos e retorno administrativo.</textarea>
                </div>

                <div class="form-group col-2">
                    <label for="observacoes">Observações</label>
                    <textarea id="observacoes" name="observacoes" rows="4">Cliente com prioridade alta. Necessário concluir com rapidez e validar documentação antes do envio final.</textarea>
                </div>

                <div class="form-group col-2">
                    <h3>Checklist de revisão</h3>
                </div>

                <div class="col-2 config-grid">
                    <div class="switch-field">
                        <div class="switch-field-info">
                            <strong>Documentação conferida</strong>
                            <small>Confirme se os anexos do protocolo foram revisados.</small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="documentacaoConferida" checked>
                            <span class="switch-slider"></span>
                        </label>
                    </div>

                    <div class="switch-field">
                        <div class="switch-field-info">
                            <strong>Valores revisados</strong>
                            <small>Valide se o valor final está correto antes de atualizar.</small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="valoresRevisados" checked>
                            <span class="switch-slider"></span>
                        </label>
                    </div>

                    <div class="switch-field">
                        <div class="switch-field-info">
                            <strong>Prazo confirmado</strong>
                            <small>Garanta que a data de retorno está correta.</small>
                        </div>
                        <label class="switch">
                            <input type="checkbox" name="prazoConfirmado">
                            <span class="switch-slider"></span>
                        </label>
                    </div>
                </div>

                <div class="form-actions col-2">
                    <a href="<?= route_url('administrativo', 'orcamentos') ?>" class="btn-secondary">Cancelar</a>
                    <a href="<?= route_url('administrativo', 'orcamentoVisualizar') ?>" class="btn-outline">Visualizar</a>
                    <button type="submit" class="btn-primary">Atualizar Orçamento</button>
                </div>
            </form>
        </section>

        <section class="bottom-grid">
            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h3>Histórico recente</h3>
                        <p>Últimos apontamentos do orçamento em edição.</p>
                    </div>
                </div>

                <div class="alert-list">
                    <div class="alert-item">
                        <strong>Cadastro inicial realizado</strong>
                        <p>Orçamento criado com base no protocolo prioritário encaminhado pela recepção.</p>
                        <span class="alert-tag info">Registro inicial</span>
                    </div>

                    <div class="alert-item">
                        <strong>Revisão documental concluída</strong>
                        <p>Os anexos principais foram conferidos e aprovados para continuidade do fluxo.</p>
                        <span class="alert-tag attention">Conferido</span>
                    </div>

                    <div class="alert-item">
                        <strong>Necessita retorno rápido</strong>
                        <p>Por conta da prioridade alta, este orçamento deve ter atualização imediata no setor.</p>
                        <span class="alert-tag urgent">Urgente</span>
                    </div>
                </div>
            </article>

            <article class="card panel">
                <div class="panel-header">
                    <div>
                        <h3>Orientações da edição</h3>
                        <p>Pontos importantes antes de atualizar o registro.</p>
                    </div>
                </div>

                <div class="config-grid">
                    <div class="setting-block">
                        <h3>Evite divergência</h3>
                        <p>Mantenha o protocolo, cliente e descrição do serviço alinhados com a solicitação original.</p>
                    </div>

                    <div class="setting-block">
                        <h3>Revise o valor final</h3>
                        <p>Qualquer ajuste financeiro precisa estar coerente com a análise e com os anexos recebidos.</p>
                    </div>

                    <div class="setting-block">
                        <h3>Atualize o status corretamente</h3>
                        <p>O status certo ajuda o time a acompanhar o fluxo e evita retrabalho na fila administrativa.</p>
                    </div>
                </div>
            </article>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
