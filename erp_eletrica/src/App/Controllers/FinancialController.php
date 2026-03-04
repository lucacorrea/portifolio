<?php
namespace App\Controllers;

use App\Services\FinancialService;
use App\Models\AccountPayable;
use App\Models\AccountReceivable;

class FinancialController extends BaseController {
    private $service;

    public function __construct() {
        $this->service = new FinancialService();
    }

    public function index() {
        $receivableModel = new AccountReceivable();
        $payableModel = new AccountPayable();

        $stats = [
            'areceber' => $receivableModel->getSummary()['total_pendente'],
            'apagar' => $payableModel->getSummary()['total_pendente']
        ];

        $this->render('financial/index', [
            'contas_receber' => $receivableModel->getRecent(),
            'contas_pagar' => $payableModel->getRecent(),
            'stats' => $stats,
            'pageTitle' => 'Painel Financeiro & Fluxo de Caixa'
        ]);
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

    public function abcCurve() {
        $results = $this->service->getProductABCCurve();
        $cumulative = 0;
        foreach ($results as &$res) {
            $cumulative += $res['percentage'];
            $res['cumulative'] = $cumulative;
            if ($cumulative <= 80) $res['class'] = 'A';
            elseif ($cumulative <= 95) $res['class'] = 'B';
            else $res['class'] = 'C';
        }
        $this->render('financial/abc_curve', ['results' => $results, 'pageTitle' => 'Curva ABC de Vendas']);
    }

    public function delinquency() {
        $report = $this->service->getDelinquencyReport();
        $this->render('financial/delinquency', ['report' => $report, 'pageTitle' => 'Relatório de Inadimplência']);
    }
}
