<?php
/**
 * Configuração do Banco de Dados - MySQL
 * 
 * IMPORTANTE: Configure os dados de conexão abaixo com suas credenciais MySQL
 */

// ============================================
// CONFIGURAÇÕES MYSQL - EDITE AQUI!
// ============================================

// Host do MySQL (geralmente localhost)
define('DB_HOST', 'localhost');

// Nome do banco de dados
define('DB_NAME', 'u784961086_igreja_membros');

// Usuário MySQL
define('DB_USER', 'u784961086_igreja_membros');

// Senha MySQL
define('DB_PASS', 'ndYsT0!i');

// Porta MySQL (padrão: 3306)
define('DB_PORT', 3306);

// Charset
define('DB_CHARSET', 'utf8mb4');

// ============================================
// NÃO EDITE ABAIXO DESTA LINHA
// ============================================

try {
    // Criar DSN (Data Source Name)
    $dsn = "mysql:host=" . DB_HOST . ";port=" . DB_PORT . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    
    // Criar conexão PDO
    $pdo = new PDO($dsn, DB_USER, DB_PASS);
    
    // Configurar modo de erro
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Configurar fetch padrão
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    
    // Criar tabelas se não existirem
    criarTabelasMembros();
    
} catch (PDOException $e) {
    die("❌ Erro ao conectar ao banco de dados MySQL: " . $e->getMessage());
}

/**
 * Função para criar tabelas se não existirem
 */
function criarTabelasMembros() {
    global $pdo;
    
    try {
        // Criar tabela de membros
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS membros (
                id INT PRIMARY KEY AUTO_INCREMENT,
                nome_completo VARCHAR(255) NOT NULL,
                data_nascimento DATE,
                nacionalidade VARCHAR(100),
                naturalidade VARCHAR(100),
                estado_uf VARCHAR(2),
                sexo VARCHAR(1),
                tipo_sanguineo VARCHAR(5),
                escolaridade VARCHAR(100),
                profissao VARCHAR(100),
                rg VARCHAR(20),
                cpf VARCHAR(14) UNIQUE,
                titulo_eleitor VARCHAR(20),
                ctp VARCHAR(20),
                cdi VARCHAR(20),
                filiacao_pai VARCHAR(255),
                filiacao_mae VARCHAR(255),
                estado_civil VARCHAR(50),
                conjuge VARCHAR(255),
                filhos INT DEFAULT 0,
                endereco_rua VARCHAR(255),
                endereco_numero VARCHAR(10),
                endereco_bairro VARCHAR(100),
                endereco_cep VARCHAR(10),
                endereco_cidade VARCHAR(100),
                endereco_uf VARCHAR(2),
                telefone VARCHAR(20),
                tipo_integracao VARCHAR(50),
                data_integracao DATE,
                batismo_aguas DATE,
                batismo_espirito_santo DATE,
                procedencia VARCHAR(100),
                congregacao VARCHAR(100),
                area VARCHAR(100),
                nucleo VARCHAR(100),
                foto_path VARCHAR(255),
                data_cadastro DATETIME DEFAULT CURRENT_TIMESTAMP,
                data_atualizacao DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_nome (nome_completo),
                INDEX idx_cpf (cpf),
                INDEX idx_tipo_integracao (tipo_integracao),
                INDEX idx_data_cadastro (data_cadastro)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        
        // Criar tabela de logs (opcional)
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                acao VARCHAR(100),
                membro_id INT,
                usuario VARCHAR(100),
                descricao TEXT,
                data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_membro_id (membro_id),
                INDEX idx_data_hora (data_hora)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ");
        
    } catch (PDOException $e) {
        die("❌ Erro ao criar tabelas: " . $e->getMessage());
    }
}

?>
