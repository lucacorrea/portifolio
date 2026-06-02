<?php
require_once __DIR__ . '/../config.php';
$db = \App\Config\Database::getInstance()->getConnection();

echo "=== DIAGNOSTICO DE TRANSFERENCIAS ===\n\n";

// 1. Ultimas transferencias de Filial -> Matriz ou vice-versa
$sql = "SELECT t.id, t.codigo_transferencia, t.tipo, t.origem_filial_id, t.destino_filial_id, t.status, 
               t.data_solicitacao, t.data_envio, t.data_recebimento
        FROM erp_transferencias t
        ORDER BY t.id DESC LIMIT 10";
$transfers = $db->query($sql)->fetchAll(PDO::FETCH_ASSOC);

foreach ($transfers as $t) {
    echo "ID: {$t['id']} | Codigo: {$t['codigo_transferencia']} | Tipo: {$t['tipo']} | Origem: {$t['origem_filial_id']} -> Destino: {$t['destino_filial_id']} | Status: {$t['status']} | Recebido em: {$t['data_recebimento']}\n";
    
    // Itens da transferencia
    $stmt = $db->prepare("
        SELECT ti.produto_id, p.nome, p.codigo, ti.quantidade_solicitada, ti.quantidade_enviada, ti.quantidade_recebida,
               p.quantidade as qtd_produtos_tab,
               (SELECT quantidade FROM estoque_filiais WHERE produto_id = ti.produto_id AND filial_id = t.origem_filial_id) as qtd_origem_filial,
               (SELECT quantidade FROM estoque_filiais WHERE produto_id = ti.produto_id AND filial_id = t.destino_filial_id) as qtd_destino_filial
        FROM erp_transferencias_itens ti
        JOIN produtos p ON ti.produto_id = p.id
        JOIN erp_transferencias t ON ti.transferencia_id = t.id
        WHERE ti.transferencia_id = ?
    ");
    $stmt->execute([$t['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($items as $item) {
        echo "  - Produto: [{$item['codigo']}] {$item['nome']} (ID: {$item['produto_id']})\n";
        echo "    * Qtd Solicitada: {$item['quantidade_solicitada']} | Enviada: {$item['quantidade_enviada']} | Recebida: {$item['quantidade_recebida']}\n";
        echo "    * Estoque tabela produtos: {$item['qtd_produtos_tab']}\n";
        echo "    * Estoque filial origem ({$t['origem_filial_id']}): " . ($item['qtd_origem_filial'] ?? 'NULL') . "\n";
        echo "    * Estoque filial destino ({$t['destino_filial_id']}): " . ($item['qtd_destino_filial'] ?? 'NULL') . "\n";
    }
    echo "\n";
}
