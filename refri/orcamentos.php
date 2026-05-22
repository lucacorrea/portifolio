<?php
$pageTitle = 'Orçamentos';
$activePage = 'orcamentos';
$pageCss = ['tables'];
$pageJs = ['tables','orcamentos'];
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>
  <section class="page-header"><div><span class="eyebrow">Comercial técnico</span><h1>Orçamentos</h1><p>Crie orçamentos com serviços e peças, gere PDF e envie ao cliente pelo WhatsApp.</p></div><div class="page-header__actions"><button class="btn btn--primary" data-modal="orcamento">+ Novo Orçamento</button><button class="btn btn--secondary" id="btnDemoWhatsapp">Gerar PDF e WhatsApp</button></div></section>
  <section class="grid-4 module-summary" id="summaryCards"></section>
  <section class="panel flow-panel"><div class="panel__header"><div><span class="eyebrow">Fluxo do orçamento</span><h2>Da proposta à OS</h2><p>O botão WhatsApp gera o PDF e abre a conversa com mensagem pronta. Envio automático real de documento exige WhatsApp Business API configurado.</p></div></div><div class="stepper"><div class="step is-active"><strong>1. Cliente</strong><span>Selecionar ou cadastrar</span></div><div class="step"><strong>2. Serviços</strong><span>Mão de obra e descrição</span></div><div class="step"><strong>3. Peças</strong><span>Itens e quantidades</span></div><div class="step"><strong>4. PDF</strong><span>Documento profissional</span></div><div class="step"><strong>5. WhatsApp</strong><span>Envio com link seguro</span></div></div></section>
  <section class="filter-panel"><div class="filter-grid"><label class="field"><span>Buscar</span><input id="tableSearch" type="search" placeholder="Número, cliente, telefone..."></label><label class="field"><span>Status</span><select id="filterStatus"><option value="">Todos</option><option>Rascunho</option><option>Enviado</option><option>Aguardando aprovação</option><option>Aprovado</option><option>Recusado</option></select></label><label class="field"><span>Responsável</span><select><option>Todos</option><option>Administrativo</option><option>Leonardo</option></select></label><label class="field"><span>Data inicial</span><input type="date"></label><label class="field"><span>Data final</span><input type="date"></label><button class="btn btn--primary" id="btnFilter">Filtrar</button><button class="btn btn--secondary" id="btnClear">Limpar</button></div></section>
  <section class="panel"><div class="panel__header"><div><span class="eyebrow">Propostas</span><h2>Orçamentos cadastrados</h2></div><div class="action-strip"><button class="btn btn--secondary btn--sm">Exportar</button></div></div><div class="responsive-table"><table><thead><tr><th>Orçamento</th><th>Cliente</th><th>Validade</th><th>Status</th><th>Total</th><th>Responsável</th><th>Ações</th></tr></thead><tbody id="tableBody" data-endpoint="orcamentos"></tbody></table></div><div class="pagination"><span id="tableCount">Carregando...</span><div class="action-strip"><button class="btn btn--secondary btn--sm">Anterior</button><button class="btn btn--secondary btn--sm">Próxima</button></div></div></section>
</main>
<?php include 'includes/footer.php'; ?>
