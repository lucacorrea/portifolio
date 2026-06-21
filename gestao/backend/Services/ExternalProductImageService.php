<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Env;
use RuntimeException;

final class ExternalProductImageService
{
    private const MAX_BYTES = 2097152;

    public function download(int $empresaId, string $url): ?array
    {
        $url = trim($url);
        if (!$this->isAllowedUrl($url) || !function_exists('curl_init')) {
            return null;
        }

        $tmpFile = tempnam(sys_get_temp_dir(), 'product-image-');
        if ($tmpFile === false) {
            return null;
        }

        $handle = fopen($tmpFile, 'wb');
        if ($handle === false) {
            @unlink($tmpFile);
            return null;
        }

        $downloaded = 0;
        $ch = curl_init($url);
        if ($ch === false) {
            fclose($handle);
            @unlink($tmpFile);
            return null;
        }

        $userAgent = trim((string)Env::get('OPENFOODFACTS_USER_AGENT', 'LJCaixa/1.0'));
        $options = [
            CURLOPT_FILE => $handle,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: image/jpeg,image/png,image/webp'],
            CURLOPT_USERAGENT => $userAgent,
            CURLOPT_WRITEFUNCTION => static function ($curl, string $data) use ($handle, &$downloaded): int {
                $length = strlen($data);
                $downloaded += $length;
                if ($downloaded > self::MAX_BYTES) {
                    return 0;
                }

                return fwrite($handle, $data) ?: 0;
            },
        ];

        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        curl_setopt_array($ch, $options);
        $ok = curl_exec($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);
        fclose($handle);

        if ($ok !== true || $httpCode < 200 || $httpCode >= 300 || $downloaded <= 0 || $downloaded > self::MAX_BYTES) {
            @unlink($tmpFile);
            return null;
        }

        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($tmpFile);
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
        ];

        if (!is_string($mime) || !isset($extensions[$mime]) || @getimagesize($tmpFile) === false) {
            @unlink($tmpFile);
            return null;
        }

        if ($contentType !== '' && stripos($contentType, 'image/') === false) {
            @unlink($tmpFile);
            return null;
        }

        $directory = BASE_PATH . '/uploads/produtos';
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            @unlink($tmpFile);
            throw new RuntimeException('Não foi possível preparar o diretório de imagens.');
        }

        $filename = sprintf('empresa-%d-ext-%s.%s', $empresaId, bin2hex(random_bytes(8)), $extensions[$mime]);
        $absolutePath = $directory . '/' . $filename;

        if (!rename($tmpFile, $absolutePath)) {
            @unlink($tmpFile);
            return null;
        }

        return [
            'relativePath' => 'uploads/produtos/' . $filename,
            'absolutePath' => $absolutePath,
        ];
    }

    private function isAllowedUrl(string $url): bool
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false || !str_starts_with($url, 'https://')) {
            return false;
        }

        $host = strtolower((string)parse_url($url, PHP_URL_HOST));
        if (!$this->isAllowedHost($host)) {
            return false;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : (gethostbynamel($host) ?: []);
        if (!$ips) {
            return false;
        }

        foreach ($ips as $ip) {
            if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
                return false;
            }
        }

        return true;
    }

    private function isAllowedHost(string $host): bool
    {
        return $host === 'openfoodfacts.org'
            || $host === 'world.openfoodfacts.org'
            || $host === 'images.openfoodfacts.org'
            || str_ends_with($host, '.openfoodfacts.org');
    }
}
