<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if (!pode_alterar('clientes')) {
    flash('warning', 'Seu usuário possui acesso somente para consulta de clientes.');
    redirect('clientes.php');
}

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$cliente = null;

if ($id > 0) {
    $stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$id]);
    $cliente = $stmt->fetch();

    if (!$cliente) {
        flash('danger', 'Cliente não encontrado.');
        redirect('clientes.php');
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();

    $tipo = ($_POST['tipo'] ?? 'PF') === 'PJ' ? 'PJ' : 'PF';
    $nome = trim((string) ($_POST['nome_razao'] ?? ''));
    $cpfCnpj = only_digits($_POST['cpf_cnpj'] ?? '');
    $email = trim((string) ($_POST['email'] ?? ''));

    if ($nome === '') {
        flash('danger', 'Informe o nome ou razão social.');
        redirect('cliente_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Informe um e-mail válido.');
        redirect('cliente_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }

    $dados = [
        'tipo' => $tipo,
        'nome_razao' => $nome,
        'cpf_cnpj' => $cpfCnpj === '' ? null : $cpfCnpj,
        'rg_ie' => nullable_string($_POST['rg_ie'] ?? null),
        'telefone' => nullable_string($_POST['telefone'] ?? null),
        'whatsapp' => nullable_string($_POST['whatsapp'] ?? null),
        'email' => $email === '' ? null : $email,
        'cep' => nullable_string($_POST['cep'] ?? null),
        'logradouro' => nullable_string($_POST['logradouro'] ?? null),
        'numero' => nullable_string($_POST['numero'] ?? null),
        'complemento' => nullable_string($_POST['complemento'] ?? null),
        'bairro' => nullable_string($_POST['bairro'] ?? null),
        'cidade' => nullable_string($_POST['cidade'] ?? null),
        'uf' => strtoupper(substr(trim((string) ($_POST['uf'] ?? '')), 0, 2)) ?: null,
        'observacoes' => nullable_string($_POST['observacoes'] ?? null),
        'ativo' => isset($_POST['ativo']) ? 1 : 0,
    ];

    try {
        if ($id > 0) {
            $antes = $cliente;
            $stmt = $pdo->prepare('UPDATE clientes SET tipo = ?, nome_razao = ?, cpf_cnpj = ?, rg_ie = ?, telefone = ?, whatsapp = ?, email = ?, cep = ?, logradouro = ?, numero = ?, complemento = ?, bairro = ?, cidade = ?, uf = ?, observacoes = ?, ativo = ? WHERE id = ? AND deleted_at IS NULL');
            $stmt->execute([
                $dados['tipo'], $dados['nome_razao'], $dados['cpf_cnpj'], $dados['rg_ie'], $dados['telefone'], $dados['whatsapp'], $dados['email'], $dados['cep'], $dados['logradouro'], $dados['numero'], $dados['complemento'], $dados['bairro'], $dados['cidade'], $dados['uf'], $dados['observacoes'], $dados['ativo'], $id,
            ]);
            audit($pdo, 'clientes', $id, 'atualizar', $antes, $dados);
            flash('success', 'Cliente atualizado com sucesso.');
        } else {
            $stmt = $pdo->prepare('INSERT INTO clientes (tipo, nome_razao, cpf_cnpj, rg_ie, telefone, whatsapp, email, cep, logradouro, numero, complemento, bairro, cidade, uf, observacoes, ativo) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $dados['tipo'], $dados['nome_razao'], $dados['cpf_cnpj'], $dados['rg_ie'], $dados['telefone'], $dados['whatsapp'], $dados['email'], $dados['cep'], $dados['logradouro'], $dados['numero'], $dados['complemento'], $dados['bairro'], $dados['cidade'], $dados['uf'], $dados['observacoes'], $dados['ativo'],
            ]);
            $id = (int) $pdo->lastInsertId();
            audit($pdo, 'clientes', $id, 'criar', null, $dados);
            flash('success', 'Cliente cadastrado com sucesso.');
        }

        redirect('clientes.php');
    } catch (PDOException $e) {
        if ((string) $e->getCode() === '23000') {
            flash('danger', 'CPF ou CNPJ já cadastrado.');
        } else {
            flash('danger', 'Não foi possível salvar o cliente.');
        }

        redirect('cliente_form.php' . ($id > 0 ? '?id=' . $id : ''));
    }
}

$pageTitle = $id > 0 ? 'Editar Cliente' : 'Novo Cliente';
$currentPage = 'clientes';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header($id > 0 ? 'Editar cliente' : 'Novo cliente', $id > 0 ? 'Atualize os dados do cliente selecionado.' : 'Cadastre os dados principais do cliente.', [
    ['label' => 'Voltar', 'href' => 'clientes.php', 'icon' => 'chevron', 'class' => 'btn-secondary']
]) ?>

<form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <input type="hidden" name="id" value="<?= $id ?>">

    <div class="card section-card">
        <div class="section-title"><div><h2>Dados principais</h2><p>Informações de identificação e contato.</p></div></div>
        <div class="form-row">
            <div class="form-group">
                <label>Tipo</label>
                <select class="select" name="tipo">
                    <option value="PF" <?= (($cliente['tipo'] ?? 'PF') === 'PF') ? 'selected' : '' ?>>Pessoa Física</option>
                    <option value="PJ" <?= (($cliente['tipo'] ?? '') === 'PJ') ? 'selected' : '' ?>>Pessoa Jurídica</option>
                </select>
            </div>
            <div class="form-group"><label>Nome / Razão social</label><input class="input" name="nome_razao" maxlength="190" required value="<?= h($cliente['nome_razao'] ?? '') ?>"></div>
            <div class="form-group"><label>CPF / CNPJ</label><input class="input" name="cpf_cnpj" maxlength="20" value="<?= h($cliente['cpf_cnpj'] ?? '') ?>"></div>
            <div class="form-group"><label>RG / Inscrição estadual</label><input class="input" name="rg_ie" maxlength="30" value="<?= h($cliente['rg_ie'] ?? '') ?>"></div>
            <div class="form-group"><label>Telefone</label><input class="input" name="telefone" maxlength="30" value="<?= h($cliente['telefone'] ?? '') ?>"></div>
            <div class="form-group"><label>WhatsApp</label><input class="input" name="whatsapp" maxlength="30" value="<?= h($cliente['whatsapp'] ?? '') ?>"></div>
            <div class="form-group"><label>E-mail</label><input class="input" type="email" name="email" maxlength="190" value="<?= h($cliente['email'] ?? '') ?>"></div>
            <div class="form-group"><label>CEP</label><input class="input" name="cep" maxlength="10" value="<?= h($cliente['cep'] ?? '') ?>"></div>
        </div>
    </div>

    <div class="card section-card">
        <div class="section-title"><div><h2>Endereço</h2><p>Localização do cliente.</p></div></div>
        <div class="form-row">
            <div class="form-group"><label>Logradouro</label><input class="input" name="logradouro" maxlength="190" value="<?= h($cliente['logradouro'] ?? '') ?>"></div>
            <div class="form-group"><label>Número</label><input class="input" name="numero" maxlength="30" value="<?= h($cliente['numero'] ?? '') ?>"></div>
            <div class="form-group"><label>Complemento</label><input class="input" name="complemento" maxlength="120" value="<?= h($cliente['complemento'] ?? '') ?>"></div>
            <div class="form-group"><label>Bairro</label><input class="input" name="bairro" maxlength="120" value="<?= h($cliente['bairro'] ?? '') ?>"></div>
            <div class="form-group"><label>Cidade</label><input class="input" name="cidade" maxlength="120" value="<?= h($cliente['cidade'] ?? 'Coari') ?>"></div>
            <div class="form-group"><label>UF</label><input class="input" name="uf" maxlength="2" value="<?= h($cliente['uf'] ?? 'AM') ?>"></div>
            <div class="form-group" style="grid-column:1/-1"><label>Observações</label><textarea class="input" name="observacoes"><?= h($cliente['observacoes'] ?? '') ?></textarea></div>
            <div class="form-group"><label><input type="checkbox" name="ativo" value="1" <?= !isset($cliente['ativo']) || (int) $cliente['ativo'] === 1 ? 'checked' : '' ?>> Cliente ativo</label></div>
        </div>
    </div>

    <div class="actions">
        <button class="btn btn-primary" type="submit"><?= $id > 0 ? 'Salvar alterações' : 'Cadastrar cliente' ?></button>
        <a class="btn" href="clientes.php">Cancelar</a>
    </div>
</form>

<?php require __DIR__ . '/includes/footer.php'; ?>
