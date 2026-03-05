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
        // Vendas em dinheiro durante o período do caixa
        $sqlVendas = "
            SELECT COALESCE(SUM(valor_total), 0) 
            FROM vendas 
            WHERE usuario_id = (SELECT operador_id FROM caixas WHERE id = ?) 
            AND data_venda >= (SELECT data_abertura FROM caixas WHERE id = ?)
            AND forma_pagamento = 'dinheiro'
            AND status = 'concluido'
        ";
        $stmtVendas = $this->db->prepare($sqlVendas);
        $stmtVendas->execute([$caixaId, $caixaId]);
        $vendasDinheiro = $stmtVendas->fetchColumn();

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

        return [
            'vendas_dinheiro' => $vendasDinheiro,
            'suprimentos' => $movs['suprimentos'],
            'sangrias' => $movs['sangrias']
        ];
    }
}
