<?php
declare(strict_types=1);

namespace App\Integration\SO\Repository;

use App\Integration\SO\DTO\SoSupplierData;
use PDO;

final class SoSupplierRepository
{
    public function __construct(private readonly PDO $connection) {}
    /** @return array{items:SoSupplierData[],total:int} */
    public function search(string $search, int $page, int $perPage): array { $page=max(1,$page);$perPage=min(100,max(1,$perPage));$where='';$params=[];$digits=preg_replace('/\D+/','',$search)??'';if($search!==''){$where=' WHERE nome LIKE :name'.($digits!==''?' OR cnpj LIKE :cnpj':'');$params['name']='%'.$search.'%';if($digits!=='')$params['cnpj']='%'.$digits.'%';}$count=$this->connection->prepare('SELECT COUNT(*) FROM fornecedores'.$where);$count->execute($params);$statement=$this->connection->prepare('SELECT id,nome,cnpj,contato,telefone FROM fornecedores'.$where.' ORDER BY nome ASC LIMIT :limit OFFSET :offset');foreach($params as $key=>$value)$statement->bindValue(':'.$key,$value);$statement->bindValue(':limit',$perPage,PDO::PARAM_INT);$statement->bindValue(':offset',($page-1)*$perPage,PDO::PARAM_INT);$statement->execute();return ['items'=>array_map(static fn(array $r):SoSupplierData=>SoSupplierData::fromArray($r),$statement->fetchAll()),'total'=>(int)$count->fetchColumn()]; }
    public function findById(int $id): ?SoSupplierData { if($id<=0)return null;$stmt=$this->connection->prepare('SELECT id,nome,cnpj,contato,telefone FROM fornecedores WHERE id=:id LIMIT 1');$stmt->execute(['id'=>$id]);$row=$stmt->fetch();return $row===false?null:SoSupplierData::fromArray($row); }
}
