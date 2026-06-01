<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\ProductService;
use InvalidArgumentException;

final class ProductController
{
    private ProductService $service;

    public function __construct(?ProductService $service = null)
    {
        $this->service = $service ?? new ProductService();
    }

    public function list(Request $request): void
    {
        Auth::requireLogin();
        $empresaId = (int)Auth::user()['empresa_id'];

        Response::success($this->service->list($empresaId, (string)$request->query('q', '')));
    }

    public function search(Request $request): void
    {
        Auth::requireLogin();
        $empresaId = (int)Auth::user()['empresa_id'];
        $code = trim((string)$request->query('code', $request->query('q', '')));

        if ($code === '') {
            Response::fail('Informe o código do produto.', [], 422);
        }

        $product = $this->service->findByCode($empresaId, $code);

        if (!$product) {
            Response::fail('Produto não encontrado.', [], 404);
        }

        Response::success($product);
    }

    public function save(Request $request): void
    {
        Auth::requireLogin();
        $this->validateCsrf($request);

        try {
            $empresaId = (int)Auth::user()['empresa_id'];
            $payload = $request->all();
            $payload['image'] = $this->storeProductImage($empresaId, $payload['image'] ?? '');

            Response::success($this->service->save($empresaId, $payload));
        } catch (InvalidArgumentException $e) {
            Response::fail($e->getMessage(), [], 422);
        }
    }

    public function delete(Request $request): void
    {
        Auth::requireLogin();
        $this->validateCsrf($request);

        try {
            $empresaId = (int)Auth::user()['empresa_id'];
            $this->service->inactivate($empresaId, (int)$request->input('id', 0));
            Response::success();
        } catch (InvalidArgumentException $e) {
            Response::fail($e->getMessage(), [], 422);
        }
    }

    private function validateCsrf(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('csrf_token', ''))) {
            Response::fail('Sessão expirada. Atualize a página e tente novamente.', [], 419);
        }
    }

    private function storeProductImage(int $empresaId, mixed $currentImage): string
    {
        $image = trim((string)$currentImage);

        if (!isset($_FILES['imageFile'])) {
            return $image;
        }

        $file = $_FILES['imageFile'];

        if (!is_array($file) || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
            return $image;
        }

        if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException('Não foi possível receber a imagem do produto.');
        }

        if ((int)($file['size'] ?? 0) > 2 * 1024 * 1024) {
            throw new InvalidArgumentException('A imagem deve ter no máximo 2MB.');
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $mime = is_file($tmpName) ? (new \finfo(FILEINFO_MIME_TYPE))->file($tmpName) : '';
        $extensions = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            'image/gif' => 'gif',
        ];

        if (!isset($extensions[$mime])) {
            throw new InvalidArgumentException('Formato de imagem inválido.');
        }

        $dir = BASE_PATH . '/uploads/produtos';

        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new InvalidArgumentException('Não foi possível preparar o diretório de uploads.');
        }

        $filename = sprintf('empresa-%d-%s.%s', $empresaId, bin2hex(random_bytes(8)), $extensions[$mime]);
        $target = $dir . '/' . $filename;

        if (!move_uploaded_file($tmpName, $target)) {
            throw new InvalidArgumentException('Não foi possível salvar a imagem do produto.');
        }

        return 'uploads/produtos/' . $filename;
    }
}
