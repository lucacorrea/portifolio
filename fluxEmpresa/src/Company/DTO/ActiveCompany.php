<?php
declare(strict_types=1);

namespace App\Company\DTO;

final class ActiveCompany
{
    public function __construct(private readonly int $companyId, private readonly string $uuid, private readonly string $name, private readonly int $supportUserId, private readonly int $auditId, private readonly string $enteredAt) {}
    public function companyId(): int { return $this->companyId; }
    public function uuid(): string { return $this->uuid; }
    public function name(): string { return $this->name; }
    public function supportUserId(): int { return $this->supportUserId; }
    public function auditId(): int { return $this->auditId; }
    public function enteredAt(): string { return $this->enteredAt; }
    public function isSupportMode(): bool { return true; }
    public function toArray(): array { return ['company_id'=>$this->companyId,'uuid'=>$this->uuid,'name'=>$this->name,'support_user_id'=>$this->supportUserId,'audit_id'=>$this->auditId,'entered_at'=>$this->enteredAt]; }
    public static function fromArray(array $data): ?self { $id=(int)($data['company_id']??0);$user=(int)($data['support_user_id']??0);$audit=(int)($data['audit_id']??0);$uuid=trim((string)($data['uuid']??''));$name=trim((string)($data['name']??''));$entered=trim((string)($data['entered_at']??'')); return $id>0&&$user>0&&$audit>0&&preg_match('/^[a-f0-9-]{36}$/i',$uuid)&&$name!==''&&$entered!==''?new self($id,$uuid,$name,$user,$audit,$entered):null; }
}
