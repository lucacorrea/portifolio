<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use InvalidArgumentException;

final class FiscalTechnicalResponsible
{
    public function __construct(
        private readonly string $cnpj,
        private readonly string $contactName,
        private readonly string $email,
        private readonly string $phone,
        private readonly string $cpf = '',
        private readonly string $csrtId = '',
        private readonly string $csrt = ''
    ) {
        $this->assertValid();
    }

    public static function fromEnvironment(): self
    {
        return new self(
            self::envOrDefault('FISCAL_RESP_TEC_CNPJ', '65975879000132'),
            self::envOrDefault('FISCAL_RESP_TEC_CONTATO', 'Lucas de Souza Correa'),
            self::envOrDefault('FISCAL_RESP_TEC_EMAIL', 'ljsolucoestechh@gmail.com'),
            self::envOrDefault('FISCAL_RESP_TEC_FONE', '97991515710'),
            self::envOrDefault('FISCAL_RESP_TEC_CPF', '06736898242'),
            self::envOrDefault('FISCAL_RESP_TEC_ID_CSRT', ''),
            self::envOrDefault('FISCAL_RESP_TEC_CSRT', '')
        );
    }

    public function toNfePhpData(): array
    {
        return [
            'CNPJ' => $this->digits($this->cnpj),
            'xContato' => trim($this->contactName),
            'email' => trim($this->email),
            'fone' => $this->digits($this->phone),
            'idCSRT' => $this->hasCsrt() ? trim($this->csrtId) : null,
            'CSRT' => $this->hasCsrt() ? trim($this->csrt) : null,
        ];
    }

    public function cpf(): string
    {
        return $this->digits($this->cpf);
    }

    private function hasCsrt(): bool
    {
        return trim($this->csrtId) !== '' && trim($this->csrt) !== '';
    }

    private function assertValid(): void
    {
        $cnpj = $this->digits($this->cnpj);

        if (!$this->isValidCnpj($cnpj)) {
            throw new InvalidArgumentException(
                'CNPJ do responsável técnico inválido.'
            );
        }

        $contact = trim($this->contactName);

        if ($contact === '' || $this->length($contact) > 60) {
            throw new InvalidArgumentException(
                'Nome do contato do responsável técnico inválido.'
            );
        }

        $email = trim($this->email);

        if (
            filter_var($email, FILTER_VALIDATE_EMAIL) === false
            || $this->length($email) > 60
        ) {
            throw new InvalidArgumentException(
                'E-mail do responsável técnico inválido.'
            );
        }

        $phone = $this->digits($this->phone);

        if (strlen($phone) < 6 || strlen($phone) > 14) {
            throw new InvalidArgumentException(
                'Telefone do responsável técnico inválido.'
            );
        }

        $hasId = trim($this->csrtId) !== '';
        $hasToken = trim($this->csrt) !== '';

        if ($hasId xor $hasToken) {
            throw new InvalidArgumentException(
                'idCSRT e CSRT devem ser configurados juntos.'
            );
        }

        if (
            $hasId
            && preg_match('/^\d{1,2}$/', trim($this->csrtId)) !== 1
        ) {
            throw new InvalidArgumentException(
                'Identificador CSRT inválido.'
            );
        }
    }

    private function isValidCnpj(string $cnpj): bool
    {
        if (
            strlen($cnpj) !== 14
            || preg_match('/^(\d)\1{13}$/', $cnpj) === 1
        ) {
            return false;
        }

        $first = $this->cnpjDigit(
            substr($cnpj, 0, 12),
            [5,4,3,2,9,8,7,6,5,4,3,2]
        );

        $second = $this->cnpjDigit(
            substr($cnpj, 0, 12) . $first,
            [6,5,4,3,2,9,8,7,6,5,4,3,2]
        );

        return substr($cnpj, -2) === $first . $second;
    }

    private function cnpjDigit(string $base, array $weights): string
    {
        $sum = 0;

        foreach (str_split($base) as $index => $digit) {
            $sum += (int) $digit * $weights[$index];
        }

        $remainder = $sum % 11;

        return $remainder < 2
            ? '0'
            : (string) (11 - $remainder);
    }

    private static function envOrDefault(
        string $name,
        string $default
    ): string {
        $value = getenv($name);

        return is_string($value) && trim($value) !== ''
            ? trim($value)
            : $default;
    }

    private function digits(string $value): string
    {
        return preg_replace('/\D+/', '', $value) ?? '';
    }

    private function length(string $value): int
    {
        return function_exists('mb_strlen')
            ? mb_strlen($value, 'UTF-8')
            : strlen($value);
    }
}
