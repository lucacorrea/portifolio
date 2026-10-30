<?php
declare(strict_types=1);

function auth_login_form(): void
{
    if (me()) go('/painel');
    view('login');
}

function auth_login(): never
{
    if (me()) go('/painel');
    $email = strtolower(input('email'));
    $password = (string)($_POST['password'] ?? '');
    $lock = (int)($_SESSION['login_lock'] ?? 0);
    if ($lock > time()) fail('Muitas tentativas. Tente novamente em alguns minutos.', '/entrar');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        fail('Informe e-mail e senha válidos.', '/entrar');
    }
    $st = db()->prepare('SELECT id, role, password FROM users WHERE email = ? LIMIT 1');
    $st->execute([$email]);
    $user = $st->fetch();
    if (!$user || !password_verify($password, $user['password'])
        || !in_array($user['role'], ['admin','professor','estudante'], true)) {
        $_SESSION['login_fails'] = 1 + (int)($_SESSION['login_fails'] ?? 0);
        if ($_SESSION['login_fails'] >= 6) {
            $_SESSION['login_lock'] = time() + 300;
            $_SESSION['login_fails'] = 0;
        }
        fail('E-mail ou senha incorretos.', '/entrar');
    }
    unset($_SESSION['login_fails'], $_SESSION['login_lock']);
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$user['id'];
    go('/painel');
}

function auth_register_form(): void
{
    if (me()) go('/painel');
    $instituicoes = db()->query('SELECT id, nome FROM instituicoes ORDER BY nome')->fetchAll();
    view('register', compact('instituicoes'));
}

function auth_register(): never
{
    if (me()) go('/painel');
    $name = input('name');
    $email = strtolower(input('email'));
    $pass = (string)($_POST['password'] ?? '');
    $inst = (int)($_POST['instituicao_id'] ?? 0);
    if ($name === '' || field_len($name) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || field_len($email) > 255 || strlen($pass) < 8 ||
        $pass !== ($_POST['password_confirmation'] ?? null)) {
        fail('Confira nome, e-mail e senha (mínimo 8 caracteres, confirmada).', '/cadastro');
    }
    if ($inst > 0) {
        $st = db()->prepare('SELECT id FROM instituicoes WHERE id = ?');
        $st->execute([$inst]);
        if (!$st->fetchColumn()) fail('Instituição inválida.', '/cadastro');
    }
    $existing = db()->prepare('SELECT id FROM users WHERE email = ?');
    $existing->execute([$email]);
    if ($existing->fetchColumn()) fail('Já existe uma conta com este e-mail.', '/cadastro');

    db()->beginTransaction();
    try {
        $st = db()->prepare("INSERT INTO users (name,email,password,role,created_at,updated_at)
                            VALUES (?, ?, ?, 'estudante', NOW(), NOW())");
        $st->execute([$name, $email, password_hash($pass, PASSWORD_DEFAULT)]);
        $id = (int)db()->lastInsertId();
        $st = db()->prepare('INSERT INTO estudantes (user_id,instituicao_id,created_at,updated_at)
                            VALUES (?, ?, NOW(), NOW())');
        $st->execute([$id, $inst > 0 ? $inst : null]);
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    session_regenerate_id(true);
    $_SESSION['user_id'] = $id;
    flash('Cadastro realizado com sucesso!');
    go('/painel');
}

function auth_logout(): never
{
    $_SESSION = [];
    session_regenerate_id(true);
    flash('Você saiu da sua conta.');
    go('/');
}

function dashboard(): void
{
    $user = need_login();
    if ($user['role'] === 'admin') {
        $stats = [
            'Usuários' => (int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
            'Materiais publicados' => (int)db()->query("SELECT COUNT(*) FROM objetos_aprendizagem WHERE status = 'publicado'")->fetchColumn(),
            'Rascunhos' => (int)db()->query("SELECT COUNT(*) FROM objetos_aprendizagem WHERE status = 'rascunho'")->fetchColumn(),
        ];
    } elseif ($user['role'] === 'professor') {
        $st = db()->prepare('SELECT id FROM professores WHERE user_id = ?');
        $st->execute([$user['id']]);
        $prof = (int)$st->fetchColumn();
        $st = db()->prepare("SELECT COUNT(*) total, SUM(status = 'publicado') publicados
            FROM objetos_aprendizagem WHERE professor_id = ?");
        $st->execute([$prof]);
        $counts = $st->fetch();
        $stats = ['Meus materiais' => (int)$counts['total'], 'Publicados' => (int)$counts['publicados']];
    } else {
        $stats = ['Materiais disponíveis' => (int)db()->query(
            "SELECT COUNT(*) FROM objetos_aprendizagem WHERE status='publicado'")->fetchColumn()];
    }
    view('dashboard', compact('user', 'stats'));
}
