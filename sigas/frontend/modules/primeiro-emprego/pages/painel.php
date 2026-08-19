<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/lib/repository.php';

$dbReady = pe_db_ready() && pe_schema_ready();
$stats = ['total'=>0,'contemplados'=>0,'visitas'=>0,'deferidos'=>0,'indeferidos'=>0,'importados'=>0,'revisao_pendente'=>0,'revisar_cadastro'=>0,'cpf_duplicado'=>0];
$reviewCounts = [];
$monthly = ['labels'=>[], 'values'=>[]];
$activity = [];

if ($dbReady) {
    try {
        $pdo = pe_db();
        $stats = pe_dashboard_stats($pdo);
        $reviewCounts = pe_dashboard_review_counts($pdo);
        $monthly = pe_dashboard_monthly($pdo, 6);
        $activity = pe_recent_activity($pdo, 8);
    } catch (Throwable) {
        $dbReady = false;
    }
}

$pageDefinition = [
    'title' => 'Painel',
    'description' => 'Visão consolidada e operacional dos candidatos, revisões, visitas e contemplados do programa.',
    'demo' => false,
    'show_states' => false,
    'actions' => [
        ['label' => 'Novo candidato', 'icon' => 'person-plus', 'primary' => true, 'href' => 'primeiro-emprego/cadastro-candidato.php'],
        ['label' => 'Importar Excel', 'icon' => 'file-earmark-spreadsheet', 'href' => 'primeiro-emprego/importar-candidatos.php'],
    ],
    'stats' => [
        ['label'=>'Candidatos','value'=>(string)$stats['total'],'detail'=>'Base cadastrada','icon'=>'people'],
        ['label'=>'Contemplados','value'=>(string)$stats['contemplados'],'detail'=>'Situação do programa','icon'=>'person-check'],
        ['label'=>'Revisão pendente','value'=>(string)$stats['revisao_pendente'],'detail'=>'Qualidade cadastral','icon'=>'exclamation-circle'],
        ['label'=>'CPF duplicado','value'=>(string)$stats['cpf_duplicado'],'detail'=>'Conflitos não confirmados','icon'=>'person-exclamation'],
        ['label'=>'Visitas sociais','value'=>(string)$stats['visitas'],'detail'=>'Pareceres registrados','icon'=>'house-check'],
        ['label'=>'Importados','value'=>(string)$stats['importados'],'detail'=>'Origem planilha','icon'=>'file-earmark-spreadsheet'],
    ],
    'blocks' => $dbReady ? [
        [
            'type' => 'chart',
            'kicker' => 'Evolução da base',
            'title' => 'Novos candidatos por mês',
            'chart' => 'line',
            'labels' => $monthly['labels'],
            'values' => $monthly['values'],
        ],
        [
            'type' => 'info',
            'kicker' => 'Revisão cadastral',
            'title' => 'Pendências que exigem conferência',
            'description' => 'A revisão não impede o candidato de permanecer cadastrado.',
            'items' => [
                ['icon'=>'person-vcard','title'=>'Revisar CPF','text'=>'CPF ausente, inconsistente ou duplicado.','badge'=>(string)($reviewCounts['Revisar CPF'] ?? 0)],
                ['icon'=>'telephone','title'=>'Revisar Telefone','text'=>'Telefone ausente ou fora do padrão.','badge'=>(string)($reviewCounts['Revisar Telefone'] ?? 0)],
                ['icon'=>'calendar3','title'=>'Revisar nascimento','text'=>'Data ausente ou inválida.','badge'=>(string)($reviewCounts['Revisar Data de Nascimento'] ?? 0)],
                ['icon'=>'clipboard2-check','title'=>'Revisar Cadastro','text'=>'Mais de uma pendência no mesmo cadastro.','badge'=>(string)($reviewCounts['Revisar Cadastro'] ?? 0)],
            ],
        ],
    ] : [],
    'modal' => ['title' => 'Resumo do painel'],
];

ob_start();
?>
<?php if (!$dbReady): ?>
    <section class="content-card pe-form-card"><div class="alert alert-warning mb-0"><strong>Primeiro Emprego ainda não está pronto no banco.</strong> Execute <code>database/primeiroEmprego/0001-primeiroEmprego.sql</code> em instalação nova ou <code>0002-primeiroEmprego-operacional.sql</code> em banco já existente.</div></section>
<?php elseif ($activity): ?>
    <section class="content-card pe-form-card mt-3">
        <div class="pe-form-header"><div><div class="card-kicker">Movimentações</div><h2>Atividade recente</h2><p>Últimos cadastros, importações e visitas registradas no módulo.</p></div></div>
        <div class="pe-activity-list">
            <?php foreach ($activity as $item): ?>
                <article><i class="bi bi-clock-history"></i><div><strong><?= pe_h($item['titulo']) ?></strong><span><?= pe_h($item['descricao']) ?></span></div><time><?= pe_h(date('d/m/Y H:i', strtotime((string)$item['data_evento']))) ?></time></article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>
<?php
$pageCustomContent = (string) ob_get_clean();
