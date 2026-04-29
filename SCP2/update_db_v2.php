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

    echo "Iniciando criação das tabelas isoladas do SCP 2.0...\n";

    // 1. Tabela de Processos v2
    $pdo->exec("CREATE TABLE IF NOT EXISTS processos_v2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        numero VARCHAR(255),
        tipo_processo VARCHAR(50),
        tipo_ato VARCHAR(255),
        natureza VARCHAR(255),
        tipo_manifestacao VARCHAR(255),
        revelia VARCHAR(50),
        data_envio VARCHAR(50),
        data_ciencia VARCHAR(50),
        tipo_contagem VARCHAR(50),
        final_prazo VARCHAR(50),
        prazo_critico VARCHAR(50),
        analisador VARCHAR(255),
        peticionador VARCHAR(255),
        quantidade_dias INT,
        status VARCHAR(100),
        data_protocolo VARCHAR(50),
        observacoes TEXT,
        id_aviso_seeu VARCHAR(100),
        last_sync DATETIME,
        nivel_sigilo INT DEFAULT 0,
        classe_processual VARCHAR(255),
        magistrado VARCHAR(255),
        id_movimentacao_seeu VARCHAR(100),
        data_criacao DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Tabela 'processos_v2' criada/verificada.\n";

    // 2. Tabela de Usuários v2
    $pdo->exec("CREATE TABLE IF NOT EXISTS usuarios_v2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(255),
        login VARCHAR(100) UNIQUE,
        senha VARCHAR(255),
        senha_plana VARCHAR(255),
        perfil VARCHAR(50) DEFAULT 'ANALISADOR'
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Tabela 'usuarios_v2' criada/verificada.\n";

    // 3. Tabela de Auditoria v2
    $pdo->exec("CREATE TABLE IF NOT EXISTS auditoria_v2 (
        id INT AUTO_INCREMENT PRIMARY KEY,
        usuario_id INT,
        usuario_nome VARCHAR(255),
        acao VARCHAR(100),
        tabela VARCHAR(100),
        registro_id INT,
        dados_anteriores TEXT,
        dados_novos TEXT,
        data_hora DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
    echo "Tabela 'auditoria_v2' criada/verificada.\n";

    echo "\nIsolamento concluído com sucesso! O SCP 2.0 agora é totalmente independente.";

} catch (PDOException $e) {
    die("Erro ao atualizar banco: " . $e->getMessage());
}
