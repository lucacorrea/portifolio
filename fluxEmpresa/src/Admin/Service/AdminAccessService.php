<?php
declare(strict_types=1);

namespace App\Admin\Service;

use App\Admin\Repository\AdminAccessRepository;
use App\Company\DTO\ActiveCompany;
use App\Company\Service\ActiveCompanyContext;
use PDO;
use Throwable;

final class AdminAccessService
{
    public function __construct(
        private readonly PDO $connection,
        private readonly AdminAccessRepository $repository
    ) {
    }

    public function enter(
        array $company,
        int $userId,
        string $ip,
        string $reason,
        ActiveCompanyContext $context
    ): void {
        if (str_contains($reason, "\0")) {
            throw new \InvalidArgumentException('Motivo do atendimento inválido.');
        }
        $reason = trim(preg_replace('/\s+/', ' ', $reason) ?? '');
        if (strlen($reason) < 10 || strlen($reason) > 255) {
            throw new \InvalidArgumentException('Informe o motivo do atendimento com 10 a 255 caracteres.');
        }

        $sessionKey = hash('sha256', session_id());
        $active = $context->current();
        $this->connection->beginTransaction();
        try {
            if ($active !== null && $active->supportUserId === $userId) {
                $this->repository->close($active->accessId);
            }
            $this->repository->closeOpenForSession($userId, $sessionKey);
            $accessId = $this->repository->open((int) $company['id'], $userId, $ip, $reason, $sessionKey);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }

        $context->enter(new ActiveCompany(
            (int) $company['id'],
            (string) ($company['uuid'] ?? ''),
            (string) ($company['nome_fantasia'] ?? $company['razao_social'] ?? 'Empresa'),
            $userId,
            $accessId,
            date(DATE_ATOM)
        ));
    }

    public function leave(ActiveCompanyContext $context): void
    {
        $active = $context->current();
        if ($active !== null) {
            $this->repository->close($active->accessId);
        }
        $context->clear();
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function list(int $page, int $perPage): array
    {
        return $this->repository->paginate(max(1, $page), min(100, max(1, $perPage)));
    }
}
