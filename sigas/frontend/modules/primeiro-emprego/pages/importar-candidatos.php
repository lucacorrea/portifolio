<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';
require_once dirname(__DIR__) . '/lib/spreadsheet.php';
require_once dirname(__DIR__) . '/lib/payment-pdf.php';
require_once dirname(__DIR__) . '/lib/waitlist-import.php';

$pageDefinition = [
    'title' => 'Importações',
    'description' => 'Importe candidatos, atualize a Lista de espera e concilie pagamentos oficiais do Meu Primeiro Emprego por PDF.',
    'actions' => [['label' => 'Ver candidatos', 'icon' => 'people', 'href' => 'primeiro-emprego/candidatos.php']],
    'demo' => false,
    'show_states' => false,
    'modal' => ['title' => 'Importação'],
];
$extraStyles = ['frontend/modules/primeiro-emprego/module.css'];
$extraScripts = ['frontend/modules/primeiro-emprego/payment-pdf-import.js'];

$baseDbReady = pe_db_ready() && pe_schema_ready();
$excelDbReady = $baseDbReady && pe_import_schema_ready();
$paymentDbReady = $baseDbReady && pe_payment_pdf_schema_ready();
$waitlistDbReady = $excelDbReady && pe_waitlist_schema_ready();

// Endpoint JSON da análise do PDF. O arquivo permanece no input do navegador porque a análise usa fetch.
if (($_GET['ajax'] ?? '') === 'payment_pdf_analyze') {
    header('Content-Type: application/json; charset=utf-8');
    $csrfChecked = false;
    try {
        pe_verify_csrf();
        $csrfChecked = true;
        if (!$paymentDbReady) {
            throw new RuntimeException('Execute database/primeiroEmprego/0006-primeiroEmprego-pagamentos-pdf.sql antes de usar a importação por PDF.');
        }
        if (empty($_FILES['pagamento_pdf'])) {
            throw new InvalidArgumentException('Selecione o PDF de pagamentos.');
        }

        $file = pe_payment_pdf_validate_upload($_FILES['pagamento_pdf']);
        $extracted = pe_payment_pdf_extract_text($file['tmp_name'], $_POST['texto_pdf'] ?? null);
        $parsed = pe_payment_pdf_parse_text($extracted['text']);
        $competence = trim((string) ($_POST['competencia_pagamento'] ?? ''));
        if ($competence === '') {
            $competence = (string) ($parsed['meta']['competencia_padrao'] ?? '');
        }
        $analysis = pe_payment_pdf_analyze(pe_db(), $parsed, $competence);

        echo json_encode([
            'success' => true,
            'csrf_token' => pe_csrf_token(),
            'source' => $extracted['source'],
            'file_hash' => $file['hash'],
            'analysis' => $analysis,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (Throwable $e) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'csrf_token' => $csrfChecked ? pe_csrf_token() : pe_csrf_token(),
            'message' => $e->getMessage(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
    exit;
}

$result = null;
$preview = null;
$paymentResult = null;
$waitlistPreview = null;
$waitlistResult = null;
$message = null;
$history = [];
$paymentHistory = [];
$waitlistHistory = [];
$currentAction = (string) ($_POST['pe_action'] ?? '');
$spreadsheetAction = in_array($currentAction, ['validate_import', 'run_import'], true);
$markContemplados = !$spreadsheetAction || !empty($_POST['mark_contemplados']);

// Fluxo XLSX/CSV existente.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pe_action']) && in_array($_POST['pe_action'], ['validate_import', 'run_import'], true)) {
    try {
        pe_verify_csrf();
        if (!$excelDbReady) {
            throw new RuntimeException('Execute a atualização do banco do Primeiro Emprego antes da importação por planilha.');
        }
        if (empty($_FILES['planilha']['tmp_name']) || !is_uploaded_file($_FILES['planilha']['tmp_name'])) {
            throw new InvalidArgumentException('Selecione uma planilha XLSX ou CSV.');
        }
        if ((int) ($_FILES['planilha']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload da planilha. Código: ' . (int) $_FILES['planilha']['error']);
        }
        if ((int) $_FILES['planilha']['size'] <= 0 || (int) $_FILES['planilha']['size'] > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('A planilha deve ter até 8 MB.');
        }

        $original = basename((string) $_FILES['planilha']['name']);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            throw new InvalidArgumentException('Formato inválido. Envie XLSX ou CSV.');
        }

        pe_validate_spreadsheet_upload($_FILES['planilha']['tmp_name'], $ext);
        $rawRows = pe_spreadsheet_rows($_FILES['planilha']['tmp_name'], $original);
        $preview = pe_prepare_import($rawRows);

        if ($_POST['pe_action'] === 'run_import') {
            $result = pe_import_prepared(pe_db(), $preview['rows'], $original, [
                'mark_contemplados' => $markContemplados,
                'file_hash' => hash_file('sha256', $_FILES['planilha']['tmp_name']) ?: null,
                'responsavel' => pe_current_user_label(),
            ]);
            $message = [
                'type' => count($result['errors']) === 0 ? 'success' : 'warning',
                'text' => 'Importação concluída: ' . $result['imported'] . ' candidato(s) incluído(s), ' . $result['updated'] . ' cadastro(s) da mesma carga atualizados e ' . $result['review_pending'] . ' encaminhado(s) para revisão.',
            ];
        } else {
            $message = [
                'type' => 'info',
                'text' => 'Validação concluída. Nenhum dado foi gravado. Pendências cadastrais não bloqueiam a importação; elas seguem para revisão.',
            ];
        }
    } catch (Throwable $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

// Importação específica da Lista de espera. Usa a mesma leitura XLSX/CSV, mas concilia por CPF e nunca rebaixa um Contemplado.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($currentAction, ['validate_waitlist', 'run_waitlist'], true)) {
    try {
        pe_verify_csrf();
        if (!$waitlistDbReady) {
            throw new RuntimeException('Execute database/primeiroEmprego/0007-primeiroEmprego-lista-espera.sql antes de usar a Lista de espera.');
        }
        if (empty($_FILES['lista_espera']['tmp_name']) || !is_uploaded_file($_FILES['lista_espera']['tmp_name'])) {
            throw new InvalidArgumentException('Selecione uma planilha XLSX ou CSV da Lista de espera.');
        }
        if ((int) ($_FILES['lista_espera']['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Falha no upload da Lista de espera. Código: ' . (int) $_FILES['lista_espera']['error']);
        }
        if ((int) $_FILES['lista_espera']['size'] <= 0 || (int) $_FILES['lista_espera']['size'] > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('A Lista de espera deve ter até 8 MB.');
        }

        $waitOriginal = basename((string) $_FILES['lista_espera']['name']);
        $waitExt = strtolower(pathinfo($waitOriginal, PATHINFO_EXTENSION));
        if (!in_array($waitExt, ['xlsx', 'csv'], true)) {
            throw new InvalidArgumentException('Formato inválido. Envie XLSX ou CSV.');
        }

        pe_validate_spreadsheet_upload($_FILES['lista_espera']['tmp_name'], $waitExt);
        $waitRawRows = pe_spreadsheet_rows($_FILES['lista_espera']['tmp_name'], $waitOriginal);
        $waitPrepared = pe_prepare_import($waitRawRows);
        $waitlistPreview = pe_waitlist_analyze(pe_db(), $waitPrepared['rows']);

        if ($currentAction === 'run_waitlist') {
            $waitlistResult = pe_waitlist_import(pe_db(), $waitPrepared['rows'], $waitOriginal, [
                'file_hash' => hash_file('sha256', $_FILES['lista_espera']['tmp_name']) ?: null,
                'responsavel' => pe_current_user_label(),
            ]);
            $message = [
                'type' => count($waitlistResult['errors']) > 0 || $waitlistResult['blocked'] > 0 ? 'warning' : 'success',
                'text' => 'Lista de espera #' . $waitlistResult['import_id'] . ' concluída: '
                    . $waitlistResult['imported'] . ' novo(s), '
                    . $waitlistResult['updated'] . ' cadastro(s) atualizado(s), '
                    . $waitlistResult['kept_contemplated'] . ' contemplado(s) preservado(s), '
                    . $waitlistResult['ignored_duplicates'] . ' linha(s) repetida(s) ignorada(s), '
                    . $waitlistResult['blocked'] . ' bloqueio(s) de segurança e '
                    . count($waitlistResult['errors']) . ' erro(s).',
            ];
        } else {
            $message = [
                'type' => $waitlistPreview['summary']['bloqueados'] > 0 ? 'warning' : 'info',
                'text' => 'Pré-validação da Lista de espera concluída. Nenhum dado foi gravado. Confira os vínculos por CPF e os bloqueios antes de confirmar.',
            ];
        }
    } catch (Throwable $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

// Conciliação final do PDF. O servidor revalida o arquivo, reextrai/reprocessa o texto e refaz os matches.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pe_action'] ?? '') === 'run_payment_pdf') {
    try {
        pe_verify_csrf();
        if (!$paymentDbReady) {
            throw new RuntimeException('Execute database/primeiroEmprego/0006-primeiroEmprego-pagamentos-pdf.sql antes de usar a importação por PDF.');
        }
        if (empty($_FILES['pagamento_pdf'])) {
            throw new InvalidArgumentException('Selecione o PDF de pagamentos.');
        }

        $file = pe_payment_pdf_validate_upload($_FILES['pagamento_pdf']);
        $extracted = pe_payment_pdf_extract_text($file['tmp_name'], $_POST['texto_pdf'] ?? null);
        $parsed = pe_payment_pdf_parse_text($extracted['text']);
        $competence = trim((string) ($_POST['competencia_pagamento'] ?? ''));
        if ($competence === '') {
            $competence = (string) ($parsed['meta']['competencia_padrao'] ?? '');
        }

        $paymentResult = pe_payment_pdf_apply(
            pe_db(),
            $parsed,
            $competence,
            $file,
            pe_current_user_label(),
            $extracted['source']
        );

        $message = [
            'type' => $paymentResult['erros'] > 0 || $paymentResult['conflitos_financeiros'] > 0 ? 'warning' : 'success',
            'text' => 'Conciliação #' . $paymentResult['import_id'] . ' concluída: '
                . $paymentResult['conciliados'] . ' novo(s) pagamento(s), '
                . $paymentResult['atualizados'] . ' bolsa(s) atualizada(s), '
                . $paymentResult['ja_conciliados'] . ' já conciliado(s), '
                . $paymentResult['nao_localizados'] . ' CPF(s) não localizado(s), '
                . $paymentResult['ambiguos'] . ' ambíguo(s) e '
                . $paymentResult['conflitos_financeiros'] . ' conflito(s) financeiro(s).',
        ];
    } catch (Throwable $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

if ($excelDbReady) {
    try {
        $history = pe_import_history(pe_db(), 8, 'candidatos');
    } catch (Throwable) {
        $history = [];
    }
}
if ($paymentDbReady) {
    try {
        $paymentHistory = pe_payment_pdf_history(pe_db(), 8);
    } catch (Throwable) {
        $paymentHistory = [];
    }
}
if ($waitlistDbReady) {
    try {
        $waitlistHistory = pe_waitlist_history(pe_db(), 8);
    } catch (Throwable) {
        $waitlistHistory = [];
    }
}

$defaultImportMode = in_array($currentAction, ['validate_waitlist', 'run_waitlist'], true)
    ? 'waitlist'
    : ($currentAction === 'run_payment_pdf' ? 'payment-pdf' : 'spreadsheet');

ob_start();
?>
<section class="content-card pe-form-card pe-import-hub" data-pe-import-hub data-pe-default-import-mode="<?= pe_h($defaultImportMode) ?>">
    <?php if ($message): ?><div class="alert alert-<?= pe_h($message['type']) ?>"><?= pe_h($message['text']) ?></div><?php endif; ?>

    <div class="pe-form-header">
        <div>
            <div class="card-kicker">Central de importações</div>
            <h2>Atualizar a base do Meu Primeiro Emprego</h2>
            <p>Cadastre ou atualize candidatos por planilha, organize a Lista de espera com conciliação segura por CPF e use o PDF oficial do Banco do Brasil para registrar pagamentos sem sobrescrever dados pessoais.</p>
        </div>
    </div>

    <div class="pe-import-mode-switch pe-no-print" role="tablist" aria-label="Tipo de importação">
        <button class="pe-import-mode is-active" type="button" data-pe-import-mode="spreadsheet" role="tab" aria-selected="true">
            <i class="bi bi-file-earmark-spreadsheet"></i><span><strong>Planilha de candidatos</strong><small>XLSX ou CSV</small></span>
        </button>
        <button class="pe-import-mode" type="button" data-pe-import-mode="waitlist" role="tab" aria-selected="false">
            <i class="bi bi-hourglass-split"></i><span><strong>Lista de espera</strong><small>XLSX ou CSV · atualiza por CPF</small></span>
        </button>
        <button class="pe-import-mode" type="button" data-pe-import-mode="payment-pdf" role="tab" aria-selected="false">
            <i class="bi bi-file-earmark-pdf"></i><span><strong>PDF de pagamentos</strong><small>Extrato do Banco do Brasil</small></span>
        </button>
    </div>

    <div data-pe-import-panel="spreadsheet">
        <?php if (!$excelDbReady): ?>
            <div class="alert alert-warning mt-3">
                <strong>Estrutura da planilha não pronta.</strong> Execute <code>database/primeiroEmprego/ATUALIZAR_HOSPEDAGEM_PRIMEIRO_EMPREGO.sql</code> no banco atual.
            </div>
        <?php endif; ?>

        <div class="pe-import-guide mt-3">
            <div><i class="bi bi-1-circle"></i><strong>Importar todos</strong><span>CPF ausente, inválido ou duplicado não impede a entrada do candidato.</span></div>
            <div><i class="bi bi-2-circle"></i><strong>Classificar</strong><span>O SIGAS separa CPF, telefone, nascimento ou cadastro para revisão.</span></div>
            <div><i class="bi bi-3-circle"></i><strong>Revisar depois</strong><span>Cada candidato mantém seu ID próprio e o histórico da revisão.</span></div>
        </div>

        <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end mt-2" <?= !$excelDbReady ? 'inert' : '' ?>>
            <?= pe_csrf_field() ?>
            <div class="col-lg-8">
                <label class="form-label required">Planilha Excel ou CSV</label>
                <input class="form-control" type="file" name="planilha" accept=".xlsx,.csv" required>
                <small class="text-muted">Máximo 8 MB. Você pode validar a lista antes de gravar.</small>
            </div>
            <div class="col-lg-4">
                <label class="form-check d-flex align-items-start gap-2 mb-2">
                    <input class="form-check-input mt-1" type="checkbox" name="mark_contemplados" value="1"<?= $markContemplados ? ' checked' : '' ?>>
                    <span><strong>Lista de contemplados</strong><small class="d-block text-muted">Mantém o status como “Contemplado”.</small></span>
                </label>
            </div>
            <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
                <button class="btn btn-outline-primary" type="submit" name="pe_action" value="validate_import"><i class="bi bi-shield-check"></i> Validar sem gravar</button>
                <button class="btn btn-primary" type="submit" name="pe_action" value="run_import" onclick="return confirm('Confirmar a inclusão dos candidatos da planilha? As pendências serão importadas e marcadas para revisão.')"><i class="bi bi-database-add"></i> Importar todos</button>
            </div>
        </form>

        <?php if ($preview): $s = $preview['summary']; ?>
            <div class="row g-3 mt-4">
                <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Total da lista</span><strong><?= (int) $s['total'] ?></strong></div></div>
                <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Sem pendência</span><strong><?= (int) $s['sem_pendencia'] ?></strong></div></div>
                <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Revisar CPF</span><strong><?= (int) $s['revisar_cpf'] ?></strong></div></div>
                <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Revisar telefone</span><strong><?= (int) $s['revisar_telefone'] ?></strong></div></div>
                <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Revisar nascimento</span><strong><?= (int) $s['revisar_nascimento'] ?></strong></div></div>
                <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Revisar cadastro</span><strong><?= (int) $s['revisar_cadastro'] ?></strong></div></div>
            </div>

            <div class="alert alert-light border mt-3 mb-0">
                <div class="d-flex flex-wrap gap-3 align-items-center">
                    <span><strong><?= (int) $s['pendentes_revisao'] ?></strong> candidato(s) com alguma pendência</span>
                    <span><strong><?= (int) $s['cpf_duplicados'] ?></strong> candidato(s) envolvidos em CPF duplicado</span>
                    <span><strong>0 bloqueados por validação cadastral</strong></span>
                </div>
            </div>

            <div class="mt-4">
                <h3 class="h6 mb-2">Prévia das primeiras linhas</h3>
                <div class="table-responsive pe-table-scroll">
                    <table class="table table-sm align-middle pe-candidate-review-table">
                        <thead><tr><th>Linha</th><th>Nome</th><th>CPF</th><th>Telefone</th><th>Setor</th><th>Classificação</th><th>Motivos</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($preview['rows'], 0, 50) as $row): ?>
                            <?php
                            $previewClass = '';
                            if (!empty($row['cpf_duplicado'])) $previewClass = 'pe-review-critical';
                            elseif (($row['revisao_status'] ?? '') === 'Revisar Cadastro') $previewClass = 'pe-review-multiple';
                            elseif (!empty($row['revisao_status'])) $previewClass = 'pe-review-warning';
                            ?>
                            <tr class="<?= pe_h($previewClass) ?>">
                                <td><?= (int) $row['row'] ?></td>
                                <td><strong><?= pe_h($row['nome']) ?></strong></td>
                                <td><?= pe_h(pe_format_cpf($row['cpf'] ?: $row['cpf_informado'] ?: '—')) ?></td>
                                <td><?= pe_h(pe_format_phone($row['telefone'] ?: '—')) ?></td>
                                <td><?= pe_h($row['setor'] ?: '—') ?></td>
                                <td><?php if (!empty($row['revisao_status'])): ?><span class="badge <?= !empty($row['cpf_duplicado']) ? 'text-bg-danger' : (($row['revisao_status'] === 'Revisar Cadastro') ? 'text-bg-warning' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle') ?>"><?= pe_h($row['revisao_status']) ?></span><?php else: ?><span class="badge text-bg-success">Sem pendência</span><?php endif; ?></td>
                                <td><small><?= pe_h(!empty($row['revisao_motivos']) ? implode(' · ', $row['revisao_motivos']) : '—') ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($result && $result['errors']): ?>
            <div class="alert alert-warning mt-4"><strong>Erros técnicos de banco:</strong><ul class="mb-0 mt-2"><?php foreach (array_slice($result['errors'], 0, 30) as $error): ?><li>Linha <?= (int) $error['row'] ?>: <?= pe_h($error['message']) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($history): ?>
            <div class="mt-5">
                <h3 class="h6 mb-3">Últimas importações de candidatos</h3>
                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Data</th><th>Arquivo</th><th>Total</th><th>Incluídos</th><th>Para revisão</th><th>Erros</th><th>Responsável</th></tr></thead><tbody><?php foreach ($history as $item): ?><tr><td><?= pe_h(date('d/m/Y H:i', strtotime((string) $item['criado_em']))) ?></td><td><?= pe_h($item['arquivo_nome']) ?></td><td><?= (int) $item['total_linhas'] ?></td><td><?= (int) $item['importados'] ?></td><td><?= (int) $item['pendentes_revisao'] ?></td><td><?= (int) $item['erros'] ?></td><td><?= pe_h($item['responsavel'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
            </div>
        <?php endif; ?>
    </div>

    <div data-pe-import-panel="waitlist" hidden>
        <?php if (!$waitlistDbReady): ?>
            <div class="alert alert-warning mt-3">
                <strong>Ative a Lista de espera.</strong> Execute <code>database/primeiroEmprego/0007-primeiroEmprego-lista-espera.sql</code>. As demais importações continuam disponíveis mesmo antes desta atualização.
            </div>
        <?php endif; ?>

        <div class="pe-import-guide pe-import-guide--waitlist mt-3">
            <div><i class="bi bi-person-vcard"></i><strong>Conciliar cadastro</strong><span>CPF válido e único atualiza o cadastro existente sem criar outra pessoa para o mesmo documento.</span></div>
            <div><i class="bi bi-hourglass-split"></i><strong>Entrar na espera</strong><span>Novos candidatos recebem o status “Lista de espera”. Quem já é Contemplado nunca é rebaixado.</span></div>
            <div><i class="bi bi-shield-exclamation"></i><strong>Bloquear conflitos</strong><span>CPF em múltiplos cadastros ou CPF ligado a nomes incompatíveis exige revisão manual.</span></div>
        </div>

        <form method="post" enctype="multipart/form-data" class="pe-waitlist-form mt-3" <?= !$waitlistDbReady ? 'inert' : '' ?>>
            <?= pe_csrf_field() ?>
            <div class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label required" for="peWaitlistFile">Planilha da Lista de espera</label>
                    <input class="form-control" id="peWaitlistFile" type="file" name="lista_espera" accept=".xlsx,.csv" required>
                    <small class="text-muted">Máximo 8 MB. Cabeçalho mínimo: NOME. Também reconhece CPF, DATA NASC., TELEFONE, BAIRRO, ENDEREÇO, RESPONSÁVEL e SETOR.</small>
                </div>
                <div class="col-lg-4">
                    <div class="pe-waitlist-rule-card">
                        <i class="bi bi-check2-circle"></i>
                        <div><strong>Atualização conservadora</strong><small>Campos vazios da planilha não apagam dados já existentes.</small></div>
                    </div>
                </div>
            </div>

            <div class="pe-waitlist-security mt-3">
                <i class="bi bi-shield-lock"></i>
                <div>
                    <strong>Regra da Lista de espera</strong>
                    <p class="mb-0">A planilha pode atualizar nome, nascimento, responsável, bairro, endereço e telefone quando o CPF identifica um único cadastro. O campo SETOR fica apenas no histórico da importação e não cria lotação antes da contemplação.</p>
                </div>
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
                <button class="btn btn-outline-primary" type="submit" name="pe_action" value="validate_waitlist"><i class="bi bi-search"></i> Validar Lista de espera</button>
                <button class="btn btn-primary" type="submit" name="pe_action" value="run_waitlist" onclick="return confirm('Confirmar a importação da Lista de espera? Cadastros localizados por CPF serão atualizados e contemplados existentes serão preservados.')"><i class="bi bi-hourglass-split"></i> Importar Lista de espera</button>
            </div>
        </form>

        <?php if ($waitlistPreview): $ws = $waitlistPreview['summary']; ?>
            <div class="pe-waitlist-analysis mt-4">
                <div class="pe-payment-analysis__head">
                    <div><div class="card-kicker">Pré-análise</div><h3>Conferência da Lista de espera</h3><p>O SIGAS prioriza os conflitos para que você revise antes de gravar.</p></div>
                    <span class="badge <?= $ws['bloqueados'] > 0 ? 'text-bg-warning' : 'text-bg-success' ?>"><?= (int) $ws['bloqueados'] ?> bloqueio(s)</span>
                </div>

                <div class="pe-payment-kpis pe-waitlist-kpis">
                    <div class="pe-payment-kpi"><span>Total</span><strong><?= (int) $ws['total'] ?></strong></div>
                    <div class="pe-payment-kpi"><span>Novos</span><strong><?= (int) ($ws['novos'] + $ws['novos_revisao']) ?></strong></div>
                    <div class="pe-payment-kpi"><span>Atualizar cadastro</span><strong><?= (int) $ws['atualizar'] ?></strong></div>
                    <div class="pe-payment-kpi"><span>Já contemplados</span><strong><?= (int) $ws['ja_contemplados'] ?></strong></div>
                    <div class="pe-payment-kpi"><span>Em revisão cadastral</span><strong><?= (int) $ws['pendentes_revisao'] ?></strong></div>
                    <div class="pe-payment-kpi"><span>Linhas repetidas</span><strong><?= (int) $ws['duplicados_ignorados'] ?></strong></div>
                    <div class="pe-payment-kpi"><span>CPF ambíguo</span><strong><?= (int) $ws['cpf_ambiguos'] ?></strong></div>
                    <div class="pe-payment-kpi"><span>Divergência/Conflito</span><strong><?= (int) ($ws['divergencias_nome'] + $ws['conflitos_cpf_lista']) ?></strong></div>
                </div>

                <div class="alert <?= $ws['bloqueados'] > 0 ? 'alert-warning' : 'alert-success' ?> mt-3 mb-0">
                    <?php if ($ws['bloqueados'] > 0): ?>
                        <strong><?= (int) $ws['bloqueados'] ?> linha(s) não serão aplicadas automaticamente.</strong> Corrija CPF duplicado entre pessoas diferentes, múltiplos cadastros no SIGAS ou divergência forte entre CPF e nome.
                    <?php else: ?>
                        <strong>Lista pronta para importação.</strong> Nenhum conflito de identidade foi encontrado na pré-análise.
                    <?php endif; ?>
                </div>

                <?php
                $waitPriority = ['conflito_cpf_lista' => 1, 'cpf_ambiguo' => 2, 'divergencia_nome' => 3, 'novo_revisao' => 4, 'duplicado_lista' => 5, 'atualizar' => 6, 'novo' => 7, 'ja_contemplado' => 8];
                $waitRows = $waitlistPreview['rows'];
                usort($waitRows, static fn(array $a, array $b): int => ($waitPriority[$a['waitlist_status']] ?? 99) <=> ($waitPriority[$b['waitlist_status']] ?? 99));
                ?>
                <div class="table-responsive pe-table-scroll mt-3">
                    <table class="table table-sm align-middle pe-waitlist-preview-table">
                        <thead><tr><th>Linha</th><th>Nome</th><th>CPF</th><th>Telefone</th><th>SIGAS</th><th>Resultado</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($waitRows, 0, 120) as $row): ?>
                            <?php
                            [$waitLabel, $waitBadge] = match ($row['waitlist_status']) {
                                'novo' => ['Novo na espera', 'success'],
                                'novo_revisao' => ['Novo + revisão', 'warning'],
                                'atualizar' => ['Atualizar cadastro', 'primary'],
                                'ja_contemplado' => ['Preservar contemplado', 'success'],
                                'duplicado_lista' => ['Linha repetida', 'secondary'],
                                'conflito_cpf_lista' => ['Conflito no arquivo', 'danger'],
                                'cpf_ambiguo' => ['CPF ambíguo', 'danger'],
                                'divergencia_nome' => ['Divergência de nome', 'danger'],
                                default => ['Revisar', 'secondary'],
                            };
                            ?>
                            <tr class="pe-waitlist-row pe-waitlist-row--<?= pe_h($row['waitlist_status']) ?>">
                                <td><?= (int) $row['row'] ?></td>
                                <td><strong><?= pe_h($row['nome']) ?></strong><?php if (!empty($row['revisao_status'])): ?><small class="d-block text-warning-emphasis"><?= pe_h($row['revisao_status']) ?></small><?php endif; ?></td>
                                <td><?= pe_h(pe_format_cpf($row['cpf'] ?: $row['cpf_informado'] ?: '—')) ?></td>
                                <td><?= pe_h(pe_format_phone($row['telefone'] ?: '—')) ?></td>
                                <td><?php if (!empty($row['candidate_id'])): ?><strong>#<?= (int) $row['candidate_id'] ?> · <?= pe_h($row['candidate_name']) ?></strong><small class="d-block text-muted"><?= pe_h($row['candidate_status'] ?: '—') ?></small><?php else: ?>—<?php endif; ?></td>
                                <td><span class="badge text-bg-<?= pe_h($waitBadge) ?>"><?= pe_h($waitLabel) ?></span><small class="d-block mt-1 text-muted"><?= pe_h($row['waitlist_message']) ?></small></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($waitRows) > 120): ?><small class="text-muted">Exibindo 120 de <?= count($waitRows) ?> linhas, priorizando conflitos e pendências.</small><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($waitlistResult && $waitlistResult['errors']): ?>
            <div class="alert alert-warning mt-4"><strong>Erros técnicos:</strong><ul class="mb-0 mt-2"><?php foreach (array_slice($waitlistResult['errors'], 0, 30) as $error): ?><li>Linha <?= (int) $error['row'] ?>: <?= pe_h($error['message']) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($waitlistHistory): ?>
            <div class="mt-5">
                <h3 class="h6 mb-3">Últimas importações da Lista de espera</h3>
                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Data</th><th>Arquivo</th><th>Total</th><th>Novos</th><th>Atualizados/Preservados</th><th>Bloqueados</th><th>Revisão</th><th>Erros</th><th>Responsável</th></tr></thead><tbody><?php foreach ($waitlistHistory as $item): ?><tr><td><?= pe_h(date('d/m/Y H:i', strtotime((string) $item['criado_em']))) ?></td><td><?= pe_h($item['arquivo_nome']) ?></td><td><?= (int) $item['total_linhas'] ?></td><td><?= (int) $item['importados'] ?></td><td><?= (int) $item['atualizados'] ?></td><td><?= (int) $item['bloqueados'] ?></td><td><?= (int) $item['pendentes_revisao'] ?></td><td><?= (int) $item['erros'] ?></td><td><?= pe_h($item['responsavel'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
            </div>
        <?php endif; ?>
    </div>

    <div data-pe-import-panel="payment-pdf" hidden>
        <?php if (!$paymentDbReady): ?>
            <div class="alert alert-warning mt-3">
                <strong>Ative a conciliação por PDF.</strong> Execute <code>database/primeiroEmprego/0006-primeiroEmprego-pagamentos-pdf.sql</code> ou o arquivo consolidado de atualização.
            </div>
        <?php endif; ?>

        <div class="pe-import-guide pe-import-guide--payment mt-3">
            <div><i class="bi bi-file-earmark-check"></i><strong>Extrair e conferir</strong><span>O sistema lê todas as páginas e exige que quantidade e valor total confiram com o resumo do PDF.</span></div>
            <div><i class="bi bi-person-check"></i><strong>Conciliar por CPF</strong><span>Somente CPF válido e único no SIGAS é aplicado automaticamente. Nome é usado como conferência.</span></div>
            <div><i class="bi bi-shield-lock"></i><strong>Sem sobrescrever cadastro</strong><span>Telefone, endereço, nascimento, RG e outros dados pessoais não são alterados pelo PDF.</span></div>
        </div>

        <form method="post" enctype="multipart/form-data" class="pe-payment-import-form mt-3" data-pe-payment-pdf-form <?= !$paymentDbReady ? 'inert' : '' ?>>
            <?= pe_csrf_field() ?>
            <input type="hidden" name="pe_action" value="run_payment_pdf">
            <textarea name="texto_pdf" data-pe-payment-pdf-text hidden></textarea>

            <div class="row g-3 align-items-end">
                <div class="col-lg-8">
                    <label class="form-label required" for="pePaymentPdfFile">Extrato de Lista de Pagamentos</label>
                    <input class="form-control" id="pePaymentPdfFile" type="file" name="pagamento_pdf" accept="application/pdf,.pdf" data-pe-payment-pdf-file required>
                    <small class="text-muted">PDF do Banco do Brasil, máximo 12 MB. O texto é extraído no navegador e revalidado no servidor quando houver suporte.</small>
                </div>
                <div class="col-lg-4">
                    <label class="form-label required" for="pePaymentCompetence">Competência da bolsa</label>
                    <input class="form-control" id="pePaymentCompetence" type="month" name="competencia_pagamento" data-pe-payment-competence required>
                    <small class="text-muted">Preenchida automaticamente pela data do pagamento; confirme antes de aplicar.</small>
                </div>
            </div>

            <div class="pe-payment-security-note mt-3">
                <i class="bi bi-info-circle"></i>
                <div><strong>Regra de segurança</strong><p class="mb-0">O PDF só pode marcar o candidato como Contemplado e registrar/conciliar a bolsa. Se já existir pagamento com valor ou data incompatível, o SIGAS cria um conflito e não sobrescreve o financeiro.</p></div>
            </div>

            <div class="d-flex flex-wrap gap-2 justify-content-end mt-3">
                <button class="btn btn-outline-primary" type="button" data-pe-payment-analyze><i class="bi bi-search"></i> Analisar PDF</button>
                <button class="btn btn-primary" type="submit" data-pe-payment-apply disabled><i class="bi bi-bank"></i> Confirmar conciliação</button>
            </div>
        </form>

        <div class="pe-payment-analysis mt-4" data-pe-payment-analysis hidden>
            <div class="pe-payment-analysis__head">
                <div><div class="card-kicker">Pré-análise</div><h3>Conferência do extrato</h3><p data-pe-payment-meta>—</p></div>
                <span class="badge text-bg-light border" data-pe-payment-source>—</span>
            </div>
            <div class="pe-payment-kpis" data-pe-payment-kpis></div>
            <div class="alert alert-light border mt-3 mb-0" data-pe-payment-warning></div>
            <div class="table-responsive pe-table-scroll mt-3">
                <table class="table table-sm align-middle pe-payment-preview-table">
                    <thead><tr><th>N IDENT.</th><th>CPF</th><th>Nome no banco</th><th>Valor</th><th>SIGAS</th><th>Resultado</th></tr></thead>
                    <tbody data-pe-payment-rows></tbody>
                </table>
            </div>
            <small class="text-muted" data-pe-payment-limit-note></small>
        </div>

        <?php if ($paymentResult && $paymentResult['errors_list']): ?>
            <div class="alert alert-warning mt-4"><strong>Falhas técnicas durante a conciliação:</strong><ul class="mb-0 mt-2"><?php foreach (array_slice($paymentResult['errors_list'], 0, 30) as $error): ?><li>N IDENT. <?= pe_h($error['n_ident']) ?>: <?= pe_h($error['message']) ?></li><?php endforeach; ?></ul></div>
        <?php endif; ?>

        <?php if ($paymentHistory): ?>
            <div class="mt-5">
                <h3 class="h6 mb-3">Últimas conciliações de pagamento</h3>
                <div class="table-responsive"><table class="table table-sm align-middle"><thead><tr><th>Data</th><th>Arquivo</th><th>Convênio/Lista</th><th>Competência</th><th>Total</th><th>Novos</th><th>Atualizados</th><th>Não localizados</th><th>Conflitos</th><th>Responsável</th></tr></thead><tbody><?php foreach ($paymentHistory as $item): ?><tr><td><?= pe_h(date('d/m/Y H:i', strtotime((string) $item['criado_em']))) ?></td><td><?= pe_h($item['arquivo_nome']) ?></td><td><?= pe_h(($item['convenio_numero'] ?: '—') . ' / ' . ($item['lista_numero'] ?: '—')) ?></td><td><?= pe_h($item['competencia']) ?></td><td><?= (int) $item['total_pagamentos'] ?><small class="d-block text-muted">R$ <?= number_format((float) $item['valor_total'], 2, ',', '.') ?></small></td><td><?= (int) $item['conciliados'] ?></td><td><?= (int) $item['atualizados'] ?></td><td><?= (int) $item['nao_localizados'] ?></td><td><?= (int) $item['conflitos_financeiros'] ?></td><td><?= pe_h($item['responsavel'] ?: '—') ?></td></tr><?php endforeach; ?></tbody></table></div>
            </div>
        <?php endif; ?>
    </div>
</section>
<?php $pageCustomContent = (string) ob_get_clean();
