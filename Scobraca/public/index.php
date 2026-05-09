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
    <title>FluxPay - Plataforma de Cobranças Recorrentes e Links de Pagamento</title>
    <meta name="description" content="Automatize cobranças, gerencie assinaturas, envie links de pagamento e acompanhe recebimentos em uma plataforma moderna, segura e simples.">
    <meta name="robots" content="index, follow">
    <meta name="theme-color" content="#061826">

    <meta property="og:type" content="website">
    <meta property="og:title" content="FluxPay - Cobranças recorrentes e links de pagamento">
    <meta property="og:description" content="Controle cobranças, clientes, assinaturas e recebimentos com uma plataforma SaaS moderna e preparada para integrações seguras.">
    <meta property="og:image" content="<?= e(asset_url('/assets/img/fluxpay-og.svg')) ?>">
    <meta property="og:locale" content="pt_BR">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="FluxPay - Plataforma de Cobranças Recorrentes">
    <meta name="twitter:description" content="Venda, cobre, acompanhe pagamentos e reduza inadimplência com uma experiência simples e profissional.">
    <meta name="twitter:image" content="<?= e(asset_url('/assets/img/fluxpay-og.svg')) ?>">

    <link rel="icon" type="image/svg+xml" href="<?= e(asset_url('/assets/icons/favicon.svg')) ?>">
    <link rel="preload" href="<?= e(asset_url('/assets/css/landing.css')) ?>" as="style">
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/landing.css')) ?>">
</head>

<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<svg class="icon-sprite" aria-hidden="true" focusable="false">
    <symbol id="icon-logo" viewBox="0 0 24 24">
        <path d="M6.25 5.5h11.5a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6.25a2 2 0 0 1-2-2v-9a2 2 0 0 1 2-2Z" fill="none" stroke="currentColor" stroke-width="1.8"/>
        <path d="M7.5 10.2h6.8M7.5 13.7h4.2M15.4 14.5l2.1-2.1-2.1-2.1" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    </symbol>
    <symbol id="icon-arrow" viewBox="0 0 24 24">
        <path d="M5 12h13M13 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </symbol>
    <symbol id="icon-check" viewBox="0 0 24 24">
        <path d="m5 12.5 4.2 4.2L19 7" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
    </symbol>
    <symbol id="icon-x" viewBox="0 0 24 24">
        <path d="m6.5 6.5 11 11M17.5 6.5l-11 11" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </symbol>
    <symbol id="icon-menu" viewBox="0 0 24 24">
        <path d="M4 7h16M4 12h16M4 17h16" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
    </symbol>
    <symbol id="icon-shield" viewBox="0 0 24 24">
        <path d="M12 3.5 19 6v5.3c0 4.3-2.7 7.8-7 9.2-4.3-1.4-7-4.9-7-9.2V6l7-2.5Z" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
        <path d="m8.8 12 2.1 2.1 4.6-4.7" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    </symbol>
    <symbol id="icon-chart" viewBox="0 0 24 24">
        <path d="M4.8 18.5h14.4M7 15.5v-5M12 15.5V6.8M17 15.5v-8" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    </symbol>
    <symbol id="icon-card" viewBox="0 0 24 24">
        <path d="M4.5 7.5h15v9a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2v-9Z" fill="none" stroke="currentColor" stroke-width="1.8"/>
        <path d="M4.5 10h15M7.5 15h3" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    </symbol>
    <symbol id="icon-repeat" viewBox="0 0 24 24">
        <path d="M17 2.8 20.2 6 17 9.2M4 11V9a3 3 0 0 1 3-3h13M7 21.2 3.8 18 7 14.8M20 13v2a3 3 0 0 1-3 3H4" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    </symbol>
    <symbol id="icon-users" viewBox="0 0 24 24">
        <path d="M16.5 19.5v-1.2c0-1.9-1.6-3.4-3.5-3.4H8.5c-1.9 0-3.5 1.5-3.5 3.4v1.2M10.8 11.6a3.6 3.6 0 1 0 0-7.2 3.6 3.6 0 0 0 0 7.2ZM19 19.5v-1.1c0-1.6-1-2.8-2.5-3.2M16.7 4.8a3.1 3.1 0 0 1 0 6" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
    </symbol>
    <symbol id="icon-bell" viewBox="0 0 24 24">
        <path d="M18 10.5c0-3.5-2.1-6-6-6s-6 2.5-6 6v3.8L4.5 17h15L18 14.3v-3.8ZM10 19.2a2.2 2.2 0 0 0 4 0" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
    </symbol>
    <symbol id="icon-lock" viewBox="0 0 24 24">
        <path d="M7 10V8a5 5 0 0 1 10 0v2M6.5 10h11a1.8 1.8 0 0 1 1.8 1.8v6a1.8 1.8 0 0 1-1.8 1.8h-11a1.8 1.8 0 0 1-1.8-1.8v-6A1.8 1.8 0 0 1 6.5 10Z" fill="none" stroke="currentColor" stroke-width="1.8"/>
    </symbol>
    <symbol id="icon-mail" viewBox="0 0 24 24">
        <path d="M4.5 7.5h15v9a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2v-9Z" fill="none" stroke="currentColor" stroke-width="1.8"/>
        <path d="m5.5 8.5 6.5 5 6.5-5" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
    </symbol>
    <symbol id="icon-star" viewBox="0 0 24 24">
        <path d="m12 3.8 2.4 4.9 5.4.8-3.9 3.8.9 5.4-4.8-2.5-4.8 2.5.9-5.4-3.9-3.8 5.4-.8L12 3.8Z" fill="currentColor"/>
    </symbol>
    <symbol id="icon-chevron" viewBox="0 0 24 24">
        <path d="m7 9.5 5 5 5-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
    </symbol>
</svg>

<header class="site-header" data-header>
    <div class="container header-shell">
        <a class="brand" href="#inicio" aria-label="FluxPay">
            <span class="brand-mark"><svg><use href="#icon-logo"></use></svg></span>
            <span>
                <strong>FluxPay</strong>
                <small>Cobranças recorrentes</small>
            </span>
        </a>

        <nav class="nav-menu" id="menu-principal" aria-label="Menu principal" data-nav>
            <a href="#inicio">Início</a>
            <a href="#recursos">Recursos</a>
            <a href="#como-funciona">Como funciona</a>
            <a href="#planos">Planos</a>
            <a href="#seguranca">Segurança</a>
            <a href="#depoimentos">Depoimentos</a>
            <a href="#faq">FAQ</a>
        </nav>

        <div class="header-actions">
<<<<<<< ours
            <a class="btn btn-ghost" href="<?= e(public_url('/login.php')) ?>">Entrar</a>
=======
            <a class="btn btn-ghost" href="<?= e(asset_url('/login.php')) ?>">Entrar</a>
>>>>>>> theirs
            <a class="btn btn-gradient" href="#contato">Começar agora</a>
            <button class="menu-toggle" type="button" aria-label="Abrir menu" aria-controls="menu-principal" aria-expanded="false" data-menu-toggle>
                <svg class="menu-icon"><use href="#icon-menu"></use></svg>
                <svg class="close-icon"><use href="#icon-x"></use></svg>
            </button>
        </div>
    </div>
</header>

<main id="conteudo">
    <section class="hero-section section-dark" id="inicio" aria-labelledby="hero-title">
        <div class="hero-gridline" aria-hidden="true"></div>
        <div class="container hero-layout">
            <div class="hero-copy reveal">
                <span class="eyebrow">SaaS financeiro para operações recorrentes</span>
                <h1 id="hero-title">Cobranças automáticas, pagamentos recorrentes e controle financeiro em uma única plataforma.</h1>
                <p>A FluxPay ajuda empresas a vender, cobrar, acompanhar pagamentos e reduzir inadimplência com uma experiência simples, segura e profissional.</p>

                <div class="hero-actions-row">
                    <a class="btn btn-gradient btn-large" href="#contato">
                        Começar teste grátis
                        <svg><use href="#icon-arrow"></use></svg>
                    </a>
                    <a class="btn btn-dark-outline btn-large" href="#produto">Ver demonstração</a>
                </div>

                <p class="hero-microcopy">Sem burocracia • Configuração rápida • Ideal para negócios recorrentes</p>

                <div class="hero-proof-list" aria-label="Destaques da plataforma">
                    <span><svg><use href="#icon-check"></use></svg> Links de pagamento</span>
                    <span><svg><use href="#icon-check"></use></svg> Assinaturas</span>
                    <span><svg><use href="#icon-check"></use></svg> Relatórios</span>
                </div>
            </div>

            <div class="hero-visual reveal" aria-label="Prévia visual do painel FluxPay">
                <div class="floating-metric metric-received">
                    <span>Recebido este mês</span>
                    <strong>R$ 24.580</strong>
                </div>
                <div class="floating-metric metric-paid">
                    <span>Cobranças pagas</span>
                    <strong>94%</strong>
                </div>
                <div class="floating-metric metric-subscriptions">
                    <span>Assinaturas ativas</span>
                    <strong>127</strong>
                </div>
                <div class="floating-metric metric-methods">
                    <span>Métodos</span>
                    <strong>Pix, cartão e boleto</strong>
                </div>

                <div class="dashboard-preview hero-dashboard">
                    <div class="dashboard-sidebar">
                        <span class="sidebar-logo"></span>
                        <span class="sidebar-item active"></span>
                        <span class="sidebar-item"></span>
                        <span class="sidebar-item"></span>
                        <span class="sidebar-item"></span>
                    </div>
                    <div class="dashboard-content">
                        <div class="dashboard-toolbar">
                            <div>
                                <span>Painel financeiro</span>
                                <strong>Maio de 2026</strong>
                            </div>
                            <button type="button">Nova cobrança</button>
                        </div>
                        <div class="mini-kpis">
                            <article>
                                <span>Receita</span>
                                <strong>R$ 48.920</strong>
                                <em>+18%</em>
                            </article>
                            <article>
                                <span>Pendente</span>
                                <strong>R$ 6.410</strong>
                                <em class="warning">23 itens</em>
                            </article>
                            <article>
                                <span>Clientes</span>
                                <strong>342</strong>
                                <em>+31</em>
                            </article>
                        </div>
                        <div class="chart-card" aria-hidden="true">
                            <span style="height:48%"></span>
                            <span style="height:64%"></span>
                            <span style="height:42%"></span>
                            <span style="height:76%"></span>
                            <span style="height:58%"></span>
                            <span style="height:88%"></span>
                            <span style="height:72%"></span>
                        </div>
                        <div class="payment-list">
                            <div><span>Escola Prisma</span><b class="status paid">Pago</b><strong>R$ 890</strong></div>
                            <div><span>Studio Move</span><b class="status pending">Pendente</b><strong>R$ 420</strong></div>
                            <div><span>Clínica Norte</span><b class="status late">Vencido</b><strong>R$ 1.240</strong></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="trust-section" aria-labelledby="trust-title">
        <div class="container">
            <div class="trust-copy reveal">
                <h2 id="trust-title">Feito para empresas que precisam cobrar melhor, vender mais e controlar seus recebimentos.</h2>
            </div>
            <div class="trust-tags reveal">
                <span>Cobranças recorrentes</span>
                <span>Links de pagamento</span>
                <span>Gestão de clientes</span>
                <span>Relatórios financeiros</span>
                <span>Segurança de dados</span>
                <span>Integração futura via API</span>
            </div>
            <div class="logo-strip reveal" aria-label="Segmentos atendidos">
                <span>Empresa A</span>
                <span>Agência B</span>
                <span>Clínica C</span>
                <span>Escola D</span>
                <span>Academia E</span>
            </div>
        </div>
    </section>

    <section class="benefits-section section-light" id="beneficios" aria-labelledby="benefits-title">
        <div class="container">
            <div class="section-heading reveal">
                <span class="eyebrow">Por que usar a FluxPay?</span>
                <h2 id="benefits-title">Mais previsibilidade no caixa, menos trabalho manual na cobrança.</h2>
                <p>Centralize a operação financeira recorrente em um fluxo claro para equipe e cliente.</p>
            </div>

            <div class="card-grid benefits-grid">
                <article class="feature-card reveal">
                    <span class="card-icon"><svg><use href="#icon-bell"></use></svg></span>
                    <h3>Menos inadimplência</h3>
                    <p>Automatize lembretes, acompanhe pagamentos pendentes e tenha mais previsibilidade no caixa.</p>
                </article>
                <article class="feature-card reveal">
                    <span class="card-icon"><svg><use href="#icon-card"></use></svg></span>
                    <h3>Cobrança profissional</h3>
                    <p>Envie links de pagamento, organize clientes e mantenha uma experiência confiável do início ao fim.</p>
                </article>
                <article class="feature-card reveal">
                    <span class="card-icon"><svg><use href="#icon-repeat"></use></svg></span>
                    <h3>Assinaturas recorrentes</h3>
                    <p>Crie planos mensais, trimestrais ou anuais para produtos, serviços e contratos recorrentes.</p>
                </article>
                <article class="feature-card reveal">
                    <span class="card-icon"><svg><use href="#icon-chart"></use></svg></span>
                    <h3>Controle em tempo real</h3>
                    <p>Acompanhe pagamentos aprovados, pendentes, vencidos e cancelados em um painel simples.</p>
                </article>
                <article class="feature-card reveal">
                    <span class="card-icon"><svg><use href="#icon-users"></use></svg></span>
                    <h3>Experiência para o cliente</h3>
                    <p>Seu cliente paga com facilidade, visualiza informações claras e recebe uma jornada objetiva.</p>
                </article>
                <article class="feature-card reveal">
                    <span class="card-icon"><svg><use href="#icon-shield"></use></svg></span>
                    <h3>Escalável para crescer</h3>
                    <p>Comece simples e evolua com recursos avançados, integrações e automações.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="product-section section-dark" id="produto" aria-labelledby="product-title">
        <div class="container product-layout">
            <div class="product-copy reveal">
                <span class="eyebrow">Painel do produto</span>
                <h2 id="product-title">Tudo que você precisa para gerenciar cobranças em um só painel.</h2>
                <p>Uma visão operacional para acompanhar recebíveis, clientes, assinaturas e alertas sem depender de planilhas soltas.</p>
                <ul class="check-list">
                    <li><svg><use href="#icon-check"></use></svg> Visão geral de receitas</li>
                    <li><svg><use href="#icon-check"></use></svg> Status das cobranças</li>
                    <li><svg><use href="#icon-check"></use></svg> Clientes ativos</li>
                    <li><svg><use href="#icon-check"></use></svg> Assinaturas recorrentes</li>
                    <li><svg><use href="#icon-check"></use></svg> Links de pagamento</li>
                    <li><svg><use href="#icon-check"></use></svg> Histórico de transações</li>
                    <li><svg><use href="#icon-check"></use></svg> Alertas de vencimento</li>
                    <li><svg><use href="#icon-check"></use></svg> Relatórios por período</li>
                </ul>
            </div>

            <div class="product-dashboard reveal">
                <div class="dashboard-preview large-dashboard">
                    <div class="dashboard-sidebar full">
                        <span class="sidebar-logo"></span>
                        <span class="sidebar-label active">Resumo</span>
                        <span class="sidebar-label">Clientes</span>
                        <span class="sidebar-label">Assinaturas</span>
                        <span class="sidebar-label">Relatórios</span>
                    </div>
                    <div class="dashboard-content">
                        <div class="dashboard-toolbar">
                            <div>
                                <span>Recebíveis</span>
                                <strong>Operação ativa</strong>
                            </div>
                            <button type="button">Nova cobrança</button>
                        </div>
                        <div class="mini-kpis">
                            <article><span>Receita prevista</span><strong>R$ 72.450</strong><em>+12,4%</em></article>
                            <article><span>Assinaturas</span><strong>486</strong><em>Ativas</em></article>
                            <article><span>Em atraso</span><strong>31</strong><em class="warning">Ação sugerida</em></article>
                        </div>
                        <div class="chart-and-table">
                            <div class="line-panel" aria-hidden="true">
                                <span></span>
                                <i style="left:10%; bottom:34%"></i>
                                <i style="left:27%; bottom:52%"></i>
                                <i style="left:44%; bottom:43%"></i>
                                <i style="left:61%; bottom:67%"></i>
                                <i style="left:79%; bottom:58%"></i>
                            </div>
                            <div class="payment-list table-like">
                                <div><span>Maria Lopes</span><b class="status paid">Pago</b><strong>R$ 280</strong></div>
                                <div><span>Agência Norte</span><b class="status pending">Pendente</b><strong>R$ 1.900</strong></div>
                                <div><span>Academia Alpha</span><b class="status late">Vencido</b><strong>R$ 640</strong></div>
                                <div><span>Curso Prime</span><b class="status paid">Pago</b><strong>R$ 3.200</strong></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="steps-section" id="como-funciona" aria-labelledby="steps-title">
        <div class="container">
            <div class="section-heading reveal">
                <span class="eyebrow">Como funciona</span>
                <h2 id="steps-title">Da criação da conta ao acompanhamento financeiro em quatro etapas.</h2>
            </div>

            <div class="steps-line">
                <article class="step-card reveal">
                    <span>01</span>
                    <h3>Crie sua conta</h3>
                    <p>Configure sua empresa, dados básicos e preferências de cobrança.</p>
                </article>
                <article class="step-card reveal">
                    <span>02</span>
                    <h3>Cadastre clientes e planos</h3>
                    <p>Organize clientes, defina valores, recorrência e formas de pagamento.</p>
                </article>
                <article class="step-card reveal">
                    <span>03</span>
                    <h3>Envie cobranças</h3>
                    <p>Compartilhe links, gere cobranças e acompanhe tudo pelo painel.</p>
                </article>
                <article class="step-card reveal">
                    <span>04</span>
                    <h3>Receba e acompanhe</h3>
                    <p>Veja pagamentos em tempo real, reduza atrasos e tome decisões com dados.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="resources-section section-light" id="recursos" aria-labelledby="resources-title">
        <div class="container">
            <div class="section-heading reveal">
                <span class="eyebrow">Recursos</span>
                <h2 id="resources-title">Funcionalidades para uma operação de cobrança mais madura.</h2>
                <p>O pacote de recursos foi pensado para autônomos, escolas, clínicas, academias, agências, infoprodutores e negócios digitais.</p>
            </div>

            <div class="resources-grid">
                <article class="resource-card reveal"><svg><use href="#icon-card"></use></svg><h3>Links de pagamento personalizados</h3><p>Compartilhe cobranças com uma experiência objetiva para o cliente.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-repeat"></use></svg><h3>Cobranças únicas e recorrentes</h3><p>Atenda vendas pontuais, mensalidades, planos e contratos.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-users"></use></svg><h3>Cadastro de clientes</h3><p>Mantenha contatos, histórico e status financeiro organizados.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-repeat"></use></svg><h3>Planos e assinaturas</h3><p>Crie ofertas recorrentes com periodicidade editável.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-bell"></use></svg><h3>Alertas de vencimento</h3><p>Reduza esquecimento com lembretes preparados para automação.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-chart"></use></svg><h3>Relatórios financeiros</h3><p>Acompanhe desempenho por período, status e carteira.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-chart"></use></svg><h3>Dashboard de recebíveis</h3><p>Visualize valores pagos, pendentes, vencidos e cancelados.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-card"></use></svg><h3>Histórico de transações</h3><p>Registre eventos financeiros com rastreabilidade.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-check"></use></svg><h3>Controle de status</h3><p>Identifique prioridades de cobrança em poucos segundos.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-users"></use></svg><h3>Área do cliente</h3><p>Estrutura preparada para autosserviço e segunda via.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-lock"></use></svg><h3>Pix, cartão e boleto</h3><p>Preparada para integração futura com meios de pagamento.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-logo"></use></svg><h3>API para integrações</h3><p>Base planejada para conectar FluxPay a outros sistemas.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-chart"></use></svg><h3>Exportação de relatórios</h3><p>Facilite conferências financeiras e prestação de contas.</p></article>
                <article class="resource-card reveal"><svg><use href="#icon-mail"></use></svg><h3>E-mail e WhatsApp futuramente</h3><p>Notificações preparadas para escalar a comunicação.</p></article>
            </div>
        </div>
    </section>

    <section class="pricing-section" id="planos" aria-labelledby="pricing-title">
        <div class="container">
            <div class="section-heading reveal">
                <span class="eyebrow">Planos e preços</span>
                <h2 id="pricing-title">Escolha o plano ideal para sua operação</h2>
                <p>Comece simples e evolua conforme sua empresa cresce.</p>
            </div>

            <div class="pricing-trust reveal" aria-label="Condições dos planos">
                <span><svg><use href="#icon-check"></use></svg> Cancele quando quiser</span>
                <span><svg><use href="#icon-check"></use></svg> Suporte especializado</span>
                <span><svg><use href="#icon-check"></use></svg> Ambiente seguro</span>
                <span><svg><use href="#icon-check"></use></svg> Teste grátis disponível</span>
            </div>

            <div class="plans-grid">
                <article class="plan-card reveal">
                    <span class="plan-kicker">Starter</span>
                    <h3>Para autônomos e pequenos negócios.</h3>
                    <div class="price"><span>R$</span><strong>49</strong><em>/mês</em></div>
                    <a class="btn btn-plan" href="#contato">Começar no Starter</a>
                    <ul>
                        <li><svg><use href="#icon-check"></use></svg> Até 50 cobranças/mês</li>
                        <li><svg><use href="#icon-check"></use></svg> Links de pagamento</li>
                        <li><svg><use href="#icon-check"></use></svg> Cadastro de clientes</li>
                        <li><svg><use href="#icon-check"></use></svg> Dashboard básico</li>
                        <li><svg><use href="#icon-check"></use></svg> Relatórios simples</li>
                        <li><svg><use href="#icon-check"></use></svg> Suporte por e-mail</li>
                    </ul>
                </article>

                <article class="plan-card recommended reveal">
                    <div class="plan-badge">Mais recomendado</div>
                    <span class="discount-badge">Economia no anual</span>
                    <span class="plan-kicker">Pro</span>
                    <h3>Para empresas que já vendem de forma recorrente.</h3>
                    <div class="price"><span>R$</span><strong>99</strong><em>/mês</em></div>
                    <a class="btn btn-gradient btn-plan" href="#contato">Escolher Pro</a>
                    <ul>
                        <li><svg><use href="#icon-check"></use></svg> Cobranças ilimitadas</li>
                        <li><svg><use href="#icon-check"></use></svg> Assinaturas recorrentes</li>
                        <li><svg><use href="#icon-check"></use></svg> Lembretes automáticos</li>
                        <li><svg><use href="#icon-check"></use></svg> Dashboard completo</li>
                        <li><svg><use href="#icon-check"></use></svg> Relatórios avançados</li>
                        <li><svg><use href="#icon-check"></use></svg> Área do cliente</li>
                        <li><svg><use href="#icon-check"></use></svg> Suporte prioritário</li>
                    </ul>
                </article>

                <article class="plan-card reveal">
                    <span class="plan-kicker">Business</span>
                    <h3>Para empresas com alto volume.</h3>
                    <div class="price"><span>R$</span><strong>199</strong><em>/mês</em></div>
                    <a class="btn btn-plan" href="#contato">Falar com vendas</a>
                    <ul>
                        <li><svg><use href="#icon-check"></use></svg> Tudo do Pro</li>
                        <li><svg><use href="#icon-check"></use></svg> Multiusuários</li>
                        <li><svg><use href="#icon-check"></use></svg> Permissões por equipe</li>
                        <li><svg><use href="#icon-check"></use></svg> API de integração</li>
                        <li><svg><use href="#icon-check"></use></svg> Relatórios exportáveis</li>
                        <li><svg><use href="#icon-check"></use></svg> Integrações personalizadas</li>
                        <li><svg><use href="#icon-check"></use></svg> Atendimento consultivo</li>
                    </ul>
                </article>
            </div>
        </div>
    </section>

    <section class="comparison-section section-light" aria-labelledby="comparison-title">
        <div class="container">
            <div class="section-heading reveal">
                <span class="eyebrow">Comparativo</span>
                <h2 id="comparison-title">Compare os recursos antes de contratar.</h2>
            </div>

            <div class="comparison-table-wrap reveal" tabindex="0" aria-label="Tabela comparativa de planos">
                <table class="comparison-table">
                    <thead>
                        <tr>
                            <th>Recurso</th>
                            <th>Starter</th>
                            <th>Pro</th>
                            <th>Business</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td>Links de pagamento</td><td>Sim</td><td>Sim</td><td>Sim</td></tr>
                        <tr><td>Cobranças recorrentes</td><td>Limitado</td><td>Sim</td><td>Sim</td></tr>
                        <tr><td>Cadastro de clientes</td><td>Sim</td><td>Sim</td><td>Sim</td></tr>
                        <tr><td>Relatórios</td><td>Simples</td><td>Avançados</td><td>Exportáveis</td></tr>
                        <tr><td>Lembretes automáticos</td><td>Não</td><td>Sim</td><td>Sim</td></tr>
                        <tr><td>Área do cliente</td><td>Não</td><td>Sim</td><td>Sim</td></tr>
                        <tr><td>Multiusuários</td><td>Não</td><td>Não</td><td>Sim</td></tr>
                        <tr><td>API</td><td>Não</td><td>Não</td><td>Sim</td></tr>
                        <tr><td>Suporte prioritário</td><td>Não</td><td>Sim</td><td>Sim</td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>

    <section class="security-section section-dark" id="seguranca" aria-labelledby="security-title">
        <div class="container security-layout">
            <div class="security-copy reveal">
                <span class="eyebrow">Segurança</span>
                <h2 id="security-title">Segurança e transparência em cada cobrança.</h2>
                <p>A FluxPay foi pensada para operar com boas práticas de segurança, organização de dados e clareza nas transações.</p>
            </div>
            <div class="security-grid">
                <article class="security-card reveal"><svg><use href="#icon-lock"></use></svg><h3>Ambiente preparado para HTTPS/SSL</h3></article>
                <article class="security-card reveal"><svg><use href="#icon-check"></use></svg><h3>Validação de dados nos formulários</h3></article>
                <article class="security-card reveal"><svg><use href="#icon-users"></use></svg><h3>Controle de acesso por usuário</h3></article>
                <article class="security-card reveal"><svg><use href="#icon-chart"></use></svg><h3>Histórico de operações</h3></article>
                <article class="security-card reveal"><svg><use href="#icon-shield"></use></svg><h3>Estrutura preparada para integrações seguras</h3></article>
                <article class="security-card reveal"><svg><use href="#icon-lock"></use></svg><h3>Proteção contra falhas comuns</h3></article>
            </div>
        </div>
    </section>

    <section class="checkout-section" aria-labelledby="checkout-title">
        <div class="container checkout-layout">
            <div class="checkout-copy reveal">
                <span class="eyebrow">Checkout de conversão</span>
                <h2 id="checkout-title">Uma experiência de pagamento simples para reduzir abandono e aumentar conversões.</h2>
                <p>O mockup abaixo representa uma tela futura de pagamento em página única, pronta para integração com gateway.</p>
                <ul class="check-list">
                    <li><svg><use href="#icon-check"></use></svg> Checkout em página única</li>
                    <li><svg><use href="#icon-check"></use></svg> Resumo claro do pedido</li>
                    <li><svg><use href="#icon-check"></use></svg> Sem distrações</li>
                    <li><svg><use href="#icon-check"></use></svg> Total visível</li>
                    <li><svg><use href="#icon-check"></use></svg> Pix, cartão e boleto futuramente</li>
                </ul>
            </div>

            <div class="checkout-mockup reveal" aria-label="Mockup visual de checkout FluxPay">
                <div class="checkout-panel">
                    <div class="checkout-header">
                        <span>Assinatura FluxPay Pro</span>
                        <strong>Resumo seguro</strong>
                    </div>
                    <label>E-mail<input type="email" value="cliente@empresa.com" readonly></label>
                    <div class="checkout-two">
                        <label>Nome<input type="text" value="Mariana Costa" readonly></label>
                        <label>WhatsApp<input type="text" value="(11) 90000-0000" readonly></label>
                    </div>
                    <div class="payment-options" role="list" aria-label="Formas de pagamento">
                        <span class="selected">Cartão</span>
                        <span>Pix</span>
                        <span>Boleto</span>
                    </div>
                    <label>Cupom de desconto<input type="text" value="BOASVINDAS" readonly></label>
                    <div class="order-summary">
                        <div><span>Plano Pro</span><strong>R$ 99,00</strong></div>
                        <div><span>Desconto</span><strong>-R$ 20,00</strong></div>
                        <div class="total"><span>Total hoje</span><strong>R$ 79,00</strong></div>
                    </div>
                    <button type="button">Finalizar assinatura</button>
                    <p><svg><use href="#icon-shield"></use></svg> Ambiente preparado para checkout seguro e integração futura.</p>
                </div>
            </div>
        </div>
    </section>

    <section class="testimonials-section section-light" id="depoimentos" aria-labelledby="testimonials-title">
        <div class="container">
            <div class="section-heading reveal">
                <span class="eyebrow">Depoimentos</span>
                <h2 id="testimonials-title">O tipo de operação que a FluxPay foi criada para organizar.</h2>
            </div>
            <div class="testimonial-grid">
                <article class="testimonial-card reveal">
                    <div class="stars" aria-label="5 estrelas"><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg></div>
                    <p>A FluxPay nos ajudou a organizar mensalidades, reduzir atrasos e acompanhar tudo com mais clareza.</p>
                    <div class="author"><span>MC</span><div><strong>Mariana Costa</strong><small>Diretora de escola</small><em>Educação</em></div></div>
                </article>
                <article class="testimonial-card reveal">
                    <div class="stars" aria-label="5 estrelas"><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg></div>
                    <p>Antes o controle era manual. Agora consigo visualizar assinaturas, pagamentos pendentes e recebimentos em poucos segundos.</p>
                    <div class="author"><span>RL</span><div><strong>Rafael Lima</strong><small>Dono de academia</small><em>Fitness</em></div></div>
                </article>
                <article class="testimonial-card reveal">
                    <div class="stars" aria-label="5 estrelas"><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg><svg><use href="#icon-star"></use></svg></div>
                    <p>Os links de pagamento e cobranças recorrentes deixaram nosso processo comercial muito mais profissional.</p>
                    <div class="author"><span>BT</span><div><strong>Bianca Torres</strong><small>Gestora de agência</small><em>Serviços digitais</em></div></div>
                </article>
            </div>
        </div>
    </section>

    <section class="stats-section" aria-labelledby="stats-title">
        <div class="container">
            <div class="section-heading reveal">
                <span class="eyebrow">Indicadores</span>
                <h2 id="stats-title">Números demonstrativos para apresentar potencial de operação.</h2>
            </div>
            <!-- Dados demonstrativos — substituir por métricas reais quando disponíveis. -->
            <div class="stats-grid reveal">
                <article><strong data-counter data-count="2500" data-prefix="+">0</strong><span>cobranças gerenciadas</span></article>
                <article><strong data-counter data-count="94" data-suffix="%">0</strong><span>de pagamentos acompanhados em tempo real</span></article>
                <article><strong data-counter data-count="120" data-prefix="+">0</strong><span>empresas em fase de implantação</span></article>
                <article><strong data-counter data-count="3" data-suffix="x">0</strong><span>mais organização no processo de cobrança</span></article>
            </div>
        </div>
    </section>

    <section class="faq-section section-light" id="faq" aria-labelledby="faq-title">
        <div class="container">
            <div class="section-heading reveal">
                <span class="eyebrow">FAQ</span>
                <h2 id="faq-title">Perguntas frequentes</h2>
            </div>
            <div class="faq-list" data-faq-list>
                <article class="faq-item reveal">
                    <button type="button" aria-expanded="false">A FluxPay já processa pagamentos reais?<svg><use href="#icon-chevron"></use></svg></button>
                    <div class="faq-answer"><p>A landing page está preparada para integração. O processamento real dependerá do gateway configurado, como Pix, cartão ou boleto.</p></div>
                </article>
                <article class="faq-item reveal">
                    <button type="button" aria-expanded="false">Posso usar para cobranças mensais?<svg><use href="#icon-chevron"></use></svg></button>
                    <div class="faq-answer"><p>Sim. A proposta da FluxPay é facilitar cobranças únicas e recorrentes, ideal para mensalidades, assinaturas e contratos.</p></div>
                </article>
                <article class="faq-item reveal">
                    <button type="button" aria-expanded="false">Consigo acompanhar pagamentos atrasados?<svg><use href="#icon-chevron"></use></svg></button>
                    <div class="faq-answer"><p>Sim. O painel destaca cobranças pagas, pendentes, vencidas e canceladas para priorizar ações.</p></div>
                </article>
                <article class="faq-item reveal">
                    <button type="button" aria-expanded="false">Tem área do cliente?<svg><use href="#icon-chevron"></use></svg></button>
                    <div class="faq-answer"><p>A estrutura da página apresenta a área do cliente como recurso do produto, preparada para implementação no sistema.</p></div>
                </article>
                <article class="faq-item reveal">
                    <button type="button" aria-expanded="false">É seguro?<svg><use href="#icon-chevron"></use></svg></button>
                    <div class="faq-answer"><p>O projeto deve seguir boas práticas de segurança, validação de dados, HTTPS/SSL e integração segura com provedores de pagamento.</p></div>
                </article>
                <article class="faq-item reveal">
                    <button type="button" aria-expanded="false">Posso cancelar o plano?<svg><use href="#icon-chevron"></use></svg></button>
                    <div class="faq-answer"><p>Sim. A comunicação comercial deixa claro que o cliente pode cancelar conforme as regras do plano contratado.</p></div>
                </article>
            </div>
        </div>
    </section>

    <section class="final-cta section-dark" id="contato" aria-labelledby="final-title">
        <div class="container final-layout">
            <div class="final-copy reveal">
                <span class="eyebrow">Comece agora</span>
                <h2 id="final-title">Pronto para profissionalizar suas cobranças?</h2>
                <p>Comece com uma experiência simples, moderna e preparada para escalar junto com sua empresa.</p>
                <p class="hero-microcopy">Configuração rápida • Plataforma escalável • Suporte para implantação</p>
                <div class="final-actions">
                    <a class="btn btn-gradient" href="#lead-form">Começar agora</a>
                    <a class="btn btn-dark-outline" href="mailto:contato@fluxpay.com.br">Falar com especialista</a>
                </div>
            </div>

            <form class="lead-form reveal" id="lead-form" novalidate data-lead-form>
                <div class="form-header">
                    <strong>Quero conhecer a FluxPay</strong>
                    <span>Retorno comercial sem envio real nesta versão.</span>
                </div>
                <div class="form-field">
                    <label for="lead-name">Nome</label>
                    <input id="lead-name" name="name" type="text" autocomplete="name" placeholder="Seu nome completo" required>
                    <small data-error-for="name"></small>
                </div>
                <div class="form-field">
                    <label for="lead-email">E-mail</label>
                    <input id="lead-email" name="email" type="email" autocomplete="email" placeholder="voce@empresa.com" required>
                    <small data-error-for="email"></small>
                </div>
                <div class="form-field">
                    <label for="lead-phone">WhatsApp</label>
                    <input id="lead-phone" name="phone" type="tel" autocomplete="tel" placeholder="(00) 00000-0000" required>
                    <small data-error-for="phone"></small>
                </div>
                <div class="form-field">
                    <label for="lead-business">Tipo de negócio</label>
                    <select id="lead-business" name="business" required>
                        <option value="">Selecione uma opção</option>
                        <option value="empresa">Empresa de serviços</option>
                        <option value="infoprodutor">Infoprodutor</option>
                        <option value="escola">Escola ou curso</option>
                        <option value="academia">Academia ou studio</option>
                        <option value="clinica">Clínica</option>
                        <option value="agencia">Agência</option>
                        <option value="digital">Negócio digital</option>
                    </select>
                    <small data-error-for="business"></small>
                </div>
                <button class="btn btn-gradient btn-large" type="submit">Quero conhecer a FluxPay</button>
                <p class="form-status" role="status" aria-live="polite" data-form-status></p>
            </form>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container footer-grid">
        <div class="footer-brand-col">
            <a class="brand footer-brand" href="#inicio" aria-label="FluxPay">
                <span class="brand-mark"><svg><use href="#icon-logo"></use></svg></span>
                <span><strong>FluxPay</strong><small>Controle financeiro recorrente</small></span>
            </a>
            <p>Plataforma de cobranças recorrentes, links de pagamento, gestão de clientes e acompanhamento financeiro.</p>
        </div>

        <nav aria-label="Produto">
            <strong>Produto</strong>
            <a href="#recursos">Recursos</a>
            <a href="#planos">Planos</a>
            <a href="#seguranca">Segurança</a>
            <a href="#produto">Demonstração</a>
        </nav>

        <nav aria-label="Empresa">
            <strong>Empresa</strong>
            <a href="#inicio">Sobre</a>
            <a href="#contato">Contato</a>
            <a href="#recursos">Blog</a>
            <a href="#contato">Parcerias</a>
        </nav>

        <nav aria-label="Suporte">
            <strong>Suporte</strong>
            <a href="#faq">Central de ajuda</a>
            <a href="https://wa.me/5500000000000" rel="noopener noreferrer" target="_blank">WhatsApp</a>
            <a href="mailto:contato@fluxpay.com.br">E-mail</a>
            <a href="#seguranca">Status do sistema</a>
        </nav>

        <nav aria-label="Legal">
            <strong>Legal</strong>
            <a href="#inicio">Termos de uso</a>
            <a href="#inicio">Política de privacidade</a>
            <a href="#inicio">Política de reembolso</a>
            <a href="#inicio">Contrato de assinatura</a>
        </nav>

        <div class="payment-methods">
            <strong>Meios de pagamento</strong>
            <span>Pix</span>
            <span>Cartão</span>
            <span>Boleto</span>
        </div>
    </div>

    <div class="container footer-bottom">
        <span>© 2026 FluxPay. Todos os direitos reservados.</span>
        <span>Desenvolvido por L&amp;J Soluções Tecnológicas.</span>
    </div>
</footer>

<script src="<?= e(asset_url('/assets/js/landing.js')) ?>" defer></script>
</body>
</html>
