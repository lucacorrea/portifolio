<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = isset($_POST['acao']) ? (string) $_POST['acao'] : '';

    if ($acao === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $tipo = ($_POST['tipo'] ?? 'PF') === 'PJ' ? 'PJ' : 'PF';
        $nome = trim((string) ($_POST['nome_razao'] ?? ''));
        $cpfCnpj = only_digits($_POST['cpf_cnpj'] ?? '');
        $email = trim((string) ($_POST['email'] ?? ''));

        if ($nome === '') {
            flash('danger', 'Informe o nome ou razão social.');
            redirect('clientes.php' . ($id > 0 ? '?editar=' . $id : ''));
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Informe um e-mail válido.');
            redirect('clientes.php' . ($id > 0 ? '?editar=' . $id : ''));
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
                $antesStmt = $pdo->prepare('SELECT * FROM clientes WHERE id = ? AND deleted_at IS NULL');
                $antesStmt->execute([$id]);
                $antes = $antesStmt->fetch();

                if (!$antes) {
                    flash('danger', 'Cliente não encontrado.');
                    redirect('clientes.php');
                }

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
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                flash('danger', 'CPF ou CNPJ já cadastrado.');
            } else {
                flash('danger', 'Não foi possível salvar o cliente.');
            }
        }

        redirect('clientes.php');
    }

    if ($acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);
        $stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);
        $antes = $stmt->fetch();

        if ($antes) {
            $pdo->prepare('UPDATE clientes SET ativo = 0, deleted_at = NOW() WHERE id = ?')->execute([$id]);
            audit($pdo, 'clientes', $id, 'excluir', $antes, null);
            flash('success', 'Cliente removido com sucesso.');
        }

        redirect('clientes.php');
    }
}

$editar = null;
$editarId = (int) ($_GET['editar'] ?? 0);
if ($editarId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$editarId]);
    $editar = $stmt->fetch() ?: null;
}

$q = trim((string) ($_GET['q'] ?? ''));
$sql = 'SELECT c.*, (SELECT COUNT(*) FROM veiculos v WHERE v.cliente_id = c.id AND v.deleted_at IS NULL) AS total_veiculos, (SELECT MAX(o.data_entrada) FROM ordens_servico o WHERE o.cliente_id = c.id) AS ultimo_servico FROM clientes c WHERE c.deleted_at IS NULL';
$params = [];

if ($q !== '') {
    $sql .= ' AND (c.nome_razao LIKE ? OR c.cpf_cnpj LIKE ? OR c.telefone LIKE ? OR c.whatsapp LIKE ?)';
    $busca = '%' . $q . '%';
    $params = [$busca, $busca, $busca, $busca];
}

$sql .= ' ORDER BY c.nome_razao ASC LIMIT 300';
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$clientes = $stmt->fetchAll();

$pageTitle = 'Clientes';
$currentPage = 'clientes';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header('Clientes', 'Cadastro e histórico dos clientes atendidos pela oficina.', [
    ['label' => 'Novo cliente', 'href' => 'clientes.php#form-cliente', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="card section-card" id="form-cliente">
    <div class="section-title">
        <div>
            <h2><?= $editar ? 'Editar cliente' : 'Novo cliente' ?></h2>
            <p>Preencha os dados principais do cliente.</p>
        </div>
        <?php if ($editar): ?><a class="btn" href="clientes.php">Cancelar edição</a><?php endif; ?>
    </div>

    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <input type="hidden" name="acao" value="salvar">
        <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">

        <div class="form-row">
            <div class="form-group">
                <label>Tipo</label>
                <select class="select" name="tipo">
                    <option value="PF" <?= (($editar['tipo'] ?? 'PF') === 'PF') ? 'selected' : '' ?>>Pessoa Física</option>
                    <option value="PJ" <?= (($editar['tipo'] ?? '') === 'PJ') ? 'selected' : '' ?>>Pessoa Jurídica</option>
                </select>
            </div>
            <div class="form-group">
                <label>Nome / Razão social</label>
                <input class="input" name="nome_razao" maxlength="190" required value="<?= h($editar['nome_razao'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>CPF / CNPJ</label>
                <input class="input" name="cpf_cnpj" maxlength="20" value="<?= h($editar['cpf_cnpj'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>RG / Inscrição estadual</label>
                <input class="input" name="rg_ie" maxlength="30" value="<?= h($editar['rg_ie'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Telefone</label>
                <input class="input" name="telefone" maxlength="30" value="<?= h($editar['telefone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>WhatsApp</label>
                <input class="input" name="whatsapp" maxlength="30" value="<?= h($editar['whatsapp'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>E-mail</label>
                <input class="input" type="email" name="email" maxlength="190" value="<?= h($editar['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>CEP</label>
                <input class="input" name="cep" maxlength="10" value="<?= h($editar['cep'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Logradouro</label>
                <input class="input" name="logradouro" maxlength="190" value="<?= h($editar['logradouro'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Número</label>
                <input class="input" name="numero" maxlength="30" value="<?= h($editar['numero'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Complemento</label>
                <input class="input" name="complemento" maxlength="120" value="<?= h($editar['complemento'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Bairro</label>
                <input class="input" name="bairro" maxlength="120" value="<?= h($editar['bairro'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Cidade</label>
                <input class="input" name="cidade" maxlength="120" value="<?= h($editar['cidade'] ?? 'Coari') ?>">
            </div>
            <div class="form-group">
                <label>UF</label>
                <input class="input" name="uf" maxlength="2" value="<?= h($editar['uf'] ?? 'AM') ?>">
            </div>
            <div class="form-group" style="grid-column:1/-1">
                <label>Observações</label>
                <textarea class="input" name="observacoes"><?= h($editar['observacoes'] ?? '') ?></textarea>
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="ativo" value="1" <?= !isset($editar['ativo']) || (int) $editar['ativo'] === 1 ? 'checked' : '' ?>> Cliente ativo</label>
            </div>
        </div>

        <div class="actions" style="margin-top:16px">
            <button class="btn btn-primary" type="submit"><?= $editar ? 'Salvar alterações' : 'Cadastrar cliente' ?></button>
        </div>
    </form>
</div>

<div class="card section-card">
    <form class="filters" method="get">
        <div class="filter-grow">
            <input class="input" name="q" value="<?= h($q) ?>" placeholder="Pesquisar por nome, CPF/CNPJ ou telefone">
        </div>
        <button class="btn" type="submit">Pesquisar</button>
        <?php if ($q !== ''): ?><a class="btn" href="clientes.php">Limpar</a><?php endif; ?>
    </form>

    <div class="table-shell table-desktop">
        <table class="table">
            <thead><tr><th>Cliente</th><th>CPF/CNPJ</th><th>Telefone</th><th>Veículos</th><th>Último serviço</th><th>Status</th><th>Ações</th></tr></thead>
            <tbody>
            <?php if (!$clientes): ?>
                <tr><td colspan="7" class="muted">Nenhum cliente encontrado.</td></tr>
            <?php endif; ?>
            <?php foreach ($clientes as $cliente): ?>
                <tr>
                    <td><strong><?= h($cliente['nome_razao']) ?></strong></td>
                    <td><?= h($cliente['cpf_cnpj'] ?: '-') ?></td>
                    <td><?= h($cliente['telefone'] ?: $cliente['whatsapp'] ?: '-') ?></td>
                    <td><?= (int) $cliente['total_veiculos'] ?></td>
                    <td><?= date_br($cliente['ultimo_servico']) ?></td>
                    <td><span class="badge <?= (int) $cliente['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $cliente['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></td>
                    <td>
                        <div class="actions">
                            <a class="btn" href="clientes.php?editar=<?= (int) $cliente['id'] ?>#form-cliente">Editar</a>
                            <a class="btn" href="veiculos.php?cliente_id=<?= (int) $cliente['id'] ?>">Veículos</a>
                            <form method="post" onsubmit="return confirm('Remover este cliente?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="acao" value="excluir">
                                <input type="hidden" name="id" value="<?= (int) $cliente['id'] ?>">
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
        <?php foreach ($clientes as $cliente): ?>
            <div class="card mobile-card">
                <div class="mobile-card-top"><strong><?= h($cliente['nome_razao']) ?></strong><span class="badge <?= (int) $cliente['ativo'] === 1 ? 'success' : 'warning' ?>"><?= (int) $cliente['ativo'] === 1 ? 'Ativo' : 'Inativo' ?></span></div>
                <p><?= h($cliente['cpf_cnpj'] ?: 'Sem CPF/CNPJ') ?></p>
                <p><?= h($cliente['telefone'] ?: $cliente['whatsapp'] ?: 'Sem telefone') ?></p>
                <div class="mobile-card-bottom"><span><?= (int) $cliente['total_veiculos'] ?> veículo(s)</span><a class="btn" href="clientes.php?editar=<?= (int) $cliente['id'] ?>#form-cliente">Editar</a></div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
