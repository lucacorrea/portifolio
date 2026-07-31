<?php
declare(strict_types=1);

namespace App\Company\DTO;

use InvalidArgumentException;

final class CompanyScope
{
    /**
     * @param string[] $permissions
     */
    public function __construct(
        private readonly int $companyId,
        private readonly string $companyUuid,
        private readonly string $companyName,
        private readonly int $actorUserId,
        private readonly string $mode,
        private readonly array $permissions = [],
        private readonly ?int $membershipId = null,
        private readonly ?int $supportAccessId = null,
        private readonly ?int $profileId = null,
        private readonly ?string $profileCode = null,
        private readonly ?string $profileName = null
    ) {
        if ($companyId <= 0 || $actorUserId <= 0) {
            throw new InvalidArgumentException('Contexto operacional inválido.');
        }
        if (!in_array($mode, ['member', 'support'], true)) {
            throw new InvalidArgumentException('Modo do contexto operacional inválido.');
        }
        if (($mode === 'member' && $membershipId === null)
            || ($mode === 'support' && $supportAccessId === null)) {
            throw new InvalidArgumentException('Origem do contexto operacional ausente.');
        }
    }

    public function id(): int { return $this->companyId; }
    public function uuid(): string { return $this->companyUuid; }
    public function name(): string { return $this->companyName; }
    public function actorUserId(): int { return $this->actorUserId; }
    public function mode(): string { return $this->mode; }
    public function isSupport(): bool { return $this->mode === 'support'; }
    public function membershipId(): ?int { return $this->membershipId; }
    public function supportAccessId(): ?int { return $this->supportAccessId; }
    public function profileId(): ?int { return $this->profileId; }
    public function profileCode(): ?string { return $this->profileCode; }
    public function profileName(): ?string { return $this->profileName; }

    /** @return string[] */
    public function permissions(): array { return $this->permissions; }

    public function allows(string $permission): bool
    {
        $permission = trim($permission);
        if ($permission === '') {
            return false;
        }

        // Usuários e perfis ainda são identidades globais da plataforma. Até que
        // esse cadastro tenha armazenamento próprio por empresa, ele não pode ser
        // aberto dentro do painel operacional sem expor outro tenant.
        if (str_starts_with($permission, 'usuario.') || str_starts_with($permission, 'perfil.')) {
            return false;
        }

        return $this->isSupport() || in_array($permission, $this->permissions, true);
    }
}
