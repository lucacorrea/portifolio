<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';

$pageDefinition = [
    'title' => 'Candidatos',
    'description' => 'Base de candidatos com controle de qualidade cadastral e filtros de revisão.',
    'actions' => [],
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
$page = max(1, (int) ($_GET['p'] ?? 1));
$list = ['rows' => [], 'total' => 0, 'page' => 1, 'pages' => 1, 'per_page' => 50];
$filterOptions = ['bairros' => [], 'setores' => []];

if ($dbReady) {
    $pdo = pe_db();
    $stats = pe_dashboard_stats($pdo);
    $list = pe_candidate_page($pdo, $filters, $page, 50);
    $filterOptions = pe_candidate_filters($pdo);
}

function pe_candidate_page_url(int $targetPage): string
{
    $query = $_GET;
    $query['p'] = $targetPage;
    return '?' . http_build_query($query);
}

ob_start();
?>
<section class="content-card pe-form-card">
    <?php if (!$dbReady): ?>
        <div class="alert alert-warning mb-3">
            <strong>Estrutura de revisão não pronta.</strong>
            Execute <code>database/003_revisao_sem_bloqueios.sql</code> no banco da hospedagem.
        </div>
    <?php endif; ?>

    <div class="pe-form-header">
        <div>
            <div class="card-kicker">Banco de candidatos</div>
            <h2>Candidatos do Meu Primeiro Emprego</h2>
            <p>O cadastro permanece ativo mesmo quando houver dados incompletos. As pendências são tratadas separadamente pela fila de revisão.</p>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Total</span><strong><?= (int) $stats['total'] ?></strong></div></div>
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Contemplados</span><strong><?= (int) $stats['contemplados'] ?></strong></div></div>
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Revisão pendente</span><strong><?= (int) $stats['revisao_pendente'] ?></strong></div></div>
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Revisar cadastro</span><strong><?= (int) $stats['revisar_cadastro'] ?></strong></div></div>
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>CPF duplicado</span><strong><?= (int) $stats['cpf_duplicado'] ?></strong></div></div>
        <div class="col-6 col-xl-2"><div class="pe-kpi"><span>Importados</span><strong><?= (int) $stats['importados'] ?></strong></div></div>
    </div>

    <div class="pe-review-legend pe-no-print mb-3">
        <span><i class="pe-review-dot pe-review-dot--yellow"></i> uma pendência</span>
        <span><i class="pe-review-dot pe-review-dot--orange"></i> revisar cadastro</span>
        <span><i class="pe-review-dot pe-review-dot--red"></i> CPF duplicado</span>
    </div>

    <form method="get" class="row g-2 mb-4 pe-no-print">
        <div class="col-xl-4 col-lg-6">
            <input class="form-control" name="q" value="<?= pe_h($filters['q']) ?>" placeholder="Nome, CPF, telefone, responsável, bairro ou setor">
        </div>
        <div class="col-xl-2 col-lg-3">
            <select class="form-select" name="revisao">
                <option value="">Todas as revisões</option>
                <option value="pendentes"<?= $filters['revisao'] === 'pendentes' ? ' selected' : '' ?>>Todas pendentes</option>
                <option value="cpf"<?= $filters['revisao'] === 'cpf' ? ' selected' : '' ?>>Revisar CPF</option>
                <option value="telefone"<?= $filters['revisao'] === 'telefone' ? ' selected' : '' ?>>Revisar Telefone</option>
                <option value="nascimento"<?= $filters['revisao'] === 'nascimento' ? ' selected' : '' ?>>Revisar Data de Nascimento</option>
                <option value="cadastro"<?= $filters['revisao'] === 'cadastro' ? ' selected' : '' ?>>Revisar Cadastro</option>
                <option value="cpf_duplicado"<?= $filters['revisao'] === 'cpf_duplicado' ? ' selected' : '' ?>>CPF Duplicado</option>
                <option value="sem_pendencia"<?= $filters['revisao'] === 'sem_pendencia' ? ' selected' : '' ?>>Sem pendência</option>
            </select>
        </div>
        <div class="col-xl-2 col-lg-3">
            <select class="form-select" name="status">
                <option value="">Todos os status</option>
                <?php foreach (['Em triagem','Em análise','Deferido','Indeferido','Importado','Contemplado'] as $v): ?>
                    <option value="<?= pe_h($v) ?>"<?= $filters['status'] === $v ? ' selected' : '' ?>><?= pe_h($v) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-2 col-lg-3">
            <select class="form-select" name="bairro">
                <option value="">Todos os bairros</option>
                <?php foreach ($filterOptions['bairros'] as $v): ?><option value="<?= pe_h($v) ?>"<?= $filters['bairro'] === $v ? ' selected' : '' ?>><?= pe_h($v) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-2 col-lg-3">
            <select class="form-select" name="setor">
                <option value="">Todos os setores</option>
                <?php foreach ($filterOptions['setores'] as $v): ?><option value="<?= pe_h($v) ?>"<?= $filters['setor'] === $v ? ' selected' : '' ?>><?= pe_h($v) ?></option><?php endforeach; ?>
            </select>
        </div>
        <div class="col-xl-2 col-lg-3">
            <select class="form-select" name="origem">
                <option value="">Todas as origens</option>
                <option value="manual"<?= $filters['origem'] === 'manual' ? ' selected' : '' ?>>Manual</option>
                <option value="importacao"<?= $filters['origem'] === 'importacao' ? ' selected' : '' ?>>Importação</option>
            </select>
        </div>
        <div class="col-xl-2 col-lg-3 d-flex gap-2">
            <button class="btn btn-primary flex-fill">Filtrar</button>
            <a class="btn btn-light" href="?" title="Limpar filtros"><i class="bi bi-x-lg"></i></a>
        </div>
    </form>

    <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
        <small class="text-muted"><strong><?= (int) $list['total'] ?></strong> candidato(s) encontrado(s). Página <?= (int) $list['page'] ?> de <?= (int) $list['pages'] ?>.</small>
    </div>

    <div class="table-responsive">
        <table class="table align-middle pe-data-table pe-candidate-review-table">
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
                <tr><td colspan="8" class="text-center text-muted py-5">Nenhum candidato encontrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($list['rows'] as $row): ?>
                <?php $reviewLabels = pe_review_labels($row); ?>
                <tr class="<?= pe_h(pe_review_row_class($row)) ?>">
                    <td>
                        <strong><?= pe_h($row['nome']) ?></strong>
                        <small class="d-block text-muted">#<?= (int) $row['id'] ?> · <?= pe_h($row['origem']) ?></small>
                    </td>
                    <td>
                        <?= pe_h(pe_format_cpf($row['cpf'] ?: $row['cpf_informado'] ?: '—')) ?>
                        <?php if (!empty($row['cpf_duplicado'])): ?><small class="d-block fw-semibold text-danger"><i class="bi bi-exclamation-triangle-fill"></i> duplicado</small><?php endif; ?>
                    </td>
                    <td><?= $row['data_nascimento'] ? pe_h(date('d/m/Y', strtotime((string) $row['data_nascimento']))) : '<span class="text-muted">Não informada</span>' ?></td>
                    <td><?= pe_h(pe_format_phone($row['telefone'] ?: '—')) ?></td>
                    <td><?= pe_h($row['bairro'] ?: '—') ?></td>
                    <td><?= pe_h($row['setor'] ?: '—') ?></td>
                    <td class="pe-review-cell">
                        <?php if (!empty($row['revisao_status'])): ?>
                            <span class="badge <?= pe_h(pe_review_badge_class($row)) ?>"><?= pe_h($row['revisao_status']) ?></span>
                            <?php if ($reviewLabels): ?>
                                <small class="d-block mt-1 text-body-secondary"><?= pe_h(implode(' · ', $reviewLabels)) ?></small>
                            <?php endif; ?>
                        <?php else: ?>
                            <span class="badge text-bg-success">Sem pendência</span>
                        <?php endif; ?>
                    </td>
                    <td><span class="badge text-bg-light border"><?= pe_h($row['status']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
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
