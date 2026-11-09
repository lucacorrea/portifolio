<?php

declare(strict_types=1);

$pageKey = is_string($_GET['pagina'] ?? null) ? trim($_GET['pagina']) : 'painel';
$pageKey = $pageKey !== '' ? $pageKey : 'painel';

require __DIR__ . '/_layout.php';
