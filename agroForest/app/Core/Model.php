<?php
abstract class Model
{
    public static function db(): PDO
    {
        return Database::pdo();
    }

    public static function config(): array
    {
        return Database::config();
    }
}
