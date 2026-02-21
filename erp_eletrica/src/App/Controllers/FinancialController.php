<?php
namespace App\Controllers;

class FinancialController extends BaseController {
    public function index() {
        $db = \App\Config\Database::getInstance()->getConnection();
        
        $stats = [
            'receber_total' => $db->query("SELECT SUM(valor) FROM contas_receber WHERE status = 'pendente'")->fetchColumn() ?: 0,
            'pagar_total' => $db->query("SELECT SUM(valor) FROM contas_pagar WHERE status = 'pendente'")->fetchColumn() ?: 0,
            'recebido_hoje' => $db->query("SELECT SUM(valor) FROM contas_receber WHERE status = 'pago' AND data_pagamento = CURRENT_DATE")->fetchColumn() ?: 0,
            'pago_hoje' => $db->query("SELECT SUM(valor) FROM contas_pagar WHERE status = 'pago' AND data_pagamento = CURRENT_DATE")->fetchColumn() ?: 0
        ];

        $receber = $db->query("
            SELECT cr.*, c.nome as cliente_nome 
            FROM contas_receber cr 
            LEFT JOIN clientes c ON cr.cliente_id = c.id 
            ORDER BY cr.data_vencimento ASC LIMIT 20
        ")->fetchAll();

        $pagar = $db->query("
            SELECT cp.* FROM contas_pagar cp 
            ORDER BY cp.data_vencimento ASC LIMIT 20
        ")->fetchAll();

        ob_start();
        $data = [
            'stats' => $stats, 
            'contas_receber' => $receber, 
            'contas_pagar' => $pagar
        ];
        extract($data);
        require __DIR__ . "/../../../views/financial.view.php";
        $content = ob_get_clean();

        $this->render('layouts/main', [
            'title' => 'Centro Financeiro Technical',
            'pageTitle' => 'Gestão de Liquidez e Tesouraria',
            'content' => $content
        ]);
    }

    public function pay() {
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $db = \App\Config\Database::getInstance()->getConnection();
            $id = $_POST['id'];
            $type = $_POST['origem']; // 'receber' ou 'pagar'
            $table = ($type == 'receber') ? 'contas_receber' : 'contas_pagar';

            $stmt = $db->prepare("UPDATE $table SET status = 'pago', data_pagamento = CURRENT_DATE WHERE id = ?");
            $stmt->execute([$id]);

            $this->redirect('financeiro.php?msg=Transação processada');
        }
    }
}
