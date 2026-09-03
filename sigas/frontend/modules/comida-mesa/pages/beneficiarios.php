<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/forms.php';
require_once dirname(__DIR__) . '/lib/import.php';

$pageDefinition = [
    'title' => 'Beneficiários',
    'description' => 'Gestão das famílias beneficiárias, situação no programa, entregas e pendências operacionais.',
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
try {
    $data = $service->getDashboardData($_GET);
    $filter = $data['filter'];
    $competence = $data['competence'];
    $competences = $data['competences'];
    $poles = $data['poles'];
    $statistics = $data['statistics'];
    $registrations = $data['registrations'];
} catch (Throwable $e) {
    $loadError = 'Não foi possível carregar as famílias beneficiárias.';
    $filter = $service->buildFilter($_GET);
    $competence = null;
    $competences = [];
    $poles = [];
    $statistics = ['familias_cadastradas'=>0,'beneficiarias_ativas'=>0,'em_analise'=>0,'lista_espera'=>0,'suspensas'=>0,'bloqueadas'=>0,'polos_ativos'=>0,'entregas_competencia'=>0,'aguardando_retirada'=>0];
    $registrations = new App\DTO\PaginatedResult([], 0, 1, 20);
}

$currentCompetenceId = $competence ? (int) $competence['id'] : null;
$competenceLabel = $competence ? cm_month_label((int) $competence['mes'], (int) $competence['ano']) : 'Sem competência';
$frontendContext['comidaMesa']['competenciaId'] = $currentCompetenceId;
$frontendContext['comidaMesa']['competenceLabel'] = $competenceLabel;
$frontendContext['comidaMesa']['competences'] = array_map(static fn(array $item): array => [
    'id'=>(int)$item['id'],'month'=>(int)$item['mes'],'year'=>(int)$item['ano'],'status'=>(string)$item['status'],
    'startsAt'=>$item['inicio_entregas']??'','endsAt'=>$item['fim_entregas']??'','observation'=>$item['observacao']??''
], $competences);

$programStatuses = ['ativa'=>'Beneficiária ativa','em_analise'=>'Em análise','lista_espera'=>'Lista de espera','suspensa'=>'Suspensa','bloqueada'=>'Bloqueada','encerrada'=>'Encerrada'];
$deliveryStatuses = ['recebida'=>'Recebida','aguardando'=>'Aguardando retirada','bloqueada'=>'Bloqueada','indisponivel'=>'Não disponível'];
$items = $registrations->getItems();
$total = $registrations->getTotal();
$currentPage = $registrations->getPage();
$totalPages = $registrations->getTotalPages();

$importOnlyCounts = ['beneficiarios'=>0,'lista_espera'=>0];
$importOnly = ['items'=>[], 'total'=>0, 'page'=>1, 'per_page'=>50, 'total_pages'=>1];
$importOnlyError = null;
try {
    $importOnlyCounts = cm_import_confirmed_unlinked_counts(cm_db());

    // Registros confirmados na importação continuam visíveis na lista principal.
    // Pendência cadastral não é suspensão do benefício. Filtros explícitos de entrega
    // usam apenas inscrições oficiais para não classificar incorretamente registros em revisão.
    $canShowImportOnly = $filter->zone === null
        && $filter->district === null
        && $filter->community === null
        && $filter->poleId === null
        && in_array($filter->programStatus, [null, 'ativa', 'lista_espera'], true)
        && $filter->deliveryStatus === null;

    if ($canShowImportOnly) {
        $importProgramStatus = (string) ($filter->programStatus ?? '');
        $importOnlyPage = max(1, (int) ($_GET['import_page'] ?? 1));
        $importOnly = cm_import_confirmed_unlinked(cm_db(), $importProgramStatus, (string) $filter->search, $importOnlyPage, 50);
    }
} catch (Throwable $e) {
    $importOnlyError = 'Não foi possível carregar os confirmados da importação que ainda aguardam regularização cadastral.';
}

// Para CPF duplicado ou vínculo incompleto, localizar em lote o cadastro oficial já
// existente daquele CPF. Esse cadastro é o único usado para entrega, evitando benefício
// duplicado enquanto o erro humano é corrigido individualmente.
$officialByCpf = [];
if (!empty($importOnly['items'])) {
    try {
        $cpfs = [];
        foreach ($importOnly['items'] as $pendingItem) {
            $cpf = preg_replace('/\D+/', '', (string) ($pendingItem['cpf_validado'] ?? '')) ?: '';
            if (strlen($cpf) === 11) {
                $cpfs[$cpf] = $cpf;
            }
        }

        if ($cpfs) {
            $params = [];
            $placeholders = [];
            foreach (array_values($cpfs) as $index => $cpf) {
                $key = 'cpf_' . $index;
                $placeholders[] = ':' . $key;
                $params[$key] = $cpf;
            }

            if ($currentCompetenceId !== null) {
                $deliveryJoin = 'LEFT JOIN comida_mesa_entregas entrega ON entrega.inscricao_id = i.id AND entrega.competencia_id = :canonical_competencia_id';
                $params['canonical_competencia_id'] = $currentCompetenceId;
            } else {
                $deliveryJoin = 'LEFT JOIN comida_mesa_entregas entrega ON 1 = 0';
            }

            $sql = "SELECT
                    p.cpf,
                    p.nome AS responsavel_nome,
                    f.id AS familia_id,
                    f.codigo AS familia_codigo,
                    i.id AS inscricao_id,
                    i.status AS inscricao_status,
                    i.polo_id,
                    i.data_inscricao,
                    i.atualizado_em,
                    polo.nome AS polo_nome,
                    polo.ativo AS polo_ativo,
                    entrega.id AS entrega_id,
                    entrega.status AS entrega_status,
                    entrega.entregue_em AS entrega_data,
                    entrega_operador.nome AS entrega_operador_nome
                FROM pessoas p
                INNER JOIN familias f ON f.responsavel_pessoa_id = p.id
                INNER JOIN comida_mesa_inscricoes i ON i.familia_id = f.id
                LEFT JOIN comida_mesa_polos polo ON polo.id = i.polo_id
                {$deliveryJoin}
                LEFT JOIN usuarios entrega_operador ON entrega_operador.id = entrega.entregue_por
                WHERE p.cpf IN (" . implode(',', $placeholders) . ")";

            $stmt = cm_db()->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value, $key === 'canonical_competencia_id' ? PDO::PARAM_INT : PDO::PARAM_STR);
            }
            $stmt->execute();
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) ?: [] as $officialRow) {
                $cpf = preg_replace('/\D+/', '', (string) ($officialRow['cpf'] ?? '')) ?: '';
                if ($cpf !== '') {
                    $officialByCpf[$cpf] = $officialRow;
                }
            }
        }
    } catch (Throwable $e) {
        // A lista continua funcionando; apenas o atalho operacional pelo cadastro oficial fica indisponível.
        $officialByCpf = [];
    }
}

$statistics['beneficiarias_ativas'] += $importOnlyCounts['beneficiarios'];
$statistics['lista_espera'] += $importOnlyCounts['lista_espera'];
$mainVisibleCount = count($items) + count($importOnly['items']);
$mainVisibleTotal = $total + (int) $importOnly['total'];
$hasAdvanced = $filter->zone !== null || $filter->district !== null || $filter->community !== null || $filter->poleId !== null;
$exportParams = array_filter([
    'tipo' => 'beneficiarios',
    'search' => $filter->search,
    'competencia_id' => $currentCompetenceId,
    'program_status' => $filter->programStatus,
    'delivery_status' => $filter->deliveryStatus,
    'zone' => $filter->zone,
    'district' => $filter->district,
    'community' => $filter->community,
    'pole_id' => $filter->poleId,
], static fn($v) => $v !== null && $v !== '');
$exportExcelUrl = 'comida-mesa/exportar-excel.php?' . http_build_query($exportParams);

$queryForPage = static function (int $pageNumber) use ($filter): string {
    $params = array_filter([
        'search'=>$filter->search,'competencia_id'=>$filter->competenceId,'program_status'=>$filter->programStatus,
        'delivery_status'=>$filter->deliveryStatus,'zone'=>$filter->zone,'district'=>$filter->district,'community'=>$filter->community,
        'pole_id'=>$filter->poleId,'page'=>$pageNumber > 1 ? $pageNumber : null,
    ], static fn($v) => $v !== null && $v !== '');
    $q = http_build_query($params);
    return 'comida-mesa/beneficiarios.php' . ($q ? '?' . $q : '');
};

$queryForImportPage = static function (int $pageNumber) use ($filter, $currentPage): string {
    $params = array_filter([
        'search'=>$filter->search,'competencia_id'=>$filter->competenceId,'program_status'=>$filter->programStatus,
        'delivery_status'=>$filter->deliveryStatus,'zone'=>$filter->zone,'district'=>$filter->district,'community'=>$filter->community,
        'pole_id'=>$filter->poleId,'page'=>$currentPage > 1 ? $currentPage : null,
        'import_page'=>$pageNumber > 1 ? $pageNumber : null,
    ], static fn($v) => $v !== null && $v !== '');
    $q = http_build_query($params);
    return 'comida-mesa/beneficiarios.php' . ($q ? '?' . $q : '');
};

ob_start();
?>
<section class="content-card cm-list-card">
    <?php cm_list_header('Gestão do benefício', 'Famílias beneficiárias', 'Consulte, filtre e acompanhe as famílias. Pendências cadastrais são corrigidas sem suspender quem já recebe.'); ?>
    <?php cm_metrics([
        ['label'=>'Famílias cadastradas','value'=>$statistics['familias_cadastradas'],'hint'=>'Base do programa','tone'=>'neutral'],
        ['label'=>'Beneficiárias ativas','value'=>$statistics['beneficiarias_ativas'],'hint'=>'Aptas no programa','tone'=>'success'],
        ['label'=>'Em análise','value'=>$statistics['em_analise'],'hint'=>'Aguardam decisão','tone'=>'warning'],
        ['label'=>'Lista de espera','value'=>$statistics['lista_espera'],'hint'=>'Aguardam disponibilidade','tone'=>'info'],
        ['label'=>'Entregas na competência','value'=>$statistics['entregas_competencia'],'hint'=>$competenceLabel,'tone'=>'success'],
        ['label'=>'Aguardando retirada','value'=>$statistics['aguardando_retirada'],'hint'=>'Benefício ainda não retirado','tone'=>'warning'],
        ['label'=>'Suspensas/bloqueadas','value'=>$statistics['suspensas']+$statistics['bloqueadas'],'hint'=>'Exigem atenção','tone'=>'danger'],
        ['label'=>'Polos ativos','value'=>$statistics['polos_ativos'],'hint'=>'Rede de distribuição','tone'=>'neutral'],
    ]); ?>

    <?php if ($loadError): ?><div class="alert alert-danger mt-3 mb-0"><?= cm_h($loadError) ?></div><?php endif; ?>
    <?php if ($importOnlyError): ?><div class="alert alert-warning mt-3 mb-0"><?= cm_h($importOnlyError) ?></div><?php endif; ?>

    <div class="alert alert-warning mt-3 mb-0" role="alert">
        <strong><i class="bi bi-exclamation-triangle"></i> Regularização pendente não suspende o benefício.</strong>
        <div class="mt-1">CPF duplicado, dados incompletos ou vínculo de importação ficam sinalizados para correção individual. Quando o CPF já possui cadastro oficial, somente esse cadastro é usado para entrega, evitando pagamento/benefício em duplicidade.</div>
    </div>

    <div class="d-flex gap-2 flex-wrap mt-3">
        <a class="btn <?= $filter->programStatus === 'ativa' ? 'btn-success' : 'btn-light' ?>" href="comida-mesa/beneficiarios.php?program_status=ativa"><i class="bi bi-people-fill"></i> Beneficiários ativos</a>
        <a class="btn <?= $filter->programStatus === 'lista_espera' ? 'btn-warning' : 'btn-light' ?>" href="comida-mesa/beneficiarios.php?program_status=lista_espera"><i class="bi bi-hourglass-split"></i> Lista de espera</a>
        <?php if (cm_can('comida_mesa.importar') || cm_can('comida_mesa.cadastrar')): ?>
            <a class="btn btn-light" href="comida-mesa/importar-beneficiarios.php"><i class="bi bi-list-check"></i> Conferir importação</a>
        <?php endif; ?>
    </div>

    <form class="cm-filter-panel" method="get" action="comida-mesa/beneficiarios.php" data-server-filter>
        <label class="cm-filter-search"><span>Pesquisa</span><div class="cm-input-icon"><i class="bi bi-search"></i><input class="form-control" name="search" type="search" value="<?= cm_h($filter->search) ?>" placeholder="Nome, CPF, NIS ou código"></div></label>
        <label><span>Competência</span><select class="form-select" name="competencia_id"><option value="">Padrão</option><?php foreach ($competences as $item): ?><option value="<?= (int)$item['id'] ?>"<?= cm_selected($currentCompetenceId,$item['id']) ?>><?= cm_h(cm_month_label((int)$item['mes'],(int)$item['ano'])) ?></option><?php endforeach; ?></select></label>
        <label><span>Situação no programa</span><select class="form-select" name="program_status"><option value="">Todas</option><?php foreach ($programStatuses as $value=>$label): ?><option value="<?= cm_h($value) ?>"<?= cm_selected($filter->programStatus,$value) ?>><?= cm_h($label) ?></option><?php endforeach; ?></select></label>
        <label><span>Situação da entrega</span><select class="form-select" name="delivery_status"><option value="">Todas</option><?php foreach ($deliveryStatuses as $value=>$label): ?><option value="<?= cm_h($value) ?>"<?= cm_selected($filter->deliveryStatus,$value) ?>><?= cm_h($label) ?></option><?php endforeach; ?></select></label>
        <button class="btn btn-light" type="button" data-toggle-advanced aria-expanded="<?= $hasAdvanced?'true':'false' ?>"><i class="bi bi-sliders"></i> Avançados</button>
        <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button>
        <a class="btn btn-light" href="<?= cm_h($exportExcelUrl) ?>"><i class="bi bi-file-earmark-excel"></i> Exportar Excel</a>
        <a class="btn btn-light cm-filter-clear" href="comida-mesa/beneficiarios.php" title="Limpar filtros"><i class="bi bi-x-lg"></i></a>
        <div class="cm-advanced-filters<?= $hasAdvanced?' show':'' ?>" id="advancedFilters">
            <label><span>Zona</span><select class="form-select" name="zone"><option value="">Todas</option><option value="urbana"<?= cm_selected($filter->zone,'urbana') ?>>Urbana</option><option value="rural"<?= cm_selected($filter->zone,'rural') ?>>Rural</option></select></label>
            <label><span>Bairro</span><input class="form-control" name="district" value="<?= cm_h($filter->district) ?>"></label>
            <label><span>Comunidade</span><input class="form-control" name="community" value="<?= cm_h($filter->community) ?>"></label>
            <label><span>Polo</span><select class="form-select" name="pole_id"><option value="">Todos</option><?php foreach ($poles as $pole): ?><option value="<?= (int)$pole['id'] ?>"<?= cm_selected($filter->poleId,$pole['id']) ?>><?= cm_h($pole['nome']) ?></option><?php endforeach; ?></select></label>
        </div>
    </form>

    <div class="cm-table-shell">
        <div class="cm-table-toolbar">
            <div>
                <h3>Famílias beneficiárias</h3>
                <p>Exibindo <?= number_format($mainVisibleCount,0,',','.') ?> de <?= number_format($mainVisibleTotal,0,',','.') ?> registro(s) · <?= cm_h($competenceLabel) ?></p>
            </div>
            <span><i class="bi bi-shield-check"></i> Pendência cadastral é aviso de regularização, não bloqueio do benefício</span>
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
                            <th>Cadastro</th>
                            <th>Entrega</th>
                            <th>Atualização</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $row): ?>
                        <?php
                        $delivery = $service->deliveryStatusForRow($row, $competence);
                        $eligibility = $service->deliveryEligibility([
                            'status'=>(string)$row['inscricao_status'],'polo_id'=>$row['polo_id']??null,'polo_ativo'=>$row['polo_ativo']??null,
                        ], $competence, empty($row['entrega_id']) ? null : ['status'=>(string)$row['entrega_status']]);
                        $deliveryAction=(string)$eligibility['action'];
                        $canDeliverRow=cm_can('comida_mesa.entregar') && (bool)$eligibility['allowed'] && in_array($deliveryAction,['register','reactivate'],true);
                        $canCancelRow=cm_can('comida_mesa.cancelar_entrega') && (bool)$eligibility['allowed'] && $deliveryAction==='cancel';
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
                            data-can-edit="<?= cm_can('comida_mesa.editar')?'1':'0' ?>"
                            data-can-deliver="<?= $canDeliverRow?'1':'0' ?>"
                            data-can-cancel="<?= $canCancelRow?'1':'0' ?>"
                            data-can-document="<?= cm_can('comida_mesa.documentos_enviar')?'1':'0' ?>"
                            data-can-history="<?= cm_can('comida_mesa.historico_visualizar')?'1':'0' ?>">
                            <td><strong><?= cm_h($row['familia_codigo']) ?></strong></td>
                            <td><div class="cm-person-cell"><span class="cm-avatar"><?= cm_h(cm_initials((string)$row['responsavel_nome'])) ?></span><div><strong><?= cm_h($row['responsavel_nome']) ?></strong><small>CPF <?= cm_h(cm_format_cpf($row['cpf'])) ?><?= $row['nis'] ? ' · NIS '.cm_h($row['nis']) : '' ?></small></div></div></td>
                            <td><?= cm_h(cm_location($row)) ?></td>
                            <td><?= cm_h($row['polo_nome'] ?: 'Sem polo') ?></td>
                            <td><?= cm_status($service->programStatusLabel((string)$row['inscricao_status'])) ?></td>
                            <td><span class="cm-status cm-status--success">Regular</span></td>
                            <td><?= cm_status((string)$delivery['label']) ?></td>
                            <td><?= cm_h(cm_date($row['atualizado_em'] ?? $row['data_inscricao'])) ?></td>
                        </tr>
                    <?php endforeach; ?>

                    <?php foreach ($importOnly['items'] as $pending): ?>
                        <?php
                        $source = $pending['dados_origem'] ?? [];
                        $document = trim((string) ($pending['cpf_validado'] ?: $pending['cpf_informado']));
                        $digits = preg_replace('/\D+/', '', $document) ?: '';
                        $documentDisplay = strlen($digits) === 11 ? cm_format_cpf($digits) : ($document !== '' ? $document : 'Documento não informado');
                        $locationParts = array_values(array_filter([
                            $pending['bairro_origem'] ?? '',
                            $pending['endereco_origem'] ?? '',
                            $pending['local_origem'] ?? '',
                        ], static fn($v) => trim((string)$v) !== ''));
                        $isBeneficiary = (string)$pending['situacao_programa'] === 'Beneficiario';
                        $programLabel = $isBeneficiary ? 'Beneficiária ativa' : 'Lista de espera';
                        $official = strlen($digits) === 11 ? ($officialByCpf[$digits] ?? null) : null;
                        $hasOfficial = is_array($official) && !empty($official['inscricao_id']);
                        $reasonText = trim((string)($pending['efetivacao_motivo'] ?? '') . ' ' . (string)($pending['motivos'] ?? '') . ' ' . (string)($pending['classificacao'] ?? ''));
                        $reasonNormalized = mb_strtolower($reasonText, 'UTF-8');
                        $isDuplicateCpf = str_contains($reasonNormalized, 'duplic') || str_contains($reasonNormalized, 'mesmo cpf');
                        $registrationNotice = $isDuplicateCpf ? 'CPF duplicado · regularizar' : 'Regularização pendente';

                        $pendingDelivery = null;
                        $pendingEligibility = ['allowed'=>false,'action'=>'none','reason'=>'Regularize o cadastro para concluir o vínculo oficial.'];
                        $pendingCanDeliver = false;
                        $pendingCanCancel = false;
                        if ($hasOfficial) {
                            $pendingDelivery = $service->deliveryStatusForRow($official, $competence);
                            $pendingEligibility = $service->deliveryEligibility([
                                'status'=>(string)$official['inscricao_status'],
                                'polo_id'=>$official['polo_id']??null,
                                'polo_ativo'=>$official['polo_ativo']??null,
                            ], $competence, empty($official['entrega_id']) ? null : ['status'=>(string)$official['entrega_status']]);
                            $pendingDeliveryAction = (string)$pendingEligibility['action'];
                            $pendingCanDeliver = cm_can('comida_mesa.entregar') && (bool)$pendingEligibility['allowed'] && in_array($pendingDeliveryAction,['register','reactivate'],true);
                            $pendingCanCancel = cm_can('comida_mesa.cancelar_entrega') && (bool)$pendingEligibility['allowed'] && $pendingDeliveryAction === 'cancel';
                        } else {
                            $pendingDeliveryAction = 'none';
                        }
                        ?>
                        <tr class="table-warning"<?= $hasOfficial ? ' tabindex="0" data-cm-action-row' : '' ?>
                            <?php if ($hasOfficial): ?>
                            data-registration-id="<?= (int)$official['inscricao_id'] ?>"
                            data-registration-name="<?= cm_h($official['responsavel_nome'] ?: $pending['nome']) ?>"
                            data-family-code="<?= cm_h($official['familia_codigo'] ?: ('IMP-' . (int)$pending['id'])) ?>"
                            data-pole-name="<?= cm_h($official['polo_nome'] ?: 'Sem polo') ?>"
                            data-program-status="<?= cm_h($service->programStatusLabel((string)$official['inscricao_status'])) ?>"
                            data-delivery-label="<?= cm_h($pendingDelivery['label'] ?? 'Não informado') ?>"
                            data-delivery-action="<?= cm_h($pendingDeliveryAction) ?>"
                            data-delivery-title="<?= cm_h($pendingEligibility['reason'] ?? '') ?>"
                            data-delivery-date="<?= cm_h(cm_date($pendingDelivery['delivered_at'] ?? null,true)) ?>"
                            data-delivery-operator="<?= cm_h($official['entrega_operador_nome'] ?: 'Não informado') ?>"
                            data-can-edit="<?= cm_can('comida_mesa.editar')?'1':'0' ?>"
                            data-can-deliver="<?= $pendingCanDeliver?'1':'0' ?>"
                            data-can-cancel="<?= $pendingCanCancel?'1':'0' ?>"
                            data-can-document="<?= cm_can('comida_mesa.documentos_enviar')?'1':'0' ?>"
                            data-can-history="<?= cm_can('comida_mesa.historico_visualizar')?'1':'0' ?>"
                            <?php endif; ?>
                            title="<?= cm_h($pending['efetivacao_motivo'] ?: $pending['motivos'] ?: 'Regularização cadastral pendente. O benefício permanece confirmado.') ?>">
                            <td>
                                <strong>IMP-<?= (int)$pending['id'] ?></strong>
                                <small class="d-block text-muted">Carga #<?= (int)$pending['importacao_id'] ?> · Linha <?= (int)$pending['linha'] ?></small>
                                <?php if ($hasOfficial): ?><small class="d-block text-success">Usa <?= cm_h((string)$official['familia_codigo']) ?> para operações</small><?php endif; ?>
                            </td>
                            <td>
                                <div class="cm-person-cell">
                                    <span class="cm-avatar"><?= cm_h(cm_initials((string)$pending['nome'])) ?></span>
                                    <div>
                                        <strong><?= cm_h($pending['nome']) ?></strong>
                                        <small><?= cm_h($documentDisplay) ?><?= $pending['telefone_informado'] ? ' · '.cm_h($pending['telefone_informado']) : '' ?></small>
                                    </div>
                                </div>
                            </td>
                            <td><?= cm_h($locationParts ? implode(' · ', $locationParts) : 'Não informado') ?></td>
                            <td><?= cm_h($hasOfficial ? ($official['polo_nome'] ?: 'Sem polo') : ($pending['polo_informado'] ?: ($source['polo_informado'] ?? 'Sem polo'))) ?></td>
                            <td>
                                <span class="cm-status cm-status--<?= $isBeneficiary ? 'success' : 'info' ?>"><?= cm_h($programLabel) ?></span>
                                <?php if ($isBeneficiary): ?><small class="d-block mt-1 text-success">Benefício mantido durante a regularização</small><?php endif; ?>
                            </td>
                            <td>
                                <span class="cm-status cm-status--warning"><?= cm_h($registrationNotice) ?></span>
                                <?php if ($hasOfficial): ?>
                                    <small class="d-block mt-1 text-muted">Cadastro oficial localizado pelo CPF: <?= cm_h((string)$official['familia_codigo']) ?>. A pendência não cria uma segunda entrega.</small>
                                <?php else: ?>
                                    <small class="d-block mt-1 text-muted"><?= cm_h($pending['efetivacao_motivo'] ?: $pending['motivos'] ?: 'Corrija os dados sem suspender a situação no programa.') ?></small>
                                <?php endif; ?>
                                <?php if (cm_can('comida_mesa.importar') || cm_can('comida_mesa.cadastrar')): ?>
                                    <small class="d-block mt-1"><a href="comida-mesa/importar-beneficiarios.php?import_id=<?= (int)$pending['importacao_id'] ?>#lista-conferencia"><i class="bi bi-tools"></i> Corrigir cadastro</a></small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($hasOfficial && $pendingDelivery): ?>
                                    <?= cm_status((string)$pendingDelivery['label']) ?>
                                    <small class="d-block mt-1 text-muted">Operação vinculada ao cadastro oficial do CPF</small>
                                <?php elseif ($isBeneficiary): ?>
                                    <span class="cm-status cm-status--success">Benefício mantido</span>
                                    <small class="d-block mt-1 text-muted">Regularize o cadastro para registrar novas operações digitais.</small>
                                <?php else: ?>
                                    <span class="cm-status cm-status--muted">Não disponível</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?= cm_h(cm_date($pending['decidido_em'] ?: $pending['importado_em'], true)) ?>
                                <small class="d-block text-muted"><?= cm_h($pending['decisor_nome'] ?: 'Importação') ?></small>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="cm-pagination">
                    <span>Cadastros oficiais · Página <?= $currentPage ?> de <?= $totalPages ?></span>
                    <nav><?php if($currentPage>1): ?><a href="<?= cm_h($queryForPage($currentPage-1)) ?>"><i class="bi bi-chevron-left"></i></a><?php endif; ?><?php for($p=max(1,$currentPage-2);$p<=min($totalPages,$currentPage+2);$p++): ?><a class="<?= $p===$currentPage?'active':'' ?>" href="<?= cm_h($queryForPage($p)) ?>"><?= $p ?></a><?php endfor; ?><?php if($currentPage<$totalPages): ?><a href="<?= cm_h($queryForPage($currentPage+1)) ?>"><i class="bi bi-chevron-right"></i></a><?php endif; ?></nav>
                </div>
            <?php endif; ?>

            <?php if ((int)$importOnly['total_pages'] > 1): ?>
                <div class="cm-pagination">
                    <span>Regularizações da importação · Página <?= (int)$importOnly['page'] ?> de <?= (int)$importOnly['total_pages'] ?></span>
                    <nav>
                        <?php if($importOnly['page']>1): ?><a href="<?= cm_h($queryForImportPage((int)$importOnly['page']-1)) ?>"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
                        <?php for($p=max(1,(int)$importOnly['page']-2);$p<=min((int)$importOnly['total_pages'],(int)$importOnly['page']+2);$p++): ?><a class="<?= $p===(int)$importOnly['page']?'active':'' ?>" href="<?= cm_h($queryForImportPage($p)) ?>"><?= $p ?></a><?php endfor; ?>
                        <?php if($importOnly['page']<$importOnly['total_pages']): ?><a href="<?= cm_h($queryForImportPage((int)$importOnly['page']+1)) ?>"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
                    </nav>
                </div>
            <?php endif; ?>
        <?php else: ?>
            <?php cm_empty('Nenhuma família encontrada','Ajuste os filtros ou cadastre uma nova inscrição.','people'); ?>
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