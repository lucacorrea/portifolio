<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$q = trim((string) ($_GET['q'] ?? ''));
$entidade = trim((string) ($_GET['entidade'] ?? ''));
$acao = trim((string) ($_GET['acao'] ?? ''));
$dataInicio = trim((string) ($_GET['data_inicio'] ?? ''));
$dataFim = trim((string) ($_GET['data_fim'] ?? ''));

$sql = 'SELECT a.*, u.nome AS usuario_nome, u.email AS usuario_email FROM auditoria a LEFT JOIN usuarios u ON u.id = a.usuario_id WHERE 1=1';
$params = [];

if ($q !== '') {
    $sql .= ' AND (u.nome LIKE ? OR u.email LIKE ? OR a.entidade LIKE ? OR a.acao LIKE ? OR a.ip LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca, $busca, $busca];
}

if ($entidade !== '') {
    $sql .= ' AND a.entidade = ?';
    $params[] = $entidade;
}

if ($acao !== '') {
    $sql .= ' AND a.acao = ?';
    $params[] = $acao;
}

if ($dataInicio !== '') {
    $sql .= ' AND a.created_at >= ?';
    $params[] = $dataInicio . ' 00:00:00';
}

if ($dataFim !== '') {
    $sql .= ' AND a.created_at <= ?';
    $params[] = $dataFim . ' 23:59:59';
}

$sql .= ' ORDER BY a.created_at DESC, a.id DESC LIMIT 500';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$registros = $stmt->fetchAll();

$entidades = $pdo->query('SELECT DISTINCT entidade FROM auditoria ORDER BY entidade')->fetchAll(PDO::FETCH_COLUMN);
$acoes = $pdo->query('SELECT DISTINCT acao FROM auditoria ORDER BY acao')->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Auditoria';
$currentPage = 'auditoria';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= page_header('Auditoria', 'Consulte quem realizou alterações e acessos registrados pelo sistema.', [
    ['label' => 'Configurações', 'href' => 'configuracoes.php', 'icon' => 'settings', 'class' => 'btn-secondary']
]) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar usuário, entidade, ação ou IP"></div>
        <select class="select" name="entidade">
            <option value="">Todas as entidades</option>
            <?php foreach ($entidades as $item): ?><option value="<?= h((string) $item) ?>" <?= $entidade === $item ? 'selected' : '' ?>><?= h(ucfirst((string) $item)) ?></option><?php endforeach; ?>
        </select>
        <select class="select" name="acao">
            <option value="">Todas as ações</option>
            <?php foreach ($acoes as $item): ?><option value="<?= h((string) $item) ?>" <?= $acao === $item ? 'selected' : '' ?>><?= h(ucfirst(str_replace('_', ' ', (string) $item))) ?></option><?php endforeach; ?>
        </select>
        <input class="input" type="date" name="data_inicio" value="<?= h($dataInicio) ?>">
        <input class="input" type="date" name="data_fim" value="<?= h($dataFim) ?>">
        <button class="btn" type="submit">Filtrar</button>
        <?php if ($q !== '' || $entidade !== '' || $acao !== '' || $dataInicio !== '' || $dataFim !== ''): ?><a class="btn" href="auditoria.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Data</th><th>Usuário</th><th>Entidade</th><th>Ação</th><th>IP</th><th>Detalhes</th></tr></thead>
            <tbody>
            <?php if (!$registros): ?><tr><td colspan="6" class="muted">Nenhum registro de auditoria encontrado.</td></tr><?php endif; ?>
            <?php foreach ($registros as $registro): ?>
                <tr>
                    <td><?= datetime_br($registro['created_at']) ?></td>
                    <td><strong><?= h($registro['usuario_nome'] ?: 'Sistema') ?></strong><?= $registro['usuario_email'] ? '<div class="muted">' . h($registro['usuario_email']) . '</div>' : '' ?></td>
                    <td><?= h($registro['entidade']) ?><?= $registro['entidade_id'] ? ' #' . (int) $registro['entidade_id'] : '' ?></td>
                    <td><span class="badge info"><?= h(ucfirst(str_replace('_', ' ', $registro['acao']))) ?></span></td>
                    <td><?= h($registro['ip'] ?: '-') ?></td>
                    <td>
                        <?php if ($registro['dados_anteriores'] || $registro['dados_novos']): ?>
                            <details>
                                <summary class="btn">Ver alterações</summary>
                                <div style="margin-top:10px;display:grid;gap:10px;min-width:320px;max-width:560px">
                                    <?php if ($registro['dados_anteriores']): ?><div><strong>Antes</strong><pre style="white-space:pre-wrap;word-break:break-word;background:var(--surface-soft);padding:12px;border-radius:12px;border:1px solid var(--border)"><?= h($registro['dados_anteriores']) ?></pre></div><?php endif; ?>
                                    <?php if ($registro['dados_novos']): ?><div><strong>Depois</strong><pre style="white-space:pre-wrap;word-break:break-word;background:var(--surface-soft);padding:12px;border-radius:12px;border:1px solid var(--border)"><?= h($registro['dados_novos']) ?></pre></div><?php endif; ?>
                                </div>
                            </details>
                        <?php else: ?>
                            <span class="muted">Sem detalhes</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mobile-cards">
        <?php foreach ($registros as $registro): ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($registro['usuario_nome'] ?: 'Sistema') ?></strong><span class="badge info"><?= h(ucfirst(str_replace('_', ' ', $registro['acao']))) ?></span></div>
                <p><?= datetime_br($registro['created_at']) ?></p>
                <p><?= h($registro['entidade']) ?><?= $registro['entidade_id'] ? ' #' . (int) $registro['entidade_id'] : '' ?></p>
                <p>IP: <?= h($registro['ip'] ?: '-') ?></p>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
