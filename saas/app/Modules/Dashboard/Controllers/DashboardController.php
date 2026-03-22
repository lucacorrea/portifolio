<?php
declare(strict_types=1);

namespace App\Modules\Dashboard\Controllers;

require_once base_path('app/Services/MenuService.php');

use App\Services\MenuService;

final class DashboardController
{
    public function index(): string
    {
        return view('layouts/app', [
            'title'      => 'Dashboard Contábil',
            'pageTitle'  => 'Painel do Contador',
            'activeMenu' => 'dashboard',
            'menuItems'  => MenuService::items(),
            'user'       => auth_user(),

            'cards' => [
                [
                    'titulo' => 'Empresas Ativas',
                    'valor' => '86',
                    'desc' => 'clientes em acompanhamento',
                    'trend' => '+4 no mês',
                    'trend_class' => 'is-positive',
                ],
                [
                    'titulo' => 'Obrigações Hoje',
                    'valor' => '14',
                    'desc' => 'tarefas com vencimento hoje',
                    'trend' => 'urgente',
                    'trend_class' => 'is-warning',
                ],
                [
                    'titulo' => 'Guias a Vencer',
                    'valor' => '9',
                    'desc' => 'próximos 3 dias',
                    'trend' => 'prioridade',
                    'trend_class' => 'is-warning',
                ],
                [
                    'titulo' => 'Faturamento',
                    'valor' => 'R$ 28.940',
                    'desc' => 'previsão do mês',
                    'trend' => '+8%',
                    'trend_class' => 'is-positive',
                ],
            ],

            'alertas' => [
                [
                    'titulo' => '3 empresas com DAS vencendo hoje',
                    'desc' => 'Emitir e encaminhar antes do fechamento do expediente.',
                ],
                [
                    'titulo' => '5 documentos pendentes de envio',
                    'desc' => 'Cobrar retorno dos clientes para não travar o fechamento.',
                ],
                [
                    'titulo' => '2 folhas aguardando conferência',
                    'desc' => 'Validar cálculo antes da transmissão.',
                ],
            ],

            'empresasPendentes' => [
                ['empresa' => 'Auto Peças Modelo', 'categoria' => 'Comércio', 'status' => 'Documento pendente', 'prazo' => 'Hoje'],
                ['empresa' => 'Mercadinho Central', 'categoria' => 'Simples Nacional', 'status' => 'Guia em aberto', 'prazo' => 'Amanhã'],
                ['empresa' => 'Construtora Norte', 'categoria' => 'Lucro Presumido', 'status' => 'Folha para revisar', 'prazo' => 'Hoje'],
                ['empresa' => 'Clínica Vida', 'categoria' => 'Serviços', 'status' => 'SPED pendente', 'prazo' => '2 dias'],
                ['empresa' => 'Oficina Brasil', 'categoria' => 'MEI', 'status' => 'Notas para lançar', 'prazo' => 'Hoje'],
            ],

            'obrigacoes' => [
                ['nome' => 'DAS', 'quantidade' => 6, 'cor' => 'primary'],
                ['nome' => 'FGTS', 'quantidade' => 2, 'cor' => 'success'],
                ['nome' => 'Folha', 'quantidade' => 3, 'cor' => 'warning'],
                ['nome' => 'SPED', 'quantidade' => 1, 'cor' => 'danger'],
            ],

            'contentView' => 'modules/dashboard/index',
        ]);
    }
}
