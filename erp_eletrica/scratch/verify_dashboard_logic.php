<?php
// Mocking the behavior of the DashboardController refactored logic
// This script verifies that the queries are being built correctly and parameters are mapped properly.

function mock_query_logic($is_matriz, $filial_id, $mes_atual, $ano_atual) {
    echo "Testing logic for: IsMatriz=" . ($is_matriz ? "Yes" : "No") . ", FilialID=" . var_export($filial_id, true) . "\n";
    
    // Logic from the refactored DashboardController
    $where_filial_query = $is_matriz ? "" : " AND filial_id = :filial_id";
    $params = $is_matriz ? [] : [':filial_id' => $filial_id];

    // Simulating the execute calls
    echo "1. Hoje Query: SELECT ... $where_filial_query\n";
    echo "   Params: " . json_encode($params) . "\n";

    echo "2. Mes Query: SELECT ... WHERE MONTH... = :mes AND YEAR... = :ano $where_filial_query\n";
    $mes_params = array_merge($params, [':mes' => $mes_atual, ':ano' => $ano_atual]);
    echo "   Params: " . json_encode($mes_params) . "\n";

    $sql_margem = "
        SELECT (...) / NULLIF(SUM(vi.preco_unitario * vi.quantidade), 0) * 100
        FROM ...
        WHERE MONTH(v.data_venda) = :mes " . ($is_matriz ? "" : "AND v.filial_id = :filial_id");
    echo "3. Margem Query: $sql_margem\n";
    $margem_params = array_merge([':mes' => $mes_atual], $params);
    echo "   Params: " . json_encode($margem_params) . "\n";

    echo "-----------------------------------\n";
}

echo "--- VALID SCENARIOS ---\n";
mock_query_logic(true, null, "04", "2026");
mock_query_logic(false, 1, "04", "2026");

echo "--- DANGEROUS SCENARIOS (POTENTIAL CAUSE OF ERROR) ---\n";
mock_query_logic(false, null, "04", "2026");
mock_query_logic(false, "", "04", "2026");
