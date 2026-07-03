<?php

declare(strict_types=1);

namespace App\Core;

final class Csrf
{
    private const SESSION_KEY = '_csrf_tokens';

    public static function token(string $form = 'default'): string
    {
        Session::start();

        if (empty($_SESSION[self::SESSION_KEY][$form])) {
            $_SESSION[self::SESSION_KEY][$form] = bin2hex(random_bytes(32));
        }

        return (string) $_SESSION[self::SESSION_KEY][$form];
    }

    public static function validate(?string $token, string $form = 'default'): bool
    {
        Session::start();

        if (!is_string($token) || trim($token) === '') {
            return false;
        }

        $stored = $_SESSION[self::SESSION_KEY][$form] ?? null;

        if (!is_string($stored) || $stored === '') {
            return false;
        }

        return hash_equals($stored, $token);
    }

    public static function validateAndConsume(?string $token, string $form = 'default'): bool
    {
        if (!self::validate($token, $form)) {
            return false;
        }

        unset($_SESSION[self::SESSION_KEY][$form]);

        return true;
    }

    public static function validateAndRotate(?string $token, string $form = 'default'): bool
    {
        if (!self::validate($token, $form)) {
            return false;
        }

        self::rotate($form);

        return true;
    }

    public static function rotate(string $form = 'default'): string
    {
        Session::start();
        $_SESSION[self::SESSION_KEY][$form] = bin2hex(random_bytes(32));

        return (string) $_SESSION[self::SESSION_KEY][$form];
    }

    public static function input(string $form = 'default'): string
    {
        $token = htmlspecialchars(self::token($form), ENT_QUOTES, 'UTF-8');

        return '<input type="hidden" name="_csrf" value="' . $token . '">';
    }
}
