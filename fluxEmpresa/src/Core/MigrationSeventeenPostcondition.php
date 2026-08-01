<?php

declare(strict_types=1);

namespace App\Core;

trait MigrationSeventeenPostcondition
{
    private function migrationSeventeenSatisfied(): bool
    {
        if (!$this->allTables([
            'fiscal_certificados', 'fiscal_configuracoes', 'fiscal_series', 'fiscal_auditoria',
        ])
            || !$this->allColumns('fiscal_certificados', [
                'arquivo_referencia', 'arquivo_sha256', 'certificado_fingerprint_sha256',
                'titular_cnpj', 'valido_de', 'valido_ate', 'senha_ciphertext', 'senha_nonce', 'senha_tag',
                'cifra_algoritmo', 'chave_versao', 'status', 'criado_por',
            ])
            || !$this->allColumns('fiscal_configuracoes', [
                'ambiente', 'modelo', 'versao', 'uf', 'schema_versao', 'certificado_id',
                'csc_ciphertext', 'csc_nonce', 'csc_tag', 'csc_algoritmo',
                'segredo_chave_versao', 'status',
                'configuracao_ativa_chave',
            ])
            || !$this->allColumns('fiscal_series', [
                'ambiente', 'modelo', 'serie', 'proximo_numero', 'ultimo_numero_reservado', 'status',
            ])
            || !$this->allColumns('fiscal_auditoria', [
                'entidade_tipo', 'entidade_id', 'acao', 'ambiente', 'modelo', 'usuario_id', 'detalhes',
            ])
            || !$this->allColumns('produtos', [
                'cest', 'origem_mercadoria', 'cfop_padrao', 'cst_icms', 'csosn', 'cst_pis',
                'cst_cofins', 'aliquota_icms', 'aliquota_pis', 'aliquota_cofins',
                'gtin_tributavel', 'unidade_tributavel', 'cst_ibs_cbs', 'classificacao_tributaria_ibs_cbs',
            ])
            || !$this->allColumns('clientes', [
                'inscricao_estadual', 'indicador_ie', 'codigo_municipio_ibge',
            ])
            || !$this->allColumns('configuracoes_empresa', [
                'crt', 'cnae_principal', 'endereco_logradouro', 'endereco_numero',
                'endereco_complemento', 'endereco_bairro', 'endereco_cidade', 'endereco_uf',
                'endereco_cep', 'codigo_municipio_ibge',
            ])
            || !$this->allIndexes([
                ['fiscal_certificados', 'uq_fiscal_certificado_fingerprint'],
                ['fiscal_configuracoes', 'uq_fiscal_configuracao_versao'],
                ['fiscal_configuracoes', 'uq_fiscal_configuracao_ativa'],
                ['fiscal_series', 'uq_fiscal_serie_ambiente_modelo'],
                ['fiscal_auditoria', 'idx_fiscal_auditoria_entidade'],
            ])
            || !$this->allForeignKeys([
                'fk_fiscal_certificado_criado_usuario', 'fk_fiscal_certificado_substituto',
                'fk_fiscal_configuracao_certificado', 'fk_fiscal_configuracao_criado_usuario',
                'fk_fiscal_configuracao_ativado_usuario', 'fk_fiscal_configuracao_desativado_usuario',
                'fk_fiscal_serie_criado_usuario', 'fk_fiscal_serie_atualizado_usuario',
                'fk_fiscal_auditoria_usuario',
            ])
        ) {
            return false;
        }

        $codes = [
            'nota_fiscal.configurar', 'nota_fiscal.gerenciar_credenciais',
            'nota_fiscal.ativar_producao', 'nota_fiscal.testar_integracao',
            'nota_fiscal.baixar_xml',
        ];
        foreach ($codes as $code) {
            if (!$this->permissionSatisfied($code)) return false;
        }

        return $this->scalar(
            "SELECT COUNT(*)
               FROM perfil_permissoes pp
               JOIN perfis perfil ON perfil.id = pp.perfil_id
               JOIN permissoes permissao ON permissao.id = pp.permissao_id
              WHERE permissao.codigo IN (
                    'nota_fiscal.configurar', 'nota_fiscal.gerenciar_credenciais',
                    'nota_fiscal.ativar_producao', 'nota_fiscal.testar_integracao',
                    'nota_fiscal.baixar_xml'
              )
                AND perfil.nome NOT IN ('Administrador', 'Dono')"
        ) === 0;
    }
}
