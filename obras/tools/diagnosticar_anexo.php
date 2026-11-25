<?php
/**
 * OBRAS360 — verifica um anexo pelo terminal sem editar registros.
 * Exemplo: php tools/diagnosticar_anexo.php 1
 * Somente CLI: nunca exponha os nomes/path em HTTP.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit('Indisponível.'); }
require dirname(__DIR__) . '/app/data.php';
$id = filter_var($argv[1] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range'=>1]]);
if (!$id) { fwrite(STDERR, "Uso: php tools/diagnosticar_anexo.php <ID>\n"); exit(2); }
try {
    $st = db()->prepare('SELECT id,module,record_code,original_name,stored_name,size_bytes,deleted_at FROM attachments WHERE id=? LIMIT 1');
    $st->execute([$id]); $row = $st->fetch();
    echo "OBRAS360 - ANEXO {$id}\n";
    if (!$row) { echo "RESULTADO: nenhum registro do anexo no MySQL.\n"; exit(1); }
    echo 'Banco: registro encontrado / módulo=' . $row['module'] . ' / código=' . $row['record_code'] . "\n";
    echo 'Nome exibido: ' . $row['original_name'] . "\n";
    echo 'Tamanho esperado: ' . $row['size_bytes'] . " bytes\n";
    echo 'Removido logicamente: ' . ($row['deleted_at'] === null ? 'não' : 'sim') . "\n";
    if ($row['deleted_at'] !== null) exit(1);
    $record = record_row((string)$row['module'], (string)$row['record_code']);
    echo 'Registro principal ativo: ' . ($record ? 'sim' : 'não') . "\n";
    $name = (string)$row['stored_name'];
    if (!preg_match('/^[a-f0-9]{40}\.(pdf|jpg|jpeg|png|doc|docx)$/iD', $name)) {
        echo "RESULTADO: stored_name não atende ao padrão de segurança.\n"; exit(1);
    }
    $ok = false;
    foreach (obras_upload_dirs() as $candidate) {
        $dir = realpath($candidate);
        echo 'Pasta: ' . $candidate . ' / existe=' . ($dir ? 'sim' : 'não') . "\n";
        if (!$dir) continue;
        $file = realpath($dir . DIRECTORY_SEPARATOR . $name);
        if (!$file || !str_starts_with($file, $dir . DIRECTORY_SEPARATOR) || !is_file($file)) continue;
        $ok = true;
        echo 'Arquivo: presente / legível=' . (is_readable($file) ? 'sim' : 'não') . "\n";
        echo 'Tamanho físico: ' . filesize($file) . " bytes\n";
        echo 'Tamanho igual ao MySQL: ' . (filesize($file) == $row['size_bytes'] ? 'sim' : 'não') . "\n";
    }
    if (!$ok) { echo "RESULTADO: arquivo físico não encontrado. Verifique backups e sincronização da pasta privada.\n"; exit(1); }
    echo "RESULTADO: caminho físico encontrado. Teste o download com usuário autenticado.\n";
} catch (Throwable $e) {
    echo "Falha no diagnóstico. Consulte error_log do PHP.\n";
    error_log('[OBRAS360 DIAG ANEXO] ' . $e->getMessage());
    exit(1);
}
