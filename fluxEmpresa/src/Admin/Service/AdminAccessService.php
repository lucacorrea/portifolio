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
        string $sessionBindingHash,
        ActiveCompanyContext $context
    ): void {
        if ((int) ($company['id'] ?? 0) <= 0 || (string) ($company['status'] ?? '') !== 'ativo') {
            throw new \InvalidArgumentException('A empresa precisa estar ativa para acessar o painel operacional.');
        }
        if (str_contains($reason, "\0")) {
            throw new \InvalidArgumentException('Motivo do atendimento inválido.');
        }
        $reason = trim(preg_replace('/\s+/', ' ', $reason) ?? '');
        if (strlen($reason) < 10 || strlen($reason) > 255) {
            throw new \InvalidArgumentException('Informe o motivo do atendimento com 10 a 255 caracteres.');
        }

        if (!preg_match('/^[a-f0-9]{64}$/D', $sessionBindingHash)) {
            throw new \InvalidArgumentException('Vínculo da sessão administrativa inválido.');
        }
        $active = $context->current();
        $this->connection->beginTransaction();
        try {
            if ($active !== null && $active->supportUserId === $userId) {
                $this->repository->closeOwned($active->accessId, $userId, $sessionBindingHash);
            }
            $this->repository->closeOpenForSession($userId, $sessionBindingHash);
            $accessId = $this->repository->open((int) $company['id'], $userId, $ip, $reason, $sessionBindingHash);
            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }
            throw $exception;
        }

        try {
            $context->enter(new ActiveCompany(
                (int) $company['id'],
                (string) ($company['uuid'] ?? ''),
                (string) ($company['nome_fantasia'] ?? $company['razao_social'] ?? 'Empresa'),
                $userId,
                $accessId,
                date(DATE_ATOM)
            ));
        } catch (Throwable $exception) {
            $this->repository->closeOwned($accessId, $userId, $sessionBindingHash);
            throw $exception;
        }
    }

    public function leaveAuthorized(
        ActiveCompanyContext $context,
        int $userId,
        string $sessionBindingHash
    ): void {
        $active = $context->current();
        try {
            if ($active !== null) {
                $this->repository->closeOwned($active->accessId, $userId, $sessionBindingHash);
            }
        } finally {
            $context->clear();
        }
    }

    /** @return array{items:array<int,array<string,mixed>>,total:int} */
    public function list(int $page, int $perPage): array
    {
        return $this->repository->paginate(max(1, $page), min(100, max(1, $perPage)));
    }
}
