<?php

declare(strict_types=1);

namespace App\Core;

trait MigrationFourteenPostcondition
{
    private function migrationFourteenSatisfied(): bool
    {
        if (!$this->allTables(['metas_comissao_mensais'])
            || !$this->allColumns('metas_comissao_mensais', [
                'competencia', 'versao', 'valor_meta', 'percentual_comissao', 'ativa',
                'criada_por', 'criada_em', 'desativada_por', 'desativada_em', 'configuracao_ativa_chave',
            ])
            || !$this->allIndexes([
                ['metas_comissao_mensais', 'uq_meta_comissao_competencia_versao'],
                ['metas_comissao_mensais', 'uq_meta_comissao_competencia_ativa'],
                ['metas_comissao_mensais', 'idx_meta_comissao_competencia'],
            ])
            || !$this->allForeignKeys([
                'fk_meta_comissao_criada_usuario',
                'fk_meta_comissao_desativada_usuario',
            ])
            || !$this->permissionSatisfied('relatorio.comissao.visualizar')
            || !$this->permissionSatisfied('relatorio.meta_comissao.configurar')
        ) {
            return false;
        }

        $missingRequiredGrants = $this->scalar(
            "SELECT COUNT(*)
               FROM perfis perfil
               JOIN permissoes permissao
                 ON (permissao.codigo = 'relatorio.comissao.visualizar'
                     AND perfil.nome IN ('Administrador', 'Dono', 'Gerente'))
                  OR (permissao.codigo = 'relatorio.meta_comissao.configurar'
                     AND perfil.nome IN ('Administrador', 'Dono'))
              WHERE NOT EXISTS (
                    SELECT 1 FROM perfil_permissoes pp
                     WHERE pp.perfil_id = perfil.id AND pp.permissao_id = permissao.id
              )"
        );
        $unauthorizedConfigurationGrants = $this->scalar(
            "SELECT COUNT(*)
               FROM perfil_permissoes pp
               JOIN perfis perfil ON perfil.id = pp.perfil_id
               JOIN permissoes permissao ON permissao.id = pp.permissao_id
              WHERE permissao.codigo = 'relatorio.meta_comissao.configurar'
                AND perfil.nome NOT IN ('Administrador', 'Dono')"
        );

        return $missingRequiredGrants === 0 && $unauthorizedConfigurationGrants === 0;
    }
}
