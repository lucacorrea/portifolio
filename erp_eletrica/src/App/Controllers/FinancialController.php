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

        // Pagination
        $perPage = 15;
        $page = (int)($_GET['page'] ?? 1);
        $totalResults = count($results);
        $totalPages = ceil($totalResults / $perPage);
        $page = max(1, min($page, $totalPages ?: 1));
        $offset = ($page - 1) * $perPage;

        $pagedResults = array_slice($results, $offset, $perPage);

        $this->render('financial/abc_curve', [
            'results' => $pagedResults, 
            'pageTitle' => 'Curva ABC de Vendas',
            'pagination' => [
                'current' => $page,
                'total_pages' => $totalPages,
                'total_results' => $totalResults
            ]
        ]);
    }

    public function delinquency() {
        $report = $this->service->getDelinquencyReport();
        $this->render('financial/delinquency', ['report' => $report, 'pageTitle' => 'Relatório de Inadimplência']);
    }
}
