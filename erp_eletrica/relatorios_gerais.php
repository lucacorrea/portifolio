<?php
require_once 'config.php';
checkAuth(['admin', 'gerente']);

use App\Controllers\ReportsController;

$controller = new ReportsController();
$data = $controller->index();

// Variáveis para a view
$kpis = $data['kpis'];
$salesChart = $data['sales_chart'];
$topProducts = $data['top_products'];
$topSellers = $data['top_sellers'];
$categoriesChart = $data['categories_chart'];
$branches = $data['branches'];
$filters = $data['filters'];

$pageTitle = "Relatórios Gerais de Inteligência";
renderView('reports_general', [
    'kpis' => $kpis,
    'salesChart' => $salesChart,
    'topProducts' => $topProducts,
    'topSellers' => $topSellers,
    'categoriesChart' => $categoriesChart,
    'branches' => $branches,
    'filters' => $filters
]);
