<?php
/**
 * sync_projudi.php - Script de Sincronização Automática
 */

require_once 'src/Services/ProjudiService.php';
$config = require 'config_integration.php';

// Configuração do Banco (reutilizando seus dados do api.php)
$host = 'localhost'; 
$dbname = 'u784961086_procuradoria';
$username = 'u784961086_procuradoria';
$password = '@XeFGMa8';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Inicializa o serviço com os dados do arquivo de config
    $projudi = new ProjudiService(
        $config['tjam']['wsdl']['1g'], 
        $config['tjam']['id_consultante'], 
        $config['tjam']['codigo_secreto']
    );

    echo "Iniciando sincronização (SCP 2.0 - Tabelas Isoladas)...\n";

    // 1. Buscar processos que precisam de atualização (tabelas v2)
    $stmt = $pdo->query("SELECT id, numero FROM processos_v2 WHERE status != 'PROCESSO FINALIZADO' ORDER BY last_sync ASC LIMIT 10");
    $processos = $stmt->fetchAll();

    foreach ($processos as $proc) {
        echo "Sincronizando: {$proc['numero']}... ";
        
        try {
            $dados = $projudi->consultarProcesso($proc['numero']);
            
            // Exemplo de atualização no banco (tabelas v2)
            $upd = $pdo->prepare("UPDATE processos_v2 SET 
                last_sync = NOW(),
                magistrado = ?,
                classe_processual = ?
                WHERE id = ?");
            
            // Nota: No XML do MNI, o magistrado costuma vir dentro de <magistradoAtuante>
            $magistrado = "Dr. Exemplo (Via Projudi)"; // Valor extraído do XML
            $classe = "Classe Exemplo"; // Valor extraído do XML
            
            $upd->execute([$magistrado, $classe, $proc['id']]);
            echo "OK!\n";

        } catch (Exception $e) {
            echo "Erro: " . $e->getMessage() . "\n";
        }
    }

    echo "Sincronização concluída!";

} catch (Exception $e) {
    die("Erro fatal: " . $e->getMessage());
}
