<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\BarcodeProductProviderInterface;
use App\Core\Env;
use App\Integrations\OpenFoodFactsClient;
use App\Repositories\ProductRepository;
use InvalidArgumentException;
use Throwable;

final class BarcodeProductLookupService
{
    private ProductRepository $products;
    /** @var BarcodeProductProviderInterface[] */
    private array $providers;

    public function __construct(?ProductRepository $products = null, ?array $providers = null)
    {
        $this->products = $products ?? new ProductRepository();
        $this->providers = $providers !== null ? $this->normalizeProviders($providers) : $this->buildProvidersFromConfig();
    }

    public function lookup(int $empresaId, string $barcode, string $format = ''): array
    {
        $lookupId = bin2hex(random_bytes(6));
        $normalized = $this->normalizeBarcode($barcode);
        $this->validateBarcode($normalized, $format);

        $local = $this->products->findByBarcode($empresaId, $normalized);
        if ($local !== null) {
            return [
                'lookup_id' => $lookupId,
                'source' => 'local',
                'exists' => true,
                'product' => $local,
                'message' => 'Produto já cadastrado.',
                'attempts' => [],
            ];
        }

        $attempts = [];
        $hadAvailableProvider = false;
        $hadRateLimit = false;
        $retryAfter = null;

        foreach ($this->providers as $provider) {
            if (!$provider->isConfigured()) {
                $attempt = [
                    'provider' => $provider->name(),
                    'status' => 'not_configured',
                    'reason' => 'provider_not_configured',
                    'http_status' => null,
                    'elapsed_ms' => 0,
                    'retry_after' => null,
                ];
                $attempts[] = $attempt;
                $this->logAttempt($lookupId, $empresaId, $normalized, $attempt);
                continue;
            }

            try {
                $result = $provider->lookup($normalized);
            } catch (Throwable $e) {
                $result = [
                    'status' => 'unavailable',
                    'provider' => $provider->name(),
                    'product' => [],
                    'reason' => 'unexpected_provider_error',
                    'retry_after' => null,
                    'http_status' => null,
                    'elapsed_ms' => 0,
                ];
            }

            $attempt = [
                'provider' => (string)($result['provider'] ?? $provider->name()),
                'status' => (string)($result['status'] ?? 'unavailable'),
                'reason' => (string)($result['reason'] ?? ''),
                'http_status' => $result['http_status'] ?? null,
                'elapsed_ms' => $result['elapsed_ms'] ?? 0,
                'retry_after' => $result['retry_after'] ?? null,
            ];
            $attempts[] = $attempt;
            $this->logAttempt($lookupId, $empresaId, $normalized, $attempt);

            if ($attempt['status'] === 'found') {
                return [
                    'lookup_id' => $lookupId,
                    'source' => $provider->name(),
                    'exists' => false,
                    'product' => $this->mapProviderProduct($normalized, is_array($result['product'] ?? null) ? $result['product'] : [], $provider->name()),
                    'message' => 'Produto encontrado. Confira os dados antes de salvar.',
                    'attempts' => $attempts,
                ];
            }

            if ($attempt['status'] === 'not_found') {
                $hadAvailableProvider = true;
                continue;
            }

            if ($attempt['status'] === 'rate_limit') {
                $hadRateLimit = true;
                $retryAfter = $attempt['retry_after'] ?? $retryAfter;
                continue;
            }
        }

        if ($hadAvailableProvider) {
            return [
                'lookup_id' => $lookupId,
                'source' => 'none',
                'exists' => false,
                'product' => null,
                'barcode' => $normalized,
                'message' => 'Produto não encontrado nas bases consultadas. Preencha os dados manualmente.',
                'attempts' => $attempts,
            ];
        }

        return [
            'lookup_id' => $lookupId,
            'source' => $hadRateLimit ? 'rate_limit' : 'unavailable',
            'exists' => false,
            'product' => null,
            'barcode' => $normalized,
            'message' => $hadRateLimit ? 'O serviço externo atingiu o limite de consultas.' : 'Nenhuma base externa pôde ser consultada.',
            'attempts' => $attempts,
            'retry_after' => $retryAfter,
        ];
    }

    private function normalizeBarcode(string $barcode): string
    {
        $barcode = preg_replace('/[\x00-\x1F\x7F\s-]+/u', '', $barcode) ?? '';
        $barcode = trim($barcode);

        if (preg_match('/^\d+$/', $barcode)) {
            return $barcode;
        }

        return $this->limitText($barcode, 80);
    }

    private function validateBarcode(string $barcode, string $format): void
    {
        if ($barcode === '') {
            throw new InvalidArgumentException('Código de barras inválido.');
        }

        $format = strtoupper(str_replace('-', '_', trim($format)));
        if (!preg_match('/^\d+$/', $barcode)) {
            throw new InvalidArgumentException('Consulta externa automática aceita somente EAN/UPC/GTIN numérico.');
        }

        $length = strlen($barcode);
        if ($format === 'UPC_E' && in_array($length, [6, 8], true)) {
            return;
        }

        if (!in_array($length, [8, 12, 13, 14], true) || !$this->isValidGtin($barcode)) {
            throw new InvalidArgumentException('Código de barras inválido.');
        }
    }

    private function isValidGtin(string $barcode): bool
    {
        if (!preg_match('/^\d{8}$|^\d{12}$|^\d{13}$|^\d{14}$/', $barcode)) {
            return false;
        }

        $sum = 0;
        $digits = str_split($barcode);
        $check = (int)array_pop($digits);
        $digits = array_reverse($digits);

        foreach ($digits as $index => $digit) {
            $sum += (int)$digit * ($index % 2 === 0 ? 3 : 1);
        }

        return ((10 - ($sum % 10)) % 10) === $check;
    }

    private function mapProviderProduct(string $barcode, array $product, string $provider): array
    {
        $name = $this->firstText($product, ['product_name_pt', 'product_name', 'generic_name_pt', 'generic_name', 'name'], 180);
        $description = $this->firstText($product, ['generic_name_pt', 'generic_name', 'description'], 65535);
        $quantity = $this->firstText($product, ['quantity', 'packageQuantity'], 50);
        $category = $this->categoryFromProduct($product);
        $imageUrl = $this->httpsUrl($this->firstText($product, ['image_front_url', 'image_url', 'externalImageUrl'], 500));
        $manufacturer = $this->firstText($product, ['manufacturing_places', 'manufacturer'], 150);

        return [
            'id' => 0,
            'name' => $name,
            'sku' => $barcode,
            'barcode' => $barcode,
            'category' => $category,
            'description' => $description,
            'brand' => $this->firstText($product, ['brands', 'brand'], 150),
            'unit' => $this->inferUnit($quantity),
            'packageQuantity' => $quantity,
            'ncm' => '',
            'cest' => '',
            'manufacturer' => $manufacturer,
            'cost' => '',
            'price' => '',
            'stock' => '',
            'minStock' => '',
            'lot' => '',
            'expiry' => '',
            'image' => '',
            'source' => $provider,
            'externalImageUrl' => $imageUrl,
        ];
    }

    private function firstText(array $source, array $keys, int $maxLength): string
    {
        foreach ($keys as $key) {
            $value = $source[$key] ?? '';
            if (is_array($value)) {
                $value = implode(', ', array_filter(array_map('strval', $value)));
            }

            $text = $this->cleanText((string)$value, $maxLength);
            if ($text !== '') {
                return $text;
            }
        }

        return '';
    }

    private function categoryFromProduct(array $product): string
    {
        $categories = $this->firstText($product, ['categories', 'category'], 120);
        if ($categories !== '') {
            $parts = array_values(array_filter(array_map('trim', explode(',', $categories))));
            if ($parts) {
                return $this->cleanText($parts[0], 120);
            }
        }

        $tags = $product['categories_tags'] ?? [];
        if (is_array($tags) && $tags) {
            $tag = (string)reset($tags);
            $tag = preg_replace('/^[a-z]{2}:/', '', $tag) ?? $tag;
            $tag = str_replace(['-', '_'], ' ', $tag);
            return $this->cleanText(ucwords($tag), 120);
        }

        return '';
    }

    private function inferUnit(string $quantity): string
    {
        $normalized = strtolower(trim($quantity));
        if ($normalized === '') {
            return '';
        }

        if (preg_match('/\b(kg|quilo|quilos)\b/u', $normalized)) {
            return 'KG';
        }

        if (preg_match('/\b(g|grama|gramas)\b/u', $normalized)) {
            return 'G';
        }

        if (preg_match('/\b(ml|mililitro|mililitros)\b/u', $normalized)) {
            return 'ML';
        }

        if (preg_match('/\b(l|lt|litro|litros)\b/u', $normalized)) {
            return 'L';
        }

        if (preg_match('/\b(un|unidade|unidades)\b/u', $normalized)) {
            return 'UN';
        }

        return '';
    }

    private function httpsUrl(string $url): string
    {
        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return '';
        }

        return str_starts_with($url, 'https://') ? $url : '';
    }

    private function cleanText(string $value, int $maxLength): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value) ?? '';
        $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);

        return $this->limitText($value, $maxLength);
    }

    private function limitText(string $value, int $maxLength): string
    {
        return function_exists('mb_substr') ? mb_substr($value, 0, $maxLength) : substr($value, 0, $maxLength);
    }

    /**
     * @param array<int, mixed> $providers
     * @return BarcodeProductProviderInterface[]
     */
    private function normalizeProviders(array $providers): array
    {
        return array_values(array_filter($providers, static fn($provider): bool => $provider instanceof BarcodeProductProviderInterface));
    }

    /**
     * @return BarcodeProductProviderInterface[]
     */
    private function buildProvidersFromConfig(): array
    {
        $raw = (string)Env::get('PRODUCT_LOOKUP_PROVIDERS', Env::get('PRODUCT_LOOKUP_PROVIDER', 'open_food_facts'));
        $keys = array_values(array_filter(array_map('trim', explode(',', $raw))));
        $providers = [];

        foreach ($keys ?: ['open_food_facts'] as $key) {
            if ($key === 'open_food_facts') {
                $providers[] = new OpenFoodFactsClient();
                continue;
            }

            $providers[] = $this->notConfiguredProvider($key);
        }

        return $providers;
    }

    private function notConfiguredProvider(string $name): BarcodeProductProviderInterface
    {
        $safeName = preg_replace('/[^a-z0-9_:-]/i', '', $name) ?: 'unknown_provider';

        return new class($safeName) implements BarcodeProductProviderInterface {
            private string $name;

            public function __construct(string $name)
            {
                $this->name = $name;
            }

            public function name(): string
            {
                return $this->name;
            }

            public function isConfigured(): bool
            {
                return false;
            }

            public function lookup(string $barcode): array
            {
                return [
                    'status' => 'unavailable',
                    'provider' => $this->name,
                    'product' => [],
                    'reason' => 'provider_not_configured',
                    'retry_after' => null,
                ];
            }
        };
    }

    private function logAttempt(string $lookupId, int $empresaId, string $barcode, array $attempt): void
    {
        if (!function_exists('log_app_message')) {
            return;
        }

        \log_app_message(sprintf(
            "[%s] Product lookup: lookup_id=%s empresa_id=%d barcode=%s provider=%s status=%s reason=%s http_status=%s tempo_ms=%s\n",
            date('Y-m-d H:i:s'),
            $this->safeLogValue($lookupId),
            $empresaId,
            $this->safeLogValue($barcode),
            $this->safeLogValue((string)($attempt['provider'] ?? '')),
            $this->safeLogValue((string)($attempt['status'] ?? '')),
            $this->safeLogValue((string)($attempt['reason'] ?? '')),
            $attempt['http_status'] === null ? '' : (string)(int)$attempt['http_status'],
            (string)(int)($attempt['elapsed_ms'] ?? 0)
        ));
    }

    private function safeLogValue(string $value): string
    {
        return preg_replace('/[^0-9A-Za-z_.:-]/', '', $value) ?? '';
    }
}
