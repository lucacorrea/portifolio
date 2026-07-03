<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Logger;
use App\DTO\PaginatedResult;
use App\DTO\Pagination;
use App\Exceptions\RepositoryException;
use App\Models\AuditLog;
use PDO;
use PDOException;

final class AuditLogRepository
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function record(?int $userId, ?int $targetUserId, string $action, string $module, ?string $description, ?array $before, ?array $after): int
    {
        try {
            $stmt = $this->pdo->prepare(
                'INSERT INTO auditoria
                    (usuario_id, usuario_alvo_id, acao, modulo, descricao, dados_anteriores, dados_novos, ip, user_agent, criado_em)
                 VALUES
                    (:usuario_id, :usuario_alvo_id, :acao, :modulo, :descricao, :dados_anteriores, :dados_novos, :ip, :user_agent, NOW())'
            );
            $stmt->execute([
                'usuario_id' => $userId,
                'usuario_alvo_id' => $targetUserId,
                'acao' => $action,
                'modulo' => $module,
                'descricao' => $description,
                'dados_anteriores' => $before === null ? null : json_encode($before, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'dados_novos' => $after === null ? null : json_encode($after, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255),
            ]);

            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $exception) {
            throw $this->fail('record', 'Falha ao registrar auditoria.', $exception);
        }
    }

    public function findByTargetUser(int $targetUserId, Pagination $pagination): PaginatedResult
    {
        return $this->paginate('usuario_alvo_id = :target_id', ['target_id' => $targetUserId], $pagination);
    }

    public function findAuthenticationEvents(Pagination $pagination): PaginatedResult
    {
        return $this->paginate("modulo = 'autenticacao'", [], $pagination);
    }

    /** @param array<string,mixed> $params */
    private function paginate(string $where, array $params, Pagination $pagination): PaginatedResult
    {
        try {
            $count = $this->pdo->prepare("SELECT COUNT(*) FROM auditoria WHERE {$where}");
            $count->execute($params);
            $total = (int) $count->fetchColumn();

            $stmt = $this->pdo->prepare(
                "SELECT id, usuario_id, usuario_alvo_id, acao, modulo, descricao, dados_anteriores, dados_novos, ip, user_agent, criado_em
                 FROM auditoria
                 WHERE {$where}
                 ORDER BY criado_em DESC
                 LIMIT :limit OFFSET :offset"
            );

            foreach ($params as $key => $value) {
                $stmt->bindValue(':' . $key, $value);
            }

            $stmt->bindValue(':limit', $pagination->getPerPage(), PDO::PARAM_INT);
            $stmt->bindValue(':offset', $pagination->getOffset(), PDO::PARAM_INT);
            $stmt->execute();

            $items = array_map(static fn (array $row): AuditLog => AuditLog::fromArray($row), $stmt->fetchAll());

            return new PaginatedResult($items, $total, $pagination->getPage(), $pagination->getPerPage());
        } catch (PDOException $exception) {
            throw $this->fail('paginate', 'Falha ao consultar auditoria.', $exception);
        }
    }

    private function fail(string $operation, string $message, PDOException $exception): RepositoryException
    {
        Logger::application('Repository operation failed.', [
            'repository' => self::class,
            'operation' => $operation,
            'type' => $exception::class,
            'code' => $exception->getCode(),
        ]);

        return new RepositoryException($message, 0, $exception);
    }
}
