<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Contracts\BarcodeProductProviderInterface;
use App\Core\Env;

final class OpenFoodFactsClient implements BarcodeProductProviderInterface
{
    private string $baseUrl;
    private string $userAgent;
    private int $timeout;

    public function __construct(?string $baseUrl = null, ?string $userAgent = null, ?int $timeout = null)
    {
        $this->baseUrl = rtrim((string)($baseUrl ?? Env::get('OPENFOODFACTS_BASE_URL', 'https://world.openfoodfacts.org/api/v3.6')), '/');
        $this->userAgent = trim((string)($userAgent ?? Env::get('OPENFOODFACTS_USER_AGENT', '')));
        $this->timeout = max(1, min(30, (int)($timeout ?? Env::int('PRODUCT_LOOKUP_TIMEOUT', 10))));
    }

    public function name(): string
    {
        return 'open_food_facts';
    }

    public function isConfigured(): bool
    {
        return $this->userAgent !== '' && str_starts_with($this->baseUrl, 'https://');
    }

    public function lookup(string $barcode): array
    {
        $startedAt = microtime(true);
        $host = (string)parse_url($this->baseUrl, PHP_URL_HOST);
        $context = [
            'host' => $this->safeHost($host),
            'http_status' => null,
            'content_type' => '',
            'elapsed_ms' => 0,
            'url' => '',
        ];

        if (!$this->isConfigured()) {
            return $this->result('unavailable', 'provider_not_configured', [], $context, $startedAt);
        }

        $fields = implode(',', [
            'code',
            'product_name_pt',
            'product_name',
            'generic_name_pt',
            'generic_name',
            'brands',
            'categories',
            'categories_tags',
            'quantity',
            'image_front_url',
            'image_url',
            'manufacturing_places',
            'manufacturing_places_tags',
        ]);

        $url = $this->baseUrl . '/product/' . rawurlencode($barcode) . '.json?fields=' . rawurlencode($fields);
        $context['url'] = $url;

        if (!function_exists('curl_init')) {
            return $this->result('unavailable', 'curl_unavailable', [], $context, $startedAt);
        }

        $responseHeaders = [];
        $ch = curl_init($url);

        if ($ch === false) {
            return $this->result('unavailable', 'network_error', [], $context, $startedAt);
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HEADERFUNCTION => static function ($curl, string $headerLine) use (&$responseHeaders): int {
                $length = strlen($headerLine);
                $parts = explode(':', $headerLine, 2);

                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }

                return $length;
            },
        ];

        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        curl_close($ch);

        $context['errno'] = $curlErrno;
        $context['http_status'] = $httpCode;
        $context['content_type'] = $this->safeHeaderValue($contentType);
        $context['retry_after'] = $this->retryAfter($responseHeaders['retry-after'] ?? null);

        if (defined('CURLE_OPERATION_TIMEDOUT') && $curlErrno === CURLE_OPERATION_TIMEDOUT) {
            return $this->result('unavailable', 'timeout', [], $context, $startedAt);
        }

        if ($this->isSslError($curlErrno)) {
            return $this->result('unavailable', 'ssl_error', [], $context, $startedAt);
        }

        if ($raw === false || $curlErrno !== 0) {
            return $this->result('unavailable', 'network_error', [], $context, $startedAt);
        }

        if ($httpCode === 404) {
            return $this->result('not_found', 'product_not_found', [], $context, $startedAt);
        }

        if ($httpCode === 429) {
            return $this->result('rate_limit', 'rate_limit', [], $context, $startedAt);
        }

        if ($httpCode >= 500 || $httpCode === 0) {
            return $this->result('unavailable', 'provider_unavailable', [], $context, $startedAt);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            return $this->result('unavailable', 'unexpected_http_status', [], $context, $startedAt);
        }

        if ($contentType !== '' && stripos($contentType, 'application/json') === false) {
            return $this->result('unavailable', 'invalid_content_type', [], $context, $startedAt);
        }

        $decoded = json_decode((string)$raw, true);
        if (!is_array($decoded)) {
            return $this->result('unavailable', 'invalid_json', [], $context, $startedAt);
        }

        if (isset($decoded['status']) && (int)$decoded['status'] === 0) {
            return $this->result('not_found', 'product_not_found', [], $context, $startedAt);
        }

        $product = $decoded['product'] ?? null;
        if (!is_array($product)) {
            return $this->result('unavailable', 'invalid_json', [], $context, $startedAt);
        }

        $context['api_status'] = isset($decoded['status']) ? (int)$decoded['status'] : null;
        $context['api_code'] = (string)($decoded['code'] ?? $barcode);

        return $this->result('found', '', $product, $context, $startedAt);
    }

    private function result(string $status, string $reason, array $product, array $context, float $startedAt): array
    {
        $elapsedMs = (int)round((microtime(true) - $startedAt) * 1000);

        return [
            'status' => $status,
            'provider' => $this->name(),
            'product' => $product,
            'reason' => $reason,
            'retry_after' => $context['retry_after'] ?? null,
            'http_status' => $context['http_status'] ?? null,
            'content_type' => $context['content_type'] ?? '',
            'elapsed_ms' => $elapsedMs,
            'url' => $context['url'] ?? '',
            'host' => $context['host'] ?? '',
            'errno' => $context['errno'] ?? null,
            'api_status' => $context['api_status'] ?? null,
            'api_code' => $context['api_code'] ?? '',
        ];
    }

    private function retryAfter(?string $value): ?int
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        $value = trim($value);
        if (ctype_digit($value)) {
            return max(0, (int)$value);
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        return max(0, $timestamp - time());
    }

    private function isSslError(int $curlErrno): bool
    {
        $sslErrors = [];

        foreach (['CURLE_SSL_CONNECT_ERROR', 'CURLE_PEER_FAILED_VERIFICATION', 'CURLE_SSL_CACERT'] as $constant) {
            if (defined($constant)) {
                $sslErrors[] = constant($constant);
            }
        }

        return in_array($curlErrno, $sslErrors, true);
    }

    private function safeHeaderValue(string $value): string
    {
        return substr(preg_replace('/[\r\n]+/', ' ', $value) ?? '', 0, 120);
    }

    private function safeHost(string $host): string
    {
        return preg_replace('/[^a-z0-9.-]/i', '', strtolower($host)) ?? '';
    }
}
