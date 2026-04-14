CREATE TABLE IF NOT EXISTS configuracoes_automacao (
    id                                      INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    empresa_nome                            VARCHAR(150) NOT NULL,
    empresa_cnpj                            VARCHAR(20) DEFAULT NULL,
    empresa_telefone                        VARCHAR(20) DEFAULT NULL,
    empresa_email                           VARCHAR(150) DEFAULT NULL,
    empresa_endereco                        VARCHAR(255) DEFAULT NULL,

    automacao_ativa                         TINYINT(1) NOT NULL DEFAULT 1,

    dia_vencimento_padrao                   TINYINT UNSIGNED NOT NULL DEFAULT 10,
    mensalidade_padrao                      DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    multa_atraso                            DECIMAL(5,2) NOT NULL DEFAULT 2.00,
    juros_atraso                            DECIMAL(5,2) NOT NULL DEFAULT 1.00,
    bloquear_apos_dias                      INT NOT NULL DEFAULT 7,

    pix_nome_recebedor                      VARCHAR(150) NOT NULL,
    pix_tipo_chave                          VARCHAR(30) NOT NULL,
    pix_chave                               VARCHAR(255) NOT NULL,

    mensagem_10_dias                        TEXT NOT NULL,
    mensagem_5_dias                         TEXT NOT NULL,
    mensagem_dia_vencimento                 TEXT NOT NULL,
    mensagem_7_dias_atraso                  TEXT NOT NULL,

    status_cliente_apos_atraso              VARCHAR(30) NOT NULL DEFAULT 'Pendente',
    status_cliente_apos_bloqueio            VARCHAR(30) NOT NULL DEFAULT 'Bloqueado',

    atualizado_em                           DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    criado_em                               DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
);