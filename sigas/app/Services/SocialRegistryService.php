<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Integrations\Anexo\AnexoIntegrationService;
use App\Repositories\BenefitRequestRepository;
use App\Repositories\PersonRegistryRepository;
use App\Repositories\SocioeconomicRepository;
use RuntimeException;

final class SocialRegistryService
{
    public function __construct(
        private readonly PersonRegistryRepository $people,
        private readonly SocioeconomicRepository $socioeconomic,
        private readonly BenefitRequestRepository $benefits,
        private readonly AnexoIntegrationService $anexo,
    ) {
    }

    /** @return array<string,mixed> */
    public function lookupByCpf(string $cpf, bool $consultAnexo = true): array
    {
        $cpf = Validator::onlyDigits($cpf);
        if (!Validator::cpf($cpf)) {
            throw new RuntimeException('Informe um CPF válido.', 422);
        }

        $person = $this->people->findByCpf($cpf);
        $profile = null;
        $requests = [];
        $history = [];

        if (is_array($person)) {
            $personId = (int) ($person['id'] ?? 0);
            if ($personId > 0) {
                $profile = $this->socioeconomic->findByPersonId($personId);
                $requests = $this->benefits->listByPerson($personId);
                $history = array_map(
                    static fn (array $item): array => [
                        'id' => (int) ($item['id'] ?? 0),
                        'origem' => (string) ($item['origem'] ?? 'sigas'),
                        'motivo' => (string) ($item['motivo'] ?? 'Atualização do prontuário'),
                        'criado_em' => $item['criado_em'] ?? null,
                        'usuario_nome' => $item['usuario_nome'] ?? null,
                    ],
                    $this->socioeconomic->history($personId, 20)
                );
            }
        }

        $anexo = $consultAnexo ? $this->anexo->consultCpf($cpf) : [
            'enabled' => false,
            'available' => false,
            'found' => false,
            'person' => null,
        ];

        return [
            'cpf' => $cpf,
            'registered' => is_array($person),
            'person' => $person,
            'socioeconomic' => $profile,
            'socioeconomic_state' => $this->profileState($profile),
            'benefit_requests' => $requests,
            'history' => $history,
            'anexo' => $anexo,
            'anexo_draft' => !is_array($profile) && !empty($anexo['found'])
                ? $this->draftFromAnexo($anexo)
                : null,
        ];
    }

    /**
     * Salva/atualiza pessoa + família + prontuário central.
     * A decisão de benefício não ocorre aqui.
     *
     * @param array<string,mixed> $input
     * @return array{person_id:int,family_id:int,socioeconomic_id:int,person_created:bool}
     */
    public function save(array $input, int $userId): array
    {
        $person = is_array($input['person'] ?? null) ? $input['person'] : $input;
        $family = is_array($input['family'] ?? null) ? $input['family'] : $input;
        $profile = is_array($input['socioeconomic'] ?? null) ? $input['socioeconomic'] : $input;
        $members = is_array($input['members'] ?? null) ? array_values($input['members']) : [];

        $cpf = Validator::onlyDigits((string) ($person['cpf'] ?? ''));
        if (!Validator::cpf($cpf)) {
            throw new RuntimeException('Informe um CPF válido.', 422);
        }
        $person['cpf'] = $cpf;

        $name = trim((string) ($person['nome'] ?? $person['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Informe o nome completo.', 422);
        }
        $person['nome'] = $name;

        if ($members !== []) {
            $family['quantidade_membros'] = max((int) ($family['quantidade_membros'] ?? 0), count($members) + 1);
        }
        $profile['quantidade_membros'] = max(1, (int) ($family['quantidade_membros'] ?? 1));

        $registration = $this->people->saveByCpf($person, $family, $userId);

        $origin = trim((string) ($profile['origem'] ?? 'sigas'));
        if (!in_array($origin, ['sigas', 'anexo', 'primeiro_emprego', 'comida_mesa'], true)) {
            $origin = 'sigas';
        }

        $socioeconomicId = $this->socioeconomic->save(
            $registration['person_id'],
            $registration['family_id'],
            $profile,
            $members,
            $userId,
            $origin,
            trim((string) ($input['motivo_atualizacao'] ?? 'Atualização pela entrevista social'))
        );

        return [
            'person_id' => $registration['person_id'],
            'family_id' => $registration['family_id'],
            'socioeconomic_id' => $socioeconomicId,
            'person_created' => $registration['created'],
        ];
    }

    /** @return array<string,mixed> */
    public function draftFromAnexo(array $anexoPayload): array
    {
        $person = is_array($anexoPayload['person'] ?? null) ? $anexoPayload['person'] : [];
        $members = is_array($anexoPayload['familiares'] ?? null) ? array_values($anexoPayload['familiares']) : [];
        $requests = is_array($anexoPayload['solicitacoes'] ?? null) ? $anexoPayload['solicitacoes'] : [];
        $helps = is_array($anexoPayload['historico_ajudas'] ?? null) ? $anexoPayload['historico_ajudas'] : [];

        $benefitNames = [];
        foreach (array_merge($requests, $helps) as $item) {
            if (!is_array($item)) {
                continue;
            }
            $name = trim((string) ($item['type_name'] ?? ''));
            if ($name !== '') {
                $benefitNames[$name] = $name;
            }
        }

        $benefitDetails = [];
        $this->appendDeclaredBenefit(
            $benefitNames,
            $benefitDetails,
            'Bolsa Família',
            $person['pbf_status'] ?? null,
            $person['pbf_value'] ?? null
        );
        $this->appendDeclaredBenefit(
            $benefitNames,
            $benefitDetails,
            'BPC',
            $person['bpc_status'] ?? null,
            $person['bpc_value'] ?? null
        );
        $this->appendDeclaredBenefit(
            $benefitNames,
            $benefitDetails,
            'Benefício municipal',
            $person['municipal_benefit_status'] ?? null,
            $person['municipal_benefit_value'] ?? null
        );
        $this->appendDeclaredBenefit(
            $benefitNames,
            $benefitDetails,
            'Benefício estadual',
            $person['state_benefit_status'] ?? null,
            $person['state_benefit_value'] ?? null
        );

        $draftMembers = [];
        foreach ($members as $member) {
            if (!is_array($member)) {
                continue;
            }
            $name = trim((string) ($member['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $draftMembers[] = [
                'nome' => $name,
                'data_nascimento' => $member['birth_date'] ?? null,
                'parentesco' => $member['relationship'] ?? null,
                'escolaridade' => $member['schooling'] ?? null,
                'observacao' => $member['observation'] ?? null,
            ];
        }

        $spouseName = trim((string) ($person['spouse_name'] ?? ''));
        if ($spouseName !== '' && !$this->memberNameExists($draftMembers, $spouseName)) {
            $spouseNotes = [];
            foreach ([
                'CPF' => $person['spouse_cpf_formatted'] ?? null,
                'NIS' => $person['spouse_nis'] ?? null,
                'RG' => $person['spouse_rg'] ?? null,
            ] as $label => $value) {
                $text = trim((string) ($value ?? ''));
                if ($text !== '' && !str_contains($text, '***')) {
                    $spouseNotes[] = $label . ': ' . $text;
                } elseif ($label !== 'CPF' && $text !== '') {
                    $spouseNotes[] = $label . ': ' . $text;
                }
            }

            $draftMembers[] = [
                'nome' => $spouseName,
                'data_nascimento' => $person['spouse_birth_date'] ?? null,
                'parentesco' => 'Cônjuge/companheiro(a)',
                'escolaridade' => null,
                'observacao' => $spouseNotes === [] ? 'Cônjuge informado no cadastro ANEXO.' : implode(' | ', $spouseNotes),
            ];
        }

        $traditionalGroup = trim((string) ($person['traditional_group'] ?? ''));
        $traditionalOther = trim((string) ($person['traditional_group_other'] ?? ''));
        if ($traditionalOther !== '' && ($traditionalGroup === '' || mb_strtolower($traditionalGroup) === 'outro')) {
            $traditionalGroup = $traditionalOther;
        }

        $surroundings = trim((string) ($person['surroundings'] ?? ''));
        $sourceNotes = [];
        if ($surroundings !== '') {
            $sourceNotes[] = 'Entorno informado no ANEXO: ' . $surroundings;
        }
        if (($person['families_count'] ?? null) !== null) {
            $sourceNotes[] = 'Total de famílias na residência: ' . (int) $person['families_count'];
        }
        if (trim((string) ($person['income_range'] ?? '')) !== '') {
            $sourceNotes[] = 'Faixa de renda no ANEXO: ' . trim((string) $person['income_range']);
        }
        if (trim((string) ($person['typification'] ?? '')) !== '') {
            $sourceNotes[] = 'Tipificação: ' . trim((string) $person['typification']);
        }

        $hasDisability = $this->isYes($person['disability_status'] ?? null);

        return [
            'person' => [
                'cpf' => (string) ($person['cpf'] ?? ''),
                'nome' => (string) ($person['name'] ?? ''),
                'nis' => $person['nis'] ?? null,
                'rg' => $person['rg'] ?? null,
                'rg_emissao' => $person['rg_issued_at'] ?? null,
                'rg_uf' => $person['rg_state'] ?? null,
                'data_nascimento' => $person['birth_date'] ?? null,
                'genero' => $this->normalizeGender($person['gender'] ?? null),
                'estado_civil' => $this->normalizeMaritalStatus($person['marital_status'] ?? null),
                'naturalidade' => $person['birthplace'] ?? null,
                'nacionalidade' => $person['nationality'] ?? null,
                'telefone' => $person['phone'] ?? null,
            ],
            'family' => [
                'logradouro' => $person['street'] ?? null,
                'numero' => $person['number'] ?? null,
                'complemento' => $person['complement'] ?? null,
                'bairro' => $person['district'] ?? null,
                'ponto_referencia' => $person['reference_point'] ?? null,
                'quantidade_membros' => max(1, (int) ($person['members_count'] ?? count($draftMembers) + 1)),
                'renda_familiar' => $person['family_income'] ?? null,
            ],
            'socioeconomic' => [
                'origem' => 'anexo',
                'anexo_solicitante_id' => $person['id'] ?? null,
                'anexo_atualizado_em' => $person['updated_at'] ?? null,
                'data_entrevista' => $person['updated_at'] ?? $person['created_at'] ?? null,

                'situacao_trabalho' => $this->normalizeWorkStatus($person['work_status'] ?? null),
                'renda_individual' => $person['individual_income'] ?? null,
                'renda_familiar' => $person['family_income'] ?? null,
                'grupo_tradicional' => $traditionalGroup !== '' ? $traditionalGroup : null,
                'possui_deficiencia' => $hasDisability ? 1 : 0,
                'deficiencia_descricao' => $person['disability_type'] ?? null,
                'beneficios' => array_values($benefitNames),

                // No SEMAS, situacao_imovel representa posse/uso do imóvel e
                // tipo_moradia representa o material/forma construtiva.
                'tipo_moradia' => $person['housing_status'] ?? null,
                'material_moradia' => $person['housing_material'] ?? null,
                'abastecimento_agua' => $person['water_supply'] ?? null,
                'energia_eletrica' => $person['lighting'] ?? null,
                'coleta_lixo' => $person['trash_destination'] ?? null,
                'esgotamento_sanitario' => $person['sewer'] ?? null,

                'resumo_social' => $person['summary'] ?? null,
                'observacoes' => $sourceNotes === [] ? null : implode("\n", $sourceNotes),

                // Campos de fidelidade integral introduzidos pela migration 019.
                'tempo_moradia_anos' => $person['residence_years'] ?? null,
                'tempo_moradia_meses' => $person['residence_months'] ?? null,
                'renda_mensal_faixa' => $person['income_range'] ?? null,
                'renda_mensal_outros' => $person['income_range_other'] ?? null,
                'total_rendimentos' => $person['total_income'] ?? null,
                'total_familias' => $person['families_count'] ?? null,
                'pcd_residencia' => $this->isYes($person['household_disability_status'] ?? null) ? 1 : 0,
                'total_pcd' => $person['household_disability_count'] ?? null,
                'situacao_imovel_valor' => $person['housing_cost'] ?? null,
                'iluminacao' => $person['lighting'] ?? null,
                'destino_lixo' => $person['trash_destination'] ?? null,
                'entorno' => $person['surroundings'] ?? null,
                'tipificacao' => $person['typification'] ?? null,
                'beneficios_detalhes' => $benefitDetails,
            ],
            'members' => $draftMembers,
            'source' => [
                'system' => 'ANEXO',
                'read_only_origin' => true,
                'created_by' => $person['created_by'] ?? null,
                'message' => 'Dados trazidos do ANEXO para conferência. O ANEXO não será alterado.',
            ],
        ];
    }

    /** @param array<string,string> $benefitNames @param array<string,array<string,mixed>> $details */
    private function appendDeclaredBenefit(
        array &$benefitNames,
        array &$details,
        string $name,
        mixed $status,
        mixed $value,
    ): void {
        if (!$this->isYes($status)) {
            return;
        }

        $benefitNames[$name] = $name;
        $details[$name] = [
            'nome' => $name,
            'recebe' => true,
            'valor' => $value,
            'origem' => 'anexo',
        ];
    }

    /** @param list<array<string,mixed>> $members */
    private function memberNameExists(array $members, string $name): bool
    {
        $expected = $this->normalizeComparable($name);
        foreach ($members as $member) {
            if ($this->normalizeComparable((string) ($member['nome'] ?? '')) === $expected) {
                return true;
            }
        }
        return false;
    }

    private function normalizeComparable(string $value): string
    {
        $value = mb_strtolower(trim($value));
        return preg_replace('/\s+/u', ' ', $value) ?? $value;
    }

    private function isYes(mixed $value): bool
    {
        $normalized = mb_strtolower(trim((string) ($value ?? '')));
        return in_array($normalized, ['1', 'sim', 's', 'yes', 'true'], true);
    }

    private function normalizeGender(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $normalized = mb_strtolower($raw);
        if (str_starts_with($normalized, 'masc')) {
            return 'Masculino';
        }
        if (str_starts_with($normalized, 'fem')) {
            return 'Feminino';
        }
        if (str_contains($normalized, 'pref')) {
            return 'Prefere não informar';
        }
        if (in_array($normalized, ['outro', 'outros'], true)) {
            return 'Outro';
        }
        return $raw;
    }

    private function normalizeMaritalStatus(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $normalized = mb_strtolower($raw);
        return match (true) {
            str_contains($normalized, 'uni') && str_contains($normalized, 'est') => 'União estável',
            str_contains($normalized, 'solteir') => 'Solteiro(a)',
            str_contains($normalized, 'casad') => 'Casado(a)',
            str_contains($normalized, 'separad') => 'Separado(a)',
            str_contains($normalized, 'divorciad') => 'Divorciado(a)',
            str_contains($normalized, 'viuv'), str_contains($normalized, 'viúv') => 'Viúvo(a)',
            default => $raw,
        };
    }

    private function normalizeWorkStatus(mixed $value): ?string
    {
        $raw = trim((string) ($value ?? ''));
        if ($raw === '') {
            return null;
        }
        $normalized = mb_strtolower($raw);
        return match (true) {
            str_contains($normalized, 'desempreg') => 'Desempregado(a)',
            str_contains($normalized, 'empregad') => 'Empregado(a)',
            str_contains($normalized, 'aut') && str_contains($normalized, 'nom') => 'Autônomo(a)',
            str_contains($normalized, 'informal') => 'Trabalho informal',
            str_contains($normalized, 'aposent'), str_contains($normalized, 'pension') => 'Aposentado(a)/Pensionista',
            str_contains($normalized, 'estud') => 'Estudante',
            default => $raw,
        };
    }

    /** @return array{exists:bool,state:string,age_days:?int,needs_review:bool,label:string} */
    private function profileState(?array $profile): array
    {
        if (!is_array($profile)) {
            return [
                'exists' => false,
                'state' => 'ausente',
                'age_days' => null,
                'needs_review' => true,
                'label' => 'Formulário socioeconômico ainda não preenchido',
            ];
        }

        $date = (string) ($profile['data_entrevista'] ?? $profile['atualizado_em'] ?? $profile['criado_em'] ?? '');
        $timestamp = $date !== '' ? strtotime($date) : false;
        $ageDays = $timestamp === false ? null : max(0, (int) floor((time() - $timestamp) / 86400));
        $needsReview = $ageDays === null || $ageDays > 180;

        return [
            'exists' => true,
            'state' => $needsReview ? 'revisar' : 'atual',
            'age_days' => $ageDays,
            'needs_review' => $needsReview,
            'label' => $needsReview
                ? 'Prontuário existente; confira se os dados continuam atuais'
                : 'Prontuário socioeconômico atualizado',
        ];
    }
}
