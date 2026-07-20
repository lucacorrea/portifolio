<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Core/SqlStatementSplitter.php';
require dirname(__DIR__) . '/src/Core/MigrationException.php';
require dirname(__DIR__) . '/src/Core/MigrationRunner.php';

use App\Core\MigrationRunner;
use App\Core\SqlStatementSplitter;

function migrationAssertSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Esperado: ' . var_export($expected, true) . '; obtido: ' . var_export($actual, true));
    }
}

$sample = <<<'SQL'
-- comentário com ; não encerra comando
SET @sql := 'SELECT ''valor;interno''';
/* bloco ; ignorado */
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;
SQL;

$sampleStatements = SqlStatementSplitter::split($sample);
migrationAssertSame(4, count($sampleStatements), 'O parser deve respeitar comentários e ponto e vírgula dentro de string.');
migrationAssertSame(true, str_contains($sampleStatements[0], "valor;interno"), 'O conteúdo da string SQL deve ser preservado.');

$migrationPaths = glob(dirname(__DIR__) . '/database/migrations/*.sql') ?: [];
sort($migrationPaths, SORT_NATURAL | SORT_FLAG_CASE);
migrationAssertSame(18, count($migrationPaths), 'A sequência atual deve conter 18 migrations.');

$expectedVersion = 1;
foreach ($migrationPaths as $path) {
    $name = basename($path);
    migrationAssertSame(
        true,
        preg_match('/^(\d{3})_[a-z0-9_]+\.sql$/', $name, $matches) === 1,
        'Nome de migration inválido: ' . $name
    );
    migrationAssertSame($expectedVersion, (int) $matches[1], 'A sequência de migrations deve ser contínua.');
    migrationAssertSame(
        true,
        MigrationRunner::supportsVersion((int) $matches[1]),
        'Toda migration versionada deve estar homologada para execução automática.'
    );
    $sql = file_get_contents($path);
    migrationAssertSame(true, is_string($sql) && trim($sql) !== '', 'Migration vazia: ' . $name);
    migrationAssertSame(true, count(SqlStatementSplitter::split((string) $sql)) > 0, 'Migration sem comandos: ' . $name);
    ++$expectedVersion;
}

migrationAssertSame(false, MigrationRunner::supportsVersion($expectedVersion), 'Versão futura não pode ser executada sem homologação.');

$commissionMigration = file_get_contents(dirname(__DIR__) . '/database/migrations/014_create_monthly_commission_goals.sql');
migrationAssertSame(true, is_string($commissionMigration), 'A migration de metas mensais deve ser legível.');
migrationAssertSame(true, str_contains((string) $commissionMigration, 'uq_meta_comissao_competencia_ativa'), 'Apenas uma configuração ativa deve existir por competência.');
migrationAssertSame(true, str_contains((string) $commissionMigration, 'uq_meta_comissao_competencia_versao'), 'O histórico deve preservar versões da competência.');
migrationAssertSame(true, str_contains((string) $commissionMigration, 'relatorio.comissao.visualizar'), 'A permissão de visualização deve ser criada.');
migrationAssertSame(true, str_contains((string) $commissionMigration, 'relatorio.meta_comissao.configurar'), 'A permissão de configuração deve ser criada.');
migrationAssertSame(false, str_contains((string) $commissionMigration, 'DAYOFMONTH('), 'A migration deve evitar função incompatível com o analisador SQL da hospedagem.');
migrationAssertSame(false, str_contains((string) $commissionMigration, 'desativada_por IS NULL'), 'Coluna com ON DELETE SET NULL não pode participar de CHECK no MariaDB da hospedagem.');

$installmentMigration = file_get_contents(dirname(__DIR__) . '/database/migrations/015_accounts_payable_installments.sql');
migrationAssertSame(true, is_string($installmentMigration), 'A migration de parcelas deve ser legível.');
migrationAssertSame(true, str_contains((string) $installmentMigration, 'contas_pagar_parcelas'), 'A estrutura de parcelas deve ser criada.');
migrationAssertSame(true, str_contains((string) $installmentMigration, 'contas_pagar_parcela_eventos'), 'Quitações e estornos devem preservar histórico.');
migrationAssertSame(true, str_contains((string) $installmentMigration, 'contas_pagar.estornar_pagamento'), 'O estorno deve possuir permissão própria.');
migrationAssertSame(true, str_contains((string) $installmentMigration, 'caixa_movimentacao_id'), 'Quitações devem estar vinculadas às movimentações do Caixa.');

$cashMigration = file_get_contents(dirname(__DIR__) . '/database/migrations/016_cash_register_pos.sql');
migrationAssertSame(true, is_string($cashMigration), 'A migration do Caixa deve ser legível.');
migrationAssertSame(true, str_contains((string) $cashMigration, 'caixa_sessoes'), 'A sessão operacional de Caixa deve ser criada.');
migrationAssertSame(true, str_contains((string) $cashMigration, 'uq_caixa_sessao_aberta'), 'Somente uma sessão de Caixa pode permanecer aberta.');
migrationAssertSame(true, str_contains((string) $cashMigration, 'caixa_sessao_id'), 'Movimentações e vendas devem identificar sua sessão de Caixa.');
migrationAssertSame(true, str_contains((string) $cashMigration, 'saida_venda'), 'O estoque deve identificar baixas originadas pelo PDV.');
migrationAssertSame(true, str_contains((string) $cashMigration, 'caixa.registrar_venda'), 'A operação do PDV deve possuir permissão própria.');

$fiscalMigration = file_get_contents(dirname(__DIR__) . '/database/migrations/017_secure_fiscal_foundation.sql');
migrationAssertSame(true, is_string($fiscalMigration), 'A migration da fundação fiscal deve ser legível.');
migrationAssertSame(true, str_contains((string) $fiscalMigration, 'fiscal_certificados'), 'Certificados devem possuir armazenamento de metadados próprio.');
migrationAssertSame(true, str_contains((string) $fiscalMigration, 'senha_ciphertext'), 'Senha do certificado deve ser armazenada somente cifrada.');
migrationAssertSame(true, str_contains((string) $fiscalMigration, 'senha_tag'), 'Senha cifrada deve preservar a tag de autenticação AEAD.');
migrationAssertSame(true, str_contains((string) $fiscalMigration, 'csc_ciphertext'), 'CSC deve ser armazenado somente cifrado.');
migrationAssertSame(true, str_contains((string) $fiscalMigration, 'csc_tag'), 'CSC cifrado deve preservar a tag de autenticação AEAD.');
migrationAssertSame(true, str_contains((string) $fiscalMigration, 'uq_fiscal_configuracao_ativa'), 'Ambiente e modelo devem possuir apenas uma configuração ativa.');
migrationAssertSame(true, str_contains((string) $fiscalMigration, 'uq_fiscal_serie_ambiente_modelo'), 'Numeração deve ser isolada por ambiente, modelo e série.');
migrationAssertSame(true, str_contains((string) $fiscalMigration, 'fiscal_auditoria'), 'Operações fiscais sensíveis devem possuir auditoria própria.');
migrationAssertSame(false, str_contains((string) $fiscalMigration, 'certificado_senha VARCHAR'), 'Segredos fiscais não podem ser armazenados em texto puro.');
migrationAssertSame(false, str_contains((string) $fiscalMigration, ' csc VARCHAR'), 'CSC não pode ser armazenado em texto puro na fundação nova.');

$agendaReminderMigration = file_get_contents(dirname(__DIR__) . '/database/migrations/018_complete_agenda_reminders.sql');
migrationAssertSame(true, is_string($agendaReminderMigration), 'A migration de conclusão dos lembretes deve ser legível.');
migrationAssertSame(true, str_contains((string) $agendaReminderMigration, "ENUM('ativo', 'concluido', 'cancelado')"), 'Conclusão não pode reutilizar o estado cancelado.');
migrationAssertSame(true, str_contains((string) $agendaReminderMigration, 'concluido_em'), 'A conclusão deve registrar data e hora.');
migrationAssertSame(true, str_contains((string) $agendaReminderMigration, 'concluido_por'), 'A conclusão deve registrar o usuário responsável.');
migrationAssertSame(true, str_contains((string) $agendaReminderMigration, 'fk_agenda_lembretes_concluido_usuario'), 'A auditoria da conclusão deve preservar integridade referencial.');

echo "MigrationRunnerTest: OK\n";
