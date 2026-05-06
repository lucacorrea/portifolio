<?php
class Usuario extends Model
{
    public function buscarPorIdentificacao(string $identificacao): ?array
    {
        $identificacao = trim($identificacao);

        $stmt = self::db()->prepare(
            'SELECT id, nome, email, senha, nivel, ativo
             FROM usuarios
             WHERE email = :email OR nome = :nome
             ORDER BY id ASC
             LIMIT 1'
        );
        $stmt->execute([
            'email' => strtolower($identificacao),
            'nome' => $identificacao,
        ]);
        $usuario = $stmt->fetch();

        return $usuario ?: null;
    }

    public function buscarAtivoPorIdentificacao(string $identificacao): ?array
    {
        $usuario = $this->buscarPorIdentificacao($identificacao);

        if (!$usuario || (int) ($usuario['ativo'] ?? 0) !== 1) {
            return null;
        }

        return $usuario;
    }

    public function registrarUltimoLogin(int $id): void
    {
        try {
            $stmt = self::db()->prepare('UPDATE usuarios SET ultimo_login = NOW() WHERE id = :id');
            $stmt->execute(['id' => $id]);
        } catch (PDOException $exception) {
            if ($exception->getCode() !== '42S22') {
                throw $exception;
            }
        }
    }
}
