<?php

declare(strict_types=1);

namespace App\Catalog\Entity;

use InvalidArgumentException;

final class ServiceDefinition
{
    public function __construct(
        private readonly int $id,
        private readonly ?string $code,
        private readonly string $name,
        private readonly ?string $category,
        private readonly ?string $compatibleEquipment,
        private readonly int $durationMinutes,
        private readonly string $value,
        private readonly ?string $description,
        /** @var array<string,mixed> */
        private readonly array $fiscal,
        private readonly string $status,
        private readonly string $createdAt,
        private readonly string $updatedAt
    ) {
        if ($this->id <= 0 || $this->name === '') {
            throw new InvalidArgumentException('Serviço inválido.');
        }
    }

    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            code: isset($data['codigo']) ? (string) $data['codigo'] : null,
            name: (string) ($data['nome'] ?? ''),
            category: isset($data['categoria']) ? (string) $data['categoria'] : null,
            compatibleEquipment: isset($data['equipamentos_compativeis']) ? (string) $data['equipamentos_compativeis'] : null,
            durationMinutes: (int) ($data['duracao_minutos'] ?? 0),
            value: (string) ($data['valor'] ?? '0.00'),
            description: isset($data['descricao']) ? (string) $data['descricao'] : null,
            fiscal: [
                'tax_code'=>$data['codigo_tributacao_nacional'] ?? null,
                'nbs'=>$data['nbs'] ?? null,
                'fiscal_description'=>$data['descricao_fiscal'] ?? null,
                'municipality_code'=>$data['municipio_incidencia_ibge'] ?? null,
                'iss_taxation'=>$data['tributacao_iss'] ?? null,
                'iss_withheld'=>(int)($data['iss_retido'] ?? 0),
                'iss_rate'=>$data['aliquota_iss'] ?? null,
                'special_regime'=>$data['regime_especial'] ?? null,
                'iss_enforceability'=>$data['exigibilidade_iss'] ?? null,
                'pis_service_cst'=>$data['cst_pis_servico'] ?? null,
                'cofins_service_cst'=>$data['cst_cofins_servico'] ?? null,
                'pis_service_rate'=>$data['aliquota_pis_servico'] ?? null,
                'cofins_service_rate'=>$data['aliquota_cofins_servico'] ?? null,
                'ibs_cbs_cst'=>$data['cst_ibs_cbs'] ?? null,
                'ibs_cbs_classification'=>$data['classificacao_tributaria_ibs_cbs'] ?? null,
                'operation_indicator'=>$data['cindop'] ?? null,
                'nfse_purpose'=>$data['finalidade_nfse'] ?? null,
                'operation_type'=>$data['tipo_operacao'] ?? null,
            ],
            status: (string) ($data['status'] ?? 'ativo'),
            createdAt: (string) ($data['criado_em'] ?? ''),
            updatedAt: (string) ($data['atualizado_em'] ?? '')
        );
    }

    public function id(): int { return $this->id; }
    public function code(): ?string { return $this->code; }
    public function displayCode(): string { return $this->code ?? sprintf('SRV-%06d', $this->id); }
    public function name(): string { return $this->name; }
    public function category(): ?string { return $this->category; }
    public function compatibleEquipment(): ?string { return $this->compatibleEquipment; }
    public function durationMinutes(): int { return $this->durationMinutes; }
    public function value(): string { return $this->value; }
    public function description(): ?string { return $this->description; }
    /** @return array<string,mixed> */
    public function fiscal(): array { return $this->fiscal; }
    public function status(): string { return $this->status; }
    public function createdAt(): string { return $this->createdAt; }
    public function updatedAt(): string { return $this->updatedAt; }
}
