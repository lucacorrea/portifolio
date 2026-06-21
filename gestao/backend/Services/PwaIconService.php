<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class PwaIconService
{
    public function iconsForBrand(array $brand): array
    {
        if (empty($brand['has_logo']) || (string)($brand['logo_path'] ?? '') === '') {
            return [];
        }

        if (!extension_loaded('gd')) {
            throw new RuntimeException('A extensão GD não está disponível para gerar os ícones do aplicativo.');
        }

        $companyId = (int)($brand['company_id'] ?? 0);
        $source = (string)$brand['logo_path'];
        $sourceAbsolute = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $source);

        if ($companyId <= 0 || !is_file($sourceAbsolute)) {
            return [];
        }

        $targetDir = sprintf('uploads/empresas/%d/pwa', $companyId);
        $targetAbsoluteDir = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $targetDir);

        if (!is_dir($targetAbsoluteDir) && !mkdir($targetAbsoluteDir, 0755, true) && !is_dir($targetAbsoluteDir)) {
            throw new RuntimeException('Não foi possível criar a pasta de ícones do aplicativo.');
        }

        $targets = [
            ['file' => 'icon-192.png', 'size' => 192, 'purpose' => 'any', 'safe' => 0.86],
            ['file' => 'icon-512.png', 'size' => 512, 'purpose' => 'any', 'safe' => 0.86],
            ['file' => 'icon-maskable-512.png', 'size' => 512, 'purpose' => 'maskable', 'safe' => 0.72],
            ['file' => 'apple-touch-icon.png', 'size' => 180, 'purpose' => 'any', 'safe' => 0.86],
        ];

        $sourceMtime = filemtime($sourceAbsolute) ?: time();
        $icons = [];

        foreach ($targets as $target) {
            $targetPath = $targetDir . '/' . $target['file'];
            $targetAbsolute = $targetAbsoluteDir . DIRECTORY_SEPARATOR . $target['file'];

            if (!is_file($targetAbsolute) || (filemtime($targetAbsolute) ?: 0) < $sourceMtime) {
                $this->generatePng($sourceAbsolute, $targetAbsolute, (int)$target['size'], (float)$target['safe']);
            }

            if ($target['file'] !== 'apple-touch-icon.png') {
                $icons[] = [
                    'src' => $targetPath,
                    'sizes' => (string)$target['size'] . 'x' . (string)$target['size'],
                    'type' => 'image/png',
                    'purpose' => $target['purpose'],
                ];
            }
        }

        return $icons;
    }

    public function appleTouchIconPath(array $brand): string
    {
        if (empty($brand['has_logo'])) {
            return '';
        }

        $companyId = (int)($brand['company_id'] ?? 0);
        $path = sprintf('uploads/empresas/%d/pwa/apple-touch-icon.png', $companyId);
        $absolute = BASE_PATH . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $path);

        return is_file($absolute) ? $path : '';
    }

    public function deleteGeneratedIcons(int $empresaId): void
    {
        if ($empresaId <= 0) {
            return;
        }

        $dir = BASE_PATH . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'empresas' . DIRECTORY_SEPARATOR . $empresaId . DIRECTORY_SEPARATOR . 'pwa';
        foreach (['icon-192.png', 'icon-512.png', 'icon-maskable-512.png', 'apple-touch-icon.png'] as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_file($path)) {
                @unlink($path);
            }
        }
    }

    private function generatePng(string $sourcePath, string $targetPath, int $size, float $safeArea): void
    {
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            throw new RuntimeException('A logo cadastrada não é uma imagem válida.');
        }

        $source = match ((string)($imageInfo['mime'] ?? '')) {
            'image/jpeg' => @imagecreatefromjpeg($sourcePath),
            'image/png' => @imagecreatefrompng($sourcePath),
            'image/webp' => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($sourcePath) : false,
            default => false,
        };

        if (!$source) {
            throw new RuntimeException('Não foi possível ler a logo para gerar os ícones.');
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $canvas = imagecreatetruecolor($size, $size);
        imagesavealpha($canvas, false);
        $background = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $background);

        $maxSide = (int)floor($size * $safeArea);
        $scale = min($maxSide / $sourceWidth, $maxSide / $sourceHeight);
        $targetWidth = max(1, (int)round($sourceWidth * $scale));
        $targetHeight = max(1, (int)round($sourceHeight * $scale));
        $targetX = (int)floor(($size - $targetWidth) / 2);
        $targetY = (int)floor(($size - $targetHeight) / 2);

        imagecopyresampled($canvas, $source, $targetX, $targetY, 0, 0, $targetWidth, $targetHeight, $sourceWidth, $sourceHeight);

        if (!imagepng($canvas, $targetPath, 9)) {
            imagedestroy($source);
            imagedestroy($canvas);
            throw new RuntimeException('Não foi possível gravar os ícones do aplicativo.');
        }

        @chmod($targetPath, 0644);
        imagedestroy($source);
        imagedestroy($canvas);
    }
}
