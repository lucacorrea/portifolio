<?php
class Usuario extends Model
{
    public function buscarAtivoPorEmail(string $email): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT id, nome, email, senha, nivel, ativo FROM usuarios WHERE email = :email LIMIT 1'
        );
        $stmt->execute(['email' => $email]);
        $usuario = $stmt->fetch();

        if (!$usuario || (int) $usuario['ativo'] !== 1) {
            return null;
        }

        return $usuario;
    }

    public function registrarUltimoLogin(int $id): void
    {
        $stmt = self::db()->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
