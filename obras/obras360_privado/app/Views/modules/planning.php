<div class="page-header"><div><span class="eyebrow">GESTÃO DO TEMPO</span><h1>Planejamento e cronogramas</h1><p>Etapas de execução, prazos e marcos de entrega por obra.</p></div><div class="header-actions"><a class="btn btn-outline" href="<?= h(url('obras')) ?>"><?= ic('hard-hat') ?> Obras</a><?php if (\App\Core\Auth::canWrite()): ?><a class="btn btn-primary" href="<?= h(url('marcos',['action'=>'create'])) ?>"><?= ic('plus') ?> Novo marco</a><?php endif; ?></div></div>
<div class="module-metrics"><div class="small-stat"><span>Obras no cronograma</span><strong><?= count($works) ?></strong></div><div class="small-stat"><span>Marcos cadastrados</span><strong><?= count($milestones) ?></strong></div><div class="small-stat"><span>Em atenção</span><strong><?= count(array_filter($works,fn($w)=>in_array($w['status'],['Em atenção','Paralisada']))) ?></strong></div></div>
<section class="panel gantt-panel"><div class="panel-heading"><div><h2>Cronograma físico das intervenções</h2><p>Visão de datas reais cadastradas na plataforma.</p></div><span class="tag">CRONOGRAMA</span></div>
<?php
$starts=array_filter(array_column($works,'start_date'));$ends=array_filter(array_column($works,'end_date'));
$minDate=$starts?min($starts):date('Y-m-01');$maxDate=$ends?max($ends):date('Y-m-d',strtotime('+12 months'));
$minTs=strtotime($minDate)?:time();$maxTs=strtotime($maxDate)?:time()+86400;$span=max(86400,$maxTs-$minTs);
?>
<div class="gantt-legend"><span><?= h(datebr($minDate)) ?></span><span><?= h(datebr($maxDate)) ?></span></div>
<div class="gantt-body"><?php foreach($works as $w):
 $start=$w['start_date']?strtotime($w['start_date']):$minTs;
 $end=$w['end_date']?strtotime($w['end_date']):$maxTs;
 $left=max(0,min(100,(($start-$minTs)/$span)*100));$width=max(1,min(100-$left,(($end-$start)/$span)*100));
?><a class="gantt-entry" href="<?= h(url('obras',['action'=>'show','id'=>$w['id']])) ?>"><div class="gantt-title"><strong><?= h($w['title']) ?></strong><small><?= h($w['local']) ?></small></div><div class="gantt-track"><span class="gantt-bar" style="left:<?= round($left,2) ?>%;width:<?= round($width,2) ?>%"><i style="width:<?= max(0,min(100,(float)$w['progress'])) ?>%"></i></span></div><strong class="gantt-value"><?= h(numeric($w['progress'])) ?>%</strong></a><?php endforeach; ?>
<?php if(!$works): ?><div class="empty-table">Cadastre uma obra para criar o cronograma.</div><?php endif; ?></div><p class="panel-note">Barras baseadas nas datas cadastradas; o preenchimento corresponde à execução física informada.</p></section>
<div class="section-title"><div><span class="eyebrow">ACOMPANHAMENTO DE ENTREGAS</span><h2>Marcos do planejamento</h2></div><a class="text-link" href="<?= h(url('marcos')) ?>">Gerenciar todos <?= ic('arrow-right') ?></a></div>
<div class="milestone-grid"><?php foreach($milestones as $m): ?><a class="milestone-card" href="<?= h(url('marcos',['action'=>'show','id'=>$m['id']])) ?>"><div class="milestone-icon"><?= ic('calendar-check') ?></div><span><?= h(datebr($m['due_date'])) ?></span><strong><?= h($m['title']) ?></strong><?= status_pill($m['status']) ?></a><?php endforeach; ?>
<?php if(!$milestones): ?><div class="tip-panel">Ainda não há marcos. Cadastre o primeiro para acompanhar entregas e inspeções.</div><?php endif; ?></div>
