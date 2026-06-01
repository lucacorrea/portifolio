<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Security\Csrf;
use App\Services\ClientService;
use InvalidArgumentException;

final class ClientController
{
    private ClientService $service;

    public function __construct(?ClientService $service = null)
    {
        $this->service = $service ?? new ClientService();
    }

    public function list(Request $request): void
    {
        Auth::requireLogin();
        $empresaId = (int)Auth::user()['empresa_id'];

        Response::success($this->service->list($empresaId, (string)$request->query('q', '')));
    }

    public function details(Request $request): void
    {
        Auth::requireLogin();
        $empresaId = (int)Auth::user()['empresa_id'];
        $id = (int)$request->query('id', 0);

        if ($id <= 0) {
            Response::fail('Cliente inválido.', [], 422);
        }

        $client = $this->service->details($empresaId, $id);

        if (!$client) {
            Response::fail('Cliente não encontrado.', [], 404);
        }

        Response::success($client);
    }

    public function save(Request $request): void
    {
        Auth::requireLogin();
        $this->validateCsrf($request);

        try {
            $empresaId = (int)Auth::user()['empresa_id'];
            Response::success($this->service->save($empresaId, $request->all()));
        } catch (InvalidArgumentException $e) {
            Response::fail($e->getMessage(), [], 422);
        }
    }

    public function payment(Request $request): void
    {
        Auth::requireLogin();
        $this->validateCsrf($request);

        try {
            $user = Auth::user();
            $this->service->registerPayment((int)$user['empresa_id'], (int)$user['id'], $request->all());
            Response::success();
        } catch (InvalidArgumentException $e) {
            Response::fail($e->getMessage(), [], 422);
        }
    }

    public function warning(Request $request): void
    {
        Auth::requireLogin();
        $empresaId = (int)Auth::user()['empresa_id'];
        $id = (int)$request->query('id', $request->input('id', 0));

        if ($id <= 0) {
            Response::fail('Cliente inválido.', [], 422);
        }

        $warning = $this->service->warningMessage($empresaId, $id);

        if (!$warning) {
            Response::fail('Cliente não encontrado.', [], 404);
        }

        Response::success($warning);
    }

    private function validateCsrf(Request $request): void
    {
        if (!Csrf::validate((string)$request->input('csrf_token', ''))) {
            Response::fail('Sessão expirada. Atualize a página e tente novamente.', [], 419);
        }
    }
}
