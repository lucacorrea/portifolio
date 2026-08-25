<?php

declare(strict_types=1);

namespace App\Nfse\Provider;

use App\Nfse\Contract\NfseProviderInterface;
use App\Nfse\Contract\NfseTransportInterface;
use App\Nfse\DTO\NfseEventResult;
use App\Nfse\DTO\NfseQueryResult;
use App\Nfse\DTO\NfseSubmissionResult;
use DOMDocument;
use DOMElement;
use InvalidArgumentException;

final class BethaFlyNfseProvider implements NfseProviderInterface
{
    public function __construct(private readonly NfseTransportInterface $transport)
    {
    }

    public function submit(string $signedDps): NfseSubmissionResult
    {
        $response = $this->transport->send('RecepcionarDps', $this->envelope($signedDps));
        $dom = $this->xml($response);
        $protocol = $this->value($dom, 'protocolo');
        $providerStatus = $this->value($dom, 'status');
        $code = $this->value($dom, 'codigo');
        $message = $this->value($dom, 'mensagem') ?: $providerStatus;
        $status = $protocol !== '' && stripos($providerStatus, 'rejeitad') === false
            ? 'aguardando_validacao'
            : 'rejeitado_estrutura';
        return new NfseSubmissionResult($status, $protocol ?: null, $code ?: null, $message, $response);
    }

    public function query(array $context): NfseQueryResult
    {
        foreach (['environment','municipality','provider_document','protocol'] as $field) {
            if (trim($context[$field] ?? '') === '') {
                throw new InvalidArgumentException('Contexto de consulta NFS-e incompleto.');
            }
        }
        $body = '<ConsultarStatusDpsEnvio xmlns="http://www.betha.com.br/e-nota-dps">'
            . '<tpAmb>' . ($context['environment'] === 'producao' ? '1' : '2') . '</tpAmb>'
            . '<codigoIbge>' . $this->escape($context['municipality']) . '</codigoIbge>'
            . '<cpfCnpjPrestador>' . $this->escape($this->digits($context['provider_document'])) . '</cpfCnpjPrestador>'
            . '<protocolo>' . $this->escape($context['protocol']) . '</protocolo>'
            . '<tipoIntegracao>EMISSAO</tipoIntegracao></ConsultarStatusDpsEnvio>';
        $response = $this->transport->send('ConsultarStatusDps', $this->envelope($body));
        $dom = $this->xml($response);
        $providerStatus = $this->value($dom, 'statusProcessamento');
        $message = $this->value($dom, 'mensagemErro') ?: $providerStatus;
        $status = match (mb_strtolower($providerStatus)) {
            'processado com sucesso' => 'autorizado',
            'processado com erro' => 'rejeitado_regra',
            default => 'aguardando_validacao',
        };
        return new NfseQueryResult(
            $status, $context['protocol'], $this->nullable($this->value($dom, 'idDps')),
            $this->nullable($this->value($dom, 'chaveAcesso')),
            $this->nullable($this->value($dom, 'numeroNotaFiscal')),
            $this->safePdfUrl($this->value($dom, 'linkPdf')),
            $this->nullable($this->value($dom, 'codigo')), $message, $response
        );
    }

    public function cancel(string $signedEvent): NfseEventResult
    {
        throw new InvalidArgumentException('Cancelamento Betha via Web Service está temporariamente indisponível (E900).');
    }

    public function substitute(string $signedEvent): NfseEventResult
    {
        throw new InvalidArgumentException('Substituição Betha via Web Service está temporariamente indisponível (E901).');
    }

    private function envelope(string $body): string
    {
        return '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/">'
            . '<soapenv:Header/><soapenv:Body>' . $body . '</soapenv:Body></soapenv:Envelope>';
    }

    private function xml(string $xml): DOMDocument
    {
        $dom = new DOMDocument();
        if ($xml === '' || !@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException('O provedor NFS-e retornou XML inválido.');
        }
        return $dom;
    }

    private function value(DOMDocument $dom, string $name): string
    {
        $node = $dom->getElementsByTagName($name)->item(0);
        return $node instanceof DOMElement ? trim((string) $node->nodeValue) : '';
    }

    private function nullable(string $value): ?string { return $value === '' ? null : $value; }
    private function digits(string $value): string { return preg_replace('/\D+/', '', $value) ?? ''; }
    private function escape(string $value): string { return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8'); }
    private function safePdfUrl(string $url): ?string
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false || parse_url($url, PHP_URL_SCHEME) !== 'https') {
            return null;
        }
        return $url;
    }
}
