<?php

declare(strict_types=1);

namespace App\Integrations;

use App\Core\Env;
use RuntimeException;

final class OpenFoodFactsClient
{
    private string $baseUrl;
    private string $userAgent;
    private int $timeout;

    public function __construct(?string $baseUrl = null, ?string $userAgent = null, ?int $timeout = null)
    {
        $this->baseUrl = rtrim((string)($baseUrl ?? Env::get('OPENFOODFACTS_BASE_URL', 'https://world.openfoodfacts.org/api/v3.6')), '/');
        $this->userAgent = trim((string)($userAgent ?? Env::get('OPENFOODFACTS_USER_AGENT', '')));
        $this->timeout = max(1, min(20, (int)($timeout ?? Env::int('PRODUCT_LOOKUP_TIMEOUT', 8))));
    }

    public function lookup(string $barcode): array
    {
        if (!str_starts_with($this->baseUrl, 'https://')) {
            $this->fail('provider_not_configured', ['host' => (string)parse_url($this->baseUrl, PHP_URL_HOST)]);
        }

        if ($this->userAgent === '') {
            $this->fail('provider_not_configured', ['host' => (string)parse_url($this->baseUrl, PHP_URL_HOST)]);
        }

        if (!function_exists('curl_init')) {
            $this->fail('curl_unavailable', ['host' => (string)parse_url($this->baseUrl, PHP_URL_HOST)]);
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
        $ch = curl_init($url);

        if ($ch === false) {
            $this->fail('network_error', ['host' => (string)parse_url($this->baseUrl, PHP_URL_HOST)]);
        }

        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 3,
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_HTTPHEADER => ['Accept: application/json'],
            CURLOPT_USERAGENT => $this->userAgent,
            CURLOPT_HEADER => true,
        ];

        if (defined('CURLOPT_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        if (defined('CURLOPT_REDIR_PROTOCOLS') && defined('CURLPROTO_HTTPS')) {
            $options[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
        }

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $curlError = curl_error($ch);
        $curlErrno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        $context = [
            'errno' => $curlErrno,
            'curl_error' => $curlError,
            'http_status' => $httpCode,
            'content_type' => $contentType,
            'host' => (string)parse_url($this->baseUrl, PHP_URL_HOST),
        ];

        if (defined('CURLE_OPERATION_TIMEDOUT') && $curlErrno === CURLE_OPERATION_TIMEDOUT) {
            $this->fail('timeout', $context);
        }

        $sslErrors = [];
        if (defined('CURLE_SSL_CONNECT_ERROR')) {
            $sslErrors[] = CURLE_SSL_CONNECT_ERROR;
        }
        if (defined('CURLE_PEER_FAILED_VERIFICATION')) {
            $sslErrors[] = CURLE_PEER_FAILED_VERIFICATION;
        }
        if (defined('CURLE_SSL_CACERT')) {
            $sslErrors[] = CURLE_SSL_CACERT;
        }

        if (in_array($curlErrno, $sslErrors, true)) {
            $this->fail('ssl_error', $context);
        }

        if ($raw === false || $curlErrno !== 0) {
            $this->fail('network_error', $context);
        }

        $body = substr((string)$raw, $headerSize);

        if ($httpCode === 404) {
            return ['found' => false, 'product' => []];
        }

        if ($httpCode === 429) {
            $this->fail('rate_limit', $context);
        }

        if ($httpCode >= 500 || $httpCode === 0) {
            $this->fail('provider_unavailable', $context);
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            $this->fail('unexpected_http_status', $context);
        }

        if ($contentType !== '' && stripos($contentType, 'application/json') === false) {
            $this->fail('invalid_content_type', $context);
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $this->fail('invalid_json', $context);
        }

        if (isset($decoded['status']) && (int)$decoded['status'] === 0) {
            return ['found' => false, 'product' => []];
        }

        $product = $decoded['product'] ?? null;
        if (!is_array($product)) {
            $this->fail('invalid_json', $context);
        }

        return [
            'found' => true,
            'product' => $product,
            'code' => (string)($decoded['code'] ?? $barcode),
        ];
    }

    private function fail(string $reason, array $context = [])
    {
        $safeContext = [
            'errno' => isset($context['errno']) ? (int)$context['errno'] : null,
            'http_status' => isset($context['http_status']) ? (int)$context['http_status'] : null,
            'content_type' => isset($context['content_type']) ? substr(preg_replace('/[\r\n]+/', ' ', (string)$context['content_type']) ?? '', 0, 120) : '',
            'host' => isset($context['host']) ? preg_replace('/[^a-z0-9.-]/i', '', (string)$context['host']) : '',
        ];

        if (isset($context['curl_error']) && $context['curl_error'] !== '') {
            $safeContext['curl_error'] = substr(preg_replace('/[\r\n]+/', ' ', (string)$context['curl_error']) ?? '', 0, 180);
        }

        if (function_exists('log_app_message')) {
            \log_app_message(sprintf(
                "[%s] Open Food Facts lookup failure: reason=%s errno=%s http_status=%s content_type=%s host=%s\n",
                date('Y-m-d H:i:s'),
                $reason,
                (string)($safeContext['errno'] ?? ''),
                (string)($safeContext['http_status'] ?? ''),
                (string)($safeContext['content_type'] ?? ''),
                (string)($safeContext['host'] ?? '')
            ));
        }

        throw new RuntimeException($reason);
    }
}
