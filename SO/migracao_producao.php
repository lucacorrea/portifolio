<?php
require_once 'config/database.php';

try {
    // Atualiza o ENUM da tabela oficios para suportar o novo status
    $pdo->exec("ALTER TABLE oficios MODIFY COLUMN status ENUM('PENDENTE_ITENS', 'ENVIADO', 'EM_ANALISE', 'APROVADO', 'REPROVADO', 'ARQUIVADO') DEFAULT 'PENDENTE_ITENS'");
    echo "Status ENUM em oficios atualizado com sucesso.<br>";

    // Atualiza o ENUM da tabela usuarios para suportar os novos níveis
    $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN nivel ENUM('ADMIN', 'SUPORTE', 'SECRETARIO', 'CASA_CIVIL', 'SEFAZ', 'FUNCIONARIO') NOT NULL");
    echo "Nivel ENUM em usuarios atualizado com sucesso.<br>";

    if (function_exists('db_sync_secretarias_relatorio')) {
        db_sync_secretarias_relatorio($pdo);
        echo "Secretarias, codigos de acesso e cores de relatorio sincronizados.<br>";
    }

    // Adiciona as novas colunas SE elas ainda não existirem
    
    // Tabela oficios
    $stmt = $pdo->query("SHOW COLUMNS FROM oficios LIKE 'fornecedor_indicado_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE oficios ADD COLUMN fornecedor_indicado_id INT NULL AFTER usuario_id");
        echo "Coluna fornecedor_indicado_id adicionada em oficios.<br>";
    }

    if (!db_index_exists($pdo, 'oficios', 'idx_oficios_fornecedor_indicado')) {
        $pdo->exec("CREATE INDEX idx_oficios_fornecedor_indicado ON oficios (fornecedor_indicado_id)");
        echo "Indice de fornecedor indicado criado em oficios.<br>";
    }

    if (!db_foreign_key_exists($pdo, 'oficios', 'fk_oficios_fornecedor_indicado')) {
        $pdo->exec("
            ALTER TABLE oficios
            ADD CONSTRAINT fk_oficios_fornecedor_indicado
            FOREIGN KEY (fornecedor_indicado_id) REFERENCES fornecedores(id)
            ON DELETE SET NULL
        ");
        echo "Relacionamento de fornecedor indicado criado em oficios.<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM oficios LIKE 'arquivo_orcamento'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE oficios ADD COLUMN arquivo_orcamento VARCHAR(255) DEFAULT NULL AFTER usuario_id");
        echo "Coluna arquivo_orcamento adicionada em oficios.<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM oficios LIKE 'arquivo_oficio'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE oficios ADD COLUMN arquivo_oficio VARCHAR(255) DEFAULT NULL AFTER arquivo_orcamento");
        echo "Coluna arquivo_oficio adicionada em oficios.<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM oficios LIKE 'valor_orcamento'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE oficios ADD COLUMN valor_orcamento DECIMAL(15,2) NULL DEFAULT NULL AFTER arquivo_oficio");
        echo "Coluna valor_orcamento adicionada em oficios.<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM oficios LIKE 'resumo_itens'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE oficios ADD COLUMN resumo_itens TEXT NULL AFTER justificativa");
        echo "Coluna resumo_itens adicionada em oficios.<br>";
    }

    // Tabela de anexos/fotos dos ofícios
    $stmt = $pdo->query("SHOW TABLES LIKE 'oficio_anexos'");
    if (!$stmt->fetch()) {
        $pdo->exec("CREATE TABLE oficio_anexos (
            id INT AUTO_INCREMENT PRIMARY KEY,
            oficio_id INT NOT NULL,
            caminho VARCHAR(255) NOT NULL,
            tipo ENUM('ORCAMENTO', 'OFICIO') NOT NULL,
            nome_original VARCHAR(255),
            criado_em DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (oficio_id) REFERENCES oficios(id) ON DELETE CASCADE
        )");
        echo "Tabela oficio_anexos criada.<br>";
    } else {
        $stmt = $pdo->query("SHOW COLUMNS FROM oficio_anexos LIKE 'nome_original'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE oficio_anexos ADD COLUMN nome_original VARCHAR(255) NULL AFTER tipo");
            echo "Coluna nome_original adicionada em oficio_anexos.<br>";
        }

        $stmt = $pdo->query("SHOW COLUMNS FROM oficio_anexos LIKE 'criado_em'");
        if (!$stmt->fetch()) {
            $pdo->exec("ALTER TABLE oficio_anexos ADD COLUMN criado_em DATETIME DEFAULT CURRENT_TIMESTAMP AFTER nome_original");
            echo "Coluna criado_em adicionada em oficio_anexos.<br>";
        }
    }

    $stmt = $pdo->query("SELECT id, arquivo_orcamento, arquivo_oficio FROM oficios WHERE arquivo_orcamento IS NOT NULL OR arquivo_oficio IS NOT NULL");
    $oficiosComAnexos = $stmt->fetchAll();
    $verificaAnexo = $pdo->prepare("SELECT COUNT(*) FROM oficio_anexos WHERE oficio_id = ? AND caminho = ? AND tipo = ?");
    $insereAnexo = $pdo->prepare("INSERT INTO oficio_anexos (oficio_id, caminho, tipo, nome_original) VALUES (?, ?, ?, ?)");
    foreach ($oficiosComAnexos as $oficio) {
        if (!empty($oficio['arquivo_orcamento'])) {
            $verificaAnexo->execute([$oficio['id'], $oficio['arquivo_orcamento'], 'ORCAMENTO']);
            if ((int)$verificaAnexo->fetchColumn() === 0) {
                $insereAnexo->execute([$oficio['id'], $oficio['arquivo_orcamento'], 'ORCAMENTO', basename((string)$oficio['arquivo_orcamento'])]);
            }
        }

        if (!empty($oficio['arquivo_oficio'])) {
            $verificaAnexo->execute([$oficio['id'], $oficio['arquivo_oficio'], 'OFICIO']);
            if ((int)$verificaAnexo->fetchColumn() === 0) {
                $insereAnexo->execute([$oficio['id'], $oficio['arquivo_oficio'], 'OFICIO', basename((string)$oficio['arquivo_oficio'])]);
            }
        }
    }
    echo "Anexos antigos conferidos/migrados.<br>";

    // Tabela itens_oficio
    $stmt = $pdo->query("SHOW COLUMNS FROM itens_oficio LIKE 'valor_unitario'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE itens_oficio ADD COLUMN valor_unitario DECIMAL(15,2) NULL DEFAULT 0.00 AFTER unidade");
        echo "Coluna valor_unitario adicionada em itens_oficio.<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM itens_aquisicao LIKE 'oficio_item_id'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE itens_aquisicao ADD COLUMN oficio_item_id INT NULL AFTER aquisicao_id");
        $pdo->exec("CREATE INDEX idx_itens_aquisicao_oficio_item ON itens_aquisicao (oficio_item_id)");
        echo "Coluna oficio_item_id adicionada em itens_aquisicao.<br>";
    }

    echo "<h1>Migracao de producao concluida com sucesso!</h1>";

} catch (PDOException $e) {
    echo "<h1>Erro na atualizacao do banco de dados:</h1>";
    echo $e->getMessage();
}
