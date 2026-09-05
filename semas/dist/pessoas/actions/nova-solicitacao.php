<?php

header('Content-Type: application/json; charset=utf-8');

if (!auth_can_create_solicitacao()) {
    http_response_code(403);
    echo pc_json(array('ok' => false, 'error' => 'Você não tem permissão para criar solicitações.'));
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo pc_json(array('ok' => false, 'error' => 'Método inválido.'));
    exit;
}
if (!pc_verify_csrf(isset($_POST['csrf']) ? $_POST['csrf'] : null)) {
    http_response_code(419);
    echo pc_json(array('ok' => false, 'error' => 'Sessão expirada. Atualize a página.'));
    exit;
}
$solicitanteId = isset($_POST['solicitante_id']) ? (int)$_POST['solicitante_id'] : 0;
$ajudaTipoId = isset($_POST['ajuda_tipo_id']) && $_POST['ajuda_tipo_id'] !== '' ? (int)$_POST['ajuda_tipo_id'] : null;
$resumo = isset($_POST['resumo_caso']) ? trim((string)$_POST['resumo_caso']) : '';
if ($solicitanteId <= 0 || ($ajudaTipoId === null && $resumo === '')) {
    echo pc_json(array('ok' => false, 'error' => 'Informe o tipo de ajuda ou o resumo da solicitação.'));
    exit;
}
try {
    $usuario = !empty($_SESSION['user_nome']) ? $_SESSION['user_nome'] : (!empty($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Sistema');
    $id = $solicitacoesRepository->criar($solicitanteId, $ajudaTipoId, $resumo, $usuario);
    echo pc_json(array('ok' => true, 'id' => $id));
} catch (Throwable $e) {
    http_response_code(500);
    echo pc_json(array('ok' => false, 'error' => 'Não foi possível registrar a solicitação.'));
}
exit;
