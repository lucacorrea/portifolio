<?php
require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'caixa';
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Caixa | Arte&Flor</title>

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">

  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/pages.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">

  <style>
    .cashier-page {
      --cashier-green: #4F8F6B;
      --cashier-green-dark: #2F6B4C;
      --cashier-green-soft: #EAF6EA;
      --cashier-cream: #FFF8F0;
      --cashier-pink: #FBE8EF;
      --cashier-wine: #8A4A5B;
      --cashier-red: #B42318;
      --cashier-red-soft: #FEE4E2;
      --cashier-blue: #2563EB;
      --cashier-blue-soft: #EFF6FF;
      --cashier-yellow: #B7791F;
      --cashier-yellow-soft: #FFF7D6;
      --cashier-border: rgba(79, 143, 107, .16);
      --cashier-shadow: 0 18px 50px rgba(65, 48, 35, .10);
      --cashier-shadow-strong: 0 28px 80px rgba(65, 48, 35, .16);
      --cashier-radius: 26px;
    }

    .cashier-toolbar {
      display: flex;
      flex-wrap: wrap;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 24px;
    }

    .cashier-toolbar-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
    }

    .cashier-status-card {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 16px;
      align-items: center;
      padding: 18px;
      border-radius: 24px;
      background:
        radial-gradient(circle at top right, rgba(245, 198, 214, .24), transparent 12rem),
        linear-gradient(135deg, rgba(255,255,255,.94), rgba(234,246,234,.82));
      border: 1px solid var(--cashier-border);
      box-shadow: var(--cashier-shadow);
    }

    .cashier-status-card strong {
      display: block;
      color: var(--verde-profundo, #254736);
      font-size: 1.1rem;
    }

    .cashier-status-card span {
      color: var(--grafite-medio, #666);
      font-size: .92rem;
      font-weight: 700;
    }

    .cashier-open-badge {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 9px 13px;
      border-radius: 999px;
      color: var(--cashier-green-dark);
      background: var(--cashier-green-soft);
      border: 1px solid rgba(79,143,107,.20);
      font-weight: 900;
      white-space: nowrap;
    }

    .cashier-open-badge::before {
      content: "";
      width: 9px;
      height: 9px;
      border-radius: 50%;
      background: var(--cashier-green);
      box-shadow: 0 0 0 5px rgba(79,143,107,.12);
    }

    .cashier-kpis {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 18px;
      margin-bottom: 24px;
    }

    .cashier-kpi {
      position: relative;
      overflow: hidden;
      min-height: 150px;
      padding: 22px;
      border-radius: var(--cashier-radius);
      background: rgba(255,255,255,.92);
      border: 1px solid var(--cashier-border);
      box-shadow: var(--cashier-shadow);
    }

    .cashier-kpi::after {
      content: "";
      position: absolute;
      right: -38px;
      top: -38px;
      width: 120px;
      height: 120px;
      border-radius: 50%;
      background: rgba(79,143,107,.10);
    }

    .cashier-kpi span {
      display: block;
      color: var(--grafite-medio, #666);
      font-size: .88rem;
      font-weight: 850;
      margin-bottom: 8px;
    }

    .cashier-kpi strong {
      display: block;
      color: var(--verde-profundo, #254736);
      font-family: "Playfair Display", Georgia, serif;
      font-size: clamp(1.7rem, 3vw, 2.35rem);
      line-height: 1;
    }

    .cashier-kpi small {
      display: inline-flex;
      margin-top: 12px;
      padding: 6px 10px;
      border-radius: 999px;
      background: var(--cashier-green-soft);
      color: var(--cashier-green-dark);
      font-weight: 900;
      font-size: .76rem;
    }

    .finance-positive {
      color: #047857 !important;
    }

    .finance-negative {
      color: var(--cashier-red) !important;
    }

    .cashier-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.15fr) minmax(340px, .85fr);
      gap: 24px;
      align-items: start;
    }

    .cashier-card {
      background: rgba(255,255,255,.92);
      border: 1px solid var(--cashier-border);
      border-radius: var(--cashier-radius);
      box-shadow: var(--cashier-shadow);
      padding: clamp(22px, 3vw, 32px);
    }

    .cashier-card-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 22px;
    }

    .cashier-card-header h2 {
      margin: 8px 0 4px;
      color: var(--verde-profundo, #254736);
      font-family: "Playfair Display", Georgia, serif;
      font-size: clamp(1.5rem, 2.5vw, 2.15rem);
      line-height: 1.1;
    }

    .cashier-card-header p {
      color: var(--grafite-medio, #666);
    }

    .quick-actions {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
      margin-bottom: 24px;
    }

    .quick-action {
      display: grid;
      gap: 8px;
      min-height: 118px;
      padding: 16px;
      border-radius: 22px;
      text-align: left;
      color: var(--verde-profundo, #254736);
      background: rgba(255,255,255,.92);
      border: 1px solid var(--cashier-border);
      box-shadow: 0 12px 32px rgba(65,48,35,.07);
      transition: .22s ease;
    }

    .quick-action:hover {
      transform: translateY(-3px);
      box-shadow: var(--cashier-shadow);
      border-color: rgba(79,143,107,.28);
    }

    .quick-action i {
      display: grid;
      place-items: center;
      width: 42px;
      height: 42px;
      border-radius: 16px;
      background: var(--cashier-green-soft);
      font-style: normal;
      font-size: 1.25rem;
    }

    .quick-action strong {
      font-size: .95rem;
      font-weight: 950;
    }

    .quick-action span {
      color: var(--grafite-medio, #666);
      font-size: .8rem;
      font-weight: 700;
      line-height: 1.35;
    }

    .cashier-form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
    }

    .cashier-form-grid .full {
      grid-column: 1 / -1;
    }

    .cashier-form-grid label {
      display: grid;
      gap: 7px;
      color: var(--verde-profundo, #254736);
      font-weight: 900;
    }

    .cashier-form-grid input,
    .cashier-form-grid select,
    .cashier-form-grid textarea {
      width: 100%;
      min-height: 48px;
      border: 1px solid rgba(79,143,107,.18);
      border-radius: 16px;
      padding: 13px 15px;
      background: rgba(255,255,255,.95);
      color: var(--grafite-suave, #333);
      outline: none;
      transition: .2s ease;
    }

    .cashier-form-grid textarea {
      min-height: 112px;
      resize: vertical;
    }

    .cashier-form-grid input:focus,
    .cashier-form-grid select:focus,
    .cashier-form-grid textarea:focus {
      border-color: var(--cashier-green);
      box-shadow: 0 0 0 4px rgba(79,143,107,.10);
      background: #fff;
    }

    .payment-methods {
      grid-column: 1 / -1;
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 12px;
    }

    .payment-method {
      position: relative;
      cursor: pointer;
    }

    .payment-method input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .payment-method span {
      display: grid;
      gap: 4px;
      min-height: 96px;
      padding: 14px;
      border-radius: 18px;
      background: rgba(255,255,255,.92);
      border: 1px solid rgba(79,143,107,.16);
      transition: .2s ease;
    }

    .payment-method b {
      color: var(--verde-profundo, #254736);
      font-size: .94rem;
    }

    .payment-method small {
      color: var(--grafite-medio, #666);
      font-weight: 700;
      line-height: 1.3;
    }

    .payment-method input:checked + span {
      border-color: var(--cashier-green);
      background:
        radial-gradient(circle at top right, rgba(245,198,214,.24), transparent 10rem),
        var(--cashier-green-soft);
      box-shadow: 0 12px 32px rgba(79,143,107,.13);
    }

    .pix-panel {
      grid-column: 1 / -1;
      display: none;
      grid-template-columns: 220px minmax(0, 1fr);
      gap: 18px;
      align-items: stretch;
      padding: 20px;
      border-radius: 24px;
      background:
        radial-gradient(circle at top right, rgba(245,198,214,.30), transparent 13rem),
        linear-gradient(135deg, var(--cashier-green-soft), #fff);
      border: 1px solid rgba(79,143,107,.20);
    }

    .pix-panel.is-visible {
      display: grid;
      animation: pixEnter .24s ease;
    }

    @keyframes pixEnter {
      from {
        opacity: 0;
        transform: translateY(8px);
      }

      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .pix-qr {
      position: relative;
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 7px;
      aspect-ratio: 1;
      padding: 18px;
      border-radius: 24px;
      background:
        linear-gradient(90deg, rgba(79,143,107,.13) 50%, transparent 50%),
        linear-gradient(rgba(79,143,107,.13) 50%, transparent 50%),
        #fff;
      background-size: 20px 20px;
      border: 1px solid rgba(79,143,107,.20);
      box-shadow: inset 0 0 0 12px rgba(234,246,234,.72);
      overflow: hidden;
    }

    .pix-qr span {
      border-radius: 6px;
      background: var(--verde-profundo, #254736);
      opacity: .92;
    }

    .pix-qr span:nth-child(2n) {
      background: var(--cashier-green);
    }

    .pix-qr span:nth-child(3n) {
      background: transparent;
      border: 1px solid rgba(79,143,107,.24);
      opacity: .32;
    }

    .pix-qr strong {
      position: absolute;
      inset: 50%;
      width: 70px;
      height: 70px;
      translate: -50% -50%;
      display: grid;
      place-items: center;
      border-radius: 20px;
      color: var(--verde-profundo, #254736);
      background: #fff;
      border: 1px solid rgba(79,143,107,.18);
      box-shadow: 0 10px 24px rgba(37,71,54,.15);
      font-weight: 950;
      letter-spacing: .08em;
    }

    .pix-info {
      display: grid;
      gap: 10px;
      align-content: start;
    }

    .pix-info small {
      color: var(--grafite-medio, #666);
      font-size: .72rem;
      font-weight: 950;
      letter-spacing: .05em;
      text-transform: uppercase;
    }

    .pix-info code {
      display: block;
      padding: 12px;
      border-radius: 14px;
      background: #fff;
      border: 1px dashed rgba(79,143,107,.35);
      color: var(--verde-profundo, #254736);
      font-size: .78rem;
      word-break: break-all;
      line-height: 1.45;
    }

    .cashier-actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 18px;
    }

    .cashier-side {
      display: grid;
      gap: 20px;
    }

    .cashier-summary-card {
      background:
        radial-gradient(circle at top right, rgba(245,198,214,.25), transparent 12rem),
        rgba(255,255,255,.92);
    }

    .cashier-summary-list {
      display: grid;
      gap: 12px;
      margin-top: 16px;
    }

    .cashier-summary-line {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      padding: 12px 0;
      border-bottom: 1px solid rgba(79,143,107,.10);
    }

    .cashier-summary-line span {
      color: var(--grafite-medio, #666);
      font-weight: 800;
    }

    .cashier-summary-line strong {
      color: var(--verde-profundo, #254736);
      font-weight: 950;
    }

    .cashier-total-box {
      margin-top: 18px;
      padding: 18px;
      border-radius: 22px;
      background: var(--cashier-green-soft);
      border: 1px solid rgba(79,143,107,.18);
    }

    .cashier-total-box span {
      display: block;
      color: var(--grafite-medio, #666);
      font-weight: 800;
      margin-bottom: 4px;
    }

    .cashier-total-box strong {
      color: var(--verde-profundo, #254736);
      font-family: "Playfair Display", Georgia, serif;
      font-size: 2.2rem;
      line-height: 1;
    }

    .cashier-alert {
      padding: 16px;
      border-radius: 20px;
      border: 1px solid rgba(183,121,31,.20);
      background: var(--cashier-yellow-soft);
      color: #7C4A03;
      font-weight: 800;
    }

    .cashier-alert strong {
      display: block;
      margin-bottom: 4px;
    }

    .history-panel {
      margin-top: 24px;
    }

    .history-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 16px;
      margin-bottom: 16px;
    }

    .history-header h2 {
      color: var(--verde-profundo, #254736);
      font-family: "Playfair Display", Georgia, serif;
      font-size: 2rem;
    }

    .table-wrap {
      overflow-x: auto;
      border-radius: 24px;
      border: 1px solid var(--cashier-border);
      background: rgba(255,255,255,.92);
    }

    table {
      width: 100%;
      min-width: 860px;
      border-collapse: collapse;
    }

    th,
    td {
      padding: 15px 16px;
      text-align: left;
      border-bottom: 1px solid rgba(79,143,107,.10);
      vertical-align: middle;
    }

    th {
      color: var(--verde-profundo, #254736);
      background: rgba(234,246,234,.68);
      font-size: .76rem;
      letter-spacing: .07em;
      text-transform: uppercase;
    }

    tbody tr:hover td {
      background: rgba(234,246,234,.30);
    }

    .status-pill {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      width: fit-content;
      padding: 6px 10px;
      border-radius: 999px;
      font-weight: 900;
      font-size: .75rem;
    }

    .status-paid {
      color: #047857;
      background: #D1FAE5;
    }

    .status-pending {
      color: #92400E;
      background: #FEF3C7;
    }

    .status-out {
      color: var(--cashier-red);
      background: var(--cashier-red-soft);
    }

    .toast {
      position: fixed;
      right: 18px;
      bottom: 18px;
      z-index: 999;
      max-width: min(380px, calc(100% - 36px));
      padding: 14px 16px;
      border-radius: 18px;
      color: var(--verde-profundo, #254736);
      background: rgba(255,255,255,.96);
      border: 1px solid var(--cashier-border);
      box-shadow: var(--cashier-shadow-strong);
      font-weight: 850;
      opacity: 0;
      pointer-events: none;
      transform: translateY(12px);
      transition: .24s ease;
    }

    .toast.is-visible {
      opacity: 1;
      transform: translateY(0);
    }

    @media (max-width: 1180px) {
      .cashier-kpis,
      .quick-actions {
        grid-template-columns: repeat(2, minmax(0, 1fr));
      }

      .cashier-layout {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 760px) {
      .cashier-toolbar,
      .cashier-card-header,
      .history-header,
      .cashier-status-card {
        grid-template-columns: 1fr;
        flex-direction: column;
        align-items: stretch;
      }

      .cashier-toolbar-actions,
      .cashier-actions {
        flex-direction: column;
      }

      .cashier-toolbar-actions .btn,
      .cashier-actions .btn {
        width: 100%;
      }

      .cashier-kpis,
      .quick-actions,
      .cashier-form-grid,
      .payment-methods,
      .pix-panel {
        grid-template-columns: 1fr;
      }

      .admin-main {
        padding-inline: 16px;
      }

      .cashier-card,
      .cashier-kpi {
        border-radius: 22px;
      }
    }
  </style>
</head>

<body class="cashier-page">
<div class="admin-shell">
  <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?>

  <main class="admin-main">
    <div class="cashier-toolbar">
      <div>
        <span class="badge">Financeiro visual</span>
        <h1 class="section-title">Caixa / PDV</h1>
        <p class="muted">Controle demonstrativo para vendas presenciais, Pix, despesas, fechamento diário e atendimento via WhatsApp.</p>
      </div>

      <div class="cashier-toolbar-actions">
        <button class="btn btn-soft" type="button" data-shortcut="open">Abrir caixa</button>
        <button class="btn btn-outline" type="button" data-shortcut="close">Fechar caixa</button>
        <button class="btn btn-primary" type="button" data-shortcut="report">Resumo do dia</button>
      </div>
    </div>

    <section class="cashier-status-card" aria-label="Status do caixa">
      <div>
        <strong>Caixa aberto para demonstração</strong>
        <span>Operador: Admin Arte&Flor · Abertura: 08:30 · Unidade: Coari-AM</span>
      </div>
      <div class="cashier-open-badge">Aberto agora</div>
    </section>

    <section class="cashier-kpis" style="margin-top: 24px;">
      <article class="cashier-kpi">
        <span>Recebimentos</span>
        <strong class="finance-positive">R$ 820,00</strong>
        <small>+12 vendas hoje</small>
      </article>

      <article class="cashier-kpi">
        <span>Despesas</span>
        <strong class="finance-negative">R$ 140,00</strong>
        <small>2 saídas registradas</small>
      </article>

      <article class="cashier-kpi">
        <span>Saldo visual</span>
        <strong>R$ 680,00</strong>
        <small>Atualizado agora</small>
      </article>

      <article class="cashier-kpi">
        <span>Pix recebido</span>
        <strong>R$ 420,00</strong>
        <small>6 pagamentos Pix</small>
      </article>
    </section>

    <section class="quick-actions" aria-label="Atalhos rápidos do caixa">
      <button class="quick-action" type="button" data-fill="pix">
        <i>💠</i>
        <strong>Venda Pix</strong>
        <span>Preenche uma venda rápida com Pix e exibe QR visual.</span>
      </button>

      <button class="quick-action" type="button" data-fill="dinheiro">
        <i>💵</i>
        <strong>Venda dinheiro</strong>
        <span>Registra atendimento presencial em dinheiro.</span>
      </button>

      <button class="quick-action" type="button" data-fill="despesa">
        <i>📦</i>
        <strong>Despesa</strong>
        <span>Registra saída para embalagem, folhagem ou entrega.</span>
      </button>

      <button class="quick-action" type="button" data-fill="whatsapp">
        <i>💬</i>
        <strong>Pedido WhatsApp</strong>
        <span>Simula pedido vindo do catálogo ou atendimento.</span>
      </button>
    </section>

    <section class="cashier-layout">
      <form class="cashier-card" data-demo-form id="cashierForm">
        <div class="cashier-card-header">
          <div>
            <span class="badge">Registro rápido</span>
            <h2>Nova movimentação</h2>
            <p>Use os atalhos ou preencha manualmente para simular uma venda, despesa ou recebimento.</p>
          </div>
          <span class="status-pill status-paid" id="formStatus">Pronto</span>
        </div>

        <div class="cashier-form-grid">
          <label>
            <span>Tipo de movimentação</span>
            <select name="tipo" id="tipoMovimento" required>
              <option value="Recebimento">Recebimento</option>
              <option value="Despesa">Despesa</option>
              <option value="Ajuste">Ajuste de caixa</option>
            </select>
          </label>

          <label>
            <span>Valor</span>
            <input name="valor" id="valorMovimento" type="number" step="0.01" placeholder="0,00" required>
          </label>

          <label>
            <span>Cliente / fornecedor</span>
            <input name="cliente" id="clienteMovimento" placeholder="Ex: Maria Clara">
          </label>

          <label>
            <span>Data</span>
            <input name="data" id="dataMovimento" type="date" required>
          </label>

          <label class="full">
            <span>Descrição</span>
            <textarea name="descricao" id="descricaoMovimento" placeholder="Ex: Pedido #AF-1025 - Buquê Tons Pastel"></textarea>
          </label>

          <div class="payment-methods">
            <label class="payment-method">
              <input type="radio" name="forma" value="Pix" data-payment-method checked>
              <span>
                <b>💠 Pix</b>
                <small>Exibe QR Code demonstrativo e chave Pix.</small>
              </span>
            </label>

            <label class="payment-method">
              <input type="radio" name="forma" value="Dinheiro" data-payment-method>
              <span>
                <b>💵 Dinheiro</b>
                <small>Ideal para venda presencial.</small>
              </span>
            </label>

            <label class="payment-method">
              <input type="radio" name="forma" value="Cartão" data-payment-method>
              <span>
                <b>💳 Cartão</b>
                <small>Simulação para maquininha.</small>
              </span>
            </label>

            <label class="payment-method">
              <input type="radio" name="forma" value="WhatsApp" data-payment-method>
              <span>
                <b>💬 WhatsApp</b>
                <small>Pedido vindo do catálogo.</small>
              </span>
            </label>
          </div>

          <div class="pix-panel" id="pixPanel">
            <div class="pix-qr" role="img" aria-label="QR Code Pix demonstrativo">
              <span></span><span></span><span></span><span></span><span></span><span></span>
              <span></span><span></span><span></span><span></span><span></span><span></span>
              <span></span><span></span><span></span><span></span><span></span><span></span>
              <span></span><span></span><span></span><span></span><span></span><span></span>
              <span></span><span></span><span></span><span></span><span></span><span></span>
              <span></span><span></span><span></span><span></span><span></span><span></span>
              <strong>PIX</strong>
            </div>

            <div class="pix-info">
              <small>Chave Pix demonstrativa</small>
              <strong>arteflor@pix.demo</strong>

              <small>Código copia e cola</small>
              <code id="pixCode">00020126580014BR.GOV.BCB.PIX0136arteflor-demo-caixa5204000053039865802BR5910ARTE E FLOR6005COARI62070503***6304DEMO</code>

              <div class="cashier-actions">
                <button class="btn btn-soft" type="button" id="copyPix">Copiar Pix</button>
                <button class="btn btn-primary" type="button" id="confirmPix">Confirmar pagamento</button>
              </div>

              <p class="muted" id="pixFeedback">Este QR Code é apenas visual para demonstração.</p>
            </div>
          </div>
        </div>

        <div class="cashier-actions">
          <button class="btn btn-primary" type="submit">Registrar demonstração</button>
          <button class="btn btn-soft" type="button" id="clearForm">Limpar campos</button>
          <button class="btn btn-outline" type="button" id="sendWhatsApp">Enviar resumo via WhatsApp</button>
        </div>
      </form>

      <aside class="cashier-side">
        <article class="cashier-card cashier-summary-card">
          <div class="cashier-card-header">
            <div>
              <span class="badge">Resumo</span>
              <h2>Fechamento parcial</h2>
              <p>Valores visuais para apresentação do fluxo do caixa.</p>
            </div>
          </div>

          <div class="cashier-summary-list">
            <div class="cashier-summary-line">
              <span>Pix</span>
              <strong class="finance-positive">R$ 420,00</strong>
            </div>

            <div class="cashier-summary-line">
              <span>Dinheiro</span>
              <strong class="finance-positive">R$ 180,00</strong>
            </div>

            <div class="cashier-summary-line">
              <span>Cartão / presencial</span>
              <strong class="finance-positive">R$ 220,00</strong>
            </div>

            <div class="cashier-summary-line">
              <span>Despesas</span>
              <strong class="finance-negative">- R$ 140,00</strong>
            </div>
          </div>

          <div class="cashier-total-box">
            <span>Saldo estimado</span>
            <strong>R$ 680,00</strong>
          </div>
        </article>

        <article class="cashier-alert">
          <strong>Atenção comercial</strong>
          Esta tela ainda é demonstrativa. Na versão com backend, os registros serão salvos no banco de dados, com controle real de operador, data, pedido e forma de pagamento.
        </article>

        <article class="cashier-card">
          <div class="cashier-card-header">
            <div>
              <span class="badge">Atalhos úteis</span>
              <h2>Ações rápidas</h2>
            </div>
          </div>

          <div class="cashier-actions">
            <a class="btn btn-soft" href="pedidos.php">Ver pedidos</a>
            <a class="btn btn-soft" href="relatorios.php">Relatórios</a>
            <a class="btn btn-soft" href="integracoes.php">Pix e WhatsApp API</a>
          </div>
        </article>
      </aside>
    </section>

    <section class="cashier-card history-panel">
      <div class="history-header">
        <div>
          <span class="badge">Histórico</span>
          <h2>Movimentações do dia</h2>
          <p class="muted">Lista fictícia com entradas, despesas e pedidos para demonstração.</p>
        </div>

        <div class="cashier-toolbar-actions">
          <button class="btn btn-soft" type="button" data-shortcut="filter">Filtrar</button>
          <button class="btn btn-primary" type="button" data-shortcut="export">Exportar</button>
        </div>
      </div>

      <div class="table-wrap">
        <table>
          <thead>
          <tr>
            <th>Horário</th>
            <th>Descrição</th>
            <th>Cliente / fornecedor</th>
            <th>Forma</th>
            <th>Status</th>
            <th>Valor</th>
          </tr>
          </thead>
          <tbody id="historyBody">
          <tr>
            <td>09:20</td>
            <td><strong>Pedido #AF-1025</strong><br><small>Buquê Tons Pastel</small></td>
            <td>Maria Clara</td>
            <td>Pix</td>
            <td><span class="status-pill status-paid">Pago</span></td>
            <td class="finance-positive">R$ 189,90</td>
          </tr>
          <tr>
            <td>10:05</td>
            <td><strong>Compra de embalagens</strong><br><small>Sacos, laços e cartões</small></td>
            <td>Fornecedor local</td>
            <td>Dinheiro</td>
            <td><span class="status-pill status-out">Despesa</span></td>
            <td class="finance-negative">- R$ 48,00</td>
          </tr>
          <tr>
            <td>11:40</td>
            <td><strong>Pedido #AF-1024</strong><br><small>Arranjo Floral Premium</small></td>
            <td>Ana Beatriz</td>
            <td>Presencial</td>
            <td><span class="status-pill status-paid">Pago</span></td>
            <td class="finance-positive">R$ 119,90</td>
          </tr>
          <tr>
            <td>14:15</td>
            <td><strong>Reposição de folhagens</strong><br><small>Entrada para montagem</small></td>
            <td>Fornecedor</td>
            <td>Pix</td>
            <td><span class="status-pill status-out">Despesa</span></td>
            <td class="finance-negative">- R$ 92,00</td>
          </tr>
          </tbody>
        </table>
      </div>
    </section>
  </main>
</div>

<div class="toast" data-toast role="status" aria-live="polite"></div>

<script src="<?= asset('js/app.js') ?>"></script>
<script src="<?= asset('js/admin.js') ?>"></script>

<script>
  (function () {
    const WHATSAPP_NUMBER = '5597000000000'; // Troque pelo WhatsApp da loja.

    const form = document.getElementById('cashierForm');
    const tipo = document.getElementById('tipoMovimento');
    const valor = document.getElementById('valorMovimento');
    const cliente = document.getElementById('clienteMovimento');
    const data = document.getElementById('dataMovimento');
    const descricao = document.getElementById('descricaoMovimento');
    const pixPanel = document.getElementById('pixPanel');
    const pixCode = document.getElementById('pixCode');
    const copyPix = document.getElementById('copyPix');
    const confirmPix = document.getElementById('confirmPix');
    const pixFeedback = document.getElementById('pixFeedback');
    const clearForm = document.getElementById('clearForm');
    const sendWhatsApp = document.getElementById('sendWhatsApp');
    const historyBody = document.getElementById('historyBody');
    const formStatus = document.getElementById('formStatus');
    const toastEl = document.querySelector('[data-toast]');

    const today = new Date().toISOString().slice(0, 10);
    data.value = today;

    const money = (number) => {
      return Number(number || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
      });
    };

    const toast = (message) => {
      if (window.AdminUI && typeof AdminUI.toast === 'function') {
        AdminUI.toast(message);
        return;
      }

      if (!toastEl) return;

      toastEl.textContent = message;
      toastEl.classList.add('is-visible');

      setTimeout(() => {
        toastEl.classList.remove('is-visible');
      }, 3200);
    };

    const getSelectedPayment = () => {
      return document.querySelector('[data-payment-method]:checked')?.value || 'Pix';
    };

    const setPayment = (method) => {
      const input = document.querySelector(`[data-payment-method][value="${method}"]`);
      if (input) {
        input.checked = true;
      }
      togglePix();
    };

    const togglePix = () => {
      const isPix = getSelectedPayment() === 'Pix';
      pixPanel.classList.toggle('is-visible', isPix);
    };

    const setFormStatus = (text, type = 'paid') => {
      formStatus.textContent = text;
      formStatus.classList.remove('status-paid', 'status-pending', 'status-out');

      if (type === 'pending') {
        formStatus.classList.add('status-pending');
      } else if (type === 'out') {
        formStatus.classList.add('status-out');
      } else {
        formStatus.classList.add('status-paid');
      }
    };

    const fillShortcut = (shortcut) => {
      const presets = {
        pix: {
          tipo: 'Recebimento',
          valor: '149.90',
          cliente: 'Maria Clara',
          descricao: 'Pedido #AF-1026 - Buquê de Rosas Vermelhas',
          pagamento: 'Pix',
          status: 'Pix selecionado',
          statusType: 'pending'
        },
        dinheiro: {
          tipo: 'Recebimento',
          valor: '59.90',
          cliente: 'Atendimento presencial',
          descricao: 'Venda presencial - Mini Buquê Delicado',
          pagamento: 'Dinheiro',
          status: 'Venda em dinheiro',
          statusType: 'paid'
        },
        despesa: {
          tipo: 'Despesa',
          valor: '48.00',
          cliente: 'Fornecedor local',
          descricao: 'Compra de embalagens e cartões',
          pagamento: 'Dinheiro',
          status: 'Despesa selecionada',
          statusType: 'out'
        },
        whatsapp: {
          tipo: 'Recebimento',
          valor: '229.90',
          cliente: 'Cliente do WhatsApp',
          descricao: 'Pedido via WhatsApp - Cesta com Flores',
          pagamento: 'WhatsApp',
          status: 'Pedido WhatsApp',
          statusType: 'pending'
        }
      };

      const preset = presets[shortcut];
      if (!preset) return;

      tipo.value = preset.tipo;
      valor.value = preset.valor;
      cliente.value = preset.cliente;
      descricao.value = preset.descricao;
      data.value = today;
      setPayment(preset.pagamento);
      setFormStatus(preset.status, preset.statusType);

      toast('Atalho aplicado ao formulário.');
    };

    document.querySelectorAll('[data-payment-method]').forEach((input) => {
      input.addEventListener('change', togglePix);
    });

    document.querySelectorAll('[data-fill]').forEach((button) => {
      button.addEventListener('click', () => {
        fillShortcut(button.dataset.fill);
      });
    });

    document.querySelectorAll('[data-shortcut]').forEach((button) => {
      button.addEventListener('click', () => {
        const action = button.dataset.shortcut;

        const messages = {
          open: 'Caixa aberto para demonstração.',
          close: 'Fechamento de caixa simulado.',
          report: 'Resumo do dia gerado visualmente.',
          filter: 'Filtros serão conectados na versão com backend.',
          export: 'Exportação será ativada na versão com backend.'
        };

        toast(messages[action] || 'Ação demonstrativa executada.');
      });
    });

    copyPix.addEventListener('click', async () => {
      try {
        await navigator.clipboard.writeText(pixCode.textContent.trim());
        pixFeedback.textContent = 'Código Pix demonstrativo copiado.';
        toast('Código Pix copiado.');
      } catch (error) {
        pixFeedback.textContent = 'Não foi possível copiar automaticamente. Copie manualmente.';
        toast('Copie o código manualmente.');
      }
    });

    confirmPix.addEventListener('click', () => {
      pixFeedback.textContent = 'Pagamento Pix confirmado no sistema demonstrativo.';
      setFormStatus('Pix confirmado', 'paid');
      toast('Pagamento Pix confirmado visualmente.');
    });

    clearForm.addEventListener('click', () => {
      form.reset();
      data.value = today;
      setPayment('Pix');
      setFormStatus('Pronto', 'paid');
      pixFeedback.textContent = 'Este QR Code é apenas visual para demonstração.';
      toast('Campos limpos.');
    });

    sendWhatsApp.addEventListener('click', () => {
      const message = [
        'Olá, segue resumo do caixa Arte&Flor:',
        '',
        `Tipo: ${tipo.value}`,
        `Valor: ${money(valor.value)}`,
        `Cliente/Fornecedor: ${cliente.value || '-'}`,
        `Forma: ${getSelectedPayment()}`,
        `Descrição: ${descricao.value || '-'}`,
        '',
        'Mensagem gerada pela tela demonstrativa do caixa.'
      ].join('\n');

      window.open(`https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`, '_blank', 'noopener');
      toast('Resumo aberto no WhatsApp.');
    });

    form.addEventListener('submit', (event) => {
      event.preventDefault();

      const selectedType = tipo.value;
      const selectedPayment = getSelectedPayment();
      const rawValue = Number(valor.value || 0);
      const isOut = selectedType === 'Despesa';
      const hour = new Date().toLocaleTimeString('pt-BR', {
        hour: '2-digit',
        minute: '2-digit'
      });

      const statusClass = isOut ? 'status-out' : selectedPayment === 'Pix' ? 'status-pending' : 'status-paid';
      const statusText = isOut ? 'Despesa' : selectedPayment === 'Pix' ? 'Aguardando' : 'Pago';
      const valueClass = isOut ? 'finance-negative' : 'finance-positive';
      const signal = isOut ? '- ' : '';

      const row = document.createElement('tr');
      row.innerHTML = `
        <td>${hour}</td>
        <td><strong>${descricao.value || 'Movimentação manual'}</strong><br><small>Registro demonstrativo</small></td>
        <td>${cliente.value || '-'}</td>
        <td>${selectedPayment}</td>
        <td><span class="status-pill ${statusClass}">${statusText}</span></td>
        <td class="${valueClass}">${signal}${money(rawValue)}</td>
      `;

      historyBody.prepend(row);

      setFormStatus('Registrado', isOut ? 'out' : 'paid');
      toast('Movimentação registrada na demonstração.');
    });

    togglePix();
  })();
</script>
</body>
</html>