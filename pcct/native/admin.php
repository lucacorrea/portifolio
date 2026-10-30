<?php
declare(strict_types=1);

function admin_index(): void
{
    need_role('admin');
    $users = db()->query("SELECT id,name,email,role,created_at FROM users ORDER BY id DESC LIMIT 50")->fetchAll();
    $instituicoes = db()->query('SELECT id,nome,localidade FROM instituicoes ORDER BY nome')->fetchAll();
    $disciplinas = db()->query('SELECT id,nome,area_conhecimento FROM disciplinas ORDER BY nome')->fetchAll();
    $habilidades = db()->query('SELECT h.codigo,h.nome,d.nome disciplina
        FROM habilidades_bncc h LEFT JOIN disciplinas d ON d.id=h.disciplina_id
        ORDER BY h.codigo LIMIT 100')->fetchAll();
    view('admin', compact('users','instituicoes','disciplinas','habilidades'));
}

function admin_user(): never
{
    need_role('admin');
    $name = input('name');
    $email = strtolower(input('email'));
    $password = (string)($_POST['password'] ?? '');
    $role = input('role');
    $inst = (int)input('instituicao_id');
    $formacao = input('formacao');
    if (!in_array($role, ['professor','estudante'], true) || $name === ''
        || field_len($name) > 255 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || field_len($email) > 255 || strlen($password) < 8
        || $password !== ($_POST['password_confirmation'] ?? null)
        || field_len($formacao) > 150) {
        fail('Confira nome, e-mail, perfil e confirmação da senha (mínimo 8 caracteres).', '/admin');
    }
    $st = db()->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$email]);
    if ($st->fetchColumn()) fail('E-mail já cadastrado.', '/admin');
    if ($inst) {
        $st = db()->prepare('SELECT id FROM instituicoes WHERE id=?');
        $st->execute([$inst]);
        if (!$st->fetchColumn()) fail('Instituição inválida.', '/admin');
    }

    db()->beginTransaction();
    try {
        $st = db()->prepare('INSERT INTO users(name,email,password,role,created_at,updated_at)
                             VALUES (?, ?, ?, ?, NOW(), NOW())');
        $st->execute([$name,$email,password_hash($password, PASSWORD_DEFAULT),$role]);
        $uid = (int)db()->lastInsertId();
        if ($role === 'professor') {
            $st = db()->prepare('INSERT INTO professores(user_id,instituicao_id,formacao,created_at,updated_at)
                                 VALUES(?, ?, ?, NOW(), NOW())');
            $st->execute([$uid,$inst ?: null,$formacao ?: null]);
        } else {
            $st = db()->prepare('INSERT INTO estudantes(user_id,instituicao_id,created_at,updated_at)
                                 VALUES(?, ?, NOW(), NOW())');
            $st->execute([$uid,$inst ?: null]);
        }
        db()->commit();
    } catch (Throwable $e) {
        db()->rollBack();
        throw $e;
    }
    flash('Usuário cadastrado.');
    go('/admin');
}

function admin_institution(): never
{
    need_role('admin');
    $name = input('nome');
    $local = input('localidade');
    $desc = input('descricao');
    if ($name === '' || field_len($name) > 255 || field_len($local) > 255) {
        fail('Informe nome e localidade válidos.', '/admin');
    }
    $st = db()->prepare('INSERT INTO instituicoes(nome,localidade,descricao,created_at,updated_at)
                          VALUES(?, ?, ?, NOW(), NOW())');
    $st->execute([$name, $local ?: null, $desc ?: null]);
    flash('Instituição cadastrada.');
    go('/admin');
}

function admin_subject(): never
{
    need_role('admin');
    $name = input('nome');
    $area = input('area_conhecimento');
    $areas = ['Linguagens','Matemática','Ciências da Natureza','Ciências Humanas','Formação Técnica'];
    if ($name === '' || field_len($name) > 255 || !in_array($area, $areas, true)) {
        fail('Dados da disciplina inválidos.', '/admin');
    }
    $st = db()->prepare('INSERT INTO disciplinas(nome,descricao,area_conhecimento,created_at,updated_at)
                         VALUES(?, ?, ?, NOW(), NOW())');
    $st->execute([$name, input('descricao') ?: null, $area]);
    flash('Disciplina cadastrada.');
    go('/admin');
}

function admin_skill(): never
{
    need_role('admin');
    $code = strtoupper(input('codigo'));
    $name = input('nome');
    $disc = (int)input('disciplina_id');
    $year = input('ano_escolar');
    if (!preg_match('/^EM[0-9A-Z]{6,16}$/', $code) || $name === '' || field_len($name) > 255
        || field_len($year) > 80 || $disc < 1) {
        fail('Preencha o código oficial da BNCC, o nome e a disciplina.', '/admin');
    }
    $st = db()->prepare('SELECT id FROM disciplinas WHERE id=?');
    $st->execute([$disc]);
    if (!$st->fetchColumn()) fail('Disciplina inválida.', '/admin');
    $st = db()->prepare('SELECT id FROM habilidades_bncc WHERE codigo=?');
    $st->execute([$code]);
    if ($st->fetchColumn()) fail('Código BNCC já cadastrado.', '/admin');
    $st = db()->prepare('INSERT INTO habilidades_bncc(codigo,nome,descricao,disciplina_id,ano_escolar,created_at,updated_at)
                         VALUES(?, ?, ?, ?, ?, NOW(), NOW())');
    $st->execute([$code,$name,input('descricao') ?: null,$disc,$year ?: null]);
    flash('Habilidade cadastrada. Confira se o código e a descrição correspondem à BNCC oficial.');
    go('/admin');
}
