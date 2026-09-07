<?php

declare(strict_types=1);

namespace App\Config;

final class AccessModuleCatalog
{
    /**
     * Módulos em que um usuário comum pode receber exceções individuais.
     * Governança fica fora deste catálogo para impedir elevação acidental de privilégio.
     *
     * @return array<string,array{label:string,permission_module:string,view_permission:string}>
     */
    public static function operational(): array
    {
        return [
            'kit-maternidade' => [
                'label' => 'Kit Maternidade',
                'permission_module' => 'kit_maternidade',
                'view_permission' => 'kit_maternidade.visualizar',
            ],
            'aluguel-social' => [
                'label' => 'Aluguel Social',
                'permission_module' => 'aluguel_social',
                'view_permission' => 'aluguel_social.visualizar',
            ],
            'beneficios-eventuais' => [
                'label' => 'Benefícios Eventuais',
                'permission_module' => 'beneficios_eventuais',
                'view_permission' => 'beneficios_eventuais.visualizar',
            ],
            'comida-mesa' => [
                'label' => 'Coari Comida na Mesa',
                'permission_module' => 'comida_mesa',
                'view_permission' => 'comida_mesa.visualizar',
            ],
            'primeiro-emprego' => [
                'label' => 'Coari Meu Primeiro Emprego',
                'permission_module' => 'primeiro_emprego',
                'view_permission' => 'primeiro_emprego.visualizar',
            ],
        ];
    }

    /** @return array<string,string> permission module => public module key */
    public static function permissionModuleIndex(): array
    {
        $index = [];
        foreach (self::operational() as $moduleKey => $definition) {
            $index[$definition['permission_module']] = $moduleKey;
        }
        return $index;
    }
}
