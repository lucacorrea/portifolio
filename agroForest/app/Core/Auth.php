<?php
class Auth
{
    public static function check(): bool
    {
        return !empty($_SESSION['user']);
    }
}
