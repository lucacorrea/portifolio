-- SO | Aquisições favoritas | Executar manualmente no phpMyAdmin (uma vez).
-- Não modifica aquisições nem ofícios existentes.
CREATE TABLE IF NOT EXISTS aquisicoes_favoritas (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    aquisicao_origem_id INT NOT NULL,
    titulo VARCHAR(190) NOT NULL,
    dados_json LONGTEXT NOT NULL,
    itens_json LONGTEXT NOT NULL,
    criado_por INT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_aquisicao_favorita_origem (aquisicao_origem_id),
    KEY idx_aquisicoes_favoritas_atualizado (atualizado_em),
    CONSTRAINT chk_favoritas_dados_json CHECK (JSON_VALID(dados_json)),
    CONSTRAINT chk_favoritas_itens_json CHECK (JSON_VALID(itens_json))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
