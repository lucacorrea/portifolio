<?php

declare(strict_types=1);

require_once __DIR__ . '/os-action-common.php';

function agenda_redirect(App\Core\Application $application, string $target = 'agenda.php'): never
{
    header('Location: ' . $application->redirect()->applicationUrl($target), true, 303);
    exit;
}
