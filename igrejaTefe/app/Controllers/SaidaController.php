<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Categoria;
use App\Models\Saida;
use DateTimeImmutable;
use Throwable;

final class SaidaController
{
    public function index(): Response
    {
        $igrejaId = (int) Session::get('igreja_id', 0);
        $saidas = [];
        $summary = [
            'total' => 0,
            'quantidade' => 0,
        ];
        $loadError = null;

        if ($igrejaId > 0) {
            try {
                $saida = new Saida();
                $saidas = $saida->listLatestByChurch($igrejaId);
                $summary = $saida->currentMonthSummary($igrejaId);
            } catch (Throwable) {
                $loadError = 'Não foi possível carregar as saídas agora.';
            }
        }

        return Response::html(View::render('saidas/index', [
            'title' => 'Saídas',
            'saidas' => $saidas,
            'summary' => $summary,
            'loadError' => $loadError,
            'success' => Session::pullFlash('saida_success'),
        ]));
    }

    public function create(): Response
    {
        $categorias = [];

        try {
            $categorias = (new Categoria())->listByChurch((int) Session::get('igreja_id', 0));
        } catch (Throwable) {
            $categorias = [];
        }

        return Response::html(View::render('saidas/create', [
            'title' => 'Cadastrar saída',
            'today' => date('Y-m-d'),
            'categorias' => array_values(array_filter($categorias, static fn (array $categoria): bool => (int) $categoria['ativo'] === 1)),
            'error' => Session::pullFlash('saida_error'),
            'old' => Session::pullFlash('saida_old', []),
        ]));
    }

    public function store(): Response
    {
        $igrejaId = (int) Session::get('igreja_id', 0);
        $usuarioId = (int) Session::get('user_id', 0);
        $data = $this->sanitizePayload($_POST);
        $error = null;

        if ($igrejaId <= 0 || $usuarioId <= 0) {
            $error = 'Sessão inválida. Entre novamente para continuar.';
        }

        if ($error === null) {
            try {
                $error = $this->validatePayload($data, $igrejaId);
            } catch (Throwable) {
                $error = 'Não foi possível validar a categoria selecionada.';
            }
        }

        if ($error !== null) {
            Session::flash('saida_error', $error);
            Session::flash('saida_old', $data);

            return Response::redirect(url('/saidas/criar'));
        }

        try {
            (new Saida())->create([
                'igreja_id' => $igrejaId,
                'usuario_id' => $usuarioId,
                'categoria_id' => $data['categoria_id'],
                'valor' => $data['valor'],
                'descricao' => $data['descricao'],
                'fornecedor' => $data['fornecedor'],
                'forma_pagamento' => $data['forma_pagamento'],
                'data_saida' => $data['data_saida'],
            ]);
        } catch (Throwable) {
            Session::flash('saida_error', 'Não foi possível salvar a saída. Revise os dados e tente novamente.');
            Session::flash('saida_old', $data);

            return Response::redirect(url('/saidas/criar'));
        }

        Session::flash('saida_success', 'Saída cadastrada com sucesso.');

        return Response::redirect(url('/saidas'));
    }

    private function sanitizePayload(array $payload): array
    {
        return [
            'categoria_id' => (int) ($payload['categoria_id'] ?? 0),
            'valor' => (float) str_replace(',', '.', (string) ($payload['valor'] ?? '0')),
            'data_saida' => trim((string) ($payload['data_saida'] ?? '')),
            'forma_pagamento' => trim((string) ($payload['forma_pagamento'] ?? '')) ?: null,
            'fornecedor' => substr(trim((string) ($payload['fornecedor'] ?? '')), 0, 180) ?: null,
            'descricao' => trim((string) ($payload['descricao'] ?? '')) ?: null,
        ];
    }

    private function validatePayload(array $data, int $igrejaId): ?string
    {
        if ($data['categoria_id'] <= 0 || (new Categoria())->findActiveByChurch((int) $data['categoria_id'], $igrejaId) === null) {
            return 'Selecione uma categoria válida.';
        }

        if ((float) $data['valor'] <= 0) {
            return 'Informe um valor maior que zero.';
        }

        if (!$this->isValidDate((string) $data['data_saida'])) {
            return 'Informe uma data válida.';
        }

        $formas = ['dinheiro', 'pix', 'cartao', 'transferencia', 'boleto', 'outro'];

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
