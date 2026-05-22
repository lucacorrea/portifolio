<?php
$pageTitle = 'Configurações';
$activePage = 'configuracoes';
$pageCss = ['tables'];
$pageJs = [];
include 'includes/header.php';
include 'includes/sidebar.php';
?>
<main class="main">
  <?php include 'includes/topbar.php'; ?>
  <section class="page-header"><div><span class="eyebrow">Administração</span><h1>Configurações</h1><p>Configure dados da empresa, mensagens de WhatsApp, preferências de PDF e opções fiscais.</p></div><div class="page-header__actions"><button class="btn btn--primary">Salvar alterações</button></div></section>
  <section class="grid-2">
    <article class="panel"><div class="panel__header"><div><span class="eyebrow">Empresa</span><h2>Dados da K.Yamaguchi</h2></div></div><div class="form-grid"><label class="field"><span>Nome fantasia</span><input value="K.Yamaguchi Refrigeração"></label><label class="field"><span>CNPJ</span><input placeholder="00.000.000/0001-00"></label><label class="field"><span>WhatsApp</span><input value="+55 92 90000-0000"></label><label class="field"><span>E-mail</span><input value="contato@kyamaguchi.com.br"></label><label class="field field--full"><span>Endereço</span><input placeholder="Rua, número, bairro, cidade"></label></div></article>
    <article class="panel"><div class="panel__header"><div><span class="eyebrow">WhatsApp</span><h2>Envio de orçamento</h2><p>Para envio automático de documento, configure WhatsApp Business Cloud API.</p></div></div><div class="form-grid"><label class="field"><span>Telefone remetente</span><input placeholder="Phone Number ID"></label><label class="field"><span>Token API</span><input type="password" placeholder="Token seguro"></label><label class="field field--full"><span>Mensagem padrão</span><textarea>Olá, {cliente}! Segue o orçamento {numero} no valor de {valor}. Acesse o PDF: {link}</textarea></label></div></article>
    <article class="panel"><div class="panel__header"><div><span class="eyebrow">PDF</span><h2>Preferências do orçamento</h2></div></div><div class="form-grid"><label class="field"><span>Validade padrão</span><select><option>7 dias</option><option selected>15 dias</option><option>30 dias</option></select></label><label class="field"><span>Numeração</span><input value="ORC-{ANO}-{SEQ}"></label><label class="field field--full"><span>Rodapé</span><textarea>Documento gerado pela K.Yamaguchi Service. Obrigado pela preferência.</textarea></label></div></article>
    <article class="panel"><div class="panel__header"><div><span class="eyebrow">Fiscal</span><h2>Integração futura</h2></div></div><div class="detail-card"><div class="detail-card__line"><span>NFS-e</span><strong>Preparado</strong></div><div class="detail-card__line"><span>NF-e</span><strong>Preparado</strong></div><div class="detail-card__line"><span>Certificado digital</span><strong>Pendente</strong></div><div class="detail-card__line"><span>Provedor fiscal</span><strong>Não configurado</strong></div></div></article>
  </section>
</main>
<?php include 'includes/footer.php'; ?>
