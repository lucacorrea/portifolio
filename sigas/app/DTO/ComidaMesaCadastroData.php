<?php

declare(strict_types=1);

namespace App\DTO;

use App\Core\Validator;

final readonly class ComidaMesaCadastroData
{
    /** @param array<string,mixed> $source */
    public static function fromArray(array $source): self
    {
        return new self(
            self::intOrNull($source['inscricao_id'] ?? null),
            self::text($source['nome'] ?? null),
            Validator::onlyDigits(self::text($source['cpf'] ?? null)),
            self::nullableDigits($source['telefone'] ?? null),
            self::nullableDigits($source['nis'] ?? null),
            self::nullableText($source['rg'] ?? null, 30),
            self::nullableDate($source['data_nascimento'] ?? null),
            self::nullableText($source['email'] ?? null, 180),
            self::nullableText($source['zona'] ?? null, 20),
            self::nullableText($source['logradouro'] ?? null, 180),
            self::nullableText($source['numero'] ?? null, 30),
            self::nullableText($source['complemento'] ?? null, 120),
            self::nullableText($source['bairro'] ?? null, 120),
            self::nullableText($source['comunidade'] ?? null, 150),
            self::nullableText($source['ponto_referencia'] ?? null, 255),
            self::nullableDigits($source['cep'] ?? null),
            self::intOrNull($source['quantidade_membros'] ?? null) ?? 1,
            self::moneyOrNull($source['renda_familiar'] ?? null),
            self::intOrNull($source['polo_id'] ?? null),
            self::nullableText($source['status'] ?? null, 30) ?? 'em_analise',
            self::nullableText($source['prioridade'] ?? null, 20) ?? 'normal',
            self::nullableDate($source['data_inscricao'] ?? null),
            self::nullableText($source['observacao'] ?? null, 1000),
            self::nullableText($source['motivo_suspensao'] ?? null, 255),
        );
    }

    public function __construct(
        public ?int $registrationId,
        public string $name,
        public string $cpf,
        public ?string $phone,
        public ?string $nis,
        public ?string $rg,
        public ?string $birthDate,
        public ?string $email,
        public ?string $zone,
        public ?string $street,
        public ?string $number,
        public ?string $complement,
        public ?string $district,
        public ?string $community,
        public ?string $referencePoint,
        public ?string $zipCode,
        public int $membersCount,
        public ?float $familyIncome,
        public ?int $poleId,
        public string $status,
        public string $priority,
        public ?string $registrationDate,
        public ?string $observation,
        public ?string $suspensionReason,
    ) {
    }

    private static function text(mixed $value): string
    {
        return trim((string) $value);
    }

    private static function nullableText(mixed $value, int $max): ?string
    {
        $value = mb_substr(self::text($value), 0, $max);

        return $value === '' ? null : $value;
    }

    private static function nullableDigits(mixed $value): ?string
    {
        $digits = Validator::onlyDigits(self::text($value));

        return $digits === '' ? null : $digits;
    }

    private static function intOrNull(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        return is_string($value) && preg_match('/^\d+$/', $value) === 1 && (int) $value > 0 ? (int) $value : null;
    }

    private static function moneyOrNull(mixed $value): ?float
    {
        $value = str_replace(',', '.', self::text($value));

        return is_numeric($value) ? round((float) $value, 2) : null;
    }

    private static function nullableDate(mixed $value): ?string
    {
        $value = self::text($value);

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 ? $value : null;
    }
}
