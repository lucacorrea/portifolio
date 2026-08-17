<?php

declare(strict_types=1);

namespace App\Nfse\Transport;

use App\Nfse\Contract\NfseTransportInterface;
use InvalidArgumentException;
use RuntimeException;

final class CurlMtlsTransport implements NfseTransportInterface
{
    private const MAX_RESPONSE_BYTES = 5_242_880;

    public function __construct(
        private readonly string $baseUrl,
        private readonly string $pkcs12Path,
        private string $pkcs12Password,
        private readonly int $timeoutSeconds = 60
    ) {
        if (!str_starts_with($baseUrl, 'https://') || !is_file($pkcs12Path)) {
            throw new InvalidArgumentException('Configuração mTLS NFS-e inválida.');
        }
    }

    public function send(string $operation, string $soapXml): string
    {
        if (!function_exists('curl_init')) {
            throw new RuntimeException('A extensão cURL é obrigatória para transmissão NFS-e com mTLS.');
        }
        if (strlen($soapXml) > self::MAX_RESPONSE_BYTES || !in_array($operation, [
            'RecepcionarDps','ConsultarStatusDps','RecepcionarEventoCancelamento','RecepcionarEventoSubstituicao',
        ], true)) {
            throw new InvalidArgumentException('Requisição NFS-e inválida.');
        }
        $handle = curl_init(rtrim($this->baseUrl, '/') . '/ws');
        if ($handle === false) throw new RuntimeException('Não foi possível iniciar o transporte NFS-e.');
        $response = '';
        try {
            curl_setopt_array($handle, [
                CURLOPT_POST=>true, CURLOPT_POSTFIELDS=>$soapXml, CURLOPT_RETURNTRANSFER=>false,
                CURLOPT_CONNECTTIMEOUT=>15, CURLOPT_TIMEOUT=>$this->timeoutSeconds,
                CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2,
                CURLOPT_SSLCERT=>$this->pkcs12Path, CURLOPT_SSLCERTTYPE=>'P12',
                CURLOPT_SSLCERTPASSWD=>$this->pkcs12Password,
                CURLOPT_HTTPHEADER=>[
                    'Content-Type: text/xml; charset=UTF-8',
                    'SOAPAction: "' . $operation . '"',
                    'Expect:',
                ],
                CURLOPT_WRITEFUNCTION=>static function ($curl, string $chunk) use (&$response): int {
                    if (strlen($response) + strlen($chunk) > self::MAX_RESPONSE_BYTES) return 0;
                    $response .= $chunk;
                    return strlen($chunk);
                },
            ]);
            $ok = curl_exec($handle);
            $httpCode = (int)curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            if ($ok === false || $httpCode < 200 || $httpCode >= 300) {
                throw new RuntimeException('A comunicação NFS-e ficou inconclusiva; consulte o protocolo antes de reenviar.');
            }
            return $response;
        } finally {
            curl_close($handle);
        }
    }

    public function __destruct()
    {
        if ($this->pkcs12Password !== '' && function_exists('sodium_memzero')) sodium_memzero($this->pkcs12Password);
    }
}
