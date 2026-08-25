<?php

declare(strict_types=1);

namespace App\Nfse\Storage;

use InvalidArgumentException;
use RuntimeException;

final class NfseDocumentStorage
{
    public function __construct(private readonly string $root)
    {
    }

    public static function forProjectRoot(string $projectRoot): self
    {
        return new self(dirname(rtrim($projectRoot, '/\\'), 2) . DIRECTORY_SEPARATOR
            . 'configuracoes' . DIRECTORY_SEPARATOR . 'yk' . DIRECTORY_SEPARATOR . 'fiscal'
            . DIRECTORY_SEPARATOR . 'documentos' . DIRECTORY_SEPARATOR . 'nfse');
    }

    /** @return array{reference:string,sha256:string} */
    public function store(string $environment, int $documentId, string $type, string $contents): array
    {
        if (!in_array($environment, ['homologacao','producao'], true) || $documentId <= 0
            || preg_match('/^[a-z_]{3,40}$/', $type) !== 1 || $contents === ''
        ) throw new InvalidArgumentException('Artefato NFS-e inválido.');
        $directory = $this->root . DIRECTORY_SEPARATOR . $environment . DIRECTORY_SEPARATOR . $documentId;
        if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível criar o armazenamento NFS-e.');
        }
        $name = $type . '-' . bin2hex(random_bytes(8)) . '.xml';
        $path = $directory . DIRECTORY_SEPARATOR . $name;
        if (file_put_contents($path, $contents, LOCK_EX) !== strlen($contents)) {
            throw new RuntimeException('Não foi possível persistir o artefato NFS-e.');
        }
        @chmod($path, 0600);
        return [
            'reference'=>'nfse/' . $environment . '/' . $documentId . '/' . $name,
            'sha256'=>hash('sha256', $contents),
        ];
    }
}
