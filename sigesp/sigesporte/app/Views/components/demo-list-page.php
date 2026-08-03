<?php
declare(strict_types=1);

use Sigesp\Core\View;

$page = is_array($page ?? null) ? $page : [];
$module = (string) ($page['module'] ?? 'modulo');
$records = is_array($page['records'] ?? null) ? $page['records'] : [];
$columns = is_array($page['columns'] ?? null) ? $page['columns'] : [];
$stats = is_array($page['stats'] ?? null) ? $page['stats'] : [];
$filters = is_array($page['filters'] ?? null) ? $page['filters'] : [];
$highlights = is_array($page['highlights'] ?? null) ? $page['highlights'] : [];
$statusTone = static function (string $status): string {
    $normalized = mb_strtolower($status);
    if (str_contains($normalized, 'ativo') && !str_contains($normalized, 'inativo')) return 'success';
    if (str_contains($normalized, 'aprov') || str_contains($normalized, 'conclu') || str_contains($normalized, 'dispon')) return 'success';
    if (str_contains($normalized, 'venc') || str_contains($normalized, 'rejeit') || str_contains($normalized, 'manuten')) return 'danger';
    if (str_contains($normalized, 'pend') || str_contains($normalized, 'agend') || str_contains($normalized, 'andamento')) return 'warning';
    return 'neutral';
};
?>
<?php View::component('page-header', [
    'eyebrow' => 'SIGESP · Modo demonstração',
    'heading' => (string) ($page['title'] ?? 'Módulo'),
    'description' => (string) ($page['description'] ?? ''),
    'actionLabel' => (string) ($page['action'] ?? 'Novo registro'),
    'actionHref' => (string) ($page['actionPath'] ?? '#'),
    'secondaryLabel' => 'Exportar',
    'secondaryMessage' => 'A exportação será habilitada na integração com o back-end.',
]); ?>

<?php if ($stats !== []): ?>
<section class="stats-grid" aria-label="Indicadores de <?= View::e((string) ($page['title'] ?? $module)) ?>">
    <?php foreach ($stats as $index => $stat): ?>
        <?php View::component('stats-card', [
            'icon' => (string) ($stat['icon'] ?? ['◉', '✓', '⌁', '▥'][$index % 4]),
            'label' => (string) ($stat['label'] ?? ''),
            'value' => (string) ($stat['value'] ?? '0'),
            'detail' => (string) ($stat['detail'] ?? 'Dados fictícios'),
        ]); ?>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php if ($highlights !== []): ?>
<section class="module-highlights" aria-label="Destaques do módulo">
    <?php foreach ($highlights as $highlight): ?>
        <?php $highlightValues = array_values(array_filter($highlight, static fn (mixed $value): bool => is_scalar($value) && (string) $value !== '')); ?>
        <article class="module-highlight"><strong><?= View::e((string) ($highlight['title'] ?? $highlight['nome'] ?? $highlight['atleta'] ?? $highlight['equipe'] ?? $highlight['item'] ?? $highlightValues[0] ?? 'Destaque')) ?></strong><span><?= View::e((string) ($highlight['detail'] ?? $highlight['description'] ?? implode(' · ', array_slice(array_map('strval', $highlightValues), 1, 3)))) ?></span><?php if (!empty($highlight['action'])): ?><a href="<?= View::e(View::url((string) ($highlight['path'] ?? '/' . $module))) ?>"><?= View::e((string) $highlight['action']) ?></a><?php endif; ?></article>
    <?php endforeach; ?>
</section>
<?php endif; ?>

<?php ob_start(); ?>
<form class="filter-form" data-demo-filter data-filter-target="#<?= View::e($module) ?>-records">
    <label class="field">Buscar<input type="search" data-filter-search placeholder="Pesquisar nesta página"></label>
    <?php foreach (array_slice($filters, 0, 3) as $key => $filter): ?>
        <?php $label = is_array($filter) ? (string) ($filter['label'] ?? $key) : (string) $filter; $options = is_array($filter) ? (array) ($filter['options'] ?? []) : []; ?>
        <label class="field"><?= View::e($label) ?><select data-filter-select><option value="">Todos</option><?php foreach ($options as $option): ?><option><?= View::e((string) $option) ?></option><?php endforeach; ?></select></label>
    <?php endforeach; ?>
    <div><button class="button" type="submit">Aplicar filtros</button><button class="button button--secondary" type="reset">Limpar</button></div>
</form>
<?php $filterContent = (string) ob_get_clean(); View::component('filter-bar', ['filters' => $filterContent]); ?>

<section class="surface" id="<?= View::e($module) ?>-records">
    <div class="demo-toolbar"><div class="demo-toolbar__summary"><strong data-record-count><?= count($records) ?></strong> registros fictícios nesta visualização.</div><div class="demo-toolbar__actions"><button class="button button--secondary" type="button" data-demo-action="print">Imprimir</button><button class="button button--secondary" type="button" data-demo-action="export">Excel/PDF</button></div></div>
    <?php if ($records === []): ?>
        <?php View::component('empty-state', ['heading' => 'Nenhum registro demonstrativo', 'message' => 'A amostra deste módulo está sendo preparada.']); ?>
    <?php else: ?>
        <div class="data-table demo-table"><table><thead><tr><?php foreach ($columns as $label): ?><th scope="col"><?= View::e((string) $label) ?></th><?php endforeach; ?><th scope="col">Ações</th></tr></thead><tbody>
        <?php foreach ($records as $index => $record): ?><tr data-demo-record data-search-text="<?= View::e(mb_strtolower(implode(' ', array_map('strval', $record)))) ?>"><?php foreach ($columns as $key => $label): ?><?php $value = (string) ($record[$key] ?? '—'); ?><td><?php if ($key === 'status' || str_contains((string) $key, 'situacao')): ?><?php View::component('badge', ['label' => $value, 'tone' => $statusTone($value)]); ?><?php elseif ($key === 'nome' || $key === 'atleta' || $key === 'equipe' || $key === 'item'): ?><strong><?= View::e($value) ?></strong><?php else: ?><?= View::e($value) ?><?php endif; ?></td><?php endforeach; ?><td><div class="demo-actions"><button class="button button--secondary" type="button" data-demo-action="view" data-record-name="<?= View::e((string) ($record['nome'] ?? $record['atleta'] ?? $record['equipe'] ?? $record['item'] ?? 'registro')) ?>">Ver</button><button class="button button--secondary" type="button" data-demo-delete data-record-name="<?= View::e((string) ($record['nome'] ?? $record['atleta'] ?? $record['equipe'] ?? $record['item'] ?? 'registro')) ?>">⋯</button></div></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <section class="record-cards" aria-label="Registros em cartões"><?php foreach ($records as $record): ?><article class="record-card" data-demo-record data-search-text="<?= View::e(mb_strtolower(implode(' ', array_map('strval', $record)))) ?>"><div class="record-card__head"><span class="avatar"><?= View::e(mb_strtoupper(mb_substr((string) reset($record), 0, 2))) ?></span><div><strong><?= View::e((string) ($record['nome'] ?? $record['atleta'] ?? $record['equipe'] ?? $record['item'] ?? reset($record))) ?></strong><small><?= View::e(implode(' · ', array_slice(array_map('strval', array_values($record)), 1, 2))) ?></small></div><?php if (isset($record['status'])) View::component('badge', ['label' => (string) $record['status'], 'tone' => $statusTone((string) $record['status'])]); ?></div><div class="record-card__meta"><button class="button button--secondary" type="button" data-demo-action="view">Ver detalhes</button><button class="button button--secondary" type="button" data-demo-delete>Mais ações</button></div></article><?php endforeach; ?></section>
    <?php endif; ?>
</section>
