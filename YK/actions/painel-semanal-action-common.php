<?php

declare(strict_types=1);

require_once __DIR__ . '/os-action-common.php';

function painel_semanal_redirect(App\Core\Application $application, string $target = 'painel-semanal.php'): never
{
    header('Location: ' . $application->redirect()->applicationUrl($target), true, 303);
    exit;
}

function painel_semanal_return_target(?string $modal = null): string
{
    $week = (string) ($_POST['return_week'] ?? date('Y-m-d'));
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $week);
    if (!$parsed || $parsed->format('Y-m-d') !== $week) {
        $week = date('Y-m-d');
    }

    $query = ['week' => $week];
    if ($modal !== null && $modal !== '') {
        $query['modal'] = $modal;
    }

    return 'painel-semanal.php?' . http_build_query($query);
}
