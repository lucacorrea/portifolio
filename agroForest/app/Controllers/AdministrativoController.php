<?php
declare(strict_types=1);

class AdministrativoController extends Controller
{
    public function dashboard(): void
    {
        require APP_PATH . '/Views/administrativo/dashboard.php';
    }

    public function protocolosRecebidos(): void
    {
        require APP_PATH . '/Views/administrativo/protocolosRecebidos.php';
    }

    public function protocoloVisualizar(): void
    {
        require APP_PATH . '/Views/administrativo/protocoloVisualizar.php';
    }

    public function orcamentos(): void
    {
        require APP_PATH . '/Views/administrativo/orcamentos.php';
    }

    public function orcamentoCadastrar(): void
    {
        require APP_PATH . '/Views/administrativo/orcamentoCadastrar.php';
    }

    public function orcamentoEditar(): void
    {
        require APP_PATH . '/Views/administrativo/orcamentoEditar.php';
    }

    public function orcamentoVisualizar(): void
    {
        require APP_PATH . '/Views/administrativo/orcamentoVisualizar.php';
    }

    public function clientes(): void
    {
        require APP_PATH . '/Views/administrativo/clientes.php';
    }

    public function clienteCadastrar(): void
    {
        require APP_PATH . '/Views/administrativo/clienteCadastrar.php';
    }

    public function clienteEditar(): void
    {
        require APP_PATH . '/Views/administrativo/clienteEditar.php';
    }

    public function clienteVisualizar(): void
    {
        require APP_PATH . '/Views/administrativo/clienteVisualizar.php';
    }

    public function documentos(): void
    {
        require APP_PATH . '/Views/administrativo/documentos.php';
    }

    public function documentoVisualizar(): void
    {
        require APP_PATH . '/Views/administrativo/documentoVisualizar.php';
    }

    public function pendencias(): void
    {
        require APP_PATH . '/Views/administrativo/pendencias.php';
    }

    public function pendenciaCadastrar(): void
    {
        require APP_PATH . '/Views/administrativo/pendenciaCadastrar.php';
    }

    public function pendenciaEditar(): void
    {
        require APP_PATH . '/Views/administrativo/pendenciaEditar.php';
    }

    public function pendenciaVisualizar(): void
    {
        require APP_PATH . '/Views/administrativo/pendenciaVisualizar.php';
    }

    public function relatorios(): void
    {
        require APP_PATH . '/Views/administrativo/relatorios.php';
    }

    public function configuracoes(): void
    {
        require APP_PATH . '/Views/administrativo/configurações.php';
    }
}
