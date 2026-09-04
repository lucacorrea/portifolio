<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

function pc_materializar_inicial_json(array $payload, $status = 200)
{
    http_response_code((int)$status);
    echo pc_json($payload);
    exit;
}

if (!auth_can_assign_beneficio()) {
    pc_materializar_inicial_json(array(
        'ok' => false,
        'error' => 'Você não tem permissão para atribuir benefícios.',
        'code' => 'FORBIDDEN',
    ), 403);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    pc_materializar_inicial_json(array(
        'ok' => false,
        'error' => 'Método inválido.',
        'code' => 'METHOD_NOT_ALLOWED',
    ), 405);
}

if (!pc_verify_csrf(isset($_POST['csrf']) ? $_POST['csrf'] : null)) {
    pc_materializar_inicial_json(array(
        'ok' => false,
        'error' => 'Sua sessão expirou. Atualize a página e tente novamente.',
        'code' => 'CSRF_EXPIRED',
    ), 419);
}

$solicitanteId = isset($_POST['solicitante_id']) ? (int)$_POST['solicitante_id'] : 0;
if ($solicitanteId <= 0) {
    pc_materializar_inicial_json(array(
        'ok' => false,
        'error' => 'Cadastro inválido. Reabra os detalhes e tente novamente.',
        'code' => 'INVALID_PERSON',
    ), 422);
}

try {
    $pdo->beginTransaction();

    $personFields = 'id, cpf, ajuda_tipo_id, resumo_caso, created_at';
    if (pc_table_has_column($pdo, 'solicitantes', 'responsavel')) {
        $personFields .= ', responsavel';
    } else {
        $personFields .= ', NULL AS responsavel';
    }

    // O lock serializa cliques concorrentes para o mesmo cadastro e evita
    // materializar duas solicitações iniciais em paralelo.
    $stmt = $pdo->prepare(
        'SELECT ' . $personFields . ' FROM solicitantes WHERE id = :id LIMIT 1 FOR UPDATE'
    );
    $stmt->execute(array(':id' => $solicitanteId));
    $pessoa = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$pessoa) {
        $pdo->rollBack();
        pc_materializar_inicial_json(array(
            'ok' => false,
            'error' => 'O solicitante não foi encontrado.',
            'code' => 'PERSON_NOT_FOUND',
        ), 404);
    }

    $ajudaTipoId = isset($pessoa['ajuda_tipo_id']) && (int)$pessoa['ajuda_tipo_id'] > 0
        ? (int)$pessoa['ajuda_tipo_id']
        : null;
    $resumo = trim((string)(isset($pessoa['resumo_caso']) ? $pessoa['resumo_caso'] : ''));

    if ($ajudaTipoId === null && $resumo === '') {
        $pdo->rollBack();
        pc_materializar_inicial_json(array(
            'ok' => false,
            'error' => 'Este cadastro não possui uma solicitação inicial para atribuição.',
            'code' => 'INITIAL_REQUEST_EMPTY',
        ), 422);
    }

    $dataOriginal = trim((string)(isset($pessoa['created_at']) ? $pessoa['created_at'] : ''));
    $tsOriginal = $dataOriginal !== '' ? strtotime($dataOriginal) : false;
    if ($tsOriginal === false) {
        // Fallback somente para cadastros antigos com data inválida/nula.
        // Não altera solicitantes.created_at; usa o horário corrente apenas na nova linha.
        $dataOriginal = date('Y-m-d H:i:s');
    } else {
        $dataOriginal = date('Y-m-d H:i:s', $tsOriginal);
    }

    $existente = $solicitacoesRepository->buscarInicialCompativelPorPessoa(
        $solicitanteId,
        $ajudaTipoId,
        $resumo,
        $dataOriginal
    );

    if ($existente && isset($existente['id']) && (int)$existente['id'] > 0) {
        $solicitacaoId = (int)$existente['id'];
    } else {
        $responsavelOriginal = trim((string)(isset($pessoa['responsavel']) ? $pessoa['responsavel'] : ''));
        $usuario = $responsavelOriginal !== ''
            ? $responsavelOriginal
            : (!empty($_SESSION['user_nome'])
                ? (string)$_SESSION['user_nome']
                : (!empty($_SESSION['user_name']) ? (string)$_SESSION['user_name'] : 'Sistema'));

        $solicitacaoId = $solicitacoesRepository->criarInicialCadastro(
            $solicitanteId,
            $ajudaTipoId,
            $resumo,
            $dataOriginal,
            'Aberto',
            $usuario
        );
    }

    if ($solicitacaoId <= 0) {
        throw new RuntimeException('Não foi possível identificar a solicitação inicial.');
    }

    $pdo->commit();

    $cpf = preg_replace('/\D+/', '', (string)(isset($pessoa['cpf']) ? $pessoa['cpf'] : ''));
    $url = 'atribuirBeneficio.php?solicitacao_id=' . rawurlencode((string)$solicitacaoId);
    if (is_string($cpf) && strlen($cpf) === 11) {
        $url .= '&cpf=' . rawurlencode($cpf);
    }

    pc_materializar_inicial_json(array(
        'ok' => true,
        'id' => $solicitacaoId,
        'url' => $url,
    ));
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    @error_log(
        '[SEMAS][MATERIALIZAR_SOLICITACAO_INICIAL] '
        . get_class($e)
        . ' | '
        . $e->getMessage()
    );

    pc_materializar_inicial_json(array(
        'ok' => false,
        'error' => 'Não foi possível preparar a solicitação inicial para atribuição. Tente novamente.',
        'code' => 'MATERIALIZE_ERROR',
    ), 500);
}
