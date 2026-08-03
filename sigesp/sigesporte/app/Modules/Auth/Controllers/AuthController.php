<?php
declare(strict_types=1);
namespace Sigesp\Modules\Auth\Controllers;
use Sigesp\Core\{Auth,Controller,Csrf,Flash,Request,Response,View};
use Sigesp\Modules\Auth\Services\AuthService;
final class AuthController extends Controller
{
    public function loginForm(Request $request): Response { if (Auth::check()) return Response::redirect('/dashboard'); return new Response(View::render('auth/login', ['title' => 'Entrar'], 'layouts/auth')); }
    public function login(Request $request): Response { if (!Csrf::validate($request->input('_token'))) { Flash::add('error', 'Solicitação expirada. Tente novamente.'); return Response::redirect('/login'); } if ((new AuthService())->attempt((string) $request->input('identificador'), (string) $request->input('senha'), $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')) return Response::redirect('/dashboard'); Flash::add('error', 'Não foi possível entrar com estas credenciais.'); return Response::redirect('/login'); }
    public function logout(Request $request): Response { if (Csrf::validate($request->input('_token'))) Auth::logout(); return Response::redirect('/login'); }
    public function forgot(Request $request): Response { return new Response(View::render('auth/forgot', ['title' => 'Recuperar acesso'], 'layouts/auth')); }
}
