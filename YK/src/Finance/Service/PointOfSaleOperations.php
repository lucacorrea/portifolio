<?php

declare(strict_types=1);

namespace App\Finance\Service;

use InvalidArgumentException;

trait PointOfSaleOperations
{
    /** @return array<int,array<string,mixed>> */
    public function availableProducts(): array
    {
        return $this->connection->query(
            'SELECT id, codigo, nome, unidade, codigo_barras, preco_venda, estoque
               FROM produtos WHERE status = "ativo" AND estoque > 0 AND preco_venda > 0
              ORDER BY nome, id'
        )->fetchAll();
    }

    /** @return array<int,array<string,mixed>> */
    public function listSalesByDate(string $date): array
    {
        $statement = $this->connection->prepare(
            'SELECT venda.*, cliente.nome AS cliente_nome, usuario.nome AS usuario_nome,
                    sessao.codigo AS sessao_codigo,
                    (SELECT COUNT(*) FROM venda_avulsa_itens item WHERE item.venda_avulsa_id = venda.id) AS itens
               FROM vendas_avulsas venda
               LEFT JOIN clientes cliente ON cliente.id = venda.cliente_id
               JOIN usuarios usuario ON usuario.id = venda.criada_por
               LEFT JOIN caixa_sessoes sessao ON sessao.id = venda.caixa_sessao_id
              WHERE DATE(venda.criada_em) = :date
              ORDER BY venda.id DESC'
        );
        $statement->execute(['date' => $this->validDate($date)]);
        return $statement->fetchAll();
    }

    /** @return array{id:int,numero:string} */
    public function createSale(array $data, int $userId): array
    {
        $items = $this->saleItems($data['itens'] ?? []);
        $form = (string) ($data['forma_pagamento'] ?? '');
        if (!in_array($form, self::paymentForms(), true)) throw new InvalidArgumentException('Forma de pagamento inválida.');
        $discount = $this->moneyCents($data['desconto'] ?? '0', true);
        $increase = $this->moneyCents($data['acrescimo'] ?? '0', true);
        $clientId = $this->optionalPositiveInt($data['cliente_id'] ?? null);

        return $this->transactional(function () use ($items, $form, $discount, $increase, $clientId, $userId): array {
            $session = $this->requireOpenSession(true);
            if ($clientId !== null) {
                $statement = $this->connection->prepare('SELECT id FROM clientes WHERE id = :id AND status = "ativo"');
                $statement->execute(['id' => $clientId]);
                if ($statement->fetchColumn() === false) throw new InvalidArgumentException('Cliente do PDV não encontrado ou inativo.');
            }

            $products = [];
            $subtotal = 0;
            foreach ($items as $item) {
                $statement = $this->connection->prepare('SELECT id, nome, unidade, preco_venda, estoque FROM produtos WHERE id = :id AND status = "ativo" FOR UPDATE');
                $statement->execute(['id' => $item['product_id']]);
                $product = $statement->fetch();
                if ($product === false) throw new InvalidArgumentException('Produto do PDV não encontrado ou inativo.');
                if ((float) $product['estoque'] < $item['quantity']) throw new InvalidArgumentException('Estoque insuficiente para ' . $product['nome'] . '.');
                $unitCents = (int) round((float) $product['preco_venda'] * 100);
                if ($unitCents <= 0) throw new InvalidArgumentException('Produto sem preço de venda válido.');
                $lineCents = (int) round($unitCents * $item['quantity']);
                $subtotal += $lineCents;
                $products[] = $item + ['row' => $product, 'unit_cents' => $unitCents, 'line_cents' => $lineCents];
            }
            $total = $subtotal - $discount + $increase;
            if ($total <= 0) throw new InvalidArgumentException('O total da venda deve ser maior que zero.');
            if ($discount > $subtotal) throw new InvalidArgumentException('O desconto não pode superar o subtotal.');

            $this->connection->prepare(
                'INSERT INTO vendas_avulsas
                    (caixa_sessao_id, numero, cliente_id, subtotal, desconto, acrescimo, total,
                     forma_pagamento, status, criada_por)
                 VALUES (:session_id, NULL, :client_id, :subtotal, :discount, :increase, :total,
                         :form, "emitida", :user_id)'
            )->execute([
                'session_id' => $session['id'], 'client_id' => $clientId,
                'subtotal' => $this->centsToDecimal($subtotal), 'discount' => $this->centsToDecimal($discount),
                'increase' => $this->centsToDecimal($increase), 'total' => $this->centsToDecimal($total),
                'form' => $form, 'user_id' => $userId,
            ]);
            $saleId = (int) $this->connection->lastInsertId();
            $number = sprintf('PDV-%06d', $saleId);
            $this->connection->prepare('UPDATE vendas_avulsas SET numero = :number WHERE id = :id')->execute(['number' => $number, 'id' => $saleId]);

            $insertItem = $this->connection->prepare(
                'INSERT INTO venda_avulsa_itens
                    (venda_avulsa_id, produto_id, descricao, unidade, quantidade, valor_unitario,
                     desconto, subtotal, estoque_movimentacao_id, ordem)
                 VALUES (:sale_id, :product_id, :description, :unit, :quantity, :unit_price,
                         0, :subtotal, :stock_movement_id, :sort_order)'
            );
            foreach ($products as $index => $product) {
                $stockId = $this->inventory->consumeForSale($saleId, $product['product_id'], number_format($product['quantity'], 3, '.', ''), $userId);
                $insertItem->execute([
                    'sale_id' => $saleId, 'product_id' => $product['product_id'],
                    'description' => $product['row']['nome'], 'unit' => $product['row']['unidade'],
                    'quantity' => number_format($product['quantity'], 3, '.', ''),
                    'unit_price' => $this->centsToDecimal($product['unit_cents']),
                    'subtotal' => $this->centsToDecimal($product['line_cents']),
                    'stock_movement_id' => $stockId, 'sort_order' => $index,
                ]);
            }
            $cashId = $this->insertMovement((int) $session['id'], 'entrada', 'venda_avulsa', $saleId, 'Venda ' . $number . ' no PDV', $form, $total, $userId);
            $this->connection->prepare('UPDATE vendas_avulsas SET caixa_movimentacao_id = :cash_id WHERE id = :id')->execute(['cash_id' => $cashId, 'id' => $saleId]);
            return ['id' => $saleId, 'numero' => $number];
        });
    }

    public function reverseSale(int $saleId, string $reason, int $userId): void
    {
        $reason = $this->requiredText($reason, 255, 'Informe o motivo do estorno.');
        $this->transactional(function () use ($saleId, $reason, $userId): void {
            $this->requireOpenSession(true);
            $statement = $this->connection->prepare('SELECT * FROM vendas_avulsas WHERE id = :id FOR UPDATE');
            $statement->execute(['id' => $saleId]);
            $sale = $statement->fetch();
            if ($sale === false) throw new InvalidArgumentException('Venda do PDV não encontrada.');
            if ((string) $sale['status'] !== 'emitida') throw new InvalidArgumentException('Somente venda emitida pode ser estornada.');
            $statement = $this->connection->prepare('SELECT * FROM venda_avulsa_itens WHERE venda_avulsa_id = :id ORDER BY produto_id, id FOR UPDATE');
            $statement->execute(['id' => $saleId]);
            foreach ($statement->fetchAll() as $item) {
                if ($item['estoque_movimentacao_id'] === null) throw new InvalidArgumentException('Venda sem vínculo de estoque auditável.');
                $this->inventory->restoreSaleMovement((int) $item['estoque_movimentacao_id'], $saleId, $userId, $reason);
            }
            if ($sale['caixa_movimentacao_id'] === null) throw new InvalidArgumentException('Venda sem vínculo financeiro auditável.');
            $this->reverseMovement((int) $sale['caixa_movimentacao_id'], 'venda_avulsa_estorno', $saleId, 'Estorno da venda ' . $sale['numero'] . ': ' . $reason, $userId);
            $this->connection->prepare(
                'UPDATE vendas_avulsas SET status = "estornada", estornada_por = :user_id,
                        estornada_em = CURRENT_TIMESTAMP, motivo_estorno = :reason WHERE id = :id'
            )->execute(['id' => $saleId, 'user_id' => $userId, 'reason' => $reason]);
        });
    }

    /** @return array<int,array{product_id:int,quantity:float}> */
    private function saleItems(mixed $rows): array
    {
        if (!is_array($rows)) throw new InvalidArgumentException('Informe os produtos da venda.');
        if (count($rows) > 100) throw new InvalidArgumentException('A venda excede o limite de 100 itens.');
        $items = [];
        foreach ($rows as $row) {
            if (!is_array($row)) continue;
            $productId = $this->optionalPositiveInt($row['produto_id'] ?? null);
            $quantity = str_replace(',', '.', trim((string) ($row['quantidade'] ?? '')));
            if ($productId === null || preg_match('/^\d+(\.\d{1,3})?$/', $quantity) !== 1 || (float) $quantity <= 0) {
                throw new InvalidArgumentException('Produto ou quantidade inválida no PDV.');
            }
            $items[$productId] = ($items[$productId] ?? 0.0) + (float) $quantity;
            if ($items[$productId] > 999999.999) throw new InvalidArgumentException('Quantidade do produto excede o limite permitido.');
        }
        if ($items === []) throw new InvalidArgumentException('Informe ao menos um produto na venda.');
        ksort($items);
        return array_map(static fn(int $id, float $quantity): array => ['product_id' => $id, 'quantity' => $quantity], array_keys($items), $items);
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || trim((string) $value) === '') return null;
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (!is_int($id)) throw new InvalidArgumentException('Identificador inválido.');
        return $id;
    }
}
