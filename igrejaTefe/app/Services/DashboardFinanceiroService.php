<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use DateTimeImmutable;
use PDO;

final class DashboardFinanceiroService
{
    private const MONTH_NAMES = [
        1 => 'Jan',
        2 => 'Fev',
        3 => 'Mar',
        4 => 'Abr',
        5 => 'Mai',
        6 => 'Jun',
        7 => 'Jul',
        8 => 'Ago',
        9 => 'Set',
        10 => 'Out',
        11 => 'Nov',
        12 => 'Dez',
    ];

    public function build(int $igrejaId): array
    {
        if ($igrejaId <= 0) {
            return self::emptyDashboard();
        }

        $monthStart = (new DateTimeImmutable('first day of this month'))->format('Y-m-d');
        $monthEnd = (new DateTimeImmutable('last day of this month'))->format('Y-m-d');
        $metrics = $this->currentMonthMetrics($igrejaId, $monthStart, $monthEnd);
        $monthly = $this->lastSixMonths($igrejaId);
        $categories = $this->expenseCategories($igrejaId, $monthStart, $monthEnd);

        return [
            'loadError' => null,
            'periodoLabel' => date('m/Y'),
            'metrics' => $metrics,
            'chartData' => [
                'months' => $monthly['labels'],
                'entradas' => $monthly['entradas'],
                'saidas' => $monthly['saidas'],
                'categorias' => array_column($categories, 'nome'),
                'categoriasValores' => array_map('floatval', array_column($categories, 'total')),
            ],
            'transactions' => $this->latestMovements($igrejaId),
        ];
    }

    public static function emptyDashboard(?string $loadError = null): array
    {
        $labels = [];
        $currentMonth = new DateTimeImmutable('first day of this month');
        $start = $currentMonth->modify('-5 months');

        for ($index = 0; $index < 6; $index++) {
            $month = $start->modify('+' . $index . ' months');
            $labels[] = self::MONTH_NAMES[(int) $month->format('n')];
        }

        return [
            'loadError' => $loadError,
            'periodoLabel' => date('m/Y'),
            'metrics' => [
                'entradas_mes' => 0.0,
                'entradas_qtd' => 0,
                'dizimos' => 0.0,
                'dizimos_qtd' => 0,
                'ofertas' => 0.0,
                'ofertas_qtd' => 0,
                'saidas_mes' => 0.0,
                'saidas_qtd' => 0,
                'saldo_mes' => 0.0,
                'movimentacoes_qtd' => 0,
            ],
            'chartData' => [
                'months' => $labels,
                'entradas' => array_fill(0, 6, 0),
                'saidas' => array_fill(0, 6, 0),
                'categorias' => [],
                'categoriasValores' => [],
            ],
            'transactions' => [],
        ];
    }

    private function currentMonthMetrics(int $igrejaId, string $monthStart, string $monthEnd): array
    {
        $entradas = [
            'dizimo' => ['total' => 0.0, 'quantidade' => 0],
            'oferta' => ['total' => 0.0, 'quantidade' => 0],
        ];

        $statement = $this->pdo()->prepare(
            'SELECT tipo,
                    COALESCE(SUM(valor), 0) AS total,
                    COUNT(*) AS quantidade
             FROM entradas
             WHERE igreja_id = :igreja_id
               AND data_entrada BETWEEN :data_inicio AND :data_fim
             GROUP BY tipo'
        );
        $statement->execute([
            'igreja_id' => $igrejaId,
            'data_inicio' => $monthStart,
            'data_fim' => $monthEnd,
        ]);

        foreach ($statement->fetchAll() as $row) {
            $type = (string) $row['tipo'];

            if (!array_key_exists($type, $entradas)) {
                continue;
            }

            $entradas[$type] = [
                'total' => (float) $row['total'],
                'quantidade' => (int) $row['quantidade'],
            ];
        }

        $statement = $this->pdo()->prepare(
            'SELECT COALESCE(SUM(valor), 0) AS total,
                    COUNT(*) AS quantidade
             FROM saidas
             WHERE igreja_id = :igreja_id
               AND data_saida BETWEEN :data_inicio AND :data_fim'
        );
        $statement->execute([
            'igreja_id' => $igrejaId,
            'data_inicio' => $monthStart,
            'data_fim' => $monthEnd,
        ]);
        $saidas = $statement->fetch() ?: ['total' => 0, 'quantidade' => 0];

        $dizimos = (float) $entradas['dizimo']['total'];
        $ofertas = (float) $entradas['oferta']['total'];
        $entradasTotal = $dizimos + $ofertas;
        $saidasTotal = (float) $saidas['total'];
        $entradasQuantidade = (int) $entradas['dizimo']['quantidade'] + (int) $entradas['oferta']['quantidade'];
        $saidasQuantidade = (int) $saidas['quantidade'];

        return [
            'entradas_mes' => $entradasTotal,
            'entradas_qtd' => $entradasQuantidade,
            'dizimos' => $dizimos,
            'dizimos_qtd' => (int) $entradas['dizimo']['quantidade'],
            'ofertas' => $ofertas,
            'ofertas_qtd' => (int) $entradas['oferta']['quantidade'],
            'saidas_mes' => $saidasTotal,
            'saidas_qtd' => $saidasQuantidade,
            'saldo_mes' => $entradasTotal - $saidasTotal,
            'movimentacoes_qtd' => $entradasQuantidade + $saidasQuantidade,
        ];
    }

    private function lastSixMonths(int $igrejaId): array
    {
        $labels = [];
        $keys = [];
        $currentMonth = new DateTimeImmutable('first day of this month');
        $start = $currentMonth->modify('-5 months');
        $end = new DateTimeImmutable('last day of this month');

        for ($index = 0; $index < 6; $index++) {
            $month = $start->modify('+' . $index . ' months');
            $key = $month->format('Y-m');
            $keys[] = $key;
            $labels[] = self::MONTH_NAMES[(int) $month->format('n')];
        }

        return [
            'labels' => $labels,
            'entradas' => $this->monthlyTotals(
                'entradas',
                'data_entrada',
                $igrejaId,
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $keys
            ),
            'saidas' => $this->monthlyTotals(
                'saidas',
                'data_saida',
                $igrejaId,
                $start->format('Y-m-d'),
                $end->format('Y-m-d'),
                $keys
            ),
        ];
    }

    private function monthlyTotals(string $table, string $dateColumn, int $igrejaId, string $start, string $end, array $keys): array
    {
        $totals = array_fill_keys($keys, 0.0);
        $statement = $this->pdo()->prepare(
            "SELECT DATE_FORMAT({$dateColumn}, '%Y-%m') AS mes,
                    COALESCE(SUM(valor), 0) AS total
             FROM {$table}
             WHERE igreja_id = :igreja_id
               AND {$dateColumn} BETWEEN :data_inicio AND :data_fim
             GROUP BY mes"
        );
        $statement->execute([
            'igreja_id' => $igrejaId,
            'data_inicio' => $start,
            'data_fim' => $end,
        ]);

        foreach ($statement->fetchAll() as $row) {
            $month = (string) $row['mes'];

            if (array_key_exists($month, $totals)) {
                $totals[$month] = (float) $row['total'];
            }
        }

        return array_values($totals);
    }

    private function expenseCategories(int $igrejaId, string $monthStart, string $monthEnd): array
    {
        $statement = $this->pdo()->prepare(
            'SELECT c.nome,
                    c.cor,
                    COALESCE(SUM(s.valor), 0) AS total
             FROM saidas s
             INNER JOIN categorias c
                ON c.id = s.categoria_id
               AND c.igreja_id = s.igreja_id
             WHERE s.igreja_id = :igreja_id
               AND s.data_saida BETWEEN :data_inicio AND :data_fim
             GROUP BY c.id, c.nome, c.cor
             ORDER BY total DESC
             LIMIT 6'
        );
        $statement->execute([
            'igreja_id' => $igrejaId,
            'data_inicio' => $monthStart,
            'data_fim' => $monthEnd,
        ]);

        return $statement->fetchAll();
    }

    private function latestMovements(int $igrejaId, int $limit = 8): array
    {
        $limit = max(1, min($limit, 20));
        $statement = $this->pdo()->prepare(
            "SELECT *
             FROM (
                 SELECT e.id AS origem_id,
                        'entrada' AS movimento,
                        CASE WHEN e.tipo = 'dizimo' THEN 'Dízimos' ELSE 'Ofertas' END AS categoria_nome,
                        CASE WHEN e.tipo = 'dizimo' THEN '#286CC8' ELSE '#8057C7' END AS categoria_cor,
                        e.valor,
                        e.descricao,
                        e.contribuinte_nome AS pessoa,
                        e.forma_pagamento,
                        e.data_entrada AS data_movimento,
                        e.criado_em
                 FROM entradas e
                 WHERE e.igreja_id = :igreja_id_entradas

                 UNION ALL

                 SELECT s.id AS origem_id,
                        'saida' AS movimento,
                        c.nome AS categoria_nome,
                        c.cor AS categoria_cor,
                        s.valor,
                        s.descricao,
                        s.fornecedor AS pessoa,
                        s.forma_pagamento,
                        s.data_saida AS data_movimento,
                        s.criado_em
                 FROM saidas s
                 INNER JOIN categorias c
                    ON c.id = s.categoria_id
                   AND c.igreja_id = s.igreja_id
                 WHERE s.igreja_id = :igreja_id_saidas
             ) movimentos
             ORDER BY data_movimento DESC, criado_em DESC, origem_id DESC
             LIMIT {$limit}"
        );
        $statement->execute([
            'igreja_id_entradas' => $igrejaId,
            'igreja_id_saidas' => $igrejaId,
        ]);

        return array_map(static function (array $row): array {
            $isEntrada = $row['movimento'] === 'entrada';
            $timestamp = strtotime((string) $row['data_movimento']);

            return [
                'tipo' => $isEntrada ? 'entrada' : 'saida',
                'descricao' => $row['descricao'] ?: ($isEntrada ? 'Entrada registrada' : 'Saída registrada'),
                'categoria' => (string) $row['categoria_nome'],
                'data' => $timestamp ? date('d/m/Y', $timestamp) : '-',
                'valor' => ($isEntrada ? '+' : '-') . 'R$ ' . number_format((float) $row['valor'], 2, ',', '.'),
                'url' => url($isEntrada ? '/entradas' : '/saidas'),
            ];
        }, $statement->fetchAll());
    }

    private function pdo(): PDO
    {
        return Database::connection();
    }
}
