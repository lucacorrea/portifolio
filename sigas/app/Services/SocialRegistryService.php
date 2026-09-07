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

        if (is_array($person)) {
            $personId = (int) ($person['id'] ?? 0);
            $profile = $personId > 0 ? $this->socioeconomic->findByPersonId($personId) : null;
            $requests = $personId > 0 ? $this->benefits->listByPerson($personId) : [];
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
            $family['quantidade_membros'] = max(
                (int) ($family['quantidade_membros'] ?? 0),
                count($members) + 1
            );
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
        $members = is_array($anexoPayload['familiares'] ?? null) ? $anexoPayload['familiares'] : [];
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

        return [
            'person' => [
                'cpf' => (string) ($person['cpf'] ?? ''),
                'nome' => (string) ($person['name'] ?? ''),
                'nis' => $person['nis'] ?? null,
                'rg' => $person['rg'] ?? null,
                'data_nascimento' => $person['birth_date'] ?? null,
                'telefone' => $person['phone'] ?? null,
            ],
            'family' => [
                'logradouro' => $person['street'] ?? null,
                'numero' => $person['number'] ?? null,
                'complemento' => $person['complement'] ?? null,
                'bairro' => $person['district'] ?? null,
                'ponto_referencia' => $person['reference_point'] ?? null,
                'quantidade_membros' => max(1, (int) ($person['members_count'] ?? count($members) + 1)),
                'renda_familiar' => $person['family_income'] ?? null,
            ],
            'socioeconomic' => [
                'origem' => 'anexo',
                'anexo_solicitante_id' => $person['id'] ?? null,
                'anexo_atualizado_em' => $person['updated_at'] ?? null,
                'renda_familiar' => $person['family_income'] ?? null,
                'beneficios' => array_values($benefitNames),
                'resumo_social' => $person['summary'] ?? null,
                'data_entrevista' => $person['updated_at'] ?? $person['created_at'] ?? null,
            ],
            'members' => array_map(static fn (array $member): array => [
                'nome' => (string) ($member['name'] ?? ''),
                'data_nascimento' => $member['birth_date'] ?? null,
                'parentesco' => $member['relationship'] ?? null,
                'escolaridade' => $member['schooling'] ?? null,
            ], array_values(array_filter($members, 'is_array'))),
            'source' => [
                'system' => 'ANEXO',
                'read_only_origin' => true,
                'message' => 'Dados trazidos do ANEXO para conferência. O ANEXO não será alterado.',
            ],
        ];
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
