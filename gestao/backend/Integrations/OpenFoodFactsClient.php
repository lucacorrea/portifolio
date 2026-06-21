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
            throw new RuntimeException('provider_unavailable');
        }

        if ($this->userAgent === '') {
            throw new RuntimeException('provider_unavailable');
        }

        if (!function_exists('curl_init')) {
            throw new RuntimeException('provider_unavailable');
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

        $url = $this->baseUrl . '/product/' . rawurlencode($barcode) . '?fields=' . rawurlencode($fields);
        $ch = curl_init($url);

        if ($ch === false) {
            throw new RuntimeException('provider_unavailable');
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

        curl_setopt_array($ch, $options);
        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $httpCode = (int)curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $contentType = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        curl_close($ch);

        if ($raw === false || $errno !== 0) {
            throw new RuntimeException('provider_unavailable');
        }

        $body = substr((string)$raw, $headerSize);

        if ($httpCode === 404) {
            return ['found' => false, 'product' => []];
        }

        if ($httpCode === 429) {
            throw new RuntimeException('rate_limit');
        }

        if ($httpCode >= 500 || $httpCode === 0) {
            throw new RuntimeException('provider_unavailable');
        }

        if ($httpCode < 200 || $httpCode >= 300) {
            throw new RuntimeException('provider_unavailable');
        }

        if ($contentType !== '' && stripos($contentType, 'application/json') === false) {
            throw new RuntimeException('provider_unavailable');
        }

        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            throw new RuntimeException('provider_unavailable');
        }

        if (($decoded['status'] ?? 0) !== 1 || !is_array($decoded['product'] ?? null)) {
            return ['found' => false, 'product' => []];
        }

        return [
            'found' => true,
            'product' => $decoded['product'],
            'code' => (string)($decoded['code'] ?? $barcode),
        ];
    }
}
