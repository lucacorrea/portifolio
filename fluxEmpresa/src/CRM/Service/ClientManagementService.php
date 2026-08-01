<?php

declare(strict_types=1);

namespace App\CRM\Service;

use App\CRM\DTO\ClientFormData;
use App\CRM\Entity\Client;
use App\CRM\Repository\ClientRepository;
use InvalidArgumentException;

final class ClientManagementService
{
    public function __construct(private readonly ClientRepository $clients)
    {
    }

    /** @return Client[] */
    public function listClients(array $filters = []): array
    {
        return $this->clients->findAll($filters);
    }

    /** @return array{total:int,active:int,inactive:int,new_month:int} */
    public function clientSummary(): array
    {
        return $this->clients->summary();
    }

    public function getClient(int $id): Client
    {
        $client = $this->clients->findById($id);
        if ($client === null) {
            throw new InvalidArgumentException('Cliente não encontrado.');
        }
        return $client;
    }

    public function createClient(ClientFormData $data): Client
    {
        $this->assertUniqueDocument($data->document());
        return $this->clients->create($data);
    }

    public function updateClient(int $id, ClientFormData $data): void
    {
        $this->getClient($id);
        $this->assertUniqueDocument($data->document(), $id);
        $this->clients->update($id, $data);
    }

    public function changeClientStatus(int $id, string $status): void
    {
        $this->getClient($id);
        $this->clients->changeStatus($id, $status);
    }

    public function deleteClient(int $id, int $userId): void
    {
        $this->clients->softDelete($id, $userId);
    }

    private function assertUniqueDocument(?string $document, ?int $ignoreId = null): void
    {
        if ($document === null || $document === '') return;
        if ($this->clients->existsByDocument($document, $ignoreId)) {
            throw new InvalidArgumentException('Já existe um cliente com este CPF/CNPJ.');
        }
    }
}
