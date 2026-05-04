<?php
class Usuario extends Model
{
    public function buscarAtivoPorIdentificacao(string $identificacao): ?array
    {
        $stmt = self::db()->prepare(
            'SELECT id, nome, email, senha, nivel, ativo
             FROM usuarios
             WHERE ativo = 1 AND (email = :identificacao OR nome = :identificacao)
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute(['identificacao' => $identificacao]);
        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    public function registrarUltimoLogin(int $id): void
    {
        $stmt = self::db()->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id');
        $stmt->execute(['id' => $id]);
    }
}
