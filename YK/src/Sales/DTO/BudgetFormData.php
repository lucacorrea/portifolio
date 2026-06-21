<?php

declare(strict_types=1);

namespace App\Sales\DTO;

use InvalidArgumentException;

final class BudgetFormData
{
    /** @param BudgetItemData[] $items */
    public function __construct(
        private readonly int $clientId,
        private readonly ?int $responsibleId,
        private readonly string $issueDate,
        private readonly string $validUntil,
        private readonly string $status,
        private readonly ?string $notes,
        private readonly string $discount,
        private readonly string $increase,
        private readonly array $items
    ) {
    }

    public static function fromArray(array $data): self
    {
        $clientId = self::positiveInt($data['client_id'] ?? $data['cliente_id'] ?? null, 'cliente');
        $responsibleId = self::optionalPositiveInt($data['responsible_id'] ?? $data['responsavel_id'] ?? null, 'responsável');
        $issueDate = self::date((string) ($data['issue_date'] ?? $data['data_emissao'] ?? ''), 'data de emissão');
        $validUntil = self::date((string) ($data['valid_until'] ?? $data['validade'] ?? ''), 'validade');

        if ($validUntil < $issueDate) {
            throw new InvalidArgumentException('A validade deve ser igual ou posterior à emissão.');
        }

        $status = (string) ($data['status'] ?? 'rascunho');
        if (!in_array($status, ['rascunho', 'enviado', 'aguardando_aprovacao'], true)) {
            throw new InvalidArgumentException('Status inválido para cadastro ou edição.');
        }

        $items = [];
        foreach (self::normalizeItems($data['items'] ?? $data['itens'] ?? []) as $index => $item) {
            $items[] = BudgetItemData::fromArray($item, $index);
        }

        if ($items === []) {
            throw new InvalidArgumentException('Inclua pelo menos um item no orçamento.');
        }

        return new self(
            clientId: $clientId,
            responsibleId: $responsibleId,
            issueDate: $issueDate,
            validUntil: $validUntil,
            status: $status,
            notes: self::longText($data['notes'] ?? $data['observacoes'] ?? null),
            discount: self::decimal($data['discount'] ?? $data['desconto'] ?? '0', 'desconto geral'),
            increase: self::decimal($data['increase'] ?? $data['acrescimo'] ?? '0', 'acréscimo'),
            items: $items
        );
    }

    public function clientId(): int { return $this->clientId; }
    public function responsibleId(): ?int { return $this->responsibleId; }
    public function issueDate(): string { return $this->issueDate; }
    public function validUntil(): string { return $this->validUntil; }
    public function status(): string { return $this->status; }
    public function notes(): ?string { return $this->notes; }
    public function discount(): string { return $this->discount; }
    public function increase(): string { return $this->increase; }
    /** @return BudgetItemData[] */
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

    private static function optionalPositiveInt(mixed $value, string $field): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
        return self::positiveInt($value, $field);
    }

    private static function date(string $value, string $field): string
    {
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        if (!$date || $date->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('Informe uma ' . $field . ' válida.');
        }
        return $value;
    }

    private static function longText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return null;
        if (str_contains($value, "\0")) throw new InvalidArgumentException('Observações inválidas.');
        return $value;
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
        $number = (float) $value;
        if ($number < 0) throw new InvalidArgumentException('O campo ' . $field . ' não pode ser negativo.');
        return number_format($number, 2, '.', '');
    }
}
