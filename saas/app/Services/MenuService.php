<?php
namespace App\Services;

class MenuService {
    public static function items(){
        return [
            ['label'=>'Dashboard','route'=>'dashboard','key'=>'dashboard'],
            ['label'=>'Clientes','route'=>'#','key'=>'clientes'],
            ['label'=>'Financeiro','route'=>'#','key'=>'financeiro'],
            ['label'=>'Assinaturas','route'=>'#','key'=>'assinaturas'],
        ];
    }
}
