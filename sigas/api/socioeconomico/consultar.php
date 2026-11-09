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
    sigas_api_require_method('GET');
    $context = sigas_api_context();
    sigas_api_require_permission($context, 'socioeconomico.visualizar');

    $cpf = trim((string) ($_GET['cpf'] ?? ''));
    $authorization = $context['authorization'];
    $user = $context['user'];
    $canImportAnexo = $authorization->isAdministrator($user)
        || $authorization->isSupport($user)
        || $authorization->can($user, 'socioeconomico.importar_anexo');

    $service = new SocialRegistryService(
        new PersonRegistryRepository($context['pdo']),
        new SocioeconomicRepository($context['pdo']),
        new BenefitRequestRepository($context['pdo']),
        new AnexoIntegrationService(),
    );

    $data = $service->lookupByCpf($cpf, $canImportAnexo);
    if (!$canImportAnexo) {
        unset($data['anexo'], $data['anexo_draft']);
    }

    sigas_api_json(200, ['ok' => true, 'data' => $data]);
} catch (Throwable $exception) {
    sigas_api_exception($exception);
}
