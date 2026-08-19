<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';
require_once dirname(__DIR__) . '/lib/lotacoes.php';
require_once dirname(__DIR__) . '/lib/list-ui.php';

$pageDefinition = [
    'title' => 'Lotações',
    'description' => 'Situação de lotação de todos os candidatos, com pendências, vínculos e histórico de atuação.',
    'demo' => false,
    'show_states' => false,
    'actions' => [],
    'modal' => ['title' => 'Lotação'],
];

$dbReady = pe_db_ready() && pe_schema_ready();
$message = null;
$rows = [];
$candidates = [];
$partners = [];
$placementReady = false;

if ($dbReady) {
    $pdo = pe_db();
    $placementReady = pe_lotacao_table_ready($pdo);

    if ($placementReady) {
        $candidates = pe_lotacao_candidates($pdo);
        $partners = pe_lotacao_partners($pdo);

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            try {
                pe_verify_csrf();
                $action = (string) ($_POST['pe_action'] ?? '');

                if ($action === 'save_placement') {
                    pe_lotacao_save($pdo, $_POST);
                    $message = ['type' => 'success', 'text' => 'Lotação salva com sucesso.'];
                } elseif ($action === 'end_placement') {
                    pe_lotacao_end($pdo, (int) ($_POST['id'] ?? 0), (string) ($_POST['data_fim'] ?? ''));
                    $message = ['type' => 'success', 'text' => 'Lotação encerrada e preservada no histórico.'];
                }
            } catch (Throwable $e) {
                $message = ['type' => 'danger', 'text' => $e->getMessage()];
            }
        }

        $rows = pe_lotacao_rows($pdo);
    }
}

function pe_lotacao_date_br(?string $date): string
{
    if (!$date) return '—';
    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : '—';
}

$lotados = pe_count_rows($rows, fn(array $r): bool => ($r['situacao_lotacao'] ?? '') === 'Lotado');
$naoLotados = pe_count_rows($rows, fn(array $r): bool => ($r['situacao_lotacao'] ?? '') === 'Não lotado');
$revisarLotacao = pe_count_rows($rows, fn(array $r): bool => ($r['situacao_lotacao'] ?? '') === 'Revisar lotação');
$prontos = pe_count_rows($rows, fn(array $r): bool => ($r['situacao_lotacao'] ?? '') === 'Pronto para importar');
$pendenciasCadastro = pe_count_rows($rows, fn(array $r): bool => trim((string) ($r['revisao_status'] ?? '')) !== '');

foreach ($rows as &$row) {
    $row['cadastro_situacao'] = trim((string) ($row['revisao_status'] ?? '')) !== '' ? (string) $row['revisao_status'] : 'Regular';
    $row['parceiro_filtro'] = trim((string) ($row['parceiro_sigla'] ?? '')) !== ''
        ? (string) $row['parceiro_sigla']
        : (string) ($row['parceiro_nome'] ?? '');
    $row['local_filtro'] = trim((string) ($row['local_atuacao'] ?? '')) !== ''
        ? (string) $row['local_atuacao']
        : (string) ($row['setor_informado'] ?? '');
}
unset($row);

ob_start();
?>
<section class="content-card pe-form-card pe-page pe-list-page">
    <?php if (!$dbReady): ?>
        <?= pe_db_notice() ?>
    <?php elseif (!$placementReady): ?>
        <div class="alert alert-warning mb-3">
            <strong>Estrutura de lotações não encontrada.</strong>
            Execute <code>database/primeiroEmprego/0004-primeiroEmprego-lotacoes.sql</code>.
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-<?= pe_h($message['type']) ?>"><?= pe_h($message['text']) ?></div>
    <?php endif; ?>

    <?php pe_list_header(
        'Distribuição do programa',
        'Situação das lotações',
        'Visualize todos os candidatos, identifique quem está lotado, quem ainda não possui vínculo e quem precisa de revisão.',
        'Nova lotação',
        '#pePlacementForm',
        'diagram-3'
    ); ?>

    <?php pe_list_metrics([
        ['label' => 'Candidatos', 'value' => count($rows), 'tone' => 'neutral'],
        ['label' => 'Lotados', 'value' => $lotados, 'tone' => 'success', 'filter_key' => 'lotacao', 'filter_value' => 'Lotado'],
        ['label' => 'Não lotados', 'value' => $naoLotados, 'tone' => 'warning', 'filter_key' => 'lotacao', 'filter_value' => 'Não lotado'],
        ['label' => 'Revisar lotação', 'value' => $revisarLotacao, 'tone' => 'danger', 'filter_key' => 'lotacao', 'filter_value' => 'Revisar lotação'],
        ['label' => 'Prontos p/ importar', 'value' => $prontos, 'tone' => 'info', 'filter_key' => 'lotacao', 'filter_value' => 'Pronto para importar'],
        ['label' => 'Pendência cadastral', 'value' => $pendenciasCadastro, 'tone' => 'warning', 'filter_key' => 'cadastro_grupo', 'filter_value' => 'Com pendência'],
    ], '#pePlacementFilters'); ?>

    <?php pe_list_filter_panel(
        'pePlacementTable',
        'Buscar nome, CPF, órgão, local, setor ou informação original...',
        [
            ['key' => 'lotacao', 'label' => 'Situação da lotação', 'options' => [
                '' => 'Todas',
                'Lotado' => 'Lotados',
                'Não lotado' => 'Não lotados',
                'Revisar lotação' => 'Revisar lotação',
                'Pronto para importar' => 'Prontos para importar',
            ]],
            ['key' => 'cadastro_grupo', 'label' => 'Cadastro', 'options' => [
                '' => 'Todos', 'Regular' => 'Regular', 'Com pendência' => 'Com pendência',
            ]],
            ['key' => 'cadastro', 'label' => 'Tipo de pendência', 'options' => pe_filter_options($rows, 'cadastro_situacao', 'Todas')],
            ['key' => 'parceiro', 'label' => 'Órgão / instituição', 'options' => pe_filter_options($rows, 'parceiro_filtro', 'Todos')],
            ['key' => 'local', 'label' => 'Local / setor', 'options' => pe_filter_options($rows, 'local_filtro', 'Todos')],
        ],
        count($rows),
        'candidato(s)'
    ); ?>

    <div class="pe-table-wrap">
        <div class="table-responsive">
            <table id="pePlacementTable" class="table align-middle pe-data-table pe-list-table" data-pe-list-table>
                <thead>
                <tr>
                    <th>Candidato</th>
                    <th>CPF</th>
                    <th>Cadastro</th>
                    <th>Órgão / instituição</th>
                    <th>Local / setor</th>
                    <th>Lotação</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="pe-empty-row"><td colspan="7" class="text-center text-muted py-5">Nenhum candidato encontrado.</td></tr>
                <?php endif; ?>

                <?php foreach ($rows as $row): ?>
                    <?php
                    $hasPlacement = (int) ($row['id'] ?? 0) > 0;
                    $record = $row;
                    $record['id'] = $hasPlacement ? (int) $row['id'] : '';
                    if (!$hasPlacement) {
                        $record['status'] = 'Ativa';
                        $record['data_inicio'] = date('Y-m-d');
                        $record['data_fim'] = '';
                        $record['origem'] = 'manual';
                    }
                    $record['__title'] = $row['nome'];
                    $record['__subtitle'] = (string) $row['situacao_lotacao'];
                    $record['cpf_formatado'] = pe_format_cpf($row['cpf'] ?: $row['cpf_informado']);
                    $record['telefone_formatado'] = pe_format_phone($row['telefone'] ?: '');
                    $record['cadastro_situacao'] = $row['cadastro_situacao'];
                    $record['situacao_lotacao_exibicao'] = $row['situacao_lotacao'];
                    $record['parceiro_nome_exibicao'] = trim((string) ($row['parceiro_nome'] ?? '')) !== ''
                        ? (trim((string) ($row['parceiro_sigla'] ?? '')) !== '' ? $row['parceiro_sigla'] . ' — ' : '') . $row['parceiro_nome']
                        : 'Não vinculado';
                    $record['local_atuacao_exibicao'] = $row['local_atuacao'] ?: '—';
                    $record['setor_exibicao'] = $row['setor'] ?: '—';
                    $record['setor_informado_exibicao'] = $row['setor_informado'] ?: '—';
                    $record['turno_atuacao_exibicao'] = $row['turno_atuacao'] ?: '—';
                    $record['data_inicio_formatada'] = pe_lotacao_date_br($row['data_inicio']);
                    $record['data_fim_formatada'] = pe_lotacao_date_br($row['data_fim']);
                    $record['status_exibicao'] = $row['status'] ?: 'Sem lotação ativa';
                    $record['observacao_exibicao'] = $row['observacao'] ?: '—';
                    $record['registrado_por_exibicao'] = $row['registrado_por'] ?: '—';
                    $cadastroGrupo = $row['cadastro_situacao'] === 'Regular' ? 'Regular' : 'Com pendência';
                    $partnerDisplay = trim((string) ($row['parceiro_sigla'] ?? '')) !== ''
                        ? (string) $row['parceiro_sigla']
                        : (string) ($row['parceiro_nome'] ?? '');
                    $localDisplay = $row['local_atuacao'] ?: ($row['setor_informado'] ?: '—');
                    ?>
                    <tr
                        class="pe-list-row"
                        tabindex="0"
                        role="button"
                        data-pe-list-row
                        data-pe-actions-target="<?= $hasPlacement ? '#pePlacementActions' : '#pePlacementCandidateActions' ?>"
                        data-pe-record="<?= pe_record_attr($record) ?>"
                        data-pe-filter-lotacao="<?= pe_h($row['situacao_lotacao']) ?>"
                        data-pe-filter-cadastro-grupo="<?= pe_h($cadastroGrupo) ?>"
                        data-pe-filter-cadastro="<?= pe_h($row['cadastro_situacao']) ?>"
                        data-pe-filter-parceiro="<?= pe_h($row['parceiro_filtro']) ?>"
                        data-pe-filter-local="<?= pe_h($row['local_filtro']) ?>"
                    >
                        <td data-label="Candidato"><strong><?= pe_h($row['nome']) ?></strong><small class="d-block text-muted">#<?= (int) $row['candidato_id'] ?></small></td>
                        <td data-label="CPF"><?= pe_h(pe_format_cpf($row['cpf'] ?: $row['cpf_informado'])) ?></td>
                        <td data-label="Cadastro"><?= pe_status_label($row['cadastro_situacao']) ?></td>
                        <td data-label="Órgão / instituição">
                            <?= $partnerDisplay !== '' ? pe_h($partnerDisplay) : '<span class="text-muted">—</span>' ?>
                            <?php if ($partnerDisplay !== '' && trim((string) ($row['parceiro_nome'] ?? '')) !== '' && $partnerDisplay !== $row['parceiro_nome']): ?>
                                <small class="d-block text-muted"><?= pe_h($row['parceiro_nome']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td data-label="Local / setor">
                            <strong><?= pe_h($localDisplay) ?></strong>
                            <?php if ($row['setor']): ?><small class="d-block text-muted"><?= pe_h($row['setor']) ?></small><?php endif; ?>
                        </td>
                        <td data-label="Lotação"><?= pe_status_label($row['situacao_lotacao']) ?></td>
                        <td data-label="Status"><?= pe_status_label($row['candidato_status']) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="pe-filter-empty" data-pe-filter-empty hidden>Nenhum candidato corresponde aos filtros selecionados.</div>
    </div>

    <dialog class="pe-modal pe-modal--actions" id="pePlacementActions">
        <div class="pe-modal__shell"><header class="pe-modal__header"><div><div class="card-kicker">Lotação ativa</div><h2 data-pe-current-title>Registro selecionado</h2><p data-pe-current-subtitle></p></div><button type="button" class="pe-modal__close" data-pe-dialog-close><i class="bi bi-x-lg"></i></button></header>
            <div class="pe-modal__body"><div class="pe-modal-actions-title">Ações disponíveis</div><div class="pe-modal-actions">
                <button class="pe-modal-action" type="button" data-pe-open="#pePlacementView" data-pe-mode="view"><span class="pe-modal-action__icon"><i class="bi bi-eye"></i></span><span><strong>Visualizar</strong><small>Consultar vínculo e informação de origem</small></span><i class="bi bi-chevron-right"></i></button>
                <button class="pe-modal-action pe-modal-action--primary" type="button" data-pe-open="#pePlacementForm" data-pe-mode="edit"><span class="pe-modal-action__icon"><i class="bi bi-pencil-square"></i></span><span><strong>Editar / corrigir</strong><small>Atualizar órgão, local, setor, turno ou datas</small></span><i class="bi bi-chevron-right"></i></button>
                <button class="pe-modal-action pe-modal-action--danger" type="button" data-pe-open="#pePlacementEnd" data-pe-mode="view"><span class="pe-modal-action__icon"><i class="bi bi-box-arrow-right"></i></span><span><strong>Encerrar lotação</strong><small>Preservar o vínculo no histórico</small></span><i class="bi bi-chevron-right"></i></button>
            </div></div>
        </div>
    </dialog>

    <dialog class="pe-modal pe-modal--actions" id="pePlacementCandidateActions">
        <div class="pe-modal__shell"><header class="pe-modal__header"><div><div class="card-kicker">Situação de lotação</div><h2 data-pe-current-title>Candidato</h2><p data-pe-current-subtitle></p></div><button type="button" class="pe-modal__close" data-pe-dialog-close><i class="bi bi-x-lg"></i></button></header>
            <div class="pe-modal__body"><div class="pe-modal-actions-title">Ações disponíveis</div><div class="pe-modal-actions">
                <button class="pe-modal-action" type="button" data-pe-open="#pePlacementView" data-pe-mode="view"><span class="pe-modal-action__icon"><i class="bi bi-eye"></i></span><span><strong>Visualizar situação</strong><small>Ver cadastro e informação original da planilha</small></span><i class="bi bi-chevron-right"></i></button>
                <button class="pe-modal-action pe-modal-action--primary" type="button" data-pe-open="#pePlacementForm" data-pe-mode="edit"><span class="pe-modal-action__icon"><i class="bi bi-diagram-3"></i></span><span><strong>Cadastrar lotação</strong><small>Definir o vínculo correto deste candidato</small></span><i class="bi bi-chevron-right"></i></button>
            </div></div>
        </div>
    </dialog>

    <dialog class="pe-modal pe-modal--form" id="pePlacementForm" data-pe-create-title="Nova lotação" data-pe-edit-title="Cadastrar / editar lotação">
        <div class="pe-modal__shell"><header class="pe-modal__header"><div><div class="card-kicker">Lotação</div><h2 data-pe-form-title>Nova lotação</h2><p>Vincule o participante ao órgão, local, setor e período correto.</p></div><button type="button" class="pe-modal__close" data-pe-dialog-close><i class="bi bi-x-lg"></i></button></header>
            <div class="pe-modal__body"><form method="post" class="pe-action-form" data-pe-record-form autocomplete="off">
                <?= pe_csrf_field() ?><input type="hidden" name="pe_action" value="save_placement"><input type="hidden" name="id" data-pe-field="id"><input type="hidden" name="origem" value="manual" data-pe-field="origem"><input type="hidden" name="registrado_por" value="<?= pe_h(pe_current_user_label()) ?>">
                <div class="pe-action-form-grid pe-action-form-grid--2">
                    <div class="pe-field-span-2"><label class="form-label required">Candidato</label><select class="form-select" name="candidato_id" data-pe-field="candidato_id" required><option value="">Selecione...</option><?php foreach ($candidates as $candidate): ?><option value="<?= (int) $candidate['id'] ?>"><?= pe_h($candidate['nome']) ?><?= $candidate['lotacao_ativa_local'] ? ' — atualmente: ' . pe_h($candidate['lotacao_ativa_local']) : '' ?></option><?php endforeach; ?></select><small class="pe-field-help">Uma nova lotação ativa encerra automaticamente o vínculo ativo anterior.</small></div>
                    <div><label class="form-label">Órgão / instituição</label><select class="form-select" name="parceiro_id" data-pe-field="parceiro_id"><option value="">Não vinculado</option><?php foreach ($partners as $partner): ?><option value="<?= (int) $partner['id'] ?>"><?= pe_h(($partner['sigla'] ? $partner['sigla'] . ' — ' : '') . $partner['nome']) ?><?= $partner['status'] !== 'Ativa' ? ' — ' . pe_h($partner['status']) : '' ?></option><?php endforeach; ?></select></div>
                    <div><label class="form-label">Turno</label><select class="form-select" name="turno_atuacao" data-pe-field="turno_atuacao"><option value="">Não informado</option><?php foreach (['Matutino','Vespertino','Noturno','Integral'] as $shift): ?><option value="<?= pe_h($shift) ?>"><?= pe_h($shift) ?></option><?php endforeach; ?></select></div>
                    <div class="pe-field-span-2"><label class="form-label required">Local de atuação</label><input class="form-control" name="local_atuacao" data-pe-field="local_atuacao" maxlength="180" required placeholder="Ex.: CRAS I, Escola Municipal X, Sede SEMAS"></div>
                    <div><label class="form-label">Setor</label><input class="form-control" name="setor" data-pe-field="setor" maxlength="160" placeholder="Ex.: Administrativo, recepção, mídia"></div>
                    <div><label class="form-label required">Status</label><select class="form-select" name="status" data-pe-field="status" required><option value="Ativa" selected>Ativa</option><option value="Encerrada">Encerrada</option></select></div>
                    <div><label class="form-label required">Data de início</label><input class="form-control" type="date" name="data_inicio" data-pe-field="data_inicio" value="<?= pe_h(date('Y-m-d')) ?>" required></div>
                    <div><label class="form-label">Data de término</label><input class="form-control" type="date" name="data_fim" data-pe-field="data_fim"><small class="pe-field-help">Deixe vazio enquanto a lotação estiver ativa.</small></div>
                    <div class="pe-field-span-2"><label class="form-label">Observação</label><textarea class="form-control" name="observacao" data-pe-field="observacao" rows="3" maxlength="500"></textarea></div>
                </div>
                <footer class="pe-action-modal-footer"><button type="button" class="btn btn-light" data-pe-dialog-close>Cancelar</button><button class="btn btn-primary" type="submit"><i class="bi bi-floppy"></i> Salvar lotação</button></footer>
            </form></div>
        </div>
    </dialog>

    <dialog class="pe-modal pe-modal--view" id="pePlacementView">
        <div class="pe-modal__shell"><header class="pe-modal__header"><div><div class="card-kicker">Situação do candidato</div><h2 data-pe-current-title>Detalhes</h2><p data-pe-current-subtitle></p></div><button type="button" class="pe-modal__close" data-pe-dialog-close><i class="bi bi-x-lg"></i></button></header>
            <div class="pe-modal__body">
                <dl class="pe-modal-details pe-modal-details--2">
                    <div><dt>CPF</dt><dd data-pe-text="cpf_formatado">—</dd></div><div><dt>Telefone</dt><dd data-pe-text="telefone_formatado">—</dd></div>
                    <div><dt>Cadastro</dt><dd data-pe-text="cadastro_situacao">—</dd></div><div><dt>Situação da lotação</dt><dd data-pe-text="situacao_lotacao_exibicao">—</dd></div>
                    <div><dt>Órgão / instituição</dt><dd data-pe-text="parceiro_nome_exibicao">—</dd></div><div><dt>Local de atuação</dt><dd data-pe-text="local_atuacao_exibicao">—</dd></div>
                    <div><dt>Setor</dt><dd data-pe-text="setor_exibicao">—</dd></div><div><dt>Turno</dt><dd data-pe-text="turno_atuacao_exibicao">—</dd></div>
                    <div><dt>Início</dt><dd data-pe-text="data_inicio_formatada">—</dd></div><div><dt>Término</dt><dd data-pe-text="data_fim_formatada">—</dd></div>
                    <div><dt>Status do vínculo</dt><dd data-pe-text="status_exibicao">—</dd></div><div><dt>Registrado por</dt><dd data-pe-text="registrado_por_exibicao">—</dd></div>
                </dl>
                <div class="pe-view-note"><strong>Informação original da planilha</strong><p data-pe-text="setor_informado_exibicao">—</p></div>
                <div class="pe-view-note"><strong>Observação da lotação</strong><p data-pe-text="observacao_exibicao">—</p></div>
            </div>
        </div>
    </dialog>

    <dialog class="pe-modal pe-modal--confirm" id="pePlacementEnd">
        <div class="pe-modal__shell"><header class="pe-modal__header"><div><div class="card-kicker">Encerrar lotação</div><h2 data-pe-current-title>Lotação selecionada</h2><p>O vínculo não será apagado; continuará disponível no histórico.</p></div><button type="button" class="pe-modal__close" data-pe-dialog-close><i class="bi bi-x-lg"></i></button></header>
            <div class="pe-modal__body"><div class="pe-delete-warning"><i class="bi bi-clock-history"></i><div><strong data-pe-current-subtitle>Lotação selecionada</strong><span>A data final será registrada e o candidato ficará sem lotação ativa.</span></div></div>
                <form method="post" class="pe-delete-form"><?= pe_csrf_field() ?><input type="hidden" name="pe_action" value="end_placement"><input type="hidden" name="id" data-pe-field="id"><div class="mb-3"><label class="form-label required">Data de encerramento</label><input class="form-control" type="date" name="data_fim" value="<?= pe_h(date('Y-m-d')) ?>" required></div><label class="pe-check-option pe-delete-confirm"><input type="checkbox" required><span>Confirmo o encerramento desta lotação.</span></label><footer class="pe-action-modal-footer"><button type="button" class="btn btn-light" data-pe-dialog-close>Cancelar</button><button type="submit" class="btn btn-danger"><i class="bi bi-box-arrow-right"></i> Encerrar lotação</button></footer></form>
            </div>
        </div>
    </dialog>
</section>
<?php $pageCustomContent = (string) ob_get_clean();
