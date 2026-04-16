<?php
declare(strict_types=1);

require_once APP_PATH . '/Core/Model.php';

final class Admin extends Model
{
    public static function findByEmail(string $email): ?array
    {
        $sql = "
            SELECT
                id,
                nome,
                email,
                senha_hash,
                telefone,
                nivel,
                status,
                ultimo_login_em,
                criado_em,
                atualizado_em
            FROM admins
            WHERE email = :email
            LIMIT 1
        ";

        $stmt = self::db()->prepare($sql);
        $stmt->bindValue(':email', $email, PDO::PARAM_STR);
        $stmt->execute();

        $admin = $stmt->fetch();

        return $admin !== false ? $admin : null;
    }

    public static function updateLastLogin(int $id): void
    {
        $sql = "UPDATE admins SET ultimo_login_em = NOW() WHERE id = :id LIMIT 1";

        $stmt = self::db()->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
    }
}
