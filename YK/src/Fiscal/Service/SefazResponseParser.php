<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use DOMDocument;
use DOMElement;
use InvalidArgumentException;

final class SefazResponseParser
{
    /** @return array{authorized:bool,pending:bool,duplicate:bool,cstat:string,reason:string,protocol:string,receipt:?string} */
    public function authorization(string $xml): array
    {
        $dom = $this->load($xml, 'A SEFAZ retornou XML de autorização inválido.');
        $protocolNode = $dom->getElementsByTagName('infProt')->item(0);
        $protocolCode = $protocolNode instanceof DOMElement ? $this->child($protocolNode, 'cStat') : '';
        [$outerCode, $outerReason] = $this->outerStatus($dom);
        $code = $protocolCode !== '' ? $protocolCode : $outerCode;
        if (preg_match('/^\d{3}$/', $code) !== 1) {
            throw new InvalidArgumentException('A resposta da SEFAZ não contém cStat válido.');
        }
        $reason = $protocolNode instanceof DOMElement ? $this->child($protocolNode, 'xMotivo') : $outerReason;
        $receipt = trim((string) ($dom->getElementsByTagName('nRec')->item(0)?->nodeValue ?? ''));
        $protocol = $protocolNode instanceof DOMElement ? $this->child($protocolNode, 'nProt') : '';
        $validAuthorizationProtocol = preg_match('/^\d{15}$/', $protocol) === 1;
        $duplicate = in_array($code, ['204', '539'], true);
        $pending = in_array($outerCode, ['103', '105', '106', '108', '109', '217'], true)
            || ($outerCode === '104' && $protocolCode === '')
            || (in_array($protocolCode, ['100', '150'], true) && !$validAuthorizationProtocol)
            || $duplicate;
        return [
            'authorized' => in_array($protocolCode, ['100', '150'], true) && $validAuthorizationProtocol,
            'pending' => $pending,
            'duplicate' => $duplicate,
            'cstat' => $code,
            'reason' => $this->safeReason($reason),
            'protocol' => $protocol,
            'receipt' => $receipt === '' ? null : $receipt,
        ];
    }

    /** @return array{terminal:bool,pending:bool,cstat:string,reason:string,protocol:string} */
    public function inutilization(string $xml): array
    {
        $dom = $this->load($xml, 'A SEFAZ retornou XML de inutilização inválido.');
        $node = $dom->getElementsByTagName('infInut')->item(0);
        if (!$node instanceof DOMElement) {
            throw new InvalidArgumentException('A SEFAZ retornou inutilização sem resultado fiscal.');
        }
        $code = $this->child($node, 'cStat');
        if (preg_match('/^\d{3}$/', $code) !== 1) {
            throw new InvalidArgumentException('A resposta de inutilização não contém cStat válido.');
        }
        $protocol = $this->child($node, 'nProt');
        $validProtocol = preg_match('/^\d{15}$/', $protocol) === 1;
        return [
            'terminal' => $code === '102' && $validProtocol,
            'pending' => $code === '102' && !$validProtocol,
            'cstat' => $code,
            'reason' => $this->safeReason($this->child($node, 'xMotivo')),
            'protocol' => $protocol,
        ];
    }

    /** @return array{terminal:bool,pending:bool,cstat:string,reason:string,protocol:string} */
    public function cancellation(string $xml): array
    {
        $dom = $this->load($xml, 'A SEFAZ retornou XML de cancelamento inválido.');
        foreach ($dom->getElementsByTagName('infEvento') as $node) {
            if (!$node instanceof DOMElement) {
                continue;
            }
            if ($this->child($node, 'tpEvento') !== '110111') {
                continue;
            }
            $code = $this->child($node, 'cStat');
            if ($code === '') {
                continue;
            }
            $protocol = $this->child($node, 'nProt');
            $terminalCode = in_array($code, ['135', '155'], true);
            $validProtocol = preg_match('/^\d{15}$/', $protocol) === 1;
            $terminal = $terminalCode && $validProtocol;
            return [
                'terminal' => $terminal,
                'pending' => in_array($code, ['136', '573'], true) || ($terminalCode && !$validProtocol),
                'cstat' => $code,
                'reason' => $this->safeReason($this->child($node, 'xMotivo')),
                'protocol' => $protocol,
            ];
        }
        throw new InvalidArgumentException('A SEFAZ retornou cancelamento sem protocolo de evento.');
    }

    private function load(string $xml, string $message): DOMDocument
    {
        $dom = new DOMDocument();
        if ($xml === '' || !@$dom->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS)) {
            throw new InvalidArgumentException($message);
        }
        return $dom;
    }

    /** @return array{0:string,1:string} */
    private function outerStatus(DOMDocument $dom): array
    {
        foreach ($dom->getElementsByTagName('cStat') as $node) {
            if (($node->parentNode?->localName ?? '') === 'infProt') {
                continue;
            }
            $parent = $node->parentNode;
            return [
                trim((string) $node->nodeValue),
                $parent instanceof DOMElement ? $this->child($parent, 'xMotivo') : '',
            ];
        }
        return ['', ''];
    }

    private function safeReason(string $reason): string
    {
        $reason = preg_replace('/[\x00-\x1F\x7F]+/u', ' ', strip_tags($reason)) ?? '';
        $reason = substr(trim($reason), 0, 255);
        return $reason === '' ? 'Resposta fiscal sem motivo informado.' : $reason;
    }

    private function child(DOMElement $element, string $name): string
    {
        return trim((string) ($element->getElementsByTagName($name)->item(0)?->nodeValue ?? ''));
    }
}
