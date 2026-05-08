<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Assinaturas';
$pageDescription = 'Controle das empresas pagantes, em teste, vencidas ou bloqueadas.';
$assinaturas = db()->query(
    "SELECT a.*, e.nome AS empresa, p.nome AS plano
     FROM assinaturas a
     INNER JOIN empresas e ON e.id = a.empresa_id
     INNER JOIN planos p ON p.id = a.plano_id
     ORDER BY a.data_vencimento ASC"
)->fetchAll();

$totalAssinaturas = count($assinaturas);
$assinaturasAtivas = count(array_filter($assinaturas, static fn (array $assinatura): bool => in_array($assinatura['status'], ['ativa', 'teste'], true)));
$assinaturasEmRisco = count(array_filter($assinaturas, static fn (array $assinatura): bool => in_array($assinatura['status'], ['vencida', 'bloqueada'], true)));
$valorMensal = array_sum(array_map(
    static fn (array $assinatura): float => in_array($assinatura['status'], ['ativa', 'teste'], true) ? (float) $assinatura['valor'] : 0.00,
    $assinaturas
));
$valorEmRisco = array_sum(array_map(
    static fn (array $assinatura): float => in_array($assinatura['status'], ['vencida', 'bloqueada'], true) ? (float) $assinatura['valor'] : 0.00,
    $assinaturas
));
$renovacoes7Dias = count(array_filter($assinaturas, static function (array $assinatura): bool {
    $vencimento = strtotime($assinatura['data_vencimento'] ?? '');
    return $vencimento !== false && $vencimento >= strtotime('today') && $vencimento <= strtotime('+7 days');
}));
$formatarData = static function (?string $data): string {
    $timestamp = $data ? strtotime($data) : false;
    return $timestamp !== false ? date('d/m/Y', $timestamp) : '-';
};

$fluxoAssinaturas = [
    ['titulo' => 'Recebido', 'valor' => 64, 'classe' => 'green'],
    ['titulo' => 'A receber', 'valor' => 78, 'classe' => ''],
    ['titulo' => 'Em teste', 'valor' => 36, 'classe' => 'yellow'],
    ['titulo' => 'Em risco', 'valor' => 18, 'classe' => 'red'],
];
$agendaCobranca = [
    ['data' => 'D+1', 'titulo' => 'Primeiro lembrete automático', 'texto' => 'Empresas em teste recebem mensagem de ativação do plano.'],
    ['data' => 'D+3', 'titulo' => 'Revisão de vencidos', 'texto' => 'Separar bloqueio financeiro de cancelamento definitivo.'],
    ['data' => 'D+7', 'titulo' => 'Negociação de renovação', 'texto' => 'Priorizar contratos com uso alto e pagamento pendente.'],
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="<?= e(asset_url('/assets/css/app.css')) ?>">
</head>
<body>
<div class="layout">
    <?php require APP_PATH . '/Includes/admin_sidebar.php'; ?>
    <main class="content">
        <?php require APP_PATH . '/Includes/topbar.php'; ?>
        <?php require APP_PATH . '/Includes/flash.php'; ?>

        <section class="grid four">
            <article class="card metric accent-blue"><span>Total de assinaturas</span><strong><?= $totalAssinaturas ?></strong><small class="metric-note">Contratos acompanhados</small></article>
            <article class="card metric accent-green"><span>Ativas e teste</span><strong><?= $assinaturasAtivas ?></strong><small class="metric-note">Com potencial de receita</small></article>
            <article class="card metric accent-yellow"><span>Renovações em 7 dias</span><strong><?= $renovacoes7Dias ?></strong><small class="metric-note">Vencimentos próximos</small></article>
            <article class="card metric accent-red"><span>Receita em risco</span><strong><?= moeda_br($valorEmRisco) ?></strong><small class="metric-note"><?= $assinaturasEmRisco ?> assinatura(s) críticas</small></article>
        </section>

        <section class="report-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Fluxo de receita</h2>
                        <p>Projeção operacional para acompanhar cobranças e renovações.</p>
                    </div>
                    <span class="soft-label success"><?= moeda_br($valorMensal) ?>/mês</span>
                </div>
                <div class="mini-chart">
                    <?php foreach ($fluxoAssinaturas as $item): ?>
                        <div class="mini-chart-item">
                            <div class="mini-chart-bar <?= e($item['classe']) ?>" style="--h: <?= (int) $item['valor'] ?>%;"></div>
                            <span class="mini-chart-label"><?= e($item['titulo']) ?></span>
                            <span class="mini-chart-value"><?= (int) $item['valor'] ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Risco e retenção</h2>
                        <p>Indicadores para orientar o contato financeiro.</p>
                    </div>
                </div>
                <div class="progress-list">
                    <div class="progress-item"><div class="progress-head"><span>Assinaturas saudáveis</span><strong>91%</strong></div><div class="progress-track"><span class="progress-fill green" style="--value: 91%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Renovação prevista</span><strong>76%</strong></div><div class="progress-track"><span class="progress-fill" style="--value: 76%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Uso baixo da plataforma</span><strong>18%</strong></div><div class="progress-track"><span class="progress-fill yellow" style="--value: 18%;"></span></div></div>
                    <div class="progress-item"><div class="progress-head"><span>Bloqueio recomendado</span><strong>9%</strong></div><div class="progress-track"><span class="progress-fill red" style="--value: 9%;"></span></div></div>
                </div>
            </article>
        </section>

        <section class="report-grid reverse">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Agenda de cobrança</h2>
                        <p>Sequência sugerida para contratos com vencimento próximo.</p>
                    </div>
                    <span class="soft-label warning">Operação</span>
                </div>
                <div class="timeline">
                    <?php foreach ($agendaCobranca as $evento): ?>
                        <div class="timeline-item">
                            <div class="timeline-date"><?= e($evento['data']) ?></div>
                            <div class="timeline-body"><strong><?= e($evento['titulo']) ?></strong><span><?= e($evento['texto']) ?></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Assinaturas cadastradas</h2>
                        <p class="muted">Lista de contratos, planos, status e vencimentos.</p>
                    </div>
                </div>
                <div class="table-responsive">
                    <table>
                        <thead>
                        <tr><th>Empresa</th><th>Plano</th><th>Status</th><th>Valor</th><th>Início</th><th>Vencimento</th></tr>
                        </thead>
                        <tbody>
                        <?php foreach ($assinaturas as $a): ?>
                            <tr>
                                <td><strong><?= e($a['empresa']) ?></strong></td>
                                <td><?= e($a['plano']) ?></td>
                                <td><span class="badge <?= e($a['status']) ?>"><?= e($a['status']) ?></span></td>
                                <td><strong><?= moeda_br((float) $a['valor']) ?></strong></td>
                                <td><?= e($formatarData($a['data_inicio'])) ?></td>
                                <td><?= e($formatarData($a['data_vencimento'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (!$assinaturas): ?>
                            <tr><td colspan="6">Nenhuma assinatura cadastrada.</td></tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </article>
        </section>
    </main>
</div>
</body>
</html>
