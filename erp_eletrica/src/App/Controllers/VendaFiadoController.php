<?php
namespace App\Controllers;

use App\Models\AccountReceivable;
use App\Models\Client;
use App\Services\AuditLogService;

class VendaFiadoController extends BaseController {
    public function index() {
        $model = new AccountReceivable();
        $db = \App\Config\Database::getInstance()->getConnection();
        
        $filialId = $_SESSION['filial_id'] ?? null;
        $where = "WHERE cr.status = 'pendente'";
        $params = [];
        
        if ($filialId && ($_SESSION['usuario_nivel'] ?? '') !== 'master') {
            $where .= " AND cr.filial_id = ?";
            $params[] = $filialId;
        }

        $sql = "
            SELECT cr.*, c.nome as cliente_nome, 
                   DATEDIFF(CURRENT_DATE, cr.data_vencimento) as dias_atraso
            FROM contas_receber cr 
            JOIN clientes c ON cr.cliente_id = c.id 
            $where
            ORDER BY cr.data_vencimento ASC
        ";
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $debitos = $stmt->fetchAll();

        $this->render('vendas_fiado', [
            'debitos' => $debitos,
            'title' => 'Gestão de Vendas Fiado',
            'pageTitle' => 'Controle de Débitos de Clientes'
        ]);
    }

    public function pagar() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $data = json_decode(file_get_contents('php://input'), true);
            $id = $data['id'] ?? null;
            $valorPago = (float)($data['valor'] ?? 0);

            if (!$id || $valorPago <= 0) {
                echo json_encode(['success' => false, 'error' => 'Dados inválidos.']);
                exit;
            }

            $model = new AccountReceivable();
            $debito = $model->find($id);

            if (!$debito) {
                echo json_encode(['success' => false, 'error' => 'Lançamento não encontrado.']);
                exit;
            }

            $novoValorPago = (float)$debito['valor_pago'] + $valorPago;
            $novoSaldo = (float)$debito['valor'] - $novoValorPago;
            $status = ($novoSaldo <= 0.01) ? 'pago' : 'pendente';

            try {
                $db = \App\Config\Database::getInstance()->getConnection();
                $db->beginTransaction();

                $model->update($id, [
                    'valor_pago' => $novoValorPago,
                    'saldo' => max(0, $novoSaldo),
                    'status' => $status,
                    'data_pagamento' => ($status === 'pago') ? date('Y-m-d') : null
                ]);

                $audit = new AuditLogService();
                $audit->record('Pagamento fiado', 'contas_receber', $id, json_encode($debito), json_encode([
                    'valor_pago_agora' => $valorPago,
                    'novo_saldo' => $novoSaldo,
                    'status_final' => $status
                ]));

                $db->commit();
                echo json_encode(['success' => true]);
            } catch (\Exception $e) {
                $db->rollBack();
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            exit;
        }
    }
}
