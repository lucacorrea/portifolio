<?php

declare(strict_types=1);

namespace App\Core;

trait MigrationFifteenPostcondition
{
    private function migrationFifteenSatisfied(): bool
    {
        if (!$this->allTables(['contas_pagar_parcelas', 'contas_pagar_parcela_eventos'])
            || !$this->allColumns('contas_pagar', ['tipo_pagamento', 'quantidade_parcelas', 'forma_pagamento'])
            || !$this->allColumns('contas_pagar_parcelas', [
                'conta_pagar_id', 'numero', 'vencimento_em', 'valor', 'status',
                'quitada_em', 'quitada_por', 'forma_pagamento_quitacao', 'caixa_movimentacao_id',
            ])
            || !$this->allColumns('contas_pagar_parcela_eventos', [
                'parcela_id', 'tipo', 'forma_pagamento', 'observacao', 'usuario_id', 'caixa_movimentacao_id',
            ])
            || !$this->allIndexes([
                ['contas_pagar_parcelas', 'uq_conta_pagar_parcela_numero'],
                ['contas_pagar_parcelas', 'idx_conta_pagar_parcela_status_vencimento'],
                ['contas_pagar_parcelas', 'idx_conta_pagar_parcela_caixa'],
                ['contas_pagar_parcela_eventos', 'idx_conta_pagar_evento_parcela_data'],
                ['contas_pagar_parcela_eventos', 'idx_conta_pagar_evento_caixa'],
            ])
            || !$this->allForeignKeys([
                'fk_conta_pagar_parcela_conta', 'fk_conta_pagar_parcela_quitada_usuario',
                'fk_conta_pagar_parcela_caixa', 'fk_conta_pagar_evento_parcela',
                'fk_conta_pagar_evento_usuario', 'fk_conta_pagar_evento_caixa',
            ])
            || !$this->permissionSatisfied('contas_pagar.quitar')
            || !$this->permissionSatisfied('contas_pagar.estornar_pagamento')
        ) {
            return false;
        }

        return $this->scalar(
            'SELECT COUNT(*) FROM contas_pagar conta
              WHERE NOT EXISTS (
                    SELECT 1 FROM contas_pagar_parcelas parcela
                     WHERE parcela.conta_pagar_id = conta.id
              )'
        ) === 0;
    }
}
