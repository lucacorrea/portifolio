SET NAMES utf8mb4;

START TRANSACTION;

-- Exceções individuais de ações. O nível continua sendo a regra padrão;
-- esta tabela registra somente diferenças explícitas para uma pessoa usuária.
CREATE TABLE IF NOT EXISTS usuario_permissao_excecoes (
    usuario_id BIGINT UNSIGNED NOT NULL,
    permissao_id BIGINT UNSIGNED NOT NULL,
    permitido TINYINT(1) NOT NULL,
    motivo VARCHAR(500) NULL,
    atualizado_por BIGINT UNSIGNED NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (usuario_id, permissao_id),
    KEY idx_usuario_permissao_excecoes_permissao (permissao_id),
    KEY idx_usuario_permissao_excecoes_permitido (permitido),
    KEY idx_usuario_permissao_excecoes_atualizado_por (atualizado_por),
    CONSTRAINT fk_usuario_permissao_excecoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_usuario_permissao_excecoes_permissao
        FOREIGN KEY (permissao_id) REFERENCES permissoes(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_usuario_permissao_excecoes_operador
        FOREIGN KEY (atualizado_por) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Um atendimento representa uma jornada da pessoa para uma finalidade concreta.
-- A pessoa não pertence a um setor: registramos onde começou e onde está agora.
CREATE TABLE IF NOT EXISTS pessoa_atendimentos (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    protocolo VARCHAR(48) NOT NULL,
    finalidade VARCHAR(180) NOT NULL,
    beneficio_modulo VARCHAR(80) NULL,
    setor_origem_id BIGINT UNSIGNED NULL,
    modulo_origem VARCHAR(80) NOT NULL,
    setor_atual_id BIGINT UNSIGNED NULL,
    modulo_atual VARCHAR(80) NOT NULL,
    usuario_abertura_id BIGINT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'aberto',
    referencia_modulo VARCHAR(80) NULL,
    referencia_tipo VARCHAR(80) NULL,
    referencia_id BIGINT UNSIGNED NULL,
    observacao VARCHAR(500) NULL,
    aberto_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    concluido_em DATETIME NULL,
    PRIMARY KEY (id),
    UNIQUE KEY uk_pessoa_atendimentos_protocolo (protocolo),
    UNIQUE KEY uk_pessoa_atendimentos_referencia (referencia_modulo, referencia_tipo, referencia_id),
    KEY idx_pessoa_atendimentos_pessoa (pessoa_id, aberto_em),
    KEY idx_pessoa_atendimentos_status (status),
    KEY idx_pessoa_atendimentos_setor_origem (setor_origem_id),
    KEY idx_pessoa_atendimentos_setor_atual (setor_atual_id),
    KEY idx_pessoa_atendimentos_modulo_atual (modulo_atual),
    CONSTRAINT fk_pessoa_atendimentos_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_atendimentos_setor_origem
        FOREIGN KEY (setor_origem_id) REFERENCES setores(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_atendimentos_setor_atual
        FOREIGN KEY (setor_atual_id) REFERENCES setores(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_atendimentos_usuario_abertura
        FOREIGN KEY (usuario_abertura_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pessoa_movimentacoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    atendimento_id BIGINT UNSIGNED NOT NULL,
    pessoa_id BIGINT UNSIGNED NOT NULL,
    tipo VARCHAR(40) NOT NULL,
    setor_origem_id BIGINT UNSIGNED NULL,
    setor_destino_id BIGINT UNSIGNED NULL,
    modulo_origem VARCHAR(80) NULL,
    modulo_destino VARCHAR(80) NULL,
    usuario_id BIGINT UNSIGNED NULL,
    observacao VARCHAR(500) NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY idx_pessoa_movimentacoes_atendimento (atendimento_id, criado_em),
    KEY idx_pessoa_movimentacoes_pessoa (pessoa_id, criado_em),
    KEY idx_pessoa_movimentacoes_setor_destino (setor_destino_id),
    KEY idx_pessoa_movimentacoes_usuario (usuario_id),
    CONSTRAINT fk_pessoa_movimentacoes_atendimento
        FOREIGN KEY (atendimento_id) REFERENCES pessoa_atendimentos(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_movimentacoes_pessoa
        FOREIGN KEY (pessoa_id) REFERENCES pessoas(id)
        ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_movimentacoes_setor_origem
        FOREIGN KEY (setor_origem_id) REFERENCES setores(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_movimentacoes_setor_destino
        FOREIGN KEY (setor_destino_id) REFERENCES setores(id)
        ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_pessoa_movimentacoes_usuario
        FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Primeiro Emprego passa a poder apontar para a mesma pessoa central usada pelos
-- demais módulos. CPF inválido/ausente continua permitido e fica sem vínculo.
ALTER TABLE pe_candidatos
    ADD COLUMN IF NOT EXISTS pessoa_id BIGINT UNSIGNED NULL AFTER id;

CREATE INDEX IF NOT EXISTS idx_pe_candidatos_pessoa_id
    ON pe_candidatos (pessoa_id);

UPDATE pe_candidatos c
INNER JOIN pessoas p ON p.cpf = c.cpf
SET c.pessoa_id = p.id
WHERE c.pessoa_id IS NULL
  AND c.cpf IS NOT NULL
  AND CHAR_LENGTH(c.cpf) = 11;

INSERT INTO permissoes (nome, slug, descricao, modulo, ativo) VALUES
(
    'Gerenciar exceções individuais de acesso',
    'governanca.excecoes_usuario',
    'Permite alterar módulos e ações específicas para um usuário sem modificar o nível inteiro.',
    'governanca',
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
INNER JOIN permissoes p ON p.slug = 'governanca.excecoes_usuario'
WHERE n.slug IN ('administrador', 'suporte');

COMMIT;
