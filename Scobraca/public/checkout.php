<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

$baseUrl = '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Checkout FluxPay — Assinatura Growth</title>
    <meta name="description" content="Checkout demonstrativo da FluxPay para assinatura Growth, preparado para integração futura com Pix, cartão e boleto.">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#2F1C6A">

    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('/assets/icons/favicon.svg')) ?>">
    <link rel="preload" href="<?= e(asset_url('/assets/css/style.css')) ?>" as="style">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/style.css')) ?>">
</head>
<body class="checkout-page">
<a class="skip-link" href="#conteudo">Pular para o checkout</a>

<svg class="icon-sprite" aria-hidden="true" focusable="false">
    <symbol id="i-logo" viewBox="0 0 24 24"><path d="M5 6.5h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.9"/><path d="M7 11h7.2M7 14h4.2M16 14.5l2.4-2.4L16 9.7" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h13M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12.7 4 4L19 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><path d="M7 10V8a5 5 0 0 1 10 0v2M6.5 10h11a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.9"/></symbol>
    <symbol id="i-card" viewBox="0 0 24 24"><path d="M4 7.5h16v9A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-9Z" fill="none" stroke="currentColor" stroke-width="1.9"/><path d="M4 10.5h16M7.2 15.2h3.8" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></symbol>
</svg>

<header class="checkout-header" data-header>
    <div class="container checkout-header-shell">
        <a class="brand" href="<?= e(public_url('/index.php')) ?>" aria-label="Voltar para FluxPay">
            <span class="brand-mark"><svg><use href="#i-logo"></use></svg></span>
            <span class="brand-copy"><strong>FluxPay</strong><small>Checkout demonstrativo</small></span>
        </a>
        <div class="checkout-header-actions">
            <span><svg><use href="#i-lock"></use></svg> Ambiente preparado para integração segura</span>
            <a class="btn btn-outline" href="<?= e(public_url('/index.php')) ?>">Voltar para o site</a>
        </div>
    </div>
</header>

<main id="conteudo" class="checkout-main">
    <section class="checkout-page-hero">
        <div class="container">
            <div class="checkout-title reveal">
                <span class="badge">Checkout FluxPay</span>
                <h1>Finalize sua assinatura com clareza e sem distrações</h1>
                <p>Esta tela é uma simulação visual preparada para integração futura com gateway de pagamento. Nenhum pagamento real será processado agora.</p>
            </div>
        </div>
    </section>

    <section class="checkout-page-section">
        <div class="container checkout-page-grid">
            <form class="checkout-flow reveal" data-checkout-form novalidate>
                <section class="checkout-block">
                    <div class="checkout-step-heading">
                        <span>1</span>
                        <div>
                            <strong>Conta</strong>
                            <small>Dados para identificação da assinatura</small>
                        </div>
                    </div>
                    <div class="checkout-fields two-cols">
                        <label>E-mail<input type="email" name="email" autocomplete="email" placeholder="voce@empresa.com" required><small data-error-for="email"></small></label>
                        <label>WhatsApp<input type="tel" name="phone" autocomplete="tel" placeholder="(00) 00000-0000" required><small data-error-for="phone"></small></label>
                    </div>
                    <label>Nome completo<input type="text" name="name" autocomplete="name" placeholder="Seu nome completo" required><small data-error-for="name"></small></label>
                </section>

                <section class="checkout-block">
                    <div class="checkout-step-heading">
                        <span>2</span>
                        <div>
                            <strong>Plano</strong>
                            <small>Growth selecionado para recorrência mensal</small>
                        </div>
                    </div>
                    <div class="selected-plan-row">
                        <div><span>Plano selecionado</span><strong>Growth</strong></div>
                        <div><span>Valor mensal</span><strong>R$ 89,00</strong></div>
                    </div>
                    <div class="checkout-cycle" data-checkout-cycle>
                        <button type="button" class="active" data-cycle="monthly">Mensal</button>
                        <button type="button" data-cycle="annual">Anual</button>
                    </div>
                    <label>Cupom de desconto<input type="text" name="coupon" value="BOASVINDAS" autocomplete="off"><small>Exemplo visual. Validar cupom no back-end futuramente.</small></label>
                </section>

                <section class="checkout-block">
                    <div class="checkout-step-heading">
                        <span>3</span>
                        <div>
                            <strong>Pagamento</strong>
                            <small>Escolha uma forma para a integração futura</small>
                        </div>
                    </div>
                    <div class="checkout-payment-methods" data-checkout-payments>
                        <button type="button" class="active" data-payment="pix"><svg><use href="#i-check"></use></svg> Pix</button>
                        <button type="button" data-payment="card"><svg><use href="#i-card"></use></svg> Cartão</button>
                        <button type="button" data-payment="boleto"><svg><use href="#i-lock"></use></svg> Boleto</button>
                    </div>
                    <div class="payment-preview" data-payment-preview>
                        <strong>Pix selecionado</strong>
                        <p>Após a integração real, o sistema poderá exibir QR Code, copia e cola e confirmação automática do gateway.</p>
                    </div>
                </section>

                <section class="checkout-block">
                    <div class="checkout-step-heading">
                        <span>4</span>
                        <div>
                            <strong>Confirmação</strong>
                            <small>Revise os dados antes de finalizar</small>
                        </div>
                    </div>
                    <label class="checkbox-line"><input type="checkbox" name="terms" required> Aceito receber contato sobre a assinatura FluxPay e entendo que este checkout ainda é demonstrativo.<small data-error-for="terms"></small></label>
                    <button class="btn btn-solid checkout-submit" type="submit">Finalizar assinatura</button>
                    <p class="form-status" data-checkout-status role="status" aria-live="polite"></p>
                </section>
            </form>

            <aside class="checkout-summary reveal" data-parallax-card>
                <div class="summary-card">
                    <span class="summary-kicker">Resumo do pedido</span>
                    <h2>Plano Growth</h2>
                    <p>Ideal para vender de forma recorrente com links, assinaturas e relatórios por período.</p>
                    <div class="summary-lines">
                        <div><span>Mensalidade</span><strong data-summary-price>R$ 89,00/mês</strong></div>
                        <div><span>Desconto aplicado</span><strong data-summary-discount>-R$ 30,00</strong></div>
                        <div class="total"><span>Total hoje</span><strong data-summary-total>R$ 59,00</strong></div>
                        <div><span>Renovação</span><strong data-summary-renewal>R$ 89,00/mês</strong></div>
                    </div>
                    <div class="summary-trust">
                        <span><svg><use href="#i-check"></use></svg> Cancele quando quiser</span>
                        <span><svg><use href="#i-check"></use></svg> Suporte na implantação</span>
                        <span><svg><use href="#i-check"></use></svg> Dados tratados com boas práticas</span>
                        <span><svg><use href="#i-check"></use></svg> Gateway será integrado futuramente</span>
                    </div>
                    <small>Não afirmar certificações inexistentes. Este layout está preparado para integração segura com provedor de pagamento.</small>
                </div>
            </aside>
        </div>
    </section>
</main>

<script src="<?= e(asset_url('/assets/js/main.js')) ?>" defer></script>
</body>
</html>
