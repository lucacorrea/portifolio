<?php require_once __DIR__ . '/../includes/helpers.php'; ?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Caixa / PDV | Arte&Flor</title>
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
        <span class="badge">Caixa / PDV</span>
        <h1 class="section-title">Caixa moderno</h1>
        <p class="muted">Tela visual para registrar vendas presenciais, Pix manual, despesas e fechamento diário.</p>
      </div>
      <button class="btn btn-primary" type="button">Abrir caixa</button>
    </div>

    <section class="grid-4" style="margin-bottom:24px">
      <div class="card kpi"><span>Saldo aberto</span><strong>R$ 680</strong></div>
      <div class="card kpi"><span>Recebido em Pix</span><strong>R$ 420</strong></div>
      <div class="card kpi"><span>Presencial</span><strong>R$ 260</strong></div>
      <div class="card kpi"><span>Pedidos no caixa</span><strong>12</strong></div>
    </section>

    <section class="cashier-layout">
      <div class="card cashier-panel">
        <div class="cashier-topline">
          <div>
            <span class="badge">Venda rápida</span>
            <h2>Registrar atendimento</h2>
          </div>
          <span class="status">Caixa aberto</span>
        </div>

        <div class="form-grid">
          <label class="form-group"><span>Cliente</span><input placeholder="Nome do cliente"></label>
          <label class="form-group"><span>WhatsApp</span><input placeholder="(97) 00000-0000"></label>
          <label class="form-group"><span>Produto / serviço</span><input placeholder="Buquê, arranjo, entrega..."></label>
          <label class="form-group"><span>Valor</span><input type="number" step="0.01" placeholder="149.90"></label>
          <label class="form-group"><span>Forma de recebimento</span><select><option>Pix</option><option>Dinheiro</option><option>Cartão presencial</option><option>Pedido pelo WhatsApp</option></select></label>
          <label class="form-group"><span>Status</span><select><option>Aguardando pagamento</option><option>Pago</option><option>Reservado</option><option>Cancelado</option></select></label>
          <label class="form-group full"><span>Observação</span><textarea placeholder="Ex: entrega no bairro Centro às 16h, incluir cartão"></textarea></label>
        </div>

        <div class="actions">
          <button class="btn btn-primary" type="button">Registrar venda</button>
          <button class="btn btn-soft" type="button">Registrar despesa</button>
          <button class="btn btn-outline" type="button">Fechar caixa</button>
        </div>
      </div>

      <aside class="card pix-box">
        <span class="badge">Pix manual</span>
        <h2>Pagamento Pix</h2>
        <p class="muted">Nesta fase é uma simulação visual. No backend real pode gerar QR Code dinâmico por API.</p>
        <div class="qr-placeholder">PIX</div>
        <div class="pix-key-box">
          <small>Chave Pix demonstrativa</small>
          <strong>arteflor@email.com</strong>
        </div>
        <button class="btn btn-primary" type="button">Copiar chave Pix</button>
        <button class="btn btn-soft" type="button">Marcar como pago</button>
      </aside>
    </section>

    <section class="card" style="margin-top:24px">
      <div class="cashier-topline">
        <div>
          <h2>Movimentações do dia</h2>
          <p class="muted">Histórico visual de recebimentos, despesas e pedidos presenciais.</p>
        </div>
        <button class="btn btn-soft" type="button">Exportar relatório</button>
      </div>
      <div class="table-wrap">
        <table>
          <tr><th>Hora</th><th>Descrição</th><th>Forma</th><th>Status</th><th>Valor</th></tr>
          <tr><td>09:20</td><td>Buquê Tons Pastel</td><td>Pix</td><td><span class="status">Pago</span></td><td>R$ 119,90</td></tr>
          <tr><td>10:15</td><td>Entrega Centro</td><td>Presencial</td><td><span class="status">Pago</span></td><td>R$ 15,00</td></tr>
          <tr><td>11:40</td><td>Cesta com Flores</td><td>WhatsApp</td><td><span class="status">Aguardando</span></td><td>R$ 229,90</td></tr>
        </table>
      </div>
    </section>
  </main>
</div>
</body>
</html>
