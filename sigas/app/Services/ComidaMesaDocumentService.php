<?php

declare(strict_types=1);

namespace App\Services;

use App\Config\StorageConfig;
use App\Core\Storage;
use App\Repositories\ComidaMesaRepository;
use RuntimeException;

final class ComidaMesaDocumentService
{
    /** @var array<string,string> */
    private const MIME_EXTENSIONS = [
        'application/pdf' => 'pdf',
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly ComidaMesaRepository $repository)
    {
    }

    /** @param array<string,mixed> $file @return array<string,mixed> */
    public function store(int $registrationId, string $type, ?string $description, array $file, int $userId, ?int $sectorId, AuditService $audit): array
    {
        if ($this->repository->findRegistrationById($registrationId) === null) {
            throw new RuntimeException('Inscrição não localizada.', 404);
        }

        $type = mb_substr(trim($type), 0, 60);
        $description = $description === null ? null : mb_substr(trim($description), 0, 255);
        if ($type === '') {
            throw new RuntimeException(json_encode(['fields' => ['tipo' => 'Informe o tipo do documento.']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 422);
        }

        $tmp = isset($file['tmp_name']) && is_string($file['tmp_name']) ? $file['tmp_name'] : '';
        $name = isset($file['name']) && is_string($file['name']) ? $file['name'] : 'documento';
        $size = isset($file['size']) ? (int) $file['size'] : 0;
        $error = isset($file['error']) ? (int) $file['error'] : UPLOAD_ERR_NO_FILE;

        $uploaded = PHP_SAPI === 'cli' ? is_file($tmp) : is_uploaded_file($tmp);
        if ($error !== UPLOAD_ERR_OK || !$uploaded) {
            throw new RuntimeException(json_encode(['fields' => ['arquivo' => 'Envie um arquivo válido.']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 422);
        }
        if ($size < 1 || $size > StorageConfig::maxDocumentSize()) {
            throw new RuntimeException(json_encode(['fields' => ['arquivo' => 'Arquivo excede o tamanho permitido.']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 422);
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmp) ?: '';
        if (!array_key_exists($mime, self::MIME_EXTENSIONS)) {
            throw new RuntimeException(json_encode(['fields' => ['arquivo' => 'Formato de arquivo não permitido.']], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), 422);
        }

        $extension = self::MIME_EXTENSIONS[$mime];
        $storedName = bin2hex(random_bytes(24)) . '.' . $extension;
        $relativeDirectory = 'comida-mesa/' . Storage::buildRelativeDirectory();
        $directory = Storage::ensureDocumentDirectory($relativeDirectory);
        $target = $directory . DIRECTORY_SEPARATOR . $storedName;
        $relativePath = str_replace('\\', '/', $relativeDirectory . '/' . $storedName);

        $moved = PHP_SAPI === 'cli' ? rename($tmp, $target) : move_uploaded_file($tmp, $target);
        if (!$moved) {
            throw new RuntimeException('Não foi possível salvar o arquivo.', 500);
        }

        try {
            $documentId = $this->repository->transaction(function (ComidaMesaRepository $repo) use ($registrationId, $type, $description, $userId, $sectorId, $name, $storedName, $relativePath, $mime, $extension, $size, $target, $audit): int {
                $id = $repo->insertArchiveAndDocument(
                    [
                        'usuario_id' => $userId,
                        'setor_id' => $sectorId,
                        'tipo' => 'documento',
                        'finalidade' => 'comida_mesa',
                        'nome_original' => mb_substr($name, 0, 255),
                        'nome_armazenado' => $storedName,
                        'caminho_relativo' => $relativePath,
                        'mime_type' => $mime,
                        'extensao' => $extension,
                        'tamanho' => $size,
                        'hash_arquivo' => hash_file('sha256', $target),
                    ],
                    [
                        'inscricao_id' => $registrationId,
                        'tipo' => $type,
                        'descricao' => $description,
                        'enviado_por' => $userId,
                    ]
                );
                $repo->addHistory($registrationId, $userId, 'documento_enviado', $type, null, ['documento_id' => $id, 'nome_original' => $name, 'mime_type' => $mime, 'tamanho' => $size]);
                $audit->record($userId, null, 'documento_enviado', 'comida_mesa', $type, null, ['inscricao_id' => $registrationId, 'documento_id' => $id]);

                return $id;
            });
        } catch (\Throwable $exception) {
            @unlink($target);
            throw $exception;
        }

        return ['id' => $documentId, 'nome_original' => $name, 'mime_type' => $mime, 'tamanho' => $size];
    }

    /** @return array<string,mixed> */
    public function resolveForView(int $documentId): array
    {
        $document = $this->repository->findDocumentForView($documentId);
        if ($document === null || (int) ($document['arquivo_ativo'] ?? 0) !== 1) {
            throw new RuntimeException('Documento não localizado.', 404);
        }

        $path = Storage::resolveDocumentPath((string) $document['caminho_relativo']);
        $root = Storage::documentRoot();
        $realPath = realpath($path);

        if ($realPath === false || !Storage::isInsideRoot($realPath, $root) || !is_file($realPath)) {
            throw new RuntimeException('Documento não localizado.', 404);
        }

        $document['absolute_path'] = $realPath;

        return $document;
    }
}
