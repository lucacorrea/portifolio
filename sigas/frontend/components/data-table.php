<?php

declare(strict_types=1);

$blockTitle = (string) ($block['title'] ?? 'Registros');
$blockPrimary = (string) ($block['primary'] ?? ($block['columns'][0]['key'] ?? ''));
$blockRowActions = is_array($block['row_actions'] ?? null) ? $block['row_actions'] : [];
$blockRowActionsJson = json_encode(
    $blockRowActions,
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
) ?: '[]';
?>
<section class="content-card frontend-data-card" data-frontend-dataset>
    <div class="card-heading">
        <div>
            <div class="card-kicker"><?= sigas_frontend_escape($block['kicker'] ?? 'Visão operacional') ?></div>
            <h2><?= sigas_frontend_escape($blockTitle) ?></h2>
            <p><?= sigas_frontend_escape($block['description'] ?? 'Registros demonstrativos para composição visual.') ?></p>
        </div>
        <span class="frontend-row-hint"><i class="bi bi-cursor"></i> Clique na linha para abrir ações</span>
    </div>

    <div class="table-responsive frontend-desktop-table">
        <table class="table align-middle">
            <thead>
                <tr>
                    <?php foreach ($block['columns'] as $column): ?>
                        <th><?= sigas_frontend_escape($column['label']) ?></th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($block['rows'] as $row): ?>
                <?php
                $recordJson = json_encode(
                    $row,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
                ) ?: '{}';
                $primaryValue = $blockPrimary !== '' ? ($row[$blockPrimary] ?? reset($row)) : reset($row);
                $rowActions = is_array($row['_actions'] ?? null) ? $row['_actions'] : $blockRowActions;
                $rowActionsJson = $rowActions === $blockRowActions
                    ? $blockRowActionsJson
                    : (json_encode(
                        $rowActions,
                        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
                    ) ?: '[]');
                ?>
                <tr
                    class="frontend-action-row"
                    tabindex="0"
                    role="button"
                    aria-haspopup="dialog"
                    data-filter-row
                    data-sigas-action-row
                    data-sigas-record="<?= sigas_frontend_escape($recordJson) ?>"
                    data-sigas-row-actions="<?= sigas_frontend_escape($rowActionsJson) ?>"
                    data-sigas-row-title="<?= sigas_frontend_escape((string) $primaryValue) ?>"
                    data-sigas-table-title="<?= sigas_frontend_escape($blockTitle) ?>"
                    data-search="<?= sigas_frontend_escape(strtolower(implode(' ', array_map('strval', array_filter($row, static fn ($key): bool => $key !== '_actions', ARRAY_FILTER_USE_KEY))))) ?>"
                >
                    <?php foreach ($block['columns'] as $column): ?>
                        <td><?= sigas_frontend_escape($row[$column['key']] ?? '—') ?></td>
                    <?php endforeach; ?>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="frontend-mobile-records">
        <?php foreach ($block['rows'] as $row): ?>
            <?php
            $recordJson = json_encode(
                $row,
                JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
            ) ?: '{}';
            $primaryValue = $blockPrimary !== '' ? ($row[$blockPrimary] ?? reset($row)) : reset($row);
            $rowActions = is_array($row['_actions'] ?? null) ? $row['_actions'] : $blockRowActions;
            $rowActionsJson = $rowActions === $blockRowActions
                ? $blockRowActionsJson
                : (json_encode(
                    $rowActions,
                    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT
                ) ?: '[]');
            ?>
            <article
                class="frontend-action-record"
                tabindex="0"
                role="button"
                aria-haspopup="dialog"
                data-filter-row
                data-sigas-action-row
                data-sigas-record="<?= sigas_frontend_escape($recordJson) ?>"
                data-sigas-row-actions="<?= sigas_frontend_escape($rowActionsJson) ?>"
                data-sigas-row-title="<?= sigas_frontend_escape((string) $primaryValue) ?>"
                data-sigas-table-title="<?= sigas_frontend_escape($blockTitle) ?>"
                data-search="<?= sigas_frontend_escape(strtolower(implode(' ', array_map('strval', array_filter($row, static fn ($key): bool => $key !== '_actions', ARRAY_FILTER_USE_KEY))))) ?>"
            >
                <div class="frontend-action-record__heading">
                    <strong><?= sigas_frontend_escape((string) $primaryValue) ?></strong>
                    <i class="bi bi-chevron-right" aria-hidden="true"></i>
                </div>
                <dl>
                    <?php foreach (array_slice($block['columns'], 1, 4) as $column): ?>
                        <div>
                            <dt><?= sigas_frontend_escape($column['label']) ?></dt>
                            <dd><?= sigas_frontend_escape($row[$column['key']] ?? '—') ?></dd>
                        </div>
                    <?php endforeach; ?>
                </dl>
                <small class="frontend-action-record__hint"><i class="bi bi-hand-index-thumb"></i> Toque para ver as ações</small>
            </article>
        <?php endforeach; ?>
    </div>

    <div class="state-panel frontend-no-results" data-no-results hidden>
        <i class="bi bi-search"></i>
        <h3>Sem resultados</h3>
        <p>Altere os filtros ou a pesquisa para continuar.</p>
    </div>

    <div class="frontend-pagination">
        <span>Exibindo página 1 de 3</span>
        <nav aria-label="Paginação demonstrativa">
            <button class="btn btn-light btn-sm" type="button" disabled>Anterior</button>
            <button class="btn btn-primary btn-sm" type="button" aria-current="page">1</button>
            <button class="btn btn-light btn-sm" type="button" data-demo-action="Abrir página 2">2</button>
            <button class="btn btn-light btn-sm" type="button" data-demo-action="Abrir próxima página">Próxima</button>
        </nav>
    </div>
</section>
