<?php
declare(strict_types=1);
namespace App\Integration\SO\Service;
use App\Integration\SO\DTO\SoSupplierData;
use App\Integration\SO\Repository\SoSupplierRepository;
use App\Integration\SO\SoDatabase;
use App\Integration\SO\SoIntegrationException;
use Throwable;
final class SoSupplierService
{
    private ?SoSupplierRepository $repository = null;
    public function __construct(private readonly SoDatabase $database) {}
    public function paginate(string $search, int $page, int $perPage): array { return $this->execute('paginate', fn(): array => $this->repository()->paginate(trim($search), max(1, $page), min(100, max(1, $perPage)))); }
    public function count(string $search): int { return $this->execute('count', fn(): int => $this->repository()->count(trim($search))); }
    public function findById(int $id): ?SoSupplierData { return $id > 0 ? $this->execute('find', fn(): ?SoSupplierData => $this->repository()->findById($id)) : null; }
    private function repository(): SoSupplierRepository { return $this->repository ??= new SoSupplierRepository($this->database->connection()); }
    private function execute(string $operation, callable $callback): mixed
    {
        try {
            return $callback();
        } catch (Throwable $exception) {
            error_log('SO supplier query failed. operation=' . $operation . ' type=' . $exception::class . ' code=' . $exception->getCode() . '.');
            if ($exception instanceof SoIntegrationException) throw $exception;
            throw new SoIntegrationException(reason: 'query_failed', previous: $exception);
        }
    }
}
