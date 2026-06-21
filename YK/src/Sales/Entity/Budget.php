<?php

declare(strict_types=1);

namespace App\Sales\Entity;

use InvalidArgumentException;

final class Budget
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $number,
        private readonly int $clientId,
        private readonly string $clientCode,
        private readonly string $clientName,
        private readonly ?string $clientDocument,
        private readonly ?int $responsibleId,
        private readonly ?string $responsibleName,
        private readonly string $issueDate,
        private readonly string $validUntil,
        private readonly string $status,
        private readonly ?string $notes,
        private readonly ?string $rejectionReason,
        private readonly string $servicesSubtotal,
        private readonly string $productsSubtotal,
        private readonly string $othersSubtotal,
        private readonly string $discount,
        private readonly string $increase,
        private readonly string $total,
        private readonly ?string $approvedAt,
        private readonly ?string $rejectedAt,
        private readonly string $createdAt,
        private readonly string $updatedAt,
        private readonly int $itemsCount
    ) {
        if ($this->id <= 0 || $this->clientId <= 0) {
            throw new InvalidArgumentException('Orçamento inválido.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            number: isset($data['numero']) ? (string) $data['numero'] : null,
            clientId: (int) ($data['cliente_id'] ?? 0),
            clientCode: (string) ($data['cliente_codigo'] ?? ''),
            clientName: (string) ($data['cliente_nome'] ?? ''),
            clientDocument: isset($data['cliente_documento']) ? (string) $data['cliente_documento'] : null,
            responsibleId: isset($data['responsavel_id']) ? (int) $data['responsavel_id'] : null,
            responsibleName: isset($data['responsavel_nome']) ? (string) $data['responsavel_nome'] : null,
            issueDate: (string) ($data['data_emissao'] ?? ''),
            validUntil: (string) ($data['validade'] ?? ''),
            status: (string) ($data['status'] ?? 'rascunho'),
            notes: isset($data['observacoes']) ? (string) $data['observacoes'] : null,
            rejectionReason: isset($data['motivo_recusa']) ? (string) $data['motivo_recusa'] : null,
            servicesSubtotal: (string) ($data['subtotal_servicos'] ?? '0.00'),
            productsSubtotal: (string) ($data['subtotal_produtos'] ?? '0.00'),
            othersSubtotal: (string) ($data['subtotal_outros'] ?? '0.00'),
            discount: (string) ($data['desconto'] ?? '0.00'),
            increase: (string) ($data['acrescimo'] ?? '0.00'),
            total: (string) ($data['total'] ?? '0.00'),
            approvedAt: isset($data['aprovado_em']) ? (string) $data['aprovado_em'] : null,
            rejectedAt: isset($data['recusado_em']) ? (string) $data['recusado_em'] : null,
            createdAt: (string) ($data['criado_em'] ?? ''),
            updatedAt: (string) ($data['atualizado_em'] ?? ''),
            itemsCount: (int) ($data['itens_total'] ?? 0)
        );
    }

    public function id(): int { return $this->id; }
    public function number(): ?string { return $this->number; }
    public function displayNumber(): string { return $this->number ?? sprintf('ORC-%06d', $this->id); }
    public function clientId(): int { return $this->clientId; }
    public function clientCode(): string { return $this->clientCode; }
    public function clientName(): string { return $this->clientName; }
    public function clientDocument(): ?string { return $this->clientDocument; }
    public function responsibleId(): ?int { return $this->responsibleId; }
    public function responsibleName(): ?string { return $this->responsibleName; }
    public function issueDate(): string { return $this->issueDate; }
    public function validUntil(): string { return $this->validUntil; }
    public function status(): string { return $this->status; }
    public function notes(): ?string { return $this->notes; }
    public function rejectionReason(): ?string { return $this->rejectionReason; }
    public function servicesSubtotal(): string { return $this->servicesSubtotal; }
    public function productsSubtotal(): string { return $this->productsSubtotal; }
    public function othersSubtotal(): string { return $this->othersSubtotal; }
    public function discount(): string { return $this->discount; }
    public function increase(): string { return $this->increase; }
    public function total(): string { return $this->total; }
    public function approvedAt(): ?string { return $this->approvedAt; }
    public function rejectedAt(): ?string { return $this->rejectedAt; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }
    public function itemsCount(): int { return $this->itemsCount; }

    public function displayStatus(): string
    {
        if ($this->isExpired()) return 'vencido';
        return $this->status;
    }

    public function isExpired(): bool
    {
        return in_array($this->status, ['enviado', 'aguardando_aprovacao'], true)
            && $this->validUntil < date('Y-m-d');
    }
}
