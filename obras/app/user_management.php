<?php
declare(strict_types=1);
require_once __DIR__ . '/auth.php';

/**
 * Salvar usuários com uma única regra de negócio, usada pela API e formulário PHP.
 * Apenas suporte e administrador podem cadastrar contas.
 * O suporte pode cadastrar outros suportes; administrador não.
 * @param array<string, mixed> $data
 */
function save_managed_user(array $actor, array $data): int {
    require_admin($actor);
    $rawId = trim((string)($data['id'] ?? ''));
    if ($rawId !== '' && !preg_match('/^[1-9][0-9]{0,15}$/D', $rawId)) throw new HttpError('Identificador de usuário inválido.');
    $id = $rawId === '' ? 0 : (int)$rawId;
    $name = trim((string)($data['name'] ?? ''));
    $username = strtolower(trim((string)($data['username'] ?? '')));
    $email = strtolower(trim((string)($data['email'] ?? '')));
    $role = trim((string)($data['role'] ?? ''));
    $password = (string)($data['password'] ?? '');
    $confirmation = array_key_exists('confirm_password', $data) ? (string)$data['confirm_password'] : null;

    if ($name === '' || strlen($name) > 600 || (function_exists('mb_strlen') && mb_strlen($name) > 150)) throw new HttpError('Informe um nome de até 150 caracteres.');
    if (!preg_match('/^[a-z0-9._-]{3,50}$/D', $username)) throw new HttpError('O usuário deve ter de 3 a 50 caracteres (letras minúsculas, números, ponto, traço ou sublinhado).');
    if (strlen($email) > 190 || !filter_var($email, FILTER_VALIDATE_EMAIL)) throw new HttpError('Informe um e-mail válido.');
    if (!in_array($role, ['suporte','administrador','gestor','operador','consulta'], true)) throw new HttpError('Selecione um nível de acesso válido.');
    if ($actor['role'] !== 'suporte' && $role === 'suporte') throw new HttpError('Somente o suporte pode conceder o perfil suporte.',403);
    if ($id === 0 && $password === '') throw new HttpError('Informe uma senha inicial.');
    if (strlen($password) > 128) throw new HttpError('A senha não pode exceder 128 caracteres.');
    if ($password !== '' && !password_secure($password)) throw new HttpError('Senha inválida: use pelo menos 8 caracteres com maiúscula, minúscula, número e símbolo.');
    if ($confirmation !== null && !hash_equals($password, $confirmation)) throw new HttpError('A confirmação da senha não confere.');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        if ($id > 0) {
            // Ordem de locks idêntica à desativação via API, para que duas
            // mudanças simultâneas não removam o último suporte ativo.
            $supportLocks=$pdo->query("SELECT id,active FROM users WHERE role='suporte' ORDER BY id FOR UPDATE")->fetchAll();
            $st = $pdo->prepare('SELECT id,role,active FROM users WHERE id=? FOR UPDATE');
            $st->execute([$id]);
            $old = $st->fetch();
            if (!$old) throw new HttpError('Usuário não encontrado.',404);
            if ($actor['role'] !== 'suporte' && $old['role'] === 'suporte') throw new HttpError('Somente o suporte pode editar outro suporte.',403);
            if ($id === (int)$actor['id'] && $role !== $actor['role']) throw new HttpError('Você não pode alterar seu próprio nível de acesso.',403);
            if ($old['role'] === 'suporte' && $role !== 'suporte' && (int)$old['active'] === 1) {
                $n = count(array_filter($supportLocks,static fn($row)=>(int)$row['active']===1));
                if ($n <= 1) throw new HttpError('O último suporte ativo não pode perder esse perfil.');
            }
            if ($id === (int)$actor['id'] && $password !== '') throw new HttpError('Para trocar sua própria senha, use Minha conta → Alterar senha.');
            $pdo->prepare('UPDATE users SET name=?,username=?,email=?,role=? WHERE id=?')->execute([$name,$username,$email,$role,$id]);
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost'=>65536,'time_cost'=>3,'threads'=>2]);
                if (!is_string($hash)) throw new RuntimeException('Falha ao proteger senha.');
                $pdo->prepare('UPDATE users SET password_hash=?,must_change_password=1,failed_attempts=0,locked_until=NULL WHERE id=?')->execute([$hash,$id]);
            }
        } else {
            $hash = password_hash($password, PASSWORD_ARGON2ID, ['memory_cost'=>65536,'time_cost'=>3,'threads'=>2]);
            if (!is_string($hash)) throw new RuntimeException('Falha ao proteger senha.');
            $pdo->prepare('INSERT INTO users(username,email,name,role,password_hash,must_change_password,created_by,active) VALUES (?,?,?,?,?,1,?,1)')
                ->execute([$username,$email,$name,$role,$hash,(int)$actor['id']]);
            $id=(int)$pdo->lastInsertId();
        }
        audit_log($rawId === '' ? 'user.created' : 'user.updated','usuarios',(string)$id,['role'=>$role]);
        $pdo->commit();
        return $id;
    } catch (PDOException $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ((string)$e->getCode() === '23000') throw new HttpError('Já existe um usuário ou e-mail com esses dados.',409);
        throw $e;
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        throw $e;
    }
}
