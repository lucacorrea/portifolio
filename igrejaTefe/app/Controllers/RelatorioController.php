<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Services\RelatorioFinanceiroService;

final class RelatorioController
{
    public function index(): Response
    {
        $report = $this->reportService()->build((int) Session::get('igreja_id', 0), $_GET);

        return Response::html(View::render('relatorios/index', [
            'title' => 'Relatórios',
            'report' => $report,
        ]));
    }

    public function exportExcel(): Response
    {
        $report = $this->reportService()->build((int) Session::get('igreja_id', 0), $_GET);
        $filename = 'relatorio-financeiro-' . date('Y-m-d') . '.xls';
        $body = View::render('relatorios/excel', [
            'title' => 'Relatório financeiro',
            'report' => $report,
            'churchName' => Session::get('igreja_nome', 'Igreja'),
        ], null);

        return new Response("\xEF\xBB\xBF" . $body, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }

    public function exportPdf(): Response
    {
        $report = $this->reportService()->build((int) Session::get('igreja_id', 0), $_GET);

        return Response::html(View::render('relatorios/pdf', [
            'title' => 'Relatório financeiro',
            'report' => $report,
            'churchName' => Session::get('igreja_nome', 'Igreja'),
        ], null));
    }

    private function reportService(): RelatorioFinanceiroService
    {
        return new RelatorioFinanceiroService();
    }
}
