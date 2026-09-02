<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = isset($_POST['acao']) ? (string) $_POST['acao'] : '';

    if ($acao === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $cpf = only_digits($_POST['cpf'] ?? '');
        $comissao = decimal_value($_POST['comissao_percentual'] ?? 0);

        if ($nome === '') {
            flash('danger', 'Informe o nome do mecânico.');
            redirect('mecanicos.php' . ($id > 0 ? '?editar=' . $id : ''));
        }

        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('danger', 'Informe um e-mail válido.');
            redirect('mecanicos.php' . ($id > 0 ? '?editar=' . $id : ''));
        }

        if ($comissao < 0 || $comissao > 100) {
            flash('danger', 'A comissão deve ficar entre 0 e 100%.');
            redirect('mecanicos.php' . ($id > 0 ? '?editar=' . $id : ''));
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
                $antesStmt = $pdo->prepare('SELECT * FROM mecanicos WHERE id = ? AND deleted_at IS NULL');
                $antesStmt->execute([$id]);
                $antes = $antesStmt->fetch();

                if (!$antes) {
                    flash('danger', 'Mecânico não encontrado.');
                    redirect('mecanicos.php');
                }

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
        } catch (PDOException $e) {
            if ((string) $e->getCode() === '23000') {
                flash('danger', 'CPF já cadastrado para outro mecânico.');
            } else {
                flash('danger', 'Não foi possível salvar o mecânico.');
            }
        }

        redirect('mecanicos.php');
    }

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

$editar = null;
$editarId = (int) ($_GET['editar'] ?? 0);
if ($editarId > 0) {
    $stmt = $pdo->prepare('SELECT * FROM mecanicos WHERE id = ? AND deleted_at IS NULL');
    $stmt->execute([$editarId]);
    $editar = $stmt->fetch() ?: null;
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

$pageTitle = 'Mecânicos';
$currentPage = 'mecanicos';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>

<?= page_header('Mecânicos', 'Equipe técnica, especialidades e produtividade.', [
    ['label' => 'Novo mecânico', 'href' => 'mecanicos.php#form-mecanico', 'icon' => 'plus', 'class' => 'btn-primary']
]) ?>

<div class="card section-card" id="form-mecanico">
    <div class="section-title">
        <div>
            <h2><?= $editar ? 'Editar mecânico' : 'Novo mecânico' ?></h2>
            <p>Cadastre os dados da equipe técnica.</p>
        </div>
        <?php if ($editar): ?><a class="btn" href="mecanicos.php">Cancelar edição</a><?php endif; ?>
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
                <label>CPF</label>
                <input class="input" name="cpf" maxlength="14" value="<?= h($editar['cpf'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Telefone</label>
                <input class="input" name="telefone" maxlength="30" value="<?= h($editar['telefone'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>E-mail</label>
                <input class="input" type="email" name="email" maxlength="190" value="<?= h($editar['email'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Especialidades</label>
                <input class="input" name="especialidades" maxlength="500" placeholder="Ex.: Motor, suspensão, freios" value="<?= h($editar['especialidades'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label>Comissão (%)</label>
                <input class="input" name="comissao_percentual" inputmode="decimal" value="<?= h(isset($editar['comissao_percentual']) ? number_format((float) $editar['comissao_percentual'], 2, ',', '.') : '0,00') ?>">
            </div>
            <div class="form-group">
                <label><input type="checkbox" name="ativo" value="1" <?= !isset($editar['ativo']) || (int) $editar['ativo'] === 1 ? 'checked' : '' ?>> Mecânico ativo</label>
            </div>
        </div>

        <div class="actions" style="margin-top:16px">
            <button class="btn btn-primary" type="submit"><?= $editar ? 'Salvar alterações' : 'Cadastrar mecânico' ?></button>
        </div>
    </form>
</div>

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
                <div class="actions" style="margin-top:14px">
                    <a class="btn" href="mecanicos.php?editar=<?= (int) $mecanico['id'] ?>#form-mecanico">Editar</a>
                    <form method="post" onsubmit="return confirm('Remover este mecânico?')">
                        <?= csrf_field() ?>
                        <input type="hidden" name="acao" value="excluir">
                        <input type="hidden" name="id" value="<?= (int) $mecanico['id'] ?>">
                        <button class="btn btn-danger" type="submit">Excluir</button>
                    </form>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
