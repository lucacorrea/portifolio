<?php
$adminTitle = 'Integrações';
$activeAdmin = 'integracoes';
require_once __DIR__ . '/../includes/admin-head.php';
?>
<section class="admin-page-hero">
  <div class="admin-page-title">
    <span class="badge">Integrações</span>
    <h1>Pix e atendimento</h1>
    <p>Configurações demonstrativas para Pix manual no MVP, Pix automático futuro e WhatsApp apenas como atendimento/notificação.</p>
  </div>
  <span class="status status-warn">MVP sem API real</span>
</section>

<section class="integration-hero card">
  <div>
    <span class="badge">Fluxo correto</span>
    <h2>Venda finalizada no sistema visual</h2>
    <p class="muted">Catálogo, checkout e PDV registram pedidos/vendas no localStorage. WhatsApp permanece como canal secundário de suporte.</p>
  </div>
  <div class="integration-flow">
    <span>Pedido</span>
    <span>Pagamento visual</span>
    <span>Status no painel</span>
  </div>
</section>

<section class="integration-grid">
  <article class="card integration-card">
    <div class="integration-icon">PIX</div>
    <h2>Pix manual no MVP</h2>
    <p class="muted">A loja confirma manualmente o pagamento no painel, sem gateway, sem cobrança real e sem webhook.</p>
    <ul class="feature-list">
      <li>Chave Pix demonstrativa</li>
      <li>QR Code visual</li>
      <li>Status aguardando pagamento</li>
      <li>Confirmação manual</li>
    </ul>
  </article>

  <article class="card integration-card featured">
    <div class="integration-icon">API</div>
    <h2>Pix automático futuro</h2>
    <p class="muted">Na etapa de backend, pode gerar QR dinâmico e confirmar pagamento via API oficial ou gateway.</p>
    <ul class="feature-list">
      <li>QR Code dinâmico</li>
      <li>Webhook de confirmação</li>
      <li>Baixa automática do pedido</li>
      <li>Histórico financeiro</li>
    </ul>
  </article>

  <article class="card integration-card">
    <div class="integration-icon">WA</div>
    <h2>WhatsApp como atendimento</h2>
    <p class="muted">No MVP, o WhatsApp aparece somente para dúvidas, suporte e contato humano. A venda não finaliza por mensagem.</p>
    <ul class="feature-list">
      <li>Dúvidas sobre produto</li>
      <li>Suporte de pedido</li>
      <li>Notificações futuras</li>
      <li>Sem checkout externo</li>
    </ul>
  </article>

  <article class="card integration-card featured">
    <div class="integration-icon">CRM</div>
    <h2>Notificações futuras</h2>
    <p class="muted">Com backend real, a loja poderá avisar cliente sobre preparo, entrega e retirada sem mover a venda para fora do sistema.</p>
    <ul class="feature-list">
      <li>Pedido recebido</li>
      <li>Pagamento confirmado</li>
      <li>Saiu para entrega</li>
      <li>Pedido finalizado</li>
    </ul>
  </article>
</section>

<section class="admin-form-card">
  <div class="admin-panel-header">
    <div><span class="badge">Configurações demonstrativas</span><h2>Campos de integração</h2></div>
    <button class="btn btn-primary" type="button">Salvar demonstração</button>
  </div>
  <div class="admin-form-grid">
    <label class="admin-field"><span>Chave Pix</span><input value="arteflor@pix.demo"></label>
    <label class="admin-field"><span>Nome do recebedor</span><input value="Arte&Flor"></label>
    <label class="admin-field"><span>Modelo de Pix</span><select><option>Manual no MVP</option><option>API futura</option></select></label>
    <label class="admin-field"><span>Canal de atendimento</span><input value="WhatsApp Business"></label>
    <label class="admin-field full"><span>Mensagem de notificação futura</span><textarea>Seu pedido Arte&Flor teve o status atualizado. Acompanhe a evolução pela área do cliente.</textarea></label>
  </div>
</section>
<?php require_once __DIR__ . '/../includes/admin-footer.php'; ?>
