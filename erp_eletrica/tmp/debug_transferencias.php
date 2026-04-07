<?php
// tmp/debug_transferencias.php - Diagnóstico do módulo B2B
require_once '../config.php';
checkAuth();

echo "<style>body{font-family:monospace;padding:20px;} table{border-collapse:collapse;margin-bottom:20px;} td,th{border:1px solid #ccc;padding:6px 10px;} th{background:#eee;}</style>";
echo "<h2>Diagnóstico: Transferências B2B</h2>";

echo "<h3>Sessão Atual</h3><table>";
echo "<tr><th>Chave</th><th>Valor</th></tr>";
foreach (['usuario_id','usuario_nome','usuario_nivel','filial_id','is_matriz'] as $k) {
    $v = $_SESSION[$k] ?? '<não definido>';
    echo "<tr><td>$k</td><td>" . htmlspecialchars(var_export($v, true)) . "</td></tr>";
}
echo "</table>";

echo "<h3>Tabela: filiais</h3>";
$filiais = $pdo->query("SELECT id, nome, principal FROM filiais ORDER BY id")->fetchAll();
echo "<table><tr><th>id</th><th>nome</th><th>principal</th></tr>";
foreach ($filiais as $f) {
    $isMatriz = $f['principal'] ? '<strong style=color:green>SIM (Matriz)</strong>' : 'NÃO';
    echo "<tr><td>{$f['id']}</td><td>{$f['nome']}</td><td>$isMatriz</td></tr>";
}
echo "</table>";

echo "<h3>Tabela: erp_transferencias (últimas 10)</h3>";
try {
    $transferencias = $pdo->query("SELECT id, codigo_transferencia, tipo, origem_filial_id, destino_filial_id, status, data_solicitacao, data_envio FROM erp_transferencias ORDER BY id DESC LIMIT 10")->fetchAll();
    echo "<table><tr><th>id</th><th>codigo</th><th>tipo</th><th>origem_filial_id</th><th>destino_filial_id</th><th>status</th><th>data_solicitacao</th><th>data_envio</th></tr>";
    foreach ($transferencias as $t) {
        echo "<tr><td>{$t['id']}</td><td>{$t['codigo_transferencia']}</td><td>{$t['tipo']}</td><td><strong>{$t['origem_filial_id']}</strong></td><td><strong>{$t['destino_filial_id']}</strong></td><td>{$t['status']}</td><td>{$t['data_solicitacao']}</td><td>{$t['data_envio']}</td></tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "<p style='color:red'>Tabela não encontrada: " . $e->getMessage() . "</p>";
}

echo "<h3>ID da Matriz (filial com principal = 1)</h3>";
$matriz = $pdo->query("SELECT id, nome FROM filiais WHERE principal = 1 LIMIT 1")->fetch();
if ($matriz) {
    echo "<p>ID: <strong>{$matriz['id']}</strong> | Nome: <strong>{$matriz['nome']}</strong></p>";
    echo "<p style='color:" . ($matriz['id'] == 1 ? 'green' : 'red') . "'>O ID da Matriz é <strong>{$matriz['id']}</strong>. ";
    echo $matriz['id'] == 1 ? "✅ OK, coincide com o código hardcoded." : "⚠️ NÃO é 1! O código está hardcoded com 1 mas a Matriz tem ID " . $matriz['id'] . ".";
    echo "</p>";
} else {
    echo "<p style='color:red'>Nenhuma filial com principal = 1 encontrada!</p>";
}

echo "<h3>Simulação da Query em_transito (filial_id da sessão)</h3>";
$filialId = $_SESSION['filial_id'] ?? null;
if ($filialId) {
    echo "<p>Filtrando por <code>destino_filial_id = $filialId</code> AND status = 'em_transito'</p>";
    $stmt = $pdo->prepare("SELECT t.*, f.nome as nome_filial FROM erp_transferencias t LEFT JOIN filiais f ON t.origem_filial_id = f.id WHERE t.destino_filial_id = ? AND t.status = 'em_transito'");
    $stmt->execute([$filialId]);
    $rows = $stmt->fetchAll();
    if (empty($rows)) {
        echo "<p style='color:orange'>⚠️ Nenhum resultado com LEFT JOIN.</p>";
        // Show all em_transito to compare
        $all = $pdo->query("SELECT id, origem_filial_id, destino_filial_id, status FROM erp_transferencias WHERE status = 'em_transito'")->fetchAll();
        echo "<p>Total de registros em_transito no banco: " . count($all) . "</p>";
        if ($all) {
            echo "<table><tr><th>id</th><th>origem_filial_id</th><th>destino_filial_id</th><th>status</th></tr>";
            foreach ($all as $r) echo "<tr><td>{$r['id']}</td><td>{$r['origem_filial_id']}</td><td>{$r['destino_filial_id']}</td><td>{$r['status']}</td></tr>";
            echo "</table>";
        }
    } else {
        echo "<p style='color:green'>✅ " . count($rows) . " resultado(s) encontrados.</p>";
    }
}
