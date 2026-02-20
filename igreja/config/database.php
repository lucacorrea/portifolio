<?php
// Configuração do banco de dados SQLite
define('DB_PATH', __DIR__ . '/../data/membros.db');

// Criar diretório de dados se não existir
if (!is_dir(__DIR__ . '/../data')) {
    mkdir(__DIR__ . '/../data', 0755, true);
}

try {
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Criar tabela de membros se não existir
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS membros (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            nome_completo TEXT NOT NULL,
            data_nascimento DATE,
            nacionalidade TEXT,
            naturalidade TEXT,
            estado_uf TEXT,
            sexo TEXT,
            tipo_sanguineo TEXT,
            escolaridade TEXT,
            profissao TEXT,
            rg TEXT,
            cpf TEXT UNIQUE,
            titulo_eleitor TEXT,
            ctp TEXT,
            cdi TEXT,
            filiacao_pai TEXT,
            filiacao_mae TEXT,
            estado_civil TEXT,
            conjuge TEXT,
            filhos INTEGER DEFAULT 0,
            endereco_rua TEXT,
            endereco_numero TEXT,
            endereco_bairro TEXT,
            endereco_cep TEXT,
            endereco_cidade TEXT,
            endereco_uf TEXT,
            telefone TEXT,
            tipo_integracao TEXT,
            data_integracao DATE,
            batismo_aguas TEXT,
            batismo_espirito_santo TEXT,
            procedencia TEXT,
            congregacao TEXT,
            area TEXT,
            nucleo TEXT,
            foto_path TEXT,
            data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
            data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");
    
} catch (PDOException $e) {
    die("Erro ao conectar ao banco de dados: " . $e->getMessage());
}
?>
