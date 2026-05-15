<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Igreja;
use Throwable;

final class ConfiguracaoController
{
    public function index(): Response
    {
        $igreja = [
            'nome' => Session::get('igreja_nome', 'Igreja cadastrada'),
            'cnpj' => '',
            'email' => '',
            'telefone' => '',
            'status' => 'ativa',
        ];

        try {
            $loaded = (new Igreja())->findActiveSummary((int) Session::get('igreja_id', 0));

            if (is_array($loaded)) {
                $igreja = array_merge($igreja, $loaded);
            }
        } catch (Throwable) {
            $igreja['nome'] = Session::get('igreja_nome', 'Igreja cadastrada');
        }

        return Response::html(View::render('configuracoes/index', [
            'title' => 'Configurações',
            'igreja' => $igreja,
            'usuario' => [
                'nome' => Session::get('user_name', ''),
                'email' => Session::get('user_email', ''),
                'papel' => Session::get('user_role', ''),
            ],
        ]));
    }
}
