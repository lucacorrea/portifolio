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
        $this->assertCanCreateFilial($usuarioId, $empresaId);
    }

    public function assertMatrixContext(int $empresaId): array
    {
        $company = $this->stores->findById($empresaId);

        if (!$company || (int)($company['ativo'] ?? 0) !== 1) {
            throw new RuntimeException('Empresa matriz inválida ou inativa.');
        }

        if ((string)($company['tipo'] ?? 'matriz') !== 'matriz' || $company['empresa_pai_id'] !== null) {
            throw new RuntimeException('Entre em uma empresa matriz para cadastrar uma filial.');
        }

        return $company;
    }

    public function canCreateFilial(int $usuarioId, int $matrizId): bool
    {
        try {
            $this->assertCanCreateFilial($usuarioId, $matrizId);
            return true;
        } catch (RuntimeException) {
            return false;
        }
    }

    public function assertCanCreateFilial(int $usuarioId, int $matrizId): array
    {
        $matrix = $this->assertMatrixContext($matrizId);
        $membership = $this->memberships->findMembership($usuarioId, $matrizId);

        if (!$membership || (int)$membership['usuario_ativo'] !== 1 || (int)$membership['vinculo_ativo'] !== 1) {
            throw new RuntimeException('Você não possui vínculo ativo com esta matriz.');
        }

        if ((new PlatformAuthorizationService())->isPlatformOwner($usuarioId)) {
            return $matrix;
        }

        if ((new MatrixAdminService())->isPrimaryAdmin($usuarioId, $matrizId)) {
            return $matrix;
        }

        throw new RuntimeException('Somente o administrador principal da matriz pode criar filiais.');
    }

    public function assertCanManageFilial(int $usuarioId, int $matrizId, int $filialId): array
    {
        $this->assertCanCreateFilial($usuarioId, $matrizId);
        return $this->assertChildOf($matrizId, $filialId);
    }

    public function assertChildOf(int $empresaPaiId, int $lojaId): array
    {
        $store = $this->stores->findChild($empresaPaiId, $lojaId);

        if (!$store) {
            throw new RuntimeException('Filial não encontrada para a matriz atual.');
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
