<?php

use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;

define('LARAVEL_START', microtime(true));

/*
|--------------------------------------------------------------------------
| Check If The Application Is Under Maintenance
|--------------------------------------------------------------------------
|
| If the application is in maintenance / demo mode via the "down" command
| we will load this file so that any pre-rendered content can be shown
| instead of starting the framework, which could cause an exception.
|
*/

if (file_exists($maintenance = __DIR__.'/../storage/framework/maintenance.php')) {
    require $maintenance;
}

/*
|--------------------------------------------------------------------------
| Register The Auto Loader
|--------------------------------------------------------------------------
|
| Composer provides a convenient, automatically generated class loader for
| this application. We just need to utilize it! We'll simply require it
| into the script here so we don't need to manually load our classes.
|
*/

$autoload = __DIR__.'/../vendor/autoload.php';
if (!is_file($autoload)) {
    error_log('[PCCT] Dependencias Laravel ausentes: vendor/autoload.php (verifique deploy da main)');
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    header('Cache-Control: no-store');
    echo '<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
        . '<title>PCCT em manutenção</title><style>body{font:16px/1.6 system-ui,sans-serif;min-height:90vh;display:grid;place-items:center;background:#f2f7f4;color:#174434;margin:0;padding:22px}'
        . 'main{max-width:520px;background:white;padding:32px;border-radius:15px;box-shadow:0 8px 26px #0001}h1{margin-top:0;font-size:1.65rem}</style></head>'
        . '<body><main><h1>PCCT temporariamente indisponível</h1><p>O sistema está passando por uma atualização. Tente novamente após a conclusão da implantação.</p></main></body></html>';
    exit;
}

require $autoload;

/*
|--------------------------------------------------------------------------
| Run The Application
|--------------------------------------------------------------------------
|
| Once we have the application, we can handle the incoming request using
| the application's HTTP kernel. Then, we will send the response back
| to this client's browser, allowing them to enjoy our application.
|
*/

$app = require_once __DIR__.'/../bootstrap/app.php';

$kernel = $app->make(Kernel::class);

$response = $kernel->handle(
    $request = Request::capture()
)->send();

$kernel->terminate($request, $response);
