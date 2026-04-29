<?php
declare(strict_types=1);

class DonoController extends Controller
{
    public function dashboard(): void
    {
        require APP_PATH . '/Views/dono/dashboard.php';
    }

    public function usuarios(): void
    {
        require APP_PATH . '/Views/dono/usuarios.php';
    }

    public function usuarioCadastrar(): void
    {
        require APP_PATH . '/Views/dono/usuarioCadastrar.php';
    }

    public function usuarioVisualizar(): void
    {
        require APP_PATH . '/Views/dono/usuarioVisualizar.php';
    }

    public function usuarioEditar(): void
    {
        require APP_PATH . '/Views/dono/usuarioEditar.php';
    }

    public function permissoes(): void
    {
        require APP_PATH . '/Views/dono/permissoes.php';
    }

    public function permissaoCadastrar(): void
    {
        require APP_PATH . '/Views/dono/permissaoCadastrar.php';
    }

    public function permissaoVisualizar(): void
    {
        require APP_PATH . '/Views/dono/permissaoVisualizar.php';
    }

    public function permissaoEditar(): void
    {
        require APP_PATH . '/Views/dono/permissaoEditar.php';
    }

    public function tiposServicos(): void
    {
        require APP_PATH . '/Views/dono/tiposServicos.php';
    }

    public function tipoServicoCadastrar(): void
    {
        require APP_PATH . '/Views/dono/tipoServicoCadastrar.php';
    }

    public function tipoServicoVisualizar(): void
    {
        require APP_PATH . '/Views/dono/tipoServicoVisualizar.php';
    }

    public function tipoServicoEditar(): void
    {
        require APP_PATH . '/Views/dono/tipoServicoEditar.php';
    }

    public function relatorios(): void
    {
        require APP_PATH . '/Views/dono/relatorios.php';
    }

    public function configuracoes(): void
    {
        require APP_PATH . '/Views/dono/configuracoes.php';
    }
}
