<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Validator;
use App\Repositories\CategoryRepository;
use App\Repositories\ProductRepository;
use InvalidArgumentException;
use PDOException;

final class ProductService
{
    private ProductRepository $products;
    private CategoryRepository $categories;

    public function __construct(?ProductRepository $products = null, ?CategoryRepository $categories = null)
    {
        $this->products = $products ?? new ProductRepository();
        $this->categories = $categories ?? new CategoryRepository();
    }

    public function list(int $empresaId, string $query = '', ?bool $active = true): array
    {
        return $this->products->findAll($empresaId, $query, $active);
    }

    public function find(int $empresaId, int $id): ?array
    {
        return $this->products->findById($empresaId, $id);
    }

    public function findWithStatus(int $empresaId, int $id, ?bool $active = true): ?array
    {
        return $this->products->findByIdWithStatus($empresaId, $id, $active);
    }

    public function findByCode(int $empresaId, string $code): ?array
    {
        return $this->products->findByCode($empresaId, $code);
    }

    public function save(int $empresaId, array $payload): array
    {
        $id = (int)($payload['id'] ?? 0);
        $name = $this->cleanText((string)($payload['name'] ?? $payload['nome'] ?? ''), 180);
        $category = $this->cleanText((string)($payload['category'] ?? $payload['categoria'] ?? ''), 120);
        $expiry = trim((string)($payload['expiry'] ?? $payload['validade'] ?? ''));
        $image = trim((string)($payload['image'] ?? ''));
        $sku = $this->cleanText((string)($payload['sku'] ?? ''), 80);
        $barcode = $this->normalizeBarcode((string)($payload['barcode'] ?? $payload['codigo_barras'] ?? ''));
        $lot = $this->cleanText((string)($payload['lot'] ?? $payload['lote'] ?? ''), 80);
        $description = $this->cleanText((string)($payload['description'] ?? ''), 65535);
        $brand = $this->cleanText((string)($payload['brand'] ?? ''), 150);
        $unit = strtoupper($this->cleanText((string)($payload['unit'] ?? ''), 20));
        $packageQuantity = $this->cleanText((string)($payload['packageQuantity'] ?? ''), 50);
        $ncm = preg_replace('/\D+/', '', (string)($payload['ncm'] ?? '')) ?? '';
        $cest = preg_replace('/\D+/', '', (string)($payload['cest'] ?? '')) ?? '';
        $manufacturer = $this->cleanText((string)($payload['manufacturer'] ?? ''), 150);
        $source = $this->normalizeSource((string)($payload['source'] ?? 'manual'));
        $externalImageUrl = $this->cleanText((string)($payload['externalImageUrl'] ?? ''), 500);

        if (!Validator::required($name) || !Validator::max($name, 180)) {
            throw new InvalidArgumentException('Informe um nome de produto válido.');
        }

        if (!Validator::required($category) || !Validator::max($category, 120)) {
            throw new InvalidArgumentException('Informe uma categoria válida.');
        }

        if (!Validator::max($sku, 80)) {
            throw new InvalidArgumentException('O código interno deve ter no máximo 80 caracteres.');
        }

        if ($barcode !== '' && !Validator::max($barcode, 80)) {
            throw new InvalidArgumentException('O código de barras deve ter no máximo 80 caracteres.');
        }

        if ($barcode !== '') {
            $duplicate = $this->products->findByBarcode($empresaId, $barcode, $id > 0 ? $id : null);
            if ($duplicate !== null) {
                throw new InvalidArgumentException('Este código de barras já está cadastrado em outro produto.');
            }
        }

        if ($sku === '') {
            $sku = $this->generateSku($empresaId, $id);
        }

        if ($lot !== '' && !Validator::max($lot, 80)) {
            throw new InvalidArgumentException('O lote deve ter no máximo 80 caracteres.');
        }

        if ($expiry !== '' && !Validator::date($expiry)) {
            throw new InvalidArgumentException('Informe uma validade válida.');
        }

        $cost = $payload['cost'] ?? 0;
        if ($cost === '') {
            $cost = 0;
        }

        if (!Validator::decimal($cost)) {
            throw new InvalidArgumentException('O preço de custo deve ser numérico.');
        }

        foreach (['price', 'stock', 'minStock'] as $field) {
            if (!Validator::decimal($payload[$field] ?? 0)) {
                throw new InvalidArgumentException('Valores de preço e estoque devem ser numéricos.');
            }
        }

        if ($image !== '' && (!Validator::max($image, 255) || preg_match('/(^|\/)\.\.(\/|$)|[<>:"\\\\|?*]/', $image))) {
            throw new InvalidArgumentException('Imagem do produto inválida.');
        }

        if ($unit !== '' && !preg_match('/^[A-Z0-9 .\/_-]{1,20}$/', $unit)) {
            throw new InvalidArgumentException('Informe uma unidade válida.');
        }

        if ($ncm !== '' && strlen($ncm) > 8) {
            throw new InvalidArgumentException('NCM deve conter até 8 dígitos.');
        }

        if ($cest !== '' && strlen($cest) > 7) {
            throw new InvalidArgumentException('CEST deve conter até 7 dígitos.');
        }

        if ($externalImageUrl !== '' && (!Validator::max($externalImageUrl, 500) || filter_var($externalImageUrl, FILTER_VALIDATE_URL) === false)) {
            throw new InvalidArgumentException('URL da imagem externa inválida.');
        }

        $categoryId = $this->categories->findOrCreate($empresaId, $category);
        $data = [
            'categoria_id' => $categoryId,
            'nome' => $name,
            'sku' => $sku,
            'codigo_barras' => $barcode,
            'lote' => $lot,
            'validade' => $expiry,
            'quantidade' => (float)($payload['stock'] ?? 0),
            'estoque_minimo' => (float)($payload['minStock'] ?? 0),
            'preco_custo' => (float)$cost,
            'preco_venda' => (float)($payload['price'] ?? 0),
            'imagem' => $image,
            'descricao' => $description,
            'marca' => $brand,
            'unidade' => $unit,
            'quantidade_embalagem' => $packageQuantity,
            'ncm' => $ncm,
            'cest' => $cest,
            'fabricante' => $manufacturer,
            'origem_dados' => $source,
            'url_imagem_origem' => $externalImageUrl,
        ];

        try {
            if ($id > 0) {
                $this->products->update($empresaId, $id, $data);
            } else {
                $id = $this->products->create($empresaId, $data);
            }
        } catch (PDOException $e) {
            $errorMessage = $e->getMessage();
            if ($e->getCode() === '23000') {
                if (str_contains($errorMessage, 'codigo_barras') || str_contains($errorMessage, 'uk_produtos_empresa_codigo_barras')) {
                    throw new InvalidArgumentException('Este código de barras já está cadastrado em outro produto.');
                }

                throw new InvalidArgumentException('Já existe um produto com este SKU nesta empresa.');
            }

            throw $e;
        }

        return $this->products->findById($empresaId, $id) ?? [];
    }

    public function inactivate(int $empresaId, int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Produto inválido.');
        }

        $this->products->inactivate($empresaId, $id);
    }

    public function activate(int $empresaId, int $id): void
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Produto inválido.');
        }

        $this->products->activate($empresaId, $id);
    }

    public function deleteInactive(int $empresaId, int $id): array
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Produto inválido.');
        }

        $product = $this->products->findByIdWithStatus($empresaId, $id, null);
        if ($product === null) {
            throw new InvalidArgumentException('Produto não encontrado.');
        }

        if (($product['active'] ?? true) === true) {
            throw new InvalidArgumentException('Inative o produto antes de excluir definitivamente.');
        }

        if ($this->products->hasSaleHistory($empresaId, $id)) {
            throw new InvalidArgumentException('Este produto possui vendas vinculadas. Mantenha inativo para preservar o histórico.');
        }

        if (!$this->products->deleteInactive($empresaId, $id)) {
            throw new InvalidArgumentException('Não foi possível excluir o produto inativo. Atualize a lista e tente novamente.');
        }

        return $product;
    }

    private function normalizeBarcode(string $barcode): string
    {
        $barcode = preg_replace('/[\x00-\x1F\x7F\s-]+/u', '', $barcode) ?? '';
        $barcode = trim($barcode);

        return mb_substr($barcode, 0, 80);
    }

    private function generateSku(int $empresaId, int $productId): string
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $suffix = strtoupper(bin2hex(random_bytes($attempt < 3 ? 3 : 4)));
            $sku = 'AUTO-' . $empresaId . '-' . $suffix;
            if ($this->products->findByCode($empresaId, $sku) === null) {
                return mb_substr($sku, 0, 80);
            }
        }

        return mb_substr('AUTO-' . $empresaId . '-' . ($productId > 0 ? $productId : time()) . '-' . bin2hex(random_bytes(2)), 0, 80);
    }

    private function cleanText(string $value, int $maxLength): string
    {
        $value = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]+/u', '', $value) ?? '';
        $value = trim($value);

        return mb_substr($value, 0, $maxLength);
    }

    private function normalizeSource(string $source): string
    {
        $source = trim($source);

        return in_array($source, ['manual', 'open_food_facts', 'cosmos'], true) ? $source : 'manual';
    }
}
