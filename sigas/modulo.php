<?php

declare(strict_types=1);

/**
 * Compatibilidade com a antiga entrada monolítica do Comida na Mesa.
 *
 * O módulo oficial agora vive em /comida-mesa/, como o Primeiro Emprego.
 * Mantemos este arquivo apenas para não quebrar favoritos, links antigos ou
 * integrações que ainda apontem para /modulo.php.
 */
$action = trim((string) ($_GET['action'] ?? ''));
$params = $_GET;

if ($action === 'competence') {
    unset($params['action']);
    $target = 'comida-mesa/competencias.php';
} elseif ($action === 'new-competence') {
    unset($params['action']);
    $params['modal'] = 'nova';
    $target = 'comida-mesa/competencias.php';
} else {
    $target = 'comida-mesa/beneficiarios.php';
}

$query = http_build_query($params);
header('Location: ' . $target . ($query !== '' ? '?' . $query : ''), true, 302);
exit;
