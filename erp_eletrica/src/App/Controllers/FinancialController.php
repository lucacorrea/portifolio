<?php
namespace App\Controllers;

use App\Services\FinancialService;

class FinancialController extends BaseController {
    private $service;

    public function __construct() {
        $this->service = new FinancialService();
    }

    public function dre() {
        $month = $_GET['month'] ?? date('m');
        $year = $_GET['year'] ?? date('Y');
        
        $dre = $this->service->getDRE($month, $year);
        
        $this->render('financial/dre', [
            'dre' => $dre,
            'month' => $month,
            'year' => $year,
            'pageTitle' => "DRE - " . date('M/Y', strtotime("$year-$month-01"))
        ]);
    }

    public function osProfitability() {
        $osId = $_GET['os_id'] ?? null;
        if ($osId) {
            $report = $this->service->getOSProfitability($osId);
            $this->render('financial/os_profitability', ['report' => $report]);
        } else {
            // List all closed OS with their profitability
            $results = $this->db->query("
                SELECT id, numero_os 
                FROM os 
                WHERE status = 'concluido' 
                ORDER BY data_conclusao DESC
            ")->fetchAll();
            
            $reports = [];
            foreach($results as $res) {
                $reports[] = $this->service->getOSProfitability($res['id']);
            }
            
            $this->render('financial/os_profit_list', [
                'reports' => $reports,
                'pageTitle' => 'Rentabilidade por Ordem de Serviço'
            ]);
        }
    public function delinquency() {
        $report = $this->service->getDelinquencyReport();
        $this->render('financial/delinquency', [
            'report' => $report,
            'pageTitle' => 'Relatório de Inadimplência'
        ]);
    }
}
