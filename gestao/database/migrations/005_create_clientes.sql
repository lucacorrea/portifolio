SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS clientes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    empresa_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(180) NOT NULL,
    telefone VARCHAR(30) NULL,
    cpf_cnpj VARCHAR(20) NULL,
    endereco VARCHAR(255) NULL,
    observacao TEXT NULL,
    ativo TINYINT(1) NOT NULL DEFAULT 1,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_clientes_empresa (empresa_id),
    KEY idx_clientes_nome (nome),
    CONSTRAINT fk_clientes_empresa FOREIGN KEY (empresa_id) REFERENCES empresas(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


