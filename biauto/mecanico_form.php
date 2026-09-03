<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!pode_alterar('mecanicos')) {
    flash('warning', 'Seu usuário possui acesso somente para consulta de mecânicos.');
    redirect('mecanicos.php');
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$mecanico = null;

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM mecanicos WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $mecanico = $stmt->fetch();

    if (!$mecanico) {
        flash('danger', 'Mecânico não encontrado.');
        redirect('mecanicos.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $nome = trim((string) ($_POST['nome'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $cpf = only_digits($_POST['cpf'] ?? '');
    $comissao = decimal_value($_POST['comissao_percentual'] ?? 0);

    if ($nome === '') {
        flash('danger', 'Informe o nome do mecânico.');
        redirect('mecanico_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Informe um e-mail válido.');
        redirect('mecanico_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    if ($comissao < 0 || $comissao > 100) {
        flash('danger', 'A comissão deve ficar entre 0 e 100%.');
        redirect('mecanico_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    $dados = [
        'nome' => $nome,
        'cpf' => $cpf === '' ? null : $cpf,
        'telefone' => nullable_string($_POST['telefone'] ?? null),
        'email' => $email === '' ? null : $email,
        'especialidades' => nullable_string($_POST['especialidades'] ?? null),
        'comissao_percentual' => $comissao,
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
    ];

    try {
        if ($id > 0) {
            $antes = $mecanico;
            $stmt = $pdo->prepare('UPDATE mecanicos SET nome = ?, cpf = ?, telefone = ?, email = ?, especialidades = ?, comissao_percentual = ?, ativo = ? WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([$dados['nome'], $dados['cpf'], $dados['telefone'], $dados['email'], $dados['especialidades'], $dados['comissao_percentual'], $dados['ativo'], $id]);
            audit($pdo, 'mecanicos', $id, 'atualizar', $antes, $dados);
            flash('success', 'Mecânico atualizado com sucesso.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO mecanicos (nome, cpf, telefone, email, especialidades, comissao_percentual, ativo) VALUES (?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$dados['nome'], $dados['cpf'], $dados['telefone'], $dados['email'], $dados['especialidades'], $dados['comissao_percentual'], $dados['ativo']]);
            $id = (int) $pdo->lastInsertId();
            audit($pdo, 'mecanicos', $id, 'criar', null, $dados);
            flash('success', 'Mecânico cadastrado com sucesso.');
        }

        redirect('mecanicos.php');
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            flash('danger', 'CPF já cadastrado para outro mecânico.');
        } else {
            flash('danger', 'Não foi possível salvar o mecânico.');
        }

        redirect('mecanico_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }
}

$pageTitle = $id > 0 ? 'Editar Mecânico' : 'Novo Mecânico';
$currentPage = 'mecanicos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header($id > 0 ? 'Editar mecânico' : 'Novo mecânico', $id > 0 ? 'Atualize os dados do profissional selecionado.' : 'Cadastre um profissional da equipe técnica.', [
    ['label' => 'Voltar', 'href' => 'mecanicos.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="card section-card">
        <div class="section-title"><div><h2>Dados do mecânico</h2><p>Informações de contato, especialidades e comissão.</p></div></div>
        <div class="form-row">
            <div class="form-group"><label>Nome</label><input class="input" name="nome" maxlength="160" required value="<?= h($mecanico['nome'] ?? '') ?>"></div>
            <div class="form-group"><label>CPF</label><input class="input" name="cpf" maxlength="14" value="<?= h($mecanico['cpf'] ?? '') ?>"></div>
            <div class="form-group"><label>Telefone</label><input class="input" name="telefone" maxlength="30" value="<?= h($mecanico['telefone'] ?? '') ?>"></div>
            <div class="form-group"><label>E-mail</label><input class="input" type="email" name="email" maxlength="190" value="<?= h($mecanico['email'] ?? '') ?>"></div>
            <div class="form-group"><label>Especialidades</label><input class="input" name="especialidades" maxlength="500" placeholder="Ex.: Motor, suspensão, freios" value="<?= h($mecanico['especialidades'] ?? '') ?>"></div>
            <div class="form-group"><label>Comissão (%)</label><input class="input" name="comissao_percentual" inputmode="decimal" value="<?= h(isset($mecanico['comissao_percentual']) ? number_format((float) $mecanico['comissao_percentual'], 2, ',', '.') : '0,00') ?>"></div>
            <div class="form-group"><label><input type="checkbox" name="ativo" value="1" <?= !isset($mecanico['ativo']) || (int) $mecanico['ativo'] === 1 ? 'checked' : '' ?>> Mecânico ativo</label></div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= $id > 0 ? 'Salvar alterações' : 'Cadastrar mecânico' ?></button>
        <a class="btn" href="mecanicos.php">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
