<?php
declare(strict_types=1);

$paginaAtual = 'recebimento';
$paginaTitulo = 'Recebimento rápido';

$pendentes = [
    ['prot'=>'1146/2026','oficio'=>'0406/2026','origem'=>'SEMAS','assunto'=>'Manutenção do sistema de ar-condicionado','hora'=>'09:52'],
    ['prot'=>'1142/2026','oficio'=>'0294/2026','origem'=>'SEMED','assunto'=>'Encaminhamento de expediente','hora'=>'09:14'],
    ['prot'=>'1138/2026','oficio'=>'0611/2026','origem'=>'SEINFRA','assunto'=>'Solicitação de manifestação','hora'=>'08:32'],
];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#f8fafe">
<title>SIGO - Recebimento rápido</title>
<link rel="stylesheet" href="../assets/css/style.css?v=20260928-4">
<link rel="stylesheet" href="../assets/css/pages.css?v=20260928-2">
</head>
<body class="receipt-page">
<div class="app-shell">
<?php require dirname(__DIR__) . '/includes/topbar.php'; ?>

<div class="app-body">
<?php require dirname(__DIR__) . '/includes/sidebar.php'; ?>

<main class="module-page receipt-module">
<section class="receipt-shell" data-receipt-flow>
    <header class="receipt-flow-header">
        <div class="receipt-heading">
            <span class="module-kicker">Confirmação de recebimento</span>
            <h1>Recebimento rápido</h1>
            <p>Faça a confirmação em poucos passos. Leva menos de um minuto.</p>
        </div>

        <div class="receipt-steps" aria-label="Etapas do recebimento">
            <div class="receipt-step active" data-step-indicator="1">
                <span>1</span>
                <strong>Identificação</strong>
            </div>
            <i></i>
            <div class="receipt-step" data-step-indicator="2">
                <span>2</span>
                <strong>Ofícios</strong>
            </div>
            <i></i>
            <div class="receipt-step" data-step-indicator="3">
                <span>3</span>
                <strong>Confirmar</strong>
            </div>
        </div>
    </header>

    <section class="receipt-stage active" data-receipt-step="1">
        <article class="receipt-stage-card identity-step-card">
            <div class="receipt-stage-icon">
                <i data-lucide="user-round-check"></i>
            </div>

            <div class="receipt-stage-copy">
                <span class="receipt-step-label">Etapa 1 de 3</span>
                <h2>Quem está recebendo?</h2>
                <p>Selecione seu nome e informe seu PIN para acessar apenas os ofícios encaminhados para você.</p>
            </div>

            <div class="receipt-form">
                <div class="field mobile-field">
                    <label for="receiptPerson">Seu nome</label>
                    <div class="input-with-icon">
                        <i data-lucide="user"></i>
                        <select id="receiptPerson" data-receipt-person>
                            <option value="">Selecione seu nome</option>
                            <option value="Marcos Almeida">Marcos Almeida</option>
                            <option value="Carla Souza">Carla Souza</option>
                            <option value="Paulo Martins">Paulo Martins</option>
                        </select>
                    </div>
                </div>

                <div class="field mobile-field">
                    <label for="receiptPin">PIN de acesso</label>
                    <div class="input-with-icon">
                        <i data-lucide="lock-keyhole"></i>
                        <input id="receiptPin" data-receipt-pin type="password" inputmode="numeric" maxlength="6" placeholder="Digite seu PIN">
                    </div>
                    <small>Use o PIN pessoal de 4 a 6 números.</small>
                </div>

                <label class="remember-person">
                    <input type="checkbox" data-remember-person checked>
                    <span>
                        <strong>Lembrar meu nome neste aparelho</strong>
                        <small>Na próxima vez você só informa o PIN.</small>
                    </span>
                </label>

                <div class="receipt-inline-error" data-receipt-error hidden>
                    <i data-lucide="circle-alert"></i>
                    <span></span>
                </div>

                <button class="primary-button receipt-main-button" type="button" data-receipt-next="2">
                    Continuar
                    <i data-lucide="arrow-right"></i>
                </button>
            </div>

            <div class="receipt-security-note">
                <i data-lucide="shield-check"></i>
                <span>Seu PIN serve apenas para confirmar que foi você quem recebeu o documento.</span>
            </div>
        </article>
    </section>

    <section class="receipt-stage" data-receipt-step="2" hidden>
        <article class="receipt-stage-card offices-step-card">
            <div class="receipt-mobile-topline">
                <button class="receipt-back" type="button" data-receipt-back="1">
                    <i data-lucide="arrow-left"></i>
                    Voltar
                </button>
                <span class="receipt-step-label">Etapa 2 de 3</span>
            </div>

            <div class="receipt-person-summary">
                <span class="receipt-person-avatar" data-person-initials>MA</span>
                <div>
                    <small>Recebendo como</small>
                    <strong data-person-name>Marcos Almeida</strong>
                </div>
                <button type="button" data-receipt-back="1">Trocar</button>
            </div>

            <div class="receipt-stage-copy">
                <h2>Quais ofícios você recebeu?</h2>
                <p>Marque apenas os documentos que estão fisicamente com você agora.</p>
            </div>

            <div class="receipt-selection-bar">
                <label class="receipt-select-all">
                    <input type="checkbox" data-select-all>
                    <span>Selecionar todos</span>
                </label>
                <strong><span data-selected-count>0</span> selecionado(s)</strong>
            </div>

            <div class="receipt-list receipt-list-mobile">
                <?php foreach ($pendentes as $index => $p): ?>
                <label class="receipt-item receipt-mobile-card">
                    <input
                        type="checkbox"
                        data-receipt-check
                        data-office="<?= htmlspecialchars($p['oficio'], ENT_QUOTES, 'UTF-8') ?>"
                        data-protocol="<?= htmlspecialchars($p['prot'], ENT_QUOTES, 'UTF-8') ?>"
                        data-origin="<?= htmlspecialchars($p['origem'], ENT_QUOTES, 'UTF-8') ?>"
                        data-subject="<?= htmlspecialchars($p['assunto'], ENT_QUOTES, 'UTF-8') ?>"
                    >
                    <span class="receipt-check-ui"><i data-lucide="check"></i></span>
                    <span class="receipt-item-copy">
                        <span class="receipt-item-topline">
                            <strong>Ofício <?= htmlspecialchars($p['oficio']) ?></strong>
                            <time><?= htmlspecialchars($p['hora']) ?></time>
                        </span>
                        <span class="receipt-protocol">Protocolo <?= htmlspecialchars($p['prot']) ?></span>
                        <span class="receipt-subject"><?= htmlspecialchars($p['assunto']) ?></span>
                        <span class="receipt-origin"><i data-lucide="building-2"></i><?= htmlspecialchars($p['origem']) ?></span>
                    </span>
                </label>
                <?php endforeach; ?>
            </div>

            <div class="receipt-inline-error" data-receipt-error-step2 hidden>
                <i data-lucide="circle-alert"></i>
                <span>Selecione pelo menos um ofício.</span>
            </div>

            <div class="receipt-sticky-action">
                <button class="primary-button receipt-main-button" type="button" data-receipt-next="3">
                    Revisar recebimento
                    <span class="receipt-button-count" data-button-count hidden>0</span>
                    <i data-lucide="arrow-right"></i>
                </button>
            </div>
        </article>
    </section>

    <section class="receipt-stage" data-receipt-step="3" hidden>
        <article class="receipt-stage-card review-step-card">
            <div class="receipt-mobile-topline">
                <button class="receipt-back" type="button" data-receipt-back="2">
                    <i data-lucide="arrow-left"></i>
                    Voltar
                </button>
                <span class="receipt-step-label">Etapa 3 de 3</span>
            </div>

            <div class="receipt-stage-icon success-soft">
                <i data-lucide="clipboard-check"></i>
            </div>

            <div class="receipt-stage-copy">
                <h2>Confira antes de confirmar</h2>
                <p>Depois de confirmar, o SIGO registrará seu nome, data e hora no histórico dos documentos.</p>
            </div>

            <div class="review-person-card">
                <span class="receipt-person-avatar" data-review-initials>MA</span>
                <div>
                    <small>Recebido por</small>
                    <strong data-review-person>Marcos Almeida</strong>
                </div>
                <span class="review-verified"><i data-lucide="badge-check"></i>Identificado</span>
            </div>

            <div class="review-offices">
                <div class="review-offices-title">
                    <span>Ofícios selecionados</span>
                    <strong data-review-count>0</strong>
                </div>
                <div class="review-list" data-review-list></div>
            </div>

            <label class="receipt-confirm-check">
                <input type="checkbox" data-confirm-check>
                <span>
                    <strong>Confirmo que recebi fisicamente os documentos acima.</strong>
                    <small>Esta ação será registrada no histórico do SIGO.</small>
                </span>
            </label>

            <div class="receipt-inline-error" data-receipt-error-step3 hidden>
                <i data-lucide="circle-alert"></i>
                <span>Confirme a declaração para continuar.</span>
            </div>

            <button class="primary-button receipt-main-button receipt-confirm-button" type="button" data-confirm-receipt-final>
                <i data-lucide="circle-check-big"></i>
                Confirmar recebimento
            </button>
        </article>
    </section>

    <section class="receipt-stage" data-receipt-step="4" hidden>
        <article class="receipt-stage-card receipt-success-card">
            <div class="receipt-success-icon">
                <i data-lucide="check"></i>
            </div>

            <span class="receipt-step-label">Recebimento confirmado</span>
            <h2>Tudo certo!</h2>
            <p data-success-message>Os ofícios foram registrados como recebidos.</p>

            <div class="receipt-success-summary">
                <div>
                    <small>Responsável</small>
                    <strong data-success-person>—</strong>
                </div>
                <div>
                    <small>Documentos</small>
                    <strong data-success-count>0</strong>
                </div>
                <div>
                    <small>Data e hora</small>
                    <strong data-success-time>—</strong>
                </div>
            </div>

            <button class="primary-button receipt-main-button" type="button" data-receipt-finish>
                Concluir
            </button>
        </article>
    </section>
</section>
</main>
</div>

<?php require dirname(__DIR__) . '/includes/footer.php'; ?>
</div>

<?php require dirname(__DIR__) . '/includes/searchOverlay.php'; ?>
<script src="https://unpkg.com/lucide@0.468.0/dist/umd/lucide.min.js"></script>
<script src="../assets/js/app.js?v=20260928-4"></script>
<script src="../assets/js/pages.js?v=20260928-2"></script>
</body>
</html>