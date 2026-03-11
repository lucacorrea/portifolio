<?php
/**
 * repair_fiscal_tables.php
 * Script para garantir que todas as tabelas fiscais (incluindo nfce_emitidas) existam no banco.
 */

require_once 'config.php';

use App\Services\MigrationService;

try {
    echo "<h1>Sincronização de Tabelas Fiscais</h1>";
    echo "<p>Iniciando verificação...</p>";
    
    $migrationService = new MigrationService();
    $migrationService->run();
    
    echo "<h3 style='color: green;'>Sucesso: Todas as tabelas fiscais (incluindo nfce_emitidas) foram criadas/atualizadas!</h3>";
    echo "<p>Você já pode fechar esta aba e continuar usando o sistema normalmente.</p>";
    
} catch (Exception $e) {
    echo "<h3 style='color: red;'>Erro ao aplicar migrações:</h3>";
    echo "<pre>" . $e->getMessage() . "</pre>";
    exit(1);
}
