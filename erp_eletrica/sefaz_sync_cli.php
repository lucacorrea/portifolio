<?php
/**
 * SEFAZ Automatic Sync CLI
 * This script is intended to be run via Cron or Windows Task Scheduler.
 */

// Define as if we were in the root directory
$basePath = __DIR__;
require_once $basePath . '/config.php';

echo "[" . date('Y-m-d H:i:s') . "] Starting SEFAZ background sync...\n";

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    $service = new \App\Services\SefazConsultaService();

    // Get all active branches with CNPJ
    $stmt = $db->query("SELECT id, nome, cnpj FROM filiais WHERE cnpj IS NOT NULL AND cnpj != ''");
    $branches = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($branches)) {
        echo "No branches found with configured CNPJ.\n";
        exit;
    }

    foreach ($branches as $branch) {
        try {
            echo "Processing Branch: {$branch['nome']} ({$branch['cnpj']})...\n";
            
            // Note: We need a session filial_id because many services/models rely on it.
            // In CLI, we mock it.
            $_SESSION['filial_id'] = $branch['id'];
            $_SESSION['usuario_id'] = 0; // System/Cron

            $resultado = $service->consultarNotas($branch['cnpj']);
            $count = count($resultado['documentos'] ?? []);
            
            echo " - Success: $count new documents found.\n";
            
            // If there's more to fetch, we can loop again if needed, 
            // but for a cron run, we usually just catch up in the next run.
            if ($resultado['ultNSU'] < $resultado['maxNSU']) {
                echo " - Note: Still more documents pending in SEFAZ pool.\n";
            }

        } catch (Exception $be) {
            echo " - Error in Branch {$branch['id']}: " . $be->getMessage() . "\n";
        }
    }

    // Save global last sync timestamp
    $stmt = $db->prepare("INSERT INTO configuracoes (chave, valor) VALUES (?, ?) ON DUPLICATE KEY UPDATE valor = ?");
    $stmt->execute(['nfe_last_sync_timestamp', date('Y-m-d H:i:s'), date('Y-m-d H:i:s')]);

    echo "[" . date('Y-m-d H:i:s') . "] SEFAZ background sync completed.\n";

} catch (Exception $e) {
    echo "CRITICAL ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
