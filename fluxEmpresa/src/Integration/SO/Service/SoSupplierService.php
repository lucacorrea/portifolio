<?php
declare(strict_types=1);
namespace App\Integration\SO\Service;
use App\Integration\SO\DTO\SoSupplierData;
use App\Integration\SO\Repository\SoSupplierRepository;
use App\Integration\SO\SoDatabase;
final class SoSupplierService
{
    private ?SoSupplierRepository $repository = null;
    public function __construct(private readonly SoDatabase $database) {}
    public function paginate(string $search, int $page, int $perPage): array { return $this->repository()->paginate(trim($search), max(1, $page), min(100, max(1, $perPage))); }
    public function count(string $search): int { return $this->repository()->count(trim($search)); }
    public function findById(int $id): ?SoSupplierData { return $id > 0 ? $this->repository()->findById($id) : null; }
    private function repository(): SoSupplierRepository { return $this->repository ??= new SoSupplierRepository($this->database->connection()); }
}
