<?php
/**
 * update_db_v2.php - Atualização da estrutura do banco para o SCP 2.0
 */

$host = 'localhost'; 
$dbname = 'u784961086_procuradoria';
$username = 'u784961086_procuradoria';
$password = '@XeFGMa8';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "Iniciando atualização do banco de dados...\n";

    // Lista de novas colunas
    $colunas = [
        'id_aviso_seeu' => "VARCHAR(100)",
        'last_sync' => "DATETIME",
        'nivel_sigilo' => "INT DEFAULT 0",
        'classe_processual' => "VARCHAR(255)",
        'magistrado' => "VARCHAR(255)",
        'id_movimentacao_seeu' => "VARCHAR(100)"
    ];

    foreach ($colunas as $coluna => $tipo) {
        $check = $pdo->query("SHOW COLUMNS FROM processos LIKE '$coluna'")->fetch();
        if (!$check) {
            $pdo->exec("ALTER TABLE processos ADD COLUMN $coluna $tipo");
            echo "Coluna '$coluna' adicionada com sucesso.\n";
        } else {
            echo "Coluna '$coluna' já existe.\n";
        }
    }

    echo "Atualização concluída com sucesso!";

} catch (PDOException $e) {
    die("Erro ao atualizar banco: " . $e->getMessage());
}
