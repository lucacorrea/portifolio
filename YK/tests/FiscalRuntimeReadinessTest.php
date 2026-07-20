<?php

declare(strict_types=1);

require dirname(__DIR__) . '/src/Fiscal/Service/FiscalRuntimeReadiness.php';

use App\Fiscal\Service\FiscalRuntimeReadiness;

function fiscalRuntimeAssert(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
}

$required = [
    'openssl' => true, 'curl' => true, 'dom' => true,
    'simplexml' => true, 'soap' => true, 'nfephp' => true,
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

echo "Fiscal runtime readiness tests passed.\n";
