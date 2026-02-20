-- ============================================
-- SCRIPT DE INSTALAÇÃO - SISTEMA DE MEMBROS
-- ============================================
-- 
-- Execute este script no phpMyAdmin ou MySQL CLI
-- para criar o banco de dados e as tabelas
--

-- ============================================
-- 1. CRIAR BANCO DE DADOS
-- ============================================

CREATE DATABASE IF NOT EXISTS igreja_membros 
CHARACTER SET utf8mb4 
COLLATE utf8mb4_unicode_ci;
ndYsT0!i
-- Usar o banco de dados
USE igreja_membros;

-- ============================================
-- 2. CRIAR TABELA DE MEMBROS
-- ============================================

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
    
    -- Índices para melhor performance
    INDEX idx_nome (nome_completo),
    INDEX idx_cpf (cpf),
    INDEX idx_tipo_integracao (tipo_integracao),
    INDEX idx_data_cadastro (data_cadastro),
    INDEX idx_estado_civil (estado_civil),
    INDEX idx_sexo (sexo)
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 3. CRIAR TABELA DE LOGS (OPCIONAL)
-- ============================================

CREATE TABLE IF NOT EXISTS logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    acao VARCHAR(100),
    membro_id INT,
    usuario VARCHAR(100),
    descricao TEXT,
    ip_address VARCHAR(45),
    data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
    
    -- Índices
    INDEX idx_membro_id (membro_id),
    INDEX idx_data_hora (data_hora),
    INDEX idx_acao (acao),
    
    -- Chave estrangeira (opcional)
    FOREIGN KEY (membro_id) REFERENCES membros(id) ON DELETE CASCADE
    
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- 4. INSERIR DADOS DE EXEMPLO (OPCIONAL)
-- ============================================

INSERT INTO membros (
    nome_completo, data_nascimento, nacionalidade, naturalidade, estado_uf,
    sexo, tipo_sanguineo, escolaridade, profissao, rg, cpf, titulo_eleitor,
    ctp, cdi, filiacao_pai, filiacao_mae, estado_civil, conjuge, filhos,
    endereco_rua, endereco_numero, endereco_bairro, endereco_cep,
    endereco_cidade, endereco_uf, telefone, tipo_integracao,
    data_integracao, batismo_aguas, batismo_espirito_santo,
    procedencia, congregacao, area, nucleo
) VALUES 
(
    'João Silva Santos', '1985-03-15', 'Brasileira', 'Manaus', 'AM',
    'M', 'O+', 'Ensino Médio', 'Engenheiro', '1234567', '12345678901', '123456789',
    '', '', 'José Silva', 'Maria Santos', 'Casado', 'Ana Silva', 2,
    'Avenida Joanico', '195', 'Urucu', '69460000',
    'Coari', 'AM', '92999999999', 'Batismo',
    '2023-01-15', '2023-01-15', '2023-01-22',
    'Igreja Evangélica', 'Urucu', 'Administrativa', 'Centro'
),
(
    'Maria Oliveira Costa', '1990-07-22', 'Brasileira', 'Manaus', 'AM',
    'F', 'A+', 'Ensino Superior', 'Professora', '2345678', '23456789012', '234567890',
    '', '', 'Carlos Oliveira', 'Francisca Costa', 'Solteira', '', 0,
    'Rua das Flores', '456', 'Centro', '69460100',
    'Coari', 'AM', '92988888888', 'Mudança',
    '2023-06-10', '2020-05-20', '2020-06-10',
    'Igreja Assembléia de Deus', 'Centro', 'Educação', 'Norte'
),
(
    'Pedro Ferreira Lima', '1978-11-08', 'Brasileira', 'Belém', 'PA',
    'M', 'B+', 'Ensino Médio', 'Comerciante', '3456789', '34567890123', '345678901',
    '', '', 'Antonio Ferreira', 'Rosa Lima', 'Divorciado', '', 1,
    'Rua Principal', '789', 'Bairro Novo', '69460200',
    'Coari', 'AM', '92987777777', 'Aclamação',
    '2022-09-03', '2015-03-10', '2015-04-05',
    'Igreja Batista', 'Bairro Novo', 'Comercial', 'Leste'
),
(
    'Ana Paula Mendes', '1995-02-14', 'Brasileira', 'Manaus', 'AM',
    'F', 'AB+', 'Ensino Superior', 'Enfermeira', '4567890', '45678901234', '456789012',
    '', '', 'Roberto Mendes', 'Lucia Silva', 'Casada', 'Carlos Mendes', 1,
    'Avenida Brasil', '321', 'Urucu', '69460150',
    'Coari', 'AM', '92986666666', 'Batismo',
    '2024-01-20', '2024-01-20', '2024-02-10',
    'Sem religião', 'Urucu', 'Saúde', 'Oeste'
),
(
    'Lucas Rodrigues Alves', '2000-05-30', 'Brasileira', 'Manaus', 'AM',
    'M', 'O-', 'Ensino Superior Incompleto', 'Estudante', '5678901', '56789012345', '567890123',
    '', '', 'Marcos Rodrigues', 'Juliana Alves', 'Solteiro', '', 0,
    'Rua da Paz', '654', 'Vila Nova', '69460300',
    'Coari', 'AM', '92985555555', 'Batismo',
    '2023-08-12', '2023-08-12', '2023-09-02',
    'Criado na Igreja', 'Vila Nova', 'Juventude', 'Sul'
);

-- ============================================
-- 5. CRIAR USUÁRIO MYSQL (OPCIONAL)
-- ============================================
-- 
-- Se quiser criar um usuário específico para o sistema:
-- 
-- CREATE USER 'igreja_user'@'localhost' IDENTIFIED BY 'senha_segura_aqui';
-- GRANT ALL PRIVILEGES ON igreja_membros.* TO 'igreja_user'@'localhost';
-- FLUSH PRIVILEGES;
--
-- Depois edite config/database.php com:
-- define('DB_USER', 'igreja_user');
-- define('DB_PASS', 'senha_segura_aqui');

-- ============================================
-- 6. VERIFICAR DADOS
-- ============================================

SELECT COUNT(*) as total_membros FROM membros;
SELECT * FROM membros LIMIT 5;

-- ============================================
-- FIM DO SCRIPT
-- ============================================
