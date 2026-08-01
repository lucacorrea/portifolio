<?php

declare(strict_types=1);

namespace App\CRM\DTO;

use InvalidArgumentException;

final class ClientFormData
{
    public function __construct(
        private readonly string $personType,
        private readonly string $name,
        private readonly ?string $document,
        private readonly ?string $phone,
        private readonly ?string $whatsapp,
        private readonly ?string $email,
        private readonly ?string $address,
        private readonly ?string $number,
        private readonly ?string $complement,
        private readonly ?string $district,
        private readonly ?string $city,
        private readonly ?string $state,
        private readonly ?string $zipCode,
        private readonly ?string $notes,
        private readonly string $status
    ) {
    }

    public static function fromArray(array $data): self
    {
        $personType = self::normalizePersonType((string) ($data['person_type'] ?? $data['tipo_pessoa'] ?? 'fisica'));
        $document = self::digits((string) ($data['document'] ?? $data['documento'] ?? ''));

        if ($document !== null) {
            if ($personType === 'fisica' && !self::isValidCpf($document)) {
                throw new InvalidArgumentException('Informe um CPF válido.');
            }

            if ($personType === 'juridica' && !self::isValidCnpj($document)) {
                throw new InvalidArgumentException('Informe um CNPJ válido.');
            }
        }

        return new self(
            personType: $personType,
            name: self::text((string) ($data['name'] ?? $data['nome'] ?? ''), 'nome', 150, true),
            document: $document,
            phone: self::text((string) ($data['phone'] ?? $data['telefone'] ?? ''), 'telefone', 30, false),
            whatsapp: self::text((string) ($data['whatsapp'] ?? ''), 'WhatsApp', 30, false),
            email: self::normalizeEmail((string) ($data['email'] ?? '')),
            address: self::text((string) ($data['address'] ?? $data['endereco'] ?? ''), 'endereço', 150, false),
            number: self::text((string) ($data['number'] ?? $data['numero'] ?? ''), 'número', 30, false),
            complement: self::text((string) ($data['complement'] ?? $data['complemento'] ?? ''), 'complemento', 100, false),
            district: self::text((string) ($data['district'] ?? $data['bairro'] ?? ''), 'bairro', 100, false),
            city: self::text((string) ($data['city'] ?? $data['cidade'] ?? ''), 'cidade', 100, false),
            state: self::normalizeState((string) ($data['state'] ?? $data['uf'] ?? '')),
            zipCode: self::text((string) ($data['zip_code'] ?? $data['cep'] ?? ''), 'CEP', 10, false),
            notes: self::longText($data['notes'] ?? $data['observacoes'] ?? null),
            status: self::normalizeStatus((string) ($data['status'] ?? 'ativo'))
        );
    }

    public function personType(): string { return $this->personType; }
    public function name(): string { return $this->name; }
    public function document(): ?string { return $this->document; }
    public function phone(): ?string { return $this->phone; }
    public function whatsapp(): ?string { return $this->whatsapp; }
    public function email(): ?string { return $this->email; }
    public function address(): ?string { return $this->address; }
    public function number(): ?string { return $this->number; }
    public function complement(): ?string { return $this->complement; }
    public function district(): ?string { return $this->district; }
    public function city(): ?string { return $this->city; }
    public function state(): ?string { return $this->state; }
    public function zipCode(): ?string { return $this->zipCode; }
    public function notes(): ?string { return $this->notes; }
    public function status(): string { return $this->status; }

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

        if (mb_strlen($value) > $max) {
            throw new InvalidArgumentException('Campo ' . $field . ' excede ' . $max . ' caracteres.');
        }

        return $value;
    }

    private static function longText(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));
        if ($value === '') return null;
        if (str_contains($value, "\0")) {
            throw new InvalidArgumentException('Observações inválidas.');
        }
        return $value;
    }

    private static function normalizeEmail(string $value): ?string
    {
        $value = self::text($value, 'e-mail', 150, false);
        if ($value !== null && filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Informe um e-mail válido.');
        }
        return $value;
    }

    private static function digits(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';
        return $digits === '' ? null : $digits;
    }

    private static function normalizePersonType(string $value): string
    {
        if (!in_array($value, ['fisica', 'juridica'], true)) {
            throw new InvalidArgumentException('Tipo de pessoa inválido.');
        }
        return $value;
    }

    private static function normalizeState(string $value): ?string
    {
        $value = strtoupper((string) self::text($value, 'UF', 2, false));
        if ($value !== '' && !preg_match('/^[A-Z]{2}$/', $value)) {
            throw new InvalidArgumentException('Informe uma UF válida.');
        }
        return $value === '' ? null : $value;
    }

    private static function normalizeStatus(string $value): string
    {
        if (!in_array($value, ['ativo', 'inativo'], true)) {
            throw new InvalidArgumentException('Status inválido.');
        }
        return $value;
    }

    private static function isValidCpf(string $cpf): bool
    {
        if (!preg_match('/^\d{11}$/', $cpf) || preg_match('/^(\d)\1{10}$/', $cpf)) return false;
        for ($t = 9; $t < 11; $t++) {
            $sum = 0;
            for ($i = 0; $i < $t; $i++) $sum += (int) $cpf[$i] * (($t + 1) - $i);
            $digit = ((10 * $sum) % 11) % 10;
            if ((int) $cpf[$t] !== $digit) return false;
        }
        return true;
    }

    private static function isValidCnpj(string $cnpj): bool
    {
        if (!preg_match('/^\d{14}$/', $cnpj) || preg_match('/^(\d)\1{13}$/', $cnpj)) return false;
        $weights = [[5,4,3,2,9,8,7,6,5,4,3,2], [6,5,4,3,2,9,8,7,6,5,4,3,2]];
        for ($round = 0; $round < 2; $round++) {
            $sum = 0;
            foreach ($weights[$round] as $i => $weight) $sum += (int) $cnpj[$i] * $weight;
            $digit = $sum % 11 < 2 ? 0 : 11 - ($sum % 11);
            if ((int) $cnpj[12 + $round] !== $digit) return false;
        }
        return true;
    }
}
