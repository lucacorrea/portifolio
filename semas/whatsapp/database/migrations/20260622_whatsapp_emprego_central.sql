CREATE TABLE IF NOT EXISTS whatsapp_semas_campanhas (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(180) NOT NULL,
  tipo VARCHAR(60) NOT NULL DEFAULT 'atualizacao_profissional',
  mensagem_modelo TEXT NOT NULL,
  filtros_json TEXT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'rascunho',
  criado_por VARCHAR(150) NULL,
  criado_por_id INT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  iniciado_em DATETIME NULL,
  finalizado_em DATETIME NULL,
  INDEX idx_whatsapp_semas_campanhas_tipo (tipo),
  INDEX idx_whatsapp_semas_campanhas_status (status),
  INDEX idx_whatsapp_semas_campanhas_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_semas_destinatarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campanha_id INT UNSIGNED NOT NULL,
  solicitante_id INT UNSIGNED NOT NULL,
  solicitacao_id INT NOT NULL,
  telefone_original VARCHAR(40) NULL,
  telefone_normalizado VARCHAR(20) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'na_fila',
  tentativas INT UNSIGNED NOT NULL DEFAULT 0,
  erro_ultimo_envio VARCHAR(255) NULL,
  mensagem_externa_id VARCHAR(120) NULL,
  complemento_enviado_em DATETIME NULL,
  enviado_em DATETIME NULL,
  entregue_em DATETIME NULL,
  lido_em DATETIME NULL,
  respondido_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_whatsapp_dest_campanha_solicitacao (campanha_id, solicitacao_id),
  KEY idx_whatsapp_dest_campanha_status (campanha_id, status),
  KEY idx_whatsapp_dest_solicitante (solicitante_id),
  KEY idx_whatsapp_dest_solicitacao (solicitacao_id),
  KEY idx_whatsapp_dest_telefone (telefone_normalizado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_semas_mensagens (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campanha_id INT UNSIGNED NULL,
  destinatario_id INT UNSIGNED NULL,
  solicitante_id INT UNSIGNED NULL,
  solicitacao_id INT NULL,
  mensagem_externa_id VARCHAR(120) NULL,
  direcao VARCHAR(20) NOT NULL,
  tipo VARCHAR(30) NOT NULL DEFAULT 'texto',
  conteudo TEXT NULL,
  telefone VARCHAR(20) NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'registrada',
  payload_sanitizado TEXT NULL,
  data_mensagem DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_whatsapp_msg_externa (mensagem_externa_id),
  KEY idx_whatsapp_msg_campanha (campanha_id),
  KEY idx_whatsapp_msg_destinatario (destinatario_id),
  KEY idx_whatsapp_msg_solicitante (solicitante_id),
  KEY idx_whatsapp_msg_telefone (telefone),
  KEY idx_whatsapp_msg_data (data_mensagem)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_semas_emprego (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  solicitante_id INT UNSIGNED NOT NULL,
  solicitacao_id INT NOT NULL,
  campanha_id INT UNSIGNED NULL,
  mensagem_id INT UNSIGNED NULL,
  resposta_original TEXT NULL,
  profissao_original VARCHAR(180) NULL,
  profissao_normalizada VARCHAR(180) NULL,
  categoria VARCHAR(120) NULL,
  confianca DECIMAL(5,2) NOT NULL DEFAULT 0,
  regra_interpretacao VARCHAR(120) NULL,
  status_revisao VARCHAR(40) NOT NULL DEFAULT 'pendente',
  resumo_anterior MEDIUMTEXT NULL,
  resumo_sugerido MEDIUMTEXT NULL,
  resumo_aprovado MEDIUMTEXT NULL,
  atualizado_por VARCHAR(150) NULL,
  revisado_por VARCHAR(150) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revisado_em DATETIME NULL,
  aplicado_em DATETIME NULL,
  KEY idx_whatsapp_att_solicitante (solicitante_id),
  KEY idx_whatsapp_att_solicitacao (solicitacao_id),
  KEY idx_whatsapp_att_campanha (campanha_id),
  KEY idx_whatsapp_att_status (status_revisao)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_semas_optout (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  solicitante_id INT UNSIGNED NULL,
  telefone_normalizado VARCHAR(20) NOT NULL,
  origem VARCHAR(80) NOT NULL DEFAULT 'whatsapp_emprego',
  motivo VARCHAR(255) NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  revogado_em DATETIME NULL,
  revogado_por VARCHAR(150) NULL,
  UNIQUE KEY uk_whatsapp_semas_optout_telefone_ativo (telefone_normalizado, ativo),
  KEY idx_whatsapp_semas_optout_solicitante (solicitante_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_semas_auditoria (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NULL,
  usuario_nome VARCHAR(150) NULL,
  acao VARCHAR(80) NOT NULL,
  solicitante_id INT UNSIGNED NULL,
  solicitacao_id INT NULL,
  campanha_id INT UNSIGNED NULL,
  antes_json TEXT NULL,
  depois_json TEXT NULL,
  ip VARCHAR(45) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_whatsapp_audit_acao (acao),
  KEY idx_whatsapp_audit_campanha (campanha_id),
  KEY idx_whatsapp_audit_solicitante (solicitante_id),
  KEY idx_whatsapp_audit_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_semas_settings (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  chave VARCHAR(120) NOT NULL,
  valor TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL,
  UNIQUE KEY uk_whatsapp_semas_settings_chave (chave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_semas_sessions (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  instance_id VARCHAR(80) NOT NULL,
  estado VARCHAR(60) NOT NULL DEFAULT 'not_initialized',
  telefone_mascarado VARCHAR(32) NULL,
  ultimo_evento_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL,
  UNIQUE KEY uk_whatsapp_semas_sessions_instance (instance_id),
  KEY idx_whatsapp_semas_sessions_estado (estado)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_semas_queue (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  campanha_id INT UNSIGNED NOT NULL,
  destinatario_id INT UNSIGNED NOT NULL,
  idempotency_key VARCHAR(120) NOT NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'pendente',
  disponivel_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  locked_at DATETIME NULL,
  locked_by VARCHAR(80) NULL,
  tentativas INT UNSIGNED NOT NULL DEFAULT 0,
  erro_ultimo VARCHAR(255) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  atualizado_em DATETIME NULL,
  UNIQUE KEY uk_whatsapp_semas_queue_idempotency (idempotency_key),
  KEY idx_whatsapp_semas_queue_status (status, disponivel_em),
  KEY idx_whatsapp_semas_queue_campanha (campanha_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_semas_conversations (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  solicitante_id INT UNSIGNED NOT NULL,
  telefone_normalizado VARCHAR(20) NOT NULL,
  ultima_mensagem_id INT UNSIGNED NULL,
  status VARCHAR(40) NOT NULL DEFAULT 'aberta',
  atualizado_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_whatsapp_semas_conv_solicitante_tel (solicitante_id, telefone_normalizado),
  KEY idx_whatsapp_semas_conv_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_semas_summary_history (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  atualizacao_id INT UNSIGNED NOT NULL,
  solicitante_id INT UNSIGNED NOT NULL,
  solicitacao_id INT NOT NULL,
  resumo_anterior MEDIUMTEXT NULL,
  resumo_novo MEDIUMTEXT NULL,
  usuario_id INT NULL,
  usuario_nome VARCHAR(150) NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_whatsapp_semas_summary_solicitacao (solicitacao_id),
  KEY idx_whatsapp_semas_summary_atualizacao (atualizacao_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS whatsapp_semas_idempotency (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  idempotency_key VARCHAR(120) NOT NULL,
  escopo VARCHAR(80) NOT NULL,
  resposta_json TEXT NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uk_whatsapp_semas_idempotency_key (idempotency_key),
  KEY idx_whatsapp_semas_idempotency_escopo (escopo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
