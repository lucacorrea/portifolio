<?php

declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$totalUsuarios = (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE deleted_at IS NULL')->fetchColumn();
$primeiroCadastro = $totalUsuarios === 0;

if (!$primeiroCadastro && !usuario_admin()) {
    flash('warning', 'Somente o administrador pode cadastrar usuários.');
    redirect(usuario_logado() ? 'index.php' : 'login.php');
}

$erro = '';
$editar = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
    $acao = (string) ($_POST['acao'] ?? 'salvar');

    if ($acao === 'salvar') {
        $id = (int) ($_POST['id'] ?? 0);
        $nome = trim((string) ($_POST['nome'] ?? ''));
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $senha = (string) ($_POST['senha'] ?? '');
        $confirmarSenha = (string) ($_POST['confirmar_senha'] ?? '');
        $nivel = $primeiroCadastro ? 'admin' : (string) ($_POST['nivel'] ?? 'atendente');
        $niveis = ['admin', 'gerente', 'atendente', 'mecanico', 'leitor'];

        if ($nome === '') {
            $erro = 'Informe o nome do usuário.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $erro = 'Informe um e-mail válido.';
        } elseif (!in_array($nivel, $niveis, true)) {
            $erro = 'Nível de acesso inválido.';
        } elseif (($id === 0 || $primeiroCadastro) && strlen($senha) < 8) {
            $erro = 'A senha precisa ter pelo menos 8 caracteres.';
        } elseif ($senha !== '' && strlen($senha) < 8) {
            $erro = 'A senha precisa ter pelo menos 8 caracteres.';
        } elseif ($senha !== $confirmarSenha) {
            $erro = 'As senhas informadas são diferentes.';
        } elseif (!$primeiroCadastro && $id === (int) ($_SESSION['usuario_id'] ?? 0) && $nivel !== 'admin') {
            $erro = 'Você não pode remover seu próprio nível de administrador.';
        } else {
            $stmt = $pdo->prepare('SELECT id FROM usuarios WHERE email = ? AND deleted_at IS NULL AND id <> ? LIMIT 1');
            $stmt->execute([$email, $id]);

            if ($stmt->fetch()) {
                $erro = 'Já existe um usuário com este e-mail.';
            } elseif ($id > 0 && !$primeiroCadastro) {
                $stmt = $pdo->prepare('SELECT id, nome, email, nivel, ativo FROM usuarios WHERE id = ? AND deleted_at IS NULL LIMIT 1');
                $stmt->execute([$id]);
                $antes = $stmt->fetch();

                if (!$antes) {
                    $erro = 'Usuário não encontrado.';
                } else {
                    if ($senha !== '') {
                        $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                        $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, nivel = ?, senha_hash = ?, senha_alterada_em = NOW() WHERE id = ?');
                        $stmt->execute([$nome, $email, $nivel, $senhaHash, $id]);
                    } else {
                        $stmt = $pdo->prepare('UPDATE usuarios SET nome = ?, email = ?, nivel = ? WHERE id = ?');
                        $stmt->execute([$nome, $email, $nivel, $id]);
                    }

                    audit($pdo, 'usuarios', $id, 'editar', $antes, ['nome' => $nome, 'email' => $email, 'nivel' => $nivel]);
                    flash('success', 'Usuário atualizado com sucesso.');
                    redirect('cadastro.php');
                }
            } else {
                $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO usuarios (nome, email, senha_hash, nivel, ativo, senha_alterada_em) VALUES (?, ?, ?, ?, 1, NOW())');
                $stmt->execute([$nome, $email, $senhaHash, $nivel]);
                $usuarioId = (int) $pdo->lastInsertId();

                if ($primeiroCadastro) {
                    iniciar_sessao_usuario([
                        'id' => $usuarioId,
                        'nome' => $nome,
                        'email' => $email,
                        'nivel' => 'admin',
                    ]);
                    audit($pdo, 'usuarios', $usuarioId, 'cadastro_inicial');
                    redirect('index.php');
                }

                audit($pdo, 'usuarios', $usuarioId, 'cadastro');
                flash('success', 'Usuário cadastrado com sucesso.');
                redirect('cadastro.php');
            }
        }
    }

    if (!$primeiroCadastro && $acao === 'status') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === (int) ($_SESSION['usuario_id'] ?? 0)) {
            flash('warning', 'Você não pode desativar seu próprio usuário.');
            redirect('cadastro.php');
        }

        $stmt = $pdo->prepare('SELECT id, ativo FROM usuarios WHERE id = ? AND deleted_at IS NULL LIMIT 1');
        $stmt->execute([$id]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $novoStatus = (int) $usuario['ativo'] === 1 ? 0 : 1;
            $stmt = $pdo->prepare('UPDATE usuarios SET ativo = ? WHERE id = ?');
            $stmt->execute([$novoStatus, $id]);
            audit($pdo, 'usuarios', $id, $novoStatus === 1 ? 'ativar' : 'desativar');
            flash('success', $novoStatus === 1 ? 'Usuário ativado.' : 'Usuário desativado.');
        }

        redirect('cadastro.php');
    }

    if (!$primeiroCadastro && $acao === 'excluir') {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id === (int) ($_SESSION['usuario_id'] ?? 0)) {
            flash('warning', 'Você não pode excluir seu próprio usuário.');
            redirect('cadastro.php');
        }

        $stmt = $pdo->prepare('UPDATE usuarios SET deleted_at = NOW(), ativo = 0 WHERE id = ? AND deleted_at IS NULL');
        $stmt->execute([$id]);

        if ($stmt->rowCount() > 0) {
            audit($pdo, 'usuarios', $id, 'excluir');
            flash('success', 'Usuário removido do sistema.');
        }

        redirect('cadastro.php');
    }
}

if (!$primeiroCadastro && isset($_GET['editar'])) {
    $idEditar = (int) $_GET['editar'];
    $stmt = $pdo->prepare('SELECT id, nome, email, nivel, ativo FROM usuarios WHERE id = ? AND deleted_at IS NULL LIMIT 1');
    $stmt->execute([$idEditar]);
    $editar = $stmt->fetch() ?: null;
}

if ($primeiroCadastro):
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Primeiro cadastro • Bianka Oficina</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/login.css?v=2">
</head>
<body>
<div class="auth-page">
    <section class="auth-cover">
        <div class="auth-brand">
            <div class="auth-brand-mark">B</div>
            <div>
                <strong>Bianka</strong>
                <span>Oficina Mecânica</span>
            </div>
        </div>
        <div class="auth-cover-content">
            <h1>Crie o primeiro acesso.</h1>
            <p>A primeira conta criada será a administradora do sistema.</p>
        </div>
        <div class="auth-cover-footer">BIAUTO • Sistema acadêmico</div>
    </section>

    <main class="auth-main">
        <div class="auth-box">
            <h2>Primeiro cadastro</h2>
            <p class="auth-subtitle">Preencha os dados para criar a conta administradora.</p>

            <?php if ($erro !== ''): ?>
                <div class="auth-alert danger"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></div>
            <?php endif; ?>

            <form method="post" autocomplete="off">
                <?= csrf_field() ?>
                <input type="hidden" name="acao" value="salvar">

                <div class="form-group">
                    <label for="nome">Nome</label>
                    <input class="input" id="nome" name="nome" required maxlength="160" value="<?= htmlspecialchars((string) ($_POST['nome'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input class="input" id="email" name="email" type="email" required maxlength="190" value="<?= htmlspecialchars((string) ($_POST['email'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                </div>

                <div class="form-row">
                    <div class="form-group">
                        <label for="senha">Senha</label>
                        <input class="input" id="senha" name="senha" type="password" required minlength="8">
                    </div>
                    <div class="form-group">
                        <label for="confirmar_senha">Confirmar senha</label>
                        <input class="input" id="confirmar_senha" name="confirmar_senha" type="password" required minlength="8">
                    </div>
                </div>

                <div class="password-help">A senha deve ter pelo menos 8 caracteres.</div>
                <div style="height:16px"></div>
                <button class="btn btn-primary" type="submit">Criar administrador</button>
            </form>

            <div class="auth-links"><a href="login.php">Voltar para o login</a></div>
        </div>
    </main>
</div>
</body>
</html>
<?php
exit;
endif;

$usuarios = $pdo->query('SELECT id, nome, email, nivel, ativo, ultimo_login_em, created_at FROM usuarios WHERE deleted_at IS NULL ORDER BY nome')->fetchAll();
$pageTitle = 'Usuários';
$currentPage = 'usuarios';
require __DIR__ . '/includes/header.php';
require __DIR__ . '/includes/sidebar.php';
?>

<?= render_flash() ?>
<?= page_header('Usuários', 'Cadastre e controle quem pode acessar o sistema.') ?>

<div class="two-col">
    <div class="card section-card">
        <div class="section-title">
            <div>
                <h2><?= $editar ? 'Editar usuário' : 'Novo usuário' ?></h2>
                <p><?= $editar ? 'Altere os dados do usuário selecionado.' : 'Cadastre uma nova pessoa para acessar o sistema.' ?></p>
            </div>
        </div>

        <?php if ($erro !== ''): ?>
            <div style="margin-bottom:16px"><span class="badge danger"><?= h($erro) ?></span></div>
        <?php endif; ?>

        <form method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="acao" value="salvar">
            <input type="hidden" name="id" value="<?= (int) ($editar['id'] ?? 0) ?>">

            <div class="form-row">
                <div class="form-group">
                    <label for="nome">Nome</label>
                    <input class="input" id="nome" name="nome" required maxlength="160" value="<?= h((string) ($_POST['nome'] ?? $editar['nome'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="email">E-mail</label>
                    <input class="input" id="email" name="email" type="email" required maxlength="190" value="<?= h((string) ($_POST['email'] ?? $editar['email'] ?? '')) ?>">
                </div>

                <div class="form-group">
                    <label for="nivel">Nível de acesso</label>
                    <?php $nivelSelecionado = (string) ($_POST['nivel'] ?? $editar['nivel'] ?? 'atendente'); ?>
                    <select class="select" id="nivel" name="nivel" required>
                        <option value="atendente" <?= $nivelSelecionado === 'atendente' ? 'selected' : '' ?>>Atendente</option>
                        <option value="mecanico" <?= $nivelSelecionado === 'mecanico' ? 'selected' : '' ?>>Mecânico</option>
                        <option value="gerente" <?= $nivelSelecionado === 'gerente' ? 'selected' : '' ?>>Gerente</option>
                        <option value="leitor" <?= $nivelSelecionado === 'leitor' ? 'selected' : '' ?>>Leitor</option>
                        <option value="admin" <?= $nivelSelecionado === 'admin' ? 'selected' : '' ?>>Administrador</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="senha">Senha <?= $editar ? '(deixe vazia para manter)' : '' ?></label>
                    <input class="input" id="senha" name="senha" type="password" <?= $editar ? '' : 'required' ?> minlength="8">
                </div>

                <div class="form-group">
                    <label for="confirmar_senha">Confirmar senha</label>
                    <input class="input" id="confirmar_senha" name="confirmar_senha" type="password" <?= $editar ? '' : 'required' ?> minlength="8">
                </div>
            </div>

            <div style="display:flex;gap:10px;margin-top:16px">
                <button class="btn btn-primary" type="submit"><?= $editar ? 'Salvar alterações' : 'Cadastrar usuário' ?></button>
                <?php if ($editar): ?>
                    <a class="btn" href="cadastro.php">Cancelar</a>
                <?php endif; ?>
            </div>
        </form>
    </div>

    <div class="card section-card">
        <div class="section-title">
            <div>
                <h2>Acessos cadastrados</h2>
                <p><?= count($usuarios) ?> usuário(s)</p>
            </div>
        </div>

        <div class="table-shell">
            <table class="table" style="min-width:760px">
                <thead>
                    <tr>
                        <th>Usuário</th>
                        <th>Nível</th>
                        <th>Status</th>
                        <th>Último acesso</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($usuarios as $usuario): ?>
                    <tr>
                        <td>
                            <strong><?= h($usuario['nome']) ?></strong><br>
                            <span class="muted"><?= h($usuario['email']) ?></span>
                        </td>
                        <td><?= h(ucfirst($usuario['nivel'])) ?></td>
                        <td>
                            <span class="badge <?= (int) $usuario['ativo'] === 1 ? 'success' : 'warning' ?>">
                                <?= (int) $usuario['ativo'] === 1 ? 'Ativo' : 'Inativo' ?>
                            </span>
                        </td>
                        <td><?= datetime_br($usuario['ultimo_login_em']) ?></td>
                        <td>
                            <div style="display:flex;gap:6px;flex-wrap:wrap">
                                <a class="btn" href="cadastro.php?editar=<?= (int) $usuario['id'] ?>">Editar</a>
                                <?php if ((int) $usuario['id'] !== (int) $_SESSION['usuario_id']): ?>
                                    <form method="post" style="margin:0">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="acao" value="status">
                                        <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                                        <button class="btn" type="submit"><?= (int) $usuario['ativo'] === 1 ? 'Desativar' : 'Ativar' ?></button>
                                    </form>
                                    <form method="post" style="margin:0" onsubmit="return confirm('Remover este usuário?')">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="acao" value="excluir">
                                        <input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>">
                                        <button class="btn btn-danger" type="submit">Excluir</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
