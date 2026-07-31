<?php
declare(strict_types=1);

namespace App\Integration\SO\Service;

use App\Integration\SO\DTO\SoSupplierData;
use App\Integration\SO\Repository\SoSupplierRepository;
use App\Integration\SO\SoDatabase;

final class SoSupplierService
{
    private ?SoSupplierRepository $suppliers = null;
    public function __construct(private readonly SoDatabase $database) {}
    /** @return array{items:SoSupplierData[],total:int} */
    public function search(array $filters=[]): array { $search=trim((string)($filters['search']??''));if(strlen($search)>150)throw new \InvalidArgumentException('Busca inválida.');return $this->repository()->search($search,(int)($filters['page']??1),(int)($filters['per_page']??20)); }
    public function find(int $id): ?SoSupplierData { if($id<=0)throw new \InvalidArgumentException('Fornecedor inválido.');return $this->repository()->findById($id); }
    private function repository(): SoSupplierRepository { return $this->suppliers ??= new SoSupplierRepository($this->database->connection()); }
}
