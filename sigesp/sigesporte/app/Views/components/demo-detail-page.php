<?php
declare(strict_types=1);
use Sigesp\Core\View;

$page = is_array($page ?? null) ? $page : [];
$module = (string) ($page['module'] ?? 'modulo');
$records = is_array($page['records'] ?? null) ? $page['records'] : [];
$record = $records[0] ?? [];
$columns = is_array($page['columns'] ?? null) ? $page['columns'] : [];
?>
<?php View::component('page-header',['eyebrow'=>(string)($page['title']??'Módulo').' · Demonstração','heading'=>'Detalhes do registro','description'=>'Visualização completa de um registro fictício.','actionLabel'=>'Voltar','actionHref'=>'/'.$module,'secondaryLabel'=>'Editar','secondaryMessage'=>'A edição é simulada e não altera dados.']); ?>
<section class="dashboard-grid"><article class="surface"><div class="surface__header"><div><h2>Informações</h2><p>Dados demonstrativos do módulo.</p></div><?php if(isset($record['status'])) View::component('badge',['label'=>(string)$record['status'],'tone'=>'success']); ?></div><dl class="details-list"><?php foreach($columns as $key=>$label): ?><dt><?= View::e((string)$label) ?></dt><dd><?= View::e((string)($record[$key]??'—')) ?></dd><?php endforeach; ?></dl></article><aside class="surface"><div class="surface__header"><div><h2>Histórico</h2><p>Atividades simuladas.</p></div></div><?php View::component('activity-timeline',['items'=>[['title'=>'Registro atualizado','detail'=>'Hoje · Marcos Oliveira'],['title'=>'Situação conferida','detail'=>'Ontem · Ambiente DEMO'],['title'=>'Registro criado','detail'=>'15/07/2026 · Gestão Esportiva']]]); ?><div class="demo-toolbar__actions" style="margin-top:16px"><button class="button button--secondary" type="button" data-demo-action="edit">Editar</button><button class="button button--secondary" type="button" data-demo-delete>Inativar</button></div></aside></section>
