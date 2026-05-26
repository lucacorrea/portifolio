CREATE TABLE IF NOT EXISTS whatsapp_notificacoes (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  pedido_id BIGINT UNSIGNED NOT NULL,
  telefone_destino VARCHAR(40) NOT NULL,
  tipo ENUM('pedido_recebido','pedido_status','pedido_reenvio') NOT NULL DEFAULT 'pedido_recebido',
  mensagem TEXT NOT NULL,
  status ENUM('pendente','enviado','erro','simulado') NOT NULL DEFAULT 'pendente',
  resposta_api TEXT NULL,
  erro TEXT NULL,
  enviado_em DATETIME NULL,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_whatsapp_notificacoes_pedido (pedido_id),
  KEY idx_whatsapp_notificacoes_status (status),
  CONSTRAINT fk_whatsapp_notificacoes_pedido
    FOREIGN KEY (pedido_id) REFERENCES pedidos (id)
    ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
