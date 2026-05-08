<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Planos do SaaS';
$pageDescription = 'Cadastre os planos que serão vendidos para as empresas.';
$planos = db()->query('SELECT * FROM planos ORDER BY preco ASC')->fetchAll();

$totalPlanos = count($planos);
$planosAtivos = count(array_filter($planos, static fn (array $plano): bool => (int) $plano['ativo'] === 1));
$precoMedio = $totalPlanos > 0
    ? array_sum(array_map(static fn (array $plano): float => (float) $plano['preco'], $planos)) / $totalPlanos
    : 0.00;
$mrrEstimado = $precoMedio * 38;

$mixPlanos = [
    ['nome' => 'Entrada', 'valor' => 32, 'classe' => ''],
    ['nome' => 'Crescimento', 'valor' => 46, 'classe' => 'green'],
    ['nome' => 'Premium', 'valor' => 22, 'classe' => 'yellow'],
];
$benchmarks = [
    ['titulo' => 'Conversão de teste', 'valor' => '68%', 'classe' => 'green'],
    ['titulo' => 'Upgrade sugerido', 'valor' => '24%', 'classe' => ''],
    ['titulo' => 'Planos sem uso de relatórios', 'valor' => '11%', 'classe' => 'yellow'],
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
            <article class="card metric accent-blue"><span>Planos cadastrados</span><strong><?= $totalPlanos ?></strong><small class="metric-note">Modelos comerciais disponíveis</small></article>
            <article class="card metric accent-green"><span>Planos ativos</span><strong><?= $planosAtivos ?></strong><small class="metric-note">Visíveis para contratos</small></article>
            <article class="card metric accent-purple"><span>Preço médio</span><strong><?= moeda_br($precoMedio) ?></strong><small class="metric-note">Baseado nos planos atuais</small></article>
            <article class="card metric accent-yellow"><span>MRR estimado</span><strong><?= moeda_br($mrrEstimado) ?></strong><small class="metric-note">Projeção com 38 contratos</small></article>
        </section>

        <section class="report-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Distribuição comercial</h2>
                        <p>Leitura estimada da procura por categoria de plano.</p>
                    </div>
                    <span class="soft-label success">Crescimento</span>
                </div>
                <div class="mini-chart">
                    <?php foreach ($mixPlanos as $item): ?>
                        <div class="mini-chart-item">
                            <div class="mini-chart-bar <?= e($item['classe']) ?>" style="--h: <?= (int) $item['valor'] ?>%;"></div>
                            <span class="mini-chart-label"><?= e($item['nome']) ?></span>
                            <span class="mini-chart-value"><?= (int) $item['valor'] ?>%</span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Benchmarks dos planos</h2>
                        <p>Indicadores para ajustar preço, limites e benefícios.</p>
                    </div>
                </div>
                <div class="progress-list">
                    <?php foreach ($benchmarks as $benchmark): ?>
                        <div class="progress-item">
                            <div class="progress-head"><span><?= e($benchmark['titulo']) ?></span><strong><?= e($benchmark['valor']) ?></strong></div>
                            <div class="progress-track"><span class="progress-fill <?= e($benchmark['classe']) ?>" style="--value: <?= e($benchmark['valor']) ?>;"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>
        </section>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Novo plano</h2>
                    <p class="muted">Defina limites e recursos que serão usados no contrato da empresa locatária.</p>
                </div>
                <span class="soft-label">Catálogo</span>
            </div>
            <form method="post" action="<?= e(public_url('/actions/admin/salvar_plano.php')) ?>" class="form-grid">
                <?= csrf_field() ?>
                <label>Nome<input name="nome" required placeholder="Básico, Profissional, Premium"></label>
                <label>Preço<input name="preco" required placeholder="99,90"></label>
                <label>Limite de clientes<input type="number" name="limite_clientes" min="0" placeholder="ex: 50"></label>
                <label>Limite de usuários<input type="number" name="limite_usuarios" min="0" placeholder="ex: 3"></label>
                <label class="check"><input type="checkbox" name="whatsapp_ativo" checked> WhatsApp ativo</label>
                <label class="check"><input type="checkbox" name="leitura_comprovante" checked> Leitura de comprovante</label>
                <label class="check"><input type="checkbox" name="relatorios_avancados" checked> Relatórios avançados</label>
                <div class="form-actions"><button class="btn btn-primary">Cadastrar plano</button></div>
            </form>
        </section>

        <section class="grid three">
            <?php foreach ($planos as $plano): ?>
                <article class="card plan plan-card">
                    <div class="section-heading">
                        <div>
                            <h3><?= e($plano['nome']) ?></h3>
                            <p><?= (int) $plano['ativo'] === 1 ? 'Disponível para venda' : 'Fora do catálogo' ?></p>
                        </div>
                        <span class="soft-label <?= (int) $plano['ativo'] === 1 ? 'success' : 'danger' ?>"><?= (int) $plano['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span>
                    </div>
                    <strong><?= moeda_br((float) $plano['preco']) ?></strong>
                    <ul class="plan-feature-list">
                        <li><?= $plano['limite_clientes'] ? 'Até ' . (int) $plano['limite_clientes'] . ' clientes' : 'Clientes ilimitados' ?></li>
                        <li><?= $plano['limite_usuarios'] ? 'Até ' . (int) $plano['limite_usuarios'] . ' usuários' : 'Usuários ilimitados' ?></li>
                        <li class="<?= (int) $plano['whatsapp_ativo'] === 1 ? '' : 'feature-disabled' ?>">WhatsApp <?= (int) $plano['whatsapp_ativo'] === 1 ? 'incluído' : 'não incluído' ?></li>
                        <li class="<?= (int) $plano['leitura_comprovante'] === 1 ? '' : 'feature-disabled' ?>">Leitura de comprovantes <?= (int) $plano['leitura_comprovante'] === 1 ? 'incluída' : 'indisponível' ?></li>
                        <li class="<?= (int) $plano['relatorios_avancados'] === 1 ? '' : 'feature-disabled' ?>">Relatórios avançados <?= (int) $plano['relatorios_avancados'] === 1 ? 'incluídos' : 'indisponíveis' ?></li>
                    </ul>
                </article>
            <?php endforeach; ?>
            <?php if (!$planos): ?>
                <article class="card empty-state">Nenhum plano cadastrado ainda.</article>
            <?php endif; ?>
        </section>
    </main>
</div>
</body>
</html>
