<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Entrada;
use Throwable;

final class EntradaController
{
    public function index(): Response
    {
        $igrejaId = (int) Session::get('igreja_id', 0);
        $entradas = [];
        $summary = [
            'total' => 0,
            'quantidade' => 0,
        ];
        $loadError = null;

        if ($igrejaId > 0) {
            try {
                $entrada = new Entrada();
                $entradas = $entrada->listLatestByChurch($igrejaId);
                $summary = $entrada->currentMonthSummary($igrejaId);
            } catch (Throwable) {
                $loadError = 'Não foi possível carregar as entradas agora.';
            }
        }

        return Response::html(View::render('entradas/index', [
            'title' => 'Entradas',
            'entradas' => $entradas,
            'summary' => $summary,
            'loadError' => $loadError,
        ]));
    }

    public function create(): Response
    {
        return Response::html(View::render('entradas/create', [
            'title' => 'Cadastrar entrada',
            'today' => date('Y-m-d'),
        ]));
    }
}
