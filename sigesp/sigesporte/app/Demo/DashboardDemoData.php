<?php
declare(strict_types=1);

namespace Sigesp\Demo;

final class DashboardDemoData
{
    public static function all(): array
    {
        $athletes = AtletasDemoData::all();
        return [
            'stats' => [
                'total' => 1248,
                'ativos' => 1102,
                'novos_mes' => 84,
                'incompletos' => 32,
                'pendentes' => 47,
                'vencidos' => 18,
                'modalidades' => 14,
                'equipes' => 26,
            ],
            'chart' => [
                ['nome' => 'Futsal', 'total' => 286], ['nome' => 'Voleibol', 'total' => 218],
                ['nome' => 'Natação', 'total' => 176], ['nome' => 'Atletismo', 'total' => 154],
                ['nome' => 'Basquete', 'total' => 132], ['nome' => 'Judô', 'total' => 96],
            ],
            'charts' => [
                'faixaEtaria' => ['Até 12' => 214, '13 a 15' => 342, '16 a 18' => 391, '19 ou mais' => 301],
                'bairros' => ['Centro' => 210, 'Cidade Nova' => 188, 'Flores' => 164, 'Alvorada' => 143, 'Outros' => 543],
                'evolucao' => ['Mar' => 42, 'Abr' => 51, 'Mai' => 63, 'Jun' => 71, 'Jul' => 78, 'Ago' => 84],
                'documentos' => ['Regulares' => 1183, 'Pendentes' => 47, 'Vencidos' => 18],
                'frequencia' => ['Mar' => 86, 'Abr' => 88, 'Mai' => 87, 'Jun' => 91, 'Jul' => 90, 'Ago' => 92],
                'sexo' => ['Feminino' => 642, 'Masculino' => 582, 'Não informado' => 24],
            ],
            'recentAthletes' => array_slice($athletes, 0, 6),
            'upcomingEvents' => array_slice(EventsDemoData::datasets()['eventos'], 0, 5),
            'expiringDocuments' => array_slice(PeopleDemoData::datasets()['documentos'], 0, 5),
            'activities' => [
                ['title' => 'Frequência registrada', 'detail' => 'Equipe Tigres do Norte · há 12 min'],
                ['title' => 'Documento aprovado', 'detail' => 'Cadastro ATL-2026-0004 · há 35 min'],
                ['title' => 'Reserva confirmada', 'detail' => 'Ginásio Municipal · há 1 h'],
                ['title' => 'Atleta incluído em equipe', 'detail' => 'Seleção Municipal Feminina · há 2 h'],
            ],
            'alerts' => [
                ['tone' => 'warning', 'title' => '47 documentos pendentes', 'detail' => 'Aguardam análise da equipe'],
                ['tone' => 'danger', 'title' => '18 documentos vencidos', 'detail' => 'Solicite atualização cadastral'],
                ['tone' => 'info', 'title' => '3 espaços em manutenção', 'detail' => 'Consulte a agenda de reservas'],
            ],
            'shortcuts' => ['/atletas/novo', '/frequencias/registrar', '/eventos/novo', '/relatorios'],
            'ranking' => ['Futsal' => 286, 'Voleibol' => 218, 'Natação' => 176, 'Atletismo' => 154],
            'teams' => array_slice(SportsDemoData::datasets()['equipes'], 0, 5),
        ];
    }
}
