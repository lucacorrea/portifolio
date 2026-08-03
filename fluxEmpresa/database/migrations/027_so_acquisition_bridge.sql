/* =====================================================================
 * MIGRATION 027 — PONTE FLUX EMPRESAS ↔ AQUISIÇÕES DO SO
 * =====================================================================
 *
 * Compatibilidade:
 *   MariaDB 10.4+
 *
 * Objetivos:
 *   1. Vincular orçamento, ordem de serviço e aquisição do SO.
 *   2. Impedir duplicidade de integrações e aquisições.
 *   3. Registrar direção, origem, status, tentativas e erros.
 *   4. Criar uma outbox para comunicação assíncrona e segura.
 *   5. Corrigir instalações que receberam uma versão anterior.
 *
 * Observações:
 *   - Não consulta information_schema.
 *   - Não utiliza CHECK, evitando incompatibilidade no phpMyAdmin.
 *   - Não apaga registros de integração já existentes.
 * ===================================================================== */

SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci;


/* =====================================================================
 * 1. VÍNCULO CENTRAL ENTRE FLUX EMPRESAS E SO
 * ===================================================================== */

CREATE TABLE IF NOT EXISTS integracao_so_aquisicoes (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    empresa_id INT UNSIGNED NOT NULL,

    /*
     * Preenchido quando a aquisição nasceu da aprovação
     * de um orçamento no Flux Empresas.
     */
    orcamento_id INT UNSIGNED NULL,

    /*
     * Preenchido quando:
     * - a aquisição nasceu de uma OS direta;
     * - uma OS foi criada a partir de orçamento;
     * - uma aquisição existente no SO foi convertida em OS.
     */
    ordem_servico_id INT UNSIGNED NULL,

    /*
     * ID do fornecedor correspondente no banco do SO.
     */
    fornecedor_so_id INT UNSIGNED NOT NULL,

    /*
     * Dados oficiais retornados pelo SO.
     */
    aquisicao_so_id INT UNSIGNED NULL,
    numero_aquisicao_so VARCHAR(50) NULL,
    codigo_entrega_so VARCHAR(50) NULL,

    /*
     * flux_para_so:
     *   orçamento ou OS do Flux gera aquisição no SO.
     *
     * so_para_flux:
     *   aquisição existente no SO gera OS no Flux.
     */
    direcao ENUM(
        'flux_para_so',
        'so_para_flux'
    ) NOT NULL,

    /*
     * orcamento_flux:
     *   integração originada de orçamento.
     *
     * os_flux:
     *   integração originada de OS direta.
     *
     * aquisicao_so:
     *   OS criada a partir de aquisição existente no SO.
     */
    origem ENUM(
        'orcamento_flux',
        'os_flux',
        'aquisicao_so'
    ) NOT NULL,

    status_integracao ENUM(
        'pendente',
        'processando',
        'sincronizado',
        'falha',
        'cancelado'
    ) NOT NULL DEFAULT 'pendente',

    /*
     * Status recebido do SO.
     *
     * VARCHAR permite que os estados externos evoluam sem exigir
     * alteração imediata do banco do Flux Empresas.
     */
    status_so VARCHAR(80) NULL,

    /*
     * SHA-256 da origem lógica.
     *
     * Exemplos antes do hash:
     * fluxempresa:empresa:2:orcamento:45
     * fluxempresa:empresa:2:os:158
     * so:aquisicao:981
     */
    chave_idempotencia CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL,

    /*
     * SHA-256 do payload normalizado.
     */
    payload_hash CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NULL,

    /*
     * JSON da operação que originou a integração.
     *
     * Nunca armazenar senha, token, cookie ou segredo da API.
     */
    payload_snapshot LONGTEXT NULL,

    /*
     * Última resposta sanitizada recebida do SO.
     */
    resposta_snapshot LONGTEXT NULL,

    tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    ultimo_erro_codigo VARCHAR(100) NULL,
    ultimo_erro_mensagem VARCHAR(1000) NULL,
    ultimo_erro_em DATETIME NULL,

    criado_por INT UNSIGNED NOT NULL,

    criado_em DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    atualizado_em DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    sincronizado_em DATETIME NULL,
    cancelado_em DATETIME NULL,

    PRIMARY KEY (id),

    /*
     * A mesma operação lógica não pode ser registrada duas vezes.
     */
    UNIQUE KEY uk_integracao_so_idempotencia (
        chave_idempotencia
    ),

    /*
     * Um orçamento pode possuir somente uma aquisição do SO.
     */
    UNIQUE KEY uk_integracao_so_orcamento (
        empresa_id,
        orcamento_id
    ),

    /*
     * Uma OS pode possuir somente uma aquisição vinculada.
     */
    UNIQUE KEY uk_integracao_so_ordem_servico (
        empresa_id,
        ordem_servico_id
    ),

    /*
     * Uma aquisição externa não pode gerar duas integrações.
     */
    UNIQUE KEY uk_integracao_so_aquisicao_externa (
        aquisicao_so_id
    ),

    /*
     * O número oficial retornado pelo SO não pode se repetir.
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
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


/* =====================================================================
 * 2. OUTBOX — FILA SEGURA DE COMUNICAÇÃO COM O SO
 * =====================================================================
 *
 * Na mesma transação local:
 *
 *   1. aprova orçamento ou OS;
 *   2. cria integracao_so_aquisicoes;
 *   3. cria integracao_outbox;
 *   4. executa COMMIT.
 *
 * Depois do COMMIT, um worker envia o evento ao SO.
 * ===================================================================== */

CREATE TABLE IF NOT EXISTS integracao_outbox (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,

    empresa_id INT UNSIGNED NOT NULL,

    integracao_id BIGINT UNSIGNED NOT NULL,

    /*
     * Eventos iniciais:
     * so.aquisicao.criar
     * so.aquisicao.consultar
     */
    evento VARCHAR(100) NOT NULL,

    /*
     * Mesma chave lógica utilizada na integração e no SO.
     */
    chave_idempotencia CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NOT NULL,

    /*
     * JSON completo que será enviado ao SO.
     */
    payload LONGTEXT NOT NULL,

    status ENUM(
        'pendente',
        'processando',
        'processado',
        'falha',
        'cancelado'
    ) NOT NULL DEFAULT 'pendente',

    /*
     * Menor valor representa maior prioridade.
     * A aplicação utiliza valores entre 1 e 10.
     */
    prioridade TINYINT UNSIGNED NOT NULL DEFAULT 5,

    tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 0,

    max_tentativas SMALLINT UNSIGNED NOT NULL DEFAULT 10,

    /*
     * Data a partir da qual o evento poderá ser processado.
     */
    disponivel_em DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    /*
     * Lease temporário do worker.
     */
    bloqueado_ate DATETIME NULL,

    /*
     * SHA-256 aleatório que identifica o worker atual.
     */
    worker_token CHAR(64)
        CHARACTER SET ascii
        COLLATE ascii_bin
        NULL,

    ultimo_erro_codigo VARCHAR(100) NULL,
    ultimo_erro_mensagem VARCHAR(1000) NULL,
    ultimo_erro_em DATETIME NULL,

    criado_em DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP,

    atualizado_em DATETIME NOT NULL
        DEFAULT CURRENT_TIMESTAMP
        ON UPDATE CURRENT_TIMESTAMP,

    processado_em DATETIME NULL,
    cancelado_em DATETIME NULL,

    PRIMARY KEY (id),

    /*
     * A mesma chave pode participar de eventos diferentes,
     * mas o mesmo evento não pode ser duplicado.
     */
    UNIQUE KEY uk_integracao_outbox_evento_chave (
        evento,
        chave_idempotencia
    ),

    /*
     * Índice principal do worker.
     */
    KEY idx_integracao_outbox_processamento (
        status,
        disponivel_em,
        bloqueado_ate,
        prioridade
    ),

    /*
     * Índice adicional otimizado para processamento por empresa.
     */
    KEY idx_integracao_outbox_empresa_fila (
        empresa_id,
        evento,
        status,
        disponivel_em,
        prioridade,
        id
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
        ON DELETE RESTRICT
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;


/* =====================================================================
 * 3. REPARO DE INSTALAÇÕES QUE RECEBERAM VERSÃO ANTERIOR
 * =====================================================================
 *
 * CREATE TABLE IF NOT EXISTS não altera tabelas já existentes.
 * Os comandos seguintes alinham instalações antigas sem apagar dados.
 * ===================================================================== */

/*
 * Adiciona prioridade caso ainda não exista.
 */
ALTER TABLE integracao_outbox
    ADD COLUMN IF NOT EXISTS prioridade
        TINYINT UNSIGNED NOT NULL DEFAULT 5
        AFTER status;


/*
 * Remove o índice único antigo, que não considerava o evento.
 */
DROP INDEX IF EXISTS
    uk_integracao_outbox_idempotencia
ON integracao_outbox;


/*
 * Garante o índice único correto.
 */
CREATE UNIQUE INDEX IF NOT EXISTS
    uk_integracao_outbox_evento_chave
ON integracao_outbox (
    evento,
    chave_idempotencia
);


/*
 * Recria o índice principal de processamento incluindo prioridade.
 */
DROP INDEX IF EXISTS
    idx_integracao_outbox_processamento
ON integracao_outbox;

CREATE INDEX IF NOT EXISTS
    idx_integracao_outbox_processamento
ON integracao_outbox (
    status,
    disponivel_em,
    bloqueado_ate,
    prioridade
);


/*
 * Índice otimizado para o worker filtrar por empresa e evento.
 */
CREATE INDEX IF NOT EXISTS
    idx_integracao_outbox_empresa_fila
ON integracao_outbox (
    empresa_id,
    evento,
    status,
    disponivel_em,
    prioridade,
    id
);


/*
 * Normaliza prioridades antigas ou inválidas.
 */
UPDATE integracao_outbox
SET prioridade = 5
WHERE prioridade < 1
   OR prioridade > 10;