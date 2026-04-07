<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once '../config.php';

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    $sql = file_get_contents('../migrations/032_create_transferencias_estoque.sql');
    
    // Separa os comandos por ';'
    $commands = explode(';', $sql);
    
    $successCount = 0;
    foreach($commands as $command) {
        $cmd = trim($command);
        if(!empty($cmd)) {
            $db->exec($cmd);
            $successCount++;
        }
    }
    
    echo "Sucesso: $successCount comandos executados. \n";
} catch (PDOException $e) {
    die("Erro no Banco de Dados: " . $e->getMessage());
} catch (Exception $e) {
    die("Erro: " . $e->getMessage());
}
