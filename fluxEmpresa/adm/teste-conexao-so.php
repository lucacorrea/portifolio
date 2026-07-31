<?php
declare(strict_types=1);

use App\Integration\SO\SoIntegrationException;

require __DIR__ . '/includes/admin-guard.php';

$driverAvailable = extension_loaded('pdo_mysql');
$explicitPathConfigured = trim((string) getenv('SO_ENV_PATH')) !== '';
$connected = false;
$supplierCount = 0;
$reason = 'unavailable';

try {
    $supplierCount = $application->soSuppliers()->count('');
    $connected = true;
    $reason = 'connected';
} catch (SoIntegrationException $exception) {
    $reason = $exception->reason();
} catch (Throwable $exception) {
    error_log('Unexpected SO diagnostic failure. type=' . $exception::class . ' code=' . $exception->getCode() . '.');
}

$diagnostics = [
    'connected' => ['Conexão e consulta concluídas.', 'A integração está pronta para uso.'],
    'environment_not_found' => ['Arquivo de configuração não localizado.', 'Configure SO_ENV_PATH no ambiente principal do Flux apontando para configuracao/so/conect/.env.'],
    'environment_unreadable' => ['Arquivo de configuração sem permissão de leitura.', 'Revise as permissões do arquivo externo para o processo PHP.'],
    'configuration_incomplete' => ['Configuração do SO incompleta.', 'Confira DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD e DB_CHARSET no arquivo externo.'],
    'configuration_invalid' => ['Configuração do SO inválida.', 'Confira principalmente DB_PORT e DB_CHARSET no arquivo externo.'],
    'database_connection_failed' => ['O arquivo foi lido, mas o MySQL recusou a conexão.', 'Revise usuário, senha, banco, host e permissões do usuário do banco.'],
    'query_failed' => ['O banco conectou, mas a consulta de fornecedores falhou.', 'Confira se a tabela fornecedores possui id, nome, cnpj, contato e telefone.'],
    'unavailable' => ['Falha não identificada na integração.', 'Consulte storage/logs/app.log após repetir o teste.'],
];
[$diagnosticTitle, $diagnosticAction] = $diagnostics[$reason] ?? $diagnostics['unavailable'];

$pageTitle = 'Diagnóstico da conexão SO';
$pageSubtitle = 'Verificação segura da integração somente leitura';
$activePage = 'integrations';
$adminContent = static function () use (
    $driverAvailable,
    $explicitPathConfigured,
    $connected,
    $supplierCount,
    $diagnosticTitle,
    $diagnosticAction
): void {
    ?>
    <section class="admin-panel">
        <h2><i class="bi bi-activity"></i> Resultado do diagnóstico</h2>
        <dl class="admin-details">
            <div><dt>Driver MySQL do PHP</dt><dd><?= admin_h($driverAvailable ? 'Disponível' : 'Indisponível') ?></dd></div>
            <div><dt>Caminho explícito</dt><dd><?= admin_h($explicitPathConfigured ? 'Configurado' : 'Descoberta automática') ?></dd></div>
            <div><dt>Fornecedores acessíveis</dt><dd><?= admin_h($connected ? $supplierCount : 'Não consultado') ?></dd></div>
        </dl>
        <div class="alert alert-<?= $connected ? 'success' : 'danger' ?>">
            <strong><?= admin_h($diagnosticTitle) ?></strong><br>
            <?= admin_h($diagnosticAction) ?>
        </div>
        <div class="admin-actions">
            <a class="btn btn-primary" href="<?= admin_url('teste-conexao-so.php') ?>">Testar novamente</a>
            <a class="btn btn-light" href="<?= admin_url('integracoes.php') ?>">Voltar às integrações</a>
        </div>
    </section>
    <?php
};

require __DIR__ . '/includes/shell.php';
