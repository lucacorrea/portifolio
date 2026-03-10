<?php
require_once 'config.php';

error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<div style='font-family: sans-serif; max-width: 800px; margin: 20px auto; padding: 20px; border: 1px solid #ccc; border-radius: 8px;'>";
echo "<h1 style='color: #2c3e50;'>🛠️ Super Reparador de Autorizações</h1>";

try {
    // 1. Check current table structure
    echo "<h3>1. Verificando estrutura atual...</h3>";
    $stmt = $pdo->query("DESCRIBE autorizacoes_temporarias");
    $columns = $stmt->fetchAll();
    
    $tipoCol = null;
    foreach ($columns as $col) {
        if ($col['Field'] === 'tipo') {
            $tipoCol = $col;
            break;
        }
    }

    if ($tipoCol) {
        echo "<p>Tipo atual da coluna 'tipo': <strong>{$tipoCol['Type']}</strong></p>";
        
        if (strpos(strtolower($tipoCol['Type']), 'enum') !== false) {
            echo "<p style='color: orange;'>⚠️ A coluna ainda é um ENUM! Tentando converter para VARCHAR...</p>";
            
            // Attempt conversion
            $pdo->exec("ALTER TABLE autorizacoes_temporarias MODIFY COLUMN tipo VARCHAR(50) NOT NULL");
            
            // Re-verify
            $stmt = $pdo->query("DESCRIBE autorizacoes_temporarias");
            foreach ($stmt->fetchAll() as $col) {
                if ($col['Field'] === 'tipo') {
                    echo "<p style='color: green;'>✅ Sucesso! Agora a coluna é: <strong>{$col['Type']}</strong></p>";
                    break;
                }
            }
        } else {
            echo "<p style='color: green;'>✅ A coluna já está no formato correto (VARCHAR).</p>";
        }
    } else {
        echo "<p style='color: red;'>❌ Coluna 'tipo' não encontrada!</p>";
    }

    // 2. Check migrations table
    echo "<h3>2. Histórico de Migrações:</h3>";
    $migs = $pdo->query("SELECT migration FROM migrations ORDER BY id DESC")->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($migs as $m) {
        echo "<li>$m</li>";
    }
    echo "</ul>";

    // 3. Fix existing empty records if any
    echo "<h3>3. Verificando registros órfãos...</h3>";
    $orphans = $pdo->query("SELECT COUNT(*) FROM autorizacoes_temporarias WHERE tipo = '' OR tipo IS NULL")->fetchColumn();
    if ($orphans > 0) {
        echo "<p style='color: orange;'>⚠️ Encontrados $orphans registros com tipo vazio. Eles provavelmente foram tentativas de 'Suprimento' que falharam.</p>";
        // Note: We can't safely guess, so we just inform.
    } else {
        echo "<p style='color: green;'>✅ Nenhum registro vazio encontrado.</p>";
    }

    echo "<hr><p><strong>Próximo passo:</strong> Volte na página de Gerar Código, selecione <strong>Suprimento</strong> e gere um NOVO código para testar.</p>";

} catch (Exception $e) {
    echo "<div style='background: #fee; padding: 15px; border: 1px solid #fcc;'>";
    echo "<strong>❌ Erro Crítico:</strong> " . $e->getMessage();
    echo "</div>";
}
echo "</div>";
