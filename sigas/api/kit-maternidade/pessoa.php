<?php

declare(strict_types=1);

use App\Core\Validator;
use App\Repositories\BenefitRequestRepository;
use App\Repositories\PersonRegistryRepository;
use App\Repositories\SocioeconomicRepository;

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__) . '/_common.php';

try {
    sigas_api_require_method('GET');
    $context = sigas_api_context();
    sigas_api_require_permission($context, 'kit_maternidade.cadastrar');

    $cpf = Validator::onlyDigits((string) ($_GET['cpf'] ?? ''));
    if (!Validator::cpf($cpf)) {
        sigas_api_json(422, ['ok' => false, 'error' => 'Informe um CPF válido.']);
    }

    $people = new PersonRegistryRepository($context['pdo']);
    $person = $people->findByCpf($cpf);
    if (!is_array($person)) {
        sigas_api_json(200, [
            'ok' => true,
            'data' => [
                'found' => false,
                'cpf' => $cpf,
                'message' => 'Pessoa ainda não cadastrada no SIGAS.',
            ],
        ]);
    }

    $personId = (int) ($person['id'] ?? 0);
    $profile = (new SocioeconomicRepository($context['pdo']))->findByPersonId($personId);
    $benefits = (new BenefitRequestRepository($context['pdo']))->listByPerson($personId);
    $openKit = (new BenefitRequestRepository($context['pdo']))->findOpenByPersonAndCode(
        $personId,
        'kit-maternidade',
        'kit-maternidade'
    );

    sigas_api_json(200, [
        'ok' => true,
        'data' => [
            'found' => true,
            'person' => [
                'id' => $personId,
                'nome' => (string) ($person['nome'] ?? ''),
                'cpf' => $cpf,
                'telefone' => $person['telefone'] ?? null,
                'bairro' => $person['bairro'] ?? null,
            ],
            'socioeconomic' => [
                'exists' => is_array($profile),
                'data_entrevista' => is_array($profile) ? ($profile['data_entrevista'] ?? null) : null,
                'origem' => is_array($profile) ? ($profile['origem'] ?? null) : null,
            ],
            'benefit_requests' => $benefits,
            'open_kit_request' => $openKit,
        ],
    ]);
} catch (Throwable $exception) {
    sigas_api_exception($exception);
}
