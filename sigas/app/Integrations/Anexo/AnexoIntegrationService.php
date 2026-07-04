<?php

declare(strict_types=1);

namespace App\Integrations\Anexo;

use App\Core\Logger;
use App\Core\Validator;
use Throwable;

final class AnexoIntegrationService
{
    private ?AnexoRepository $repository = null;
    private ?string $configurationState = null;

    /** @return array<string,mixed> */
    public function consultCpf(string $cpf): array
    {
        $repository = $this->repository();
        if (!$repository instanceof AnexoRepository) {
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

            $id = (int) $solicitante['id'];
            $historicoAjudas = array_map(
                [$this, 'deliveryPayload'],
                $repository->entregasPorPessoa($id, (string) $solicitante['cpf'])
            );

            return [
                'enabled' => true,
                'available' => true,
                'found' => true,
                'person' => $this->personPayload($solicitante),
                'familiares' => array_map([$this, 'familyMemberPayload'], $repository->familiares($id)),
                'solicitacoes' => array_map([$this, 'requestPayload'], $repository->solicitacoes($id)),
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
    public function summary(): array
    {
        $repository = $this->repository();
        if (!$repository instanceof AnexoRepository) {
            return ['enabled' => false, 'available' => false, 'count' => null, 'state' => $this->configurationState ?? 'not_configured'];
        }

        try {
            return ['enabled' => true, 'available' => true, 'count' => $repository->countSolicitantes(), 'state' => 'available'];
        } catch (Throwable $exception) {
            Logger::application('ANEXO summary unavailable.', [
                'type' => $exception::class,
                'code' => $exception->getCode(),
            ]);

            return ['enabled' => true, 'available' => false, 'count' => null, 'state' => 'unavailable'];
        }
    }

    private function repository(): ?AnexoRepository
    {
        if ($this->repository instanceof AnexoRepository) {
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
        return [
            'id' => (int) $row['id'],
            'name' => (string) $row['nome'],
            'cpf' => Validator::onlyDigits((string) $row['cpf']),
            'cpf_masked' => $this->maskCpf((string) $row['cpf']),
            'nis' => $row['nis'] === null ? null : (string) $row['nis'],
            'phone' => $row['telefone'] === null ? null : (string) $row['telefone'],
            'district' => $row['bairro_nome'] === null ? null : (string) $row['bairro_nome'],
            'gender' => $row['genero'] === null ? null : (string) $row['genero'],
            'marital_status' => $row['estado_civil'] === null ? null : (string) $row['estado_civil'],
            'birth_date' => $row['data_nascimento'] === null ? null : (string) $row['data_nascimento'],
            'nationality' => $row['nacionalidade'] === null ? null : (string) $row['nacionalidade'],
            'birthplace' => $row['naturalidade'] === null ? null : (string) $row['naturalidade'],
            'rg' => $row['rg'] === null ? null : (string) $row['rg'],
            'rg_issued_at' => $row['rg_emissao'] === null ? null : (string) $row['rg_emissao'],
            'rg_state' => $row['rg_uf'] === null ? null : (string) $row['rg_uf'],
            'street' => $row['endereco'] === null ? null : (string) $row['endereco'],
            'number' => $row['numero'] === null ? null : (string) $row['numero'],
            'complement' => $row['complemento'] === null ? null : (string) $row['complemento'],
            'reference_point' => $row['referencia'] === null ? null : (string) $row['referencia'],
            'family_income' => $row['renda_familiar'] === null ? null : (string) $row['renda_familiar'],
            'members_count' => $row['total_moradores'] === null ? null : (int) $row['total_moradores'],
            'families_count' => $row['total_familias'] === null ? null : (int) $row['total_familias'],
            'summary' => $row['resumo_caso'] === null ? null : mb_substr((string) $row['resumo_caso'], 0, 500),
            'spouse_name' => $row['conj_nome'] === null ? null : (string) $row['conj_nome'],
            'spouse_cpf' => $row['conj_cpf'] === null ? null : $this->maskCpf((string) $row['conj_cpf']),
            'spouse_nis' => $row['conj_nis'] === null ? null : (string) $row['conj_nis'],
            'spouse_rg' => $row['conj_rg'] === null ? null : (string) $row['conj_rg'],
            'spouse_birth_date' => $row['conj_nasc'] === null ? null : (string) $row['conj_nasc'],
            'created_by' => $row['responsavel'] === null ? null : (string) $row['responsavel'],
            'created_at' => $row['created_at'] === null ? null : (string) $row['created_at'],
            'updated_at' => $row['updated_at'] === null ? null : (string) $row['updated_at'],
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function familyMemberPayload(array $row): array
    {
        return [
            'name' => (string) $row['nome'],
            'birth_date' => $row['data_nascimento'] === null ? null : (string) $row['data_nascimento'],
            'relationship' => $row['parentesco'] === null ? null : (string) $row['parentesco'],
            'schooling' => $row['escolaridade'] === null ? null : (string) $row['escolaridade'],
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function requestPayload(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'type_id' => $row['ajuda_tipo_id'] === null ? null : (int) $row['ajuda_tipo_id'],
            'type_name' => $row['ajuda_nome'] === null ? null : (string) $row['ajuda_nome'],
            'type_category' => $row['ajuda_categoria'] === null ? null : (string) $row['ajuda_categoria'],
            'summary' => $row['resumo_caso'] === null ? null : mb_substr((string) $row['resumo_caso'], 0, 500),
            'requested_at' => $row['data_solicitacao'] === null ? null : (string) $row['data_solicitacao'],
            'status' => $row['status'] === null ? null : (string) $row['status'],
            'created_by' => $row['created_by'] === null ? null : (string) $row['created_by'],
            'origin' => $row['origem'] === null ? null : (string) $row['origem'],
            'deliveries_count' => isset($row['entregas_count']) ? (int) $row['entregas_count'] : 0,
            'last_delivery_date' => $row['data_entrega'] === null ? null : (string) $row['data_entrega'],
            'last_delivery_time' => $row['hora_entrega'] === null ? null : (string) $row['hora_entrega'],
            'assigned' => isset($row['entregas_count']) && (int) $row['entregas_count'] > 0,
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    private function deliveryPayload(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'type_id' => $row['ajuda_tipo_id'] === null ? null : (int) $row['ajuda_tipo_id'],
            'type_name' => $row['ajuda_nome'] === null ? null : (string) $row['ajuda_nome'],
            'type_category' => $row['ajuda_categoria'] === null ? null : (string) $row['ajuda_categoria'],
            'person_id' => $row['pessoa_id'] === null ? null : (int) $row['pessoa_id'],
            'person_cpf' => $row['pessoa_cpf'] === null ? null : $this->maskCpf((string) $row['pessoa_cpf']),
            'family_id' => $row['familia_id'] === null ? null : (int) $row['familia_id'],
            'delivered_date' => $row['data_entrega'] === null ? null : (string) $row['data_entrega'],
            'delivered_time' => $row['hora_entrega'] === null ? null : (string) $row['hora_entrega'],
            'quantity' => $row['quantidade'] === null ? null : (int) $row['quantidade'],
            'applied_value' => $row['valor_aplicado'] === null ? null : (string) $row['valor_aplicado'],
            'responsible' => $row['responsavel'] === null ? null : (string) $row['responsavel'],
            'observation' => $row['observacao'] === null ? null : mb_substr((string) $row['observacao'], 0, 500),
            'delivered' => strtoupper((string) ($row['entregue'] ?? '')) === 'SIM',
            'created_at' => $row['created_at'] === null ? null : (string) $row['created_at'],
            'request_id' => $row['solicitacao_id'] === null ? null : (int) $row['solicitacao_id'],
            'request_status' => $row['solicitacao_status'] === null ? null : (string) $row['solicitacao_status'],
        ];
    }

    private function maskCpf(string $cpf): string
    {
        $cpf = Validator::onlyDigits($cpf);

        return strlen($cpf) === 11 ? substr($cpf, 0, 3) . '.***.***-' . substr($cpf, 9, 2) : '***.***.***-**';
    }
}
