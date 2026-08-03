<?php
declare(strict_types=1);

namespace Sigesp\Demo;

final class AtletasDemoData
{
    private static ?array $cache = null;

    public static function all(): array
    {
        if (self::$cache !== null) return self::$cache;
        $names = [
            'Ana Beatriz Souza', 'Bruno Gabriel Lima', 'Carla Vitória Rocha', 'Daniel Henrique Alves',
            'Ester Sofia Martins', 'Felipe Augusto Nunes', 'Gabriela Melo Costa', 'Heitor Lucas Barros',
            'Isabela Cristina Reis', 'João Pedro Freitas', 'Kauã Rafael Gomes', 'Larissa Eduarda Dias',
            'Miguel Ângelo Castro', 'Nicole Maria Lopes', 'Otávio César Ramos', 'Paula Fernanda Luz',
            'Rafael Vinícius Moraes', 'Sara Helena Pinto', 'Thiago André Xavier', 'Valentina Alves Melo',
            'Wesley Matheus Silva', 'Yasmin Clara Tavares', 'Arthur Bento Sales', 'Luísa Manuela Prado',
        ];
        $sports = ['Voleibol', 'Futsal', 'Natação', 'Atletismo', 'Basquete', 'Handebol', 'Judô', 'Tênis', 'Ciclismo', 'Ginástica'];
        $neighborhoods = ['Centro', 'São José', 'Planalto', 'Cidade Nova', 'Flores', 'Alvorada', 'Parque Dez', 'Compensa'];
        $teams = ['Seleção Municipal Feminina', 'Tigres do Norte', 'Nadadores do Futuro', 'Velocidade Jovem', 'Cestas da Amazônia', 'Handebol Cidadão', 'Judocas do Sol', 'Raquetes Livres'];
        $statuses = ['Ativo', 'Ativo', 'Ativo', 'Ativo', 'Inativo', 'Cadastro incompleto'];
        $documents = ['Regular', 'Aprovado', 'Pendente', 'Regular', 'Vencido'];

        return self::$cache = array_map(static function (string $name, int $index) use ($sports, $neighborhoods, $teams, $statuses, $documents): array {
            $id = $index + 1;
            $year = 2005 + ($index % 11);
            $month = str_pad((string) (($index % 12) + 1), 2, '0', STR_PAD_LEFT);
            $day = str_pad((string) (($index % 25) + 1), 2, '0', STR_PAD_LEFT);
            $birth = sprintf('%d-%s-%s', $year, $month, $day);
            $age = (new \DateTimeImmutable($birth))->diff(new \DateTimeImmutable('2026-08-03'))->y;
            return [
                'id' => $id,
                'codigo' => sprintf('ATL-2026-%04d', $id),
                'nome' => $name,
                'nome_social' => null,
                'cpf' => sprintf('***.***.***-%02d', 10 + $id),
                'nascimento' => $birth,
                'idade' => $age,
                'sexo' => $index % 2 === 0 ? 'Feminino' : 'Masculino',
                'telefone' => sprintf('(97) 99999-%04d', 1000 + $id),
                'email' => sprintf('atleta%02d@demonstracao.local', $id),
                'modalidade' => $sports[$index % count($sports)],
                'categoria' => $age < 14 ? 'Sub-14' : ($age < 17 ? 'Sub-17' : 'Adulto'),
                'equipe' => $teams[$index % count($teams)],
                'bairro' => $neighborhoods[$index % count($neighborhoods)],
                'municipio' => 'Município Demonstrativo',
                'logradouro' => 'Rua Demonstrativa',
                'numero' => (string) (100 + $id),
                'status' => $statuses[$index % count($statuses)],
                'documentos_status' => $documents[$index % count($documents)],
                'frequencia' => 72 + (($index * 7) % 27),
            ];
        }, $names, array_keys($names));
    }

    public static function find(int $id): ?array
    {
        foreach (self::all() as $athlete) {
            if ($athlete['id'] === $id) {
                return $athlete;
            }
        }
        return null;
    }

    public static function paginate(array $filters, int $page = 1, int $perPage = 15): array
    {
        $query = mb_strtolower(trim((string) ($filters['q'] ?? '')));
        $status = mb_strtolower(trim((string) ($filters['status'] ?? '')));
        $items = array_values(array_filter(self::all(), static function (array $athlete) use ($query, $status): bool {
            $matchesQuery = $query === '' || str_contains(mb_strtolower($athlete['nome'] . ' ' . $athlete['codigo'] . ' ' . $athlete['cpf']), $query);
            $matchesStatus = $status === '' || mb_strtolower($athlete['status']) === $status;
            return $matchesQuery && $matchesStatus;
        }));
        $page = max(1, $page);
        $perPage = in_array($perPage, [15, 24, 30, 50], true) ? $perPage : 15;
        return [
            'items' => array_slice($items, ($page - 1) * $perPage, $perPage),
            'total' => count($items),
            'page' => $page,
            'per_page' => $perPage,
        ];
    }
}
