<?php
declare(strict_types=1);
namespace App\Admin\Service;
use App\Admin\Repository\AdminAccessRepository;
use App\Company\DTO\ActiveCompany;
use App\Company\Service\ActiveCompanyContext;
use PDO;
final class AdminAccessService { public function __construct(private readonly PDO $connection,private readonly AdminAccessRepository $repository){} public function enter(array $company,int $userId,string $ip,ActiveCompanyContext $context):void{$this->repository->closeOpenForUser($userId);$access=$this->repository->open((int)$company['id'],$userId,$ip);$context->enter(new ActiveCompany((int)$company['id'],(string)($company['uuid']??''),(string)($company['nome_fantasia']??$company['razao_social']??'Empresa'),$userId,$access,date(DATE_ATOM)));} public function leave(ActiveCompanyContext $context):void{$active=$context->current();if($active)$this->repository->close($active->accessId);$context->clear();} public function list(int $page,int $perPage):array{return $this->repository->paginate($page,$perPage);} }
