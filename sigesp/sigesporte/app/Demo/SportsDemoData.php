<?php
declare(strict_types=1);

namespace Sigesp\Demo;

final class SportsDemoData
{
    private static ?array $cache = null;

    public static function datasets(): array
    {
        if (self::$cache !== null) return self::$cache;
        $sportNames = ['Futsal', 'Voleibol', 'Natação', 'Atletismo', 'Basquete', 'Handebol', 'Judô', 'Tênis', 'Ciclismo', 'Ginástica'];
        $modalidades = [];
        foreach ($sportNames as $index => $name) {
            $modalidades[] = ['id' => $index + 1, 'nome' => $name, 'atletas' => 54 + $index * 17, 'equipes' => 1 + ($index % 4), 'categorias' => 2 + ($index % 3), 'treinadores' => 1 + ($index % 2), 'status' => $index === 9 ? 'Inativo' : 'Ativo'];
        }
        $categorias = [];
        foreach (range(1, 12) as $id) {
            $minimum = 8 + (($id - 1) % 6) * 2;
            $categorias[] = ['id' => $id, 'nome' => $minimum >= 18 ? 'Adulto ' . $id : 'Sub-' . ($minimum + 2), 'modalidade' => $sportNames[($id - 1) % 10], 'idade_minima' => $minimum, 'idade_maxima' => $minimum + 2, 'sexo' => ['Misto', 'Feminino', 'Masculino'][($id - 1) % 3], 'atletas' => 18 + $id * 3, 'status' => $id === 12 ? 'Inativa' : 'Ativa'];
        }
        $teamNames = ['Tigres do Norte', 'Seleção Municipal Feminina', 'Nadadores do Futuro', 'Velocidade Jovem', 'Cestas da Amazônia', 'Handebol Cidadão', 'Judocas do Sol', 'Raquetes Livres'];
        $equipes = [];
        foreach ($teamNames as $index => $name) {
            $equipes[] = ['id' => $index + 1, 'nome' => $name, 'modalidade' => $sportNames[$index], 'categoria' => ['Sub-14', 'Sub-17', 'Adulto'][$index % 3], 'treinador' => 'Treinador Demonstrativo ' . ($index + 1), 'atletas' => 12 + $index, 'proximo_treino' => sprintf('2026-08-%02d 17:30', 5 + $index), 'status' => 'Ativa'];
        }
        $treinos = [];
        foreach (range(1, 14) as $id) {
            $treinos[] = ['id' => $id, 'equipe' => $teamNames[($id - 1) % 8], 'treinador' => 'Treinador Demonstrativo ' . (($id - 1) % 8 + 1), 'local' => ['Ginásio Central', 'Arena Comunitária', 'Piscina Municipal', 'Pista Olímpica'][$id % 4], 'data' => sprintf('2026-08-%02d', 4 + $id), 'horario' => sprintf('%02d:00', 15 + ($id % 5)), 'participantes' => 10 + ($id % 9), 'status' => $id < 4 ? 'Concluído' : 'Agendado'];
        }
        $frequencias = [];
        foreach (range(1, 20) as $id) {
            $frequencias[] = ['id' => $id, 'atleta' => AtletasDemoData::all()[($id - 1) % 24]['nome'], 'equipe' => $teamNames[($id - 1) % 8], 'data' => sprintf('2026-07-%02d', 5 + $id), 'presenca' => ['Presente', 'Presente', 'Ausente', 'Justificado'][$id % 4], 'percentual' => 76 + ($id % 23), 'status' => 'Registrada'];
        }
        $avaliacoes = [];
        foreach (range(1, 8) as $id) {
            $avaliacoes[] = ['id' => $id, 'atleta' => AtletasDemoData::all()[$id - 1]['nome'], 'data' => sprintf('2026-%02d-15', ($id % 7) + 1), 'peso' => 48 + $id * 2, 'altura' => 1.52 + $id / 25, 'imc' => 19.2 + $id / 3, 'velocidade' => 7.4 + $id / 10, 'resistencia' => 72 + $id * 2, 'status' => 'Concluída'];
        }
        return self::$cache = compact('modalidades', 'categorias', 'equipes', 'treinos', 'frequencias', 'avaliacoes');
    }
}
