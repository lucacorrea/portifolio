<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

if (!empty($_SESSION['usuario'])) {
    if (($_SESSION['usuario']['tipo'] ?? '') === 'platform_admin') {
        redirect('/admin/dashboard.php');
    }

    redirect('/app/dashboard.php');
}

$baseUrl = '';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FluxPay — Cobranças Recorrentes, Links de Pagamento e Assinaturas</title>
    <meta name="description" content="Organize cobranças recorrentes, envie links de pagamento, acompanhe assinaturas e reduza inadimplência com a FluxPay.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#2F1C6A">

    <meta property="og:type" content="website">
    <meta property="og:title" content="FluxPay — Cobranças recorrentes e links de pagamento">
    <meta property="og:description" content="Uma plataforma para criar cobranças, gerenciar assinaturas, controlar clientes e acompanhar recebimentos recorrentes.">
    <meta property="og:image" content="<?= e(asset_url('/assets/img/fluxpay-og.svg')) ?>">
    <meta property="og:locale" content="pt_BR">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="FluxPay — Cobranças Recorrentes e Assinaturas">
    <meta name="twitter:description" content="Links de pagamento, assinaturas, relatórios e checkout em uma experiência SaaS moderna.">
    <meta name="twitter:image" content="<?= e(asset_url('/assets/img/fluxpay-og.svg')) ?>">

    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('/assets/icons/favicon.svg')) ?>">
    <link rel="preload" href="<?= e(asset_url('/assets/css/style.css')) ?>" as="style">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/style.css')) ?>">
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<svg class="icon-sprite" aria-hidden="true" focusable="false">
    <symbol id="i-logo" viewBox="0 0 24 24"><path d="M5 6.5h14a2 2 0 0 1 2 2v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-7a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.9"/><path d="M7 11h7.2M7 14h4.2M16 14.5l2.4-2.4L16 9.7" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-arrow" viewBox="0 0 24 24"><path d="M5 12h13M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-check" viewBox="0 0 24 24"><path d="m5 12.7 4 4L19 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-x" viewBox="0 0 24 24"><path d="m6.5 6.5 11 11M17.5 6.5l-11 11" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></symbol>
    <symbol id="i-menu" viewBox="0 0 24 24"><path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/></symbol>
    <symbol id="i-chevron" viewBox="0 0 24 24"><path d="m7 9.5 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-card" viewBox="0 0 24 24"><path d="M4 7.5h16v9A2.5 2.5 0 0 1 17.5 19h-11A2.5 2.5 0 0 1 4 16.5v-9Z" fill="none" stroke="currentColor" stroke-width="1.9"/><path d="M4 10.5h16M7.2 15.2h3.8" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></symbol>
    <symbol id="i-repeat" viewBox="0 0 24 24"><path d="M17.5 3 21 6.5 17.5 10M3 11V9.5a3 3 0 0 1 3-3h15M6.5 21 3 17.5 6.5 14M21 13v1.5a3 3 0 0 1-3 3H3" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round" stroke-linejoin="round"/></symbol>
    <symbol id="i-users" viewBox="0 0 24 24"><path d="M16.5 20v-1.3a3.7 3.7 0 0 0-3.7-3.7H8.2a3.7 3.7 0 0 0-3.7 3.7V20M10.5 11.5a3.7 3.7 0 1 0 0-7.4 3.7 3.7 0 0 0 0 7.4ZM20 20v-1.1a3.4 3.4 0 0 0-2.5-3.3M16.8 4.7a3.3 3.3 0 0 1 0 6.2" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></symbol>
    <symbol id="i-chart" viewBox="0 0 24 24"><path d="M4.5 19h15M7 16v-5.5M12 16V6M17 16V8.5" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linecap="round"/></symbol>
    <symbol id="i-lock" viewBox="0 0 24 24"><path d="M7 10V8a5 5 0 0 1 10 0v2M6.5 10h11a2 2 0 0 1 2 2v6a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2v-6a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.9"/></symbol>
    <symbol id="i-bell" viewBox="0 0 24 24"><path d="M18 10.5c0-3.6-2.1-6-6-6s-6 2.4-6 6v3.8L4.5 17h15L18 14.3v-3.8ZM10 19.4a2.2 2.2 0 0 0 4 0" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/></symbol>
    <symbol id="i-star" viewBox="0 0 24 24"><path d="m12 3.6 2.4 4.9 5.4.8-3.9 3.8.9 5.4L12 16l-4.8 2.5.9-5.4-3.9-3.8 5.4-.8L12 3.6Z" fill="currentColor"/></symbol>
    <symbol id="i-spark" viewBox="0 0 24 24"><path d="M12 2.8 14.3 9l6.1 2.3-6.1 2.4L12 20l-2.3-6.3-6.1-2.4L9.7 9 12 2.8Z" fill="none" stroke="currentColor" stroke-width="1.9" stroke-linejoin="round"/></symbol>
</svg>

<header class="site-header" data-header>
    <div class="container header-shell">
        <a class="brand" href="#inicio" aria-label="FluxPay">
            <span class="brand-mark"><svg><use href="#i-logo"></use></svg></span>
            <span class="brand-copy"><strong>FluxPay</strong><small>Pagamentos recorrentes</small></span>
        </a>

        <nav class="main-nav" id="menu-principal" aria-label="Menu principal" data-mobile-menu>
            <div class="nav-item has-mega">
                <button type="button" class="nav-link" data-mega-trigger="plataforma" aria-expanded="false">Plataforma <svg><use href="#i-chevron"></use></svg></button>
                <div class="mega-menu" data-mega-panel="plataforma">
                    <a href="#crie-cobre"><span><svg><use href="#i-repeat"></use></svg></span><strong>Cobranças recorrentes</strong><small>Planos e cobranças automáticas.</small></a>
                    <a href="#ferramentas"><span><svg><use href="#i-card"></use></svg></span><strong>Links de pagamento</strong><small>Envie links com uma jornada clara.</small></a>
                    <a href="#controle"><span><svg><use href="#i-users"></use></svg></span><strong>Gestão de clientes</strong><small>Histórico e status em um lugar.</small></a>
                    <a href="<?= e(public_url('/checkout.php')) ?>"><span><svg><use href="#i-lock"></use></svg></span><strong>Checkout</strong><small>Fluxo limpo para conversão.</small></a>
                </div>
            </div>

            <div class="nav-item has-mega">
                <button type="button" class="nav-link" data-mega-trigger="solucoes" aria-expanded="false">Soluções <svg><use href="#i-chevron"></use></svg></button>
                <div class="mega-menu mega-menu-small" data-mega-panel="solucoes">
                    <a href="#prova-social"><span><svg><use href="#i-check"></use></svg></span><strong>Escolas</strong><small>Mensalidades e contratos.</small></a>
                    <a href="#prova-social"><span><svg><use href="#i-check"></use></svg></span><strong>Academias</strong><small>Assinaturas e vencimentos.</small></a>
                    <a href="#prova-social"><span><svg><use href="#i-check"></use></svg></span><strong>Clínicas</strong><small>Recebíveis e clientes ativos.</small></a>
                    <a href="#prova-social"><span><svg><use href="#i-check"></use></svg></span><strong>Agências</strong><small>Recorrência para serviços.</small></a>
                </div>
            </div>

            <a class="nav-link plain" href="#ferramentas">Recursos</a>
            <a class="nav-link plain" href="#planos">Preços</a>
            <a class="nav-link plain" href="#seguranca">Segurança</a>
            <a class="nav-link plain" href="#clientes">Clientes</a>
        </nav>

        <div class="header-actions">
            <button class="country-pill" type="button" aria-label="Idioma Brasil">BR</button>
            <a class="btn btn-outline" href="<?= e(public_url('/login.php')) ?>">Entrar</a>
            <a class="btn btn-solid" href="#lead">Começar agora</a>
            <button class="menu-toggle" type="button" aria-label="Abrir menu" aria-controls="menu-principal" aria-expanded="false" data-menu-toggle>
                <svg class="open-icon"><use href="#i-menu"></use></svg>
                <svg class="close-icon"><use href="#i-x"></use></svg>
            </button>
        </div>
    </div>
</header>

<main id="conteudo">
    <section class="hero section-soft" id="inicio" aria-labelledby="hero-title">
        <div class="hero-bg" aria-hidden="true"></div>
        <div class="container">
            <div class="hero-center reveal">
                <span class="badge">FluxPay para cobranças recorrentes</span>
                <h1 id="hero-title">Receba pagamentos recorrentes sem complicar sua operação</h1>
                <p>Crie cobranças, envie links de pagamento, gerencie assinaturas e acompanhe seus recebimentos em uma plataforma simples, segura e feita para negócios que vendem todos os meses.</p>
                <div class="hero-actions">
                    <a class="btn btn-solid btn-large" href="#lead">Começar agora <svg><use href="#i-arrow"></use></svg></a>
                    <a class="btn btn-ghost btn-large" href="#planos">Ver planos</a>
                </div>
                <div class="trust-line" aria-label="Condições comerciais">
                    <span><svg><use href="#i-check"></use></svg> 7 dias para testar</span>
                    <span><svg><use href="#i-check"></use></svg> Cancele quando quiser</span>
                    <span><svg><use href="#i-check"></use></svg> Suporte na implantação</span>
                </div>
            </div>

            <div class="hero-offer-grid">
                <article class="offer-card reveal" data-parallax-card>
                    <div class="offer-icon"><svg><use href="#i-chart"></use></svg></div>
                    <div>
                        <h2>Planos para qualquer fase do seu negócio</h2>
                        <p>Comece com cobranças simples e evolua para assinaturas, relatórios e automações.</p>
                        <strong>A partir de R$ 49/mês</strong>
                    </div>
                    <a href="#planos">Ver planos <svg><use href="#i-arrow"></use></svg></a>
                </article>

                <article class="offer-card reveal" data-parallax-card>
                    <div class="offer-icon accent"><svg><use href="#i-card"></use></svg></div>
                    <div>
                        <h2>Checkout preparado para conversão</h2>
                        <p>Uma experiência de pagamento objetiva, segura e sem distrações para seus clientes.</p>
                        <strong>Pix, cartão e boleto</strong>
                    </div>
                    <a href="<?= e(public_url('/checkout.php')) ?>">Ver checkout <svg><use href="#i-arrow"></use></svg></a>
                </article>
            </div>
        </div>
    </section>

    <section class="social-proof" id="prova-social" aria-labelledby="proof-title">
        <div class="container">
            <div class="section-title centered reveal">
                <span class="kicker">Operações recorrentes</span>
                <h2 id="proof-title">Empresas recorrentes precisam de cobrança previsível</h2>
                <p>FluxPay foi pensada para escolas, academias, clínicas, agências, infoprodutores e empresas de serviço que precisam controlar pagamentos todos os meses.</p>
            </div>
            <div class="logo-cloud reveal" aria-label="Logos fictícios">
                <span>Escola Prisma</span>
                <span>Studio Move</span>
                <span>Clínica Norte</span>
                <span>Agência Alpha</span>
                <span>Curso Prime</span>
                <span>Consultoria Beta</span>
            </div>
        </div>
    </section>

    <section class="testimonials" id="clientes" aria-labelledby="testimonials-title">
        <div class="container">
            <div class="section-row reveal">
                <div>
                    <span class="kicker">Clientes</span>
                    <h2 id="testimonials-title">Eles organizaram suas cobranças — agora é a sua vez</h2>
                </div>
                <div class="carousel-actions">
                    <button type="button" aria-label="Depoimento anterior" data-carousel-prev><svg><use href="#i-arrow"></use></svg></button>
                    <button type="button" aria-label="Próximo depoimento" data-carousel-next><svg><use href="#i-arrow"></use></svg></button>
                </div>
            </div>

            <div class="testimonial-carousel reveal" data-carousel tabindex="0" aria-label="Carrossel de depoimentos">
                <article class="testimonial-card">
                    <div class="testimonial-author"><span>MC</span><div><strong>Mariana Costa</strong><small>Diretora administrativa — Educação</small></div></div>
                    <p>Antes, o controle das mensalidades era manual. Com a FluxPay, conseguimos acompanhar cobranças pagas, pendentes e vencidas em poucos cliques.</p>
                    <div class="tag-list"><span>Cobranças recorrentes</span><span>Relatórios</span><span>Clientes</span></div>
                </article>
                <article class="testimonial-card">
                    <div class="testimonial-author"><span>RL</span><div><strong>Rafael Lima</strong><small>Gestor — Academia</small></div></div>
                    <p>O painel deixa claro quem pagou, quem atrasou e quais cobranças precisam de atenção. A rotina ficou muito mais previsível.</p>
                    <div class="tag-list"><span>Assinaturas</span><span>Lembretes</span><span>Dashboard</span></div>
                </article>
                <article class="testimonial-card">
                    <div class="testimonial-author"><span>BT</span><div><strong>Bianca Torres</strong><small>Sócia — Agência digital</small></div></div>
                    <p>Os links de pagamento tornaram nossa cobrança muito mais profissional e reduziram a troca de mensagens antes do pagamento.</p>
                    <div class="tag-list"><span>Links de pagamento</span><span>Checkout</span><span>Recorrência</span></div>
                </article>
            </div>
        </div>
    </section>

    <section class="create-charge section-soft" id="crie-cobre" aria-labelledby="create-title">
        <div class="container split-layout">
            <div class="section-title reveal">
                <span class="kicker">Crie e cobre</span>
                <h2 id="create-title">Crie cobranças em poucos segundos</h2>
                <p>Gere cobranças únicas, recorrentes ou links de pagamento sem depender de planilhas, mensagens soltas ou controle manual.</p>
                <div class="inline-actions">
                    <a class="btn btn-solid" href="#lead">Criar primeira cobrança</a>
                    <a class="btn btn-ghost" href="#controle">Ver como funciona</a>
                </div>
            </div>

            <div class="charge-builder reveal" data-parallax-card aria-label="Mockup animado de criação de cobrança">
                <div class="builder-header">
                    <span>Nova cobrança</span>
                    <strong>Link em criação</strong>
                </div>
                <div class="builder-fields">
                    <label>Cliente <span class="typing typing-client">Escola Prisma</span></label>
                    <label>Valor <span class="typing typing-value">R$ 890,00</span></label>
                    <label>Vencimento <span class="typing typing-date">15/06/2026</span></label>
                </div>
                <div class="payment-choice">
                    <span class="active">Pix</span>
                    <span>Cartão</span>
                    <span>Boleto</span>
                </div>
                <button type="button">Gerar cobrança</button>
                <div class="generated-link">
                    <small>Preview do link</small>
                    <strong>fluxpay.link/prisma-890</strong>
                    <em>Pronto para enviar</em>
                </div>
            </div>
        </div>
    </section>

    <section class="control-section" id="controle" aria-labelledby="control-title">
        <div class="container control-grid">
            <div class="control-copy reveal">
                <span class="kicker dark">Mais poder e controle</span>
                <h2 id="control-title">Mais controle sobre recebimentos, assinaturas e inadimplência</h2>
                <div class="control-cards">
                    <article><svg><use href="#i-repeat"></use></svg><h3>Automatize cobranças</h3><p>Reduza trabalho manual com cobranças recorrentes e lembretes preparados para automação.</p></article>
                    <article><svg><use href="#i-chart"></use></svg><h3>Acompanhe status em tempo real</h3><p>Visualize pagamentos pagos, pendentes, vencidos e cancelados em uma única tela.</p></article>
                    <article><svg><use href="#i-users"></use></svg><h3>Organize clientes</h3><p>Mantenha histórico, dados e cobranças de cada cliente em uma estrutura clara.</p></article>
                    <article><svg><use href="#i-spark"></use></svg><h3>Escale sua operação</h3><p>Comece simples e evolua para planos, API, multiusuários e relatórios avançados.</p></article>
                </div>
            </div>

            <div class="dashboard-showcase reveal" data-parallax-card aria-label="Mockup de dashboard FluxPay">
                <div class="dash-top">
                    <div><span>Receita prevista</span><strong>R$ 72.450</strong></div>
                    <div><span>Assinaturas</span><strong>486</strong></div>
                    <div><span>Em atraso</span><strong>31</strong></div>
                </div>
                <div class="dash-chart" aria-hidden="true">
                    <span style="height:52%"></span><span style="height:70%"></span><span style="height:44%"></span><span style="height:86%"></span><span style="height:66%"></span><span style="height:92%"></span>
                </div>
                <div class="dash-table">
                    <div><span>Maria Lopes</span><b class="paid">Pago</b><strong>R$ 280</strong></div>
                    <div><span>Agência Norte</span><b class="pending">Pendente</b><strong>R$ 1.900</strong></div>
                    <div><span>Academia Alpha</span><b class="late">Vencido</b><strong>R$ 640</strong></div>
                </div>
            </div>
        </div>
    </section>

    <section class="stats" aria-labelledby="stats-title">
        <div class="container">
            <div class="section-title centered reveal">
                <span class="kicker">Indicadores demonstrativos</span>
                <h2 id="stats-title">Uma operação recorrente precisa de números claros</h2>
            </div>
            <!-- Substituir por métricas reais quando disponíveis. -->
            <div class="stats-grid reveal">
                <article><strong data-counter data-count="2500" data-prefix="+">0</strong><span>cobranças gerenciadas</span></article>
                <article><strong data-counter data-count="94" data-suffix="%">0</strong><span>de pagamentos acompanhados</span></article>
                <article><strong data-counter data-count="120" data-prefix="+">0</strong><span>operações em implantação</span></article>
                <article><strong data-counter data-count="3" data-suffix="x">0</strong><span>mais organização na cobrança</span></article>
            </div>
        </div>
    </section>

    <section class="smart-tools section-soft" id="ferramentas" aria-labelledby="tools-title">
        <div class="container">
            <div class="section-title centered reveal">
                <span class="kicker">Ferramentas inteligentes</span>
                <h2 id="tools-title">Ferramentas inteligentes para cobrar melhor</h2>
                <p>A FluxPay centraliza recursos que ajudam sua empresa a vender, cobrar, acompanhar e tomar decisões com mais clareza.</p>
            </div>
            <div class="tools-grid">
                <article class="tool-card reveal"><svg><use href="#i-spark"></use></svg><h3>Sugestão de cobrança</h3><p>Destaque clientes com cobranças próximas do vencimento.</p></article>
                <article class="tool-card reveal"><svg><use href="#i-chart"></use></svg><h3>Relatórios financeiros</h3><p>Veja receita prevista, valores recebidos e pendências por período.</p></article>
                <article class="tool-card reveal"><svg><use href="#i-bell"></use></svg><h3>Lembretes preparados</h3><p>Estrutura pronta para notificações por e-mail e WhatsApp.</p></article>
                <article class="tool-card reveal"><svg><use href="#i-card"></use></svg><h3>Checkout objetivo</h3><p>Pagamento claro, rápido e com resumo do pedido sempre visível.</p></article>
                <article class="tool-card reveal"><svg><use href="#i-users"></use></svg><h3>Área do cliente</h3><p>Permita que o cliente visualize cobranças, links e status.</p></article>
                <article class="tool-card reveal"><svg><use href="#i-logo"></use></svg><h3>API futura</h3><p>Base planejada para integrações com sistemas externos.</p></article>
            </div>
        </div>
    </section>

    <section class="pricing" id="planos" aria-labelledby="pricing-title">
        <div class="container">
            <div class="section-title centered reveal">
                <span class="kicker">Planos e preços</span>
                <h2 id="pricing-title">Escolha o plano ideal para sua operação</h2>
            </div>
            <div class="pricing-badges reveal">
                <span><svg><use href="#i-check"></use></svg> 7 dias para testar</span>
                <span><svg><use href="#i-check"></use></svg> Cancele quando quiser</span>
                <span><svg><use href="#i-check"></use></svg> Suporte na implantação</span>
            </div>

            <div class="plans-grid">
                <article class="price-card reveal">
                    <span class="plan-label">Para começar</span>
                    <h3>Start</h3>
                    <p>Perfeito para autônomos e pequenos negócios.</p>
                    <div class="old-price">R$ 79,00</div>
                    <div class="plan-price"><span>R$</span><strong>49</strong><em>,00/mês</em></div>
                    <a class="btn btn-plan" href="#lead">Começar</a>
                    <ul><li>Até 50 cobranças/mês</li><li>Links de pagamento</li><li>Cadastro de clientes</li><li>Dashboard básico</li><li>Relatórios simples</li><li>Suporte por e-mail</li></ul>
                </article>

                <article class="price-card reveal">
                    <span class="plan-label popular">Mais popular</span>
                    <h3>Growth</h3>
                    <p>Tudo que você precisa para vender de forma recorrente.</p>
                    <div class="old-price">R$ 149,00</div>
                    <div class="plan-price"><span>R$</span><strong>89</strong><em>,00/mês</em></div>
                    <a class="btn btn-plan" href="#lead">Escolher Growth</a>
                    <ul><li>Cobranças recorrentes</li><li>Links ilimitados</li><li>Assinaturas</li><li>Lembretes preparados</li><li>Dashboard completo</li><li>Relatórios por período</li><li>Área do cliente</li></ul>
                </article>

                <article class="price-card recommended reveal">
                    <span class="recommend-badge">Recomendado</span>
                    <span class="plan-label">Mais controle</span>
                    <h3>Pro</h3>
                    <p>Mais recursos para empresas que querem controle e escala.</p>
                    <div class="old-price">R$ 249,00</div>
                    <div class="plan-price"><span>R$</span><strong>129</strong><em>,00/mês</em></div>
                    <a class="btn btn-solid btn-plan" href="#lead">Começar com Pro</a>
                    <ul><li>Tudo do Growth</li><li>Clientes ilimitados</li><li>Relatórios avançados</li><li>Multiusuários</li><li>Controle de permissões</li><li>Exportação de dados</li><li>Suporte prioritário</li></ul>
                </article>

                <article class="price-card reveal">
                    <span class="plan-label">Alta operação</span>
                    <h3>Scale</h3>
                    <p>Para empresas com alto volume e integrações.</p>
                    <div class="old-price">R$ 399,00</div>
                    <div class="plan-price"><span>R$</span><strong>199</strong><em>,00/mês</em></div>
                    <a class="btn btn-plan" href="#lead">Falar com vendas</a>
                    <ul><li>Tudo do Pro</li><li>API de integração</li><li>Webhooks futuramente</li><li>Consultoria de implantação</li><li>Integrações personalizadas</li><li>Atendimento consultivo</li><li>SLA comercial</li></ul>
                </article>
            </div>

            <p class="price-note">O valor exibido é mensal. Condições, integrações e taxas de gateway podem variar conforme o provedor de pagamento utilizado.</p>

            <button class="compare-toggle reveal" type="button" data-comparison-toggle aria-expanded="false" aria-controls="comparativo">Comparar todos os recursos <svg><use href="#i-chevron"></use></svg></button>
        </div>
    </section>

    <section class="comparison" id="comparativo" data-comparison-panel hidden aria-labelledby="comparison-title">
        <div class="container">
            <h2 id="comparison-title">Compare todos os recursos</h2>
            <div class="comparison-table-wrap" tabindex="0">
                <table>
                    <thead><tr><th>Recurso</th><th>Start</th><th>Growth</th><th>Pro</th><th>Scale</th></tr></thead>
                    <tbody>
                        <tr><td>Links de pagamento</td><td>Sim</td><td>Ilimitado</td><td>Ilimitado</td><td>Ilimitado</td></tr>
                        <tr><td>Cobranças recorrentes</td><td>Limitado</td><td>Sim</td><td>Sim</td><td>Sim</td></tr>
                        <tr><td>Assinaturas</td><td>Não</td><td>Sim</td><td>Sim</td><td>Sim</td></tr>
                        <tr><td>Cadastro de clientes</td><td>Sim</td><td>Sim</td><td>Ilimitado</td><td>Ilimitado</td></tr>
                        <tr><td>Dashboard</td><td>Básico</td><td>Completo</td><td>Completo</td><td>Avançado</td></tr>
                        <tr><td>Relatórios</td><td>Simples</td><td>Por período</td><td>Avançados</td><td>Exportáveis</td></tr>
                        <tr><td>Área do cliente</td><td>Não</td><td>Sim</td><td>Sim</td><td>Sim</td></tr>
                        <tr><td>Multiusuários</td><td>Não</td><td>Não</td><td>Sim</td><td>Sim</td></tr>
                        <tr><td>Permissões</td><td>Não</td><td>Não</td><td>Sim</td><td>Sim</td></tr>
                        <tr><td>API</td><td>Não</td><td>Não</td><td>Não</td><td>Sim</td></tr>
                        <tr><td>Suporte prioritário</td><td>Não</td><td>Não</td><td>Sim</td><td>Sim</td></tr>
                        <tr><td>Implantação</td><td>Guiada</td><td>Guiada</td><td>Prioritária</td><td>Consultiva</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="checkout-preview section-solid" id="checkout-preview" aria-labelledby="checkout-title">
        <div class="container">
            <div class="checkout-preview-panel reveal" data-parallax-card>
                <div class="section-title">
                    <span class="kicker dark">Checkout separado</span>
                    <h2 id="checkout-title">Uma página de pagamento dedicada para reduzir distrações</h2>
                    <p>O checkout agora fica em uma página própria, com etapas claras, resumo fixo e visual preparado para integração futura com gateway.</p>
                    <div class="inline-actions">
                        <a class="btn btn-light" href="<?= e(public_url('/checkout.php')) ?>">Abrir checkout</a>
                        <a class="btn btn-dark-outline" href="#planos">Ver planos</a>
                    </div>
                </div>
                <div class="checkout-mini-card" aria-hidden="true">
                    <div><span>Plano Growth</span><strong>R$ 89,00/mês</strong></div>
                    <div><span>Desconto</span><strong>-R$ 30,00</strong></div>
                    <div class="total"><span>Total hoje</span><strong>R$ 59,00</strong></div>
                    <button type="button">Finalizar assinatura</button>
                </div>
            </div>
        </div>
    </section>

    <section class="security" id="seguranca" aria-labelledby="security-title">
        <div class="container">
            <div class="section-title centered reveal">
                <span class="kicker">Segurança</span>
                <h2 id="security-title">Segurança, clareza e controle em cada cobrança</h2>
                <p>A FluxPay foi planejada para trabalhar com boas práticas de validação, controle de acesso, organização de dados e integração segura com provedores de pagamento.</p>
            </div>
            <div class="security-grid">
                <article class="tool-card reveal"><svg><use href="#i-check"></use></svg><h3>Validação de dados</h3></article>
                <article class="tool-card reveal"><svg><use href="#i-lock"></use></svg><h3>Controle de acesso</h3></article>
                <article class="tool-card reveal"><svg><use href="#i-chart"></use></svg><h3>Histórico de operações</h3></article>
                <article class="tool-card reveal"><svg><use href="#i-lock"></use></svg><h3>HTTPS/SSL no ambiente</h3></article>
                <article class="tool-card reveal"><svg><use href="#i-card"></use></svg><h3>Integração segura com gateway</h3></article>
                <article class="tool-card reveal"><svg><use href="#i-spark"></use></svg><h3>Estrutura preparada para auditoria</h3></article>
            </div>
        </div>
    </section>

    <section class="final-cta" id="lead" aria-labelledby="lead-title">
        <div class="container final-panel">
            <div class="final-copy reveal">
                <span class="kicker dark">Comece agora</span>
                <h2 id="lead-title">Comece a cobrar com mais profissionalismo</h2>
                <p>Organize seus clientes, automatize cobranças e acompanhe recebimentos em uma plataforma feita para operações recorrentes.</p>
                <div class="hero-actions">
                    <a class="btn btn-light" href="#lead-form">Começar agora</a>
                    <a class="btn btn-dark-outline" href="mailto:contato@fluxpay.com.br">Falar com especialista</a>
                </div>
                <div class="final-trust">Teste rápido • Cancele quando quiser • Suporte na implantação</div>
            </div>

            <form class="lead-form reveal" id="lead-form" data-lead-form novalidate>
                <div class="form-heading"><strong>Quero conhecer a FluxPay</strong><span>Sem envio real nesta versão.</span></div>
                <label>Nome<input name="name" type="text" autocomplete="name" placeholder="Seu nome completo" required><small data-error-for="name"></small></label>
                <label>E-mail<input name="email" type="email" autocomplete="email" placeholder="voce@empresa.com" required><small data-error-for="email"></small></label>
                <label>WhatsApp<input name="phone" type="tel" autocomplete="tel" placeholder="(00) 00000-0000" required><small data-error-for="phone"></small></label>
                <label>Tipo de negócio<select name="business" required><option value="">Selecione</option><option value="escola">Escola</option><option value="academia">Academia</option><option value="clinica">Clínica</option><option value="agencia">Agência</option><option value="infoprodutor">Infoprodutor</option><option value="servicos">Prestador de serviço</option></select><small data-error-for="business"></small></label>
                <button class="btn btn-solid" type="submit">Quero conhecer a FluxPay</button>
                <p class="form-status" data-form-status role="status" aria-live="polite"></p>
            </form>
        </div>
    </section>

    <section class="faq section-soft" id="faq" aria-labelledby="faq-title">
        <div class="container faq-layout">
            <div class="section-title reveal">
                <span class="kicker">FAQ</span>
                <h2 id="faq-title">Perguntas frequentes</h2>
            </div>
            <div class="faq-list" data-faq-list>
                <article class="faq-item reveal"><button type="button" aria-expanded="false">A FluxPay já processa pagamentos reais?<svg><use href="#i-chevron"></use></svg></button><div><p>A página está preparada para integração. O processamento real dependerá do gateway configurado, como Pix, cartão ou boleto.</p></div></article>
                <article class="faq-item reveal"><button type="button" aria-expanded="false">Posso usar para mensalidades?<svg><use href="#i-chevron"></use></svg></button><div><p>Sim. A proposta é facilitar cobranças mensais, assinaturas e contratos recorrentes.</p></div></article>
                <article class="faq-item reveal"><button type="button" aria-expanded="false">Consigo acompanhar cobranças vencidas?<svg><use href="#i-chevron"></use></svg></button><div><p>Sim. A interface apresenta status de cobranças pagas, pendentes, vencidas e canceladas.</p></div></article>
                <article class="faq-item reveal"><button type="button" aria-expanded="false">A plataforma tem área do cliente?<svg><use href="#i-chevron"></use></svg></button><div><p>A área do cliente está apresentada como recurso do produto, preparada para evolução no sistema.</p></div></article>
                <article class="faq-item reveal"><button type="button" aria-expanded="false">É possível integrar com Pix, cartão e boleto?<svg><use href="#i-chevron"></use></svg></button><div><p>Sim, a estrutura visual e técnica foi pensada para integração futura com provedores de pagamento.</p></div></article>
                <article class="faq-item reveal"><button type="button" aria-expanded="false">Posso cancelar o plano?<svg><use href="#i-chevron"></use></svg></button><div><p>Sim. O cancelamento deve seguir as regras comerciais do plano contratado.</p></div></article>
                <article class="faq-item reveal"><button type="button" aria-expanded="false">Existe suporte na implantação?<svg><use href="#i-chevron"></use></svg></button><div><p>Sim. A comunicação comercial prevê suporte para implantação e primeiros passos da operação.</p></div></article>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-intro">
            <a class="brand footer-brand" href="#inicio" aria-label="FluxPay"><span class="brand-mark"><svg><use href="#i-logo"></use></svg></span><span class="brand-copy"><strong>FluxPay</strong><small>Pagamentos recorrentes</small></span></a>
            <p>Plataforma para cobranças recorrentes, links de pagamento, checkout e gestão financeira de negócios com receita mensal.</p>
        </div>
        <nav><strong>FluxPay</strong><a href="#inicio">Plataforma</a><a href="#crie-cobre">Cobranças recorrentes</a><a href="#ferramentas">Links de pagamento</a><a href="<?= e(public_url('/checkout.php')) ?>">Checkout</a><a href="#controle">Relatórios</a></nav>
        <nav><strong>Soluções</strong><a href="#prova-social">Escolas</a><a href="#prova-social">Academias</a><a href="#prova-social">Clínicas</a><a href="#prova-social">Agências</a><a href="#prova-social">Infoprodutores</a><a href="#prova-social">Prestadores de serviço</a></nav>
        <nav><strong>Empresa</strong><a href="#inicio">Sobre</a><a href="#lead">Contato</a><a href="#lead">Parcerias</a><a href="#clientes">Blog</a></nav>
        <nav><strong>Suporte</strong><a href="#faq">Central de ajuda</a><a href="https://wa.me/5500000000000" target="_blank" rel="noopener noreferrer">WhatsApp</a><a href="mailto:contato@fluxpay.com.br">E-mail</a><a href="#seguranca">Status</a></nav>
        <nav><strong>Legal</strong><a href="#inicio">Termos de uso</a><a href="#inicio">Política de privacidade</a><a href="#inicio">Política de reembolso</a><a href="#inicio">Contrato de assinatura</a></nav>
        <div class="payment-methods"><strong>Meios de pagamento</strong><span>Pix</span><span>Cartão</span><span>Boleto</span></div>
    </div>
    <div class="container footer-bottom">
        <span>© 2026 FluxPay. Todos os direitos reservados.</span>
        <span>Desenvolvido por L&amp;J Soluções Tecnológicas.</span>
    </div>
</footer>

<script src="<?= e(asset_url('/assets/js/main.js')) ?>" defer></script>
</body>
</html>
