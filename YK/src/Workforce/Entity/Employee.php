<?php

declare(strict_types=1);

namespace App\Workforce\Entity;

use InvalidArgumentException;

final class Employee
{
    private const STATUSES = [
        'ativo',
        'inativo',
    ];

    private const DETAIL_FIELDS = [
        'foto',
        'funcao',
        'salario',
        'endereco',
        'telefone_celular',
        'data_nascimento',
        'estado_civil',
        'sexo',
        'data_cadastro',
        'data_admissao',
        'banco',
        'agencia',
        'conta',
        'tipo_conta',
        'pix',
        'rg_numero',
        'rg_uf',
        'rg_orgao_emissor',
        'rg_data_emissao',
        'cpf_numero',
        'titulo_eleitor_numero',
        'titulo_eleitor_uf',
        'titulo_eleitor_secao',
        'titulo_eleitor_zona',
        'reservista_numero',
        'reservista_data_emissao',
        'certidao_nascimento_numero',
        'certidao_nascimento_cidade',
        'certidao_nascimento_livro',
        'certidao_nascimento_folha',
        'certidao_nascimento_data_emissao',
        'carteira_trabalho_numero',
        'carteira_trabalho_serie',
        'carteira_trabalho_uf',
        'pis_pasep_numero',
        'cnh_numero_registro',
        'cnh_categoria',
        'cnh_data_vencimento',
        'manequim_camisa',
        'manequim_calca',
        'manequim_calcado',
    ];

    /**
     * @param array<string,string|null> $details
     */
    private function __construct(
        private readonly int $id,
        private readonly ?string $code,
        private readonly string $name,
        private readonly string $status,
        private readonly array $details,
        private readonly string $createdAt,
        private readonly string $updatedAt
    ) {
        if ($this->id <= 0) {
            throw new InvalidArgumentException(
                'ID de funcionário inválido.'
            );
        }

        if ($this->name === '') {
            throw new InvalidArgumentException(
                'Nome do funcionário é obrigatório.'
            );
        }

        if (
            !in_array(
                $this->status,
                self::STATUSES,
                true
            )
        ) {
            throw new InvalidArgumentException(
                'Status do funcionário inválido.'
            );
        }
    }

    public static function fromArray(
        array $data
    ): self {
        $details = [];

        foreach (
            self::DETAIL_FIELDS
            as $field
        ) {
            $details[$field] =
                isset($data[$field])
                    ? (string) $data[$field]
                    : null;
        }

        return new self(
            id: (int) ($data['id'] ?? 0),

            code: isset($data['codigo'])
                ? (string) $data['codigo']
                : null,

            name: (string) (
                $data['nome']
                ?? ''
            ),

            status: (string) (
                $data['status']
                ?? 'ativo'
            ),

            details: $details,

            createdAt: (string) (
                $data['criado_em']
                ?? ''
            ),

            updatedAt: (string) (
                $data['atualizado_em']
                ?? ''
            )
        );
    }

    public function id(): int
    {
        return $this->id;
    }

    public function code(): ?string
    {
        return $this->code;
    }

    public function displayCode(): string
    {
        return $this->code
            ?? sprintf(
                'FUN-%06d',
                $this->id
            );
    }

    public function name(): string
    {
        return $this->name;
    }

    public function status(): string
    {
        return $this->status;
    }

    public function isActive(): bool
    {
        return $this->status === 'ativo';
    }

    public function displayStatus(): string
    {
        return $this->isActive()
            ? 'Ativo'
            : 'Inativo';
    }

    public function photo(): ?string
    {
        return $this->value('foto');
    }

    public function functionName(): ?string
    {
        return $this->value('funcao');
    }

    public function salary(): ?string
    {
        return $this->value('salario');
    }

    public function address(): ?string
    {
        return $this->value('endereco');
    }

    public function mobilePhone(): ?string
    {
        return $this->value(
            'telefone_celular'
        );
    }

    public function cpfNumber(): ?string
    {
        return $this->value(
            'cpf_numero'
        );
    }

    public function value(
        string $field
    ): ?string {
        return $this->details[$field]
            ?? null;
    }

    /**
     * @return array<string,string|null>
     */
    public function details(): array
    {
        return $this->details;
    }

    /**
     * @return array<string,int|string|null>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->code,
            'nome' => $this->name,
            'status' => $this->status,
        ] + $this->details + [
            'criado_em' => $this->createdAt,
            'atualizado_em' => $this->updatedAt,
        ];
    }

    public function createdAt(): string
    {
        return $this->createdAt;
    }

    public function updatedAt(): string
    {
        return $this->updatedAt;
    }
}