<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) return;
    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) require $file;
});

use App\Fiscal\Service\SefazResponseParser;

function fiscalResponseAssert(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$parser = new SefazResponseParser();
$authorized = $parser->authorization(
    '<retEnviNFe><cStat>104</cStat><xMotivo>Lote processado</xMotivo><protNFe><infProt>'
    . '<cStat>100</cStat><xMotivo>Autorizado o uso da NF-e</xMotivo><nProt>113260000000001</nProt>'
    . '</infProt></protNFe></retEnviNFe>'
);
fiscalResponseAssert($authorized['authorized'], 'cStat interno 100 deve prevalecer sobre o retorno do lote.');
fiscalResponseAssert($authorized['protocol'] === '113260000000001', 'Protocolo autorizado deve ser preservado.');

$authorizationWithoutProtocol = $parser->authorization(
    '<retEnviNFe><cStat>104</cStat><xMotivo>Lote processado</xMotivo><protNFe><infProt>'
    . '<cStat>100</cStat><xMotivo>Autorizado sem protocolo</xMotivo>'
    . '</infProt></protNFe></retEnviNFe>'
);
fiscalResponseAssert(
    !$authorizationWithoutProtocol['authorized'] && $authorizationWithoutProtocol['pending'],
    'Autorização sem protocolo válido deve permanecer inconclusiva.'
);

$pending = $parser->authorization(
    '<retEnviNFe><tpAmb>2</tpAmb><cStat>103</cStat><xMotivo>Lote recebido</xMotivo><infRec>'
    . '<nRec>130000000000001</nRec></infRec></retEnviNFe>'
);
fiscalResponseAssert($pending['pending'] && $pending['receipt'] === '130000000000001', 'Lote 103 deve aguardar recibo.');

$duplicate = $parser->authorization(
    '<retEnviNFe><cStat>204</cStat><xMotivo>Duplicidade de NF-e</xMotivo></retEnviNFe>'
);
fiscalResponseAssert($duplicate['duplicate'] && $duplicate['pending'], 'Duplicidade deve consultar a chave antes de ser terminal.');

$cancelled = $parser->cancellation(
    '<retEnvEvento><retEvento><infEvento><tpEvento>110111</tpEvento><cStat>135</cStat><xMotivo>Evento registrado e vinculado</xMotivo>'
    . '<nProt>113260000000002</nProt></infEvento></retEvento></retEnvEvento>'
);
fiscalResponseAssert($cancelled['terminal'], 'Cancelamento 135 com protocolo deve ser terminal.');
$cancellationWithoutProtocol = $parser->cancellation(
    '<retEnvEvento><retEvento><infEvento><tpEvento>110111</tpEvento><cStat>135</cStat>'
    . '<xMotivo>Evento sem protocolo válido</xMotivo><nProt>x</nProt></infEvento></retEvento></retEnvEvento>'
);
fiscalResponseAssert(
    !$cancellationWithoutProtocol['terminal'] && $cancellationWithoutProtocol['pending'],
    'Cancelamento 135 sem protocolo válido deve permanecer inconclusivo.'
);
$unlinked = $parser->cancellation(
    '<retEnvEvento><retEvento><infEvento><tpEvento>110110</tpEvento><cStat>135</cStat><xMotivo>CC-e registrada</xMotivo>'
    . '<nProt>113260000000099</nProt></infEvento></retEvento>'
    . '<retEvento><infEvento><tpEvento>110111</tpEvento><cStat>136</cStat><xMotivo>Evento registrado sem vinculo</xMotivo>'
    . '<nProt>113260000000003</nProt></infEvento></retEvento></retEnvEvento>'
);
fiscalResponseAssert(!$unlinked['terminal'] && $unlinked['pending'], 'cStat 136 não pode marcar documento cancelado.');

foreach (['106', '108', '109', '217'] as $technicalCode) {
    $technical = $parser->authorization(
        '<retConsReciNFe><cStat>' . $technicalCode . '</cStat><xMotivo>Resposta técnica inconclusiva</xMotivo></retConsReciNFe>'
    );
    fiscalResponseAssert($technical['pending'], 'cStat técnico ' . $technicalCode . ' deve permanecer em reconsulta.');
}
$incompleteBatch = $parser->authorization(
    '<retConsReciNFe><cStat>104</cStat><xMotivo>Lote processado sem protocolo</xMotivo></retConsReciNFe>'
);
fiscalResponseAssert($incompleteBatch['pending'], 'Lote 104 sem protocolo deve permanecer inconclusivo.');

$duplicateCancellation = $parser->cancellation(
    '<retEnvEvento><retEvento><infEvento><tpEvento>110111</tpEvento><cStat>573</cStat>'
    . '<xMotivo>Duplicidade de evento</xMotivo></infEvento></retEvento></retEnvEvento>'
);
fiscalResponseAssert($duplicateCancellation['pending'], 'Duplicidade de cancelamento deve exigir confirmação por consulta.');

$inutilized = $parser->inutilization(
    '<retInutNFe><infInut><cStat>102</cStat><xMotivo>Inutilização homologada</xMotivo>'
    . '<nProt>113260000000004</nProt></infInut></retInutNFe>'
);
fiscalResponseAssert($inutilized['terminal'], 'Inutilização 102 com protocolo válido deve ser terminal.');
$inutilizationWithoutProtocol = $parser->inutilization(
    '<retInutNFe><infInut><cStat>102</cStat><xMotivo>Inutilização sem protocolo</xMotivo>'
    . '</infInut></retInutNFe>'
);
fiscalResponseAssert(
    !$inutilizationWithoutProtocol['terminal'] && $inutilizationWithoutProtocol['pending'],
    'Inutilização 102 sem protocolo deve permanecer pendente e bloquear reenvio.'
);
$rejectedInutilization = $parser->inutilization(
    '<retInutNFe><infInut><cStat>256</cStat><xMotivo>Uma inutilização da faixa já existe</xMotivo>'
    . '</infInut></retInutNFe>'
);
fiscalResponseAssert(
    !$rejectedInutilization['terminal'] && !$rejectedInutilization['pending'],
    'Rejeição conclusiva de inutilização não deve ser autorizada nem pendente.'
);

$invalidInutilization = false;
try {
    $parser->inutilization('<retInutNFe><xMotivo>Sem infInut</xMotivo></retInutNFe>');
} catch (InvalidArgumentException) {
    $invalidInutilization = true;
}
fiscalResponseAssert($invalidInutilization, 'Resposta inválida de inutilização deve falhar fechada.');

$invalid = false;
try {
    $parser->authorization('<retEnviNFe><xMotivo>Sem status</xMotivo></retEnviNFe>');
} catch (InvalidArgumentException) {
    $invalid = true;
}
fiscalResponseAssert($invalid, 'Resposta sem cStat deve ser inconclusiva.');

echo "FiscalSefazResponseParserTest: OK\n";
