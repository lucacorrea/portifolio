<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout | Arte&Flor</title>
  <meta name="description" content="Checkout demonstrativo Arte&Flor com Pix visual e envio do pedido pelo WhatsApp.">

  <style>
    :root {
      --verde-principal: #4F8F6B;
      --verde-hover: #3D7254;
      --verde-profundo: #254736;
      --verde-pastel: #DDEBDD;
      --verde-menta: #EAF6EA;
      --creme: #FFF8F0;
      --creme-2: #F6EBDD;
      --rosa: #F5C6D6;
      --rosa-suave: #FBE8EF;
      --vinho: #8A4A5B;
      --marrom: #B48A63;
      --branco: #FFFFFF;
      --texto: #303030;
      --texto-suave: #666666;
      --borda: rgba(79, 143, 107, .16);
      --sombra: 0 18px 50px rgba(65, 48, 35, .10);
      --sombra-forte: 0 28px 80px rgba(65, 48, 35, .16);
      --raio: 26px;
      --raio-menor: 16px;
      --fonte-titulo: Georgia, "Times New Roman", serif;
      --fonte: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
    }

    html {
      scroll-behavior: smooth;
    }

    body {
      min-height: 100vh;
      font-family: var(--fonte);
      color: var(--texto);
      background:
        radial-gradient(circle at 10% 10%, rgba(245, 198, 214, .32), transparent 28rem),
        radial-gradient(circle at 88% 12%, rgba(79, 143, 107, .26), transparent 30rem),
        linear-gradient(180deg, var(--creme), #fffdf9 48%, var(--verde-menta));
      line-height: 1.6;
      overflow-x: hidden;
    }

    body::before {
      content: "";
      position: fixed;
      inset: 0;
      z-index: -1;
      pointer-events: none;
      opacity: .45;
      background-image:
        linear-gradient(rgba(79, 143, 107, .05) 1px, transparent 1px),
        linear-gradient(90deg, rgba(79, 143, 107, .05) 1px, transparent 1px);
      background-size: 42px 42px;
      mask-image: linear-gradient(to bottom, #000, transparent 75%);
    }

    a {
      color: inherit;
    }

    img {
      max-width: 100%;
      display: block;
    }

    button,
    input,
    select,
    textarea {
      font: inherit;
    }

    button {
      cursor: pointer;
      border: 0;
    }

    .container {
      width: min(1180px, calc(100% - 36px));
      margin: 0 auto;
    }

    .site-header {
      position: sticky;
      top: 0;
      z-index: 100;
      background: rgba(255, 255, 255, .86);
      backdrop-filter: blur(18px);
      border-bottom: 1px solid var(--borda);
      box-shadow: 0 10px 30px rgba(65, 48, 35, .05);
    }

    .header-inner {
      min-height: 76px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 18px;
    }

    .brand {
      display: inline-flex;
      align-items: center;
      gap: 12px;
      text-decoration: none;
      font-family: var(--fonte-titulo);
      font-weight: 800;
      font-size: 1.45rem;
      color: var(--verde-principal);
      white-space: nowrap;
    }

    .brand span span {
      color: var(--vinho);
    }

    .brand-icon {
      width: 46px;
      height: 46px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: linear-gradient(135deg, var(--verde-menta), var(--verde-pastel));
      box-shadow: inset 0 0 0 1px rgba(79, 143, 107, .18);
    }

    .main-nav {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 7px;
      border-radius: 999px;
      background: rgba(255, 255, 255, .7);
      border: 1px solid var(--borda);
    }

    .main-nav a {
      text-decoration: none;
      padding: 9px 14px;
      border-radius: 999px;
      color: var(--texto-suave);
      font-weight: 800;
      font-size: .9rem;
      transition: .2s;
    }

    .main-nav a:hover,
    .main-nav a.active {
      background: var(--verde-menta);
      color: var(--verde-hover);
    }

    .menu-toggle {
      display: none;
      width: 44px;
      height: 44px;
      border-radius: 14px;
      background: var(--verde-menta);
      color: var(--verde-profundo);
      font-weight: 900;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      min-height: 46px;
      padding: 12px 22px;
      border-radius: 999px;
      font-weight: 900;
      text-decoration: none;
      transition: .22s ease;
      border: 1px solid transparent;
    }

    .btn-primary {
      color: #fff;
      background: linear-gradient(135deg, var(--verde-principal), var(--verde-hover));
      box-shadow: 0 16px 34px rgba(79, 143, 107, .24);
    }

    .btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 22px 54px rgba(79, 143, 107, .32);
    }

    .btn-soft {
      color: var(--verde-hover);
      background: var(--verde-menta);
      border-color: rgba(79, 143, 107, .16);
    }

    .btn-soft:hover {
      background: var(--verde-pastel);
      transform: translateY(-2px);
    }

    .btn-outline {
      color: var(--verde-hover);
      border-color: rgba(79, 143, 107, .35);
      background: rgba(255, 255, 255, .5);
    }

    .btn-outline:hover {
      background: #fff;
      transform: translateY(-2px);
    }

    .page-hero {
      position: relative;
      padding: clamp(52px, 8vw, 92px) 0;
      overflow: hidden;
      border-bottom: 1px solid var(--borda);
      background:
        radial-gradient(circle at 82% 20%, rgba(245, 198, 214, .30), transparent 22rem),
        linear-gradient(135deg, rgba(234, 246, 234, .96), rgba(255, 248, 240, .96));
    }

    .checkout-hero-grid {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 360px;
      gap: 32px;
      align-items: center;
    }

    .badge,
    .eyebrow,
    .status {
      display: inline-flex;
      width: fit-content;
      align-items: center;
      gap: 7px;
      padding: 7px 12px;
      border-radius: 999px;
      background: linear-gradient(135deg, var(--verde-menta), #fff);
      color: var(--verde-hover);
      border: 1px solid var(--borda);
      font-size: .75rem;
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: .06em;
    }

    .badge::before,
    .status::before {
      content: "";
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: var(--verde-principal);
    }

    .status.is-paid {
      color: #14532d;
      background: #dcfce7;
      border-color: #86efac;
    }

    .status.is-waiting {
      color: #92400e;
      background: #fef3c7;
      border-color: #fde68a;
    }

    .section-title {
      margin-top: 14px;
      font-family: var(--fonte-titulo);
      font-size: clamp(2.4rem, 5vw, 4.8rem);
      line-height: 1;
      letter-spacing: -.04em;
      color: var(--verde-profundo);
    }

    .section-subtitle {
      max-width: 720px;
      margin-top: 16px;
      color: var(--texto-suave);
      font-size: 1.08rem;
    }

    .checkout-hero-aside {
      padding: 24px;
      border-radius: var(--raio);
      background:
        linear-gradient(rgba(37, 71, 54, .08), rgba(37, 71, 54, .08)),
        url("https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=700&q=80") center/cover;
      min-height: 250px;
      display: grid;
      align-content: end;
      gap: 10px;
      box-shadow: var(--sombra);
      overflow: hidden;
      position: relative;
    }

    .checkout-hero-aside::before {
      content: "";
      position: absolute;
      inset: 0;
      background: linear-gradient(180deg, transparent 30%, rgba(37, 71, 54, .65));
    }

    .checkout-hero-aside span {
      position: relative;
      z-index: 1;
      width: fit-content;
      padding: 9px 12px;
      border-radius: 999px;
      background: rgba(255, 255, 255, .18);
      color: #fff;
      border: 1px solid rgba(255, 255, 255, .32);
      backdrop-filter: blur(12px);
      font-weight: 900;
    }

    .section {
      padding: clamp(52px, 7vw, 90px) 0;
    }

    .checkout-layout {
      display: grid;
      grid-template-columns: minmax(0, 1.2fr) minmax(320px, .8fr);
      gap: 28px;
      align-items: start;
    }

    .card {
      background: rgba(255, 255, 255, .9);
      border: 1px solid var(--borda);
      border-radius: var(--raio);
      box-shadow: var(--sombra);
      padding: clamp(22px, 3vw, 34px);
    }

    .checkout-summary {
      position: sticky;
      top: 100px;
    }

    .checkout-intro {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 20px;
      align-items: start;
      margin-bottom: 26px;
    }

    .checkout-intro h2,
    .checkout-summary h2,
    .pix-panel-header h2 {
      font-family: var(--fonte-titulo);
      color: var(--verde-profundo);
      font-size: clamp(1.6rem, 3vw, 2.2rem);
      margin-top: 8px;
    }

    .checkout-intro p,
    .muted {
      color: var(--texto-suave);
    }

    .checkout-step-list {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      justify-content: flex-end;
    }

    .checkout-step-list span {
      display: inline-flex;
      align-items: center;
      gap: 7px;
      padding: 8px 11px;
      border-radius: 999px;
      background: var(--verde-menta);
      color: var(--verde-hover);
      font-size: .82rem;
      font-weight: 800;
    }

    .checkout-step-list strong {
      width: 22px;
      height: 22px;
      border-radius: 50%;
      display: grid;
      place-items: center;
      background: #fff;
      color: var(--verde-hover);
      font-size: .72rem;
    }

    .checkout-step-list .is-active {
      color: #fff;
      background: var(--verde-principal);
    }

    .checkout-block {
      border: 1px solid rgba(79, 143, 107, .16);
      border-radius: 22px;
      padding: 22px;
      margin-top: 18px;
      background: rgba(255, 255, 255, .58);
    }

    .checkout-block legend {
      padding: 0 10px;
      font-weight: 900;
      color: var(--verde-profundo);
    }

    .checkout-block legend span {
      margin-right: 8px;
      color: var(--vinho);
    }

    .checkout-block-note {
      color: var(--texto-suave);
      margin: 0 0 14px;
    }

    .form-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 16px;
    }

    .form-group {
      display: grid;
      gap: 7px;
      color: var(--verde-profundo);
      font-weight: 850;
    }

    .form-group.full {
      grid-column: 1 / -1;
    }

    .form-group input,
    .form-group select,
    .form-group textarea {
      width: 100%;
      border: 1px solid rgba(79, 143, 107, .18);
      border-radius: 16px;
      padding: 13px 15px;
      background: rgba(255, 255, 255, .94);
      color: var(--texto);
      outline: none;
      transition: .2s;
    }

    .form-group textarea {
      min-height: 110px;
      resize: vertical;
    }

    .form-group input:focus,
    .form-group select:focus,
    .form-group textarea:focus {
      border-color: var(--verde-principal);
      box-shadow: 0 0 0 4px rgba(79, 143, 107, .10);
      background: #fff;
    }

    .payment-options {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 14px;
    }

    .payment-option {
      position: relative;
      display: grid;
      grid-template-columns: auto 1fr;
      gap: 12px;
      align-items: start;
      padding: 16px;
      border-radius: 20px;
      border: 1px solid rgba(79, 143, 107, .16);
      background: rgba(255, 255, 255, .78);
      transition: .2s;
      cursor: pointer;
    }

    .payment-option:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 32px rgba(65, 48, 35, .08);
    }

    .payment-option input {
      margin-top: 6px;
      accent-color: var(--verde-principal);
    }

    .payment-option span {
      display: grid;
      gap: 3px;
    }

    .payment-option em {
      width: fit-content;
      padding: 5px 9px;
      border-radius: 999px;
      background: var(--verde-menta);
      color: var(--verde-hover);
      font-size: .7rem;
      font-style: normal;
      font-weight: 950;
      letter-spacing: .06em;
    }

    .payment-option strong {
      color: var(--verde-profundo);
      font-size: 1rem;
    }

    .payment-option small {
      color: var(--texto-suave);
      line-height: 1.4;
    }

    .payment-option:has(input:checked) {
      border-color: var(--verde-principal);
      background:
        radial-gradient(circle at top right, rgba(245, 198, 214, .24), transparent 10rem),
        rgba(234, 246, 234, .92);
      box-shadow: 0 16px 42px rgba(79, 143, 107, .16);
    }

    .payment-option-featured {
      border-color: rgba(138, 74, 91, .24);
    }

    .pix-checkout-panel {
      margin-top: 18px;
      padding: 24px;
      border-radius: 24px;
      background:
        radial-gradient(circle at top right, rgba(245, 198, 214, .28), transparent 14rem),
        linear-gradient(135deg, rgba(234, 246, 234, .94), rgba(255, 255, 255, .92));
      border: 1px solid rgba(79, 143, 107, .18);
      box-shadow: inset 0 0 0 1px rgba(255, 255, 255, .65), var(--sombra);
      animation: pixEnter .25s ease;
    }

    .pix-checkout-panel[hidden] {
      display: none !important;
    }

    @keyframes pixEnter {
      from {
        opacity: 0;
        transform: translateY(10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }

    .pix-panel-header {
      display: flex;
      align-items: flex-start;
      justify-content: space-between;
      gap: 18px;
      margin-bottom: 22px;
    }

    .pix-panel-grid {
      display: grid;
      grid-template-columns: 260px minmax(0, 1fr);
      gap: 22px;
      align-items: stretch;
    }

    .pix-qr-demo {
      position: relative;
      width: 100%;
      aspect-ratio: 1;
      border-radius: 28px;
      padding: 22px;
      background:
        linear-gradient(90deg, rgba(79, 143, 107, .13) 50%, transparent 50%),
        linear-gradient(rgba(79, 143, 107, .13) 50%, transparent 50%),
        #fff;
      background-size: 22px 22px;
      border: 1px solid rgba(79, 143, 107, .22);
      box-shadow: inset 0 0 0 14px rgba(234, 246, 234, .72);
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 8px;
      overflow: hidden;
    }

    .pix-qr-demo span {
      border-radius: 7px;
      background: var(--verde-profundo);
      opacity: .88;
    }

    .pix-qr-demo span:nth-child(2n) {
      background: var(--verde-principal);
    }

    .pix-qr-demo span:nth-child(3n) {
      opacity: .16;
      background: transparent;
      border: 1px solid rgba(79, 143, 107, .25);
    }

    .pix-qr-demo strong {
      position: absolute;
      inset: 50%;
      width: 78px;
      height: 78px;
      translate: -50% -50%;
      border-radius: 22px;
      display: grid;
      place-items: center;
      color: var(--verde-profundo);
      background: #fff;
      border: 1px solid rgba(79, 143, 107, .20);
      box-shadow: 0 10px 26px rgba(37, 71, 54, .16);
      font-weight: 950;
      letter-spacing: .08em;
    }

    .pix-payment-box {
      display: grid;
      gap: 12px;
      align-content: start;
      padding: 18px;
      border-radius: 22px;
      background: rgba(255, 255, 255, .78);
      border: 1px solid rgba(79, 143, 107, .14);
    }

    .pix-payment-box small {
      color: var(--texto-suave);
      font-weight: 900;
      text-transform: uppercase;
      letter-spacing: .05em;
      font-size: .7rem;
    }

    .pix-payment-box strong {
      color: var(--verde-profundo);
      font-size: 1.05rem;
    }

    .pix-payment-box code {
      display: block;
      max-height: 92px;
      overflow: auto;
      white-space: normal;
      word-break: break-all;
      padding: 13px;
      border-radius: 16px;
      color: var(--verde-profundo);
      background: var(--verde-menta);
      border: 1px dashed rgba(79, 143, 107, .35);
      font-size: .82rem;
      line-height: 1.5;
    }

    .actions {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      margin-top: 6px;
    }

    .checkout-submit-row {
      margin-top: 22px;
      display: grid;
      gap: 8px;
    }

    .checkout-submit-row p {
      color: var(--texto-suave);
      font-size: .92rem;
    }

    .checkout-summary h2 {
      font-family: var(--fonte-titulo);
      color: var(--verde-profundo);
      font-size: 2rem;
      margin-top: 10px;
    }

    .checkout-summary-item {
      display: grid;
      grid-template-columns: 64px minmax(0, 1fr) auto;
      gap: 12px;
      align-items: center;
      padding: 12px;
      margin: 14px 0;
      border-radius: 18px;
      background: rgba(234, 246, 234, .55);
      border: 1px solid rgba(79, 143, 107, .12);
    }

    .checkout-summary-item img {
      width: 64px;
      height: 64px;
      object-fit: cover;
      border-radius: 16px;
    }

    .checkout-summary-item strong {
      color: var(--verde-profundo);
    }

    .checkout-summary-item span {
      display: block;
      color: var(--texto-suave);
      font-size: .88rem;
    }

    .checkout-summary-item b {
      color: var(--vinho);
      white-space: nowrap;
    }

    .empty-state {
      padding: 18px;
      border-radius: 18px;
      border: 1px dashed rgba(79, 143, 107, .35);
      background: rgba(234, 246, 234, .45);
      margin: 14px 0;
    }

    .summary-line {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 13px 0;
      border-top: 1px solid rgba(79, 143, 107, .12);
      color: var(--texto-suave);
    }

    .summary-line strong {
      color: var(--verde-profundo);
    }

    .price {
      color: var(--vinho) !important;
      font-size: 1.45rem;
      font-weight: 950;
    }

    .checkout-demo-button {
      width: 100%;
      margin-top: 14px;
    }

    .checkout-next-step {
      margin-top: 18px;
      padding: 16px;
      border-radius: 18px;
      background: var(--rosa-suave);
      border: 1px solid rgba(138, 74, 91, .12);
    }

    .checkout-next-step strong {
      color: var(--vinho);
    }

    .checkout-next-step p {
      color: var(--texto-suave);
      margin-top: 4px;
    }

    .toast {
      position: fixed;
      right: 18px;
      bottom: 18px;
      z-index: 200;
      max-width: min(380px, calc(100% - 36px));
      padding: 14px 16px;
      border-radius: 18px;
      color: var(--verde-profundo);
      background: rgba(255, 255, 255, .94);
      border: 1px solid var(--borda);
      box-shadow: var(--sombra-forte);
      font-weight: 850;
      opacity: 0;
      pointer-events: none;
      transform: translateY(10px);
      transition: .25s ease;
    }

    .toast.is-visible {
      opacity: 1;
      transform: translateY(0);
    }

    @media (max-width: 980px) {
      .main-nav {
        position: fixed;
        top: 76px;
        left: 18px;
        right: 18px;
        display: none;
        flex-direction: column;
        align-items: stretch;
        border-radius: 24px;
        box-shadow: var(--sombra-forte);
      }

      .main-nav.open {
        display: flex;
      }

      .main-nav a {
        text-align: center;
        padding: 13px;
      }

      .menu-toggle {
        display: grid;
        place-items: center;
      }

      .checkout-hero-grid,
      .checkout-layout,
      .pix-panel-grid,
      .checkout-intro {
        grid-template-columns: 1fr;
      }

      .checkout-summary {
        position: static;
      }

      .checkout-step-list {
        justify-content: flex-start;
      }

      .payment-options {
        grid-template-columns: 1fr;
      }
    }

    @media (max-width: 640px) {
      .container {
        width: min(100% - 24px, 1180px);
      }

      .header-inner {
        min-height: 66px;
      }

      .brand {
        font-size: 1.2rem;
      }

      .brand-icon {
        width: 40px;
        height: 40px;
      }

      .page-hero {
        padding: 44px 0;
      }

      .checkout-hero-aside {
        min-height: 210px;
      }

      .form-grid {
        grid-template-columns: 1fr;
      }

      .checkout-block,
      .card {
        border-radius: 22px;
        padding: 18px;
      }

      .checkout-summary-item {
        grid-template-columns: 1fr;
      }

      .checkout-summary-item img {
        width: 100%;
        height: 160px;
      }

      .actions,
      .checkout-submit-row {
        grid-template-columns: 1fr;
      }

      .actions .btn,
      .checkout-submit-row .btn {
        width: 100%;
      }
    }
  </style>
</head>

<body>
  <header class="site-header">
    <div class="container header-inner">
      <a class="brand" href="index.html">
        <span class="brand-icon">🌿</span>
        <span>Arte<span>&</span>Flor</span>
      </a>

      <nav class="main-nav" aria-label="Navegação principal">
        <a href="index.html">Início</a>
        <a href="catalogo.html">Catálogo</a>
        <a href="blog.html">Blog</a>
        <a href="cliente.html">Área do cliente</a>
        <a class="active" href="checkout.html">Checkout</a>
      </nav>

      <button class="menu-toggle" type="button" data-menu-toggle aria-label="Abrir menu">☰</button>
    </div>
  </header>

  <main>
    <section class="page-hero checkout-hero">
      <div class="container">
        <div class="checkout-hero-grid">
          <div>
            <span class="badge">Finalização segura</span>
            <h1 class="section-title">Finalizar pedido</h1>
            <p class="section-subtitle">
              Revise os itens, informe a entrega e escolha a forma de pagamento para enviar o pedido pronto ao atendimento da Arte&Flor.
            </p>
          </div>

          <div class="checkout-hero-aside" aria-label="Resumo da experiência">
            <span>Pedido organizado</span>
            <span>Pix visual</span>
            <span>Envio por WhatsApp</span>
          </div>
        </div>
      </div>
    </section>

    <section class="section checkout-section">
      <div class="container checkout-layout">
        <form class="card checkout-form-card" id="checkoutForm">
          <div class="checkout-intro">
            <div>
              <span class="eyebrow">Pedido Arte&Flor</span>
              <h2>Dados do pedido</h2>
              <p>Preencha as informações para gerar uma solicitação clara para a loja.</p>
            </div>

            <div class="checkout-step-list" aria-label="Etapas do checkout">
              <span class="is-active"><strong>1</strong> Cliente</span>
              <span><strong>2</strong> Entrega</span>
              <span><strong>3</strong> Pagamento</span>
              <span><strong>4</strong> Envio</span>
            </div>
          </div>

          <fieldset class="checkout-block">
            <legend><span>01</span> Cliente</legend>
            <div class="form-grid">
              <label class="form-group">
                <span>Nome completo</span>
                <input name="nome" autocomplete="name" placeholder="Ex: Maria Clara" required>
              </label>

              <label class="form-group">
                <span>WhatsApp</span>
                <input name="whatsapp" inputmode="tel" autocomplete="tel" placeholder="(00) 00000-0000" required>
              </label>
            </div>
          </fieldset>

          <fieldset class="checkout-block">
            <legend><span>02</span> Entrega</legend>
            <div class="form-grid">
              <label class="form-group full">
                <span>Endereço</span>
                <input name="endereco" autocomplete="street-address" placeholder="Rua, número e complemento" required>
              </label>

              <label class="form-group">
                <span>Bairro</span>
                <input name="bairro" required>
              </label>

              <label class="form-group">
                <span>Ponto de referência</span>
                <input name="referencia" placeholder="Opcional">
              </label>

              <label class="form-group">
                <span>Tipo de recebimento</span>
                <select name="recebimento" required>
                  <option>Entrega</option>
                  <option>Retirada</option>
                </select>
              </label>

              <label class="form-group">
                <span>Data desejada</span>
                <input name="data" type="date" required>
              </label>

              <label class="form-group">
                <span>Horário desejado</span>
                <input name="horario" type="time" required>
              </label>
            </div>
          </fieldset>

          <fieldset class="checkout-block">
            <legend><span>03</span> Pagamento</legend>
            <p class="checkout-block-note">
              Selecione uma forma de pagamento. Ao escolher Pix, o QR Code demonstrativo aparecerá automaticamente.
            </p>

            <div class="payment-options">
              <label class="payment-option payment-option-featured">
                <input type="radio" name="pagamento" value="Pix" data-payment-method required>
                <span>
                  <em>PIX</em>
                  <strong>Pix com QR Code</strong>
                  <small>Código copia e cola, status visual e finalização demonstrativa.</small>
                </span>
              </label>

              <label class="payment-option">
                <input type="radio" name="pagamento" value="Presencial" data-payment-method>
                <span>
                  <em>LOJA</em>
                  <strong>Presencial</strong>
                  <small>Para pagar na retirada ou diretamente com a loja.</small>
                </span>
              </label>

              <label class="payment-option">
                <input type="radio" name="pagamento" value="Dinheiro" data-payment-method>
                <span>
                  <em>R$</em>
                  <strong>Dinheiro</strong>
                  <small>O cliente pode informar troco nas observações.</small>
                </span>
              </label>

              <label class="payment-option">
                <input type="radio" name="pagamento" value="Cartão na entrega" data-payment-method>
                <span>
                  <em>CARD</em>
                  <strong>Cartão na entrega</strong>
                  <small>Simulação para pagamento na maquininha.</small>
                </span>
              </label>
            </div>
          </fieldset>

          <div class="pix-checkout-panel form-group full" data-pix-panel hidden>
            <div class="pix-panel-header">
              <div>
                <span class="badge">Pix</span>
                <h2>Pagamento via Pix</h2>
                <p class="muted">
                  Prévia visual do pagamento. O QR Code abaixo é fictício para apresentação.
                </p>
              </div>

              <span class="status is-waiting" data-pix-status>Aguardando pagamento</span>
            </div>

            <div class="pix-panel-grid">
              <div class="pix-qr-demo" role="img" aria-label="QR Code Pix demonstrativo">
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <span></span><span></span><span></span><span></span><span></span><span></span>
                <strong>PIX</strong>
              </div>

              <div class="pix-payment-box">
                <small>Chave Pix demonstrativa</small>
                <strong id="pixKey">arteflor@pix.demo</strong>

                <small>Código copia e cola</small>
                <code data-pix-code>
00020126580014BR.GOV.BCB.PIX0136arteflor-demo-checkout5204000053039865802BR5910ARTE E FLOR6005COARI62070503***6304DEMO
                </code>

                <div class="actions">
                  <button class="btn btn-soft" type="button" data-copy-pix>Copiar código Pix</button>
                  <button class="btn btn-primary" type="button" data-system-finish>Finalizar no sistema</button>
                </div>

                <p class="muted" data-system-result>
                  Ao finalizar no sistema, a venda fica marcada como paga apenas nesta demonstração.
                </p>
              </div>
            </div>
          </div>

          <fieldset class="checkout-block">
            <legend><span>04</span> Mensagem e observações</legend>
            <div class="form-grid">
              <label class="form-group full">
                <span>Mensagem para cartão</span>
                <textarea name="cartao" placeholder="Mensagem que acompanha o presente"></textarea>
              </label>

              <label class="form-group full">
                <span>Observações</span>
                <textarea name="observacoes" placeholder="Preferência de flores, cores, embalagem, troco ou instruções de entrega"></textarea>
              </label>
            </div>
          </fieldset>

          <div class="checkout-submit-row">
            <button class="btn btn-primary" type="submit">Enviar pedido pelo WhatsApp</button>
            <p>O pedido será aberto no WhatsApp com todos os dados preenchidos.</p>
          </div>
        </form>

        <aside class="card checkout-summary">
          <span class="eyebrow">Resumo</span>
          <h2>Seu pedido</h2>

          <div id="checkoutSummary"></div>

          <div class="summary-line">
            <span>Subtotal</span>
            <strong id="checkoutSubtotal">R$ 0,00</strong>
          </div>

          <div class="summary-line">
            <span>Entrega</span>
            <strong>A combinar</strong>
          </div>

          <div class="summary-line">
            <span>Total</span>
            <strong class="price" id="checkoutTotal">R$ 0,00</strong>
          </div>

          <button class="btn btn-soft checkout-demo-button" type="button" data-load-demo-order>
            Simular pedido de apresentação
          </button>

          <div class="checkout-next-step">
            <strong>Próximo passo</strong>
            <p>Depois de preencher o formulário, o sistema monta uma mensagem organizada para o WhatsApp da loja.</p>
          </div>

          <p class="muted" style="margin-top: 14px;">
            Pagamento real e backend serão conectados na próxima etapa.
          </p>
        </aside>
      </div>
    </section>
  </main>

  <div class="toast" data-toast></div>

  <script>
    (function () {
      const WHATSAPP_NUMBER = '5597000000000'; // TROQUE AQUI PELO WHATSAPP DA LOJA
      const CART_KEY = 'arteflor_cart';
      const ORDERS_KEY = 'arteflor_orders';
      const SALES_KEY = 'arteflor_demo_sales';

      const demoItems = [
        {
          id: 1001,
          nome: 'Buquê Tons Pastel',
          preco: 119.90,
          qty: 1,
          imagem: 'https://images.unsplash.com/photo-1490750967868-88aa4486c946?auto=format&fit=crop&w=500&q=80'
        },
        {
          id: 1002,
          nome: 'Cartão personalizado',
          preco: 12.00,
          qty: 1,
          imagem: 'https://images.unsplash.com/photo-1526047932273-341f2a7631f9?auto=format&fit=crop&w=500&q=80'
        }
      ];

      const $ = (selector) => document.querySelector(selector);
      const $$ = (selector) => document.querySelectorAll(selector);

      const money = (value) => {
        return Number(value || 0).toLocaleString('pt-BR', {
          style: 'currency',
          currency: 'BRL'
        });
      };

      const escapeHtml = (value) => String(value || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');

      const getJson = (key, fallback = []) => {
        try {
          return JSON.parse(localStorage.getItem(key) || JSON.stringify(fallback));
        } catch (error) {
          return fallback;
        }
      };

      const setJson = (key, value) => {
        localStorage.setItem(key, JSON.stringify(value));
      };

      const getCart = () => getJson(CART_KEY, []);
      const setCart = (cart) => setJson(CART_KEY, cart);

      const getCartTotal = (cart) => cart.reduce((sum, item) => {
        return sum + Number(item.preco || 0) * Number(item.qty || 0);
      }, 0);

      const toast = (message) => {
        const toastEl = $('[data-toast]');
        if (!toastEl) return;

        toastEl.textContent = message;
        toastEl.classList.add('is-visible');

        setTimeout(() => {
          toastEl.classList.remove('is-visible');
        }, 3200);
      };

      const whatsappUrl = (message) => {
        return `https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`;
      };

      const saveOrder = (order) => {
        const orders = getJson(ORDERS_KEY, []);
        orders.unshift(order);
        setJson(ORDERS_KEY, orders.slice(0, 20));
      };

      const renderSummary = () => {
        const summary = $('#checkoutSummary');
        const subtotal = $('#checkoutSubtotal');
        const total = $('#checkoutTotal');

        if (!summary || !subtotal || !total) return;

        const cart = getCart();
        const value = getCartTotal(cart);

        if (!cart.length) {
          summary.innerHTML = `
            <div class="empty-state checkout-empty-state">
              <strong>Nenhum produto no carrinho.</strong>
              <p>Use a simulação para apresentar a experiência completa sem depender de um carrinho real.</p>
            </div>
          `;
        } else {
          summary.innerHTML = cart.map((item) => `
            <div class="checkout-summary-item">
              ${item.imagem ? `<img src="${escapeHtml(item.imagem)}" alt="Imagem de ${escapeHtml(item.nome)}" loading="lazy">` : ''}
              <div>
                <strong>${escapeHtml(item.nome)}</strong>
                <span>${Number(item.qty || 0)} un. · ${money(Number(item.preco || 0))}</span>
              </div>
              <b>${money(Number(item.preco || 0) * Number(item.qty || 0))}</b>
            </div>
          `).join('');
        }

        subtotal.textContent = money(value);
        total.textContent = money(value);
      };

      const getPaymentValue = (form) => {
        const data = new FormData(form);
        return data.get('pagamento') || '';
      };

      const buildMessage = (data, cart, total, pixStatusText) => {
        const items = cart.length
          ? cart.map((item) => `- ${item.qty}x ${item.nome} (${money(Number(item.preco || 0) * Number(item.qty || 0))})`).join('\n')
          : '- Pedido personalizado sem itens no carrinho.';

        return [
          'Olá, vim pelo site da Arte&Flor.',
          '',
          'Pedido:',
          items,
          '',
          `Total demonstrativo: ${money(total)}`,
          '',
          `Cliente: ${data.nome || '-'}`,
          `WhatsApp: ${data.whatsapp || '-'}`,
          `Recebimento: ${data.recebimento || '-'}`,
          `Data desejada: ${data.data || '-'}`,
          `Horário desejado: ${data.horario || '-'}`,
          `Bairro: ${data.bairro || '-'}`,
          `Endereço: ${data.endereco || '-'}`,
          `Referência: ${data.referencia || '-'}`,
          `Pagamento: ${data.pagamento || '-'}`,
          pixStatusText ? `Status Pix demonstrativo: ${pixStatusText}` : '',
          `Mensagem para cartão: ${data.cartao || 'Nenhuma'}`,
          `Observações: ${data.observacoes || 'Nenhuma'}`
        ].filter(Boolean).join('\n');
      };

      document.addEventListener('DOMContentLoaded', () => {
        const form = $('#checkoutForm');
        const paymentMethods = $$('[data-payment-method]');
        const pixPanel = $('[data-pix-panel]');
        const pixStatus = $('[data-pix-status]');
        const pixCode = $('[data-pix-code]');
        const copyPixButton = $('[data-copy-pix]');
        const finishSystemButton = $('[data-system-finish]');
        const systemResult = $('[data-system-result]');
        const loadDemoButton = $('[data-load-demo-order]');
        const menuToggle = $('[data-menu-toggle]');
        const mainNav = $('.main-nav');

        let systemFinished = false;
        let systemOrderCode = '';

        menuToggle?.addEventListener('click', () => {
          mainNav?.classList.toggle('open');
        });

        renderSummary();

        const togglePixPanel = () => {
          const isPix = form ? getPaymentValue(form) === 'Pix' : false;

          if (pixPanel) {
            pixPanel.hidden = !isPix;
          }

          if (!isPix && pixStatus) {
            pixStatus.textContent = 'Aguardando pagamento';
            pixStatus.classList.remove('is-paid');
            pixStatus.classList.add('is-waiting');
          }

          if (isPix) {
            setTimeout(() => {
              pixPanel?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }, 80);
          }
        };

        const saveDemoSale = () => {
          const cart = getCart();
          const total = getCartTotal(cart);
          const sales = getJson(SALES_KEY, []);

          systemOrderCode = `AF-${String(Date.now()).slice(-6)}`;
          systemFinished = true;

          sales.unshift({
            codigo: systemOrderCode,
            status: 'Venda finalizada no sistema',
            pagamento: 'Pix',
            total,
            itens: cart,
            criadoEm: new Date().toISOString()
          });

          setJson(SALES_KEY, sales.slice(0, 10));

          if (pixStatus) {
            pixStatus.textContent = 'Pagamento confirmado';
            pixStatus.classList.remove('is-waiting');
            pixStatus.classList.add('is-paid');
          }

          if (systemResult) {
            systemResult.textContent = `Venda ${systemOrderCode} finalizada no sistema demonstrativo. Nenhum pagamento real foi processado.`;
          }

          if (finishSystemButton) {
            finishSystemButton.textContent = 'Venda finalizada';
            finishSystemButton.disabled = true;
          }

          toast(`Venda ${systemOrderCode} finalizada no sistema demonstrativo.`);
        };

        paymentMethods.forEach((input) => {
          input.addEventListener('change', togglePixPanel);
        });

        togglePixPanel();

        loadDemoButton?.addEventListener('click', () => {
          setCart(demoItems);
          renderSummary();
          toast('Pedido de apresentação carregado no resumo.');
        });

        copyPixButton?.addEventListener('click', async () => {
          const code = pixCode?.textContent?.trim() || '';

          try {
            await navigator.clipboard.writeText(code);
            if (systemResult) {
              systemResult.textContent = 'Código Pix demonstrativo copiado.';
            }
            toast('Código Pix copiado.');
          } catch (error) {
            if (systemResult) {
              systemResult.textContent = 'Não foi possível copiar automaticamente. Selecione e copie o código manualmente.';
            }
            toast('Copie o código Pix manualmente.');
          }
        });

        finishSystemButton?.addEventListener('click', saveDemoSale);

        form?.addEventListener('submit', (event) => {
          event.preventDefault();

          const cart = getCart();
          const total = getCartTotal(cart);
          const data = Object.fromEntries(new FormData(form).entries());
          const codigo = `AF-${String(Date.now()).slice(-5)}`;

          const pixStatusText = data.pagamento === 'Pix'
            ? (systemFinished ? `finalizado no sistema (${systemOrderCode})` : 'QR Code exibido, confirmação pendente')
            : '';

          saveOrder({
            codigo,
            status: 'Pedido recebido',
            pagamento: data.pagamento,
            total,
            itensResumo: cart.length
              ? cart.map((item) => `${item.qty}x ${item.nome}`).join(', ')
              : 'Atendimento manual',
            criadoEm: new Date().toISOString()
          });

          window.open(whatsappUrl(buildMessage(data, cart, total, pixStatusText)), '_blank', 'noopener');

          toast(`Pedido #${codigo} gerado para WhatsApp.`);
        });
      });
    })();
  </script>
</body>
</html>