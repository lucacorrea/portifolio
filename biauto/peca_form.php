<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!pode_alterar('pecas')) {
    flash('warning', 'Seu usuário possui acesso somente para consulta de peças.');
    redirect('pecas.php');
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$peca = null;

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM pecas WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $peca = $stmt->fetch();

    if (!$peca) {
        flash('danger', 'Peça não encontrada.');
        redirect('pecas.php');
    }
}

$fornecedores = $pdo->query('SELECT id, nome_razao FROM fornecedores WHERE deleted_at IS NULL AND ativo = 1 ORDER BY nome_razao')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $nome = trim((string) ($_POST['nome'] ?? ''));
    $codigo = strtoupper(trim((string) ($_POST['codigo'] ?? '')));
    $fornecedorId = (int) ($_POST['fornecedor_id'] ?? 0);
    $precoVenda = decimal_value($_POST['preco_venda'] ?? 0);
    $custoMedio = decimal_value($_POST['custo_medio'] ?? 0);
    $estoqueMinimo = max(0, (float) str_replace(',', '.', (string) ($_POST['estoque_minimo'] ?? 0)));

    if ($nome === '') {
        flash('danger', 'Informe o nome da peça.');
        redirect('peca_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    if ($precoVenda < 0 || $custoMedio < 0) {
        flash('danger', 'Os valores de custo e venda não podem ser negativos.');
        redirect('peca_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    if ($fornecedorId > 0) {
        $stmt = $pdo->prepare('SELECT id FROM fornecedores WHERE id = ? AND deleted_at IS NULL AND ativo = 1');
        $stmt->execute([$fornecedorId]);
        if (!$stmt->fetch()) {
            flash('danger', 'Fornecedor inválido.');
            redirect('peca_form.php' . ($id > 0 ? '?id=' . $id : ''));
        }
    }

    $dados = [
        'fornecedor_id' => $fornecedorId > 0 ? $fornecedorId : null,
        'codigo' => $codigo === '' ? null : $codigo,
        'codigo_barras' => nullable_string($_POST['codigo_barras'] ?? null),
        'nome' => $nome,
        'marca' => nullable_string($_POST['marca'] ?? null),
        'unidade' => strtoupper(trim((string) ($_POST['unidade'] ?? 'UN'))) ?: 'UN',
        'estoque_minimo' => $estoqueMinimo,
        'custo_medio' => $custoMedio,
        'preco_venda' => $precoVenda,
        'localizacao' => nullable_string($_POST['localizacao'] ?? null),
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
    ];

    try {
        if ($id > 0) {
            $antes = $peca;
            $stmt = $pdo->prepare('UPDATE pecas SET fornecedor_id = ?, codigo = ?, codigo_barras = ?, nome = ?, marca = ?, unidade = ?, estoque_minimo = ?, custo_medio = ?, preco_venda = ?, localizacao = ?, ativo = ? WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$dados['fornecedor_id'], $dados['codigo'], $dados['codigo_barras'], $dados['nome'], $dados['marca'], $dados['unidade'], $dados['estoque_minimo'], $dados['custo_medio'], $dados['preco_venda'], $dados['localizacao'], $dados['ativo'], $id]);
            audit($pdo, 'pecas', $id, 'atualizar', $antes, $dados);
            flash('success', 'Peça atualizada com sucesso.');
        } else {
            $estoqueInicial = max(0, (float) str_replace(',', '.', (string) ($_POST['estoque_inicial'] ?? 0)));
            $pdo->beginTransaction();
            $stmt = $pdo->prepare('INSERT INTO pecas (fornecedor_id, codigo, codigo_barras, nome, marca, unidade, estoque_atual, estoque_minimo, custo_medio, preco_venda, localizacao, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$dados['fornecedor_id'], $dados['codigo'], $dados['codigo_barras'], $dados['nome'], $dados['marca'], $dados['unidade'], $estoqueInicial, $dados['estoque_minimo'], $dados['custo_medio'], $dados['preco_venda'], $dados['localizacao'], $dados['ativo']]);
            $id = (int) $pdo->lastInsertId();

            if ($estoqueInicial > 0) {
                $mov = $pdo->prepare('INSERT INTO estoque_movimentos (peca_id, tipo, quantidade, custo_unitario, observacao, usuario_id) VALUES (?, ?, ?, ?, ?, ?)');
                $mov->execute([$id, 'entrada', $estoqueInicial, $dados['custo_medio'], 'Estoque inicial', (int) $_SESSION['usuario_id']]);
            }

            $pdo->commit();
            audit($pdo, 'pecas', $id, 'criar', null, $dados);
            flash('success', 'Peça cadastrada com sucesso.');
        }

        redirect('pecas.php');
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        if ((string) $e->getCode() === '23000') {
            flash('danger', 'Código ou código de barras já cadastrado.');
        } else {
            flash('danger', 'Não foi possível salvar a peça.');
        }

        redirect('peca_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }
}

$pageTitle = $id > 0 ? 'Editar Peça' : 'Nova Peça';
$currentPage = 'pecas';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header($id > 0 ? 'Editar peça' : 'Nova peça', $id > 0 ? 'Atualize os dados da peça selecionada.' : 'Cadastre o item e os parâmetros de estoque.', [
    ['label' => 'Fornecedores', 'href' => 'fornecedores.php', 'icon' => 'clients', 'class' => 'btn-secondary'],
    ['label' => 'Voltar', 'href' => 'pecas.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="card section-card">
        <div class="section-title"><div><h2>Identificação</h2><p>Dados básicos da peça e fornecedor.</p></div></div>
        <div class="form-row">
            <div class="form-group"><label>Nome</label><input class="input" name="nome" maxlength="190" required value="<?= h($peca['nome'] ?? '') ?>"></div>
            <div class="form-group"><label>Fornecedor</label><select class="select" name="fornecedor_id"><option value="0">Não informado</option><?php foreach ($fornecedores as $fornecedor): ?><option value="<?= (int) $fornecedor['id'] ?>" <?= (int) ($peca['fornecedor_id'] ?? 0) === (int) $fornecedor['id'] ? 'selected' : '' ?>><?= h($fornecedor['nome_razao']) ?></option><?php endforeach; ?></select></div>
            <div class="form-group"><label>Código</label><input class="input" name="codigo" maxlength="80" value="<?= h($peca['codigo'] ?? '') ?>"></div>
            <div class="form-group"><label>Código de barras</label><input class="input" name="codigo_barras" maxlength="80" value="<?= h($peca['codigo_barras'] ?? '') ?>"></div>
            <div class="form-group"><label>Marca</label><input class="input" name="marca" maxlength="100" value="<?= h($peca['marca'] ?? '') ?>"></div>
            <div class="form-group"><label>Unidade</label><input class="input" name="unidade" maxlength="20" value="<?= h($peca['unidade'] ?? 'UN') ?>"></div>
            <div class="form-group"><label>Localização</label><input class="input" name="localizacao" maxlength="120" value="<?= h($peca['localizacao'] ?? '') ?>"></div>
        </div>
    </div>

    <div class="card section-card">
        <div class="section-title"><div><h2>Estoque e valores</h2><p>Defina estoque mínimo, custo e preço de venda.</p></div></div>
        <div class="form-row">
            <?php if ($id === 0): ?><div class="form-group"><label>Estoque inicial</label><input class="input" name="estoque_inicial" inputmode="decimal" value="0"></div><?php endif; ?>
            <div class="form-group"><label>Estoque mínimo</label><input class="input" name="estoque_minimo" inputmode="decimal" value="<?= h(isset($peca['estoque_minimo']) ? (string) $peca['estoque_minimo'] : '0') ?>"></div>
            <div class="form-group"><label>Custo médio</label><input class="input" name="custo_medio" inputmode="decimal" value="<?= h(isset($peca['custo_medio']) ? number_format((float) $peca['custo_medio'], 2, ',', '.') : '0,00') ?>"></div>
            <div class="form-group"><label>Preço de venda</label><input class="input" name="preco_venda" inputmode="decimal" required value="<?= h(isset($peca['preco_venda']) ? number_format((float) $peca['preco_venda'], 2, ',', '.') : '0,00') ?>"></div>
            <?php if ($id > 0): ?><div class="form-group"><label>Estoque atual</label><input class="input" value="<?= number_format((float) $peca['estoque_atual'], 3, ',', '.') . ' ' . h($peca['unidade']) ?>" disabled></div><?php endif; ?>
            <div class="form-group"><label><input type="checkbox" name="ativo" value="1" <?= !isset($peca['ativo']) || (int) $peca['ativo'] === 1 ? 'checked' : '' ?>> Peça ativa</label></div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= $id > 0 ? 'Salvar alterações' : 'Cadastrar peça' ?></button>
        <a class="btn" href="pecas.php">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
