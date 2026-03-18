<?php
namespace App\Controllers;

use App\Models\AccountReceivable;
use App\Models\AccountReceivablePayment;
use App\Models\Client;
use App\Services\AuditLogService;

class VendaFiadoController extends BaseController {
    public function index() {
        $this->render('vendas_fiado', [
            'title' => 'Gestão de Vendas Fiado',
            'pageTitle' => 'Controle de Débitos de Clientes'
        ]);
    }

    public function fetch() {
        $model = new AccountReceivable();
        $db = \App\Config\Database::getInstance()->getConnection();
        
        $filialId = $_SESSION['filial_id'] ?? null;
        $di = $_GET['di'] ?? '';
        $df = $_GET['df'] ?? '';
        $status = $_GET['status'] ?? 'TODOS';
        
        $where = "WHERE 1=1";
        $params = [];
        
        if ($filialId && ($_SESSION['usuario_nivel'] ?? '') !== 'master') {
            $where .= " AND cr.filial_id = ?";
            $params[] = $filialId;
        }

        if ($di) {
            $where .= " AND DATE(cr.created_at) >= ?";
            $params[] = $di;
        }
        if ($df) {
            $where .= " AND DATE(cr.created_at) <= ?";
            $params[] = $df;
        }
        if ($status !== 'TODOS') {
            $where .= " AND cr.status = ?";
            $params[] = strtolower($status);
        }

        $sql = "
            SELECT cr.*, 
                   (COALESCE(cr.valor, 0) - COALESCE(cr.valor_pago, 0)) as saldo, 
                   c.nome as cliente_nome, 
                   DATEDIFF(CURRENT_DATE(), cr.data_vencimento) as dias_atraso
            FROM contas_receber cr 
            JOIN clientes c ON cr.cliente_id = c.id 
            $where
            ORDER BY cr.data_vencimento ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        // Calculate totals
        $totais = [
            'qtd' => count($rows),
            'total_venda' => 0,
            'total_pago' => 0,
            'total_restante' => 0
        ];
        foreach ($rows as $r) {
            $totais['total_venda'] += (float)$r['valor'];
            $totais['total_pago'] += (float)$r['valor_pago'];
            $totais['total_restante'] += (float)$r['saldo'];
        }

        echo json_encode([
            'ok' => true,
            'rows' => $rows,
            'totais' => $totais
        ]);
        exit;
    }

    public function get_details() {
        $id = $_GET['id'] ?? null;
        if (!$id) {
            echo json_encode(['ok' => false, 'msg' => 'ID inválido.']);
            exit;
        }

        $db = \App\Config\Database::getInstance()->getConnection();
        $sql = "
            SELECT cr.*, (COALESCE(cr.valor, 0) - COALESCE(cr.valor_pago, 0)) as saldo, 
                   c.nome as cliente_nome, v.created_at as data_venda
            FROM contas_receber cr 
            JOIN clientes c ON cr.cliente_id = c.id 
            LEFT JOIN vendas v ON cr.venda_id = v.id
            WHERE cr.id = ?
        ";
        $stmt = $db->prepare($sql);
        $stmt->execute([$id]);
        $debito = $stmt->fetch();

        if (!$debito) {
            echo json_encode(['ok' => false, 'msg' => 'Débito não encontrado.']);
            exit;
        }

        $model = new AccountReceivable();
        $items = $model->getItems($debito['venda_id']);
        
        $paymentModel = new AccountReceivablePayment();
        $payments = $paymentModel->findByFiado($id);

        echo json_encode([
            'ok' => true,
            'fiado' => $debito,
            'items' => $items,
            'payments' => $payments
        ]);
        exit;
    }

    public function pagar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $valorPago = (float)($data['valor'] ?? 0);
            $metodo = $data['metodo'] ?? 'DINHEIRO';

            if (!$id || $valorPago <= 0) {
                echo json_encode(['ok' => false, 'msg' => 'Dados inválidos.']);
                exit;
            }

            $model = new AccountReceivable();
            $debito = $model->find($id);

            if (!$debito) {
                echo json_encode(['ok' => false, 'msg' => 'Lançamento não encontrado.']);
                exit;
            }

            $saldoAtual = (float)$debito['valor'] - (float)$debito['valor_pago'];

            if ($valorPago > $saldoAtual + 0.01) {
                echo json_encode(['ok' => false, 'msg' => 'O valor informado é maior que o saldo devedor.']);
                exit;
            }

            try {
                $db = \App\Config\Database::getInstance()->getConnection();
                $db->beginTransaction();

                // Record payment history
                $paymentModel = new AccountReceivablePayment();
                $paymentModel->create([
                    'fiado_id' => $id,
                    'valor' => $valorPago,
                    'metodo' => $metodo
                ]);

                $novoValorPagoTotal = (float)$debito['valor_pago'] + $valorPago;
                $novoSaldo = (float)$debito['valor'] - $novoValorPagoTotal;
                $status = ($novoSaldo <= 0.01) ? 'pago' : 'pendente';

                $model->update($id, [
                    'valor_pago' => $novoValorPagoTotal,
                    'saldo' => max(0, $novoSaldo),
                    'status' => $status,
                    'data_pagamento' => ($status === 'pago') ? date('Y-m-d') : null
                ]);

                // Record audit log
                $audit = new AuditLogService();
                $audit->record('Pagamento fiado', 'contas_receber', $id, json_encode($debito), json_encode([
                    'valor_pago_agora' => $valorPago,
                    'metodo' => $metodo,
                    'novo_saldo' => $novoSaldo,
                    'status_final' => $status
                ]));

                $db->commit();
                echo json_encode(['ok' => true, 'msg' => 'Pagamento registrado com sucesso.']);
            } catch (\Exception $e) {
                if ($db->inTransaction()) $db->rollBack();
                echo json_encode(['ok' => false, 'msg' => 'Erro ao processar pagamento: ' . $e->getMessage()]);
            }
            exit;
        }
    }

    public function excel() {
        header('Content-Type: application/vnd.ms-excel; charset=UTF-8');
        header('Content-Disposition: attachment; filename="relatorio_fiados_' . date('Ymd_His') . '.xls"');
        
        $model = new AccountReceivable();
        $db = \App\Config\Database::getInstance()->getConnection();
        
        $filialId = $_SESSION['filial_id'] ?? null;
        $where = "WHERE 1=1";
        $params = [];
        if ($filialId && ($_SESSION['usuario_nivel'] ?? '') !== 'master') {
            $where .= " AND cr.filial_id = ?";
            $params[] = $filialId;
        }

        $sql = "
            SELECT cr.*, (cr.valor - cr.valor_pago) as saldo, c.nome as cliente_nome
            FROM contas_receber cr 
            JOIN clientes c ON cr.cliente_id = c.id 
            $where
            ORDER BY cr.data_vencimento ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll();

        echo "\xEF\xBB\xBF"; // UTF-8 BOM
        echo "<table>";
        echo "<thead><tr><th>Venda #</th><th>Cliente</th><th>Valor Total</th><th>Valor Pago</th><th>Saldo</th><th>Vencimento</th><th>Status</th></tr></thead>";
        echo "<tbody>";
        foreach ($rows as $r) {
            echo "<tr>";
            echo "<td>" . $r['venda_id'] . "</td>";
            echo "<td>" . htmlspecialchars($r['cliente_nome']) . "</td>";
            echo "<td>" . number_format($r['valor'], 2, ',', '.') . "</td>";
            echo "<td>" . number_format($r['valor_pago'], 2, ',', '.') . "</td>";
            echo "<td>" . number_format($r['saldo'], 2, ',', '.') . "</td>";
            echo "<td>" . date('d/m/Y', strtotime($r['data_vencimento'])) . "</td>";
            echo "<td>" . strtoupper($r['status']) . "</td>";
            echo "</tr>";
        }
        echo "</tbody></table>";
        exit;
    }
}
