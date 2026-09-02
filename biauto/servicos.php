<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = isset($_POST['acao']) ? (string) $_POST['acao'] : '';

    if ($acao === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $valorBase = decimal_value($_POST['valor_base'] ?? 0);
        $tempo = ($_POST['tempo_estimado_min'] ?? '') !== '' ? max(0, (int) $_POST['tempo_estimado_min']) : null;

        if ($nome === '') {
            flash('danger', 'Informe o nome do serviço.');
            redirect('servicos.php' . ($id > 0 ? '?editar=' . $id : ''));
        }

        if ($valorBase < 0) {
            flash('danger', 'O valor do serviço não pode ser negativo.');
            redirect('servicos.php' . ($id > 0 ? '?editar=' . $id : ''));
        }

        $dados = [
            'nome' => $nome,
            'categoria' => nullable_string($_POST['categoria'] ?? null),
            'descricao' => nullable_string($_POST['descricao'] ?? null),
            'tempo_estimado_min' => $tempo,
            'valor_base' => $valorBase,
            'ativo' => isset($_POST['ativo']) ? 1 : 0,
        ];

        if ($id > 0) {
            $antesStmt = $pdo->prepare('SELECT * FROM servicos WHERE id = ? AND deleted_at IS NULL');
            $antesStmt->execute([$id]);
            $antes = $antesStmt->fetch();

            if (!$antes) {
                flash('danger', 'Serviço não encontrado.');
                redirect('servicos.php');
            }

            $stmt = $pdo->prepare('UPDATE servicos SET nome = ?, categoria = ?, descricao = ?, tempo_estimado_min = ?, valor_base = ?, ativo = ? WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$dados['nome'], $dados['categoria'], $dados['descricao'], $dados['tempo_estimado_min'], $dados['valor_base'], $dados['ativo'], $id]);
            audit($pdo, 'servicos', $id, 'atualizar', $antes, $dados);
            flash('success', 'Serviço atualizado com sucesso.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO servicos (nome, categoria, descricao, tempo_estimado_min, valor_base, ativo) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$dados['nome'], $dados['categoria'], $dados['descricao'], $dados['tempo_estimado_min'], $dados['valor_base'], $dados['ativo']]);
            $id = (int) $pdo->lastInsertId();
            audit($pdo, 'servicos', $id, 'criar', null, $dados);
            flash('success', 'Serviço cadastrado com sucesso.');
        }

        redirect('servicos.php');
    }

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM servicos WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $antes = $stmt->fetch();

        if ($antes) {
            $pdo->prepare('UPDATE servicos SET ativo = 0, deleted_at = NOW() WHERE id = ?')->execute([$id]);
            audit($pdo, 'servicos', $id, 'excluir', $antes, null);
            flash('success', 'Serviço removido com sucesso.');
        }

        redirect('servicos.php');
    }
}

$editar = null;
$editarId = (int) ($_GET['editar'] ?? 0);
if ($editarId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM servicos WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$editarId]);
    $editar = $stmt->fetch() ?: null;
}

$q = trim((string) ($_GET['q'] ?? ''));
$status = isset($_GET['status']) ? (string) $_GET['status'] : '';
$sql = 'SELECT * FROM servicos WHERE deleted_at IS NULL';
$params = [];

if ($q !== '') {
    $sql .= ' AND (nome LIKE ? OR categoria LIKE ? OR descricao LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca];
}

if ($status === 'ativo') {
    $sql .= ' AND ativo = 1';
} elseif ($status === 'inativo') {
    $sql .= ' AND ativo = 0';
}

$sql .= ' ORDER BY nome ASC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$servicos = $stmt->fetchAll();

$pageTitle = 'Serviços';
$currentPage = 'servicos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header('Serviços', 'Catálogo de serviços com categoria, tempo estimado e preço-base.', [
    ['label' => 'Novo serviço', 'href' => 'servicos.php#form-servico', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="card section-card" id="form-servico">
    <div class="section-title">
        <div>
            <h2><?= $editar ? 'Editar serviço' : 'Novo serviço' ?></h2>
            <p>Defina os dados usados nos orçamentos e ordens de serviço.</p>
        </div>
        <?php if ($editar): ?><a class="btn" href="servicos.php">Cancelar edição</a><?php endif; ?>
    </div>

    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">

        <div class="form-row">
            <div class="form-group">
                <label>Nome</label>
                <input class="input" name="nome" maxlength="160" required value="<?= h($editar['nome'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Categoria</label>
                <input class="input" name="categoria" maxlength="100" value="<?= h($editar['categoria'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Tempo estimado em minutos</label>
                <input class="input" type="number" name="tempo_estimado_min" min="0" value="<?= h(isset($editar['tempo_estimado_min']) ? (string) $editar['tempo_estimado_min'] : '') ?>">
            </div>
            <div class="form-group">
                <label>Valor base</label>
                <input class="input" name="valor_base" inputmode="decimal" required value="<?= h(isset($editar['valor_base']) ? number_format((float) $editar['valor_base'], 2, ',', '.') : '0,00') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>Descrição</label>
                <textarea class="input" name="descricao"><?= h($editar['descricao'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="ativo" value="1" <?= !isset($editar['ativo']) || (int) $editar['ativo'] === 1 ? 'checked' : '' ?>> Serviço ativo</label>
            </div>
        </div>

        <div class="actions" style="margin-top:16px">
            <button class="btn btn-primary" type="submit"><?= $editar ? 'Salvar alterações' : 'Cadastrar serviço' ?></button>
        </div>
    </form>
</div>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow"><input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar serviço, categoria ou descrição"></div>
        <select class="select" name="status">
            <option value="">Todos os status</option>
            <option value="ativo" <?= $status === 'ativo' ? 'selected' : '' ?>>Ativos</option>
            <option value="inativo" <?= $status === 'inativo' ? 'selected' : '' ?>>Inativos</option>
        </select>
        <button class="btn" type="submit">Pesquisar</button>
        <?php if ($q !== '' || $status !== ''): ?><a class="btn" href="servicos.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Serviço</th><th>Categoria</th><th>Tempo estimado</th><th>Valor base</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (!$servicos): ?><tr><td colspan="6" class="muted">Nenhum serviço encontrado.</td></tr><?php endif; ?>
            <?php foreach ($servicos as $servico): ?>
                <tr>
                    <td><strong><?= h($servico['nome']) ?></strong></td>
                    <td><?= h($servico['categoria'] ?: '-') ?></td>
                    <td><?= $servico['tempo_estimado_min'] !== null ? (int) $servico['tempo_estimado_min'] . ' min' : '-' ?></td>
                    <td class="money"><?= money_br($servico['valor_base']) ?></td>
                    <td><span class="badge <?= (int) $servico['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $servico['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
                    <td>
                        <div class="actions">
                            <a class="btn" href="servicos.php?editar=<?= (int) $servico['id'] ?>#form-servico">Editar</a>
                            <form method="post" onsubmit="return confirm('Remover este serviço?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $servico['id'] ?>">
                                <button class="btn btn-danger" type="submit">Excluir</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <div class="mobile-cards">
        <?php foreach ($servicos as $servico): ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($servico['nome']) ?></strong><span class="badge <?= (int) $servico['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $servico['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></div>
                <p><?= h($servico['categoria'] ?: 'Sem categoria') ?></p>
                <div class="mobile-card-bottom"><span class="money"><?= money_br($servico['valor_base']) ?></span><a class="btn" href="servicos.php?editar=<?= (int) $servico['id'] ?>#form-servico">Editar</a></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
