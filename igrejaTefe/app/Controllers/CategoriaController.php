<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Categoria;
use Throwable;

final class CategoriaController
{
    public function index(): Response
    {
        $categorias = [];
        $loadError = null;

        try {
            $categorias = (new Categoria())->listByChurch((int) Session::get('igreja_id', 0));
        } catch (Throwable) {
            $loadError = 'Não foi possível carregar as categorias agora.';
        }

        return Response::html(View::render('categorias/index', [
            'title' => 'Categorias',
            'categorias' => $categorias,
            'loadError' => $loadError,
            'success' => Session::pullFlash('categoria_success'),
        ]));
    }

    public function create(): Response
    {
        return Response::html(View::render('categorias/create', [
            'title' => 'Cadastrar categoria',
            'error' => Session::pullFlash('categoria_error'),
            'old' => Session::pullFlash('categoria_old', []),
        ]));
    }

    public function store(): Response
    {
        $igrejaId = (int) Session::get('igreja_id', 0);
        $data = [
            'nome' => substr(trim((string) ($_POST['nome'] ?? '')), 0, 120),
            'descricao' => trim((string) ($_POST['descricao'] ?? '')) ?: null,
            'cor' => strtoupper(trim((string) ($_POST['cor'] ?? '#2FAF8F'))),
        ];

        $error = $this->validatePayload($data);

        if ($igrejaId <= 0) {
            $error = 'Sessão inválida. Entre novamente para continuar.';
        }

        if ($error !== null) {
            Session::flash('categoria_error', $error);
            Session::flash('categoria_old', $data);

            return Response::redirect(url('/categorias/criar'));
        }

        try {
            (new Categoria())->create([
                'igreja_id' => $igrejaId,
                'nome' => $data['nome'],
                'descricao' => $data['descricao'],
                'cor' => $data['cor'],
            ]);
        } catch (Throwable) {
            Session::flash('categoria_error', 'Não foi possível salvar a categoria. Verifique se o nome já existe.');
            Session::flash('categoria_old', $data);

            return Response::redirect(url('/categorias/criar'));
        }

        Session::flash('categoria_success', 'Categoria cadastrada com sucesso.');

        return Response::redirect(url('/categorias'));
    }

    private function validatePayload(array $data): ?string
    {
        if ($data['nome'] === '') {
            return 'Informe o nome da categoria.';
        }

        if (preg_match('/^#[0-9A-F]{6}$/', $data['cor']) !== 1) {
            return 'Informe uma cor válida.';
        }

        return null;
    }
}
