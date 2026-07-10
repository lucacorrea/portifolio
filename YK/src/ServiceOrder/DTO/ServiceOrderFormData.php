<?php

declare(strict_types=1);

namespace App\ServiceOrder\DTO;

use InvalidArgumentException;

final class ServiceOrderFormData
{
    private const STATUSES = ['rascunho', 'aberta', 'aguardando_agendamento', 'agendada', 'em_deslocamento', 'em_execucao', 'aguardando_peca', 'finalizada', 'cancelada'];
    private const INITIAL_STATUSES = ['rascunho', 'aberta', 'aguardando_agendamento', 'agendada'];

    /** @param ServiceOrderItemData[] $items */
    public function __construct(
        private readonly int $clientId,
        private readonly ?int $budgetId,
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
        private readonly string $discount,
        private readonly string $increase,
        private readonly array $items
    ) {
    }

    public static function fromArray(array $data, bool $editing = false): self
    {
        $status = (string) ($data['status'] ?? 'aberta');
        if ($editing) {
            if (!in_array($status, self::STATUSES, true)) {
                throw new InvalidArgumentException('Status da OS inválido.');
            }
        } elseif (!in_array($status, self::INITIAL_STATUSES, true)) {
            throw new InvalidArgumentException('Status inicial inválido para OS.');
        }

        $priority = (string) ($data['priority'] ?? $data['prioridade'] ?? 'media');
        if (!in_array($priority, ['baixa', 'media', 'alta', 'urgente'], true)) {
            throw new InvalidArgumentException('Prioridade inválida.');
        }

        $items = [];
        foreach (self::normalizeItems($data['items'] ?? $data['itens'] ?? []) as $index => $item) {
            $items[] = ServiceOrderItemData::fromArray($item, $index);
        }
        if ($status !== 'rascunho' && $items === []) {
            throw new InvalidArgumentException('Inclua pelo menos um item na OS.');
        }

        return new self(
            clientId: self::positiveInt($data['client_id'] ?? $data['cliente_id'] ?? null, 'cliente'),
            budgetId: self::optionalPositiveInt($data['budget_id'] ?? $data['orcamento_id'] ?? null),
            status: $status,
            priority: $priority,
            equipmentType: self::optionalSimpleText($data['equipment_type'] ?? $data['equipamento_tipo'] ?? null, 100),
            equipmentBrand: self::optionalSimpleText($data['equipment_brand'] ?? $data['equipamento_marca'] ?? null, 100),
            equipmentModel: self::optionalSimpleText($data['equipment_model'] ?? $data['equipamento_modelo'] ?? null, 100),
            equipmentCapacity: self::optionalSimpleText($data['equipment_capacity'] ?? $data['equipamento_capacidade'] ?? null, 100),
            equipmentSerialNumber: self::optionalSimpleText($data['equipment_serial_number'] ?? $data['equipamento_numero_serie'] ?? null, 100),
            equipmentEnvironment: self::optionalSimpleText($data['equipment_environment'] ?? $data['equipamento_ambiente'] ?? null, 100),
            equipmentLocation: self::optionalSimpleText($data['equipment_location'] ?? $data['equipamento_local'] ?? null, 150),
            reportedProblem: self::optionalLongText($data['reported_problem'] ?? $data['problema_relatado'] ?? null),
            identifiedProblem: self::optionalLongText($data['identified_problem'] ?? $data['problema_identificado'] ?? null),
            diagnosis: self::optionalLongText($data['diagnosis'] ?? $data['diagnostico'] ?? null),
            solution: self::optionalLongText($data['solution'] ?? $data['solucao'] ?? null),
            recommendation: self::optionalLongText($data['recommendation'] ?? $data['recomendacao'] ?? null),
            internalNotes: self::optionalLongText($data['internal_notes'] ?? $data['observacoes_internas'] ?? null),
            notes: self::optionalLongText($data['notes'] ?? $data['observacoes'] ?? null),
            discount: self::decimal($data['discount'] ?? $data['desconto'] ?? '0', 'desconto'),
            increase: self::decimal($data['increase'] ?? $data['acrescimo'] ?? '0', 'acréscimo'),
            items: $items
        );
    }

    public function clientId(): int { return $this->clientId; }
    public function budgetId(): ?int { return $this->budgetId; }
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
    public function discount(): string { return $this->discount; }
    public function increase(): string { return $this->increase; }
    /** @return ServiceOrderItemData[] */
    public function items(): array { return $this->items; }

    /** @return array{services:string,products:string,others:string,total:string} */
    public function totals(): array
    {
        $services = 0.0;
        $products = 0.0;
        $others = 0.0;
        foreach ($this->items as $item) {
            if ($item->type() === 'servico') $services += (float) $item->subtotal();
            if ($item->type() === 'produto') $products += (float) $item->subtotal();
            if ($item->type() === 'outro') $others += (float) $item->subtotal();
        }
        $total = max(0.0, $services + $products + $others - (float) $this->discount + (float) $this->increase);
        return [
            'services' => number_format($services, 2, '.', ''),
            'products' => number_format($products, 2, '.', ''),
            'others' => number_format($others, 2, '.', ''),
            'total' => number_format($total, 2, '.', ''),
        ];
    }

    private static function normalizeItems(mixed $items): array
    {
        if (!is_array($items)) return [];
        $normalized = [];
        foreach ($items as $item) {
            if (is_array($item) && trim((string) ($item['description'] ?? $item['descricao'] ?? '')) !== '') {
                $normalized[] = $item;
            }
        }
        return $normalized;
    }

    private static function positiveInt(mixed $value, string $field): int
    {
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($int)) throw new InvalidArgumentException('Informe um ' . $field . ' válido.');
        return $int;
    }

    private static function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
        $int = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return is_int($int) ? $int : null;
    }

    private static function optionalSimpleText(mixed $value, int $maxLength): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        if (str_contains($text, "\0") || $text !== strip_tags($text) || mb_strlen($text) > $maxLength) {
            throw new InvalidArgumentException('Há campos com texto inválido.');
        }
        return $text;
    }

    private static function optionalLongText(mixed $value): ?string
    {
        $text = trim((string) ($value ?? ''));
        if ($text === '') return null;
        if (str_contains($text, "\0")) throw new InvalidArgumentException('Há campos com texto inválido.');
        return $text;
    }

    private static function decimal(mixed $value, string $field): string
    {
        $value = str_replace(' ', '', trim((string) $value));
        if ($value === '') $value = '0';
        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }
        if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Informe um valor válido para ' . $field . '.');
        }
        return number_format((float) $value, 2, '.', '');
    }
}
