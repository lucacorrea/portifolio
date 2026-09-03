<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/forms.php';
require_once dirname(__DIR__) . '/lib/import.php';
require_once dirname(__DIR__) . '/lib/beneficiary-review.php';
require_once dirname(__DIR__) . '/lib/beneficiary-review-import.php';

$pageExtraStyles[] = 'assets/css/modules/comida-mesa-review.css';

$pageDefinition = [
    'title' => 'Beneficiários',
    'description' => 'Gestão das famílias beneficiárias com controle de qualidade cadastral, entregas e filtros de revisão.',
    'actions' => [
        ['label' => 'Nova inscrição', 'icon' => 'person-plus', 'primary' => true, 'href' => 'comida-mesa/nova-inscricao.php'],
        ...((cm_can('comida_mesa.importar') || cm_can('comida_mesa.cadastrar')) ? [['label' => 'Importar Excel', 'icon' => 'file-earmark-spreadsheet', 'href' => 'comida-mesa/importar-beneficiarios.php']] : []),
        ['label' => 'Consultar CPF', 'icon' => 'person-bounding-box', 'href' => 'comida-mesa/consulta-cpf.php'],
        ['label' => 'Registrar entrega', 'icon' => 'box-seam', 'href' => 'comida-mesa/registrar-entrega.php'],
    ],
    'demo' => false,
    'show_states' => false,
];

$service = cm_app()['service'];
$loadError = null;
$reviewError = null;
$reviewFilter = cm_beneficiary_review_key($_GET['review'] ?? '');
$originFilter = cm_beneficiary_origin_key($_GET['origin'] ?? '');
$priorityFilter = cm_beneficiary_priority_key($_GET['priority'] ?? '');

try {
    $data = $service->getDashboardData($_GET);
    $filter = $data['filter'];
    $competence = $data['competence'];
    $competences = $data['competences'];
    $poles = $data['poles'];
    $statistics = $data['statistics'];

    // A lista é paginada pelo motor de qualidade cadastral para que os filtros de
    // revisão funcionem no banco e não apenas nos registros visíveis da página.
    $registrations = cm_beneficiary_review_paginate(
        cm_db(),
        $filter,
        $reviewFilter,
        $originFilter,
        $priorityFilter
    );
} catch (Throwable $e) {
    $loadError = 'Não foi possível carregar as famílias beneficiárias.';
    $filter = $service->buildFilter($_GET);
    $competence = null;
    $competences = [];
    $poles = [];
    $statistics = [
        'familias_cadastradas'=>0,
        'beneficiarias_ativas'=>0,
        'em_analise'=>0,
        'lista_espera'=>0,
        'suspensas'=>0,
        'bloqueadas'=>0,
        'polos_ativos'=>0,
        'entregas_competencia'=>0,
        'aguardando_retirada'=>0,
    ];
    $registrations = new App\DTO\PaginatedResult([], 0, 1, 20);
}

try {
    $reviewStats = cm_beneficiary_review_stats(cm_db());
} catch (Throwable $e) {
    $reviewError = 'Os indicadores de qualidade cadastral não puderam ser calculados.';
    $reviewStats = [
        'total'=>0,
        'revisao_pendente'=>0,
        'revisar_cadastro'=>0,
        'revisar_cpf'=>0,
        'cpf_duplicado'=>0,
        'revisar_telefone'=>0,
        'revisar_polo'=>0,
        'regular'=>0,
        'importados'=>0,
    ];
}

$currentCompetenceId = $competence ? (int) $competence['id'] : null;
$competenceLabel = $competence ? cm_month_label((int) $competence['mes'], (int) $competence['ano']) : 'Sem competência';
$frontendContext['comidaMesa']['competenciaId'] = $currentCompetenceId;
$frontendContext['comidaMesa']['competenceLabel'] = $competenceLabel;
$frontendContext['comidaMesa']['competences'] = array_map(static fn(array $item): array => [
    'id'=>(int)$item['id'],
    'month'=>(int)$item['mes'],
    'year'=>(int)$item['ano'],
    'status'=>(string)$item['status'],
    'startsAt'=>$item['inicio_entregas']??'',
    'endsAt'=>$item['fim_entregas']??'',
    'observation'=>$item['observacao']??'',
], $competences);

$programStatuses = [
    'ativa'=>'Beneficiária ativa',
    'em_analise'=>'Em análise',
    'lista_espera'=>'Lista de espera',
    'suspensa'=>'Suspensa',
    'bloqueada'=>'Bloqueada',
    'encerrada'=>'Encerrada',
];
$deliveryStatuses = [
    'recebida'=>'Recebida',
    'aguardando'=>'Aguardando retirada',
    'bloqueada'=>'Bloqueada',
    'indisponivel'=>'Não disponível',
];
$reviewOptions = [
    'pendente'=>'Regularização pendente',
    'cadastro'=>'Revisar cadastro',
    'cpf'=>'Revisar CPF',
    'cpf_duplicado'=>'CPF duplicado',
    'telefone'=>'Revisar telefone',
    'polo'=>'Revisar polo',
    'regular'=>'Sem pendência',
];

$items = $registrations->getItems();
$total = $registrations->getTotal();
$currentPage = $registrations->getPage();
$totalPages = $registrations->getTotalPages();

// Confirmados que ainda não foram transformados em inscrição oficial permanecem na
// mesma lista. Após executar a regularização, eles desaparecem daqui e passam para a
// paginação oficial automaticamente.
$importOnlyCounts = ['beneficiarios'=>0,'lista_espera'=>0];
$importOnly = ['items'=>[], 'total'=>0, 'page'=>1, 'per_page'=>50, 'total_pages'=>1];
$importOnlyError = null;
try {
    $importOnlyCounts = cm_import_confirmed_unlinked_counts(cm_db());
    $canShowImportOnly = $filter->zone === null
        && $filter->district === null
        && $filter->community === null
        && $filter->poleId === null
        && $filter->deliveryStatus === null
        && $priorityFilter === ''
        && $originFilter !== 'manual'
        && in_array($filter->programStatus, [null, 'ativa', 'lista_espera'], true);

    if ($canShowImportOnly) {
        $importProgramStatus = (string) ($filter->programStatus ?? '');
        $importOnlyPage = max(1, (int) ($_GET['import_page'] ?? 1));
        $importOnly = cm_beneficiary_review_unlinked_paginate(
            cm_db(),
            $importProgramStatus,
            (string) ($filter->search ?? ''),
            $reviewFilter,
            $importOnlyPage,
            50
        );
    }
} catch (Throwable $e) {
    $importOnlyError = 'Não foi possível carregar os beneficiários que ainda aguardam regularização da importação.';
}

$statistics['beneficiarias_ativas'] += $importOnlyCounts['beneficiarios'];
$statistics['lista_espera'] += $importOnlyCounts['lista_espera'];
$mainVisibleCount = count($items) + count($importOnly['items']);
$mainVisibleTotal = $total + (int) $importOnly['total'];

$hasAdvanced = $filter->zone !== null
    || $filter->district !== null
    || $filter->community !== null
    || $filter->poleId !== null
    || $filter->deliveryStatus !== null
    || $priorityFilter !== '';

$baseQuery = array_filter([
    'search' => $filter->search,
    'competencia_id' => $filter->competenceId,
    'program_status' => $filter->programStatus,
    'delivery_status' => $filter->deliveryStatus,
    'zone' => $filter->zone,
    'district' => $filter->district,
    'community' => $filter->community,
    'pole_id' => $filter->poleId,
    'review' => $reviewFilter,
    'origin' => $originFilter,
    'priority' => $priorityFilter,
], static fn($value) => $value !== null && $value !== '');

$makeUrl = static function (array $overrides = []) use ($baseQuery): string {
    $params = $baseQuery;
    foreach ($overrides as $key => $value) {
        if ($value === null || $value === '') unset($params[$key]);
        else $params[$key] = $value;
    }
    unset($params['page'], $params['import_page']);
    $query = http_build_query($params);
    return 'comida-mesa/beneficiarios.php' . ($query !== '' ? '?' . $query : '');
};

$queryForPage = static function (int $pageNumber) use ($baseQuery): string {
    $params = $baseQuery;
    if ($pageNumber > 1) $params['page'] = $pageNumber;
    $query = http_build_query($params);
    return 'comida-mesa/beneficiarios.php' . ($query !== '' ? '?' . $query : '');
};

$queryForImportPage = static function (int $pageNumber) use ($baseQuery, $currentPage): string {
    $params = $baseQuery;
    if ($currentPage > 1) $params['page'] = $currentPage;
    if ($pageNumber > 1) $params['import_page'] = $pageNumber;
    $query = http_build_query($params);
    return 'comida-mesa/beneficiarios.php' . ($query !== '' ? '?' . $query : '');
};

$exportParams = array_filter([
    'tipo'=>'beneficiarios',
    'search'=>$filter->search,
    'competencia_id'=>$currentCompetenceId,
    'program_status'=>$filter->programStatus,
    'delivery_status'=>$filter->deliveryStatus,
    'zone'=>$filter->zone,
    'district'=>$filter->district,
    'community'=>$filter->community,
    'pole_id'=>$filter->poleId,
], static fn($value) => $value !== null && $value !== '');
$exportExcelUrl = 'comida-mesa/exportar-excel.php?' . http_build_query($exportParams);
$exportMatchesCurrentFilter = $reviewFilter === '' && $originFilter === '' && $priorityFilter === '';

ob_start();
?>
<section class="content-card cm-list-card">
    <?php cm_list_header(
        'Gestão do benefício',
        'Famílias beneficiárias',
        'Base de beneficiários com controle de qualidade cadastral, filtros de revisão e ações sem suspender quem já recebe.'
    ); ?>

    <?php cm_metrics([
        ['label'=>'Famílias cadastradas','value'=>$statistics['familias_cadastradas'],'hint'=>'Base oficial do programa','tone'=>'neutral'],
        ['label'=>'Beneficiárias ativas','value'=>$statistics['beneficiarias_ativas'],'hint'=>'Aptas no programa','tone'=>'success'],
        ['label'=>'Em análise','value'=>$statistics['em_analise'],'hint'=>'Aguardam decisão','tone'=>'warning'],
        ['label'=>'Lista de espera','value'=>$statistics['lista_espera'],'hint'=>'Aguardam disponibilidade','tone'=>'info'],
        ['label'=>'Entregas na competência','value'=>$statistics['entregas_competencia'],'hint'=>$competenceLabel,'tone'=>'success'],
        ['label'=>'Aguardando retirada','value'=>$statistics['aguardando_retirada'],'hint'=>'Benefício ainda não retirado','tone'=>'warning'],
        ['label'=>'Suspensas/bloqueadas','value'=>$statistics['suspensas']+$statistics['bloqueadas'],'hint'=>'Situação do programa','tone'=>'danger'],
        ['label'=>'Polos ativos','value'=>$statistics['polos_ativos'],'hint'=>'Rede de distribuição','tone'=>'neutral'],
    ]); ?>

    <?php if ($loadError): ?><div class="alert alert-danger mt-3 mb-0"><?= cm_h($loadError) ?></div><?php endif; ?>
    <?php if ($reviewError): ?><div class="alert alert-warning mt-3 mb-0"><?= cm_h($reviewError) ?></div><?php endif; ?>
    <?php if ($importOnlyError): ?><div class="alert alert-warning mt-3 mb-0"><?= cm_h($importOnlyError) ?></div><?php endif; ?>

    <div class="cm-review-quality">
        <div class="cm-review-quality__header">
            <div>
                <div class="card-kicker">QUALIDADE CADASTRAL</div>
                <h3>Revisões da base de beneficiários</h3>
                <p>Clique em um indicador para trabalhar somente naquele tipo de pendência.</p>
            </div>
            <?php if ($reviewFilter !== '' || $originFilter !== ''): ?>
                <a class="btn btn-light btn-sm" href="<?= cm_h($makeUrl(['review'=>null,'origin'=>null])) ?>"><i class="bi bi-x-lg"></i> Limpar revisão</a>
            <?php endif; ?>
        </div>

        <div class="cm-review-kpi-grid">
            <a class="cm-review-kpi cm-review-kpi--warning<?= $reviewFilter === 'pendente' ? ' is-active' : '' ?>" href="<?= cm_h($makeUrl(['review'=>'pendente'])) ?>">
                <span>Regularização pendente</span><strong><?= number_format($reviewStats['revisao_pendente'],0,',','.') ?></strong><small>Possuem ao menos uma pendência</small>
            </a>
            <a class="cm-review-kpi cm-review-kpi--warning<?= $reviewFilter === 'cadastro' ? ' is-active' : '' ?>" href="<?= cm_h($makeUrl(['review'=>'cadastro'])) ?>">
                <span>Revisar cadastro</span><strong><?= number_format($reviewStats['revisar_cadastro'],0,',','.') ?></strong><small>Duas ou mais pendências</small>
            </a>
            <a class="cm-review-kpi cm-review-kpi--warning<?= $reviewFilter === 'cpf' ? ' is-active' : '' ?>" href="<?= cm_h($makeUrl(['review'=>'cpf'])) ?>">
                <span>Revisar CPF</span><strong><?= number_format($reviewStats['revisar_cpf'],0,',','.') ?></strong><small>CPF pendente ou em revisão</small>
            </a>
            <a class="cm-review-kpi cm-review-kpi--danger<?= $reviewFilter === 'cpf_duplicado' ? ' is-active' : '' ?>" href="<?= cm_h($makeUrl(['review'=>'cpf_duplicado'])) ?>">
                <span>CPF duplicado</span><strong><?= number_format($reviewStats['cpf_duplicado'],0,',','.') ?></strong><small>Conferência individual necessária</small>
            </a>
            <a class="cm-review-kpi cm-review-kpi--warning<?= $reviewFilter === 'telefone' ? ' is-active' : '' ?>" href="<?= cm_h($makeUrl(['review'=>'telefone'])) ?>">
                <span>Revisar telefone</span><strong><?= number_format($reviewStats['revisar_telefone'],0,',','.') ?></strong><small>Ausente ou fora do padrão</small>
            </a>
            <a class="cm-review-kpi cm-review-kpi--warning<?= $reviewFilter === 'polo' ? ' is-active' : '' ?>" href="<?= cm_h($makeUrl(['review'=>'polo'])) ?>">
                <span>Revisar polo</span><strong><?= number_format($reviewStats['revisar_polo'],0,',','.') ?></strong><small>Polo não definido ou inativo</small>
            </a>
            <a class="cm-review-kpi cm-review-kpi--success<?= $reviewFilter === 'regular' ? ' is-active' : '' ?>" href="<?= cm_h($makeUrl(['review'=>'regular'])) ?>">
                <span>Sem pendência</span><strong><?= number_format($reviewStats['regular'],0,',','.') ?></strong><small>Cadastro sem alerta atual</small>
            </a>
            <a class="cm-review-kpi cm-review-kpi--info<?= $originFilter === 'importado' ? ' is-active' : '' ?>" href="<?= cm_h($makeUrl(['review'=>null,'origin'=>'importado'])) ?>">
                <span>Importados</span><strong><?= number_format($reviewStats['importados'],0,',','.') ?></strong><small>Originados de planilha</small>
            </a>
        </div>

        <div class="cm-review-legend">
            <span><i class="cm-review-dot cm-review-dot--warning"></i> uma pendência</span>
            <span><i class="cm-review-dot cm-review-dot--warning"></i> revisar cadastro</span>
            <span><i class="cm-review-dot cm-review-dot--danger"></i> CPF duplicado</span>
            <span><i class="cm-review-dot cm-review-dot--success"></i> sem pendência</span>
        </div>
    </div>

    <div class="alert alert-warning mt-3 mb-0" role="alert">
        <strong><i class="bi bi-exclamation-triangle"></i> Pendência cadastral não suspende o benefício.</strong>
        <div class="mt-1">A coluna Revisão indica o que precisa ser corrigido. Situação do programa e entrega permanecem controles separados. CPF duplicado nunca é substituído por CPF inventado.</div>
    </div>

    <form class="cm-filter-panel cm-filter-panel--review" method="get" action="comida-mesa/beneficiarios.php" data-server-filter>
        <label class="cm-filter-search">
            <span>Pesquisa</span>
            <div class="cm-input-icon"><i class="bi bi-search"></i><input class="form-control" name="search" type="search" value="<?= cm_h($filter->search) ?>" placeholder="Nome, CPF, NIS ou código"></div>
        </label>
        <label>
            <span>Revisão</span>
            <select class="form-select" name="review">
                <option value="">Todas as revisões</option>
                <?php foreach ($reviewOptions as $value=>$label): ?><option value="<?= cm_h($value) ?>"<?= cm_selected($reviewFilter,$value) ?>><?= cm_h($label) ?></option><?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>Situação no programa</span>
            <select class="form-select" name="program_status"><option value="">Todas</option><?php foreach ($programStatuses as $value=>$label): ?><option value="<?= cm_h($value) ?>"<?= cm_selected($filter->programStatus,$value) ?>><?= cm_h($label) ?></option><?php endforeach; ?></select>
        </label>
        <label>
            <span>Origem</span>
            <select class="form-select" name="origin">
                <option value="">Todas</option>
                <option value="importado"<?= cm_selected($originFilter,'importado') ?>>Importação</option>
                <option value="manual"<?= cm_selected($originFilter,'manual') ?>>Cadastro manual</option>
            </select>
        </label>
        <button class="btn btn-light" type="button" data-toggle-advanced aria-expanded="<?= $hasAdvanced?'true':'false' ?>"><i class="bi bi-sliders"></i> Avançados</button>
        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
        <a class="btn btn-light cm-filter-clear" href="comida-mesa/beneficiarios.php" title="Limpar filtros"><i class="bi bi-x-lg"></i></a>

        <div class="cm-advanced-filters<?= $hasAdvanced?' show':'' ?>" id="advancedFilters">
            <label><span>Situação da entrega</span><select class="form-select" name="delivery_status"><option value="">Todas</option><?php foreach ($deliveryStatuses as $value=>$label): ?><option value="<?= cm_h($value) ?>"<?= cm_selected($filter->deliveryStatus,$value) ?>><?= cm_h($label) ?></option><?php endforeach; ?></select></label>
            <label><span>Competência</span><select class="form-select" name="competencia_id"><option value="">Padrão</option><?php foreach ($competences as $item): ?><option value="<?= (int)$item['id'] ?>"<?= cm_selected($currentCompetenceId,$item['id']) ?>><?= cm_h(cm_month_label((int)$item['mes'],(int)$item['ano'])) ?></option><?php endforeach; ?></select></label>
            <label><span>Prioridade</span><select class="form-select" name="priority"><option value="">Todas</option><option value="alta"<?= cm_selected($priorityFilter,'alta') ?>>Alta</option><option value="normal"<?= cm_selected($priorityFilter,'normal') ?>>Normal</option><option value="baixa"<?= cm_selected($priorityFilter,'baixa') ?>>Baixa</option></select></label>
            <label><span>Zona</span><select class="form-select" name="zone"><option value="">Todas</option><option value="urbana"<?= cm_selected($filter->zone,'urbana') ?>>Urbana</option><option value="rural"<?= cm_selected($filter->zone,'rural') ?>>Rural</option></select></label>
            <label><span>Bairro</span><input class="form-control" name="district" value="<?= cm_h($filter->district) ?>"></label>
            <label><span>Comunidade</span><input class="form-control" name="community" value="<?= cm_h($filter->community) ?>"></label>
            <label><span>Polo</span><select class="form-select" name="pole_id"><option value="">Todos</option><?php foreach ($poles as $pole): ?><option value="<?= (int)$pole['id'] ?>"<?= cm_selected($filter->poleId,$pole['id']) ?>><?= cm_h($pole['nome']) ?></option><?php endforeach; ?></select></label>
        </div>
    </form>

    <div class="d-flex gap-2 flex-wrap align-items-center mt-2 mb-2">
        <?php if ($reviewFilter !== ''): ?><span class="cm-review-active-filter"><i class="bi bi-exclamation-circle"></i> Revisão: <?= cm_h($reviewOptions[$reviewFilter] ?? $reviewFilter) ?></span><?php endif; ?>
        <?php if ($originFilter !== ''): ?><span class="cm-review-active-filter"><i class="bi bi-file-earmark-spreadsheet"></i> Origem: <?= $originFilter === 'importado' ? 'Importação' : 'Cadastro manual' ?></span><?php endif; ?>
        <?php if ($priorityFilter !== ''): ?><span class="cm-review-active-filter"><i class="bi bi-flag"></i> Prioridade: <?= cm_h(ucfirst($priorityFilter)) ?></span><?php endif; ?>
        <span class="ms-auto"></span>
        <?php if ($exportMatchesCurrentFilter): ?><a class="btn btn-light btn-sm" href="<?= cm_h($exportExcelUrl) ?>"><i class="bi bi-file-earmark-excel"></i> Exportar Excel</a><?php endif; ?>
    </div>

    <div class="cm-table-shell">
        <div class="cm-table-toolbar">
            <div>
                <h3>Famílias beneficiárias</h3>
                <p>Exibindo <?= number_format($mainVisibleCount,0,',','.') ?> de <?= number_format($mainVisibleTotal,0,',','.') ?> registro(s) · <?= cm_h($competenceLabel) ?></p>
            </div>
            <span><i class="bi bi-hand-index"></i> Clique em uma linha para abrir as ações</span>
        </div>

        <?php if ($items || !empty($importOnly['items'])): ?>
            <div class="table-responsive">
                <table class="cm-data-table">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Responsável familiar</th>
                            <th>Localidade</th>
                            <th>Polo</th>
                            <th>Situação no programa</th>
                            <th>Revisão</th>
                            <th>Entrega</th>
                            <th>Atualização</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $row): ?>
                        <?php
                        $delivery = $service->deliveryStatusForRow($row, $competence);
                        $eligibility = $service->deliveryEligibility([
                            'status'=>(string)$row['inscricao_status'],
                            'polo_id'=>$row['polo_id']??null,
                            'polo_ativo'=>$row['polo_ativo']??null,
                        ], $competence, empty($row['entrega_id']) ? null : ['status'=>(string)$row['entrega_status']]);
                        $deliveryAction = (string) $eligibility['action'];
                        $canDeliverRow = cm_can('comida_mesa.entregar') && (bool)$eligibility['allowed'] && in_array($deliveryAction,['register','reactivate'],true);
                        $canCancelRow = cm_can('comida_mesa.cancelar_entrega') && (bool)$eligibility['allowed'] && $deliveryAction === 'cancel';
                        $reviewMeta = cm_beneficiary_review_meta($row);
                        $reviewReasons = implode(' · ', $reviewMeta['reasons']);
                        $cpfValue = trim((string)($row['cpf'] ?? ''));
                        $cpfDisplay = $cpfValue !== '' ? cm_format_cpf($cpfValue) : 'Pendente';
                        ?>
                        <tr tabindex="0" data-cm-action-row
                            data-registration-id="<?= (int)$row['inscricao_id'] ?>"
                            data-registration-name="<?= cm_h($row['responsavel_nome']) ?>"
                            data-family-code="<?= cm_h($row['familia_codigo']) ?>"
                            data-pole-name="<?= cm_h($row['polo_nome'] ?: 'Sem polo') ?>"
                            data-program-status="<?= cm_h($service->programStatusLabel((string)$row['inscricao_status'])) ?>"
                            data-delivery-label="<?= cm_h($delivery['label']) ?>"
                            data-delivery-action="<?= cm_h($deliveryAction) ?>"
                            data-delivery-title="<?= cm_h($eligibility['reason'] ?? '') ?>"
                            data-delivery-date="<?= cm_h(cm_date($delivery['delivered_at'] ?? null,true)) ?>"
                            data-delivery-operator="<?= cm_h($row['entrega_operador_nome'] ?: 'Não informado') ?>"
                            data-review-status="<?= cm_h($reviewMeta['label']) ?>"
                            data-review-reasons="<?= cm_h($reviewReasons) ?>"
                            data-can-edit="<?= cm_can('comida_mesa.editar')?'1':'0' ?>"
                            data-can-deliver="<?= $canDeliverRow?'1':'0' ?>"
                            data-can-cancel="<?= $canCancelRow?'1':'0' ?>"
                            data-can-document="<?= cm_can('comida_mesa.documentos_enviar')?'1':'0' ?>"
                            data-can-history="<?= cm_can('comida_mesa.historico_visualizar')?'1':'0' ?>">
                            <td><strong><?= cm_h($row['familia_codigo']) ?></strong></td>
                            <td>
                                <div class="cm-person-cell">
                                    <span class="cm-avatar"><?= cm_h(cm_initials((string)$row['responsavel_nome'])) ?></span>
                                    <div>
                                        <strong><?= cm_h($row['responsavel_nome']) ?></strong>
                                        <small>CPF <?= cm_h($cpfDisplay) ?><?= $row['nis'] ? ' · NIS '.cm_h($row['nis']) : '' ?></small>
                                        <?php if ($reviewMeta['imported']): ?><span class="cm-review-origin"><i class="bi bi-file-earmark-spreadsheet"></i> via planilha</span><?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td><?= cm_h(cm_location($row)) ?></td>
                            <td><?= cm_h($row['polo_nome'] ?: 'Sem polo') ?></td>
                            <td><?= cm_status($service->programStatusLabel((string)$row['inscricao_status'])) ?></td>
                            <td class="cm-review-cell">
                                <span class="cm-status cm-status--<?= cm_h($reviewMeta['tone']) ?>"><?= cm_h($reviewMeta['label']) ?></span>
                                <?php if ($reviewReasons !== ''): ?><small><?= cm_h($reviewReasons) ?></small><?php endif; ?>
                                <?php if ($reviewMeta['count'] > 0): ?><small class="text-success">Benefício mantido durante a regularização</small><?php endif; ?>
                            </td>
                            <td><?= cm_status((string)$delivery['label']) ?></td>
                            <td><?= cm_h(cm_date($row['atualizado_em'] ?? $row['data_inscricao'])) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php foreach ($importOnly['items'] as $pending): ?>
                        <?php
                        $source = $pending['dados_origem'] ?? [];
                        $document = trim((string)($pending['cpf_validado'] ?: $pending['cpf_informado']));
                        $digits = preg_replace('/\D+/', '', $document) ?: '';
                        $documentDisplay = strlen($digits) === 11 ? cm_format_cpf($digits) : ($document !== '' ? $document : 'CPF pendente');
                        $locationParts = array_values(array_filter([
                            $pending['bairro_origem'] ?? '',
                            $pending['endereco_origem'] ?? '',
                            $pending['local_origem'] ?? '',
                        ], static fn($value) => trim((string)$value) !== ''));
                        $isBeneficiary = (string)$pending['situacao_programa'] === 'Beneficiario';
                        $programLabel = $isBeneficiary ? 'Beneficiária ativa' : 'Lista de espera';
                        $importReview = cm_beneficiary_review_import_meta($pending);
                        $importReasons = implode(' · ', $importReview['reasons']);
                        $reviewParams = ['import_id'=>(int)$pending['importacao_id'], 'review_situation'=>''];
                        if (strlen($digits) === 11) $reviewParams['review_search'] = $digits;
                        $reviewHref = 'comida-mesa/importar-beneficiarios.php?' . http_build_query($reviewParams) . '#lista-conferencia';
                        ?>
                        <tr class="table-warning" title="Regularização cadastral pendente. O benefício permanece confirmado.">
                            <td><strong>IMP-<?= (int)$pending['id'] ?></strong><small class="d-block text-muted">Carga #<?= (int)$pending['importacao_id'] ?> · Linha <?= (int)$pending['linha'] ?></small></td>
                            <td>
                                <div class="cm-person-cell">
                                    <span class="cm-avatar"><?= cm_h(cm_initials((string)$pending['nome'])) ?></span>
                                    <div><strong><?= cm_h($pending['nome']) ?></strong><small>CPF informado <?= cm_h($documentDisplay) ?></small><span class="cm-review-origin"><i class="bi bi-file-earmark-spreadsheet"></i> aguardando vínculo oficial</span></div>
                                </div>
                            </td>
                            <td><?= cm_h($locationParts ? implode(' · ', $locationParts) : 'Não informado') ?></td>
                            <td><?= cm_h($pending['polo_informado'] ?: ($source['polo_informado'] ?? 'Sem polo')) ?></td>
                            <td><span class="cm-status cm-status--<?= $isBeneficiary?'success':'info' ?>"><?= cm_h($programLabel) ?></span></td>
                            <td class="cm-review-cell">
                                <span class="cm-status cm-status--<?= cm_h($importReview['tone']) ?>"><?= cm_h($importReview['label']) ?></span>
                                <?php if ($importReasons !== ''): ?><small><?= cm_h($importReasons) ?></small><?php endif; ?>
                                <small><a href="<?= cm_h($reviewHref) ?>"><i class="bi bi-tools"></i> Corrigir cadastro</a></small>
                            </td>
                            <td>
                                <?php if ($isBeneficiary): ?><span class="cm-status cm-status--success">Benefício mantido</span><small class="d-block mt-1 text-muted">Regularize o cadastro para concluir as operações digitais.</small><?php else: ?><span class="cm-status cm-status--muted">Não disponível</span><?php endif; ?>
                            </td>
                            <td><?= cm_h(cm_date($pending['decidido_em'] ?: $pending['importado_em'], true)) ?><small class="d-block text-muted"><?= cm_h($pending['decisor_nome'] ?: 'Importação') ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="cm-pagination">
                    <span>Cadastros oficiais · Página <?= $currentPage ?> de <?= $totalPages ?></span>
                    <nav>
                        <?php if($currentPage>1): ?><a href="<?= cm_h($queryForPage($currentPage-1)) ?>"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
                        <?php for($page=max(1,$currentPage-2);$page<=min($totalPages,$currentPage+2);$page++): ?><a class="<?= $page===$currentPage?'active':'' ?>" href="<?= cm_h($queryForPage($page)) ?>"><?= $page ?></a><?php endfor; ?>
                        <?php if($currentPage<$totalPages): ?><a href="<?= cm_h($queryForPage($currentPage+1)) ?>"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>

            <?php if ((int)$importOnly['total_pages'] > 1): ?>
                <div class="cm-pagination">
                    <span>Regularizações da importação · Página <?= (int)$importOnly['page'] ?> de <?= (int)$importOnly['total_pages'] ?></span>
                    <nav>
                        <?php if($importOnly['page']>1): ?><a href="<?= cm_h($queryForImportPage((int)$importOnly['page']-1)) ?>"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
                        <?php for($page=max(1,(int)$importOnly['page']-2);$page<=min((int)$importOnly['total_pages'],(int)$importOnly['page']+2);$page++): ?><a class="<?= $page===(int)$importOnly['page']?'active':'' ?>" href="<?= cm_h($queryForImportPage($page)) ?>"><?= $page ?></a><?php endfor; ?>
                        <?php if($importOnly['page']<$importOnly['total_pages']): ?><a href="<?= cm_h($queryForImportPage((int)$importOnly['page']+1)) ?>"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php cm_empty('Nenhuma família encontrada','Ajuste os filtros de revisão ou os demais critérios da pesquisa.','people'); ?>
        <?php endif; ?>
    </div>
</section>

<?php cm_new_registration_lookup_modal($competence); ?>
<?php cm_action_modal(); ?>
<?php cm_registration_modal($poles,$programStatuses); ?>
<?php cm_detail_modal(); ?>
<?php cm_delivery_modal($competence); ?>
<?php cm_cancel_delivery_modal($competence); ?>
<?php cm_document_modal(); ?>
<?php cm_competence_modal(); ?>
<?php
$pageCustomContent = (string) ob_get_clean();
