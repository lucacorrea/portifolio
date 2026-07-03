<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/app/Core/Autoloader.php';

App\Core\Autoloader::register();

use App\Core\Environment;
use App\Core\Storage;

$failures = 0;

function assert_true(bool $condition, string $message): void
{
    global $failures;

    if (!$condition) {
        $failures++;
        echo "FAIL: {$message}" . PHP_EOL;
    }
}

function remove_tree(string $path): void
{
    if (!is_dir($path)) {
        return;
    }

    $items = scandir($path) ?: [];

    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $full = $path . DIRECTORY_SEPARATOR . $item;

        is_dir($full) ? remove_tree($full) : @unlink($full);
    }

    @rmdir($path);
}

$base = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'sigas_storage_test_' . bin2hex(random_bytes(6));
$imageRoot = $base . DIRECTORY_SEPARATOR . 'img';
$documentRoot = $base . DIRECTORY_SEPARATOR . 'document';
$logRoot = $base . DIRECTORY_SEPARATOR . 'logs';
mkdir($imageRoot, 0750, true);
mkdir($documentRoot, 0750, true);
mkdir($logRoot, 0750, true);

$envFile = $base . DIRECTORY_SEPARATOR . '.env';
file_put_contents($envFile, "SIGAS_IMAGE_PATH={$imageRoot}\nSIGAS_DOCUMENT_PATH={$documentRoot}\nSIGAS_LOG_PATH={$logRoot}\n");
Environment::load($envFile);

assert_true(Storage::buildRelativeDirectory(new DateTimeImmutable('2026-07-03')) === '2026/07', 'diretorio anual mensal');
assert_true(Storage::imageRoot() === $imageRoot, 'raiz de imagem configurada');
assert_true(Storage::documentRoot() === $documentRoot, 'raiz de documento configurada');

$filename = Storage::generateRandomFilename('PDF');
assert_true((bool) preg_match('/^[a-f0-9]{32}\.pdf$/', $filename), 'nome aleatorio normalizado');

$invalidExtensionThrown = false;

try {
    Storage::generateRandomFilename('php');
} catch (Throwable) {
    $invalidExtensionThrown = true;
}

assert_true($invalidExtensionThrown, 'extensao invalida bloqueada');

$traversalThrown = false;

try {
    Storage::resolveDocumentPath('../fora.pdf');
} catch (Throwable) {
    $traversalThrown = true;
}

assert_true($traversalThrown, 'path traversal bloqueado');

$outside = $base . DIRECTORY_SEPARATOR . 'fora.pdf';
file_put_contents($outside, 'x');
assert_true(!Storage::isInsideRoot($outside, $documentRoot), 'caminho fora da raiz rejeitado');

$created = $documentRoot . DIRECTORY_SEPARATOR . Storage::buildRelativeDirectory(new DateTimeImmutable('2026-07-03'));
Storage::ensureDirectory($created);
assert_true(is_dir($created), 'cria diretorio anual mensal');

$fileAsDirectory = $base . DIRECTORY_SEPARATOR . 'arquivo';
file_put_contents($fileAsDirectory, 'x');

$notDirectoryThrown = false;

try {
    Storage::ensureDirectory($fileAsDirectory);
} catch (Throwable) {
    $notDirectoryThrown = true;
}

assert_true($notDirectoryThrown, 'arquivo nao e aceito como diretorio');

@unlink($outside);
@unlink($fileAsDirectory);
@unlink($envFile);
remove_tree($base);

echo $failures === 0 ? 'PASS storage-test' . PHP_EOL : "FAILURES: {$failures}" . PHP_EOL;
exit($failures === 0 ? 0 : 1);
