<?php

declare(strict_types=1);

use App\Core\Logger;
use App\Exceptions\RepositoryException;
use App\Integrations\Anexo\AnexoIntegrationService;
use App\Repositories\BenefitRequestRepository;
use App\Repositories\PersonRegistryRepository;
use App\Repositories\SocioeconomicRepository;
use App\Services\SocialRegistryService;

require_once dirname(__DIR__, 2) . '/bootstrap.php';
require_once dirname(__DIR__) . '/_common.php';

/** @return array{section:string,field:?string,message:string} */
function socio_save_error_context(Throwable $exception): array
{
    $message = trim($exception->getMessage());
    $normalized = mb_strtolower($message, 'UTF-8');

    if (str_contains($normalized, 'cpf')) {
        return [
            'section' => 'Localizar pessoa / Identificação',
            'field' => 'CPF',
            'message' => $message !== '' ? $message : 'Revise o CPF informado.',
        ];
    }

    if (str_contains($normalized, 'nome completo') || str_contains($normalized, 'nome da pessoa')) {
        return [
            'section' => 'Identificação',
            'field' => 'Nome completo',
            'message' => $message !== '' ? $message : 'Revise o nome completo.',
        ];
    }

    if (str_contains($normalized, 'família') || str_contains($normalized, 'familia')) {
        return [
            'section' => 'Família',
            'field' => null,
            'message' => $message !== '' ? $message : 'Revise a composição familiar.',
        ];
    }

    if (str_contains($normalized, 'pessoa no cadastro central') || str_contains($normalized, 'salvar a pessoa')) {
        return [
            'section' => 'Identificação / cadastro central',
            'field' => null,
            'message' => 'Não foi possível gravar os dados cadastrais da pessoa.',
        ];
    }

    if (str_contains($normalized, 'prontuário socioeconômico') || str_contains($normalized, 'prontuario socioeconomico')) {
        return [
            'section' => 'Prontuário socioeconômico',
            'field' => null,
            'message' => 'Não foi possível gravar os dados do prontuário socioeconômico.',
        ];
    }

    return [
        'section' => 'Gravação no servidor',
        'field' => null,
        'message' => 'O servidor não conseguiu concluir a gravação. A versão anterior permanece preservada.',
    ];
}

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
} catch (RepositoryException $exception) {
    $where = socio_save_error_context($exception);
    $reference = 'SOC-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);

    Logger::application('Socioeconomic record save failed.', [
        'reference' => $reference,
        'type' => $exception::class,
        'code' => $exception->getCode(),
        'section' => $where['section'],
    ]);

    $fieldText = $where['field'] !== null ? ' · Campo: ' . $where['field'] : '';
    sigas_api_json(500, [
        'ok' => false,
        'error' => 'Erro em ' . $where['section'] . $fieldText . ': ' . $where['message'] . ' Referência: ' . $reference . '.',
        'where' => $where,
        'reference' => $reference,
    ]);
} catch (RuntimeException $exception) {
    $status = in_array((int) $exception->getCode(), [400, 401, 403, 404, 409, 419, 422], true)
        ? (int) $exception->getCode()
        : 422;
    $where = socio_save_error_context($exception);
    $fieldText = $where['field'] !== null ? ' · Campo: ' . $where['field'] : '';

    sigas_api_json($status, [
        'ok' => false,
        'error' => 'Revise ' . $where['section'] . $fieldText . ': ' . $where['message'],
        'where' => $where,
    ]);
} catch (Throwable $exception) {
    $reference = 'SOC-' . date('Ymd-His') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    Logger::application('Unexpected socioeconomic record save failure.', [
        'reference' => $reference,
        'type' => $exception::class,
        'code' => $exception->getCode(),
    ]);

    sigas_api_json(500, [
        'ok' => false,
        'error' => 'Erro na gravação no servidor. A versão anterior permanece preservada. Referência: ' . $reference . '.',
        'where' => [
            'section' => 'Gravação no servidor',
            'field' => null,
            'message' => 'Falha interna durante a gravação.',
        ],
        'reference' => $reference,
    ]);
}
