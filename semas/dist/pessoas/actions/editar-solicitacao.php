<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

if (!auth_can_edit_solicitacao()) {
    http_response_code(403);
    echo pc_json(array(
        'ok' => false,
        'error' => 'Você não tem permissão para editar solicitações.',
        'code' => 'FORBIDDEN'
    ));
    exit;
}

function pc_editar_solicitacao_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo pc_json($payload);
    exit;
}

function pc_editar_solicitacao_normalizar_texto($value): string
{
    $text = trim((string)$value);
    if ($text === '') {
        return '';
    }

    $text = function_exists('mb_strtolower')
        ? mb_strtolower($text, 'UTF-8')
        : strtolower($text);

    $normalized = preg_replace('/\s+/u', ' ', $text);
    return is_string($normalized) ? $normalized : $text;
}

function pc_editar_solicitacao_datetime($value): ?string
{
    $raw = trim((string)$value);
    if ($raw === '') {
        return null;
    }

    $formats = array('Y-m-d\\TH:i:s', 'Y-m-d\\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i');
    foreach ($formats as $format) {
        $date = DateTime::createFromFormat('!' . $format, $raw);
        $errors = DateTime::getLastErrors();
        $validErrors = !is_array($errors) || ((int)$errors['warning_count'] === 0 && (int)$errors['error_count'] === 0);

        if ($date instanceof DateTime && $validErrors) {
            $year = (int)$date->format('Y');
            if ($year < 1900 || $year > 2100) {
                return null;
            }
            return $date->format('Y-m-d H:i:s');
        }
    }

    return null;
}

function pc_editar_solicitacao_row_is_initial(array $row, array $person): bool
{
    $origin = pc_editar_solicitacao_normalizar_texto(isset($row['origem']) ? $row['origem'] : '');
    if ($origin !== '' && strpos($origin, 'cadastro') !== false) {
        return true;
    }

    $rowAid = isset($row['ajuda_tipo_id']) ? (int)$row['ajuda_tipo_id'] : 0;
    $personAid = isset($person['ajuda_tipo_id']) ? (int)$person['ajuda_tipo_id'] : 0;
    if ($rowAid > 0 && $personAid > 0 && $rowAid !== $personAid) {
        return false;
    }

    $rowSummary = pc_editar_solicitacao_normalizar_texto(isset($row['resumo_caso']) ? $row['resumo_caso'] : '');
    $personSummary = pc_editar_solicitacao_normalizar_texto(isset($person['resumo_caso']) ? $person['resumo_caso'] : '');
    if ($rowSummary === '' || $personSummary === '' || $rowSummary !== $personSummary) {
        return false;
    }

    $rowTs = !empty($row['data_solicitacao']) ? strtotime((string)$row['data_solicitacao']) : false;
    $personTs = !empty($person['created_at']) ? strtotime((string)$person['created_at']) : false;
    if ($rowTs !== false && $personTs !== false) {
        return abs($rowTs - $personTs) <= 300;
    }

    return true;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pc_editar_solicitacao_json(
        array('ok' => false, 'error' => 'Método inválido.', 'code' => 'METHOD_NOT_ALLOWED'),
        405
    );
}

if (!pc_verify_csrf(isset($_POST['csrf']) ? $_POST['csrf'] : null)) {
    pc_editar_solicitacao_json(
        array('ok' => false, 'error' => 'Sua sessão expirou. Atualize a página e tente novamente.', 'code' => 'CSRF_EXPIRED'),
        419
    );
}

$solicitacaoId = isset($_POST['solicitacao_id']) ? (int)$_POST['solicitacao_id'] : 0;
$solicitanteId = isset($_POST['solicitante_id']) ? (int)$_POST['solicitante_id'] : 0;
$frontendInitial = isset($_POST['eh_inicial']) && (string)$_POST['eh_inicial'] === '1';
$ajudaTipoId = isset($_POST['ajuda_tipo_id']) && $_POST['ajuda_tipo_id'] !== ''
    ? (int)$_POST['ajuda_tipo_id']
    : null;
$resumo = isset($_POST['resumo_caso']) ? trim((string)$_POST['resumo_caso']) : '';
$status = isset($_POST['status']) ? trim((string)$_POST['status']) : 'Aberto';
$dataSolicitacao = pc_editar_solicitacao_datetime(isset($_POST['data_solicitacao']) ? $_POST['data_solicitacao'] : '');

$allowedStatus = array('Aberto', 'Em andamento', 'Concluído', 'Cancelado');

if ($solicitanteId <= 0 || $solicitacaoId < 0) {
    pc_editar_solicitacao_json(
        array('ok' => false, 'error' => 'Solicitação inválida. Reabra o cadastro e tente novamente.', 'code' => 'INVALID_REQUEST'),
        422
    );
}

if ($solicitacaoId === 0 && !$frontendInitial) {
    pc_editar_solicitacao_json(
        array('ok' => false, 'error' => 'Esta solicitação legada não pode ser identificada com segurança.', 'code' => 'INVALID_LEGACY_REQUEST'),
        422
    );
}

if ($ajudaTipoId === null && $resumo === '') {
    pc_editar_solicitacao_json(
        array('ok' => false, 'error' => 'Informe o tipo de ajuda ou o resumo da solicitação.', 'code' => 'VALIDATION_ERROR'),
        422
    );
}

if (strlen($resumo) > 3000) {
    pc_editar_solicitacao_json(
        array('ok' => false, 'error' => 'O resumo da solicitação deve ter no máximo 3000 caracteres.', 'code' => 'VALIDATION_ERROR'),
        422
    );
}

if (!in_array($status, $allowedStatus, true)) {
    pc_editar_solicitacao_json(
        array('ok' => false, 'error' => 'Status inválido.', 'code' => 'VALIDATION_ERROR'),
        422
    );
}

if ($dataSolicitacao === null) {
    pc_editar_solicitacao_json(
        array('ok' => false, 'error' => 'Informe uma data e hora válidas para a solicitação.', 'code' => 'VALIDATION_ERROR'),
        422
    );
}

try {
    // Tipos antigos/inativos continuam válidos em histórico; exige apenas que o ID exista.
    if ($ajudaTipoId !== null) {
        $stmtAjuda = $pdo->prepare('SELECT id FROM ajudas_tipos WHERE id = :id LIMIT 1');
        $stmtAjuda->execute(array(':id' => $ajudaTipoId));
        if (!$stmtAjuda->fetchColumn()) {
            pc_editar_solicitacao_json(
                array('ok' => false, 'error' => 'O tipo de ajuda selecionado não existe.', 'code' => 'INVALID_HELP_TYPE'),
                422
            );
        }
    }

    $pdo->beginTransaction();

    $personFields = 'id, ajuda_tipo_id, resumo_caso, created_at';
    if (pc_table_has_column($pdo, 'solicitantes', 'responsavel')) {
        $personFields .= ', responsavel';
    } else {
        $personFields .= ', NULL AS responsavel';
    }

    $stmtPerson = $pdo->prepare(
        'SELECT ' . $personFields . ' FROM solicitantes WHERE id = :id LIMIT 1 FOR UPDATE'
    );
    $stmtPerson->execute(array(':id' => $solicitanteId));
    $person = $stmtPerson->fetch(PDO::FETCH_ASSOC);

    if (!$person) {
        $pdo->rollBack();
        pc_editar_solicitacao_json(
            array('ok' => false, 'error' => 'O solicitante não foi encontrado.', 'code' => 'PERSON_NOT_FOUND'),
            404
        );
    }

    $isInitial = false;
    $requestIdToUpdate = $solicitacaoId;

    if ($solicitacaoId > 0) {
        $row = $solicitacoesRepository->buscarPorId($solicitacaoId);
        if (!$row || (int)$row['solicitante_id'] !== $solicitanteId) {
            $pdo->rollBack();
            pc_editar_solicitacao_json(
                array('ok' => false, 'error' => 'A solicitação não pertence ao cadastro selecionado.', 'code' => 'REQUEST_NOT_FOUND'),
                404
            );
        }

        $isInitial = pc_editar_solicitacao_row_is_initial($row, $person);
    } else {
        // Cadastro legado: cria uma linha real em solicitacoes para que a data da
        // solicitação possa ser alterada sem mexer em solicitantes.created_at.
        $existingInitial = $solicitacoesRepository->buscarInicialCadastroPorPessoa($solicitanteId);

        if ($existingInitial) {
            $requestIdToUpdate = (int)$existingInitial['id'];
        } else {
            $responsavelOriginal = trim((string)(isset($person['responsavel']) ? $person['responsavel'] : ''));
            $usuario = $responsavelOriginal !== ''
                ? $responsavelOriginal
                : (!empty($_SESSION['user_nome'])
                    ? (string)$_SESSION['user_nome']
                    : (!empty($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : 'Sistema'));

            $requestIdToUpdate = $solicitacoesRepository->criarInicialCadastro(
                $solicitanteId,
                $ajudaTipoId,
                $resumo,
                $dataSolicitacao,
                $status,
                $usuario
            );
        }

        $isInitial = true;
    }

    if ($requestIdToUpdate <= 0) {
        throw new RuntimeException('Não foi possível identificar a solicitação para atualização.');
    }

    if (!$solicitacoesRepository->atualizarCompleta(
        $requestIdToUpdate,
        $solicitanteId,
        $ajudaTipoId,
        $resumo,
        $dataSolicitacao,
        $status
    )) {
        throw new RuntimeException('Falha ao atualizar a solicitação.');
    }

    if ($isInitial) {
        // Mantém os campos legados sincronizados para que o histórico continue
        // reconhecendo a demanda como a solicitação inicial. NÃO altera created_at.
        $stmtSync = $pdo->prepare(
            'UPDATE solicitantes
                SET ajuda_tipo_id = :aid,
                    resumo_caso = :resumo
              WHERE id = :id
              LIMIT 1'
        );
        $stmtSync->execute(array(
            ':aid' => $ajudaTipoId !== null ? $ajudaTipoId : null,
            ':resumo' => $resumo !== '' ? $resumo : null,
            ':id' => $solicitanteId,
        ));
    }

    $pdo->commit();

    pc_editar_solicitacao_json(
        array(
            'ok' => true,
            'id' => $requestIdToUpdate,
            'message' => 'Solicitação atualizada com sucesso.',
        )
    );
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    @error_log(
        '[SEMAS][EDITAR_SOLICITACAO] '
        . get_class($e)
        . ' | '
        . $e->getMessage()
    );

    pc_editar_solicitacao_json(
        array(
            'ok' => false,
            'error' => 'Não foi possível atualizar a solicitação. Tente novamente.',
            'code' => 'SAVE_ERROR',
        ),
        500
    );
}
