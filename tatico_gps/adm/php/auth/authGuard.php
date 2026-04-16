<?php
declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| CONFIGURAÇÃO
|--------------------------------------------------------------------------
| Ajuste a URL abaixo conforme a raiz do seu sistema.
| Exemplo:
| /index.html
| /tatico/index.html
|--------------------------------------------------------------------------
*/
const AUTH_LOGIN_URL = './index.html';

/*
|--------------------------------------------------------------------------
| INICIA SESSÃO SE AINDA NÃO ESTIVER INICIADA
|--------------------------------------------------------------------------
*/
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

/*
|--------------------------------------------------------------------------
| NÃO FECHAR POR INATIVIDADE
|--------------------------------------------------------------------------
| Aqui propositalmente NÃO existe controle de tempo parado.
| A sessão só será encerrada manualmente no logout.php.
|--------------------------------------------------------------------------
*/

/*
|--------------------------------------------------------------------------
| FUNÇÃO AUXILIAR PARA REDIRECIONAR
|--------------------------------------------------------------------------
*/
function auth_redirecionar(string $destino): never
{
    header('Location: ' . $destino);
    exit;
}

/*
|--------------------------------------------------------------------------
| FUNÇÃO PARA EXIGIR LOGIN
|--------------------------------------------------------------------------
*/
function exigir_login(): void
{
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    $logado    = $_SESSION['logado'] ?? false;

    if (!$usuarioId || $logado !== true) {
        auth_redirecionar(AUTH_LOGIN_URL);
    }
}

/*
|--------------------------------------------------------------------------
| FUNÇÃO OPCIONAL: IMPEDIR ACESSO À TELA DE LOGIN QUANDO JÁ ESTIVER LOGADO
|--------------------------------------------------------------------------
*/
function impedir_login_se_logado(string $destino = './dashboard.php'): void
{
    $usuarioId = $_SESSION['usuario_id'] ?? null;
    $logado    = $_SESSION['logado'] ?? false;

    if ($usuarioId && $logado === true) {
        auth_redirecionar($destino);
    }
}

/*
|--------------------------------------------------------------------------
| FUNÇÃO OPCIONAL PARA PEGAR DADOS DO USUÁRIO LOGADO
|--------------------------------------------------------------------------
*/
function usuario_logado(): array
{
    return [
        'usuario_id' => $_SESSION['usuario_id'] ?? null,
        'username'   => $_SESSION['username'] ?? null,
        'email'      => $_SESSION['email'] ?? null,
        'logado'     => $_SESSION['logado'] ?? false,
    ];
}
