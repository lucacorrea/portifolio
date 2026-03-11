<?php
/**
 * repair_fiscal_tables.php
 * Script para garantir que todas as tabelas fiscais (incluindo nfce_emitidas) existam no banco.
 */

require_once 'config.php';

use App\Services\MigrationService;

try {
    echo "Iniciando verificação de tabelas fiscais...\n";
    
    $migrationService = new MigrationService();
    $migrationService->run();
    
    echo "Sucesso: Todas as migrações (incluindo nfce_emitidas) foram aplicadas!\n";
    echo "Você já pode realizar vendas e emissões de NFC-e normalmente.\n";
    
} catch (Exception $e) {
    echo "Erro ao aplicar migrações: " . $e->getMessage() . "\n";
    exit(1);
}
