<?php
declare(strict_types=1);

namespace App\Services;

final class MenuService
{
    public static function items(): array
    {
        return [
            [
                'label' => 'Dashboard',
                'icon'  => 'home',
                'route' => \url('dashboard'),
                'key'   => 'dashboard',
            ],
            [
                'label' => 'Empresas',
                'icon'  => 'users',
                'route' => '#',
                'key'   => 'empresas',
            ],
            [
                'label' => 'Documentos',
                'icon'  => 'file',
                'route' => '#',
                'key'   => 'documentos',
            ],
            [
                'label' => 'Financeiro',
                'icon'  => 'money',
                'route' => '#',
                'key'   => 'financeiro',
            ],
            [
                'label' => 'Assinaturas',
                'icon'  => 'card',
                'route' => '#',
                'key'   => 'assinaturas',
            ],
            [
                'label' => 'Configurações',
                'icon'  => 'settings',
                'route' => '#',
                'key'   => 'configuracoes',
            ],
        ];
    }

    public static function isActive(string $active, string $key): bool
    {
        return $active === $key;
    }
}
