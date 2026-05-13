<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

function checkout_normalize_text(mixed $value): string
{
    return trim(preg_replace('/\s+/', ' ', (string) $value) ?? '');
}

function checkout_fetch_active_plan(PDO $pdo, int $planId): ?array
{
    if ($planId <= 0) {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, nome, preco FROM planos WHERE id = :id AND ativo = 1 LIMIT 1');
    $stmt->execute([':id' => $planId]);
    $plan = $stmt->fetch();

    return $plan ?: null;
}

function checkout_fetch_plan_by_name(PDO $pdo, string $name): ?array
{
    $name = trim($name);

    if ($name === '') {
        return null;
    }

    $stmt = $pdo->prepare('SELECT id, nome, preco FROM planos WHERE ativo = 1 AND LOWER(nome) = LOWER(:nome) ORDER BY id ASC LIMIT 1');
    $stmt->execute([':nome' => $name]);
    $plan = $stmt->fetch();

    return $plan ?: null;
}

function checkout_default_plan(PDO $pdo): ?array
{
    $configuredPlanId = (int) env('CHECKOUT_DEFAULT_PLAN_ID', '0');
    $plan = checkout_fetch_active_plan($pdo, $configuredPlanId);

    if ($plan) {
        return $plan;
    }

    $configuredPlanName = (string) env('CHECKOUT_DEFAULT_PLAN_NAME', 'Growth');
    $plan = checkout_fetch_plan_by_name($pdo, $configuredPlanName);

    if ($plan) {
        return $plan;
    }

    foreach (['Growth', 'Profissional', 'Pro', 'Premium', 'Básico', 'Start'] as $name) {
        $plan = checkout_fetch_plan_by_name($pdo, $name);

        if ($plan) {
            return $plan;
        }
    }

    $stmt = $pdo->query('SELECT id, nome, preco FROM planos WHERE ativo = 1 ORDER BY preco ASC, id ASC LIMIT 1');
    $plan = $stmt->fetch();

    return $plan ?: null;
}

function checkout_exists(PDO $pdo, string $sql, array $params): bool
{
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    return (bool) $stmt->fetchColumn();
}

function checkout_has_error(array $errors, string $field): string
{
    return isset($errors[$field]) ? ' is-invalid' : '';
}

function checkout_field_error(array $errors, string $field): string
{
    return e((string) ($errors[$field] ?? ''));
}

function checkout_document_input_value(string $documento): string
{
    return $documento !== '' ? formatar_documento($documento) : '';
}

function checkout_cycle_label(string $cycle): string
{
    return $cycle === 'annual' ? 'Anual' : 'Mensal';
}

function checkout_payment_label(string $payment): string
{
    return [
        'pix' => 'Pix',
        'card' => 'Cartão',
        'boleto' => 'Boleto',
    ][$payment] ?? 'Pix';
}

function checkout_redirect_success(): never
{
    redirect('/checkout.php?sucesso=1');
}

$pdo = db();
$errors = [];
$globalError = '';
$success = null;
$trialDays = max(1, (int) env('CHECKOUT_TRIAL_DAYS', '7'));
$today = new DateTimeImmutable('today');
$trialDueDate = $today->modify('+' . $trialDays . ' days');

$values = [
    'empresa_nome' => '',
    'empresa_cnpj' => '',
    'name' => '',
    'email' => '',
    'phone' => '',
    'usuario_documento' => '',
    'cycle' => 'monthly',
    'payment' => 'pix',
    'terms' => '',
];

$requestedPlanId = (int) ($_GET['plano_id'] ?? 0);
$selectedPlan = checkout_fetch_active_plan($pdo, $requestedPlanId) ?: checkout_default_plan($pdo);

if (($_GET['sucesso'] ?? '') === '1' && !empty($_SESSION['checkout_success']) && is_array($_SESSION['checkout_success'])) {
    $success = $_SESSION['checkout_success'];
    unset($_SESSION['checkout_success']);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $values = [
        'empresa_nome' => checkout_normalize_text($_POST['empresa_nome'] ?? ''),
        'empresa_cnpj' => only_digits((string) ($_POST['empresa_cnpj'] ?? '')),
        'name' => checkout_normalize_text($_POST['name'] ?? ''),
        'email' => strtolower(checkout_normalize_text($_POST['email'] ?? '')),
        'phone' => only_digits((string) ($_POST['phone'] ?? '')),
        'usuario_documento' => only_digits((string) ($_POST['usuario_documento'] ?? '')),
        'cycle' => (string) ($_POST['cycle'] ?? 'monthly'),
        'payment' => (string) ($_POST['payment'] ?? 'pix'),
        'terms' => isset($_POST['terms']) ? '1' : '',
    ];

    $postedPlanId = (int) ($_POST['plano_id'] ?? 0);
    $selectedPlan = checkout_fetch_active_plan($pdo, $postedPlanId);
    $usuarioDocumentoTipo = usuario_documento_tipo($values['usuario_documento']);
    $senha = (string) ($_POST['senha'] ?? '');
    $senhaConfirmacao = (string) ($_POST['senha_confirmacao'] ?? '');

    if (!$selectedPlan) {
        $errors['plano_id'] = 'Selecione um plano ativo para continuar.';
        $selectedPlan = checkout_default_plan($pdo);
    }

    if ($values['empresa_nome'] === '' || strlen($values['empresa_nome']) < 3 || strlen($values['empresa_nome']) > 150) {
        $errors['empresa_nome'] = 'Informe o nome da empresa com 3 a 150 caracteres.';
    }

    if ($values['empresa_cnpj'] === '' || !cnpj_valido($values['empresa_cnpj'])) {
        $errors['empresa_cnpj'] = 'Informe um CNPJ válido para a empresa.';
    }

    if ($values['name'] === '' || strlen($values['name']) < 3 || strlen($values['name']) > 120) {
        $errors['name'] = 'Informe o nome completo do administrador.';
    }

    if ($values['email'] === '' || strlen($values['email']) > 150 || !filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Informe um e-mail válido.';
    }

    if (strlen($values['phone']) < 10 || strlen($values['phone']) > 11) {
        $errors['phone'] = 'Informe um WhatsApp válido com DDD.';
    }

    if ($values['usuario_documento'] === '' || !$usuarioDocumentoTipo || !documento_cpf_cnpj_valido($values['usuario_documento'])) {
        $errors['usuario_documento'] = 'Informe um CPF ou CNPJ válido para o administrador.';
    }

    if (strlen($senha) < 8 || !preg_match('/[A-Za-z]/', $senha) || !preg_match('/\d/', $senha)) {
        $errors['senha'] = 'Use uma senha com pelo menos 8 caracteres, letras e números.';
    }

    if ($senhaConfirmacao === '' || !hash_equals($senha, $senhaConfirmacao)) {
        $errors['senha_confirmacao'] = 'Confirme a mesma senha informada acima.';
    }

    if (!in_array($values['cycle'], ['monthly', 'annual'], true)) {
        $errors['cycle'] = 'Selecione um ciclo de cobrança válido.';
        $values['cycle'] = 'monthly';
    }

    if (!in_array($values['payment'], ['pix', 'card', 'boleto'], true)) {
        $errors['payment'] = 'Selecione uma forma de pagamento válida.';
        $values['payment'] = 'pix';
    }

    if ($values['terms'] !== '1') {
        $errors['terms'] = 'Confirme o aceite para criar a assinatura.';
    }

    if (!$errors) {
        if (checkout_exists($pdo, "SELECT id FROM empresas WHERE REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(cnpj, ''), '.', ''), '/', ''), '-', ''), ' ', '') = :cnpj LIMIT 1", [':cnpj' => $values['empresa_cnpj']])) {
            $errors['empresa_cnpj'] = 'Já existe uma empresa cadastrada com este CNPJ.';
        }

        if (checkout_exists($pdo, 'SELECT id FROM usuarios WHERE email = :email LIMIT 1', [':email' => $values['email']])) {
            $errors['email'] = 'Já existe um usuário cadastrado com este e-mail.';
        }

        if (checkout_exists($pdo, 'SELECT id FROM usuarios WHERE documento = :documento LIMIT 1', [':documento' => $values['usuario_documento']])) {
            $errors['usuario_documento'] = 'Já existe um usuário cadastrado com este CPF/CNPJ.';
        }
    }

    if (!$errors && $selectedPlan) {
        $planoValorMensal = (float) $selectedPlan['preco'];
        $assinaturaValor = $values['cycle'] === 'annual' ? $planoValorMensal * 10 : $planoValorMensal;
        $assinaturaPeriodo = $values['cycle'] === 'annual' ? 'ano' : 'mês';

        $pdo->beginTransaction();

        try {
            $stmt = $pdo->prepare(
                "INSERT INTO empresas (plano_id, nome, cnpj, email, telefone, status, criado_em)
                 VALUES (:plano_id, :nome, :cnpj, :email, :telefone, 'teste', NOW())"
            );
            $stmt->execute([
                ':plano_id' => (int) $selectedPlan['id'],
                ':nome' => $values['empresa_nome'],
                ':cnpj' => $values['empresa_cnpj'],
                ':email' => $values['email'],
                ':telefone' => $values['phone'],
            ]);

            $empresaId = (int) $pdo->lastInsertId();

            $stmt = $pdo->prepare(
                "INSERT INTO usuarios (empresa_id, nome, email, documento, documento_tipo, senha, tipo, ativo, criado_em)
                 VALUES (:empresa_id, :nome, :email, :documento, :documento_tipo, :senha, 'empresa_admin', 1, NOW())"
            );
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':nome' => $values['name'],
                ':email' => $values['email'],
                ':documento' => $values['usuario_documento'],
                ':documento_tipo' => $usuarioDocumentoTipo,
                ':senha' => password_hash($senha, PASSWORD_DEFAULT),
            ]);

            $stmt = $pdo->prepare(
                "INSERT INTO assinaturas (empresa_id, plano_id, status, valor, data_inicio, data_vencimento, criado_em)
                 VALUES (:empresa_id, :plano_id, 'teste', :valor, :data_inicio, :data_vencimento, NOW())"
            );
            $stmt->execute([
                ':empresa_id' => $empresaId,
                ':plano_id' => (int) $selectedPlan['id'],
                ':valor' => $assinaturaValor,
                ':data_inicio' => $today->format('Y-m-d'),
                ':data_vencimento' => $trialDueDate->format('Y-m-d'),
            ]);

            $pdo->commit();

            $_SESSION['checkout_success'] = [
                'empresa_nome' => $values['empresa_nome'],
                'email' => $values['email'],
                'plano_nome' => (string) $selectedPlan['nome'],
                'plano_valor' => moeda_br($assinaturaValor) . '/' . $assinaturaPeriodo,
                'cycle' => checkout_cycle_label($values['cycle']),
                'payment' => checkout_payment_label($values['payment']),
                'data_vencimento' => $trialDueDate->format('Y-m-d'),
            ];

            checkout_redirect_success();
        } catch (PDOException $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[CHECKOUT] ' . $e->getMessage());

            if ($e->getCode() === '23000') {
                $globalError = 'Não foi possível concluir: e-mail, CNPJ ou CPF/CNPJ já cadastrado.';
            } else {
                $globalError = 'Não foi possível concluir o checkout agora. Tente novamente em instantes.';
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            error_log('[CHECKOUT] ' . $e->getMessage());
            $globalError = 'Não foi possível concluir o checkout agora. Tente novamente em instantes.';
        }
    } elseif ($errors) {
        $globalError = 'Revise os campos destacados para finalizar sua assinatura.';
    }
}

if (!$selectedPlan && $success === null) {
    $globalError = 'Nenhum plano ativo foi encontrado. Cadastre um plano ativo no painel administrativo antes de usar o checkout.';
}

$monthlyValue = $selectedPlan ? (float) $selectedPlan['preco'] : 0.0;
$annualValue = $monthlyValue * 10;
$selectedCycle = in_array($values['cycle'], ['monthly', 'annual'], true) ? $values['cycle'] : 'monthly';
$selectedPayment = in_array($values['payment'], ['pix', 'card', 'boleto'], true) ? $values['payment'] : 'pix';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= $success ? 'Assinatura criada - FluxPay' : 'Checkout FluxPay - Assinatura' ?></title>
    <meta name="description" content="Checkout FluxPay para criação segura de empresa, administrador e assinatura inicial.">
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
            <span class="brand-copy"><strong>FluxPay</strong><small>Checkout seguro</small></span>
        </a>
        <div class="checkout-header-actions">
            <span><svg><use href="#i-lock"></use></svg> Validação server-side e proteção CSRF</span>
            <a class="btn btn-outline" href="<?= e(public_url('/index.php')) ?>">Voltar para o site</a>
        </div>
    </div>
</header>

<main id="conteudo" class="checkout-main">
    <?php if ($success): ?>
        <section class="checkout-page-hero">
            <div class="container">
                <div class="checkout-title reveal">
                    <span class="badge">Assinatura criada</span>
                    <h1>Empresa cadastrada com sucesso</h1>
                    <p>O ambiente da <?= e((string) $success['empresa_nome']) ?> foi criado com administrador, plano e assinatura inicial de teste.</p>
                </div>
            </div>
        </section>

        <section class="checkout-page-section">
            <div class="container checkout-success-grid">
                <article class="checkout-success-card reveal">
                    <div class="checkout-success-icon"><svg><use href="#i-check"></use></svg></div>
                    <h2>Próximo passo: acessar o painel</h2>
                    <p>Entre com o e-mail cadastrado e a senha definida no checkout para configurar clientes, cobranças e automações da empresa.</p>
                    <dl>
                        <div><dt>E-mail de acesso</dt><dd><?= e((string) $success['email']) ?></dd></div>
                        <div><dt>Plano</dt><dd><?= e((string) $success['plano_nome']) ?> - <?= e((string) $success['plano_valor']) ?></dd></div>
                        <div><dt>Ciclo escolhido</dt><dd><?= e((string) $success['cycle']) ?></dd></div>
                        <div><dt>Preferência de pagamento</dt><dd><?= e((string) $success['payment']) ?></dd></div>
                        <div><dt>Teste válido até</dt><dd><?= e(data_br((string) $success['data_vencimento'])) ?></dd></div>
                    </dl>
                    <div class="checkout-success-actions">
                        <a class="btn btn-solid" href="<?= e(public_url('/login.php')) ?>">Acessar painel</a>
                        <a class="btn btn-ghost" href="<?= e(public_url('/index.php')) ?>">Voltar ao site</a>
                    </div>
                </article>
            </div>
        </section>
    <?php else: ?>
        <section class="checkout-page-hero">
            <div class="container">
                <div class="checkout-title reveal">
                    <span class="badge">Checkout FluxPay</span>
                    <h1>Crie sua conta e ative a assinatura inicial</h1>
                    <p>Preencha os dados da empresa e do administrador. O sistema valida as informações no servidor e cria o acesso em uma transação segura.</p>
                </div>
            </div>
        </section>

        <section class="checkout-page-section">
            <div class="container checkout-page-grid">
                <form class="checkout-flow reveal" method="post" action="<?= e(public_url('/checkout.php')) ?>" data-checkout-form novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="plano_id" value="<?= $selectedPlan ? (int) $selectedPlan['id'] : 0 ?>">
                    <input type="hidden" name="cycle" value="<?= e($selectedCycle) ?>">
                    <input type="hidden" name="payment" value="<?= e($selectedPayment) ?>">

                    <?php if ($globalError !== ''): ?>
                        <div class="checkout-alert is-error" role="alert"><?= e($globalError) ?></div>
                    <?php endif; ?>

                    <section class="checkout-block">
                        <div class="checkout-step-heading">
                            <span>1</span>
                            <div>
                                <strong>Empresa</strong>
                                <small>Dados usados para criar a conta locatária</small>
                            </div>
                        </div>
                        <label class="<?= checkout_has_error($errors, 'empresa_nome') ?>">Nome da empresa<input type="text" name="empresa_nome" autocomplete="organization" placeholder="Nome comercial da empresa" required value="<?= e($values['empresa_nome']) ?>"><small data-error-for="empresa_nome"><?= checkout_field_error($errors, 'empresa_nome') ?></small></label>
                        <div class="checkout-fields two-cols">
                            <label class="<?= checkout_has_error($errors, 'empresa_cnpj') ?>">CNPJ<input type="text" name="empresa_cnpj" inputmode="numeric" autocomplete="off" placeholder="00.000.000/0000-00" required value="<?= e(checkout_document_input_value($values['empresa_cnpj'])) ?>"><small data-error-for="empresa_cnpj"><?= checkout_field_error($errors, 'empresa_cnpj') ?></small></label>
                            <label class="<?= checkout_has_error($errors, 'phone') ?>">WhatsApp<input type="tel" name="phone" autocomplete="tel" placeholder="(00) 00000-0000" required value="<?= e($values['phone']) ?>"><small data-error-for="phone"><?= checkout_field_error($errors, 'phone') ?></small></label>
                        </div>
                    </section>

                    <section class="checkout-block">
                        <div class="checkout-step-heading">
                            <span>2</span>
                            <div>
                                <strong>Administrador</strong>
                                <small>Usuário principal da empresa</small>
                            </div>
                        </div>
                        <div class="checkout-fields two-cols">
                            <label class="<?= checkout_has_error($errors, 'name') ?>">Nome completo<input type="text" name="name" autocomplete="name" placeholder="Nome do administrador" required value="<?= e($values['name']) ?>"><small data-error-for="name"><?= checkout_field_error($errors, 'name') ?></small></label>
                            <label class="<?= checkout_has_error($errors, 'email') ?>">E-mail<input type="email" name="email" autocomplete="email" placeholder="voce@empresa.com" required value="<?= e($values['email']) ?>"><small data-error-for="email"><?= checkout_field_error($errors, 'email') ?></small></label>
                        </div>
                        <label class="<?= checkout_has_error($errors, 'usuario_documento') ?>">CPF ou CNPJ do administrador<input type="text" name="usuario_documento" inputmode="numeric" autocomplete="username" placeholder="CPF ou CNPJ para login" required value="<?= e(checkout_document_input_value($values['usuario_documento'])) ?>"><small data-error-for="usuario_documento"><?= checkout_field_error($errors, 'usuario_documento') ?></small></label>
                        <div class="checkout-fields two-cols">
                            <label class="<?= checkout_has_error($errors, 'senha') ?>">Senha<input type="password" name="senha" autocomplete="new-password" placeholder="Mínimo 8 caracteres" required><small data-error-for="senha"><?= checkout_field_error($errors, 'senha') ?></small></label>
                            <label class="<?= checkout_has_error($errors, 'senha_confirmacao') ?>">Confirmar senha<input type="password" name="senha_confirmacao" autocomplete="new-password" placeholder="Repita a senha" required><small data-error-for="senha_confirmacao"><?= checkout_field_error($errors, 'senha_confirmacao') ?></small></label>
                        </div>
                    </section>

                    <section class="checkout-block">
                        <div class="checkout-step-heading">
                            <span>3</span>
                            <div>
                                <strong>Plano</strong>
                                <small>Assinatura inicial vinculada ao plano ativo</small>
                            </div>
                        </div>
                        <div class="selected-plan-row">
                            <div><span>Plano selecionado</span><strong><?= $selectedPlan ? e((string) $selectedPlan['nome']) : 'Indisponível' ?></strong></div>
                            <div><span>Valor mensal</span><strong><?= moeda_br($monthlyValue) ?></strong></div>
                        </div>
                        <?php if (isset($errors['plano_id'])): ?>
                            <p class="checkout-inline-error"><?= e($errors['plano_id']) ?></p>
                        <?php endif; ?>
                        <div class="checkout-cycle" data-checkout-cycle>
                            <button type="button" class="<?= $selectedCycle === 'monthly' ? 'active' : '' ?>" data-cycle="monthly" aria-pressed="<?= $selectedCycle === 'monthly' ? 'true' : 'false' ?>">Mensal</button>
                            <button type="button" class="<?= $selectedCycle === 'annual' ? 'active' : '' ?>" data-cycle="annual" aria-pressed="<?= $selectedCycle === 'annual' ? 'true' : 'false' ?>">Anual</button>
                        </div>
                    </section>

                    <section class="checkout-block">
                        <div class="checkout-step-heading">
                            <span>4</span>
                            <div>
                                <strong>Pagamento</strong>
                                <small>Preferência para contato comercial e ativação</small>
                            </div>
                        </div>
                        <div class="checkout-payment-methods" data-checkout-payments>
                            <button type="button" class="<?= $selectedPayment === 'pix' ? 'active' : '' ?>" data-payment="pix" aria-pressed="<?= $selectedPayment === 'pix' ? 'true' : 'false' ?>"><svg><use href="#i-check"></use></svg> Pix</button>
                            <button type="button" class="<?= $selectedPayment === 'card' ? 'active' : '' ?>" data-payment="card" aria-pressed="<?= $selectedPayment === 'card' ? 'true' : 'false' ?>"><svg><use href="#i-card"></use></svg> Cartão</button>
                            <button type="button" class="<?= $selectedPayment === 'boleto' ? 'active' : '' ?>" data-payment="boleto" aria-pressed="<?= $selectedPayment === 'boleto' ? 'true' : 'false' ?>"><svg><use href="#i-lock"></use></svg> Boleto</button>
                        </div>
                        <?php if (isset($errors['payment'])): ?>
                            <p class="checkout-inline-error"><?= e($errors['payment']) ?></p>
                        <?php endif; ?>
                        <div class="payment-preview" data-payment-preview>
                            <strong><?= e(checkout_payment_label($selectedPayment)) ?> selecionado</strong>
                            <p>A assinatura inicial será criada em teste. A cobrança recorrente deve ser confirmada conforme a forma escolhida e as regras comerciais do plano.</p>
                        </div>
                    </section>

                    <section class="checkout-block">
                        <div class="checkout-step-heading">
                            <span>5</span>
                            <div>
                                <strong>Confirmação</strong>
                                <small>Revise os dados antes de finalizar</small>
                            </div>
                        </div>
                        <label class="checkbox-line<?= checkout_has_error($errors, 'terms') ?>"><input type="checkbox" name="terms" value="1" required <?= $values['terms'] === '1' ? 'checked' : '' ?>> Confirmo que os dados informados são verdadeiros e aceito criar a assinatura inicial da empresa na FluxPay.<small data-error-for="terms"><?= checkout_field_error($errors, 'terms') ?></small></label>
                        <button class="btn btn-solid checkout-submit" type="submit" <?= $selectedPlan ? '' : 'disabled' ?>>Finalizar assinatura</button>
                        <p class="form-status" data-checkout-status role="status" aria-live="polite"></p>
                    </section>
                </form>

                <aside class="checkout-summary reveal" data-parallax-card>
                    <div class="summary-card">
                        <span class="summary-kicker">Resumo do pedido</span>
                        <h2><?= $selectedPlan ? e((string) $selectedPlan['nome']) : 'Plano indisponível' ?></h2>
                        <p>Assinatura inicial da empresa com acesso administrativo, usuários internos e gestão de cobranças recorrentes.</p>
                        <div class="summary-lines">
                            <div><span>Mensalidade</span><strong data-summary-price data-monthly-price="<?= e(moeda_br($monthlyValue) . '/mês') ?>" data-annual-price="<?= e(moeda_br($annualValue) . '/ano') ?>"><?= e($selectedCycle === 'annual' ? moeda_br($annualValue) . '/ano' : moeda_br($monthlyValue) . '/mês') ?></strong></div>
                            <div><span>Teste inicial</span><strong data-summary-discount data-monthly-discount="<?= e($trialDays . ' dias inclusos') ?>" data-annual-discount="<?= e($trialDays . ' dias inclusos') ?>"><?= e($trialDays) ?> dias inclusos</strong></div>
                            <div class="total"><span>Total hoje</span><strong data-summary-total data-monthly-total="<?= e(moeda_br(0)) ?>" data-annual-total="<?= e(moeda_br(0)) ?>"><?= moeda_br(0) ?></strong></div>
                            <div><span>Renovação</span><strong data-summary-renewal data-monthly-renewal="<?= e(moeda_br($monthlyValue) . '/mês') ?>" data-annual-renewal="<?= e(moeda_br($annualValue) . '/ano') ?>"><?= e($selectedCycle === 'annual' ? moeda_br($annualValue) . '/ano' : moeda_br($monthlyValue) . '/mês') ?></strong></div>
                        </div>
                        <div class="summary-trust">
                            <span><svg><use href="#i-check"></use></svg> Conta criada com senha criptografada</span>
                            <span><svg><use href="#i-check"></use></svg> Validação de e-mail, CNPJ e CPF/CNPJ</span>
                            <span><svg><use href="#i-check"></use></svg> Empresa, administrador e assinatura em transação</span>
                            <span><svg><use href="#i-check"></use></svg> Acesso imediato ao painel durante o teste</span>
                        </div>
                        <small>O processamento financeiro recorrente deve ser conciliado pelo administrador da plataforma conforme o provedor de pagamento utilizado.</small>
                    </div>
                </aside>
            </div>
        </section>
    <?php endif; ?>
</main>

<script src="<?= e(asset_url('/assets/js/main.js')) ?>" defer></script>
</body>
</html>
