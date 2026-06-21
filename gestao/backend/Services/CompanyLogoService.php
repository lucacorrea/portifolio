<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

final class CompanyLogoService
{
    private const MAX_FILE_SIZE = 2 * 1024 * 1024;

    private const MAX_WIDTH = 5000;

    private const MAX_HEIGHT = 5000;

    private const ALLOWED_MIME_TYPES = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    /**
     * Salva uma nova logo e retorna somente o caminho relativo.
     *
     * Exemplo:
     * uploads/empresas/1/logo-a1b2c3d4.webp
     */
    public function upload(int $empresaId, array $file): string
    {
        if ($empresaId <= 0) {
            throw new InvalidArgumentException(
                'Empresa inválida para envio da logo.'
            );
        }

        $this->validateUploadError($file);

        $temporaryFile = (string)($file['tmp_name'] ?? '');
        $fileSize = (int)($file['size'] ?? 0);

        if (
            $temporaryFile === ''
            || !is_uploaded_file($temporaryFile)
        ) {
            throw new InvalidArgumentException(
                'O arquivo enviado não é válido.'
            );
        }

        if ($fileSize <= 0) {
            throw new InvalidArgumentException(
                'A logo enviada está vazia.'
            );
        }

        if ($fileSize > self::MAX_FILE_SIZE) {
            throw new InvalidArgumentException(
                'A logo deve possuir no máximo 2 MB.'
            );
        }

        $mimeType = $this->detectMimeType($temporaryFile);

        if (!isset(self::ALLOWED_MIME_TYPES[$mimeType])) {
            throw new InvalidArgumentException(
                'Formato inválido. Envie uma imagem JPG, PNG ou WEBP.'
            );
        }

        $this->validateImageDimensions($temporaryFile);

        $extension = self::ALLOWED_MIME_TYPES[$mimeType];

        $relativeDirectory = sprintf(
            'uploads/empresas/%d',
            $empresaId
        );

        $absoluteDirectory = BASE_PATH
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativeDirectory
            );

        $this->ensureDirectory($absoluteDirectory);

        $fileName = sprintf(
            'logo-%s.%s',
            bin2hex(random_bytes(16)),
            $extension
        );

        $relativePath = $relativeDirectory . '/' . $fileName;

        $absolutePath = $absoluteDirectory
            . DIRECTORY_SEPARATOR
            . $fileName;

        if (!move_uploaded_file($temporaryFile, $absolutePath)) {
            throw new RuntimeException(
                'Não foi possível salvar a logo da empresa.'
            );
        }

        @chmod($absolutePath, 0644);

        return $relativePath;
    }

    /**
     * Remove uma logo antiga do armazenamento.
     *
     * Somente arquivos dentro de uploads/empresas podem ser removidos.
     */
    public function delete(?string $relativePath): void
    {
        $relativePath = $this->normalizePath(
            (string)$relativePath
        );

        if ($relativePath === '') {
            return;
        }

        if (
            !str_starts_with(
                $relativePath,
                'uploads/empresas/'
            )
        ) {
            return;
        }

        if (
            str_contains($relativePath, '../')
            || str_contains($relativePath, '..\\')
        ) {
            return;
        }

        $absolutePath = BASE_PATH
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath
            );

        if (is_file($absolutePath)) {
            @unlink($absolutePath);
        }
    }

    /**
     * Verifica se o arquivo de logo existe fisicamente.
     */
    public function exists(?string $relativePath): bool
    {
        $relativePath = $this->normalizePath(
            (string)$relativePath
        );

        if (
            $relativePath === ''
            || !str_starts_with(
                $relativePath,
                'uploads/empresas/'
            )
            || str_contains($relativePath, '../')
        ) {
            return false;
        }

        $absolutePath = BASE_PATH
            . DIRECTORY_SEPARATOR
            . str_replace(
                '/',
                DIRECTORY_SEPARATOR,
                $relativePath
            );

        return is_file($absolutePath);
    }

    private function validateUploadError(array $file): void
    {
        $error = (int)(
            $file['error']
            ?? UPLOAD_ERR_NO_FILE
        );

        if ($error === UPLOAD_ERR_OK) {
            return;
        }

        $message = match ($error) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE =>
                'A logo excede o tamanho máximo permitido.',

            UPLOAD_ERR_PARTIAL =>
                'O envio da logo foi interrompido. Tente novamente.',

            UPLOAD_ERR_NO_FILE =>
                'Selecione uma imagem para a logo.',

            UPLOAD_ERR_NO_TMP_DIR =>
                'A pasta temporária do servidor não está disponível.',

            UPLOAD_ERR_CANT_WRITE =>
                'O servidor não conseguiu gravar a logo.',

            UPLOAD_ERR_EXTENSION =>
                'O envio da logo foi bloqueado pelo servidor.',

            default =>
                'Ocorreu um erro durante o envio da logo.',
        };

        throw new InvalidArgumentException($message);
    }

    private function detectMimeType(string $filePath): string
    {
        if (!class_exists(\finfo::class)) {
            throw new RuntimeException(
                'A extensão Fileinfo não está disponível no servidor.'
            );
        }

        $fileInfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $fileInfo->file($filePath);

        if (!is_string($mimeType) || $mimeType === '') {
            throw new InvalidArgumentException(
                'Não foi possível identificar o formato da imagem.'
            );
        }

        return strtolower(trim($mimeType));
    }

    private function validateImageDimensions(
        string $filePath
    ): void {
        $imageData = @getimagesize($filePath);

        if ($imageData === false) {
            throw new InvalidArgumentException(
                'O arquivo enviado não é uma imagem válida.'
            );
        }

        $width = (int)($imageData[0] ?? 0);
        $height = (int)($imageData[1] ?? 0);

        if ($width <= 0 || $height <= 0) {
            throw new InvalidArgumentException(
                'A imagem possui dimensões inválidas.'
            );
        }

        if (
            $width > self::MAX_WIDTH
            || $height > self::MAX_HEIGHT
        ) {
            throw new InvalidArgumentException(
                'A logo deve possuir no máximo 5000 x 5000 pixels.'
            );
        }
    }

    private function ensureDirectory(
        string $absoluteDirectory
    ): void {
        if (
            !is_dir($absoluteDirectory)
            && !mkdir(
                $absoluteDirectory,
                0755,
                true
            )
            && !is_dir($absoluteDirectory)
        ) {
            throw new RuntimeException(
                'Não foi possível criar a pasta da empresa.'
            );
        }

        if (!is_writable($absoluteDirectory)) {
            throw new RuntimeException(
                'A pasta de logos não possui permissão de escrita.'
            );
        }
    }

    private function normalizePath(string $path): string
    {
        return ltrim(
            str_replace('\\', '/', trim($path)),
            '/'
        );
    }
}
