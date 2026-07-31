<?php
declare(strict_types=1);

namespace App\Company\Service;

use App\Company\DTO\ActiveCompany;
use App\Security\SessionManager;

final class ActiveCompanyContext
{
    private const KEY = 'platform_company_context';
    public function __construct(private readonly SessionManager $session) {}
    public function current(): ?ActiveCompany { $value=$this->session->get(self::KEY); return is_array($value)?ActiveCompany::fromArray($value):null; }
    public function currentForUser(int $userId): ?ActiveCompany { $company=$this->current(); if ($company !== null && $company->supportUserId() === $userId) return $company; if ($company !== null) $this->clear(); return null; }
    public function activate(ActiveCompany $company): void { $this->session->set(self::KEY,$company->toArray()); }
    public function clear(): void { $this->session->remove(self::KEY); }
}
