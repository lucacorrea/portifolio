<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\StoreRepository;
use App\Repositories\UserCompanyRepository;
use RuntimeException;

final class StoreAccessService
{
    public function __construct(
        private UserCompanyRepository $memberships,
        private StoreRepository $stores
    ) {
    }

    public function assertAdmin(int $usuarioId, int $empresaId): void
    {
        if (!$this->memberships->userIsAdmin($usuarioId, $empresaId)) {
            throw new RuntimeException('Acesso permitido somente para administradores da empresa ativa.');
        }
    }

    public function assertChildOf(int $empresaPaiId, int $lojaId): array
    {
        $store = $this->stores->findChild($empresaPaiId, $lojaId);

        if (!$store) {
            throw new RuntimeException('Loja não encontrada para a empresa atual.');
        }

        return $store;
    }

    public function assertCanSwitch(int $usuarioId, int $empresaId): array
    {
        $membership = $this->memberships->findMembership($usuarioId, $empresaId);

        if (!$membership) {
            throw new RuntimeException('Você não possui vínculo com esta empresa.');
        }

        if ((int)$membership['usuario_ativo'] !== 1) {
            throw new RuntimeException('Usuário inativo.');
        }

        if ((int)$membership['vinculo_ativo'] !== 1) {
            throw new RuntimeException('Seu vínculo com esta empresa está inativo.');
        }

        if ((int)$membership['empresa_ativa'] !== 1) {
            throw new RuntimeException('Empresa inativa.');
        }

        return $membership;
    }
}
