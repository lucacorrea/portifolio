SET NAMES utf8mb4;

-- Composição familiar da entrevista social.
-- Não exige cadastro central/CPF para cada dependente; evita criar pessoas artificiais.
CREATE TABLE IF NOT EXISTS pessoa_socioeconomico_membros (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    socioeconomico_id BIGINT UNSIGNED NOT NULL,
    nome VARCHAR(160) NOT NULL,
    data_nascimento DATE NULL,
    parentesco VARCHAR(80) NULL,
    escolaridade VARCHAR(120) NULL,
    ocupacao VARCHAR(150) NULL,
    renda_mensal DECIMAL(12,2) NULL,
    possui_deficiencia TINYINT(1) NOT NULL DEFAULT 0,
    observacao VARCHAR(500) NULL,
    ordem SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,

    PRIMARY KEY (id),
    KEY idx_socio_membros_socio (socioeconomico_id, ordem, id),
    KEY idx_socio_membros_nome (nome),

    CONSTRAINT fk_socio_membros_socio
        FOREIGN KEY (socioeconomico_id) REFERENCES pessoa_socioeconomico(id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
