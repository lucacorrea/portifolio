<?php
session_start();
$_SESSION['usuario_nivel'] = 'master';
$_SESSION['filial_id'] = 589; // Wait, maybe Matriz is 125?

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/src/App/Controllers/ImportacaoAutomaticaController.php';

$c = new \App\Controllers\ImportacaoAutomaticaController();
try {
    $c->sincronizar();
} catch (\Throwable $e) {
    echo "CAUGHT EXCEPTION:\n" . $e->getMessage() . "\n" . $e->getTraceAsString();
}
