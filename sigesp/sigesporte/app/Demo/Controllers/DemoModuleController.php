<?php
declare(strict_types=1);

namespace Sigesp\Demo\Controllers;

use Sigesp\Core\{Request, Response, View};
use Sigesp\Demo\DemoData;

final class DemoModuleController
{
    private const VIEWS = [
        'responsaveis' => ['index' => 'responsaveis/index', 'novo' => 'responsaveis/form', 'perfil' => 'responsaveis/show'],
        'documentos' => ['index' => 'documentos/index', 'analise' => 'documentos/analise', 'tipos' => 'documentos/tipos', 'perfil' => 'documentos/show'],
        'carteiras-digitais' => ['index' => 'carteiras/index'],
        'modalidades' => ['index' => 'modalidades/index', 'novo' => 'modalidades/form', 'perfil' => 'modalidades/show'],
        'categorias' => ['index' => 'categorias/index', 'novo' => 'categorias/form'],
        'equipes' => ['index' => 'equipes/index', 'novo' => 'equipes/form', 'perfil' => 'equipes/show'],
        'treinos' => ['index' => 'treinos/index', 'novo' => 'treinos/form', 'perfil' => 'treinos/show'],
        'frequencias' => ['index' => 'frequencias/index', 'registrar' => 'frequencias/registrar', 'relatorio' => 'frequencias/relatorio'],
        'avaliacoes' => ['index' => 'avaliacoes/index', 'novo' => 'avaliacoes/form', 'perfil' => 'avaliacoes/show'],
        'eventos' => ['index' => 'eventos/index', 'novo' => 'eventos/form', 'perfil' => 'eventos/show'],
        'competicoes' => ['index' => 'competicoes/index', 'novo' => 'competicoes/form', 'perfil' => 'competicoes/show'],
        'inscricoes' => ['index' => 'inscricoes/index', 'novo' => 'inscricoes/form'],
        'resultados' => ['index' => 'resultados/index', 'novo' => 'resultados/form'],
        'beneficios' => ['index' => 'beneficios/index', 'novo' => 'beneficios/form', 'perfil' => 'beneficios/show'],
        'espacos-esportivos' => ['index' => 'espacos-esportivos/index', 'novo' => 'espacos-esportivos/form', 'perfil' => 'espacos-esportivos/show'],
        'reservas' => ['index' => 'reservas/index', 'calendario' => 'reservas/calendario', 'novo' => 'reservas/form'],
        'materiais' => ['index' => 'materiais/index', 'novo' => 'materiais/form', 'perfil' => 'materiais/show', 'movimentacoes' => 'materiais/movimentacoes'],
        'relatorios' => ['index' => 'relatorios/index', 'visualizar' => 'relatorios/visualizar'],
        'usuarios' => ['index' => 'usuarios/index', 'novo' => 'usuarios/form', 'perfil' => 'usuarios/show'],
        'permissoes' => ['index' => 'permissoes/index'],
        'auditoria' => ['index' => 'auditoria/index', 'perfil' => 'auditoria/show'],
        'configuracoes' => ['index' => 'configuracoes/index'],
    ];

    public function page(Request $request, string $module, string $screen = 'index'): Response
    {
        $views = self::VIEWS[$module] ?? self::VIEWS['responsaveis'];
        $view = $views[$screen] ?? $views['index'];
        $page = DemoData::page($module);
        $data = array_merge($page, [
            'page' => $page,
            'screen' => $screen,
            'demoMode' => true,
            'demoUser' => ['nome' => 'Marcos Oliveira', 'perfil' => 'Administrador do Sistema'],
        ]);
        return new Response(View::render($view, $data));
    }

    public function simulate(Request $request, string $module): Response
    {
        $module = isset(self::VIEWS[$module]) ? $module : 'dashboard';
        return Response::redirect('/' . ($module === 'dashboard' ? 'dashboard' : $module) . '?simulated=1');
    }
}
