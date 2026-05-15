<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Entrada;
use App\Models\Saida;
use Throwable;

final class RelatorioController
{
    public function index(): Response
    {
        $igrejaId = (int) Session::get('igreja_id', 0);
        $entradas = ['total' => 0, 'quantidade' => 0];
        $saidas = ['total' => 0, 'quantidade' => 0];
        $categorias = [];

        if ($igrejaId > 0) {
            try {
                $entradas = (new Entrada())->currentMonthSummary($igrejaId);
                $saidaModel = new Saida();
                $saidas = $saidaModel->currentMonthSummary($igrejaId);
                $categorias = $saidaModel->categorySummaryByChurch($igrejaId);
            } catch (Throwable) {
                $entradas = ['total' => 0, 'quantidade' => 0];
                $saidas = ['total' => 0, 'quantidade' => 0];
                $categorias = [];
            }
        }

        return Response::html(View::render('relatorios/index', [
            'title' => 'Relatórios',
            'entradas' => $entradas,
            'saidas' => $saidas,
            'categorias' => $categorias,
        ]));
    }
}
