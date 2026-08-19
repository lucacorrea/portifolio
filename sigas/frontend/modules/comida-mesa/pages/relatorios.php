<?php

declare(strict_types=1);

$pageDefinition=['title'=>'Relatórios','description'=>'Análise consolidada de beneficiários, entregas, território e execução por competência.','actions'=>[],'demo'=>false,'show_states'=>false];
$competences=cm_app()['repository']->listCompetences();
$selectedCompetenceId=isset($_GET['competencia_id']) && preg_match('/^\d+$/',(string)$_GET['competencia_id'])===1 ? (int)$_GET['competencia_id'] : null;
if($selectedCompetenceId===null){$default=$moduleRepository->defaultCompetence();$selectedCompetenceId=$default?(int)$default['id']:null;}
$selectedCompetence=null; foreach($competences as $c){if((int)$c['id']===$selectedCompetenceId){$selectedCompetence=$c;break;}}
$report=$moduleRepository->reportOverview($selectedCompetenceId); $stats=$report['stats'];
$competenceLabel=$selectedCompetence?cm_month_label((int)$selectedCompetence['mes'],(int)$selectedCompetence['ano']):'Sem competência';

if(($_GET['export']??'')==='csv'){
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="comida-na-mesa-relatorio-'.date('Ymd-His').'.csv"');
    echo "\xEF\xBB\xBF";
    $out=fopen('php://output','wb');
    fputcsv($out,['RELATÓRIO COARI COMIDA NA MESA'], ';');
    fputcsv($out,['Competência',$competenceLabel], ';');
    fputcsv($out,[], ';');
    fputcsv($out,['INDICADOR','VALOR'], ';');
    foreach([
        'Inscrições'=>$stats['inscricoes']??0,'Beneficiárias ativas'=>$stats['ativas']??0,'Em análise'=>$stats['em_analise']??0,
        'Lista de espera'=>$stats['lista_espera']??0,'Restrições'=>$stats['restricoes']??0,'Entregas'=>$stats['entregas']??0,
        'Aguardando retirada'=>$stats['aguardando']??0,'Execução (%)'=>$stats['execucao_percentual']??0,
    ] as $label=>$value){fputcsv($out,[$label,$value],';');}
    fputcsv($out,[], ';'); fputcsv($out,['POLO','FAMÍLIAS','ATIVAS','ENTREGAS','SITUAÇÃO'], ';');
    foreach($report['poles'] as $p){fputcsv($out,[$p['nome'],$p['familias'],$p['ativas'],$p['entregas'],((int)$p['ativo']===1?'Ativo':'Inativo')],';');}
    fclose($out); exit;
}

$chartPayload=[
    'monthly'=>$report['monthly'], 'program'=>$report['status'], 'delivery'=>$report['delivery'],
    'poles'=>array_map(static fn($r)=>['label'=>(string)$r['nome'],'value'=>(int)$r['familias']],$report['poles']),
    'zones'=>$report['zones'],'districts'=>$report['districts'],
];
ob_start();
?>
<section class="content-card cm-list-card cm-report-page">
<?php cm_list_header('Análise gerencial','Relatórios do Comida na Mesa','Indicadores e gráficos reais do banco do SIGAS para acompanhamento e prestação de informações.'); ?>
<form class="cm-filter-panel cm-filter-panel--report" method="get" action="comida-mesa/relatorios.php"><label><span>Competência</span><select class="form-select" name="competencia_id"><option value="">Padrão</option><?php foreach($competences as $c): ?><option value="<?= (int)$c['id'] ?>"<?= cm_selected($selectedCompetenceId,$c['id']) ?>><?= cm_h(cm_month_label((int)$c['mes'],(int)$c['ano'])) ?> — <?= cm_h(cm_competence_label((string)$c['status'])) ?></option><?php endforeach; ?></select></label><button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Aplicar</button><a class="btn btn-light" href="comida-mesa/relatorios.php?competencia_id=<?= (int)$selectedCompetenceId ?>&export=csv"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV</a><button class="btn btn-light" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir / PDF</button></form>
<?php cm_metrics([
['label'=>'Inscrições','value'=>$stats['inscricoes']??0,'hint'=>'Base total','tone'=>'neutral'],['label'=>'Beneficiárias ativas','value'=>$stats['ativas']??0,'hint'=>'Aptas no programa','tone'=>'success'],['label'=>'Entregas','value'=>$stats['entregas']??0,'hint'=>$competenceLabel,'tone'=>'success'],['label'=>'Aguardando retirada','value'=>$stats['aguardando']??0,'hint'=>'Na competência','tone'=>'warning'],['label'=>'Execução','value'=>number_format((float)($stats['execucao_percentual']??0),1,',','.').'%','hint'=>'Ativas x entregues','tone'=>'info'],['label'=>'Restrições','value'=>$stats['restricoes']??0,'hint'=>'Suspensas/bloqueadas','tone'=>'danger'],
]); ?>
<div class="cm-report-grid">
<article class="cm-chart-card cm-chart-card--wide"><div><div class="card-kicker">Evolução</div><h3>Entregas por competência</h3><p>Histórico mensal consolidado.</p></div><div class="cm-chart"><canvas id="cmChartMonthly"></canvas></div></article>
<article class="cm-chart-card"><div><div class="card-kicker">Programa</div><h3>Situação das inscrições</h3></div><div class="cm-chart cm-chart--donut"><canvas id="cmChartProgram"></canvas></div></article>
<article class="cm-chart-card"><div><div class="card-kicker">Competência</div><h3>Situação das entregas</h3></div><div class="cm-chart cm-chart--donut"><canvas id="cmChartDelivery"></canvas></div></article>
<article class="cm-chart-card"><div><div class="card-kicker">Rede</div><h3>Famílias por polo</h3></div><div class="cm-chart"><canvas id="cmChartPoles"></canvas></div></article>
<article class="cm-chart-card"><div><div class="card-kicker">Território</div><h3>Distribuição por zona</h3></div><div class="cm-chart cm-chart--donut"><canvas id="cmChartZones"></canvas></div></article>
<article class="cm-chart-card cm-chart-card--wide"><div><div class="card-kicker">Território</div><h3>Principais bairros</h3></div><div class="cm-chart"><canvas id="cmChartDistricts"></canvas></div></article>
</div>
<div class="cm-table-shell mt-3"><div class="cm-table-toolbar"><div><h3>Execução por polo</h3><p><?= cm_h($competenceLabel) ?></p></div></div><?php if($report['poles']): ?><div class="table-responsive"><table class="cm-data-table"><thead><tr><th>Polo</th><th>Famílias vinculadas</th><th>Beneficiárias ativas</th><th>Entregas</th><th>Cobertura sobre ativas</th><th>Situação</th></tr></thead><tbody><?php foreach($report['poles'] as $p): $coverage=(int)$p['ativas']>0?round(((int)$p['entregas']/(int)$p['ativas'])*100,1):0; ?><tr><td><strong><?= cm_h($p['nome']) ?></strong></td><td><?= (int)$p['familias'] ?></td><td><?= (int)$p['ativas'] ?></td><td><?= (int)$p['entregas'] ?></td><td><?= cm_h(number_format($coverage,1,',','.')) ?>%</td><td><?= cm_status((int)$p['ativo']===1?'Ativo':'Inativo') ?></td></tr><?php endforeach; ?></tbody></table></div><?php else: ?><?php cm_empty('Sem dados por polo','Cadastre polos e vincule as famílias para consolidar esta análise.','bar-chart'); ?><?php endif; ?></div>
</section>
<script id="cmChartPayload" type="application/json"><?= json_encode($chartPayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?></script>
<?php $pageCustomContent=(string)ob_get_clean(); ?>
