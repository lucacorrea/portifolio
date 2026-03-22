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
                'label' => 'Clientes',
                'icon'  => 'users',
                'key'   => 'clientes',
                'children' => [
                    [
                        'label' => 'Lista de clientes',
                        'route' => '#',
                        'key'   => 'clientes.lista',
                    ],
                    [
                        'label' => 'Novo cliente',
                        'route' => '#',
                        'key'   => 'clientes.create',
                    ],
                ],
            ],
            [
                'label' => 'Documentos',
                'icon'  => 'file',
                'key'   => 'documentos',
                'children' => [
                    [
                        'label' => 'Todos',
                        'route' => '#',
                        'key'   => 'documentos.lista',
                    ],
                    [
                        'label' => 'Upload',
                        'route' => '#',
                        'key'   => 'documentos.upload',
                    ],
                ],
            ],
            [
                'label' => 'Financeiro',
                'icon'  => 'money',
                'key'   => 'financeiro',
                'children' => [
                    [
                        'label' => 'Visão geral',
                        'route' => '#',
                        'key'   => 'financeiro.dashboard',
                    ],
                    [
                        'label' => 'Receitas',
                        'route' => '#',
                        'key'   => 'financeiro.receitas',
                    ],
                    [
                        'label' => 'Despesas',
                        'route' => '#',
                        'key'   => 'financeiro.despesas',
                    ],
                ],
            ],
            [
                'label' => 'Assinaturas',
                'icon'  => 'card',
                'key'   => 'assinaturas',
                'children' => [
                    [
                        'label' => 'Planos',
                        'route' => '#',
                        'key'   => 'assinaturas.planos',
                    ],
                    [
                        'label' => 'Faturas',
                        'route' => '#',
                        'key'   => 'assinaturas.faturas',
                    ],
                    [
                        'label' => 'Pagamentos',
                        'route' => '#',
                        'key'   => 'assinaturas.pagamentos',
                    ],
                ],
            ],
            [
                'label' => 'Configurações',
                'icon'  => 'settings',
                'key'   => 'configuracoes',
                'children' => [
                    [
                        'label' => 'Usuários',
                        'route' => '#',
                        'key'   => 'configuracoes.usuarios',
                    ],
                    [
                        'label' => 'Empresa',
                        'route' => '#',
                        'key'   => 'configuracoes.empresa',
                    ],
                ],
            ],
        ];
    }

    public static function isActive(string $active, string $key): bool
    {
        return $active === $key || strpos($active, $key . '.') === 0;
    }
}