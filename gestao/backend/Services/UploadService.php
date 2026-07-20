<?php

declare(strict_types=1);

namespace App\Services;

use InvalidArgumentException;
use RuntimeException;

final class UploadService
{
    private const PRODUCT_MAX_BYTES = 2097152;
    private const PRODUCT_UPLOAD_DIR = '/uploads/produtos';

    /**
     * @param array<string,mixed>|null $file
     * @return array{0:string,1:?string}
     */
    public function storeProductImage(int $empresaId, ?array $file, string $currentImage = ''): array
    {
        if (!is_array($file) || (int)($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return [$currentImage, null];
        }

        $error = (int)($file['error'] ?? UPLOAD_ERR_OK);
        if ($error !== UPLOAD_ERR_OK) {
            if (in_array($error, [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)) {
                throw new InvalidArgumentException('A imagem ultrapassou o limite configurado no servidor antes de chegar ao sistema.');
            }

            throw new InvalidArgumentException('Não foi possível receber a imagem do produto.');
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        if ($tmpName === '' || !is_uploaded_file($tmpName)) {
            throw new InvalidArgumentException('Arquivo de imagem inválido.');
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpName);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!is_string($mime) || !isset($extensions[$mime]) || @getimagesize($tmpName) === false) {
            throw new InvalidArgumentException('Formato de imagem inválido. Use JPG, PNG, WEBP ou GIF.');
        }

        $directory = BASE_PATH . self::PRODUCT_UPLOAD_DIR;
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Não foi possível preparar o diretório de imagens.');
        }

        $sourceSize = (int)($file['size'] ?? 0);
        if ($sourceSize <= 0) {
            $sourceSize = (int)(filesize($tmpName) ?: 0);
        }
        if ($sourceSize <= self::PRODUCT_MAX_BYTES) {
            $filename = $this->productFilename($empresaId, $extensions[$mime]);
            $absolutePath = $directory . '/' . $filename;

            if (!move_uploaded_file($tmpName, $absolutePath)) {
                throw new RuntimeException('Não foi possível salvar a imagem do produto.');
            }

            return ['uploads/produtos/' . $filename, $absolutePath];
        }

        $compressedPath = $this->compressImageToJpeg($tmpName, $mime);
        $filename = $this->productFilename($empresaId, 'jpg');
        $absolutePath = $directory . '/' . $filename;

        if (!@rename($compressedPath, $absolutePath)) {
            @unlink($compressedPath);
            throw new RuntimeException('Não foi possível salvar a imagem compactada do produto.');
        }

        return ['uploads/produtos/' . $filename, $absolutePath];
    }

    public function removeProductUpload(?string $absolutePath): void
    {
        if ($absolutePath === null || !is_file($absolutePath)) {
            return;
        }

        $uploadDirectory = realpath(BASE_PATH . self::PRODUCT_UPLOAD_DIR);
        $fileDirectory = realpath(dirname($absolutePath));

        if ($uploadDirectory !== false && $fileDirectory === $uploadDirectory) {
            @unlink($absolutePath);
        }
    }

    private function compressImageToJpeg(string $sourcePath, string $mime): string
    {
        if (extension_loaded('imagick')) {
            return $this->compressWithImagick($sourcePath);
        }

        if (extension_loaded('gd')) {
            return $this->compressWithGd($sourcePath, $mime);
        }

        throw new InvalidArgumentException('Para compactar imagens acima de 2MB, habilite a extensão GD ou Imagick no PHP.');
    }

    private function compressWithImagick(string $sourcePath): string
    {
        $image = new \Imagick($sourcePath);
        $image = $image->coalesceImages();
        $image->setIteratorIndex(0);
        $image->autoOrient();
        $image->stripImage();
        $image->setImageFormat('jpeg');
        $image->setImageBackgroundColor('white');
        $image = $image->mergeImageLayers(\Imagick::LAYERMETHOD_FLATTEN);

        $width = $image->getImageWidth();
        $height = $image->getImageHeight();
        $target = $this->temporaryJpegPath();

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $quality = max(48, 86 - ($attempt * 7));
            $scale = max(0.35, 1 - ($attempt * 0.12));
            $work = clone $image;
            $work->resizeImage(max(1, (int)round($width * $scale)), max(1, (int)round($height * $scale)), \Imagick::FILTER_LANCZOS, 1, true);
            $work->setImageCompressionQuality($quality);
            $work->writeImage($target);
            $work->clear();

            if (filesize($target) <= self::PRODUCT_MAX_BYTES) {
                $image->clear();
                return $target;
            }
        }

        $image->clear();
        @unlink($target);
        throw new InvalidArgumentException('Não foi possível reduzir a imagem para 2MB. Tente uma foto com resolução menor.');
    }

    private function compressWithGd(string $sourcePath, string $mime): string
    {
        $source = match ($mime) {
            'image/jpeg' => function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($sourcePath) : false,
            'image/png' => function_exists('imagecreatefrompng') ? @imagecreatefrompng($sourcePath) : false,
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            'image/gif' => function_exists('imagecreatefromgif') ? @imagecreatefromgif($sourcePath) : false,
            default => false,
        };

        if (!$source instanceof \GdImage) {
            throw new InvalidArgumentException('Não foi possível processar a imagem enviada.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $target = $this->temporaryJpegPath();

        for ($attempt = 0; $attempt < 8; $attempt++) {
            $quality = max(48, 86 - ($attempt * 7));
            $scale = max(0.35, 1 - ($attempt * 0.12));
            $width = max(1, (int)round($sourceWidth * $scale));
            $height = max(1, (int)round($sourceHeight * $scale));
            $canvas = imagecreatetruecolor($width, $height);
            $white = imagecolorallocate($canvas, 255, 255, 255);
            imagefill($canvas, 0, 0, $white);
            imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);
            imagejpeg($canvas, $target, $quality);
            imagedestroy($canvas);

            if (filesize($target) <= self::PRODUCT_MAX_BYTES) {
                imagedestroy($source);
                return $target;
            }
        }

        imagedestroy($source);
        @unlink($target);
        throw new InvalidArgumentException('Não foi possível reduzir a imagem para 2MB. Tente uma foto com resolução menor.');
    }

    private function temporaryJpegPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'produto-img-');
        if ($path === false) {
            throw new RuntimeException('Não foi possível criar arquivo temporário para a imagem.');
        }

        return $path;
    }

    private function productFilename(int $empresaId, string $extension): string
    {
        return sprintf('empresa-%d-%s.%s', $empresaId, bin2hex(random_bytes(8)), $extension);
    }
}
