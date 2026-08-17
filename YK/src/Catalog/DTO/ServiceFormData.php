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
        /** @var array<string,string|int|null> */
        private readonly array $fiscal,
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
            self::fiscalData($data),
            self::normalizeStatus((string) ($data['status'] ?? 'ativo'))
        );
    }

    public function name(): string { return $this->name; }
    public function category(): ?string { return $this->category; }
    public function compatibleEquipment(): ?string { return $this->compatibleEquipment; }
    public function durationMinutes(): int { return $this->durationMinutes; }
    public function value(): string { return $this->value; }
    public function description(): ?string { return $this->description; }
    /** @return array<string,string|int|null> */
    public function fiscal(): array { return $this->fiscal; }
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
            $this->fiscal,
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

        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $value)) {
            throw new InvalidArgumentException('Informe um valor válido para ' . $field . '.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        return ltrim($whole, '0') !== '' ? ltrim($whole, '0') . '.' . str_pad(substr($fraction, 0, 2), 2, '0')
            : '0.' . str_pad(substr($fraction, 0, 2), 2, '0');
    }

    private static function normalizeStatus(string $status): string
    {
        if (!in_array($status, ['ativo', 'inativo'], true)) {
            throw new InvalidArgumentException('Status inválido.');
        }

        return $status;
    }

    /** @param array<string,mixed> $data @return array<string,string|int|null> */
    private static function fiscalData(array $data): array
    {
        $patterns = [
            'tax_code'=>['/^\d{6}$/','código de tributação nacional'],
            'nbs'=>['/^\d{9}$/','NBS'],
            'municipality_code'=>['/^\d{7}$/','município de incidência'],
            'pis_service_cst'=>['/^\d{2}$/','CST PIS do serviço'],
            'cofins_service_cst'=>['/^\d{2}$/','CST COFINS do serviço'],
            'ibs_cbs_cst'=>['/^\d{3}$/','CST IBS/CBS'],
            'ibs_cbs_classification'=>['/^\d{6}$/','cClassTrib'],
            'operation_indicator'=>['/^\d{6}$/','cIndOp'],
            'nfse_purpose'=>['/^\d{1,2}$/','finalidade NFS-e'],
            'operation_type'=>['/^\d{1,2}$/','tipo de operação'],
        ];
        $result = [];
        foreach ($patterns as $field => [$pattern,$label]) {
            $value = preg_replace('/\D+/', '', trim((string)($data[$field] ?? ''))) ?? '';
            if ($value !== '' && preg_match($pattern, $value) !== 1) {
                throw new InvalidArgumentException('Campo fiscal inválido: ' . $label . '.');
            }
            $result[$field] = $value === '' ? null : $value;
        }
        foreach (['iss_rate','pis_service_rate','cofins_service_rate'] as $field) {
            $raw = trim((string)($data[$field] ?? ''));
            $result[$field] = $raw === '' ? null : self::decimalRate($raw, $field);
        }
        $result['fiscal_description'] = self::longText($data['fiscal_description'] ?? null);
        $result['iss_taxation'] = self::text((string)($data['iss_taxation'] ?? ''), 'tributação ISS', 30, false);
        $result['special_regime'] = self::text((string)($data['special_regime'] ?? ''), 'regime especial', 30, false);
        $result['iss_enforceability'] = self::text((string)($data['iss_enforceability'] ?? ''), 'exigibilidade ISS', 30, false);
        $result['iss_withheld'] = !empty($data['iss_withheld']) ? 1 : 0;
        return $result;
    }

    private static function decimalRate(string $value, string $field): string
    {
        $value = str_replace(',', '.', trim($value));
        if (preg_match('/^\d+(?:\.\d{1,4})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Alíquota fiscal inválida: ' . $field . '.');
        }
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        $whole = ltrim($whole, '0') ?: '0';
        if (strlen($whole) > 3 || (int) $whole > 100 || ((int) $whole === 100 && trim($fraction, '0') !== '')) {
            throw new InvalidArgumentException('Alíquota fiscal inválida: ' . $field . '.');
        }
        return $whole . '.' . str_pad($fraction, 4, '0');
    }
}
