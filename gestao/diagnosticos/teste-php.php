<?php

declare(strict_types=1);

header('Content-Type: text/plain; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow', true);

echo "PHP carregou\n";
echo "Versao: " . PHP_VERSION . "\n";
echo "Diretorio atual: " . __DIR__ . "\n";
