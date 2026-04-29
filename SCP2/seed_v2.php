<?php
require_once 'config_integration.php';

// Conexão com o banco (usando as mesmas configs do sistema)
$host = 'localhost';
$db   = 'u784961086_procuradoria'; 
$user = 'u784961086_procuradoria';
$pass = '@XeFGMa8';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Iniciando carga de dados de demonstração...\n";

    // Limpar dados antigos da v2 para não duplicar no demo (OPCIONAL)
    // $pdo->exec("DELETE FROM processos_v2");

    $processos = [
        [
            'numero' => '0645123-45.2024.8.04.0001',
            'tipo_processo' => 'CIÊNCIA',
            'tipo_ato' => 'DESPACHO',
            'natureza' => 'CONTESTAÇÃO',
            'data_ciencia' => date('Y-m-d', strtotime('-2 days')),
            'final_prazo' => date('Y-m-d', strtotime('+5 days')),
            'analisador' => 'KELLEN',
            'status' => 'PENDENTE'
        ],
        [
            'numero' => '0712345-67.2023.8.04.0001',
            'tipo_processo' => 'CUMPRIMENTO',
            'tipo_ato' => 'DECISÃO',
            'natureza' => 'PAGAMENTO',
            'data_ciencia' => date('Y-m-d', strtotime('-10 days')),
            'final_prazo' => date('Y-m-d', strtotime('-1 days')),
            'analisador' => 'LUIZ',
            'status' => 'PENDENTE' // VENCIDO
        ],
        [
            'numero' => '0200987-12.2024.8.04.0001',
            'tipo_processo' => 'CIÊNCIA',
            'tipo_ato' => 'SENTENÇA',
            'natureza' => 'RECURSO INOMINADO',
            'data_ciencia' => date('Y-m-d', strtotime('-1 days')),
            'final_prazo' => date('Y-m-d', strtotime('+14 days')),
            'analisador' => 'KELLEN',
            'status' => 'EM ELABORAÇÃO'
        ],
        [
            'numero' => '0600111-22.2024.8.04.0001',
            'tipo_processo' => 'RECURSO - CIÊNCIA',
            'tipo_ato' => 'ACÓRDÃO',
            'natureza' => 'MANIFESTAÇÃO',
            'data_ciencia' => date('Y-m-d', strtotime('-5 days')),
            'final_prazo' => date('Y-m-d', strtotime('+2 days')),
            'analisador' => 'ADMIN',
            'status' => 'PROTOCOLADO',
            'data_protocolo' => date('Y-m-d H:i:s', strtotime('-1 days'))
        ],
        [
            'numero' => '0655443-00.2024.8.04.0001',
            'tipo_processo' => 'CIÊNCIA',
            'tipo_ato' => 'ATO ORDINATÓRIO',
            'natureza' => 'CIÊNCIA',
            'data_ciencia' => date('Y-m-d'),
            'final_prazo' => date('Y-m-d', strtotime('+10 days')),
            'analisador' => 'KELLEN',
            'status' => 'PENDENTE'
        ],
        [
            'numero' => '0800999-88.2023.8.04.0001',
            'tipo_processo' => 'CUMPRIMENTO',
            'tipo_ato' => 'DESPACHO',
            'natureza' => 'PETIÇÃO DIVERSA',
            'data_ciencia' => date('Y-m-d', strtotime('-20 days')),
            'final_prazo' => date('Y-m-d', strtotime('-5 days')),
            'analisador' => 'LUIZ',
            'status' => 'ANALISADO'
        ]
    ];

    $sql = "INSERT INTO processos_v2 (numero, tipo_processo, tipo_ato, natureza, data_ciencia, final_prazo, analisador, status, data_protocolo) 
            VALUES (:numero, :tipo_processo, :tipo_ato, :natureza, :data_ciencia, :final_prazo, :analisador, :status, :data_protocolo)";
    
    $stmt = $pdo->prepare($sql);

    foreach ($processos as $p) {
        $stmt->execute([
            ':numero' => $p['numero'],
            ':tipo_processo' => $p['tipo_processo'],
            ':tipo_ato' => $p['tipo_ato'],
            ':natureza' => $p['natureza'],
            ':data_ciencia' => $p['data_ciencia'],
            ':final_prazo' => $p['final_prazo'],
            ':analisador' => $p['analisador'],
            ':status' => $p['status'],
            ':data_protocolo' => $p['data_protocolo'] ?? null
        ]);
    }

    echo "Carga finalizada com sucesso! " . count($processos) . " processos inseridos.\n";

} catch (PDOException $e) {
    die("Erro ao carregar dados: " . $e->getMessage());
}
