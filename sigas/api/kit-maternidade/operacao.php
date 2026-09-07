<?php

declare(strict_types=1);

use App\Repositories\BenefitRequestRepository;
use App\Repositories\KitMaternityRepository;
use App\Repositories\PersonJourneyRepository;
use App\Repositories\SocioeconomicRepository;
use App\Services\BenefitRequestService;
use App\Services\KitMaternityService;
use App\Services\PersonJourneyService;

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__) . '/_common.php';

try {
    sigas_api_require_method('POST');
    $input = sigas_api_input();
    sigas_api_require_csrf($input, 'kit_maternidade_operacao');

    $context = sigas_api_context();
    $action = strtolower(trim((string) ($input['action'] ?? '')));
    $permission = match ($action) {
        'abrir' => 'kit_maternidade.cadastrar',
        'acompanhamento', 'encerrar' => 'kit_maternidade.acompanhar',
        'avaliar' => 'kit_maternidade.avaliar',
        'entregar' => 'kit_maternidade.entregar',
        default => null,
    };
    if ($permission === null) {
        sigas_api_json(422, ['ok' => false, 'error' => 'Operação inválida.']);
    }
    sigas_api_require_permission($context, $permission);

    $pdo = $context['pdo'];
    $requestRepository = new BenefitRequestRepository($pdo);
    $journey = new PersonJourneyService(new PersonJourneyRepository($pdo));
    $benefits = new BenefitRequestService($pdo, $requestRepository, $journey);
    $service = new KitMaternityService(
        $pdo,
        new KitMaternityRepository($pdo),
        $requestRepository,
        new SocioeconomicRepository($pdo),
        $benefits,
        $journey,
    );

    $kitRequestId = (int) ($input['kit_solicitacao_id'] ?? 0);
    $resultId = null;
    $message = '';

    switch ($action) {
        case 'abrir':
            $resultId = $service->openRequest($input, $context['user']->id, $context['user']->setorId);
            $message = 'Solicitação do Kit Maternidade registrada.';
            break;

        case 'acompanhamento':
            if ($kitRequestId <= 0) {
                throw new RuntimeException('Informe a solicitação acompanhada.', 422);
            }
            $resultId = $service->addFollowUp($kitRequestId, $input, $context['user']->id);
            $message = 'Acompanhamento registrado.';
            break;

        case 'avaliar':
            if ($kitRequestId <= 0) {
                throw new RuntimeException('Informe a solicitação avaliada.', 422);
            }
            $resultId = $service->evaluate($kitRequestId, $input, $context['user']->id);
            $message = 'Avaliação técnica registrada.';
            break;

        case 'entregar':
            if ($kitRequestId <= 0) {
                throw new RuntimeException('Informe a solicitação do Kit.', 422);
            }
            $resultId = $service->deliver($kitRequestId, $input, $context['user']->id);
            $message = 'Entrega do Kit registrada.';
            break;

        case 'encerrar':
            if ($kitRequestId <= 0) {
                throw new RuntimeException('Informe a solicitação encerrada.', 422);
            }
            $service->closePostpartum(
                $kitRequestId,
                $context['user']->id,
                trim((string) ($input['observacao'] ?? ''))
            );
            $message = 'Acompanhamento encerrado.';
            break;
    }

    try {
        $context['audit']->record(
            $context['user']->id,
            null,
            'kit_maternidade_' . $action,
            'kit_maternidade',
            (string) ($kitRequestId > 0 ? $kitRequestId : ($resultId ?? '')),
            null,
            ['action' => $action, 'result_id' => $resultId]
        );
    } catch (Throwable) {
        // A operação principal já foi concluída.
    }

    sigas_api_json($action === 'abrir' ? 201 : 200, [
        'ok' => true,
        'message' => $message,
        'data' => [
            'kit_solicitacao_id' => $action === 'abrir' ? $resultId : $kitRequestId,
            'registro_id' => $resultId,
        ],
    ]);
} catch (Throwable $exception) {
    sigas_api_exception($exception);
}
