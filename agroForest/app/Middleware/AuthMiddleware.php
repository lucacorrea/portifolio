<?php
class AuthMiddleware
{
    public static function handle(): void
    {
        if (!Auth::check()) {
            flash_set('error', 'Faça login para acessar o sistema.');
            header('Location: ' . route_url('auth', 'login'));
            exit;
        }
    }
}
