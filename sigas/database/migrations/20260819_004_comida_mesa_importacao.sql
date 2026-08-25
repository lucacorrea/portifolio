SET NAMES utf8mb4;
START TRANSACTION;

CREATE TABLE IF NOT EXISTS comida_mesa_importacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    arquivo_nome VARCHAR(255) NOT NULL,
    arquivo_hash CHAR(64) NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'Processando',
    total_linhas INT UNSIGNED NOT NULL DEFAULT 0,
    novos INT UNSIGNED NOT NULL DEFAULT 0,
    atualizados INT UNSIGNED NOT NULL DEFAULT 0,
    ignorados INT UNSIGNED NOT NULL DEFAULT 0,
    revisar INT UNSIGNED NOT NULL DEFAULT 0,
    erros INT UNSIGNED NOT NULL DEFAULT 0,
    polo_padrao_id BIGINT UNSIGNED NULL,
    status_padrao VARCHAR(30) NOT NULL DEFAULT 'em_analise',
    prioridade_padrao VARCHAR(20) NOT NULL DEFAULT 'normal',
    zona_padrao VARCHAR(20) NULL,
    atualizar_existentes TINYINT(1) NOT NULL DEFAULT 0,
    criado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    finalizado_em DATETIME NULL,
    PRIMARY KEY (id),
    KEY idx_cm_importacoes_hash (arquivo_hash),
    KEY idx_cm_importacoes_status (status),
    KEY idx_cm_importacoes_criado_em (criado_em),
    KEY idx_cm_importacoes_polo_padrao (polo_padrao_id),
    KEY idx_cm_importacoes_criado_por (criado_por),
    CONSTRAINT fk_cm_importacoes_polo_padrao
        FOREIGN KEY (polo_padrao_id) REFERENCES comida_mesa_polos(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_cm_importacoes_criado_por
        FOREIGN KEY (criado_por) REFERENCES usuarios(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS comida_mesa_importacao_itens (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    importacao_id BIGINT UNSIGNED NOT NULL,
    linha INT UNSIGNED NOT NULL,
    status VARCHAR(30) NOT NULL,
    pessoa_id BIGINT UNSIGNED NULL,
    familia_id BIGINT UNSIGNED NULL,
    inscricao_id BIGINT UNSIGNED NULL,
    nome VARCHAR(150) NOT NULL,
    cpf_informado VARCHAR(40) NULL,
    cpf_validado CHAR(11) NULL,
    telefone_informado VARCHAR(40) NULL,
    polo_informado VARCHAR(180) NULL,
    classificacao VARCHAR(60) NULL,
    motivos TEXT NULL,
    dados_json LONGTEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uk_cm_importacao_linha (importacao_id, linha),
    KEY idx_cm_importacao_itens_status (status),
    KEY idx_cm_importacao_itens_classificacao (classificacao),
    KEY idx_cm_importacao_itens_cpf (cpf_validado),
    KEY idx_cm_importacao_itens_pessoa (pessoa_id),
    KEY idx_cm_importacao_itens_familia (familia_id),
    KEY idx_cm_importacao_itens_inscricao (inscricao_id),
    CONSTRAINT fk_cm_importacao_itens_importacao
        FOREIGN KEY (importacao_id) REFERENCES comida_mesa_importacoes(id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_cm_importacao_itens_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_cm_importacao_itens_familia
        FOREIGN KEY (familia_id) REFERENCES familias(id)
        ON UPDATE CASCADE ON DELETE SET NULL,
    CONSTRAINT fk_cm_importacao_itens_inscricao
        FOREIGN KEY (inscricao_id) REFERENCES comida_mesa_inscricoes(id)
        ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO permissoes (nome, slug, descricao, modulo, ativo)
VALUES (
    'Importar beneficiários no Comida na Mesa',
    'comida_mesa.importar',
    'Permite validar e importar famílias beneficiárias em lote por Excel ou CSV.',
    'comida_mesa',
    1
)
ON DUPLICATE KEY UPDATE
    nome = VALUES(nome),
    descricao = VALUES(descricao),
    modulo = VALUES(modulo),
    ativo = VALUES(ativo);

INSERT IGNORE INTO nivel_permissoes (nivel_id, permissao_id)
SELECT n.id, p.id
FROM niveis_acesso n
INNER JOIN permissoes p ON p.slug = 'comida_mesa.importar'
WHERE n.slug IN ('administrador', 'suporte', 'gestor');

COMMIT;
