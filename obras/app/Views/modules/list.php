<?php
$filters=$filters??[];$pages=max(1,(int)ceil($total/$perPage));
$baseParams=array_filter(['q'=>$filters['q']??'','status'=>$filters['status']??''],static fn($v)=>$v!=='');
?>
<div class="page-header"><div><span class="eyebrow">GESTÃO INTEGRADA · OBRAS360</span><h1><?= h($config['name']) ?></h1><p><?= h($config['description']) ?></p></div>
<div class="header-actions"><a class="btn btn-outline" href="<?= h(url('relatorios',['module'=>$module,'format'=>'csv']+$baseParams)) ?>"><?= ic('download') ?> CSV</a><a class="btn btn-outline" href="<?= h(url('relatorios',['module'=>$module,'format'=>'xlsx']+$baseParams)) ?>"><?= ic('download') ?> Excel</a>
<?php if (\App\Core\Auth::canWrite()): ?><a class="btn btn-primary" href="<?= h(url($module,['action'=>'create'])) ?>"><?= ic('plus') ?> <?= h($module==='obras'?'Nova obra':($module==='ordens'?'Nova ordem':'Novo cadastro')) ?></a><?php endif; ?></div></div>
<div class="module-metrics"><div class="small-stat"><span>Total cadastrado</span><strong><?= h(numeric($total)) ?></strong><small>Conforme os filtros</small></div>
<?php foreach(array_slice($statusCounts,0,3) as $bucket): ?><div class="small-stat"><span><?= h($bucket['label']) ?></span><strong><?= h($bucket['total']) ?></strong><small>Registro(s)</small></div><?php endforeach; ?></div>
<section class="panel table-panel"><div class="panel-heading"><div><h2>Registros e acompanhamento</h2><p>Localize registros e abra as fichas para consultar detalhes ou atualizar dados.</p></div><span class="table-count"><?= h($total) ?> registros</span></div>
 <form class="filter-form" method="get" action="/index.php"><input type="hidden" name="r" value="<?= h($module) ?>">
 <label class="search-box"><?= ic('search') ?><input aria-label="Pesquisar" name="q" value="<?= h($filters['q']??'') ?>" placeholder="Pesquisar código, serviço ou local"></label>
 <label class="sr-label" for="filter-status">Situação</label><select id="filter-status" name="status"><option value="">Todas as situações</option>
 <?php foreach($statusCounts as $bucket): ?><option value="<?= h($module==='materiais'&&$bucket['label']==='Estoque baixo'?'Baixo':$bucket['label']) ?>" <?= ($filters['status']??'')===($module==='materiais'&&$bucket['label']==='Estoque baixo'?'Baixo':$bucket['label'])?'selected':'' ?>><?= h($bucket['label']) ?></option><?php endforeach; ?></select>
 <button class="btn btn-primary" type="submit">Filtrar</button><a class="btn btn-subtle" href="<?= h(url($module)) ?>">Limpar</a>
 </form>
 <div class="table-scroll"><table class="data-table"><thead><tr><?php foreach($config['columns'] as $label): ?><th><?= h($label) ?></th><?php endforeach; ?><th class="actions-col">Ações</th></tr></thead><tbody>
 <?php foreach($rows as $record): ?><tr><?php foreach($config['columns'] as $key=>$label): ?><td>
 <?php if ($key==='code'): ?><span class="code-label"><?= h($record['code']) ?></span>
 <?php elseif($key==='title'): ?><a class="table-main-link" href="<?= h(url($module,['action'=>'show','id'=>$record['id']])) ?>"><?= h($record['title']) ?></a><small class="table-subtitle"><?= h($record['local']??$record['type']??$record['period']??'') ?></small>
 <?php elseif($module==='materiais' && $key==='quantity'): ?><strong class="<?= (float)$record['quantity']<(float)$record['minimum']?'text-danger':'' ?>"><?= h(numeric($record['quantity'],2)) ?></strong> <?php if ((float)$record['quantity']<(float)$record['minimum']): ?><span class="mini-alert">Estoque baixo</span><?php endif; ?>
 <?php else: ?><?= display_cell($key,$record[$key]??null,$record,$works) ?><?php endif; ?></td><?php endforeach; ?>
 <td class="table-action-cell"><a title="Ver detalhes" class="mini-btn" href="<?= h(url($module,['action'=>'show','id'=>$record['id']])) ?>"><?= ic('eye') ?></a>
 <?php if (\App\Core\Auth::canWrite()): ?><a title="Editar" class="mini-btn" href="<?= h(url($module,['action'=>'edit','id'=>$record['id']])) ?>"><?= ic('edit') ?></a><?php endif; ?></td></tr><?php endforeach; ?>
 <?php if(!$rows): ?><tr><td colspan="<?= count($config['columns'])+1 ?>"><div class="empty-table"><?= ic('search') ?><strong>Nenhum registro encontrado</strong><p>Ajuste a pesquisa ou cadastre um novo registro.</p></div></td></tr><?php endif; ?></tbody></table></div>
 <div class="table-footer"><span>Exibindo <?= $total?($page-1)*$perPage+1:0 ?>–<?= min($total,$page*$perPage) ?> de <?= $total ?></span>
 <?php if($pages>1): ?><nav aria-label="Paginação" class="pagination">
  <?php if($page>1): ?><a href="<?= h(url($module,['page'=>$page-1]+$baseParams)) ?>">Anterior</a><?php endif; ?>
  <span>Página <?= $page ?> / <?= $pages ?></span>
  <?php if($page<$pages): ?><a href="<?= h(url($module,['page'=>$page+1]+$baseParams)) ?>">Próxima</a><?php endif; ?>
 </nav><?php endif; ?></div></section>
<?php if($module==='ordens'):
 $board=\App\Core\Repository::list('ordens',[],100);
?><div class="section-title"><div><span class="eyebrow">VISÃO OPERACIONAL</span><h2>Quadro de ordens de serviço</h2></div><span class="hint">Arraste? Não: altere a situação pela ficha para manter o histórico.</span></div>
<div class="kanban"><?php foreach(['Aberta','Em execução','Concluída'] as $status): ?><section class="kanban-col"><h3><?= h($status) ?><span><?= count(array_filter($board,fn($r)=>$r['status']===$status)) ?></span></h3>
<?php foreach($board as $ord):if($ord['status']!==$status)continue; ?><a class="kanban-card" href="<?= h(url('ordens',['action'=>'show','id'=>$ord['id']])) ?>"><small><?= h($ord['code']) ?> · <?= h($ord['priority']) ?></small><strong><?= h($ord['title']) ?></strong><span><?= ic('pin') ?> <?= h($ord['local']) ?></span><span class="kanban-row"><?= h($ord['responsible']) ?> <?= ic('arrow-right') ?></span></a><?php endforeach; ?></section><?php endforeach; ?></div><?php endif; ?>
<?php if($module==='materiais'): ?><div class="tip-panel"><?= ic('package') ?><div><strong>Movimentações de estoque com histórico</strong><p>Abra a ficha de qualquer material para registrar entrada ou saída. O saldo é atualizado pelo servidor em transação e não pode ficar negativo.</p></div></div><?php endif; ?>
