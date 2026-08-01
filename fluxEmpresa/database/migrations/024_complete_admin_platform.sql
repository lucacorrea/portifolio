-- Completa a estrutura da administração da plataforma e a auditoria de suporte.
-- Compatibilidade: MariaDB 10.4+.

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

ALTER TABLE perfis
    ADD COLUMN IF NOT EXISTS codigo VARCHAR(80) NULL AFTER nome;

UPDATE perfis
   SET codigo = CASE
       WHEN LOWER(TRIM(nome)) IN ('suporte', 'support') THEN 'suporte'
       WHEN LOWER(TRIM(nome)) IN ('super admin', 'super administrador', 'super_administrador') THEN 'super_admin'
       ELSE CONCAT('perfil_', id)
   END
 WHERE codigo IS NULL OR TRIM(codigo) = '';

ALTER TABLE perfis
    MODIFY COLUMN codigo VARCHAR(80) NOT NULL,
    ADD UNIQUE INDEX IF NOT EXISTS uk_perfis_codigo (codigo);

CREATE TABLE IF NOT EXISTS empresas (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    uuid CHAR(36) NOT NULL,
    razao_social VARCHAR(180) NOT NULL,
    nome_fantasia VARCHAR(150) NULL,
    documento VARCHAR(14) NOT NULL,
    tipo_pessoa ENUM('fisica', 'juridica') NOT NULL,
    segmento VARCHAR(120) NOT NULL,
    contato_responsavel VARCHAR(150) NULL,
    telefone VARCHAR(30) NULL,
    email VARCHAR(150) NULL,
    status ENUM('pendente', 'ativo', 'inativo', 'bloqueado') NOT NULL DEFAULT 'pendente',
    criado_por INT UNSIGNED NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresas_uuid (uuid),
    UNIQUE KEY uk_empresas_documento (documento),
    KEY idx_empresas_status (status),
    KEY idx_empresas_criado_por (criado_por),
    CONSTRAINT fk_empresas_criado_por FOREIGN KEY (criado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empresa_integracoes (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    sistema VARCHAR(30) NOT NULL,
    entidade VARCHAR(50) NOT NULL,
    identificador_externo VARCHAR(100) NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_empresa_integracao_externa (sistema, entidade, identificador_externo),
    KEY idx_empresa_integracoes_empresa (empresa_id),
    CONSTRAINT fk_empresa_integracoes_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS empresa_acessos_administrativos (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED NOT NULL,
    ip VARCHAR(45) NOT NULL,
    motivo VARCHAR(255) NOT NULL,
    sessao_chave CHAR(64) NOT NULL,
    iniciado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    encerrado_em DATETIME NULL,
    KEY idx_empresa_acessos_empresa (empresa_id),
    KEY idx_empresa_acessos_usuario_data (usuario_id, iniciado_em),
    KEY idx_empresa_acessos_sessao_aberta (usuario_id, sessao_chave, encerrado_em),
    CONSTRAINT fk_empresa_acessos_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
        ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_empresa_acessos_usuario FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE empresas
    ADD COLUMN IF NOT EXISTS uuid CHAR(36) NULL AFTER id,
    ADD COLUMN IF NOT EXISTS razao_social VARCHAR(180) NULL,
    ADD COLUMN IF NOT EXISTS nome_fantasia VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS documento VARCHAR(14) NULL AFTER nome_fantasia,
    ADD COLUMN IF NOT EXISTS tipo_pessoa ENUM('fisica', 'juridica') NULL,
    ADD COLUMN IF NOT EXISTS segmento VARCHAR(120) NULL,
    ADD COLUMN IF NOT EXISTS contato_responsavel VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS telefone VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS email VARCHAR(150) NULL,
    ADD COLUMN IF NOT EXISTS status ENUM('pendente', 'ativo', 'inativo', 'bloqueado') NOT NULL DEFAULT 'pendente',
    ADD COLUMN IF NOT EXISTS criado_por INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

UPDATE empresas SET uuid = UUID() WHERE uuid IS NULL OR uuid = '';

SET @has_legacy_cnpj := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresas' AND COLUMN_NAME = 'cnpj'
);
SET @backfill_cnpj := IF(
    @has_legacy_cnpj > 0,
    'UPDATE empresas SET documento = REGEXP_REPLACE(cnpj, ''[^0-9]'', '''') WHERE (documento IS NULL OR documento = '''') AND cnpj IS NOT NULL',
    'SELECT 1'
);
PREPARE admin_stmt FROM @backfill_cnpj;
EXECUTE admin_stmt;
DEALLOCATE PREPARE admin_stmt;

SET @has_legacy_cpf := (
    SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'empresas' AND COLUMN_NAME = 'cpf'
);
SET @backfill_cpf := IF(
    @has_legacy_cpf > 0,
    'UPDATE empresas SET documento = REGEXP_REPLACE(cpf, ''[^0-9]'', '''') WHERE (documento IS NULL OR documento = '''') AND cpf IS NOT NULL',
    'SELECT 1'
);
PREPARE admin_stmt FROM @backfill_cpf;
EXECUTE admin_stmt;
DEALLOCATE PREPARE admin_stmt;

ALTER TABLE empresas
    ADD UNIQUE INDEX IF NOT EXISTS uk_empresas_uuid (uuid),
    ADD UNIQUE INDEX IF NOT EXISTS uk_empresas_documento (documento);

ALTER TABLE empresa_integracoes
    ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS sistema VARCHAR(30) NULL,
    ADD COLUMN IF NOT EXISTS entidade VARCHAR(50) NULL,
    ADD COLUMN IF NOT EXISTS identificador_externo VARCHAR(100) NULL,
    ADD COLUMN IF NOT EXISTS criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD UNIQUE INDEX IF NOT EXISTS uk_empresa_integracao_externa (sistema, entidade, identificador_externo),
    ADD INDEX IF NOT EXISTS idx_empresa_integracoes_empresa (empresa_id);

ALTER TABLE empresa_acessos_administrativos
    ADD COLUMN IF NOT EXISTS empresa_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS usuario_id INT UNSIGNED NULL,
    ADD COLUMN IF NOT EXISTS ip VARCHAR(45) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS motivo VARCHAR(255) NOT NULL DEFAULT 'Atendimento administrativo',
    ADD COLUMN IF NOT EXISTS sessao_chave CHAR(64) NOT NULL DEFAULT '',
    ADD COLUMN IF NOT EXISTS iniciado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ADD COLUMN IF NOT EXISTS encerrado_em DATETIME NULL,
    ADD INDEX IF NOT EXISTS idx_empresa_acessos_usuario_data (usuario_id, iniciado_em),
    ADD INDEX IF NOT EXISTS idx_empresa_acessos_sessao_aberta (usuario_id, sessao_chave, encerrado_em);
