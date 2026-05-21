<?php
require_once 'config/database.php';

try {
    // Atualiza o ENUM da tabela oficios para suportar o novo status
    $pdo->exec("ALTER TABLE oficios MODIFY COLUMN status ENUM('PENDENTE_ITENS', 'ENVIADO', 'EM_ANALISE', 'APROVADO', 'REPROVADO') DEFAULT 'PENDENTE_ITENS'");
    echo "Status ENUM em oficios atualizado com sucesso.<br>";

    // Atualiza o ENUM da tabela usuarios para suportar os novos níveis
    $pdo->exec("ALTER TABLE usuarios MODIFY COLUMN nivel ENUM('ADMIN', 'SUPORTE', 'SECRETARIO', 'CASA_CIVIL', 'SEFAZ', 'FUNCIONARIO') NOT NULL");
    echo "Nivel ENUM em usuarios atualizado com sucesso.<br>";

    // Adiciona as novas colunas SE elas ainda não existirem
    
    // Tabela oficios
    $stmt = $pdo->query("SHOW COLUMNS FROM oficios LIKE 'valor_orcamento'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE oficios ADD COLUMN valor_orcamento DECIMAL(15,2) NULL DEFAULT NULL AFTER arquivo_orcamento");
        echo "Coluna valor_orcamento adicionada em oficios.<br>";
    }

    $stmt = $pdo->query("SHOW COLUMNS FROM oficios LIKE 'resumo_itens'");
    if (!$stmt->fetch()) {
        $pdo->exec("ALTER TABLE oficios ADD COLUMN resumo_itens TEXT NULL AFTER justificativa");
        echo "Coluna resumo_itens adicionada em oficios.<br>";
    }

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
