<?php
/** Testa regras básicas de anexos sem banco, apenas via CLI. */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require dirname(__DIR__) . '/app/data.php';
$temporary = tempnam(sys_get_temp_dir(), 'obras_test_');
if (!$temporary) exit(1);
try {
    // PNG 1x1 válido. Arquivos menores que 30 KiB não devem ser recodificados.
    $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/BqoAAAAASUVORK5CYII=', true);
    if ($png === false || file_put_contents($temporary, $png) === false) exit(1);
    $result = obras_imagem_30kb($temporary, 'png');
    $ok = $result['size'] <= 30 * 1024 && $result['temporary'] === false
        && $result['extension'] === 'png' && hash_file('sha256', $temporary) === hash('sha256', $png);
    echo ($ok ? 'OK' : 'FALHA') . " imagem pequena preservada sem conversão\n";
    echo 'Extensão PHP GD: ' . (extension_loaded('gd') ? 'disponível' : 'indisponível (necessária para fotos grandes)') . "\n";
    exit($ok ? 0 : 1);
} finally { @unlink($temporary); }
