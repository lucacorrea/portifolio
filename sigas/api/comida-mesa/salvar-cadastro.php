<?php

declare(strict_types=1);

use App\Core\Csrf;
use App\Core\Database;
use App\Core\Logger;
use App\Core\Validator;
use App\DTO\ComidaMesaCadastroData;
use App\Repositories\AccessLevelRepository;
use App\Repositories\AuditLogRepository;
use App\Repositories\ComidaMesaRepository;
use App\Repositories\PermissionRepository;
use App\Repositories\UserRepository;
use App\Repositories\UserSessionRepository;
use App\Services\AuditService;
use App\Services\AuthService;
use App\Services\AuthorizationService;
use App\Services\ComidaMesaService;
use App\Services\PermissionService;

require_once dirname(__DIR__, 2) . '/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function cm_json(int $status, array $payload): never
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function cm_error(RuntimeException $exception): never
{
    $payload = ['ok' => false, 'error' => 'Não foi possível concluir a operação.'];

    if ($exception->getCode() === 422) {
        $decoded = json_decode($exception->getMessage(), true);
        $payload['error'] = 'Revise os campos informados.';
        if (is_array($decoded['fields'] ?? null)) {
            $payload['fields'] = $decoded['fields'];
        }
    } elseif (in_array($exception->getCode(), [400, 403, 404, 409, 419], true)) {
        $payload['error'] = $exception->getMessage();
    }

    $status = in_array($exception->getCode(), [400, 403, 404, 409, 419, 422], true)
        ? $exception->getCode()
        : 500;
    cm_json($status, $payload);
}

function cm_context(PDO $pdo): array
{
    $levels = new AccessLevelRepository($pdo);
    $audit = new AuditService(new AuditLogRepository($pdo));
    $auth = new AuthService(new UserRepository($pdo), new UserSessionRepository($pdo), $levels, $audit);
    $user = $auth->currentUser();

    if ($user === null) {
        cm_json(401, ['ok' => false, 'error' => 'Não autenticado.']);
    }

    return [
        $user,
        new AuthorizationService(new PermissionService(new PermissionRepository($pdo)), $levels),
        $audit,
    ];
}

/** @param array<string,string> $fields */
function cm_validation_exception(array $fields): RuntimeException
{
    return new RuntimeException(
        json_encode(['fields' => $fields], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{"fields":{}}',
        422
    );
}

function cm_normalize_edit_version(mixed $value): ?string
{
    if ($value === null || trim((string) $value) === '') {
        return null;
    }

    $raw = trim((string) $value);
    $timestamp = strtotime($raw);

    return $timestamp === false ? $raw : date('Y-m-d H:i:s', $timestamp);
}

/**
 * Regulariza somente o caso seguro em que a pessoa responsável já existe,
 * está vinculada à inscrição atual e ainda não possui CPF no cadastro central.
 *
 * Não troca CPF já definido e nunca reaproveita CPF pertencente a outra pessoa.
 *
 * @return array<string,mixed>|null
 */
function cm_regularize_missing_cpf(PDO $pdo, ComidaMesaCadastroData $data, int $userId): ?array
{
    if ($data->registrationId === null) {
        return null;
    }

    $cpf = Validator::onlyDigits($data->cpf);
    if (!Validator::cpf($cpf)) {
        throw cm_validation_exception(['cpf' => 'Informe um CPF válido.']);
    }

    $pdo->beginTransaction();

    try {
        $stmt = $pdo->prepare(
            "SELECT i.id AS inscricao_id,
                    COALESCE(i.atualizado_em, i.criado_em) AS versao_atualizacao,
                    f.codigo AS familia_codigo,
                    p.id AS pessoa_id,
                    p.nome AS pessoa_nome,
                    p.cpf AS cpf_atual
             FROM comida_mesa_inscricoes i
             INNER JOIN familias f ON f.id = i.familia_id
             INNER JOIN pessoas p ON p.id = f.responsavel_pessoa_id
             WHERE i.id = :id
             LIMIT 1
             FOR UPDATE"
        );
        $stmt->execute(['id' => $data->registrationId]);
        $current = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!is_array($current)) {
            throw new RuntimeException('Inscrição não localizada.', 404);
        }

        $currentVersion = cm_normalize_edit_version($current['versao_atualizacao'] ?? null);
        $submittedVersion = cm_normalize_edit_version($data->updateVersion);
        if ($submittedVersion === null || $currentVersion !== $submittedVersion) {
            throw new RuntimeException(
                'Este cadastro foi alterado por outro usuário. Recarregue os dados antes de salvar novamente.',
                409
            );
        }

        $personId = (int) $current['pessoa_id'];
        $currentCpf = Validator::onlyDigits((string) ($current['cpf_atual'] ?? ''));

        if ($currentCpf !== '') {
            if (hash_equals($currentCpf, $cpf)) {
                $pdo->commit();
                return [
                    'changed' => false,
                    'person_id' => $personId,
                    'family_code' => (string) ($current['familia_codigo'] ?? ''),
                    'cpf' => $cpf,
                ];
            }

            throw cm_validation_exception([
                'cpf' => 'Este cadastro já possui outro CPF definido. Revise a pessoa antes de alterar a identidade do registro.',
            ]);
        }

        $duplicate = $pdo->prepare(
            'SELECT id, nome FROM pessoas WHERE cpf = :cpf AND id <> :pessoa_id LIMIT 1 FOR UPDATE'
        );
        $duplicate->execute([
            'cpf' => $cpf,
            'pessoa_id' => $personId,
        ]);
        $otherPerson = $duplicate->fetch(PDO::FETCH_ASSOC);

        if (is_array($otherPerson)) {
            $otherName = trim((string) ($otherPerson['nome'] ?? ''));
            $message = 'Este CPF já está vinculado a outra pessoa no SIGAS.';
            if ($otherName !== '') {
                $message .= ' Cadastro localizado: ' . $otherName . '.';
            }
            $message .= ' Confira pelo Consultar CPF antes de prosseguir.';

            throw cm_validation_exception(['cpf' => $message]);
        }

        $update = $pdo->prepare(
            "UPDATE pessoas
             SET cpf = :cpf,
                 atualizado_por = :usuario_id
             WHERE id = :pessoa_id
               AND (cpf IS NULL OR cpf = '')"
        );
        $update->execute([
            'cpf' => $cpf,
            'usuario_id' => $userId,
            'pessoa_id' => $personId,
        ]);

        if ($update->rowCount() !== 1) {
            throw new RuntimeException('O CPF não pôde ser regularizado. Recarregue o cadastro e tente novamente.', 409);
        }

        $pdo->commit();

        return [
            'changed' => true,
            'person_id' => $personId,
            'person_name' => (string) ($current['pessoa_nome'] ?? ''),
            'family_code' => (string) ($current['familia_codigo'] ?? ''),
            'cpf' => $cpf,
        ];
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $exception;
    }
}

try {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        header('Allow: POST');
        cm_json(405, ['ok' => false, 'error' => 'Método não permitido.']);
    }

    if (!Csrf::validate($_POST['_csrf'] ?? null, 'comida_mesa_salvar_cadastro')) {
        cm_json(419, ['ok' => false, 'error' => 'Requisição inválida.']);
    }

    $pdo = Database::connection();
    [$user, $authorization, $audit] = cm_context($pdo);
    $data = ComidaMesaCadastroData::fromArray($_POST);
    $permission = $data->registrationId === null ? 'comida_mesa.cadastrar' : 'comida_mesa.editar';

    if (!$authorization->can($user, $permission)) {
        cm_json(403, ['ok' => false, 'error' => 'Acesso negado.']);
    }

    $repository = new ComidaMesaRepository($pdo);
    $service = new ComidaMesaService($repository);
    $cpfRegularization = null;

    try {
        $result = $service->saveRegistration($data, $user->id, $audit);
    } catch (RuntimeException $exception) {
        $isMissingCpfRegularization = $data->registrationId !== null
            && $exception->getCode() === 422
            && $exception->getMessage() === 'Não é permitido alterar o CPF para outra pessoa.';

        if (!$isMissingCpfRegularization) {
            throw $exception;
        }

        // A primeira tentativa já passou por toda a validação do serviço e falhou
        // somente na trava histórica de alteração de CPF. Regularizamos NULL -> CPF
        // na mesma pessoa e repetimos o salvamento normal.
        $cpfRegularization = cm_regularize_missing_cpf($pdo, $data, $user->id);
        $result = $service->saveRegistration($data, $user->id, $audit);
    }

    if (is_array($cpfRegularization) && !empty($cpfRegularization['changed']) && $data->registrationId !== null) {
        try {
            $repository->addHistory(
                $data->registrationId,
                $user->id,
                'cpf_regularizado',
                'CPF informado durante regularização cadastral.',
                ['cpf' => null],
                ['cpf' => $cpfRegularization['cpf']]
            );
            $audit->record(
                $user->id,
                null,
                'cpf_regularizado',
                'comida_mesa',
                $cpfRegularization['family_code'] ?? null,
                ['pessoa_id' => $cpfRegularization['person_id'], 'cpf' => null],
                ['pessoa_id' => $cpfRegularization['person_id'], 'cpf' => $cpfRegularization['cpf']]
            );
        } catch (Throwable $auditException) {
            Logger::application('Comida Mesa CPF regularization audit failed.', [
                'type' => $auditException::class,
                'registration_id' => $data->registrationId,
            ]);
        }
    }

    cm_json($result['created'] ? 201 : 200, [
        'ok' => true,
        'message' => $result['created']
            ? 'Cadastro criado.'
            : ($cpfRegularization !== null ? 'Cadastro atualizado e CPF regularizado.' : 'Cadastro atualizado.'),
        'data' => $result,
    ]);
} catch (RuntimeException $exception) {
    cm_error($exception);
} catch (Throwable $exception) {
    Logger::application('Comida Mesa save registration failed.', [
        'type' => $exception::class,
        'code' => $exception->getCode(),
    ]);
    cm_json(500, ['ok' => false, 'error' => 'Não foi possível salvar o cadastro.']);
}
