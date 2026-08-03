<?php
declare(strict_types=1);

namespace Sigesp\Demo;

final class ResourcesDemoData
{
    private static ?array $cache = null;

    public static function datasets(): array
    {
        if (self::$cache !== null) return self::$cache;
        $beneficios = [];
        foreach (range(1, 10) as $id) {
            $beneficios[] = ['id' => $id, 'atleta' => AtletasDemoData::all()[$id - 1]['nome'], 'tipo' => ['Auxílio-transporte', 'Bolsa atleta', 'Kit esportivo', 'Ajuda de custo'][$id % 4], 'evento' => EventsDemoData::datasets()['eventos'][($id - 1) % 10]['nome'], 'valor' => 'R$ ' . number_format(150 + $id * 75, 2, ',', '.'), 'prestacao' => ['Regular', 'Pendente', 'Não exigida'][$id % 3], 'status' => ['Concedido', 'Em análise', 'Prestado contas'][$id % 3]];
        }
        $spaceNames = ['Ginásio Central', 'Arena Comunitária', 'Complexo Aquático', 'Pista Olímpica', 'Quadra do Parque', 'Centro de Lutas', 'Campo Municipal', 'Centro de Ginástica'];
        $espacos = [];
        foreach ($spaceNames as $index => $name) {
            $espacos[] = ['id' => $index + 1, 'nome' => $name, 'bairro' => ['Centro', 'Cidade Nova', 'Flores', 'Alvorada'][$index % 4], 'capacidade' => 120 + $index * 90, 'estrutura' => ['Coberto', 'Acessível', 'Vestiários', 'Iluminação'][$index % 4], 'disponibilidade' => $index % 3 === 0 ? 'Parcial' : 'Disponível', 'status' => $index === 5 ? 'Em manutenção' : 'Ativo'];
        }
        $reservas = [];
        foreach (range(1, 12) as $id) {
            $reservas[] = ['id' => $id, 'espaco' => $spaceNames[($id - 1) % 8], 'data' => sprintf('2026-08-%02d', 4 + $id), 'horario' => sprintf('%02d:00', 8 + ($id % 11)), 'finalidade' => ['Treino', 'Competição', 'Evento comunitário'][$id % 3], 'responsavel' => 'Responsável Demonstrativo ' . $id, 'status' => ['Confirmada', 'Pendente', 'Concluída'][$id % 3]];
        }
        $materialNames = ['Bola de futsal', 'Bola de voleibol', 'Cone de treino', 'Colete esportivo', 'Tatame', 'Cronômetro', 'Rede', 'Kit de primeiros socorros', 'Halter', 'Corda', 'Prancha de natação', 'Raquete', 'Capacete', 'Bicicleta', 'Barreira', 'Colchonete'];
        $materiais = [];
        foreach ($materialNames as $index => $name) {
            $stock = 4 + (($index * 7) % 28);
            $loaned = $index % 5;
            $materiais[] = ['id' => $index + 1, 'item' => $name, 'categoria' => ['Bolas', 'Treinamento', 'Proteção', 'Equipamentos'][$index % 4], 'estoque' => $stock, 'disponivel' => $stock - $loaned, 'emprestado' => $loaned, 'manutencao' => $index % 6 === 0 ? 1 : 0, 'status' => ['Disponível', 'Emprestado', 'Manutenção'][$index % 3]];
        }
        return self::$cache = compact('beneficios', 'espacos', 'reservas', 'materiais');
    }
}
