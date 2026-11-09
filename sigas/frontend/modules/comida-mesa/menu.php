<?php

declare(strict_types=1);

$menuEnvironmentKey = 'comida-mesa';

/*
 * O menu mostra somente páginas que o usuário realmente pode utilizar.
 * A autorização de backend continua obrigatória em cada rota/ação.
 */
$menuVisiblePageKeys = ['painel', 'beneficiarios'];

if (cm_can('comida_mesa.cadastrar')) {
    $menuVisiblePageKeys[] = 'nova-inscricao';
}
if (cm_can('comida_mesa.importar') || cm_can('comida_mesa.cadastrar')) {
    $menuVisiblePageKeys[] = 'importar-beneficiarios';
}
if (cm_can('comida_mesa.consultar_cpf')) {
    $menuVisiblePageKeys[] = 'consulta-cpf';
}
if (cm_can('comida_mesa.entregar')) {
    $menuVisiblePageKeys[] = 'registrar-entrega';
}
if (cm_can('comida_mesa.competencias_gerenciar')) {
    $menuVisiblePageKeys[] = 'competencias';
}
if (cm_can('comida_mesa.polos_gerenciar')) {
    $menuVisiblePageKeys[] = 'polos';
}
if (cm_can('comida_mesa.documentos_visualizar') || cm_can('comida_mesa.documentos_enviar')) {
    $menuVisiblePageKeys[] = 'documentos';
}
if (cm_can('comida_mesa.historico_visualizar')) {
    $menuVisiblePageKeys[] = 'historico';
}

// Relatórios permanecem disponíveis para quem já possui acesso de visualização ao módulo.
$menuVisiblePageKeys[] = 'relatorios';

require dirname(__DIR__, 2) . '/navigation/module-menu.php';
