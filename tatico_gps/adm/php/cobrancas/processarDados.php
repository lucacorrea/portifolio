<?php
/**
 * Processamento de Cobranças - Tático GPS
 */

header('Content-Type: application/json');
require_once __DIR__ . '/../conexao.php';

$acao = $_POST['acao'] ?? '';

if ($acao === 'salvar') {
    $cliente_id      = (int)$_POST['cliente_id'];
    $referencia      = $_POST['referencia'] ?? '';
    $valor           = (float)str_replace(',', '.', $_POST['valor'] ?? '0');
    $data_vencimento = $_POST['data_vencimento'] ?? '';
    $status          = $_POST['status'] ?? 'Em aberto';
    $observacoes     = $_POST['observacoes'] ?? '';

    if ($cliente_id <= 0 || empty($referencia) || empty($data_vencimento)) {
        echo json_encode(['ok' => false, 'error' => 'Preencha todos os campos obrigatórios.']);
        exit;
    }

    try {
        $sql = "INSERT INTO cobrancas (cliente_id, referencia, valor, data_vencimento, status, observacoes) 
                VALUES (:cid, :ref, :val, :venc, :status, :obs)";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':cid'    => $cliente_id,
            ':ref'    => $referencia,
            ':val'    => $valor,
            ':venc'   => $data_vencimento,
            ':status' => $status,
            ':obs'    => $observacoes
        ]);

        echo json_encode(['ok' => true]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
} 

elseif ($acao === 'gerar_lote') {
    // Gerar cobranças para todos os clientes ativos se não houver cobrança para a referência atual
    $refAtual = date('m/Y');
    
    try {
        // Buscar clientes ativos
        $stmtCli = $pdo->query("SELECT id, mensalidade, dia_vencimento FROM clientes WHERE status = 'Ativo'");
        $clientes = $stmtCli->fetchAll();
        $totalGerado = 0;

        foreach ($clientes as $cli) {
            // Verificar se já existe cobrança para este cliente nesta referência
            $stmtCheck = $pdo->prepare("SELECT COUNT(*) FROM cobrancas WHERE cliente_id = :cid AND referencia = :ref");
            $stmtCheck->execute([':cid' => $cli['id'], ':ref' => $refAtual]);
            
            if ($stmtCheck->fetchColumn() == 0) {
                // Criar data de vencimento (ajustar para o mês/ano atual)
                $dia = str_pad($cli['dia_vencimento'], 2, '0', STR_PAD_LEFT);
                $vencimento = date("Y-m-$dia");

                $sql = "INSERT INTO cobrancas (cliente_id, referencia, valor, data_vencimento, status) 
                        VALUES (:cid, :ref, :val, :venc, 'Em aberto')";
                $stmtIns = $pdo->prepare($sql);
                $stmtIns->execute([
                    ':cid'  => $cli['id'],
                    ':ref'  => $refAtual,
                    ':val'  => (float)$cli['mensalidade'],
                    ':venc' => $vencimento
                ]);
                $totalGerado++;
            }
        }

        echo json_encode(['ok' => true, 'total' => $totalGerado]);
    } catch (Exception $e) {
        echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
    }
}

else {
    echo json_encode(['ok' => false, 'error' => 'Ação inválida.']);
}
