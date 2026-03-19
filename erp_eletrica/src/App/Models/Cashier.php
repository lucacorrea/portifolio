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

    public function getSummary($caixaId) {
        $stmtOp = $this->db->prepare("SELECT operador_id, data_abertura, data_fechamento, filial_id FROM caixas WHERE id = ?");
        $stmtOp->execute([$caixaId]);
        $caixa = $stmtOp->fetch();

        $dataAbertura = $caixa['data_abertura'] ?? date('Y-m-d H:i:s');
        $dataFechamento = $caixa['data_fechamento']; // Será NULL se aberto
        $filialId = $caixa['filial_id'] ?? 0;

        $whereTime = "AND data_venda >= ?";
        $paramsTime = [$filialId, $dataAbertura];
        if ($dataFechamento) {
            $whereTime .= " AND data_venda <= ?";
            $paramsTime[] = $dataFechamento;
        }

        // 1. Vendas diretas concluídas na sessão
        $sqlVendas = "
            SELECT LOWER(forma_pagamento), COALESCE(SUM(valor_total), 0) as total
            FROM vendas 
            WHERE filial_id = ? $whereTime AND status = 'concluido'
            GROUP BY forma_pagamento
        ";
        $stmtVendas = $this->db->prepare($sqlVendas);
        $stmtVendas->execute($paramsTime);
        $vendasPorForma = $stmtVendas->fetchAll(\PDO::FETCH_KEY_PAIR);

        // 2. Pagamentos de Fiados (contas a receber) recebidos NA SESSÃO
        $whereTimePagos = "AND fp.created_at >= ?";
        $paramsTimePagos = [$filialId, $dataAbertura];
        if ($dataFechamento) {
            $whereTimePagos .= " AND fp.created_at <= ?";
            $paramsTimePagos[] = $dataFechamento;
        }

        $sqlPagos = "
            SELECT LOWER(fp.metodo), COALESCE(SUM(fp.valor), 0) as total
            FROM fiados_pagamentos fp
            JOIN contas_receber cr ON fp.fiado_id = cr.id
            WHERE cr.filial_id = ? $whereTimePagos
            GROUP BY fp.metodo
        ";
        $stmtPagos = $this->db->prepare($sqlPagos);
        $stmtPagos->execute($paramsTimePagos);
        $pagosPorForma = $stmtPagos->fetchAll(\PDO::FETCH_KEY_PAIR);

        // Somar todas as entradas no caixa que tenham "Fiado" no motivo (com ou sem parênteses)
        $vTrasFiado = "
            SELECT COALESCE(SUM(valor), 0) 
            FROM caixa_movimentacoes 
            WHERE caixa_id = ? AND tipo = 'entrada' AND motivo LIKE '%Fiado%'
        ";
        $stmtEntradaFiado = $this->db->prepare($vTrasFiado);
        $stmtEntradaFiado->execute([$caixaId]);
        $entradasFiadoDinheiro = (float)$stmtEntradaFiado->fetchColumn();

        // Movimentações (sangria/suprimento)
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
        
        $vendasDinheiro = (float)($vendasPorForma['dinheiro'] ?? 0);
        $vendasPix = (float)($vendasPorForma['pix'] ?? 0);
        $vendasCC = (float)($vendasPorForma['cartao_credito'] ?? 0);
        $vendasCD = (float)($vendasPorForma['cartao_debito'] ?? 0);
        $vendasCG = (float)($vendasPorForma['cartao'] ?? 0);
        $vendasBoleto = (float)($vendasPorForma['boleto'] ?? 0);
        $vendasFiado = (float)($vendasPorForma['fiado'] ?? 0);
        
        // Adicionar pagamentos de fiados feitos em meios digitais
        $pagosPix = (float)($pagosPorForma['PIX'] ?? ($pagosPorForma['pix'] ?? 0));
        $pagosCartao = (float)($pagosPorForma['CARTAO'] ?? ($pagosPorForma['cartao'] ?? 0));
        $pagosBoleto = (float)($pagosPorForma['BOLETO'] ?? ($pagosPorForma['boleto'] ?? 0));
        
        $vendasCartaoTotal = $vendasCC + $vendasCD + $vendasCG + $pagosCartao;
        $totalPix = $vendasPix + $pagosPix;
        $totalBoleto = $vendasBoleto + $pagosBoleto;
        
        $totalBruto = $vendasDinheiro + $vendasPix + ($vendasCC + $vendasCD + $vendasCG) + $vendasBoleto + $vendasFiado + array_sum($pagosPorForma);

        // O que realmente deve estar na gaveta física do caixa
        $dinheiroEmGaveta = $vendasDinheiro + $entradasFiadoDinheiro + $movs['suprimentos'] - $movs['sangrias'];

        return [
            'vendas_dinheiro' => $vendasDinheiro,
            'vendas_pix' => $totalPix,
            'vendas_cartao' => $vendasCartaoTotal,
            'vendas_boleto' => $totalBoleto,
            'vendas_fiado' => $vendasFiado,
            'entradas_fiado_dinheiro' => $entradasFiadoDinheiro,
            'suprimentos' => $movs['suprimentos'],
            'sangrias' => $movs['sangrias'],
            'dinheiro_em_gaveta' => $dinheiroEmGaveta,
            'total_bruto' => $totalBruto
        ];
    }
}
