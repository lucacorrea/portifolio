<?php
/**
 * Migration 20260311_add_nfce_tables.sql
 */
require_once __DIR__ . '/../../config.php';

try {
    $db = \App\Config\Database::getInstance()->getConnection();
    
    // 1. Create nfce_emitidas table
    $sqlEmitidas = "CREATE TABLE IF NOT EXISTS nfce_emitidas (
      id                                        BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
      empresa_id                                VARCHAR(40) NOT NULL,
      venda_id                                  BIGINT DEFAULT NULL,
      ambiente                                  TINYINT NOT NULL,
      serie                                     INT NOT NULL,
      numero                                    INT NOT NULL,
      chave                                     CHAR(44) NOT NULL,
      protocolo                                 VARCHAR(50) DEFAULT NULL,
      status_sefaz                              VARCHAR(10) NOT NULL,
      mensagem                                  VARCHAR(255) DEFAULT NULL,
      xml_nfeproc                               MEDIUMTEXT DEFAULT NULL,
      xml_envio                                 MEDIUMTEXT DEFAULT NULL,
      xml_retorno                               MEDIUMTEXT DEFAULT NULL,
      valor_total                               DECIMAL(12,2) DEFAULT 0.00,
      valor_troco                               DECIMAL(12,2) DEFAULT 0.00,
      tpag_json                                 LONGTEXT,
      created_at                                DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
      UNIQUE KEY unique_chave (chave)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sqlEmitidas);
    echo "Tabela nfce_emitidas criada/verificada com sucesso.\n";

    // 2. Ensure filiais table has all necessary columns
    $columns = [
        "razao_social VARCHAR(150)",
        "nome_fantasia VARCHAR(150)",
        "inscricao_estadual VARCHAR(30)",
        "inscricao_municipal VARCHAR(30)",
        "logradouro VARCHAR(150)",
        "numero_endereco VARCHAR(20)",
        "complemento VARCHAR(100)",
        "bairro VARCHAR(100)",
        "cep VARCHAR(20)",
        "cidade VARCHAR(100)",
        "uf VARCHAR(2)",
        "codigo_uf VARCHAR(2)",
        "codigo_municipio VARCHAR(10)",
        "telefone VARCHAR(20)",
        "email VARCHAR(100)",
        "ambiente TINYINT(1) DEFAULT 2",
        "regime_tributario TINYINT(1) DEFAULT 1",
        "serie_nfce INT DEFAULT 1",
        "ultimo_numero_nfce INT DEFAULT 0",
        "csc VARCHAR(100)",
        "csc_id VARCHAR(50)",
        "tipo_emissao VARCHAR(50) DEFAULT '1'",
        "finalidade VARCHAR(50) DEFAULT '1'",
        "ind_pres VARCHAR(50) DEFAULT '1'",
        "tipo_impressao VARCHAR(50) DEFAULT '4'",
        "certificado_pfx VARCHAR(255)",
        "certificado_senha VARCHAR(255)"
    ];

    foreach ($columns as $column) {
        $parts = explode(' ', trim($column));
        $colName = $parts[0];
        try {
            $db->exec("ALTER TABLE filiais ADD COLUMN $column");
            echo "Coluna $colName adicionada.\n";
        } catch (PDOException $e) {
            // Probably column already exists
            if (strpos($e->getMessage(), 'Duplicate column name') === false) {
                // Not a duplicate column error, so output it
                echo "Erro ao adicionar $colName: " . $e->getMessage() . "\n";
            }
        }
    }

    echo "Migração concluída com sucesso.\n";

} catch (Exception $e) {
    die("Erro na migração: " . $e->getMessage());
}
