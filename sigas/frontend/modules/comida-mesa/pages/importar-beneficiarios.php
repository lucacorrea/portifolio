<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Exceptions\AuthorizationException;

require_once dirname(__DIR__) . '/lib/import.php';

if (!cm_can('comida_mesa.importar') && !cm_can('comida_mesa.cadastrar')) {
    throw new AuthorizationException('Acesso negado.');
}

$pageDefinition = [
    'title' => 'Importar beneficiários',
    'description' => 'Valide e importe famílias do Comida na Mesa por Excel ou CSV com controle de duplicidades e fila de revisão.',
    'actions' => [
        ['label' => 'Baixar modelo Excel', 'icon' => 'file-earmark-arrow-down', 'primary' => true, 'href' => 'comida-mesa/modelo-importacao.php'],
        ['label' => 'Beneficiários', 'icon' => 'people', 'href' => 'comida-mesa/beneficiarios.php'],
    ],
    'demo' => false,
    'show_states' => false,
];

$pdo = cm_db();
$schemaReady = cm_import_schema_ready($pdo);
$poles = cm_app()['repository']->listActivePoles();
$history = $schemaReady ? cm_import_history($pdo, 15) : [];
$preview = null;
$result = null;
$message = null;

$options = [
    'polo_padrao_id' => isset($_POST['polo_padrao_id']) && (int) $_POST['polo_padrao_id'] > 0 ? (int) $_POST['polo_padrao_id'] : null,
    'status_padrao' => trim((string) ($_POST['status_padrao'] ?? 'em_analise')),
    'prioridade_padrao' => trim((string) ($_POST['prioridade_padrao'] ?? 'normal')),
    'zona_padrao' => trim((string) ($_POST['zona_padrao'] ?? '')),
    'atualizar_existentes' => isset($_POST['atualizar_existentes']) && $_POST['atualizar_existentes'] === '1',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Csrf::validateAndRotate($_POST['_csrf'] ?? null, 'comida_mesa_importar')) {
            throw new RuntimeException('Sessão de segurança expirada. Atualize a página e tente novamente.');
        }
        if (!isset($_FILES['planilha']) || !is_array($_FILES['planilha'])) {
            throw new InvalidArgumentException('Selecione uma planilha para continuar.');
        }
        $file = $_FILES['planilha'];
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('O upload da planilha não foi concluído. Código: ' . (int) ($file['error'] ?? -1));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');
        $name = basename((string) ($file['name'] ?? 'planilha.xlsx'));
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        cm_import_validate_upload($tmp, $extension);
        $rows = cm_import_spreadsheet_rows($tmp, $name);
        $preview = cm_import_prepare($pdo, $rows, $options);

        $action = (string) ($_POST['cm_action'] ?? 'validate');
        if ($action === 'import') {
            if (!$schemaReady) {
                throw new RuntimeException('Execute a migration 20260819_004_comida_mesa_importacao.sql antes de importar.');
            }
            $hash = hash_file('sha256', $tmp) ?: '';
            $result = cm_import_execute($pdo, $preview['rows'], $name, $hash, $options);
            $counts = $result['counts'];
            $message = [
                'type' => $counts['erros'] > 0 ? 'warning' : 'success',
                'text' => sprintf(
                    'Importação concluída: %d novo(s), %d atualizado(s), %d ignorado(s), %d em revisão e %d erro(s).',
                    $counts['novos'], $counts['atualizados'], $counts['ignorados'], $counts['revisar'], $counts['erros']
                ),
            ];
            $history = cm_import_history($pdo, 15);
        } else {
            $message = ['type' => 'info', 'text' => 'Validação concluída. Nenhum dado foi gravado no cadastro de beneficiários.'];
        }
    } catch (Throwable $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

$flattenIssues = static function (array $row): string {
    $items = [];
    foreach (($row['issues'] ?? []) as $messages) {
        foreach ($messages as $message) $items[] = (string) $message;
    }
    return implode(' · ', $items);
};

ob_start();
?>
<section class="content-card cm-list-card cm-import-page">
    <?php cm_list_header(
        'Carga de famílias',
        'Importar beneficiários do Comida na Mesa',
        'A planilha é validada antes da gravação. Linhas inconsistentes não são descartadas: ficam registradas como revisão da importação.'
    ); ?>

    <?php if (!$schemaReady): ?>
        <div class="alert alert-warning mt-3">
            <strong>Importação ainda não instalada no banco.</strong><br>
            Execute <code>database/migrations/20260819_004_comida_mesa_importacao.sql</code>. A validação da planilha pode ser usada, mas a gravação ficará bloqueada até a migration ser executada.
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-<?= cm_h($message['type']) ?> mt-3 mb-0"><?= cm_h($message['text']) ?></div>
    <?php endif; ?>

    <div class="cm-import-steps">
        <article><span>1</span><div><strong>Enviar planilha</strong><small>XLSX ou CSV, até 10 MB.</small></div></article>
        <article><span>2</span><div><strong>Validar</strong><small>CPF, telefone, endereço, polo e duplicidades.</small></div></article>
        <article><span>3</span><div><strong>Importar</strong><small>Somente registros seguros entram no cadastro oficial.</small></div></article>
        <article><span>4</span><div><strong>Revisar</strong><small>Linhas inconsistentes permanecem no histórico da carga.</small></div></article>
    </div>

    <form method="post" enctype="multipart/form-data" class="cm-import-form mt-3" action="comida-mesa/importar-beneficiarios.php">
        <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_importar')) ?>">
        <div class="cm-import-upload">
            <label>
                <span>Planilha Excel ou CSV *</span>
                <input class="form-control" type="file" name="planilha" accept=".xlsx,.csv" required>
                <small>O sistema reconhece automaticamente os cabeçalhos mais comuns. Use o modelo para obter o melhor resultado.</small>
            </label>
            <a class="btn btn-light" href="comida-mesa/modelo-importacao.php"><i class="bi bi-file-earmark-excel"></i> Modelo Excel</a>
        </div>

        <div class="cm-import-defaults">
            <label><span>Polo padrão</span><select class="form-select" name="polo_padrao_id"><option value="">Não aplicar</option><?php foreach ($poles as $pole): ?><option value="<?= (int) $pole['id'] ?>"<?= cm_selected($options['polo_padrao_id'], $pole['id']) ?>><?= cm_h($pole['nome']) ?></option><?php endforeach; ?></select><small>Usado somente quando a planilha não informar polo.</small></label>
            <label><span>Situação padrão</span><select class="form-select" name="status_padrao"><option value="em_analise"<?= cm_selected($options['status_padrao'],'em_analise') ?>>Em análise</option><option value="ativa"<?= cm_selected($options['status_padrao'],'ativa') ?>>Beneficiária ativa</option><option value="lista_espera"<?= cm_selected($options['status_padrao'],'lista_espera') ?>>Lista de espera</option></select><small>Para linhas sem coluna de situação.</small></label>
            <label><span>Prioridade padrão</span><select class="form-select" name="prioridade_padrao"><option value="normal"<?= cm_selected($options['prioridade_padrao'],'normal') ?>>Normal</option><option value="alta"<?= cm_selected($options['prioridade_padrao'],'alta') ?>>Alta</option><option value="baixa"<?= cm_selected($options['prioridade_padrao'],'baixa') ?>>Baixa</option></select></label>
            <label><span>Zona padrão</span><select class="form-select" name="zona_padrao"><option value="">Detectar automaticamente</option><option value="urbana"<?= cm_selected($options['zona_padrao'],'urbana') ?>>Urbana</option><option value="rural"<?= cm_selected($options['zona_padrao'],'rural') ?>>Rural</option></select></label>
        </div>

        <label class="cm-import-check">
            <input class="form-check-input" type="checkbox" name="atualizar_existentes" value="1"<?= $options['atualizar_existentes'] ? ' checked' : '' ?>>
            <span><strong>Atualizar cadastros já existentes pelo CPF</strong><small>Quando desmarcado, CPFs já cadastrados são ignorados. Quando marcado, somente campos presentes na planilha substituem os dados existentes.</small></span>
        </label>

        <div class="cm-import-actions">
            <button class="btn btn-light" type="submit" name="cm_action" value="validate"><i class="bi bi-shield-check"></i> Validar sem gravar</button>
            <button class="btn btn-primary" type="submit" name="cm_action" value="import"<?= !$schemaReady ? ' disabled' : '' ?> onclick="return confirm('Confirmar a importação das linhas válidas para o Comida na Mesa? As linhas com pendência ficarão registradas para revisão.')"><i class="bi bi-database-add"></i> Importar linhas válidas</button>
        </div>
    </form>

    <?php if ($preview): $s = $preview['summary']; ?>
        <?php cm_metrics([
            ['label'=>'Linhas identificadas','value'=>$s['total'],'hint'=>'Registros úteis','tone'=>'neutral'],
            ['label'=>'Novos','value'=>$s['novos'],'hint'=>'Prontos para cadastrar','tone'=>'success'],
            ['label'=>'Atualizar','value'=>$s['atualizar'],'hint'=>'CPF já existente','tone'=>'info'],
            ['label'=>'Ignorar','value'=>$s['ignorar'],'hint'=>'Existentes sem atualização','tone'=>'neutral'],
            ['label'=>'Revisar','value'=>$s['revisar'],'hint'=>'Não entram no cadastro oficial','tone'=>'warning'],
            ['label'=>'CPF inconsistente','value'=>$s['cpf_invalidos'],'hint'=>'Ausente ou inválido','tone'=>'danger'],
            ['label'=>'Telefone inconsistente','value'=>$s['telefone_invalido'],'hint'=>'Ausente ou fora do padrão','tone'=>'warning'],
            ['label'=>'Polo pendente','value'=>$s['polo_pendente'],'hint'=>'Obrigatório para status ativo','tone'=>'warning'],
        ]); ?>

        <div class="cm-import-note">
            <i class="bi bi-shield-lock"></i>
            <div><strong>Regra de integridade</strong><span>O cadastro central <code>pessoas</code> exige CPF válido e único. Linhas sem CPF válido são preservadas no histórico da importação para revisão, mas não recebem CPF artificial nem sobrescrevem outra pessoa.</span></div>
        </div>

        <div class="cm-table-shell mt-3">
            <div class="cm-table-toolbar"><div><h3>Prévia da planilha</h3><p>Exibindo as primeiras <?= min(50, count($preview['rows'])) ?> linhas classificadas.</p></div><span><i class="bi bi-palette"></i> A classificação define o tratamento da linha</span></div>
            <div class="table-responsive">
                <table class="cm-data-table cm-import-table">
                    <thead><tr><th>Linha</th><th>Responsável</th><th>CPF</th><th>Telefone</th><th>Localidade</th><th>Polo</th><th>Tratamento</th><th>Motivo</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($preview['rows'], 0, 50) as $row): $d=$row['data']; ?>
                        <?php $tone = $row['action']==='Novo'?'success':($row['action']==='Atualizar'?'info':($row['action']==='Revisar'?'warning':'muted')); ?>
                        <tr>
                            <td><?= (int) $row['row'] ?></td>
                            <td><strong><?= cm_h($d['nome']) ?></strong><small class="d-block text-muted"><?= cm_h($row['classification']) ?></small></td>
                            <td><?= cm_h($d['cpf'] !== '' ? cm_format_cpf($d['cpf']) : ($d['cpf_informado'] ?: '—')) ?></td>
                            <td><?= cm_h($d['telefone'] ?: '—') ?></td>
                            <td><?= cm_h(($d['zona'] ?: '—') . ($d['bairro'] ? ' · '.$d['bairro'] : '') . ($d['comunidade'] ? ' · '.$d['comunidade'] : '')) ?></td>
                            <td><?= cm_h($d['polo_informado'] ?: ($d['polo_id'] ? 'Polo #'.$d['polo_id'] : '—')) ?></td>
                            <td><span class="cm-status cm-status--<?= cm_h($tone) ?>"><?= cm_h($row['action']) ?></span></td>
                            <td class="cm-import-reason"><?= cm_h($flattenIssues($row) ?: 'Sem pendências') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($result && !empty($result['errors'])): ?>
        <div class="alert alert-warning mt-3">
            <strong>Erros durante a gravação</strong>
            <ul class="mb-0 mt-2"><?php foreach (array_slice($result['errors'], 0, 30) as $error): ?><li>Linha <?= (int) $error['row'] ?>: <?= cm_h($error['message']) ?></li><?php endforeach; ?></ul>
        </div>
    <?php endif; ?>

    <?php if ($history): ?>
        <div class="cm-table-shell mt-4">
            <div class="cm-table-toolbar"><div><h3>Histórico de importações</h3><p>Últimas cargas processadas no módulo.</p></div></div>
            <div class="table-responsive"><table class="cm-data-table"><thead><tr><th>Data</th><th>Arquivo</th><th>Total</th><th>Novos</th><th>Atualizados</th><th>Ignorados</th><th>Revisar</th><th>Erros</th><th>Responsável</th></tr></thead><tbody>
            <?php foreach ($history as $item): ?><tr>
                <td><?= cm_h(cm_date($item['criado_em'], true)) ?></td>
                <td><strong><?= cm_h($item['arquivo_nome']) ?></strong><small class="d-block text-muted"><?= cm_h($item['status']) ?></small></td>
                <td><?= (int) $item['total_linhas'] ?></td>
                <td class="text-success fw-bold"><?= (int) $item['novos'] ?></td>
                <td class="text-primary fw-bold"><?= (int) $item['atualizados'] ?></td>
                <td><?= (int) $item['ignorados'] ?></td>
                <td class="text-warning fw-bold"><?= (int) $item['revisar'] ?></td>
                <td class="text-danger fw-bold"><?= (int) $item['erros'] ?></td>
                <td><?= cm_h($item['usuario_nome'] ?: '—') ?></td>
            </tr><?php endforeach; ?></tbody></table></div>
        </div>
    <?php endif; ?>
</section>
<?php
$pageCustomContent = (string) ob_get_clean();
