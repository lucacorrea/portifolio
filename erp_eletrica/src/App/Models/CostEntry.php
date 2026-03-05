<?php
namespace App\Models;

class CostEntry extends BaseModel {
    protected $table = 'lancamentos_custos';

    public function getMonthlyTotals(int $filial_id, string $mes_ano) {
        $sql = "SELECT cc.tipo, SUM(lc.valor) as total 
                FROM lancamentos_custos lc
                JOIN centros_custo cc ON lc.centro_custo_id = cc.id
                WHERE lc.filial_id = ? AND lc.data_lancamento LIKE ?
                GROUP BY cc.tipo";
        $stmt = $this->query($sql, [$filial_id, $mes_ano . '%']);
        return $stmt->fetchAll();
    }

    public function getDetailedByMonth(int $filial_id, string $mes_ano) {
        $sql = "SELECT lc.*, cc.nome as centro_nome, cc.tipo 
                FROM lancamentos_custos lc
                JOIN centros_custo cc ON lc.centro_custo_id = cc.id
                WHERE lc.filial_id = ? AND lc.data_lancamento LIKE ?
                ORDER BY lc.data_lancamento ASC";
        return $this->query($sql, [$filial_id, $mes_ano . '%'])->fetchAll();
    }
}
