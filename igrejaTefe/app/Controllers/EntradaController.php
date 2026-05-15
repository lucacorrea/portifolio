<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Entrada;
use App\Support\Pagination;
use DateTimeImmutable;
use Throwable;

final class EntradaController
{
    public function index(): Response
    {
        $igrejaId = (int) Session::get('igreja_id', 0);
        $entradas = [];
        $summary = [
            'total' => 0,
            'quantidade' => 0,
        ];
        $paginationInput = Pagination::fromRequest($_GET);
        $pagination = Pagination::meta(0, $paginationInput['page'], $paginationInput['per_page']);
        $loadError = null;

        if ($igrejaId > 0) {
            try {
                $entrada = new Entrada();
                $total = $entrada->countByChurch($igrejaId);
                $pagination = Pagination::meta($total, $paginationInput['page'], $paginationInput['per_page']);
                $offset = ((int) $pagination['current_page'] - 1) * (int) $pagination['per_page'];
                $entradas = $entrada->paginateByChurch($igrejaId, (int) $pagination['per_page'], $offset);
                $summary = $entrada->currentMonthSummary($igrejaId);
            } catch (Throwable) {
                $loadError = 'Não foi possível carregar as entradas agora.';
            }
        }

        return Response::html(View::render('entradas/index', [
            'title' => 'Entradas',
            'entradas' => $entradas,
            'summary' => $summary,
            'pagination' => $pagination,
            'loadError' => $loadError,
            'success' => Session::pullFlash('entrada_success'),
        ]));
    }

    public function create(): Response
    {
        return Response::html(View::render('entradas/create', [
            'title' => 'Cadastrar entrada',
            'today' => date('Y-m-d'),
            'error' => Session::pullFlash('entrada_error'),
            'old' => Session::pullFlash('entrada_old', []),
        ]));
    }

    public function store(): Response
    {
        $igrejaId = (int) Session::get('igreja_id', 0);
        $usuarioId = (int) Session::get('user_id', 0);
        $data = $this->sanitizePayload($_POST);
        $error = $this->validatePayload($data);

        if ($igrejaId <= 0 || $usuarioId <= 0) {
            $error = 'Sessão inválida. Entre novamente para continuar.';
        }

        if ($error !== null) {
            Session::flash('entrada_error', $error);
            Session::flash('entrada_old', $data);

            return Response::redirect(url('/entradas/criar'));
        }

        try {
            (new Entrada())->create([
                'igreja_id' => $igrejaId,
                'usuario_id' => $usuarioId,
                'tipo' => $data['tipo'],
                'valor' => $data['valor'],
                'descricao' => $data['descricao'],
                'contribuinte_nome' => $data['contribuinte_nome'],
                'forma_pagamento' => $data['forma_pagamento'],
                'data_entrada' => $data['data_entrada'],
            ]);
        } catch (Throwable) {
            Session::flash('entrada_error', 'Não foi possível salvar a entrada. Revise os dados e tente novamente.');
            Session::flash('entrada_old', $data);

            return Response::redirect(url('/entradas/criar'));
        }

        Session::flash('entrada_success', 'Entrada cadastrada com sucesso.');

        return Response::redirect(url('/entradas'));
    }

    private function sanitizePayload(array $payload): array
    {
        return [
            'tipo' => trim((string) ($payload['tipo'] ?? '')),
            'valor' => (float) str_replace(',', '.', (string) ($payload['valor'] ?? '0')),
            'data_entrada' => trim((string) ($payload['data_entrada'] ?? '')),
            'forma_pagamento' => trim((string) ($payload['forma_pagamento'] ?? '')) ?: null,
            'contribuinte_nome' => substr(trim((string) ($payload['contribuinte_nome'] ?? '')), 0, 180) ?: null,
            'descricao' => trim((string) ($payload['descricao'] ?? '')) ?: null,
        ];
    }

    private function validatePayload(array $data): ?string
    {
        if (!in_array($data['tipo'], ['dizimo', 'oferta'], true)) {
            return 'Selecione um tipo de entrada válido.';
        }

        if ((float) $data['valor'] <= 0) {
            return 'Informe um valor maior que zero.';
        }

        if (!$this->isValidDate((string) $data['data_entrada'])) {
            return 'Informe uma data válida.';
        }

        $formas = ['dinheiro', 'pix', 'cartao', 'transferencia', 'outro'];

        if ($data['forma_pagamento'] !== null && !in_array($data['forma_pagamento'], $formas, true)) {
            return 'Selecione uma forma de pagamento válida.';
        }

        return null;
    }

    private function isValidDate(string $date): bool
    {
        $parsed = DateTimeImmutable::createFromFormat('Y-m-d', $date);

        return $parsed instanceof DateTimeImmutable && $parsed->format('Y-m-d') === $date;
    }
}
