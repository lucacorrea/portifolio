<?php

declare(strict_types=1);

namespace App\Catalog\DTO;

use InvalidArgumentException;

final class ServiceFormData
{
    public function __construct(
        private readonly string $name,
        private readonly ?string $category,
        private readonly ?string $compatibleEquipment,
        private readonly int $durationMinutes,
        private readonly string $value,
        private readonly ?string $description,
        private readonly string $status
    ) {
    }

    public static function fromArray(array $data): self
    {
        return new self(
            self::text((string) ($data['name'] ?? ''), 'nome', 150, true),
            self::text((string) ($data['category'] ?? ''), 'categoria', 100, false),
            self::text((string) ($data['compatible_equipment'] ?? ''), 'equipamentos compatíveis', 255, false),
            self::minutes($data['duration_minutes'] ?? 0),
            self::decimal($data['value'] ?? '0', 'valor'),
            self::longText($data['description'] ?? null),
            self::normalizeStatus((string) ($data['status'] ?? 'ativo'))
        );
    }

    public function name(): string { return $this->name; }
    public function category(): ?string { return $this->category; }
    public function compatibleEquipment(): ?string { return $this->compatibleEquipment; }
    public function durationMinutes(): int { return $this->durationMinutes; }
    public function value(): string { return $this->value; }
    public function description(): ?string { return $this->description; }
    public function status(): string { return $this->status; }

    public function withValue(string $value): self
    {
        return new self(
            $this->name,
            $this->category,
            $this->compatibleEquipment,
            $this->durationMinutes,
            self::decimal($value, 'valor'),
            $this->description,
            $this->status
        );
    }

    private static function text(string $value, string $field, int $max, bool $required): ?string
    {
        if ($value !== strip_tags($value) || str_contains($value, "\0")) {
            throw new InvalidArgumentException('Campo ' . $field . ' inválido.');
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

        if ($value === '') {
            if ($required) {
                throw new InvalidArgumentException('Informe o ' . $field . '.');
            }

            return null;
        }

        if (strlen($value) > $max) {
            throw new InvalidArgumentException('Campo ' . $field . ' excede ' . $max . ' caracteres.');
        }

        return $value;
    }

    private static function longText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        if ($value === '') {
            return null;
        }

        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException('Descrição inválida.');
        }

        return $value;
    }

    private static function minutes(mixed $value): int
    {
        $minutes = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);

        if (!is_int($minutes)) {
            throw new InvalidArgumentException('Duração inválida.');
        }

        return $minutes;
    }

    private static function decimal(mixed $value, string $field): string
    {
        $value = trim((string) $value);
        $value = str_replace(' ', '', $value);

        if ($value === '') {
            $value = '0';
        }

        if (str_contains($value, ',')) {
            $value = str_replace('.', '', $value);
            $value = str_replace(',', '.', $value);
        }

        if (!preg_match('/^\d+(\.\d+)?$/', $value)) {
            throw new InvalidArgumentException('Informe um valor válido para ' . $field . '.');
        }

        $number = (float) $value;

        if ($number < 0) {
            throw new InvalidArgumentException('O campo ' . $field . ' não pode ser negativo.');
        }

        return number_format($number, 2, '.', '');
    }

    private static function normalizeStatus(string $status): string
    {
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            throw new InvalidArgumentException('Status inválido.');
        }

        return $status;
    }
}
