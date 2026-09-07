<?php

declare(strict_types=1);

use App\Integrations\Anexo\AnexoIntegrationService;
use App\Repositories\BenefitRequestRepository;
use App\Repositories\PersonRegistryRepository;
use App\Repositories\SocioeconomicRepository;
use App\Services\SocialRegistryService;

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__) . '/_common.php';

try {
    sigas_api_require_method('POST');
    $input = sigas_api_input();
    sigas_api_require_csrf($input, 'socioeconomico_salvar');

    $context = sigas_api_context();
    sigas_api_require_permission($context, 'socioeconomico.editar');

    $profileInput = is_array($input['socioeconomic'] ?? null) ? $input['socioeconomic'] : $input;
    $origin = strtolower(trim((string) ($profileInput['origem'] ?? 'sigas')));
    if ($origin === 'anexo') {
        sigas_api_require_permission($context, 'socioeconomico.importar_anexo');
    }

    $service = new SocialRegistryService(
        new PersonRegistryRepository($context['pdo']),
        new SocioeconomicRepository($context['pdo']),
        new BenefitRequestRepository($context['pdo']),
        new AnexoIntegrationService(),
    );

    $result = $service->save($input, $context['user']->id);

    try {
        $context['audit']->record(
            $context['user']->id,
            null,
            'prontuario_socioeconomico_salvo',
            'socioeconomico',
            (string) $result['person_id'],
            null,
            [
                'pessoa_id' => $result['person_id'],
                'familia_id' => $result['family_id'],
                'socioeconomico_id' => $result['socioeconomic_id'],
                'origem' => $origin,
            ]
        );
    } catch (Throwable) {
        // O prontuário já foi salvo; auditoria complementar não deve duplicar a operação.
    }

    sigas_api_json($result['person_created'] ? 201 : 200, [
        'ok' => true,
        'message' => $result['person_created']
            ? 'Pessoa e prontuário socioeconômico cadastrados.'
            : 'Prontuário socioeconômico atualizado.',
        'data' => $result,
    ]);
} catch (Throwable $exception) {
    sigas_api_exception($exception);
}
