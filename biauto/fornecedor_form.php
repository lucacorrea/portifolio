<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!pode_alterar('pecas')) {
    flash('warning', 'Seu usuário possui acesso somente para consulta de fornecedores.');
    redirect('fornecedores.php');
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$fornecedor = null;

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM fornecedores WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $fornecedor = $stmt->fetch();

    if (!$fornecedor) {
        flash('danger', 'Fornecedor não encontrado.');
        redirect('fornecedores.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $nome = trim((string) ($_POST['nome_razao'] ?? ''));
    $cpfCnpj = only_digits($_POST['cpf_cnpj'] ?? '');
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($nome === '') {
        flash('danger', 'Informe o nome ou razão social do fornecedor.');
        redirect('fornecedor_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Informe um e-mail válido.');
        redirect('fornecedor_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    $dados = [
        'nome_razao' => $nome,
        'cpf_cnpj' => $cpfCnpj === '' ? null : $cpfCnpj,
        'contato' => nullable_string($_POST['contato'] ?? null),
        'telefone' => nullable_string($_POST['telefone'] ?? null),
        'email' => $email === '' ? null : $email,
        'observacoes' => nullable_string($_POST['observacoes'] ?? null),
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
    ];

    try {
        if ($id > 0) {
            $antes = $fornecedor;
            $stmt = $pdo->prepare('UPDATE fornecedores SET nome_razao = ?, cpf_cnpj = ?, contato = ?, telefone = ?, email = ?, observacoes = ?, ativo = ? WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$dados['nome_razao'], $dados['cpf_cnpj'], $dados['contato'], $dados['telefone'], $dados['email'], $dados['observacoes'], $dados['ativo'], $id]);
            audit($pdo, 'fornecedores', $id, 'atualizar', $antes, $dados);
            flash('success', 'Fornecedor atualizado com sucesso.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO fornecedores (nome_razao, cpf_cnpj, contato, telefone, email, observacoes, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$dados['nome_razao'], $dados['cpf_cnpj'], $dados['contato'], $dados['telefone'], $dados['email'], $dados['observacoes'], $dados['ativo']]);
            $id = (int) $pdo->lastInsertId();
            audit($pdo, 'fornecedores', $id, 'criar', null, $dados);
            flash('success', 'Fornecedor cadastrado com sucesso.');
        }

        redirect('fornecedores.php');
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            flash('danger', 'CPF ou CNPJ já cadastrado para outro fornecedor.');
        } else {
            flash('danger', 'Não foi possível salvar o fornecedor.');
        }

        redirect('fornecedor_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }
}

$pageTitle = $id > 0 ? 'Editar Fornecedor' : 'Novo Fornecedor';
$currentPage = 'fornecedores';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header($id > 0 ? 'Editar fornecedor' : 'Novo fornecedor', $id > 0 ? 'Atualize os dados do fornecedor selecionado.' : 'Cadastre um fornecedor para vincular às peças.', [
    ['label' => 'Voltar', 'href' => 'fornecedores.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="card section-card">
        <div class="section-title"><div><h2>Dados do fornecedor</h2><p>Informações de identificação e contato.</p></div></div>
        <div class="form-row">
            <div class="form-group"><label>Nome / Razão social</label><input class="input" name="nome_razao" maxlength="190" required value="<?= h($fornecedor['nome_razao'] ?? '') ?>"></div>
            <div class="form-group"><label>CPF / CNPJ</label><input class="input" name="cpf_cnpj" maxlength="20" value="<?= h($fornecedor['cpf_cnpj'] ?? '') ?>"></div>
            <div class="form-group"><label>Pessoa de contato</label><input class="input" name="contato" maxlength="160" value="<?= h($fornecedor['contato'] ?? '') ?>"></div>
            <div class="form-group"><label>Telefone</label><input class="input" name="telefone" maxlength="30" value="<?= h($fornecedor['telefone'] ?? '') ?>"></div>
            <div class="form-group"><label>E-mail</label><input class="input" type="email" name="email" maxlength="190" value="<?= h($fornecedor['email'] ?? '') ?>"></div>
            <div class="form-group" style="grid-column:1/-1"><label>Observações</label><textarea class="input" name="observacoes"><?= h($fornecedor['observacoes'] ?? '') ?></textarea></div>
            <div class="form-group"><label><input type="checkbox" name="ativo" value="1" <?= !isset($fornecedor['ativo']) || (int) $fornecedor['ativo'] === 1 ? 'checked' : '' ?>> Fornecedor ativo</label></div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= $id > 0 ? 'Salvar alterações' : 'Cadastrar fornecedor' ?></button>
        <a class="btn" href="fornecedores.php">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
