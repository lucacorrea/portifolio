<?php

declare(strict_types=1);

namespace App\DTO;

use DateTimeImmutable;

final readonly class ComidaMesaCompetenciaData
{
    /** @param array<string,mixed> $source */
    public static function fromArray(array $source): self
    {
        $errors = [];

        return new self(
            self::intOrNull($source['competencia_id'] ?? null),
            self::intValue($source['mes'] ?? null),
            self::intValue($source['ano'] ?? null),
            trim((string) ($source['status'] ?? 'planejada')),
            self::dateOrNull($source['inicio_entregas'] ?? null, 'inicio_entregas', $errors),
            self::dateOrNull($source['fim_entregas'] ?? null, 'fim_entregas', $errors),
            self::textOrNull($source['observacao'] ?? null),
            $errors,
        );
    }

    /** @param array<string,string> $fieldErrors */
    public function __construct(
        public ?int $id,
        public int $month,
        public int $year,
        public string $status,
        public ?string $startsAt,
        public ?string $endsAt,
        public ?string $observation,
        public array $fieldErrors = [],
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

    /** @param array<string,string> $errors */
    private static function dateOrNull(mixed $value, string $field, array &$errors): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $lastErrors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($lastErrors !== false && ($lastErrors['warning_count'] > 0 || $lastErrors['error_count'] > 0))) {
            $errors[$field] = 'Informe uma data válida.';
            return null;
        }

        return $date->format('Y-m-d');
    }

    private static function textOrNull(mixed $value): ?string
    {
        $value = mb_substr(trim((string) $value), 0, 255);

        return $value === '' ? null : $value;
    }
}
