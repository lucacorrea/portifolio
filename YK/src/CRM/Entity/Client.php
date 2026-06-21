<?php

declare(strict_types=1);

namespace App\CRM\Entity;

use InvalidArgumentException;

final class Client
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $code,
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
        private readonly string $status,
        private readonly string $createdAt,
        private readonly string $updatedAt
    ) {
        if ($this->id <= 0 || $this->name === '') {
            throw new InvalidArgumentException('Cliente inválido.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            code: isset($data['codigo']) ? (string) $data['codigo'] : null,
            personType: (string) ($data['tipo_pessoa'] ?? 'fisica'),
            name: (string) ($data['nome'] ?? ''),
            document: isset($data['documento']) ? (string) $data['documento'] : null,
            phone: isset($data['telefone']) ? (string) $data['telefone'] : null,
            whatsapp: isset($data['whatsapp']) ? (string) $data['whatsapp'] : null,
            email: isset($data['email']) ? (string) $data['email'] : null,
            address: isset($data['endereco']) ? (string) $data['endereco'] : null,
            number: isset($data['numero']) ? (string) $data['numero'] : null,
            complement: isset($data['complemento']) ? (string) $data['complemento'] : null,
            district: isset($data['bairro']) ? (string) $data['bairro'] : null,
            city: isset($data['cidade']) ? (string) $data['cidade'] : null,
            state: isset($data['uf']) ? (string) $data['uf'] : null,
            zipCode: isset($data['cep']) ? (string) $data['cep'] : null,
            notes: isset($data['observacoes']) ? (string) $data['observacoes'] : null,
            status: (string) ($data['status'] ?? 'ativo'),
            createdAt: (string) ($data['criado_em'] ?? ''),
            updatedAt: (string) ($data['atualizado_em'] ?? '')
        );
    }

    public function id(): int { return $this->id; }
    public function code(): ?string { return $this->code; }
    public function displayCode(): string { return $this->code ?? sprintf('CLI-%06d', $this->id); }
    public function personType(): string { return $this->personType; }
    public function personTypeLabel(): string { return $this->personType === 'juridica' ? 'Pessoa Jurídica' : 'Pessoa Física'; }
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
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }
}
