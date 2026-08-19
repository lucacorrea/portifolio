<?php

declare(strict_types=1);

$pageDefinition=[
    'title'=>'Painel','description'=>'Visão executiva e operacional do Coari Comida na Mesa com dados reais do SIGAS.',
    'actions'=>[
        ['label'=>'Nova inscrição','icon'=>'person-plus','primary'=>true,'href'=>'comida-mesa/nova-inscricao.php'],
        ['label'=>'Registrar entrega','icon'=>'box-seam','href'=>'comida-mesa/registrar-entrega.php'],
        ['label'=>'Relatórios','icon'=>'bar-chart-line','href'=>'comida-mesa/relatorios.php'],
    ],'demo'=>false,'show_states'=>false,
];
$competence=$moduleRepository->defaultCompetence(); $competenceId=$competence?(int)$competence['id']:null; $competenceLabel=$competence?cm_month_label((int)$competence['mes'],(int)$competence['ano']):'Sem competência';
$stats=$moduleRepository->dashboardStats($competenceId); $monthly=$moduleRepository->monthlyDeliveries(8); $program=$moduleRepository->programStatusDistribution(); $delivery=$moduleRepository->deliveryDistribution($competenceId); $poles=$moduleRepository->topPoles($competenceId,8); $recent=$moduleRepository->histories([],10);
$chartPayload=['monthly'=>$monthly,'program'=>$program,'delivery'=>$delivery,'poles'=>array_map(static fn($r)=>['label'=>(string)$r['nome'],'value'=>(int)$r['familias']],$poles)];
$pageDefinition['stats']=[
['label'=>'Beneficiárias ativas','value'=>(string)($stats['ativas']??0),'detail'=>'Aptas no programa','icon'=>'people'],
['label'=>'Entregas','value'=>(string)($stats['entregas']??0),'detail'=>$competenceLabel,'icon'=>'box-seam'],
['label'=>'Aguardando retirada','value'=>(string)($stats['aguardando']??0),'detail'=>'Competência atual','icon'=>'hourglass-split'],
['label'=>'Execução','value'=>number_format((float)($stats['execucao_percentual']??0),1,',','.').'%','detail'=>'Ativas x entregues','icon'=>'graph-up-arrow'],
['label'=>'Em análise','value'=>(string)($stats['em_analise']??0),'detail'=>'Aguardam decisão','icon'=>'clipboard-check'],
['label'=>'Restrições','value'=>(string)($stats['restricoes']??0),'detail'=>'Suspensas ou bloqueadas','icon'=>'shield-exclamation'],
['label'=>'Polos ativos','value'=>(string)($stats['polos_ativos']??0),'detail'=>'Rede de distribuição','icon'=>'geo-alt'],
['label'=>'Documentos','value'=>(string)($stats['documentos']??0),'detail'=>'Arquivos vinculados','icon'=>'folder2-open'],
];
ob_start();
?>
<section class="cm-dashboard-grid">
<article class="content-card cm-dashboard-card cm-dashboard-card--wide"><div class="cm-card-head"><div><div class="card-kicker">Execução mensal</div><h2>Entregas por competência</h2><p>Acompanha a evolução real das distribuições mensais.</p></div><span class="cm-context-chip"><?= cm_h($competenceLabel) ?></span></div><div class="cm-chart"><canvas id="cmChartMonthly"></canvas></div></article>
<article class="content-card cm-dashboard-card"><div class="cm-card-head"><div><div class="card-kicker">Situação do programa</div><h2>Inscrições</h2></div></div><div class="cm-chart cm-chart--donut"><canvas id="cmChartProgram"></canvas></div></article>
<article class="content-card cm-dashboard-card"><div class="cm-card-head"><div><div class="card-kicker">Retirada do benefício</div><h2>Entregas da competência</h2></div></div><div class="cm-chart cm-chart--donut"><canvas id="cmChartDelivery"></canvas></div></article>
<article class="content-card cm-dashboard-card cm-dashboard-card--wide"><div class="cm-card-head"><div><div class="card-kicker">Distribuição territorial</div><h2>Famílias por polo</h2><p>Concentração dos vínculos nos pontos de retirada.</p></div><a class="btn btn-light" href="comida-mesa/polos.php">Ver polos</a></div><div class="cm-chart"><canvas id="cmChartPoles"></canvas></div></article>
</section>
<section class="content-card cm-list-card mt-3"><div class="cm-list-hero"><div><div class="card-kicker">Movimentações</div><h2>Atividade recente</h2><p>Últimos eventos registrados no histórico do programa.</p></div><a class="btn btn-light" href="comida-mesa/historico.php"><i class="bi bi-clock-history"></i> Histórico completo</a></div><?php if($recent): ?><div class="cm-activity-list"><?php foreach($recent as $row): ?><article><span class="cm-activity-icon"><i class="bi bi-clock-history"></i></span><div><strong><?= cm_h($row['acao']) ?></strong><span><?= cm_h($row['familia_codigo'].' · '.$row['responsavel_nome']) ?><?= $row['descricao']?' · '.cm_h($row['descricao']):'' ?></span></div><time><?= cm_h(cm_date($row['criado_em'],true)) ?></time></article><?php endforeach; ?></div><?php else: ?><?php cm_empty('Nenhuma movimentação ainda','As operações realizadas aparecerão aqui.','clock-history'); ?><?php endif; ?></section>
<script id="cmChartPayload" type="application/json"><?= json_encode($chartPayload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?></script>
<?php $pageCustomContent=(string)ob_get_clean(); ?>
