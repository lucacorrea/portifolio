<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/ui.php';

/*
|--------------------------------------------------------------------------
| Permissões
|--------------------------------------------------------------------------
*/

$canConfigure =
    $authorization->can('nota_fiscal.configurar');

$canManageCredentials =
    $authorization->can('nota_fiscal.gerenciar_credenciais');

$canTestIntegration =
    $authorization->can('nota_fiscal.testar_integracao');

$canActivateProduction =
    $authorization->can('nota_fiscal.ativar_producao');

$canInutilize =
    $authorization->can('nota_fiscal.inutilizar');

/*
|--------------------------------------------------------------------------
| Requisitos de execução
|--------------------------------------------------------------------------
*/

$runtime =
    $application
        ->fiscalRuntimeReadiness()
        ->inspect();

/*
|--------------------------------------------------------------------------
| Ambiente
|--------------------------------------------------------------------------
*/

$selectedEnvironment = strtolower(
    trim(
        (string) ($_GET['ambiente'] ?? 'homologacao')
    )
);

if (
    !in_array(
        $selectedEnvironment,
        ['homologacao', 'producao'],
        true
    )
) {
    $selectedEnvironment = 'homologacao';
}

/*
 * Usuário sem permissão de produção nunca consegue sequer
 * abrir a configuração de produção.
 */
if (
    $selectedEnvironment === 'producao'
    && !$canActivateProduction
) {
    $selectedEnvironment = 'homologacao';
}

$selectedEnvironmentLabel =
    $selectedEnvironment === 'producao'
        ? 'Produção'
        : 'Homologação';

$canEditSelectedEnvironment =
    $selectedEnvironment === 'producao'
        ? $canActivateProduction
        : $canConfigure;

/*
|--------------------------------------------------------------------------
| Modelo fiscal
|--------------------------------------------------------------------------
*/

$selectedModel = trim(
    (string) ($_GET['modelo'] ?? '65')
);

if (
    !in_array(
        $selectedModel,
        ['55', '65'],
        true
    )
) {
    $selectedModel = '65';
}

$selectedDocumentLabel =
    $selectedModel === '55'
        ? 'NF-e'
        : 'NFC-e';

/*
|--------------------------------------------------------------------------
| Configuração
|--------------------------------------------------------------------------
*/

$overview = null;

try {
    $overview =
        $application
            ->fiscalConfiguration()
            ->overview(
                $selectedEnvironment,
                $selectedModel
            );
} catch (Throwable $exception) {
    error_log(
        'Fiscal configuration overview unavailable ['
        . get_class($exception)
        . ']: '
        . $exception->getMessage()
    );
}

$readiness =
    is_array($overview['readiness'] ?? null)
        ? $overview['readiness']
        : null;

$configuration =
    is_array($overview['configuration'] ?? null)
        ? $overview['configuration']
        : null;

$integrationTest =
    is_array($overview['integration_test'] ?? null)
        ? $overview['integration_test']
        : null;

$certificates =
    is_array($overview['certificates'] ?? null)
        ? $overview['certificates']
        : [];

$series =
    is_array($overview['series'] ?? null)
        ? $overview['series']
        : [];

/*
|--------------------------------------------------------------------------
| Inutilizações
|--------------------------------------------------------------------------
*/

$inutilizations = [];

if (
    $canInutilize
    && (
        $selectedEnvironment !== 'producao'
        || $canActivateProduction
    )
) {
    try {
        $inutilizations =
            $application
                ->fiscalInutilization()
                ->recent(
                    $selectedEnvironment,
                    $selectedModel
                );
    } catch (Throwable $exception) {
        error_log(
            'Fiscal inutilization overview unavailable ['
            . get_class($exception)
            . ']: '
            . $exception->getMessage()
        );
    }
}

/*
|--------------------------------------------------------------------------
| Valores atuais
|--------------------------------------------------------------------------
*/

$currentUf = strtoupper(
    trim(
        (string) (
            $configuration['uf']
            ?? 'AM'
        )
    )
);

if ($currentUf === '') {
    $currentUf = 'AM';
}

$currentSchemaVersion = trim(
    (string) (
        $configuration['schema_versao']
        ?? '4.00'
    )
);

if ($currentSchemaVersion === '') {
    $currentSchemaVersion = '4.00';
}

$currentCertificateId =
    (int) (
        $configuration['certificado_id']
        ?? 0
    );

?>

<div class="page-body settings-page">

    <!-- =========================================================
         SELETOR DE AMBIENTE
         ========================================================= -->

    <section class="panel mb-4">

        <div class="panel-header">
            <div class="panel-title">
                <i class="bi bi-diagram-3"></i>
                Ambiente fiscal
            </div>
        </div>

        <div class="p-3">

            <form
                method="get"
                action="configuracoes-fiscais.php"
                class="row g-3 align-items-end"
            >

                <div class="col-12 col-md-5">

                    <label
                        class="form-label"
                        for="fiscal-environment"
                    >
                        Ambiente SEFAZ
                    </label>

                    <select
                        class="form-select form-control-os"
                        id="fiscal-environment"
                        name="ambiente"
                        required
                    >
                        <option
                            value="homologacao"
                            <?= $selectedEnvironment === 'homologacao'
                                ? 'selected'
                                : '' ?>
                        >
                            Homologação
                        </option>

                        <?php if ($canActivateProduction): ?>

                            <option
                                value="producao"
                                <?= $selectedEnvironment === 'producao'
                                    ? 'selected'
                                    : '' ?>
                            >
                                Produção
                            </option>

                        <?php endif; ?>

                    </select>

                </div>

                <div class="col-12 col-md-5">

                    <label
                        class="form-label"
                        for="fiscal-model"
                    >
                        Documento fiscal
                    </label>

                    <select
                        class="form-select form-control-os"
                        id="fiscal-model"
                        name="modelo"
                        required
                    >
                        <option
                            value="55"
                            <?= $selectedModel === '55'
                                ? 'selected'
                                : '' ?>
                        >
                            NF-e — Modelo 55
                        </option>

                        <option
                            value="65"
                            <?= $selectedModel === '65'
                                ? 'selected'
                                : '' ?>
                        >
                            NFC-e — Modelo 65
                        </option>
                    </select>

                </div>

                <div class="col-12 col-md-2">

                    <button
                        class="btn-modal-save w-100"
                        type="submit"
                    >
                        <i class="bi bi-arrow-repeat"></i>
                        Alterar
                    </button>

                </div>

            </form>

            <div class="mt-3">

                <span class="badge <?= $selectedEnvironment === 'producao'
                    ? 'text-bg-danger'
                    : 'text-bg-primary' ?>">
                    <?= h($selectedEnvironmentLabel) ?>
                </span>

                <span class="badge text-bg-secondary">
                    <?= h($selectedDocumentLabel) ?>
                    modelo
                    <?= h($selectedModel) ?>
                </span>

            </div>

        </div>

    </section>


    <!-- =========================================================
         AVISO DO AMBIENTE
         ========================================================= -->

    <div
        class="alert <?= $selectedEnvironment === 'producao'
            ? 'alert-danger'
            : 'alert-info' ?> mb-3"
    >

        <i class="bi bi-shield-check me-2"></i>

        <?php if ($selectedEnvironment === 'producao'): ?>

            <strong>Ambiente de produção.</strong>

            Série, numeração e configuração são independentes.

            O teste de Status Serviço não deve emitir documento fiscal.

        <?php else: ?>

            <strong>Ambiente de homologação.</strong>

            Utilize este ambiente para validar certificado,
            configuração e comunicação antes da produção.

        <?php endif; ?>

    </div>


    <!-- =========================================================
         ATALHOS
         ========================================================= -->

    <nav
        class="d-flex flex-wrap gap-2 mb-2"
        aria-label="Ambiente fiscal"
    >

        <a
            class="btn-filter <?= $selectedEnvironment === 'homologacao'
                ? 'btn-filter-primary'
                : 'btn-filter-ghost' ?>"
            href="configuracoes-fiscais.php?ambiente=homologacao&amp;modelo=<?= h($selectedModel) ?>"
        >
            <i class="bi bi-tools"></i>
            Homologação
        </a>

        <?php if ($canActivateProduction): ?>

            <a
                class="btn-filter <?= $selectedEnvironment === 'producao'
                    ? 'btn-filter-primary'
                    : 'btn-filter-ghost' ?>"
                href="configuracoes-fiscais.php?ambiente=producao&amp;modelo=<?= h($selectedModel) ?>"
            >
                <i class="bi bi-building-check"></i>
                Produção
            </a>

        <?php endif; ?>

    </nav>

    <nav
        class="d-flex flex-wrap gap-2 mb-4"
        aria-label="Modelo de documento fiscal"
    >

        <a
            class="btn-filter <?= $selectedModel === '55'
                ? 'btn-filter-primary'
                : 'btn-filter-ghost' ?>"
            href="configuracoes-fiscais.php?ambiente=<?= h($selectedEnvironment) ?>&amp;modelo=55"
        >
            <i class="bi bi-file-earmark-text"></i>
            NF-e (modelo 55)
        </a>

        <a
            class="btn-filter <?= $selectedModel === '65'
                ? 'btn-filter-primary'
                : 'btn-filter-ghost' ?>"
            href="configuracoes-fiscais.php?ambiente=<?= h($selectedEnvironment) ?>&amp;modelo=65"
        >
            <i class="bi bi-receipt-cutoff"></i>
            NFC-e (modelo 65)
        </a>

    </nav>


    <!-- =========================================================
         ESTRUTURA DO BANCO
         ========================================================= -->

    <?php if ($overview === null): ?>

        <div class="alert alert-warning">

            <i class="bi bi-database-exclamation me-2"></i>

            A estrutura fiscal ainda não está disponível.

            Execute a migração fiscal pelo processo controlado
            antes de configurar.

        </div>

    <?php endif; ?>


    <!-- =========================================================
         REQUISITOS DO SERVIDOR
         ========================================================= -->

    <section class="panel mb-4">

        <div class="panel-header">

            <div class="panel-title">
                <i class="bi bi-pc-display-horizontal"></i>
                Requisitos do servidor
            </div>

        </div>

        <div class="p-3">

            <div class="row g-2">

                <?php foreach (($runtime['checks'] ?? []) as $check): ?>

                    <?php
                    $checkOk =
                        (bool) ($check['ok'] ?? false);

                    $checkLabel =
                        (string) (
                            $check['label']
                            ?? 'Requisito'
                        );
                    ?>

                    <div class="col-12 col-md-6">

                        <div
                            class="d-flex justify-content-between align-items-center border rounded-3 p-3 h-100"
                        >

                            <span>
                                <?= h($checkLabel) ?>
                            </span>

                            <span
                                class="badge <?= $checkOk
                                    ? 'text-bg-success'
                                    : 'text-bg-danger' ?>"
                            >
                                <?= $checkOk
                                    ? 'OK'
                                    : 'Pendente' ?>
                            </span>

                        </div>

                    </div>

                <?php endforeach; ?>

            </div>

            <?php if (!($runtime['homologation_ready'] ?? false)): ?>

                <p class="text-danger mt-3 mb-0">

                    Nenhuma chamada à SEFAZ será liberada
                    enquanto houver requisito técnico pendente.

                </p>

            <?php endif; ?>

        </div>

    </section>


    <div class="settings-grid">

        <!-- =====================================================
             CERTIFICADO
             ===================================================== -->

        <section class="panel settings-panel">

            <div class="panel-header">

                <div class="panel-title">
                    <i class="bi bi-file-earmark-lock2"></i>
                    Certificado digital A1
                </div>

            </div>

            <div class="p-3">

                <p class="text-muted">

                    O certificado PFX/P12 deve ser validado
                    contra o CNPJ da empresa e armazenado fora
                    do diretório público.

                    A senha deve permanecer cifrada.

                </p>


                <?php if ($certificates !== []): ?>

                    <?php foreach ($certificates as $certificate): ?>

                        <div class="border rounded-3 p-3 mb-2">

                            <strong>
                                <?= h(
                                    (string) (
                                        $certificate['titular_nome']
                                        ?? 'Certificado A1'
                                    )
                                ) ?>
                            </strong>

                            <div class="small text-muted">

                                CNPJ
                                <?= h(
                                    (string) (
                                        $certificate['titular_cnpj']
                                        ?? ''
                                    )
                                ) ?>

                                <?php if (!empty($certificate['valido_ate'])): ?>

                                    · válido até

                                    <?= h(
                                        date(
                                            'd/m/Y',
                                            strtotime(
                                                (string) $certificate['valido_ate']
                                            )
                                        )
                                    ) ?>

                                <?php endif; ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="alert alert-warning">
                        Nenhum certificado A1 válido cadastrado.
                    </div>

                <?php endif; ?>


                <?php if (
                    $canManageCredentials
                    && $overview !== null
                ): ?>

                    <form
                        method="post"
                        action="actions/configuracao-fiscal-certificado-salvar.php"
                        enctype="multipart/form-data"
                        data-fiscal-certificate-form
                    >

                        <?= $csrf->field() ?>

                        <?php return_to_field(); ?>

                        <input
                            type="hidden"
                            name="MAX_FILE_SIZE"
                            value="2097152"
                        >

                        <div class="form-group">

                            <label
                                class="form-label"
                                for="fiscal-certificate"
                            >
                                Certificado PFX/P12
                            </label>

                            <input
                                class="form-control-os"
                                id="fiscal-certificate"
                                type="file"
                                name="certificado"
                                accept=".pfx,.p12,application/x-pkcs12"
                                required
                            >

                        </div>

                        <div class="form-group">

                            <label
                                class="form-label"
                                for="fiscal-certificate-password"
                            >
                                Senha do certificado
                            </label>

                            <input
                                class="form-control-os"
                                id="fiscal-certificate-password"
                                type="password"
                                name="senha_certificado"
                                maxlength="200"
                                autocomplete="new-password"
                                required
                            >

                        </div>

                        <div
                            class="alert alert-danger py-2 d-none"
                            role="alert"
                            data-fiscal-certificate-feedback
                        ></div>

                        <button
                            class="btn-modal-save"
                            type="submit"
                            data-fiscal-certificate-submit
                        >
                            <i class="bi bi-shield-lock"></i>
                            Validar e armazenar
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </section>


        <!-- =====================================================
             CONFIGURAÇÃO
             ===================================================== -->

        <section class="panel settings-panel">

            <div class="panel-header">

                <div class="panel-title">

                    <i class="bi bi-receipt"></i>

                    <?= h($selectedDocumentLabel) ?>
                    ·
                    <?= h($selectedEnvironmentLabel) ?>

                </div>

            </div>

            <div class="p-3">

                <?php if ($configuration !== null): ?>

                    <div class="alert alert-info">

                        Versão
                        <?= (int) ($configuration['versao'] ?? 0) ?>

                        · modelo
                        <?= h($selectedModel) ?>

                        · status

                        <strong>
                            <?= h(
                                (string) (
                                    $configuration['status']
                                    ?? 'desconhecido'
                                )
                            ) ?>
                        </strong>

                    </div>

                <?php else: ?>

                    <div class="alert alert-warning">

                        Nenhuma configuração encontrada para

                        <strong>
                            <?= h($selectedDocumentLabel) ?>
                            /
                            <?= h($selectedEnvironmentLabel) ?>
                        </strong>.

                    </div>

                <?php endif; ?>


                <?php if (
                    $canEditSelectedEnvironment
                    && $certificates !== []
                    && $overview !== null
                ): ?>

                    <form
                        method="post"
                        action="actions/configuracao-fiscal-salvar.php"
                    >

                        <?= $csrf->field() ?>

                        <?php return_to_field(); ?>

                        <input
                            type="hidden"
                            name="ambiente"
                            value="<?= h($selectedEnvironment) ?>"
                        >

                        <input
                            type="hidden"
                            name="modelo"
                            value="<?= h($selectedModel) ?>"
                        >

                        <div class="form-row">

                            <div class="form-group">

                                <label
                                    class="form-label"
                                    for="fiscal-state"
                                >
                                    UF
                                </label>

                                <input
                                    class="form-control-os"
                                    id="fiscal-state"
                                    name="uf"
                                    value="<?= h($currentUf) ?>"
                                    maxlength="2"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label
                                    class="form-label"
                                    for="fiscal-schema"
                                >
                                    Schema NF-e
                                </label>

                                <input
                                    class="form-control-os"
                                    id="fiscal-schema"
                                    name="schema_versao"
                                    value="<?= h($currentSchemaVersion) ?>"
                                    maxlength="10"
                                    required
                                >

                            </div>

                        </div>

                        <div class="form-group">

                            <label
                                class="form-label"
                                for="fiscal-certificate-id"
                            >
                                Certificado
                            </label>

                            <select
                                class="form-select form-control-os"
                                id="fiscal-certificate-id"
                                name="certificado_id"
                                required
                            >

                                <?php foreach ($certificates as $certificate): ?>

                                    <?php
                                    $certificateId =
                                        (int) (
                                            $certificate['id']
                                            ?? 0
                                        );
                                    ?>

                                    <option
                                        value="<?= $certificateId ?>"
                                        <?= $currentCertificateId === $certificateId
                                            ? 'selected'
                                            : '' ?>
                                    >

                                        <?= h(
                                            (string) (
                                                $certificate['titular_nome']
                                                ?? $certificate['titular_cnpj']
                                                ?? 'Certificado'
                                            )
                                        ) ?>

                                        <?php if (!empty($certificate['valido_ate'])): ?>

                                            —
                                            <?= h(
                                                date(
                                                    'd/m/Y',
                                                    strtotime(
                                                        (string) $certificate['valido_ate']
                                                    )
                                                )
                                            ) ?>

                                        <?php endif; ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <?php if ($selectedModel === '65'): ?>

                            <div class="alert alert-info py-2">

                                Configuração específica da NFC-e
                                modelo 65.

                            </div>

                            <input
                                type="hidden"
                                name="qr_code_versao"
                                value="3"
                            >

                        <?php endif; ?>


                        <?php if ($selectedEnvironment === 'producao'): ?>

                            <div class="alert alert-danger py-2">

                                <strong>Atenção:</strong>

                                você está criando uma configuração
                                para o ambiente de produção.

                            </div>

                        <?php endif; ?>


                        <button
                            class="btn-modal-save"
                            type="submit"
                        >
                            <i class="bi bi-save"></i>
                            Criar nova versão
                        </button>

                    </form>

                <?php elseif ($certificates === []): ?>

                    <p class="text-muted">
                        Cadastre primeiro o certificado A1.
                    </p>

                <?php endif; ?>

            </div>

        </section>


        <!-- =====================================================
             SÉRIE
             ===================================================== -->

        <section class="panel settings-panel">

            <div class="panel-header">

                <div class="panel-title">
                    <i class="bi bi-123"></i>
                    Série e numeração
                </div>

            </div>

            <div class="p-3">

                <?php if ($series !== []): ?>

                    <?php foreach ($series as $serie): ?>

                        <div class="border rounded-3 p-3 mb-2">

                            <strong>
                                Série
                                <?= (int) ($serie['serie'] ?? 0) ?>
                            </strong>

                            <div class="small text-muted">

                                Próximo número:

                                <?= (int) (
                                    $serie['proximo_numero']
                                    ?? 1
                                ) ?>

                            </div>

                        </div>

                    <?php endforeach; ?>

                <?php else: ?>

                    <div class="alert alert-warning">
                        Nenhuma série ativa para este ambiente/modelo.
                    </div>

                <?php endif; ?>


                <?php if (
                    $canEditSelectedEnvironment
                    && $overview !== null
                ): ?>

                    <form
                        method="post"
                        action="actions/configuracao-fiscal-serie-salvar.php"
                    >

                        <?= $csrf->field() ?>

                        <?php return_to_field(); ?>

                        <input
                            type="hidden"
                            name="ambiente"
                            value="<?= h($selectedEnvironment) ?>"
                        >

                        <input
                            type="hidden"
                            name="modelo"
                            value="<?= h($selectedModel) ?>"
                        >

                        <div class="form-row">

                            <div class="form-group">

                                <label
                                    class="form-label"
                                    for="fiscal-series"
                                >
                                    Série
                                </label>

                                <input
                                    class="form-control-os"
                                    id="fiscal-series"
                                    type="number"
                                    name="serie"
                                    min="0"
                                    max="999"
                                    required
                                >

                            </div>

                            <div class="form-group">

                                <label
                                    class="form-label"
                                    for="fiscal-next-number"
                                >
                                    Próximo número confirmado
                                </label>

                                <input
                                    class="form-control-os"
                                    id="fiscal-next-number"
                                    type="number"
                                    name="proximo_numero"
                                    min="1"
                                    max="999999999"
                                    required
                                >

                            </div>

                        </div>

                        <p class="small text-muted">

                            Confirme a série e o próximo número
                            com a contabilidade/SEFAZ.

                            A numeração é independente por
                            ambiente e modelo.

                        </p>

                        <button
                            class="btn-modal-save"
                            type="submit"
                        >
                            <i class="bi bi-save"></i>
                            Salvar e ativar série
                        </button>

                    </form>

                <?php endif; ?>

            </div>

        </section>


        <!-- =====================================================
             CHECKLIST / TESTE SEFAZ
             ===================================================== -->

        <section class="panel settings-panel">

            <div class="panel-header">

                <div class="panel-title">
                    <i class="bi bi-clipboard2-check"></i>
                    Checklist fiscal
                </div>

            </div>

            <div class="p-3">

                <?php if ($readiness === null): ?>

                    <p class="text-muted">
                        Checklist indisponível até a migração fiscal.
                    </p>

                <?php else: ?>


                    <?php foreach (
                        ($readiness['errors'] ?? [])
                        as $error
                    ): ?>

                        <div class="alert alert-danger py-2">
                            <?= h((string) $error) ?>
                        </div>

                    <?php endforeach; ?>


                    <?php foreach (
                        ($readiness['warnings'] ?? [])
                        as $warning
                    ): ?>

                        <div class="alert alert-warning py-2">
                            <?= h((string) $warning) ?>
                        </div>

                    <?php endforeach; ?>


                    <?php if (
                        ($readiness['errors'] ?? []) === []
                    ): ?>

                        <div class="alert alert-success">

                            Cadastros fiscais completos para
                            tentativa de comunicação em

                            <strong>
                                <?= h($selectedEnvironmentLabel) ?>
                            </strong>.

                        </div>

                    <?php endif; ?>


                    <!-- ÚLTIMO TESTE -->

                    <?php if ($integrationTest !== null): ?>

                        <?php
                        $testSuccess =
                            (bool) (
                                $integrationTest['success']
                                ?? false
                            );

                        $testCode =
                            trim(
                                (string) (
                                    $integrationTest['code']
                                    ?? ''
                                )
                            );

                        $testMessage =
                            trim(
                                (string) (
                                    $integrationTest['message']
                                    ?? ''
                                )
                            );

                        $testedAt =
                            trim(
                                (string) (
                                    $integrationTest['tested_at']
                                    ?? ''
                                )
                            );
                        ?>

                        <div
                            class="alert <?= $testSuccess
                                ? 'alert-success'
                                : 'alert-danger' ?> py-2"
                        >

                            <div>

                                <strong>
                                    <?= $testSuccess
                                        ? 'Comunicação validada'
                                        : 'Falha na comunicação' ?>
                                </strong>

                            </div>

                            <?php if ($testedAt !== ''): ?>

                                <div class="small mt-1">

                                    Testado em

                                    <?= h(
                                        date(
                                            'd/m/Y H:i',
                                            strtotime($testedAt)
                                        )
                                    ) ?>

                                </div>

                            <?php endif; ?>

                            <div class="mt-1">

                                Código:

                                <strong>
                                    <?= h(
                                        $testCode !== ''
                                            ? $testCode
                                            : 'falha'
                                    ) ?>
                                </strong>

                            </div>

                            <?php if ($testMessage !== ''): ?>

                                <div>
                                    <?= h($testMessage) ?>
                                </div>

                            <?php endif; ?>

                        </div>

                    <?php endif; ?>


                    <!-- TESTAR SEFAZ -->

                    <?php
                    $canRunSefazTest =
                        (
                            $selectedEnvironment === 'producao'
                                ? $canActivateProduction
                                : $canTestIntegration
                        )
                        && $configuration !== null
                        && ($runtime['homologation_ready'] ?? false);
                    ?>

                    <?php if ($canRunSefazTest): ?>

                        <form
                            class="mb-3"
                            method="post"
                            action="actions/configuracao-fiscal-testar-sefaz.php"
                        >

                            <?= $csrf->field() ?>

                            <?php return_to_field(); ?>

                            <input
                                type="hidden"
                                name="configuracao_id"
                                value="<?= (int) $configuration['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="ambiente"
                                value="<?= h($selectedEnvironment) ?>"
                            >

                            <?php if ($selectedEnvironment === 'producao'): ?>

                                <div class="alert alert-danger py-2">

                                    Este teste utilizará o certificado
                                    e a configuração do ambiente de

                                    <strong>produção</strong>.

                                    O Status Serviço não deve gerar
                                    uma NF-e/NFC-e.

                                </div>

                            <?php endif; ?>

                            <button
                                class="btn-modal-save"
                                type="submit"
                            >
                                <i class="bi bi-cloud-check"></i>

                                Testar SEFAZ
                                <?= h($selectedEnvironmentLabel) ?>
                            </button>

                        </form>

                    <?php endif; ?>


                    <!-- ATIVAÇÃO -->

                    <?php
                    $canActivateConfiguration =
                        (
                            $selectedEnvironment === 'producao'
                                ? $canActivateProduction
                                : $canConfigure
                        )
                        && $configuration !== null
                        && (bool) (
                            $readiness['ready']
                            ?? false
                        )
                        && ($runtime['homologation_ready'] ?? false)
                        && (
                            (
                                $integrationTest['success']
                                ?? false
                            ) === true
                        )
                        && (
                            ($configuration['status'] ?? '')
                            !== 'ativa'
                        );
                    ?>


                    <?php if ($canActivateConfiguration): ?>

                        <form
                            method="post"
                            action="actions/configuracao-fiscal-ativar.php"
                        >

                            <?= $csrf->field() ?>

                            <?php return_to_field(); ?>

                            <input
                                type="hidden"
                                name="configuracao_id"
                                value="<?= (int) $configuration['id'] ?>"
                            >

                            <input
                                type="hidden"
                                name="ambiente"
                                value="<?= h($selectedEnvironment) ?>"
                            >

                            <button
                                class="btn-modal-save"
                                type="submit"
                            >
                                <i class="bi bi-check2-circle"></i>

                                Ativar configuração de
                                <?= h($selectedEnvironmentLabel) ?>

                            </button>

                        </form>

                    <?php endif; ?>


                <?php endif; ?>

            </div>

        </section>

    </div>


    <!-- =========================================================
         INUTILIZAÇÃO
         ========================================================= -->

    <?php if (
        $canInutilize
        && (
            $selectedEnvironment !== 'producao'
            || $canActivateProduction
        )
        && $configuration !== null
    ): ?>

        <section class="panel mt-4">

            <div class="panel-header">

                <div class="panel-title">
                    <i class="bi bi-slash-circle"></i>
                    Inutilização de faixa
                </div>

            </div>

            <div class="p-3">

                <div class="alert alert-warning">

                    Utilize somente para lacunas reais da
                    numeração fiscal.

                    A faixa não deve conter documentos
                    previamente utilizados.

                </div>

                <?php if ($selectedEnvironment === 'producao'): ?>

                    <div class="alert alert-danger">

                        <strong>Atenção:</strong>

                        esta inutilização será realizada no
                        ambiente de produção.

                    </div>

                <?php endif; ?>


                <form
                    method="post"
                    action="actions/nota-fiscal-inutilizar.php"
                >

                    <?= $csrf->field() ?>

                    <?php return_to_field(); ?>

                    <input
                        type="hidden"
                        name="ambiente"
                        value="<?= h($selectedEnvironment) ?>"
                    >

                    <input
                        type="hidden"
                        name="modelo"
                        value="<?= h($selectedModel) ?>"
                    >

                    <input
                        type="hidden"
                        name="configuracao_id"
                        value="<?= (int) $configuration['id'] ?>"
                    >

                    <input
                        type="hidden"
                        name="idempotency_key"
                        value="<?= h(bin2hex(random_bytes(32))) ?>"
                    >


                    <div class="form-row">

                        <div class="form-group">

                            <label
                                class="form-label"
                                for="inut-serie"
                            >
                                Série
                            </label>

                            <select
                                class="form-select form-control-os"
                                id="inut-serie"
                                name="serie"
                                required
                            >

                                <option value="">
                                    Selecione
                                </option>

                                <?php foreach ($series as $item): ?>

                                    <option
                                        value="<?= (int) ($item['serie'] ?? 0) ?>"
                                    >

                                        <?= (int) (
                                            $item['serie']
                                            ?? 0
                                        ) ?>

                                        · próximo

                                        <?= (int) (
                                            $item['proximo_numero']
                                            ?? 1
                                        ) ?>

                                    </option>

                                <?php endforeach; ?>

                            </select>

                        </div>


                        <div class="form-group">

                            <label
                                class="form-label"
                                for="inut-ano"
                            >
                                Ano
                            </label>

                            <input
                                class="form-control-os"
                                id="inut-ano"
                                type="number"
                                name="ano"
                                min="2006"
                                max="2099"
                                value="<?= h(date('Y')) ?>"
                                required
                            >

                        </div>

                    </div>


                    <div class="form-row">

                        <div class="form-group">

                            <label
                                class="form-label"
                                for="inut-inicio"
                            >
                                Número inicial
                            </label>

                            <input
                                class="form-control-os"
                                id="inut-inicio"
                                type="number"
                                name="numero_inicial"
                                min="1"
                                max="999999999"
                                required
                            >

                        </div>


                        <div class="form-group">

                            <label
                                class="form-label"
                                for="inut-fim"
                            >
                                Número final
                            </label>

                            <input
                                class="form-control-os"
                                id="inut-fim"
                                type="number"
                                name="numero_final"
                                min="1"
                                max="999999999"
                                required
                            >

                        </div>

                    </div>


                    <div class="form-group">

                        <label
                            class="form-label"
                            for="inut-justificativa"
                        >
                            Justificativa
                        </label>

                        <textarea
                            class="form-control-os"
                            id="inut-justificativa"
                            name="justificativa"
                            minlength="15"
                            maxlength="255"
                            required
                        ></textarea>

                    </div>


                    <button
                        class="btn btn-danger"
                        type="submit"
                    >
                        <i class="bi bi-slash-circle"></i>
                        Solicitar inutilização na SEFAZ
                    </button>

                </form>


                <?php if ($inutilizations !== []): ?>

                    <div class="table-responsive mt-3">

                        <table class="table align-middle">

                            <thead>

                                <tr>
                                    <th>Faixa</th>
                                    <th>Ano</th>
                                    <th>Status</th>
                                    <th>Retorno</th>
                                </tr>

                            </thead>

                            <tbody>

                                <?php foreach (
                                    $inutilizations
                                    as $item
                                ): ?>

                                    <tr>

                                        <td>

                                            <?= (int) (
                                                $item['serie']
                                                ?? 0
                                            ) ?>

                                            /

                                            <?= (int) (
                                                $item['numero_inicial']
                                                ?? 0
                                            ) ?>

                                            –

                                            <?= (int) (
                                                $item['numero_final']
                                                ?? 0
                                            ) ?>

                                        </td>

                                        <td>
                                            <?= (int) (
                                                $item['ano']
                                                ?? 0
                                            ) ?>
                                        </td>

                                        <td>
                                            <?= h(
                                                (string) (
                                                    $item['status']
                                                    ?? ''
                                                )
                                            ) ?>
                                        </td>

                                        <td>

                                            <?= h(
                                                trim(
                                                    (string) (
                                                        $item['cstat']
                                                        ?? ''
                                                    )
                                                    . ' '
                                                    . (string) (
                                                        $item['xmotivo']
                                                        ?? ''
                                                    )
                                                )
                                            ) ?>

                                        </td>

                                    </tr>

                                <?php endforeach; ?>

                            </tbody>

                        </table>

                    </div>

                <?php endif; ?>

            </div>

        </section>

    <?php endif; ?>

</div>