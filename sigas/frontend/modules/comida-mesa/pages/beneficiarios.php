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
    $canShowImportOnly = $filter->deliveryStatus === null
        && $filter->zone === null
        && $filter->district === null
        && $filter->community === null
        && $filter->poleId === null;
    if ($canShowImportOnly) {
        $importOnlyPage = max(1, (int) ($_GET['import_page'] ?? 1));
        $importOnly = cm_import_confirmed_unlinked(cm_db(), (string) ($filter->programStatus ?? ''), (string) $filter->search, $importOnlyPage, 50);
    }
} catch (Throwable $e) {
    $importOnlyError = 'Não foi possível carregar os confirmados da importação que ainda aguardam vínculo cadastral.';
}

$statistics['beneficiarias_ativas'] += $importOnlyCounts['beneficiarios'];
$statistics['lista_espera'] += $importOnlyCounts['lista_espera'];
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
    <?php cm_list_header('Gestão do benefício', 'Famílias beneficiárias', 'Consulte, filtre e acompanhe as famílias. Clique em uma linha para abrir a central de ações.'); ?>
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
        <div class="cm-table-toolbar"><div><h3>Famílias beneficiárias</h3><p>Exibindo <?= count($items) ?> de <?= number_format($total,0,',','.') ?> registro(s) · <?= cm_h($competenceLabel) ?></p></div><span><i class="bi bi-cursor"></i> Clique em uma linha para abrir as ações</span></div>
        <?php if ($items): ?>
            <div class="table-responsive"><table class="cm-data-table"><thead><tr><th>Código</th><th>Responsável familiar</th><th>Localidade</th><th>Polo</th><th>Situação no programa</th><th>Entrega</th><th>Atualização</th></tr></thead><tbody>
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
                    <td><?= cm_status((string)$delivery['label']) ?></td>
                    <td><?= cm_h(cm_date($row['atualizado_em'] ?? $row['data_inscricao'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody></table></div>
            <div class="cm-pagination"><span>Página <?= $currentPage ?> de <?= $totalPages ?></span><nav><?php if($currentPage>1): ?><a href="<?= cm_h($queryForPage($currentPage-1)) ?>"><i class="bi bi-chevron-left"></i></a><?php endif; ?><?php for($p=max(1,$currentPage-2);$p<=min($totalPages,$currentPage+2);$p++): ?><a class="<?= $p===$currentPage?'active':'' ?>" href="<?= cm_h($queryForPage($p)) ?>"><?= $p ?></a><?php endfor; ?><?php if($currentPage<$totalPages): ?><a href="<?= cm_h($queryForPage($currentPage+1)) ?>"><i class="bi bi-chevron-right"></i></a><?php endif; ?></nav></div>
        <?php else: ?><?php cm_empty('Nenhuma família encontrada','Ajuste os filtros ou cadastre uma nova inscrição.','people'); ?><?php endif; ?>
    </div>

    <?php if ($importOnlyError): ?>
        <div class="alert alert-warning mt-3"><?= cm_h($importOnlyError) ?></div>
    <?php elseif (!empty($importOnly['items'])): ?>
        <?php
        $importTitle = $filter->programStatus === 'ativa'
            ? 'Beneficiários confirmados com cadastro pendente'
            : ($filter->programStatus === 'lista_espera'
                ? 'Lista de espera confirmada com cadastro pendente'
                : 'Confirmados pela importação com cadastro pendente');
        ?>
        <div class="cm-table-shell mt-4" id="confirmados-importacao">
            <div class="cm-table-toolbar">
                <div>
                    <h3><?= cm_h($importTitle) ?></h3>
                    <p>Já estão na lista definida na conferência, mas ainda não possuem <code>inscricao_id</code> por alguma pendência de vínculo cadastral.</p>
                </div>
                <span><i class="bi bi-shield-exclamation"></i> Entrega bloqueada até concluir o vínculo</span>
            </div>
            <div class="table-responsive">
                <table class="cm-data-table">
                    <thead><tr><th>Código</th><th>Responsável</th><th>Localidade</th><th>Origem</th><th>Situação no programa</th><th>Cadastro</th><th>Confirmação</th></tr></thead>
                    <tbody>
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
                        ?>
                        <tr>
                            <td><strong>IMP-<?= (int)$pending['id'] ?></strong><small class="d-block text-muted">Carga #<?= (int)$pending['importacao_id'] ?></small></td>
                            <td><div class="cm-person-cell"><span class="cm-avatar"><?= cm_h(cm_initials((string)$pending['nome'])) ?></span><div><strong><?= cm_h($pending['nome']) ?></strong><small><?= cm_h($documentDisplay) ?> · <?= cm_h($pending['telefone_informado'] ?: 'Sem telefone') ?></small></div></div></td>
                            <td><?= cm_h($locationParts ? implode(' · ', $locationParts) : 'Não informado') ?></td>
                            <td><strong><?= cm_h($pending['arquivo_nome']) ?></strong><small class="d-block text-muted">Linha <?= (int)$pending['linha'] ?></small></td>
                            <td><span class="cm-status cm-status--<?= $isBeneficiary ? 'success' : 'info' ?>"><?= $isBeneficiary ? 'Beneficiária ativa' : 'Lista de espera' ?></span></td>
                            <td><span class="cm-status cm-status--warning">Cadastro pendente</span><small class="d-block mt-1 text-muted"><?= cm_h($pending['efetivacao_motivo'] ?: $pending['motivos'] ?: 'Necessário concluir o vínculo ao cadastro central.') ?></small></td>
                            <td><?= cm_h(cm_date($pending['decidido_em'], true)) ?><small class="d-block text-muted"><?= cm_h($pending['decisor_nome'] ?: 'Não informado') ?></small></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="cm-pagination">
                <span>Exibindo <?= count($importOnly['items']) ?> de <?= number_format((int)$importOnly['total'],0,',','.') ?> registro(s) · Página <?= (int)$importOnly['page'] ?> de <?= (int)$importOnly['total_pages'] ?></span>
                <nav>
                    <?php if($importOnly['page']>1): ?><a href="<?= cm_h($queryForImportPage((int)$importOnly['page']-1)) ?>#confirmados-importacao"><i class="bi bi-chevron-left"></i></a><?php endif; ?>
                    <?php for($p=max(1,(int)$importOnly['page']-2);$p<=min((int)$importOnly['total_pages'],(int)$importOnly['page']+2);$p++): ?><a class="<?= $p===(int)$importOnly['page']?'active':'' ?>" href="<?= cm_h($queryForImportPage($p)) ?>#confirmados-importacao"><?= $p ?></a><?php endfor; ?>
                    <?php if($importOnly['page']<$importOnly['total_pages']): ?><a href="<?= cm_h($queryForImportPage((int)$importOnly['page']+1)) ?>#confirmados-importacao"><i class="bi bi-chevron-right"></i></a><?php endif; ?>
                </nav>
            </div>
        </div>
    <?php endif; ?>
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
