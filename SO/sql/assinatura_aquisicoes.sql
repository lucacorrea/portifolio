CREATE TABLE IF NOT EXISTS assinaturas_aquisicoes (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    aquisicao_id INT UNSIGNED NOT NULL,
    assinatura_id INT UNSIGNED NOT NULL,
    assinado_por VARCHAR(150) DEFAULT NULL,
    assinado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_assinatura_aquisicao (aquisicao_id),
    INDEX idx_assinatura_aquisicoes_assinatura (assinatura_id),
    CONSTRAINT fk_assinatura_aquisicao_aquisicao
        FOREIGN KEY (aquisicao_id) REFERENCES aquisicoes(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_assinatura_aquisicao_assinatura
        FOREIGN KEY (assinatura_id) REFERENCES assinaturas_sistema(id)
        ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
