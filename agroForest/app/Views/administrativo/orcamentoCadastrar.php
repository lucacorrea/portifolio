<?php
$paginaAtual = 'orcamentos';
$paginaTitulo = 'Cadastrar Orçamento';
$paginaDescricao = 'Crie um orçamento a partir de um protocolo recebido pela recepção.';
$usuarioNome = 'Paulo Martins';
$usuarioCargo = 'Administrativo';
$textoBotaoAcao = 'Voltar para Orçamentos';
$linkBotaoAcao = route_url('administrativo', 'orcamentos');
$tituloPagina = 'Administrativo - Cadastrar Orçamento';
$cssPagina = 'assets/css/administrativo/styleadm.css';

require dirname(__DIR__) . '/layouts/header.php';
?>

<div class="layout">
    <?php require __DIR__ . '/includes/sidebar.php'; ?>

    <main class="content">
        <?php require __DIR__ . '/includes/topbar.php'; ?>

        <section class="stats-grid">
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-primary">💰</div><span class="trend up">novo</span></div><h3>ORC</h3><p>Novo registro de orçamento</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-secondary">📥</div><span class="trend up">fila</span></div><h3>06</h3><p>Protocolos disponíveis para orçamento</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-accent">📎</div><span class="trend warn">revisar</span></div><h3>03</h3><p>Com anexos para conferência</p></article>
            <article class="card stat-card"><div class="stat-top"><div class="stat-icon soft-danger">⚠️</div><span class="trend down">atenção</span></div><h3>02</h3><p>Solicitações prioritárias</p></article>
        </section>

        <section class="card panel">
            <div class="panel-header">
                <div>
                    <h2>Dados do orçamento</h2>
                    <p>Preencha as informações principais para registrar uma proposta administrativa.</p>
                </div>
            </div>

            <form class="form-grid" method="POST" action="">
                <div class="form-group">
                    <label for="protocolo">Protocolo vinculado</label>
                    <select id="protocolo" name="protocolo">
                        <option value="">Selecione</option>
                        <option value="PRT-2026-0501">PRT-2026-0501 - Carlos Henrique</option>
                        <option value="PRT-2026-0502">PRT-2026-0502 - Fernanda Martins</option>
                        <option value="PRT-2026-0503">PRT-2026-0503 - Ana Beatriz Costa</option>
                    </select>
                </div>
                <div class="form-group"><label for="cliente">Cliente</label><input type="text" id="cliente" name="cliente" placeholder="Nome do cliente"></div>
                <div class="form-group"><label for="responsavel">Responsável</label><input type="text" id="responsavel" name="responsavel" value="Paulo Martins"></div>
                <div class="form-group"><label for="categoria">Categoria</label><select id="categoria" name="categoria"><option>Simples</option><option>Detalhado</option><option>Urgente</option></select></div>
                <div class="form-group col-2"><label for="servico">Serviço</label><input type="text" id="servico" name="servico" placeholder="Descrição do serviço orçado"></div>
                <div class="form-group"><label for="valor">Valor estimado</label><input type="text" id="valor" name="valor" placeholder="R$ 0,00"></div>
                <div class="form-group"><label for="prazo">Prazo de retorno</label><input type="date" id="prazo" name="prazo"></div>
                <div class="form-group"><label for="prioridade">Prioridade</label><select id="prioridade" name="prioridade"><option>Normal</option><option>Média</option><option>Alta</option></select></div>
                <div class="form-group"><label for="status">Status inicial</label><select id="status" name="status"><option>Em elaboração</option><option>Aguardando aprovação</option><option>Urgente</option></select></div>
                <div class="form-group col-2"><label for="itens">Itens inclusos</label><textarea id="itens" name="itens" rows="4" placeholder="Liste itens, serviços e condições contempladas"></textarea></div>
                <div class="form-group col-2"><label for="observacoes">Observações</label><textarea id="observacoes" name="observacoes" rows="4" placeholder="Notas internas do administrativo"></textarea></div>
                <div class="form-actions col-2">
                    <a href="<?= route_url('administrativo', 'orcamentos') ?>" class="btn-secondary">Cancelar</a>
                    <button type="submit" class="btn-primary">Salvar Orçamento</button>
                </div>
            </form>
        </section>

        <?php require __DIR__ . '/includes/footer.php'; ?>
    </main>
</div>

<?php require dirname(__DIR__) . '/layouts/footer.php'; ?>
