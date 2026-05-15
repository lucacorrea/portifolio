<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Models\Usuario;
use App\Support\Pagination;
use Throwable;

final class UsuarioController
{
    private const ROLES = ['admin', 'tesoureiro', 'visualizador'];

    public function index(): Response
    {
        $usuarios = [];
        $summary = [
            'total' => 0,
            'ativos' => 0,
            'inativos' => 0,
        ];
        $paginationInput = Pagination::fromRequest($_GET);
        $pagination = Pagination::meta(0, $paginationInput['page'], $paginationInput['per_page']);
        $loadError = null;

        try {
            $igrejaId = (int) Session::get('igreja_id', 0);
            $usuario = new Usuario();
            $summary = $usuario->statusSummaryByChurch($igrejaId);
            $pagination = Pagination::meta((int) $summary['total'], $paginationInput['page'], $paginationInput['per_page']);
            $offset = ((int) $pagination['current_page'] - 1) * (int) $pagination['per_page'];
            $usuarios = $usuario->paginateByChurch($igrejaId, (int) $pagination['per_page'], $offset);
        } catch (Throwable) {
            $loadError = 'Não foi possível carregar os usuários agora.';
        }

        return Response::html(View::render('usuarios/index', [
            'title' => 'Usuários',
            'usuarios' => $usuarios,
            'summary' => $summary,
            'pagination' => $pagination,
            'loadError' => $loadError,
            'success' => Session::pullFlash('usuario_success'),
            'currentUserId' => (int) Session::get('user_id', 0),
        ]));
    }

    public function create(): Response
    {
        return Response::html(View::render('usuarios/create', [
            'title' => 'Cadastrar usuário',
            'roles' => self::ROLES,
            'error' => Session::pullFlash('usuario_error'),
            'old' => Session::pullFlash('usuario_old', []),
        ]));
    }

    public function store(): Response
    {
        $igrejaId = (int) Session::get('igreja_id', 0);
        $data = $this->sanitizePayload($_POST);
        $password = (string) ($_POST['senha'] ?? '');
        $error = null;

        if ($igrejaId <= 0) {
            $error = 'Sessão inválida. Entre novamente para continuar.';
        }

        if ($error === null) {
            try {
                $error = $this->validatePayload($data, $password, null);
            } catch (Throwable) {
                $error = 'Não foi possível validar os dados do usuário.';
            }
        }

        if ($error !== null) {
            Session::flash('usuario_error', $error);
            Session::flash('usuario_old', $data);

            return Response::redirect(url('/usuarios/criar'));
        }

        try {
            (new Usuario())->create([
                'igreja_id' => $igrejaId,
                'nome' => $data['nome'],
                'email' => $data['email'],
                'papel' => $data['papel'],
                'senha_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (Throwable) {
            Session::flash('usuario_error', 'Não foi possível cadastrar o usuário. Verifique se o email já existe.');
            Session::flash('usuario_old', $data);

            return Response::redirect(url('/usuarios/criar'));
        }

        Session::flash('usuario_success', 'Usuário cadastrado com sucesso.');

        return Response::redirect(url('/usuarios'));
    }

    public function edit(): Response
    {
        $igrejaId = (int) Session::get('igreja_id', 0);
        $id = (int) ($_GET['id'] ?? 0);
        $usuario = null;

        try {
            $usuario = $id > 0 ? (new Usuario())->findByChurch($id, $igrejaId) : null;
        } catch (Throwable) {
            $usuario = null;
        }

        if ($usuario === null) {
            Session::flash('usuario_error', 'Usuário não encontrado.');

            return Response::redirect(url('/usuarios'));
        }

        return Response::html(View::render('usuarios/edit', [
            'title' => 'Editar usuário',
            'usuario' => $usuario,
            'roles' => self::ROLES,
            'error' => Session::pullFlash('usuario_error'),
            'old' => Session::pullFlash('usuario_old', []),
            'currentUserId' => (int) Session::get('user_id', 0),
        ]));
    }

    public function update(): Response
    {
        $igrejaId = (int) Session::get('igreja_id', 0);
        $id = (int) ($_POST['id'] ?? 0);
        $data = $this->sanitizePayload($_POST);
        $data['ativo'] = isset($_POST['ativo']) ? 1 : 0;
        $password = (string) ($_POST['senha'] ?? '');
        $usuarioModel = new Usuario();
        $usuario = null;

        try {
            $usuario = $id > 0 ? $usuarioModel->findByChurch($id, $igrejaId) : null;
        } catch (Throwable) {
            $usuario = null;
        }

        if ($usuario === null) {
            Session::flash('usuario_error', 'Usuário não encontrado.');

            return Response::redirect(url('/usuarios'));
        }

        if ($id === (int) Session::get('user_id', 0)) {
            $data['ativo'] = 1;
            $data['papel'] = (string) $usuario['papel'];
        }

        try {
            $error = $this->validatePayload($data, $password, $id, true);
        } catch (Throwable) {
            $error = 'Não foi possível validar os dados do usuário.';
        }

        if ($error !== null) {
            Session::flash('usuario_error', $error);
            Session::flash('usuario_old', $data);

            return Response::redirect(url('/usuarios/editar?id=' . $id));
        }

        try {
            $usuarioModel->updateProfile($id, $igrejaId, $data);

            if ($password !== '') {
                $usuarioModel->updatePasswordHash($id, $igrejaId, password_hash($password, PASSWORD_DEFAULT));
            }
        } catch (Throwable) {
            Session::flash('usuario_error', 'Não foi possível atualizar o usuário. Verifique os dados e tente novamente.');
            Session::flash('usuario_old', $data);

            return Response::redirect(url('/usuarios/editar?id=' . $id));
        }

        if ($id === (int) Session::get('user_id', 0)) {
            Session::put('user_name', $data['nome']);
            Session::put('user_email', $data['email']);
            Session::put('user_role', $data['papel']);
        }

        Session::flash('usuario_success', 'Usuário atualizado com sucesso.');

        return Response::redirect(url('/usuarios'));
    }

    public function deactivate(): Response
    {
        $id = (int) ($_POST['id'] ?? 0);
        $currentUserId = (int) Session::get('user_id', 0);

        if ($id <= 0 || $id === $currentUserId) {
            Session::flash('usuario_error', 'Não é possível desativar este usuário.');

            return Response::redirect(url('/usuarios'));
        }

        try {
            (new Usuario())->setActive($id, (int) Session::get('igreja_id', 0), false);
            Session::flash('usuario_success', 'Usuário desativado com sucesso.');
        } catch (Throwable) {
            Session::flash('usuario_error', 'Não foi possível desativar o usuário.');
        }

        return Response::redirect(url('/usuarios'));
    }

    public function activate(): Response
    {
        $id = (int) ($_POST['id'] ?? 0);

        if ($id <= 0) {
            Session::flash('usuario_error', 'Usuário inválido.');

            return Response::redirect(url('/usuarios'));
        }

        try {
            (new Usuario())->setActive($id, (int) Session::get('igreja_id', 0), true);
            Session::flash('usuario_success', 'Usuário ativado com sucesso.');
        } catch (Throwable) {
            Session::flash('usuario_error', 'Não foi possível ativar o usuário.');
        }

        return Response::redirect(url('/usuarios'));
    }

    private function sanitizePayload(array $payload): array
    {
        return [
            'nome' => substr(trim((string) ($payload['nome'] ?? '')), 0, 180),
            'email' => strtolower(substr(trim((string) ($payload['email'] ?? '')), 0, 180)),
            'papel' => trim((string) ($payload['papel'] ?? '')),
        ];
    }

    private function validatePayload(array $data, string $password, ?int $ignoreId = null, bool $passwordOptional = false): ?string
    {
        if ($data['nome'] === '') {
            return 'Informe o nome do usuário.';
        }

        if (filter_var($data['email'], FILTER_VALIDATE_EMAIL) === false) {
            return 'Informe um email válido.';
        }

        if (!in_array($data['papel'], self::ROLES, true)) {
            return 'Selecione um papel válido.';
        }

        if ((!$passwordOptional || $password !== '') && strlen($password) < 8) {
            return 'A senha deve ter pelo menos 8 caracteres.';
        }

        if ((new Usuario())->emailExists($data['email'], $ignoreId)) {
            return 'Este email já está cadastrado.';
        }

        return null;
    }
}
