<?php
declare(strict_types=1);

namespace Sigesp\Demo;

final class EventsDemoData
{
    private static ?array $cache = null;

    public static function datasets(): array
    {
        if (self::$cache !== null) return self::$cache;
        $eventNames = ['Festival Esportivo Municipal', 'Circuito de Natação', 'Copa de Futsal', 'Encontro de Atletismo', 'Jogos da Juventude', 'Torneio de Voleibol', 'Desafio de Judô', 'Passeio Ciclístico', 'Festival de Ginástica', 'Copa dos Bairros'];
        $eventos = [];
        foreach ($eventNames as $index => $name) {
            $eventos[] = ['id' => $index + 1, 'nome' => $name, 'data' => sprintf('2026-%02d-%02d', 8 + intdiv($index, 4), 8 + $index), 'local' => ['Ginásio Central', 'Complexo Aquático', 'Arena Comunitária', 'Pista Olímpica'][$index % 4], 'modalidade' => SportsDemoData::datasets()['modalidades'][$index]['nome'], 'participantes' => 48 + $index * 17, 'status' => ['Agendado', 'Em andamento', 'Concluído'][$index % 3]];
        }
        $competicoes = [];
        foreach (range(1, 6) as $id) {
            $competicoes[] = ['id' => $id, 'nome' => ['Copa Municipal', 'Liga Comunitária', 'Circuito Escolar', 'Jogos Interbairros', 'Troféu Cidade', 'Festival de Base'][$id - 1], 'modalidade' => SportsDemoData::datasets()['modalidades'][$id - 1]['nome'], 'inicio' => sprintf('2026-%02d-10', 7 + $id), 'equipes' => 4 + $id, 'fase' => ['Inscrições', 'Grupos', 'Semifinal', 'Final'][$id % 4], 'status' => $id < 3 ? 'Em andamento' : 'Agendada'];
        }
        $inscricoes = [];
        foreach (range(1, 18) as $id) {
            $inscricoes[] = ['id' => $id, 'atleta' => AtletasDemoData::all()[$id - 1]['nome'], 'evento' => $eventNames[($id - 1) % 10], 'documentacao' => ['Regular', 'Pendente', 'Regular'][$id % 3], 'inscrito_em' => sprintf('2026-07-%02d', 3 + $id), 'status' => ['Confirmada', 'Em análise', 'Confirmada', 'Cancelada'][$id % 4]];
        }
        $resultados = [];
        foreach (range(1, 12) as $id) {
            $place = (($id - 1) % 4) + 1;
            $resultados[] = ['id' => $id, 'competicao' => $competicoes[($id - 1) % 6]['nome'], 'atleta' => AtletasDemoData::all()[$id - 1]['nome'], 'equipe' => SportsDemoData::datasets()['equipes'][($id - 1) % 8]['nome'], 'colocacao' => $place . 'º', 'medalha' => ['Ouro', 'Prata', 'Bronze', 'Sem medalha'][$place - 1], 'pontuacao' => 110 - $place * 10, 'status' => 'Homologado'];
        }
        return self::$cache = compact('eventos', 'competicoes', 'inscricoes', 'resultados');
    }
}
