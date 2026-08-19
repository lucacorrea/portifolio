<?php

declare(strict_types=1);
require_once dirname(__DIR__) . '/lib/repository.php';
require_once dirname(__DIR__) . '/lib/list-ui.php';

$pageDefinition = ['title'=>'Acompanhamentos','description'=>'Visitas sociais, pareceres técnicos e acompanhamento do participante.','demo'=>false,'show_states'=>false,'actions'=>[],'modal'=>['title'=>'Acompanhamento']];
$dbReady = pe_db_ready() && pe_schema_ready();
$programReady = $dbReady && pe_program_schema_ready();
$message = null; $candidates = []; $visits = []; $followups = [];

if ($dbReady) {
    $pdo = pe_db();
    $candidates = pe_recent_candidates($pdo, 2000);
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        try {
            pe_verify_csrf();
            $action = (string) ($_POST['pe_action'] ?? '');
            $id = (int) ($_POST['id'] ?? 0);
            if ($action === 'save_visit') {
                if ($id > 0) pe_update_visit($pdo, $id, $_POST); else pe_save_visit($pdo, $_POST);
                $message = ['type'=>'success','text'=>$id > 0 ? 'Visita social atualizada com sucesso.' : 'Visita social registrada com sucesso.'];
            } elseif ($action === 'delete_visit') {
                pe_delete_visit($pdo, $id); $message = ['type'=>'success','text'=>'Visita social excluída com sucesso.'];
            } elseif ($action === 'save_followup' && $programReady) {
                if ($id > 0) pe_update_followup($pdo, $id, $_POST, pe_current_user_label()); else pe_save_followup($pdo, $_POST, pe_current_user_label());
                $message = ['type'=>'success','text'=>$id > 0 ? 'Acompanhamento atualizado com sucesso.' : 'Acompanhamento registrado com sucesso.'];
            } elseif ($action === 'delete_followup' && $programReady) {
                pe_delete_followup($pdo, $id); $message = ['type'=>'success','text'=>'Acompanhamento excluído com sucesso.'];
            }
        } catch (Throwable $e) { $message = ['type'=>'danger','text'=>$e->getMessage()]; }
    }
    $visits = pe_social_visit_rows($pdo);
    if ($programReady) $followups = pe_followup_rows($pdo);
}

$visitMetrics = [
    ['label'=>'Visitas','value'=>count($visits),'tone'=>'neutral'],
    ['label'=>'Pendentes','value'=>pe_count_rows($visits,fn($r)=>($r['decisao']??'')==='Pendente'),'tone'=>'warning','filter_key'=>'decisao','filter_value'=>'Pendente'],
    ['label'=>'Deferidas','value'=>pe_count_rows($visits,fn($r)=>($r['decisao']??'')==='Deferido'),'tone'=>'success','filter_key'=>'decisao','filter_value'=>'Deferido'],
    ['label'=>'Indeferidas','value'=>pe_count_rows($visits,fn($r)=>($r['decisao']??'')==='Indeferido'),'tone'=>'danger','filter_key'=>'decisao','filter_value'=>'Indeferido'],
];
$followMetrics = [
    ['label'=>'Acompanhamentos','value'=>count($followups),'tone'=>'neutral'],
    ['label'=>'Regulares','value'=>pe_count_rows($followups,fn($r)=>($r['status']??'')==='Regular'),'tone'=>'success','filter_key'=>'status','filter_value'=>'Regular'],
    ['label'=>'Atenção','value'=>pe_count_rows($followups,fn($r)=>($r['status']??'')==='Atenção'),'tone'=>'warning','filter_key'=>'status','filter_value'=>'Atenção'],
    ['label'=>'Pendentes','value'=>pe_count_rows($followups,fn($r)=>($r['status']??'')==='Pendente'),'tone'=>'warning','filter_key'=>'status','filter_value'=>'Pendente'],
    ['label'=>'Concluídos','value'=>pe_count_rows($followups,fn($r)=>($r['status']??'')==='Concluído'),'tone'=>'success','filter_key'=>'status','filter_value'=>'Concluído'],
];

ob_start(); ?>
<section class="content-card pe-form-card pe-page pe-list-page">
<?php if (!$dbReady): ?><?= pe_db_notice() ?><?php endif; ?>
<?php if ($message): ?><div class="alert alert-<?= pe_h($message['type']) ?>"><?= pe_h($message['text']) ?></div><?php endif; ?>

<div class="pe-page-hero pe-list-hero"><div><div class="card-kicker">Assistência Social</div><h2>Visitas e acompanhamentos</h2><p>Indicadores, filtros e listagens separados por fluxo. Todas as ações são realizadas em modais.</p></div><div class="pe-page-actions pe-no-print"><button class="btn btn-primary" type="button" data-pe-open="#peVisitForm" data-pe-mode="create"><i class="bi bi-house-check"></i> Nova visita</button><?php if ($programReady): ?><button class="btn btn-light" type="button" data-pe-open="#peFollowupForm" data-pe-mode="create"><i class="bi bi-clipboard2-pulse"></i> Novo acompanhamento</button><?php endif; ?></div></div>

<div class="pe-list-section">
    <div class="pe-list-section__head"><div><h3>Visitas sociais</h3><p>Pareceres e decisões das visitas realizadas.</p></div></div>
    <?php pe_list_metrics($visitMetrics, '#peVisitFilters'); ?>
    <?php pe_list_filter_panel('peVisitTable','Buscar candidato, entrevistador, técnico ou parecer...',[
        ['key'=>'decisao','label'=>'Decisão','options'=>pe_filter_options($visits,'decisao','Todas')],
        ['key'=>'entrevistador','label'=>'Entrevistador','options'=>pe_filter_options($visits,'entrevistador','Todos')],
        ['key'=>'tecnico','label'=>'Técnico responsável','options'=>pe_filter_options($visits,'tecnico_responsavel','Todos')],
    ],count($visits),'visita(s)'); ?>
    <div class="pe-table-wrap"><div class="table-responsive"><table id="peVisitTable" class="table align-middle pe-data-table pe-list-table" data-pe-list-table><thead><tr><th>Data</th><th>Candidato</th><th>Entrevistador</th><th>Decisão</th><th>Técnico</th></tr></thead><tbody>
    <?php if (!$visits): ?><tr class="pe-empty-row"><td colspan="5" class="text-center text-muted py-4">Nenhuma visita registrada.</td></tr><?php endif; ?>
    <?php foreach ($visits as $r): $record=$r; $record['__title']=$r['candidato']; $record['__subtitle']='Visita em '.date('d/m/Y',strtotime((string)$r['data_visita'])).' · '.$r['decisao']; $record['data_visita_formatada']=date('d/m/Y',strtotime((string)$r['data_visita'])); ?>
    <tr class="pe-list-row" tabindex="0" role="button" data-pe-list-row data-pe-actions-target="#peVisitActions" data-pe-record="<?= pe_record_attr($record) ?>" data-pe-filter-decisao="<?= pe_h($r['decisao']) ?>" data-pe-filter-entrevistador="<?= pe_h($r['entrevistador'] ?: '') ?>" data-pe-filter-tecnico="<?= pe_h($r['tecnico_responsavel'] ?: '') ?>"><td data-label="Data"><?= pe_h($record['data_visita_formatada']) ?></td><td data-label="Candidato"><strong><?= pe_h($r['candidato']) ?></strong></td><td data-label="Entrevistador"><?= pe_h($r['entrevistador'] ?: '—') ?></td><td data-label="Decisão"><?= pe_status_label($r['decisao']) ?></td><td data-label="Técnico"><?= pe_h($r['tecnico_responsavel'] ?: '—') ?></td></tr>
    <?php endforeach; ?></tbody></table></div><div class="pe-filter-empty" data-pe-filter-empty hidden>Nenhuma visita corresponde aos filtros.</div></div>
</div>

<?php if ($programReady): ?>
<div class="pe-list-section mt-4">
    <div class="pe-list-section__head"><div><h3>Acompanhamentos do programa</h3><p>Orientações, avaliações e próximas ações dos participantes.</p></div></div>
    <?php pe_list_metrics($followMetrics, '#peFollowupFilters'); ?>
    <?php pe_list_filter_panel('peFollowupTable','Buscar candidato, tipo, local, responsável ou próxima ação...',[
        ['key'=>'status','label'=>'Status','options'=>pe_filter_options($followups,'status','Todos')],
        ['key'=>'tipo','label'=>'Tipo','options'=>pe_filter_options($followups,'tipo','Todos')],
        ['key'=>'local','label'=>'Local de atuação','options'=>pe_filter_options($followups,'parceiro','Todos')],
        ['key'=>'responsavel','label'=>'Responsável','options'=>pe_filter_options($followups,'responsavel','Todos')],
    ],count($followups),'registro(s)'); ?>
    <div class="pe-table-wrap"><div class="table-responsive"><table id="peFollowupTable" class="table align-middle pe-data-table pe-list-table" data-pe-list-table><thead><tr><th>Data</th><th>Candidato</th><th>Tipo</th><th>Resumo</th><th>Local</th><th>Status</th><th>Responsável</th></tr></thead><tbody>
    <?php if (!$followups): ?><tr class="pe-empty-row"><td colspan="7" class="text-center text-muted py-4">Nenhum acompanhamento registrado.</td></tr><?php endif; ?>
    <?php foreach ($followups as $r): $record=$r; $record['__title']=$r['candidato']; $record['__subtitle']=$r['tipo'].' · '.$r['status']; $record['data_acompanhamento_formatada']=date('d/m/Y',strtotime((string)$r['data_acompanhamento'])); $record['data_proxima_acao_formatada']=$r['data_proxima_acao']?date('d/m/Y',strtotime((string)$r['data_proxima_acao'])):'—'; ?>
    <tr class="pe-list-row" tabindex="0" role="button" data-pe-list-row data-pe-actions-target="#peFollowupActions" data-pe-record="<?= pe_record_attr($record) ?>" data-pe-filter-status="<?= pe_h($r['status']) ?>" data-pe-filter-tipo="<?= pe_h($r['tipo']) ?>" data-pe-filter-local="<?= pe_h($r['parceiro'] ?: '') ?>" data-pe-filter-responsavel="<?= pe_h($r['responsavel'] ?: '') ?>"><td data-label="Data"><?= pe_h($record['data_acompanhamento_formatada']) ?></td><td data-label="Candidato"><strong><?= pe_h($r['candidato']) ?></strong></td><td data-label="Tipo"><?= pe_h($r['tipo']) ?></td><td data-label="Resumo"><?= pe_h(mb_strimwidth((string)$r['resumo'],0,70,'…')) ?></td><td data-label="Local"><?= pe_h($r['parceiro'] ?: '—') ?></td><td data-label="Status"><?= pe_status_label($r['status']) ?></td><td data-label="Responsável"><?= pe_h($r['responsavel'] ?: '—') ?></td></tr>
    <?php endforeach; ?></tbody></table></div><div class="pe-filter-empty" data-pe-filter-empty hidden>Nenhum acompanhamento corresponde aos filtros.</div></div>
</div>
<?php endif; ?>
<?php pe_crud_actions_dialog('peVisitActions','Visita social','#peVisitView','#peVisitForm','#peVisitDelete'); ?>
<?php if($programReady) pe_crud_actions_dialog('peFollowupActions','Acompanhamento','#peFollowupView','#peFollowupForm','#peFollowupDelete'); ?>
<dialog class="pe-modal pe-modal--form" id="peVisitForm" data-pe-create-title="Nova visita social" data-pe-edit-title="Editar visita social"><div class="pe-modal__shell"><header class="pe-modal__header"><div><div class="card-kicker">Assistência Social</div><h2 data-pe-form-title>Nova visita social</h2><p>Registre a visita e o parecer técnico.</p></div><button type="button" class="pe-modal__close" data-pe-dialog-close><i class="bi bi-x-lg"></i></button></header><div class="pe-modal__body"><form method="post" class="pe-action-form" data-pe-record-form><?=pe_csrf_field()?><input type="hidden" name="pe_action" value="save_visit"><input type="hidden" name="id" data-pe-field="id"><div class="pe-action-form-grid pe-action-form-grid--3"><div class="pe-field-span-2"><label class="form-label required">Candidato</label><select class="form-select" name="candidato_id" data-pe-field="candidato_id" required><option value="">Selecione...</option><?php foreach($candidates as $c):?><option value="<?=(int)$c['id']?>"><?=pe_h($c['nome'])?></option><?php endforeach;?></select></div><div><label class="form-label">Data</label><input class="form-control" type="date" name="data_visita" data-pe-field="data_visita" value="<?=date('Y-m-d')?>"></div><div><label class="form-label">Entrevistador(a)</label><input class="form-control" name="entrevistador" data-pe-field="entrevistador" maxlength="160"></div><div><label class="form-label">Decisão</label><select class="form-select" name="decisao" data-pe-field="decisao"><option>Pendente</option><option>Deferido</option><option>Indeferido</option></select></div><div><label class="form-label">Técnico responsável</label><input class="form-control" name="tecnico_responsavel" data-pe-field="tecnico_responsavel" maxlength="160"></div><div class="pe-field-span-3"><label class="form-label">Informações complementares</label><textarea class="form-control" name="informacoes_complementares" data-pe-field="informacoes_complementares" rows="4"></textarea></div><div class="pe-field-span-3"><label class="form-label required">Parecer técnico</label><textarea class="form-control" name="parecer_tecnico" data-pe-field="parecer_tecnico" rows="6" required></textarea></div></div><footer class="pe-action-modal-footer"><button type="button" class="btn btn-light" data-pe-dialog-close>Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Salvar visita</button></footer></form></div></div></dialog>
<dialog class="pe-modal pe-modal--view" id="peVisitView"><div class="pe-modal__shell"><header class="pe-modal__header"><div><div class="card-kicker">Visita social</div><h2 data-pe-current-title>Detalhes</h2><p data-pe-current-subtitle></p></div><button type="button" class="pe-modal__close" data-pe-dialog-close><i class="bi bi-x-lg"></i></button></header><div class="pe-modal__body"><dl class="pe-modal-details pe-modal-details--2"><div><dt>Data</dt><dd data-pe-text="data_visita_formatada">—</dd></div><div><dt>Decisão</dt><dd data-pe-text="decisao">—</dd></div><div><dt>Entrevistador</dt><dd data-pe-text="entrevistador">—</dd></div><div><dt>Técnico</dt><dd data-pe-text="tecnico_responsavel">—</dd></div></dl><div class="pe-view-note"><strong>Informações complementares</strong><p data-pe-text="informacoes_complementares">—</p></div><div class="pe-view-note"><strong>Parecer técnico</strong><p data-pe-text="parecer_tecnico">—</p></div></div></div></dialog>
<?php pe_delete_dialog('peVisitDelete','visita social','delete_visit'); ?>
<?php if($programReady):?><dialog class="pe-modal pe-modal--form" id="peFollowupForm" data-pe-create-title="Novo acompanhamento" data-pe-edit-title="Editar acompanhamento"><div class="pe-modal__shell"><header class="pe-modal__header"><div><div class="card-kicker">Pós-encaminhamento</div><h2 data-pe-form-title>Novo acompanhamento</h2><p>Registre orientação, avaliação ou próxima ação.</p></div><button type="button" class="pe-modal__close" data-pe-dialog-close><i class="bi bi-x-lg"></i></button></header><div class="pe-modal__body"><form method="post" class="pe-action-form" data-pe-record-form><?=pe_csrf_field()?><input type="hidden" name="pe_action" value="save_followup"><input type="hidden" name="id" data-pe-field="id"><div class="pe-action-form-grid pe-action-form-grid--3"><div class="pe-field-span-2"><label class="form-label required">Candidato</label><select class="form-select" name="candidato_id" data-pe-field="candidato_id" required><option value="">Selecione...</option><?php foreach($candidates as $c):?><option value="<?=(int)$c['id']?>"><?=pe_h($c['nome'])?></option><?php endforeach;?></select></div><div><label class="form-label">Data</label><input class="form-control" type="date" name="data_acompanhamento" data-pe-field="data_acompanhamento" value="<?=date('Y-m-d')?>"></div><div><label class="form-label">Tipo</label><select class="form-select" name="tipo" data-pe-field="tipo"><?php foreach(['Contato mensal','Orientação','Avaliação','Visita ao local','Outro'] as $v):?><option><?=pe_h($v)?></option><?php endforeach;?></select></div><div><label class="form-label">Status</label><select class="form-select" name="status" data-pe-field="status"><?php foreach(['Regular','Atenção','Pendente','Concluído'] as $v):?><option><?=pe_h($v)?></option><?php endforeach;?></select></div><div><label class="form-label">Próxima ação em</label><input class="form-control" type="date" name="data_proxima_acao" data-pe-field="data_proxima_acao"></div><div class="pe-field-span-3"><label class="form-label required">Resumo</label><textarea class="form-control" name="resumo" data-pe-field="resumo" rows="4" required></textarea></div><div class="pe-field-span-3"><label class="form-label">Próxima ação</label><input class="form-control" name="proxima_acao" data-pe-field="proxima_acao"></div></div><footer class="pe-action-modal-footer"><button type="button" class="btn btn-light" data-pe-dialog-close>Cancelar</button><button class="btn btn-primary" type="submit">Salvar acompanhamento</button></footer></form></div></div></dialog><dialog class="pe-modal pe-modal--view" id="peFollowupView"><div class="pe-modal__shell"><header class="pe-modal__header"><div><div class="card-kicker">Acompanhamento</div><h2 data-pe-current-title>Detalhes</h2><p data-pe-current-subtitle></p></div><button type="button" class="pe-modal__close" data-pe-dialog-close><i class="bi bi-x-lg"></i></button></header><div class="pe-modal__body"><dl class="pe-modal-details pe-modal-details--3"><div><dt>Data</dt><dd data-pe-text="data_acompanhamento_formatada">—</dd></div><div><dt>Tipo</dt><dd data-pe-text="tipo">—</dd></div><div><dt>Status</dt><dd data-pe-text="status">—</dd></div><div><dt>Responsável</dt><dd data-pe-text="responsavel">—</dd></div><div><dt>Próxima ação em</dt><dd data-pe-text="data_proxima_acao_formatada">—</dd></div><div><dt>Local</dt><dd data-pe-text="parceiro">—</dd></div></dl><div class="pe-view-note"><strong>Resumo</strong><p data-pe-text="resumo">—</p></div><div class="pe-view-note"><strong>Próxima ação</strong><p data-pe-text="proxima_acao">—</p></div></div></div></dialog><?php pe_delete_dialog('peFollowupDelete','acompanhamento','delete_followup'); ?><?php endif;?>
</section>
<?php $pageCustomContent=(string)ob_get_clean();
