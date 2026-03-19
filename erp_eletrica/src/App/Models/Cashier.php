<?php
namespace App\Models;

class Cashier extends BaseModel {
    protected $table = 'caixas';

    public function getOpenForOperador($operadorId, $filialId) {
        $stmt = $this->db->prepare("
            SELECT * FROM {$this->table} 
            WHERE operador_id = ? AND filial_id = ? AND status = 'aberto' 
            LIMIT 1
        ");
        $stmt->execute([$operadorId, $filialId]);
        return $stmt->fetch();
    }

    public function getSummary($caixaId) {
        $stmtOp = $this->db->prepare("SELECT operador_id, data_abertura FROM caixas WHERE id = ?");
        $stmtOp->execute([$caixaId]);
        $caixa = $stmtOp->fetch();
        
        $operadorId = $caixa['operador_id'] ?? 0;
        $dataAbertura = $caixa['data_abertura'] ?? date('Y-m-d H:i:s');

        // Totals grouped by forma_pagamento
        $sqlVendas = "
            SELECT forma_pagamento, COALESCE(SUM(valor_total), 0) as total
            FROM vendas 
            WHERE usuario_id = ? AND data_venda >= ? AND status = 'concluido'
            GROUP BY forma_pagamento
        ";
        $stmtVendas = $this->db->prepare($sqlVendas);
        $stmtVendas->execute([$operadorId, $dataAbertura]);
        $vendasPorForma = $stmtVendas->fetchAll(\PDO::FETCH_KEY_PAIR);

        $vTrasFiado = "
            SELECT COALESCE(SUM(valor), 0) 
            FROM caixa_movimentacoes 
            WHERE caixa_id = ? AND tipo = 'entrada' AND motivo LIKE '%(Fiado)%'
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
        
        $vendasCartaoTotal = $vendasCC + $vendasCD + $vendasCG;
        
        $totalBruto = $vendasDinheiro + $vendasPix + $vendasCartaoTotal + $vendasBoleto + $vendasFiado;

        // O que realmente deve estar na gaveta física do caixa
        $dinheiroEmGaveta = $vendasDinheiro + $entradasFiadoDinheiro + $movs['suprimentos'] - $movs['sangrias'];

        return [
            'vendas_dinheiro' => $vendasDinheiro,
            'vendas_pix' => $vendasPix,
            'vendas_cartao' => $vendasCartaoTotal,
            'vendas_boleto' => $vendasBoleto,
            'vendas_fiado' => $vendasFiado,
            'entradas_fiado_dinheiro' => $entradasFiadoDinheiro,
            'suprimentos' => $movs['suprimentos'],
            'sangrias' => $movs['sangrias'],
            'dinheiro_em_gaveta' => $dinheiroEmGaveta,
            'total_bruto' => $totalBruto
        ];
    }
}
