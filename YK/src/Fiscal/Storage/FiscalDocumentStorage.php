<?php

declare(strict_types=1);

namespace App\Fiscal\Storage;

use InvalidArgumentException;
use RuntimeException;

final class FiscalDocumentStorage
{
    public function __construct(private readonly string $storageRoot)
    {
        if (trim($storageRoot) === '' || str_contains($storageRoot, "\0")) {
            throw new InvalidArgumentException('Diretório de documentos fiscais inválido.');
        }
    }

    public static function forProjectRoot(string $projectRoot): self
    {
        return new self(
            FiscalCertificateStorage::resolveStorageRoot($projectRoot)
            . DIRECTORY_SEPARATOR
            . 'documentos'
        );
    }

    /** @return array{reference:string,sha256:string} */
    public function store(
        string $environment,
        string $model,
        int $documentId,
        string $type,
        string $xml
    ): array {
        if (!in_array($environment, ['homologacao', 'producao'], true)
            || !in_array($model, ['55', '65'], true)
            || $documentId <= 0
            || !in_array($type, ['assinado', 'resposta', 'autorizado', 'cancelamento'], true)
        ) {
            throw new InvalidArgumentException('Identificação do artefato fiscal inválida.');
        }
        $this->assertXml($xml);
        $directory = $this->storageRoot
            . DIRECTORY_SEPARATOR . $environment
            . DIRECTORY_SEPARATOR . $model
            . DIRECTORY_SEPARATOR . $documentId;
        $this->ensureDirectory($directory);
        $filename = $type . '-' . bin2hex(random_bytes(8)) . '.xml';
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        $temporary = $directory . DIRECTORY_SEPARATOR . '.tmp-' . bin2hex(random_bytes(12));
        if (file_put_contents($temporary, $xml, LOCK_EX) !== strlen($xml)) {
            @unlink($temporary);
            throw new RuntimeException('Não foi possível armazenar o XML fiscal.');
        }
        @chmod($temporary, 0600);
        if (!rename($temporary, $destination)) {
            @unlink($temporary);
            throw new RuntimeException('Não foi possível concluir o armazenamento do XML fiscal.');
        }
        @chmod($destination, 0600);

        return [
            'reference' => implode('/', [$environment, $model, (string) $documentId, $filename]),
            'sha256' => hash('sha256', $xml),
        ];
    }

    public function read(string $reference, string $expectedSha256): string
    {
        if (preg_match('#^(homologacao|producao)/(55|65)/[1-9]\d*/[a-z]+-[a-f0-9]{16}\.xml$#', $reference) !== 1
            || preg_match('/^[a-f0-9]{64}$/', $expectedSha256) !== 1
        ) {
            throw new InvalidArgumentException('Referência do XML fiscal inválida.');
        }
        $root = realpath($this->storageRoot);
        $candidate = $this->storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $reference);
        $resolved = is_file($candidate) ? realpath($candidate) : false;
        if ($root === false || $resolved === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('XML fiscal não encontrado.');
        }
        $xml = file_get_contents($resolved);
        if (!is_string($xml) || $xml === '' || !hash_equals($expectedSha256, hash('sha256', $xml))) {
            throw new RuntimeException('A integridade do XML fiscal não pôde ser confirmada.');
        }
        $this->assertXml($xml);

        return $xml;
    }

    private function assertXml(string $xml): void
    {
        if ($xml === '' || strlen($xml) > 8 * 1024 * 1024 || str_contains($xml, "\0")) {
            throw new InvalidArgumentException('Conteúdo XML fiscal inválido.');
        }
        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument();
        $loaded = $document->loadXML($xml, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);
        if (!$loaded) {
            throw new InvalidArgumentException('Conteúdo XML fiscal malformado.');
        }
    }

    private function ensureDirectory(string $directory): void
    {
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar o diretório fiscal seguro.');
        }
        @chmod($directory, 0700);
    }
}
