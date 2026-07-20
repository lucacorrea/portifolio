<?php

declare(strict_types=1);

namespace App\Core;

trait MigrationSixteenPostcondition
{
    private function migrationSixteenSatisfied(): bool
    {
        return $this->tableExists('caixa_sessoes')
            && $this->allColumns('caixa_sessoes', [
                'codigo', 'status', 'valor_abertura', 'aberto_por', 'aberto_em',
                'saldo_esperado', 'saldo_informado', 'diferenca', 'fechado_por',
                'fechado_em', 'sessao_aberta_chave',
            ])
            && $this->allColumns('caixa_movimentacoes', ['caixa_sessao_id'])
            && $this->allColumns('vendas_avulsas', ['caixa_sessao_id'])
            && $this->allIndexes([
                ['caixa_sessoes', 'uq_caixa_sessao_codigo'],
                ['caixa_sessoes', 'uq_caixa_sessao_aberta'],
                ['caixa_movimentacoes', 'idx_caixa_mov_sessao_data'],
                ['vendas_avulsas', 'idx_vendas_avulsas_sessao'],
            ])
            && $this->allForeignKeys([
                'fk_caixa_sessao_aberto_usuario', 'fk_caixa_sessao_fechado_usuario',
                'fk_caixa_mov_sessao', 'fk_vendas_avulsas_sessao',
            ])
            && $this->permissionSatisfied('caixa.abrir')
            && $this->permissionSatisfied('caixa.fechar')
            && $this->permissionSatisfied('caixa.sangria')
            && $this->permissionSatisfied('caixa.suprimento')
            && $this->permissionSatisfied('caixa.registrar_venda');
    }
}
