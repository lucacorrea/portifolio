<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\ProductService;
use App\Services\UploadService;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ProductController
{
    private ProductService $service;
    private UploadService $uploads;

    public function __construct(?ProductService $service = null, ?UploadService $uploads = null)
    {
        $this->service = $service ?? new ProductService();
        $this->uploads = $uploads ?? new UploadService();
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

        $uploadedAbsolutePath = null;

        try {
            $empresaId = (int)Auth::user()['empresa_id'];
            $payload = $request->all();
            [$image, $uploadedAbsolutePath] = $this->uploads->storeProductImage(
                $empresaId,
                is_array($_FILES['imageFile'] ?? null) ? $_FILES['imageFile'] : null,
                (string)($payload['image'] ?? '')
            );
            $payload['image'] = $image;

            Response::success($this->service->save($empresaId, $payload));
        } catch (InvalidArgumentException | RuntimeException $e) {
            $this->uploads->removeProductUpload($uploadedAbsolutePath);
            Response::fail($e->getMessage(), [], 422);
        } catch (Throwable $e) {
            $this->uploads->removeProductUpload($uploadedAbsolutePath);
            throw $e;
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

}
