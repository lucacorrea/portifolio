<?php

declare(strict_types=1);

namespace App\Config;

use App\Core\Environment;

final class DatabaseConfig
{
    /** @return array{host:string,port:int,name:string,user:string,password:string,charset:string} */
    public static function values(): array
    {
        return [
            'host' => Environment::required('DB_HOST'),
            'port' => Environment::int('DB_PORT', 3306),
            'name' => Environment::required('DB_NAME'),
            'user' => Environment::required('DB_USER'),
            'password' => Environment::required('DB_PASSWORD'),
            'charset' => (string) Environment::get('DB_CHARSET', 'utf8mb4'),
        ];
    }

    public static function dsn(): string
    {
        $config = self::values();

        return sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config['host'],
            $config['port'],
            $config['name'],
            $config['charset']
        );
    }
}
