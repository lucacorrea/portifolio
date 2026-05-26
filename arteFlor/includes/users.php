<?php
require_once __DIR__ . '/auth.php';

function admin_user_profile_options(): array
{
    return [
        'admin' => 'Administrador',
        'gerente' => 'Gerente',
        'operador' => 'Operador',
    ];
}

function admin_user_can_manage(array $adminUser): bool
{
    return (string) ($adminUser['perfil'] ?? '') === 'admin';
}

function admin_user_clean_text(mixed $value, int $limit): string
{
    if (is_array($value) || is_object($value)) {
        return '';
    }

    $text = trim((string) $value);
    $text = preg_replace('/\s+/', ' ', $text) ?? '';

    return function_exists('mb_substr') ? mb_substr($text, 0, $limit) : substr($text, 0, $limit);
}

function admin_user_find(int $userId): ?array
{
    if ($userId <= 0) {
        return null;
    }

    $statement = db()->prepare(
        'SELECT id, nome, email, perfil, ativo, ultimo_acesso_em, criado_em, atualizado_em
         FROM usuarios_admin
         WHERE id = :id
         LIMIT 1'
    );
    $statement->execute(['id' => $userId]);
    $user = $statement->fetch();

    return $user ?: null;
}

function admin_user_list(array $filters = []): array
{
    $where = ['1 = 1'];
    $params = [];

    $search = admin_user_clean_text($filters['search'] ?? '', 120);
    if ($search !== '') {
        $where[] = '(u.nome LIKE :search_nome OR u.email LIKE :search_email)';
        $params['search_nome'] = '%' . $search . '%';
        $params['search_email'] = '%' . $search . '%';
    }

    $profile = admin_user_clean_text($filters['perfil'] ?? '', 40);
    if ($profile !== '' && isset(admin_user_profile_options()[$profile])) {
        $where[] = 'u.perfil = :perfil';
        $params['perfil'] = $profile;
    }

    $status = admin_user_clean_text($filters['status'] ?? '', 20);
    if ($status === 'ativo' || $status === 'inativo') {
        $where[] = 'u.ativo = :ativo';
        $params['ativo'] = $status === 'ativo' ? 1 : 0;
    }

    $statement = db()->prepare(
        'SELECT
            u.id,
            u.nome,
            u.email,
            u.perfil,
            u.ativo,
            u.ultimo_acesso_em,
            u.criado_em,
            u.atualizado_em,
            (
                SELECT COUNT(*)
                FROM pedido_status_historico h
                WHERE h.usuario_admin_id = u.id
            ) AS acoes_pedidos,
            (
                SELECT COUNT(*)
                FROM estoque_movimentacoes m
                WHERE m.usuario_admin_id = u.id
            ) AS movimentacoes_estoque
         FROM usuarios_admin u
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY u.ativo DESC, FIELD(u.perfil, "admin", "gerente", "operador"), u.nome ASC'
    );
    $statement->execute($params);

    return $statement->fetchAll();
}

function admin_user_stats(): array
{
    $row = db()->query(
        'SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN ativo = 1 THEN 1 ELSE 0 END) AS ativos,
            SUM(CASE WHEN ativo = 1 AND perfil = "admin" THEN 1 ELSE 0 END) AS admins,
            SUM(CASE WHEN ultimo_acesso_em >= (CURRENT_TIMESTAMP - INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS recentes
         FROM usuarios_admin'
    )->fetch() ?: [];

    return [
        'total' => (int) ($row['total'] ?? 0),
        'ativos' => (int) ($row['ativos'] ?? 0),
        'admins' => (int) ($row['admins'] ?? 0),
        'recentes' => (int) ($row['recentes'] ?? 0),
    ];
}

function admin_user_active_admins_except(int $userId): int
{
    $statement = db()->prepare(
        'SELECT COUNT(*) AS total
         FROM usuarios_admin
         WHERE ativo = 1 AND perfil = "admin" AND id <> :id'
    );
    $statement->execute(['id' => $userId]);

    return (int) ($statement->fetch()['total'] ?? 0);
}

function admin_user_assert_profile_change_allowed(array $existing, string $newProfile, int $newActive, array $currentUser): void
{
    $userId = (int) $existing['id'];
    $currentUserId = (int) ($currentUser['id'] ?? 0);
    $currentProfile = (string) $existing['perfil'];
    $currentActive = (int) $existing['ativo'];

    if ($userId === $currentUserId && ($newProfile !== $currentProfile || $newActive !== $currentActive)) {
        throw new InvalidArgumentException('Você não pode alterar seu próprio perfil ou status.');
    }

    $removesLastAdmin = $currentProfile === 'admin'
        && $currentActive === 1
        && ($newProfile !== 'admin' || $newActive !== 1)
        && admin_user_active_admins_except($userId) === 0;

    if ($removesLastAdmin) {
        throw new InvalidArgumentException('Mantenha pelo menos um administrador ativo.');
    }
}

function admin_user_save_from_request(array $request, array $currentUser): int
{
    if (!admin_user_can_manage($currentUser)) {
        throw new InvalidArgumentException('Apenas administradores podem gerenciar usuários.');
    }

    $userId = filter_var($request['id'] ?? 0, FILTER_VALIDATE_INT) ?: 0;
    $name = admin_user_clean_text($request['nome'] ?? '', 140);
    $email = strtolower(admin_user_clean_text($request['email'] ?? '', 180));
    $profile = admin_user_clean_text($request['perfil'] ?? 'operador', 40);
    $activeValue = (string) ($request['ativo'] ?? '1');
    if (!in_array($activeValue, ['0', '1'], true)) {
        throw new InvalidArgumentException('Status de usuário inválido.');
    }
    $active = $activeValue === '1' ? 1 : 0;
    $password = (string) ($request['senha'] ?? '');
    $confirmation = (string) ($request['confirmar_senha'] ?? '');

    if ($name === '' || strlen($name) < 3) {
        throw new InvalidArgumentException('Informe o nome do usuário.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Informe um e-mail válido.');
    }
    if (!isset(admin_user_profile_options()[$profile])) {
        throw new InvalidArgumentException('Perfil de usuário inválido.');
    }

    $existing = $userId > 0 ? admin_user_find($userId) : null;
    if ($userId > 0 && !$existing) {
        throw new InvalidArgumentException('Usuário não encontrado.');
    }
    if ($existing) {
        admin_user_assert_profile_change_allowed($existing, $profile, $active, $currentUser);
    }

    $emailSql = 'SELECT id FROM usuarios_admin WHERE email = :email';
    $emailParams = ['email' => $email];
    if ($userId > 0) {
        $emailSql .= ' AND id <> :id';
        $emailParams['id'] = $userId;
    }
    $emailSql .= ' LIMIT 1';
    $statement = db()->prepare($emailSql);
    $statement->execute($emailParams);
    if ($statement->fetch()) {
        throw new InvalidArgumentException('Já existe um usuário com esse e-mail.');
    }

    $shouldUpdatePassword = $password !== '' || $userId === 0;
    if ($shouldUpdatePassword) {
        if (strlen($password) < 8) {
            throw new InvalidArgumentException('A senha deve ter pelo menos 8 caracteres.');
        }
        if (!hash_equals($password, $confirmation)) {
            throw new InvalidArgumentException('A confirmação de senha não confere.');
        }
    }

    if ($userId > 0) {
        $params = [
            'id' => $userId,
            'nome' => $name,
            'email' => $email,
            'perfil' => $profile,
            'ativo' => $active,
        ];
        $sql = 'UPDATE usuarios_admin
                SET nome = :nome, email = :email, perfil = :perfil, ativo = :ativo, atualizado_em = CURRENT_TIMESTAMP';
        if ($shouldUpdatePassword) {
            $sql .= ', senha_hash = :senha_hash';
            $params['senha_hash'] = password_hash($password, PASSWORD_DEFAULT);
        }
        $sql .= ' WHERE id = :id';
        db()->prepare($sql)->execute($params);

        return $userId;
    }

    db()->prepare(
        'INSERT INTO usuarios_admin (nome, email, senha_hash, perfil, ativo)
         VALUES (:nome, :email, :senha_hash, :perfil, :ativo)'
    )->execute([
        'nome' => $name,
        'email' => $email,
        'senha_hash' => password_hash($password, PASSWORD_DEFAULT),
        'perfil' => $profile,
        'ativo' => $active,
    ]);

    return (int) db()->lastInsertId();
}

function admin_user_update_status(int $userId, int $active, array $currentUser): void
{
    if (!admin_user_can_manage($currentUser)) {
        throw new InvalidArgumentException('Apenas administradores podem gerenciar usuários.');
    }

    $existing = admin_user_find($userId);
    if (!$existing) {
        throw new InvalidArgumentException('Usuário não encontrado.');
    }

    admin_user_assert_profile_change_allowed($existing, (string) $existing['perfil'], $active, $currentUser);

    db()->prepare('UPDATE usuarios_admin SET ativo = :ativo, atualizado_em = CURRENT_TIMESTAMP WHERE id = :id')
        ->execute(['id' => $userId, 'ativo' => $active]);
}

function admin_user_badge_class(string $profile): string
{
    return match ($profile) {
        'admin' => 'admin-badge-soft',
        'gerente' => 'admin-badge-info',
        default => 'admin-badge-ok',
    };
}
