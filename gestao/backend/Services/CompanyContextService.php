<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\CompanyAuditRepository;
use App\Repositories\UserCompanyRepository;
use App\Security\Session;
use RuntimeException;

final class CompanyContextService
{
    private UserCompanyRepository $memberships;
    private CompanyAuditRepository $audit;

    public function __construct(
        ?UserCompanyRepository $memberships = null,
        ?CompanyAuditRepository $audit = null
    ) {
        $db = Database::connection();
        $this->memberships = $memberships ?? new UserCompanyRepository($db);
        $this->audit = $audit ?? new CompanyAuditRepository($db);
    }

    public function activate(int $usuarioId, int $empresaId, string $action = 'selecionar'): array
    {
        $membership = $this->memberships->findMembership($usuarioId, $empresaId);

        if (!$membership) {
            throw new RuntimeException('Empresa não disponível para este usuário.');
        }

        if ((int)$membership['usuario_ativo'] !== 1) {
            throw new RuntimeException('Usuário inativo.');
        }

        if ((int)$membership['vinculo_ativo'] !== 1) {
            throw new RuntimeException('Vínculo inativo para esta empresa.');
        }

        if ((int)$membership['empresa_ativa'] !== 1) {
            throw new RuntimeException('Empresa inativa.');
        }

        $originCompanyId = Session::get('user.empresa_id');
        $originCompanyId = $originCompanyId !== null ? (int)$originCompanyId : null;

        Session::regenerate();
        Session::put('user.id', $usuarioId);
        Session::put('user.empresa_principal_id', (int)$membership['empresa_principal_id']);
        Session::put('user.empresa_id', (int)$membership['empresa_id']);
        Session::put('user.nome', (string)$membership['usuario_nome']);
        Session::put('user.email', (string)$membership['email']);
        Session::put('user.nivel', (string)$membership['nivel']);
        Session::put('user.empresa_nome', $this->displayName($membership));
        Session::put('user.empresa_pai_id', $membership['empresa_pai_id'] !== null ? (int)$membership['empresa_pai_id'] : null);
        Session::put('user.empresa_tipo', (string)$membership['empresa_tipo']);
        Session::put('user.company_selection_pending', false);

        $this->audit->record($usuarioId, $originCompanyId, $empresaId, $action);

        return $membership;
    }

    public function activeCompany(): ?array
    {
        $usuarioId = (int)Session::get('user.id', 0);
        $empresaId = (int)Session::get('user.empresa_id', 0);

        if ($usuarioId <= 0 || $empresaId <= 0) {
            return null;
        }

        return $this->memberships->findMembership($usuarioId, $empresaId);
    }

    public function requiresSelection(int $usuarioId): bool
    {
        $companies = $this->availableCompanies($usuarioId);
        return count($companies) > 1;
    }

    public function availableCompanies(int $usuarioId): array
    {
        return $this->memberships->findActiveCompaniesForUser($usuarioId);
    }

    public function clearCompanyContext(): void
    {
        Session::forget('user.empresa_id');
        Session::forget('user.empresa_nome');
        Session::forget('user.nivel');
        Session::forget('user.empresa_pai_id');
        Session::forget('user.empresa_tipo');
        Session::put('user.company_selection_pending', true);
    }

    private function displayName(array $membership): string
    {
        $fantasyName = trim((string)($membership['nome_fantasia'] ?? ''));

        return $fantasyName !== ''
            ? $fantasyName
            : trim((string)($membership['empresa_nome'] ?? ''));
    }
}
