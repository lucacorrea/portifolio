<?php
use Sigesp\Core\{Auth, View};

$title = 'Atletas';
$pageId = 'atletas';
$queryParams = array_filter([
    'q' => $filters['q'],
    'status' => $filters['status'],
    'por_pagina' => $result['per_page'],
]);
?>
<?php View::component('page-header', ['eyebrow' => 'Gestão de atletas', 'heading' => 'Atletas', 'description' => $result['total'] . ' registro(s) encontrado(s).', 'actionLabel' => Auth::can('atletas.criar') ? 'Novo atleta' : null, 'actionHref' => '/atletas/novo', 'secondaryLabel' => 'Exportar', 'secondaryMessage' => 'A exportação será liberada quando o relatório operacional estiver disponível.']); ?>
<?php ob_start(); ?>
<form method="get" action="<?= View::e(View::url('/atletas')) ?>" class="filter-form">
    <label class="field">Buscar<input name="q" value="<?= View::e($filters['q']) ?>" placeholder="Nome, CPF ou código"></label>
    <label class="field">Situação<select name="status"><option value="">Todas</option><?php foreach (['ativo'=>'Ativo','inativo'=>'Inativo','rascunho'=>'Rascunho'] as $value => $label): ?><option value="<?= $value ?>" <?= $filters['status'] === $value ? 'selected' : '' ?>><?= $label ?></option><?php endforeach; ?></select></label>
    <label class="field">Por página<select name="por_pagina"><?php foreach ([15,30,50] as $number): ?><option value="<?= $number ?>" <?= $result['per_page'] === $number ? 'selected' : '' ?>><?= $number ?></option><?php endforeach; ?></select></label>
    <label class="field">Modalidade<select disabled><option>Todos os registros</option></select></label>
    <label class="field">Situação documental<select disabled><option>Integração em breve</option></select></label>
    <div><button class="button" type="submit">Aplicar filtros</button><a class="button button--secondary" href="<?= View::e(View::url('/atletas')) ?>">Limpar</a></div>
</form>
<?php $filterContent = ob_get_clean(); View::component('filter-bar', ['filters' => $filterContent]); ?>
<?php if (!$result['items']): ?>
    <?php View::component('empty-state', ['heading' => 'Nenhum atleta encontrado', 'message' => $filters['q'] || $filters['status'] ? 'Ajuste ou limpe os filtros para tentar novamente.' : 'Comece cadastrando o primeiro atleta.', 'actionLabel' => Auth::can('atletas.criar') ? 'Cadastrar atleta' : null, 'actionHref' => '/atletas/novo']); ?>
<?php else: ?>
    <?php
    $rows = [];
    foreach ($result['items'] as $item) {
        $age = (new DateTimeImmutable($item['nascimento']))->diff(new DateTimeImmutable('today'))->y;
        ob_start();
        View::component('badge', ['label' => $item['status'], 'tone' => $item['status']]);
        $status = ob_get_clean();
        $profileUrl = View::e(View::url('/atletas/' . (int) $item['id']));
        $rows[] = ['<span class="avatar">'.View::e(mb_strtoupper(mb_substr($item['nome'], 0, 1))).'</span>', '<strong>'.View::e($item['codigo']).'</strong>', '<strong>'.View::e($item['nome']).'</strong><small>'.View::e($item['email'] ?? 'Sem e-mail').'</small>', View::e($item['cpf']), View::e((string) $age), View::e($item['modalidade'] ?? 'Não vinculada'), $status, '<a href="'.$profileUrl.'">Ver perfil</a>'];
    }
    View::component('data-table', ['columns' => ['', 'Código', 'Atleta', 'CPF', 'Idade', 'Modalidade', 'Situação', 'Ações'], 'rows' => $rows]);
    ?>
    <section class="record-cards" aria-label="Atletas em formato de cartões"><?php foreach ($result['items'] as $item): ?><article class="record-card"><div class="record-card__head"><?php View::component('avatar', ['name' => $item['nome']]); ?><div><strong><?= View::e($item['nome']) ?></strong><small><?= View::e($item['codigo']) ?> · <?= View::e($item['modalidade'] ?? 'Sem modalidade') ?></small></div><?php View::component('badge', ['label' => $item['status'], 'tone' => $item['status']]); ?></div><div class="record-card__meta"><span><?= View::e($item['cpf']) ?></span><a href="<?= View::e(View::url('/atletas/' . (int) $item['id'])) ?>">Ver perfil</a></div></article><?php endforeach; ?></section>
    <?php View::component('pagination', ['page' => $result['page'], 'pages' => (int) ceil($result['total'] / $result['per_page']), 'path' => '/atletas', 'params' => $queryParams]); ?>
<?php endif; ?>
