<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Repositories\KitMaternityRepository;

/**
 * Conecta as views já existentes do Kit Maternidade ao backend real.
 * Enquanto as migrations 016/017 não existirem no banco o repository retorna
 * coleções vazias, sem inventar dados operacionais.
 *
 * @param array<string,mixed> $definition
 * @return array{definition:array<string,mixed>,custom:string,context:array<string,mixed>}
 */
function km_runtime_page(string $pageKey, array $definition): array
{
    $repo = new KitMaternityRepository(Database::connection());
    $dashboard = $repo->dashboard();
    $records = $repo->list(500);
    $selectedId = max(0, (int) ($_GET['kit'] ?? 0));
    $selected = $selectedId > 0 ? $repo->find($selectedId) : null;
    $followUps = is_array($selected) ? $repo->followUps($selectedId) : [];
    $csrf = Csrf::token('kit_maternidade_operacao');

    $definition['demo'] = false;
    $definition['show_states'] = false;
    $definition['actions'] = km_runtime_actions($pageKey);
    $definition['stats'] = km_runtime_stats($pageKey, $dashboard, $records);
    $definition['filters'] = km_runtime_filters($pageKey);
    $definition['blocks'] = km_runtime_blocks($pageKey, $records, $followUps, $selected);

    $custom = km_runtime_custom_content($pageKey, $selected, $csrf);

    return [
        'definition' => $definition,
        'custom' => $custom,
        'context' => [
            'csrf' => $csrf,
            'selectedId' => $selectedId,
            'selected' => $selected,
        ],
    ];
}

/** @return list<array<string,mixed>> */
function km_runtime_actions(string $pageKey): array
{
    $actions = [];
    if ($pageKey !== 'cadastro' && sigas_operational_can('kit_maternidade.cadastrar')) {
        $actions[] = [
            'label' => 'Nova solicitação',
            'icon' => 'person-plus',
            'primary' => true,
            'href' => 'kit-maternidade/index.php?pagina=cadastro',
        ];
    }
    if (sigas_operational_can('socioeconomico.visualizar')) {
        $actions[] = [
            'label' => 'Prontuário socioeconômico',
            'icon' => 'person-vcard',
            'href' => 'prontuario-socioeconomico.php?retorno=kit-maternidade/index.php',
        ];
    }
    return $actions;
}

/** @param array<string,int> $dashboard @param list<array<string,mixed>> $records @return list<array<string,mixed>> */
function km_runtime_stats(string $pageKey, array $dashboard, array $records): array
{
    $stat = static fn (string $label, int|string $value, string $detail, string $icon): array => [
        'label' => $label,
        'value' => (string) $value,
        'detail' => $detail,
        'icon' => $icon,
    ];

    $base = [
        $stat('Candidatas', $dashboard['total'] ?? 0, 'Solicitações registradas', 'person-hearts'),
        $stat('Em acompanhamento', $dashboard['em_acompanhamento'] ?? 0, 'Fluxos ativos', 'clipboard2-pulse'),
        $stat('Gestação de risco', $dashboard['risco'] ?? 0, 'Acompanhamento destacado', 'exclamation-triangle'),
        $stat('DPP em 30 dias', $dashboard['dpp_30_dias'] ?? 0, 'Sem entrega registrada', 'calendar-event'),
    ];

    if ($pageKey === 'avaliacao') {
        return [
            $stat('Aptas', $dashboard['aptas'] ?? 0, 'Decisão humana registrada', 'check-circle'),
            $stat('Não aptas', $dashboard['nao_aptas'] ?? 0, 'Decisão fundamentada', 'x-circle'),
            $stat('Em acompanhamento', $dashboard['em_acompanhamento'] ?? 0, 'Aguardando avaliação/conclusão', 'hourglass-split'),
            $stat('Gestação de risco', $dashboard['risco'] ?? 0, 'Risco não define decisão automaticamente', 'shield-exclamation'),
        ];
    }
    if ($pageKey === 'entregas') {
        return [
            $stat('Aptas', $dashboard['aptas'] ?? 0, 'Podem avançar para entrega', 'check-circle'),
            $stat('Entregues', $dashboard['entregues'] ?? 0, 'Kits efetivados', 'gift'),
            $stat('Aguardando entrega', max(0, ($dashboard['aptas'] ?? 0) - ($dashboard['entregues'] ?? 0)), 'Aptas ainda sem entrega', 'box-seam'),
            $stat('DPP em 30 dias', $dashboard['dpp_30_dias'] ?? 0, 'Priorizar conferência', 'calendar-event'),
        ];
    }
    if ($pageKey === 'visitas') {
        $visits = array_sum(array_map(static fn (array $row): int => (int) ($row['visitas'] ?? 0), $records));
        return [
            $stat('Visitas registradas', $visits, 'Total do acompanhamento atual', 'house-check'),
            $stat('Candidatas acompanhadas', $dashboard['em_acompanhamento'] ?? 0, 'Fluxos ativos', 'person-check'),
            $stat('Gestação de risco', $dashboard['risco'] ?? 0, 'Risco identificado no cadastro ou acompanhamento', 'exclamation-triangle'),
            $stat('DPP em 30 dias', $dashboard['dpp_30_dias'] ?? 0, 'Atenção territorial', 'calendar-event'),
        ];
    }
    if ($pageKey === 'reunioes') {
        $meetings = array_sum(array_map(static fn (array $row): int => (int) ($row['reunioes'] ?? 0), $records));
        $presences = array_sum(array_map(static fn (array $row): int => (int) ($row['reunioes_presentes'] ?? 0), $records));
        return [
            $stat('Reuniões registradas', $meetings, 'Participações lançadas', 'people'),
            $stat('Presenças', $presences, 'Comparecimentos registrados', 'person-check'),
            $stat('Candidatas', $dashboard['total'] ?? 0, 'Solicitações no programa', 'person-hearts'),
            $stat('Em acompanhamento', $dashboard['em_acompanhamento'] ?? 0, 'Fluxos ativos', 'clipboard2-pulse'),
        ];
    }

    return $base;
}

/** @return list<array<string,mixed>> */
function km_runtime_filters(string $pageKey): array
{
    if ($pageKey === 'relatorios') {
        return [];
    }
    return [
        ['label' => 'Situação', 'options' => ['solicitado', 'aguardando socioeconomico', 'em acompanhamento', 'em analise', 'apto', 'nao apto', 'entregue', 'encerrado']],
        ['label' => 'Risco', 'options' => ['Sim', 'Não']],
    ];
}

/** @param list<array<string,mixed>> $records @param list<array<string,mixed>> $followUps @param array<string,mixed>|null $selected @return list<array<string,mixed>> */
function km_runtime_blocks(string $pageKey, array $records, array $followUps, ?array $selected): array
{
    if ($pageKey === 'cadastro') {
        return [];
    }

    if ($pageKey === 'relatorios') {
        return [[
            'type' => 'table',
            'kicker' => 'Relatórios',
            'title' => 'Indicadores disponíveis',
            'description' => 'Os indicadores abaixo são calculados a partir do fluxo operacional do Kit Maternidade.',
            'columns' => [
                ['key' => 'relatorio', 'label' => 'Relatório'],
                ['key' => 'descricao', 'label' => 'Descrição'],
            ],
            'rows' => [
                ['relatorio' => 'Candidatas por situação', 'descricao' => 'Solicitação, acompanhamento, decisão, entrega e encerramento.'],
                ['relatorio' => 'Acompanhamento gestacional', 'descricao' => 'Semanas, visitas, reuniões, risco e responsável técnico.'],
                ['relatorio' => 'Decisões e entregas', 'descricao' => 'Aptas, não aptas, pendências e kits entregues.'],
            ],
            'primary' => 'relatorio',
        ]];
    }

    if (in_array($pageKey, ['visitas', 'reunioes'], true) && is_array($selected)) {
        $type = $pageKey === 'visitas' ? 'visita' : 'reuniao';
        $rows = [];
        foreach ($followUps as $item) {
            if ((string) ($item['tipo'] ?? '') !== $type) {
                continue;
            }
            $rows[] = [
                'data' => km_runtime_datetime($item['data_evento'] ?? null),
                'semanas' => $item['idade_gestacional_semanas'] === null ? '—' : (string) $item['idade_gestacional_semanas'] . ' sem.',
                'participacao' => $item['participacao'] ?: '—',
                'risco' => !empty($item['risco_identificado']) ? 'Sim' : 'Não',
                'responsavel' => (string) ($item['usuario_nome'] ?? '—'),
                'observacao' => (string) ($item['observacao'] ?? '—'),
            ];
        }
        return [[
            'type' => 'table',
            'kicker' => 'Histórico',
            'title' => $pageKey === 'visitas' ? 'Visitas da candidata' : 'Participação em reuniões',
            'description' => 'Histórico preservado do acompanhamento.',
            'columns' => [
                ['key' => 'data', 'label' => 'Data'],
                ['key' => 'semanas', 'label' => 'Gestação'],
                ['key' => 'participacao', 'label' => 'Participação'],
                ['key' => 'risco', 'label' => 'Risco'],
                ['key' => 'responsavel', 'label' => 'Responsável'],
                ['key' => 'observacao', 'label' => 'Observação'],
            ],
            'rows' => $rows,
            'primary' => 'data',
        ]];
    }

    $rows = array_map(static fn (array $record): array => km_runtime_row($record, $pageKey), $records);
    $columns = match ($pageKey) {
        'visitas' => [
            ['key' => 'candidata', 'label' => 'Candidata'],
            ['key' => 'semanas', 'label' => 'Semanas'],
            ['key' => 'visitas', 'label' => 'Visitas'],
            ['key' => 'ultimo_acompanhamento', 'label' => 'Último acompanhamento'],
            ['key' => 'responsavel', 'label' => 'Responsável'],
            ['key' => 'risco', 'label' => 'Risco'],
        ],
        'reunioes' => [
            ['key' => 'candidata', 'label' => 'Candidata'],
            ['key' => 'semanas', 'label' => 'Semanas'],
            ['key' => 'reunioes', 'label' => 'Reuniões'],
            ['key' => 'presencas', 'label' => 'Presenças'],
            ['key' => 'responsavel', 'label' => 'Responsável'],
            ['key' => 'situacao', 'label' => 'Situação'],
        ],
        'avaliacao' => [
            ['key' => 'candidata', 'label' => 'Candidata'],
            ['key' => 'socioeconomico', 'label' => 'Socioeconômico'],
            ['key' => 'visitas', 'label' => 'Visitas'],
            ['key' => 'reunioes', 'label' => 'Reuniões'],
            ['key' => 'risco', 'label' => 'Risco'],
            ['key' => 'decisao', 'label' => 'Decisão'],
        ],
        'entregas' => [
            ['key' => 'candidata', 'label' => 'Candidata'],
            ['key' => 'dpp', 'label' => 'DPP'],
            ['key' => 'decisao', 'label' => 'Decisão'],
            ['key' => 'entrega', 'label' => 'Entrega'],
            ['key' => 'lote', 'label' => 'Lote'],
            ['key' => 'responsavel', 'label' => 'Responsável'],
        ],
        'pos-parto' => [
            ['key' => 'candidata', 'label' => 'Candidata'],
            ['key' => 'dpp', 'label' => 'DPP'],
            ['key' => 'entrega', 'label' => 'Kit'],
            ['key' => 'situacao', 'label' => 'Situação'],
            ['key' => 'responsavel', 'label' => 'Responsável'],
        ],
        default => [
            ['key' => 'candidata', 'label' => 'Candidata'],
            ['key' => 'cpf', 'label' => 'CPF'],
            ['key' => 'semanas', 'label' => 'Gestação'],
            ['key' => 'dpp', 'label' => 'DPP'],
            ['key' => 'risco', 'label' => 'Risco'],
            ['key' => 'visitas', 'label' => 'Visitas'],
            ['key' => 'reunioes', 'label' => 'Reuniões'],
            ['key' => 'responsavel', 'label' => 'Responsável'],
            ['key' => 'situacao', 'label' => 'Situação'],
        ],
    };

    return [[
        'type' => 'table',
        'kicker' => 'Operação',
        'title' => match ($pageKey) {
            'visitas' => 'Candidatas para acompanhamento domiciliar',
            'reunioes' => 'Acompanhamento de reuniões e atividades',
            'avaliacao' => 'Fila de avaliação técnica',
            'entregas' => 'Controle de entrega dos Kits',
            'pos-parto' => 'Pós-parto e encerramento',
            default => 'Candidatas ao Kit Maternidade',
        },
        'description' => 'Clique em uma candidata para abrir as ações disponíveis conforme sua permissão.',
        'columns' => $columns,
        'rows' => $rows,
        'primary' => 'candidata',
    ]];
}

/** @param array<string,mixed> $record @return array<string,mixed> */
function km_runtime_row(array $record, string $pageKey): array
{
    $id = (int) ($record['id'] ?? 0);
    $cpf = preg_replace('/\D+/', '', (string) ($record['pessoa_cpf'] ?? '')) ?? '';
    $masked = strlen($cpf) === 11 ? substr($cpf, 0, 3) . '.***.***-' . substr($cpf, -2) : '—';
    $decision = match ((string) ($record['decisao'] ?? '')) {
        'apto' => 'Apta',
        'nao_apto' => 'Não apta',
        'pendente' => 'Pendente',
        default => 'Sem decisão',
    };
    $actions = [
        ['kind' => 'detail', 'label' => 'Ver resumo', 'description' => 'Consultar os dados desta candidata.', 'icon' => 'eye'],
    ];

    if (sigas_operational_can('kit_maternidade.acompanhar')) {
        $actions[] = ['kind' => 'href', 'label' => 'Registrar visita', 'description' => 'Abrir acompanhamento domiciliar.', 'icon' => 'house-check', 'href' => 'kit-maternidade/index.php?pagina=visitas&kit=' . $id];
        $actions[] = ['kind' => 'href', 'label' => 'Registrar reunião', 'description' => 'Registrar presença ou ausência.', 'icon' => 'people', 'href' => 'kit-maternidade/index.php?pagina=reunioes&kit=' . $id];
    }
    if (sigas_operational_can('kit_maternidade.avaliar')) {
        $actions[] = ['kind' => 'href', 'label' => 'Avaliar candidata', 'description' => 'Registrar parecer e decisão humana.', 'icon' => 'clipboard2-check', 'href' => 'kit-maternidade/index.php?pagina=avaliacao&kit=' . $id];
    }
    if (sigas_operational_can('kit_maternidade.entregar')) {
        $actions[] = ['kind' => 'href', 'label' => 'Entrega do Kit', 'description' => 'Registrar entrega quando a candidata estiver apta.', 'icon' => 'gift', 'href' => 'kit-maternidade/index.php?pagina=entregas&kit=' . $id];
    }
    if (sigas_operational_can('socioeconomico.visualizar') && $cpf !== '') {
        $actions[] = ['kind' => 'href', 'label' => 'Prontuário socioeconômico', 'description' => 'Consultar o formulário central da pessoa.', 'icon' => 'person-vcard', 'href' => 'prontuario-socioeconomico.php?cpf=' . $cpf . '&retorno=kit-maternidade/index.php'];
    }

    return [
        'id' => $id,
        'candidata' => (string) ($record['pessoa_nome'] ?? '—'),
        'cpf' => $masked,
        'bairro' => (string) ($record['bairro'] ?? '—'),
        'semanas' => $record['semanas_gestacao'] === null ? '—' : (string) $record['semanas_gestacao'] . ' sem.',
        'dpp' => km_runtime_date($record['dpp'] ?? null),
        'risco' => !empty($record['gestacao_risco']) ? 'Sim' : 'Não',
        'visitas' => (string) ((int) ($record['visitas'] ?? 0)),
        'reunioes' => (string) ((int) ($record['reunioes'] ?? 0)),
        'presencas' => (string) ((int) ($record['reunioes_presentes'] ?? 0)),
        'responsavel' => (string) ($record['responsavel_tecnico_nome'] ?? 'Não definido'),
        'ultimo_acompanhamento' => km_runtime_datetime($record['ultimo_acompanhamento'] ?? null),
        'socioeconomico' => !empty($record['socioeconomico_id']) ? 'Preenchido' : 'Pendente',
        'decisao' => $decision,
        'entrega' => !empty($record['entregue_em']) ? km_runtime_datetime($record['entregue_em']) : 'Pendente',
        'lote' => (string) ($record['lote'] ?? '—'),
        'situacao' => str_replace('_', ' ', (string) ($record['status'] ?? 'solicitado')),
        '_actions' => $actions,
    ];
}

/** @param array<string,mixed>|null $selected */
function km_runtime_custom_content(string $pageKey, ?array $selected, string $csrf): string
{
    if ($pageKey === 'cadastro') {
        return km_runtime_request_form($csrf);
    }
    if (!is_array($selected)) {
        if (in_array($pageKey, ['visitas', 'reunioes', 'avaliacao', 'entregas', 'pos-parto'], true)) {
            return '<section class="content-card mt-3"><div class="card-heading"><div><div class="card-kicker">Como usar</div><h2>Selecione uma candidata</h2><p>Clique em uma linha da lista e escolha a ação correspondente para abrir o registro específico.</p></div></div></section>';
        }
        return '';
    }

    $header = km_runtime_selected_header($selected);
    return match ($pageKey) {
        'visitas' => $header . km_runtime_followup_form($selected, $csrf, 'visita'),
        'reunioes' => $header . km_runtime_followup_form($selected, $csrf, 'reuniao'),
        'avaliacao' => $header . km_runtime_evaluation_form($selected, $csrf),
        'entregas' => $header . km_runtime_delivery_form($selected, $csrf),
        'pos-parto' => $header . km_runtime_postpartum_form($selected, $csrf),
        default => '',
    };
}

function km_runtime_request_form(string $csrf): string
{
    $socioLink = sigas_operational_can('socioeconomico.visualizar')
        ? '<a class="btn btn-light" data-km-socio-link href="prontuario-socioeconomico.php?retorno=kit-maternidade/index.php?pagina=cadastro"><i class="bi bi-person-vcard"></i> Abrir prontuário socioeconômico</a>'
        : '';
    ob_start(); ?>
    <section class="content-card mt-3 km-request-card">
        <div class="card-heading"><div><div class="card-kicker">Cadastro único</div><h2>Nova solicitação do Kit Maternidade</h2><p>Primeiro localize a pessoa pelo CPF. Se ela já existir em qualquer módulo, apenas vinculamos uma nova solicitação.</p></div></div>
        <div class="km-lookup-grid">
            <div><label class="form-label" for="kmCpfLookup">CPF da candidata</label><input class="form-control form-control-lg" id="kmCpfLookup" inputmode="numeric" maxlength="14" placeholder="000.000.000-00"></div>
            <button class="btn btn-primary btn-lg" type="button" data-km-person-lookup><i class="bi bi-search"></i> Localizar pessoa</button>
        </div>
        <div class="km-person-result mt-3" data-km-person-result hidden></div>
        <div class="d-flex flex-wrap gap-2 mt-3" data-km-person-links hidden><?= $socioLink ?></div>

        <form class="km-real-form mt-4" data-km-form="abrir" hidden>
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="abrir">
            <input type="hidden" name="pessoa_id" data-km-person-id>
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">DUM</label><input class="form-control" type="date" name="dum"></div>
                <div class="col-md-4"><label class="form-label">DPP</label><input class="form-control" type="date" name="dpp"></div>
                <div class="col-md-4"><label class="form-label">Pré-natal</label><select class="form-select" name="prenatal_iniciado"><option value="0">Não informado / não</option><option value="1">Sim</option></select></div>
                <div class="col-md-6"><label class="form-label">Unidade do pré-natal</label><input class="form-control" name="unidade_prenatal" maxlength="150"></div>
                <div class="col-md-3"><label class="form-label">Número da gestação</label><input class="form-control" type="number" min="1" name="numero_gestacao"></div>
                <div class="col-md-3"><label class="form-label">Partos anteriores</label><input class="form-control" type="number" min="0" name="numero_partos"></div>
                <div class="col-md-4"><label class="form-label">Gestação de risco?</label><select class="form-select" name="gestacao_risco" data-km-risk-select><option value="0">Não</option><option value="1">Sim</option></select></div>
                <div class="col-md-8"><label class="form-label">Descrição do risco</label><input class="form-control" name="risco_descricao" maxlength="500" data-km-risk-description></div>
                <div class="col-12"><label class="form-label">Observação inicial</label><textarea class="form-control" name="observacao" rows="3" maxlength="2000"></textarea></div>
            </div>
            <div class="km-human-decision-note mt-3"><i class="bi bi-shield-check"></i><span>A solicitação não concede o Kit automaticamente. A candidata seguirá para acompanhamento e avaliação humana.</span></div>
            <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Registrar solicitação</button></div>
        </form>
    </section>
    <?php return (string) ob_get_clean();
}

/** @param array<string,mixed> $selected */
function km_runtime_selected_header(array $selected): string
{
    $risk = !empty($selected['gestacao_risco']) ? '<span class="badge text-bg-danger">Gestação de risco</span>' : '<span class="badge text-bg-success">Sem risco registrado</span>';
    return '<section class="content-card mt-3 km-selected-summary"><div><div class="card-kicker">Candidata selecionada</div><h2>' . htmlspecialchars((string) ($selected['pessoa_nome'] ?? '—'), ENT_QUOTES, 'UTF-8') . '</h2><p>' . htmlspecialchars((string) (($selected['semanas_gestacao'] ?? '—') . ' semanas · DPP ' . km_runtime_date($selected['dpp'] ?? null)), ENT_QUOTES, 'UTF-8') . '</p></div><div class="d-flex flex-wrap gap-2 align-items-center">' . $risk . '<span class="badge text-bg-light">Responsável: ' . htmlspecialchars((string) ($selected['responsavel_tecnico_nome'] ?? 'Não definido'), ENT_QUOTES, 'UTF-8') . '</span></div></section>';
}

/** @param array<string,mixed> $selected */
function km_runtime_followup_form(array $selected, string $csrf, string $type): string
{
    $meeting = $type === 'reuniao';
    ob_start(); ?>
    <section class="content-card mt-3">
        <div class="card-heading"><div><div class="card-kicker">Acompanhamento</div><h2><?= $meeting ? 'Registrar reunião/atividade' : 'Registrar visita' ?></h2><p>O registro fica vinculado à candidata, data, semanas de gestação e servidor responsável.</p></div></div>
        <form class="km-real-form" data-km-form="acompanhamento">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>">
            <input type="hidden" name="action" value="acompanhamento"><input type="hidden" name="kit_solicitacao_id" value="<?= (int) $selected['id'] ?>"><input type="hidden" name="tipo" value="<?= $meeting ? 'reuniao' : 'visita' ?>">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Data e hora</label><input class="form-control" type="datetime-local" name="data_evento" value="<?= date('Y-m-d\TH:i') ?>"></div>
                <div class="col-md-4"><label class="form-label">Semanas de gestação</label><input class="form-control" type="number" min="0" max="45" name="idade_gestacional_semanas" value="<?= htmlspecialchars((string) ($selected['semanas_gestacao'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"></div>
                <?php if ($meeting): ?><div class="col-md-4"><label class="form-label">Participação</label><select class="form-select" name="participacao" required><option value="presente">Presente</option><option value="ausente">Ausente</option><option value="justificada">Ausência justificada</option></select></div><?php endif; ?>
                <div class="col-md-4"><label class="form-label">Foi identificado risco?</label><select class="form-select" name="risco_identificado" data-km-risk-select><option value="0">Não</option><option value="1">Sim</option></select></div>
                <div class="col-md-8"><label class="form-label">Descrição do risco</label><input class="form-control" name="risco_descricao" maxlength="500" data-km-risk-description></div>
                <div class="col-12"><label class="form-label">Informações / observações</label><textarea class="form-control" name="observacao" rows="4" maxlength="4000"></textarea></div>
                <div class="col-md-8"><label class="form-label">Próxima ação</label><input class="form-control" name="proxima_acao" maxlength="255"></div>
                <div class="col-md-4"><label class="form-label">Agendar para</label><input class="form-control" type="datetime-local" name="proxima_acao_em"></div>
            </div>
            <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" type="submit"><i class="bi bi-floppy"></i> Salvar acompanhamento</button></div>
        </form>
    </section>
    <?php return (string) ob_get_clean();
}

/** @param array<string,mixed> $selected */
function km_runtime_evaluation_form(array $selected, string $csrf): string
{
    $hasProfile = !empty($selected['socioeconomico_id']);
    ob_start(); ?>
    <section class="content-card mt-3">
        <div class="card-heading"><div><div class="card-kicker">Decisão humana</div><h2>Avaliação técnica</h2><p>O sistema apresenta os dados, mas não escolhe a decisão. O parecer deve ser fundamentado pelo servidor autorizado.</p></div></div>
        <?php if (!$hasProfile): ?><div class="alert alert-warning"><i class="bi bi-exclamation-triangle"></i> Esta candidata ainda não possui formulário socioeconômico central. Preencha-o antes de concluir a avaliação.</div><?php endif; ?>
        <form class="km-real-form" data-km-form="avaliar">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="avaliar"><input type="hidden" name="kit_solicitacao_id" value="<?= (int) $selected['id'] ?>">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Resultado</label><select class="form-select" name="resultado" required><option value="pendente">Pendente / solicitar complemento</option><option value="apto">Apta</option><option value="nao_apto">Não apta</option></select></div>
                <div class="col-md-8"><label class="form-label">Pendências, se houver</label><input class="form-control" name="pendencias_texto" maxlength="500" placeholder="Ex.: visita pendente; documento; atualização socioeconômica"></div>
                <div class="col-12"><label class="form-label">Parecer técnico</label><textarea class="form-control" name="parecer_tecnico" rows="7" minlength="10" maxlength="6000" required></textarea></div>
            </div>
            <div class="km-human-decision-note mt-3"><i class="bi bi-person-check"></i><span>Risco, renda, visitas e presença em reuniões são elementos de análise. Nenhum deles gera aprovação ou indeferimento automático.</span></div>
            <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" type="submit" <?= !$hasProfile ? 'disabled' : '' ?>><i class="bi bi-clipboard2-check"></i> Registrar avaliação</button></div>
        </form>
    </section>
    <?php return (string) ob_get_clean();
}

/** @param array<string,mixed> $selected */
function km_runtime_delivery_form(array $selected, string $csrf): string
{
    $eligible = (string) ($selected['decisao'] ?? '') === 'apto';
    $delivered = !empty($selected['entregue_em']);
    ob_start(); ?>
    <section class="content-card mt-3">
        <div class="card-heading"><div><div class="card-kicker">Efetivação</div><h2>Entrega do Kit</h2><p>Somente candidatas com decisão APTA podem ter a entrega registrada.</p></div></div>
        <?php if (!$eligible): ?><div class="alert alert-warning">A candidata ainda não possui decisão APTA.</div><?php endif; ?>
        <?php if ($delivered): ?><div class="alert alert-success">Kit entregue em <?= htmlspecialchars(km_runtime_datetime($selected['entregue_em']), ENT_QUOTES, 'UTF-8') ?><?= !empty($selected['lote']) ? ' · Lote ' . htmlspecialchars((string) $selected['lote'], ENT_QUOTES, 'UTF-8') : '' ?>.</div><?php endif; ?>
        <form class="km-real-form" data-km-form="entregar">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="entregar"><input type="hidden" name="kit_solicitacao_id" value="<?= (int) $selected['id'] ?>">
            <div class="row g-3">
                <div class="col-md-4"><label class="form-label">Data/hora da entrega</label><input class="form-control" type="datetime-local" name="entregue_em" value="<?= date('Y-m-d\TH:i') ?>"></div>
                <div class="col-md-4"><label class="form-label">Lote</label><input class="form-control" name="lote" maxlength="80"></div>
                <div class="col-md-4"><label class="form-label">Termo / referência</label><input class="form-control" name="termo_referencia" maxlength="100"></div>
                <div class="col-md-8"><label class="form-label">Recebedor</label><input class="form-control" name="recebedor_nome" maxlength="150" value="<?= htmlspecialchars((string) ($selected['pessoa_nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>" required></div>
                <div class="col-md-4"><label class="form-label">CPF do recebedor</label><input class="form-control" name="recebedor_cpf" maxlength="14"></div>
                <div class="col-12"><label class="form-label">Observação</label><textarea class="form-control" name="observacao" rows="3" maxlength="2000"></textarea></div>
            </div>
            <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" type="submit" <?= (!$eligible || $delivered) ? 'disabled' : '' ?>><i class="bi bi-gift"></i> Confirmar entrega</button></div>
        </form>
    </section>
    <?php return (string) ob_get_clean();
}

/** @param array<string,mixed> $selected */
function km_runtime_postpartum_form(array $selected, string $csrf): string
{
    ob_start(); ?>
    <section class="content-card mt-3">
        <div class="card-heading"><div><div class="card-kicker">Pós-parto</div><h2>Encerramento do acompanhamento</h2><p>Registre o fechamento do fluxo, preservando todo o histórico da solicitação, visitas, reuniões, avaliação e entrega.</p></div></div>
        <form class="km-real-form" data-km-form="encerrar">
            <input type="hidden" name="_csrf" value="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"><input type="hidden" name="action" value="encerrar"><input type="hidden" name="kit_solicitacao_id" value="<?= (int) $selected['id'] ?>">
            <label class="form-label">Observação de encerramento</label><textarea class="form-control" name="observacao" rows="5" maxlength="2000" required placeholder="Ex.: nascimento registrado, Kit entregue, acompanhamento encerrado... "></textarea>
            <div class="d-flex justify-content-end mt-3"><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Encerrar acompanhamento</button></div>
        </form>
    </section>
    <?php return (string) ob_get_clean();
}

function km_runtime_date(mixed $value): string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') return '—';
    $timestamp = strtotime($raw);
    return $timestamp === false ? '—' : date('d/m/Y', $timestamp);
}

function km_runtime_datetime(mixed $value): string
{
    $raw = trim((string) ($value ?? ''));
    if ($raw === '') return '—';
    $timestamp = strtotime($raw);
    return $timestamp === false ? '—' : date('d/m/Y H:i', $timestamp);
}
