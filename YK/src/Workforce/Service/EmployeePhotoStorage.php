<?php

declare(strict_types=1);

namespace App\Workforce\Service;

use InvalidArgumentException;
use RuntimeException;

final class EmployeePhotoStorage
{
    private const MAX_BYTES = 5_242_880;
    private const MAX_DIMENSION = 4096;
    private const MAX_PIXELS = 20_000_000;
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function __construct(private readonly string $storageRoot)
    {
    }

    /** @param array<string,mixed>|null $upload */
    public function validate(?array $upload): bool
    {
        return $this->inspect($upload) !== null;
    }

    /** @param array<string,mixed>|null $upload */
    public function store(?array $upload, int $employeeId): ?string
    {
        $inspection = $this->inspect($upload);
        if ($inspection === null) return null;
        if ($employeeId <= 0) throw new InvalidArgumentException('Funcionário inválido para a foto.');
        [$temporaryPath, $mime] = $inspection;

        $directory = $this->storageRoot . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'funcionarios' . DIRECTORY_SEPARATOR . $employeeId;
        if (!is_dir($directory) && !mkdir($directory, 0750, true) && !is_dir($directory)) throw new RuntimeException('Não foi possível preparar o armazenamento da foto.');

        $filename = bin2hex(random_bytes(16)) . '.' . self::MIME_EXTENSIONS[$mime];
        $destination = $directory . DIRECTORY_SEPARATOR . $filename;
        if (!move_uploaded_file($temporaryPath, $destination)) throw new RuntimeException('Não foi possível armazenar a foto do funcionário.');

        return 'uploads/funcionarios/' . $employeeId . '/' . $filename;
    }

    /** @param array<string,mixed>|null $upload @return array{0:string,1:string}|null */
    private function inspect(?array $upload): ?array
    {
        if ($upload === null || (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) return null;
        $error = (int) ($upload['error'] ?? UPLOAD_ERR_NO_FILE);
        if ($error !== UPLOAD_ERR_OK) throw new InvalidArgumentException($this->uploadErrorMessage($error));

        $temporaryPath = (string) ($upload['tmp_name'] ?? '');
        $size = (int) ($upload['size'] ?? 0);
        if ($temporaryPath === '' || !is_uploaded_file($temporaryPath)) throw new InvalidArgumentException('O arquivo de foto enviado é inválido.');
        if ($size <= 0 || $size > self::MAX_BYTES) throw new InvalidArgumentException('A foto deve ter no máximo 5 MB.');

        $image = @getimagesize($temporaryPath);
        if (!is_array($image) || !isset($image[0], $image[1], $image['mime'])) throw new InvalidArgumentException('Envie uma foto JPEG, PNG ou WebP válida.');
        $width = (int) $image[0];
        $height = (int) $image[1];
        $mime = strtolower((string) $image['mime']);
        if (!isset(self::MIME_EXTENSIONS[$mime])) throw new InvalidArgumentException('Envie uma foto JPEG, PNG ou WebP.');
        if ($width <= 0 || $height <= 0 || $width > self::MAX_DIMENSION || $height > self::MAX_DIMENSION || ($width * $height) > self::MAX_PIXELS) {
            throw new InvalidArgumentException('A foto possui dimensões acima do limite permitido.');
        }

        if (function_exists('finfo_open')) {
            $handle = finfo_open(FILEINFO_MIME_TYPE);
            $detected = $handle === false ? false : finfo_file($handle, $temporaryPath);
            if ($handle !== false) finfo_close($handle);
            if (!is_string($detected) || strtolower($detected) !== $mime) throw new InvalidArgumentException('O conteúdo da foto não corresponde ao tipo informado.');
        }

        return [$temporaryPath, $mime];
    }

    public function resolve(string $relativePath): ?string
    {
        if (!preg_match('#^uploads/funcionarios/[1-9][0-9]*/[a-f0-9]{32}\.(?:jpg|png|webp)$#', $relativePath)) return null;
        $candidate = $this->storageRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $root = realpath($this->storageRoot);
        $resolved = is_file($candidate) ? realpath($candidate) : false;
        if ($root === false || $resolved === false || !str_starts_with($resolved, $root . DIRECTORY_SEPARATOR)) return null;
        return $resolved;
    }

    public function delete(?string $relativePath): void
    {
        if ($relativePath === null || $relativePath === '') return;
        $resolved = $this->resolve($relativePath);
        if ($resolved !== null && is_file($resolved)) @unlink($resolved);
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'A foto ultrapassa o limite de 5 MB.',
            UPLOAD_ERR_PARTIAL => 'O envio da foto foi interrompido. Tente novamente.',
            default => 'Não foi possível receber a foto enviada.',
        };
    }
}
