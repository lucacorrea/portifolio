<?php

declare(strict_types=1);

namespace App\Admin\Service;

use App\Admin\Repository\AdminAccessRepository;
use App\Company\DTO\ActiveCompany;
use App\Company\Service\ActiveCompanyContext;
use InvalidArgumentException;
use PDO;
use Throwable;

final class AdminAccessService
{
    private const DEFAULT_REASON =
        'Acesso administrativo direto pelo painel da plataforma';

    private const ALLOWED_COMPANY_STATUSES = [
        'ativo',
        'pendente',
        'inativo',
    ];

    public function __construct(
        private readonly PDO $connection,
        private readonly AdminAccessRepository $repository
    ) {
    }

    /**
     * Abre o painel operacional no contexto da empresa selecionada.
     *
     * O usuário continua sendo o suporte autenticado. Não existe
     * impersonação ou troca de identidade.
     *
     * @param array<string, mixed> $company
     */
    public function enter(
        array $company,
        int $userId,
        string $ip,
        string $userAgent,
        string $reason,
        string $sessionBindingHash,
        ActiveCompanyContext $context
    ): void {
        $companyId = (int) (
            $company['id']
            ?? 0
        );

        $companyStatus = (string) (
            $company['status']
            ?? ''
        );

        if ($companyId <= 0) {
            throw new InvalidArgumentException(
                'Empresa inválida.'
            );
        }

        if (
            !in_array(
                $companyStatus,
                self::ALLOWED_COMPANY_STATUSES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                $companyStatus === 'bloqueado'
                    ? 'A empresa está bloqueada. Reative-a antes de acessar.'
                    : 'A empresa não está disponível para acesso operacional.'
            );
        }

        if ($userId <= 0) {
            throw new InvalidArgumentException(
                'Usuário administrativo inválido.'
            );
        }

        $reason = $this->normalizeReason(
            $reason
        );

        $ip = $this->normalizeIp(
            $ip
        );

        $userAgent = $this->normalizeUserAgent(
            $userAgent
        );

        if (
            !preg_match(
                '/^[a-f0-9]{64}$/D',
                $sessionBindingHash
            )
        ) {
            throw new InvalidArgumentException(
                'Vínculo da sessão administrativa inválido.'
            );
        }

        $activeCompany = $context->current();

        $this->connection->beginTransaction();

        try {
            /*
             * Encerra o contexto anterior antes de abrir outra empresa.
             */
            if (
                $activeCompany !== null
                && $activeCompany->supportUserId === $userId
            ) {
                $this->repository->closeOwned(
                    $activeCompany->accessId,
                    $userId,
                    $sessionBindingHash
                );
            }

            /*
             * Segurança adicional: encerra qualquer outro acesso ainda
             * aberto para esta mesma sessão administrativa.
             */
            $this->repository->closeOpenForSession(
                $userId,
                $sessionBindingHash
            );

            $accessId = $this->repository->open(
                $companyId,
                $userId,
                $ip,
                $userAgent,
                $reason,
                $sessionBindingHash
            );

            $this->connection->commit();
        } catch (Throwable $exception) {
            if ($this->connection->inTransaction()) {
                $this->connection->rollBack();
            }

            throw $exception;
        }

        try {
            $companyName = trim(
                (string) (
                    $company['nome_fantasia']
                    ?? ''
                )
            );

            if ($companyName === '') {
                $companyName = trim(
                    (string) (
                        $company['razao_social']
                        ?? ''
                    )
                );
            }

            if ($companyName === '') {
                $companyName = 'Empresa';
            }

            $context->enter(
                new ActiveCompany(
                    $companyId,
                    (string) (
                        $company['uuid']
                        ?? ''
                    ),
                    $companyName,
                    $userId,
                    $accessId,
                    date(DATE_ATOM)
                )
            );
        } catch (Throwable $exception) {
            /*
             * Se não for possível salvar o contexto na sessão, encerra
             * imediatamente o acesso que acabou de ser registrado.
             */
            try {
                $this->repository->closeOwned(
                    $accessId,
                    $userId,
                    $sessionBindingHash
                );
            } catch (Throwable $closeException) {
                error_log(
                    'Could not close administrative access after context failure: '
                    . get_class($closeException)
                );
            }

            throw $exception;
        }
    }

    /**
     * Encerra o acesso administrativo atual.
     */
    public function leaveAuthorized(
        ActiveCompanyContext $context,
        int $userId,
        string $sessionBindingHash
    ): void {
        $activeCompany = $context->current();

        try {
            if ($activeCompany !== null) {
                $this->repository->closeOwned(
                    $activeCompany->accessId,
                    $userId,
                    $sessionBindingHash
                );
            }
        } finally {
            $context->clear();
        }
    }

    /**
     * Lista o histórico de acessos administrativos.
     *
     * @return array{
     *     items: array<int, array<string, mixed>>,
     *     total: int
     * }
     */
    public function list(
        int $page,
        int $perPage
    ): array {
        return $this->repository->paginate(
            max(
                1,
                $page
            ),
            min(
                100,
                max(
                    1,
                    $perPage
                )
            )
        );
    }

    private function normalizeReason(
        string $reason
    ): string {
        if (str_contains($reason, "\0")) {
            throw new InvalidArgumentException(
                'Motivo do acesso inválido.'
            );
        }

        $normalized = trim(
            preg_replace(
                '/\s+/u',
                ' ',
                $reason
            )
            ?? ''
        );

        if ($normalized === '') {
            $normalized = self::DEFAULT_REASON;
        }

        if (strlen($normalized) > 255) {
            throw new InvalidArgumentException(
                'O motivo do acesso excede o limite permitido.'
            );
        }

        return $normalized;
    }

    private function normalizeIp(
        string $ip
    ): string {
        $ip = trim(
            $ip
        );

        if (
            $ip === ''
            || filter_var(
                $ip,
                FILTER_VALIDATE_IP
            ) === false
        ) {
            return '';
        }

        return substr(
            $ip,
            0,
            45
        );
    }

    private function normalizeUserAgent(
        string $userAgent
    ): string {
        if (str_contains($userAgent, "\0")) {
            return '';
        }

        return substr(
            trim(
                $userAgent
            ),
            0,
            500
        );
    }
}