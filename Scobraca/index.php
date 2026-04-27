<?php
$baseUrl = './';
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FluxPay - Plataforma de Cobranças Automatizadas</title>

    <meta name="description" content="FluxPay é uma plataforma SaaS para gestão de clientes, cobranças recorrentes, pagamentos, PIX, WhatsApp e relatórios financeiros.">

    <link rel="stylesheet" href="<?= $baseUrl ?>/public/assets/css/landing.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body>

<header class="site-header" id="topo">
    <div class="container header-content">
        <a href="#topo" class="brand">
            <span class="brand-icon">
                <i class="ri-exchange-dollar-line"></i>
            </span>
            <span class="brand-text">
                <strong>FluxPay</strong>
                <small>Cobranças Recorrentes</small>
            </span>
        </a>

        <button class="menu-toggle" type="button" onclick="toggleMenu()" aria-label="Abrir menu">
            <i class="ri-menu-line"></i>
        </button>

        <nav class="main-nav" id="mainNav">
            <a href="#recursos">Recursos</a>
            <a href="#como-funciona">Como funciona</a>
            <a href="#planos">Planos</a>
            <a href="#perguntas">Dúvidas</a>
            <a href="#contato">Contato</a>
            <a href="login.php" class="nav-login">Entrar</a>
        </nav>
    </div>
</header>

<main>

    <section class="hero-section">
        <div class="container hero-grid">

            <div class="hero-text">
                <span class="hero-badge">
                    <i class="ri-whatsapp-line"></i>
                    Cobranças inteligentes por WhatsApp
                </span>

                <h1>
                    Controle cobranças, clientes e pagamentos em uma única plataforma.
                </h1>

                <p>
                    O FluxPay ajuda empresas a organizar mensalidades, enviar lembretes automáticos,
                    acompanhar atrasos, registrar pagamentos e manter uma visão clara do fluxo financeiro.
                </p>

                <div class="hero-actions">
                    <a href="login.php" class="btn-primary">
                        Entrar na plataforma
                        <i class="ri-arrow-right-line"></i>
                    </a>

                    <a href="#planos" class="btn-secondary">
                        Ver planos
                    </a>
                </div>

                <div class="hero-highlights">
                    <div>
                        <strong>PIX</strong>
                        <span>Dados na cobrança</span>
                    </div>

                    <div>
                        <strong>WhatsApp</strong>
                        <span>Lembretes automáticos</span>
                    </div>

                    <div>
                        <strong>Relatórios</strong>
                        <span>Visão financeira</span>
                    </div>
                </div>
            </div>

            <div class="hero-panel">
                <div class="dashboard-card">

                    <div class="dashboard-top">
                        <div>
                            <span>Painel financeiro</span>
                            <h3>Resumo do mês</h3>
                        </div>

                        <span class="status-pill">
                            <i class="ri-checkbox-circle-line"></i>
                            Online
                        </span>
                    </div>

                    <div class="stats-grid">
                        <div class="stat-card">
                            <span>Recebido</span>
                            <strong>R$ 18.420,00</strong>
                            <small>+12% comparado ao mês anterior</small>
                        </div>

                        <div class="stat-card danger">
                            <span>Pendente</span>
                            <strong>R$ 4.890,00</strong>
                            <small>32 clientes em cobrança</small>
                        </div>
                    </div>

                    <div class="progress-box">
                        <div class="progress-head">
                            <span>Pagamentos confirmados</span>
                            <strong>78%</strong>
                        </div>
                        <div class="progress-bar">
                            <span class="progress-fill"></span>
                        </div>
                    </div>

                    <div class="client-list">
                        <div class="client-item">
                            <div class="avatar">JS</div>
                            <div>
                                <strong>João Silva</strong>
                                <span>Pagamento confirmado</span>
                            </div>
                            <em>Pago</em>
                        </div>

                        <div class="client-item">
                            <div class="avatar warning">AM</div>
                            <div>
                                <strong>Ana Martins</strong>
                                <span>Vencimento em 5 dias</span>
                            </div>
                            <em class="pending">Pendente</em>
                        </div>

                        <div class="client-item">
                            <div class="avatar danger">CR</div>
                            <div>
                                <strong>Carlos Rocha</strong>
                                <span>Atrasado há 7 dias</span>
                            </div>
                            <em class="late">Atrasado</em>
                        </div>
                    </div>

                </div>
            </div>

        </div>
    </section>

    <section class="trust-section">
        <div class="container trust-grid">
            <div>
                <strong>+ organização</strong>
                <span>Controle completo dos clientes em cobrança</span>
            </div>

            <div>
                <strong>+ automação</strong>
                <span>Lembretes antes e depois do vencimento</span>
            </div>

            <div>
                <strong>+ clareza</strong>
                <span>Relatórios para tomada de decisão</span>
            </div>

            <div>
                <strong>+ recebimentos</strong>
                <span>Menos esquecimento e mais acompanhamento</span>
            </div>
        </div>
    </section>

    <section class="features-section" id="recursos">
        <div class="container">
            <div class="section-header">
                <span>Recursos principais</span>
                <h2>Tudo que sua empresa precisa para controlar cobranças</h2>
                <p>
                    Uma plataforma simples para gerenciar clientes, vencimentos, mensagens,
                    pagamentos e relatórios em um só lugar.
                </p>
            </div>

            <div class="features-grid">
                <article class="feature-card">
                    <i class="ri-user-settings-line"></i>
                    <h3>Gestão de clientes</h3>
                    <p>Cadastre clientes, telefones, mensalidades, veículos, vencimentos e status financeiro.</p>
                </article>

                <article class="feature-card">
                    <i class="ri-calendar-check-line"></i>
                    <h3>Cobranças recorrentes</h3>
                    <p>Gere cobranças por período e acompanhe quem está em dia, pendente ou atrasado.</p>
                </article>

                <article class="feature-card">
                    <i class="ri-whatsapp-line"></i>
                    <h3>Lembretes por WhatsApp</h3>
                    <p>Envie mensagens antes do vencimento, no vencimento e após atraso.</p>
                </article>

                <article class="feature-card">
                    <i class="ri-bank-card-line"></i>
                    <h3>Controle de pagamentos</h3>
                    <p>Registre valores recebidos, datas de pagamento, comprovantes e histórico financeiro.</p>
                </article>

                <article class="feature-card">
                    <i class="ri-qr-code-line"></i>
                    <h3>PIX nas mensagens</h3>
                    <p>Inclua os dados de PIX diretamente nas mensagens de cobrança enviadas aos clientes.</p>
                </article>

                <article class="feature-card">
                    <i class="ri-bar-chart-box-line"></i>
                    <h3>Relatórios inteligentes</h3>
                    <p>Acompanhe recebimentos, pendências, atrasos e evolução da carteira de clientes.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="how-section" id="como-funciona">
        <div class="container how-grid">

            <div class="how-text">
                <span class="section-kicker">Como funciona</span>
                <h2>Da cobrança ao pagamento, tudo em um fluxo simples.</h2>
                <p>
                    O FluxPay foi pensado para empresas que precisam acompanhar clientes,
                    vencimentos e pagamentos sem depender de planilhas soltas ou controles manuais.
                </p>

                <a href="login.php" class="btn-primary">
                    Acessar plataforma
                    <i class="ri-arrow-right-line"></i>
                </a>
            </div>

            <div class="steps-list">
                <div class="step-item">
                    <span>01</span>
                    <div>
                        <h3>Cadastre seus clientes</h3>
                        <p>Informe nome, telefone, vencimento, mensalidade e dados necessários para cobrança.</p>
                    </div>
                </div>

                <div class="step-item">
                    <span>02</span>
                    <div>
                        <h3>Gere as cobranças</h3>
                        <p>Crie cobranças mensais, acompanhe status e veja rapidamente quem está pendente.</p>
                    </div>
                </div>

                <div class="step-item">
                    <span>03</span>
                    <div>
                        <h3>Envie lembretes</h3>
                        <p>Use mensagens personalizadas no WhatsApp para lembrar o cliente antes e depois do vencimento.</p>
                    </div>
                </div>

                <div class="step-item">
                    <span>04</span>
                    <div>
                        <h3>Registre pagamentos</h3>
                        <p>Confirme recebimentos, organize comprovantes e acompanhe relatórios financeiros.</p>
                    </div>
                </div>
            </div>

        </div>
    </section>

    <section class="plans-section" id="planos">
        <div class="container">
            <div class="section-header">
                <span>Planos FluxPay</span>
                <h2>Escolha o plano ideal para sua operação</h2>
                <p>
                    Comece com um plano simples e evolua conforme sua carteira de clientes crescer.
                </p>
            </div>

            <div class="plans-grid">

                <article class="plan-card">
                    <div class="plan-header">
                        <span class="plan-tag">Plano inicial</span>
                        <h3>Essencial</h3>
                        <p>Para empresas pequenas que estão começando a organizar suas cobranças.</p>
                    </div>

                    <div class="plan-price">
                        <small>R$</small>
                        <strong>79,90</strong>
                        <span>/mês</span>
                    </div>

                    <div class="plan-limit">
                        <i class="ri-user-line"></i>
                        <strong>Até 50 clientes em cobrança</strong>
                    </div>

                    <ul class="plan-features">
                        <li><i class="ri-check-line"></i> Cadastro de clientes</li>
                        <li><i class="ri-check-line"></i> Controle de mensalidades</li>
                        <li><i class="ri-check-line"></i> Registro de pagamentos</li>
                        <li><i class="ri-check-line"></i> Status de clientes em dia ou pendentes</li>
                        <li><i class="ri-check-line"></i> Relatórios básicos</li>
                    </ul>

                    <a href="login.php" class="plan-button">
                        Começar agora
                    </a>
                </article>

                <article class="plan-card featured-plan">
                    <div class="popular-badge">Mais escolhido</div>

                    <div class="plan-header">
                        <span class="plan-tag">Plano profissional</span>
                        <h3>Profissional</h3>
                        <p>Para empresas com uma carteira maior e que precisam automatizar cobranças.</p>
                    </div>

                    <div class="plan-price">
                        <small>R$</small>
                        <strong>149,90</strong>
                        <span>/mês</span>
                    </div>

                    <div class="plan-limit">
                        <i class="ri-group-line"></i>
                        <strong>Até 200 clientes em cobrança</strong>
                    </div>

                    <ul class="plan-features">
                        <li><i class="ri-check-line"></i> Tudo do plano Essencial</li>
                        <li><i class="ri-check-line"></i> Lembretes automáticos por WhatsApp</li>
                        <li><i class="ri-check-line"></i> Mensagens antes e depois do vencimento</li>
                        <li><i class="ri-check-line"></i> Controle de atrasados</li>
                        <li><i class="ri-check-line"></i> Relatórios financeiros completos</li>
                        <li><i class="ri-check-line"></i> Configuração de PIX nas mensagens</li>
                    </ul>

                    <a href="login.php" class="plan-button featured-button">
                        Escolher Profissional
                    </a>
                </article>

                <article class="plan-card premium-plan">
                    <div class="plan-header">
                        <span class="plan-tag">Plano avançado</span>
                        <h3>Premium</h3>
                        <p>Para empresas com grande volume de cobranças e necessidade de escala.</p>
                    </div>

                    <div class="plan-price">
                        <small>R$</small>
                        <strong>249,90</strong>
                        <span>/mês</span>
                    </div>

                    <div class="plan-limit unlimited">
                        <i class="ri-infinity-line"></i>
                        <strong>Clientes em cobrança ilimitados</strong>
                    </div>

                    <ul class="plan-features">
                        <li><i class="ri-check-line"></i> Tudo do plano Profissional</li>
                        <li><i class="ri-check-line"></i> Clientes ilimitados</li>
                        <li><i class="ri-check-line"></i> Leitura de comprovantes</li>
                        <li><i class="ri-check-line"></i> Baixa inteligente de pagamentos</li>
                        <li><i class="ri-check-line"></i> Relatórios avançados</li>
                        <li><i class="ri-check-line"></i> Suporte prioritário</li>
                        <li><i class="ri-check-line"></i> Ideal para operação em escala</li>
                    </ul>

                    <a href="login.php" class="plan-button premium-button">
                        Quero o Premium
                    </a>
                </article>

            </div>
        </div>
    </section>

    <section class="cta-section">
        <div class="container cta-box">
            <div>
                <span>FluxPay SaaS</span>
                <h2>Pronto para organizar suas cobranças?</h2>
                <p>
                    Tenha mais controle sobre clientes, pagamentos, atrasos e lembretes automáticos.
                </p>
            </div>

            <a href="login.php" class="btn-white">
                Entrar na plataforma
                <i class="ri-arrow-right-line"></i>
            </a>
        </div>
    </section>

    <section class="faq-section" id="perguntas">
        <div class="container">
            <div class="section-header">
                <span>Dúvidas frequentes</span>
                <h2>Perguntas comuns sobre o FluxPay</h2>
                <p>Veja algumas respostas antes de começar a usar a plataforma.</p>
            </div>

            <div class="faq-list">
                <div class="faq-item active">
                    <button type="button" onclick="toggleFaq(this)">
                        O FluxPay serve para qual tipo de empresa?
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="faq-content">
                        <p>
                            Serve para empresas que trabalham com cobranças recorrentes, mensalidades,
                            clientes fixos, controle de pendências e lembretes de pagamento.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button type="button" onclick="toggleFaq(this)">
                        O sistema envia mensagens por WhatsApp?
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="faq-content">
                        <p>
                            Sim. A proposta é permitir mensagens de cobrança, lembretes de vencimento,
                            avisos de atraso e mensagens personalizadas para clientes.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button type="button" onclick="toggleFaq(this)">
                        O plano Premium é realmente ilimitado?
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="faq-content">
                        <p>
                            Sim, o plano Premium é indicado para empresas que precisam cadastrar uma grande
                            quantidade de clientes em cobrança sem limite definido no plano.
                        </p>
                    </div>
                </div>

                <div class="faq-item">
                    <button type="button" onclick="toggleFaq(this)">
                        Posso trocar de plano depois?
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="faq-content">
                        <p>
                            Sim. A empresa pode começar em um plano menor e migrar para um plano maior conforme
                            a carteira de clientes crescer.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="contact-section" id="contato">
        <div class="container contact-grid">

            <div class="contact-info">
                <span class="section-kicker">Contato</span>
                <h2>Solicite uma demonstração do FluxPay</h2>
                <p>
                    Preencha os dados ao lado para solicitar acesso, tirar dúvidas ou conhecer melhor a plataforma.
                </p>

                <div class="contact-cards">
                    <div>
                        <i class="ri-mail-line"></i>
                        <span>E-mail</span>
                        <strong>contato@fluxpay.com.br</strong>
                    </div>

                    <div>
                        <i class="ri-whatsapp-line"></i>
                        <span>WhatsApp</span>
                        <strong>(00) 00000-0000</strong>
                    </div>
                </div>
            </div>

            <form class="contact-form" action="#" method="post">
                <div class="form-group">
                    <label for="nome">Nome</label>
                    <input type="text" id="nome" name="nome" placeholder="Seu nome completo">
                </div>

                <div class="form-group">
                    <label for="empresa">Empresa</label>
                    <input type="text" id="empresa" name="empresa" placeholder="Nome da sua empresa">
                </div>

                <div class="form-grid">
                    <div class="form-group">
                        <label for="telefone">WhatsApp</label>
                        <input type="text" id="telefone" name="telefone" placeholder="(00) 00000-0000">
                    </div>

                    <div class="form-group">
                        <label for="plano">Plano de interesse</label>
                        <select id="plano" name="plano">
                            <option value="">Selecione</option>
                            <option value="essencial">Essencial</option>
                            <option value="profissional">Profissional</option>
                            <option value="premium">Premium</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="mensagem">Mensagem</label>
                    <textarea id="mensagem" name="mensagem" rows="4" placeholder="Conte rapidamente o que você precisa"></textarea>
                </div>

                <button type="submit" class="btn-primary form-button">
                    Solicitar contato
                    <i class="ri-send-plane-line"></i>
                </button>
            </form>

        </div>
    </section>

</main>

<footer class="site-footer">
    <div class="container footer-content">
        <div>
            <a href="#topo" class="footer-brand">
                <span class="brand-icon">
                    <i class="ri-exchange-dollar-line"></i>
                </span>
                <strong>FluxPay</strong>
            </a>

            <p>
                Plataforma de cobranças recorrentes, automação por WhatsApp,
                controle de pagamentos e relatórios financeiros.
            </p>
        </div>

        <div class="footer-links">
            <a href="#recursos">Recursos</a>
            <a href="#planos">Planos</a>
            <a href="#contato">Contato</a>
            <a href="login.php">Entrar</a>
        </div>
    </div>

    <div class="container footer-bottom">
        <span>© <?= date('Y') ?> FluxPay. Todos os direitos reservados.</span>
        <span>Desenvolvido por <strong>L&J Soluções Tecnológicas</strong></span>
    </div>
</footer>

<script src="<?= $baseUrl ?>/public/assets/js/landing.js"></script>
</body>
</html>