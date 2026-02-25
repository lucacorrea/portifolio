<?php
namespace App\Controllers;

use App\Models\Filial;
use App\Models\Sale;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;

class MasterController extends BaseController {
    public function index() {
        $filialModel = new Filial();
        $saleModel = new Sale();
        
        $branches = $filialModel->all();
        $recentSales = $saleModel->getRecent(15);
        
        // Consolidação Simples (Exemplo)
        $billingByBranch = $this->db->query("
            SELECT f.nome, SUM(v.valor_total) as total 
            FROM vendas v 
            JOIN filiais f ON v.filial_id = f.id 
            WHERE v.status = 'concluido'
            GROUP BY v.filial_id
            ORDER BY total DESC
        ")->fetchAll();

        $this->render('master/dashboard', [
            'branches' => $branches,
            'recentSales' => $recentSales,
            'billingByBranch' => $billingByBranch,
            'title' => 'Painel Master Global',
            'pageTitle' => 'Visão Estratégica Multi-Unidades'
        ]);
    }
}
