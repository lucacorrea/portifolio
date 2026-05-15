<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Categoria;
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
            $categorias = $this->realCategories($igrejaId);
            $movimentos = $this->realMovements($igrejaId, $filters);
        } catch (Throwable) {
            $loadError = 'Não foi possível carregar os dados financeiros do banco para o relatório.';
        }

        $movimentos = $this->applyFilters($movimentos, $filters);
        usort($movimentos, static fn (array $a, array $b): int => strcmp($b['data'], $a['data']));
        $daily = $this->dailySummary($movimentos);
        $pagination = $this->paginate($movimentos, $filters['page'], $filters['per_page']);
        $dailyPagination = $this->paginate($daily, $filters['daily_page'], $filters['daily_per_page']);

        return [
            'filters' => $filters,
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
            ]),
            'pageQuery' => http_build_query([
                'data_inicio' => $filters['data_inicio'],
                'data_fim' => $filters['data_fim'],
                'tipo' => $filters['tipo'],
                'categoria_id' => $filters['categoria_id'] ?: '',
                'forma_pagamento' => $filters['forma_pagamento'] ?? '',
                'per_page' => $filters['per_page'],
                'daily_page' => $filters['daily_page'],
            ]),
            'dailyPageQuery' => http_build_query([
                'data_inicio' => $filters['data_inicio'],
                'data_fim' => $filters['data_fim'],
                'tipo' => $filters['tipo'],
                'categoria_id' => $filters['categoria_id'] ?: '',
                'forma_pagamento' => $filters['forma_pagamento'] ?? '',
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

        return array_values(array_filter(
            (new Categoria())->listByChurch($igrejaId),
            static fn (array $categoria): bool => (int) $categoria['ativo'] === 1
        ));
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
