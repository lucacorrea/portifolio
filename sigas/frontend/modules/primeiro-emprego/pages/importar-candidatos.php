<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';
require_once dirname(__DIR__) . '/lib/spreadsheet.php';

$pageDefinition = [
    'title' => 'Importar candidatos',
    'description' => 'Importação integral da lista do Meu Primeiro Emprego com classificação automática das pendências cadastrais.',
    'actions' => [['label' => 'Ver candidatos', 'icon' => 'people', 'href' => 'primeiro-emprego/candidatos.php']],
    'demo' => false,
    'show_states' => false,
    'modal' => ['title' => 'Importação'],
];

$dbReady = pe_db_ready() && pe_schema_ready() && pe_import_schema_ready();
$result = null;
$preview = null;
$message = null;
$history = [];
$markContemplados = !isset($_POST['pe_action']) || !empty($_POST['mark_contemplados']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pe_action']) && in_array($_POST['pe_action'], ['validate_import', 'run_import'], true)) {
    try {
        pe_verify_csrf();
        if (!$dbReady) {
            throw new RuntimeException('Execute a atualização do banco do Primeiro Emprego antes da importação.');
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
                'text' => 'Importação concluída: ' . $result['imported'] . ' candidato(s) incluído(s), ' . $result['updated'] . ' cadastro(s) já existentes da mesma carga atualizados, ' . $result['review_pending'] . ' encaminhado(s) para revisão, 0 bloqueados e ' . count($result['errors']) . ' erro(s) de banco.',
            ];
        } else {
            $message = [
                'type' => 'info',
                'text' => 'Validação concluída. Nenhum dado foi gravado. CPF ausente, inválido ou duplicado e outros problemas cadastrais não bloqueiam a importação; eles geram fila de revisão.',
            ];
        }
    } catch (Throwable $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

if ($dbReady) {
    try {
        $history = pe_import_history(pe_db(), 8);
    } catch (Throwable $e) {
        $history = [];
    }
}

ob_start();
?>
<section class="content-card pe-form-card">
    <?php if (!$dbReady): ?>
        <div class="alert alert-warning mb-3">
            <strong>Atualização necessária.</strong>
            Como o banco já está na hospedagem, execute apenas <code>database/primeiroEmprego/0002-primeiroEmprego-operacional.sql</code> antes de usar esta versão.
        </div>
    <?php endif; ?>
    <?php if ($message): ?><div class="alert alert-<?= pe_h($message['type']) ?>"><?= pe_h($message['text']) ?></div><?php endif; ?>

    <div class="pe-form-header">
        <div>
            <div class="card-kicker">Carga de candidatos</div>
            <h2>Importar lista do Meu Primeiro Emprego</h2>
            <p>Todos os candidatos são preservados. A qualidade dos dados é tratada em uma fila separada de revisão, sem impedir o cadastro.</p>
        </div>
    </div>

    <div class="pe-import-guide">
        <div><i class="bi bi-1-circle"></i><strong>Importar todos</strong><span>CPF ausente, inválido ou duplicado não impede a entrada do candidato.</span></div>
        <div><i class="bi bi-2-circle"></i><strong>Classificar</strong><span>O sistema separa Revisar CPF, Telefone, Data de Nascimento ou Cadastro.</span></div>
        <div><i class="bi bi-3-circle"></i><strong>Revisar depois</strong><span>Cada candidato recebe ID próprio e nunca é sobrescrito por causa do CPF.</span></div>
    </div>

    <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end mt-2" <?= !$dbReady ? 'inert' : '' ?>>
        <?= pe_csrf_field() ?>
        <div class="col-lg-8">
            <label class="form-label required">Planilha Excel ou CSV</label>
            <input class="form-control" type="file" name="planilha" accept=".xlsx,.csv" required>
            <small class="text-muted">Máximo 8 MB. Antes da gravação você pode validar e conferir a distribuição das pendências.</small>
        </div>
        <div class="col-lg-4">
            <label class="form-check d-flex align-items-start gap-2 mb-2">
                <input class="form-check-input mt-1" type="checkbox" name="mark_contemplados" value="1"<?= $markContemplados ? ' checked' : '' ?>>
                <span><strong>Lista de contemplados</strong><small class="d-block text-muted">Mantém o status do programa como “Contemplado”, independentemente da revisão cadastral.</small></span>
            </label>
        </div>
        <div class="col-12 d-flex flex-wrap gap-2 justify-content-end">
            <button class="btn btn-outline-primary" type="submit" name="pe_action" value="validate_import"><i class="bi bi-shield-check"></i> Validar sem gravar</button>
            <button class="btn btn-primary" type="submit" name="pe_action" value="run_import" onclick="return confirm('Confirmar a inclusão de todos os candidatos da planilha? Pendências cadastrais serão importadas e marcadas para revisão.')"><i class="bi bi-database-add"></i> Importar todos</button>
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
                        if (!empty($row['cpf_duplicado'])) {
                            $previewClass = 'pe-review-critical';
                        } elseif (($row['revisao_status'] ?? '') === 'Revisar Cadastro') {
                            $previewClass = 'pe-review-multiple';
                        } elseif (!empty($row['revisao_status'])) {
                            $previewClass = 'pe-review-warning';
                        }
                        ?>
                        <tr class="<?= pe_h($previewClass) ?>">
                            <td><?= (int) $row['row'] ?></td>
                            <td><strong><?= pe_h($row['nome']) ?></strong></td>
                            <td><?= pe_h(pe_format_cpf($row['cpf'] ?: $row['cpf_informado'] ?: '—')) ?></td>
                            <td><?= pe_h(pe_format_phone($row['telefone'] ?: '—')) ?></td>
                            <td><?= pe_h($row['setor'] ?: '—') ?></td>
                            <td>
                                <?php if (!empty($row['revisao_status'])): ?>
                                    <span class="badge <?= !empty($row['cpf_duplicado']) ? 'text-bg-danger' : (($row['revisao_status'] === 'Revisar Cadastro') ? 'text-bg-warning' : 'bg-warning-subtle text-warning-emphasis border border-warning-subtle') ?>"><?= pe_h($row['revisao_status']) ?></span>
                                <?php else: ?>
                                    <span class="badge text-bg-success">Sem pendência</span>
                                <?php endif; ?>
                            </td>
                            <td><small><?= pe_h(!empty($row['revisao_motivos']) ? implode(' · ', $row['revisao_motivos']) : '—') ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if ($preview['warnings']): ?>
            <div class="mt-4">
                <h3 class="h6">Pendências detectadas</h3>
                <div class="table-responsive pe-table-scroll">
                    <table class="table table-sm align-middle">
                        <thead><tr><th>Linha</th><th>Pendência</th><th>Destino</th></tr></thead>
                        <tbody>
                        <?php foreach (array_slice($preview['warnings'], 0, 180) as $warning): ?>
                            <tr><td><?= (int) $warning['row'] ?></td><td><?= pe_h($warning['message']) ?></td><td><span class="badge text-bg-light border"><?= pe_h($warning['value']) ?></span></td></tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (count($preview['warnings']) > 180): ?><small class="text-muted">Exibindo as primeiras 180 pendências.</small><?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($result && $result['errors']): ?>
        <div class="alert alert-warning mt-4">
            <strong>Erros técnicos de banco:</strong>
            <p class="mb-2">Esses erros não são validações de CPF/telefone/data. São falhas que realmente impediram a gravação da linha e precisam ser corrigidas.</p>
            <ul class="mb-0"><?php foreach (array_slice($result['errors'], 0, 30) as $error): ?><li>Linha <?= (int) $error['row'] ?>: <?= pe_h($error['message']) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($history): ?>
        <div class="mt-5">
            <h3 class="h6 mb-3">Últimas importações</h3>
            <div class="table-responsive">
                <table class="table table-sm align-middle">
                    <thead><tr><th>Data</th><th>Arquivo</th><th>Total</th><th>Incluídos</th><th>Para revisão</th><th>Bloqueados</th><th>Erros</th><th>Responsável</th></tr></thead>
                    <tbody><?php foreach ($history as $item): ?><tr>
                        <td><?= pe_h(date('d/m/Y H:i', strtotime((string) $item['criado_em']))) ?></td>
                        <td><?= pe_h($item['arquivo_nome']) ?></td>
                        <td><?= (int) $item['total_linhas'] ?></td>
                        <td><?= (int) $item['importados'] ?></td>
                        <td><?= (int) $item['pendentes_revisao'] ?></td>
                        <td><?= (int) $item['bloqueados'] ?></td>
                        <td><?= (int) $item['erros'] ?></td>
                        <td><?= pe_h($item['responsavel'] ?: '—') ?></td>
                    </tr><?php endforeach; ?></tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</section>
<?php $pageCustomContent = (string) ob_get_clean();
