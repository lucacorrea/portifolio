<?php

declare(strict_types=1);

namespace App\Core;

trait MigrationThirteenPostcondition
{
    private function migrationThirteenSatisfied(): bool
    {
        $permissionCodes = [
            'fornecedor.visualizar', 'fornecedor.criar', 'fornecedor.editar', 'fornecedor.desativar',
            'contas_pagar.visualizar', 'contas_pagar.criar', 'contas_pagar.editar', 'contas_pagar.cancelar',
        ];
        if (!$this->allTables(['fornecedores', 'contas_pagar'])
            || !$this->allColumns('fornecedores', [
                'codigo', 'tipo_pessoa', 'nome', 'nome_fantasia', 'documento', 'inscricao_estadual',
                'contato', 'telefone', 'whatsapp', 'email', 'cep', 'endereco', 'numero',
                'complemento', 'bairro', 'cidade', 'estado', 'observacao', 'status', 'criado_por',
                'criado_em', 'atualizado_em',
            ])
            || !$this->allColumns('contas_pagar', [
                'codigo', 'fornecedor_id', 'descricao', 'documento', 'data_emissao', 'vencimento_em',
                'valor', 'status', 'observacao', 'criado_por', 'cancelada_em', 'cancelada_por',
                'motivo_cancelamento', 'criado_em', 'atualizado_em',
            ])
            || !$this->allIndexes([
                ['fornecedores', 'uq_fornecedores_codigo'], ['fornecedores', 'uq_fornecedores_documento'],
                ['contas_pagar', 'uq_contas_pagar_codigo'], ['contas_pagar', 'uq_contas_pagar_fornecedor_documento'],
                ['contas_pagar', 'idx_contas_pagar_status_vencimento'],
            ])
            || !$this->allForeignKeys([
                'fk_fornecedores_criado_por', 'fk_contas_pagar_fornecedor',
                'fk_contas_pagar_criado_por', 'fk_contas_pagar_cancelada_por',
            ])
        ) {
            return false;
        }

        foreach ($permissionCodes as $code) {
            if (!$this->permissionSatisfied($code)) return false;
        }

        return $this->scalar(
            "SELECT COUNT(*) FROM perfis perfil
               JOIN permissoes permissao ON permissao.codigo IN (
                    'fornecedor.visualizar', 'fornecedor.criar', 'fornecedor.editar', 'fornecedor.desativar',
                    'contas_pagar.visualizar', 'contas_pagar.criar', 'contas_pagar.editar', 'contas_pagar.cancelar'
               )
              WHERE perfil.nome IN ('Administrador', 'Dono', 'Gerente')
                AND NOT EXISTS (
                    SELECT 1 FROM perfil_permissoes pp
                     WHERE pp.perfil_id = perfil.id AND pp.permissao_id = permissao.id
                )"
        ) === 0;
    }
}
