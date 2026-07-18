<?php

declare(strict_types=1);

use App\Workforce\Service\EmployeePhotoStorage;

require __DIR__ . '/actions/funcionario-action-common.php';

try {
    [$application] = employee_action_context('funcionario.visualizar', false);
    $employeeId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!is_int($employeeId)) throw new InvalidArgumentException('Funcionário inválido.');
    session_write_close();

    $employee = $application->employeeManagement()->getEmployee($employeeId);
    $photo = $employee->photo();
    $storage = new EmployeePhotoStorage(__DIR__ . '/storage');
    $path = $photo === null ? null : $storage->resolve($photo);
    if ($path === null) throw new RuntimeException('Foto não encontrada.');

    $mime = match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
        'jpg', 'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        default => throw new RuntimeException('Tipo de foto inválido.'),
    };
    $etag = '"' . hash('sha256', $path . '|' . filemtime($path) . '|' . filesize($path)) . '"';
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . (string) filesize($path));
    header('Content-Disposition: inline; filename="foto-funcionario.' . pathinfo($path, PATHINFO_EXTENSION) . '"');
    header('Cache-Control: private, max-age=300');
    header('X-Content-Type-Options: nosniff');
    header('ETag: ' . $etag);
    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) { http_response_code(304); exit; }
    readfile($path);
} catch (Throwable $exception) {
    http_response_code(404);
}
