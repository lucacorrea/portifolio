<?php

declare(strict_types=1);

use App\Access\Exception\AuthenticationException;
use App\Access\Exception\AuthorizationException;
use App\Core\Application;

$app = require __DIR__ . '/bootstrap.php';
/** @var Application $application */
$application = $app['application'];
$session = $application->session();
$session->start();

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');

try {
    $authorization = $application->authorization();
    $authorization->requireLogin();
    $authorization->requirePermission('nota_fiscal.baixar_xml');
} catch (AuthenticationException) {
    header('Location: login.php', true, 303);
    exit;
} catch (AuthorizationException) {
    header('Location: acesso-negado.php', true, 303);
    exit;
}

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($id)) {
    http_response_code(404);
    exit('Documento fiscal não encontrado.');
}

try {
    $document = $application->fiscalDocumentPrinter()->authorizedXml($id);
    header('Content-Type: application/xml; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $document['filename'] . '"');
    header('Content-Length: ' . strlen($document['xml']));
    echo $document['xml'];
} catch (InvalidArgumentException $exception) {
    http_response_code(409);
    exit(htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
} catch (Throwable $exception) {
    error_log('Fiscal XML download failed [' . get_class($exception) . '].');
    http_response_code(500);
    exit('Não foi possível baixar o XML fiscal.');
}
