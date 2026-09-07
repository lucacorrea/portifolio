<?php

declare(strict_types=1);

use App\Repositories\KitMaternityRepository;

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__) . '/_common.php';

try {
    sigas_api_require_method('GET');
    $context = sigas_api_context();
    sigas_api_require_permission($context, 'kit_maternidade.visualizar');

    $repository = new KitMaternityRepository($context['pdo']);
    $id = (int) ($_GET['id'] ?? 0);

    if ($id > 0) {
        $record = $repository->find($id);
        if (!is_array($record)) {
            sigas_api_json(404, ['ok' => false, 'error' => 'Solicitação não localizada.']);
        }
        $record['acompanhamentos'] = $repository->followUps($id);
        sigas_api_json(200, ['ok' => true, 'data' => $record]);
    }

    sigas_api_json(200, [
        'ok' => true,
        'data' => [
            'dashboard' => $repository->dashboard(),
            'records' => $repository->list(500),
        ],
    ]);
} catch (Throwable $exception) {
    sigas_api_exception($exception);
}
