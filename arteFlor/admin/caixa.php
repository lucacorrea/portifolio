<?php require_once __DIR__ . '/../includes/helpers.php';
$activeAdmin = 'caixa'; ?>
<!doctype html>
<html lang="pt-BR">

<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Frente de Caixa | Arte&Flor</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Nunito+Sans:wght@400;500;600;700;800;900&family=Playfair+Display:wght@600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= asset('css/base.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/layout.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/components.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/pages.css') ?>">
  <link rel="stylesheet" href="<?= asset('css/responsive.css') ?>">
  <style>
    :root {
      --pdv-green: #4F8F6B;
      --pdv-green-dark: #28583E;
      --pdv-green-soft: #EAF6EA;
      --pdv-cream: #FFF8F0;
      --pdv-white: #FFFFFF;
      --pdv-pink: #FBE8EF;
      --pdv-wine: #8A4A5B;
      --pdv-red: #B42318;
      --pdv-red-soft: #FEE4E2;
      --pdv-yellow: #B7791F;
      --pdv-yellow-soft: #FFF7D6;
      --pdv-blue: #2563EB;
      --pdv-blue-soft: #EFF6FF;
      --pdv-border: rgba(79, 143, 107, .16);
      --pdv-shadow: 0 18px 50px rgba(65, 48, 35, .10);
      --pdv-shadow-strong: 0 28px 80px rgba(65, 48, 35, .16);
      --pdv-radius: 24px;
      --pdv-text: #2f2f2f;
      --pdv-muted: #666;
    }

    body.pdv-page {
      background: radial-gradient(circle at top left, rgba(245, 198, 214, .28), transparent 28rem), radial-gradient(circle at 92% 8%, rgba(79, 143, 107, .20), transparent 32rem), linear-gradient(180deg, var(--pdv-cream), #fffdf9);
    }

    .pdv-topbar {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 18px;
      align-items: center;
      margin-bottom: 20px;
    }

    .pdv-title-block h1 {
      margin: 8px 0 4px;
      font-family: "Playfair Display", Georgia, serif;
      font-size: clamp(2rem, 4vw, 3.4rem);
      color: var(--pdv-green-dark);
      line-height: 1;
    }

    .pdv-title-block p {
      color: var(--pdv-muted);
      font-weight: 700;
    }

    .pdv-status-bar {
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      justify-content: flex-end;
    }

    .pdv-status-pill {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 10px 13px;
      border-radius: 999px;
      background: rgba(255, 255, 255, .88);
      border: 1px solid var(--pdv-border);
      color: var(--pdv-green-dark);
      font-weight: 900;
      box-shadow: 0 10px 28px rgba(65, 48, 35, .06);
      white-space: nowrap;
    }

    .pdv-status-pill::before {
      content: "";
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: var(--pdv-green);
      box-shadow: 0 0 0 5px rgba(79, 143, 107, .14);
    }

    .pdv-layout {
      display: grid;
      grid-template-columns: 360px minmax(0, 1fr) 360px;
      gap: 18px;
      align-items: start;
    }

    .pdv-card {
      background: rgba(255, 255, 255, .94);
      border: 1px solid var(--pdv-border);
      border-radius: var(--pdv-radius);
      box-shadow: var(--pdv-shadow);
      overflow: hidden;
    }

    .pdv-card-header {
      padding: 18px 20px;
      border-bottom: 1px solid rgba(79, 143, 107, .11);
      background: radial-gradient(circle at top right, rgba(245, 198, 214, .22), transparent 12rem), linear-gradient(135deg, rgba(234, 246, 234, .75), rgba(255, 255, 255, .9));
    }

    .pdv-card-header span {
      display: inline-flex;
      width: fit-content;
      padding: 6px 10px;
      border-radius: 999px;
      background: var(--pdv-green-soft);
      color: var(--pdv-green-dark);
      font-size: .72rem;
      font-weight: 950;
      text-transform: uppercase;
      letter-spacing: .07em;
    }

    .pdv-card-header h2 {
      margin-top: 8px;
      font-family: "Playfair Display", Georgia, serif;
      color: var(--pdv-green-dark);
      font-size: 1.55rem;
      line-height: 1.1;
    }

    .pdv-card-body {
      padding: 18px;
    }

    .pdv-scanner-box {
      display: grid;
      gap: 12px;
    }

    .pdv-label {
      display: grid;
      gap: 7px;
      color: var(--pdv-green-dark);
      font-weight: 900;
    }

    .pdv-label input,
    .pdv-label select,
    .pdv-label textarea {
      width: 100%;
      min-height: 48px;
      border: 1px solid rgba(79, 143, 107, .18);
      border-radius: 16px;
      padding: 13px 15px;
      background: rgba(255, 255, 255, .96);
      color: var(--pdv-text);
      outline: none;
      transition: .2s ease;
    }

    .pdv-label input:focus,
    .pdv-label select:focus,
    .pdv-label textarea:focus {
      border-color: var(--pdv-green);
      box-shadow: 0 0 0 4px rgba(79, 143, 107, .10);
      background: #fff;
    }

    .pdv-scan-input {
      font-size: 1.05rem;
      font-weight: 900;
      letter-spacing: .03em;
    }

    .pdv-actions-row {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .pdv-btn {
      min-height: 46px;
      border-radius: 999px;
      padding: 11px 16px;
      font-weight: 950;
      border: 1px solid transparent;
      transition: .2s ease;
      cursor: pointer;
    }

    .pdv-btn-primary {
      color: #fff;
      background: linear-gradient(135deg, var(--pdv-green), var(--pdv-green-dark));
      box-shadow: 0 14px 30px rgba(79, 143, 107, .22);
    }

    .pdv-btn-primary:hover {
      transform: translateY(-2px);
      box-shadow: 0 18px 44px rgba(79, 143, 107, .28);
    }

    .pdv-btn-soft {
      color: var(--pdv-green-dark);
      background: var(--pdv-green-soft);
      border-color: rgba(79, 143, 107, .16);
    }

    .pdv-btn-soft:hover {
      transform: translateY(-2px);
      background: #DDEBDD;
    }

    .pdv-btn-danger {
      color: var(--pdv-red);
      background: var(--pdv-red-soft);
      border-color: rgba(180, 35, 24, .14);
    }

    .pdv-shortcuts {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
      margin-top: 18px;
    }

    .pdv-shortcut {
      display: grid;
      gap: 6px;
      min-height: 108px;
      padding: 14px;
      border-radius: 18px;
      background: rgba(255, 255, 255, .92);
      border: 1px solid rgba(79, 143, 107, .14);
      text-align: left;
      cursor: pointer;
      transition: .2s ease;
    }

    .pdv-shortcut:hover {
      transform: translateY(-2px);
      box-shadow: 0 12px 30px rgba(65, 48, 35, .08);
      border-color: rgba(79, 143, 107, .26);
    }

    .pdv-shortcut i {
      width: 38px;
      height: 38px;
      display: grid;
      place-items: center;
      border-radius: 14px;
      background: var(--pdv-green-soft);
      font-style: normal;
      font-size: 1.22rem;
    }

    .pdv-shortcut strong {
      color: var(--pdv-green-dark);
      font-weight: 950;
      font-size: .92rem;
    }

    .pdv-shortcut small {
      color: var(--pdv-muted);
      font-weight: 700;
      line-height: 1.35;
    }

    .pdv-categories {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-top: 18px;
    }

    .pdv-category {
      padding: 8px 12px;
      border-radius: 999px;
      border: 1px solid rgba(79, 143, 107, .16);
      background: rgba(255, 255, 255, .9);
      color: var(--pdv-green-dark);
      font-weight: 900;
      font-size: .82rem;
      cursor: pointer;
    }

    .pdv-category.is-active {
      color: #fff;
      background: var(--pdv-green);
      border-color: var(--pdv-green);
    }

    .pdv-products {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
      margin-top: 16px;
      max-height: 390px;
      overflow: auto;
      padding-right: 4px;
    }

    .pdv-product {
      display: grid;
      gap: 8px;
      min-height: 132px;
      padding: 14px;
      border-radius: 18px;
      background: radial-gradient(circle at top right, rgba(245, 198, 214, .18), transparent 9rem), rgba(255, 255, 255, .92);
      border: 1px solid rgba(79, 143, 107, .14);
      text-align: left;
      cursor: pointer;
      transition: .2s ease;
    }

    .pdv-product:hover {
      transform: translateY(-2px);
      box-shadow: 0 14px 34px rgba(65, 48, 35, .09);
    }

    .pdv-product .emoji {
      font-size: 1.8rem;
    }

    .pdv-product strong {
      color: var(--pdv-green-dark);
      font-size: .92rem;
      line-height: 1.2;
    }

    .pdv-product small {
      color: var(--pdv-muted);
      font-weight: 800;
    }

    .pdv-product b {
      color: var(--pdv-wine);
      font-weight: 950;
    }

    .pdv-sale-card {
      min-height: 760px;
      display: grid;
      grid-template-rows: auto 1fr auto;
    }

    .pdv-sale-top {
      display: grid;
      grid-template-columns: 1fr auto;
      gap: 12px;
      align-items: center;
      padding: 18px 20px;
      border-bottom: 1px solid rgba(79, 143, 107, .11);
      background: rgba(255, 255, 255, .86);
    }

    .pdv-sale-top h2 {
      font-family: "Playfair Display", Georgia, serif;
      color: var(--pdv-green-dark);
      font-size: 1.8rem;
    }

    .pdv-sale-code {
      padding: 8px 11px;
      border-radius: 999px;
      background: var(--pdv-green-soft);
      color: var(--pdv-green-dark);
      font-size: .82rem;
      font-weight: 950;
    }

    .pdv-cart {
      padding: 14px;
      overflow: auto;
      max-height: 530px;
    }

    .pdv-empty {
      height: 100%;
      min-height: 380px;
      display: grid;
      place-items: center;
      text-align: center;
      padding: 30px;
      border: 1px dashed rgba(79, 143, 107, .30);
      border-radius: 22px;
      background: rgba(234, 246, 234, .38);
    }

    .pdv-empty strong {
      display: block;
      color: var(--pdv-green-dark);
      font-family: "Playfair Display", Georgia, serif;
      font-size: 1.8rem;
      margin-bottom: 8px;
    }

    .pdv-empty p {
      max-width: 420px;
      color: var(--pdv-muted);
      font-weight: 700;
    }

    .pdv-cart-item {
      display: grid;
      grid-template-columns: minmax(0, 1fr) 92px 112px 42px;
      gap: 12px;
      align-items: center;
      padding: 13px;
      margin-bottom: 10px;
      border-radius: 18px;
      background: rgba(255, 255, 255, .94);
      border: 1px solid rgba(79, 143, 107, .13);
      box-shadow: 0 10px 24px rgba(65, 48, 35, .05);
    }

    .pdv-cart-item-name strong {
      color: var(--pdv-green-dark);
      font-weight: 950;
    }

    .pdv-cart-item-name small {
      display: block;
      color: var(--pdv-muted);
      font-weight: 750;
      margin-top: 2px;
    }

    .pdv-cart-qty {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 4px;
      border: 1px solid rgba(79, 143, 107, .16);
      border-radius: 999px;
      padding: 5px;
      background: var(--pdv-green-soft);
    }

    .pdv-cart-qty button {
      width: 28px;
      height: 28px;
      border-radius: 50%;
      background: #fff;
      color: var(--pdv-green-dark);
      font-weight: 950;
      border: 1px solid rgba(79, 143, 107, .14);
    }

    .pdv-cart-qty span {
      min-width: 24px;
      text-align: center;
      font-weight: 950;
      color: var(--pdv-green-dark);
    }

    .pdv-cart-subtotal {
      color: var(--pdv-wine);
      font-weight: 950;
      text-align: right;
    }

    .pdv-remove {
      width: 38px;
      height: 38px;
      border-radius: 14px;
      background: var(--pdv-red-soft);
      color: var(--pdv-red);
      font-weight: 950;
    }

    .pdv-total-bar {
      padding: 18px 20px;
      border-top: 1px solid rgba(79, 143, 107, .11);
      background: radial-gradient(circle at right, rgba(245, 198, 214, .22), transparent 12rem), linear-gradient(135deg, rgba(234, 246, 234, .72), rgba(255, 255, 255, .94));
    }

    .pdv-total-lines {
      display: grid;
      gap: 10px;
      margin-bottom: 14px;
    }

    .pdv-total-line {
      display: flex;
      justify-content: space-between;
      gap: 14px;
      color: var(--pdv-muted);
      font-weight: 850;
    }

    .pdv-total-line strong {
      color: var(--pdv-green-dark);
      font-weight: 950;
    }

    .pdv-grand-total {
      display: flex;
      justify-content: space-between;
      align-items: end;
      gap: 16px;
      padding: 16px;
      border-radius: 22px;
      background: var(--pdv-green-dark);
      color: #fff;
      margin-bottom: 14px;
    }

    .pdv-grand-total span {
      display: block;
      font-weight: 800;
      opacity: .86;
    }

    .pdv-grand-total strong {
      font-family: "Playfair Display", Georgia, serif;
      font-size: clamp(2.2rem, 5vw, 3.6rem);
      line-height: .9;
    }

    .pdv-sale-actions {
      display: grid;
      grid-template-columns: repeat(4, minmax(0, 1fr));
      gap: 10px;
    }

    .pdv-side {
      display: grid;
      gap: 18px;
    }

    .pdv-customer-grid {
      display: grid;
      gap: 12px;
    }

    .pdv-payment-grid {
      display: grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap: 10px;
    }

    .pdv-payment {
      position: relative;
      cursor: pointer;
    }

    .pdv-payment input {
      position: absolute;
      opacity: 0;
      pointer-events: none;
    }

    .pdv-payment span {
      min-height: 86px;
      display: grid;
      gap: 4px;
      padding: 13px;
      border-radius: 18px;
      border: 1px solid rgba(79, 143, 107, .15);
      background: rgba(255, 255, 255, .92);
      transition: .2s ease;
    }

    .pdv-payment b {
      color: var(--pdv-green-dark);
      font-weight: 950;
    }

    .pdv-payment small {
      color: var(--pdv-muted);
      font-weight: 700;
    }

    .pdv-payment input:checked+span {
      background: var(--pdv-green-soft);
      border-color: var(--pdv-green);
      box-shadow: 0 12px 28px rgba(79, 143, 107, .13);
    }

    .pdv-pix-panel {
      display: none;
      padding: 16px;
      border-radius: 22px;
      background: radial-gradient(circle at top right, rgba(245, 198, 214, .30), transparent 12rem), var(--pdv-green-soft);
      border: 1px solid rgba(79, 143, 107, .20);
    }

    .pdv-pix-panel.is-visible {
      display: grid;
      gap: 14px;
      animation: pixEnter .22s ease;
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

    .pdv-qr {
      position: relative;
      width: min(100%, 220px);
      aspect-ratio: 1;
      margin: 0 auto;
      display: grid;
      grid-template-columns: repeat(6, 1fr);
      gap: 7px;
      padding: 18px;
      border-radius: 24px;
      background: linear-gradient(90deg, rgba(79, 143, 107, .13) 50%, transparent 50%), linear-gradient(rgba(79, 143, 107, .13) 50%, transparent 50%), #fff;
      background-size: 20px 20px;
      border: 1px solid rgba(79, 143, 107, .20);
      box-shadow: inset 0 0 0 12px rgba(234, 246, 234, .72);
      overflow: hidden;
    }

    .pdv-qr span {
      border-radius: 6px;
      background: var(--pdv-green-dark);
      opacity: .92;
    }

    .pdv-qr span:nth-child(2n) {
      background: var(--pdv-green);
    }

    .pdv-qr span:nth-child(3n) {
      background: transparent;
      border: 1px solid rgba(79, 143, 107, .24);
      opacity: .32;
    }

    .pdv-qr strong {
      position: absolute;
      inset: 50%;
      width: 70px;
      height: 70px;
      translate: -50% -50%;
      border-radius: 20px;
      display: grid;
      place-items: center;
      background: #fff;
      color: var(--pdv-green-dark);
      border: 1px solid rgba(79, 143, 107, .18);
      box-shadow: 0 10px 24px rgba(37, 71, 54, .15);
      font-weight: 950;
    }

    .pdv-pix-code {
      display: block;
      max-height: 88px;
      overflow: auto;
      word-break: break-all;
      padding: 12px;
      border-radius: 14px;
      background: #fff;
      border: 1px dashed rgba(79, 143, 107, .35);
      color: var(--pdv-green-dark);
      font-size: .78rem;
      line-height: 1.45;
    }

    .pdv-finalize {
      display: grid;
      gap: 10px;
    }

    .pdv-finalize .pdv-btn {
      width: 100%;
    }

    .pdv-history {
      margin-top: 18px;
    }

    .pdv-history-list {
      display: grid;
      gap: 10px;
      max-height: 260px;
      overflow: auto;
    }

    .pdv-history-item {
      display: flex;
      justify-content: space-between;
      gap: 12px;
      padding: 12px;
      border-radius: 16px;
      background: rgba(255, 255, 255, .86);
      border: 1px solid rgba(79, 143, 107, .11);
    }

    .pdv-history-item strong {
      color: var(--pdv-green-dark);
    }

    .pdv-history-item small {
      display: block;
      color: var(--pdv-muted);
      font-weight: 750;
    }

    .pdv-history-item b {
      color: var(--pdv-wine);
      white-space: nowrap;
    }

    .pdv-toast {
      position: fixed;
      right: 18px;
      bottom: 18px;
      z-index: 999;
      max-width: min(420px, calc(100% - 36px));
      padding: 14px 16px;
      border-radius: 18px;
      color: var(--pdv-green-dark);
      background: rgba(255, 255, 255, .96);
      border: 1px solid var(--pdv-border);
      box-shadow: var(--pdv-shadow-strong);
      font-weight: 850;
      opacity: 0;
      pointer-events: none;
      transform: translateY(12px);
      transition: .24s ease;
    }

    .pdv-toast.is-visible {
      opacity: 1;
      transform: translateY(0);
    }

    @media (max-width: 1380px) {
      .pdv-layout {
        grid-template-columns: 320px minmax(0, 1fr);
      }

      .pdv-side {
        grid-column: 1 / -1;
        grid-template-columns: repeat(3, minmax(0, 1fr));
      }
    }

    @media (max-width: 1020px) {

      .pdv-layout,
      .pdv-side {
        grid-template-columns: 1fr;
      }

      .pdv-sale-card {
        min-height: auto;
      }

      .pdv-cart {
        max-height: none;
      }
    }

    @media (max-width: 720px) {

      .pdv-topbar,
      .pdv-sale-top,
      .pdv-cart-item,
      .pdv-grand-total {
        grid-template-columns: 1fr;
      }

      .pdv-status-bar,
      .pdv-actions-row,
      .pdv-sale-actions {
        grid-template-columns: 1fr;
        justify-content: stretch;
      }

      .pdv-actions-row,
      .pdv-shortcuts,
      .pdv-products,
      .pdv-payment-grid {
        grid-template-columns: 1fr;
      }

      .pdv-cart-subtotal {
        text-align: left;
      }

      .pdv-status-bar .pdv-status-pill,
      .pdv-sale-actions .pdv-btn,
      .pdv-actions-row .pdv-btn {
        width: 100%;
      }
    }
  </style>
</head>

<body class="pdv-page">
  <div class="admin-shell"> <?php require __DIR__ . '/../includes/admin-sidebar.php'; ?> <main class="admin-main">
      <section class="pdv-topbar">
        <div class="pdv-title-block"> <span class="badge">Frente de caixa</span>
          <h1>PDV Arte&Flor</h1>
          <p>Modelo inspirado em caixas de supermercado: busca rápida, carrinho, total grande e pagamento imediato.</p>
        </div>
        <div class="pdv-status-bar"> <span class="pdv-status-pill">Caixa aberto</span> <span class="pdv-status-pill">Operador: Admin</span> <span class="pdv-status-pill" id="pdvClock">--:--</span> </div>
      </section>
      <section class="pdv-layout">
        <aside class="pdv-card">
          <div class="pdv-card-header"> <span>Entrada de produto</span>
            <h2>Leitor / busca</h2>
          </div>
          <div class="pdv-card-body">
            <div class="pdv-scanner-box"> <label class="pdv-label"> <span>Código, nome ou SKU</span> <input class="pdv-scan-input" id="productSearch" type="text" placeholder="Ex: 1001, buquê, rosa..." autocomplete="off" autofocus> </label> <label class="pdv-label"> <span>Quantidade</span> <input id="productQty" type="number" min="1" value="1"> </label>
              <div class="pdv-actions-row"> <button class="pdv-btn pdv-btn-primary" type="button" id="addBySearch">Adicionar</button> <button class="pdv-btn pdv-btn-soft" type="button" id="clearSearch">Limpar</button> </div>
            </div>
            <div class="pdv-shortcuts"> <button class="pdv-shortcut" type="button" data-product-code="1001"> <i>🌹</i> <strong>Rosas</strong> <small>Buquê mais vendido</small> </button> <button class="pdv-shortcut" type="button" data-product-code="1002"> <i>💐</i> <strong>Tons Pastel</strong> <small>Presente delicado</small> </button> <button class="pdv-shortcut" type="button" data-product-code="1005"> <i>🧺</i> <strong>Cesta</strong> <small>Presente completo</small> </button> <button class="pdv-shortcut" type="button" data-product-code="1010"> <i>💌</i> <strong>Cartão</strong> <small>Adicional rápido</small> </button> </div>
            <div class="pdv-categories" id="categoryFilters"> <button class="pdv-category is-active" type="button" data-category="todos">Todos</button> <button class="pdv-category" type="button" data-category="buques">Buquês</button> <button class="pdv-category" type="button" data-category="arranjos">Arranjos</button> <button class="pdv-category" type="button" data-category="vasos">Vasos</button> <button class="pdv-category" type="button" data-category="presentes">Presentes</button> </div>
            <div class="pdv-products" id="productGrid"></div>
          </div>
        </aside>
        <section class="pdv-card pdv-sale-card">
          <div class="pdv-sale-top">
            <div>
              <h2>Venda atual</h2>
              <p class="muted">Itens adicionados aparecem como no caixa de mercado.</p>
            </div> <span class="pdv-sale-code" id="saleCode">Venda #AF-0001</span>
          </div>
          <div class="pdv-cart" id="cartContainer">
            <div class="pdv-empty">
              <div> <strong>Nenhum item na venda</strong>
                <p>Digite o código, busque o produto ou use os atalhos rápidos para começar.</p>
              </div>
            </div>
          </div>
          <div class="pdv-total-bar">
            <div class="pdv-total-lines">
              <div class="pdv-total-line"> <span>Subtotal</span> <strong id="subtotalText">R$ 0,00</strong> </div>
              <div class="pdv-total-line"> <span>Desconto</span> <strong id="discountText">R$ 0,00</strong> </div>
              <div class="pdv-total-line"> <span>Quantidade de itens</span> <strong id="itemsCountText">0</strong> </div>
            </div>
            <div class="pdv-grand-total">
              <div> <span>Total da venda</span> <strong id="totalText">R$ 0,00</strong> </div> <span id="paymentSelectedText">Pagamento: Pix</span>
            </div>
            <div class="pdv-sale-actions"> <button class="pdv-btn pdv-btn-soft" type="button" id="holdSale">Suspender</button> <button class="pdv-btn pdv-btn-soft" type="button" id="applyDiscount">Desconto</button> <button class="pdv-btn pdv-btn-danger" type="button" id="cancelSale">Cancelar</button> <button class="pdv-btn pdv-btn-primary" type="button" id="finalizeSale">Finalizar</button> </div>
          </div>
        </section>
        <aside class="pdv-side">
          <section class="pdv-card">
            <div class="pdv-card-header"> <span>Cliente</span>
              <h2>Dados rápidos</h2>
            </div>
            <div class="pdv-card-body pdv-customer-grid"> <label class="pdv-label"> <span>Cliente</span> <input id="customerName" placeholder="Cliente balcão"> </label> <label class="pdv-label"> <span>WhatsApp</span> <input id="customerPhone" placeholder="(97) 00000-0000"> </label> <label class="pdv-label"> <span>Observação</span> <textarea id="saleNote" placeholder="Ex: entregar às 16h, incluir cartão"></textarea> </label> </div>
          </section>
          <section class="pdv-card">
            <div class="pdv-card-header"> <span>Pagamento</span>
              <h2>Forma de pagamento</h2>
            </div>
            <div class="pdv-card-body">
              <div class="pdv-payment-grid"> <label class="pdv-payment"> <input type="radio" name="payment" value="Pix" checked> <span> <b>💠 Pix</b> <small>QR visual</small> </span> </label> <label class="pdv-payment"> <input type="radio" name="payment" value="Dinheiro"> <span> <b>💵 Dinheiro</b> <small>Balcão</small> </span> </label> <label class="pdv-payment"> <input type="radio" name="payment" value="Cartão"> <span> <b>💳 Cartão</b> <small>Maquininha</small> </span> </label> <label class="pdv-payment"> <input type="radio" name="payment" value="WhatsApp"> <span> <b>💬 WhatsApp</b> <small>Enviar resumo</small> </span> </label> </div>
              <div class="pdv-pix-panel is-visible" id="pixPanel" style="margin-top: 14px;">
                <div class="pdv-qr" aria-label="QR Code Pix demonstrativo"> <span></span><span></span><span></span><span></span><span></span><span></span> <span></span><span></span><span></span><span></span><span></span><span></span> <span></span><span></span><span></span><span></span><span></span><span></span> <span></span><span></span><span></span><span></span><span></span><span></span> <span></span><span></span><span></span><span></span><span></span><span></span> <span></span><span></span><span></span><span></span><span></span><span></span> <strong>PIX</strong> </div> <code class="pdv-pix-code" id="pixCode">00020126580014BR.GOV.BCB.PIX0136arteflor-pdv-demo5204000053039865802BR5910ARTE E FLOR6005COARI62070503***6304DEMO</code>
                <div class="pdv-actions-row"> <button class="pdv-btn pdv-btn-soft" type="button" id="copyPix">Copiar Pix</button> <button class="pdv-btn pdv-btn-primary" type="button" id="confirmPayment">Confirmar</button> </div>
              </div>
            </div>
          </section>
          <section class="pdv-card pdv-history">
            <div class="pdv-card-header"> <span>Últimas vendas</span>
              <h2>Histórico rápido</h2>
            </div>
            <div class="pdv-card-body">
              <div class="pdv-history-list" id="salesHistory"></div>
            </div>
          </section>
        </aside>
      </section>
    </main>
  </div>
  <div class="pdv-toast" id="pdvToast"></div>
  <script src="<?= asset('js/app.js') ?>"></script>
  <script src="<?= asset('js/admin.js') ?>"></script>
  <script>
    (function() {
      const WHATSAPP_NUMBER = '5597000000000';
      const products = [{
        code: '1001',
        name: 'Buquê de Rosas Vermelhas',
        price: 129.90,
        category: 'buques',
        icon: '🌹',
        stock: 8
      }, {
        code: '1002',
        name: 'Buquê Tons Pastel',
        price: 119.90,
        category: 'buques',
        icon: '💐',
        stock: 5
      }, {
        code: '1003',
        name: 'Arranjo Floral Premium',
        price: 189.90,
        category: 'arranjos',
        icon: '🌺',
        stock: 3
      }, {
        code: '1004',
        name: 'Vaso de Violeta',
        price: 39.90,
        category: 'vasos',
        icon: '🪴',
        stock: 12
      }, {
        code: '1005',
        name: 'Cesta com Flores',
        price: 229.90,
        category: 'presentes',
        icon: '🧺',
        stock: 2
      }, {
        code: '1006',
        name: 'Planta para Jardim',
        price: 24.90,
        category: 'vasos',
        icon: '🌱',
        stock: 20
      }, {
        code: '1007',
        name: 'Arranjo para Aniversário',
        price: 159.90,
        category: 'arranjos',
        icon: '🎂',
        stock: 4
      }, {
        code: '1008',
        name: 'Orquídea',
        price: 89.90,
        category: 'vasos',
        icon: '🌸',
        stock: 6
      }, {
        code: '1009',
        name: 'Kit Presente Romântico',
        price: 179.90,
        category: 'presentes',
        icon: '🎁',
        stock: 2
      }, {
        code: '1010',
        name: 'Cartão Personalizado',
        price: 12.00,
        category: 'presentes',
        icon: '💌',
        stock: 50
      }];
      let cart = [];
      let discount = 0;
      let activeCategory = 'todos';
      let saleNumber = 1;
      let sales = [{
        code: 'AF-1025',
        total: 189.90,
        payment: 'Pix',
        customer: 'Maria Clara'
      }, {
        code: 'AF-1024',
        total: 119.90,
        payment: 'Presencial',
        customer: 'Ana Beatriz'
      }, {
        code: 'AF-1021',
        total: 59.90,
        payment: 'Dinheiro',
        customer: 'Cliente balcão'
      }];
      const $ = (selector) => document.querySelector(selector);
      const $$ = (selector) => document.querySelectorAll(selector);
      const productSearch = $('#productSearch');
      const productQty = $('#productQty');
      const productGrid = $('#productGrid');
      const cartContainer = $('#cartContainer');
      const subtotalText = $('#subtotalText');
      const discountText = $('#discountText');
      const totalText = $('#totalText');
      const itemsCountText = $('#itemsCountText');
      const paymentSelectedText = $('#paymentSelectedText');
      const pixPanel = $('#pixPanel');
      const pixCode = $('#pixCode');
      const salesHistory = $('#salesHistory');
      const saleCode = $('#saleCode');
      const toastEl = $('#pdvToast');
      const pdvClock = $('#pdvClock');
      const money = (value) => Number(value || 0).toLocaleString('pt-BR', {
        style: 'currency',
        currency: 'BRL'
      });
      const toast = (message) => {
        toastEl.textContent = message;
        toastEl.classList.add('is-visible');
        setTimeout(() => {
          toastEl.classList.remove('is-visible');
        }, 2800);
      };
      const getSelectedPayment = () => {
        return document.querySelector('input[name="payment"]:checked')?.value || 'Pix';
      };
      const getSubtotal = () => {
        return cart.reduce((sum, item) => sum + item.price * item.qty, 0);
      };
      const getTotal = () => {
        return Math.max(0, getSubtotal() - discount);
      };
      const getItemsCount = () => {
        return cart.reduce((sum, item) => sum + item.qty, 0);
      };
      const updateClock = () => {
        const now = new Date();
        pdvClock.textContent = now.toLocaleTimeString('pt-BR', {
          hour: '2-digit',
          minute: '2-digit'
        });
      };
      const updateSaleCode = () => {
        saleCode.textContent = `Venda #AF-${String(saleNumber).padStart(4, '0')}`;
      };
      const renderProducts = () => {
        const term = productSearch.value.trim().toLowerCase();
        const filtered = products.filter((product) => {
          const matchCategory = activeCategory === 'todos' || product.category === activeCategory;
          const matchSearch = !term || product.code.includes(term) || product.name.toLowerCase().includes(term);
          return matchCategory && matchSearch;
        });
        productGrid.innerHTML = filtered.map((product) => ` <button class="pdv-product" type="button" data-code="${product.code}"> <span class="emoji">${product.icon}</span> <strong>${product.name}</strong> <small>Cód. ${product.code} · Estoque ${product.stock}</small> <b>${money(product.price)}</b> </button> `).join('');
        productGrid.querySelectorAll('[data-code]').forEach((button) => {
          button.addEventListener('click', () => {
            addProduct(button.dataset.code);
          });
        });
      };
      const renderCart = () => {
        if (!cart.length) {
          cartContainer.innerHTML = ` <div class="pdv-empty"> <div> <strong>Nenhum item na venda</strong> <p>Digite o código, busque o produto ou use os atalhos rápidos para começar.</p> </div> </div> `;
        } else {
          cartContainer.innerHTML = cart.map((item) => ` <div class="pdv-cart-item"> <div class="pdv-cart-item-name"> <strong>${item.icon} ${item.name}</strong> <small>Cód. ${item.code} · Unitário ${money(item.price)}</small> </div> <div class="pdv-cart-qty"> <button type="button" data-dec="${item.code}">−</button> <span>${item.qty}</span> <button type="button" data-inc="${item.code}">+</button> </div> <div class="pdv-cart-subtotal">${money(item.price * item.qty)}</div> <button class="pdv-remove" type="button" data-remove="${item.code}">×</button> </div> `).join('');
        }
        subtotalText.textContent = money(getSubtotal());
        discountText.textContent = money(discount);
        totalText.textContent = money(getTotal());
        itemsCountText.textContent = String(getItemsCount());
        paymentSelectedText.textContent = `Pagamento: ${getSelectedPayment()}`;
        $$('[data-inc]').forEach((button) => {
          button.addEventListener('click', () => changeQty(button.dataset.inc, 1));
        });
        $$('[data-dec]').forEach((button) => {
          button.addEventListener('click', () => changeQty(button.dataset.dec, -1));
        });
        $$('[data-remove]').forEach((button) => {
          button.addEventListener('click', () => removeProduct(button.dataset.remove));
        });
      };
      const renderHistory = () => {
        salesHistory.innerHTML = sales.map((sale) => ` <div class="pdv-history-item"> <div> <strong>${sale.code}</strong> <small>${sale.customer} · ${sale.payment}</small> </div> <b>${money(sale.total)}</b> </div> `).join('');
      };
      const addProduct = (code) => {
        const qty = Math.max(1, Number(productQty.value || 1));
        const product = products.find((item) => item.code === String(code));
        if (!product) {
          toast('Produto não encontrado.');
          return;
        }
        const current = cart.find((item) => item.code === product.code);
        if (current) {
          current.qty += qty;
        } else {
          cart.push({
            ...product,
            qty
          });
        }
        productQty.value = 1;
        productSearch.value = '';
        productSearch.focus();
        renderProducts();
        renderCart();
        toast(`${product.name} adicionado à venda.`);
      };
      const changeQty = (code, amount) => {
        cart = cart.map((item) => {
          if (item.code === code) {
            return {
              ...item,
              qty: Math.max(1, item.qty + amount)
            };
          }
          return item;
        });
        renderCart();
      };
      const removeProduct = (code) => {
        cart = cart.filter((item) => item.code !== code);
        renderCart();
        toast('Item removido.');
      };
      const findProductFromSearch = () => {
        const term = productSearch.value.trim().toLowerCase();
        if (!term) {
          toast('Digite um código ou nome do produto.');
          return;
        }
        const product = products.find((item) => item.code === term || item.name.toLowerCase().includes(term));
        if (!product) {
          toast('Produto não encontrado.');
          return;
        }
        addProduct(product.code);
      };
      const togglePixPanel = () => {
        const isPix = getSelectedPayment() === 'Pix';
        pixPanel.classList.toggle('is-visible', isPix);
        renderCart();
      };
      const finalizeSale = () => {
        if (!cart.length) {
          toast('Adicione produtos antes de finalizar.');
          return;
        }
        const payment = getSelectedPayment();
        const code = `AF-${String(Date.now()).slice(-5)}`;
        const customer = $('#customerName').value || 'Cliente balcão';
        const note = $('#saleNote').value || '';
        sales.unshift({
          code,
          total: getTotal(),
          payment,
          customer
        });
        sales = sales.slice(0, 8);
        const summary = cart.map((item) => `${item.qty}x ${item.name}`).join(', ');
        if (payment === 'WhatsApp') {
          const message = ['Olá, segue venda do caixa Arte&Flor:', '', `Venda: ${code}`, `Cliente: ${customer}`, `Itens: ${summary}`, `Total: ${money(getTotal())}`, `Pagamento: ${payment}`, `Observação: ${note || 'Nenhuma'}`].join('\n');
          window.open(`https://wa.me/${WHATSAPP_NUMBER}?text=${encodeURIComponent(message)}`, '_blank', 'noopener');
        }
        cart = [];
        discount = 0;
        saleNumber += 1;
        updateSaleCode();
        renderCart();
        renderHistory();
        toast(`Venda ${code} finalizada com ${payment}.`);
      };
      const cancelSale = () => {
        if (!cart.length) {
          toast('Não há venda para cancelar.');
          return;
        }
        if (!confirm('Cancelar a venda atual?')) {
          return;
        }
        cart = [];
        discount = 0;
        renderCart();
        toast('Venda cancelada.');
      };
      const applyDiscount = () => {
        const value = prompt('Informe o desconto em R$:', '10');
        if (value === null) {
          return;
        }
        discount = Math.max(0, Number(String(value).replace(',', '.')) || 0);
        renderCart();
        toast('Desconto aplicado.');
      };
      const holdSale = () => {
        if (!cart.length) {
          toast('Não há itens para suspender.');
          return;
        }
        localStorage.setItem('arteflor_pdv_suspended_sale', JSON.stringify({
          cart,
          discount,
          createdAt: new Date().toISOString()
        }));
        cart = [];
        discount = 0;
        renderCart();
        toast('Venda suspensa no navegador.');
      };
      productSearch.addEventListener('input', renderProducts);
      productSearch.addEventListener('keydown', (event) => {
        if (event.key === 'Enter') {
          event.preventDefault();
          findProductFromSearch();
        }
      });
      $('#addBySearch').addEventListener('click', findProductFromSearch);
      $('#clearSearch').addEventListener('click', () => {
        productSearch.value = '';
        productQty.value = 1;
        productSearch.focus();
        renderProducts();
      });
      $$('.pdv-shortcut').forEach((button) => {
        button.addEventListener('click', () => {
          addProduct(button.dataset.productCode);
        });
      });
      $$('.pdv-category').forEach((button) => {
        button.addEventListener('click', () => {
          $$('.pdv-category').forEach((item) => item.classList.remove('is-active'));
          button.classList.add('is-active');
          activeCategory = button.dataset.category;
          renderProducts();
        });
      });
      $$('input[name="payment"]').forEach((input) => {
        input.addEventListener('change', togglePixPanel);
      });
      $('#copyPix').addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(pixCode.textContent.trim());
          toast('Código Pix copiado.');
        } catch (error) {
          toast('Copie o código Pix manualmente.');
        }
      });
      $('#confirmPayment').addEventListener('click', () => {
        toast('Pagamento Pix confirmado visualmente.');
      });
      $('#finalizeSale').addEventListener('click', finalizeSale);
      $('#cancelSale').addEventListener('click', cancelSale);
      $('#applyDiscount').addEventListener('click', applyDiscount);
      $('#holdSale').addEventListener('click', holdSale);
      updateClock();
      setInterval(updateClock, 30000);
      updateSaleCode();
      renderProducts();
      renderCart();
      renderHistory();
      togglePixPanel();
    })();
  </script>
</body>

</html>