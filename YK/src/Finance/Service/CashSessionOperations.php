<?php

declare(strict_types=1);

namespace App\Finance\Service;

use InvalidArgumentException;

trait CashSessionOperations
{
    public function openSession(string $openingValue, ?string $notes, int $userId): int
    {
        $cents = $this->moneyCents($openingValue, true);
        return $this->transactional(function () use ($cents, $notes, $userId): int {
            if ($this->currentSession() !== null) throw new InvalidArgumentException('Já existe um Caixa aberto. Feche-o antes de iniciar outro.');
            $statement = $this->connection->prepare(
                'INSERT INTO caixa_sessoes (codigo, valor_abertura, observacao_abertura, aberto_por)
                 VALUES (NULL, :opening_value, :notes, :user_id)'
            );
            $statement->execute([
                'opening_value' => $this->centsToDecimal($cents),
                'notes' => $this->optionalText($notes, 255),
                'user_id' => $userId,
            ]);
            $id = (int) $this->connection->lastInsertId();
            $this->connection->prepare('UPDATE caixa_sessoes SET codigo = :code WHERE id = :id')->execute([
                'id' => $id, 'code' => sprintf('CX-%06d', $id),
            ]);
            return $id;
        });
    }

    public function closeSession(string $countedValue, ?string $notes, int $userId): void
    {
        $counted = $this->moneyCents($countedValue, true);
        $this->transactional(function () use ($counted, $notes, $userId): void {
            $session = $this->requireOpenSession(true);
            $expected = $this->cashPositionCents((int) $session['id'], (int) round((float) $session['valor_abertura'] * 100));
            $this->connection->prepare(
                'UPDATE caixa_sessoes
                    SET status = "fechada", saldo_esperado = :expected, saldo_informado = :counted,
                        diferenca = :difference, observacao_fechamento = :notes,
                        fechado_por = :user_id, fechado_em = CURRENT_TIMESTAMP
                  WHERE id = :id AND status = "aberta"'
            )->execute([
                'id' => $session['id'], 'expected' => $this->centsToDecimal($expected),
                'counted' => $this->centsToDecimal($counted),
                'difference' => $this->centsToDecimal($counted - $expected),
                'notes' => $this->optionalText($notes, 255), 'user_id' => $userId,
            ]);
        });
    }

    public function registerAdjustment(string $type, string $value, string $notes, int $userId): int
    {
        if (!in_array($type, ['sangria', 'suprimento'], true)) throw new InvalidArgumentException('Tipo de ajuste de Caixa inválido.');
        $cents = $this->moneyCents($value);
        return $this->transactional(function () use ($type, $cents, $notes, $userId): int {
            $session = $this->requireOpenSession(true);
            if ($type === 'sangria') {
                $opening = (int) round((float) $session['valor_abertura'] * 100);
                if ($cents > $this->cashPositionCents((int) $session['id'], $opening)) {
                    throw new InvalidArgumentException('A sangria não pode superar o dinheiro disponível no Caixa.');
                }
            }
            $description = ($type === 'sangria' ? 'Sangria: ' : 'Suprimento: ') . $this->requiredText($notes, 220, 'Informe o motivo da operação.');
            return $this->insertMovement(
                (int) $session['id'], $type === 'sangria' ? 'saida' : 'entrada',
                'caixa_' . $type, (int) $session['id'], $description, 'dinheiro', $cents, $userId
            );
        });
    }

    /** @return array{entrada:string,saida:string,estornos:string,saldo:string,dinheiro_esperado:string,por_forma:array<string,string>} */
    public function sessionSummary(?int $sessionId = null): array
    {
        if ($sessionId === null) {
            $current = $this->currentSession();
            if ($current === null) return ['entrada' => '0.00', 'saida' => '0.00', 'estornos' => '0.00', 'saldo' => '0.00', 'dinheiro_esperado' => '0.00', 'por_forma' => []];
            $sessionId = (int) $current['id'];
            $opening = (int) round((float) $current['valor_abertura'] * 100);
        } else {
            $statement = $this->connection->prepare('SELECT valor_abertura FROM caixa_sessoes WHERE id = :id');
            $statement->execute(['id' => $sessionId]);
            $opening = (int) round((float) ($statement->fetchColumn() ?: 0) * 100);
        }
        $statement = $this->connection->prepare(
            'SELECT forma_pagamento,
                    SUM(CASE WHEN tipo = "entrada" THEN valor ELSE 0 END) entrada,
                    SUM(CASE WHEN tipo = "saida" THEN valor ELSE 0 END) saida,
                    SUM(CASE WHEN tipo = "estorno_entrada" THEN valor ELSE 0 END) estorno_entrada,
                    SUM(CASE WHEN tipo = "estorno_saida" THEN valor ELSE 0 END) estorno_saida
               FROM caixa_movimentacoes WHERE caixa_sessao_id = :id GROUP BY forma_pagamento'
        );
        $statement->execute(['id' => $sessionId]);
        $totals = ['entrada' => 0, 'saida' => 0, 'estorno_entrada' => 0, 'estorno_saida' => 0];
        $forms = [];
        foreach ($statement->fetchAll() as $row) {
            $net = 0;
            foreach (array_keys($totals) as $key) {
                $value = (int) round((float) $row[$key] * 100);
                $totals[$key] += $value;
                $net += in_array($key, ['entrada', 'estorno_saida'], true) ? $value : -$value;
            }
            $forms[(string) ($row['forma_pagamento'] ?? 'sem_forma')] = $this->centsToDecimal($net);
        }
        $balance = $totals['entrada'] - $totals['saida'] - $totals['estorno_entrada'] + $totals['estorno_saida'];
        return [
            'entrada' => $this->centsToDecimal($totals['entrada']),
            'saida' => $this->centsToDecimal($totals['saida']),
            'estornos' => $this->centsToDecimal($totals['estorno_entrada'] + $totals['estorno_saida']),
            'saldo' => $this->centsToDecimal($balance),
            'dinheiro_esperado' => $this->centsToDecimal($this->cashPositionCents($sessionId, $opening)),
            'por_forma' => $forms,
        ];
    }

    /** @return array<int,array<string,mixed>> */
    public function recentSessions(int $limit = 10): array
    {
        $limit = max(1, min(30, $limit));
        return $this->connection->query(
            'SELECT sessao.*, abertura.nome AS aberto_por_nome, fechamento.nome AS fechado_por_nome
               FROM caixa_sessoes sessao JOIN usuarios abertura ON abertura.id = sessao.aberto_por
               LEFT JOIN usuarios fechamento ON fechamento.id = sessao.fechado_por
              ORDER BY sessao.id DESC LIMIT ' . $limit
        )->fetchAll();
    }

    private function cashPositionCents(int $sessionId, int $opening): int
    {
        $statement = $this->connection->prepare(
            'SELECT COALESCE(SUM(CASE
                    WHEN tipo = "entrada" THEN valor WHEN tipo = "saida" THEN -valor
                    WHEN tipo = "estorno_entrada" THEN -valor WHEN tipo = "estorno_saida" THEN valor ELSE 0 END), 0)
               FROM caixa_movimentacoes WHERE caixa_sessao_id = :id AND forma_pagamento = "dinheiro"'
        );
        $statement->execute(['id' => $sessionId]);
        return $opening + (int) round((float) $statement->fetchColumn() * 100);
    }
}
