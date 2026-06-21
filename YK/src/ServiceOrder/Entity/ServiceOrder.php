<?php

declare(strict_types=1);

namespace App\ServiceOrder\Entity;

use InvalidArgumentException;

final class ServiceOrder
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $number,
        private readonly int $clientId,
        private readonly string $clientName,
        private readonly ?int $budgetId,
        private readonly ?int $primaryEmployeeId,
        private readonly ?string $primaryEmployeeCode,
        private readonly ?string $primaryEmployeeName,
        private readonly ?int $supportEmployeeId,
        private readonly ?string $supportEmployeeCode,
        private readonly ?string $supportEmployeeName,
        private readonly ?string $scheduledStart,
        private readonly ?string $scheduledEnd,
        private readonly string $status,
        private readonly string $priority,
        private readonly ?string $notes,
        private readonly string $createdAt,
        private readonly string $updatedAt
    ) {
        if ($this->id <= 0 || $this->clientId <= 0) {
            throw new InvalidArgumentException('Ordem de serviço inválida.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            number: isset($data['numero']) ? (string) $data['numero'] : null,
            clientId: (int) ($data['cliente_id'] ?? 0),
            clientName: (string) ($data['cliente_nome'] ?? ''),
            budgetId: isset($data['orcamento_id']) ? (int) $data['orcamento_id'] : null,
            primaryEmployeeId: isset($data['funcionario_principal_id']) ? (int) $data['funcionario_principal_id'] : null,
            primaryEmployeeCode: isset($data['funcionario_principal_codigo']) ? (string) $data['funcionario_principal_codigo'] : null,
            primaryEmployeeName: isset($data['funcionario_principal_nome']) ? (string) $data['funcionario_principal_nome'] : null,
            supportEmployeeId: isset($data['funcionario_apoio_id']) ? (int) $data['funcionario_apoio_id'] : null,
            supportEmployeeCode: isset($data['funcionario_apoio_codigo']) ? (string) $data['funcionario_apoio_codigo'] : null,
            supportEmployeeName: isset($data['funcionario_apoio_nome']) ? (string) $data['funcionario_apoio_nome'] : null,
            scheduledStart: isset($data['agendado_inicio']) ? (string) $data['agendado_inicio'] : null,
            scheduledEnd: isset($data['agendado_fim']) ? (string) $data['agendado_fim'] : null,
            status: (string) ($data['status'] ?? 'aberta'),
            priority: (string) ($data['prioridade'] ?? 'media'),
            notes: isset($data['observacoes']) ? (string) $data['observacoes'] : null,
            createdAt: (string) ($data['criado_em'] ?? ''),
            updatedAt: (string) ($data['atualizado_em'] ?? '')
        );
    }

    public function id(): int { return $this->id; }
    public function number(): ?string { return $this->number; }
    public function displayNumber(): string { return $this->number ?? sprintf('OS-%06d', $this->id); }
    public function clientId(): int { return $this->clientId; }
    public function clientName(): string { return $this->clientName; }
    public function budgetId(): ?int { return $this->budgetId; }
    public function primaryEmployeeId(): ?int { return $this->primaryEmployeeId; }
    public function supportEmployeeId(): ?int { return $this->supportEmployeeId; }
    public function scheduledStart(): ?string { return $this->scheduledStart; }
    public function scheduledEnd(): ?string { return $this->scheduledEnd; }
    public function status(): string { return $this->status; }
    public function priority(): string { return $this->priority; }
    public function notes(): ?string { return $this->notes; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }

    public function displayPrimaryEmployee(): ?string
    {
        return $this->displayEmployee($this->primaryEmployeeCode, $this->primaryEmployeeId, $this->primaryEmployeeName);
    }

    public function displaySupportEmployee(): ?string
    {
        return $this->displayEmployee($this->supportEmployeeCode, $this->supportEmployeeId, $this->supportEmployeeName);
    }

    private function displayEmployee(?string $code, ?int $id, ?string $name): ?string
    {
        if ($id === null || $name === null || $name === '') {
            return null;
        }

        return ($code ?: sprintf('FUN-%06d', $id)) . ' — ' . $name;
    }
}
