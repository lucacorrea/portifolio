<?php

declare(strict_types=1);

namespace App\Catalog\Service;

use App\Catalog\DTO\ServiceFormData;
use App\Catalog\Entity\ServiceDefinition;
use App\Catalog\Repository\ServiceRepository;
use InvalidArgumentException;

final class ServiceManagementService
{
    public function __construct(
        private readonly ServiceRepository $services
    ) {
    }

    /** @return ServiceDefinition[] */
    public function listServices(array $filters = []): array
    {
        return $this->services->findAll($filters);
    }

    /** @return array{total:int,active:int,inactive:int} */
    public function serviceSummary(): array
    {
        return $this->services->summary();
    }

    public function getService(int $id): ServiceDefinition
    {
        $service = $this->services->findById($id);

        if ($service === null) {
            throw new InvalidArgumentException('Serviço não encontrado.');
        }

        return $service;
    }

    public function createService(ServiceFormData $data): ServiceDefinition
    {
        return $this->services->create($data);
    }

    public function updateService(
        int $id,
        ServiceFormData $data
    ): void {
        $this->getService($id);
        $this->services->update($id, $data);
    }

    public function deleteService(int $id, int $userId): void
    {
        $this->services->softDelete($id, $userId);
    }
}
