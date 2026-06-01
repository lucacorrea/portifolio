<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Security\Auth;
use App\Services\ReportService;
use InvalidArgumentException;

final class ReportController
{
    private ReportService $service;

    public function __construct(?ReportService $service = null)
    {
        $this->service = $service ?? new ReportService();
    }

    public function summary(Request $request): void
    {
        Auth::requireLogin();

        try {
            Response::success($this->service->summary((int)Auth::user()['empresa_id'], $request->all()));
        } catch (InvalidArgumentException $e) {
            Response::fail($e->getMessage(), [], 422);
        }
    }

    public function sales(Request $request): void
    {
        Auth::requireLogin();

        try {
            Response::success($this->service->sales((int)Auth::user()['empresa_id'], $request->all()));
        } catch (InvalidArgumentException $e) {
            Response::fail($e->getMessage(), [], 422);
        }
    }

    public function products(Request $request): void
    {
        Auth::requireLogin();

        try {
            Response::success($this->service->products((int)Auth::user()['empresa_id'], $request->all()));
        } catch (InvalidArgumentException $e) {
            Response::fail($e->getMessage(), [], 422);
        }
    }

    public function validity(): void
    {
        Auth::requireLogin();

        Response::success($this->service->validity((int)Auth::user()['empresa_id']));
    }
}
