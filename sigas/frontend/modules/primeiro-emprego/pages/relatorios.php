<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/relatorios.php';
require_once dirname(__DIR__) . '/lib/list-ui.php';

$pageDefinition = [
    'title' => 'Relatórios',
    'description' => 'Central analítica do Meu Primeiro Emprego com filtros, indicadores, gráficos, detalhamento, impressão e exportação.',
    'actions' => [],
    'demo' => false,
    'show_states' => false,
    'modal' => ['title' => 'Relatórios'],
];

$dbReady = pe_db_ready() && pe_schema_ready() && pe_program_schema_ready();
$pdo = $dbReady ? pe_db() : null;
if ($dbReady && !pe_lotacao_table_ready($pdo)) {
    $dbReady = false;
}

$filters = [
    'q' => trim((string) ($_GET['q'] ?? '')),
    'status' => trim((string) ($_GET['status'] ?? '')),
    'revisao' => trim((string) ($_GET['revisao'] ?? '')),
    'lotacao' => trim((string) ($_GET['lotacao'] ?? '')),
    'bairro' => trim((string) ($_GET['bairro'] ?? '')),
    'setor' => trim((string) ($_GET['setor'] ?? '')),
    'parceiro_id' => trim((string) ($_GET['parceiro_id'] ?? '')),
    'origem' => trim((string) ($_GET['origem'] ?? '')),
    'sexo' => trim((string) ($_GET['sexo'] ?? '')),
    'idade_min' => trim((string) ($_GET['idade_min'] ?? '')),
    'idade_max' => trim((string) ($_GET['idade_max'] ?? '')),
    'data_inicio' => trim((string) ($_GET['data_inicio'] ?? '')),
    'data_fim' => trim((string) ($_GET['data_fim'] ?? '')),
];

$currentPage = max(1, (int) ($_GET['pagina'] ?? 1));
$perPage = 50;
$total = 0;
$pages = 1;
$rows = [];
$summary = [
    'total' => 0,
    'cadastro_regular' => 0,
    'revisao_pendente' => 0,
    'cpf_duplicado' => 0,
    'lotados' => 0,
    'nao_lotados' => 0,
    'revisar_lotacao' => 0,
    'pronto_importar' => 0,
    'idade_media' => 0.0,
];
$distributions = [
    'status' => [], 'lotacao' => [], 'revisao' => [], 'sexo' => [],
    'origem' => [], 'bairros' => [], 'parceiros' => [], 'idades' => [],
];
$operational = [
    'visitas' => ['total' => 0, 'deferidos' => 0, 'indeferidos' => 0, 'pendentes' => 0, 'series' => []],
    'encaminhamentos' => [],
    'documentos' => [],
    'documentos_resumo' => ['total'=>0,'pendentes'=>0,'vencidos'=>0,'sem_arquivo'=>0],
    'frequencia' => ['competencia' => null, 'media' => 0.0, 'total' => 0, 'series' => []],
    'bolsas' => ['competencia' => null, 'total' => 0, 'valor_pago' => 0.0, 'series' => []],
    'rede' => ['parceiros_ativos' => 0, 'vagas_abertas' => 0, 'vagas_total' => 0, 'capacitacoes_ativas' => 0],
];
$options = ['bairros'=>[], 'status'=>[], 'origens'=>[], 'sexos'=>[], 'setores'=>[], 'partners'=>[]];

if ($dbReady) {
    try {
        $summary = pe_rel_summary($pdo, $filters);
        $distributions = pe_rel_distributions($pdo, $filters);
        $operational = pe_rel_operational($pdo, $filters);
        $options = pe_rel_filter_options($pdo);
        $total = pe_rel_candidate_count($pdo, $filters);
        $pages = max(1, (int) ceil($total / $perPage));
        if ($currentPage > $pages) {
            $currentPage = $pages;
        }
        $rows = pe_rel_candidate_rows($pdo, $filters, $perPage, ($currentPage - 1) * $perPage);
    } catch (Throwable) {
        $dbReady = false;
    }
}

if ($dbReady && ($_GET['pe_export'] ?? '') === 'csv') {
    $exportRows = pe_rel_candidate_rows($pdo, $filters, null, 0);
    if (!headers_sent()) {
        header('Content-Type: text/csv; charset=UTF-8');
        header('Content-Disposition: attachment; filename="relatorio_primeiro_emprego_' . date('Y-m-d_H-i') . '.csv"');
    }

    echo "\xEF\xBB\xBF";
    $out = fopen('php://output', 'wb');
    fputcsv($out, [
        '#', 'NOME', 'SEXO', 'NASCIMENTO', 'IDADE', 'CPF', 'TELEFONE', 'BAIRRO', 'ENDEREÇO',
        'RESPONSÁVEL FAMILIAR', 'STATUS', 'CADASTRO', 'MOTIVOS DA REVISÃO', 'SITUAÇÃO DA LOTAÇÃO',
        'ÓRGÃO / INSTITUIÇÃO', 'LOCAL DE ATUAÇÃO', 'SETOR', 'TURNO', 'ÚLTIMO PARECER',
        'ÚLTIMO ENCAMINHAMENTO', 'DOCUMENTOS PENDENTES', 'ORIGEM', 'DATA DO CADASTRO'
    ], ';');

    $i = 0;
    foreach ($exportRows as $row) {
        $i++;
        $partner = trim((string) ($row['parceiro_sigla'] ?? ''));
        if ($partner !== '') {
            $partner .= ' — ';
        }
        $partner .= (string) ($row['parceiro_nome'] ?? '');

        fputcsv($out, [
            $i,
            $row['nome'],
            $row['sexo'],
            $row['data_nascimento'] ? date('d/m/Y', strtotime((string) $row['data_nascimento'])) : '',
            $row['idade'],
            pe_format_cpf((string) ($row['cpf'] ?? '')),
            pe_format_phone((string) ($row['telefone'] ?? '')),
            $row['bairro'],
            $row['endereco'],
            $row['responsavel_familiar'],
            $row['status'],
            $row['revisao_status'] ?: 'Regular',
            $row['revisao_motivos'],
            $row['situacao_lotacao'],
            trim($partner),
            $row['local_atuacao'],
            $row['setor'],
            $row['turno_atuacao'],
            $row['parecer'] ?: 'Pendente',
            $row['encaminhamento_status'] ?: 'Sem encaminhamento',
            $row['documentos_pendentes'],
            $row['origem'],
            $row['created_at'] ? date('d/m/Y H:i', strtotime((string) $row['created_at'])) : '',
        ], ';');
    }

    fclose($out);
    exit;
}

$exportQuery = $_GET;
unset($exportQuery['pagina']);
$exportQuery['pe_export'] = 'csv';
$exportUrl = 'primeiro-emprego/relatorios.php?' . http_build_query($exportQuery);

$freqComp = $operational['frequencia']['competencia']
    ? pe_rel_month_label((string) $operational['frequencia']['competencia'])
    : 'Sem competência';
$grantComp = $operational['bolsas']['competencia']
    ? pe_rel_month_label((string) $operational['bolsas']['competencia'])
    : 'Sem competência';

$lotacaoPercent = $summary['total'] > 0 ? ($summary['lotados'] / $summary['total']) * 100 : 0;
$regularPercent = $summary['total'] > 0 ? ($summary['cadastro_regular'] / $summary['total']) * 100 : 0;
$reviewPercent = $summary['total'] > 0 ? ($summary['revisao_pendente'] / $summary['total']) * 100 : 0;

$chartPayload = [
    'status' => $distributions['status'],
    'lotacao' => $distributions['lotacao'],
    'revisao' => $distributions['revisao'],
    'sexo' => $distributions['sexo'],
    'origem' => $distributions['origem'],
    'bairros' => $distributions['bairros'],
    'parceiros' => $distributions['parceiros'],
    'idades' => $distributions['idades'],
    'visitas' => $operational['visitas']['series'],
    'encaminhamentos' => $operational['encaminhamentos'],
    'documentos' => $operational['documentos'],
    'frequencia' => $operational['frequencia']['series'],
    'bolsas' => $operational['bolsas']['series'],
];

ob_start();
?>
<section class="content-card pe-form-card pe-page pe-report-page">
    <?php if (!$dbReady): ?>
        <div class="alert alert-warning mb-0">
            <strong>O relatório analítico não pôde ser carregado.</strong>
            Confirme se as tabelas do módulo e a estrutura de lotações estão atualizadas.
        </div>
    <?php else: ?>
        <div class="pe-page-hero pe-list-hero">
            <div>
                <div class="card-kicker">Central de relatórios</div>
                <h2>Relatório analítico do Meu Primeiro Emprego</h2>
                <p>Consolide a base, aplique filtros, compare indicadores e exporte o detalhamento para conferência ou apresentação.</p>
            </div>
            <div class="pe-page-actions pe-no-print">
                <a class="btn btn-primary" href="<?= pe_h($exportUrl) ?>"><i class="bi bi-file-earmark-spreadsheet"></i> Exportar CSV</a>
                <button class="btn btn-light" type="button" onclick="window.print()"><i class="bi bi-printer"></i> Imprimir / PDF</button>
            </div>
        </div>

        <form method="get" class="pe-report-filter-panel pe-no-print">
            <div class="pe-report-filter-head">
                <div>
                    <div class="card-kicker">Filtros globais</div>
                    <h3>Refine toda a análise</h3>
                    <p>Os indicadores, gráficos e a tabela abaixo respondem aos filtros de candidatos.</p>
                </div>
                <div class="pe-report-filter-actions">
                    <a class="btn btn-light" href="primeiro-emprego/relatorios.php"><i class="bi bi-x-lg"></i> Limpar</a>
                    <button class="btn btn-primary" type="submit"><i class="bi bi-funnel"></i> Aplicar filtros</button>
                </div>
            </div>

            <div class="pe-report-filter-grid">
                <label class="pe-report-filter-search pe-field-span-2">
                    <span>Pesquisa</span>
                    <div><i class="bi bi-search"></i><input class="form-control" name="q" value="<?= pe_h($filters['q']) ?>" placeholder="Nome, CPF, telefone, bairro, órgão, local ou setor"></div>
                </label>

                <label><span>Status</span><select class="form-select" name="status"><option value="">Todos</option><?php foreach ($options['status'] as $v): ?><option value="<?= pe_h((string) $v) ?>"<?= $filters['status'] === (string) $v ? ' selected' : '' ?>><?= pe_h((string) $v) ?></option><?php endforeach; ?></select></label>
                <label><span>Cadastro</span><select class="form-select" name="revisao"><option value="">Todos</option><option value="sem_pendencia"<?= $filters['revisao']==='sem_pendencia'?' selected':'' ?>>Regular</option><option value="pendentes"<?= $filters['revisao']==='pendentes'?' selected':'' ?>>Com pendência</option><option value="cpf"<?= $filters['revisao']==='cpf'?' selected':'' ?>>Revisar CPF</option><option value="telefone"<?= $filters['revisao']==='telefone'?' selected':'' ?>>Revisar telefone</option><option value="nascimento"<?= $filters['revisao']==='nascimento'?' selected':'' ?>>Revisar nascimento</option><option value="cadastro"<?= $filters['revisao']==='cadastro'?' selected':'' ?>>Revisar cadastro</option><option value="cpf_duplicado"<?= $filters['revisao']==='cpf_duplicado'?' selected':'' ?>>CPF duplicado</option></select></label>
                <label><span>Lotação</span><select class="form-select" name="lotacao"><option value="">Todas</option><option value="lotado"<?= $filters['lotacao']==='lotado'?' selected':'' ?>>Lotados</option><option value="nao_lotado"<?= $filters['lotacao']==='nao_lotado'?' selected':'' ?>>Não lotados</option><option value="revisar"<?= $filters['lotacao']==='revisar'?' selected':'' ?>>Revisar lotação</option><option value="pronto_importar"<?= $filters['lotacao']==='pronto_importar'?' selected':'' ?>>Pronto para importar</option></select></label>
                <label><span>Órgão / instituição</span><select class="form-select" name="parceiro_id"><option value="">Todos</option><?php foreach ($options['partners'] as $p): $label = trim((string)($p['sigla'] ?? '')); if ($label !== '') $label .= ' — '; $label .= (string)$p['nome']; ?><option value="<?= (int)$p['id'] ?>"<?= (string)$filters['parceiro_id'] === (string)$p['id'] ? ' selected' : '' ?>><?= pe_h($label) ?></option><?php endforeach; ?></select></label>
                <label><span>Bairro</span><select class="form-select" name="bairro"><option value="">Todos</option><?php foreach ($options['bairros'] as $v): ?><option value="<?= pe_h((string)$v) ?>"<?= $filters['bairro']===(string)$v?' selected':'' ?>><?= pe_h((string)$v) ?></option><?php endforeach; ?></select></label>
                <label><span>Local / setor</span><select class="form-select" name="setor"><option value="">Todos</option><?php foreach ($options['setores'] as $v): ?><option value="<?= pe_h((string)$v) ?>"<?= $filters['setor']===(string)$v?' selected':'' ?>><?= pe_h((string)$v) ?></option><?php endforeach; ?></select></label>
                <label><span>Origem</span><select class="form-select" name="origem"><option value="">Todas</option><?php foreach ($options['origens'] as $v): ?><option value="<?= pe_h((string)$v) ?>"<?= $filters['origem']===(string)$v?' selected':'' ?>><?= pe_h((string)$v) ?></option><?php endforeach; ?></select></label>
                <label><span>Sexo</span><select class="form-select" name="sexo"><option value="">Todos</option><?php foreach ($options['sexos'] as $v): ?><option value="<?= pe_h((string)$v) ?>"<?= $filters['sexo']===(string)$v?' selected':'' ?>><?= pe_h((string)$v) ?></option><?php endforeach; ?></select></label>
                <label><span>Idade mínima</span><input class="form-control" type="number" min="0" max="120" name="idade_min" value="<?= pe_h($filters['idade_min']) ?>" placeholder="Ex.: 18"></label>
                <label><span>Idade máxima</span><input class="form-control" type="number" min="0" max="120" name="idade_max" value="<?= pe_h($filters['idade_max']) ?>" placeholder="Ex.: 24"></label>
                <label><span>Cadastro a partir de</span><input class="form-control" type="date" name="data_inicio" value="<?= pe_h($filters['data_inicio']) ?>"></label>
                <label><span>Cadastro até</span><input class="form-control" type="date" name="data_fim" value="<?= pe_h($filters['data_fim']) ?>"></label>
            </div>
        </form>

        <div class="pe-report-kpi-grid mt-3">
            <article class="pe-report-kpi"><span>Resultado filtrado</span><strong><?= pe_h((string)$summary['total']) ?></strong><small>candidato(s)</small></article>
            <article class="pe-report-kpi pe-report-kpi--success"><span>Cadastro regular</span><strong><?= pe_h((string)$summary['cadastro_regular']) ?></strong><small><?= pe_h(number_format($regularPercent,1,',','.')) ?>% da seleção</small></article>
            <article class="pe-report-kpi pe-report-kpi--warning"><span>Revisão pendente</span><strong><?= pe_h((string)$summary['revisao_pendente']) ?></strong><small><?= pe_h(number_format($reviewPercent,1,',','.')) ?>% da seleção</small></article>
            <article class="pe-report-kpi pe-report-kpi--success"><span>Lotados</span><strong><?= pe_h((string)$summary['lotados']) ?></strong><small><?= pe_h(number_format($lotacaoPercent,1,',','.')) ?>% da seleção</small></article>
            <article class="pe-report-kpi pe-report-kpi--warning"><span>Não lotados</span><strong><?= pe_h((string)$summary['nao_lotados']) ?></strong><small>sem vínculo ativo</small></article>
            <article class="pe-report-kpi pe-report-kpi--danger"><span>Revisar lotação</span><strong><?= pe_h((string)$summary['revisar_lotacao']) ?></strong><small>inconsistências</small></article>
            <article class="pe-report-kpi pe-report-kpi--danger"><span>CPF duplicado</span><strong><?= pe_h((string)$summary['cpf_duplicado']) ?></strong><small>conflitos pendentes</small></article>
            <article class="pe-report-kpi"><span>Idade média</span><strong><?= pe_h(number_format((float)$summary['idade_media'],1,',','.')) ?></strong><small>anos</small></article>
        </div>

        <section class="pe-report-executive-grid mt-3">
            <article class="pe-report-executive-card">
                <div class="card-kicker">Leitura gerencial</div>
                <h3>Resumo da seleção</h3>
                <div class="pe-report-progress-list">
                    <div><header><span>Taxa de lotação</span><strong><?= pe_h(number_format($lotacaoPercent,1,',','.')) ?>%</strong></header><div class="pe-report-progress"><span style="width:<?= min(100,max(0,$lotacaoPercent)) ?>%"></span></div></div>
                    <div><header><span>Cadastros regulares</span><strong><?= pe_h(number_format($regularPercent,1,',','.')) ?>%</strong></header><div class="pe-report-progress"><span style="width:<?= min(100,max(0,$regularPercent)) ?>%"></span></div></div>
                    <div><header><span>Base com revisão pendente</span><strong><?= pe_h(number_format($reviewPercent,1,',','.')) ?>%</strong></header><div class="pe-report-progress pe-report-progress--warning"><span style="width:<?= min(100,max(0,$reviewPercent)) ?>%"></span></div></div>
                </div>
            </article>

            <article class="pe-report-executive-card">
                <div class="card-kicker">Execução operacional</div>
                <h3>Indicadores relacionados</h3>
                <div class="pe-report-mini-metrics">
                    <div><strong><?= pe_h((string)$operational['visitas']['total']) ?></strong><span>Visitas</span></div>
                    <div><strong><?= pe_h((string)$operational['visitas']['deferidos']) ?></strong><span>Deferidos</span></div>
                    <div><strong><?= pe_h(number_format((float)$operational['frequencia']['media'],1,',','.')) ?>%</strong><span>Freq. média · <?= pe_h($freqComp) ?></span></div>
                    <div><strong>R$ <?= pe_h(number_format((float)$operational['bolsas']['valor_pago'],2,',','.')) ?></strong><span>Bolsas pagas · <?= pe_h($grantComp) ?></span></div>
                    <div><strong><?= pe_h((string)$operational['rede']['vagas_abertas']) ?></strong><span>Vagas abertas</span></div>
                    <div><strong><?= pe_h((string)$operational['rede']['parceiros_ativos']) ?></strong><span>Parceiros ativos</span></div>
                    <div><strong><?= pe_h((string)$operational['documentos_resumo']['pendentes']) ?></strong><span>Docs. pendentes</span></div>
                    <div><strong><?= pe_h((string)$operational['documentos_resumo']['vencidos']) ?></strong><span>Docs. vencidos</span></div>
                </div>
            </article>
        </section>

        <section class="pe-report-chart-grid mt-3">
            <?php
            $charts = [
                ['id'=>'peRelStatus','k'=>'Situação do programa','t'=>'Status dos candidatos','d'=>'Distribuição dos status na seleção filtrada.'],
                ['id'=>'peRelLotacao','k'=>'Vínculos','t'=>'Situação de lotação','d'=>'Lotados, não lotados e registros que exigem revisão.'],
                ['id'=>'peRelReview','k'=>'Qualidade cadastral','t'=>'Situação da revisão','d'=>'Distribuição das pendências cadastrais.'],
                ['id'=>'peRelAge','k'=>'Perfil','t'=>'Faixa etária','d'=>'Distribuição dos candidatos por idade.'],
                ['id'=>'peRelSex','k'=>'Perfil','t'=>'Distribuição por sexo','d'=>'Composição da seleção pelos valores cadastrados.'],
                ['id'=>'peRelOrigin','k'=>'Origem','t'=>'Origem dos cadastros','d'=>'Comparação entre importação e cadastro manual.'],
                ['id'=>'peRelBairros','k'=>'Território','t'=>'Bairros com mais candidatos','d'=>'Top 10 bairros da seleção.'],
                ['id'=>'peRelPartners','k'=>'Rede parceira','t'=>'Distribuição por órgão','d'=>'Candidatos vinculados por órgão ou instituição.'],
                ['id'=>'peRelVisits','k'=>'Assistência social','t'=>'Pareceres de visitas','d'=>'Deferidos, indeferidos e pendentes.'],
                ['id'=>'peRelReferrals','k'=>'Seleção','t'=>'Encaminhamentos','d'=>'Status dos encaminhamentos associados à seleção.'],
                ['id'=>'peRelDocs','k'=>'Documentação','t'=>'Situação dos documentos','d'=>'Status dos documentos associados aos candidatos filtrados.'],
                ['id'=>'peRelFrequency','k'=>'Frequência','t'=>'Faixas de frequência','d'=>'Competência mais recente: '.$freqComp.'.'],
                ['id'=>'peRelGrants','k'=>'Bolsas','t'=>'Situação das bolsas','d'=>'Competência mais recente: '.$grantComp.'.'],
            ];
            foreach ($charts as $chart): ?>
                <article class="pe-report-chart-card">
                    <div class="card-kicker"><?= pe_h($chart['k']) ?></div>
                    <h3><?= pe_h($chart['t']) ?></h3>
                    <p><?= pe_h($chart['d']) ?></p>
                    <div class="pe-report-chart-wrap"><canvas id="<?= pe_h($chart['id']) ?>"></canvas></div>
                </article>
            <?php endforeach; ?>
        </section>

        <section class="pe-report-detail-section mt-3">
            <div class="pe-report-detail-head">
                <div>
                    <div class="card-kicker">Base detalhada</div>
                    <h3>Candidatos encontrados</h3>
                    <p><strong><?= pe_h((string)$total) ?></strong> registro(s) encontrados. Página <?= pe_h((string)$currentPage) ?> de <?= pe_h((string)$pages) ?>.</p>
                </div>
                <div class="pe-no-print"><a class="btn btn-light" href="<?= pe_h($exportUrl) ?>"><i class="bi bi-download"></i> Baixar dados filtrados</a></div>
            </div>

            <div class="pe-table-wrap">
                <div class="table-responsive">
                    <table class="table table-sm align-middle pe-data-table pe-report-table pe-report-detail-table">
                        <thead><tr><th>#</th><th>Candidato</th><th>CPF</th><th>Idade</th><th>Bairro</th><th>Cadastro</th><th>Lotação</th><th>Órgão / local</th><th>Parecer</th><th>Encaminhamento</th><th>Docs.</th><th>Status</th></tr></thead>
                        <tbody>
                        <?php if (!$rows): ?><tr><td colspan="12" class="text-center text-muted py-5">Nenhum registro encontrado com os filtros informados.</td></tr><?php endif; ?>
                        <?php foreach ($rows as $index => $row):
                            $position = (($currentPage - 1) * $perPage) + $index + 1;
                            $partner = trim((string)($row['parceiro_sigla'] ?? ''));
                            if ($partner !== '') $partner .= ' — ';
                            $partner .= (string)($row['parceiro_nome'] ?? '');
                            $local = trim((string)($row['local_atuacao'] ?? ''));
                            $setor = trim((string)($row['setor'] ?? ''));
                            if ($setor !== '' && $setor !== $local) $local .= ($local !== '' ? ' · ' : '') . $setor;
                            $reviewTone = ($row['revisao_status'] ?? '') ? 'warning' : 'success';
                            $lotTone = 'success';
                            if ($row['situacao_lotacao'] === 'Não lotado') $lotTone = 'warning';
                            elseif ($row['situacao_lotacao'] === 'Revisar lotação') $lotTone = 'danger';
                            elseif ($row['situacao_lotacao'] === 'Pronto para importar') $lotTone = 'info';
                        ?>
                            <tr>
                                <td><?= $position ?></td>
                                <td><strong><?= pe_h((string)$row['nome']) ?></strong><small><?= pe_h((string)($row['origem'] ?: '—')) ?></small></td>
                                <td><?= pe_h(pe_format_cpf((string)($row['cpf'] ?? ''))) ?></td>
                                <td><?= $row['idade'] !== null ? pe_h((string)$row['idade']) : '—' ?></td>
                                <td><?= pe_h((string)($row['bairro'] ?: '—')) ?></td>
                                <td><span class="pe-inline-badge pe-inline-badge--<?= pe_h($reviewTone) ?>"><?= pe_h((string)($row['revisao_status'] ?: 'Regular')) ?></span></td>
                                <td><span class="pe-inline-badge pe-inline-badge--<?= pe_h($lotTone) ?>"><?= pe_h((string)$row['situacao_lotacao']) ?></span></td>
                                <td><strong><?= pe_h(trim($partner) ?: '—') ?></strong><small><?= pe_h($local ?: '—') ?></small></td>
                                <td><?= pe_h((string)($row['parecer'] ?: 'Pendente')) ?></td>
                                <td><?= pe_h((string)($row['encaminhamento_status'] ?: '—')) ?></td>
                                <td><?= (int)$row['documentos_pendentes'] > 0 ? '<span class="pe-status-font pe-status-font--warning">'.(int)$row['documentos_pendentes'].' pend.</span>' : '<span class="pe-status-font pe-status-font--success">Regular</span>' ?></td>
                                <td><?= pe_status_label((string)$row['status']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if ($pages > 1): ?>
                <nav class="pe-report-pagination pe-no-print" aria-label="Paginação do relatório">
                    <?php
                    $baseQuery = $_GET;
                    unset($baseQuery['pe_export']);
                    $prevQuery = $baseQuery; $prevQuery['pagina'] = max(1, $currentPage - 1);
                    $nextQuery = $baseQuery; $nextQuery['pagina'] = min($pages, $currentPage + 1);
                    ?>
                    <a class="btn btn-light<?= $currentPage <= 1 ? ' disabled' : '' ?>" href="primeiro-emprego/relatorios.php?<?= pe_h(http_build_query($prevQuery)) ?>"><i class="bi bi-chevron-left"></i> Anterior</a>
                    <span>Página <strong><?= $currentPage ?></strong> de <strong><?= $pages ?></strong></span>
                    <a class="btn btn-light<?= $currentPage >= $pages ? ' disabled' : '' ?>" href="primeiro-emprego/relatorios.php?<?= pe_h(http_build_query($nextQuery)) ?>">Próxima <i class="bi bi-chevron-right"></i></a>
                </nav>
            <?php endif; ?>
        </section>

        <script id="peReportPayload" type="application/json"><?= json_encode($chartPayload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
        <script>
        (function () {
            const payloadEl = document.getElementById('peReportPayload');
            if (!payloadEl) return;
            const payload = JSON.parse(payloadEl.textContent || '{}');

            const boot = () => {
                if (!window.Chart) return;

                const palette = ['#176b85','#3e8ea7','#7ab6c8','#b2d7e2','#f4a259','#de6b48','#7c4dff','#2e7d32','#64748b','#d9a404'];
                Chart.defaults.font.family = 'Inter, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif';
                Chart.defaults.color = '#49606b';
                Chart.defaults.plugins.legend.position = 'bottom';
                Chart.defaults.plugins.legend.labels.boxWidth = 13;
                Chart.defaults.plugins.legend.labels.usePointStyle = true;
                Chart.defaults.maintainAspectRatio = false;

                const normalized = value => Array.isArray(value) ? value.filter(item => Number(item.value || 0) > 0) : [];
                const empty = id => {
                    const canvas = document.getElementById(id);
                    if (canvas && canvas.parentElement) canvas.parentElement.innerHTML = '<div class="pe-chart-empty">Sem dados suficientes para este recorte.</div>';
                };

                const donut = (id, source) => {
                    const series = normalized(source);
                    if (!series.length) return empty(id);
                    const canvas = document.getElementById(id); if (!canvas) return;
                    new Chart(canvas, {type:'doughnut',data:{labels:series.map(x=>x.label),datasets:[{data:series.map(x=>Number(x.value||0)),backgroundColor:palette}]}});
                };
                const bar = (id, source, horizontal=false) => {
                    const series = normalized(source);
                    if (!series.length) return empty(id);
                    const canvas = document.getElementById(id); if (!canvas) return;
                    new Chart(canvas, {type:'bar',data:{labels:series.map(x=>x.label),datasets:[{label:'Registros',data:series.map(x=>Number(x.value||0)),backgroundColor:'#3e8ea7'}]},options:{indexAxis:horizontal?'y':'x',scales:{[horizontal?'x':'y']:{beginAtZero:true,ticks:{precision:0}}}}});
                };

                donut('peRelStatus', payload.status);
                donut('peRelLotacao', payload.lotacao);
                bar('peRelReview', payload.revisao, true);
                bar('peRelAge', payload.idades, false);
                donut('peRelSex', payload.sexo);
                donut('peRelOrigin', payload.origem);
                bar('peRelBairros', payload.bairros, true);
                bar('peRelPartners', payload.parceiros, true);
                donut('peRelVisits', payload.visitas);
                donut('peRelReferrals', payload.encaminhamentos);
                donut('peRelDocs', payload.documentos);
                bar('peRelFrequency', payload.frequencia, false);
                donut('peRelGrants', payload.bolsas);
            };

            if (window.Chart) return boot();
            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js';
            script.onload = boot;
            document.head.appendChild(script);
        })();
        </script>
    <?php endif; ?>
</section>
<?php
$pageCustomContent = (string) ob_get_clean();
