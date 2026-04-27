<?php
$baseUrl = './';

$planos = [
    [
        'nome' => 'Essencial',
        'tag' => 'Plano inicial',
        'descricao' => 'Para empresas pequenas que precisam sair da planilha e controlar melhor suas cobranças.',
        'preco_antigo' => '79,90',
        'preco' => '49,90',
        'anual' => '499,00',
        'economia' => 'Economize R$ 99,80 no anual',
        'limite' => 'Até 50 clientes em cobrança',
        'icone' => 'ri-user-line',
        'botao' => 'Começar no Essencial',
        'destaque' => false,
        'premium' => false,
        'features' => [
            'Cadastro de clientes',
            'Controle de mensalidades',
            'Registro manual de pagamentos',
            'Status de clientes em dia, pendentes e atrasados',
            'Relatórios básicos',
            'Ideal para começar com baixo custo'
        ]
    ],
    [
        'nome' => 'Profissional',
        'tag' => 'Mais escolhido',
        'descricao' => 'Para empresas que querem automatizar lembretes e acompanhar cobranças com mais organização.',
        'preco_antigo' => '149,90',
        'preco' => '89,90',
        'anual' => '899,00',
        'economia' => 'Economize R$ 179,80 no anual',
        'limite' => 'Até 200 clientes em cobrança',
        'icone' => 'ri-group-line',
        'botao' => 'Escolher Profissional',
        'destaque' => true,
        'premium' => false,
        'features' => [
            'Tudo do plano Essencial',
            'Lembretes automáticos por WhatsApp',
            'Mensagens antes e depois do vencimento',
            'Controle de clientes atrasados',
            'PIX nas mensagens de cobrança',
            'Relatórios financeiros completos'
        ]
    ],
    [
        'nome' => 'Premium',
        'tag' => 'Maior escala',
        'descricao' => 'Para empresas com alto volume de cobranças e necessidade de recursos avançados.',
        'preco_antigo' => '249,90',
        'preco' => '149,90',
        'anual' => '1.499,00',
        'economia' => 'Economize R$ 299,80 no anual',
        'limite' => 'Clientes em cobrança ilimitados*',
        'icone' => 'ri-infinity-line',
        'botao' => 'Quero o Premium',
        'destaque' => false,
        'premium' => true,
        'features' => [
            'Tudo do plano Profissional',
            'Clientes em cobrança ilimitados',
            'Leitura assistida de comprovantes',
            'Baixa inteligente de pagamentos',
            'Relatórios avançados',
            'Suporte prioritário da L&J'
        ]
    ],
];

$depoimentos = [
    [
        'nome' => 'Marcos Almeida',
        'empresa' => 'Empresa de Rastreamento',
        'comentario' => 'Antes o controle era todo em planilha. Com uma plataforma desse tipo, a equipe consegue visualizar quem pagou, quem está pendente e quem precisa receber lembrete.',
        'iniciais' => 'MA'
    ],
    [
        'nome' => 'Renata Costa',
        'empresa' => 'Serviços Recorrentes',
        'comentario' => 'O maior ganho está em não esquecer cobranças. Ter lembretes, status e relatórios no mesmo lugar ajuda muito na rotina.',
        'iniciais' => 'RC'
    ],
    [
        'nome' => 'Felipe Santos',
        'empresa' => 'Gestão de Mensalidades',
        'comentario' => 'O painel deixa claro quanto entrou, quanto ainda falta receber e quais clientes precisam de atenção. Isso economiza tempo todos os dias.',
        'iniciais' => 'FS'
    ],
];
?>
<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>FluxPay - Plataforma de Cobranças Automatizadas</title>

    <meta name="description" content="FluxPay é uma plataforma SaaS criada pela L&J Soluções Tecnológicas para gestão de clientes, cobranças recorrentes, pagamentos, PIX, WhatsApp e relatórios financeiros.">

    <link rel="stylesheet" href="<?= $baseUrl ?>public/assets/css/landing.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
</head>

<body>

<div class="top-offer">
    <div class="container top-offer-content">
        <span>
            <i class="ri-fire-line"></i>
            Oferta de lançamento FluxPay
        </span>

        <strong>
            Contrate no anual, pague 10 meses e use por 12 meses.
        </strong>

        <a href="#planos">
            Ver planos
            <i class="ri-arrow-right-line"></i>
        </a>
    </div>
</div>

<header class="site-header" id="topo">
    <div class="container header-content">
        <a href="#topo" class="brand">
            <span class="brand-icon">
                <i class="ri-exchange-dollar-line"></i>
            </span>
            <span class="brand-text">
                <strong>FluxPay</strong>
                <small>by L&J Soluções Tecnológicas</small>
            </span>
        </a>

        <button class="menu-toggle" type="button" onclick="toggleMenu()" aria-label="Abrir menu">
            <i class="ri-menu-line"></i>
        </button>

        <nav class="main-nav" id="mainNav">
            <a href="#recursos">Recursos</a>
            <a href="#como-funciona">Como funciona</a>
            <a href="#planos">Planos</a>
            <a href="#depoimentos">Comentários</a>
            <a href="#perguntas">Dúvidas</a>
            <a href="#contato">Contato</a>
            <a href="login.php" class="nav-login">Entrar</a>
        </nav>
    </div>
</header>

<main>

    <section class="hero-section">
        <div class="hero-glow hero-glow-one"></div>
        <div class="hero-glow hero-glow-two"></div>

        <div class="container hero-grid">

            <div class="hero-text" data-animate="fade-right">
                <span class="hero-badge">
                    <i class="ri-whatsapp-line"></i>
                    Cobranças automáticas para vender mais e esquecer menos
                </span>

                <h1>
                    Pare de perder dinheiro por falta de controle nas cobranças.
                </h1>

                <p>
                    O FluxPay organiza clientes, mensalidades, lembretes no WhatsApp,
                    pagamentos, PIX e relatórios em uma plataforma simples, feita pela
                    <strong>L&J Soluções Tecnológicas</strong>.
                </p>

                <div class="hero-actions">
                    <a href="#planos" class="btn-primary">
                        Quero organizar minhas cobranças
                        <i class="ri-arrow-right-line"></i>
                    </a>

                    <a href="login.php" class="btn-secondary">
                        Já sou cliente
                    </a>
                </div>

                <div class="hero-proof">
                    <div class="proof-avatars">
                        <span> LJ </span>
                        <span> FP </span>
                        <span> + </span>
                    </div>

                    <p>
                        Plataforma pensada para empresas que trabalham com clientes recorrentes,
                        mensalidades e cobrança por WhatsApp.
                    </p>
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

            <div class="hero-panel" data-animate="fade-left">
                <div class="floating-card floating-card-one">
                    <i class="ri-notification-3-line"></i>
                    <div>
                        <strong>Lembrete enviado</strong>
                        <span>Cliente avisado antes do vencimento</span>
                    </div>
                </div>

                <div class="floating-card floating-card-two">
                    <i class="ri-money-dollar-circle-line"></i>
                    <div>
                        <strong>Pagamento confirmado</strong>
                        <span>Baixa registrada no painel</span>
                    </div>
                </div>

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
            <div data-animate="fade-up">
                <strong>+ organização</strong>
                <span>Clientes e cobranças em um só lugar</span>
            </div>

            <div data-animate="fade-up">
                <strong>+ lembretes</strong>
                <span>Mensagens antes e depois do vencimento</span>
            </div>

            <div data-animate="fade-up">
                <strong>+ clareza</strong>
                <span>Relatórios para tomada de decisão</span>
            </div>

            <div data-animate="fade-up">
                <strong>+ recebimentos</strong>
                <span>Menos esquecimento e mais acompanhamento</span>
            </div>
        </div>
    </section>

    <section class="pain-section">
        <div class="container pain-grid">
            <div class="pain-text" data-animate="fade-right">
                <span class="section-kicker">Problema real</span>
                <h2>Sua empresa pode estar perdendo dinheiro por falta de acompanhamento.</h2>
                <p>
                    Cliente esquece. Equipe esquece. Planilha fica desatualizada.
                    Mensagem não é enviada no dia certo. No final, o caixa sofre.
                </p>
            </div>

            <div class="pain-cards">
                <article data-animate="fade-up">
                    <i class="ri-file-excel-2-line"></i>
                    <h3>Planilhas confusas</h3>
                    <p>Difícil saber quem pagou, quem está atrasado e quem já recebeu cobrança.</p>
                </article>

                <article data-animate="fade-up">
                    <i class="ri-time-line"></i>
                    <h3>Cobrança atrasada</h3>
                    <p>Quando o lembrete não vai no tempo certo, a chance de atraso aumenta.</p>
                </article>

                <article data-animate="fade-up">
                    <i class="ri-alert-line"></i>
                    <h3>Falta de visão</h3>
                    <p>Sem relatório, fica difícil saber quanto entrou e quanto ainda falta receber.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="features-section" id="recursos">
        <div class="container">
            <div class="section-header" data-animate="fade-up">
                <span>Recursos principais</span>
                <h2>Tudo que sua empresa precisa para controlar cobranças</h2>
                <p>
                    Uma plataforma simples para gerenciar clientes, vencimentos, mensagens,
                    pagamentos e relatórios em um só lugar.
                </p>
            </div>

            <div class="features-grid">
                <article class="feature-card" data-animate="fade-up">
                    <i class="ri-user-settings-line"></i>
                    <h3>Gestão de clientes</h3>
                    <p>Cadastre clientes, telefones, mensalidades, veículos, vencimentos e status financeiro.</p>
                </article>

                <article class="feature-card" data-animate="fade-up">
                    <i class="ri-calendar-check-line"></i>
                    <h3>Cobranças recorrentes</h3>
                    <p>Gere cobranças por período e acompanhe quem está em dia, pendente ou atrasado.</p>
                </article>

                <article class="feature-card" data-animate="fade-up">
                    <i class="ri-whatsapp-line"></i>
                    <h3>Lembretes por WhatsApp</h3>
                    <p>Envie mensagens antes do vencimento, no vencimento e após atraso.</p>
                </article>

                <article class="feature-card" data-animate="fade-up">
                    <i class="ri-bank-card-line"></i>
                    <h3>Controle de pagamentos</h3>
                    <p>Registre valores recebidos, datas de pagamento, comprovantes e histórico financeiro.</p>
                </article>

                <article class="feature-card" data-animate="fade-up">
                    <i class="ri-qr-code-line"></i>
                    <h3>PIX nas mensagens</h3>
                    <p>Inclua os dados de PIX diretamente nas mensagens de cobrança enviadas aos clientes.</p>
                </article>

                <article class="feature-card" data-animate="fade-up">
                    <i class="ri-bar-chart-box-line"></i>
                    <h3>Relatórios inteligentes</h3>
                    <p>Acompanhe recebimentos, pendências, atrasos e evolução da carteira de clientes.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="how-section" id="como-funciona">
        <div class="container how-grid">

            <div class="how-text" data-animate="fade-right">
                <span class="section-kicker">Como funciona</span>
                <h2>Da cobrança ao pagamento, tudo em um fluxo simples.</h2>
                <p>
                    O FluxPay foi pensado para empresas que precisam acompanhar clientes,
                    vencimentos e pagamentos sem depender de planilhas soltas ou controles manuais.
                </p>

                <a href="#planos" class="btn-primary">
                    Ver planos promocionais
                    <i class="ri-arrow-right-line"></i>
                </a>
            </div>

            <div class="steps-list">
                <div class="step-item" data-animate="fade-up">
                    <span>01</span>
                    <div>
                        <h3>Cadastre seus clientes</h3>
                        <p>Informe nome, telefone, vencimento, mensalidade e dados necessários para cobrança.</p>
                    </div>
                </div>

                <div class="step-item" data-animate="fade-up">
                    <span>02</span>
                    <div>
                        <h3>Gere as cobranças</h3>
                        <p>Crie cobranças mensais, acompanhe status e veja rapidamente quem está pendente.</p>
                    </div>
                </div>

                <div class="step-item" data-animate="fade-up">
                    <span>03</span>
                    <div>
                        <h3>Envie lembretes</h3>
                        <p>Use mensagens personalizadas no WhatsApp para lembrar o cliente antes e depois do vencimento.</p>
                    </div>
                </div>

                <div class="step-item" data-animate="fade-up">
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
            <div class="section-header" data-animate="fade-up">
                <span>Planos promocionais de lançamento</span>
                <h2>Comece pagando menos e organize sua cobrança ainda este mês</h2>
                <p>
                    Valores reduzidos para entrada de novos clientes. No plano anual,
                    você paga 10 meses e usa o FluxPay por 12 meses.
                </p>
            </div>

            <div class="billing-banner" data-animate="fade-up">
                <div>
                    <i class="ri-gift-line"></i>
                    <strong>Oferta anual</strong>
                    <span>Contrate por 1 ano e ganhe 2 meses de uso.</span>
                </div>

                <a href="#contato">
                    Solicitar proposta
                    <i class="ri-arrow-right-line"></i>
                </a>
            </div>

            <div class="plans-grid">
                <?php foreach ($planos as $plano): ?>
                    <article class="plan-card <?= $plano['destaque'] ? 'featured-plan' : '' ?> <?= $plano['premium'] ? 'premium-plan' : '' ?>" data-animate="fade-up">
                        <?php if ($plano['destaque']): ?>
                            <div class="popular-badge">Mais indicado</div>
                        <?php endif; ?>

                        <div class="plan-header">
                            <span class="plan-tag"><?= htmlspecialchars($plano['tag']) ?></span>
                            <h3><?= htmlspecialchars($plano['nome']) ?></h3>
                            <p><?= htmlspecialchars($plano['descricao']) ?></p>
                        </div>

                        <div class="old-price">
                            De R$ <?= htmlspecialchars($plano['preco_antigo']) ?>/mês
                        </div>

                        <div class="plan-price">
                            <small>R$</small>
                            <strong><?= htmlspecialchars($plano['preco']) ?></strong>
                            <span>/mês</span>
                        </div>

                        <div class="annual-price">
                            <i class="ri-discount-percent-line"></i>
                            <div>
                                <strong>R$ <?= htmlspecialchars($plano['anual']) ?> no anual</strong>
                                <span><?= htmlspecialchars($plano['economia']) ?></span>
                            </div>
                        </div>

                        <div class="plan-limit <?= $plano['premium'] ? 'unlimited' : '' ?>">
                            <i class="<?= htmlspecialchars($plano['icone']) ?>"></i>
                            <strong><?= htmlspecialchars($plano['limite']) ?></strong>
                        </div>

                        <ul class="plan-features">
                            <?php foreach ($plano['features'] as $feature): ?>
                                <li>
                                    <i class="ri-check-line"></i>
                                    <?= htmlspecialchars($feature) ?>
                                </li>
                            <?php endforeach; ?>
                        </ul>

                        <a href="#contato" class="plan-button <?= $plano['destaque'] ? 'featured-button' : '' ?> <?= $plano['premium'] ? 'premium-button' : '' ?>">
                            <?= htmlspecialchars($plano['botao']) ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>

            <p class="plans-note">
                *Clientes ilimitados sujeitos à política de uso justo da plataforma e capacidade operacional contratada.
                Os valores promocionais podem ser ajustados futuramente para novas contratações.
            </p>
        </div>
    </section>

    <section class="testimonials-section" id="depoimentos">
        <div class="container">
            <div class="section-header" data-animate="fade-up">
                <span>Prova social</span>
                <h2>Comentários que mostram a dor que o FluxPay resolve</h2>
                <p>
                    Estes textos são modelos demonstrativos. Substitua pelos depoimentos reais
                    assim que os primeiros clientes começarem a usar.
                </p>
            </div>

            <div class="testimonials-grid">
                <?php foreach ($depoimentos as $depoimento): ?>
                    <article class="testimonial-card" data-animate="fade-up">
                        <div class="stars">
                            <i class="ri-star-fill"></i>
                            <i class="ri-star-fill"></i>
                            <i class="ri-star-fill"></i>
                            <i class="ri-star-fill"></i>
                            <i class="ri-star-fill"></i>
                        </div>

                        <p>
                            “<?= htmlspecialchars($depoimento['comentario']) ?>”
                        </p>

                        <div class="testimonial-author">
                            <span><?= htmlspecialchars($depoimento['iniciais']) ?></span>
                            <div>
                                <strong><?= htmlspecialchars($depoimento['nome']) ?></strong>
                                <small><?= htmlspecialchars($depoimento['empresa']) ?></small>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="guarantee-section">
        <div class="container guarantee-box" data-animate="zoom-in">
            <div class="guarantee-icon">
                <i class="ri-shield-check-line"></i>
            </div>

            <div>
                <span>Desenvolvido pela L&J Soluções Tecnológicas</span>
                <h2>Você não está contratando só um sistema. Está contratando organização para sua cobrança.</h2>
                <p>
                    O FluxPay foi pensado para pequenos e médios negócios que precisam controlar melhor
                    mensalidades, inadimplência, lembretes e pagamentos sem complicação.
                </p>
            </div>

            <a href="#contato" class="btn-primary">
                Falar com a L&J
                <i class="ri-whatsapp-line"></i>
            </a>
        </div>
    </section>

    <section class="cta-section">
        <div class="container cta-box" data-animate="zoom-in">
            <div>
                <span>Oferta de lançamento</span>
                <h2>Quanto custa continuar perdendo cobrança por esquecimento?</h2>
                <p>
                    Com planos a partir de R$ 49,90/mês, o FluxPay foi criado para caber no bolso
                    e ajudar sua empresa a cobrar melhor.
                </p>
            </div>

            <a href="#planos" class="btn-white">
                Ver planos agora
                <i class="ri-arrow-right-line"></i>
            </a>
        </div>
    </section>

    <section class="faq-section" id="perguntas">
        <div class="container">
            <div class="section-header" data-animate="fade-up">
                <span>Dúvidas frequentes</span>
                <h2>Perguntas comuns sobre o FluxPay</h2>
                <p>Veja algumas respostas antes de começar a usar a plataforma.</p>
            </div>

            <div class="faq-list">
                <div class="faq-item active" data-animate="fade-up">
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

                <div class="faq-item" data-animate="fade-up">
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

                <div class="faq-item" data-animate="fade-up">
                    <button type="button" onclick="toggleFaq(this)">
                        Como funciona o desconto anual?
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="faq-content">
                        <p>
                            No plano anual, o cliente paga o equivalente a 10 meses e utiliza a plataforma
                            por 12 meses. É uma forma de reduzir o custo mensal e garantir acesso por mais tempo.
                        </p>
                    </div>
                </div>

                <div class="faq-item" data-animate="fade-up">
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

                <div class="faq-item" data-animate="fade-up">
                    <button type="button" onclick="toggleFaq(this)">
                        Quem desenvolve o FluxPay?
                        <i class="ri-arrow-down-s-line"></i>
                    </button>
                    <div class="faq-content">
                        <p>
                            O FluxPay é desenvolvido pela L&J Soluções Tecnológicas, com foco em sistemas
                            práticos para organização, cobrança e gestão de negócios.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="contact-section" id="contato">
        <div class="container contact-grid">

            <div class="contact-info" data-animate="fade-right">
                <span class="section-kicker">Contato</span>
                <h2>Solicite acesso ao FluxPay</h2>
                <p>
                    Preencha os dados ao lado para solicitar uma demonstração, tirar dúvidas
                    ou contratar um dos planos promocionais.
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

                    <div>
                        <i class="ri-building-4-line"></i>
                        <span>Empresa responsável</span>
                        <strong>L&J Soluções Tecnológicas</strong>
                    </div>
                </div>
            </div>

            <form class="contact-form" action="#" method="post" data-animate="fade-left">
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
                            <option value="essencial">Essencial - R$ 49,90/mês</option>
                            <option value="profissional">Profissional - R$ 89,90/mês</option>
                            <option value="premium">Premium - R$ 149,90/mês</option>
                            <option value="anual">Quero desconto anual</option>
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

                <p class="form-note">
                    Ao solicitar contato, a equipe da L&J Soluções Tecnológicas poderá falar com você
                    para apresentar o FluxPay e orientar sobre o melhor plano.
                </p>
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
            <a href="#depoimentos">Comentários</a>
            <a href="#contato">Contato</a>
            <a href="login.php">Entrar</a>
        </div>
    </div>

    <div class="container footer-bottom">
        <span>© <?= date('Y') ?> FluxPay. Todos os direitos reservados.</span>
        <span>Desenvolvido por <strong>L&J Soluções Tecnológicas</strong></span>
    </div>
</footer>

<script src="<?= $baseUrl ?>public/assets/js/landing.js"></script>
</body>
</html>