<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

require_once __DIR__ . '/../backend/bootstrap.php';

use App\Contracts\BarcodeProductProviderInterface;
use App\Core\Env;
use App\Integrations\OpenFoodFactsClient;
use App\Repositories\ProductRepository;

function cliNormalizeBarcode(string $barcode): string
{
    $barcode = preg_replace('/[\x00-\x1F\x7F\s-]+/u', '', $barcode) ?? '';

    return substr(trim($barcode), 0, 80);
}

function cliBarcodeFormat(string $barcode): string
{
    if (!preg_match('/^\d+$/', $barcode)) {
        return 'nao numerico';
    }

    return match (strlen($barcode)) {
        8 => 'EAN-8',
        12 => 'UPC-A',
        13 => 'EAN-13',
        14 => 'GTIN-14',
        default => 'comprimento inesperado',
    };
}

function cliValidGtin(string $barcode): bool
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

function cliProductName(array $product): string
{
    foreach (['product_name_pt', 'product_name', 'generic_name_pt', 'generic_name', 'name'] as $key) {
        $value = trim((string)($product[$key] ?? ''));
        if ($value !== '') {
            return substr(preg_replace('/\s+/', ' ', $value) ?? $value, 0, 180);
        }
    }

    return '-';
}

function cliProviders(): array
{
    $raw = (string)Env::get('PRODUCT_LOOKUP_PROVIDERS', Env::get('PRODUCT_LOOKUP_PROVIDER', 'open_food_facts'));
    $keys = array_values(array_filter(array_map('trim', explode(',', $raw))));
    $providers = [];

    foreach ($keys ?: ['open_food_facts'] as $key) {
        if ($key === 'open_food_facts') {
            $providers[] = new OpenFoodFactsClient();
            continue;
        }

        $providers[] = new class($key) implements BarcodeProductProviderInterface {
            private string $name;

            public function __construct(string $name)
            {
                $this->name = preg_replace('/[^a-z0-9_:-]/i', '', $name) ?: 'unknown_provider';
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
                ];
            }
        };
    }

    return $providers;
}

$empresaId = 0;
$codes = [];

foreach (array_slice($argv, 1) as $argument) {
    if (str_starts_with($argument, '--empresa-id=')) {
        $empresaId = max(0, (int)substr($argument, 13));
        continue;
    }

    if (str_starts_with($argument, '--empresa=')) {
        $empresaId = max(0, (int)substr($argument, 10));
        continue;
    }

    $codes[] = $argument;
}

if (!$codes) {
    echo "Uso: php scripts/test-product-lookup.php [--empresa-id=1] 7894900010015 1234567890128\n";
    exit(1);
}

$curlInfo = function_exists('curl_version') ? curl_version() : [];
echo 'PHP: ' . PHP_VERSION . PHP_EOL;
echo 'cURL disponivel: ' . (function_exists('curl_init') ? 'sim' : 'nao') . PHP_EOL;
echo 'cURL versao: ' . (is_array($curlInfo) ? (string)($curlInfo['version'] ?? '-') : '-') . PHP_EOL;
echo 'OpenSSL/cURL SSL: ' . (is_array($curlInfo) ? (string)($curlInfo['ssl_version'] ?? '-') : '-') . PHP_EOL;
echo 'allow_url_fopen: ' . (filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOL) ? 'sim' : 'nao') . PHP_EOL;
echo 'DNS world.openfoodfacts.org: ' . (gethostbynamel('world.openfoodfacts.org') ? 'sim' : 'nao') . PHP_EOL;
echo PHP_EOL;

$repository = null;
$localError = '';
if ($empresaId > 0) {
    try {
        $repository = new ProductRepository();
    } catch (Throwable $e) {
        $localError = $e->getMessage();
    }
}

foreach ($codes as $code) {
    $normalized = cliNormalizeBarcode($code);
    echo 'Codigo: ' . $code . PHP_EOL;
    echo 'Normalizado: ' . $normalized . PHP_EOL;
    echo 'Formato: ' . cliBarcodeFormat($normalized) . PHP_EOL;
    echo 'Digitos: ' . strlen($normalized) . PHP_EOL;
    echo 'GTIN valido: ' . (cliValidGtin($normalized) ? 'sim' : 'nao') . PHP_EOL;

    if ($empresaId <= 0) {
        echo 'Banco local: nao testado (informe --empresa-id=ID)' . PHP_EOL;
    } elseif ($repository === null) {
        echo 'Banco local: indisponivel (' . substr(preg_replace('/\s+/', ' ', $localError) ?? '', 0, 160) . ')' . PHP_EOL;
    } else {
        try {
            echo 'Banco local: ' . ($repository->findByBarcode($empresaId, $normalized) ? 'found' : 'not_found') . PHP_EOL;
        } catch (Throwable $e) {
            echo 'Banco local: indisponivel' . PHP_EOL;
        }
    }

    foreach (cliProviders() as $provider) {
        if (!$provider->isConfigured()) {
            echo 'Provedor: ' . $provider->name() . PHP_EOL;
            echo 'Status: not_configured' . PHP_EOL;
            continue;
        }

        $result = $provider->lookup($normalized);
        echo 'Provedor: ' . $provider->name() . PHP_EOL;
        echo 'URL: ' . (string)($result['url'] ?? '-') . PHP_EOL;
        echo 'HTTP: ' . (($result['http_status'] ?? null) === null ? '-' : (string)(int)$result['http_status']) . PHP_EOL;
        echo 'Content-Type: ' . ((string)($result['content_type'] ?? '') ?: '-') . PHP_EOL;
        echo 'Status: ' . (string)($result['status'] ?? 'unavailable') . PHP_EOL;
        echo 'Motivo: ' . ((string)($result['reason'] ?? '') ?: '-') . PHP_EOL;
        echo 'Nome: ' . cliProductName(is_array($result['product'] ?? null) ? $result['product'] : []) . PHP_EOL;
        echo 'Tempo: ' . (int)($result['elapsed_ms'] ?? 0) . ' ms' . PHP_EOL;
        if (($result['retry_after'] ?? null) !== null) {
            echo 'Retry-After: ' . (int)$result['retry_after'] . ' s' . PHP_EOL;
        }
    }

    echo str_repeat('-', 48) . PHP_EOL;
}
