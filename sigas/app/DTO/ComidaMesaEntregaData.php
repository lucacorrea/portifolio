<?php

declare(strict_types=1);

namespace App\DTO;

use App\Core\Validator;

final readonly class ComidaMesaEntregaData
{
    /** @param array<string,mixed> $source */
    public static function fromArray(array $source): self
    {
        return new self(
            self::intValue($source['inscricao_id'] ?? null),
            self::intValue($source['competencia_id'] ?? null),
            trim((string) ($source['recebedor_nome'] ?? '')),
            self::nullableCpf($source['recebedor_cpf'] ?? null),
            self::nullableText($source['recebedor_parentesco'] ?? null, 60),
            self::nullableText($source['observacao'] ?? null, 255),
        );
    }

    public function __construct(
        public int $registrationId,
        public int $competenceId,
        public string $receiverName,
        public ?string $receiverCpf,
        public ?string $receiverKinship,
        public ?string $observation,
    ) {
    }

    private static function intValue(mixed $value): int
    {
        return is_string($value) && preg_match('/^\d+$/', $value) === 1 ? (int) $value : (int) $value;
    }

    private static function nullableCpf(mixed $value): ?string
    {
        $digits = Validator::onlyDigits((string) $value);

        return $digits === '' ? null : $digits;
    }

    private static function nullableText(mixed $value, int $max): ?string
    {
        $value = mb_substr(trim((string) $value), 0, $max);

        return $value === '' ? null : $value;
    }
}
