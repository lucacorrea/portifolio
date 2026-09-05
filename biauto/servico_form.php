<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!pode_alterar('servicos')) {
    flash('warning', 'Seu usuário possui acesso somente para consulta de serviços.');
    redirect('servicos.php');
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$servico = null;

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM servicos WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $servico = $stmt->fetch();

    if (!$servico) {
        flash('danger', 'Serviço não encontrado.');
        redirect('servicos.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $nome = trim((string) ($_POST['nome'] ?? ''));
    $valorBase = decimal_value($_POST['valor_base'] ?? 0);
    $tempo = ($_POST['tempo_estimado_min'] ?? '') !== '' ? max(0, (int) $_POST['tempo_estimado_min']) : null;

    if ($nome === '') {
        flash('danger', 'Informe o nome do serviço.');
        redirect('servico_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    if ($valorBase < 0) {
        flash('danger', 'O valor do serviço não pode ser negativo.');
        redirect('servico_form.php' . ($id > 0 ? '?id=' . $id : ''));
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
        $antes = $servico;
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

$pageTitle = $id > 0 ? 'Editar Serviço' : 'Novo Serviço';
$currentPage = 'servicos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header($id > 0 ? 'Editar serviço' : 'Novo serviço', $id > 0 ? 'Atualize os dados do serviço selecionado.' : 'Cadastre um serviço para usar em orçamentos e ordens.', [
    ['label' => 'Voltar', 'href' => 'servicos.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="card section-card">
        <div class="section-title"><div><h2>Dados do serviço</h2><p>Defina categoria, tempo estimado e valor base.</p></div></div>
        <div class="form-row">
            <div class="form-group"><label>Nome</label><input class="input" name="nome" maxlength="160" required value="<?= h($servico['nome'] ?? '') ?>"></div>
            <div class="form-group"><label>Categoria</label><input class="input" name="categoria" maxlength="100" value="<?= h($servico['categoria'] ?? '') ?>"></div>
            <div class="form-group"><label>Tempo estimado em minutos</label><input class="input" type="number" name="tempo_estimado_min" min="0" value="<?= h(isset($servico['tempo_estimado_min']) ? (string) $servico['tempo_estimado_min'] : '') ?>"></div>
            <div class="form-group"><label>Valor base</label><input class="input" name="valor_base" inputmode="decimal" required value="<?= h(isset($servico['valor_base']) ? number_format((float) $servico['valor_base'], 2, ',', '.') : '0,00') ?>"></div>
            <div class="form-group" style="grid-column:1/-1"><label>Descrição</label><textarea class="input" name="descricao"><?= h($servico['descricao'] ?? '') ?></textarea></div>
            <div class="form-group"><label><input type="checkbox" name="ativo" value="1" <?= !isset($servico['ativo']) || (int) $servico['ativo'] === 1 ? 'checked' : '' ?>> Serviço ativo</label></div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= $id > 0 ? 'Salvar alterações' : 'Cadastrar serviço' ?></button>
        <a class="btn" href="servicos.php">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
