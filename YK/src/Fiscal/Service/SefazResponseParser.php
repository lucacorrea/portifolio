<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use DOMDocument;
use DOMElement;
use DOMNode;
use InvalidArgumentException;

final class SefazResponseParser
{
    private const AUTHORIZED_PROTOCOL_CODES = ['100', '120', '150'];
    private const DUPLICATE_CODES = ['204', '539'];
    private const AUTHORIZATION_PENDING_OUTER_CODES = ['103', '105', '106', '108', '109', '217'];
    private const AUTHORIZATION_ROOTS = ['retEnviNFe', 'retConsReciNFe', 'retConsSitNFe'];
    private const INUTILIZATION_ROOTS = ['retInutNFe'];
    private const CANCELLATION_ROOTS = ['retEnvEvento', 'retEvento'];

    /**
     * @return array{
     *     authorized:bool,
     *     pending:bool,
     *     duplicate:bool,
     *     cstat:string,
     *     reason:string,
     *     protocol:string,
     *     receipt:?string,
     *     fiscal_xml:string
     * }
     */
    public function authorization(string $xml): array
    {
        $dom = $this->loadFiscalResponse(
            $xml,
            self::AUTHORIZATION_ROOTS,
            'A SEFAZ retornou XML de autorização inválido.'
        );

        $protocolNode = $this->firstElement($dom, 'infProt');
        $protocolCode = $protocolNode instanceof DOMElement
            ? $this->directChildValue($protocolNode, 'cStat')
            : '';

        [$outerCode, $outerReason] = $this->authorizationOuterStatus($dom);
        $code = $protocolCode !== '' ? $protocolCode : $outerCode;

        if (preg_match('/^\d{3}$/', $code) !== 1) {
            throw new InvalidArgumentException(
                'A resposta da SEFAZ não contém cStat fiscal válido.'
            );
        }

        $protocolReason = $protocolNode instanceof DOMElement
            ? $this->directChildValue($protocolNode, 'xMotivo')
            : '';

        $reason = $protocolReason !== '' ? $protocolReason : $outerReason;

        $protocol = $protocolNode instanceof DOMElement
            ? $this->directChildValue($protocolNode, 'nProt')
            : '';

        $validAuthorizationProtocol = preg_match('/^\d{15}$/', $protocol) === 1;

        $receiptRaw = $this->firstValue($dom, 'nRec');
        $receipt = preg_match('/^\d{15}$/', $receiptRaw) === 1
            ? $receiptRaw
            : null;

        $authorized = in_array($protocolCode, self::AUTHORIZED_PROTOCOL_CODES, true)
            && $validAuthorizationProtocol;

        $duplicate = in_array($code, self::DUPLICATE_CODES, true);

        $pending = in_array($outerCode, self::AUTHORIZATION_PENDING_OUTER_CODES, true)
            || ($outerCode === '104' && $protocolCode === '')
            || (in_array($code, self::AUTHORIZED_PROTOCOL_CODES, true) && !$validAuthorizationProtocol)
            || $duplicate;

        return [
            'authorized' => $authorized,
            'pending' => $pending,
            'duplicate' => $duplicate,
            'cstat' => $code,
            'reason' => $this->safeReason($reason),
            'protocol' => $protocol,
            'receipt' => $receipt,
            'fiscal_xml' => $this->saveXml($dom),
        ];
    }

    /** @return array{terminal:bool,pending:bool,cstat:string,reason:string,protocol:string} */
    public function inutilization(string $xml): array
    {
        $dom = $this->loadFiscalResponse(
            $xml,
            self::INUTILIZATION_ROOTS,
            'A SEFAZ retornou XML de inutilização inválido.'
        );

        $node = $this->firstElement($dom, 'infInut');
        if (!$node instanceof DOMElement) {
            throw new InvalidArgumentException(
                'A SEFAZ retornou inutilização sem resultado fiscal.'
            );
        }

        $code = $this->directChildValue($node, 'cStat');
        if (preg_match('/^\d{3}$/', $code) !== 1) {
            throw new InvalidArgumentException(
                'A resposta de inutilização não contém cStat válido.'
            );
        }

        $protocol = $this->directChildValue($node, 'nProt');
        $validProtocol = preg_match('/^\d{15}$/', $protocol) === 1;

        return [
            'terminal' => $code === '102' && $validProtocol,
            'pending' => $code === '102' && !$validProtocol,
            'cstat' => $code,
            'reason' => $this->safeReason($this->directChildValue($node, 'xMotivo')),
            'protocol' => $protocol,
        ];
    }

    /** @return array{terminal:bool,pending:bool,cstat:string,reason:string,protocol:string} */
    public function cancellation(string $xml): array
    {
        $dom = $this->loadFiscalResponse(
            $xml,
            self::CANCELLATION_ROOTS,
            'A SEFAZ retornou XML de cancelamento inválido.'
        );

        foreach ($this->elements($dom, 'infEvento') as $node) {
            if ($this->directChildValue($node, 'tpEvento') !== '110111') {
                continue;
            }

            $code = $this->directChildValue($node, 'cStat');
            if ($code === '') {
                continue;
            }

            if (preg_match('/^\d{3}$/', $code) !== 1) {
                throw new InvalidArgumentException(
                    'A resposta de cancelamento não contém cStat válido.'
                );
            }

            $protocol = $this->directChildValue($node, 'nProt');
            $terminalCode = in_array($code, ['135', '155'], true);
            $validProtocol = preg_match('/^\d{15}$/', $protocol) === 1;

            return [
                'terminal' => $terminalCode && $validProtocol,
                'pending' => in_array($code, ['136', '573'], true)
                    || ($terminalCode && !$validProtocol),
                'cstat' => $code,
                'reason' => $this->safeReason($this->directChildValue($node, 'xMotivo')),
                'protocol' => $protocol,
            ];
        }

        [$outerCode, $outerReason] = $this->statusFromRoots(
            $dom,
            ['retEnvEvento', 'retEvento']
        );

        if (preg_match('/^\d{3}$/', $outerCode) === 1) {
            return [
                'terminal' => false,
                'pending' => true,
                'cstat' => $outerCode,
                'reason' => $this->safeReason($outerReason),
                'protocol' => '',
            ];
        }

        throw new InvalidArgumentException(
            'A SEFAZ retornou cancelamento sem protocolo de evento.'
        );
    }

    /**
     * Aceita XML fiscal direto, SOAP, CDATA e XML escapado em texto.
     *
     * @param string[] $expectedRoots
     */
    private function loadFiscalResponse(
        string $xml,
        array $expectedRoots,
        string $invalidMessage
    ): DOMDocument {
        $xml = $this->normalizeInput($xml);
        $dom = $this->loadDom($xml, $invalidMessage);

        $this->throwIfSoapFault($dom);

        if ($this->containsExpectedRoot($dom, $expectedRoots)) {
            return $dom;
        }

        $embedded = $this->findEmbeddedFiscalXml($dom, $expectedRoots);
        if ($embedded instanceof DOMDocument) {
            $this->throwIfSoapFault($embedded);
            return $embedded;
        }

        if ($this->firstElement($dom, 'cStat') instanceof DOMElement) {
            return $dom;
        }

        throw new InvalidArgumentException(
            'A resposta XML da SEFAZ não contém um retorno fiscal reconhecido.'
        );
    }

    private function normalizeInput(string $xml): string
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', $xml) ?? $xml;
        $xml = trim($xml);

        if ($xml === '') {
            return '';
        }

        if (!str_starts_with($xml, '<') && str_contains($xml, '&lt;')) {
            $decoded = html_entity_decode(
                $xml,
                ENT_QUOTES | ENT_XML1,
                'UTF-8'
            );

            if (is_string($decoded) && str_starts_with(trim($decoded), '<')) {
                return trim($decoded);
            }
        }

        return $xml;
    }

    private function loadDom(string $xml, string $message): DOMDocument
    {
        if ($xml === '') {
            throw new InvalidArgumentException($message);
        }

        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadXML(
                $xml,
                LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if (!$loaded) {
            throw new InvalidArgumentException($message);
        }

        return $dom;
    }

    /** @param string[] $expectedRoots */
    private function containsExpectedRoot(DOMDocument $dom, array $expectedRoots): bool
    {
        foreach ($expectedRoots as $root) {
            if ($this->firstElement($dom, $root) instanceof DOMElement) {
                return true;
            }
        }

        return false;
    }

    /** @param string[] $expectedRoots */
    private function findEmbeddedFiscalXml(
        DOMDocument $dom,
        array $expectedRoots
    ): ?DOMDocument {
        foreach ($dom->getElementsByTagName('*') as $node) {
            if (!$node instanceof DOMElement || $this->hasElementChild($node)) {
                continue;
            }

            $text = trim((string) $node->textContent);
            if ($text === '' || (!str_contains($text, '<') && !str_contains($text, '&lt;'))) {
                continue;
            }

            $candidate = $text;

            for ($attempt = 0; $attempt < 3; $attempt++) {
                $candidate = trim($candidate);

                if (str_starts_with($candidate, '<')) {
                    $embedded = $this->tryLoadDom($candidate);

                    if ($embedded instanceof DOMDocument
                        && ($this->containsExpectedRoot($embedded, $expectedRoots)
                            || $this->firstElement($embedded, 'cStat') instanceof DOMElement)
                    ) {
                        return $embedded;
                    }
                }

                $decoded = html_entity_decode(
                    $candidate,
                    ENT_QUOTES | ENT_XML1,
                    'UTF-8'
                );

                if (!is_string($decoded) || $decoded === $candidate) {
                    break;
                }

                $candidate = $decoded;
            }
        }

        return null;
    }

    private function tryLoadDom(string $xml): ?DOMDocument
    {
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors(true);

        try {
            $loaded = $dom->loadXML(
                $xml,
                LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_NOCDATA
            );
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        return $loaded ? $dom : null;
    }

    private function hasElementChild(DOMElement $element): bool
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement) {
                return true;
            }
        }

        return false;
    }

    private function throwIfSoapFault(DOMDocument $dom): void
    {
        $fault = $this->firstElement($dom, 'Fault');
        if (!$fault instanceof DOMElement) {
            return;
        }

        $reason = '';

        foreach (['faultstring', 'Text', 'Reason'] as $candidate) {
            $reason = $this->firstValueInside($fault, $candidate);
            if ($reason !== '') {
                break;
            }
        }

        throw new InvalidArgumentException(
            'A SEFAZ retornou SOAP Fault'
            . ($reason !== '' ? ': ' . $this->safeReason($reason) : '.')
        );
    }

    /** @return array{0:string,1:string} */
    private function authorizationOuterStatus(DOMDocument $dom): array
    {
        [$code, $reason] = $this->statusFromRoots($dom, self::AUTHORIZATION_ROOTS);
        if ($code !== '') {
            return [$code, $reason];
        }

        foreach ($this->elements($dom, 'cStat') as $statusNode) {
            if ($this->isInsideAny($statusNode, ['infProt', 'infEvento', 'infInut'])) {
                continue;
            }

            $parent = $statusNode->parentNode;

            return [
                trim((string) $statusNode->nodeValue),
                $parent instanceof DOMElement
                    ? $this->directChildValue($parent, 'xMotivo')
                    : '',
            ];
        }

        return ['', ''];
    }

    /**
     * @param string[] $roots
     * @return array{0:string,1:string}
     */
    private function statusFromRoots(DOMDocument $dom, array $roots): array
    {
        foreach ($roots as $rootName) {
            foreach ($this->elements($dom, $rootName) as $root) {
                $code = $this->directChildValue($root, 'cStat');
                if ($code === '') {
                    continue;
                }

                return [
                    $code,
                    $this->directChildValue($root, 'xMotivo'),
                ];
            }
        }

        return ['', ''];
    }

    /** @return DOMElement[] */
    private function elements(DOMDocument $dom, string $name): array
    {
        $result = [];

        foreach ($dom->getElementsByTagName('*') as $node) {
            if ($node instanceof DOMElement && $node->localName === $name) {
                $result[] = $node;
            }
        }

        return $result;
    }

    private function firstElement(DOMDocument $dom, string $name): ?DOMElement
    {
        foreach ($this->elements($dom, $name) as $element) {
            return $element;
        }

        return null;
    }

    private function firstValue(DOMDocument $dom, string $name): string
    {
        $node = $this->firstElement($dom, $name);

        return $node instanceof DOMElement
            ? trim((string) $node->nodeValue)
            : '';
    }

    private function firstValueInside(DOMElement $element, string $name): string
    {
        foreach ($element->getElementsByTagName('*') as $node) {
            if ($node instanceof DOMElement && $node->localName === $name) {
                return trim((string) $node->nodeValue);
            }
        }

        return '';
    }

    private function directChildValue(DOMElement $element, string $name): string
    {
        foreach ($element->childNodes as $child) {
            if ($child instanceof DOMElement && $child->localName === $name) {
                return trim((string) $child->nodeValue);
            }
        }

        return '';
    }

    /** @param string[] $ancestorNames */
    private function isInsideAny(DOMNode $node, array $ancestorNames): bool
    {
        $parent = $node->parentNode;

        while ($parent !== null) {
            if ($parent instanceof DOMElement
                && in_array($parent->localName, $ancestorNames, true)
            ) {
                return true;
            }

            $parent = $parent->parentNode;
        }

        return false;
    }

    private function saveXml(DOMDocument $dom): string
    {
        $xml = $dom->saveXML();

        if (!is_string($xml) || $xml === '') {
            throw new InvalidArgumentException(
                'Não foi possível normalizar o XML de resposta da SEFAZ.'
            );
        }

        return $xml;
    }

    private function safeReason(string $reason): string
    {
        $reason = preg_replace(
            '/[\x00-\x1F\x7F]+/u',
            ' ',
            strip_tags($reason)
        ) ?? '';

        $reason = substr(trim($reason), 0, 255);

        return $reason === ''
            ? 'Resposta fiscal sem motivo informado.'
            : $reason;
    }
}
