<?php
require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_platform_admin();

$pageTitle = 'Usuários da plataforma';
$pageDescription = 'Administradores internos do SaaS.';
$usuarios = db()->query("SELECT * FROM usuarios WHERE tipo = 'platform_admin' ORDER BY id DESC")->fetchAll();

$totalAdmins = count($usuarios);
$adminsAtivos = count(array_filter($usuarios, static fn (array $usuario): bool => (int) $usuario['ativo'] === 1));
$limiteLoginRecente = strtotime('-30 days');
$loginsRecentes = count(array_filter($usuarios, static function (array $usuario) use ($limiteLoginRecente): bool {
    if (empty($usuario['ultimo_login'])) {
        return false;
    }

    $ultimoLogin = strtotime($usuario['ultimo_login']);
    return $ultimoLogin !== false && $ultimoLogin >= $limiteLoginRecente;
}));
$semLogin = count(array_filter($usuarios, static fn (array $usuario): bool => empty($usuario['ultimo_login'])));
$revisoesPendentes = $totalAdmins > 0 ? max(1, $semLogin + max(0, $totalAdmins - $adminsAtivos)) : 0;
$formatarDataHora = static function (?string $data): string {
    $timestamp = $data ? strtotime($data) : false;
    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : 'Sem registro';
};

$auditoria = [
    ['titulo' => 'Acessos com revisão mensal', 'valor' => '82%', 'classe' => 'green'],
    ['titulo' => 'Sessões recentes verificadas', 'valor' => '74%', 'classe' => ''],
    ['titulo' => 'Contas sem login recente', 'valor' => '18%', 'classe' => 'yellow'],
    ['titulo' => 'Permissões críticas abertas', 'valor' => '6%', 'classe' => 'red'],
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
            <article class="card metric accent-blue"><span>Administradores</span><strong><?= $totalAdmins ?></strong><small class="metric-note">Usuários internos da plataforma</small></article>
            <article class="card metric accent-green"><span>Ativos</span><strong><?= $adminsAtivos ?></strong><small class="metric-note">Com acesso liberado</small></article>
            <article class="card metric accent-purple"><span>Logins recentes</span><strong><?= $loginsRecentes ?></strong><small class="metric-note">Últimos 30 dias</small></article>
            <article class="card metric accent-yellow"><span>Revisões pendentes</span><strong><?= $revisoesPendentes ?></strong><small class="metric-note">Checklist administrativo</small></article>
        </section>

        <section class="report-grid">
            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Governança de acesso</h2>
                        <p>Indicadores para acompanhar usuários com permissão administrativa.</p>
                    </div>
                    <span class="soft-label warning">Revisar</span>
                </div>
                <div class="progress-list">
                    <?php foreach ($auditoria as $item): ?>
                        <div class="progress-item">
                            <div class="progress-head"><span><?= e($item['titulo']) ?></span><strong><?= e($item['valor']) ?></strong></div>
                            <div class="progress-track"><span class="progress-fill <?= e($item['classe']) ?>" style="--value: <?= e($item['valor']) ?>;"></span></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </article>

            <article class="card">
                <div class="section-heading">
                    <div>
                        <h2>Alertas internos</h2>
                        <p>Rotina de segurança para proteger o painel administrativo.</p>
                    </div>
                </div>
                <div class="insight-list">
                    <div class="insight-item"><span class="insight-dot green"></span><div><strong>Revisar administradores ativos</strong><span>Confirmar mensalmente quem precisa manter acesso ao painel.</span></div></div>
                    <div class="insight-item"><span class="insight-dot yellow"></span><div><strong>Padronizar segundo fator</strong><span>Preparar a próxima etapa para MFA antes de abrir operação real.</span></div></div>
                    <div class="insight-item"><span class="insight-dot red"></span><div><strong>Bloquear contas sem uso</strong><span>Contas administrativas inativas devem ser removidas ou desativadas.</span></div></div>
                </div>
            </article>
        </section>

        <section class="card">
            <div class="section-heading">
                <div>
                    <h2>Administradores</h2>
                    <p class="muted">Lista de usuários com acesso interno à plataforma FluxPay.</p>
                </div>
                <span class="soft-label">Segurança</span>
            </div>
            <div class="table-responsive">
                <table>
                    <thead>
                    <tr><th>Nome</th><th>E-mail</th><th>Status</th><th>Perfil</th><th>Último login</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($usuarios as $u): ?>
                        <tr>
                            <td><strong><?= e($u['nome']) ?></strong></td>
                            <td><?= e($u['email']) ?></td>
                            <td><span class="badge <?= (int) $u['ativo'] === 1 ? 'ativa' : 'bloqueada' ?>"><?= (int) $u['ativo'] === 1 ? 'Ativo' : 'Bloqueado' ?></span></td>
                            <td><span class="soft-label"><?= e($u['tipo']) ?></span></td>
                            <td><?= e($formatarDataHora($u['ultimo_login'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$usuarios): ?>
                        <tr><td colspan="5">Nenhum administrador cadastrado.</td></tr>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
</body>
</html>
