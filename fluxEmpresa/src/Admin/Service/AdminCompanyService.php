<?php
declare(strict_types=1);
namespace App\Admin\Service;
use App\Admin\Repository\AdminCompanyRepository;
use PDO;
final class AdminCompanyService { public function __construct(private readonly PDO $connection, private readonly AdminCompanyRepository $repository) {} public function list(array $filters,int $page,int $perPage,?int $owner=null):array{return $this->repository->paginate($filters,max(1,$page),min(100,max(1,$perPage)),$owner);} public function find(int $id):?array{return $this->repository->find($id);} public function counts(int $userId):array{return $this->repository->counts($userId);} public function integrationBySupplier(int $id):?array{return $this->repository->integrationBySupplier($id);} public function findByDocument(string $d):?array{return $this->repository->findByDocument(preg_replace('/\D/','',$d)??'');} public function import(array $data,int $userId,?int $supplierId):int{return $this->repository->create($data,$userId,$supplierId);} public function status(int $id,string $status):void{if(!in_array($status,['pendente','ativo','inativo','bloqueado'],true))throw new \InvalidArgumentException('Status inválido.');$this->repository->updateStatus($id,$status);} }
