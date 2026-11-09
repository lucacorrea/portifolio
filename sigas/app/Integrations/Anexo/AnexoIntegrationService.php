<?php

declare(strict_types=1);

namespace App\Integrations\Anexo;

use App\Core\Logger;
use App\Core\Validator;
use Throwable;

final class AnexoIntegrationService
{
    private ?object $repository = null;
    private ?string $configurationState = null;

    public function __construct(?object $repository = null, ?string $configurationState = null)
    {
        $this->repository = $repository;
        $this->configurationState = $configurationState;
    }

    /** @return array<string,mixed> */
    public function consultCpf(string $cpf): array
    {
        $repository = $this->repository();
        if ($repository === null) {
            return $this->unavailablePayload($this->configurationState ?? 'not_configured');
        }

        try {
            $solicitante = $repository->findSolicitanteByCpf($cpf);
            if ($solicitante === null) {
                return [
                    'enabled' => true,
                    'available' => true,
                    'found' => false,
                    'person' => null,
                    'message' => 'CPF não localizado no ANEXO.',
                ];
            }

            $id = (int) ($solicitante['id'] ?? 0);
            $historicoAjudas = array_map(
                [$this, 'deliveryPayload'],
                $repository->entregasPorPessoa($id, (string) ($solicitante['cpf'] ?? ''))
            );

            return [
                'enabled' => true,
                'available' => true,
                'found' => true,
                'person' => $this->personPayload($solicitante),
                'familiares' => array_map([$this, 'familyMemberPayload'], $repository->familiares($id)),
                'solicitacoes' => array_map([$this, 'requestPayload'], $repository->solicitacoes($id, (string) ($solicitante['cpf'] ?? ''))),
                'historico_ajudas' => $historicoAjudas,
                'received_help' => $historicoAjudas !== [],
                'received_help_count' => count($historicoAjudas),
                'last_help' => $historicoAjudas[0] ?? null,
                'message' => 'CPF localizado no ANEXO.',
            ];
        } catch (Throwable $exception) {
            Logger::application('ANEXO CPF consultation unavailable.', [
                'type' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            return $this->unavailablePayload('unavailable');
        }
    }

    /** @return array<string,mixed> */
    public function consultCpfBasic(string $cpf): array
    {
        $repository = $this->repository();
        if ($repository === null) {
            return $this->unavailablePayload($this->configurationState ?? 'not_configured');
        }

        try {
            $solicitante = $repository->findSolicitanteByCpf($cpf);
            if ($solicitante === null) {
                return [
                    'enabled' => true,
                    'available' => true,
                    'found' => false,
                    'person' => null,
                    'message' => 'CPF não localizado no ANEXO.',
                ];
            }

            return [
                'enabled' => true,
                'available' => true,
                'found' => true,
                'person' => $this->basicPersonPayload($solicitante),
                'message' => 'CPF localizado no ANEXO.',
            ];
        } catch (Throwable $exception) {
            Logger::application('ANEXO basic CPF consultation unavailable.', [
                'type' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            return $this->unavailablePayload('unavailable');
        }
    }

    /**
     * Consulta vários CPFs em uma única passagem para telas de importação.
     * @param list<string> $cpfs
     * @return array{enabled:bool,available:bool,matches:array<string,array<string,mixed>>,state:string}
     */
    public function consultCpfsBasic(array $cpfs): array
    {
        $repository = $this->repository();
        if ($repository === null) {
            $state = $this->configurationState ?? 'not_configured';
            return [
                'enabled' => !in_array($state, ['disabled', 'not_configured'], true),
                'available' => false,
                'matches' => [],
                'state' => $state,
            ];
        }

        try {
            if (!method_exists($repository, 'findSolicitantesSummaryByCpfs')) {
                return ['enabled' => true, 'available' => false, 'matches' => [], 'state' => 'unsupported'];
            }

            $rows = $repository->findSolicitantesSummaryByCpfs($cpfs);
            $matches = [];
            foreach ($rows as $row) {
                $cpf = Validator::onlyDigits((string) ($row['cpf'] ?? ''));
                if ($cpf === '') {
                    continue;
                }
                $benefitsRaw = trim((string) ($row['beneficios'] ?? ''));
                $benefits = $benefitsRaw === ''
                    ? []
                    : array_values(array_unique(array_filter(array_map('trim', explode('||', $benefitsRaw)))));
                $matches[$cpf] = [
                    'id' => (int) ($row['id'] ?? 0),
                    'nome' => (string) ($row['nome'] ?? ''),
                    'cpf' => $cpf,
                    'solicitacoes' => (int) ($row['solicitacoes_count'] ?? 0),
                    'beneficios' => $benefits,
                ];
            }

            return ['enabled' => true, 'available' => true, 'matches' => $matches, 'state' => 'available'];
        } catch (Throwable $exception) {
            Logger::application('ANEXO batch CPF consultation unavailable.', [
                'type' => $exception::class,
                'code' => $exception->getCode(),
            ]);
            return ['enabled' => true, 'available' => false, 'matches' => [], 'state' => 'unavailable'];
        }
    }

    /** @return array<string,mixed> */
    public function summary(): array
    {
        $repository = $this->repository();
        if (!$repository instanceof AnexoRepository) {
            return [
                'enabled' => false,
                'available' => false,
                'count' => null,
                'state' => $this->configurationState ?? 'not_configured',
            ];
        }

        try {
            return [
                'enabled' => true,
                'available' => true,
                'count' => $repository->countSolicitantes(),
                'state' => 'available',
            ];
        } catch (Throwable $exception) {
            Logger::application('ANEXO summary unavailable.', [
                'type' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            return ['enabled' => true, 'available' => false, 'count' => null, 'state' => 'unavailable'];
        }
    }

    private function repository(): ?object
    {
        if ($this->repository !== null) {
            return $this->repository;
        }

        try {
            $path = AnexoEnvironment::locate();
            if ($path === null) {
                $this->configurationState = 'not_configured';
                return null;
            }

            $config = AnexoDatabaseConfig::fromEnvironment(AnexoEnvironment::load($path));
            if (!$config->enabled()) {
                $this->configurationState = 'disabled';
                return null;
            }

            $this->configurationState = 'enabled';
            $this->repository = new AnexoRepository(new AnexoDatabase($config));

            return $this->repository;
        } catch (Throwable $exception) {
            Logger::application('ANEXO integration configuration unavailable.', [
                'type' => $exception::class,
                'code' => $exception->getCode(),
            ]);
            $this->configurationState = 'configuration_error';

            return null;
        }
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function basicPersonPayload(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['nome'] ?? ''),
            'cpf_formatted' => $this->formatCpf((string) ($row['cpf'] ?? '')),
            'cpf_masked' => $this->maskCpf((string) ($row['cpf'] ?? '')),
            'phone' => $this->stringOrNull($row, 'telefone'),
            'district' => $this->stringOrNull($row, 'bairro_nome'),
        ];
    }

    /** @return array<string,mixed> */
    private function unavailablePayload(string $state): array
    {
        return [
            'enabled' => !in_array($state, ['disabled', 'not_configured'], true),
            'available' => false,
            'found' => false,
            'person' => null,
            'state' => $state,
            'message' => match ($state) {
                'disabled' => 'Integração ANEXO desativada.',
                'not_configured' => 'Integração ANEXO não configurada.',
                default => 'ANEXO indisponível no momento.',
            },
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function personPayload(array $row): array
    {
        $spouseCpf = $this->stringOrNull($row, 'conj_cpf');

        return [
            'id' => (int) ($row['id'] ?? 0),
            'name' => (string) ($row['nome'] ?? ''),
            'cpf' => Validator::onlyDigits((string) ($row['cpf'] ?? '')),
            'cpf_formatted' => $this->formatCpf((string) ($row['cpf'] ?? '')),
            'cpf_masked' => $this->maskCpf((string) ($row['cpf'] ?? '')),
            'nis' => $this->stringOrNull($row, 'nis'),
            'phone' => $this->stringOrNull($row, 'telefone'),
            'district' => $this->stringOrNull($row, 'bairro_nome'),
            'gender' => $this->stringOrNull($row, 'genero'),
            'marital_status' => $this->stringOrNull($row, 'estado_civil'),
            'birth_date' => $this->stringOrNull($row, 'data_nascimento'),
            'nationality' => $this->stringOrNull($row, 'nacionalidade'),
            'birthplace' => $this->stringOrNull($row, 'naturalidade'),
            'rg' => $this->stringOrNull($row, 'rg'),
            'rg_issued_at' => $this->stringOrNull($row, 'rg_emissao'),
            'rg_state' => $this->stringOrNull($row, 'rg_uf'),

            'street' => $this->stringOrNull($row, 'endereco'),
            'number' => $this->stringOrNull($row, 'numero'),
            'complement' => $this->stringOrNull($row, 'complemento'),
            'reference_point' => $this->stringOrNull($row, 'referencia'),
            'residence_years' => $this->intOrNull($row, 'tempo_anos'),
            'residence_months' => $this->intOrNull($row, 'tempo_meses'),

            'traditional_group' => $this->stringOrNull($row, 'grupo_tradicional'),
            'traditional_group_other' => $this->stringOrNull($row, 'grupo_outros'),
            'disability_status' => $this->stringOrNull($row, 'pcd'),
            'disability_type' => $this->stringOrNull($row, 'pcd_tipo'),
            'bpc_status' => $this->stringOrNull($row, 'bpc'),
            'bpc_value' => $this->stringOrNull($row, 'bpc_valor'),
            'pbf_status' => $this->stringOrNull($row, 'pbf'),
            'pbf_value' => $this->stringOrNull($row, 'pbf_valor'),
            'municipal_benefit_status' => $this->stringOrNull($row, 'beneficio_municipal'),
            'municipal_benefit_value' => $this->stringOrNull($row, 'beneficio_municipal_valor'),
            'state_benefit_status' => $this->stringOrNull($row, 'beneficio_estadual'),
            'state_benefit_value' => $this->stringOrNull($row, 'beneficio_estadual_valor'),

            'income_range' => $this->stringOrNull($row, 'renda_mensal_faixa'),
            'income_range_other' => $this->stringOrNull($row, 'renda_mensal_outros'),
            'work_status' => $this->stringOrNull($row, 'trabalho'),
            'individual_income' => $this->stringOrNull($row, 'renda_individual'),
            'family_income' => $this->stringOrNull($row, 'renda_familiar'),
            'total_income' => $this->stringOrNull($row, 'total_rendimentos'),
            'typification' => $this->stringOrNull($row, 'tipificacao'),

            'members_count' => $this->intOrNull($row, 'total_moradores'),
            'families_count' => $this->intOrNull($row, 'total_familias'),
            'household_disability_status' => $this->stringOrNull($row, 'pcd_residencia'),
            'household_disability_count' => $this->intOrNull($row, 'total_pcd'),

            'housing_status' => $this->stringOrNull($row, 'situacao_imovel'),
            'housing_cost' => $this->stringOrNull($row, 'situacao_imovel_valor'),
            'housing_material' => $this->stringOrNull($row, 'tipo_moradia'),
            'water_supply' => $this->stringOrNull($row, 'abastecimento'),
            'lighting' => $this->stringOrNull($row, 'iluminacao'),
            'sewer' => $this->stringOrNull($row, 'esgoto'),
            'trash_destination' => $this->stringOrNull($row, 'lixo'),
            'surroundings' => $this->stringOrNull($row, 'entorno'),

            'summary' => ($summary = $this->stringOrNull($row, 'resumo_caso')) === null
                ? null
                : mb_substr($summary, 0, 5000),

            'spouse_name' => $this->stringOrNull($row, 'conj_nome'),
            'spouse_cpf' => $spouseCpf === null ? null : $this->maskCpf($spouseCpf),
            'spouse_cpf_formatted' => $spouseCpf === null ? null : $this->formatCpf($spouseCpf),
            'spouse_nis' => $this->stringOrNull($row, 'conj_nis'),
            'spouse_rg' => $this->stringOrNull($row, 'conj_rg'),
            'spouse_birth_date' => $this->stringOrNull($row, 'conj_nasc'),

            'created_by' => $this->stringOrNull($row, 'responsavel'),
            'created_at' => $this->stringOrNull($row, 'created_at'),
            'updated_at' => $this->stringOrNull($row, 'updated_at'),
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function familyMemberPayload(array $row): array
    {
        return [
            'name' => (string) ($row['nome'] ?? ''),
            'birth_date' => $this->stringOrNull($row, 'data_nascimento'),
            'relationship' => $this->stringOrNull($row, 'parentesco'),
            'schooling' => $this->stringOrNull($row, 'escolaridade'),
            'observation' => $this->stringOrNull($row, 'obs'),
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function requestPayload(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'type_id' => isset($row['ajuda_tipo_id']) && $row['ajuda_tipo_id'] !== null ? (int) $row['ajuda_tipo_id'] : null,
            'type_name' => $this->stringOrNull($row, 'ajuda_nome'),
            'type_category' => $this->stringOrNull($row, 'ajuda_categoria'),
            'summary' => ($summary = $this->stringOrNull($row, 'resumo_caso')) === null ? null : mb_substr($summary, 0, 500),
            'requested_at' => $this->stringOrNull($row, 'data_solicitacao'),
            'status' => $this->stringOrNull($row, 'status'),
            'created_by' => $this->stringOrNull($row, 'created_by'),
            'origin' => $this->stringOrNull($row, 'origem'),
            'deliveries_count' => isset($row['entregas_count']) ? (int) $row['entregas_count'] : 0,
            'last_delivery_date' => $this->stringOrNull($row, 'data_entrega'),
            'last_delivery_time' => $this->stringOrNull($row, 'hora_entrega'),
            'assigned' => isset($row['entregas_count']) && (int) $row['entregas_count'] > 0,
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function deliveryPayload(array $row): array
    {
        return [
            'type_name' => $this->stringOrNull($row, 'ajuda_nome'),
            'delivered_date' => $this->stringOrNull($row, 'data_entrega'),
            'delivered_time' => $this->stringOrNull($row, 'hora_entrega'),
            'delivered' => strtoupper((string) ($row['entregue'] ?? '')) === 'SIM',
            'created_at' => $this->stringOrNull($row, 'created_at'),
        ];
    }

    /** @param array<string,mixed> $row */
    private function stringOrNull(array $row, string $key): ?string
    {
        if (!array_key_exists($key, $row) || $row[$key] === null) {
            return null;
        }

        $value = trim((string) $row[$key]);
        return $value === '' ? null : $value;
    }

    /** @param array<string,mixed> $row */
    private function intOrNull(array $row, string $key): ?int
    {
        if (!array_key_exists($key, $row) || $row[$key] === null || $row[$key] === '') {
            return null;
        }

        return is_numeric($row[$key]) ? (int) $row[$key] : null;
    }

    private function maskCpf(string $cpf): string
    {
        $cpf = Validator::onlyDigits($cpf);

        return strlen($cpf) === 11 ? substr($cpf, 0, 3) . '.***.***-' . substr($cpf, 9, 2) : '***.***.***-**';
    }

    private function formatCpf(string $cpf): string
    {
        $digits = Validator::onlyDigits($cpf);

        return strlen($digits) === 11
            ? substr($digits, 0, 3) . '.' . substr($digits, 3, 3) . '.' . substr($digits, 6, 3) . '-' . substr($digits, 9, 2)
            : ($cpf === '' ? 'Não informado' : $cpf);
    }
}
