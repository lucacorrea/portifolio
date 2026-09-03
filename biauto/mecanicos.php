<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = (string) ($_POST['acao'] ?? '');

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM mecanicos WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $antes = $stmt->fetch();

        if ($antes) {
            $pdo->prepare('UPDATE mecanicos SET ativo = 0, deleted_at = NOW() WHERE id = ?')->execute([$id]);
            audit($pdo, 'mecanicos', $id, 'excluir', $antes, null);
            flash('success', 'Mecânico removido com sucesso.');
        }

        redirect('mecanicos.php');
    }
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = "SELECT m.*, (SELECT COUNT(*) FROM ordens_servico o WHERE o.mecanico_responsavel_id = m.id AND o.status NOT IN ('finalizada','cancelada')) AS os_andamento, (SELECT COUNT(*) FROM ordem_servico_servicos s WHERE s.mecanico_id = m.id AND s.concluido_em >= DATE_FORMAT(CURDATE(), '%Y-%m-01')) AS servicos_mes FROM mecanicos m WHERE m.deleted_at IS NULL";
$params = [];

if ($q !== '') {
    $sql .= ' AND (m.nome LIKE ? OR m.cpf LIKE ? OR m.telefone LIKE ? OR m.especialidades LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca, $busca];
}

$sql .= ' ORDER BY m.nome ASC LIMIT 200';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$mecanicos = $stmt->fetchAll();

$acoes = [];
if (pode_alterar('mecanicos')) {
    $acoes[] = ['label' => 'Novo mecânico', 'href' => 'mecanico_form.php', 'icon' => 'plus', 'class' => 'btn-primary'];
}

$pageTitle = 'Mecânicos';
$currentPage = 'mecanicos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header('Mecânicos', 'Equipe técnica, especialidades e produtividade.', $acoes) ?>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar por nome, CPF, telefone ou especialidade"></div>
        <button class="btn" type="submit">Pesquisar</button>
        <?php if ($q !== ''): ?><a class="btn" href="mecanicos.php">Limpar</a><?php endif; ?>
    </form>

    <div class="grid stats" style="grid-template-columns:repeat(3,minmax(0,1fr));margin-bottom:0">
        <?php if (!$mecanicos): ?><div class="card section-card"><span class="muted">Nenhum mecânico encontrado.</span></div><?php endif; ?>
        <?php foreach ($mecanicos as $mecanico): ?>
            <div class="card section-card">
                <div class="section-title">
                    <h2><?= h($mecanico['nome']) ?></h2>
                    <span class="badge <?= (int) $mecanico['ativo'] === 1 ? 'success' : 'warning' ?>"><span class="dot"></span><?= (int) $mecanico['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span>
                </div>
                <p class="muted"><?= h($mecanico['especialidades'] ?: 'Sem especialidades informadas') ?></p>
                <div class="operation-list">
                    <div class="operation-item"><div class="operation-copy"><strong>OS em andamento</strong><span>No momento</span></div><div class="operation-value"><?= (int) $mecanico['os_andamento'] ?></div></div>
                    <div class="operation-item"><div class="operation-copy"><strong>Serviços no mês</strong><span>Concluídos</span></div><div class="operation-value"><?= (int) $mecanico['servicos_mes'] ?></div></div>
                    <div class="operation-item"><div class="operation-copy"><strong>Comissão</strong><span>Percentual cadastrado</span></div><div class="operation-value"><?= number_format((float) $mecanico['comissao_percentual'], 2, ',', '.') ?>%</div></div>
                </div>
                <?php if (pode_alterar('mecanicos')): ?>
                    <div class="actions" style="margin-top:14px">
                        <a class="btn" href="mecanico_form.php?id=<?= (int) $mecanico['id'] ?>">Editar</a>
                        <form method="post" onsubmit="return confirm('Remover este mecânico?')">
                            <?= csrf_field() ?>
                            <input type="hidden" name="acao" value="excluir">
                            <input type="hidden" name="id" value="<?= (int) $mecanico['id'] ?>">
                            <button class="btn btn-danger" type="submit">Excluir</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
