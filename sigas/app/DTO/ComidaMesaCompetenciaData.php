<?php

declare(strict_types=1);

namespace App\DTO;

final readonly class ComidaMesaCompetenciaData
{
    /** @param array<string,mixed> $source */
    public static function fromArray(array $source): self
    {
        return new self(
            self::intOrNull($source['competencia_id'] ?? null),
            self::intValue($source['mes'] ?? null),
            self::intValue($source['ano'] ?? null),
            trim((string) ($source['status'] ?? 'planejada')),
            self::dateOrNull($source['inicio_entregas'] ?? null),
            self::dateOrNull($source['fim_entregas'] ?? null),
            self::textOrNull($source['observacao'] ?? null),
        );
    }

    public function __construct(
        public ?int $id,
        public int $month,
        public int $year,
        public string $status,
        public ?string $startsAt,
        public ?string $endsAt,
        public ?string $observation,
    ) {
    }

    private static function intValue(mixed $value): int
    {
        return is_string($value) && preg_match('/^\d+$/', $value) === 1 ? (int) $value : (int) $value;
    }

    private static function intOrNull(mixed $value): ?int
    {
        $value = self::intValue($value);

        return $value > 0 ? $value : null;
    }

    private static function dateOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }

    private static function textOrNull(mixed $value): ?string
    {
        $value = mb_substr(trim((string) $value), 0, 255);

        return $value === '' ? null : $value;
    }
}
