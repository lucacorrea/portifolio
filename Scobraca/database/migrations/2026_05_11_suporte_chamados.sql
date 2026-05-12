CREATE TABLE IF NOT EXISTS suporte_chamados (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED DEFAULT NULL,
    assunto VARCHAR(160) NOT NULL,
    categoria ENUM('financeiro','tecnico','acesso','automacao','outro') NOT NULL DEFAULT 'outro',
    prioridade ENUM('baixa','media','alta','urgente') NOT NULL DEFAULT 'media',
    status ENUM('aberto','em_atendimento','aguardando_empresa','resolvido','fechado') NOT NULL DEFAULT 'aberto',
    ultima_resposta_origem ENUM('empresa','admin') NOT NULL DEFAULT 'empresa',
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    atualizado_em DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    fechado_em DATETIME DEFAULT NULL,
    INDEX idx_suporte_chamados_empresa (empresa_id),
    INDEX idx_suporte_chamados_status (status),
    INDEX idx_suporte_chamados_prioridade (prioridade),
    INDEX idx_suporte_chamados_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS suporte_mensagens (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    chamado_id INT UNSIGNED NOT NULL,
    empresa_id INT UNSIGNED NOT NULL,
    usuario_id INT UNSIGNED DEFAULT NULL,
    autor_tipo ENUM('empresa','admin') NOT NULL,
    autor_nome VARCHAR(120) NOT NULL,
    mensagem TEXT NOT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_suporte_mensagens_chamado (chamado_id),
    INDEX idx_suporte_mensagens_empresa (empresa_id),
    INDEX idx_suporte_mensagens_criado (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
