<?php
namespace App\Models;

class Cashier extends BaseModel {
    protected $table = 'caixas';

    public function getOpenForFilial($filialId) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE filial_id = ? AND status = 'aberto' 
            LIMIT 1
        ");
        $stmt->execute([$filialId]);
        return $stmt->fetch();
    }

    private function sumByAliases(array $source, array $aliases): float {
        $total = 0.0;
        foreach ($aliases as $alias) {
            $total += (float)($source[$alias] ?? 0);
        }
        return $total;
    }

    private function normalizePaymentMethod($method): string {
        $key = strtoupper(trim((string)$method));
        $key = str_replace(['-', ' '], '_', $key);

        $map = [
            'CARTAO_CREDITO' => 'CARTAO_CREDITO',
            'CARTAO_CRED' => 'CARTAO_CREDITO',
            'CREDITO' => 'CARTAO_CREDITO',
            'CARTAO_DEBITO' => 'CARTAO_DEBITO',
            'CARTAO_DEB' => 'CARTAO_DEBITO',
            'DEBITO' => 'CARTAO_DEBITO',
            'CARTAO' => 'CARTAO',
            'DINHEIRO' => 'DINHEIRO',
            'PIX' => 'PIX',
            'FIADO' => 'FIADO',
            'A_PRAZO' => 'FIADO',
        ];

        return $map[$key] ?? $key;
    }

    public function getDetailedSummary($caixaId) {
        $stmtOp = $this->db->prepare("SELECT operador_id, data_abertura, data_fechamento, filial_id FROM caixas WHERE id = ?");
        $stmtOp->execute([$caixaId]);
        $caixa = $stmtOp->fetch();

        $dataAbertura = $caixa['data_abertura'] ?? date('Y-m-d H:i:s');
        $dataFechamento = $caixa['data_fechamento'] ?? date('Y-m-d H:i:s');
        $filialId = $caixa['filial_id'] ?? 0;

        $whereTime = "AND data_venda >= ? AND data_venda <= ?";
        $paramsTime = [$filialId, $dataAbertura, $dataFechamento];

        // 1. Vendas diretas por forma de pagamento exata
        $sqlVendas = "
            SELECT UPPER(forma_pagamento), COALESCE(SUM(valor_total), 0) as total
            FROM vendas 
            WHERE filial_id = ? $whereTime AND status = 'concluido'
            GROUP BY forma_pagamento
        ";
        $stmtVendas = $this->db->prepare($sqlVendas);
        $stmtVendas->execute($paramsTime);
        $vendasPorForma = $stmtVendas->fetchAll(\PDO::FETCH_KEY_PAIR);

        // 2. Pagamentos de Fiados (Recebimentos)
        $whereTimePagos = "AND fp.created_at >= ? AND fp.created_at <= ?";
        $paramsTimePagos = [$filialId, $dataAbertura, $dataFechamento];

        $sqlPagos = "
            SELECT UPPER(fp.metodo), COALESCE(SUM(fp.valor), 0) as total
            FROM fiados_pagamentos fp
            JOIN contas_receber cr ON fp.fiado_id = cr.id
            WHERE cr.filial_id = ? $whereTimePagos
            GROUP BY fp.metodo
        ";
        $stmtPagos = $this->db->prepare($sqlPagos);
        $stmtPagos->execute($paramsTimePagos);
        $pagosPorForma = $stmtPagos->fetchAll(\PDO::FETCH_KEY_PAIR);

        $multiPorForma = [];
        try {
            $hasMultiDetalhes = (bool)$this->db->query("SHOW COLUMNS FROM vendas LIKE 'multi_detalhes'")->fetch();
        } catch (\Exception $e) {
            $hasMultiDetalhes = false;
        }

        if ($hasMultiDetalhes) {
            $sqlMulti = "
                SELECT multi_detalhes, COALESCE(troco, 0) as troco
                FROM vendas
                WHERE filial_id = ? $whereTime AND status = 'concluido' AND forma_pagamento = 'multiplo'
            ";
            $stmtMulti = $this->db->prepare($sqlMulti);
            $stmtMulti->execute($paramsTime);

            foreach ($stmtMulti->fetchAll() as $multi) {
                $detalhes = json_decode($multi['multi_detalhes'] ?? '', true);
                if (!is_array($detalhes)) continue;

                $troco = (float)($multi['troco'] ?? 0);
                foreach ($detalhes as $metodo => $valor) {
                    $valor = (float)$valor;
                    if ($valor <= 0) continue;

                    $key = $this->normalizePaymentMethod($metodo);
                    if ($key === 'DINHEIRO' && $troco > 0) {
                        $valor = max(0, $valor - $troco);
                        $troco = 0;
                    }

                    $multiPorForma[$key] = ($multiPorForma[$key] ?? 0) + $valor;
                }
            }
        }

        // 3. Movimentações
        $sqlMov = "
            SELECT 
                COALESCE(SUM(CASE WHEN tipo = 'suprimento' THEN valor ELSE 0 END), 0) as suprimentos,
                COALESCE(SUM(CASE WHEN tipo = 'sangria' THEN valor ELSE 0 END), 0) as sangrias
            FROM caixa_movimentacoes 
            WHERE caixa_id = ?
        ";
        $stmtMov = $this->db->prepare($sqlMov);
        $stmtMov->execute([$caixaId]);
        $movs = $stmtMov->fetch();

        // Mapeamento e Normalização para os nomes solicitados pelo usuário
        $mapped = [
            'A PRAZO' => (float)($vendasPorForma['FIADO'] ?? 0) + (float)($vendasPorForma['A PRAZO'] ?? 0),
            'CARTAO' => (float)($vendasPorForma['CARTAO_CREDITO'] ?? 0) + (float)($vendasPorForma['CARTÃO CRÉDITO'] ?? 0) + (float)($vendasPorForma['CARTAO_DEBITO'] ?? 0) + (float)($vendasPorForma['CARTÃO DÉBITO'] ?? 0) + (float)($vendasPorForma['CARTAO'] ?? 0),
            'DINHEIRO' => (float)($vendasPorForma['DINHEIRO'] ?? 0) + (float)($pagosPorForma['DINHEIRO'] ?? 0),
            'PIX' => (float)($vendasPorForma['PIX'] ?? 0) + (float)($pagosPorForma['PIX'] ?? 0)
        ];

        $cartaoCreditoAliases = ['CARTAO_CREDITO', 'CARTAO CREDITO', 'CREDITO'];
        $cartaoDebitoAliases = ['CARTAO_DEBITO', 'CARTAO DEBITO', 'DEBITO'];
        $cartaoGenericoAliases = ['CARTAO'];
        $cartaoGenerico = $this->sumByAliases($vendasPorForma, $cartaoGenericoAliases)
            + $this->sumByAliases($pagosPorForma, $cartaoGenericoAliases)
            + (float)($multiPorForma['CARTAO'] ?? 0);

        $mapped = [
            'A PRAZO' => (float)($vendasPorForma['FIADO'] ?? 0) + (float)($vendasPorForma['A PRAZO'] ?? 0) + (float)($multiPorForma['FIADO'] ?? 0),
            'CARTAO CREDITO' => $this->sumByAliases($vendasPorForma, $cartaoCreditoAliases) + $this->sumByAliases($pagosPorForma, $cartaoCreditoAliases) + (float)($multiPorForma['CARTAO_CREDITO'] ?? 0),
            'CARTAO DEBITO' => $this->sumByAliases($vendasPorForma, $cartaoDebitoAliases) + $this->sumByAliases($pagosPorForma, $cartaoDebitoAliases) + (float)($multiPorForma['CARTAO_DEBITO'] ?? 0),
            'DINHEIRO' => (float)($vendasPorForma['DINHEIRO'] ?? 0) + (float)($pagosPorForma['DINHEIRO'] ?? 0) + (float)($multiPorForma['DINHEIRO'] ?? 0),
            'PIX' => (float)($vendasPorForma['PIX'] ?? 0) + (float)($pagosPorForma['PIX'] ?? 0) + (float)($multiPorForma['PIX'] ?? 0)
        ];

        if ($cartaoGenerico > 0) {
            $mapped['CARTAO'] = $cartaoGenerico;
        }

        // Totais e Saldo
        $totalVendido = array_sum($mapped);
        $saldoGaveta = $mapped['DINHEIRO'] + $movs['suprimentos'] - $movs['sangrias'];

        return [
            'breakdown' => $mapped,
            'suprimento' => (float)$movs['suprimentos'],
            'sangria' => (float)$movs['sangrias'],
            'saldo' => $saldoGaveta,
            'total_vendas' => $totalVendido,
            'recebimentos' => array_sum($pagosPorForma)
        ];
    }


    public function getSummary($caixaId) {
        $detailed = $this->getDetailedSummary($caixaId);
        $caixa = $this->find($caixaId);
        $valorAbertura = (float)($caixa['valor_abertura'] ?? 0);
        
        return [
            'vendas_dinheiro' => $detailed['breakdown']['DINHEIRO'],
            'vendas_pix' => $detailed['breakdown']['PIX'],
            'vendas_cartao' => ($detailed['breakdown']['CARTAO CREDITO'] ?? 0) + ($detailed['breakdown']['CARTAO DEBITO'] ?? 0) + ($detailed['breakdown']['CARTAO'] ?? 0),
            'vendas_boleto' => 0,
            'vendas_fiado' => $detailed['breakdown']['A PRAZO'],
            'entradas_fiado_dinheiro' => 0,
            'suprimentos' => $detailed['suprimento'],

            'sangrias' => $detailed['sangria'],
            'dinheiro_em_gaveta' => $detailed['saldo'] - $valorAbertura,
            'total_bruto' => $detailed['total_vendas']
        ];
    }


    public function getSessionDetails($caixaId) {
        // 1. Dados do caixa + operador
        $stmt = $this->db->prepare("
            SELECT c.*, u.nome as operador_nome 
            FROM caixas c 
            LEFT JOIN usuarios u ON c.operador_id = u.id 
            WHERE c.id = ?
        ");
        $stmt->execute([$caixaId]);
        $caixa = $stmt->fetch();

        if (!$caixa) return null;

        $dataAbertura = $caixa['data_abertura'];
        $dataFechamento = $caixa['data_fechamento'];
        $filialId = $caixa['filial_id'];

        // 2. Summary (reutiliza método detalhado agora)
        $summary = $this->getDetailedSummary($caixaId);


        // 3. Vendas do período
        $whereTime = "AND v.data_venda >= ?";
        $paramsVendas = [$filialId, $dataAbertura];
        if ($dataFechamento) {
            $whereTime .= " AND v.data_venda <= ?";
            $paramsVendas[] = $dataFechamento;
        }

        $stmtVendas = $this->db->prepare("
            SELECT v.*, 
                   IFNULL(cl.nome, v.nome_cliente_avulso) as cliente_nome,
                   u.nome as vendedor_nome
            FROM vendas v 
            LEFT JOIN clientes cl ON v.cliente_id = cl.id
            LEFT JOIN usuarios u ON v.usuario_id = u.id
            WHERE v.filial_id = ? $whereTime AND v.status = 'concluido'
            ORDER BY v.data_venda DESC
        ");
        $stmtVendas->execute($paramsVendas);
        $vendas = $stmtVendas->fetchAll();

        // 4. Recebimentos de Fiados no período
        $whereTimeFiado = "AND fp.created_at >= ?";
        $paramsFiado = [$filialId, $dataAbertura];
        if ($dataFechamento) {
            $whereTimeFiado .= " AND fp.created_at <= ?";
            $paramsFiado[] = $dataFechamento;
        }

        $stmtFiados = $this->db->prepare("
            SELECT fp.*, c.nome as cliente_nome, cr.valor as valor_fiado_total
            FROM fiados_pagamentos fp
            JOIN contas_receber cr ON fp.fiado_id = cr.id
            JOIN clientes c ON cr.cliente_id = c.id
            WHERE cr.filial_id = ? $whereTimeFiado
            ORDER BY fp.created_at DESC
        ");
        $stmtFiados->execute($paramsFiado);
        $fiadosPagamentos = $stmtFiados->fetchAll();

        // 5. Movimentações
        $stmtMov = $this->db->prepare("
            SELECT cm.*, u.nome as operador_nome
            FROM caixa_movimentacoes cm
            LEFT JOIN usuarios u ON cm.operador_id = u.id
            WHERE cm.caixa_id = ?
            ORDER BY cm.created_at ASC
        ");
        $stmtMov->execute([$caixaId]);
        $movimentacoes = $stmtMov->fetchAll();

        return [
            'caixa' => $caixa,
            'summary' => $summary,
            'vendas' => $vendas,
            'fiados_pagamentos' => $fiadosPagamentos,
            'movimentacoes' => $movimentacoes
        ];
    }
}
