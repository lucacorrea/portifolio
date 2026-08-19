<?php

declare(strict_types=1);

namespace App\Fiscal\Service;

use App\Fiscal\Print\ReadableDanfce;
use App\Fiscal\Repository\FiscalDocumentRepository;
use App\Fiscal\Storage\FiscalDocumentStorage;
use DOMDocument;
use DOMXPath;
use InvalidArgumentException;
use NFePHP\DA\NFe\Danfe;
use NFePHP\DA\NFe\Danfce;
use RuntimeException;

final class FiscalDocumentPrintService
{
    public function __construct(
        private readonly FiscalDocumentRepository $documents,
        private readonly FiscalDocumentStorage $storage
    ) {
    }

    /**
     * @return array{
     *     pdf:string,
     *     filename:string,
     *     model:string
     * }
     */
    public function renderAuthorized(
        int $documentId
    ): array {
        if ($documentId <= 0) {
            throw new InvalidArgumentException(
                'Documento fiscal inválido.'
            );
        }

        $document =
            $this->documents
                ->getById(
                    $documentId
                );

        $xml =
            $this->authorizedXmlFromDocument(
                $document
            );

        $model =
            (string) (
                $document['modelo']
                ?? ''
            );

        if ($model === '55') {
            if (!class_exists(Danfe::class)) {
                throw new RuntimeException(
                    'O gerador oficial de DANFE não está instalado no servidor.'
                );
            }

            $renderer =
                new Danfe(
                    $xml
                );

            /*
             * NF-e modelo 55 permanece em A4.
             */
            $renderer->printParameters(
                'P',
                'A4',
                2,
                2
            );
        } elseif ($model === '65') {
            if (!class_exists(Danfce::class)) {
                throw new RuntimeException(
                    'O gerador oficial de DANFCE não está instalado no servidor.'
                );
            }

            /*
             * Renderizador próprio:
             *
             * - fontes maiores;
             * - Arial;
             * - QR Code menor;
             * - sem alteração na pasta vendor.
             */
            $renderer =
                new ReadableDanfce(
                    $xml
                );
        } else {
            throw new InvalidArgumentException(
                'Modelo fiscal não suportado para impressão.'
            );
        }

        $pdf =
            $renderer->render();

        if (
            !is_string($pdf)
            || !str_starts_with(
                $pdf,
                '%PDF-'
            )
        ) {
            throw new RuntimeException(
                'O documento auxiliar fiscal não pôde ser renderizado.'
            );
        }

        $series =
            str_pad(
                (string) (
                    $document['serie']
                    ?? ''
                ),
                3,
                '0',
                STR_PAD_LEFT
            );

        $number =
            (int) (
                $document['numero']
                ?? 0
            );

        return [
            'pdf' =>
                $pdf,

            'filename' =>
                sprintf(
                    '%s-%s-%09d.pdf',
                    $model === '55'
                        ? 'danfe'
                        : 'danfce',
                    $series,
                    $number
                ),

            'model' =>
                $model,
        ];
    }

    /**
     * @return array{
     *     xml:string,
     *     filename:string
     * }
     */
    public function authorizedXml(
        int $documentId
    ): array {
        if ($documentId <= 0) {
            throw new InvalidArgumentException(
                'Documento fiscal inválido.'
            );
        }

        $document =
            $this->documents
                ->getById(
                    $documentId
                );

        $xml =
            $this->authorizedXmlFromDocument(
                $document
            );

        return [
            'xml' =>
                $xml,

            'filename' =>
                'nfe-'
                . (string) (
                    $document['chave']
                    ?? ''
                )
                . '-procNFe.xml',
        ];
    }

    /**
     * @param array<string,mixed> $document
     */
    private function authorizedXmlFromDocument(
        array $document
    ): string {
        if (
            ($document['processamento_status'] ?? '')
            !== 'autorizado'
        ) {
            throw new InvalidArgumentException(
                'Somente XML autorizado pela SEFAZ pode gerar DANFE ou DANFCE válido.'
            );
        }

        $reference =
            trim(
                (string) (
                    $document['xml_autorizado_path']
                    ?? ''
                )
            );

        $hash =
            trim(
                (string) (
                    $document['xml_autorizado_sha256']
                    ?? ''
                )
            );

        if (
            $reference === ''
            || $hash === ''
        ) {
            throw new RuntimeException(
                'O XML autorizado não está disponível para impressão.'
            );
        }

        $xml =
            $this->storage
                ->read(
                    $reference,
                    $hash
                );

        $protocol =
            $this->protocolData(
                $xml
            );

        /*
         * 100 = autorizado
         * 120 = autorizado com alerta
         * 150 = autorizado fora do prazo
         */
        if (
            !in_array(
                $protocol['status'],
                ['100', '120', '150'],
                true
            )
            || !hash_equals(
                (string) (
                    $document['chave']
                    ?? ''
                ),
                $protocol['key']
            )
            || !hash_equals(
                (string) (
                    $document['protocolo']
                    ?? ''
                ),
                $protocol['protocol']
            )
        ) {
            throw new RuntimeException(
                'O protocolo do XML não corresponde ao documento autorizado.'
            );
        }

        return $xml;
    }

    /**
     * @return array{
     *     status:string,
     *     key:string,
     *     protocol:string
     * }
     */
    private function protocolData(
        string $xml
    ): array {
        $dom =
            new DOMDocument();

        $previous =
            libxml_use_internal_errors(
                true
            );

        try {
            $loaded =
                $dom->loadXML(
                    $xml,
                    LIBXML_NONET
                    | LIBXML_NOBLANKS
                );
        } finally {
            libxml_clear_errors();

            libxml_use_internal_errors(
                $previous
            );
        }

        if (!$loaded) {
            throw new RuntimeException(
                'XML fiscal autorizado inválido.'
            );
        }

        $xpath =
            new DOMXPath(
                $dom
            );

        $xpath->registerNamespace(
            'nfe',
            'http://www.portalfiscal.inf.br/nfe'
        );

        $status =
            trim(
                (string) $xpath->evaluate(
                    'string(//nfe:protNFe/nfe:infProt/nfe:cStat)'
                )
            );

        $key =
            trim(
                (string) $xpath->evaluate(
                    'string(//nfe:protNFe/nfe:infProt/nfe:chNFe)'
                )
            );

        $protocol =
            trim(
                (string) $xpath->evaluate(
                    'string(//nfe:protNFe/nfe:infProt/nfe:nProt)'
                )
            );

        if (
            preg_match(
                '/^\d{3,4}$/',
                $status
            ) !== 1
            || preg_match(
                '/^\d{44}$/',
                $key
            ) !== 1
            || preg_match(
                '/^\d{15}$/',
                $protocol
            ) !== 1
        ) {
            throw new RuntimeException(
                'XML sem protocolo fiscal completo.'
            );
        }

        return [
            'status' =>
                $status,

            'key' =>
                $key,

            'protocol' =>
                $protocol,
        ];
    }
}
