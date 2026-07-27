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
    $user = $authorization->requireLogin();
    $authorization->requirePermission('nota_fiscal.visualizar');
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
    $document = $application->fiscalDocumentPrinter()->renderAuthorized($id);
    $application->fiscalDocuments()->recordAccess($id, 'reimpressao', $user->id());
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="' . $document['filename'] . '"');
    header('Content-Length: ' . strlen($document['pdf']));
    echo $document['pdf'];
} catch (InvalidArgumentException $exception) {
    http_response_code(409);
    exit(htmlspecialchars($exception->getMessage(), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'));
} catch (Throwable $exception) {
    error_log('Fiscal PDF rendering failed [' . get_class($exception) . '].');
    http_response_code(500);
    exit('Não foi possível gerar o documento auxiliar fiscal.');
}
