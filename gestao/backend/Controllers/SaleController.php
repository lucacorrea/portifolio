<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\SaleService;
use InvalidArgumentException;

final class SaleController
{
    private SaleService $service;

    public function __construct(?SaleService $service = null)
    {
        $this->service = $service ?? new SaleService();
    }

    public function list(): void
    {
        Auth::requireLogin();
        $empresaId = (int)Auth::user()['empresa_id'];

        Response::success($this->service->list($empresaId));
    }

    public function details(Request $request): void
    {
        Auth::requireLogin();
        $empresaId = (int)Auth::user()['empresa_id'];
        $id = (int)$request->query('id', 0);

        if ($id <= 0) {
            Response::fail('Venda inválida.', [], 422);
        }

        $sale = $this->service->details($empresaId, $id);

        if (!$sale) {
            Response::fail('Venda não encontrada.', [], 404);
        }

        Response::success($sale);
    }

    public function finish(Request $request): void
    {
        Auth::requireLogin();
        $this->validateCsrf($request);

        try {
            $user = Auth::user();
            $sale = $this->service->finalize((int)$user['empresa_id'], (int)$user['id'], $request->all());
            Response::success($sale);
        } catch (InvalidArgumentException $e) {
            Response::fail($e->getMessage(), [], 422);
        }
    }

    public function cancel(Request $request): void
    {
        Auth::requireLogin();
        $this->validateCsrf($request);

        try {
            $user = Auth::user();
            $this->service->cancel((int)$user['empresa_id'], (int)$user['id'], $request->all());
            Response::success();
        } catch (InvalidArgumentException $e) {
            Response::fail($e->getMessage(), [], 422);
        }
    }

    public function receipt(Request $request): void
    {
        $this->details($request);
    }

    private function validateCsrf(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('csrf_token', ''))) {
            Response::fail('Sessão expirada. Atualize a página e tente novamente.', [], 419);
        }
    }
}
