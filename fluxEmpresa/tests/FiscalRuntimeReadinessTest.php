<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Fiscal/Service/FiscalRuntimeReadiness.php';
require dirname(__DIR__) . '/src/Fiscal/Service/FiscalSefazConnectionService.php';
$vendorAutoload = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($vendorAutoload)) require $vendorAutoload;

use App\Fiscal\Service\FiscalRuntimeReadiness;
use App\Fiscal\Service\FiscalSefazConnectionService;

function fiscalRuntimeAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$required = [
    'openssl' => true, 'curl' => true, 'dom' => true,
    'simplexml' => true, 'soap' => true, 'mbstring' => true,
    'zlib' => true, 'nfephp' => true,
];
$ready = (new FiscalRuntimeReadiness($required, true, false, true))->inspect();
fiscalRuntimeAssert($ready['homologation_ready'], 'Todos os requisitos devem liberar a homologação.');
fiscalRuntimeAssert(!$ready['production_allowed'], 'Produção deve continuar bloqueada sem a chave de ambiente.');

$blocked = (new FiscalRuntimeReadiness($required, true, true, false))->inspect();
fiscalRuntimeAssert(!$blocked['homologation_ready'], 'Uma chave mestra inválida deve bloquear a integração.');
fiscalRuntimeAssert(!$blocked['production_allowed'], 'Produção não pode ignorar requisito ausente.');

$missingLibrary = $required;
$missingLibrary['nfephp'] = false;
$blocked = (new FiscalRuntimeReadiness($missingLibrary, true, true, true))->inspect();
fiscalRuntimeAssert(!$blocked['homologation_ready'], 'A biblioteca fiscal ausente deve bloquear a comunicação.');

$missingMbstring = $required;
$missingMbstring['mbstring'] = false;
$blocked = (new FiscalRuntimeReadiness($missingMbstring, true, true, true))->inspect();
fiscalRuntimeAssert(!$blocked['homologation_ready'], 'Mbstring ausente deve bloquear a comunicação fiscal.');

$connectionReflection = new ReflectionClass(FiscalSefazConnectionService::class);
$connection = $connectionReflection->newInstanceWithoutConstructor();
$parseStatus = $connectionReflection->getMethod('parseStatus');
$status = $parseStatus->invoke(
    $connection,
    '<retConsStatServ><tpAmb>2</tpAmb><verAplic>AM4.00</verAplic><cStat>107</cStat><xMotivo>Servico em Operacao</xMotivo><dhRecbto>2026-07-22T16:00:00-04:00</dhRecbto></retConsStatServ>'
);
fiscalRuntimeAssert($status['code'] === '107', 'A resposta operacional da SEFAZ deve ser reconhecida.');
fiscalRuntimeAssert($status['message'] === 'Servico em Operacao', 'O motivo da SEFAZ deve ser preservado de forma segura.');

$configJson = $connectionReflection->getMethod('configJson')->invoke($connection, [
    'razao_social' => 'Empresa de Homologacao',
    'titular_cnpj' => '04252011000110',
    'uf' => 'AM',
    'schema_versao' => '4.00',
    'csc_id' => '1',
], 'CSC-HOMOLOGACAO');
$nfephpConfig = NFePHP\NFe\Common\Config::validate($configJson);
fiscalRuntimeAssert($nfephpConfig->tpAmb === 2, 'O adaptador NFePHP deve permanecer em homologação.');
fiscalRuntimeAssert($nfephpConfig->siglaUF === 'AM', 'O adaptador NFePHP deve receber a UF configurada.');

echo "Fiscal runtime readiness tests passed.\n";
