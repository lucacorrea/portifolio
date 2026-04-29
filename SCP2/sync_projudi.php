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

    echo "Iniciando sincronização...\n";

    // 1. Buscar processos que precisam de atualização (ex: os últimos 50 não finalizados)
    $stmt = $pdo->query("SELECT id, numero FROM processos WHERE status != 'PROCESSO FINALIZADO' ORDER BY last_sync ASC LIMIT 10");
    $processos = $stmt->fetchAll();

    foreach ($processos as $proc) {
        echo "Sincronizando: {$proc['numero']}... ";
        
        try {
            $dados = $projudi->consultarProcesso($proc['numero']);
            
            // Aqui você processaria o XML de resposta (conforme o XSD que você me mandou)
            // Exemplo de atualização no banco:
            $upd = $pdo->prepare("UPDATE processos SET 
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
