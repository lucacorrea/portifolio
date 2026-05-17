<?php require_once __DIR__ . '/../includes/helpers.php'; ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Pix e WhatsApp API | Arte&Flor</title>
  <link rel="stylesheet" href="../assets/css/base.css">
  <link rel="stylesheet" href="../assets/css/layout.css">
  <link rel="stylesheet" href="../assets/css/components.css">
  <link rel="stylesheet" href="../assets/css/pages.css">
  <link rel="stylesheet" href="../assets/css/responsive.css">
</head>
<body>
<div class="admin-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>
  <main class="admin-main">
    <div class="admin-header">
      <div>
        <span class="badge">Integrações futuras</span>
        <h1 class="section-title">Pix e WhatsApp API</h1>
        <p class="muted">Tela demonstrativa para deixar claro como o sistema será preparado para pagamentos e comunicação automatizada.</p>
      </div>
      <span class="status">Planejado para backend</span>
    </div>

    <section class="integration-hero card">
      <div>
        <span class="badge">Fluxo comercial</span>
        <h2>Pedido, pagamento e atendimento conectados</h2>
        <p class="muted">No MVP visual, tudo é fictício. Na versão completa, o sistema poderá gerar cobrança Pix, acompanhar status e disparar mensagens oficiais via API.</p>
      </div>
      <div class="integration-flow">
        <span>🛒 Pedido</span>
        <span>→</span>
        <span>💠 Pix</span>
        <span>→</span>
        <span>💬 WhatsApp</span>
      </div>
    </section>

    <section class="integration-grid">
      <article class="card integration-card">
        <div class="integration-icon">💠</div>
        <h2>Pix manual no MVP</h2>
        <p class="muted">Ideal para validar a operação sem custo de gateway. O cliente escolhe Pix, recebe a chave ou QR visual, e a loja confirma manualmente no painel.</p>
        <ul class="feature-list">
          <li>Chave Pix configurável</li>
          <li>Status: aguardando pagamento</li>
          <li>Botão marcar como pago</li>
          <li>Registro no caixa</li>
        </ul>
      </article>

      <article class="card integration-card featured">
        <div class="integration-icon">🔐</div>
        <h2>Pix automático futuro</h2>
        <p class="muted">Na fase backend, pode integrar API de gateway ou banco para gerar QR Code dinâmico e confirmar pagamento automaticamente.</p>
        <ul class="feature-list">
          <li>QR Code dinâmico</li>
          <li>Webhook de confirmação</li>
          <li>Baixa automática do pedido</li>
          <li>Histórico financeiro por pedido</li>
        </ul>
      </article>

      <article class="card integration-card">
        <div class="integration-icon">💬</div>
        <h2>WhatsApp no MVP</h2>
        <p class="muted">O sistema já pode montar mensagens organizadas para o WhatsApp da loja usando links diretos.</p>
        <ul class="feature-list">
          <li>Pedido enviado com produtos</li>
          <li>Dados de entrega</li>
          <li>Forma de pagamento</li>
          <li>Mensagem para cartão</li>
        </ul>
      </article>

      <article class="card integration-card featured">
        <div class="integration-icon">⚙️</div>
        <h2>WhatsApp API futura</h2>
        <p class="muted">Para notificações automáticas reais, será necessário usar WhatsApp Business API ou provedor autorizado.</p>
        <ul class="feature-list">
          <li>Pedido recebido</li>
          <li>Pagamento confirmado</li>
          <li>Pedido em preparo</li>
          <li>Saiu para entrega</li>
        </ul>
      </article>
    </section>

    <section class="card" style="margin-top:24px">
      <div class="cashier-topline">
        <div>
          <h2>Configurações demonstrativas</h2>
          <p class="muted">Campos visuais para apresentar à cliente como ficará a configuração das integrações no sistema completo.</p>
        </div>
        <button class="btn btn-primary" type="button">Salvar demonstração</button>
      </div>

      <div class="form-grid" style="margin-top:20px">
        <label class="form-group"><span>Chave Pix da loja</span><input placeholder="email, CPF/CNPJ ou chave aleatória"></label>
        <label class="form-group"><span>Nome do recebedor</span><input placeholder="Arte&Flor"></label>
        <label class="form-group"><span>Número WhatsApp da loja</span><input placeholder="5597000000000"></label>
        <label class="form-group"><span>Modelo de atendimento</span><select><option>Link direto WhatsApp</option><option>WhatsApp Business API futura</option><option>Provedor externo futuro</option></select></label>
        <label class="form-group full"><span>Mensagem padrão de pedido</span><textarea>Olá, seu pedido na Arte&Flor foi recebido. Em breve confirmaremos disponibilidade, pagamento e entrega.</textarea></label>
      </div>
    </section>
  </main>
</div>
</body>
</html>
