<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';
require_once dirname(__DIR__) . '/lib/lotacoes.php';
require_once dirname(__DIR__) . '/lib/list-ui.php';

$pageDefinition = [
    'title' => 'Lotações',
    'description' => 'Histórico de distribuição dos participantes por órgão, instituição, local e setor de atuação.',
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
                    $message = [
                        'type' => 'success',
                        'text' => 'Lotação salva com sucesso.',
                    ];
                } elseif ($action === 'end_placement') {
                    pe_lotacao_end(
                        $pdo,
                        (int) ($_POST['id'] ?? 0),
                        (string) ($_POST['data_fim'] ?? '')
                    );
                    $message = [
                        'type' => 'success',
                        'text' => 'Lotação encerrada e preservada no histórico.',
                    ];
                }
            } catch (Throwable $e) {
                $message = [
                    'type' => 'danger',
                    'text' => $e->getMessage(),
                ];
            }
        }

        $rows = pe_lotacao_rows($pdo);
    }
}

function pe_lotacao_date_br(?string $date): string
{
    if (!$date) {
        return '—';
    }

    $timestamp = strtotime($date);
    return $timestamp ? date('d/m/Y', $timestamp) : '—';
}

ob_start();
?>
<section class="content-card pe-form-card pe-page pe-list-page">
    <?php if (!$dbReady): ?>
        <?= pe_db_notice() ?>
    <?php elseif (!$placementReady): ?>
        <div class="alert alert-warning mb-3">
            <strong>Estrutura de lotações não encontrada.</strong>
            Execute <code>database/primeiroEmprego/0004-primeiroEmprego-lotacoes.sql</code> no banco da hospedagem.
        </div>
    <?php endif; ?>

    <?php if ($message): ?>
        <div class="alert alert-<?= pe_h($message['type']) ?>">
            <?= pe_h($message['text']) ?>
        </div>
    <?php endif; ?>

    <?php
    pe_list_header(
        'Distribuição do programa',
        'Lotações dos participantes',
        'A tela exibe somente a listagem. Cadastro, consulta, edição e encerramento são realizados em modais.',
        'Nova lotação',
        '#pePlacementForm',
        'diagram-3'
    );
    ?>

    <?php pe_list_toolbar(count($rows), 'Buscar candidato, CPF, parceiro, local, setor, turno ou status...'); ?>

    <div class="pe-table-wrap">
        <div class="table-responsive">
            <table class="table align-middle pe-data-table pe-list-table" data-pe-list-table>
                <thead>
                <tr>
                    <th>Candidato</th>
                    <th>Órgão / instituição</th>
                    <th>Local de atuação</th>
                    <th>Setor</th>
                    <th>Turno</th>
                    <th>Período</th>
                    <th>Status</th>
                </tr>
                </thead>
                <tbody>
                <?php if (!$rows): ?>
                    <tr class="pe-empty-row">
                        <td colspan="7" class="text-center text-muted py-5">
                            <i class="bi bi-diagram-3 d-block fs-3 mb-2"></i>
                            Nenhuma lotação cadastrada ainda.
                        </td>
                    </tr>
                <?php endif; ?>

                <?php foreach ($rows as $row): ?>
                    <?php
                    $record = $row;
                    $record['__title'] = $row['nome'];
                    $record['__subtitle'] = ($row['local_atuacao'] ?: 'Local não informado')
                        . ' · ' . ($row['status'] ?: 'Status não informado');
                    $record['cpf_formatado'] = pe_format_cpf($row['cpf'] ?: $row['cpf_informado']);
                    $record['telefone_formatado'] = pe_format_phone($row['telefone'] ?: '');
                    $record['data_inicio_formatada'] = pe_lotacao_date_br($row['data_inicio']);
                    $record['data_fim_formatada'] = pe_lotacao_date_br($row['data_fim']);
                    $record['periodo_formatado'] = $record['data_inicio_formatada'] . ' → '
                        . ($row['status'] === 'Ativa' ? 'Atual' : $record['data_fim_formatada']);
                    $record['parceiro_nome_exibicao'] = $row['parceiro_nome'] ?: 'Não vinculado';
                    $record['setor_exibicao'] = $row['setor'] ?: '—';
                    $record['turno_atuacao_exibicao'] = $row['turno_atuacao'] ?: '—';
                    $record['observacao_exibicao'] = $row['observacao'] ?: '—';
                    $record['registrado_por_exibicao'] = $row['registrado_por'] ?: '—';
                    ?>
                    <tr
                        class="pe-list-row"
                        tabindex="0"
                        role="button"
                        data-pe-list-row
                        data-pe-actions-target="#pePlacementActions"
                        data-pe-record="<?= pe_record_attr($record) ?>"
                    >
                        <td data-label="Candidato">
                            <strong><?= pe_h($row['nome']) ?></strong>
                            <small class="d-block text-muted">#<?= (int) $row['candidato_id'] ?></small>
                        </td>
                        <td data-label="Órgão / instituição">
                            <?= pe_h($row['parceiro_nome'] ?: 'Não vinculado') ?>
                        </td>
                        <td data-label="Local">
                            <?= pe_h($row['local_atuacao']) ?>
                        </td>
                        <td data-label="Setor">
                            <?= pe_h($row['setor'] ?: '—') ?>
                        </td>
                        <td data-label="Turno">
                            <?= pe_h($row['turno_atuacao'] ?: '—') ?>
                        </td>
                        <td data-label="Período">
                            <span><?= pe_h(pe_lotacao_date_br($row['data_inicio'])) ?></span>
                            <small class="d-block text-muted">
                                <?= $row['status'] === 'Ativa'
                                    ? 'até o momento'
                                    : 'até ' . pe_h(pe_lotacao_date_br($row['data_fim'])) ?>
                            </small>
                        </td>
                        <td data-label="Status">
                            <span class="pe-status-text <?= $row['status'] === 'Ativa' ? 'is-success' : 'is-muted' ?>">
                                <?= pe_h($row['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- MODAL DE AÇÕES -->
    <dialog class="pe-modal pe-modal--actions" id="pePlacementActions">
        <div class="pe-modal__shell">
            <header class="pe-modal__header">
                <div>
                    <div class="card-kicker">Lotação</div>
                    <h2 data-pe-current-title>Registro selecionado</h2>
                    <p data-pe-current-subtitle>Escolha uma ação.</p>
                </div>
                <button type="button" class="pe-modal__close" data-pe-dialog-close aria-label="Fechar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </header>
            <div class="pe-modal__body">
                <div class="pe-modal-actions-title">Ações disponíveis</div>
                <div class="pe-modal-actions">
                    <button class="pe-modal-action" type="button" data-pe-open="#pePlacementView" data-pe-mode="view">
                        <span class="pe-modal-action__icon"><i class="bi bi-eye"></i></span>
                        <span>
                            <strong>Visualizar</strong>
                            <small>Consultar todos os dados desta lotação</small>
                        </span>
                        <i class="bi bi-chevron-right"></i>
                    </button>

                    <button class="pe-modal-action pe-modal-action--primary" type="button" data-pe-open="#pePlacementForm" data-pe-mode="edit">
                        <span class="pe-modal-action__icon"><i class="bi bi-pencil-square"></i></span>
                        <span>
                            <strong>Editar</strong>
                            <small>Atualizar parceiro, local, setor, turno ou datas</small>
                        </span>
                        <i class="bi bi-chevron-right"></i>
                    </button>

                    <button class="pe-modal-action pe-modal-action--danger" type="button" data-pe-open="#pePlacementEnd" data-pe-mode="view">
                        <span class="pe-modal-action__icon"><i class="bi bi-box-arrow-right"></i></span>
                        <span>
                            <strong>Encerrar lotação</strong>
                            <small>Finalizar a lotação sem apagar o histórico</small>
                        </span>
                        <i class="bi bi-chevron-right"></i>
                    </button>
                </div>
            </div>
        </div>
    </dialog>

    <!-- MODAL CADASTRO / EDIÇÃO -->
    <dialog
        class="pe-modal pe-modal--form"
        id="pePlacementForm"
        data-pe-create-title="Nova lotação"
        data-pe-edit-title="Editar lotação"
    >
        <div class="pe-modal__shell">
            <header class="pe-modal__header">
                <div>
                    <div class="card-kicker">Lotação</div>
                    <h2 data-pe-form-title>Nova lotação</h2>
                    <p>Vincule o participante ao órgão, local, setor e período de atuação.</p>
                </div>
                <button type="button" class="pe-modal__close" data-pe-dialog-close aria-label="Fechar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </header>

            <div class="pe-modal__body">
                <form method="post" class="pe-action-form" data-pe-record-form autocomplete="off">
                    <?= pe_csrf_field() ?>
                    <input type="hidden" name="pe_action" value="save_placement">
                    <input type="hidden" name="id" data-pe-field="id">
                    <input type="hidden" name="origem" value="manual" data-pe-field="origem">
                    <input type="hidden" name="registrado_por" value="<?= pe_h(pe_current_user_label()) ?>">

                    <div class="pe-action-form-grid pe-action-form-grid--2">
                        <div class="pe-field-span-2">
                            <label class="form-label required">Candidato</label>
                            <select class="form-select" name="candidato_id" data-pe-field="candidato_id" required>
                                <option value="">Selecione...</option>
                                <?php foreach ($candidates as $candidate): ?>
                                    <option value="<?= (int) $candidate['id'] ?>">
                                        <?= pe_h($candidate['nome']) ?>
                                        <?= $candidate['lotacao_ativa_local']
                                            ? ' — atualmente: ' . pe_h($candidate['lotacao_ativa_local'])
                                            : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="pe-field-help">
                                Ao criar uma nova lotação ativa para um candidato já lotado, a anterior será encerrada automaticamente.
                            </small>
                        </div>

                        <div>
                            <label class="form-label">Órgão / instituição parceira</label>
                            <select class="form-select" name="parceiro_id" data-pe-field="parceiro_id">
                                <option value="">Não vinculado</option>
                                <?php foreach ($partners as $partner): ?>
                                    <option value="<?= (int) $partner['id'] ?>">
                                        <?= pe_h($partner['nome']) ?>
                                        <?= $partner['status'] !== 'Ativa' ? ' — ' . pe_h($partner['status']) : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div>
                            <label class="form-label">Turno de atuação</label>
                            <select class="form-select" name="turno_atuacao" data-pe-field="turno_atuacao">
                                <option value="">Não informado</option>
                                <?php foreach (['Matutino', 'Vespertino', 'Noturno', 'Integral'] as $shift): ?>
                                    <option value="<?= pe_h($shift) ?>"><?= pe_h($shift) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="pe-field-span-2">
                            <label class="form-label required">Local de atuação</label>
                            <input
                                class="form-control"
                                name="local_atuacao"
                                data-pe-field="local_atuacao"
                                maxlength="180"
                                required
                                placeholder="Ex.: Escola Municipal João Vieira, CRAS I, Sede SEMAS"
                            >
                        </div>

                        <div>
                            <label class="form-label">Setor</label>
                            <input
                                class="form-control"
                                name="setor"
                                data-pe-field="setor"
                                maxlength="160"
                                placeholder="Ex.: Administrativo, recepção, mídia"
                            >
                        </div>

                        <div>
                            <label class="form-label required">Status</label>
                            <select class="form-select" name="status" data-pe-field="status" required>
                                <option value="Ativa" selected>Ativa</option>
                                <option value="Encerrada">Encerrada</option>
                            </select>
                        </div>

                        <div>
                            <label class="form-label required">Data de início</label>
                            <input
                                class="form-control"
                                type="date"
                                name="data_inicio"
                                data-pe-field="data_inicio"
                                value="<?= pe_h(date('Y-m-d')) ?>"
                                required
                            >
                        </div>

                        <div>
                            <label class="form-label">Data de término</label>
                            <input
                                class="form-control"
                                type="date"
                                name="data_fim"
                                data-pe-field="data_fim"
                            >
                            <small class="pe-field-help">Deixe vazio enquanto a lotação estiver ativa.</small>
                        </div>

                        <div class="pe-field-span-2">
                            <label class="form-label">Observação</label>
                            <textarea
                                class="form-control"
                                name="observacao"
                                data-pe-field="observacao"
                                rows="3"
                                maxlength="500"
                                placeholder="Informações complementares sobre a lotação ou transferência."
                            ></textarea>
                        </div>
                    </div>

                    <footer class="pe-action-modal-footer">
                        <button type="button" class="btn btn-light" data-pe-dialog-close>Cancelar</button>
                        <button class="btn btn-primary" type="submit">
                            <i class="bi bi-floppy"></i> Salvar lotação
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    </dialog>

    <!-- MODAL VISUALIZAÇÃO -->
    <dialog class="pe-modal pe-modal--view" id="pePlacementView">
        <div class="pe-modal__shell">
            <header class="pe-modal__header">
                <div>
                    <div class="card-kicker">Detalhes da lotação</div>
                    <h2 data-pe-current-title>Lotação</h2>
                    <p data-pe-current-subtitle></p>
                </div>
                <button type="button" class="pe-modal__close" data-pe-dialog-close aria-label="Fechar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </header>
            <div class="pe-modal__body">
                <dl class="pe-modal-details pe-modal-details--2">
                    <div><dt>CPF</dt><dd data-pe-text="cpf_formatado">—</dd></div>
                    <div><dt>Telefone</dt><dd data-pe-text="telefone_formatado">—</dd></div>
                    <div><dt>Bairro</dt><dd data-pe-text="bairro">—</dd></div>
                    <div><dt>Status da lotação</dt><dd data-pe-text="status">—</dd></div>
                    <div><dt>Órgão / instituição</dt><dd data-pe-text="parceiro_nome_exibicao">—</dd></div>
                    <div><dt>Local de atuação</dt><dd data-pe-text="local_atuacao">—</dd></div>
                    <div><dt>Setor</dt><dd data-pe-text="setor_exibicao">—</dd></div>
                    <div><dt>Turno</dt><dd data-pe-text="turno_atuacao_exibicao">—</dd></div>
                    <div><dt>Data de início</dt><dd data-pe-text="data_inicio_formatada">—</dd></div>
                    <div><dt>Data de término</dt><dd data-pe-text="data_fim_formatada">—</dd></div>
                    <div><dt>Origem</dt><dd data-pe-text="origem">—</dd></div>
                    <div><dt>Registrado por</dt><dd data-pe-text="registrado_por_exibicao">—</dd></div>
                    <div class="pe-field-span-2"><dt>Observação</dt><dd data-pe-text="observacao_exibicao">—</dd></div>
                </dl>
            </div>
        </div>
    </dialog>

    <!-- MODAL ENCERRAMENTO -->
    <dialog class="pe-modal pe-modal--confirm" id="pePlacementEnd">
        <div class="pe-modal__shell">
            <header class="pe-modal__header">
                <div>
                    <div class="card-kicker">Encerrar lotação</div>
                    <h2 data-pe-current-title>Lotação selecionada</h2>
                    <p>O registro não será apagado. Ele permanecerá disponível no histórico.</p>
                </div>
                <button type="button" class="pe-modal__close" data-pe-dialog-close aria-label="Fechar">
                    <i class="bi bi-x-lg"></i>
                </button>
            </header>
            <div class="pe-modal__body">
                <div class="pe-delete-warning">
                    <i class="bi bi-clock-history"></i>
                    <div>
                        <strong data-pe-current-subtitle>Lotação selecionada</strong>
                        <span>A lotação será marcada como encerrada e a data final será registrada.</span>
                    </div>
                </div>

                <form method="post" class="pe-delete-form">
                    <?= pe_csrf_field() ?>
                    <input type="hidden" name="pe_action" value="end_placement">
                    <input type="hidden" name="id" value="" data-pe-field="id">

                    <div class="mb-3">
                        <label class="form-label required">Data de encerramento</label>
                        <input
                            class="form-control"
                            type="date"
                            name="data_fim"
                            value="<?= pe_h(date('Y-m-d')) ?>"
                            required
                        >
                    </div>

                    <label class="pe-check-option pe-delete-confirm">
                        <input type="checkbox" required>
                        <span>Confirmo o encerramento desta lotação.</span>
                    </label>

                    <footer class="pe-action-modal-footer">
                        <button type="button" class="btn btn-light" data-pe-dialog-close>Cancelar</button>
                        <button type="submit" class="btn btn-danger">
                            <i class="bi bi-box-arrow-right"></i> Encerrar lotação
                        </button>
                    </footer>
                </form>
            </div>
        </div>
    </dialog>
</section>
<?php
$pageCustomContent = (string) ob_get_clean();
