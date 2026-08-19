<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';

$pageDefinition = [
    'title' => 'Candidatos',
    'description' => 'Base de candidatos com controle de qualidade cadastral e filtros de revisão.',
    'actions' => [
        ['label' => 'Novo candidato', 'icon' => 'person-plus', 'primary' => true, 'href' => 'primeiro-emprego/cadastro-candidato.php'],
        ['label' => 'Importar Excel', 'icon' => 'file-earmark-spreadsheet', 'href' => 'primeiro-emprego/importar-candidatos.php'],
    ],
    'demo' => false,
    'show_states' => false,
    'modal' => ['title' => 'Candidato'],
];

$dbReady = pe_db_ready() && pe_schema_ready();
$stats = [
    'total' => 0,
    'contemplados' => 0,
    'visitas' => 0,
    'deferidos' => 0,
    'indeferidos' => 0,
    'importados' => 0,
    'revisao_pendente' => 0,
    'revisar_cadastro' => 0,
    'cpf_duplicado' => 0,
];
$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'bairro' => trim((string) ($_GET['bairro'] ?? '')),
    'setor' => trim((string) ($_GET['setor'] ?? '')),
    'origem' => trim((string) ($_GET['origem'] ?? '')),
    'revisao' => trim((string) ($_GET['revisao'] ?? '')),
];
$currentPage = max(1, (int) ($_GET['p'] ?? 1));
$list = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 50];
$filterOptions = ['bairros' => [], 'setores' => []];
$message = null;
$reviewCandidate = null;
$reviewPeers = [];
$reviewHistory = [];
$reviewId = (int) ($_GET['revisar'] ?? $_POST['candidato_id'] ?? 0);

if ($dbReady) {
    $pdo = pe_db();

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['pe_action'] ?? '') === 'save_review') {
        try {
            pe_verify_csrf();
            $reviewId = (int) ($_POST['candidato_id'] ?? 0);
            pe_review_candidate($pdo, $reviewId, $_POST, pe_current_user_label());
            $message = ['type' => 'success', 'text' => 'Revisão cadastral salva. As pendências foram recalculadas automaticamente.'];
        } catch (Throwable $e) {
            $message = ['type' => 'danger', 'text' => $e->getMessage()];
        }
    }

    if ($reviewId > 0) {
        $reviewCandidate = pe_candidate_by_id($pdo, $reviewId);
        if ($reviewCandidate) {
            $reviewPeers = pe_candidate_duplicate_peers($pdo, $reviewId, $reviewCandidate['cpf'] ?? null);
            $reviewHistory = pe_review_history($pdo, $reviewId, 10);
        }
    }

    $stats = pe_dashboard_stats($pdo);
    $list = pe_candidate_page($pdo, $filters, $currentPage, 50);
    $filterOptions = pe_candidate_filters($pdo);
}

function pe_candidate_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['p'] = $targetPage;
    return 'primeiro-emprego/candidatos.php?' . http_build_query($query);
}

ob_start();
?>
<section class="content-card pe-form-card pe-candidates-page">
    <?php if (!$dbReady): ?>
        <div class="alert alert-warning mb-3">
            <strong>Estrutura de revisão não pronta.</strong>
            Execute <code>database/primeiroEmprego/0002-primeiroEmprego-operacional.sql</code> no banco da hospedagem.
        </div>
    <?php endif; ?>
    <?php if ($message): ?><div class="alert alert-<?= pe_h($message['type']) ?>"><?= pe_h($message['text']) ?></div><?php endif; ?>

    <?php if ($reviewCandidate): ?>
        <?php $reviewLabelsCurrent = pe_review_labels($reviewCandidate); ?>
        <section id="revisao-candidato" class="pe-review-editor mb-4 <?= pe_h(pe_review_row_class($reviewCandidate)) ?>">
            <div class="pe-form-header mb-3">
                <div>
                    <div class="card-kicker">Revisão cadastral</div>
                    <h2><?= pe_h($reviewCandidate['nome']) ?></h2>
                    <p>Corrija os dados disponíveis ou confirme manualmente a situação informada. O candidato permanece cadastrado em qualquer caso.</p>
                </div>
                <a class="btn btn-light pe-no-print" href="primeiro-emprego/candidatos.php"><i class="bi bi-x-lg"></i> Fechar revisão</a>
            </div>

            <?php if ($reviewLabelsCurrent): ?>
                <div class="alert alert-warning py-2"><strong>Pendências atuais:</strong> <?= pe_h(implode(' · ', $reviewLabelsCurrent)) ?></div>
            <?php else: ?>
                <div class="alert alert-success py-2"><strong>Cadastro sem pendências.</strong> Você ainda pode atualizar os dados abaixo.</div>
            <?php endif; ?>

            <?php if ($reviewPeers): ?>
                <div class="alert alert-danger">
                    <strong><i class="bi bi-exclamation-triangle-fill"></i> CPF também encontrado em outro(s) candidato(s):</strong>
                    <ul class="mb-0 mt-2">
                        <?php foreach ($reviewPeers as $peer): ?>
                            <li>#<?= (int) $peer['id'] ?> — <?= pe_h($peer['nome']) ?><?= $peer['data_nascimento'] ? ' — ' . pe_h(date('d/m/Y', strtotime((string) $peer['data_nascimento']))) : '' ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form method="post" class="row g-3 pe-no-print" autocomplete="off">
                <?= pe_csrf_field() ?>
                <input type="hidden" name="pe_action" value="save_review">
                <input type="hidden" name="candidato_id" value="<?= (int) $reviewCandidate['id'] ?>">

                <div class="col-lg-4">
                    <label class="form-label">CPF</label>
                    <input class="form-control" name="cpf" inputmode="numeric" maxlength="18" value="<?= pe_h($reviewCandidate['cpf_informado'] ?: $reviewCandidate['cpf']) ?>">
                    <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="confirmar_cpf_atual" value="1" <?= !empty($reviewCandidate['cpf_revisado_confirmado']) ? ' checked' : '' ?>><span class="form-check-label">Confirmar CPF atual/indisponível após conferência</span></label>
                    <?php if (!empty($reviewCandidate['cpf_duplicado'])): ?><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="confirmar_cpf_duplicado" value="1" <?= !empty($reviewCandidate['cpf_duplicado_confirmado']) ? ' checked' : '' ?>><span class="form-check-label">Confirmar que a duplicidade foi conferida</span></label><?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Telefone</label>
                    <input class="form-control" name="telefone" inputmode="tel" maxlength="20" value="<?= pe_h($reviewCandidate['telefone']) ?>">
                    <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="confirmar_telefone_atual" value="1" <?= !empty($reviewCandidate['telefone_revisado_confirmado']) ? ' checked' : '' ?>><span class="form-check-label">Confirmar que não possui ou manter o telefone informado</span></label>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Data de nascimento</label>
                    <input class="form-control" type="date" name="data_nascimento" value="<?= pe_h($reviewCandidate['data_nascimento']) ?>">
                    <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="confirmar_nascimento_atual" value="1" <?= !empty($reviewCandidate['nascimento_revisado_confirmado']) ? ' checked' : '' ?>><span class="form-check-label">Confirmar que a data não está disponível</span></label>
                </div>

                <div class="col-12">
                    <label class="form-label">Observação da revisão</label>
                    <textarea class="form-control" name="observacao" rows="2" maxlength="500" placeholder="Opcional: descreva a conferência realizada."></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Salvar revisão</button></div>
            </form>

            <?php if ($reviewHistory): ?>
                <details class="mt-3 pe-no-print">
                    <summary class="fw-semibold">Histórico de revisões (<?= count($reviewHistory) ?>)</summary>
                    <div class="table-responsive mt-2">
                        <table class="table table-sm align-middle">
                            <thead>
                                <tr>
                                    <th>Data</th>
                                    <th>Revisor</th>
                                    <th>Observação</th>
                                </tr>
                            </thead>
                            <tbody><?php foreach ($reviewHistory as $hist): ?><tr>
                                        <td><?= pe_h(date('d/m/Y H:i', strtotime((string) $hist['created_at']))) ?></td>
                                        <td><?= pe_h($hist['revisado_por'] ?: '—') ?></td>
                                        <td><?= pe_h($hist['observacao'] ?: 'Revisão registrada') ?></td>
                                    </tr><?php endforeach; ?></tbody>
                        </table>
                    </div>
                </details>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <div class="pe-candidates-hero">
        <div>
            <div class="card-kicker">Banco de candidatos</div>
            <h2>Candidatos do Meu Primeiro Emprego</h2>
            <p>Consulte, filtre e acompanhe os candidatos. Clique em qualquer linha para abrir o painel de ações.</p>
        </div>
        <div class="pe-candidates-hero__actions pe-no-print">
            <a class="btn btn-primary" href="primeiro-emprego/cadastro-candidato.php"><i class="bi bi-person-plus"></i> Novo candidato</a>
            <a class="btn btn-light" href="primeiro-emprego/importar-candidatos.php"><i class="bi bi-file-earmark-spreadsheet"></i> Importar Excel</a>
        </div>
    </div>

    <div class="pe-kpi-grid mb-4">
        <div class="pe-kpi pe-kpi--primary"><span>Total de candidatos</span><strong><?= (int) $stats['total'] ?></strong><small>base cadastrada</small></div>
        <div class="pe-kpi"><span>Contemplados</span><strong><?= (int) $stats['contemplados'] ?></strong><small>no programa</small></div>
        <div class="pe-kpi pe-kpi--warning"><span>Revisão pendente</span><strong><?= (int) $stats['revisao_pendente'] ?></strong><small>precisam de atenção</small></div>
        <div class="pe-kpi pe-kpi--orange"><span>Revisar cadastro</span><strong><?= (int) $stats['revisar_cadastro'] ?></strong><small>múltiplas pendências</small></div>
        <div class="pe-kpi pe-kpi--danger"><span>CPF duplicado</span><strong><?= (int) $stats['cpf_duplicado'] ?></strong><small>conferência necessária</small></div>
        <div class="pe-kpi"><span>Importados</span><strong><?= (int) $stats['importados'] ?></strong><small>via planilha</small></div>
    </div>

    <div class="pe-review-legend pe-no-print mb-3" aria-label="Legenda de revisão">
        <span><i class="pe-review-dot pe-review-dot--yellow"></i> uma pendência</span>
        <span><i class="pe-review-dot pe-review-dot--orange"></i> revisar cadastro</span>
        <span><i class="pe-review-dot pe-review-dot--red"></i> CPF duplicado</span>
        <span class="ms-auto d-none d-lg-inline-flex"><i class="bi bi-cursor"></i> clique na linha para ver ações</span>
    </div>

    <form method="get" class="pe-candidate-filters pe-no-print mb-4">
        <div class="pe-filter-search">
            <i class="bi bi-search"></i>
            <input class="form-control" name="q" value="<?= pe_h($filters['q']) ?>" placeholder="Buscar por nome, CPF, telefone, responsável, bairro ou setor" aria-label="Buscar candidatos">
        </div>
        <select class="form-select" name="revisao" aria-label="Filtrar por revisão">
            <option value="">Todas as revisões</option>
            <option value="pendentes" <?= $filters['revisao'] === 'pendentes' ? ' selected' : '' ?>>Todas pendentes</option>
            <option value="cpf" <?= $filters['revisao'] === 'cpf' ? ' selected' : '' ?>>Revisar CPF</option>
            <option value="telefone" <?= $filters['revisao'] === 'telefone' ? ' selected' : '' ?>>Revisar Telefone</option>
            <option value="nascimento" <?= $filters['revisao'] === 'nascimento' ? ' selected' : '' ?>>Revisar Nascimento</option>
            <option value="cadastro" <?= $filters['revisao'] === 'cadastro' ? ' selected' : '' ?>>Revisar Cadastro</option>
            <option value="cpf_duplicado" <?= $filters['revisao'] === 'cpf_duplicado' ? ' selected' : '' ?>>CPF Duplicado</option>
            <option value="sem_pendencia" <?= $filters['revisao'] === 'sem_pendencia' ? ' selected' : '' ?>>Sem pendência</option>
        </select>
        <select class="form-select" name="status" aria-label="Filtrar por status">
            <option value="">Todos os status</option>
            <?php foreach (['Em triagem', 'Em análise', 'Deferido', 'Indeferido', 'Importado', 'Contemplado'] as $v): ?>
                <option value="<?= pe_h($v) ?>" <?= $filters['status'] === $v ? ' selected' : '' ?>><?= pe_h($v) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" name="bairro" aria-label="Filtrar por bairro">
            <option value="">Todos os bairros</option>
            <?php foreach ($filterOptions['bairros'] as $v): ?><option value="<?= pe_h($v) ?>" <?= $filters['bairro'] === $v ? ' selected' : '' ?>><?= pe_h($v) ?></option><?php endforeach; ?>
        </select>
        <select class="form-select" name="setor" aria-label="Filtrar por setor">
            <option value="">Todos os setores</option>
            <?php foreach ($filterOptions['setores'] as $v): ?><option value="<?= pe_h($v) ?>" <?= $filters['setor'] === $v ? ' selected' : '' ?>><?= pe_h($v) ?></option><?php endforeach; ?>
        </select>
        <select class="form-select" name="origem" aria-label="Filtrar por origem">
            <option value="">Todas as origens</option>
            <option value="manual" <?= $filters['origem'] === 'manual' ? ' selected' : '' ?>>Manual</option>
            <option value="importacao" <?= $filters['origem'] === 'importacao' ? ' selected' : '' ?>>Importação</option>
        </select>
        <button class="btn btn-primary pe-filter-submit" type="submit"><i class="bi bi-funnel"></i><span>Filtrar</span></button>
        <a class="btn btn-light pe-filter-clear" href="primeiro-emprego/candidatos.php" title="Limpar filtros" aria-label="Limpar filtros"><i class="bi bi-x-lg"></i></a>
    </form>

    <div class="pe-table-toolbar">
        <div>
            <strong><?= (int) $list['total'] ?></strong> candidato(s)
            <span>• Página <?= (int) $list['page'] ?> de <?= (int) $list['pages'] ?></span>
        </div>
        <small class="text-muted d-none d-md-inline"><i class="bi bi-hand-index-thumb"></i> Clique em uma linha para abrir detalhes e ações</small>
    </div>

    <div class="pe-candidate-table-wrap">
        <div class="table-responsive pe-candidate-table-scroll">
            <table class="table align-middle pe-data-table pe-candidate-review-table pe-candidate-table">
                <thead>
                    <tr>
                        <th>Candidato</th>
                        <th>CPF</th>
                        <th>Nascimento</th>
                        <th>Telefone</th>
                        <th>Bairro</th>
                        <th>Setor</th>
                        <th>Revisão</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!$list['rows']): ?>
                        <tr class="pe-empty-row">
                            <td colspan="8" class="text-center text-muted py-5"><i class="bi bi-search d-block fs-3 mb-2"></i>Nenhum candidato encontrado com os filtros atuais.</td>
                        </tr>
                    <?php endif; ?>
                    <?php foreach ($list['rows'] as $row): ?>
                        <?php
                        $reviewLabels = pe_review_labels($row);
                        $candidateCpf = pe_format_cpf($row['cpf'] ?: $row['cpf_informado'] ?: '—');
                        $candidateBirth = $row['data_nascimento'] ? date('d/m/Y', strtotime((string) $row['data_nascimento'])) : 'Não informada';
                        $candidatePhone = pe_format_phone($row['telefone'] ?: '—');
                        $candidateReview = !empty($row['revisao_status']) ? (string) $row['revisao_status'] : 'Sem pendência';
                        $candidateReviewDetails = $reviewLabels ? implode(' · ', $reviewLabels) : 'Cadastro sem pendências';
                        ?>
                        <tr
                            class="pe-candidate-row <?= pe_h(pe_review_row_class($row)) ?>"
                            tabindex="0"
                            role="button"
                            aria-label="Abrir ações de <?= pe_h($row['nome']) ?>"
                            data-pe-candidate-row
                            data-id="<?= (int) $row['id'] ?>"
                            data-name="<?= pe_h($row['nome']) ?>"
                            data-cpf="<?= pe_h($candidateCpf) ?>"
                            data-birth="<?= pe_h($candidateBirth) ?>"
                            data-phone="<?= pe_h($candidatePhone) ?>"
                            data-neighborhood="<?= pe_h($row['bairro'] ?: 'Não informado') ?>"
                            data-sector="<?= pe_h($row['setor'] ?: 'Não informado') ?>"
                            data-review="<?= pe_h($candidateReview) ?>"
                            data-review-details="<?= pe_h($candidateReviewDetails) ?>"
                            data-status="<?= pe_h($row['status']) ?>"
                            data-origin="<?= pe_h($row['origem']) ?>"
                            data-duplicate="<?= !empty($row['cpf_duplicado']) ? '1' : '0' ?>">
                            <td data-label="Candidato" class="pe-candidate-name-cell">
                                <div class="pe-candidate-name">
                                    <span class="pe-candidate-avatar" aria-hidden="true"><i class="bi bi-person"></i></span>
                                    <div>
                                        <strong><?= pe_h($row['nome']) ?></strong>
                                        <small>#<?= (int) $row['id'] ?> · <?= pe_h($row['origem']) ?></small>
                                    </div>
                                </div>
                            </td>
                            <td data-label="CPF">
                                <span><?= pe_h($candidateCpf) ?></span>
                                <?php if (!empty($row['cpf_duplicado'])): ?><small class="pe-inline-alert"><i class="bi bi-exclamation-triangle-fill"></i> duplicado</small><?php endif; ?>
                            </td>
                            <td data-label="Nascimento"><?= pe_h($candidateBirth) ?></td>
                            <td data-label="Telefone"><?= pe_h($candidatePhone) ?></td>
                            <td data-label="Bairro"><?= pe_h($row['bairro'] ?: '—') ?></td>
                            <td data-label="Setor"><?= pe_h($row['setor'] ?: '—') ?></td>
                            <td data-label="Revisão" class="pe-review-cell">
                                <?php if (!empty($row['revisao_status'])): ?>
                                    <span class="badge <?= pe_h(pe_review_badge_class($row)) ?>"><?= pe_h($row['revisao_status']) ?></span>
                                    <?php if ($reviewLabels): ?><small><?= pe_h(implode(' · ', $reviewLabels)) ?></small><?php endif; ?>
                                <?php else: ?>
                                    <span class="badge pe-badge-ok"><i class="bi bi-check2"></i> Sem pendência</span>
                                <?php endif; ?>
                            </td>
                            <td data-label="Status"><span class="pe-status-badge"><?= pe_h($row['status']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <dialog class="pe-candidate-modal" id="peCandidateDialog" aria-labelledby="peCandidateDialogTitle">
        <div class="pe-candidate-modal__shell">
            <header class="pe-candidate-modal__header">
                <div>
                    <div class="card-kicker">Candidato</div>
                    <h2 id="peCandidateDialogTitle" data-pe-modal-name>Detalhes do candidato</h2>
                    <p data-pe-modal-meta>Selecione um candidato para visualizar as ações disponíveis.</p>
                </div>
                <button type="button" class="pe-candidate-modal__close" data-pe-modal-close aria-label="Fechar">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </header>

            <div class="pe-candidate-modal__body">
                <div class="pe-modal-review" data-pe-modal-review-box>
                    <div class="pe-modal-review__heading">
                        <i class="bi bi-shield-check" aria-hidden="true"></i>
                        <strong data-pe-modal-review>Sem pendência</strong>
                    </div>
                    <span data-pe-modal-review-details>Cadastro sem pendências.</span>
                </div>

                <dl class="pe-modal-details">
                    <div>
                        <dt>CPF</dt>
                        <dd data-pe-modal-cpf>—</dd>
                    </div>
                    <div>
                        <dt>Telefone</dt>
                        <dd data-pe-modal-phone>—</dd>
                    </div>
                    <div>
                        <dt>Nascimento</dt>
                        <dd data-pe-modal-birth>—</dd>
                    </div>
                    <div>
                        <dt>Bairro</dt>
                        <dd data-pe-modal-neighborhood>—</dd>
                    </div>
                    <div>
                        <dt>Setor</dt>
                        <dd data-pe-modal-sector>—</dd>
                    </div>
                    <div>
                        <dt>Status</dt>
                        <dd data-pe-modal-status>—</dd>
                    </div>
                </dl>

                <div class="pe-modal-actions-title">Ações do candidato</div>
                <div class="pe-modal-actions">
                    <a class="pe-modal-action pe-modal-action--primary" href="#" data-pe-modal-action-review>
                        <span class="pe-modal-action__icon"><i class="bi bi-pencil-square"></i></span>
                        <span><strong>Revisar / editar cadastro</strong><small>Corrigir ou confirmar dados cadastrais</small></span>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <a class="pe-modal-action" href="#" data-pe-modal-action-visit>
                        <span class="pe-modal-action__icon"><i class="bi bi-house-check"></i></span>
                        <span><strong>Visita social</strong><small>Abrir acompanhamento e parecer técnico</small></span>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                    <a class="pe-modal-action" href="#" data-pe-modal-action-profile>
                        <span class="pe-modal-action__icon"><i class="bi bi-person-vcard"></i></span>
                        <span><strong>Ficha cadastral</strong><small>Consultar ou preencher a ficha do candidato</small></span>
                        <i class="bi bi-chevron-right"></i>
                    </a>
                </div>
            </div>
        </div>
    </dialog>

    <?php if ($list['pages'] > 1): ?>
        <nav class="pe-no-print mt-3" aria-label="Paginação de candidatos">
            <ul class="pagination pagination-sm justify-content-end flex-wrap">
                <li class="page-item<?= $list['page'] <= 1 ? ' disabled' : '' ?>"><a class="page-link" href="<?= pe_h(pe_candidate_page_url(max(1, $list['page'] - 1))) ?>">Anterior</a></li>
                <?php
                $from = max(1, $list['page'] - 2);
                $to = min($list['pages'], $list['page'] + 2);
                for ($i = $from; $i <= $to; $i++):
                ?>
                    <li class="page-item<?= $i === $list['page'] ? ' active' : '' ?>"><a class="page-link" href="<?= pe_h(pe_candidate_page_url($i)) ?>"><?= $i ?></a></li>
                <?php endfor; ?>
                <li class="page-item<?= $list['page'] >= $list['pages'] ? ' disabled' : '' ?>"><a class="page-link" href="<?= pe_h(pe_candidate_page_url(min($list['pages'], $list['page'] + 1))) ?>">Próxima</a></li>
            </ul>
        </nav>
    <?php endif; ?>
</section>

<style>
    /* CANDIDATOS: estilos locais para manter esta tela isolada do restante do módulo. */
    .pe-candidates-page {
        --pe-line: #e3eaed;
        --pe-ink: #173640;
        --pe-muted: #6d7f86;
        --pe-soft: #f7fafb;
    }

    .pe-candidates-page .pe-candidate-table-wrap {
        overflow: hidden;
        border: 1px solid var(--pe-line);
        border-radius: 14px;
        background: #fff;
        box-shadow: 0 7px 22px rgba(20, 56, 70, .055);
    }

    .pe-candidates-page .pe-candidate-table-scroll {
        overflow-x: auto;
        scrollbar-width: thin;
    }

    .pe-candidates-page .pe-candidate-table {
        width: 100%;
        min-width: 1050px;
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
        table-layout: fixed;
    }

    .pe-candidates-page .pe-candidate-table thead th {
        position: sticky;
        top: 0;
        z-index: 2;
        padding: .78rem .72rem;
        border-bottom: 1px solid #d7e1e5;
        background: #f5f8f9;
        color: #536970;
        font-size: .69rem;
        font-weight: 800;
        letter-spacing: .035em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .pe-candidates-page .pe-candidate-table thead th:nth-child(1) {
        width: 18%;
    }

    .pe-candidates-page .pe-candidate-table thead th:nth-child(2) {
        width: 12%;
    }

    .pe-candidates-page .pe-candidate-table thead th:nth-child(3) {
        width: 10%;
    }

    .pe-candidates-page .pe-candidate-table thead th:nth-child(4) {
        width: 11%;
    }

    .pe-candidates-page .pe-candidate-table thead th:nth-child(5) {
        width: 12%;
    }

    .pe-candidates-page .pe-candidate-table thead th:nth-child(6) {
        width: 11%;
    }

    .pe-candidates-page .pe-candidate-table thead th:nth-child(7) {
        width: 18%;
    }

    .pe-candidates-page .pe-candidate-table thead th:nth-child(8) {
        width: 8%;
    }

    .pe-candidates-page .pe-candidate-table tbody td {
        padding: .82rem .72rem;
        border-bottom: 1px solid #e8edef;
        color: #263c44;
        font-size: .79rem;
        line-height: 1.35;
        vertical-align: middle;
        overflow-wrap: anywhere;
    }

    .pe-candidates-page .pe-candidate-table tbody tr:last-child td {
        border-bottom: 0;
    }

    .pe-candidates-page .pe-candidate-row {
        cursor: pointer;
        outline: none;
        transition: background-color .14s ease, box-shadow .14s ease;
    }

    .pe-candidates-page .pe-candidate-row:hover>td,
    .pe-candidates-page .pe-candidate-row:focus-visible>td {
        background-color: #f3f9fa !important;
    }

    .pe-candidates-page .pe-candidate-row:focus-visible {
        box-shadow: inset 0 0 0 2px #2b8ba5;
    }

    .pe-candidates-page .pe-candidate-name {
        display: flex;
        gap: .62rem;
        align-items: center;
    }

    .pe-candidates-page .pe-candidate-avatar {
        display: grid;
        width: 2rem;
        height: 2rem;
        flex: 0 0 2rem;
        place-items: center;
        border: 1px solid #d9e6ea;
        border-radius: 999px;
        background: #eef6f8;
        color: #16718a;
    }

    .pe-candidates-page .pe-candidate-name strong {
        display: block;
        color: #172f38;
        font-size: .82rem;
        line-height: 1.25;
    }

    .pe-candidates-page .pe-candidate-name small {
        display: block;
        margin-top: .18rem;
        color: #7b8a90;
        font-size: .68rem;
    }

    .pe-candidates-page .pe-review-cell small {
        display: block;
        margin-top: .28rem;
        color: #687b82;
        font-size: .68rem;
        line-height: 1.35;
    }

    .pe-candidates-page .pe-inline-alert {
        display: block;
        margin-top: .2rem;
        color: #c93342;
        font-size: .67rem;
        font-weight: 700;
    }

    .pe-candidate-modal {
        width: min(760px, calc(100vw - 32px));
        max-width: 760px;
        max-height: min(88vh, 760px);
        padding: 0;
        overflow: hidden;
        border: 0;
        border-radius: 18px;
        background: #fff;
        color: #20363e;
        box-shadow: 0 28px 80px rgba(15, 35, 43, .28);
    }

    .pe-candidate-modal[open] {
        animation: peModalIn .16s ease-out;
    }

    .pe-candidate-modal::backdrop {
        background: rgba(18, 34, 42, .52);
        backdrop-filter: blur(2px);
    }

    @keyframes peModalIn {
        from {
            opacity: 0;
            transform: translateY(10px) scale(.985);
        }

        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    .pe-candidate-modal__shell {
        display: flex;
        max-height: min(88vh, 760px);
        flex-direction: column;
    }

    .pe-candidate-modal__header {
        display: flex;
        gap: 1rem;
        align-items: flex-start;
        justify-content: space-between;
        padding: 1.25rem 1.35rem 1rem;
        border-bottom: 1px solid #e6ecee;
        background: linear-gradient(180deg, #fbfdfd 0%, #fff 100%);
    }

    .pe-candidate-modal__header h2 {
        margin: .15rem 0 .15rem;
        color: #15343e;
        font-size: 1.28rem;
        line-height: 1.2;
    }

    .pe-candidate-modal__header p {
        margin: 0;
        color: #73858b;
        font-size: .76rem;
    }

    .pe-candidate-modal__close {
        display: grid;
        width: 2.3rem;
        height: 2.3rem;
        flex: 0 0 2.3rem;
        place-items: center;
        border: 1px solid #dce5e8;
        border-radius: 10px;
        background: #fff;
        color: #465b62;
        cursor: pointer;
    }

    .pe-candidate-modal__close:hover {
        background: #f1f6f7;
    }

    .pe-candidate-modal__body {
        overflow-y: auto;
        padding: 1.15rem 1.35rem 1.35rem;
    }

    .pe-modal-review {
        padding: .8rem .9rem;
        border: 1px solid #c9e2d2;
        border-radius: 12px;
        background: #f5fbf7;
    }

    .pe-modal-review.is-warning {
        border-color: #ead78e;
        background: #fffaf0;
    }

    .pe-modal-review.is-multiple {
        border-color: #efc28f;
        background: #fff7ee;
    }

    .pe-modal-review.is-critical {
        border-color: #efb5bb;
        background: #fff4f5;
    }

    .pe-modal-review__heading {
        display: flex;
        gap: .45rem;
        align-items: center;
        color: #27464f;
        font-size: .8rem;
    }

    .pe-modal-review>span {
        display: block;
        margin-top: .3rem;
        color: #687b82;
        font-size: .73rem;
        line-height: 1.4;
    }

    .pe-modal-details {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: .6rem;
        margin: .9rem 0 1rem;
    }

    .pe-modal-details>div {
        min-width: 0;
        padding: .68rem .72rem;
        border: 1px solid #e4eaec;
        border-radius: 10px;
        background: #fafcfc;
    }

    .pe-modal-details dt {
        margin-bottom: .18rem;
        color: #7c8d93;
        font-size: .62rem;
        font-weight: 800;
        letter-spacing: .04em;
        text-transform: uppercase;
    }

    .pe-modal-details dd {
        margin: 0;
        color: #203a43;
        font-size: .79rem;
        font-weight: 700;
        overflow-wrap: anywhere;
    }

    .pe-modal-actions-title {
        margin: .2rem 0 .55rem;
        color: #718288;
        font-size: .65rem;
        font-weight: 800;
        letter-spacing: .045em;
        text-transform: uppercase;
    }

    .pe-modal-actions {
        display: grid;
        gap: .55rem;
    }

    .pe-modal-action {
        display: grid;
        grid-template-columns: 2.4rem minmax(0, 1fr) auto;
        gap: .72rem;
        align-items: center;
        padding: .78rem .85rem;
        border: 1px solid #dfe8ea;
        border-radius: 12px;
        background: #fff;
        color: inherit;
        text-decoration: none;
        transition: border-color .13s ease, background-color .13s ease, transform .13s ease;
    }

    .pe-modal-action:hover {
        border-color: #b7d7df;
        background: #f5fbfc;
        color: inherit;
        transform: translateX(2px);
    }

    .pe-modal-action__icon {
        display: grid;
        width: 2.4rem;
        height: 2.4rem;
        place-items: center;
        border-radius: 10px;
        background: #edf5f7;
        color: #16718a;
        font-size: 1rem;
    }

    .pe-modal-action strong,
    .pe-modal-action small {
        display: block;
    }

    .pe-modal-action strong {
        color: #1c3740;
        font-size: .8rem;
    }

    .pe-modal-action small {
        margin-top: .13rem;
        color: #74868c;
        font-size: .7rem;
        line-height: 1.3;
    }

    .pe-modal-action>i:last-child {
        color: #93a2a7;
        font-size: .75rem;
    }

    .pe-modal-action--primary {
        border-color: #bcd8df;
        background: #f8fcfd;
    }

    @media (max-width: 767.98px) {
        .pe-candidates-page .pe-candidate-table {
            min-width: 0;
        }

        .pe-candidate-modal {
            width: calc(100vw - 20px);
            max-height: calc(100vh - 20px);
            border-radius: 14px;
        }

        .pe-candidate-modal__shell {
            max-height: calc(100vh - 20px);
        }

        .pe-candidate-modal__header {
            padding: 1rem;
        }

        .pe-candidate-modal__body {
            padding: .9rem 1rem 1rem;
        }

        .pe-modal-details {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }
    }

    @media (max-width: 479.98px) {
        .pe-modal-details {
            grid-template-columns: 1fr;
        }

        .pe-modal-action {
            grid-template-columns: 2.25rem minmax(0, 1fr) auto;
            padding: .72rem;
        }
    }

    /* =========================================================
   CANDIDATOS — CABEÇALHO + INDICADORES + FILTROS
   Cole no FINAL do <style> da página candidatos.php
   ========================================================= */

    .pe-candidates-page {
        --pe-green: #075d36;
        --pe-green-hover: #064d2e;
        --pe-green-soft: #eef8f3;

        --pe-blue-soft: #f1f7fa;
        --pe-yellow-soft: #fff9e8;
        --pe-orange-soft: #fff4e8;
        --pe-red-soft: #fff0f1;

        --pe-border: #dfe7e5;
        --pe-border-soft: #edf1f0;

        --pe-text: #17352d;
        --pe-muted: #6d7d78;
    }


    /* =========================================================
   CARD PRINCIPAL
   ========================================================= */

    .pe-candidates-page {
        padding: 22px 24px 26px !important;
        border: 1px solid #e4ebe8;
        border-radius: 18px;
        background: #fff;
        box-shadow:
            0 1px 2px rgba(22, 50, 42, .03),
            0 8px 28px rgba(22, 50, 42, .05);
    }


    /* =========================================================
   CABEÇALHO
   ========================================================= */

    .pe-candidates-page .pe-candidates-hero {
        display: flex;
        align-items: flex-start;
        justify-content: space-between;
        gap: 24px;

        margin-bottom: 20px;
        padding-bottom: 18px;

        border-bottom: 1px solid var(--pe-border-soft);
    }

    .pe-candidates-page .pe-candidates-hero>div:first-child {
        min-width: 0;
    }

    .pe-candidates-page .card-kicker {
        margin-bottom: 5px;

        color: var(--pe-green);
        font-size: .68rem;
        font-weight: 800;
        letter-spacing: .08em;
        text-transform: uppercase;
    }

    .pe-candidates-page .pe-candidates-hero h2 {
        margin: 0;

        color: #16342c;
        font-size: clamp(1.35rem, 2vw, 1.9rem);
        font-weight: 750;
        line-height: 1.15;
        letter-spacing: -.025em;
    }

    .pe-candidates-page .pe-candidates-hero p {
        max-width: 760px;
        margin: 7px 0 0;

        color: var(--pe-muted);
        font-size: .82rem;
        line-height: 1.5;
    }


    /* =========================================================
   AÇÕES DO CABEÇALHO
   ========================================================= */

    .pe-candidates-page .pe-candidates-hero__actions {
        display: flex;
        flex: 0 0 auto;
        gap: 9px;
        align-items: center;
    }

    .pe-candidates-page .pe-candidates-hero__actions .btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;

        min-height: 40px;
        padding: 8px 14px;

        border-radius: 10px;

        font-size: .76rem;
        font-weight: 700;

        box-shadow: none;
    }

    .pe-candidates-page .pe-candidates-hero__actions .btn-primary {
        border-color: var(--pe-green);
        background: var(--pe-green);
    }

    .pe-candidates-page .pe-candidates-hero__actions .btn-primary:hover {
        border-color: var(--pe-green-hover);
        background: var(--pe-green-hover);
    }

    .pe-candidates-page .pe-candidates-hero__actions .btn-light {
        border: 1px solid var(--pe-border);
        background: #fff;
        color: #425a52;
    }

    .pe-candidates-page .pe-candidates-hero__actions .btn-light:hover {
        border-color: #cbd9d4;
        background: #f6f9f8;
    }


    /* =========================================================
   INDICADORES / KPIs
   ========================================================= */

    .pe-candidates-page .pe-kpi-grid {
        display: grid;
        grid-template-columns: repeat(6, minmax(0, 1fr));
        gap: 10px;

        margin-bottom: 14px !important;
    }

    .pe-candidates-page .pe-kpi {
        position: relative;
        min-width: 0;

        padding: 12px 13px 11px;

        overflow: hidden;

        border: 1px solid var(--pe-border);
        border-radius: 12px;

        background: #fafcfb;
    }

    .pe-candidates-page .pe-kpi::before {
        content: "";

        position: absolute;
        top: 0;
        right: 0;
        left: 0;

        height: 3px;

        background: #c9d9d3;
    }

    .pe-candidates-page .pe-kpi span {
        display: block;

        margin-bottom: 4px;

        overflow: hidden;

        color: #657872;
        font-size: .66rem;
        font-weight: 700;
        line-height: 1.2;

        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .pe-candidates-page .pe-kpi strong {
        display: block;

        color: #183c31;
        font-size: 1.25rem;
        font-weight: 800;
        line-height: 1.05;
    }

    .pe-candidates-page .pe-kpi small {
        display: block;

        margin-top: 4px;

        color: #8a9894;
        font-size: .60rem;
        line-height: 1.25;
    }


    /* TOTAL */

    .pe-candidates-page .pe-kpi--primary {
        border-color: #cfe4da;
        background: var(--pe-green-soft);
    }

    .pe-candidates-page .pe-kpi--primary::before {
        background: var(--pe-green);
    }


    /* REVISÃO */

    .pe-candidates-page .pe-kpi--warning {
        border-color: #eee1af;
        background: var(--pe-yellow-soft);
    }

    .pe-candidates-page .pe-kpi--warning::before {
        background: #d8a900;
    }


    /* REVISAR CADASTRO */

    .pe-candidates-page .pe-kpi--orange {
        border-color: #efd3ad;
        background: var(--pe-orange-soft);
    }

    .pe-candidates-page .pe-kpi--orange::before {
        background: #dc7f10;
    }


    /* CPF DUPLICADO */

    .pe-candidates-page .pe-kpi--danger {
        border-color: #efc5c9;
        background: var(--pe-red-soft);
    }

    .pe-candidates-page .pe-kpi--danger::before {
        background: #d93848;
    }


    /* =========================================================
   LEGENDA
   ========================================================= */

    .pe-candidates-page .pe-review-legend {
        display: flex;
        flex-wrap: wrap;
        gap: 7px;
        align-items: center;

        margin-bottom: 14px !important;
    }

    .pe-candidates-page .pe-review-legend>span {
        display: inline-flex;
        align-items: center;
        gap: 6px;

        min-height: 28px;
        padding: 5px 9px;

        border: 1px solid #e3e9e7;
        border-radius: 999px;

        background: #fafcfb;

        color: #63736e;
        font-size: .65rem;
        font-weight: 600;
    }

    .pe-candidates-page .pe-review-dot {
        width: 7px;
        height: 7px;
        flex: 0 0 7px;

        border-radius: 50%;
    }

    .pe-candidates-page .pe-review-dot--yellow {
        background: #dfb20a;
    }

    .pe-candidates-page .pe-review-dot--orange {
        background: #e38216;
    }

    .pe-candidates-page .pe-review-dot--red {
        background: #d83d4b;
    }


    /* =========================================================
   ÁREA DE FILTROS
   ========================================================= */

    .pe-candidates-page .pe-candidate-filters {
        display: grid;

        grid-template-columns: repeat(12, minmax(0, 1fr));

        gap: 10px;

        margin-bottom: 18px !important;
        padding: 15px;

        border: 1px solid #dfe7e4;
        border-radius: 14px;

        background: #f8faf9;
    }


    /* BUSCA — PRIMEIRA LINHA */

    .pe-candidates-page .pe-filter-search {
        position: relative;

        grid-column: span 6;
    }

    .pe-candidates-page .pe-filter-search>i {
        position: absolute;
        top: 50%;
        left: 13px;
        z-index: 2;

        color: #789088;
        font-size: .85rem;

        transform: translateY(-50%);

        pointer-events: none;
    }

    .pe-candidates-page .pe-filter-search .form-control {
        padding-left: 37px;
    }


    /* REVISÃO */

    .pe-candidates-page .pe-candidate-filters select[name="revisao"] {
        grid-column: span 3;
    }


    /* STATUS */

    .pe-candidates-page .pe-candidate-filters select[name="status"] {
        grid-column: span 3;
    }


    /* SEGUNDA LINHA */

    .pe-candidates-page .pe-candidate-filters select[name="bairro"] {
        grid-column: span 3;
    }

    .pe-candidates-page .pe-candidate-filters select[name="setor"] {
        grid-column: span 3;
    }

    .pe-candidates-page .pe-candidate-filters select[name="origem"] {
        grid-column: span 3;
    }


    /* CAMPOS */

    .pe-candidates-page .pe-candidate-filters .form-control,
    .pe-candidates-page .pe-candidate-filters .form-select {
        width: 100%;
        min-height: 42px;

        padding-top: 8px;
        padding-bottom: 8px;

        border: 1px solid #d9e3df;
        border-radius: 9px;

        background-color: #fff;

        color: #334f46;
        font-size: .74rem;

        box-shadow: none;

        transition:
            border-color .15s ease,
            box-shadow .15s ease,
            background-color .15s ease;
    }

    .pe-candidates-page .pe-candidate-filters .form-control:hover,
    .pe-candidates-page .pe-candidate-filters .form-select:hover {
        border-color: #bccfc8;
    }

    .pe-candidates-page .pe-candidate-filters .form-control:focus,
    .pe-candidates-page .pe-candidate-filters .form-select:focus {
        border-color: #25845b;

        box-shadow: 0 0 0 3px rgba(7, 93, 54, .09);
    }

    .pe-candidates-page .pe-candidate-filters .form-control::placeholder {
        color: #95a29e;
    }


    /* =========================================================
   BOTÃO FILTRAR
   ========================================================= */

    .pe-candidates-page .pe-filter-submit {
        grid-column: span 2;

        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 7px;

        min-height: 42px;

        border: 1px solid var(--pe-green);
        border-radius: 9px;

        background: var(--pe-green);

        color: #fff;
        font-size: .74rem;
        font-weight: 750;

        box-shadow: none;
    }

    .pe-candidates-page .pe-filter-submit:hover {
        border-color: var(--pe-green-hover);
        background: var(--pe-green-hover);
    }


    /* =========================================================
   LIMPAR FILTROS
   ========================================================= */

    .pe-candidates-page .pe-filter-clear {
        grid-column: span 1;

        display: inline-flex;
        align-items: center;
        justify-content: center;

        min-width: 42px;
        min-height: 42px;

        padding: 0;

        border: 1px solid #d9e3df;
        border-radius: 9px;

        background: #fff;

        color: #63776f;
    }

    .pe-candidates-page .pe-filter-clear:hover {
        border-color: #e0b9bc;
        background: #fff5f5;
        color: #bd3440;
    }


    /* =========================================================
   TOOLBAR ACIMA DA TABELA
   ========================================================= */

    .pe-candidates-page .pe-table-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;

        min-height: 38px;
        margin: 0 0 7px;
    }

    .pe-candidates-page .pe-table-toolbar>div {
        display: flex;
        align-items: center;
        gap: 5px;

        color: #657770;
        font-size: .70rem;
    }

    .pe-candidates-page .pe-table-toolbar strong {
        color: #183b30;
        font-size: .78rem;
    }

    .pe-candidates-page .pe-table-toolbar small {
        color: #86958f !important;
        font-size: .65rem;
    }


    /* =========================================================
   RESPONSIVO — NOTEBOOK / TABLET
   ========================================================= */

    @media (max-width: 1199.98px) {

        .pe-candidates-page .pe-kpi-grid {
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .pe-candidates-page .pe-filter-search {
            grid-column: span 12;
        }

        .pe-candidates-page .pe-candidate-filters select[name="revisao"],
        .pe-candidates-page .pe-candidate-filters select[name="status"],
        .pe-candidates-page .pe-candidate-filters select[name="bairro"],
        .pe-candidates-page .pe-candidate-filters select[name="setor"],
        .pe-candidates-page .pe-candidate-filters select[name="origem"] {
            grid-column: span 4;
        }

        .pe-candidates-page .pe-filter-submit {
            grid-column: span 3;
        }

        .pe-candidates-page .pe-filter-clear {
            grid-column: span 1;
        }
    }


    /* =========================================================
   RESPONSIVO — MOBILE
   ========================================================= */

    @media (max-width: 767.98px) {

        .pe-candidates-page {
            padding: 16px !important;
            border-radius: 14px;
        }

        .pe-candidates-page .pe-candidates-hero {
            flex-direction: column;
            gap: 14px;
        }

        .pe-candidates-page .pe-candidates-hero__actions {
            width: 100%;
        }

        .pe-candidates-page .pe-candidates-hero__actions .btn {
            flex: 1 1 0;
        }

        .pe-candidates-page .pe-kpi-grid {
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pe-candidates-page .pe-candidate-filters {
            grid-template-columns: 1fr;
            padding: 12px;
        }

        .pe-candidates-page .pe-filter-search,
        .pe-candidates-page .pe-candidate-filters select[name="revisao"],
        .pe-candidates-page .pe-candidate-filters select[name="status"],
        .pe-candidates-page .pe-candidate-filters select[name="bairro"],
        .pe-candidates-page .pe-candidate-filters select[name="setor"],
        .pe-candidates-page .pe-candidate-filters select[name="origem"],
        .pe-candidates-page .pe-filter-submit,
        .pe-candidates-page .pe-filter-clear {
            grid-column: 1 / -1;
        }

        .pe-candidates-page .pe-filter-clear {
            width: 100%;
        }

        .pe-candidates-page .pe-review-legend>span {
            font-size: .62rem;
        }
    }


    /* =========================================================
   MOBILE PEQUENO
   ========================================================= */

    @media (max-width: 479.98px) {

        .pe-candidates-page .pe-kpi-grid {
            grid-template-columns: 1fr 1fr;
            gap: 7px;
        }

        .pe-candidates-page .pe-kpi {
            padding: 10px;
        }

        .pe-candidates-page .pe-kpi strong {
            font-size: 1.1rem;
        }

        .pe-candidates-page .pe-candidates-hero__actions {
            flex-direction: column;
        }

        .pe-candidates-page .pe-candidates-hero__actions .btn {
            width: 100%;
        }
    }
</style>

<script>
    (() => {
        const rows = Array.from(document.querySelectorAll('[data-pe-candidate-row]'));
        const modal = document.getElementById('peCandidateDialog');
        if (!rows.length || !modal) return;

        const field = selector => modal.querySelector(selector);
        const fields = {
            name: field('[data-pe-modal-name]'),
            meta: field('[data-pe-modal-meta]'),
            cpf: field('[data-pe-modal-cpf]'),
            phone: field('[data-pe-modal-phone]'),
            birth: field('[data-pe-modal-birth]'),
            neighborhood: field('[data-pe-modal-neighborhood]'),
            sector: field('[data-pe-modal-sector]'),
            status: field('[data-pe-modal-status]'),
            review: field('[data-pe-modal-review]'),
            reviewDetails: field('[data-pe-modal-review-details]'),
            reviewBox: field('[data-pe-modal-review-box]'),
            reviewAction: field('[data-pe-modal-action-review]'),
            visitAction: field('[data-pe-modal-action-visit]'),
            profileAction: field('[data-pe-modal-action-profile]')
        };

        const candidateUrl = (path, id) => `primeiro-emprego/${path}?candidato_id=${encodeURIComponent(id)}`;

        const reviewUrl = id => {
            const url = new URL(window.location.href);
            url.searchParams.set('revisar', String(id));
            url.searchParams.delete('p');
            url.hash = 'revisao-candidato';
            return url.href;
        };

        const fill = row => {
            const data = row.dataset;
            fields.name.textContent = data.name || 'Candidato';
            fields.meta.textContent = `#${data.id || '—'} · ${data.origin || 'origem não informada'}`;
            fields.cpf.textContent = data.cpf || '—';
            fields.phone.textContent = data.phone || '—';
            fields.birth.textContent = data.birth || '—';
            fields.neighborhood.textContent = data.neighborhood || '—';
            fields.sector.textContent = data.sector || '—';
            fields.status.textContent = data.status || '—';
            fields.review.textContent = data.review || 'Sem pendência';
            fields.reviewDetails.textContent = data.reviewDetails || 'Cadastro sem pendências';

            fields.reviewBox.classList.remove('is-warning', 'is-multiple', 'is-critical');
            if (data.duplicate === '1') {
                fields.reviewBox.classList.add('is-critical');
            } else if ((data.review || '').toLowerCase().includes('cadastro')) {
                fields.reviewBox.classList.add('is-multiple');
            } else if ((data.review || '') && data.review !== 'Sem pendência') {
                fields.reviewBox.classList.add('is-warning');
            }

            fields.reviewAction.href = reviewUrl(data.id);
            fields.visitAction.href = candidateUrl('acompanhamentos.php', data.id);
            fields.profileAction.href = candidateUrl('lotacoes.php', data.id);
        };

        const openModal = row => {
            fill(row);
            if (typeof modal.showModal === 'function') {
                if (!modal.open) modal.showModal();
            } else {
                modal.setAttribute('open', '');
            }
        };

        const closeModal = () => {
            if (typeof modal.close === 'function' && modal.open) modal.close();
            else modal.removeAttribute('open');
        };

        rows.forEach(row => {
            row.addEventListener('click', event => {
                if (event.target.closest('a, button, input, select, textarea, label')) return;
                openModal(row);
            });

            row.addEventListener('keydown', event => {
                if (event.key !== 'Enter' && event.key !== ' ') return;
                event.preventDefault();
                openModal(row);
            });
        });

        modal.querySelectorAll('[data-pe-modal-close]').forEach(button => {
            button.addEventListener('click', closeModal);
        });

        modal.addEventListener('click', event => {
            if (event.target === modal) closeModal();
        });
    })();
</script>
<?php $pageCustomContent = (string) ob_get_clean();
