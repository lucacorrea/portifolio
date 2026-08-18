<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';
require_once dirname(__DIR__) . '/lib/spreadsheet.php';

$pageDefinition = [
    'title' => 'Importar planilha',
    'description' => 'Importação dos contemplados do Meu Primeiro Emprego para o banco de dados, com validação e prevenção de duplicidades.',
    'actions' => [],
    'modal' => ['title' => 'Importação'],
];

$dbReady = pe_db_ready();
$result = null;
$preview = null;
$message = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pe_action']) && in_array($_POST['pe_action'], ['validate_import', 'run_import'], true)) {
    try {
        pe_verify_csrf();
        if (!$dbReady) {
            throw new RuntimeException('Configure o banco de dados antes da importação.');
        }
        if (empty($_FILES['planilha']['tmp_name']) || !is_uploaded_file($_FILES['planilha']['tmp_name'])) {
            throw new InvalidArgumentException('Selecione uma planilha XLSX ou CSV.');
        }
        if ((int) $_FILES['planilha']['size'] > 8 * 1024 * 1024) {
            throw new InvalidArgumentException('A planilha deve ter no máximo 8 MB.');
        }
        $original = basename((string) $_FILES['planilha']['name']);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (!in_array($ext, ['xlsx', 'csv'], true)) {
            throw new InvalidArgumentException('Formato inválido. Envie XLSX ou CSV.');
        }

        $rawRows = pe_spreadsheet_rows($_FILES['planilha']['tmp_name'], $original);
        $preview = pe_prepare_import($rawRows);
        if ($_POST['pe_action'] === 'run_import') {
            $result = pe_import_prepared(pe_db(), $preview['rows'], $original);
            $message = ['type' => 'success', 'text' => 'Importação concluída: ' . $result['imported'] . ' novos, ' . $result['updated'] . ' atualizados e ' . count($result['errors']) . ' erros de gravação.'];
        } else {
            $message = ['type' => 'info', 'text' => 'Validação concluída. Nenhum dado foi gravado.'];
        }
    } catch (Throwable $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

ob_start();
?>
<section class="content-card pe-form-card">
    <?php if (!$dbReady): ?><?= pe_db_notice() ?><?php endif; ?>
    <?php if ($message): ?><div class="alert alert-<?= pe_h($message['type']) ?>"><?= pe_h($message['text']) ?></div><?php endif; ?>

    <div class="pe-form-header">
        <div><div class="card-kicker">Carga de dados</div><h2>Importar contemplados</h2><p>Compatível com a planilha enviada: NOME, DATA NASC., RESPONSAVEL, BAIRRO, ENDEREÇO, TELEFONE, CPF, IDADE e SETOR.</p></div>
    </div>

    <div class="pe-import-guide">
        <div><i class="bi bi-1-circle"></i><strong>Valide primeiro</strong><span>Confere cabeçalhos, datas, CPF e telefone sem gravar.</span></div>
        <div><i class="bi bi-2-circle"></i><strong>Revise os avisos</strong><span>CPF legado inconsistente é preservado, mas não entra como CPF validado.</span></div>
        <div><i class="bi bi-3-circle"></i><strong>Importe</strong><span>Registros existentes são atualizados por CPF ou chave de importação.</span></div>
    </div>

    <form method="post" enctype="multipart/form-data" class="row g-3 align-items-end mt-2" <?= !$dbReady ? 'inert' : '' ?>>
        <?= pe_csrf_field() ?>
        <div class="col-lg-8"><label class="form-label required">Planilha</label><input class="form-control" type="file" name="planilha" accept=".xlsx,.csv" required><small class="text-muted">Máximo 8 MB. XLSX requer a extensão PHP ZipArchive; CSV UTF-8 funciona como alternativa.</small></div>
        <div class="col-lg-4 d-flex gap-2"><button class="btn btn-outline-primary flex-fill" type="submit" name="pe_action" value="validate_import"><i class="bi bi-shield-check"></i> Validar</button><button class="btn btn-primary flex-fill" type="submit" name="pe_action" value="run_import" onclick="return confirm('Confirmar importação para o banco de dados?')"><i class="bi bi-database-add"></i> Importar</button></div>
    </form>

    <?php if ($preview): ?>
        <div class="row g-3 mt-4">
            <div class="col-md-4"><div class="pe-kpi"><span>Linhas válidas para processamento</span><strong><?= count($preview['rows']) ?></strong></div></div>
            <div class="col-md-4"><div class="pe-kpi"><span>Avisos de qualidade</span><strong><?= count($preview['warnings']) ?></strong></div></div>
            <div class="col-md-4"><div class="pe-kpi"><span>Colunas reconhecidas</span><strong><?= count($preview['columns']) ?></strong></div></div>
        </div>
        <?php if ($preview['warnings']): ?><div class="mt-4"><h3 class="h6">Avisos da planilha</h3><div class="table-responsive pe-table-scroll"><table class="table table-sm align-middle"><thead><tr><th>Linha</th><th>Aviso</th><th>Valor</th></tr></thead><tbody><?php foreach (array_slice($preview['warnings'], 0, 100) as $warning): ?><tr><td><?= (int)$warning['row'] ?></td><td><?= pe_h($warning['message']) ?></td><td><code><?= pe_h($warning['value']) ?></code></td></tr><?php endforeach; ?></tbody></table></div><?php if (count($preview['warnings']) > 100): ?><small class="text-muted">Exibindo os primeiros 100 avisos.</small><?php endif; ?></div><?php endif; ?>
    <?php endif; ?>

    <?php if ($result && $result['errors']): ?><div class="alert alert-warning mt-4"><strong>Erros de gravação:</strong><ul class="mb-0 mt-2"><?php foreach (array_slice($result['errors'],0,30) as $error): ?><li>Linha <?= (int)$error['row'] ?>: <?= pe_h($error['message']) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
</section>
<?php $pageCustomContent = (string) ob_get_clean();
