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
        private readonly ?string $clientAddress,
        private readonly ?string $clientNumber,
        private readonly ?string $clientDistrict,
        private readonly ?string $clientCity,
        private readonly ?string $clientState,
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
        private readonly ?string $equipmentType,
        private readonly ?string $equipmentBrand,
        private readonly ?string $equipmentModel,
        private readonly ?string $equipmentCapacity,
        private readonly ?string $equipmentSerialNumber,
        private readonly ?string $equipmentEnvironment,
        private readonly ?string $equipmentLocation,
        private readonly ?string $reportedProblem,
        private readonly ?string $identifiedProblem,
        private readonly ?string $diagnosis,
        private readonly ?string $solution,
        private readonly ?string $recommendation,
        private readonly ?string $internalNotes,
        private readonly ?string $notes,
        private readonly string $servicesSubtotal,
        private readonly string $productsSubtotal,
        private readonly string $othersSubtotal,
        private readonly string $discount,
        private readonly string $increase,
        private readonly string $total,
        private readonly ?string $finishedAt,
        private readonly ?string $canceledAt,
        private readonly string $createdAt,
        private readonly string $updatedAt,
        private readonly int $itemsCount,
        private readonly ?string $mainService
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
            clientAddress: isset($data['cliente_endereco']) ? (string) $data['cliente_endereco'] : null,
            clientNumber: isset($data['cliente_numero']) ? (string) $data['cliente_numero'] : null,
            clientDistrict: isset($data['cliente_bairro']) ? (string) $data['cliente_bairro'] : null,
            clientCity: isset($data['cliente_cidade']) ? (string) $data['cliente_cidade'] : null,
            clientState: isset($data['cliente_uf']) ? (string) $data['cliente_uf'] : null,
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
            equipmentType: isset($data['equipamento_tipo']) ? (string) $data['equipamento_tipo'] : null,
            equipmentBrand: isset($data['equipamento_marca']) ? (string) $data['equipamento_marca'] : null,
            equipmentModel: isset($data['equipamento_modelo']) ? (string) $data['equipamento_modelo'] : null,
            equipmentCapacity: isset($data['equipamento_capacidade']) ? (string) $data['equipamento_capacidade'] : null,
            equipmentSerialNumber: isset($data['equipamento_numero_serie']) ? (string) $data['equipamento_numero_serie'] : null,
            equipmentEnvironment: isset($data['equipamento_ambiente']) ? (string) $data['equipamento_ambiente'] : null,
            equipmentLocation: isset($data['equipamento_local']) ? (string) $data['equipamento_local'] : null,
            reportedProblem: isset($data['problema_relatado']) ? (string) $data['problema_relatado'] : null,
            identifiedProblem: isset($data['problema_identificado']) ? (string) $data['problema_identificado'] : null,
            diagnosis: isset($data['diagnostico']) ? (string) $data['diagnostico'] : null,
            solution: isset($data['solucao']) ? (string) $data['solucao'] : null,
            recommendation: isset($data['recomendacao']) ? (string) $data['recomendacao'] : null,
            internalNotes: isset($data['observacoes_internas']) ? (string) $data['observacoes_internas'] : null,
            notes: isset($data['observacoes']) ? (string) $data['observacoes'] : null,
            servicesSubtotal: (string) ($data['subtotal_servicos'] ?? '0.00'),
            productsSubtotal: (string) ($data['subtotal_produtos'] ?? '0.00'),
            othersSubtotal: (string) ($data['subtotal_outros'] ?? '0.00'),
            discount: (string) ($data['desconto'] ?? '0.00'),
            increase: (string) ($data['acrescimo'] ?? '0.00'),
            total: (string) ($data['total'] ?? '0.00'),
            finishedAt: isset($data['finalizada_em']) ? (string) $data['finalizada_em'] : null,
            canceledAt: isset($data['cancelada_em']) ? (string) $data['cancelada_em'] : null,
            createdAt: (string) ($data['criado_em'] ?? ''),
            updatedAt: (string) ($data['atualizado_em'] ?? ''),
            itemsCount: (int) ($data['itens_total'] ?? 0),
            mainService: isset($data['servico_principal']) ? (string) $data['servico_principal'] : null
        );
    }

    public function id(): int { return $this->id; }
    public function number(): ?string { return $this->number; }
    public function displayNumber(): string { return $this->number ?? sprintf('OS-%06d', $this->id); }
    public function clientId(): int { return $this->clientId; }
    public function clientName(): string { return $this->clientName; }
    public function clientAddress(): ?string { return $this->clientAddress; }
    public function clientNumber(): ?string { return $this->clientNumber; }
    public function clientDistrict(): ?string { return $this->clientDistrict; }
    public function clientCity(): ?string { return $this->clientCity; }
    public function clientState(): ?string { return $this->clientState; }
    public function budgetId(): ?int { return $this->budgetId; }
    public function primaryEmployeeId(): ?int { return $this->primaryEmployeeId; }
    public function supportEmployeeId(): ?int { return $this->supportEmployeeId; }
    public function scheduledStart(): ?string { return $this->scheduledStart; }
    public function scheduledEnd(): ?string { return $this->scheduledEnd; }
    public function status(): string { return $this->status; }
    public function priority(): string { return $this->priority; }
    public function equipmentType(): ?string { return $this->equipmentType; }
    public function equipmentBrand(): ?string { return $this->equipmentBrand; }
    public function equipmentModel(): ?string { return $this->equipmentModel; }
    public function equipmentCapacity(): ?string { return $this->equipmentCapacity; }
    public function equipmentSerialNumber(): ?string { return $this->equipmentSerialNumber; }
    public function equipmentEnvironment(): ?string { return $this->equipmentEnvironment; }
    public function equipmentLocation(): ?string { return $this->equipmentLocation; }
    public function reportedProblem(): ?string { return $this->reportedProblem; }
    public function identifiedProblem(): ?string { return $this->identifiedProblem; }
    public function diagnosis(): ?string { return $this->diagnosis; }
    public function solution(): ?string { return $this->solution; }
    public function recommendation(): ?string { return $this->recommendation; }
    public function internalNotes(): ?string { return $this->internalNotes; }
    public function notes(): ?string { return $this->notes; }
    public function servicesSubtotal(): string { return $this->servicesSubtotal; }
    public function productsSubtotal(): string { return $this->productsSubtotal; }
    public function othersSubtotal(): string { return $this->othersSubtotal; }
    public function discount(): string { return $this->discount; }
    public function increase(): string { return $this->increase; }
    public function total(): string { return $this->total; }
    public function finishedAt(): ?string { return $this->finishedAt; }
    public function canceledAt(): ?string { return $this->canceledAt; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }
    public function itemsCount(): int { return $this->itemsCount; }
    public function mainService(): ?string { return $this->mainService; }

    public function displayStatus(): string
    {
        return [
            'rascunho' => 'Rascunho',
            'aberta' => 'Aberta',
            'aguardando_agendamento' => 'Aguardando agendamento',
            'agendada' => 'Agendada',
            'em_deslocamento' => 'Em deslocamento',
            'em_execucao' => 'Em execução',
            'aguardando_peca' => 'Aguardando peça',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
        ][$this->status] ?? 'Aberta';
    }

    public function displayPriority(): string
    {
        return [
            'baixa' => 'Baixa',
            'media' => 'Média',
            'alta' => 'Alta',
            'urgente' => 'Urgente',
        ][$this->priority] ?? 'Média';
    }

    public function displaySchedule(): string
    {
        if ($this->scheduledStart === null || $this->scheduledEnd === null) {
            return 'Sem agendamento';
        }

        try {
            return (new \DateTimeImmutable($this->scheduledStart))->format('d/m/Y H:i')
                . ' - '
                . (new \DateTimeImmutable($this->scheduledEnd))->format('H:i');
        } catch (\Throwable) {
            return 'Sem agendamento';
        }
    }

    public function displayPrimaryEmployee(): ?string
    {
        return $this->displayEmployee($this->primaryEmployeeCode, $this->primaryEmployeeId, $this->primaryEmployeeName);
    }

    public function displaySupportEmployee(): ?string
    {
        return $this->displayEmployee($this->supportEmployeeCode, $this->supportEmployeeId, $this->supportEmployeeName);
    }

    public function displayTeam(): string
    {
        $primary = $this->displayPrimaryEmployee();
        $support = $this->displaySupportEmployee();

        if ($primary === null && $support === null) {
            return 'Sem equipe definida';
        }

        return trim(($primary ?? '-') . ' / ' . ($support ?? '-'));
    }

    public function displayEquipment(): string
    {
        $parts = array_filter([$this->equipmentType, $this->equipmentBrand, $this->equipmentModel], static fn(?string $value): bool => $value !== null && $value !== '');
        return $parts === [] ? '-' : implode(' ', $parts);
    }

    private function displayEmployee(?string $code, ?int $id, ?string $name): ?string
    {
        if ($id === null || $name === null || $name === '') {
            return null;
        }

        return ($code ?: sprintf('FUN-%06d', $id)) . ' — ' . $name;
    }
}
