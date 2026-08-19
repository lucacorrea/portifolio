<?php

declare(strict_types=1);

/**
 * Componentes compartilhados das páginas operacionais do Meu Primeiro Emprego.
 *
 * Padrão do módulo:
 * - indicadores objetivos;
 * - filtros específicos por página;
 * - tabela/listagem como conteúdo principal;
 * - status por cor de texto, sem pintar a linha;
 * - clique na linha abre ações em modal.
 */

function pe_list_header(
    string $kicker,
    string $title,
    string $description,
    string $createLabel = '',
    string $createTarget = '',
    string $icon = 'plus-lg'
): void {
    ?>
    <div class="pe-page-hero pe-list-hero">
        <div>
            <div class="card-kicker"><?= pe_h($kicker) ?></div>
            <h2><?= pe_h($title) ?></h2>
            <p><?= pe_h($description) ?></p>
        </div>
        <?php if ($createLabel !== '' && $createTarget !== ''): ?>
            <div class="pe-page-actions pe-no-print">
                <button
                    class="btn btn-primary"
                    type="button"
                    data-pe-open="<?= pe_h($createTarget) ?>"
                    data-pe-mode="create"
                >
                    <i class="bi bi-<?= pe_h($icon) ?>"></i>
                    <?= pe_h($createLabel) ?>
                </button>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

/** @param array<int,array<string,mixed>> $metrics */
function pe_list_metrics(array $metrics, ?string $filterPanel = null): void
{
    if ($metrics === []) {
        return;
    }
    ?>
    <div class="pe-kpi-grid pe-operational-kpis pe-no-print"<?= $filterPanel ? ' data-pe-metric-panel="' . pe_h($filterPanel) . '"' : '' ?>>
        <?php foreach ($metrics as $metric): ?>
            <?php
            $tone = trim((string) ($metric['tone'] ?? 'neutral'));
            $label = (string) ($metric['label'] ?? 'Indicador');
            $value = $metric['value'] ?? 0;
            $hint = (string) ($metric['hint'] ?? '');
            $filterKey = trim((string) ($metric['filter_key'] ?? ''));
            $filterValue = trim((string) ($metric['filter_value'] ?? ''));
            $interactive = $filterKey !== '' && $filterValue !== '';
            $tag = $interactive ? 'button' : 'div';
            ?>
            <<?= $tag ?>
                class="pe-kpi pe-kpi--<?= pe_h($tone) ?><?= $interactive ? ' pe-kpi--interactive' : '' ?>"
                <?php if ($interactive): ?>
                    type="button"
                    data-pe-metric-filter-key="<?= pe_h($filterKey) ?>"
                    data-pe-metric-filter-value="<?= pe_h($filterValue) ?>"
                    title="Filtrar por <?= pe_h($label) ?>"
                <?php endif; ?>
            >
                <span><?= pe_h($label) ?></span>
                <strong><?= pe_h((string) $value) ?></strong>
                <?php if ($hint !== ''): ?><small><?= pe_h($hint) ?></small><?php endif; ?>
            </<?= $tag ?>>
        <?php endforeach; ?>
    </div>
    <?php
}

/**
 * @param array<int,array{key:string,label:string,options:array<mixed>,value?:string}> $filters
 */
function pe_list_filter_panel(
    string $scopeId,
    string $placeholder,
    array $filters = [],
    int $total = 0,
    string $countLabel = 'registro(s)'
): void {
    ?>
    <?php $panelId = str_ends_with($scopeId, 'Table') ? substr($scopeId, 0, -5) . 'Filters' : $scopeId . 'Filters'; ?>
    <div
        id="<?= pe_h($panelId) ?>"
        class="pe-filter-panel pe-operational-filters pe-no-print"
        data-pe-filter-scope="#<?= pe_h($scopeId) ?>"
    >
        <label class="pe-filter-search pe-operational-search">
            <i class="bi bi-search"></i>
            <input
                class="form-control"
                type="search"
                placeholder="<?= pe_h($placeholder) ?>"
                data-pe-filter-search
                autocomplete="off"
            >
        </label>

        <?php foreach ($filters as $filter): ?>
            <?php
            $key = trim((string) ($filter['key'] ?? ''));
            if ($key === '') {
                continue;
            }
            $label = trim((string) ($filter['label'] ?? ucfirst($key)));
            $current = (string) ($filter['value'] ?? '');
            $options = $filter['options'] ?? [];
            ?>
            <label class="pe-operational-filter-field">
                <span><?= pe_h($label) ?></span>
                <select class="form-select" data-pe-filter-key="<?= pe_h($key) ?>">
                    <?php foreach ($options as $optionValue => $optionLabel): ?>
                        <?php
                        if (is_int($optionValue)) {
                            $optionValue = (string) $optionLabel;
                        }
                        ?>
                        <option
                            value="<?= pe_h((string) $optionValue) ?>"
                            <?= (string) $optionValue === $current ? ' selected' : '' ?>
                        ><?= pe_h((string) $optionLabel) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
        <?php endforeach; ?>

        <div class="pe-operational-filter-actions">
            <span class="pe-list-count">
                <strong data-pe-filter-count><?= (int) $total ?></strong>
                <span><?= pe_h($countLabel) ?></span>
            </span>
            <button class="btn btn-light pe-filter-clear" type="button" data-pe-filter-clear title="Limpar filtros">
                <i class="bi bi-x-lg"></i>
                <span>Limpar</span>
            </button>
        </div>
    </div>
    <?php
}

/** Compatibilidade com páginas que ainda usam apenas pesquisa simples. */
function pe_list_toolbar(int $count, string $placeholder = 'Buscar na listagem...'): void
{
    ?>
    <div class="pe-list-toolbar pe-no-print">
        <div class="pe-list-count"><strong><?= $count ?></strong> registro(s)</div>
        <label class="pe-list-search">
            <i class="bi bi-search"></i>
            <input class="form-control" type="search" placeholder="<?= pe_h($placeholder) ?>" data-pe-list-search autocomplete="off">
        </label>
    </div>
    <?php
}

/** @return array<string,string> */
function pe_filter_options(array $rows, string $field, string $allLabel): array
{
    $values = [];
    foreach ($rows as $row) {
        $value = trim((string) ($row[$field] ?? ''));
        if ($value !== '') {
            $values[$value] = $value;
        }
    }
    natcasesort($values);
    return ['' => $allLabel] + $values;
}

function pe_count_rows(array $rows, callable $predicate): int
{
    $count = 0;
    foreach ($rows as $row) {
        if ($predicate($row)) {
            $count++;
        }
    }
    return $count;
}

function pe_status_tone(?string $status, string $context = 'generic'): string
{
    $value = mb_strtolower(trim((string) $status), 'UTF-8');
    if ($value === '') {
        return 'muted';
    }

    $danger = [
        'indeferido', 'indeferida', 'suspensa', 'suspenso', 'vencido', 'vencida',
        'cancelada', 'cancelado', 'não selecionado', 'nao selecionado', 'irregular',
        'revisar lotação', 'revisar lotacao', 'cpf duplicado', 'bloqueado', 'bloqueada',
    ];
    $warning = [
        'pendente', 'atenção', 'atencao', 'em análise', 'em analise', 'programada',
        'em seleção', 'em selecao', 'entrevista marcada', 'revisar', 'não lotado',
        'nao lotado', 'previsto', 'prevista', 'revisar cadastro', 'revisar cpf',
        'revisar telefone', 'revisar nascimento', 'com pendência', 'sem arquivo',
    ];
    $success = [
        'ativa', 'ativo', 'aberta', 'aberto', 'regular', 'deferido', 'deferida',
        'aprovado', 'aprovada', 'paga', 'pago', 'concluída', 'concluida', 'concluído',
        'concluido', 'disponível', 'disponivel', 'contemplado', 'contemplada',
        'lotado', 'lotada', 'preenchida', 'presente', 'sem pendência',
    ];
    $info = [
        'em andamento', 'em processamento', 'encaminhado', 'encaminhada',
        'inscrições abertas', 'inscricoes abertas', 'planejada', 'planejado',
        'pronto para importar', 'inscrito', 'inscrita',
    ];
    $muted = [
        'encerrada', 'encerrado', 'não se aplica', 'nao se aplica', 'arquivado', 'arquivada',
    ];

    if (in_array($value, $danger, true)) return 'danger';
    if (in_array($value, $warning, true)) return 'warning';
    if (in_array($value, $success, true)) return 'success';
    if (in_array($value, $info, true)) return 'info';
    if (in_array($value, $muted, true)) return 'muted';

    if (str_contains($value, 'revisar') || str_contains($value, 'vencid')) return 'danger';
    if (str_contains($value, 'pendente') || str_contains($value, 'atenção') || str_contains($value, 'atencao')) return 'warning';
    if (str_contains($value, 'conclu') || str_contains($value, 'aprovad') || str_contains($value, 'regular')) return 'success';

    return $context === 'critical' ? 'danger' : 'neutral';
}

function pe_status_class(?string $status, string $context = 'generic'): string
{
    return 'pe-status-text is-' . pe_status_tone($status, $context);
}

function pe_status_label(?string $status, string $context = 'generic'): string
{
    return '<span class="' . pe_h(pe_status_class($status, $context)) . '">' . pe_h((string) ($status ?: '—')) . '</span>';
}

function pe_crud_actions_dialog(string $dialogId, string $entityLabel, string $viewTarget, string $editTarget, string $deleteTarget): void
{
    ?>
    <dialog class="pe-modal pe-modal--actions" id="<?= pe_h($dialogId) ?>">
        <div class="pe-modal__shell">
            <header class="pe-modal__header">
                <div>
                    <div class="card-kicker"><?= pe_h($entityLabel) ?></div>
                    <h2 data-pe-current-title>Registro selecionado</h2>
                    <p data-pe-current-subtitle>Escolha uma ação.</p>
                </div>
                <button type="button" class="pe-modal__close" data-pe-dialog-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
            </header>
            <div class="pe-modal__body">
                <div class="pe-modal-actions-title">Ações disponíveis</div>
                <div class="pe-modal-actions">
                    <button class="pe-modal-action" type="button" data-pe-open="<?= pe_h($viewTarget) ?>" data-pe-mode="view">
                        <span class="pe-modal-action__icon"><i class="bi bi-eye"></i></span>
                        <span><strong>Visualizar</strong><small>Consultar todos os dados deste registro</small></span>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button class="pe-modal-action pe-modal-action--primary" type="button" data-pe-open="<?= pe_h($editTarget) ?>" data-pe-mode="edit">
                        <span class="pe-modal-action__icon"><i class="bi bi-pencil-square"></i></span>
                        <span><strong>Editar</strong><small>Atualizar os dados cadastrados</small></span>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                    <button class="pe-modal-action pe-modal-action--danger" type="button" data-pe-open="<?= pe_h($deleteTarget) ?>" data-pe-mode="delete">
                        <span class="pe-modal-action__icon"><i class="bi bi-trash3"></i></span>
                        <span><strong>Excluir</strong><small>Remover o registro após confirmação</small></span>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </dialog>
    <?php
}

function pe_delete_dialog(string $dialogId, string $entityLabel, string $action): void
{
    ?>
    <dialog class="pe-modal pe-modal--confirm" id="<?= pe_h($dialogId) ?>">
        <div class="pe-modal__shell">
            <header class="pe-modal__header">
                <div>
                    <div class="card-kicker">Excluir <?= pe_h($entityLabel) ?></div>
                    <h2>Confirmar exclusão</h2>
                    <p>Esta ação não deve ser usada apenas para corrigir dados. Prefira editar quando o registro ainda for válido.</p>
                </div>
                <button type="button" class="pe-modal__close" data-pe-dialog-close aria-label="Fechar"><i class="bi bi-x-lg"></i></button>
            </header>
            <div class="pe-modal__body">
                <div class="pe-delete-warning"><i class="bi bi-exclamation-triangle"></i><div><strong data-pe-current-title>Registro selecionado</strong><span>A exclusão será executada somente após sua confirmação.</span></div></div>
                <form method="post" class="pe-delete-form">
                    <?= pe_csrf_field() ?>
                    <input type="hidden" name="pe_action" value="<?= pe_h($action) ?>">
                    <input type="hidden" name="id" value="" data-pe-field="id">
                    <label class="pe-check-option pe-delete-confirm"><input type="checkbox" required><span>Confirmo que desejo excluir este registro.</span></label>
                    <footer class="pe-action-modal-footer">
                        <button type="button" class="btn btn-light" data-pe-dialog-close>Cancelar</button>
                        <button type="submit" class="btn btn-danger"><i class="bi bi-trash3"></i> Excluir</button>
                    </footer>
                </form>
            </div>
        </div>
    </dialog>
    <?php
}
