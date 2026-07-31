-- =====================================================================
-- MIGRATION 027 — PONTE ENTRE FLUX EMPRESAS E AQUISIÇÕES DO SO
-- =====================================================================
-- Compatibilidade: MariaDB 10.4+
--
-- OBJETIVOS
--   1. Vincular orçamento, ordem de serviço e aquisição do SO.
--   2. Impedir duplicidade de aquisição e de ordem de serviço.
--   3. Identificar a direção e a origem de cada integração.
--   4. Criar uma outbox para comunicação segura com a API do SO.
--   5. Permitir reenvio em caso de indisponibilidade ou timeout.
--
-- ESTA MIGRATION NÃO:
--   - altera o banco do SO;
--   - cria aquisição no SO;
--   - executa chamada HTTP;
--   - altera orçamento ou ordem de serviço;
--   - envia dados automaticamente;
--   - apaga registros existentes.
-- =====================================================================

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;

SET FOREIGN_KEY_CHECKS = 1;


-- =====================================================================
-- 1. VÍNCULO CENTRAL ENTRE FLUX EMPRESAS E SO
-- =====================================================================

CREATE TABLE IF NOT EXISTS integracao_so_aquisicoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    empresa_id INT UNSIGNED NOT NULL,

    /*
     * Quando a aquisição nasceu da aprovação de um orçamento,
     * orcamento_id será preenchido.
     */
    orcamento_id INT UNSIGNED NULL,

    /*
     * Quando a aquisição nasceu de uma OS direta ou quando uma
     * aquisição do SO foi convertida em OS, este campo será preenchido.
     *
     * Ao converter um orçamento em OS, a mesma integração deverá
     * receber o ID da OS, sem criar outro registro.
     */
    ordem_servico_id INT UNSIGNED NULL,

    /*
     * ID do fornecedor correspondente no banco do SO.
     *
     * Ele será obtido por meio de:
     *
     * empresa_integracoes
     * sistema = SO
     * entidade = fornecedor
     */
    fornecedor_so_id INT UNSIGNED NOT NULL,

    /*
     * Dados retornados pelo SO.
     *
     * Permanecem NULL enquanto a aquisição ainda não foi criada
     * ou enquanto a resposta da API não foi confirmada.
     */
    aquisicao_so_id INT UNSIGNED NULL,
    numero_aquisicao_so VARCHAR(50) NULL,
    codigo_entrega_so VARCHAR(50) NULL,

    /*
     * flux_para_so:
     *   Orçamento ou OS criada no Flux gera aquisição no SO.
     *
     * so_para_flux:
     *   Aquisição existente no SO gera uma OS no Flux.
     */
    direcao ENUM(
        'flux_para_so',
        'so_para_flux'
    ) NOT NULL,

    /*
     * orcamento_flux:
     *   A aquisição nasceu da aprovação de um orçamento.
     *
     * os_flux:
     *   A aquisição nasceu da aprovação de uma OS direta.
     *
     * aquisicao_so:
     *   A OS foi criada a partir de uma aquisição já existente no SO.
     */
    origem ENUM(
        'orcamento_flux',
        'os_flux',
        'aquisicao_so'
    ) NOT NULL,

    /*
     * Situação da comunicação entre os sistemas.
     */
    status_integracao ENUM(
        'pendente',
        'processando',
        'sincronizado',
        'falha',
        'cancelado'
    ) NOT NULL DEFAULT 'pendente',

    /*
     * Status original recebido do SO.
     *
     * Usamos VARCHAR porque os estados externos podem evoluir sem
     * exigir alteração imediata no ENUM do banco do Flux.
     */
    status_so VARCHAR(80) NULL,

    /*
     * Hash SHA-256 exclusivo.
     *
     * Exemplos de origem antes do hash:
     *
     * fluxempresa:empresa:2:orcamento:45
     * fluxempresa:empresa:2:os:158
     * so:aquisicao:981
     *
     * O banco armazena somente o hash final com 64 caracteres.
     */
    chave_idempotencia CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL,

    /*
     * Hash SHA-256 do payload enviado ou recebido.
     *
     * Ajuda a detectar mudanças na aquisição externa sem depender
     * apenas das datas.
     */
    payload_hash CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NULL,

    /*
     * Snapshot do dado que originou a integração.
     *
     * Deve conter JSON válido criado com json_encode().
     * Não armazenar senha, token, cookie ou segredo da API.
     */
    payload_snapshot LONGTEXT NULL,

    /*
     * Última resposta sanitizada recebida da API do SO.
     */
    resposta_snapshot LONGTEXT NULL,

    tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    ultimo_erro_codigo VARCHAR(100) NULL,
    ultimo_erro_mensagem VARCHAR(1000) NULL,
    ultimo_erro_em DATETIME NULL,

    criado_por INT UNSIGNED NOT NULL,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    atualizado_em DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    sincronizado_em DATETIME NULL,
    cancelado_em DATETIME NULL,

    PRIMARY KEY (id),

    /*
     * Uma mesma chave técnica nunca poderá criar duas integrações.
     */
    UNIQUE KEY uk_integracao_so_idempotencia (
        chave_idempotencia
    ),

    /*
     * Um orçamento pode possuir somente uma aquisição do SO.
     *
     * Como orcamento_id aceita NULL, outras origens continuam
     * permitidas normalmente.
     */
    UNIQUE KEY uk_integracao_so_orcamento (
        empresa_id,
        orcamento_id
    ),

    /*
     * Uma OS pode possuir somente uma aquisição do SO.
     */
    UNIQUE KEY uk_integracao_so_ordem_servico (
        empresa_id,
        ordem_servico_id
    ),

    /*
     * Uma aquisição do SO pode gerar somente uma integração no Flux.
     */
    UNIQUE KEY uk_integracao_so_aquisicao_externa (
        aquisicao_so_id
    ),

    /*
     * O número também é único no SO, quando já estiver disponível.
     */
    UNIQUE KEY uk_integracao_so_numero_externo (
        numero_aquisicao_so
    ),

    KEY idx_integracao_so_empresa_status (
        empresa_id,
        status_integracao,
        criado_em
    ),

    KEY idx_integracao_so_fornecedor (
        fornecedor_so_id,
        criado_em
    ),

    KEY idx_integracao_so_direcao_origem (
        direcao,
        origem,
        criado_em
    ),

    KEY idx_integracao_so_sincronizacao (
        status_integracao,
        sincronizado_em
    ),

    CONSTRAINT fk_integracao_so_empresa
        FOREIGN KEY (empresa_id)
        REFERENCES empresas(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_integracao_so_orcamento
        FOREIGN KEY (orcamento_id)
        REFERENCES orcamentos(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_integracao_so_ordem_servico
        FOREIGN KEY (ordem_servico_id)
        REFERENCES ordens_servico(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_integracao_so_criado_por
        FOREIGN KEY (criado_por)
        REFERENCES usuarios(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    /*
     * Deve existir pelo menos uma referência de origem.
     */
    CONSTRAINT ck_integracao_so_referencia
        CHECK (
            orcamento_id IS NOT NULL
            OR ordem_servico_id IS NOT NULL
            OR aquisicao_so_id IS NOT NULL
        ),

    /*
     * Orçamento do Flux exige orçamento vinculado.
     */
    CONSTRAINT ck_integracao_so_origem_orcamento
        CHECK (
            origem <> 'orcamento_flux'
            OR orcamento_id IS NOT NULL
        ),

    /*
     * OS direta exige ordem de serviço vinculada.
     */
    CONSTRAINT ck_integracao_so_origem_os
        CHECK (
            origem <> 'os_flux'
            OR ordem_servico_id IS NOT NULL
        ),

    /*
     * Importação do SO exige aquisição externa.
     */
    CONSTRAINT ck_integracao_so_origem_aquisicao
        CHECK (
            origem <> 'aquisicao_so'
            OR aquisicao_so_id IS NOT NULL
        ),

    /*
     * Flux → SO precisa partir de orçamento ou OS.
     */
    CONSTRAINT ck_integracao_so_direcao_saida
        CHECK (
            direcao <> 'flux_para_so'
            OR (
                orcamento_id IS NOT NULL
                OR ordem_servico_id IS NOT NULL
            )
        ),

    /*
     * SO → Flux precisa possuir aquisição externa.
     */
    CONSTRAINT ck_integracao_so_direcao_entrada
        CHECK (
            direcao <> 'so_para_flux'
            OR aquisicao_so_id IS NOT NULL
        )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 2. OUTBOX — FILA SEGURA DE COMUNICAÇÃO COM O SO
-- =====================================================================
-- A outbox impede que a aprovação de um orçamento ou OS dependa da
-- disponibilidade imediata da API.
--
-- A transação local fará:
--
--   1. aprovar orçamento ou OS;
--   2. criar integracao_so_aquisicoes;
--   3. criar integracao_outbox;
--   4. COMMIT.
--
-- Depois do COMMIT, outro processo envia o evento ao SO.
-- =====================================================================

CREATE TABLE IF NOT EXISTS integracao_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    empresa_id INT UNSIGNED NOT NULL,
    integracao_id BIGINT UNSIGNED NOT NULL,

    /*
     * Exemplos:
     *
     * so.aquisicao.criar
     * so.aquisicao.consultar
     * so.aquisicao.atualizar_status
     */
    evento VARCHAR(100) NOT NULL,

    /*
     * Deve ser a mesma chave lógica enviada no cabeçalho ou payload
     * da API do SO.
     */
    chave_idempotencia CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL,

    /*
     * Payload JSON completo que será enviado.
     *
     * Não guardar segredo, senha, token ou credencial.
     */
    payload LONGTEXT NOT NULL,

    status ENUM(
        'pendente',
        'processando',
        'processado',
        'falha',
        'cancelado'
    ) NOT NULL DEFAULT 'pendente',

    tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    max_tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 10,

    /*
     * Permite agendar tentativa futura com backoff.
     */
    disponivel_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    /*
     * Controle de concorrência do worker.
     *
     * Enquanto bloqueado_ate ainda estiver no futuro, outro worker
     * não deve processar este evento.
     */
    bloqueado_ate DATETIME NULL,

    worker_token CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NULL,

    ultimo_erro_codigo VARCHAR(100) NULL,
    ultimo_erro_mensagem VARCHAR(1000) NULL,
    ultimo_erro_em DATETIME NULL,

    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,

    atualizado_em DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    processado_em DATETIME NULL,
    cancelado_em DATETIME NULL,

    PRIMARY KEY (id),

    /*
     * A mesma operação não pode entrar duas vezes na fila.
     */
    UNIQUE KEY uk_integracao_outbox_idempotencia (
        chave_idempotencia
    ),

    KEY idx_integracao_outbox_processamento (
        status,
        disponivel_em,
        bloqueado_ate
    ),

    KEY idx_integracao_outbox_integracao (
        integracao_id,
        criado_em
    ),

    KEY idx_integracao_outbox_empresa (
        empresa_id,
        status,
        criado_em
    ),

    KEY idx_integracao_outbox_worker (
        worker_token,
        bloqueado_ate
    ),

    CONSTRAINT fk_integracao_outbox_empresa
        FOREIGN KEY (empresa_id)
        REFERENCES empresas(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT fk_integracao_outbox_integracao
        FOREIGN KEY (integracao_id)
        REFERENCES integracao_so_aquisicoes(id)
        ON UPDATE CASCADE
        ON DELETE RESTRICT,

    CONSTRAINT ck_integracao_outbox_tentativas
        CHECK (
            tentativas <= max_tentativas
        ),

    CONSTRAINT ck_integracao_outbox_processado
        CHECK (
            status <> 'processado'
            OR processado_em IS NOT NULL
        )
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


-- =====================================================================
-- 3. VERIFICAÇÃO DA ESTRUTURA
-- =====================================================================

SELECT
    TABLE_NAME,
    ENGINE,
    TABLE_COLLATION
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'integracao_so_aquisicoes',
      'integracao_outbox'
  )
ORDER BY TABLE_NAME;


SELECT
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_KEY
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'integracao_so_aquisicoes',
      'integracao_outbox'
  )
ORDER BY
    TABLE_NAME,
    ORDINAL_POSITION;


SELECT
    TABLE_NAME,
    INDEX_NAME,
    NON_UNIQUE,
    GROUP_CONCAT(
        COLUMN_NAME
        ORDER BY SEQ_IN_INDEX
        SEPARATOR ', '
    ) AS colunas
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
      'integracao_so_aquisicoes',
      'integracao_outbox'
  )
GROUP BY
    TABLE_NAME,
    INDEX_NAME,
    NON_UNIQUE
ORDER BY
    TABLE_NAME,
    INDEX_NAME;