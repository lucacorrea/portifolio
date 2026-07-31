<?php
declare(strict_types=1);
namespace App\Company\Service;
use App\Company\DTO\ActiveCompany;
use App\Security\SessionManager;
final class ActiveCompanyContext
{
    private const KEY = 'platform_company_context';
    public function __construct(private readonly SessionManager $session) {}
    public function current(): ?ActiveCompany { $data = $this->session->get(self::KEY); if (!is_array($data) || !isset($data['id'], $data['uuid'], $data['name'], $data['support_user_id'], $data['access_id'], $data['entered_at'])) return null; return new ActiveCompany((int)$data['id'], (string)$data['uuid'], (string)$data['name'], (int)$data['support_user_id'], (int)$data['access_id'], (string)$data['entered_at']); }
    public function enter(ActiveCompany $company): void { $this->session->set(self::KEY, ['id'=>$company->id,'uuid'=>$company->uuid,'name'=>$company->name,'support_user_id'=>$company->supportUserId,'access_id'=>$company->accessId,'entered_at'=>$company->enteredAt]); }
    public function clear(): void { $this->session->remove(self::KEY); }
}
