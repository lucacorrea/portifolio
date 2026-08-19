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
<section class="content-card pe-form-card">
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
                    <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="confirmar_cpf_atual" value="1"<?= !empty($reviewCandidate['cpf_revisado_confirmado']) ? ' checked' : '' ?>><span class="form-check-label">Confirmar CPF atual/indisponível após conferência</span></label>
                    <?php if (!empty($reviewCandidate['cpf_duplicado'])): ?><label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="confirmar_cpf_duplicado" value="1"<?= !empty($reviewCandidate['cpf_duplicado_confirmado']) ? ' checked' : '' ?>><span class="form-check-label">Confirmar que a duplicidade foi conferida</span></label><?php endif; ?>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Telefone</label>
                    <input class="form-control" name="telefone" inputmode="tel" maxlength="20" value="<?= pe_h($reviewCandidate['telefone']) ?>">
                    <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="confirmar_telefone_atual" value="1"<?= !empty($reviewCandidate['telefone_revisado_confirmado']) ? ' checked' : '' ?>><span class="form-check-label">Confirmar que não possui ou manter o telefone informado</span></label>
                </div>

                <div class="col-lg-4">
                    <label class="form-label">Data de nascimento</label>
                    <input class="form-control" type="date" name="data_nascimento" value="<?= pe_h($reviewCandidate['data_nascimento']) ?>">
                    <label class="form-check mt-2"><input class="form-check-input" type="checkbox" name="confirmar_nascimento_atual" value="1"<?= !empty($reviewCandidate['nascimento_revisado_confirmado']) ? ' checked' : '' ?>><span class="form-check-label">Confirmar que a data não está disponível</span></label>
                </div>

                <div class="col-12">
                    <label class="form-label">Observação da revisão</label>
                    <textarea class="form-control" name="observacao" rows="2" maxlength="500" placeholder="Opcional: descreva a conferência realizada."></textarea>
                </div>
                <div class="col-12 d-flex justify-content-end"><button class="btn btn-primary" type="submit"><i class="bi bi-check2-circle"></i> Salvar revisão</button></div>
            </form>

            <?php if ($reviewHistory): ?>
                <details class="mt-3 pe-no-print"><summary class="fw-semibold">Histórico de revisões (<?= count($reviewHistory) ?>)</summary>
                    <div class="table-responsive mt-2"><table class="table table-sm align-middle"><thead><tr><th>Data</th><th>Revisor</th><th>Observação</th></tr></thead><tbody><?php foreach ($reviewHistory as $hist): ?><tr><td><?= pe_h(date('d/m/Y H:i', strtotime((string) $hist['created_at']))) ?></td><td><?= pe_h($hist['revisado_por'] ?: '—') ?></td><td><?= pe_h($hist['observacao'] ?: 'Revisão registrada') ?></td></tr><?php endforeach; ?></tbody></table></div>
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
            <option value="pendentes"<?= $filters['revisao'] === 'pendentes' ? ' selected' : '' ?>>Todas pendentes</option>
            <option value="cpf"<?= $filters['revisao'] === 'cpf' ? ' selected' : '' ?>>Revisar CPF</option>
            <option value="telefone"<?= $filters['revisao'] === 'telefone' ? ' selected' : '' ?>>Revisar Telefone</option>
            <option value="nascimento"<?= $filters['revisao'] === 'nascimento' ? ' selected' : '' ?>>Revisar Nascimento</option>
            <option value="cadastro"<?= $filters['revisao'] === 'cadastro' ? ' selected' : '' ?>>Revisar Cadastro</option>
            <option value="cpf_duplicado"<?= $filters['revisao'] === 'cpf_duplicado' ? ' selected' : '' ?>>CPF Duplicado</option>
            <option value="sem_pendencia"<?= $filters['revisao'] === 'sem_pendencia' ? ' selected' : '' ?>>Sem pendência</option>
        </select>
        <select class="form-select" name="status" aria-label="Filtrar por status">
            <option value="">Todos os status</option>
            <?php foreach (['Em triagem','Em análise','Deferido','Indeferido','Importado','Contemplado'] as $v): ?>
                <option value="<?= pe_h($v) ?>"<?= $filters['status'] === $v ? ' selected' : '' ?>><?= pe_h($v) ?></option>
            <?php endforeach; ?>
        </select>
        <select class="form-select" name="bairro" aria-label="Filtrar por bairro">
            <option value="">Todos os bairros</option>
            <?php foreach ($filterOptions['bairros'] as $v): ?><option value="<?= pe_h($v) ?>"<?= $filters['bairro'] === $v ? ' selected' : '' ?>><?= pe_h($v) ?></option><?php endforeach; ?>
        </select>
        <select class="form-select" name="setor" aria-label="Filtrar por setor">
            <option value="">Todos os setores</option>
            <?php foreach ($filterOptions['setores'] as $v): ?><option value="<?= pe_h($v) ?>"<?= $filters['setor'] === $v ? ' selected' : '' ?>><?= pe_h($v) ?></option><?php endforeach; ?>
        </select>
        <select class="form-select" name="origem" aria-label="Filtrar por origem">
            <option value="">Todas as origens</option>
            <option value="manual"<?= $filters['origem'] === 'manual' ? ' selected' : '' ?>>Manual</option>
            <option value="importacao"<?= $filters['origem'] === 'importacao' ? ' selected' : '' ?>>Importação</option>
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
                    <tr class="pe-empty-row"><td colspan="8" class="text-center text-muted py-5"><i class="bi bi-search d-block fs-3 mb-2"></i>Nenhum candidato encontrado com os filtros atuais.</td></tr>
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
                        data-duplicate="<?= !empty($row['cpf_duplicado']) ? '1' : '0' ?>"
                    >
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

    <div class="offcanvas offcanvas-end pe-candidate-drawer" tabindex="-1" id="peCandidateDrawer" aria-labelledby="peCandidateDrawerTitle">
        <div class="offcanvas-header">
            <div>
                <div class="card-kicker">Candidato</div>
                <h2 class="offcanvas-title" id="peCandidateDrawerTitle" data-pe-drawer-name>Detalhes do candidato</h2>
                <small class="text-muted" data-pe-drawer-meta></small>
            </div>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Fechar"></button>
        </div>
        <div class="offcanvas-body">
            <div class="pe-drawer-review" data-pe-drawer-review-box>
                <div class="pe-drawer-review__title"><i class="bi bi-shield-check"></i><strong data-pe-drawer-review></strong></div>
                <span data-pe-drawer-review-details></span>
            </div>

            <dl class="pe-drawer-details">
                <div><dt>CPF</dt><dd data-pe-drawer-cpf>—</dd></div>
                <div><dt>Telefone</dt><dd data-pe-drawer-phone>—</dd></div>
                <div><dt>Nascimento</dt><dd data-pe-drawer-birth>—</dd></div>
                <div><dt>Bairro</dt><dd data-pe-drawer-neighborhood>—</dd></div>
                <div><dt>Setor</dt><dd data-pe-drawer-sector>—</dd></div>
                <div><dt>Status</dt><dd data-pe-drawer-status>—</dd></div>
            </dl>

            <div class="pe-drawer-section-title">O que você deseja fazer?</div>
            <div class="pe-candidate-actions">
                <a class="pe-action-card pe-action-card--primary" href="#" data-pe-action-review>
                    <i class="bi bi-pencil-square"></i><span><strong>Revisar / editar cadastro</strong><small>Corrigir ou confirmar pendências cadastrais</small></span><i class="bi bi-chevron-right"></i>
                </a>
                <a class="pe-action-card" href="#" data-pe-action-visit>
                    <i class="bi bi-house-check"></i><span><strong>Visita social</strong><small>Abrir acompanhamento e parecer técnico</small></span><i class="bi bi-chevron-right"></i>
                </a>
                <a class="pe-action-card" href="#" data-pe-action-profile>
                    <i class="bi bi-person-vcard"></i><span><strong>Ficha cadastral</strong><small>Consultar ou preencher a ficha do candidato</small></span><i class="bi bi-chevron-right"></i>
                </a>
            </div>
        </div>
    </div>

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
<?php $pageCustomContent = (string) ob_get_clean();
