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
    'description' => 'Importe a lista, confira as pessoas e confirme quem já recebe ou quem ficará na lista de espera.',
    'actions' => [
        ['label' => 'Beneficiários ativos', 'icon' => 'people-fill', 'primary' => true, 'href' => 'comida-mesa/beneficiarios.php?program_status=ativa'],
        ['label' => 'Lista de espera', 'icon' => 'hourglass-split', 'href' => 'comida-mesa/beneficiarios.php?program_status=lista_espera'],
    ],
    'demo' => false,
    'show_states' => false,
];

$pdo = cm_db();
$schemaReady = cm_import_schema_ready($pdo);
$poles = cm_app()['repository']->listActivePoles();
$preview = null;
$result = null;
$message = null;

$selectedImportId = isset($_GET['import_id']) ? max(0, (int) $_GET['import_id']) : 0;
$reviewSearch = trim((string) ($_GET['review_search'] ?? ''));
$reviewSituation = trim((string) ($_GET['review_situation'] ?? 'Pendente'));
$reviewPage = max(1, (int) ($_GET['review_page'] ?? 1));

$options = [
    'polo_padrao_id' => isset($_POST['polo_padrao_id']) && (int) $_POST['polo_padrao_id'] > 0 ? (int) $_POST['polo_padrao_id'] : null,
    'status_padrao' => 'em_analise',
    'prioridade_padrao' => trim((string) ($_POST['prioridade_padrao'] ?? 'normal')),
    'zona_padrao' => trim((string) ($_POST['zona_padrao'] ?? '')),
    'atualizar_existentes' => false,
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['cm_action'] ?? 'validate');

    try {
        if ($action === 'reprocess_links') {
            if (!$schemaReady) {
                throw new RuntimeException('Execute a migration 20260825_005_comida_mesa_confirmacao_importacao.sql antes de reprocessar os vínculos.');
            }
            if (!Csrf::validateAndRotate($_POST['_csrf'] ?? null, 'comida_mesa_importar_decisao')) {
                throw new RuntimeException('Sessão de segurança expirada. Atualize a página e tente novamente.');
            }

            $selectedImportId = max(0, (int) ($_POST['import_id'] ?? 0));
            $reviewSearch = trim((string) ($_POST['return_search'] ?? ''));
            $reviewSituation = trim((string) ($_POST['return_situation'] ?? ''));
            $reviewPage = max(1, (int) ($_POST['return_page'] ?? 1));

            $decisionResult = cm_import_reprocess_confirmed_unlinked(
                $pdo,
                $selectedImportId,
                (int) cm_app()['user']->id
            );

            $message = [
                'type' => $decisionResult['errors'] || $decisionResult['conflitos'] > 0 ? 'warning' : 'success',
                'text' => sprintf(
                    'Vínculos reprocessados: %d registro(s) verificado(s), %d vinculado(s) ao cadastro oficial, %d realmente continuam com pendência cadastral e %d conflito(s).',
                    $decisionResult['updated'],
                    $decisionResult['vinculados'],
                    $decisionResult['pendentes'],
                    $decisionResult['conflitos']
                ),
            ];
            if ($decisionResult['errors']) {
                $message['text'] .= ' Ocorreram ' . count($decisionResult['errors']) . ' erro(s) pontual(is).';
            }
        } elseif ($action === 'decide') {
            if (!$schemaReady) {
                throw new RuntimeException('Execute a migration 20260825_005_comida_mesa_confirmacao_importacao.sql antes de confirmar a lista.');
            }
            if (!Csrf::validateAndRotate($_POST['_csrf'] ?? null, 'comida_mesa_importar_decisao')) {
                throw new RuntimeException('Sessão de segurança expirada. Atualize a página e tente novamente.');
            }

            $selectedImportId = max(0, (int) ($_POST['import_id'] ?? 0));
            $reviewSearch = trim((string) ($_POST['return_search'] ?? ''));
            $reviewSituation = trim((string) ($_POST['return_situation'] ?? 'Pendente'));
            $reviewPage = max(1, (int) ($_POST['return_page'] ?? 1));
            $decision = (string) ($_POST['program_decision'] ?? '');
            $decisionScope = (string) ($_POST['decision_scope'] ?? 'selected');

            $itemIds = [];
            if (isset($_POST['item_id'])) {
                $itemIds[] = (int) $_POST['item_id'];
            }
            if (isset($_POST['item_ids']) && is_array($_POST['item_ids'])) {
                foreach ($_POST['item_ids'] as $id) $itemIds[] = (int) $id;
            }

            if ($decisionScope === 'all_pending') {
                $decisionResult = cm_import_decide_all_pending(
                    $pdo,
                    $selectedImportId,
                    $decision,
                    (int) cm_app()['user']->id
                );
            } else {
                $decisionResult = cm_import_decide_items($pdo, $itemIds, $decision, (int) cm_app()['user']->id);
            }
            $label = $decision === 'Beneficiario' ? 'beneficiário(s)' : 'registro(s) para lista de espera';
            $message = [
                'type' => $decisionResult['errors'] || $decisionResult['conflitos'] > 0 ? 'warning' : 'success',
                'text' => sprintf(
                    '%d %s confirmado(s). %d vinculado(s) ao cadastro oficial, %d com cadastro pendente e %d conflito(s).',
                    $decisionResult['updated'],
                    $label,
                    $decisionResult['vinculados'],
                    $decisionResult['pendentes'],
                    $decisionResult['conflitos']
                ),
            ];
            if ($decisionResult['errors']) {
                $message['text'] .= ' Ocorreram ' . count($decisionResult['errors']) . ' erro(s) pontual(is).';
            }
        } else {
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

            if ($action === 'import') {
                if (!$schemaReady) {
                    throw new RuntimeException('Execute as migrations 20260819_004 e 20260825_005 do Comida na Mesa antes de importar.');
                }
                $hash = hash_file('sha256', $tmp) ?: '';
                $result = cm_import_execute($pdo, $preview['rows'], $name, $hash, $options);
                $selectedImportId = (int) $result['import_id'];
                $reviewSituation = 'Pendente';
                $reviewPage = 1;
                $counts = $result['counts'];
                $message = [
                    'type' => $counts['erros'] > 0 ? 'warning' : 'success',
                    'text' => sprintf(
                        'Lista importada para conferência: %d pessoa(s) aguardando confirmação, %d com alerta cadastral e %d erro(s). Nenhuma situação do programa foi decidida automaticamente.',
                        $counts['pendentes'], $counts['com_pendencia_cadastral'], $counts['erros']
                    ),
                ];
            } else {
                $message = ['type' => 'info', 'text' => 'Validação concluída. Nenhum dado foi gravado.'];
            }
        }
    } catch (Throwable $e) {
        $message = ['type' => 'danger', 'text' => $e->getMessage()];
    }
}

$history = $schemaReady ? cm_import_history($pdo, 20) : [];
if ($selectedImportId < 1 && $history) {
    $selectedImportId = (int) $history[0]['id'];
}
$selectedImport = $selectedImportId > 0 && $schemaReady ? cm_import_history_item($pdo, $selectedImportId) : null;
$review = $selectedImport !== null
    ? cm_import_review_items($pdo, $selectedImportId, $reviewSearch, $reviewSituation, $reviewPage, 50)
    : ['items'=>[], 'total'=>0, 'page'=>1, 'per_page'=>50, 'total_pages'=>1, 'counts'=>['Pendente'=>0,'Beneficiario'=>0,'ListaEspera'=>0]];
$confirmedUnlinkedCount = $selectedImport !== null ? cm_import_confirmed_unlinked_count($pdo, $selectedImportId) : 0;

$flattenIssues = static function (array $row): string {
    $items = [];
    foreach (($row['issues'] ?? []) as $messages) {
        foreach ($messages as $issue) $items[] = (string) $issue;
    }
    return implode(' · ', $items);
};

$reviewUrl = static function (int $importId, string $search, string $situation, int $page = 1): string {
    $params = array_filter([
        'import_id'=>$importId,
        'review_search'=>$search,
        'review_situation'=>$situation,
        'review_page'=>$page > 1 ? $page : null,
    ], static fn($v) => $v !== null && $v !== '');
    return 'comida-mesa/importar-beneficiarios.php?' . http_build_query($params) . '#lista-conferencia';
};

ob_start();
?>
<section class="content-card cm-list-card cm-import-page">
    <?php cm_list_header(
        'Carga e conferência',
        'Lista de importação do Comida na Mesa',
        'Primeiro a planilha entra na fila. Depois você confirma quem já recebe e quem ficará na lista de espera.'
    ); ?>

    <?php if (!$schemaReady): ?>
        <div class="alert alert-warning mt-3">
            <strong>Fluxo de confirmação ainda não instalado no banco.</strong><br>
            Execute primeiro <code>database/migrations/20260819_004_comida_mesa_importacao.sql</code> e depois
            <code>database/migrations/20260825_005_comida_mesa_confirmacao_importacao.sql</code>.
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-<?= cm_h($message['type']) ?> mt-3 mb-0"><?= cm_h($message['text']) ?></div>
    <?php endif; ?>

    <div class="cm-import-steps">
        <article><span>1</span><div><strong>Enviar planilha</strong><small>A lista é lida e validada.</small></div></article>
        <article><span>2</span><div><strong>Montar conferência</strong><small>Todos ficam como aguardando confirmação.</small></div></article>
        <article><span>3</span><div><strong>Confirmar situação</strong><small>Beneficiário ou lista de espera.</small></div></article>
        <article><span>4</span><div><strong>Enviar para a lista certa</strong><small>O sistema cria ou vincula o cadastro oficial quando possível.</small></div></article>
    </div>

    <form method="post" enctype="multipart/form-data" class="cm-import-form mt-3" action="comida-mesa/importar-beneficiarios.php">
        <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_importar')) ?>">
        <div class="cm-import-upload">
            <label>
                <span>Planilha Excel ou CSV *</span>
                <input class="form-control" type="file" name="planilha" accept=".xlsx,.csv" required>
                <small>Esta carga não define automaticamente quem é beneficiário. A decisão é feita na lista de conferência.</small>
            </label>
            <a class="btn btn-light" href="comida-mesa/modelo-importacao.php"><i class="bi bi-file-earmark-excel"></i> Modelo Excel</a>
        </div>

        <div class="cm-import-defaults">
            <label><span>Polo padrão</span><select class="form-select" name="polo_padrao_id"><option value="">Não aplicar</option><?php foreach ($poles as $pole): ?><option value="<?= (int) $pole['id'] ?>"<?= cm_selected($options['polo_padrao_id'], $pole['id']) ?>><?= cm_h($pole['nome']) ?></option><?php endforeach; ?></select><small>Se informado, poderá ser usado quando o beneficiário for efetivado.</small></label>
            <label><span>Prioridade padrão</span><select class="form-select" name="prioridade_padrao"><option value="normal"<?= cm_selected($options['prioridade_padrao'],'normal') ?>>Normal</option><option value="alta"<?= cm_selected($options['prioridade_padrao'],'alta') ?>>Alta</option><option value="baixa"<?= cm_selected($options['prioridade_padrao'],'baixa') ?>>Baixa</option></select></label>
            <label><span>Zona padrão</span><select class="form-select" name="zona_padrao"><option value="">Detectar automaticamente</option><option value="urbana"<?= cm_selected($options['zona_padrao'],'urbana') ?>>Urbana</option><option value="rural"<?= cm_selected($options['zona_padrao'],'rural') ?>>Rural</option></select></label>
        </div>

        <div class="cm-import-actions">
            <button class="btn btn-light" type="submit" name="cm_action" value="validate"><i class="bi bi-shield-check"></i> Validar planilha</button>
            <button class="btn btn-primary" type="submit" name="cm_action" value="import"<?= !$schemaReady ? ' disabled' : '' ?> onclick="return confirm('Importar esta planilha para a lista de conferência? Nenhuma pessoa será classificada automaticamente como beneficiária ou lista de espera.')"><i class="bi bi-list-check"></i> Importar para conferência</button>
        </div>
    </form>

    <?php if ($preview): $s = $preview['summary']; ?>
        <?php cm_metrics([
            ['label'=>'Pessoas identificadas','value'=>$s['total'],'hint'=>'Entrarão na conferência','tone'=>'neutral'],
            ['label'=>'Sem alerta crítico','value'=>$s['novos']+$s['ignorar']+$s['atualizar'],'hint'=>'Dados suficientes para análise','tone'=>'success'],
            ['label'=>'Com alerta cadastral','value'=>$s['revisar'],'hint'=>'Também entram na conferência','tone'=>'warning'],
            ['label'=>'CPF inconsistente','value'=>$s['cpf_invalidos'],'hint'=>'Não impede decidir a situação','tone'=>'danger'],
            ['label'=>'Telefone inconsistente','value'=>$s['telefone_invalido'],'hint'=>'Permanece como pendência cadastral','tone'=>'warning'],
            ['label'=>'Cabeçalhos encontrados','value'=>$s['cabecalhos_detectados'] ?? 1,'hint'=>'Blocos reconhecidos automaticamente','tone'=>'info'],
        ]); ?>

        <div class="cm-import-note">
            <i class="bi bi-info-circle"></i>
            <div><strong>Importação e situação no programa são coisas diferentes</strong><span>CPF ou telefone incompleto não decide se a pessoa recebe. Esses problemas ficam apenas como alerta cadastral; você confirma separadamente se ela é beneficiária ou lista de espera.</span></div>
        </div>

        <div class="cm-table-shell mt-3">
            <div class="cm-table-toolbar"><div><h3>Prévia</h3><p>Primeiras <?= min(50, count($preview['rows'])) ?> pessoas reconhecidas.</p></div></div>
            <div class="table-responsive">
                <table class="cm-data-table cm-import-table">
                    <thead><tr><th>Linha</th><th>Responsável</th><th>Documento</th><th>Telefone</th><th>Local</th><th>Situação cadastral</th><th>Observação</th></tr></thead>
                    <tbody>
                    <?php foreach (array_slice($preview['rows'], 0, 50) as $row): $d=$row['data']; ?>
                        <?php $hasIssues = $flattenIssues($row) !== ''; ?>
                        <tr>
                            <td><?= (int) $row['row'] ?></td>
                            <td><strong><?= cm_h($d['nome']) ?></strong></td>
                            <td><?= cm_h($d['cpf'] !== '' ? cm_format_cpf($d['cpf']) : ($d['cpf_informado'] ?: '—')) ?></td>
                            <td><?= cm_h($d['telefone_informado'] ?: '—') ?></td>
                            <td><?= cm_h($d['local_origem'] ?: ($d['bairro'] ?: '—')) ?></td>
                            <td><span class="cm-status cm-status--<?= $hasIssues ? 'warning' : 'success' ?>"><?= cm_h($row['classification']) ?></span></td>
                            <td class="cm-import-reason"><?= cm_h($flattenIssues($row) ?: 'Sem pendências identificadas') ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>

    <?php if ($selectedImport): ?>
        <div class="cm-table-shell mt-4" id="lista-conferencia">
            <div class="cm-table-toolbar">
                <div>
                    <h3>Lista de conferência #<?= (int) $selectedImport['id'] ?></h3>
                    <p><?= cm_h($selectedImport['arquivo_nome']) ?> · importada em <?= cm_h(cm_date($selectedImport['criado_em'], true)) ?></p>
                </div>
                <div class="d-flex gap-2 flex-wrap">
                    <a class="btn btn-light btn-sm" href="comida-mesa/beneficiarios.php?program_status=ativa"><i class="bi bi-people-fill"></i> Beneficiários</a>
                    <a class="btn btn-light btn-sm" href="comida-mesa/beneficiarios.php?program_status=lista_espera"><i class="bi bi-hourglass-split"></i> Lista de espera</a>
                </div>
            </div>

            <?php cm_metrics([
                ['label'=>'Aguardando confirmação','value'=>$review['counts']['Pendente'],'hint'=>'Ainda sem decisão','tone'=>'warning'],
                ['label'=>'Beneficiários confirmados','value'=>$review['counts']['Beneficiario'],'hint'=>'Enviados para a lista de beneficiários','tone'=>'success'],
                ['label'=>'Lista de espera','value'=>$review['counts']['ListaEspera'],'hint'=>'Aguardam vaga/disponibilidade','tone'=>'info'],
                ['label'=>'Total da carga','value'=>(int)$selectedImport['total_linhas'],'hint'=>'Pessoas importadas','tone'=>'neutral'],
            ]); ?>

            <?php if ($confirmedUnlinkedCount > 0): ?>
                <div class="alert alert-warning d-flex justify-content-between align-items-center gap-3 flex-wrap mt-3">
                    <div>
                        <strong><?= number_format($confirmedUnlinkedCount, 0, ',', '.') ?> confirmado(s) ainda sem vínculo oficial.</strong><br>
                        <span>Isso pode ter sido causado pela versão anterior da ação em lote. Reprocesse para criar/vincular automaticamente quem tiver dados suficientes. A decisão Beneficiário/Lista de espera será preservada.</span>
                    </div>
                    <form method="post" action="comida-mesa/importar-beneficiarios.php" class="m-0">
                        <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_importar_decisao')) ?>">
                        <input type="hidden" name="cm_action" value="reprocess_links">
                        <input type="hidden" name="import_id" value="<?= (int)$selectedImport['id'] ?>">
                        <input type="hidden" name="return_search" value="<?= cm_h($reviewSearch) ?>">
                        <input type="hidden" name="return_situation" value="<?= cm_h($reviewSituation) ?>">
                        <input type="hidden" name="return_page" value="<?= (int)$review['page'] ?>">
                        <button class="btn btn-warning" type="submit" onclick="return confirm('Reprocessar os vínculos dos registros já confirmados desta importação? A situação no programa não será alterada.')"><i class="bi bi-arrow-repeat"></i> Reprocessar vínculos</button>
                    </form>
                </div>
            <?php endif; ?>

            <form class="cm-filter-panel" method="get" action="comida-mesa/importar-beneficiarios.php">
                <input type="hidden" name="import_id" value="<?= (int) $selectedImport['id'] ?>">
                <label class="cm-filter-search"><span>Pesquisar na carga</span><div class="cm-input-icon"><i class="bi bi-search"></i><input class="form-control" name="review_search" value="<?= cm_h($reviewSearch) ?>" placeholder="Nome, CPF, telefone ou local"></div></label>
                <label><span>Situação no programa</span><select class="form-select" name="review_situation"><option value=""<?= cm_selected($reviewSituation,'') ?>>Todas</option><option value="Pendente"<?= cm_selected($reviewSituation,'Pendente') ?>>Aguardando confirmação</option><option value="Beneficiario"<?= cm_selected($reviewSituation,'Beneficiario') ?>>Beneficiário</option><option value="ListaEspera"<?= cm_selected($reviewSituation,'ListaEspera') ?>>Lista de espera</option></select></label>
                <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
                <a class="btn btn-light" href="<?= cm_h($reviewUrl((int)$selectedImport['id'], '', 'Pendente', 1)) ?>"><i class="bi bi-x-lg"></i> Limpar</a>
            </form>

            <?php if ($review['items']): ?>
                <form id="bulkDecisionForm" method="post" action="comida-mesa/importar-beneficiarios.php">
                    <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_importar_decisao')) ?>">
                    <input type="hidden" name="cm_action" value="decide">
                    <input type="hidden" name="decision_scope" value="selected">
                    <input type="hidden" name="import_id" value="<?= (int)$selectedImport['id'] ?>">
                    <input type="hidden" name="return_search" value="<?= cm_h($reviewSearch) ?>">
                    <input type="hidden" name="return_situation" value="<?= cm_h($reviewSituation) ?>">
                    <input type="hidden" name="return_page" value="<?= (int)$review['page'] ?>">
                </form>

                <div class="cm-import-bulkbar border-bottom">
                    <div class="cm-import-bulkgroup">
                        <div class="cm-import-bulk-title">
                            <strong>Alguns da lista</strong>
                            <small><span id="reviewSelectedCount">0</span> selecionado(s) nesta página</small>
                        </div>
                        <button data-selected-action class="btn btn-success btn-sm" type="submit" form="bulkDecisionForm" name="program_decision" value="Beneficiario" disabled onclick="return confirmSelectedDecision('Beneficiário')"><i class="bi bi-check-circle"></i> Beneficiário</button>
                        <button data-selected-action class="btn btn-warning btn-sm" type="submit" form="bulkDecisionForm" name="program_decision" value="ListaEspera" disabled onclick="return confirmSelectedDecision('Lista de espera')"><i class="bi bi-hourglass-split"></i> Lista de espera</button>
                    </div>

                    <?php if ((int)$review['counts']['Pendente'] > 0): ?>
                        <div class="cm-import-bulkgroup cm-import-bulkgroup--all">
                            <div class="cm-import-bulk-title">
                                <strong>Todos da lista</strong>
                                <small><?= number_format((int)$review['counts']['Pendente'], 0, ',', '.') ?> aguardando confirmação nesta importação</small>
                            </div>
                            <form method="post" action="comida-mesa/importar-beneficiarios.php" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_importar_decisao')) ?>">
                                <input type="hidden" name="cm_action" value="decide">
                                <input type="hidden" name="decision_scope" value="all_pending">
                                <input type="hidden" name="import_id" value="<?= (int)$selectedImport['id'] ?>">
                                <input type="hidden" name="program_decision" value="Beneficiario">
                                <input type="hidden" name="return_search" value="<?= cm_h($reviewSearch) ?>">
                                <input type="hidden" name="return_situation" value="<?= cm_h($reviewSituation) ?>">
                                <input type="hidden" name="return_page" value="<?= (int)$review['page'] ?>">
                                <button class="btn btn-outline-success btn-sm" type="submit" onclick="return confirm('Confirmar TODOS os <?= number_format((int)$review['counts']['Pendente'],0,',','.') ?> registros que ainda aguardam nesta importação como BENEFICIÁRIOS? Os já classificados não serão alterados.')"><i class="bi bi-people-fill"></i> Todos como beneficiários</button>
                            </form>
                            <form method="post" action="comida-mesa/importar-beneficiarios.php" class="d-inline">
                                <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_importar_decisao')) ?>">
                                <input type="hidden" name="cm_action" value="decide">
                                <input type="hidden" name="decision_scope" value="all_pending">
                                <input type="hidden" name="import_id" value="<?= (int)$selectedImport['id'] ?>">
                                <input type="hidden" name="program_decision" value="ListaEspera">
                                <input type="hidden" name="return_search" value="<?= cm_h($reviewSearch) ?>">
                                <input type="hidden" name="return_situation" value="<?= cm_h($reviewSituation) ?>">
                                <input type="hidden" name="return_page" value="<?= (int)$review['page'] ?>">
                                <button class="btn btn-outline-warning btn-sm" type="submit" onclick="return confirm('Enviar TODOS os <?= number_format((int)$review['counts']['Pendente'],0,',','.') ?> registros que ainda aguardam nesta importação para a LISTA DE ESPERA? Os já classificados não serão alterados.')"><i class="bi bi-hourglass-split"></i> Todos para lista de espera</button>
                            </form>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="table-responsive">
                    <table class="cm-data-table cm-import-table">
                        <thead><tr><th><input id="reviewCheckAllPage" type="checkbox" class="form-check-input" title="Selecionar todos desta página"></th><th>Ordem</th><th>Responsável</th><th>Documento / contato</th><th>Local</th><th>Cadastro</th><th>Situação no programa</th><th>Vínculo</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($review['items'] as $item): ?>
                            <?php
                            $source = $item['dados_origem'] ?? [];
                            $document = trim((string) ($item['cpf_validado'] ?: $item['cpf_informado']));
                            $digits = preg_replace('/\D+/', '', $document) ?: '';
                            $documentDisplay = strlen($digits) === 11 ? cm_format_cpf($digits) : ($document !== '' ? $document : 'Não informado');
                            $order = trim((string) ($source['ordem_origem'] ?? '')) ?: (string) $item['linha'];
                            $location = array_values(array_filter([$item['bairro_origem'] ?? '', $item['endereco_origem'] ?? '', $item['local_origem'] ?? ''], static fn($v)=>trim((string)$v)!==''));
                            $programLabel = match ((string)$item['situacao_programa']) { 'Beneficiario'=>'Beneficiário', 'ListaEspera'=>'Lista de espera', default=>'Aguardando confirmação' };
                            $programTone = match ((string)$item['situacao_programa']) { 'Beneficiario'=>'success', 'ListaEspera'=>'info', default=>'warning' };
                            $linkLabel = match ((string)$item['efetivacao_status']) { 'Vinculado'=>'Cadastro oficial vinculado', 'CadastroPendente'=>'Cadastro pendente', 'Conflito'=>'Conflito de duplicidade', default=>'Aguardando decisão' };
                            $linkTone = match ((string)$item['efetivacao_status']) { 'Vinculado'=>'success', 'Conflito'=>'danger', 'CadastroPendente'=>'warning', default=>'muted' };
                            ?>
                            <tr>
                                <td><input data-review-check class="form-check-input" type="checkbox" name="item_ids[]" value="<?= (int)$item['id'] ?>" form="bulkDecisionForm"></td>
                                <td><strong><?= cm_h($order) ?></strong><small class="d-block text-muted">Linha <?= (int)$item['linha'] ?></small></td>
                                <td><div class="cm-person-cell"><span class="cm-avatar"><?= cm_h(cm_initials((string)$item['nome'])) ?></span><div><strong><?= cm_h($item['nome']) ?></strong><?php if(!empty($item['conjuge_origem'])): ?><small>Cônjuge: <?= cm_h($item['conjuge_origem']) ?></small><?php endif; ?></div></div></td>
                                <td><strong><?= cm_h($documentDisplay) ?></strong><small class="d-block text-muted"><?= cm_h($item['telefone_informado'] ?: 'Telefone não informado') ?></small></td>
                                <td><?= cm_h($location ? implode(' · ', $location) : 'Não informado') ?></td>
                                <td><span class="cm-status cm-status--<?= empty($item['motivos']) ? 'success' : 'warning' ?>"><?= cm_h($item['classificacao'] ?: 'Sem classificação') ?></span><?php if(!empty($item['motivos'])): ?><small class="d-block mt-1 text-muted"><?= cm_h($item['motivos']) ?></small><?php endif; ?></td>
                                <td><span class="cm-status cm-status--<?= cm_h($programTone) ?>"><?= cm_h($programLabel) ?></span><?php if(!empty($item['decisor_nome'])): ?><small class="d-block text-muted"><?= cm_h($item['decisor_nome']) ?> · <?= cm_h(cm_date($item['decidido_em'], true)) ?></small><?php endif; ?></td>
                                <td><span class="cm-status cm-status--<?= cm_h($linkTone) ?>"><?= cm_h($linkLabel) ?></span><?php if(!empty($item['efetivacao_motivo'])): ?><small class="d-block mt-1 text-muted"><?= cm_h($item['efetivacao_motivo']) ?></small><?php endif; ?></td>
                                <td>
                                    <div class="d-flex gap-1 flex-wrap">
                                        <form method="post" action="comida-mesa/importar-beneficiarios.php">
                                            <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_importar_decisao')) ?>"><input type="hidden" name="cm_action" value="decide"><input type="hidden" name="decision_scope" value="selected"><input type="hidden" name="import_id" value="<?= (int)$selectedImport['id'] ?>"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="program_decision" value="Beneficiario"><input type="hidden" name="return_search" value="<?= cm_h($reviewSearch) ?>"><input type="hidden" name="return_situation" value="<?= cm_h($reviewSituation) ?>"><input type="hidden" name="return_page" value="<?= (int)$review['page'] ?>">
                                            <button class="btn btn-success btn-sm" type="submit" title="Confirmar como beneficiário"><i class="bi bi-check-lg"></i></button>
                                        </form>
                                        <form method="post" action="comida-mesa/importar-beneficiarios.php">
                                            <input type="hidden" name="_csrf" value="<?= cm_h(cm_csrf('comida_mesa_importar_decisao')) ?>"><input type="hidden" name="cm_action" value="decide"><input type="hidden" name="decision_scope" value="selected"><input type="hidden" name="import_id" value="<?= (int)$selectedImport['id'] ?>"><input type="hidden" name="item_id" value="<?= (int)$item['id'] ?>"><input type="hidden" name="program_decision" value="ListaEspera"><input type="hidden" name="return_search" value="<?= cm_h($reviewSearch) ?>"><input type="hidden" name="return_situation" value="<?= cm_h($reviewSituation) ?>"><input type="hidden" name="return_page" value="<?= (int)$review['page'] ?>">
                                            <button class="btn btn-warning btn-sm" type="submit" title="Enviar para lista de espera"><i class="bi bi-hourglass-split"></i></button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cm-pagination">
                    <span>Exibindo <?= count($review['items']) ?> de <?= number_format((int)$review['total'],0,',','.') ?> · Página <?= (int)$review['page'] ?> de <?= (int)$review['total_pages'] ?></span>
                    <nav>
                        <?php if($review['page']>1): ?><a href="<?= cm_h($reviewUrl((int)$selectedImport['id'],$reviewSearch,$reviewSituation,(int)$review['page']-1)) ?>"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
                        <?php for($p=max(1,(int)$review['page']-2);$p<=min((int)$review['total_pages'],(int)$review['page']+2);$p++): ?><a class="<?= $p===(int)$review['page']?'active':'' ?>" href="<?= cm_h($reviewUrl((int)$selectedImport['id'],$reviewSearch,$reviewSituation,$p)) ?>"><?= $p ?></a><?php endfor; ?>
                        <?php if($review['page']<$review['total_pages']): ?><a href="<?= cm_h($reviewUrl((int)$selectedImport['id'],$reviewSearch,$reviewSituation,(int)$review['page']+1)) ?>"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
                    </nav>
                </div>
            <?php else: ?>
                <?php cm_empty('Nenhum registro neste filtro','Ajuste a pesquisa ou a situação da conferência.','search'); ?>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <?php if ($history): ?>
        <div class="cm-table-shell mt-4">
            <div class="cm-table-toolbar"><div><h3>Histórico de importações</h3><p>Abra qualquer carga para continuar a conferência.</p></div></div>
            <div class="table-responsive"><table class="cm-data-table"><thead><tr><th>Data</th><th>Arquivo</th><th>Total</th><th>Aguardando</th><th>Beneficiários</th><th>Lista de espera</th><th>Erros</th><th>Responsável</th><th></th></tr></thead><tbody>
            <?php foreach ($history as $item): ?><tr>
                <td><?= cm_h(cm_date($item['criado_em'], true)) ?></td>
                <td><strong><?= cm_h($item['arquivo_nome']) ?></strong><small class="d-block text-muted"><?= cm_h($item['status']) ?></small></td>
                <td><?= (int)$item['total_linhas'] ?></td>
                <td class="text-warning fw-bold"><?= (int)$item['pendentes_confirmacao'] ?></td>
                <td class="text-success fw-bold"><?= (int)$item['beneficiarios_confirmados'] ?></td>
                <td class="text-primary fw-bold"><?= (int)$item['lista_espera_confirmados'] ?></td>
                <td class="text-danger fw-bold"><?= (int)$item['erros'] ?></td>
                <td><?= cm_h($item['usuario_nome'] ?: '—') ?></td>
                <td><a class="btn btn-light btn-sm" href="<?= cm_h($reviewUrl((int)$item['id'],'','Pendente',1)) ?>"><i class="bi bi-list-check"></i> Abrir lista</a></td>
            </tr><?php endforeach; ?></tbody></table></div>
        </div>
    <?php endif; ?>
<style>
.cm-import-bulkbar{display:flex;gap:16px;justify-content:space-between;align-items:stretch;flex-wrap:wrap;padding:14px 16px;background:#fff}.cm-import-bulkgroup{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.cm-import-bulkgroup--all{padding-left:16px;border-left:1px solid var(--bs-border-color,#dee2e6)}.cm-import-bulk-title{display:flex;flex-direction:column;min-width:145px}.cm-import-bulk-title small{color:#6c757d;font-size:.78rem}.cm-import-table tbody tr:has([data-review-check]:checked){background:rgba(13,110,253,.055)}@media(max-width:900px){.cm-import-bulkgroup--all{padding-left:0;border-left:0;border-top:1px solid var(--bs-border-color,#dee2e6);padding-top:12px;width:100%}}
</style>
<script>
(function () {
    const master = document.getElementById('reviewCheckAllPage');
    const checks = Array.from(document.querySelectorAll('[data-review-check]'));
    const count = document.getElementById('reviewSelectedCount');
    const buttons = Array.from(document.querySelectorAll('[data-selected-action]'));

    function refreshSelection() {
        const selected = checks.filter(el => el.checked).length;
        if (count) count.textContent = String(selected);
        buttons.forEach(btn => btn.disabled = selected === 0);
        if (master) {
            master.checked = checks.length > 0 && selected === checks.length;
            master.indeterminate = selected > 0 && selected < checks.length;
        }
    }

    if (master) {
        master.addEventListener('change', function () {
            checks.forEach(el => { el.checked = master.checked; });
            refreshSelection();
        });
    }
    checks.forEach(el => el.addEventListener('change', refreshSelection));
    refreshSelection();

    window.confirmSelectedDecision = function (label) {
        const selected = checks.filter(el => el.checked).length;
        if (!selected) return false;
        return window.confirm('Aplicar "' + label + '" somente aos ' + selected + ' registro(s) selecionado(s)?');
    };
})();
</script>

</section>
<?php
$pageCustomContent = (string) ob_get_clean();
