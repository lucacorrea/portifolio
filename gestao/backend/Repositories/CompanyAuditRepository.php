<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use PDO;

final class CompanyAuditRepository
{
    private PDO $db;

    public function __construct(?PDO $db = null)
    {
        $this->db = $db ?? Database::connection();
    }

    public function record(
        int $usuarioId,
        ?int $empresaOrigemId,
        int $empresaDestinoId,
        string $acao,
        array $detalhes = []
    ): void {
        $stmt = $this->db->prepare(
            'INSERT INTO empresa_contexto_auditoria (
                usuario_id,
                empresa_origem_id,
                empresa_destino_id,
                acao,
                ip,
                user_agent,
                detalhes
             ) VALUES (
                :usuario_id,
                :empresa_origem_id,
                :empresa_destino_id,
                :acao,
                :ip,
                :user_agent,
                :detalhes
             )'
        );

        $stmt->execute([
            ':usuario_id' => $usuarioId,
            ':empresa_origem_id' => $empresaOrigemId,
            ':empresa_destino_id' => $empresaDestinoId,
            ':acao' => $acao,
            ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ':detalhes' => $detalhes !== [] ? json_encode($detalhes, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }
}
