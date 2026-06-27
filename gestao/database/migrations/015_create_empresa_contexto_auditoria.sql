SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS empresa_contexto_auditoria (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    usuario_id BIGINT UNSIGNED NOT NULL,
    empresa_origem_id BIGINT UNSIGNED NULL,
    empresa_destino_id BIGINT UNSIGNED NOT NULL,
    acao ENUM(
        'login',
        'selecionar',
        'trocar',
        'criar_loja',
        'editar_loja',
        'ativar_loja',
        'inativar_loja',
        'vincular_usuario',
        'remover_vinculo'
    ) NOT NULL,
    ip VARCHAR(45) NULL,
    user_agent VARCHAR(255) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_empresa_contexto_usuario (usuario_id, criado_em),
    KEY idx_empresa_contexto_destino (empresa_destino_id, criado_em),
    CONSTRAINT fk_empresa_contexto_usuario
        FOREIGN KEY (usuario_id)
        REFERENCES usuarios(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_empresa_contexto_origem
        FOREIGN KEY (empresa_origem_id)
        REFERENCES empresas(id)
        ON DELETE RESTRICT,
    CONSTRAINT fk_empresa_contexto_destino
        FOREIGN KEY (empresa_destino_id)
        REFERENCES empresas(id)
        ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
