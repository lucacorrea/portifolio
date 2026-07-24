<?php

declare(strict_types=1);

require_once __DIR__ . '/os-action-common.php';

function agenda_redirect(App\Core\Application $application, string $target = 'agenda.php'): never
{
    header('Location: ' . $application->redirect()->applicationUrl($target), true, 303);
    exit;
}

function agenda_return_target(?string $modal = null): string
{
    if (isset($GLOBALS['application']) && $GLOBALS['application'] instanceof App\Core\Application && trim((string) ($_POST['return_to'] ?? '')) !== '') {
        return os_return_target($GLOBALS['application'], 'agenda.php', $modal === null ? [] : ['modal' => $modal]);
    }

    $view = (string) ($_POST['return_view'] ?? 'day');
    if (!in_array($view, ['day', 'week'], true)) {
        $view = 'day';
    }

    $date = (string) ($_POST['return_date'] ?? date('Y-m-d'));
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    if (!$parsed || $parsed->format('Y-m-d') !== $date) {
        $date = date('Y-m-d');
    }

    $query = ['view' => $view, 'date' => $date];
    if ($modal !== null && $modal !== '') {
        $query['modal'] = $modal;
    }

    return 'agenda.php?' . http_build_query($query);
}
