<?php
session_start();
$_SESSION['usuario_nivel'] = 'master';
$_SESSION['filial_id'] = 125; // Try guessing a filial ID, see DB dump for Matriz ID. Oh in DB dump, Matriz is often principal=1. 

require_once __DIR__ . '/src/App/Config/Database.php';

// We just want to instance SefazConsultaService directly to see who crashes
require_once __DIR__ . '/src/App/Controllers/BaseController.php';
require_once __DIR__ . '/src/App/Controllers/ImportacaoAutomaticaController.php';

// Wait, let's just make an HTTP request to ourselves or run it directly
$c = new \App\Controllers\ImportacaoAutomaticaController();
try {
    $c->sincronizar();
} catch (\Throwable $e) {
    echo "CAUGHT EXCEPTION:\n" . $e->getMessage();
}
