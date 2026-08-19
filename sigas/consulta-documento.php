<?php

declare(strict_types=1);

/**
 * Compatibilidade com a antiga página de consulta/entrega.
 * A interface oficial foi movida para /comida-mesa/.
 */
$params = $_GET;
$action = trim((string) ($params['acao'] ?? ''));
unset($params['acao']);

$target = $action === 'entrega'
    ? 'comida-mesa/registrar-entrega.php'
    : 'comida-mesa/consulta-cpf.php';

$query = http_build_query($params);
header('Location: ' . $target . ($query !== '' ? '?' . $query : ''), true, 302);
exit;
