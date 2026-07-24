<?php

declare(strict_types=1);

namespace App\Workforce\DTO;

use DateTimeImmutable;
use InvalidArgumentException;

final class EmployeeFormData
{
    /** @var array<string, int> */
    private const TEXT_FIELDS = [
        'funcao' => 100,
        'endereco' => 255,
        'banco' => 100,
        'agencia' => 30,
        'conta' => 40,
        'tipo_conta' => 30,
        'pix' => 150,
        'rg_numero' => 40,
        'rg_orgao_emissor' => 30,
        'titulo_eleitor_numero' => 40,
        'titulo_eleitor_secao' => 20,
        'titulo_eleitor_zona' => 20,
        'reservista_numero' => 60,
        'certidao_nascimento_numero' => 80,
        'certidao_nascimento_cidade' => 100,
        'certidao_nascimento_livro' => 30,
        'certidao_nascimento_folha' => 30,
        'carteira_trabalho_numero' => 40,
        'carteira_trabalho_serie' => 30,
        'pis_pasep_numero' => 40,
        'cnh_numero_registro' => 40,
        'cnh_categoria' => 20,
        'manequim_camisa' => 30,
        'manequim_calca' => 30,
        'manequim_calcado' => 30,
    ];

    private const DATE_FIELDS = [
        'data_nascimento',
        'data_cadastro',
        'data_admissao',
        'rg_data_emissao',
        'reservista_data_emissao',
        'certidao_nascimento_data_emissao',
        'cnh_data_vencimento',
    ];

    private const UF_FIELDS = [
        'rg_uf',
        'titulo_eleitor_uf',
        'carteira_trabalho_uf',
    ];

    private const VALID_UFS = [
        'AC', 'AL', 'AP', 'AM', 'BA', 'CE', 'DF', 'ES', 'GO', 'MA',
        'MT', 'MS', 'MG', 'PA', 'PB', 'PR', 'PE', 'PI', 'RJ', 'RN',
        'RS', 'RO', 'RR', 'SC', 'SP', 'SE', 'TO',
    ];

    /** @param array<string, string|null> $values */
    private function __construct(
        private readonly string $name,
        private readonly array $values
    ) {
    }

    public static function fromArray(array $data): self
    {
        $name = self::normalizeRequiredText(
            (string) ($data['name'] ?? $data['nome'] ?? ''),
            150,
            'Nome do funcionário'
        );
        $values = [];

        foreach (self::TEXT_FIELDS as $field => $maxLength) {
            if (array_key_exists($field, $data)) {
                $values[$field] = self::normalizeOptionalText(
                    $data[$field],
                    $maxLength,
                    self::label($field)
                );
            }
        }

        foreach (self::DATE_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $values[$field] = self::normalizeDate(
                    $data[$field],
                    self::label($field)
                );
            }
        }

        foreach (self::UF_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $values[$field] = self::normalizeUf($data[$field], self::label($field));
            }
        }

        if (array_key_exists('salario', $data)) {
            $values['salario'] = self::normalizeSalary($data['salario']);
        }
        if (array_key_exists('telefone_celular', $data)) {
            $values['telefone_celular'] = self::normalizePhone($data['telefone_celular']);
        }
        if (array_key_exists('cpf_numero', $data)) {
            $values['cpf_numero'] = self::normalizeCpf($data['cpf_numero']);
        }
        if (array_key_exists('estado_civil', $data)) {
            $values['estado_civil'] = self::normalizeEnum(
                $data['estado_civil'],
                ['Solteiro', 'Casado', 'Divorciado', 'Viuvo', 'Uniao estavel', 'Outro'],
                'Estado civil'
            );
        }
        if (array_key_exists('sexo', $data)) {
            $values['sexo'] = self::normalizeEnum(
                $data['sexo'],
                ['Masculino', 'Feminino'],
                'Sexo'
            );
        }

        self::validateDates($values);

        return new self($name, $values);
    }

    public function name(): string
    {
        return $this->name;
    }

    public function has(string $field): bool
    {
        return $field === 'nome' || array_key_exists($field, $this->values);
    }

    public function value(string $field): ?string
    {
        if ($field === 'nome') {
            return $this->name;
        }

        return $this->values[$field] ?? null;
    }

    /** @return array<string, string|null> */
    public function databaseValues(): array
    {
        return ['nome' => $this->name] + $this->values;
    }

    public function functionName(): ?string
    {
        return $this->value('funcao');
    }

    public function salary(): ?string
    {
        return $this->value('salario');
    }

    public function cpfNumber(): ?string
    {
        return $this->value('cpf_numero');
    }

    private static function normalizeRequiredText(
        string $value,
        int $maxLength,
        string $label
    ): string {
        $normalized = self::normalizeText($value, $maxLength, $label);
        if ($normalized === null) {
            throw new InvalidArgumentException($label . ' é obrigatório.');
        }

        return $normalized;
    }

    private static function normalizeOptionalText(mixed $value, int $maxLength, string $label): ?string
    {
        return self::normalizeText((string) ($value ?? ''), $maxLength, $label);
    }

    private static function normalizeText(string $value, int $maxLength, string $label): ?string
    {
        if (str_contains($value, "\0") || $value !== strip_tags($value)) {
            throw new InvalidArgumentException($label . ' possui conteúdo inválido.');
        }

        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');
        if ($value === '') {
            return null;
        }
        $length = function_exists('mb_strlen')
            ? mb_strlen($value)
            : strlen($value);
        if ($length > $maxLength) {
            throw new InvalidArgumentException(
                sprintf('%s deve ter no máximo %d caracteres.', $label, $maxLength)
            );
        }

        return $value;
    }

    private static function normalizeDate(mixed $value, string $label): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        $errors = DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
            || $date->format('Y-m-d') !== $value
        ) {
            throw new InvalidArgumentException($label . ' é inválida.');
        }

        return $value;
    }

    private static function normalizeSalary(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }

        if (str_contains($value, ',') && str_contains($value, '.')) {
            $value = str_replace('.', '', $value);
        }
        $value = str_replace(',', '.', $value);
        if (preg_match('/^\d{1,10}(?:\.\d{1,2})?$/', $value) !== 1) {
            throw new InvalidArgumentException('Salário inválido.');
        }

        return number_format((float) $value, 2, '.', '');
    }

    private static function normalizePhone(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^[+()\-\s\d]+$/', $raw) !== 1) {
            throw new InvalidArgumentException('Telefone celular inválido.');
        }
        $digits = preg_replace('/\D/', '', $raw) ?? '';
        if (strlen($digits) < 10 || strlen($digits) > 13) {
            throw new InvalidArgumentException('Telefone celular deve possuir de 10 a 13 dígitos.');
        }

        return $digits;
    }

    private static function normalizeCpf(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        if (preg_match('/^[.\-\s\d]+$/', $raw) !== 1) {
            throw new InvalidArgumentException('CPF inválido.');
        }
        $cpf = preg_replace('/\D/', '', $raw) ?? '';
        if (!self::isValidCpf($cpf)) {
            throw new InvalidArgumentException('CPF inválido.');
        }

        return $cpf;
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf) === 1) {
            return false;
        }
        for ($digit = 9; $digit <= 10; $digit++) {
            $sum = 0;
            for ($index = 0; $index < $digit; $index++) {
                $sum += (int) $cpf[$index] * (($digit + 1) - $index);
            }
            $expected = (11 - ($sum % 11)) % 10;
            if ((int) $cpf[$digit] !== $expected) {
                return false;
            }
        }

        return true;
    }

    private static function normalizeUf(mixed $value, string $label): ?string
    {
        $uf = strtoupper(trim((string) ($value ?? '')));
        if ($uf === '') {
            return null;
        }
        if (!in_array($uf, self::VALID_UFS, true)) {
            throw new InvalidArgumentException($label . ' é inválida.');
        }

        return $uf;
    }

    /** @param string[] $allowed */
    private static function normalizeEnum(mixed $value, array $allowed, string $label): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        if (!in_array($value, $allowed, true)) {
            throw new InvalidArgumentException($label . ' inválido.');
        }

        return $value;
    }

    /** @param array<string, string|null> $values */
    private static function validateDates(array $values): void
    {
        $today = (new DateTimeImmutable('today'))->format('Y-m-d');
        foreach (['data_nascimento', 'data_cadastro', 'data_admissao', 'rg_data_emissao',
            'reservista_data_emissao', 'certidao_nascimento_data_emissao'] as $field) {
            if (($values[$field] ?? null) !== null && $values[$field] > $today) {
                throw new InvalidArgumentException(self::label($field) . ' não pode estar no futuro.');
            }
        }
        if (($values['data_nascimento'] ?? null) !== null
            && ($values['data_admissao'] ?? null) !== null
            && $values['data_admissao'] < $values['data_nascimento']
        ) {
            throw new InvalidArgumentException('Data de admissão não pode ser anterior ao nascimento.');
        }
    }

    private static function label(string $field): string
    {
        return ucfirst(str_replace('_', ' ', $field));
    }
}
