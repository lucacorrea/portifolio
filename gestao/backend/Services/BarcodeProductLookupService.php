<?php

declare(strict_types=1);

namespace App\Services;

use App\Integrations\OpenFoodFactsClient;
use App\Repositories\ProductRepository;
use InvalidArgumentException;
use RuntimeException;

final class BarcodeProductLookupService
{
    private ProductRepository $products;
    private OpenFoodFactsClient $openFoodFacts;

    public function __construct(?ProductRepository $products = null, ?OpenFoodFactsClient $openFoodFacts = null)
    {
        $this->products = $products ?? new ProductRepository();
        $this->openFoodFacts = $openFoodFacts ?? new OpenFoodFactsClient();
    }

    public function lookup(int $empresaId, string $barcode, string $format = ''): array
    {
        $normalized = $this->normalizeBarcode($barcode);
        $this->validateBarcode($normalized, $format);

        $local = $this->products->findByBarcode($empresaId, $normalized);
        if ($local !== null) {
            return [
                'source' => 'local',
                'exists' => true,
                'product' => $local,
                'message' => 'Produto já cadastrado.',
            ];
        }

        try {
            $external = $this->openFoodFacts->lookup($normalized);
        } catch (RuntimeException $e) {
            throw $e;
        }

        if (!($external['found'] ?? false)) {
            return [
                'source' => 'none',
                'exists' => false,
                'product' => null,
                'barcode' => $normalized,
                'message' => 'Produto não encontrado na base externa. Preencha os dados manualmente.',
            ];
        }

        return [
            'source' => 'open_food_facts',
            'exists' => false,
            'product' => $this->mapOpenFoodFactsProduct($normalized, $external['product'] ?? []),
            'message' => 'Produto encontrado. Confira os dados antes de salvar.',
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

    private function mapOpenFoodFactsProduct(string $barcode, array $product): array
    {
        $name = $this->firstText($product, ['product_name_pt', 'product_name', 'generic_name_pt', 'generic_name'], 180);
        $description = $this->firstText($product, ['generic_name_pt', 'generic_name'], 65535);
        $quantity = $this->firstText($product, ['quantity'], 50);
        $category = $this->categoryFromProduct($product);
        $imageUrl = $this->httpsUrl($this->firstText($product, ['image_front_url', 'image_url'], 500));
        $manufacturer = $this->firstText($product, ['manufacturing_places'], 150);

        return [
            'id' => 0,
            'name' => $name,
            'sku' => $barcode,
            'barcode' => $barcode,
            'category' => $category,
            'description' => $description,
            'brand' => $this->firstText($product, ['brands'], 150),
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
            'source' => 'open_food_facts',
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
        $categories = $this->firstText($product, ['categories'], 120);
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
}
