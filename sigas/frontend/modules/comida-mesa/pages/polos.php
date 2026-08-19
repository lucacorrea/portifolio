<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/forms.php';

$pageDefinition = [
    'title'=>'Polos',
    'description'=>'Cadastro e acompanhamento dos pontos de distribuição e famílias vinculadas.',
    'actions'=>cm_can('comida_mesa.polos_gerenciar') ? [['label'=>'Novo polo','icon'=>'geo-alt-fill','primary'=>true,'href'=>'comida-mesa/polos.php?modal=novo']] : [],
    'demo'=>false,'show_states'=>false,
];
$filters=['q'=>trim((string)($_GET['q']??'')),'ativo'=>isset($_GET['ativo'])?trim((string)$_GET['ativo']):''];
$rows=$moduleRepository->poles($filters);
$all=$moduleRepository->poles([]);
$total=count($rows); $ativos=count(array_filter($all,static fn($r)=>(int)$r['ativo']===1)); $inativos=count($all)-$ativos; $familias=array_sum(array_map(static fn($r)=>(int)$r['familias'],$all)); $beneficiarias=array_sum(array_map(static fn($r)=>(int)$r['beneficiarias_ativas'],$all));
ob_start();
?>
<section class="content-card cm-list-card">
<?php cm_list_header('Rede de distribuição','Polos do Comida na Mesa','Organize os locais de retirada sem apagar o histórico de famílias e entregas.',cm_can('comida_mesa.polos_gerenciar')?'Novo polo':'',cm_can('comida_mesa.polos_gerenciar')?'#poleModal':'','geo-alt-fill'); ?>
<?php cm_metrics([
['label'=>'Polos cadastrados','value'=>count($all),'hint'=>'Rede total','tone'=>'neutral'],['label'=>'Ativos','value'=>$ativos,'hint'=>'Disponíveis para vínculo','tone'=>'success'],['label'=>'Inativos','value'=>$inativos,'hint'=>'Preservados no histórico','tone'=>'muted'],['label'=>'Famílias vinculadas','value'=>$familias,'hint'=>'Todos os status','tone'=>'info'],['label'=>'Beneficiárias ativas','value'=>$beneficiarias,'hint'=>'Vínculos ativos','tone'=>'success'],
]); ?>
<form class="cm-filter-panel cm-filter-panel--compact" method="get" action="comida-mesa/polos.php"><label class="cm-filter-search"><span>Pesquisa</span><div class="cm-input-icon"><i class="bi bi-search"></i><input class="form-control" name="q" type="search" value="<?= cm_h($filters['q']) ?>" placeholder="Nome ou endereço"></div></label><label><span>Situação</span><select class="form-select" name="ativo"><option value="">Todas</option><option value="1"<?= cm_selected($filters['ativo'],'1') ?>>Ativo</option><option value="0"<?= cm_selected($filters['ativo'],'0') ?>>Inativo</option></select></label><button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Filtrar</button><a class="btn btn-light" href="comida-mesa/polos.php"><i class="bi bi-x-lg"></i> Limpar</a></form>
<div class="cm-table-shell"><div class="cm-table-toolbar"><div><h3>Rede de polos</h3><p><?= $total ?> registro(s)</p></div><?php if(cm_can('comida_mesa.polos_gerenciar')): ?><span><i class="bi bi-cursor"></i> Clique na linha para editar</span><?php endif; ?></div><?php if($rows): ?><div class="table-responsive"><table class="cm-data-table"><thead><tr><th>Polo</th><th>Endereço/localização</th><th>Famílias</th><th>Beneficiárias ativas</th><th>Situação</th><th>Atualização</th></tr></thead><tbody><?php foreach($rows as $row): ?><tr<?= cm_can('comida_mesa.polos_gerenciar')?' tabindex="0" data-cm-editable-pole-row':'' ?>><td><strong><?= cm_h($row['nome']) ?></strong><small class="d-block text-muted"><?= cm_h($row['slug']) ?></small></td><td><?= cm_h($row['endereco'] ?: 'Não informado') ?></td><td><?= (int)$row['familias'] ?></td><td><?= (int)$row['beneficiarias_ativas'] ?></td><td><?= cm_status((int)$row['ativo']===1?'Ativo':'Inativo') ?></td><td><?= cm_h(cm_date($row['atualizado_em']??$row['criado_em'])) ?></td><?php if(cm_can('comida_mesa.polos_gerenciar')): ?><td class="visually-hidden"><button type="button" data-cm-edit-pole data-id="<?= (int)$row['id'] ?>" data-nome="<?= cm_h($row['nome']) ?>" data-endereco="<?= cm_h($row['endereco']) ?>" data-ativo="<?= (int)$row['ativo'] ?>">Editar</button></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div><?php else: ?><?php cm_empty('Nenhum polo encontrado','Ajuste os filtros ou cadastre um novo polo.','geo-alt'); ?><?php endif; ?></div>
</section>
<?php cm_pole_modal(); ?>
<?php if(($_GET['modal']??'')==='novo' && cm_can('comida_mesa.polos_gerenciar')): ?><script>document.addEventListener('DOMContentLoaded',()=>bootstrap.Modal.getOrCreateInstance(document.getElementById('poleModal')).show());</script><?php endif; ?>
<?php $pageCustomContent=(string)ob_get_clean(); ?>
