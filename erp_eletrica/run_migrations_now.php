<?php
require 'config.php';
$migrationService = new \App\Services\MigrationService();
try {
    $migrationService->run();
    echo "Migrações executadas com sucesso.";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
