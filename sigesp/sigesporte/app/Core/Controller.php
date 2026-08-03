<?php
declare(strict_types=1);
namespace Sigesp\Core;
abstract class Controller
{
    protected function render(string $view, array $data = []): Response { return new Response(View::render($view, $data)); }
    protected function redirect(string $path, ?string $message = null): Response { if ($message) Flash::add('success', $message); return Response::redirect($path); }
    protected function requireAuth(): void { if (!Auth::check()) { Flash::add('error', 'Sua sessão expirou. Faça login novamente.'); header('Location: /login'); exit; } }
    protected function authorize(string $permission): void { $this->requireAuth(); if (!Auth::can($permission)) { http_response_code(403); echo View::render('errors/403', ['title' => 'Acesso negado']); exit; } }
}
