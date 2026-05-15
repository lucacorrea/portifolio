<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Categoria;
use DateInterval;
use DateTimeImmutable;
use PDO;
use Throwable;

final class RelatorioFinanceiroService
{
    public function build(int $igrejaId, array $input): array
    {
        $filters = $this->normalizeFilters($input);
        $loadError = null;
        $categorias = [];
        $movimentos = [];

        try {
            $categorias = $filters['demo']
                ? $this->demoCategories()
                : $this->realCategories($igrejaId);
            $movimentos = $filters['demo']
                ? $this->demoMovements($filters)
                : $this->realMovements($igrejaId, $filters);
        } catch (Throwable) {
            $loadError = 'Não foi possível carregar os dados reais para o relatório.';
        }

        $movimentos = $this->applyFilters($movimentos, $filters);
        usort($movimentos, static fn (array $a, array $b): int => strcmp($b['data'], $a['data']));
        $daily = $this->dailySummary($movimentos);
        $pagination = $this->paginate($movimentos, $filters['page'], $filters['per_page']);
        $dailyPagination = $this->paginate($daily, $filters['daily_page'], $filters['daily_per_page']);

        return [
            'filters' => $filters,
            'isDemo' => $filters['demo'],
            'loadError' => $loadError,
            'generatedAt' => date('d/m/Y H:i'),
            'periodoLabel' => $this->formatDate($filters['data_inicio']) . ' a ' . $this->formatDate($filters['data_fim']),
            'summary' => $this->summary($movimentos),
            'categorias' => $this->categorySummary($movimentos),
            'formasPagamento' => $this->paymentSummary($movimentos),
            'daily' => $daily,
            'dailyPaginado' => $dailyPagination['items'],
            'dailyPagination' => $dailyPagination['meta'],
            'movimentos' => $movimentos,
            'movimentosPaginados' => $pagination['items'],
            'pagination' => $pagination['meta'],
            'categoryOptions' => $categorias,
            'query' => http_build_query([
                'data_inicio' => $filters['data_inicio'],
                'data_fim' => $filters['data_fim'],
                'tipo' => $filters['tipo'],
                'categoria_id' => $filters['categoria_id'] ?: '',
                'forma_pagamento' => $filters['forma_pagamento'] ?? '',
                'demo' => $filters['demo'] ? '1' : '0',
            ]),
            'pageQuery' => http_build_query([
                'data_inicio' => $filters['data_inicio'],
                'data_fim' => $filters['data_fim'],
                'tipo' => $filters['tipo'],
                'categoria_id' => $filters['categoria_id'] ?: '',
                'forma_pagamento' => $filters['forma_pagamento'] ?? '',
                'demo' => $filters['demo'] ? '1' : '0',
                'per_page' => $filters['per_page'],
                'daily_page' => $filters['daily_page'],
            ]),
            'dailyPageQuery' => http_build_query([
                'data_inicio' => $filters['data_inicio'],
                'data_fim' => $filters['data_fim'],
                'tipo' => $filters['tipo'],
                'categoria_id' => $filters['categoria_id'] ?: '',
                'forma_pagamento' => $filters['forma_pagamento'] ?? '',
                'demo' => $filters['demo'] ? '1' : '0',
                'per_page' => $filters['per_page'],
                'page' => $filters['page'],
            ]),
        ];
    }

    private function normalizeFilters(array $input): array
    {
        $start = $this->validDate((string) ($input['data_inicio'] ?? '')) ?? date('Y-m-01');
        $end = $this->validDate((string) ($input['data_fim'] ?? '')) ?? date('Y-m-t');

        if ($start > $end) {
            [$start, $end] = [$end, $start];
        }

        $tipo = (string) ($input['tipo'] ?? 'todos');
        $forma = trim((string) ($input['forma_pagamento'] ?? ''));
        $perPage = (int) ($input['per_page'] ?? 10);
        $page = (int) ($input['page'] ?? 1);
        $dailyPage = (int) ($input['daily_page'] ?? 1);
        $dailyPerPage = 4;

        if (!in_array($perPage, [10, 15, 25, 50], true)) {
            $perPage = 10;
        }

        return [
            'data_inicio' => $start,
            'data_fim' => $end,
            'tipo' => in_array($tipo, ['todos', 'entrada', 'saida'], true) ? $tipo : 'todos',
            'categoria_id' => max(0, (int) ($input['categoria_id'] ?? 0)),
            'forma_pagamento' => $forma !== '' ? $forma : null,
            'demo' => (string) ($input['demo'] ?? '1') !== '0',
            'page' => max(1, $page),
            'per_page' => $perPage,
            'daily_page' => max(1, $dailyPage),
            'daily_per_page' => $dailyPerPage,
        ];
    }

    private function paginate(array $items, int $page, int $perPage): array
    {
        $total = count($items);
        $totalPages = max(1, (int) ceil($total / $perPage));
        $currentPage = min(max(1, $page), $totalPages);
        $offset = ($currentPage - 1) * $perPage;

        return [
            'items' => array_slice($items, $offset, $perPage),
            'meta' => [
                'current_page' => $currentPage,
                'per_page' => $perPage,
                'total' => $total,
                'total_pages' => $totalPages,
                'from' => $total > 0 ? $offset + 1 : 0,
                'to' => min($offset + $perPage, $total),
            ],
        ];
    }

    private function realCategories(int $igrejaId): array
    {
        if ($igrejaId <= 0) {
            return [];
        }

        try {
            return array_values(array_filter(
                (new Categoria())->listByChurch($igrejaId),
                static fn (array $categoria): bool => (int) $categoria['ativo'] === 1
            ));
        } catch (Throwable) {
            return [];
        }
    }

    private function realMovements(int $igrejaId, array $filters): array
    {
        if ($igrejaId <= 0) {
            return [];
        }

        $movimentos = [];

        if ($filters['tipo'] !== 'saida') {
            $movimentos = array_merge($movimentos, $this->realEntradas($igrejaId, $filters));
        }

        if ($filters['tipo'] !== 'entrada') {
            $movimentos = array_merge($movimentos, $this->realSaidas($igrejaId, $filters));
        }

        return $movimentos;
    }

    private function realEntradas(int $igrejaId, array $filters): array
    {
        if ($filters['categoria_id'] > 0) {
            return [];
        }

        $sql = 'SELECT id,
                       tipo,
                       valor,
                       descricao,
                       contribuinte_nome,
                       forma_pagamento,
                       data_entrada
                FROM entradas
                WHERE igreja_id = :igreja_id
                  AND data_entrada BETWEEN :data_inicio AND :data_fim';
        $params = [
            'igreja_id' => $igrejaId,
            'data_inicio' => $filters['data_inicio'],
            'data_fim' => $filters['data_fim'],
        ];

        if ($filters['forma_pagamento'] !== null) {
            $sql .= ' AND forma_pagamento = :forma_pagamento';
            $params['forma_pagamento'] = $filters['forma_pagamento'];
        }

        $statement = $this->pdo()->prepare($sql . ' ORDER BY data_entrada DESC, id DESC');
        $statement->execute($params);

        return array_map(static function (array $row): array {
            return [
                'id' => 'E-' . $row['id'],
                'movimento' => 'entrada',
                'data' => (string) $row['data_entrada'],
                'categoria_id' => 0,
                'categoria_nome' => $row['tipo'] === 'dizimo' ? 'Dízimos' : 'Ofertas',
                'categoria_cor' => $row['tipo'] === 'dizimo' ? '#286CC8' : '#8057C7',
                'pessoa' => $row['contribuinte_nome'] ?: 'Não informado',
                'descricao' => $row['descricao'] ?: 'Entrada registrada',
                'forma_pagamento' => $row['forma_pagamento'] ?: 'outro',
                'valor' => (float) $row['valor'],
            ];
        }, $statement->fetchAll());
    }

    private function realSaidas(int $igrejaId, array $filters): array
    {
        $sql = 'SELECT s.id,
                       s.categoria_id,
                       s.valor,
                       s.descricao,
                       s.fornecedor,
                       s.forma_pagamento,
                       s.data_saida,
                       c.nome AS categoria_nome,
                       c.cor AS categoria_cor
                FROM saidas s
                INNER JOIN categorias c
                   ON c.id = s.categoria_id
                  AND c.igreja_id = s.igreja_id
                WHERE s.igreja_id = :igreja_id
                  AND s.data_saida BETWEEN :data_inicio AND :data_fim';
        $params = [
            'igreja_id' => $igrejaId,
            'data_inicio' => $filters['data_inicio'],
            'data_fim' => $filters['data_fim'],
        ];

        if ($filters['categoria_id'] > 0) {
            $sql .= ' AND s.categoria_id = :categoria_id';
            $params['categoria_id'] = $filters['categoria_id'];
        }

        if ($filters['forma_pagamento'] !== null) {
            $sql .= ' AND s.forma_pagamento = :forma_pagamento';
            $params['forma_pagamento'] = $filters['forma_pagamento'];
        }

        $statement = $this->pdo()->prepare($sql . ' ORDER BY s.data_saida DESC, s.id DESC');
        $statement->execute($params);

        return array_map(static function (array $row): array {
            return [
                'id' => 'S-' . $row['id'],
                'movimento' => 'saida',
                'data' => (string) $row['data_saida'],
                'categoria_id' => (int) $row['categoria_id'],
                'categoria_nome' => (string) $row['categoria_nome'],
                'categoria_cor' => (string) $row['categoria_cor'],
                'pessoa' => $row['fornecedor'] ?: 'Não informado',
                'descricao' => $row['descricao'] ?: 'Saída registrada',
                'forma_pagamento' => $row['forma_pagamento'] ?: 'outro',
                'valor' => (float) $row['valor'],
            ];
        }, $statement->fetchAll());
    }

    private function demoCategories(): array
    {
        return [
            ['id' => 101, 'nome' => 'Missões', 'cor' => '#8057C7', 'ativo' => 1],
            ['id' => 102, 'nome' => 'Manutenção', 'cor' => '#286CC8', 'ativo' => 1],
            ['id' => 103, 'nome' => 'Energia e água', 'cor' => '#9C7422', 'ativo' => 1],
            ['id' => 104, 'nome' => 'Eventos', 'cor' => '#2FAF8F', 'ativo' => 1],
            ['id' => 105, 'nome' => 'Ajuda social', 'cor' => '#C84D4D', 'ativo' => 1],
        ];
    }

    private function demoMovements(array $filters): array
    {
        $start = new DateTimeImmutable($filters['data_inicio']);
        $items = [
            ['entrada', 1, 'Dízimos', 0, '#286CC8', 'Culto de domingo', 'Dízimos recebidos no culto da manhã', 'pix', 4250.00],
            ['entrada', 3, 'Ofertas', 0, '#8057C7', 'Culto de celebração', 'Ofertas gerais da semana', 'dinheiro', 1380.50],
            ['saida', 4, 'Energia e água', 103, '#9C7422', 'Amazonas Energia', 'Conta de energia do templo', 'boleto', 742.90],
            ['saida', 5, 'Manutenção', 102, '#286CC8', 'Construtora Local', 'Reparo de iluminação e pintura', 'pix', 1280.00],
            ['entrada', 7, 'Dízimos', 0, '#286CC8', 'Contribuições online', 'Dízimos via transferência', 'transferencia', 3100.00],
            ['saida', 8, 'Ajuda social', 105, '#C84D4D', 'Famílias assistidas', 'Cestas básicas e apoio emergencial', 'dinheiro', 960.00],
            ['saida', 10, 'Missões', 101, '#8057C7', 'Projeto Ribeirinho', 'Apoio mensal ao campo missionário', 'pix', 1500.00],
            ['entrada', 12, 'Ofertas', 0, '#8057C7', 'Campanha missionária', 'Oferta direcionada para missões', 'pix', 2240.00],
            ['saida', 14, 'Eventos', 104, '#2FAF8F', 'Mercado Central', 'Itens para encontro de famílias', 'cartao', 685.35],
            ['entrada', 15, 'Dízimos', 0, '#286CC8', 'Culto de ensino', 'Dízimos e contribuições presenciais', 'dinheiro', 1960.00],
            ['saida', 17, 'Manutenção', 102, '#286CC8', 'Técnico de som', 'Revisão de mesa e microfones', 'pix', 430.00],
            ['entrada', 19, 'Ofertas', 0, '#8057C7', 'Oferta especial', 'Oferta para manutenção do templo', 'cartao', 980.00],
            ['saida', 21, 'Energia e água', 103, '#9C7422', 'Serviço de água', 'Conta de água do mês', 'boleto', 238.20],
            ['entrada', 23, 'Dízimos', 0, '#286CC8', 'Contribuições recorrentes', 'Dízimos recorrentes cadastrados', 'transferencia', 2860.00],
            ['saida', 25, 'Eventos', 104, '#2FAF8F', 'Comunicação visual', 'Materiais de divulgação do congresso', 'pix', 520.00],
            ['saida', 27, 'Ajuda social', 105, '#C84D4D', 'Ação comunitária', 'Medicamentos e transporte solidário', 'dinheiro', 390.00],
        ];

        return array_map(static function (array $item, int $index) use ($start): array {
            $date = $start->add(new DateInterval('P' . $item[1] . 'D'))->format('Y-m-d');

            return [
                'id' => 'D-' . str_pad((string) ($index + 1), 3, '0', STR_PAD_LEFT),
                'movimento' => $item[0],
                'data' => $date,
                'categoria_nome' => $item[2],
                'categoria_id' => $item[3],
                'categoria_cor' => $item[4],
                'pessoa' => $item[5],
                'descricao' => $item[6],
                'forma_pagamento' => $item[7],
                'valor' => $item[8],
            ];
        }, $items, array_keys($items));
    }

    private function applyFilters(array $movimentos, array $filters): array
    {
        return array_values(array_filter($movimentos, static function (array $item) use ($filters): bool {
            if ($item['data'] < $filters['data_inicio'] || $item['data'] > $filters['data_fim']) {
                return false;
            }

            if ($filters['tipo'] !== 'todos' && $item['movimento'] !== $filters['tipo']) {
                return false;
            }

            if ($filters['categoria_id'] > 0 && (int) $item['categoria_id'] !== $filters['categoria_id']) {
                return false;
            }

            if ($filters['forma_pagamento'] !== null && $item['forma_pagamento'] !== $filters['forma_pagamento']) {
                return false;
            }

            return true;
        }));
    }

    private function summary(array $movimentos): array
    {
        $entradas = 0.0;
        $saidas = 0.0;
        $qtdEntradas = 0;
        $qtdSaidas = 0;
        $maiorEntrada = 0.0;
        $maiorSaida = 0.0;

        foreach ($movimentos as $item) {
            if ($item['movimento'] === 'entrada') {
                $entradas += (float) $item['valor'];
                $qtdEntradas++;
                $maiorEntrada = max($maiorEntrada, (float) $item['valor']);
            } else {
                $saidas += (float) $item['valor'];
                $qtdSaidas++;
                $maiorSaida = max($maiorSaida, (float) $item['valor']);
            }
        }

        return [
            'entradas' => $entradas,
            'saidas' => $saidas,
            'saldo' => $entradas - $saidas,
            'quantidade_entradas' => $qtdEntradas,
            'quantidade_saidas' => $qtdSaidas,
            'quantidade_total' => count($movimentos),
            'ticket_medio_entrada' => $qtdEntradas > 0 ? $entradas / $qtdEntradas : 0,
            'ticket_medio_saida' => $qtdSaidas > 0 ? $saidas / $qtdSaidas : 0,
            'maior_entrada' => $maiorEntrada,
            'maior_saida' => $maiorSaida,
            'comprometimento' => $entradas > 0 ? ($saidas / $entradas) * 100 : 0,
        ];
    }

    private function categorySummary(array $movimentos): array
    {
        $summary = [];
        $total = 0.0;

        foreach ($movimentos as $item) {
            if ($item['movimento'] !== 'saida') {
                continue;
            }

            $key = (string) $item['categoria_nome'];
            $total += (float) $item['valor'];

            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'nome' => $key,
                    'cor' => $item['categoria_cor'],
                    'total' => 0.0,
                    'quantidade' => 0,
                    'percentual' => 0,
                ];
            }

            $summary[$key]['total'] += (float) $item['valor'];
            $summary[$key]['quantidade']++;
        }

        foreach ($summary as &$item) {
            $item['percentual'] = $total > 0 ? ($item['total'] / $total) * 100 : 0;
        }
        unset($item);

        usort($summary, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return $summary;
    }

    private function paymentSummary(array $movimentos): array
    {
        $summary = [];

        foreach ($movimentos as $item) {
            $key = (string) $item['forma_pagamento'];

            if (!isset($summary[$key])) {
                $summary[$key] = [
                    'nome' => $this->paymentLabel($key),
                    'total' => 0.0,
                    'quantidade' => 0,
                ];
            }

            $summary[$key]['total'] += (float) $item['valor'];
            $summary[$key]['quantidade']++;
        }

        usort($summary, static fn (array $a, array $b): int => $b['total'] <=> $a['total']);

        return $summary;
    }

    private function dailySummary(array $movimentos): array
    {
        $summary = [];

        foreach ($movimentos as $item) {
            $date = (string) $item['data'];

            if (!isset($summary[$date])) {
                $summary[$date] = [
                    'data' => $date,
                    'entradas' => 0.0,
                    'saidas' => 0.0,
                    'saldo' => 0.0,
                ];
            }

            if ($item['movimento'] === 'entrada') {
                $summary[$date]['entradas'] += (float) $item['valor'];
            } else {
                $summary[$date]['saidas'] += (float) $item['valor'];
            }
        }

        foreach ($summary as &$item) {
            $item['saldo'] = $item['entradas'] - $item['saidas'];
        }
        unset($item);

        ksort($summary);

        return array_values($summary);
    }

    private function validDate(string $date): ?string
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $date
            ? $date
            : null;
    }

    private function formatDate(string $date): string
    {
        $timestamp = strtotime($date);

        return $timestamp ? date('d/m/Y', $timestamp) : $date;
    }

    private function paymentLabel(string $payment): string
    {
        return [
            'dinheiro' => 'Dinheiro',
            'pix' => 'Pix',
            'cartao' => 'Cartão',
            'transferencia' => 'Transferência',
            'boleto' => 'Boleto',
            'outro' => 'Outro',
        ][$payment] ?? ucfirst(str_replace('_', ' ', $payment));
    }

    private function pdo(): PDO
    {
        return Database::connection();
    }
}
