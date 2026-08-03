<?php
declare(strict_types=1);

namespace Sigesp\Modules\Shared\Controllers;

use Sigesp\Core\{Controller, Request, Response};

final class ModuleController extends Controller
{
    public function page(Request $request, string $module, string $screen = 'index'): Response
    {
        $this->authorize('atletas.visualizar');
        $viewScreen = ['novo' => 'form', 'perfil' => 'show'][$screen] ?? $screen;
        return $this->render($module . '/' . $viewScreen);
    }
}
