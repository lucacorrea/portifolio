<?php

declare(strict_types=1);

use App\Core\Database;

final class TenantBackfillValidationException extends RuntimeException
{
}

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

putenv('DB_AUTO_MIGRATE=false');
$_ENV['DB_AUTO_MIGRATE'] = 'false';
$_SERVER['DB_AUTO_MIGRATE'] = 'false';

$options = getopt('', ['empresa-id:']);
$rawCompanyId = is_array($options) ? ($options['empresa-id'] ?? null) : null;
$companyId = filter_var($rawCompanyId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if (!is_int($companyId)) {
    fwrite(STDERR, 'Uso: php scripts/backfill-tenant-operational-scope.php --empresa-id=ID' . PHP_EOL);
    exit(2);
}

$rootTables = [
    'funcionarios', 'produtos', 'servicos', 'clientes', 'agenda_lembretes',
    'caixa_movimentacoes', 'configuracoes_empresa', 'configuracoes_fiscais',
    'documentos_fiscais', 'recibos', 'boletos', 'vendas_avulsas', 'fornecedores',
    'metas_comissao_mensais', 'caixa_sessoes', 'fiscal_certificados',
    'fiscal_configuracoes', 'fiscal_series', 'fiscal_auditoria',
];

$relationships = [
    ['orcamentos', 'cliente_id', 'clientes'],
    ['orcamento_itens', 'orcamento_id', 'orcamentos'],
    ['ordens_servico', 'cliente_id', 'clientes'],
    ['ordem_servico_itens', 'ordem_servico_id', 'ordens_servico'],
    ['ordem_servico_funcionarios', 'ordem_servico_id', 'ordens_servico'],
    ['ordem_servico_cancelamentos', 'ordem_servico_id', 'ordens_servico'],
    ['ordem_servico_finalizacoes', 'ordem_servico_id', 'ordens_servico'],
    ['ordem_servico_execucao_itens', 'ordem_servico_id', 'ordens_servico'],
    ['estoque_autorizacoes', 'ordem_servico_id', 'ordens_servico'],
    ['estoque_movimentacoes', 'produto_id', 'produtos'],
    ['ordem_servico_pagamentos', 'ordem_servico_id', 'ordens_servico'],
    ['contas_receber', 'ordem_servico_id', 'ordens_servico'],
    ['contas_receber_eventos', 'conta_receber_id', 'contas_receber'],
    ['venda_avulsa_itens', 'venda_avulsa_id', 'vendas_avulsas'],
    ['contas_pagar', 'fornecedor_id', 'fornecedores'],
    ['contas_pagar_parcelas', 'conta_pagar_id', 'contas_pagar'],
    ['contas_pagar_parcela_eventos', 'parcela_id', 'contas_pagar_parcelas'],
];

$allTables = array_merge($rootTables, array_column($relationships, 0));
$connection = null;
$lockAcquired = false;

try {
    $app = require dirname(__DIR__) . '/bootstrap.php';
    /** @var Database $database */
    $database = $app['database'];
    $connection = $database->connection();

    $lockStatement = $connection->prepare("SELECT GET_LOCK('fluxempresa_tenant_backfill', 0)");
    $lockStatement->execute();
    $lockAcquired = (int) $lockStatement->fetchColumn() === 1;
    if (!$lockAcquired) {
        throw new TenantBackfillValidationException('Outro backfill de empresa está em andamento.');
    }

    foreach ($allTables as $table) {
        $schema = $connection->prepare(
            'SELECT COUNT(*) FROM information_schema.COLUMNS
              WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
        );
        $schema->execute(['table' => $table, 'column' => 'empresa_id']);
        if ((int) $schema->fetchColumn() !== 1) {
            throw new TenantBackfillValidationException('A migration 025 ainda não está completa para ' . $table . '.');
        }
    }
    $membershipSchema = $connection->prepare(
        'SELECT COUNT(*) FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table AND COLUMN_NAME = :column'
    );
    $membershipSchema->execute(['table' => 'usuario_empresas', 'column' => 'empresa_id']);
    if ((int) $membershipSchema->fetchColumn() !== 1) {
        throw new TenantBackfillValidationException('A migration 025 ainda não criou os vínculos de usuários por empresa.');
    }

    $connection->beginTransaction();
    $company = $connection->prepare('SELECT id, status FROM empresas WHERE id = :id FOR UPDATE');
    $company->execute(['id' => $companyId]);
    $companyRow = $company->fetch();
    if (!is_array($companyRow)) {
        throw new TenantBackfillValidationException('Empresa informada não existe.');
    }
    if ((string) $companyRow['status'] !== 'ativo') {
        throw new TenantBackfillValidationException('A empresa informada precisa estar ativa.');
    }

    $membershipConflicts = $connection->prepare(
        "SELECT COUNT(DISTINCT usuario.id)
           FROM usuarios usuario
           JOIN perfis perfil ON perfil.id = usuario.perfil_id
           JOIN usuario_empresas vinculo ON vinculo.usuario_id = usuario.id
          WHERE perfil.codigo NOT IN ('suporte', 'super_admin')
            AND vinculo.empresa_id <> :empresa_id"
    );
    $membershipConflicts->execute(['empresa_id' => $companyId]);
    if ((int) $membershipConflicts->fetchColumn() > 0) {
        throw new TenantBackfillValidationException('Backfill ambíguo: usuários operacionais já possuem vínculo com outra empresa.');
    }

    foreach ($allTables as $table) {
        $conflicts = $connection->prepare(
            "SELECT COUNT(*) FROM `$table` WHERE empresa_id IS NOT NULL AND empresa_id <> :empresa_id"
        );
        $conflicts->execute(['empresa_id' => $companyId]);
        if ((int) $conflicts->fetchColumn() > 0) {
            throw new TenantBackfillValidationException('Backfill ambíguo: ' . $table . ' já possui vínculo com outra empresa.');
        }
    }

    if ((int) $connection->query('SELECT COUNT(*) FROM configuracoes_empresa')->fetchColumn() > 1) {
        throw new TenantBackfillValidationException('Backfill ambíguo: existe mais de uma configuração legada de empresa.');
    }

    foreach ($relationships as [$child, $foreignKey, $parent]) {
        $orphans = (int) $connection->query(
            "SELECT COUNT(*) FROM `$child` filho
              LEFT JOIN `$parent` pai ON pai.id = filho.`$foreignKey`
             WHERE filho.`$foreignKey` IS NOT NULL AND pai.id IS NULL"
        )->fetchColumn();
        if ($orphans > 0) {
            throw new TenantBackfillValidationException('Backfill abortado: ' . $child . ' possui vínculo órfão com ' . $parent . '.');
        }
    }

    $counts = [];
    foreach ($rootTables as $table) {
        $update = $connection->prepare("UPDATE `$table` SET empresa_id = :empresa_id WHERE empresa_id IS NULL");
        $update->execute(['empresa_id' => $companyId]);
        $counts[$table] = $update->rowCount();
    }

    foreach ($relationships as [$child, $foreignKey, $parent]) {
        $update = $connection->prepare(
            "UPDATE `$child` filho
               JOIN `$parent` pai ON pai.id = filho.`$foreignKey`
                SET filho.empresa_id = pai.empresa_id
              WHERE filho.empresa_id IS NULL AND pai.empresa_id IS NOT NULL"
        );
        $update->execute();
        $counts[$child] = $update->rowCount();
    }

    $insertMemberships = $connection->prepare(
        "INSERT INTO usuario_empresas (empresa_id, usuario_id, perfil_id, status, principal)
         SELECT :empresa_id_insert, usuario.id, usuario.perfil_id,
                CASE usuario.status
                    WHEN 'ativo' THEN 'ativo'
                    WHEN 'bloqueado' THEN 'bloqueado'
                    ELSE 'inativo'
                END,
                1
           FROM usuarios usuario
           JOIN perfis perfil ON perfil.id = usuario.perfil_id
          WHERE perfil.codigo NOT IN ('suporte', 'super_admin')
            AND NOT EXISTS (
                SELECT 1 FROM usuario_empresas vinculo
                 WHERE vinculo.empresa_id = :empresa_id_lookup AND vinculo.usuario_id = usuario.id
            )"
    );
    $insertMemberships->execute([
        'empresa_id_insert' => $companyId,
        'empresa_id_lookup' => $companyId,
    ]);
    $counts['usuario_empresas'] = $insertMemberships->rowCount();

    $normalizeMemberships = $connection->prepare(
        "UPDATE usuario_empresas vinculo
           JOIN usuarios usuario ON usuario.id = vinculo.usuario_id
           JOIN perfis perfil ON perfil.id = usuario.perfil_id
            SET vinculo.perfil_id = usuario.perfil_id,
                vinculo.status = CASE usuario.status
                    WHEN 'ativo' THEN 'ativo'
                    WHEN 'bloqueado' THEN 'bloqueado'
                    ELSE 'inativo'
                END,
                vinculo.principal = 1
          WHERE vinculo.empresa_id = :empresa_id
            AND perfil.codigo NOT IN ('suporte', 'super_admin')"
    );
    $normalizeMemberships->execute(['empresa_id' => $companyId]);

    foreach ($allTables as $table) {
        $remainingStatement = $connection->prepare(
            "SELECT COUNT(*) FROM `$table` WHERE empresa_id IS NULL OR empresa_id <> :empresa_id"
        );
        $remainingStatement->execute(['empresa_id' => $companyId]);
        $remaining = (int) $remainingStatement->fetchColumn();
        if ($remaining > 0) {
            throw new TenantBackfillValidationException('Backfill incompleto ou ambíguo em ' . $table . '.');
        }
    }

    foreach ($relationships as [$child, $foreignKey, $parent]) {
        $crossTenant = (int) $connection->query(
            "SELECT COUNT(*) FROM `$child` filho
               JOIN `$parent` pai ON pai.id = filho.`$foreignKey`
              WHERE filho.empresa_id <> pai.empresa_id"
        )->fetchColumn();
        if ($crossTenant > 0) {
            throw new TenantBackfillValidationException('Backfill abortado por vínculo cruzado entre ' . $child . ' e ' . $parent . '.');
        }
    }

    ksort($counts);
    $audit = $connection->prepare(
        "INSERT INTO empresa_auditoria_operacional (empresa_id, acao, entidade_tipo, entidade_id, detalhes)
         VALUES (:empresa_id, 'tenant_backfill', 'empresa', :entidade_id, :detalhes)"
    );
    $audit->execute([
        'empresa_id' => $companyId,
        'entidade_id' => $companyId,
        'detalhes' => json_encode(['tabelas' => $counts, 'total' => array_sum($counts)], JSON_THROW_ON_ERROR),
    ]);
    $connection->commit();

    echo 'Backfill concluído para a empresa #' . $companyId . '.' . PHP_EOL;
    foreach ($counts as $table => $count) {
        echo $table . ': ' . $count . PHP_EOL;
    }
    echo 'Total de registros vinculados: ' . array_sum($counts) . PHP_EOL;
} catch (Throwable $exception) {
    if ($connection instanceof PDO && $connection->inTransaction()) {
        $connection->rollBack();
    }
    if (!$exception instanceof TenantBackfillValidationException) {
        error_log('Tenant operational backfill failed: ' . get_class($exception));
    }
    fwrite(STDERR, $exception instanceof TenantBackfillValidationException
        ? $exception->getMessage() . PHP_EOL
        : 'Não foi possível concluir o backfill. Consulte storage/logs/app.log.' . PHP_EOL);
    exit(1);
} finally {
    if ($lockAcquired && $connection instanceof PDO) {
        try {
            $connection->query("SELECT RELEASE_LOCK('fluxempresa_tenant_backfill')");
        } catch (Throwable) {
        }
    }
}
