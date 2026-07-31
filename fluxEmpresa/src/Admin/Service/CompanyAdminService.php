<?php
declare(strict_types=1);
namespace App\Admin\Service;

use App\Admin\DTO\CompanyAdminFormData;
use App\Admin\DTO\CompanyImportData;
use App\Admin\Repository\CompanyAdminRepository;
use App\Company\DTO\ActiveCompany;
use App\Company\Service\ActiveCompanyContext;
use App\Integration\SO\Service\SoSupplierService;
use PDO;

final class CompanyAdminService {
    public function __construct(private readonly PDO $connection, private readonly CompanyAdminRepository $companies, private readonly ActiveCompanyContext $context) {}
    public function summary(): array { return $this->companies->summary(); }
    public function list(array $filters=[]): array { return $this->companies->list($filters); }
    public function find(int $id): ?array { return $this->companies->find($id); }
    public function findByDocument(string $document): ?array { return $this->companies->findByDocument($document); }
    public function users(int $id): array { return $this->companies->users($id); }
    public function create(CompanyAdminFormData $form,int $actorId): int { return $this->transaction(function() use($form,$actorId) { $this->assertResponsible($form); $data=$form->values(); if($data['documento']&&$this->companies->findByDocument($data['documento'],true)) throw new \InvalidArgumentException('Já existe uma empresa com este documento.'); $id=$this->companies->create($data,$actorId); $this->linkResponsible($id,$form); $this->event('EMPRESA_CRIADA_MANUALMENTE',$id); return $id; }); }
    public function import(CompanyImportData $import,int $actorId,SoSupplierService $suppliers): int { $supplier=$suppliers->find($import->supplierId()); if(!$supplier) throw new \InvalidArgumentException('Fornecedor não encontrado.'); $payload=$import->form()->values(); if($supplier->cnpj()!==null) $payload['documento']=$supplier->cnpj(); $form=CompanyAdminFormData::fromArray($payload); return $this->transaction(function() use($supplier,$form,$actorId) { if($this->companies->findByExternalSupplier($supplier->id(),true)) throw new \InvalidArgumentException('Fornecedor já está vinculado a uma empresa.'); $data=$form->values(); $this->assertResponsible($form); if($data['documento']&&$this->companies->findByDocument($data['documento'],true)) throw new \InvalidArgumentException('Já existe uma empresa com este documento. Use a vinculação.'); $id=$this->companies->create($data,$actorId); $this->companies->linkSupplier($id,$supplier->id(),$supplier->cnpj()); $this->linkResponsible($id,$form); $this->event('EMPRESA_IMPORTADA_DO_SO',$id); return $id; }); }
    public function link(int $companyId,int $supplierId,SoSupplierService $suppliers): void { $supplier=$suppliers->find($supplierId); if(!$supplier||!$this->companies->find($companyId,true)) throw new \InvalidArgumentException('Dados de vínculo inválidos.'); $this->transaction(function() use($companyId,$supplier) { if($this->companies->findByExternalSupplier($supplier->id(),true)) throw new \InvalidArgumentException('Fornecedor já vinculado.'); $this->companies->linkSupplier($companyId,$supplier->id(),$supplier->cnpj()); $this->event('EMPRESA_VINCULADA_AO_SO',$companyId); }); }
    public function update(int $id,CompanyAdminFormData $form): void { $this->transaction(function() use($id,$form) { if(!$this->companies->find($id,true)) throw new \InvalidArgumentException('Empresa não encontrada.'); $this->assertResponsible($form); $this->companies->update($id,$form->values()); $this->linkResponsible($id,$form); $this->event('EMPRESA_EDITADA',$id); }); }
    public function status(int $id,string $status): void { if(!in_array($status,['ativo','pendente','inativo','bloqueado'],true)) throw new \InvalidArgumentException('Status inválido.'); if(!$this->companies->find($id,true)) throw new \InvalidArgumentException('Empresa não encontrada.'); $this->companies->changeStatus($id,$status); $this->event('EMPRESA_STATUS_ALTERADO',$id); }
    public function enter(int $id,int $userId,?string $reason,string $ip,string $agent): ActiveCompany { $company=$this->companies->find($id,true); if(!$company||($company['status']??'')==='bloqueado') throw new \InvalidArgumentException('Empresa indisponível para acesso.'); $active=$this->transaction(function() use($company,$id,$userId,$reason,$ip,$agent) { $this->companies->closeOpenAccesses($userId); $audit=$this->companies->openAccess($id,$userId,$this->auditText($reason,500),$this->auditText($ip,45),$this->auditText($agent,500)); return new ActiveCompany($id,(string)$company['uuid'],(string)($company['nome_fantasia']?:$company['razao_social']),$userId,$audit,date('c')); }); $this->context->activate($active); $this->event('SUPORTE_ENTROU_NA_EMPRESA',$id); return $active; }
    public function leave(int $userId): void { $active=$this->context->currentForUser($userId); if($active){$this->companies->closeAccess($active->auditId());$this->event('SUPORTE_SAIU_DA_EMPRESA',$active->companyId());}$this->context->clear(); }
    private function linkResponsible(int $id,CompanyAdminFormData $form): void { if($form->responsibleUserId()) $this->companies->linkUser($id,$form->responsibleUserId()); }
    private function assertResponsible(CompanyAdminFormData $f): void { if($f->responsibleUserId()&&!$this->companies->activeUserExists($f->responsibleUserId())) throw new \InvalidArgumentException('Usuário responsável inválido.'); }
    private function auditText(?string $value,int $max): ?string { $value=trim(str_replace("\0",'',(string)$value)); return $value===''?null:mb_substr($value,0,$max); }
    private function event(string $event,int $id): void { error_log($event.' empresa_id='.$id); }
    private function transaction(callable $work): mixed { $this->connection->beginTransaction(); try{$result=$work();$this->connection->commit();return $result;}catch(\Throwable $e){if($this->connection->inTransaction())$this->connection->rollBack();throw $e;} }
}
